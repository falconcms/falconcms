<?php

namespace FalconShop;

use Illuminate\Support\Facades\File;

/**
 * Where a shop template comes from.
 *
 * A theme can override any shop template by holding a file at the same relative path —
 * ecommerce/cart.blade.php, single-product.blade.php, archive-product.blade.php … — in the
 * active theme or, for a child theme, its parent (the site's copy first, then the packaged
 * one). Anything a theme does not override comes from this plugin's own default
 * (resources/views/frontend/...).
 *
 * Each default template opens with a version comment, {{-- @version 1.0.0 --}}. Bump it when
 * a template changes in a way a theme's copy has to follow (a new variable, a form field the
 * checkout needs, a renamed hook). A copy carrying an older version — or none, as a copy made
 * before versions existed — is reported as outdated by Site Health and `shop:template`.
 * `php artisan shop:template <name>` copies a default into the theme with its version.
 */
final class Templates
{
    /** The view to render for a shop template name such as "ecommerce.cart". */
    public static function view(string $name): string
    {
        return self::override($name)['view'] ?? "falcon-shop::frontend.{$name}";
    }

    /** Absolute path of the plugin's default templates (make:theme copies from here). */
    public static function defaultsPath(): string
    {
        return dirname(__DIR__).'/resources/views/frontend';
    }

    /**
     * Every template a theme can override, as dotted names ("ecommerce.cart"): the product
     * pages at the top level and the ecommerce/ folder. The partials are the plugin's own.
     *
     * @return list<string>
     */
    public static function overridable(): array
    {
        $root = self::defaultsPath();
        $names = [];
        foreach ([...File::glob($root.'/*.blade.php'), ...File::glob($root.'/ecommerce/*.blade.php')] as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
            $names[] = str_replace('/', '.', substr($relative, 0, -strlen('.blade.php')));
        }
        sort($names);

        return $names;
    }

    /** "ecommerce/cart.blade.php", "ecommerce.cart" or "ecommerce/cart" → "ecommerce.cart". */
    public static function normalize(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        if (str_ends_with($name, '.blade.php')) {
            $name = substr($name, 0, -strlen('.blade.php'));
        }

        return str_replace('/', '.', trim($name, '/'));
    }

    /** The plugin's default file for a template. */
    public static function defaultFile(string $name): string
    {
        return self::defaultsPath().'/'.str_replace('.', '/', $name).'.blade.php';
    }

    /** The @version a template file declares, or null if it declares none. */
    public static function versionOf(string $file): ?string
    {
        $head = (string) @file_get_contents($file, false, null, 0, 1024);

        return preg_match('/\{\{--\s*@version\s+([0-9][0-9A-Za-z.\-]*)\s*--\}\}/', $head, $m) ? $m[1] : null;
    }

    /**
     * The theme's override of a template, if the active theme (or its parent) holds one.
     *
     * @return array{view: string, file: string, theme: string}|null
     */
    public static function override(string $name, ?string $theme = null): ?array
    {
        $theme ??= (string) get_cms_option('active_theme', 'falcon-theme');

        foreach (array_filter([$theme, falcon_theme_parent($theme)]) as $t) {
            foreach (["themes.{$t}.{$name}", "falcon-cms::themes.{$t}.{$name}"] as $candidate) {
                if (view()->exists($candidate)) {
                    return ['view' => $candidate, 'file' => view()->getFinder()->find($candidate), 'theme' => $t];
                }
            }
        }

        return null;
    }

    /**
     * Every overridable template with the theme's copy of it, if any, and whether that copy is
     * behind the plugin's default.
     *
     * @return list<array{name: string, version: ?string, override: ?array{view: string, file: string, theme: string}, override_version: ?string, outdated: bool}>
     */
    public static function status(?string $theme = null): array
    {
        $rows = [];
        foreach (self::overridable() as $name) {
            $version = self::versionOf(self::defaultFile($name));
            $override = self::override($name, $theme);
            $overrideVersion = $override ? self::versionOf($override['file']) : null;
            $rows[] = [
                'name' => $name,
                'version' => $version,
                'override' => $override,
                'override_version' => $overrideVersion,
                'outdated' => $override !== null && ($overrideVersion === null
                    || ($version !== null && version_compare($overrideVersion, $version, '<'))),
            ];
        }

        return $rows;
    }
}
