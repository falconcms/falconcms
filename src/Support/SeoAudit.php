<?php

namespace FalconCms\Core\Support;

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

    private const WEIGHT = ['error' => 6, 'warning' => 3, 'info' => 1];

    /**
     * @return array{score: int, checked: int, checks: list<array{key: string, label: string, severity: string, help: string, items: list<array>}>}
     */
    public static function run(): array
    {
        $posts = self::posts();
        $checks = array_values(array_filter([
            self::missingDescriptions($posts),
            self::duplicates($posts, 'title', 'Duplicate titles', 'warning',
                'Two pages with the same title compete with each other and leave searchers guessing which to click. Give each its own.'),
            self::duplicates($posts, 'description', 'Duplicate meta descriptions', 'info',
                'Each page deserves a description of what is on it, not one shared across pages.'),
            self::longTitles($posts),
            self::orphans($posts),
            self::thinContent($posts),
            self::imagesWithoutAlt($posts),
            self::missingFeaturedImage($posts),
            self::noindexed(),
        ]));

        $checks = array_values((array) apply_falcon_filters('falcon_seo_audit_checks', $checks, $posts));

        $penalty = 0;
        foreach ($checks as $check) {
            $penalty += min(25, (self::WEIGHT[$check['severity']] ?? 1) * count($check['items']));
        }

        return ['score' => max(0, 100 - $penalty), 'checked' => count($posts), 'checks' => $checks];
    }

    /**
     * Published, indexable posts with what the checks need: title and description as Google
     * gets them, word count, and the path they live at.
     *
     * @return list<array{id: int, type: string, title: string, seo_title: string, description: string, content: string, words: int, path: string, featured: bool, edit: ?string}>
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
            if (($seo['noindex'] ?? '0') == '1') {
                continue;
            }
            $text = self::text((string) $row->content);
            $seoTitle = trim((string) ($seo['title'] ?? ''));
            $out[] = [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'lang' => (string) $row->lang_code,
                'title' => (string) $row->title,
                'seo_title' => $seoTitle !== '' ? $seoTitle : (string) $row->title,
                'description' => trim((string) ($seo['description'] ?? '')) ?: trim(strip_tags((string) $row->excerpt)),
                'content' => (string) $row->content,
                'words' => $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []),
                'path' => self::path(get_falcon_permalink($row)),
                'featured' => !empty($row->featured_image),
                'edit' => self::editUrl((int) $row->id, (string) $row->type),
            ];
        }

        return $out;
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
                if (preg_match_all('#(?:href|url|link_url|link)\s*=\s*\\\\?["\']([^"\'\\\\]+)#i', (string) $content, $m)) {
                    foreach ($m[1] as $url) {
                        $linked[self::path($url)][] = (int) $id;
                    }
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

    private static function check(string $key, string $label, string $severity, string $help, array $items): ?array
    {
        return $items === [] ? null : compact('key', 'label', 'severity', 'help', 'items');
    }

    private static function item(array $p, string $detail): array
    {
        return ['id' => $p['id'], 'title' => $p['title'], 'detail' => $detail, 'edit' => $p['edit']];
    }

    /** Text a reader sees: tags, builder shortcodes and JSON markup removed. */
    private static function text(string $content): string
    {
        if (str_starts_with(ltrim($content), '{') || str_starts_with(ltrim($content), '[{')) {
            preg_match_all('/"(?:text|content|title|html)"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $content, $m);
            $content = implode(' ', array_map(fn ($s) => json_decode('"'.$s.'"') ?? $s, $m[1]));
        }
        $content = preg_replace('/\[[^\]]*\]/', ' ', $content) ?? $content;

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function path(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        return '/'.trim(mb_strtolower(rawurldecode(is_string($path) ? $path : '')), '/');
    }

    private static function editUrl(int $id, string $type): ?string
    {
        $name = $type === 'page' && Route::has('admin.pages.edit') ? 'admin.pages.edit' : 'admin.posts.edit';

        return Route::has($name) ? route($name, $id) : null;
    }
}
