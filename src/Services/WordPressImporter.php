<?php

namespace FalconCms\Core\Services;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\ProductData;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Models\TaxonomyTerm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Imports a WordPress export (WXR — the XML from WP Tools → Export) into Falcon CMS.
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
        $out = ['site' => [], 'authors' => [], 'categories' => [], 'tags' => [], 'nav_menus' => [], 'attachments' => [], 'items' => []];

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

        // Custom terms — including navigation menus, which FalconCMS exports as
        // `nav_menu` terms carrying a `_falcon_menu` termmeta with the full tree.
        foreach ($channel->children($wpNs)->term as $term) {
            if ((string) $term->term_taxonomy !== 'nav_menu') {
                continue;
            }
            $payload = null;
            foreach ($term->termmeta as $tm) {
                if ((string) $tm->meta_key === '_falcon_menu') {
                    $decoded = json_decode((string) $tm->meta_value, true);
                    if (is_array($decoded)) $payload = $decoded;
                }
            }
            if ($payload) {
                $out['nav_menus'][] = $payload;
            }
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
            $taxTerms = [];
            foreach ($item->category as $cat) {
                $domain   = (string) ($cat['domain'] ?? '');
                $nicename = (string) ($cat['nicename'] ?? '');
                $label    = (string) $cat;
                if ($domain === 'post_tag') {
                    $tags[] = ['slug' => $nicename ?: Str::slug($label), 'name' => $label];
                } elseif ($domain === 'category' || $domain === '') {
                    $cats[] = ['slug' => $nicename ?: Str::slug($label), 'name' => $label];
                } else {
                    // Custom taxonomy attachment (domain = taxonomy slug).
                    $taxTerms[] = ['taxonomy' => $domain, 'slug' => $nicename ?: Str::slug($label), 'name' => $label];
                }
            }

            $meta = [];
            foreach ($wp->postmeta as $pm) {
                $meta[(string) $pm->meta_key] = (string) $pm->meta_value;
            }

            $out['items'][] = [
                'wp_id'          => (int) $wp->post_id,
                'type'           => $type,                                   // post | page | <cpt>
                'title'          => (string) $item->title,
                'slug'           => (string) $wp->post_name,
                'status'         => (string) $wp->status,
                'content'        => (string) $item->children($cNs)->encoded,
                'excerpt'        => (string) $item->children($excNs)->encoded,
                'author_login'   => (string) $item->children($dcNs)->creator,
                'date'           => (string) $wp->post_date,
                'date_gmt'       => (string) $wp->post_date_gmt,
                'parent'         => (int) $wp->post_parent,
                'menu_order'     => (int) $wp->menu_order,
                'thumbnail_id'   => isset($meta['_thumbnail_id']) ? (int) $meta['_thumbnail_id'] : null,
                'thumbnail_path' => $meta['_thumbnail_path'] ?? null,
                'editor_type'    => $meta['_falcon_editor_type'] ?? null,
                'template'       => $meta['_falcon_template'] ?? null,
                'lang_code'      => $meta['_falcon_lang_code'] ?? null,
                'seo_meta'       => self::decodeMeta($meta['_falcon_seo'] ?? null),
                'gallery'        => self::decodeMeta($meta['_falcon_gallery'] ?? null),
                'custom_fields'  => self::decodeMeta($meta['_falcon_custom_fields'] ?? null),
                'product'        => self::decodeMeta($meta['_falcon_product'] ?? null),
                'categories'     => $cats,
                'tags'           => $tags,
                'taxonomies'     => $taxTerms,
            ];
        }

        return $out;
    }

    /**
     * Decode a postmeta value that FalconCMS may have stored as a JSON array
     * (custom fields, product data, gallery, seo). Returns the decoded array,
     * or the original scalar string when it is not JSON, or null when empty.
     */
    private static function decodeMeta($value)
    {
        if ($value === null || $value === '') return null;
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
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
            'cpt' => 0, 'menus' => 0, 'skipped' => 0, 'errors' => [],
        ];

        // 1) Categories (two pass for parents)
        $catIdBySlug = [];
        foreach ($parsed['categories'] ?? [] as $c) {
            if (empty($c['slug'])) continue;
            $cat = Category::firstOrCreate(
                ['slug' => $c['slug'], 'lang_code' => $lang],
                ['name' => $c['name'] ?: $c['slug']]
            );
            if ($cat->wasRecentlyCreated) $summary['categories']++; else $summary['skipped']++;
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
            if ($tag->wasRecentlyCreated) $summary['tags']++; else $summary['skipped']++;
            $tagIdBySlug[$t['slug']] = $tag->id;
        }

        // 2b) Navigation menus (header/footer/custom) with their full item tree.
        foreach ($parsed['nav_menus'] ?? [] as $menuData) {
            try {
                $summary['menus'] += $this->importNavMenu($menuData, $lang) ? 1 : 0;
            } catch (\Throwable $e) {
                $summary['errors'][] = 'Menu ' . ($menuData['name'] ?? '?') . ': ' . $e->getMessage();
            }
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

                // Featured image: prefer FalconCMS's exported path; otherwise map
                // WordPress's _thumbnail_id -> attachment URL (kept as a remote URL).
                $featured = null;
                if (!empty($it['thumbnail_path'])) {
                    $featured = $it['thumbnail_path'];
                } elseif (!empty($it['thumbnail_id']) && isset($attachments[$it['thumbnail_id']])) {
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
                    'editor_type'  => $it['editor_type'] ?: 'rich',
                    'user_id'      => $userId,
                    'lang_code'    => $it['lang_code'] ?: $lang,
                    'featured_image' => $featured,
                    'menu_order'   => $it['menu_order'] ?? 0,
                ]);

                // Preserve original publish ordering on the front-end.
                DB::table('posts')->where('id', $post->id)->update([
                    'created_at' => $date, 'updated_at' => $date,
                ]);

                // Optional Falcon-native fields (template, SEO, gallery) when present.
                $extra = [];
                if (!empty($it['template']) && Schema::hasColumn('posts', 'template')) {
                    $extra['template'] = $it['template'];
                }
                if (!empty($it['seo_meta']) && Schema::hasColumn('posts', 'seo_meta')) {
                    $extra['seo_meta'] = is_array($it['seo_meta']) ? json_encode($it['seo_meta']) : $it['seo_meta'];
                }
                if (!empty($it['gallery']) && Schema::hasColumn('posts', 'gallery')) {
                    $extra['gallery'] = is_array($it['gallery']) ? json_encode($it['gallery']) : $it['gallery'];
                }
                if ($extra) {
                    DB::table('posts')->where('id', $post->id)->update($extra);
                }

                // Restore ACPT custom-field values, product data & custom taxonomy terms.
                $this->restoreCustomFields($post, $it['custom_fields'] ?? null);
                $this->restoreProductData($post, $type, $it['product'] ?? null);
                $this->restoreTaxonomyTerms($post, $it['taxonomies'] ?? [], $lang);

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

    /**
     * Recreate a navigation menu and its full item tree.
     * Idempotent: an existing menu with the same slug + language is left untouched.
     *
     * @return bool  true if a menu was created.
     */
    private function importNavMenu(array $data, string $lang): bool
    {
        if (!Schema::hasTable('navigation_menus')) return false;

        $name = (string) ($data['name'] ?? 'Imported Menu');
        $slug = (string) ($data['slug'] ?? Str::slug($name) ?: 'menu');
        $menuLang = $data['lang_code'] ?? $lang;

        $existing = NavigationMenu::where('slug', $slug)
            ->when(Schema::hasColumn('navigation_menus', 'lang_code'), fn ($q) => $q->where('lang_code', $menuLang))
            ->first();
        if ($existing) return false;

        $menu = NavigationMenu::create([
            'name'      => $name,
            'slug'      => $slug,
            'location'  => $data['location'] ?? null,
            'lang_code' => $menuLang,
            'is_header' => (int) ($data['is_header'] ?? 0),
            'is_footer' => (int) ($data['is_footer'] ?? 0),
        ]);

        // Two-pass: create items, then remap parent ids from old -> new.
        $idMap = [];
        $rows  = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($rows as $row) {
            $item = NavigationMenuItem::create([
                'navigation_menu_id' => $menu->id,
                'parent_id'          => null,
                'title'              => (string) ($row['title'] ?? ''),
                'url'                => (string) ($row['url'] ?? ''),
                'type'               => (string) ($row['type'] ?? 'custom'),
                'object_id'          => $row['object_id'] ?? null,
                'target'             => (string) ($row['target'] ?? ''),
                'classes'            => (string) ($row['classes'] ?? ''),
                'icon'               => (string) ($row['icon'] ?? ''),
                'show_only_icon'     => (int) ($row['show_only_icon'] ?? 0),
                'order'              => (int) ($row['order'] ?? 0),
                'mega_menu_id'       => $row['mega_menu_id'] ?? null,
            ]);
            if (isset($row['id'])) $idMap[(int) $row['id']] = $item->id;
        }
        foreach ($rows as $row) {
            if (empty($row['parent_id']) || !isset($idMap[(int) $row['id']], $idMap[(int) $row['parent_id']])) {
                continue;
            }
            NavigationMenuItem::whereKey($idMap[(int) $row['id']])
                ->update(['parent_id' => $idMap[(int) $row['parent_id']]]);
        }

        return true;
    }

    /** Restore ACPT / custom-field values, keyed by field slug (custom_fields.name). */
    private function restoreCustomFields(Post $post, $fields): void
    {
        if (empty($fields) || !is_array($fields) || !Schema::hasTable('post_custom_field_values')) return;

        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') continue;
            $fieldId = DB::table('custom_fields')->where('name', $name)->value('id');
            if (!$fieldId) continue;
            DB::table('post_custom_field_values')->updateOrInsert(
                ['post_id' => $post->id, 'field_id' => $fieldId],
                ['value' => is_array($value) ? json_encode($value) : (string) $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /** Restore the shop product row (+ variations) for product posts. */
    private function restoreProductData(Post $post, string $type, $product): void
    {
        if ($type !== 'product' || empty($product) || !is_array($product) || !Schema::hasTable('shop_products')) return;

        $variations = $product['variations'] ?? null;
        unset($product['variations']);

        $row = ProductData::updateOrCreate(
            ['post_id' => $post->id],
            array_merge($product, ['post_id' => $post->id])
        );

        if (is_array($variations) && Schema::hasTable('shop_product_variations')) {
            foreach ($variations as $v) {
                if (!is_array($v)) continue;
                DB::table('shop_product_variations')->insert(array_merge($v, [
                    'product_id' => $row->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /** Attach custom-taxonomy terms, creating any that don't yet exist. */
    private function restoreTaxonomyTerms(Post $post, array $terms, string $lang): void
    {
        if (empty($terms) || !Schema::hasTable('taxonomy_terms') || !method_exists($post, 'taxonomyTerms')) return;

        $ids = [];
        foreach ($terms as $t) {
            if (empty($t['slug']) || empty($t['taxonomy'])) continue;
            $term = TaxonomyTerm::firstOrCreate(
                ['taxonomy_slug' => $t['taxonomy'], 'slug' => $t['slug']],
                ['name' => $t['name'] ?: $t['slug'], 'lang_code' => $lang]
            );
            $ids[] = $term->id;
        }
        if ($ids) $post->taxonomyTerms()->syncWithoutDetaching(array_unique($ids));
    }

    /** Convenience: parse + import in one call. */
    public function importFromXml(string $xml, array $opts = []): array
    {
        return $this->import(self::parse($xml), $opts);
    }
}
