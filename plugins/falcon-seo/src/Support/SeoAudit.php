<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * A site-wide SEO check-up: what to fix, on which page, in order of importance, each with a
 * link straight to the editor. Read-only: it never changes a post.
 *
 * Checks published, indexable posts and pages (up to MAX_POSTS, newest first). Plugins add
 * their own checks through the `falcon_seo_audit_checks` filter (Search Console adds "not in
 * Google's index").
 */
final class SeoAudit
{
    public const MAX_POSTS = 3000;

    /** Types that are never pages of their own (layouts, sections, cards…). */
    private const CONTENT_TYPES_EXCLUDED = ['falcon_content', 'falcon_header', 'falcon_footer', 'falcon_ptb', 'lazy_header', 'lazy_footer'];

    /** The most a kind of issue can cost, when every page has it. */
    private const WEIGHT = ['error' => 30, 'warning' => 15, 'info' => 5];

    /**
     * @return array{score: int, checked: int, checks: list<array{key: string, label: string, severity: string, help: string, items: list<array>}>}
     */
    public static function run(): array
    {
        $posts = self::posts();
        self::$redirects = null;
        $checks = array_values(array_filter([
            self::siteHidden(),
            self::emptyPages($posts),
            self::missingDescriptions($posts),
            self::duplicates($posts, 'title', 'Duplicate titles', 'warning',
                'Two pages with the same title compete with each other and leave searchers guessing which to click. Give each its own.'),
            self::duplicates($posts, 'description', 'Duplicate meta descriptions', 'info',
                'Each page deserves a description of what is on it, not one shared across pages.'),
            self::longTitles($posts),
            self::orphans($posts),
            ...self::redirectProblems($posts),
            self::linksToRedirects($posts),
            ...self::canonicalProblems($posts),
            self::thinContent($posts),
            self::imagesWithoutAlt($posts),
            self::missingFeaturedImage($posts),
            self::noindexed(),
        ]));

        $checks = array_values((array) apply_falcon_filters('falcon_seo_audit_checks', $checks, $posts));

        // Each kind of issue costs up to its weight, in proportion to the share of pages it
        // affects: one page without a description on a 500-page site is not the same as all of
        // them. (A flat cost per finding sank any real site to 0, which says nothing.)
        $total = max(1, count($posts));
        $penalty = 0.0;
        foreach ($checks as $check) {
            $share = min(1, count($check['items']) / $total);
            $penalty += (self::WEIGHT[$check['severity']] ?? 5) * max($share, 0.1);
        }

        return ['score' => max(0, (int) round(100 - $penalty)), 'checked' => count($posts), 'checks' => $checks];
    }

    /**
     * Published, indexable posts with what the checks need: title and description as Google
     * gets them, word count, and the path they live at.
     *
     * @return list<array{id: int, type: string, title: string, seo_title: string, description: string, canonical: string, content: string, words: int, path: string, featured: bool, edit: ?string}>
     */
    public static function posts(): array
    {
        try {
            $rows = DB::table('posts')->where('status', 'published')->whereNull('deleted_at')
                ->whereNotIn('type', self::CONTENT_TYPES_EXCLUDED)
                ->orderByDesc('id')->limit(self::MAX_POSTS)
                ->get(['id', 'title', 'slug', 'type', 'lang_code', 'content', 'excerpt', 'seo_meta', 'featured_image', 'origin_id']);
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $seo = json_decode((string) $row->seo_meta, true) ?: [];
            // Pages kept out of search on purpose: by the editor, or by a plugin (the shop's cart).
            if (($seo['noindex'] ?? '0') == '1' || apply_falcon_filters('falcon_seo_noindex', false, $row)) {
                continue;
            }
            $text = self::text((string) $row->content);
            $seoTitle = trim((string) ($seo['title'] ?? ''));
            $permalink = get_falcon_permalink($row);
            $out[] = [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'lang' => (string) $row->lang_code,
                'title' => (string) $row->title,
                'seo_title' => $seoTitle !== '' ? $seoTitle : (string) $row->title,
                'description' => trim((string) ($seo['description'] ?? '')) ?: trim(strip_tags((string) $row->excerpt)),
                'canonical' => trim((string) ($seo['canonical_url'] ?? '')),
                'content' => (string) $row->content,
                'words' => $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []),
                'url' => $permalink,
                'path' => self::path($permalink),
                'featured' => !empty($row->featured_image),
                'edit' => self::editUrl((int) $row->id, (string) $row->type),
            ];
        }

        return $out;
    }

    /** The whole site noindexed by SEO → Settings: right for a demo, fatal for a live site. */
    private static function siteHidden(): ?array
    {
        if (!SearchVisibility::hidden()) {
            return null;
        }

        return self::check('site_hidden', 'The whole site is hidden from search engines', 'error',
            'SEO → Settings → "Hide this site from search engines" is on, so no page can appear on Google. Right for a demo or staging copy; untick it before launch.',
            [['id' => 0, 'title' => 'Search engine visibility', 'detail' => 'Every page says noindex.',
                'edit' => Route::has('admin.settings.seo') ? route('admin.settings.seo') : null]]);
    }

    /**
     * Published pages with nothing on them. Google files them as "Soft 404": the address
     * answers, but the page looks like an error. Fill them, or unpublish them.
     */
    private static function emptyPages(array $posts): ?array
    {
        // The home, blog and shop pages are drawn by templates: no content of their own is normal.
        $templated = array_map('intval', array_filter(array_merge(
            [get_cms_option('home_page_id'), get_cms_option('blog_page_id')],
            (array) apply_falcon_filters('falcon_template_page_ids', [])
        )));
        $items = [];
        foreach ($posts as $p) {
            // Builder JSON keeps its words in keys text() does not read; a long body is not empty.
            if (in_array($p['id'], $templated, true) || mb_strlen(trim(strip_tags($p['content']))) > 300) {
                continue;
            }
            if ($p['words'] < 20 && !self::hasMedia($p['content']) && !self::hasOwnTemplate($p['path'])) {
                $items[] = self::item($p, $p['words'] === 0 ? 'No content at all.' : 'Only '.$p['words'].($p['words'] === 1 ? ' word.' : ' words.'));
            }
        }

        return self::check('empty_page', 'Empty pages (Google: "Soft 404")', 'error',
            'These pages are published but show nothing, so Google treats them as missing pages ("Soft 404"). Add content, or switch them to draft and take them out of menus.', $items);
    }

    /** A theme may draw a page from a template named after its slug (themes/…/pricing.blade.php). */
    private static function hasOwnTemplate(string $path): bool
    {
        $slug = basename($path);

        return preg_match('/^[a-z0-9-]+$/', $slug) === 1 && view()->exists($slug);
    }

    /** A page carried by a form, slider, gallery, video or embed is not empty, even without text. */
    private static function hasMedia(string $content): bool
    {
        return (bool) preg_match('/<(img|video|iframe|form|picture)\b|\[(falcon_[a-z_]+|contact|form|gallery|video|products?)\b|"type"\s*:\s*"(image|video|gallery|form|slider|map|products?)"/i', $content);
    }

    private static function missingDescriptions(array $posts): ?array
    {
        $items = [];
        foreach ($posts as $p) {
            if ($p['description'] === '') {
                $items[] = self::item($p, 'Google writes its own snippet from the page text.');
            }
        }

        return self::check('missing_description', 'Missing meta descriptions', 'warning',
            'Without one, Google picks a snippet itself, often a menu or a stray sentence. A clear description of the page usually earns more clicks.', $items);
    }

    private static function duplicates(array $posts, string $field, string $label, string $severity, string $help): ?array
    {
        $groups = [];
        foreach ($posts as $p) {
            $value = mb_strtolower(trim($field === 'title' ? $p['seo_title'] : $p['description']));
            if ($value !== '') {
                // Translations legitimately share a title in different languages only by accident; compare within a language.
                $groups[$p['lang'].'|'.$value][] = $p;
            }
        }
        $items = [];
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $p) {
                $others = implode(', ', array_map(fn ($o) => $o['title'], array_filter($group, fn ($o) => $o['id'] !== $p['id'])));
                $items[] = self::item($p, 'Same as: '.$others);
            }
        }

        return self::check('duplicate_'.$field, $label, $severity, $help, $items);
    }

    private static function longTitles(array $posts): ?array
    {
        $items = [];
        foreach ($posts as $p) {
            $length = mb_strlen($p['seo_title']);
            if ($length > 65) {
                $items[] = self::item($p, $length.' characters; Google shows about 60.');
            }
        }

        return self::check('long_title', 'Titles likely to be cut off', 'info',
            'Google shows about 600 pixels (around 60 characters) of a title. Put the words that matter first.', $items);
    }

    /** Pages nothing on the site links to: no other content and no menu points at them. */
    private static function orphans(array $posts): ?array
    {
        $linked = [];
        try {
            $sources = DB::table('posts')->where('status', 'published')->whereNull('deleted_at')->pluck('content', 'id');
            foreach ($sources as $id => $content) {
                // Builder content is JSON ("linkUrl":"https:\/\/…") as well as HTML (href="…") and shortcodes (link_url="…").
                foreach (self::links((string) $content) as $url) {
                    $linked[self::path($url)][] = (int) $id;
                }
            }
            foreach (DB::table('navigation_menu_items')->get(['url', 'type', 'object_id']) as $item) {
                if ($item->object_id && in_array($item->type, ['post', 'page', 'product'], true)) {
                    $linked['#id:'.$item->object_id][] = 0;
                }
                if ($item->url) {
                    $linked[self::path($item->url)][] = 0;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        $home = (int) get_cms_option('home_page_id', 0);
        $items = [];
        foreach ($posts as $p) {
            if ($p['id'] === $home || $p['path'] === '/' || isset($linked['#id:'.$p['id']])) {
                continue;
            }
            $from = array_diff($linked[$p['path']] ?? [], [$p['id']]);
            if ($from === []) {
                $items[] = self::item($p, 'No link to it from other pages or menus.');
            }
        }

        return self::check('orphan', 'Pages nothing links to', 'warning',
            'Search engines find and rank pages through links. Link to these from related posts or a menu so they are not left on their own.', $items);
    }

    /**
     * The redirects themselves: loops (an address that never arrives), chains (two hops or more
     * where one would do), and live pages a redirect sends visitors away from (the redirect
     * answers first, so the page cannot be reached).
     *
     * @return list<?array>
     */
    private static function redirectProblems(array $posts): array
    {
        $map = self::redirectMap();
        if ($map === []) {
            return [];
        }

        $loops = [];
        $chains = [];
        $looped = [];
        foreach ($map as $from => $to) {
            if (isset($looped[$from])) {
                continue;
            }
            $hops = [$from];
            $at = $to;
            while ($at !== null && isset($map[$at]) && !in_array($at, $hops, true) && count($hops) < 10) {
                $hops[] = $at;
                $at = $map[$at];
            }
            if ($at !== null && in_array($at, $hops, true)) {
                // One finding per circle, not one per address in it.
                $looped += array_fill_keys($hops, true);
                $loops[] = self::redirectItem($from, implode(' → ', array_merge($hops, [$at])).': never arrives anywhere.');
            } elseif (count($hops) > 1) {
                $chains[] = self::redirectItem($from, implode(' → ', array_merge($hops, [$at ?? '…'])).': point '.$from.' straight at '.($at ?? 'the last one').'.');
            }
        }

        $hidden = [];
        foreach ($posts as $p) {
            if ($p['path'] !== '/' && isset($map[$p['path']])) {
                $hidden[] = self::item($p, 'A redirect sends '.$p['path'].' to '.($map[$p['path']] ?? 'another site').', so this page cannot be reached. Remove the redirect or change the page\'s address.');
            }
        }

        return [
            self::check('redirect_loop', 'Redirect loops', 'error',
                'These addresses redirect in a circle: browsers give up with "too many redirects" and search engines drop them. Edit or remove one of the redirects.', $loops),
            self::check('redirected_page', 'Pages hidden by a redirect', 'error',
                'A redirect answers before the page does, so visitors and search engines never see these pages.', $hidden),
            self::check('redirect_chain', 'Redirect chains', 'warning',
                'Each extra hop slows visitors down and loses search engines along the way. Send the first address straight to the last.', $chains),
        ];
    }

    /** Content that links to an address that redirects: link to where it ends up instead. */
    private static function linksToRedirects(array $posts): ?array
    {
        $map = self::redirectMap();
        if ($map === []) {
            return null;
        }
        $items = [];
        foreach ($posts as $p) {
            $found = [];
            foreach (self::links($p['content']) as $url) {
                $path = self::internalPath($url);
                if ($path !== null && $path !== $p['path'] && array_key_exists($path, $map)) {
                    $found[$path] = $map[$path];
                }
            }
            if ($found !== []) {
                $list = array_map(fn ($from, $to) => $from.' (now '.($to ?? 'another site').')', array_keys($found), $found);
                $items[] = self::item($p, 'Links to '.implode(', ', array_slice($list, 0, 3)).(count($list) > 3 ? ' and '.(count($list) - 3).' more' : '').'.');
            }
        }

        return self::check('links_to_redirects', 'Links to redirected addresses', 'warning',
            'Each of these links costs a redirect on every click and crawl. Point them at the page\'s current address.', $items);
    }

    /**
     * A canonical URL set by hand that sends search engines somewhere it should not: a broken
     * address, one that redirects, a page hidden from search. Pages that deliberately name
     * another page as the original are listed too, as a reminder that they will not be shown.
     *
     * @return list<?array>
     */
    private static function canonicalProblems(array $posts): array
    {
        $map = null;
        $noindexed = null;
        $broken = [];
        $elsewhere = [];
        foreach ($posts as $p) {
            $canonical = $p['canonical'];
            if ($canonical === '') {
                continue;
            }
            if (!preg_match('#^https?://[^\s/]+\.[^\s]+#i', $canonical) && !str_starts_with($canonical, '/')) {
                $broken[] = self::item($p, '"'.$canonical.'" is not a full address (https://…).');

                continue;
            }
            $path = self::internalPath($canonical);
            if ($path === null) {
                $elsewhere[] = self::item($p, 'Names '.$canonical.' (another site) as the original.');

                continue;
            }
            if ($path === $p['path']) {
                continue;
            }
            $map ??= self::redirectMap();
            $noindexed ??= self::noindexedPaths();
            if (array_key_exists($path, $map)) {
                $broken[] = self::item($p, 'Its canonical '.$path.' redirects to '.($map[$path] ?? 'another site').'. Name the final address.');
            } elseif (isset($noindexed[$path])) {
                $broken[] = self::item($p, 'Its canonical '.$path.' is hidden from search (noindex), so neither page is shown.');
            } else {
                $elsewhere[] = self::item($p, 'Names '.$path.' as the original, so search engines show that page instead of this one.');
            }
        }

        return [
            self::check('canonical_broken', 'Canonical URLs that point the wrong way', 'error',
                'The canonical URL tells search engines which address to show. These point at an address that redirects, is hidden from search, or is not an address at all. Fix or clear them in the page\'s SEO settings.', $broken),
            self::check('canonical_elsewhere', 'Pages that name another page as the original', 'info',
                'Right for copies and syndicated posts: search engines show the other page instead. A mistake for a page that should rank on its own.', $elsewhere),
        ];
    }

    private static function thinContent(array $posts): ?array
    {
        $items = [];
        foreach ($posts as $p) {
            if ($p['type'] === 'post' && $p['words'] < 300) {
                $items[] = self::item($p, $p['words'].' words.');
            }
        }

        return self::check('thin_content', 'Short posts', 'info',
            'Posts under 300 words rarely answer a search fully. Expand them, or merge several short ones into one.', $items);
    }

    private static function imagesWithoutAlt(array $posts): ?array
    {
        $items = [];
        foreach ($posts as $p) {
            if (preg_match_all('/<img\b(?![^>]*\balt=["\'][^"\']+)[^>]*>/i', $p['content'], $m) && count($m[0]) > 0) {
                $items[] = self::item($p, count($m[0]).' image(s) without alt text.');
            }
        }
        try {
            $library = DB::table('media')->where('mime_type', 'like', 'image/%')
                ->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''))->count();
        } catch (Throwable $e) {
            $library = 0;
        }
        if ($library > 0) {
            $items[] = ['id' => 0, 'title' => 'Media library', 'detail' => $library.' image(s) have no alt text; it is used wherever an element has none of its own.',
                'edit' => Route::has('admin.media.index') ? route('admin.media.index') : null];
        }

        return self::check('missing_alt', 'Images without alt text', 'warning',
            'Alt text describes an image to screen readers and to Google Images. Describe what the image shows.', $items);
    }

    private static function missingFeaturedImage(array $posts): ?array
    {
        $items = [];
        foreach ($posts as $p) {
            if ($p['type'] === 'post' && !$p['featured']) {
                $items[] = self::item($p, 'Shared links and Google Discover show no picture.');
            }
        }

        return self::check('missing_featured', 'Posts without a featured image', 'info',
            'A featured image (1200px wide or more) is what social shares and Google Discover show.', $items);
    }

    private static function noindexed(): ?array
    {
        $items = [];
        try {
            foreach (DB::table('posts')->where('status', 'published')->whereNull('deleted_at')->where('seo_meta', 'like', '%"noindex":"1"%')->limit(200)->get(['id', 'title', 'type']) as $row) {
                $items[] = ['id' => (int) $row->id, 'title' => (string) $row->title, 'detail' => 'Hidden from search engines on purpose?', 'edit' => self::editUrl((int) $row->id, (string) $row->type)];
            }
        } catch (Throwable $e) {
        }

        return self::check('noindex', 'Pages hidden from search (noindex)', 'info',
            'These ask search engines not to list them. That is right for thank-you or private pages, a mistake anywhere else.', $items);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @var array<string, ?string>|null */
    private static ?array $redirects = null;

    /**
     * Every redirect as source path => target path on this site (null: another site).
     *
     * @return array<string, ?string>
     */
    private static function redirectMap(): array
    {
        if (self::$redirects !== null) {
            return self::$redirects;
        }
        $map = [];
        try {
            foreach (DB::table('cms_redirects')->limit(5000)->get(['old_url', 'new_url']) as $row) {
                $map[self::path((string) $row->old_url)] = self::internalPath((string) $row->new_url);
            }
        } catch (Throwable $e) {
            return [];
        }

        return self::$redirects = $map;
    }

    /** Paths of published pages hidden from search (noindex). */
    private static function noindexedPaths(): array
    {
        $paths = [];
        try {
            foreach (DB::table('posts')->where('status', 'published')->whereNull('deleted_at')->where('seo_meta', 'like', '%"noindex":"1"%')
                ->limit(500)->get(['id', 'slug', 'type', 'lang_code', 'origin_id']) as $row) {
                $paths[self::path(get_falcon_permalink($row))] = true;
            }
        } catch (Throwable $e) {
        }

        return $paths;
    }

    /** The links in content: HTML, shortcodes and the builder's JSON. */
    private static function links(string $content): array
    {
        $content = str_replace('\\/', '/', $content);
        preg_match_all('#(?:href|url|link_url|link|linkUrl)"?\s*[=:]\s*\\\\?["\']([^"\'\\\\]+)#i', $content, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** A URL's path when it is on this site (relative, or this host with or without www), else null. */
    private static function internalPath(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^(mailto|tel|javascript|data):#i', $url)) {
            return null;
        }
        $host = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $own = parse_url((string) config('app.url'), PHP_URL_HOST);
            $bare = fn ($h) => preg_replace('/^www\./', '', mb_strtolower((string) $h));
            if ($bare($host) !== $bare($own)) {
                return null;
            }
        } elseif (!str_starts_with($url, '/')) {
            return null;
        }

        return self::path($url);
    }

    /** Where a redirect's source is edited: the Redirects screen, searched for it. */
    private static function redirectItem(string $from, string $detail): array
    {
        return ['id' => 0, 'title' => $from, 'detail' => $detail,
            'edit' => Route::has('admin.redirects.index') ? route('admin.redirects.index', ['s' => $from]) : null, 'url' => null];
    }

    private static function check(string $key, string $label, string $severity, string $help, array $items): ?array
    {
        return $items === [] ? null : compact('key', 'label', 'severity', 'help', 'items');
    }

    private static function item(array $p, string $detail): array
    {
        return ['id' => $p['id'], 'title' => $p['title'], 'detail' => $detail, 'edit' => $p['edit'], 'url' => $p['url'] ?? null];
    }

    /** Text a reader sees: tags, builder shortcodes and JSON markup removed. */
    private static function text(string $content): string
    {
        return falcon_content_text($content);
    }

    private static function path(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        return '/'.trim(mb_strtolower(rawurldecode(is_string($path) ? $path : '')), '/');
    }

    /** Where a post or page is edited. */
    public static function editUrl(int $id, string $type): ?string
    {
        $name = $type === 'page' && Route::has('admin.pages.edit') ? 'admin.pages.edit' : 'admin.posts.edit';

        return Route::has($name) ? route($name, $id) : null;
    }
}
