<?php

/**
 * Plugins: whether one is active, and URLs for its assets.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Support\PluginManager;

if (!function_exists('falcon_plugin_active')) {
    /**
     * Whether a plugin is loaded on this request. Themes use it to show plugin-dependent
     * markup (a cart icon, say) only while the plugin is on.
     */
    function falcon_plugin_active(string $slug): bool
    {
        try {
            return isset(app(PluginManager::class)->loaded()[$slug]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_post_type_available')) {
    /**
     * Whether a post type's frontend pages may be served: false for a type that belongs to a
     * plugin which is switched off — products without the shop plugin answer 404, the way they
     * would on a site that never had a shop. Plugins claim their types through the
     * falcon_plugin_post_types filter (type => plugin slug).
     */
    function falcon_post_type_available(string $type): bool
    {
        $owners = (array) apply_falcon_filters('falcon_plugin_post_types', ['product' => 'falcon-shop']);
        $owner = $owners[$type] ?? null;

        return $owner === null || falcon_plugin_active((string) $owner);
    }
}

if (!function_exists('falcon_unavailable_post_types')) {
    /**
     * The post types whose plugin is switched off — what search, live search and the sitemap
     * leave out, so they never list a page that would answer 404.
     *
     * @return list<string>
     */
    function falcon_unavailable_post_types(): array
    {
        $owners = (array) apply_falcon_filters('falcon_plugin_post_types', ['product' => 'falcon-shop']);

        return array_values(array_filter(array_keys($owners), fn ($type) => !falcon_post_type_available((string) $type)));
    }
}

if (!function_exists('falcon_plugin_asset')) {
    /**
     * URL of a file in an active plugin's assets/ folder, versioned by its mtime so a changed
     * file is fetched fresh. Empty when the plugin is not active or the file does not exist —
     * a page then simply leaves the tag out instead of pointing at a 404.
     *
     *   falcon_plugin_asset('falcon-shop', 'frontend/js/cart.js')
     */
    function falcon_plugin_asset(string $slug, string $path): string
    {
        $manifest = falcon_plugin_active($slug) ? app(PluginManager::class)->loaded()[$slug] : null;
        if (!$manifest) {
            return '';
        }
        $file = $manifest['dir'].DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.ltrim($path, '/\\');
        if (!is_file($file)) {
            return '';
        }

        return route('falcon.plugin-asset', ['slug' => $slug, 'path' => ltrim(str_replace('\\', '/', $path), '/')]).'?v='.filemtime($file);
    }
}
