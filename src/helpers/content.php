<?php

/**
 * Posts, the loop, permalinks, taxonomies and revisions.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\ProductCategory;
use FalconCms\Core\Models\TaxonomyTerm;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

if (!function_exists('get_falcon_post_content')) {
    function get_falcon_post_content($content)
    {
        if (empty($content)) {
            return '';
        }
        // Check if it's builder shortcode format
        if (is_string($content) && BuilderShortcodeConverter::isBuilderShortcode($content)) {
            $content = BuilderShortcodeConverter::shortcodesToJson($content);
        }

        try {
            $layout = is_string($content) ? json_decode($content, true) : $content;

            if (!is_array($layout)) {
                // Classic / non-builder HTML: strip <script>, on* handlers and
                // javascript: URLs before output (builder layouts are already
                // sanitised per-element by the builder renderer).
                return do_falcon_shortcode(falcon_sanitize_html((string) $content));
            }

            $data = ['layout' => $layout];
            // Expose current post context so dynamic sources (feature image, author, etc.) —
            // including dynamic backgrounds on containers/columns — resolve to the viewed post.
            // Guard against recursion: _falcon_layout_post_context() computes $postContent by
            // calling get_falcon_post_content($post->content) again, so only add the context at the
            // top level — otherwise a post whose content is rendered while it is the current
            // post (e.g. the Home page inside a footer section) loops forever.
            static $ctxDepth = 0;
            $cp = view()->getShared()['current_post'] ?? null;
            if ($cp && $ctxDepth === 0 && function_exists('_falcon_layout_post_context')) {
                $ctxDepth++;
                try {
                    $data += _falcon_layout_post_context($cp);
                } finally {
                    $ctxDepth--;
                }
            }

            $rendered = view('falcon-cms::frontend.builder.render', $data)->render();

            return do_falcon_shortcode($rendered);
        } catch (Exception $e) {
            Log::error('Falcon Builder Error: '.$e->getMessage());

            return do_falcon_shortcode(falcon_sanitize_html((string) $content));
        }
    }
}

if (!function_exists('falcon_html_to_text')) {
    /**
     * Convert rendered HTML to clean plain text. Unlike strip_tags(), this first
     * removes <script>/<style>/<noscript> blocks *including their contents*, so
     * builder-injected CSS/JS never leaks out as visible text.
     */
    function falcon_html_to_text($html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}

if (!function_exists('falcon_visit_page')) {
    /**
     * Human-friendly page label for an analytics visit URL.
     * Strips the scheme + host (works for raw-IP visits too) and shows just the path;
     * for the homepage (root) it shows the domain the visit came in on instead of a bare "/".
     *
     * That is the visit's own host, not APP_URL's: a site reached under more than one domain
     * (a new domain while APP_URL still names the old one, say) would otherwise label every
     * homepage visit with a domain nobody used.
     */
    function falcon_visit_page($url)
    {
        $path = preg_replace('#^https?://[^/?\#]+#i', '', (string) $url);
        if (str_starts_with($path, '?')) {
            $path = '/'.$path; // the homepage with a query string: "/?author=1", not "?author=1"
        }
        if ($path === '' || $path === '/') {
            $host = parse_url((string) $url, PHP_URL_HOST) ?: parse_url((string) config('app.url'), PHP_URL_HOST);
            if (empty($host) && function_exists('request')) {
                try {
                    $host = request()->getHost();
                } catch (Throwable $e) {
                    $host = '';
                }
            }

            return $host ?: '/';
        }

        return $path;
    }
}

if (!function_exists('the_falcon_post_content')) {
    function the_falcon_post_content($content)
    {
        echo get_falcon_post_content($content);
    }
}

if (!function_exists('get_falcon_posts')) {
    function get_falcon_posts($args = [])
    {
        $defaults = [
            'post_type' => 'post',
            'limit' => 10,
            'offset' => 0,
            'order' => 'desc',
            'orderby' => 'created_at',
            'status' => 'published',
            'category' => null,
            'category_exclude' => null,
            'tag' => null,
            'tag_exclude' => null,
            'has_categories' => false,
            'has_tags' => false,
            'author' => null,
            'search' => null,
            'post_id' => null,
            'meta_key' => null,
            'meta_value' => null,
            'taxonomy_slug' => null,
            'taxonomy_include' => null,
            'taxonomy_exclude' => null,
            'paginate' => false,
            'page_name' => 'page',
            'lang' => null,
        ];
        $args = array_merge($defaults, $args);

        if ($args['post_type'] === 'any') {
            $query = Post::query();
        } else {
            $query = Post::where('type', $args['post_type']);
        }

        $lang = $args['lang'] ?: app()->getLocale();
        $query->where('lang_code', $lang);

        if ($args['status']) {
            if (is_array($args['status'])) {
                $query->whereIn('status', $args['status']);
            } else {
                $query->where('status', $args['status']);
            }
        }
        if ($args['category']) {
            $catSlugs = is_array($args['category']) ? $args['category'] : array_filter(explode(',', $args['category']));
            $query->whereHas('categories', function ($q) use ($catSlugs) {
                $q->whereIn('slug', $catSlugs);
            });
        } elseif ($args['has_categories']) {
            if ($args['post_type'] === 'post') {
                $query->has('categories');
            } else {
                $query->has('taxonomyTerms');
            }
        }
        if ($args['category_exclude']) {
            $catExSlugs = is_array($args['category_exclude']) ? $args['category_exclude'] : array_filter(explode(',', $args['category_exclude']));
            if (!empty($catExSlugs)) {
                $query->whereDoesntHave('categories', function ($q) use ($catExSlugs) {
                    $q->whereIn('slug', $catExSlugs);
                });
            }
        }
        if ($args['tag']) {
            $tagSlugs = is_array($args['tag']) ? $args['tag'] : array_filter(explode(',', $args['tag']));
            $query->whereHas('tags', function ($q) use ($tagSlugs) {
                $q->whereIn('slug', $tagSlugs);
            });
        } elseif ($args['has_tags']) {
            if ($args['post_type'] === 'post') {
                $query->has('tags');
            } else {
                $query->has('taxonomyTerms');
            }
        }
        if ($args['tag_exclude']) {
            $tagExSlugs = is_array($args['tag_exclude']) ? $args['tag_exclude'] : array_filter(explode(',', $args['tag_exclude']));
            if (!empty($tagExSlugs)) {
                $query->whereDoesntHave('tags', function ($q) use ($tagExSlugs) {
                    $q->whereIn('slug', $tagExSlugs);
                });
            }
        }
        if ($args['author']) {
            $query->where('user_id', $args['author']);
        }
        if ($args['search']) {
            $query->where('title', 'like', '%'.$args['search'].'%');
        }
        if (!empty($args['post_id'])) {
            $ids = is_array($args['post_id']) ? $args['post_id'] : explode(',', $args['post_id']);
            $query->whereIn('id', array_filter(array_map('intval', $ids)));
        }
        if (!empty($args['taxonomy_slug'])) {
            $taxSlug = $args['taxonomy_slug'];
            if (!empty($args['taxonomy_include'])) {
                $include = is_array($args['taxonomy_include']) ? $args['taxonomy_include'] : explode(',', $args['taxonomy_include']);
                $query->whereHas('taxonomyTerms', function ($q) use ($taxSlug, $include) {
                    $q->where('taxonomy_slug', $taxSlug)->whereIn('slug', array_filter($include));
                });
            } else {
                $query->whereHas('taxonomyTerms', function ($q) use ($taxSlug) {
                    $q->where('taxonomy_slug', $taxSlug);
                });
            }
            if (!empty($args['taxonomy_exclude'])) {
                $exclude = is_array($args['taxonomy_exclude']) ? $args['taxonomy_exclude'] : explode(',', $args['taxonomy_exclude']);
                $query->whereDoesntHave('taxonomyTerms', function ($q) use ($taxSlug, $exclude) {
                    $q->where('taxonomy_slug', $taxSlug)->whereIn('slug', array_filter($exclude));
                });
            }
        }

        if ($args['orderby'] === 'rand') {
            $query->inRandomOrder();
        } else {
            // published_at belongs here: it is the date the admin shows and the one a theme
            // means by "latest". Left out, orderby => 'published_at' silently became created_at,
            // so back-dated and scheduled posts came out in the wrong order with no error.
            $safeOrderby = in_array($args['orderby'], ['created_at', 'updated_at', 'published_at', 'title', 'views', 'menu_order', 'id'])
                ? $args['orderby'] : 'created_at';
            $query->orderBy($safeOrderby, $args['order']);
        }

        // Eager-load what a card or loop row reads for every post — author, categories, tags,
        // taxonomy terms. Without this each post in the loop fetched them on its own (the author,
        // its categories and its terms, one query per post per relation), which on a grid of a
        // dozen posts was dozens of extra queries. Loaded once here for the whole set instead.
        $query->with(['user:id,name', 'categories', 'tags', 'taxonomyTerms']);

        if ($args['paginate']) {
            // withQueryString, because page two has to be the same list as page one. Without
            // it every other parameter — a search term, a filter, a sort — is dropped from the
            // page links, so the reader silently lands on the unfiltered list. The archive
            // controller has always done this; a theme paginating its own loop did not.
            return $query->paginate($args['limit'], ['*'], $args['page_name'] ?? 'page')
                ->withQueryString();
        }

        return $query->limit($args['limit'])->offset((int) $args['offset'])->get();
    }
}

if (!function_exists('the_falcon_pagination')) {
    function the_falcon_pagination($items, $view = null)
    {
        if (!($items instanceof LengthAwarePaginator)) {
            return '';
        }

        return $items->links($view);
    }
}

if (!function_exists('the_falcon_loop')) {
    function the_falcon_loop($args = [], $view = 'falcon-cms::frontend.loop')
    {
        $posts = get_falcon_posts($args);
        echo view($view, ['posts' => $posts])->render();
    }
}

if (!function_exists('get_falcon_excerpt')) {
    function get_falcon_excerpt($post, $limit = 120)
    {
        $content = $post->content ?? '';
        $isBuilder = ($post->editor_type ?? '') === 'builder'
            || (is_string($content) && (str_starts_with(ltrim($content), '[') || str_starts_with(ltrim($content), '{')));

        if (!$isBuilder) {
            return Str::limit(strip_tags($content), $limit);
        }

        try {
            $layout = is_string($content) ? json_decode($content, true) : $content;
            if (!is_array($layout)) {
                return '';
            }

            $textTypes = ['title', 'heading', 'text', 'text_block', 'special_text'];
            $text = '';

            $extractFromElements = function (array $elements) use (&$text, &$limit, &$extractFromElements) {
                foreach ($elements as $el) {
                    $type = $el['type'] ?? '';
                    $s = $el['settings'] ?? [];
                    if (in_array($type, ['title', 'heading'])) {
                        $text .= trim($s['title'] ?? '').' ';
                    } elseif (in_array($type, ['text', 'text_block', 'special_text'])) {
                        $text .= trim(strip_tags($s['content'] ?? '')).' ';
                    } elseif ($type === 'nested-row' && !empty($el['columns'])) {
                        foreach ($el['columns'] as $ncol) {
                            $extractFromElements($ncol['elements'] ?? []);
                            if (strlen($text) > $limit) {
                                return;
                            }
                        }
                    }
                    if (strlen($text) > $limit) {
                        return;
                    }
                }
            };

            foreach ($layout as $container) {
                foreach ($container['columns'] ?? [] as $column) {
                    $extractFromElements($column['elements'] ?? []);
                    if (strlen($text) > $limit) {
                        break 2;
                    }
                }
            }

            return Str::limit(trim($text) ?: '', $limit);
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('get_falcon_post')) {
    function get_falcon_post($slugOrId)
    {
        if (is_numeric($slugOrId)) {
            return Post::find($slugOrId);
        }

        return Post::where('slug', $slugOrId)->where('lang_code', app()->getLocale())->first();
    }
}

if (!function_exists('get_falcon_category_taxonomy')) {
    /**
     * Returns ['type' => 'native'|'product'|'acpt', 'taxonomy_slug' => string|null]
     * for the category taxonomy of a given post type.
     */
    function get_falcon_category_taxonomy($postType)
    {
        if (!$postType || $postType === 'post') {
            return ['type' => 'native', 'taxonomy_slug' => null];
        }
        if ($postType === 'product') {
            return ['type' => 'product', 'taxonomy_slug' => null];
        }
        // ACPT: find an active hierarchical (category) taxonomy for this CPT
        $row = DB::table('custom_taxonomies')
            ->where('hierarchical', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn ($t) => in_array($postType, json_decode($t->post_types ?? '[]', true)));
        if (!$row) {
            return ['type' => 'none', 'taxonomy_slug' => null];
        }

        return ['type' => 'acpt', 'taxonomy_slug' => $row->slug];
    }
}

if (!function_exists('get_falcon_categories')) {
    function get_falcon_categories($taxonomy = 'category', $postType = null)
    {
        if ($taxonomy === 'category') {
            $info = get_falcon_category_taxonomy($postType);
            if ($info['type'] === 'native') {
                return Category::withCount(['posts' => fn ($r) => $r->where('status', 'published')])
                    ->orderBy('name')->get();
            }
            if ($info['type'] === 'product') {
                return ProductCategory::withCount(['posts as posts_count' => fn ($r) => $r->where('status', 'published')])
                    ->orderBy('name')->get();
            }
            if ($info['type'] === 'acpt') {
                return TaxonomyTerm::where('taxonomy_slug', $info['taxonomy_slug'])
                    ->withCount(['posts as posts_count' => fn ($q) => $q->where('status', 'published')])
                    ->orderBy('name')->get();
            }

            return collect();
        }

        return TaxonomyTerm::where('taxonomy_slug', $taxonomy)
            ->withCount(['posts' => fn ($q) => $q->where('status', 'published')])->get();
    }
}

if (!function_exists('is_falcon_homepage')) {
    function is_falcon_homepage($post)
    {
        if (!$post) {
            return false;
        }
        $homeId = (int) get_cms_option('home_page_id');
        if (!$homeId) {
            return false;
        }

        return $post->id == $homeId || ($post->origin_id && $post->origin_id == $homeId);
    }
}

if (!function_exists('get_falcon_permalink')) {
    function get_falcon_permalink($post)
    {
        if (!$post) {
            return '#';
        }

        $type = is_array($post) ? ($post['type'] ?? 'product') : ($post->type ?? 'post');
        $slug = is_array($post) ? ($post['slug'] ?? '') : ($post->slug ?? '');
        $postLang = is_array($post) ? ($post['lang_code'] ?? 'en') : ($post->lang_code ?? 'en');

        // Homepage logic
        if (!is_array($post) && is_falcon_homepage($post)) {
            $homePageId = get_cms_option('home_page_id');
            // ... (rest of homepage logic)
        }

        // Find actual default language (memoized per request — permalinks are built
        // dozens of times per page and the default language is constant).
        $defaultLang = falcon_default_language();

        // Language prefix logic: If it's not the default language, we MUST add the prefix
        $langPrefix = ($postLang === $defaultLang) ? '' : '/'.$postLang;

        // Homepage check again for safety
        if (!is_array($post) && is_falcon_homepage($post)) {
            if ($postLang === $defaultLang) {
                return url('/');
            }

            return url($postLang);
        }

        if ($type === 'page') {
            return url($langPrefix.'/'.$slug);
        }

        return url($langPrefix.'/'.$type.'/'.$slug);
    }
}

if (!function_exists('falcon_taxonomy_base')) {
    /**
     * The first segment of a taxonomy archive's address — /category/food, /tag/quick-meals.
     *
     * The bases used to be written into the route file, so a site that wanted /topic/food or
     * /post-category/food had no way to say so. They are settings now, and the routes are
     * built from them at boot.
     *
     * A base is sanitised on the way out rather than only on the way in, because these routes
     * are registered before anything else runs: a bad value saved by hand in the database
     * would otherwise take the whole front end down rather than one page. Anything that is
     * not a plain slug, and anything that would swallow a reserved path, falls back to the
     * default the CMS shipped with.
     */
    function falcon_taxonomy_base(string $taxonomy): string
    {
        $defaults = [
            'category' => 'category',
            'tag' => 'tag',
            'product_category' => 'product-category',
            'product_tag' => 'product-tag',
        ];
        $key = str_replace('-', '_', $taxonomy);
        $default = $defaults[$key] ?? $taxonomy;

        $base = (string) get_cms_option($key.'_base', $default);
        $base = trim(strtolower($base), " \t\n\r\0\x0B/");

        // One segment, slug characters only. A base with a slash in it would need a route
        // pattern this one does not have, and an empty one would claim every URL on the site.
        if ($base === '' || !preg_match('/^[a-z0-9][a-z0-9\-_]*$/', $base)) {
            return $default;
        }

        // Paths the CMS already answers on. Handing one of them to a taxonomy would shadow it.
        $reserved = [
            'admin', 'falcon-admin', 'api', 'search', 'author', 'lang', 'storage',
            'sitemap', 'sitemap.xml', 'robots.txt', 'feed', 'comment', 'form-submit',
        ];
        $reserved[] = strtolower(trim((string) get_cms_option('login_url', 'super-lazy-admin'), '/'));
        $reserved[] = strtolower(trim((string) get_cms_option('register_url', 'super-lazy-register'), '/'));
        if (in_array($base, array_filter($reserved), true)) {
            return $default;
        }

        // Two taxonomies cannot share a base: whichever route registered first would win every
        // request, and the other archive would quietly serve the wrong terms.
        foreach ($defaults as $other => $otherDefault) {
            if ($other === $key) {
                continue;
            }
            $taken = trim(strtolower((string) get_cms_option($other.'_base', $otherDefault)), '/');
            if ($base === $taken && $base !== $default) {
                return $default;
            }
        }

        return $base;
    }
}

if (!function_exists('get_falcon_term_link')) {
    /**
     * The address of a term's archive.
     *
     * Themes used to build these by hand, which meant every theme hard-coded the base and none
     * of them followed the setting. Hand it a term from falcon_post_terms() — or a plain slug —
     * and it returns the URL the routes are actually serving, language prefix included.
     */
    function get_falcon_term_link($term, string $taxonomy = 'category'): string
    {
        $slug = is_string($term) ? $term : (is_array($term) ? ($term['slug'] ?? '') : ($term->slug ?? ''));
        if ($slug === '') {
            return '#';
        }

        $names = [
            'category' => 'frontend.category',
            'categories' => 'frontend.category',
            'tag' => 'frontend.tag',
            'tags' => 'frontend.tag',
            'product_category' => 'frontend.product_category',
            'product-category' => 'frontend.product_category',
            'product_tag' => 'frontend.product_tag',
            'product-tag' => 'frontend.product_tag',
        ];
        $route = $names[strtolower($taxonomy)] ?? null;

        // An ACPT taxonomy has no route of its own; its terms are served by the category
        // archive, which falls through to taxonomy_terms when no category matches the slug.
        if (!$route) {
            $route = 'frontend.category';
        }

        $locale = app()->getLocale();
        if ($locale !== falcon_default_language() && Route::has($route.'.locale')) {
            return route($route.'.locale', ['locale' => $locale, 'slug' => $slug]);
        }

        return Route::has($route) ? route($route, $slug) : url('/'.$slug);
    }
}

if (!function_exists('falcon_post_terms')) {
    /**
     * Terms a post holds in one taxonomy, by taxonomy slug.
     *
     * Built-in taxonomies live in their own tables reached through an Eloquent relation;
     * custom (ACPT) ones live in taxonomy_terms. Slugs are accepted in any of the spellings
     * the builder may have saved (singular/plural, dash/underscore) — the same leniency the
     * Post Meta element applies, kept here so every consumer resolves terms identically.
     *
     * @return Collection
     */
    function falcon_post_terms($post, string $taxonomySlug)
    {
        if (!$post || $taxonomySlug === '') {
            return collect();
        }

        $relations = [
            'category' => 'categories',  'categories' => 'categories',
            'tag' => 'tags',        'tags' => 'tags',
            'product-category' => 'productCategories', 'product_category' => 'productCategories',
            'product-categories' => 'productCategories', 'product_categories' => 'productCategories',
            'product-tag' => 'productTags', 'product_tag' => 'productTags',
            'product-tags' => 'productTags', 'product_tags' => 'productTags',
        ];

        $candidates = array_unique(array_filter([
            $relations[$taxonomySlug] ?? null,
            Str::camel(str_replace(['-', '.'], '_', $taxonomySlug)),
        ]));
        foreach ($candidates as $rel) {
            if (!method_exists($post, $rel)) {
                continue;
            }
            try {
                $r = $post->{$rel};
                if ($r instanceof Collection) {
                    return $r;
                }
            } catch (Throwable $e) {
            }
        }

        if (method_exists($post, 'taxonomyTerms')) {
            try {
                $variants = array_unique([
                    $taxonomySlug,
                    str_replace('-', '_', $taxonomySlug),
                    str_replace('_', '-', $taxonomySlug),
                ]);

                return $post->taxonomyTerms()->whereIn('taxonomy_slug', $variants)->get();
            } catch (Throwable $e) {
            }
        }

        return collect();
    }
}

if (!function_exists('falcon_term_archive_url')) {
    /**
     * Public archive URL for a term. Custom taxonomies have no route of their own — the
     * category and product-category archives fall back to a taxonomy_terms lookup — so a
     * custom taxonomy is routed through the archive matching its post type.
     *
     * The first segment is a setting, so it is read rather than assumed. Hard-coding
     * "category" here sent every term link through the previous-base 301 on any site that
     * had changed it, which still arrived but never matched the archive it landed on.
     */
    function falcon_term_archive_url($term, string $taxonomySlug, string $postType = 'post'): string
    {
        $slug = is_object($term) ? ($term->slug ?? '') : (string) $term;
        if ($slug === '') {
            return '';
        }

        $taxonomies = [
            'category' => 'category',        'categories' => 'category',
            'tag' => 'tag',             'tags' => 'tag',
            'product-category' => 'product_category', 'product_category' => 'product_category',
            'product-categories' => 'product_category', 'product_categories' => 'product_category',
            'product-tag' => 'product_tag',     'product_tag' => 'product_tag',
            'product-tags' => 'product_tag',     'product_tags' => 'product_tag',
        ];
        $taxonomy = $taxonomies[$taxonomySlug]
            ?? ($postType === 'product' ? 'product_category' : 'category');

        return url('/'.falcon_taxonomy_base($taxonomy).'/'.$slug);
    }
}

if (!function_exists('falcon_revision_diff')) {
    /**
     * Produce an HTML line-level diff between two content versions (for the revisions compare page).
     * Builder JSON is converted to readable shortcodes first. Uses an LCS line diff — no external deps.
     */
    function falcon_revision_diff(string $old, string $new): string
    {
        $prep = function ($s) {
            $s = (string) $s;
            if (BuilderShortcodeConverter::isBuilderJson($s)) {
                $s = BuilderShortcodeConverter::jsonToShortcodes($s);
            }
            $s = preg_replace('/>\s*</', ">\n<", $s);        // break HTML onto separate lines
            $lines = preg_split('/\r\n|\r|\n/', $s);

            return array_values(array_filter($lines, fn ($l) => trim($l) !== '' || $l === ''));
        };

        $a = $prep($old);
        $b = $prep($new);
        $n = count($a);
        $m = count($b);

        // Safety cap — very large contents skip the O(n*m) diff
        if ($n + $m > 4000) {
            return '<div class="diff-note">Content too large to diff line-by-line. Use Restore to roll back.</div>';
        }

        // LCS dynamic programming table
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = ($a[$i] === $b[$j])
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $rows[] = [' ', $a[$i]];
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $rows[] = ['-', $a[$i]];
                $i++;
            } else {
                $rows[] = ['+', $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $rows[] = ['-', $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $rows[] = ['+', $b[$j]];
            $j++;
        }

        $changed = false;
        $html = '';
        foreach ($rows as [$op, $line]) {
            $esc = e($line);
            if ($op === '+') {
                $html .= '<div class="diff-line diff-add"><span class="diff-sign">+</span>'.$esc.'</div>';
                $changed = true;
            } elseif ($op === '-') {
                $html .= '<div class="diff-line diff-del"><span class="diff-sign">-</span>'.$esc.'</div>';
                $changed = true;
            } else {
                $html .= '<div class="diff-line diff-eq"><span class="diff-sign"> </span>'.$esc.'</div>';
            }
        }

        if (!$changed) {
            return '<div class="diff-note">No differences between these two versions.</div>';
        }

        return $html;
    }
}

if (!function_exists('falcon_placeholder_image')) {
    /**
     * The image shown where a post or product has none: a plain grey frame with a picture icon,
     * inline as an SVG data URI, so there is no file to publish and nothing to 404. A site can
     * point it at an image of its own with the falcon_placeholder_image filter.
     */
    function falcon_placeholder_image(): string
    {
        static $svg = null;
        $svg ??= 'data:image/svg+xml;charset=utf-8,'.rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400">'
            .'<rect width="400" height="400" fill="#eef1f5"/>'
            .'<g fill="none" stroke="#b8c0cc" stroke-width="10" stroke-linecap="round" stroke-linejoin="round">'
            .'<rect x="130" y="140" width="140" height="120" rx="12"/><circle cx="170" cy="180" r="14"/>'
            .'<path d="M135 250l45-45 30 30 25-25 35 35"/></g></svg>'
        );

        return (string) apply_falcon_filters('falcon_placeholder_image', $svg);
    }
}

if (!function_exists('falcon_media_info')) {
    /**
     * What the media library knows about an image URL from this site: its pixel size and alt
     * text, or null for anything it has no record of (an external URL, an SVG it could not
     * measure, a file uploaded outside the library).
     *
     * The size lets a page reserve the image's space before it loads, so the text below does
     * not jump when it arrives; the alt text fills in when an element was given none.
     *
     * @return array{width:?int,height:?int,alt:string}|null
     */
    function falcon_media_info(?string $url): ?array
    {
        $url = (string) $url;
        if ($url === '' || !preg_match('#/storage/(.+)$#', parse_url($url, PHP_URL_PATH) ?: '', $m)) {
            return null;
        }
        $path = rawurldecode($m[1]);

        try {
            $row = DB::table('media')->where('path', $path)->first(['width', 'height', 'alt_text']);
        } catch (Throwable $e) {
            $row = null;
        }

        return $row ? [
            'width' => $row->width ? (int) $row->width : null,
            'height' => $row->height ? (int) $row->height : null,
            'alt' => (string) ($row->alt_text ?? ''),
        ] : null;
    }
}

if (!function_exists('get_falcon_image_url')) {
    function get_falcon_image_url($path, $default = null)
    {
        if (empty($path)) {
            // the old default was an image on via.placeholder.com, which no longer answers
            return $default ?? falcon_placeholder_image();
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Check common paths
        if (file_exists(public_path($path))) {
            return asset($path);
        }
        if (file_exists(public_path('storage/'.$path))) {
            return asset('storage/'.$path);
        }

        return asset('storage/'.$path); // Fallback to storage
    }

}
