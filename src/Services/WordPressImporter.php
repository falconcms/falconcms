<?php

namespace FalconCms\Core\Services;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Tag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a WordPress export (WXR — the XML from WP Tools → Export) into Lazy CMS.
 *
 * Design:
 *   - parse(string $xml): pure, no DB — turns WXR into normalized arrays (easy to unit-test).
 *   - import(array $parsed): writes Categories, Tags, Posts and Pages and returns a summary.
 *
 * Imported content is stored as rich (HTML) content; opening it in the page builder
 * wraps it into a Text Block, so nothing is lost.
 */
class WordPressImporter
{
    /** WP status -> Lazy status. */
    private const STATUS_MAP = [
        'publish' => 'published',
        'future'  => 'scheduled',
        'draft'   => 'draft',
        'pending' => 'draft',
        'private' => 'draft',
    ];

    // =====================================================================
    // PARSE  (pure — no database)
    // =====================================================================

    /**
     * Parse a WXR XML string into normalized arrays.
     *
     * @return array{site:array,authors:array,categories:array,tags:array,attachments:array,items:array}
     */
    public static function parse(string $xml): array
    {
        $out = ['site' => [], 'authors' => [], 'categories' => [], 'tags' => [], 'attachments' => [], 'items' => []];

        $prev = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if ($root === false || !isset($root->channel)) {
            return $out;
        }

        $channel = $root->channel;
        $ns = $root->getDocNamespaces(true);
        $wpNs   = $ns['wp']      ?? 'http://wordpress.org/export/1.2/';
        $dcNs   = $ns['dc']      ?? 'http://purl.org/dc/elements/1.1/';
        $cNs    = $ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
        $excNs  = $ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';

        $out['site'] = [
            'title'   => (string) ($channel->title ?? ''),
            'link'    => (string) ($channel->link ?? ''),
            'baseUrl' => (string) ($channel->children($wpNs)->base_site_url ?? ''),
        ];

        // Authors
        foreach ($channel->children($wpNs)->author as $a) {
            $out['authors'][] = [
                'login' => (string) $a->author_login,
                'email' => (string) $a->author_email,
                'name'  => (string) $a->author_display_name,
            ];
        }

        // Category & tag definitions (from the channel)
        foreach ($channel->children($wpNs)->category as $c) {
            $out['categories'][] = [
                'slug'   => (string) $c->category_nicename,
                'name'   => (string) $c->cat_name,
                'parent' => (string) $c->category_parent, // parent slug or ''
            ];
        }
        foreach ($channel->children($wpNs)->tag as $t) {
            $out['tags'][] = [
                'slug' => (string) $t->tag_slug,
                'name' => (string) $t->tag_name,
            ];
        }

        // Items (posts, pages, attachments, …)
        foreach ($channel->item as $item) {
            $wp = $item->children($wpNs);
            $type = (string) $wp->post_type;

            // Attachments: remember their URL keyed by WP post id (for featured images)
            if ($type === 'attachment') {
                $out['attachments'][(int) $wp->post_id] = (string) $wp->attachment_url;
                continue;
            }
            // Skip non-content types
            if (in_array($type, ['nav_menu_item', 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'wp_navigation'], true)) {
                continue;
            }

            $cats = [];
            $tags = [];
            foreach ($item->category as $cat) {
                $domain   = (string) ($cat['domain'] ?? '');
                $nicename = (string) ($cat['nicename'] ?? '');
                $label    = (string) $cat;
                if ($domain === 'post_tag') {
                    $tags[] = ['slug' => $nicename ?: Str::slug($label), 'name' => $label];
                } elseif ($domain === 'category' || $domain === '') {
                    $cats[] = ['slug' => $nicename ?: Str::slug($label), 'name' => $label];
                }
            }

            $meta = [];
            foreach ($wp->postmeta as $pm) {
                $meta[(string) $pm->meta_key] = (string) $pm->meta_value;
            }

            $out['items'][] = [
                'wp_id'        => (int) $wp->post_id,
                'type'         => $type,                                   // post | page | <cpt>
                'title'        => (string) $item->title,
                'slug'         => (string) $wp->post_name,
                'status'       => (string) $wp->status,
                'content'      => (string) $item->children($cNs)->encoded,
                'excerpt'      => (string) $item->children($excNs)->encoded,
                'author_login' => (string) $item->children($dcNs)->creator,
                'date'         => (string) $wp->post_date,
                'date_gmt'     => (string) $wp->post_date_gmt,
                'parent'       => (int) $wp->post_parent,
                'menu_order'   => (int) $wp->menu_order,
                'thumbnail_id' => isset($meta['_thumbnail_id']) ? (int) $meta['_thumbnail_id'] : null,
                'categories'   => $cats,
                'tags'         => $tags,
            ];
        }

        return $out;
    }

    // =====================================================================
    // IMPORT  (writes to the database)
    // =====================================================================

    /**
     * @param  array  $parsed   Output of parse()
     * @param  array  $opts     ['user_id' => int, 'lang' => string, 'import_pages' => bool]
     * @return array  summary counts
     */
    public function import(array $parsed, array $opts = []): array
    {
        $userId = $opts['user_id'] ?? (auth()->id() ?? optional(DB::table('users')->first())->id);
        $lang   = $opts['lang'] ?? (function_exists('app') ? app()->getLocale() : 'en');
        $importPages = $opts['import_pages'] ?? true;

        $summary = [
            'categories' => 0, 'tags' => 0, 'posts' => 0, 'pages' => 0,
            'cpt' => 0, 'skipped' => 0, 'errors' => [],
        ];

        // 1) Categories (two pass for parents)
        $catIdBySlug = [];
        foreach ($parsed['categories'] ?? [] as $c) {
            if (empty($c['slug'])) continue;
            $cat = Category::firstOrCreate(
                ['slug' => $c['slug'], 'lang_code' => $lang],
                ['name' => $c['name'] ?: $c['slug']]
            );
            if ($cat->wasRecentlyCreated) $summary['categories']++;
            $catIdBySlug[$c['slug']] = ['id' => $cat->id, 'parent' => $c['parent'] ?? ''];
        }
        foreach ($catIdBySlug as $slug => $info) {
            if (!empty($info['parent']) && isset($catIdBySlug[$info['parent']])) {
                Category::whereKey($info['id'])->update(['parent_id' => $catIdBySlug[$info['parent']]['id']]);
            }
        }

        // 2) Tags
        $tagIdBySlug = [];
        foreach ($parsed['tags'] ?? [] as $t) {
            if (empty($t['slug'])) continue;
            $tag = Tag::firstOrCreate(
                ['slug' => $t['slug'], 'lang_code' => $lang],
                ['name' => $t['name'] ?: $t['slug']]
            );
            if ($tag->wasRecentlyCreated) $summary['tags']++;
            $tagIdBySlug[$t['slug']] = $tag->id;
        }

        $attachments = $parsed['attachments'] ?? [];

        // 3) Items -> posts / pages / cpt
        foreach ($parsed['items'] ?? [] as $it) {
            try {
                $wpType = $it['type'];
                $isPage = $wpType === 'page';
                if ($isPage && !$importPages) { $summary['skipped']++; continue; }

                $type = $isPage ? 'page' : ($wpType === 'post' ? 'post' : $wpType);

                $slug = $it['slug'] ?: Str::slug($it['title'] ?: 'untitled');

                // Idempotent: skip if a same-type post with this slug already exists.
                if (Post::withTrashed()->where('type', $type)->where('slug', $slug)->exists()) {
                    $summary['skipped']++;
                    continue;
                }

                $status = self::STATUS_MAP[$it['status']] ?? 'draft';

                // Date: prefer GMT (UTC); fall back to local.
                $rawDate = $it['date_gmt'] && $it['date_gmt'] !== '0000-00-00 00:00:00'
                    ? $it['date_gmt'] : $it['date'];
                $date = $rawDate ? Carbon::parse($rawDate) : now();

                // A "future" post that's actually in the past becomes published.
                $status = Post::resolveStatusForSchedule($status, $status === 'scheduled' ? $date : null);

                // Featured image: map _thumbnail_id -> attachment URL (kept as remote URL in v1).
                $featured = null;
                if (!empty($it['thumbnail_id']) && isset($attachments[$it['thumbnail_id']])) {
                    $featured = $attachments[$it['thumbnail_id']];
                }

                $post = Post::create([
                    'title'        => $it['title'] ?: '(no title)',
                    'slug'         => $slug,
                    'content'      => $it['content'],
                    'excerpt'      => $it['excerpt'],
                    'type'         => $type,
                    'status'       => $status,
                    'published_at' => $date,
                    'editor_type'  => 'rich',
                    'user_id'      => $userId,
                    'lang_code'    => $lang,
                    'featured_image' => $featured,
                    'menu_order'   => $it['menu_order'] ?? 0,
                ]);

                // Preserve original publish ordering on the front-end.
                DB::table('posts')->where('id', $post->id)->update([
                    'created_at' => $date, 'updated_at' => $date,
                ]);

                // Attach categories / tags (posts only — pages don't use them in WP)
                if (!$isPage) {
                    $catIds = [];
                    foreach ($it['categories'] as $c) {
                        if (isset($catIdBySlug[$c['slug']])) {
                            $catIds[] = $catIdBySlug[$c['slug']]['id'];
                        } elseif (!empty($c['slug'])) {
                            $cat = Category::firstOrCreate(['slug' => $c['slug'], 'lang_code' => $lang], ['name' => $c['name'] ?: $c['slug']]);
                            $catIdBySlug[$c['slug']] = ['id' => $cat->id, 'parent' => ''];
                            $catIds[] = $cat->id;
                        }
                    }
                    if ($catIds) $post->categories()->sync(array_unique($catIds));

                    $tagIds = [];
                    foreach ($it['tags'] as $t) {
                        if (isset($tagIdBySlug[$t['slug']])) {
                            $tagIds[] = $tagIdBySlug[$t['slug']];
                        } elseif (!empty($t['slug'])) {
                            $tag = Tag::firstOrCreate(['slug' => $t['slug'], 'lang_code' => $lang], ['name' => $t['name'] ?: $t['slug']]);
                            $tagIdBySlug[$t['slug']] = $tag->id;
                            $tagIds[] = $tag->id;
                        }
                    }
                    if ($tagIds) $post->tags()->sync(array_unique($tagIds));
                }

                if ($isPage)                 $summary['pages']++;
                elseif ($type === 'post')    $summary['posts']++;
                else                         $summary['cpt']++;
            } catch (\Throwable $e) {
                $summary['errors'][] = ($it['title'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return $summary;
    }

    /** Convenience: parse + import in one call. */
    public function importFromXml(string $xml, array $opts = []): array
    {
        return $this->import(self::parse($xml), $opts);
    }
}
