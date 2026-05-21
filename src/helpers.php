<?php

use Illuminate\Support\Facades\DB;

if (!defined('LAZY_CMS_VERSION')) {
    define('LAZY_CMS_VERSION', '5.9.9');
}

if (!function_exists('lazy_check_update')) {
    function lazy_check_update(bool $force = false): array
    {
        $cacheKey = 'lazy_cms_update_check';
        if (!$force && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $current = LAZY_CMS_VERSION;
        $result  = ['current' => $current, 'latest' => null, 'has_update' => false, 'url' => null, 'checked_at' => now()->toDateTimeString()];

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'LazyCMS/' . $current])
                ->get('https://repo.packagist.org/p2/tareqcodex/lazy-cms-rebuild.json');

            if ($res->successful()) {
                $versions = $res->json('packages.tareqcodex/lazy-cms-rebuild') ?? [];
                foreach ($versions as $v) {
                    $ver = ltrim($v['version'] ?? '', 'v');
                    if (preg_match('/^\d+\.\d+\.\d+$/', $ver)) {
                        $result['latest'] = $ver;
                        $result['url']    = 'https://packagist.org/packages/tareqcodex/lazy-cms-rebuild';
                        break;
                    }
                }
            }
        } catch (\Exception $e) {}

        if (!$result['latest']) {
            try {
                $gh = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/' . $current])
                    ->get('https://api.github.com/repos/tareqcodex/lazy-cms-rebuild/releases/latest');
                if ($gh->successful()) {
                    $tag = ltrim($gh->json('tag_name') ?? '', 'v');
                    if ($tag) {
                        $result['latest'] = $tag;
                        $result['url']    = $gh->json('html_url');
                    }
                }
            } catch (\Exception $e) {}
        }

        if ($result['latest']) {
            $result['has_update'] = version_compare($result['latest'], $result['current'], '>');
        }

        cache()->put($cacheKey, $result, now()->addHours(6));
        return $result;
    }
}

if (!function_exists('get_cms_option')) {
    function get_cms_option($key, $default = null)
    {
        try {
            $currentLocale = app()->getLocale();
            $localeKey = $key . '_' . $currentLocale;
            
            // 1. Check for locale specific key first (e.g. site_title_bn)
            $value = DB::table('cms_settings')->where('key', $localeKey)->value('value');
            if ($value !== null) return $value;

            // 2. Fallback to default key
            $value = DB::table('cms_settings')->where('key', $key)->value('value');
            return $value !== null ? $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('update_cms_option')) {
    function update_cms_option($key, $value)
    {
        try {
            \Illuminate\Support\Facades\DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('get_custom_field')) {
    function get_custom_field($post, $fieldName, $default = null)
    {
        try {
            $postId = is_object($post) ? $post->id : $post;
            $value = DB::table('post_custom_field_values')
                ->join('custom_fields', 'post_custom_field_values.field_id', '=', 'custom_fields.id')
                ->where('post_custom_field_values.post_id', $postId)
                ->where('custom_fields.name', $fieldName)
                ->value('post_custom_field_values.value');
            return $value !== null ? $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('get_lazy_content')) {
    function get_lazy_content($content)
    {
        if (empty($content)) return '';
        // Check if it's builder shortcode format
        if (is_string($content) && \Acme\CmsDashboard\Services\BuilderShortcodeConverter::isBuilderShortcode($content)) {
            $content = \Acme\CmsDashboard\Services\BuilderShortcodeConverter::shortcodesToJson($content);
        }

        try {
            $layout = is_string($content) ? json_decode($content, true) : $content;
            
            if (!is_array($layout)) {
                return do_lazy_shortcode($content);
            }
            
            $rendered = view('cms-dashboard::frontend.builder.render', ['layout' => $layout])->render();
            return do_lazy_shortcode($rendered);
        } catch (\Exception $e) {
            \Log::error('Lazy Builder Error: ' . $e->getMessage());
            return do_lazy_shortcode($content);
        }
    }
}

if (!function_exists('_lazy_hex_to_rgba')) {
    function _lazy_hex_to_rgba(string $hex, float $opacity = 1): string
    {
        if (empty($hex) || $hex === 'transparent') return 'transparent';
        if (strpos($hex, 'rgba') !== false) return $hex;
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        [$r, $g, $b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
        return $opacity >= 1 ? "rgb({$r},{$g},{$b})" : "rgba({$r},{$g},{$b},{$opacity})";
    }
}

if (!function_exists('_lazy_parse_builder_layout')) {
    function _lazy_parse_builder_layout(string $raw): ?array
    {
        try {
            if (\Acme\CmsDashboard\Services\BuilderShortcodeConverter::isBuilderShortcode($raw)) {
                $raw = \Acme\CmsDashboard\Services\BuilderShortcodeConverter::shortcodesToJson($raw);
            }
            $layout = json_decode($raw, true);
            return is_array($layout) ? $layout : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('_lazy_render_layout')) {
    function _lazy_render_layout(array $layout): string
    {
        $rendered = view('cms-dashboard::frontend.builder.render', ['layout' => $layout])->render();
        return do_lazy_shortcode($rendered);
    }
}

if (!function_exists('_lazy_build_sticky_wrapper')) {
    /**
     * Build a sticky wrapper element around $content.
     * $settings is the settings array of the first sticky container/column.
     * $wrapperClass is the CSS class on the wrapper (e.g. lazy-builder-header).
     * $tag is the HTML tag (header|footer|div).
     */
    function _lazy_build_sticky_wrapper(string $content, array $settings, string $wrapperClass, string $tag): string
    {
        $offset    = (int)($settings['stickyOffset']  ?? 0);
        $zIndex    = (int)($settings['stickyZIndex']  ?? 100);
        $desktop   = ($settings['stickyDesktop'] ?? true) !== false;
        $tablet    = ($settings['stickyTablet']  ?? true) !== false;
        $mobile    = ($settings['stickyMobile']  ?? true) !== false;
        $bgColor   = $settings['stickyBgColor']        ?? '';
        $bgOpacity = (float)($settings['stickyBgColorOpacity'] ?? 1);

        $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
        $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
        $bpSm1 = $bpSm + 1;

        $sOn  = "position:sticky;top:{$offset}px;z-index:{$zIndex};";
        $sOff = "position:static;top:auto;z-index:auto;";

        $baseStyle    = ($tag === 'header') ? 'width:100%;' : '';
        $wrapperStyle = $baseStyle . ($desktop ? $sOn : '');

        $mediaCss = '';
        if ($tablet !== $desktop) {
            $rule = $tablet ? $sOn : $sOff;
            $mediaCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){.{$wrapperClass}{{$rule}}}";
        }
        if ($mobile !== $tablet) {
            $rule = $mobile ? $sOn : $sOff;
            $mediaCss .= "@media(max-width:{$bpSm}px){.{$wrapperClass}{{$rule}}}";
        }

        // Suppress per-container/column sticky — the wrapper handles positioning
        $css = ".{$wrapperClass} .lazy-container,.{$wrapperClass} .lazy-column{position:static!important;top:auto!important;}";
        $css .= $mediaCss;

        if (!empty($bgColor)) {
            $rgba = _lazy_hex_to_rgba($bgColor, $bgOpacity);
            $css .= ".{$wrapperClass}{transition:background-color 0.3s ease;}";
            $css .= ".lazy-sticky-active.{$wrapperClass}{background-color:{$rgba}!important;}";
        }

        // lazy-sticky-col → IntersectionObserver detects stuck state
        return "<{$tag} class=\"{$wrapperClass} lazy-sticky-col\" style=\"{$wrapperStyle}\">"
             . "<style>{$css}</style>"
             . $content
             . "</{$tag}>";
    }
}

if (!function_exists('_lazy_builder_render_wrapper')) {
    /**
     * Render header/footer builder content with correct sticky handling.
     *
     * Only the containers from the FIRST sticky container onwards are placed
     * inside the sticky wrapper. Containers before it render in a plain div so
     * they scroll away normally (e.g. a top-bar above a sticky nav).
     */
    function _lazy_builder_render_wrapper(string $raw, string $tag, string $wrapperClass): string
    {
        $layout = _lazy_parse_builder_layout($raw);

        if (!is_array($layout) || empty($layout)) {
            $content = get_lazy_content($raw);
            $style   = $tag === 'header' ? ' style="width:100%;"' : '';
            return "<{$tag} class=\"{$wrapperClass}\"{$style}>{$content}</{$tag}>";
        }

        // Find the index of the first sticky container (check container + column settings)
        $stickyIndex    = null;
        $stickySettings = null;
        foreach ($layout as $i => $container) {
            $cs = $container['settings'] ?? [];
            if (!empty($cs['sticky'])) {
                $stickyIndex    = $i;
                $stickySettings = $cs;
                break;
            }
            foreach ($container['columns'] ?? [] as $col) {
                $cls = $col['settings'] ?? [];
                if (!empty($cls['sticky'])) {
                    $stickyIndex    = $i;
                    $stickySettings = $cls;
                    break 2;
                }
            }
        }

        if ($stickySettings === null) {
            // Nothing sticky — simple wrapper
            $style = $tag === 'header' ? ' style="width:100%;"' : '';
            return "<{$tag} class=\"{$wrapperClass}\"{$style}>"
                 . _lazy_render_layout($layout)
                 . "</{$tag}>";
        }

        // Render containers BEFORE the first sticky one in a plain above-wrapper
        $html = '';
        if ($stickyIndex > 0) {
            $html .= '<div class="' . $wrapperClass . '-above" style="width:100%;">'
                   . _lazy_render_layout(array_slice($layout, 0, $stickyIndex))
                   . '</div>';
        }

        // Render sticky containers inside the sticky wrapper
        $stickyContent = _lazy_render_layout(array_slice($layout, $stickyIndex));
        $html .= _lazy_build_sticky_wrapper($stickyContent, $stickySettings, $wrapperClass, $tag);

        return $html;
    }
}

if (!function_exists('get_lazy_header')) {
    function get_lazy_header()
    {
        $header = \Acme\CmsDashboard\Models\Post::where('type', 'lazy_header')
            ->where('status', 'published')
            ->where('lang_code', app()->getLocale())
            ->first();
        if (!$header) {
            $header = \Acme\CmsDashboard\Models\Post::where('type', 'lazy_header')
                ->where('status', 'published')
                ->first();
        }
        if ($header) {
            return _lazy_builder_render_wrapper($header->content ?? '', 'header', 'lazy-builder-header');
        }
        return null;
    }
}

if (!function_exists('get_lazy_footer')) {
    function get_lazy_footer()
    {
        $footer = \Acme\CmsDashboard\Models\Post::where('type', 'lazy_footer')
            ->where('status', 'published')
            ->where('lang_code', app()->getLocale())
            ->first();
        if (!$footer) {
            $footer = \Acme\CmsDashboard\Models\Post::where('type', 'lazy_footer')
                ->where('status', 'published')
                ->first();
        }
        if ($footer) {
            return _lazy_builder_render_wrapper($footer->content ?? '', 'footer', 'lazy-builder-footer');
        }
        return null;
    }
}

if (!function_exists('getUnitVal')) {
    function getUnitVal($val, $unit = 'px') {
        if ($val === null || $val === '') return null;
        if (is_numeric($val)) return $val . $unit;
        return $val;
    }
}

if (!function_exists('the_lazy_content')) {
    function the_lazy_content($content) { echo get_lazy_content($content); }
}

if (!function_exists('get_lazy_posts')) {
    function get_lazy_posts($args = []) {
        $defaults = [
            'post_type'        => 'post',
            'limit'            => 10,
            'offset'           => 0,
            'order'            => 'desc',
            'orderby'          => 'created_at',
            'status'           => 'published',
            'category'         => null,
            'category_exclude' => null,
            'tag'              => null,
            'tag_exclude'      => null,
            'has_categories'   => false,
            'has_tags'         => false,
            'author'           => null,
            'search'           => null,
            'post_id'          => null,
            'meta_key'         => null,
            'meta_value'       => null,
            'taxonomy_slug'    => null,
            'taxonomy_include' => null,
            'taxonomy_exclude' => null,
            'paginate'         => false,
            'page_name'        => 'page',
            'lang'             => null,
        ];
        $args = array_merge($defaults, $args);

        if ($args['post_type'] === 'any') {
            $query = \Acme\CmsDashboard\Models\Post::query();
        } else {
            $query = \Acme\CmsDashboard\Models\Post::where('type', $args['post_type']);
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
            $query->whereHas('categories', function($q) use ($catSlugs) {
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
                $query->whereDoesntHave('categories', function($q) use ($catExSlugs) {
                    $q->whereIn('slug', $catExSlugs);
                });
            }
        }
        if ($args['tag']) {
            $tagSlugs = is_array($args['tag']) ? $args['tag'] : array_filter(explode(',', $args['tag']));
            $query->whereHas('tags', function($q) use ($tagSlugs) {
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
                $query->whereDoesntHave('tags', function($q) use ($tagExSlugs) {
                    $q->whereIn('slug', $tagExSlugs);
                });
            }
        }
        if ($args['author']) {
            $query->where('user_id', $args['author']);
        }
        if ($args['search']) {
            $query->where('title', 'like', '%' . $args['search'] . '%');
        }
        if (!empty($args['post_id'])) {
            $ids = is_array($args['post_id']) ? $args['post_id'] : explode(',', $args['post_id']);
            $query->whereIn('id', array_filter(array_map('intval', $ids)));
        }
        if (!empty($args['taxonomy_slug'])) {
            $taxSlug = $args['taxonomy_slug'];
            if (!empty($args['taxonomy_include'])) {
                $include = is_array($args['taxonomy_include']) ? $args['taxonomy_include'] : explode(',', $args['taxonomy_include']);
                $query->whereHas('taxonomyTerms', function($q) use ($taxSlug, $include) {
                    $q->where('taxonomy_slug', $taxSlug)->whereIn('slug', array_filter($include));
                });
            } else {
                $query->whereHas('taxonomyTerms', function($q) use ($taxSlug) {
                    $q->where('taxonomy_slug', $taxSlug);
                });
            }
            if (!empty($args['taxonomy_exclude'])) {
                $exclude = is_array($args['taxonomy_exclude']) ? $args['taxonomy_exclude'] : explode(',', $args['taxonomy_exclude']);
                $query->whereDoesntHave('taxonomyTerms', function($q) use ($taxSlug, $exclude) {
                    $q->where('taxonomy_slug', $taxSlug)->whereIn('slug', array_filter($exclude));
                });
            }
        }

        if ($args['orderby'] === 'rand') {
            $query->inRandomOrder();
        } else {
            $safeOrderby = in_array($args['orderby'], ['created_at','updated_at','title','views','menu_order','id'])
                ? $args['orderby'] : 'created_at';
            $query->orderBy($safeOrderby, $args['order']);
        }

        if ($args['paginate']) {
            return $query->paginate($args['limit'], ['*'], $args['page_name'] ?? 'page');
        }
        return $query->limit($args['limit'])->offset((int)$args['offset'])->get();
    }
}

if (!function_exists('the_lazy_pagination')) {
    function the_lazy_pagination($items, $view = null) {
        if (!($items instanceof \Illuminate\Pagination\LengthAwarePaginator)) return '';
        return $items->links($view);
    }
}

if (!function_exists('the_lazy_loop')) {
    function the_lazy_loop($args = [], $view = 'cms-dashboard::frontend.loop')
    {
        $posts = get_lazy_posts($args);
        echo view($view, ['posts' => $posts])->render();
    }
}

if (!function_exists('get_lazy_excerpt')) {
    function get_lazy_excerpt($post, $limit = 120)
    {
        if ($post->editor_type !== 'builder') {
            return \Illuminate\Support\Str::limit(strip_tags($post->content), $limit);
        }
        try {
            $layout = is_string($post->content) ? json_decode($post->content, true) : $post->content;
            $text = '';
            if (is_array($layout)) {
                foreach ($layout as $container) {
                    if (!empty($container['columns'])) {
                        foreach ($container['columns'] as $column) {
                            if (!empty($column['elements'])) {
                                foreach ($column['elements'] as $el) {
                                    if ($el['type'] === 'heading') $text .= ($el['settings']['title'] ?? '') . ' ';
                                    elseif ($el['type'] === 'text') $text .= strip_tags($el['settings']['content'] ?? '') . ' ';
                                    if (strlen($text) > $limit) break 3;
                                }
                            }
                        }
                    }
                }
            }
            return \Illuminate\Support\Str::limit(trim($text), $limit);
        } catch (\Exception $e) { return ''; }
    }
}

if (!function_exists('get_lazy_post')) {
    function get_lazy_post($slugOrId) {
        if (is_numeric($slugOrId)) return \Acme\CmsDashboard\Models\Post::find($slugOrId);
        return \Acme\CmsDashboard\Models\Post::where('slug', $slugOrId)->where('lang_code', app()->getLocale())->first();
    }
}

if (!function_exists('get_lazy_categories')) {
    function get_lazy_categories($taxonomy = 'category') {
        if ($taxonomy === 'category') return \Acme\CmsDashboard\Models\Category::orderBy('name')->get();
        return \Acme\CmsDashboard\Models\TaxonomyTerm::where('taxonomy_slug', $taxonomy)->get();
    }
}

if (!function_exists('get_lazy_menu')) {
    function get_lazy_menu($slugOrLocation) {
        $query = \Acme\CmsDashboard\Models\NavigationMenu::query();
        
        if ($slugOrLocation === 'header') {
            $query->where('is_header', true);
        } elseif ($slugOrLocation === 'footer') {
            $query->where('is_footer', true);
        } else {
            $query->where('slug', $slugOrLocation);
        }

        $currentLocale = app()->getLocale();
        
        // Try to find menu with exact slug-locale if it's a slug
        if (!in_array($slugOrLocation, ['header', 'footer'])) {
            $langSlug = $slugOrLocation . '-' . $currentLocale;
            $menu = (clone $query)->where('slug', $langSlug)->first();
            if ($menu) return this_process_items($menu);
        }

        // Try to find by location AND lang_code
        $menu = (clone $query)->where('lang_code', $currentLocale)->first();
        
        if (!$menu) {
            // Fallback to location only without lang_code
            $menu = (clone $query)->whereNull('lang_code')->first();
        }

        if (!$menu) return collect();

        return this_process_items($menu);
    }
}

// Internal helper for menu processing (moved logic out of the main function for reuse)
if (!function_exists('this_process_items')) {
    function this_process_items($menu) {
        // Fetch active CPTs and Taxonomies to filter items
        $activePostTypes = \Acme\CmsDashboard\Models\PostType::where('is_active', true)->pluck('slug')->toArray();
        $activeTaxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)->pluck('slug')->toArray();

        // Built-in types are always active
        $activePostTypes[] = 'post';
        $activePostTypes[] = 'page';
        $activePostTypes[] = 'category'; // Default category
        $activePostTypes[] = 'custom';   // Custom links

        $items = $menu->items->filter(function($item) use ($activePostTypes, $activeTaxonomies) {
            // If it's a post/page/cpt item
            if (!in_array($item->type, ['category', 'custom'])) {
                return in_array($item->type, $activePostTypes);
            }
            // If it's a category/taxonomy item
            if ($item->type === 'category' && $item->object_id) {
                $term = \Acme\CmsDashboard\Models\TaxonomyTerm::find($item->object_id);
                if ($term) return in_array($term->taxonomy_slug, $activeTaxonomies);
                $standardCat = \Acme\CmsDashboard\Models\Category::find($item->object_id);
                return (bool) $standardCat;
            }
            return true;
        });

        $cleanItems = function($items) use (&$cleanItems) {
            return $items->map(function($item) use ($cleanItems) {
                $currentLocale = app()->getLocale();
                
                // If it's a post/page/cpt item, find translation
                if (!in_array($item->type, ['category', 'custom']) && $item->object_id) {
                    $post = \Acme\CmsDashboard\Models\Post::find($item->object_id);
                    if ($post) {
                        // Find translation in current locale
                        if ($post->lang_code !== $currentLocale) {
                            $translation = $post->getTranslation($currentLocale);
                            if ($translation) {
                                $post = $translation;
                            }
                        }
                        $item->url = get_lazy_permalink($post);
                    }
                }

                // Recursively clean children
                if ($item->children && $item->children->count() > 0) {
                    $item->setRelation('children', $cleanItems($item->children));
                }

                return $item;
            });
        };

        return $cleanItems($items);
    }
}

if (!function_exists('is_lazy_homepage')) {
    function is_lazy_homepage($post) {
        if (!$post) return false;
        $homeId = (int) get_cms_option('home_page_id');
        if (!$homeId) return false;
        return ($post->id == $homeId || ($post->origin_id && $post->origin_id == $homeId));
    }
}

if (!function_exists('get_lazy_permalink')) {
    function get_lazy_permalink($post) {
        if (!$post) return '#';
        
        $type = is_array($post) ? ($post['type'] ?? 'product') : ($post->type ?? 'post');
        $slug = is_array($post) ? ($post['slug'] ?? '') : ($post->slug ?? '');
        $postLang = is_array($post) ? ($post['lang_code'] ?? 'en') : ($post->lang_code ?? 'en');
        
        // Homepage logic
        if (!is_array($post) && is_lazy_homepage($post)) {
            $homePageId = get_cms_option('home_page_id');
            // ... (rest of homepage logic)
        }

        // Find actual default language from DB
        $defaultLang = 'en';
        try {
            $dbDefault = \Illuminate\Support\Facades\DB::table('cms_languages')->where('is_default', true)->value('code');
            if ($dbDefault) $defaultLang = $dbDefault;
        } catch (\Exception $e) {}

        // Language prefix logic: If it's not the default language, we MUST add the prefix
        $langPrefix = ($postLang === $defaultLang) ? '' : '/' . $postLang;

        // Homepage check again for safety
        if (!is_array($post) && is_lazy_homepage($post)) {
            if ($postLang === $defaultLang) return url('/');
            return url($postLang);
        }

        if ($type === 'page') {
            return url($langPrefix . '/' . $slug);
        }
        return url($langPrefix . '/' . $type . '/' . $slug);
    }
}

if (!function_exists('clear_page_cache')) {
    function clear_page_cache() {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('lazy_log_activity')) {
    function lazy_log_activity($action, $description, $model = null, $properties = []) {
        try {
            $ip = request()->ip();
            $country = null;
            $countryCode = null;

            // Simple IP to Country Cache/Lookup
            if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
                try {
                    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,countryCode");
                    if ($response) {
                        $data = json_decode($response, true);
                        if ($data && $data['status'] === 'success') {
                            $country = $data['country'];
                            $countryCode = $data['countryCode'];
                        }
                    }
                } catch (\Exception $e) {}
            }

            return \Acme\CmsDashboard\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->id : null,
                'description' => $description,
                'properties' => $properties,
                'ip_address' => $ip,
                'country' => $country,
                'country_code' => $countryCode,
                'user_agent' => request()->userAgent()
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('render_lazy_widgets')) {
    function render_lazy_widgets($area) {
        $currentLocale = app()->getLocale();
        $query = \Acme\CmsDashboard\Models\Widget::forArea($area);
        
        // 1. Filter by lang_code
        $widgets = $query->where(function($q) use ($currentLocale) {
            $q->where('lang_code', $currentLocale)->orWhereNull('lang_code');
        })->get();

        $output = '';
        foreach ($widgets as $widget) {
            // 1. Try Theme Specific Widget first: themes/lazy-theme/widgets/name.blade.php
            $activeTheme = get_cms_option('active_theme', 'lazy-theme');
            $themeWidget = "cms-dashboard::themes.{$activeTheme}.widgets.{$widget->type}";
            
            // 2. Try Package Default Widget: frontend.widgets.name
            $defaultWidget = "cms-dashboard::frontend.widgets.{$widget->type}";

            if (view()->exists($themeWidget)) {
                $output .= view($themeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($defaultWidget)) {
                $output .= view($defaultWidget, ['widget' => $widget])->render();
            } else {
                // Fallback for custom HTML or simple text
                if ($widget->type === 'custom_html') {
                    $content = $widget->settings['content'] ?? '';
                    // Process Shortcodes if any system exists (placeholder for now)
                    $content = do_lazy_shortcode($content);

                    $output .= '<div class="widget mb-12">';
                    if ($widget->title) $output .= '<h4 class="widget-title">' . e($widget->title) . '</h4>';
                    $output .= $content;
                    $output .= '</div>';
                }
            }
        }
        return $output;
    }
}

// --- Hook System Helpers ---

if (!function_exists('add_lazy_action')) {
    function add_lazy_action($tag, $callback, $priority = 10) {
        \Acme\CmsDashboard\Core\HookManager::getInstance()->addAction($tag, $callback, $priority);
    }
}

if (!function_exists('do_lazy_action')) {
    function do_lazy_action($tag, ...$args) {
        \Acme\CmsDashboard\Core\HookManager::getInstance()->doAction($tag, ...$args);
    }
}

if (!function_exists('add_lazy_filter')) {
    function add_lazy_filter($tag, $callback, $priority = 10) {
        \Acme\CmsDashboard\Core\HookManager::getInstance()->addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('apply_lazy_filters')) {
    function apply_lazy_filters($tag, $value, ...$args) {
        return \Acme\CmsDashboard\Core\HookManager::getInstance()->applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('remove_lazy_action')) {
    function remove_lazy_action($tag, $callback, $priority = 10) {
        return \Acme\CmsDashboard\Core\HookManager::getInstance()->removeAction($tag, $callback, $priority);
    }
}

if (!function_exists('remove_lazy_filter')) {
    function remove_lazy_filter($tag, $callback, $priority = 10) {
        return \Acme\CmsDashboard\Core\HookManager::getInstance()->removeFilter($tag, $callback, $priority);
    }
}

if (!function_exists('lazy_lang_switcher')) {
    function lazy_lang_switcher($showFlags = true) {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) return '';
            $languages = \Acme\CmsDashboard\Models\Language::where('status', true)->get();
            if ($languages->count() <= 1) return '';
            
            $currentLocale = app()->getLocale();
            $output = '<div class="lazy-lang-switcher flex items-center space-x-3">';
            
            // Check if we are on a single post/page to find equivalents
            $currentPost = null;
            if (request()->route('typeOrSlug')) {
                $viewData = view()->getShared();
                if (isset($viewData['post'])) {
                    $currentPost = $viewData['post'];
                }
            }

            foreach ($languages as $lang) {
                $isActive = ($currentLocale == $lang->code);
                $url = url($lang->code); 
                
                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_lazy_permalink($equivalent);
                    }
                }

                $output .= '<a href="' . $url . '" class="flex items-center text-[13px] ' . ($isActive ? 'font-bold text-blue-600' : 'text-gray-600 hover:text-black') . '">';
                if ($showFlags) $output .= '<span class="mr-1">' . $lang->flag . '</span> ';
                $output .= strtoupper($lang->code);
                $output .= '</a>';
            }
            $output .= '</div>';
            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('lazy_lang_dropdown')) {
    function lazy_lang_dropdown() {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) return '';
            $activeLangs = \Acme\CmsDashboard\Models\Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) return '';
            
            $currentLang = $activeLangs->where('code', app()->getLocale())->first() ?? $activeLangs->first();
            
            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) return true;
                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) return '';

            $displayMode = get_cms_option('lang_switcher_display', 'both');
            
            $output = '<div class="relative group inline-block language-switcher-dropdown">';
            $output .= '<button class="flex items-center gap-1.5 text-slate-700 hover:text-primary transition-colors text-[13px] font-bold cursor-pointer" onclick="this.nextElementSibling.classList.toggle(\'hidden\')">';
            
            $currentLangCode = strtolower($currentLang->code);
            $countryMap = [
                'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                'br' => 'br', 'in' => 'in'
            ];
            $currentFlagCode = $countryMap[$currentLangCode] ?? $currentLangCode;

            if (in_array($displayMode, ['both', 'flag_only'])) {
                $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                $output .= '<img src="' . url('/assets/flags/' . $currentFlagCode . '.png') . '" class="w-full h-full object-cover" alt="' . $currentLang->name . '">';
                $output .= '</span>';
            }
            
            if (in_array($displayMode, ['both', 'text_only'])) {
                $output .= '<span class="uppercase">' . $currentLang->name . '</span>';
            } elseif ($displayMode === 'code_only') {
                $output .= '<span class="uppercase">' . $currentLang->code . '</span>';
            }
            
            $output .= '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
            $output .= '</button>';
            $output .= '<div class="absolute top-full right-0 mt-2 w-32 bg-white border border-slate-100 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 rounded-md overflow-hidden">';
            $output .= '<ul class="py-1 m-0 list-none">';
            
            foreach($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);
                
                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_lazy_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_lazy_permalink($currentPost);
                    }
                }

                $output .= '<li>';
                $output .= '<a href="' . $url . '" class="flex items-center justify-between gap-2 px-4 py-2 text-[13px] font-medium text-slate-600 hover:text-primary hover:bg-slate-50 transition-all ' . ($isActive ? 'bg-slate-50 text-primary font-bold' : '') . '">';
                $output .= '<div class="flex items-center gap-2">';
                
                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in'
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                    $output .= '<img src="' . url('/assets/flags/' . $flagCode . '.png') . '" class="w-full h-full object-cover" alt="' . $lang->name . '">';
                    $output .= '</span>';
                }
                
                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span>' . $lang->name . '</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="uppercase">' . $lang->code . '</span>';
                }
                
                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a></li>';
            }
            
            $output .= '</ul></div></div>';
            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('lazy_mobile_lang_switcher')) {
    function lazy_mobile_lang_switcher() {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) return '';
            $activeLangs = \Acme\CmsDashboard\Models\Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) return '';
            
            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) return true;
                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) return '';

            $displayMode = get_cms_option('lang_switcher_display', 'both');
            $output = '<div class="grid grid-cols-2 gap-2">';
            foreach($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);
                
                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_lazy_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_lazy_permalink($currentPost);
                    }
                }

                $output .= '<a href="' . $url . '" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border ' . ($isActive ? 'border-primary bg-primary/5 text-primary' : 'border-slate-100 text-slate-600') . ' transition-all">';
                $output .= '<div class="flex items-center gap-2">';
                
                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in'
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="w-6 h-4 overflow-hidden rounded-sm flex items-center justify-center shrink-0 border border-slate-100 shadow-sm">';
                    $output .= '<img src="' . url('/assets/flags/' . $flagCode . '.png') . '" class="w-full h-full object-cover" alt="' . $lang->name . '">';
                    $output .= '</span>';
                }
                
                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span class="text-[13px] font-semibold">' . $lang->name . '</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="text-[13px] font-semibold uppercase">' . $lang->code . '</span>';
                }
                
                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a>';
            }
            $output .= '</div>';
            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('the_lazy_lang_dropdown')) {
    function the_lazy_lang_dropdown() { echo lazy_lang_dropdown(); }
}

if (!function_exists('lazy_search_form')) {
    function lazy_search_form($placeholder = 'Search...') {
        $url = route('frontend.search');
        $output = '<form action="' . $url . '" method="GET" class="relative lazy-search-form">';
        $output .= '<input type="text" name="s" placeholder="' . e($placeholder) . '" class="w-full bg-slate-50 border border-slate-200 rounded-full px-5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">';
        $output .= '<button type="submit" class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-primary text-white rounded-full text-xs font-bold hover:bg-primary/90 transition-colors uppercase">Search</button>';
        $output .= '</form>';
        return $output;
    }
}

if (!function_exists('the_lazy_search_form')) {
    function the_lazy_search_form($placeholder = 'Search...') { echo lazy_search_form($placeholder); }
}

if (!function_exists('render_lazy_form')) {
    function render_lazy_form($slug) {
        try {
            $form = \Acme\CmsDashboard\Models\Form::where('slug', $slug)->where('status', true)->first();
            if (!$form || empty($form->fields)) return '';
            return view('cms-dashboard::frontend.form-renderer', ['form' => $form])->render();
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('do_lazy_shortcode')) {
    function do_lazy_shortcode($content) {
        if (empty($content)) return $content;

        // Decode HTML entities so [lazy_form slug=&quot;x&quot;] becomes [lazy_form slug="x"]
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Handle [lazy_form slug="..."] or [lazy_form slug='...']
        $decoded = preg_replace_callback('/\[lazy_form\s+slug=["\']([^"\']+)["\']\s*\]/', function($matches) {
            return render_lazy_form($matches[1]);
        }, $decoded);

        $shortcodes = [
            '[lazy_search]'        => lazy_search_form(),
            '[lazy_lang_dropdown]' => lazy_lang_dropdown(),
        ];

        return str_replace(array_keys($shortcodes), array_values($shortcodes), $decoded);
    }
}

if (!function_exists('lazy_translate')) {
    function lazy_translate($text, $targetLang = 'en', $sourceLang = 'auto') {
        if (empty($text)) return $text;
        
        // Map common CMS codes to Google Translate codes
        $map = [
            'jp' => 'ja', 'gb' => 'en', 'in' => 'hi', 'cn' => 'zh-CN', 'kr' => 'ko',
            'ua' => 'uk', 'br' => 'pt', 'sa' => 'ar', 'bd' => 'bn', 'zh' => 'zh-CN',
            'ja' => 'ja', 'ko' => 'ko', 'pt' => 'pt', 'hi' => 'hi'
        ];
        
        $targetLang = $map[strtolower($targetLang)] ?? $targetLang;
        $sourceLang = $map[strtolower($sourceLang)] ?? $sourceLang;

        try {
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=" . $sourceLang . "&tl=" . $targetLang . "&dt=t&q=" . urlencode($text);
            
            $options = [
                "http" => [
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                $translated = '';
                if (isset($data[0])) {
                    foreach ($data[0] as $line) {
                        $translated .= $line[0];
                    }
                    return $translated;
                }
            }
        } catch (\Exception $e) {}
        return $text; 
    }
}

if (!function_exists('get_lazy_shop_url')) {
    function get_lazy_shop_url() {
        $pageId = get_shop_option('shop_shop_page_id');
        if ($pageId) {
            $page = \Acme\CmsDashboard\Models\Post::find($pageId);
            if ($page) return get_lazy_permalink($page);
        }
        return url('/product');
    }
}

if (!function_exists('get_lazy_cart_url')) {
    function get_lazy_cart_url() {
        $pageId = get_shop_option('shop_cart_page_id');
        if ($pageId) {
            $page = \Acme\CmsDashboard\Models\Post::find($pageId);
            if ($page) return get_lazy_permalink($page);
        }
        return route('shop.cart');
    }
}

if (!function_exists('get_lazy_checkout_url')) {
    function get_lazy_checkout_url() {
        $pageId = get_shop_option('shop_checkout_page_id');
        if ($pageId) {
            $page = \Acme\CmsDashboard\Models\Post::find($pageId);
            if ($page) return get_lazy_permalink($page);
        }
        return route('shop.checkout');
    }
}

if (!function_exists('lazy_price_format')) {
    function lazy_price_format($price, $order = null) {
        if ($order && is_object($order) && isset($order->currency_symbol)) {
            $symbol = $order->currency_symbol;
            $position = $order->currency_position ?? 'left';
            $decimals = (int) ($order->decimals ?? 2);
            $thousandSep = $order->thousand_separator ?? ',';
            $decimalSep = $order->decimal_separator ?? '.';
        } else {
            $currencyCode = get_shop_option('shop_currency', 'USD');
            $symbol = \Acme\CmsDashboard\Services\EcommerceData::getCurrencySymbol($currencyCode);
            
            $position = get_shop_option('shop_currency_pos', 'left');
            $decimals = (int) get_shop_option('shop_num_decimals', 2);
            $thousandSep = get_shop_option('shop_thousand_sep', ',');
            $decimalSep = get_shop_option('shop_decimal_sep', '.');
        }
        
        $formatted = number_format((float)$price, $decimals, $decimalSep, $thousandSep);
        
        switch ($position) {
            case 'left':
                return $symbol . $formatted;
            case 'right':
                return $formatted . $symbol;
            case 'left_space':
                return $symbol . ' ' . $formatted;
            case 'right_space':
                return $formatted . ' ' . $symbol;
            default:
                return $symbol . $formatted;
        }
    }
}

if (!function_exists('get_lazy_cart_count')) {
    function get_lazy_cart_count() {
        $cart = session()->get('lazy_cart', []);
        $total = 0;
        foreach($cart as $item) {
            $total += $item['quantity'] ?? 0;
        }
        return $total;
    }
}

if (!function_exists('get_lazy_cart_subtotal')) {
    function get_lazy_cart_subtotal() {
        $cart = session()->get('lazy_cart', []);
        $subtotal = 0;
        foreach($cart as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal += $price * $item['quantity'];
        }
        return $subtotal;
    }
}

if (!function_exists('get_lazy_cart_shipping')) {
    /**
     * Calculate shipping cost based on subtotal, quantity, and location.
     * @param string|null $country Customer country code
     * @return float
     */
    function get_lazy_cart_shipping($country = null) {
        $details = get_lazy_cart_shipping_details($country);
        return $details['cost'];
    }
}

if (!function_exists('get_lazy_cart_shipping_details')) {
    function get_lazy_cart_shipping_details($country = null) {
        $subtotal = get_lazy_cart_subtotal();
        $cart = session()->get('lazy_cart', []);
        $itemCount = 0;
        foreach ($cart as $item) {
            $itemCount += ($item['quantity'] ?? 0);
        }

        // 1. Check Global Free Shipping Threshold
        $globalFreeThreshold = (float) get_shop_option('shop_free_shipping_threshold', 0);
        if ($globalFreeThreshold > 0 && $subtotal >= $globalFreeThreshold) {
            return ['cost' => 0, 'label' => 'Free shipping'];
        }

        // 2. Advanced Shipping Zones
        $zones = get_shop_option('shop_shipping_zones', []);
        
        // Find matching zone if country is provided
        $matchedZone = null;
        if ($country) {
            $normalizedCountry = str_replace('—', '-', $country);
            foreach ($zones as $zone) {
                $zoneCountries = (array)($zone['countries'] ?? []);
                $normalizedZoneCountries = array_map(fn($c) => str_replace('—', '-', $c), $zoneCountries);

                if (in_array($normalizedCountry, $normalizedZoneCountries)) {
                    $matchedZone = $zone;
                    break;
                }

                if (strpos($normalizedCountry, ' - ') !== false) {
                    $parts = explode(' - ', $normalizedCountry);
                    $parentCountry = trim($parts[0]);
                    if (in_array($parentCountry, $normalizedZoneCountries)) {
                        $matchedZone = $zone;
                        break;
                    }
                }
            }
        }

        if ($matchedZone) {
            $zoneName = $matchedZone['name'] ?? 'Shipping';
            
            // Check zone-specific free shipping
            $zoneFreeThreshold = (float) ($matchedZone['free_threshold'] ?? 0);
            if ($zoneFreeThreshold > 0 && $subtotal >= $zoneFreeThreshold) {
                return ['cost' => 0, 'label' => 'Free shipping (' . $zoneName . ')'];
            }

            $baseCost = (float) ($matchedZone['cost'] ?? 0);
            $type = $matchedZone['type'] ?? 'order';

            if ($type === 'item' && !empty($matchedZone['rules'])) {
                $ruleCost = 0;
                $matchedRule = false;
                foreach ($matchedZone['rules'] as $rule) {
                    $min = (int) ($rule['min'] ?? 0);
                    $max = ($rule['max'] === '' || $rule['max'] === null) ? PHP_INT_MAX : (int) $rule['max'];
                    
                    if ($itemCount >= $min && $itemCount <= $max) {
                        $ruleCost = (float) ($rule['cost'] ?? 0);
                        $matchedRule = true;
                        break;
                    }
                }
                return [
                    'cost' => $matchedRule ? $ruleCost : $baseCost,
                    'label' => $zoneName
                ];
            }

            return ['cost' => $baseCost, 'label' => $zoneName];
        }

        // 3. Fallback to Global Flat Rate
        return [
            'cost' => (float) get_shop_option('shop_flat_rate_cost', 0),
            'label' => 'Flat rate'
        ];
    }
}

if (!function_exists('get_lazy_cart_tax')) {
    function get_lazy_cart_tax() {
        if (get_cms_option('shop_enable_tax', 0) != 1) return 0;
        $subtotal = get_lazy_cart_subtotal();
        $taxRate = (float) get_cms_option('shop_tax_rate', 0);
        return $subtotal * ($taxRate / 100);
    }
}

if (!function_exists('get_lazy_cart_total')) {
    function get_lazy_cart_total() {
        $cart = session()->get('lazy_cart', []);
        $subtotal = get_lazy_cart_subtotal();
        $shipping = get_lazy_cart_shipping(session()->get('lazy_shipping_country'));
        $tax = get_lazy_cart_tax();
        
        $coupons = session()->get('lazy_coupons', []);
        $totalDiscount = 0;
        $currentCart = $cart; // For sequential calculation if needed
        $isSequential = (int)get_shop_option('shop_coupon_stacking_policy', '1') == 1;
        $subtotal = get_lazy_cart_subtotal();
        $currentSubtotal = $subtotal;

        foreach ($coupons as $coupon) {
            $discount = get_lazy_coupon_discount_amount($coupon, $cart, $isSequential ? $currentSubtotal : $subtotal);
            $totalDiscount += $discount;
            $currentSubtotal -= $discount;
        }
        
        return max(0, $subtotal + $shipping + $tax - $totalDiscount);
    }
}

if (!function_exists('get_lazy_coupon_discount_amount')) {
    function get_lazy_coupon_discount_amount($coupon, $cart, $calcBaseSubtotal = null) {
        $amount = (float) ($coupon['amount'] ?? ($coupon['discount'] ?? 0));
        $couponType = $coupon['type'] ?? 'percent';
        $products = (array) ($coupon['products'] ?? []);
        $categories = (array) ($coupon['categories'] ?? []);
        
        // If NO restrictions, apply to the whole provided subtotal
        if (empty($products) && empty($categories)) {
            $base = $calcBaseSubtotal ?? get_lazy_cart_subtotal();
            if ($couponType === 'percent') {
                return $base * ($amount / 100);
            }
            return min($amount, $base);
        }

        // Fetch origin IDs for restricted products and categories for robust matching
        $restrictedProductOriginIds = [];
        if (!empty($products)) {
            $restrictedProductOriginIds = \Illuminate\Support\Facades\DB::table('posts')
                ->whereIn('id', $products)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        $restrictedCategoryOriginIds = [];
        if (!empty($categories)) {
            $restrictedCategoryOriginIds = \Illuminate\Support\Facades\DB::table('taxonomy_terms')
                ->whereIn('id', $categories)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        // Calculate discount
        $totalDiscount = 0;
        $eligibleSubtotal = 0;

        foreach ($cart as $item) {
            $productId = $item['id'] ?? 0;
            if (!$productId) continue;

            // Check Product Eligibility
            $matchProduct = false;
            if (!empty($restrictedProductOriginIds)) {
                $itemIdentity = \Illuminate\Support\Facades\DB::table('posts')
                    ->where('id', $productId)
                    ->selectRaw('COALESCE(origin_id, id) as identity')
                    ->value('identity');
                $matchProduct = in_array($itemIdentity, $restrictedProductOriginIds);
            }
            
            // Check Category Eligibility
            $matchCategory = false;
            if (!empty($restrictedCategoryOriginIds)) {
                $itemCategoryIdentities = \Illuminate\Support\Facades\DB::table('post_taxonomy_term')
                    ->join('taxonomy_terms', 'post_taxonomy_term.taxonomy_term_id', '=', 'taxonomy_terms.id')
                    ->where('post_taxonomy_term.post_id', $productId)
                    ->where('taxonomy_terms.taxonomy_slug', 'product_cat')
                    ->selectRaw('COALESCE(taxonomy_terms.origin_id, taxonomy_terms.id) as identity')
                    ->pluck('identity')
                    ->toArray();
                $matchCategory = !empty(array_intersect($itemCategoryIdentities, $restrictedCategoryOriginIds));
            }

            $isEligible = false;
            if (empty($restrictedProductOriginIds) && empty($restrictedCategoryOriginIds)) {
                $isEligible = true;
            } else {
                $isEligible = $matchProduct || $matchCategory;
            }

            if ($isEligible) {
                $qty = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['sale_price'] ?? $item['price']);
                
                if ($couponType === 'percent') {
                    $eligibleSubtotal += $price * $qty;
                } elseif ($couponType === 'fixed_product') {
                    $totalDiscount += $amount * $qty;
                } else { // fixed_cart
                    $eligibleSubtotal += $price * $qty;
                }
            }
        }

        if ($couponType === 'percent') {
            return $eligibleSubtotal * ($amount / 100);
        } elseif ($couponType === 'fixed_product') {
            return $totalDiscount;
        } else { // fixed_cart
            return min($amount, $eligibleSubtotal);
        }
    }
}

if (!function_exists('get_lazy_image_url')) {
    function get_lazy_image_url($path, $default = 'https://via.placeholder.com/300?text=No+Image') {
        if (empty($path)) return $default;
        if (str_starts_with($path, 'http')) return $path;
        
        // Check common paths
        if (file_exists(public_path($path))) return asset($path);
        if (file_exists(public_path('storage/' . $path))) return asset('storage/' . $path);
        
        return asset('storage/' . $path); // Fallback to storage
    }

}

/**
 * Register Special Text Element for Lazy Builder
 */
add_lazy_filter('lazy_builder_elements', function($elements) {
    $elements['text_block'] = [
        'type' => 'text_block',
        'name' => 'Text Block',
        'icon' => 'fa fa-align-left',
        'template' => 'cms-dashboard::frontend.builder.elements.text-block',
        'fields' => [
            // General
            'content' => ['type' => 'wysiwyg', 'label' => 'Content', 'default' => '<p>your content is here...</p>'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16],
            'fontSizeUnit' => ['type' => 'select', 'label' => 'Unit', 'options' => ['px' => 'px', 'em' => 'em', 'rem' => 'rem'], 'default' => 'px'],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Text Align',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'],
                'default' => 'center'
            ],
            
            // Design - Typography
            'fontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 20],
            'fontSizeUnit' => ['type' => 'text', 'label' => 'Size Unit', 'default' => 'px'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400'],
            'lineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '1.5'],
            'letterSpacing' => ['type' => 'number', 'label' => 'Letter Spacing', 'default' => 0],
            'textTransform' => [
                'type' => 'select',
                'label' => 'Text Transform',
                'options' => ['none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize'],
                'default' => 'none'
            ],
            
            // Design - Colors
            'color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#333333'],
            'hoverColor' => ['type' => 'color', 'label' => 'Hover Color', 'default' => ''],
            
            // Design - Spacing
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0],
            'marginLeft' => ['type' => 'number', 'label' => 'Margin Left', 'default' => 0],
            'marginRight' => ['type' => 'number', 'label' => 'Margin Right', 'default' => 0],
            'paddingTop' => ['type' => 'number', 'label' => 'Padding Top', 'default' => 10],
            'paddingRight' => ['type' => 'number', 'label' => 'Padding Right', 'default' => 0],
            'paddingBottom' => ['type' => 'number', 'label' => 'Padding Bottom', 'default' => 10],
            'paddingLeft' => ['type' => 'number', 'label' => 'Padding Left', 'default' => 0],

            // Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true]
            ],
            'cssClass' => ['type' => 'text', 'default' => ''],
            'cssId' => ['type' => 'text', 'default' => ''],
        ]
    ];
    return $elements;
});

if (!function_exists('get_lazy_builder_fonts')) {
    function get_lazy_builder_fonts($layout, &$fonts = []) {
        if (empty($layout) || !is_array($layout)) return $fonts;
        
        foreach ($layout as $item) {
            if (isset($item['settings']['fontFamily']) && !empty($item['settings']['fontFamily']) && $item['settings']['fontFamily'] !== 'inherit') {
                // Extract only family name before comma if exists
                $family = trim(explode(',', $item['settings']['fontFamily'])[0]);
                // Remove quotes if present
                $family = trim($family, "'\"");
                $fonts[] = $family;
            }
            
            // Check nested columns/elements
            if (isset($item['columns'])) {
                get_lazy_builder_fonts($item['columns'], $fonts);
            }
            if (isset($item['elements'])) {
                get_lazy_builder_fonts($item['elements'], $fonts);
            }
        }
        return array_unique($fonts);
    }
}

/**
 * Register Button Element for Lazy Builder
 */
add_lazy_filter('lazy_builder_elements', function($elements) {
    $elements['button'] = [
        'type' => 'button',
        'name' => 'Button',
        'icon' => 'fa fa-hand-pointer',
        'template' => 'cms-dashboard::frontend.builder.elements.button',
        'fields' => [
            // General
            'text' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Click Here'],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => '#'],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self'
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center'
            ],
            
            // Design - Typography
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16, 'tab' => 'design'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '600', 'tab' => 'design'],
            'textTransform' => ['type' => 'select', 'label' => 'Text Transform', 'options' => ['none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize'], 'default' => 'none', 'tab' => 'design'],
            
            // Design - Colors & Gradients
            'buttonStyle' => ['type' => 'text', 'default' => 'default', 'tab' => 'design'],
            'color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#ffffff', 'tab' => 'design'],
            'bgColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#0091ea', 'tab' => 'design'],
            'hoverColor' => ['type' => 'color', 'label' => 'Text Hover Color', 'default' => '#ffffff', 'tab' => 'design'],
            'hoverBgColor' => ['type' => 'color', 'label' => 'BG Hover Color', 'default' => '#007cc0', 'tab' => 'design'],
            
            'bgGradientStartColor' => ['type' => 'color', 'default' => '#0091ea', 'tab' => 'design'],
            'bgGradientEndColor'   => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientStartPosition' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'bgGradientEndPosition'   => ['type' => 'number', 'default' => 100, 'tab' => 'design'],
            'bgGradientType'          => ['type' => 'text', 'default' => 'linear', 'tab' => 'design'],
            'bgGradientAngle'         => ['type' => 'number', 'default' => 180, 'tab' => 'design'],
            'bgGradientHoverStartColor' => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientHoverEndColor'   => ['type' => 'color', 'default' => '#005fa3', 'tab' => 'design'],
            
            // Design - Spacing & Border
            'paddingTop' => ['type' => 'number', 'label' => 'Padding Top', 'default' => 12, 'tab' => 'design'],
            'paddingBottom' => ['type' => 'number', 'label' => 'Padding Bottom', 'default' => 12, 'tab' => 'design'],
            'paddingLeft' => ['type' => 'number', 'label' => 'Padding Left', 'default' => 30, 'tab' => 'design'],
            'paddingRight' => ['type' => 'number', 'label' => 'Padding Right', 'default' => 30, 'tab' => 'design'],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius', 'default' => 5, 'tab' => 'design'],
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 10, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 10, 'tab' => 'design'],
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design'
            ],
            'borderSizeTop' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeRight' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeBottom' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeLeft' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderColor' => ['type' => 'color', 'default' => '#000000', 'tab' => 'design'],
            'buttonSize' => ['type' => 'text', 'default' => 'medium', 'tab' => 'design'],
            'buttonSpan' => ['type' => 'boolean', 'default' => false, 'tab' => 'design'],
            'icon' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'iconPosition' => ['type' => 'text', 'default' => 'left', 'tab' => 'design'],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ]
    ];
    return $elements;
});

/**
 * Register Menu Element for Lazy Builder
 */
add_lazy_filter('lazy_builder_elements', function($elements) {
    $menus = [];
    try {
        $menus = \Illuminate\Support\Facades\DB::table('navigation_menus')->pluck('name', 'id')->toArray();
    } catch (\Exception $e) {}

    $elements['menu'] = [
        'type' => 'menu',
        'name' => 'Menu',
        'icon' => 'fa fa-bars',
        'template' => 'cms-dashboard::frontend.builder.elements.menu',
        'fields' => [
            // General
            'menuId' => [
                'type' => 'select',
                'label' => 'Select Menu',
                'options' => $menus,
                'default' => count($menus) > 0 ? array_key_first($menus) : '',
                'tab' => 'design'
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'options' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'],
                'default' => 'horizontal',
                'tab' => 'design'
            ],
            'transitionTime' => [
                'type' => 'range',
                'label' => 'Transition Time (s)',
                'default' => 0.3,
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'tab' => 'design'
            ],
            'submenuSpace' => [
                'type' => 'number',
                'label' => 'Space Between Main Menu and Submenu (px)',
                'default' => 10,
                'tab' => 'design'
            ],
            'showArrows' => [
                'type' => 'select',
                'label' => 'Menu Arrows',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
                'tab' => 'design'
            ],
            
            // Design - Typography
            'fontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit', 'tab' => 'design'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16, 'tab' => 'design'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400', 'tab' => 'design'],
            'lineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '', 'tab' => 'design'],
            'letterSpacing' => ['type' => 'text', 'label' => 'Letter Spacing', 'default' => '', 'tab' => 'design'],
            'textTransform' => ['type' => 'text', 'label' => 'Text Transform', 'default' => 'none', 'tab' => 'design'],

            // Design - Menu Item Styling
            'itemPaddingTop' => ['type' => 'number', 'label' => 'Item Padding Top', 'default' => 0, 'tab' => 'design'],
            'itemPaddingRight' => ['type' => 'number', 'label' => 'Item Padding Right', 'default' => 0, 'tab' => 'design'],
            'itemPaddingBottom' => ['type' => 'number', 'label' => 'Item Padding Bottom', 'default' => 0, 'tab' => 'design'],
            'itemPaddingLeft' => ['type' => 'number', 'label' => 'Item Padding Left', 'default' => 0, 'tab' => 'design'],
            'itemSpacing' => ['type' => 'number', 'label' => 'Item Spacing', 'default' => 0, 'tab' => 'design'],
            'itemBorderRadius' => ['type' => 'number', 'label' => 'Item Border Radius', 'default' => 0, 'tab' => 'design'],
            'itemTransition' => ['type' => 'number', 'label' => 'Item Transition', 'default' => 0.3, 'tab' => 'design'],
            
            'itemBgColor' => ['type' => 'color', 'label' => 'Item Background Color', 'default' => 'transparent', 'tab' => 'design'],
            'itemBgColorHover' => ['type' => 'color', 'label' => 'Item Background Color Hover', 'default' => 'transparent', 'tab' => 'design'],
            'itemColor' => ['type' => 'color', 'label' => 'Item Text Color', 'default' => '#333333', 'tab' => 'design'],
            'itemColorHover' => ['type' => 'color', 'label' => 'Item Text Color Hover', 'default' => '#0091ea', 'tab' => 'design'],
            
            'itemBorderSizeTop' => ['type' => 'number', 'label' => 'Item Border Size Top', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeRight' => ['type' => 'number', 'label' => 'Item Border Size Right', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeBottom' => ['type' => 'number', 'label' => 'Item Border Size Bottom', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeLeft' => ['type' => 'number', 'label' => 'Item Border Size Left', 'default' => 0, 'tab' => 'design'],
            
            'itemBorderSizeTopHover' => ['type' => 'number', 'label' => 'Item Border Size Top Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeRightHover' => ['type' => 'number', 'label' => 'Item Border Size Right Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeBottomHover' => ['type' => 'number', 'label' => 'Item Border Size Bottom Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeLeftHover' => ['type' => 'number', 'label' => 'Item Border Size Left Hover', 'default' => 0, 'tab' => 'design'],
            
            'itemBorderColor' => ['type' => 'color', 'label' => 'Item Border Color', 'default' => '#eeeeee', 'tab' => 'design'],
            'itemBorderColorHover' => ['type' => 'color', 'label' => 'Item Border Color Hover', 'default' => '#0091ea', 'tab' => 'design'],
            
            // Design - Sub Menu Styling
            'showArrows' => ['type' => 'text', 'label' => 'Show Arrows', 'default' => 'yes', 'tab' => 'submenu'],
            'submenuDirection' => ['type' => 'text', 'label' => 'Expand Direction', 'default' => 'right', 'tab' => 'submenu'],
            'submenuTransition' => ['type' => 'text', 'label' => 'Expand Transition', 'default' => 'fade', 'tab' => 'submenu'],
            'submenuMinWidth' => ['type' => 'text', 'label' => 'Min Width', 'default' => '200px', 'tab' => 'submenu'],
            'submenuMaxWidth' => ['type' => 'text', 'label' => 'Max Width', 'default' => '220px', 'tab' => 'submenu'],
            'submenuSpace' => ['type' => 'number', 'label' => 'Submenu Space', 'default' => 10, 'tab' => 'submenu'],
            
            // Submenu Typography
            'submenuFontFamily' => ['type' => 'text', 'label' => 'Submenu Font Family', 'default' => 'inherit', 'tab' => 'submenu'],
            'submenuFontSize' => ['type' => 'text', 'label' => 'Submenu Font Size', 'default' => '14px', 'tab' => 'submenu'],
            'submenuFontWeight' => ['type' => 'text', 'label' => 'Submenu Font Weight', 'default' => '400', 'tab' => 'submenu'],
            'submenuLineHeight' => ['type' => 'text', 'label' => 'Submenu Line Height', 'default' => '', 'tab' => 'submenu'],
            'submenuLetterSpacing' => ['type' => 'text', 'label' => 'Submenu Letter Spacing', 'default' => '', 'tab' => 'submenu'],
            'submenuTextTransform' => ['type' => 'text', 'label' => 'Submenu Text Transform', 'default' => 'none', 'tab' => 'submenu'],
            'submenuTextAlign' => ['type' => 'text', 'label' => 'Submenu Text Align', 'default' => 'left', 'tab' => 'submenu'],
            
            // Submenu Item Styling
            'submenuPaddingTop' => ['type' => 'number', 'label' => 'Submenu Padding Top', 'default' => 10, 'tab' => 'submenu'],
            'submenuPaddingRight' => ['type' => 'number', 'label' => 'Submenu Padding Right', 'default' => 20, 'tab' => 'submenu'],
            'submenuPaddingBottom' => ['type' => 'number', 'label' => 'Submenu Padding Bottom', 'default' => 10, 'tab' => 'submenu'],
            'submenuPaddingLeft' => ['type' => 'number', 'label' => 'Submenu Padding Left', 'default' => 20, 'tab' => 'submenu'],
            
            'submenuBorderRadiusTopLeft' => ['type' => 'number', 'label' => 'Submenu BR TL', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusTopRight' => ['type' => 'number', 'label' => 'Submenu BR TR', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusBottomRight' => ['type' => 'number', 'label' => 'Submenu BR BR', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusBottomLeft' => ['type' => 'number', 'label' => 'Submenu BR BL', 'default' => 4, 'tab' => 'submenu'],
            
            'submenuBoxShadow' => ['type' => 'text', 'label' => 'Box Shadow', 'default' => 'no', 'tab' => 'submenu'],
            'submenuShadowColor' => ['type' => 'color', 'label' => 'Shadow Color', 'default' => 'rgba(0,0,0,0.12)', 'tab' => 'submenu'],
            'submenuShadowH' => ['type' => 'number', 'label' => 'Shadow H', 'default' => 0, 'tab' => 'submenu'],
            'submenuShadowV' => ['type' => 'number', 'label' => 'Shadow V', 'default' => 15, 'tab' => 'submenu'],
            'submenuShadowBlur' => ['type' => 'number', 'label' => 'Shadow Blur', 'default' => 35, 'tab' => 'submenu'],
            'submenuShadowSpread' => ['type' => 'number', 'label' => 'Shadow Spread', 'default' => 0, 'tab' => 'submenu'],
            
            'submenuSeparatorColor' => ['type' => 'color', 'label' => 'Separator Color', 'default' => 'rgba(0,0,0,0.05)', 'tab' => 'submenu'],
            'submenuBgColor' => ['type' => 'color', 'label' => 'Submenu BG', 'default' => '#ffffff', 'tab' => 'submenu'],
            'submenuTextColor' => ['type' => 'color', 'label' => 'Submenu Text', 'default' => '#333333', 'tab' => 'submenu'],
            'submenuTextColorHover' => ['type' => 'color', 'label' => 'Submenu Text Hover', 'default' => '#0091ea', 'tab' => 'submenu'],

            // Mobile Menu Styling
            'mobileCollapseBreakpoint' => ['type' => 'text', 'label' => 'Collapse to Mobile Breakpoint', 'default' => 'tablet', 'tab' => 'mobile'],
            'mobileMenuMode' => ['type' => 'text', 'label' => 'Mobile Menu Mode', 'default' => 'collapsed', 'tab' => 'mobile'],
            'mobileMenuExpandMode' => ['type' => 'select', 'label' => 'Mobile Menu Expand Mode', 'default' => 'full-width-static', 'tab' => 'mobile', 'options' => ['full-width-static' => 'Full Width - Static', 'full-width-absolute' => 'Full Width - Absolute', 'sidebar' => 'Sidebar']],
            'mobileMenuSidebarSide' => ['type' => 'select', 'label' => 'Sidebar Side', 'default' => 'left', 'tab' => 'mobile', 'options' => ['left' => 'Left', 'right' => 'Right']],
            'mobileMenuOpeningMode' => ['type' => 'text', 'label' => 'Mobile Menu Opening Mode', 'default' => 'toggle', 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingTop' => ['type' => 'number', 'label' => 'Trigger Padding Top', 'default' => 10, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingRight' => ['type' => 'number', 'label' => 'Trigger Padding Right', 'default' => 15, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingBottom' => ['type' => 'number', 'label' => 'Trigger Padding Bottom', 'default' => 10, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingLeft' => ['type' => 'number', 'label' => 'Trigger Padding Left', 'default' => 15, 'tab' => 'mobile'],
            'mobileMenuTriggerBgColor' => ['type' => 'color', 'label' => 'Trigger Background Color', 'default' => '#ffffff', 'tab' => 'mobile'],
            'mobileMenuTriggerTextColor' => ['type' => 'color', 'label' => 'Trigger Text Color', 'default' => '#333333', 'tab' => 'mobile'],
            'mobileMenuTriggerText' => ['type' => 'text', 'label' => 'Trigger Text', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuTriggerExpandIcon' => ['type' => 'text', 'label' => 'Trigger Expand Icon', 'default' => 'fa-bars', 'tab' => 'mobile'],
            'mobileMenuTriggerCollapseIcon' => ['type' => 'text', 'label' => 'Trigger Collapse Icon', 'default' => 'fa-times', 'tab' => 'mobile'],
            'mobileMenuTriggerFontSize' => ['type' => 'text', 'label' => 'Trigger Font Size', 'default' => '16px', 'tab' => 'mobile'],
            'mobileMenuTriggerHorizontalAlign' => ['type' => 'text', 'label' => 'Trigger Horizontal Align', 'default' => 'flex-start', 'tab' => 'mobile'],

            'mobileMenuItemMinHeight' => ['type' => 'number', 'label' => 'Mobile Menu Item Minimum Height', 'default' => 65, 'tab' => 'mobile'],
            'mobileMenuItemPaddingTop' => ['type' => 'number', 'label' => 'Item Padding Top', 'default' => 12, 'tab' => 'mobile'],
            'mobileMenuItemPaddingBottom' => ['type' => 'number', 'label' => 'Item Padding Bottom', 'default' => 12, 'tab' => 'mobile'],
            'mobileMenuItemPaddingLeft' => ['type' => 'number', 'label' => 'Item Padding Left', 'default' => 20, 'tab' => 'mobile'],
            'mobileMenuItemPaddingRight' => ['type' => 'number', 'label' => 'Item Padding Right', 'default' => 20, 'tab' => 'mobile'],
            'mobileMenuTextAlign' => ['type' => 'text', 'label' => 'Mobile Menu Text Align', 'default' => 'left', 'tab' => 'mobile'],
            'mobileMenuIndentSubmenus' => ['type' => 'text', 'label' => 'Mobile Menu Indent Submenus', 'default' => 'on', 'tab' => 'mobile'],
            
            'mobileMenuFontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit', 'tab' => 'mobile'],
            'mobileMenuFontSize' => ['type' => 'text', 'label' => 'Font Size', 'default' => '16px', 'tab' => 'mobile'],
            'mobileMenuFontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400', 'tab' => 'mobile'],
            'mobileMenuLineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuLetterSpacing' => ['type' => 'text', 'label' => 'Letter Spacing', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuTextTransform' => ['type' => 'text', 'label' => 'Text Transform', 'default' => 'none', 'tab' => 'mobile'],

            'mobileMenuSeparatorColor' => ['type' => 'color', 'label' => 'Separator Color', 'default' => 'rgba(0,0,0,0.05)', 'tab' => 'mobile'],
            'mobileMenuBgColor' => ['type' => 'color', 'label' => 'Menu Background', 'default' => '#ffffff', 'tab' => 'mobile'],
            'mobileMenuBgColorHover' => ['type' => 'color', 'label' => 'Menu Background Hover', 'default' => '#f8f9fa', 'tab' => 'mobile'],
            'mobileMenuTextColor' => ['type' => 'color', 'label' => 'Menu Text Color', 'default' => '#333333', 'tab' => 'mobile'],
            'mobileMenuTextColorHover' => ['type' => 'color', 'label' => 'Menu Text Hover', 'default' => '#0091ea', 'tab' => 'mobile'],

            // Margins (Simplified per user request)
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0, 'tab' => 'design'],
            
            // Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design'
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ]
    ];
    return $elements;
});

/**
 * Register Image Element for Lazy Builder
 */
add_lazy_filter('lazy_builder_elements', function($elements) {
    $elements['image'] = [
        'type' => 'image',
        'name' => 'Image',
        'icon' => 'fa fa-image',
        'template' => 'cms-dashboard::frontend.builder.elements.image',
        'fields' => [
            // General
            'url' => ['type' => 'media', 'label' => 'Image URL', 'default' => ''],
            'alt' => ['type' => 'text', 'label' => 'Alt Text', 'default' => ''],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => ''],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Link Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self'
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center'
            ],
            
            // Design - Dimensions
            'width' => ['type' => 'number', 'label' => 'Width', 'default' => '', 'tab' => 'design'],
            'widthUnit' => ['type' => 'text', 'default' => 'px', 'tab' => 'design'],
            'maxWidth' => ['type' => 'number', 'label' => 'Max Width', 'default' => 100, 'tab' => 'design'],
            'maxWidthUnit' => ['type' => 'text', 'default' => '%', 'tab' => 'design'],
            
            // Design - Spacing & Border
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0, 'tab' => 'design'],
            'marginRight' => ['type' => 'number', 'label' => 'Margin Right', 'default' => 0, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0, 'tab' => 'design'],
            'marginLeft' => ['type' => 'number', 'label' => 'Margin Left', 'default' => 0, 'tab' => 'design'],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius', 'default' => 0, 'tab' => 'design'],
            'borderRadiusUnit' => ['type' => 'text', 'default' => 'px', 'tab' => 'design'],
            'borderSizeTop' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeRight' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeBottom' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeLeft' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderColor' => ['type' => 'color', 'default' => 'transparent', 'tab' => 'design'],
            'hoverType' => ['type' => 'select', 'label' => 'Hover Effect', 'default' => 'none', 'tab' => 'design'],
            
            // Visibility & Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design'
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ]
    ];
    return $elements;
});