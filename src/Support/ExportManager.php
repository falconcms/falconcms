<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Comment;
use FalconCms\Core\Models\CustomTaxonomy;
use FalconCms\Core\Models\Media;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Models\TaxonomyTerm;
use Illuminate\Support\Str;

/**
 * Builds the list of exportable "sources" dynamically from whatever the CMS
 * currently has registered (post types, taxonomies, media, …) and turns a
 * selection into a WordPress-compatible WXR (eXtended RSS) export file.
 *
 * The source list is extensible: any feature can register its own exportable
 * source through the `falcon_export_sources` filter, and — if it is exportable —
 * it will automatically appear on the Export screen without touching this class.
 *
 *   add_falcon_filter('falcon_export_sources', function (array $sources) {
 *       $sources[] = [
 *           'key'   => 'events',
 *           'label' => 'Events',
 *           'group' => 'Content',
 *           'count' => \App\Models\Event::count(),
 *           // optional: return an array of <item>…</item> WXR strings
 *           'items' => fn () => app(\App\Export\EventExporter::class)->wxrItems(),
 *       ];
 *       return $sources;
 *   });
 */
class ExportManager
{
    /** Map FalconCMS post status → WordPress status. */
    protected const STATUS_MAP = [
        'published' => 'publish',
        'publish'   => 'publish',
        'draft'     => 'draft',
        'pending'   => 'pending',
        'private'   => 'private',
        'trash'     => 'trash',
        'scheduled' => 'future',
        'future'    => 'future',
    ];

    /**
     * The dynamic list of things that can be exported.
     *
     * Every entry: ['key','label','group','count', (optional) 'items' callable].
     * Keys are stable identifiers used by the form (e.g. "post_type:post").
     */
    public static function sources(): array
    {
        $sources = [];

        // ── Content: one entry per registered post type (built-in + custom) ──
        $postTypes = PostType::query()
            ->when(self::columnExists('post_types', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(self::columnExists('post_types', 'is_builtin'), fn ($q) => $q->orderByDesc('is_builtin'))
            ->orderBy('name')
            ->get();

        foreach ($postTypes as $pt) {
            $sources[] = [
                'key'   => 'post_type:' . $pt->slug,
                'label' => $pt->name,
                'group' => 'Content',
                'count' => Post::where('type', $pt->slug)->count(),
            ];
        }

        // ── Taxonomies: built-in Categories & Tags + custom taxonomies ──
        $sources[] = [
            'key'   => 'taxonomy:category',
            'label' => 'Categories',
            'group' => 'Taxonomies',
            'count' => Category::count(),
        ];
        $sources[] = [
            'key'   => 'taxonomy:post_tag',
            'label' => 'Tags',
            'group' => 'Taxonomies',
            'count' => Tag::count(),
        ];

        foreach (CustomTaxonomy::query()
            ->when(self::columnExists('custom_taxonomies', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get() as $tax) {
            $sources[] = [
                'key'   => 'taxonomy:' . $tax->slug,
                'label' => $tax->name,
                'group' => 'Taxonomies',
                'count' => TaxonomyTerm::where('taxonomy_slug', $tax->slug)->count(),
            ];
        }

        // ── Media library ──
        $sources[] = [
            'key'   => 'media',
            'label' => 'Media',
            'group' => 'Library',
            'count' => Media::count(),
        ];

        /**
         * Let any feature contribute its own exportable source. Future features
         * that are exportable simply hook in here and appear automatically.
         */
        $sources = apply_falcon_filters('falcon_export_sources', $sources);

        // Normalise + drop malformed entries.
        return array_values(array_filter(array_map(function ($s) {
            if (empty($s['key']) || empty($s['label'])) return null;
            return [
                'key'   => (string) $s['key'],
                'label' => (string) $s['label'],
                'group' => $s['group'] ?? 'Content',
                'count' => isset($s['count']) ? (int) $s['count'] : null,
                'items' => $s['items'] ?? null,
            ];
        }, $sources)));
    }

    /**
     * Build the WXR XML for the given selection.
     *
     * @param string|array $selection  'all', a single source key, or an array of keys.
     */
    public static function generate($selection): string
    {
        $sources = self::sources();
        $byKey   = [];
        foreach ($sources as $s) $byKey[$s['key']] = $s;

        if ($selection === 'all' || $selection === ['all']) {
            $selected = $sources;
        } else {
            $keys     = (array) $selection;
            $selected = array_values(array_filter(array_map(fn ($k) => $byKey[$k] ?? null, $keys)));
        }

        $itemsXml = [];
        $termsXml = [];
        $authors  = [];

        foreach ($selected as $src) {
            // Filter-provided custom exporter takes priority.
            if (is_callable($src['items'] ?? null)) {
                foreach ((array) call_user_func($src['items']) as $frag) {
                    $itemsXml[] = (string) $frag;
                }
                continue;
            }

            $key = $src['key'];

            if (Str::startsWith($key, 'post_type:')) {
                $type = Str::after($key, 'post_type:');
                self::collectPostType($type, $itemsXml, $authors);
            } elseif (Str::startsWith($key, 'taxonomy:')) {
                self::collectTaxonomy(Str::after($key, 'taxonomy:'), $termsXml);
            } elseif ($key === 'media') {
                self::collectMedia($itemsXml, $authors);
            }
        }

        return self::wrap($termsXml, $itemsXml, $authors);
    }

    /** A short, filesystem-safe name for the download. */
    public static function filename($selection): string
    {
        $site = Str::slug(config('app.name', 'falconcms')) ?: 'falconcms';
        if (is_string($selection) && $selection !== 'all') {
            $site .= '.' . Str::slug(str_replace(':', '-', $selection));
        } elseif (is_array($selection) && count($selection) === 1) {
            $site .= '.' . Str::slug(str_replace(':', '-', $selection[0]));
        } else {
            $site .= '.all';
        }
        return $site . '.' . now()->format('Y-m-d') . '.xml';
    }

    // ───────────────────────────── collectors ─────────────────────────────

    protected static function collectPostType(string $type, array &$items, array &$authors): void
    {
        Post::where('type', $type)->with('user')->orderBy('id')->chunk(200, function ($posts) use ($type, &$items, &$authors) {
            foreach ($posts as $post) {
                if ($post->user) $authors[$post->user->id] = $post->user;
                $items[] = self::postItem($post, $type);
            }
        });
    }

    protected static function collectMedia(array &$items, array &$authors): void
    {
        Media::orderBy('id')->chunk(200, function ($all) use (&$items, &$authors) {
            foreach ($all as $m) {
                $user = self::resolveUser($m->user_id ?? null);
                if ($user) $authors[$user->id] = $user;
                $items[] = self::mediaItem($m, $user);
            }
        });
    }

    protected static function resolveUser($id)
    {
        static $cache = [];
        if (!$id) return null;
        if (!array_key_exists($id, $cache)) {
            $model = config('auth.providers.users.model', \App\Models\User::class);
            $cache[$id] = class_exists($model) ? $model::find($id) : null;
        }
        return $cache[$id];
    }

    protected static function collectTaxonomy(string $taxonomy, array &$terms): void
    {
        if ($taxonomy === 'category') {
            foreach (Category::orderBy('id')->get() as $c) {
                $terms[] = self::categoryTerm($c);
            }
            return;
        }
        if ($taxonomy === 'post_tag') {
            foreach (Tag::orderBy('id')->get() as $t) {
                $terms[] = self::tagTerm($t);
            }
            return;
        }
        foreach (TaxonomyTerm::where('taxonomy_slug', $taxonomy)->orderBy('id')->get() as $term) {
            $terms[] = self::customTerm($taxonomy, $term);
        }
    }

    // ───────────────────────────── item builders ──────────────────────────

    protected static function postItem(Post $post, string $type): string
    {
        $login   = self::authorLogin($post->user);
        $date    = ($post->published_at ?: $post->created_at);
        $dateStr = $date ? $date->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $gmt     = $date ? $date->copy()->setTimezone('UTC')->format('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
        $status  = self::STATUS_MAP[strtolower((string) $post->status)] ?? 'publish';
        $link    = self::guessPostLink($post);

        $xml  = "\t<item>\n";
        $xml .= "\t\t<title>" . self::esc($post->title) . "</title>\n";
        $xml .= "\t\t<link>" . self::esc($link) . "</link>\n";
        $xml .= "\t\t<pubDate>" . self::esc(($date ?: now())->toRfc2822String()) . "</pubDate>\n";
        $xml .= "\t\t<dc:creator>" . self::cdata($login) . "</dc:creator>\n";
        $xml .= "\t\t<guid isPermaLink=\"false\">" . self::esc($link) . "</guid>\n";
        $xml .= "\t\t<description></description>\n";
        $xml .= "\t\t<content:encoded>" . self::cdata((string) $post->content) . "</content:encoded>\n";
        $xml .= "\t\t<excerpt:encoded>" . self::cdata((string) $post->excerpt) . "</excerpt:encoded>\n";
        $xml .= "\t\t<wp:post_id>" . (int) $post->id . "</wp:post_id>\n";
        $xml .= "\t\t<wp:post_date>" . self::cdata($dateStr) . "</wp:post_date>\n";
        $xml .= "\t\t<wp:post_date_gmt>" . self::cdata($gmt) . "</wp:post_date_gmt>\n";
        $xml .= "\t\t<wp:comment_status>" . self::cdata($post->comment_status === 'closed' ? 'closed' : 'open') . "</wp:comment_status>\n";
        $xml .= "\t\t<wp:ping_status>" . self::cdata('closed') . "</wp:ping_status>\n";
        $xml .= "\t\t<wp:post_name>" . self::cdata((string) $post->slug) . "</wp:post_name>\n";
        $xml .= "\t\t<wp:status>" . self::cdata($status) . "</wp:status>\n";
        $xml .= "\t\t<wp:post_parent>" . (int) ($post->post_parent ?? 0) . "</wp:post_parent>\n";
        $xml .= "\t\t<wp:menu_order>" . (int) ($post->menu_order ?? 0) . "</wp:menu_order>\n";
        $xml .= "\t\t<wp:post_type>" . self::cdata($type) . "</wp:post_type>\n";
        $xml .= "\t\t<wp:post_password>" . self::cdata((string) ($post->password ?? '')) . "</wp:post_password>\n";
        $xml .= "\t\t<wp:is_sticky>" . (int) ($post->is_sticky ?? 0) . "</wp:is_sticky>\n";

        // Terms attached to the post.
        if (method_exists($post, 'categories')) {
            foreach ($post->categories as $c) {
                $xml .= "\t\t<category domain=\"category\" nicename=\"" . self::esc($c->slug) . "\">" . self::cdata($c->name) . "</category>\n";
            }
        }
        if (method_exists($post, 'tags')) {
            foreach ($post->tags as $t) {
                $xml .= "\t\t<category domain=\"post_tag\" nicename=\"" . self::esc($t->slug) . "\">" . self::cdata($t->name) . "</category>\n";
            }
        }
        if (method_exists($post, 'taxonomyTerms')) {
            foreach ($post->taxonomyTerms as $term) {
                $dom = $term->taxonomy_slug ?: 'custom';
                $xml .= "\t\t<category domain=\"" . self::esc($dom) . "\" nicename=\"" . self::esc($term->slug) . "\">" . self::cdata($term->name) . "</category>\n";
            }
        }

        // Post meta we want to preserve (FalconCMS-specific, prefixed so they round-trip).
        foreach (self::postMeta($post) as $k => $v) {
            if ($v === null || $v === '') continue;
            $xml .= "\t\t<wp:postmeta>\n";
            $xml .= "\t\t\t<wp:meta_key>" . self::cdata($k) . "</wp:meta_key>\n";
            $xml .= "\t\t\t<wp:meta_value>" . self::cdata(is_scalar($v) ? (string) $v : json_encode($v)) . "</wp:meta_value>\n";
            $xml .= "\t\t</wp:postmeta>\n";
        }

        // Comments.
        if (method_exists($post, 'comments')) {
            foreach ($post->comments as $comment) {
                $xml .= self::commentBlock($comment);
            }
        }

        $xml .= "\t</item>\n";
        return $xml;
    }

    protected static function postMeta(Post $post): array
    {
        $meta = [
            '_falcon_editor_type' => $post->editor_type,
            '_falcon_template'    => $post->template,
            '_falcon_lang_code'   => $post->lang_code,
        ];
        if (!empty($post->featured_image)) {
            $meta['_thumbnail_path'] = $post->featured_image;
        }
        if (!empty($post->seo_meta)) {
            $meta['_falcon_seo'] = $post->seo_meta;
        }
        if (!empty($post->gallery)) {
            $meta['_falcon_gallery'] = $post->gallery;
        }
        return $meta;
    }

    protected static function commentBlock(Comment $comment): string
    {
        $date    = $comment->created_at ?: now();
        $xml  = "\t\t<wp:comment>\n";
        $xml .= "\t\t\t<wp:comment_id>" . (int) $comment->id . "</wp:comment_id>\n";
        $xml .= "\t\t\t<wp:comment_author>" . self::cdata((string) ($comment->author_name ?? $comment->name ?? '')) . "</wp:comment_author>\n";
        $xml .= "\t\t\t<wp:comment_author_email>" . self::cdata((string) ($comment->author_email ?? $comment->email ?? '')) . "</wp:comment_author_email>\n";
        $xml .= "\t\t\t<wp:comment_author_url>" . self::cdata((string) ($comment->author_url ?? '')) . "</wp:comment_author_url>\n";
        $xml .= "\t\t\t<wp:comment_date>" . self::cdata($date->format('Y-m-d H:i:s')) . "</wp:comment_date>\n";
        $xml .= "\t\t\t<wp:comment_content>" . self::cdata((string) ($comment->content ?? $comment->body ?? '')) . "</wp:comment_content>\n";
        $approved = ($comment->status ?? 'approved');
        $xml .= "\t\t\t<wp:comment_approved>" . self::cdata($approved === 'approved' || $approved === '1' ? '1' : '0') . "</wp:comment_approved>\n";
        $xml .= "\t\t\t<wp:comment_parent>" . (int) ($comment->parent_id ?? 0) . "</wp:comment_parent>\n";
        $xml .= "\t\t</wp:comment>\n";
        return $xml;
    }

    protected static function mediaItem(Media $m, $user = null): string
    {
        $url    = method_exists($m, 'getUrlAttribute') ? $m->url : asset('storage/' . $m->path);
        $date   = $m->created_at ?: now();
        $login  = self::authorLogin($user);
        $title  = $m->title ?: $m->filename;

        $xml  = "\t<item>\n";
        $xml .= "\t\t<title>" . self::esc($title) . "</title>\n";
        $xml .= "\t\t<link>" . self::esc($url) . "</link>\n";
        $xml .= "\t\t<pubDate>" . self::esc($date->toRfc2822String()) . "</pubDate>\n";
        $xml .= "\t\t<dc:creator>" . self::cdata($login) . "</dc:creator>\n";
        $xml .= "\t\t<guid isPermaLink=\"false\">" . self::esc($url) . "</guid>\n";
        $xml .= "\t\t<description></description>\n";
        $xml .= "\t\t<content:encoded>" . self::cdata((string) $m->description) . "</content:encoded>\n";
        $xml .= "\t\t<excerpt:encoded>" . self::cdata((string) $m->caption) . "</excerpt:encoded>\n";
        $xml .= "\t\t<wp:post_id>" . (int) $m->id . "</wp:post_id>\n";
        $xml .= "\t\t<wp:post_date>" . self::cdata($date->format('Y-m-d H:i:s')) . "</wp:post_date>\n";
        $xml .= "\t\t<wp:post_name>" . self::cdata(Str::slug(pathinfo($m->filename, PATHINFO_FILENAME))) . "</wp:post_name>\n";
        $xml .= "\t\t<wp:status>" . self::cdata('inherit') . "</wp:status>\n";
        $xml .= "\t\t<wp:post_parent>0</wp:post_parent>\n";
        $xml .= "\t\t<wp:menu_order>0</wp:menu_order>\n";
        $xml .= "\t\t<wp:post_type>" . self::cdata('attachment') . "</wp:post_type>\n";
        $xml .= "\t\t<wp:attachment_url>" . self::cdata($url) . "</wp:attachment_url>\n";
        if (!empty($m->alt_text)) {
            $xml .= "\t\t<wp:postmeta>\n\t\t\t<wp:meta_key>" . self::cdata('_wp_attachment_image_alt')
                  . "</wp:meta_key>\n\t\t\t<wp:meta_value>" . self::cdata($m->alt_text) . "</wp:meta_value>\n\t\t</wp:postmeta>\n";
        }
        $xml .= "\t</item>\n";
        return $xml;
    }

    protected static function categoryTerm(Category $c): string
    {
        $xml  = "\t<wp:category>\n";
        $xml .= "\t\t<wp:term_id>" . (int) $c->id . "</wp:term_id>\n";
        $xml .= "\t\t<wp:category_nicename>" . self::cdata((string) $c->slug) . "</wp:category_nicename>\n";
        $parentSlug = ($c->parent_id && $c->parent) ? $c->parent->slug : '';
        $xml .= "\t\t<wp:category_parent>" . self::cdata((string) $parentSlug) . "</wp:category_parent>\n";
        $xml .= "\t\t<wp:cat_name>" . self::cdata((string) $c->name) . "</wp:cat_name>\n";
        if (!empty($c->description)) {
            $xml .= "\t\t<wp:category_description>" . self::cdata($c->description) . "</wp:category_description>\n";
        }
        $xml .= "\t</wp:category>\n";
        return $xml;
    }

    protected static function tagTerm(Tag $t): string
    {
        $xml  = "\t<wp:tag>\n";
        $xml .= "\t\t<wp:term_id>" . (int) $t->id . "</wp:term_id>\n";
        $xml .= "\t\t<wp:tag_slug>" . self::cdata((string) $t->slug) . "</wp:tag_slug>\n";
        $xml .= "\t\t<wp:tag_name>" . self::cdata((string) $t->name) . "</wp:tag_name>\n";
        if (!empty($t->description)) {
            $xml .= "\t\t<wp:tag_description>" . self::cdata($t->description) . "</wp:tag_description>\n";
        }
        $xml .= "\t</wp:tag>\n";
        return $xml;
    }

    protected static function customTerm(string $taxonomy, TaxonomyTerm $term): string
    {
        $xml  = "\t<wp:term>\n";
        $xml .= "\t\t<wp:term_id>" . (int) $term->id . "</wp:term_id>\n";
        $xml .= "\t\t<wp:term_taxonomy>" . self::cdata($taxonomy) . "</wp:term_taxonomy>\n";
        $xml .= "\t\t<wp:term_slug>" . self::cdata((string) $term->slug) . "</wp:term_slug>\n";
        $parentSlug = ($term->parent_id && $term->parent) ? $term->parent->slug : '';
        $xml .= "\t\t<wp:term_parent>" . self::cdata((string) $parentSlug) . "</wp:term_parent>\n";
        $xml .= "\t\t<wp:term_name>" . self::cdata((string) $term->name) . "</wp:term_name>\n";
        if (!empty($term->description)) {
            $xml .= "\t\t<wp:term_description>" . self::cdata($term->description) . "</wp:term_description>\n";
        }
        $xml .= "\t</wp:term>\n";
        return $xml;
    }

    protected static function authorBlock($user): string
    {
        $xml  = "\t<wp:author>\n";
        $xml .= "\t\t<wp:author_id>" . (int) $user->id . "</wp:author_id>\n";
        $xml .= "\t\t<wp:author_login>" . self::cdata(self::authorLogin($user)) . "</wp:author_login>\n";
        $xml .= "\t\t<wp:author_email>" . self::cdata((string) ($user->email ?? '')) . "</wp:author_email>\n";
        $xml .= "\t\t<wp:author_display_name>" . self::cdata((string) ($user->name ?? self::authorLogin($user))) . "</wp:author_display_name>\n";
        $xml .= "\t\t<wp:author_first_name>" . self::cdata((string) ($user->first_name ?? '')) . "</wp:author_first_name>\n";
        $xml .= "\t\t<wp:author_last_name>" . self::cdata((string) ($user->last_name ?? '')) . "</wp:author_last_name>\n";
        $xml .= "\t</wp:author>\n";
        return $xml;
    }

    // ───────────────────────────── envelope ───────────────────────────────

    protected static function wrap(array $terms, array $items, array $authors): string
    {
        $siteUrl = rtrim(url('/'), '/');
        $now     = now();

        $out  = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        $out .= "<!-- This is a FalconCMS export of your site, generated by the Export tool. -->\n";
        $out .= "<!-- It is a WordPress-compatible WXR file and can be re-imported via Tools → WordPress Import. -->\n";
        $out .= "<rss version=\"2.0\"\n"
              . "\txmlns:excerpt=\"http://wordpress.org/export/1.2/excerpt/\"\n"
              . "\txmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n"
              . "\txmlns:wfw=\"http://wellformedweb.org/CommentAPI/\"\n"
              . "\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n"
              . "\txmlns:wp=\"http://wordpress.org/export/1.2/\">\n";
        $out .= "<channel>\n";
        $out .= "\t<title>" . self::esc(config('app.name', 'FalconCMS')) . "</title>\n";
        $out .= "\t<link>" . self::esc($siteUrl) . "</link>\n";
        $out .= "\t<description>" . self::esc((string) get_cms_option('site_tagline', '')) . "</description>\n";
        $out .= "\t<pubDate>" . self::esc($now->toRfc2822String()) . "</pubDate>\n";
        $out .= "\t<language>" . self::esc(str_replace('_', '-', app()->getLocale())) . "</language>\n";
        $out .= "\t<wp:wxr_version>1.2</wp:wxr_version>\n";
        $out .= "\t<wp:base_site_url>" . self::esc($siteUrl) . "</wp:base_site_url>\n";
        $out .= "\t<wp:base_blog_url>" . self::esc($siteUrl) . "</wp:base_blog_url>\n";
        $out .= "\t<generator>FalconCMS/" . self::esc(self::version()) . "</generator>\n";

        foreach ($authors as $user) {
            $out .= self::authorBlock($user);
        }
        foreach ($terms as $t) {
            $out .= $t;
        }
        foreach ($items as $i) {
            $out .= $i;
        }

        $out .= "</channel>\n";
        $out .= "</rss>\n";
        return $out;
    }

    // ───────────────────────────── helpers ────────────────────────────────

    protected static function authorLogin($user): string
    {
        if (!$user) return 'admin';
        $base = $user->username ?? $user->slug ?? $user->name ?? ('user-' . $user->id);
        return Str::slug($base) ?: ('user-' . $user->id);
    }

    protected static function guessPostLink(Post $post): string
    {
        $base = rtrim(url('/'), '/');
        if ($post->type === 'page') return $base . '/' . ltrim((string) $post->slug, '/');
        if ($post->type === 'post') return $base . '/blog/' . ltrim((string) $post->slug, '/');
        return $base . '/' . $post->type . '/' . ltrim((string) $post->slug, '/');
    }

    protected static function version(): string
    {
        $path = dirname(__DIR__, 2) . '/version.json';
        if (is_file($path)) {
            $j = json_decode((string) file_get_contents($path), true);
            if (!empty($j['version'])) return (string) $j['version'];
        }
        return 'dev';
    }

    protected static function columnExists(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function esc($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected static function cdata($value): string
    {
        // Close/re-open CDATA around any literal "]]>" to keep it valid.
        $value = str_replace(']]>', ']]]]><![CDATA[>', (string) $value);
        return '<![CDATA[' . $value . ']]>';
    }
}
