<?php

/**
 * Theme template tags: views, widgets, forms and search.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Form;
use FalconCms\Core\Models\Widget;

if (!function_exists('falcon_theme_parent')) {
    /**
     * The parent named in a theme's theme.json, if it declares one.
     *
     * Read from the app copy first and the packaged copy second, the same order the service
     * provider uses when it loads the theme.
     */
    function falcon_theme_parent(string $theme): ?string
    {
        static $cache = [];
        if (array_key_exists($theme, $cache)) {
            return $cache[$theme];
        }

        foreach ([resource_path("views/themes/{$theme}/theme.json"), __DIR__."/../../resources/views/themes/{$theme}/theme.json"] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $json = json_decode((string) file_get_contents($path), true);
            $parent = is_array($json) ? ($json['parent'] ?? null) : null;

            return $cache[$theme] = ($parent && $parent !== $theme) ? (string) $parent : null;
        }

        return $cache[$theme] = null;
    }
}

if (!function_exists('falcon_theme_view')) {
    /**
     * Resolve a theme view name for the active theme, mirroring the frontend
     * controller's resolution: app-level theme → package theme → the parent theme (for a
     * child theme) → falcon-theme fallback. Usable anywhere (e.g. the 404 renderer, and the
     * layout shop templates extend, so they wear whichever theme is active).
     */
    function falcon_theme_view(string $view, ?string $fallback = null): string
    {
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        $candidates = ["themes.{$activeTheme}.{$view}", "falcon-cms::themes.{$activeTheme}.{$view}"];
        if ($parentTheme = falcon_theme_parent($activeTheme)) {
            $candidates[] = "themes.{$parentTheme}.{$view}";
            $candidates[] = "falcon-cms::themes.{$parentTheme}.{$view}";
        }
        $candidates[] = "falcon-cms::themes.falcon-theme.{$view}";
        foreach ($candidates as $candidate) {
            if (view()->exists($candidate)) {
                return $candidate;
            }
        }
        if ($fallback && $fallback !== $view) {
            return falcon_theme_view($fallback);
        }

        return "falcon-cms::themes.falcon-theme.{$view}";
    }
}

if (!function_exists('render_falcon_widgets')) {
    function render_falcon_widgets($area)
    {
        $currentLocale = app()->getLocale();
        $query = Widget::forArea($area);

        // 1. Filter by lang_code
        $widgets = $query->where(function ($q) use ($currentLocale) {
            $q->where('lang_code', $currentLocale)->orWhereNull('lang_code');
        })->get();

        $output = '';
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        foreach ($widgets as $widget) {
            // Resolution order mirrors FrontendController::resolveThemeView():
            // 1. Published theme widget (non-namespaced): resources/views/themes/{theme}/widgets/{type}
            // 2. Package theme widget (namespaced):       falcon-cms::themes.{theme}.widgets.{type}
            // 3. Package default widget (namespaced):     falcon-cms::frontend.widgets.{type}
            $publishedThemeWidget = "themes.{$activeTheme}.widgets.{$widget->type}";
            $packageThemeWidget = "falcon-cms::themes.{$activeTheme}.widgets.{$widget->type}";
            $falconThemeWidget = "falcon-cms::themes.falcon-theme.widgets.{$widget->type}";
            $defaultWidget = "falcon-cms::frontend.widgets.{$widget->type}";

            if (view()->exists($publishedThemeWidget)) {
                $output .= view($publishedThemeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($packageThemeWidget)) {
                $output .= view($packageThemeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($falconThemeWidget)) {
                $output .= view($falconThemeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($defaultWidget)) {
                $output .= view($defaultWidget, ['widget' => $widget])->render();
            } else {
                // Fallback for custom HTML or simple text
                if ($widget->type === 'custom_html') {
                    $content = $widget->settings['content'] ?? '';
                    // Process Shortcodes if any system exists (placeholder for now)
                    $content = do_falcon_shortcode($content);

                    $output .= '<div class="widget mb-12">';
                    if ($widget->title) {
                        $output .= '<h4 class="widget-title">'.e($widget->title).'</h4>';
                    }
                    $output .= $content;
                    $output .= '</div>';
                }
            }
        }

        return $output;
    }
}

if (!function_exists('falcon_search_form')) {
    function falcon_search_form($placeholder = 'Search...')
    {
        $url = route('frontend.search');
        $output = '<form action="'.$url.'" method="GET" class="relative lazy-search-form">';
        $output .= '<input type="text" name="s" placeholder="'.e($placeholder).'" class="w-full bg-slate-50 border border-slate-200 rounded-full px-5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">';
        $output .= '<button type="submit" class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-primary text-white rounded-full text-xs font-bold hover:bg-primary/90 transition-colors uppercase">Search</button>';
        $output .= '</form>';

        return $output;
    }
}

if (!function_exists('the_falcon_search_form')) {
    function the_falcon_search_form($placeholder = 'Search...')
    {
        echo falcon_search_form($placeholder);
    }
}

if (!function_exists('render_falcon_form')) {
    function render_falcon_form($slug)
    {
        try {
            $form = Form::where('slug', $slug)->where('status', true)->first();
            if (!$form || empty($form->fields)) {
                return '';
            }

            return view('falcon-cms::frontend.form-renderer', ['form' => $form])->render();
        } catch (Exception $e) {
            return '';
        }
    }
}
