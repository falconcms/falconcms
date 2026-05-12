<?php

use Illuminate\Support\Facades\DB;

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
        try {
            // Check if it's builder shortcode format
            if (is_string($content) && \Acme\CmsDashboard\Services\BuilderShortcodeConverter::isBuilderShortcode($content)) {
                $content = \Acme\CmsDashboard\Services\BuilderShortcodeConverter::shortcodesToJson($content);
            }

            $layout = is_string($content) ? json_decode($content, true) : $content;
            
            // If it's not an array, it might be raw HTML or non-builder content
            if (!is_array($layout)) {
                return do_lazy_shortcode($content);
            }
            
            $rendered = view('cms-dashboard::frontend.builder.render', ['layout' => $layout])->render();
            return do_lazy_shortcode($rendered);
        } catch (\Exception $e) {
            // Fallback to raw content if processing fails
            return do_lazy_shortcode($content);
        }
    }
}

if (!function_exists('the_lazy_content')) {
    function the_lazy_content($content) { echo get_lazy_content($content); }
}

if (!function_exists('get_lazy_posts')) {
    function get_lazy_posts($args = []) {
        $defaults = [
            'post_type' => 'post',
            'limit' => 10,
            'order' => 'desc',
            'orderby' => 'created_at',
            'status' => 'published',
            'category' => null,
            'tag' => null,
            'paginate' => false,
            'lang' => null,
        ];
        $args = array_merge($defaults, $args);
        $query = \Acme\CmsDashboard\Models\Post::where('type', $args['post_type']);
        
        $lang = $args['lang'] ?: app()->getLocale();
        $query->where('lang_code', $lang);

        if ($args['status']) {
            $query->where('status', $args['status']);
        }
        if ($args['category']) {
            $query->whereHas('categories', function($q) use ($args) {
                $q->where('slug', $args['category']);
            });
        }
        if ($args['tag']) {
            $query->whereHas('tags', function($q) use ($args) {
                $q->where('slug', $args['tag']);
            });
        }
        $query->orderBy($args['orderby'], $args['order']);
        
        if ($args['paginate']) {
            return $query->paginate($args['limit']);
        }
        return $query->limit($args['limit'])->get();
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