<?php

use Carbon\Carbon;
use Composer\InstalledVersions;
use FalconCms\Core\Core\HookManager;
use FalconCms\Core\Models\ActivityLog;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\CustomerAddress;
use FalconCms\Core\Models\CustomTaxonomy;
use FalconCms\Core\Models\Form;
use FalconCms\Core\Models\Language;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostMeta;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\ProductCategory;
use FalconCms\Core\Models\ProductData;
use FalconCms\Core\Models\Promotion;
use FalconCms\Core\Models\TaxonomyTerm;
use FalconCms\Core\Models\Widget;
use FalconCms\Core\Models\Wishlist;
use FalconCms\Core\Pro\LicenseGateway;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Services\EcommerceData;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (!defined('FALCON_CMS_VERSION')) {
    $__versionFile = __DIR__.'/../version.json';
    $__versionData = file_exists($__versionFile) ? json_decode(file_get_contents($__versionFile), true) : [];
    define('FALCON_CMS_VERSION', $__versionData['version'] ?? '1.2.0');
    unset($__versionFile, $__versionData);
}

if (!function_exists('falcon_elem_resp_css')) {
    /**
     * Generate responsive @media CSS for a builder element.
     *
     * @param  array  $s  Element settings (the raw array from $el['settings'])
     * @param  int  $bpSm  "Small" breakpoint (mobile max-width)
     * @param  int  $bpMed  "Medium" breakpoint (tablet max-width)
     * @param  array  $props  Property definitions, each:
     *                        [ 'prop' => 'fontSize', 'sel' => '.my-class',
     *                        'unitProp' => 'fontSizeUnit',  // optional – key for the unit setting
     *                        'css'      => 'font-size',     // optional – defaults to camelCase→kebab
     *                        ]
     * @return string Raw CSS (no <style> tags).  Empty string when nothing changed.
     */
    function falcon_elem_resp_css(array $s, int $bpSm, int $bpMed, array $props): string
    {
        $bpSm1 = $bpSm + 1;
        $css = '';

        foreach ([
            ['tablet', "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)"],
            ['mobile', "@media(max-width:{$bpSm}px)"],
        ] as [$dev, $mq]) {
            $bySel = [];

            foreach ($props as $p) {
                $prop = $p['prop'];
                $sel = $p['sel'];
                $unitProp = $p['unitProp'] ?? null;
                $cssProp = $p['css'] ?? strtolower(preg_replace('/([A-Z])/', '-$1', $prop));

                // Resolve responsive value (mobile cascades through tablet)
                $val = null;
                if ($dev === 'mobile') {
                    if (isset($s[$prop.'_mobile']) && (string) $s[$prop.'_mobile'] !== '') {
                        $val = (string) $s[$prop.'_mobile'];
                    } elseif (isset($s[$prop.'_tablet']) && (string) $s[$prop.'_tablet'] !== '') {
                        $val = (string) $s[$prop.'_tablet'];
                    }
                } else {
                    if (isset($s[$prop.'_tablet']) && (string) $s[$prop.'_tablet'] !== '') {
                        $val = (string) $s[$prop.'_tablet'];
                    }
                }
                if ($val === null) {
                    continue;
                }

                // Append unit when value is numeric and a unit property is declared
                if ($unitProp !== null && !preg_match('/[a-zA-Z%]/', $val)) {
                    $unit = $dev === 'mobile'
                        ? ($s[$unitProp.'_mobile'] ?? $s[$unitProp.'_tablet'] ?? $s[$unitProp] ?? 'px')
                        : ($s[$unitProp.'_tablet'] ?? $s[$unitProp] ?? 'px');
                    // Units must be safe CSS unit tokens only
                    $unit = preg_replace('/[^a-zA-Z%]/', '', (string) ($unit ?: 'px')) ?: 'px';
                    $val .= $unit;
                }

                // Strip characters that could break out of a CSS/style-tag context
                $val = preg_replace('/[<>"\']/', '', $val);
                $bySel[$sel][] = "{$cssProp}:{$val}!important";
            }

            // Build @media block
            $block = '';
            foreach ($bySel as $sel => $rules) {
                $block .= "{$sel}{".implode(';', $rules).'}';
            }
            if ($block !== '') {
                $css .= "{$mq}{{$block}}";
            }
        }

        return $css;
    }
}

if (!function_exists('falcon_max_upload_bytes')) {
    /**
     * The real maximum upload size (in bytes) the server allows — the smaller of
     * PHP's `upload_max_filesize` and `post_max_size`. Returns 0 when unlimited.
     */
    function falcon_max_upload_bytes(): int
    {
        $toBytes = function ($val): int {
            $val = trim((string) $val);
            if ($val === '') {
                return 0;
            }
            $last = strtolower($val[strlen($val) - 1]);
            $num = (int) $val;

            return match ($last) {
                'g' => $num * 1024 * 1024 * 1024,
                'm' => $num * 1024 * 1024,
                'k' => $num * 1024,
                default => $num,
            };
        };
        $limits = array_filter([
            $toBytes(ini_get('upload_max_filesize') ?: '0'),
            $toBytes(ini_get('post_max_size') ?: '0'), // 0 = unlimited → ignored
        ], fn ($v) => $v > 0);

        return $limits ? min($limits) : 0;
    }
}

if (!function_exists('falcon_max_upload_human')) {
    /** Human-readable version of falcon_max_upload_bytes() (e.g. "40 MB", "1 GB"). */
    function falcon_max_upload_human(): string
    {
        $bytes = falcon_max_upload_bytes();
        if ($bytes <= 0) {
            return 'unlimited';
        }
        if ($bytes >= 1024 * 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024 / 1024 / 1024, 1), '0'), '.').' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024).' MB';
        }

        return round($bytes / 1024).' KB';
    }
}

if (!function_exists('falcon_export_response')) {
    /** Stream a typed payload as a pretty .json download (used by all import/export tools). */
    function falcon_export_response(string $type, array $data, string $filename)
    {
        $payload = [
            '_type' => $type,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'data' => $data,
        ];
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '-', $filename) ?: 'export';

        return response()->json(
            $payload,
            200,
            ['Content-Disposition' => 'attachment; filename="'.$name.'.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}

if (!function_exists('falcon_read_import')) {
    /** Validate + decode an uploaded .json export of the given type. Returns the `data`, or null. */
    function falcon_read_import(Request $request, string $field, string $expectedType): ?array
    {
        if (!$request->hasFile($field)) {
            return null;
        }
        $file = $request->file($field);
        if (strtolower($file->getClientOriginalExtension()) !== 'json') {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (!is_array($decoded) || ($decoded['_type'] ?? null) !== $expectedType || !array_key_exists('data', $decoded)) {
            return null;
        }

        return is_array($decoded['data']) ? $decoded['data'] : null;
    }
}

if (!function_exists('falcon_sanitize_html')) {
    /**
     * Strip dangerous HTML from user-supplied rich-text content.
     * Removes <script> blocks, on* event handlers, and javascript: URLs.
     * Safe structural HTML (<a>, <img>, <iframe>, etc.) is preserved.
     */
    function falcon_sanitize_html(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove <script> blocks entirely (opening tag + content + closing tag)
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);

        // Strip on* event handler attributes from every HTML tag.
        // [\s\/]+ allows both whitespace and / as attribute separator (e.g. <img/onerror=...>).
        $html = preg_replace_callback('/<[a-z][^>]*>/i', static function (array $m): string {
            return preg_replace('/[\s\/]+on[a-z][a-z0-9]*\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i', '', $m[0]);
        }, $html);

        // Replace javascript: protocol in href/src/action/formaction with safe #
        $html = preg_replace(
            '/(\b(?:href|src|action|formaction)\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/i',
            '$1#$2',
            $html
        );

        return $html;
    }
}

if (!function_exists('lazy_sanitize_builder_json')) {
    /**
     * Recursively walks every node of a decoded builder layout array and applies
     * falcon_sanitize_html() to every string value. Numbers, booleans, and null are
     * passed through unchanged. Call this on the decoded `layout` array before
     * json_encode-ing it to the database.
     */
    function lazy_sanitize_builder_json(mixed $node): mixed
    {
        if (is_array($node)) {
            return array_map('lazy_sanitize_builder_json', $node);
        }
        if (is_string($node)) {
            return falcon_sanitize_html($node);
        }

        return $node;
    }
}

if (!function_exists('falcon_cms_installed_version')) {
    /**
     * The installed code version. version.json ships inside the package and is bumped
     * on every release, so it is the source of truth — it is accurate for both real
     * Composer installs and path-repo/dev installs (where Composer's reported version
     * can be a stale pinned alias). Composer's version is only a last-resort fallback.
     */
    function falcon_cms_installed_version(): string
    {
        // Two possible sources, each unreliable in a different way:
        //  - version.json ships in the code, but a release that forgot to bump it
        //    reports an old number (e.g. v2.0.0 still said 1.8.3).
        //  - Composer's installed version is exact for real installs but bogus for
        //    path-repo / symlink dev installs (reports a stale pinned alias).
        // Taking the HIGHER of the two is correct in every case: a real install on a
        // stale-version.json release is rescued by Composer, and a dev/symlink install
        // (where Composer lies low) is rescued by version.json.
        $fromJson = (defined('FALCON_CMS_VERSION') && preg_match('/^v?\d+\.\d+\.\d+$/', FALCON_CMS_VERSION))
            ? ltrim(FALCON_CMS_VERSION, 'v')
            : null;

        $fromComposer = null;
        if (class_exists(InstalledVersions::class)) {
            try {
                $clean = ltrim((string) InstalledVersions::getPrettyVersion('falconcms/falconcms'), 'v');
                if (preg_match('/^\d+\.\d+\.\d+$/', $clean)) {
                    $fromComposer = $clean;
                }
            } catch (Throwable $e) {
            }
        }

        if ($fromJson && $fromComposer) {
            return version_compare($fromComposer, $fromJson, '>') ? $fromComposer : $fromJson;
        }

        return $fromJson ?? $fromComposer ?? (defined('FALCON_CMS_VERSION') ? FALCON_CMS_VERSION : '1.2.0');
    }
}

if (!function_exists('lazy_check_update')) {
    function lazy_check_update(bool $force = false): array
    {
        $cacheKey = 'falcon_cms_update_check';
        if (!$force && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $current = falcon_cms_installed_version();
        $result = ['current' => $current, 'latest' => null, 'has_update' => false, 'url' => null, 'checked_at' => now()->toDateTimeString()];

        try {
            $res = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'FalconCMS/'.$current])
                ->get('https://repo.packagist.org/p2/falconcms/falconcms.json');

            if ($res->successful()) {
                $versions = $res->json('packages.falconcms/falconcms') ?? [];
                foreach ($versions as $v) {
                    $ver = ltrim($v['version'] ?? '', 'v');
                    if (preg_match('/^\d+\.\d+\.\d+$/', $ver)) {
                        $result['latest'] = $ver;
                        $result['url'] = 'https://packagist.org/packages/falconcms/falconcms';
                        break;
                    }
                }
            }
        } catch (Exception $e) {
        }

        if (!$result['latest']) {
            try {
                // Try GitHub Releases first, fall back to Tags (tags exist even without formal releases)
                $gh = Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/'.$current])
                    ->get('https://api.github.com/repos/falconcms/falconcms/releases/latest');
                if ($gh->successful() && $gh->json('tag_name')) {
                    $tag = ltrim($gh->json('tag_name'), 'v');
                    if ($tag) {
                        $result['latest'] = $tag;
                        $result['url'] = $gh->json('html_url');
                    }
                }
            } catch (Exception $e) {
            }
        }

        if (!$result['latest']) {
            try {
                // Fall back to tags list when no formal GitHub Release exists
                $gh = Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/'.$current])
                    ->get('https://api.github.com/repos/falconcms/falconcms/tags');
                if ($gh->successful()) {
                    foreach ($gh->json() ?? [] as $t) {
                        $tag = ltrim($t['name'] ?? '', 'v');
                        if (preg_match('/^\d+\.\d+\.\d+$/', $tag)) {
                            $result['latest'] = $tag;
                            $result['url'] = 'https://github.com/falconcms/falconcms/releases/tag/v'.$tag;
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }

        if ($result['latest']) {
            $result['has_update'] = version_compare($result['latest'], $result['current'], '>');
        }

        cache()->put($cacheKey, $result, now()->addHours(6));

        return $result;
    }
}

if (!function_exists('falcon_icon_sets')) {
    /**
     * The icon libraries the builder can pick from, beyond Font Awesome.
     *
     * Every set here is CLASS-BASED (`<i class="bi bi-house">`), which is why they need no
     * changes in any renderer — the existing icon markup already emits whatever class string
     * was picked, in the builder canvas and on the front-end alike.
     *
     * Weight is handled at both ends: the builder fetches a set's icon list and stylesheet only
     * when its tab is opened, and a page loads a set's stylesheet only when that page actually
     * uses one of its icons (see falcon_icon_set_links()).
     *
     * @return array<string,array{label:string,class:string,base:string,asset:string}>
     */
    function falcon_icon_sets(): array
    {
        // `class` is the regex for the set's icon classes — Boxicons ships three families
        // (bx-, bxs-, bxl-), the others one. `base` is the extra class the set needs alongside.
        return [
            'bootstrap' => ['label' => 'Bootstrap', 'class' => 'bi-',      'base' => 'bi', 'asset' => 'css/bootstrap-icons.min.css'],
            'remix' => ['label' => 'Remix',     'class' => 'ri-',      'base' => '',   'asset' => 'css/remixicon.min.css'],
            'boxicons' => ['label' => 'Boxicons',  'class' => 'bx[sl]?-', 'base' => 'bx', 'asset' => 'css/boxicons.min.css'],
            // Lucide's own font claims every `icon-*` class with !important, which would hijack
            // unrelated theme classes — the bundled copy is renamespaced to `lui-` for that reason.
            'lucide' => ['label' => 'Lucide',    'class' => 'lui-',      'base' => '',   'asset' => 'css/lucide.min.css'],
        ];
    }
}

if (!function_exists('falcon_icon_set_names')) {
    /**
     * Every icon class in one set, read from its bundled stylesheet.
     *
     * Deriving the list from the CSS that will actually render the icon means the picker can
     * never offer something the shipped font can't draw, and upgrading a set's assets updates
     * the picker with no code change. The parse is cached, since it is the same for every user.
     *
     * @return array<int,string> full class strings, e.g. "bi bi-house" / "ri-home-line"
     */
    function falcon_icon_set_names(string $set): array
    {
        $def = falcon_icon_sets()[$set] ?? null;
        if (!$def) {
            return [];
        }

        $file = __DIR__.'/../public/'.ltrim(str_replace('css/', 'assets/css/', $def['asset']), '/');
        if (!is_file($file)) {
            return [];
        }

        $key = 'falcon_icons_'.$set.'_'.(int) @filemtime($file);
        $get = function () use ($file, $def) {
            $css = (string) @file_get_contents($file);
            if ($css === '') {
                return [];
            }
            // Icon rules look like `.bi-house::before{content:"\f425"}`; the prefix keeps us off
            // the set's utility classes (sizing, spin, rotation…).
            preg_match_all('/\.('.$def['class'].'[a-z0-9-]+)\s*:{1,2}before\s*\{/i', $css, $m);
            $names = array_values(array_unique($m[1]));
            sort($names);
            // Sets differ: Bootstrap and Boxicons need their base class alongside the icon class,
            // Remix carries everything on the one class.
            $base = $def['base'] !== '' ? $def['base'].' ' : '';

            return array_map(fn ($n) => $base.$n, $names);
        };

        try {
            return Cache::remember($key, 86400, $get);
        } catch (Throwable $e) {
            return $get();
        }
    }
}

if (!function_exists('falcon_icon_set_links')) {
    /**
     * Stylesheet URLs for the icon sets a chunk of rendered HTML actually uses.
     *
     * This is what keeps the extra libraries free: a page that uses no Bootstrap icon never
     * downloads the Bootstrap icon CSS or its font. Font Awesome is deliberately not in here —
     * the themes use it in their own markup, so it stays loaded site-wide.
     *
     * @return array<int,string>
     */
    function falcon_icon_set_links(string $html): array
    {
        $links = [];
        foreach (falcon_icon_sets() as $def) {
            // Match the icon class itself (bi-house, ri-home-line, bxs-star) rather than the base
            // class, so an unrelated word in the markup can't pull a whole font in.
            // The lookbehind keeps the match on a whole class token: without it an unrelated
            // word like "big-box" would pull a whole icon font onto the page.
            if (preg_match('/class="[^"]*(?<![a-z0-9-])'.$def['class'].'[a-z0-9-]+/i', $html)) {
                $links[] = asset('vendor/falcon-cms/'.$def['asset']);
            }
        }

        return $links;
    }
}

if (!function_exists('falcon_fontawesome_aliases')) {
    /**
     * Alternate names for the bundled Font Awesome icons, keyed by icon name.
     *
     * Font Awesome keeps every old name working as an alias of the current one — the CSS
     * groups them on one rule (`.fa-ambulance,.fa-truck-medical{--fa:"\f0f9"}`). The picker
     * only ever listed the current names, so searching for a name you remember ("ambulance",
     * "trash-alt", "car") found nothing even though the icon is right there. Each icon now
     * carries its whole name group as search keywords.
     *
     * Read from the shipped stylesheet, so upgrading the Font Awesome assets updates this
     * automatically and it can never claim a name the bundled fonts can't render.
     *
     * @return array<string,string> icon name → its other names, space separated
     */
    function falcon_fontawesome_aliases(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $file = __DIR__.'/../public/assets/css/font-awesome.all.min.css';
        $css = is_file($file) ? (string) @file_get_contents($file) : '';
        if ($css === '') {
            return $map;
        }

        // Only multi-name rules matter; a lone name is already searchable as itself.
        if (preg_match_all('/((?:\.fa-[a-z0-9-]+,)+\.fa-[a-z0-9-]+)\{--fa:/i', $css, $m)) {
            foreach ($m[1] as $group) {
                $names = [];
                foreach (explode(',', $group) as $sel) {
                    $n = preg_replace('/^fa-/', '', ltrim(trim($sel), '.'));
                    if ($n !== '') {
                        $names[] = $n;
                    }
                }
                if (count($names) < 2) {
                    continue;
                }
                foreach ($names as $n) {
                    $others = array_values(array_diff($names, [$n]));
                    if ($others) {
                        $map[$n] = implode(' ', $others);
                    }
                }
            }
        }

        return $map;
    }
}

if (!function_exists('falcon_google_fonts')) {
    /**
     * The single shared Google-Fonts catalog for every typography UI (Customizer,
     * Falcon Builder, …). Reads one data file so adding a font in the catalog surfaces
     * it everywhere at once — no per-place font lists to keep in sync.
     *
     * @return array<int,array{family:string,category:string,variants:array<int,string>}>
     */
    function falcon_google_fonts(): array
    {
        static $flat = null;
        if ($flat !== null) {
            return $flat;
        }
        $file = __DIR__.'/../resources/google-fonts.json';
        $grouped = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $flat = [];
        if (is_array($grouped)) {
            foreach ($grouped as $category => $list) {
                foreach ((array) $list as $f) {
                    if (!empty($f['family'])) {
                        $flat[] = [
                            'family' => $f['family'],
                            'category' => $category,
                            'variants' => $f['variants'] ?? ['400'],
                        ];
                    }
                }
            }
        }

        return $flat;
    }
}

if (!function_exists('falcon_font_awesome_icons')) {
    /**
     * The full set of FontAwesome icon classes bundled with the theme's icon font — every
     * Solid, Regular and Brand icon it ships. The Falcon Builder's own icon picker has always
     * offered this whole set; the Menu Item Options icon picker had a hand-picked ~100-icon
     * subset instead, so an icon that existed in the builder simply wasn't there when picking
     * one for a menu item. One shared list means adding an icon anywhere adds it everywhere.
     *
     * @return array<int,string>
     */
    function falcon_font_awesome_icons(): array
    {
        static $icons = null;
        if ($icons !== null) {
            return $icons;
        }
        $file = __DIR__.'/../resources/font-awesome-icons.json';
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return $icons = is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('falcon_all_builder_icons')) {
    /**
     * Every icon the builder offers, from every set, as one flat combined list — Font Awesome
     * plus each of falcon_icon_sets() (Bootstrap, Remix, Boxicons, Lucide).
     *
     * The builder's own icon picker keeps these in separate tabs, so it fetches each set's
     * icons on demand as a tab is opened. A picker that has no tabs — like Menu Item Options —
     * needs them as one list instead: offering "all our icons" means all of them, not just the
     * Font Awesome ones, or a Bootstrap/Remix/Boxicons/Lucide icon picked for a menu item
     * would show as a blank glyph everywhere this list is the only source.
     *
     * @return array<int,string>
     */
    function falcon_all_builder_icons(): array
    {
        $icons = falcon_font_awesome_icons();
        foreach (array_keys(falcon_icon_sets()) as $set) {
            $icons = array_merge($icons, falcon_icon_set_names($set));
        }

        return $icons;
    }
}

if (!function_exists('falcon_pro_installed_version')) {
    /** The installed falconcms/pro package version, or null when Pro isn't installed. */
    function falcon_pro_installed_version(): ?string
    {
        try {
            if (class_exists(InstalledVersions::class)
                && InstalledVersions::isInstalled('falconcms/pro')) {
                return ltrim((string) InstalledVersions::getPrettyVersion('falconcms/pro'), 'v');
            }
        } catch (Throwable $e) {
        }

        return null;
    }
}

if (!function_exists('falcon_pro_check_update')) {
    /**
     * Check whether a newer falconcms/pro release is available. The Pro package lives in
     * a private repo (no Packagist), so the latest version is published as a small public
     * manifest (config falcon-options.pro_version_url). Mirrors lazy_check_update().
     *
     * @return array{installed:?string,latest:?string,has_update:bool,installed_pro:bool,url:?string,min_cms:?string,checked_at:string}
     */
    function falcon_pro_check_update(bool $force = false): array
    {
        $cacheKey = 'falcon_pro_update_check';
        if (!$force && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $installed = falcon_pro_installed_version();
        $result = [
            'installed' => $installed,
            'installed_pro' => $installed !== null,
            'latest' => null,
            'has_update' => false,
            'url' => null,
            'min_cms' => null,
            'checked_at' => now()->toDateTimeString(),
        ];

        $manifestUrl = (string) config('falcon-options.pro_version_url', 'https://falconcms.com/pro-version.json');

        try {
            $res = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'FalconCMS-Pro-Check'])
                ->get($manifestUrl);
            if ($res->successful()) {
                $data = $res->json();
                $latest = ltrim((string) ($data['version'] ?? ''), 'v');
                if (preg_match('/^\d+\.\d+\.\d+$/', $latest)) {
                    $result['latest'] = $latest;
                    $result['url'] = $data['url'] ?? null;
                    $result['min_cms'] = $data['min_cms'] ?? null;
                }
            }
        } catch (Exception $e) {
        }

        // Only meaningful when Pro is actually installed AND a newer version exists.
        if ($result['installed_pro'] && $result['latest'] && $installed) {
            $result['has_update'] = version_compare($result['latest'], $installed, '>');
        }

        cache()->put($cacheKey, $result, now()->addHours(6));

        return $result;
    }
}

if (!function_exists('_falcon_cms_options_store')) {
    function &_falcon_cms_options_store(): array
    {
        static $store = ['loaded' => false, 'data' => []];

        return $store;
    }
}

if (!function_exists('get_cms_option')) {
    function get_cms_option($key, $default = null)
    {
        try {
            $store = &_falcon_cms_options_store();

            // Bulk-load all settings on first use. Cross-request cache (Redis/file/etc.)
            // so most requests skip the DB entirely; per-request static store avoids
            // repeat cache hits. Invalidated by forget_cms_options_cache() on every write.
            if (!$store['loaded']) {
                $store['data'] = Cache::remember(
                    'falcon:cms_options',
                    now()->addHour(),
                    function () {
                        $data = [];
                        foreach (DB::table('cms_settings')->get(['key', 'value']) as $row) {
                            $data[$row->key] = $row->value;
                        }

                        return $data;
                    }
                );
                $store['loaded'] = true;
            }

            $localeKey = $key.'_'.app()->getLocale();

            if (array_key_exists($localeKey, $store['data'])) {
                return $store['data'][$localeKey] ?? $default;
            }
            if (array_key_exists($key, $store['data'])) {
                return $store['data'][$key] ?? $default;
            }

            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('update_cms_option')) {
    function update_cms_option($key, $value)
    {
        try {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
            // Drop the cross-request cache and the per-request store so the new
            // value is picked up immediately (here and on the next request).
            forget_cms_options_cache();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('forget_cms_options_cache')) {
    /**
     * Invalidate the CMS options cache. Call after ANY direct write to the
     * cms_settings table so cached settings never go stale.
     */
    function forget_cms_options_cache(): void
    {
        try {
            Cache::forget('falcon:cms_options');
        } catch (Throwable $e) {
        }
        $store = &_falcon_cms_options_store();
        $store['loaded'] = false;
        $store['data'] = [];
    }
}

if (!function_exists('falcon_refresh_route_cache')) {
    /**
     * Rebuild the route cache, so a setting that routes are built from takes effect.
     *
     * The login and registration slugs are read in routes/web.php while the routes are
     * being registered. On a cached site that file never runs, so changing either one
     * did nothing: the new URL 404'd, the old one kept working, and nothing said why.
     *
     * Rebuilt rather than only cleared — a site that was cached stays cached, instead
     * of quietly losing the speed it was set up with. If the rebuild fails (a host with
     * no writable bootstrap/cache, say) the stale cache is dropped anyway: a site that
     * has to compile its routes each request is slower, but it is correct, and a login
     * URL that silently refuses to change is not something to leave in place.
     */
    function falcon_refresh_route_cache(): void
    {
        try {
            if (!app()->routesAreCached()) {
                return;
            }

            Artisan::call('route:cache');
        } catch (Throwable $e) {
            try {
                Artisan::call('route:clear');
            } catch (Throwable $inner) {
                // Nothing further to try; the next deploy will rebuild it.
            }
        }
    }
}

if (!function_exists('cms_timezone')) {
    /**
     * The CMS display/input timezone chosen in Settings → General.
     * Storage stays UTC; this is only used to render/interpret dates for the admin.
     */
    function cms_timezone(): string
    {
        try {
            $tz = get_cms_option('timezone');
            if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
                return $tz;
            }
        } catch (Throwable $e) {
        }

        return config('app.timezone') ?: 'UTC';
    }
}

if (!function_exists('cms_now')) {
    /** Current time in the CMS timezone. Use for display and for building day-boundary queries. */
    function cms_now(): Illuminate\Support\Carbon
    {
        return Illuminate\Support\Carbon::now(cms_timezone());
    }
}

if (!function_exists('cms_date')) {
    /**
     * Format a datetime in the CMS timezone.
     * Accepts Carbon, DateTime, or a date string. Returns '—' for null/empty.
     */
    function cms_date($dt, string $format = 'M j, Y H:i'): string
    {
        if (!$dt) {
            return '—';
        }
        $c = $dt instanceof Carbon ? $dt : Illuminate\Support\Carbon::parse($dt);

        return $c->timezone(cms_timezone())->format($format);
    }
}

if (!function_exists('lazy_timezone_list')) {
    /**
     * All PHP timezones grouped by region, each labelled with its CURRENT UTC offset
     * (e.g. "(UTC+06:00) Asia/Dhaka"). Offsets are computed live, so DST/changes stay correct.
     *
     * @return array<string, array<string,string>> region => [identifier => label]
     */
    function lazy_timezone_list(): array
    {
        $groups = [];
        foreach (timezone_identifiers_list() as $tz) {
            try {
                $offset = (new DateTime('now', new DateTimeZone($tz)))->getOffset();
            } catch (Throwable $e) {
                continue;
            }
            $sign = $offset < 0 ? '-' : '+';
            $abs = abs($offset);
            $label = sprintf('(UTC%s%02d:%02d) %s', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60), $tz);
            $region = strpos($tz, '/') !== false ? explode('/', $tz)[0] : 'Other';
            $groups[$region][$tz] = $label;
        }

        return $groups;
    }
}

if (!function_exists('falcon_normalize_publish')) {
    /**
     * Normalise a save payload's publish fields:
     *  - interpret the incoming naive `published_at` in the CMS timezone and convert to UTC for storage,
     *  - then set `status` (scheduled vs published) from that UTC time on the server.
     * Keeps the DB in UTC while letting the admin work in their chosen timezone.
     */
    function falcon_normalize_publish(array $data): array
    {
        if (!empty($data['published_at'])) {
            try {
                $data['published_at'] = Illuminate\Support\Carbon::parse($data['published_at'], cms_timezone())
                    ->utc()->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
            }
        }
        $data['status'] = Post::resolveStatusForSchedule(
            $data['status'] ?? null,
            $data['published_at'] ?? null
        );

        return $data;
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
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('get_acpt_field')) {
    /**
     * Resolve a single ACPT / custom-field value for a post by the field's slug.
     * The "Field Slug" entered in the builder maps to the `custom_fields.name` column.
     * Returns the raw stored value, or $default when the field or its value is absent.
     *
     * Used by falcon_resolve_dynamic_value() for the `acpt_custom` dynamic source.
     */
    function get_acpt_field($post, string $slug, $default = '')
    {
        try {
            $postId = is_object($post) ? ($post->id ?? null) : $post;
            if (!$postId || $slug === '') {
                return $default;
            }

            $value = DB::table('post_custom_field_values')
                ->join('custom_fields', 'post_custom_field_values.field_id', '=', 'custom_fields.id')
                ->where('post_custom_field_values.post_id', $postId)
                ->where('custom_fields.name', $slug)
                ->value('post_custom_field_values.value');

            return $value !== null ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('get_post_custom_fields')) {
    /**
     * Resolve ALL custom fields that apply to a post (by its type's field groups) as a
     * keyed array [field_name => value]. Data-driven: it reads the field DEFINITIONS and
     * VALUES from the database at call time, so adding/removing a field in a field group
     * is reflected automatically everywhere — including the REST API — with no code change.
     *
     * JSON-encoded values (repeaters, galleries, checkboxes, etc.) are decoded to arrays.
     * Fields with no value yet are returned as null so consumers always see the schema.
     */
    function get_post_custom_fields($post): array
    {
        try {
            $post = is_object($post) ? $post : Post::find($post);
            if (!$post) {
                return [];
            }
            $type = $post->type;

            $groupIds = DB::table('custom_field_groups')
                ->where('is_active', 1)->get()
                ->filter(function ($g) use ($type) {
                    $rules = json_decode($g->rules ?? '', true);
                    $pt = is_array($rules) ? ($rules['post_type'] ?? null) : null;

                    return is_array($pt) ? in_array($type, $pt, true) : $pt === $type;
                })->pluck('id');

            if ($groupIds->isEmpty()) {
                return [];
            }

            $fields = DB::table('custom_fields')->whereIn('field_group_id', $groupIds)->orderBy('order')->get();
            $values = DB::table('post_custom_field_values')->where('post_id', $post->id)->pluck('value', 'field_id');

            $out = [];
            foreach ($fields as $f) {
                $raw = $values[$f->id] ?? null;
                if (is_string($raw) && strlen($raw) && in_array($raw[0], ['[', '{'], true)) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $raw = $decoded;
                    }
                }
                $out[$f->name] = $raw;
            }

            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_lazy_content')) {
    function get_lazy_content($content)
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
                return do_lazy_shortcode(falcon_sanitize_html((string) $content));
            }

            $data = ['layout' => $layout];
            // Expose current post context so dynamic sources (feature image, author, etc.) —
            // including dynamic backgrounds on containers/columns — resolve to the viewed post.
            // Guard against recursion: _lazy_layout_post_context() computes $postContent by
            // calling get_lazy_content($post->content) again, so only add the context at the
            // top level — otherwise a post whose content is rendered while it is the current
            // post (e.g. the Home page inside a footer section) loops forever.
            static $ctxDepth = 0;
            $cp = view()->getShared()['current_post'] ?? null;
            if ($cp && $ctxDepth === 0 && function_exists('_lazy_layout_post_context')) {
                $ctxDepth++;
                try {
                    $data += _lazy_layout_post_context($cp);
                } finally {
                    $ctxDepth--;
                }
            }

            $rendered = view('falcon-cms::frontend.builder.render', $data)->render();

            return do_lazy_shortcode($rendered);
        } catch (Exception $e) {
            Log::error('Falcon Builder Error: '.$e->getMessage());

            return do_lazy_shortcode(falcon_sanitize_html((string) $content));
        }
    }
}

if (!function_exists('_lazy_hex_to_rgba')) {
    function _lazy_hex_to_rgba(string $hex, float $opacity = 1): string
    {
        if (empty($hex) || $hex === 'transparent') {
            return 'transparent';
        }
        if (strpos($hex, 'rgba') !== false) {
            return $hex;
        }
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return $opacity >= 1 ? "rgb({$r},{$g},{$b})" : "rgba({$r},{$g},{$b},{$opacity})";
    }
}

if (!function_exists('_lazy_parse_builder_layout')) {
    function _lazy_parse_builder_layout(string $raw): ?array
    {
        try {
            if (BuilderShortcodeConverter::isBuilderShortcode($raw)) {
                $raw = BuilderShortcodeConverter::shortcodesToJson($raw);
            }
            $layout = json_decode($raw, true);

            return is_array($layout) ? $layout : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('_lazy_render_layout')) {
    function _lazy_render_layout(array $layout): string
    {
        $data = ['layout' => $layout];

        // When a single post/page/product is being viewed, expose its data so the
        // dynamic Post elements (Content, Post Meta, Product Meta) placed inside a
        // Layout Builder section resolve to the current post — the same variables
        // the post-card renderer provides.
        $cp = view()->getShared()['current_post'] ?? null;
        if ($cp) {
            $data += _lazy_layout_post_context($cp);
        }

        $rendered = view('falcon-cms::frontend.builder.render', $data)->render();

        return do_lazy_shortcode($rendered);
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

if (!function_exists('_lazy_layout_post_context')) {
    /** Post-context variables consumed by the Post elements (mirrors the card renderer). */
    function _lazy_layout_post_context($post): array
    {
        if (!$post) {
            return [];
        }

        $img = $post->featured_image ?? null;
        if ($img && !str_starts_with((string) $img, 'http')) {
            $img = asset('storage/'.$img);
        }

        // Full, rendered content for the Content element (builder JSON → HTML, or classic).
        $fullContent = function_exists('get_lazy_content') ? get_lazy_content($post->content ?? '') : (string) ($post->content ?? '');
        $plain = trim(strip_tags($fullContent));
        $excerpt = $post->excerpt ?? (mb_strlen($plain) > 160 ? mb_substr($plain, 0, 160).'…' : $plain);

        return [
            'post' => $post,
            'postTitle' => $post->title ?? '',
            'postContent' => $fullContent,
            'postExcerpt' => $excerpt,
            'postPublishedAt' => $post->published_at ?? null,
            'postCreatedAt' => $post->created_at ?? null,
            'postAuthor' => optional($post->user)->name ?? '',
            'postFeaturedImage' => $img,
            'postPermalink' => function_exists('get_falcon_permalink') ? get_falcon_permalink($post) : '#',
            'postCategories' => $post->categories ?? collect(),
        ];
    }
}

if (!function_exists('_lazy_build_sticky_wrapper')) {
    /**
     * Build a sticky wrapper element around $content.
     * $settings is the settings array of the first sticky container/column.
     * $wrapperClass is the CSS class on the wrapper (e.g. falcon-builder-header).
     * $tag is the HTML tag (header|footer|div).
     */
    function _lazy_build_sticky_wrapper(string $content, array $settings, string $wrapperClass, string $tag): string
    {
        $offset = (int) ($settings['stickyOffset'] ?? 0);
        $zIndex = (int) ($settings['stickyZIndex'] ?? 100);
        $desktop = ($settings['stickyDesktop'] ?? true) !== false;
        $tablet = ($settings['stickyTablet'] ?? true) !== false;
        $mobile = ($settings['stickyMobile'] ?? true) !== false;
        $bgColor = $settings['stickyBgColor'] ?? '';
        $bgOpacity = (float) ($settings['stickyBgColorOpacity'] ?? 1);

        $bpSm = (int) get_cms_option('theme_small_screen_breakpoint', '800');
        $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
        $bpSm1 = $bpSm + 1;

        $sOn = "position:sticky;top:{$offset}px;z-index:{$zIndex};";
        $sOff = 'position:static;top:auto;z-index:auto;';

        $baseStyle = ($tag === 'header') ? 'width:100%;' : '';
        $wrapperStyle = $baseStyle.($desktop ? $sOn : '');

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
             ."<style>{$css}</style>"
             .$content
             ."</{$tag}>";
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
            $style = $tag === 'header' ? ' style="width:100%;"' : '';

            return "<{$tag} class=\"{$wrapperClass}\"{$style}>{$content}</{$tag}>";
        }

        // Find the index of the first sticky container (check container + column settings)
        $stickyIndex = null;
        $stickySettings = null;
        foreach ($layout as $i => $container) {
            $cs = $container['settings'] ?? [];
            if (!empty($cs['sticky'])) {
                $stickyIndex = $i;
                $stickySettings = $cs;
                break;
            }
            foreach ($container['columns'] ?? [] as $col) {
                $cls = $col['settings'] ?? [];
                if (!empty($cls['sticky'])) {
                    $stickyIndex = $i;
                    $stickySettings = $cls;
                    break 2;
                }
            }
        }

        if ($stickySettings === null) {
            // Nothing sticky — simple wrapper
            $style = $tag === 'header' ? ' style="width:100%;"' : '';

            return "<{$tag} class=\"{$wrapperClass}\"{$style}>"
                 ._lazy_render_layout($layout)
                 ."</{$tag}>";
        }

        // Render containers BEFORE the first sticky one in a plain above-wrapper
        $html = '';
        if ($stickyIndex > 0) {
            $html .= '<div class="'.$wrapperClass.'-above" style="width:100%;">'
                   ._lazy_render_layout(array_slice($layout, 0, $stickyIndex))
                   .'</div>';
        }

        // Render sticky containers inside the sticky wrapper
        $stickyContent = _lazy_render_layout(array_slice($layout, $stickyIndex));
        $html .= _lazy_build_sticky_wrapper($stickyContent, $stickySettings, $wrapperClass, $tag);

        return $html;
    }
}

if (!function_exists('falcon_layout_context')) {
    /**
     * Request-scoped description of what the frontend is currently rendering,
     * used to decide which custom Layout (by its conditions) applies. The
     * frontend controllers set this before returning their view; the header/
     * footer resolvers read it while the view renders.
     *
     * Shape: ['kind' => 'home|single|archive|search', 'post_type' => ?, 'post_id' => ?, 'taxonomy' => ?]
     */
    function falcon_layout_context(?array $set = null): array
    {
        static $ctx = ['kind' => null];
        if ($set !== null) {
            $ctx = $set;
        }

        return $ctx;
    }
}

if (!function_exists('falcon_get_custom_layouts')) {
    /** All user-created custom layouts (name, conditions, per-slot assignments). */
    function falcon_get_custom_layouts(): array
    {
        $raw = get_cms_option('falcon_layouts', null);
        $layouts = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($layouts) ? array_values(array_filter($layouts, 'is_array')) : [];
    }
}

if (!function_exists('falcon_condition_target_matches')) {
    /** Does a single condition target match the current render context? */
    function falcon_condition_target_matches(string $target, array $ctx): bool
    {
        $kind = $ctx['kind'] ?? null;
        if ($target === 'entire_site') {
            return true;
        }
        if ($target === 'home') {
            return $kind === 'home';
        }
        if ($target === 'search') {
            return $kind === 'search';
        }
        if ($target === '404') {
            return $kind === '404';
        }
        if ($target === 'all_archives') {
            return $kind === 'archive';
        }
        if ($target === 'author_archive') {
            return $kind === 'archive' && ($ctx['archive_type'] ?? null) === 'author';
        }
        if (str_starts_with($target, 'all:')) {
            // All singular items of a post type (the front page counts if it is one).
            return in_array($kind, ['single', 'home'], true) && ($ctx['post_type'] ?? null) === substr($target, 4);
        }
        if (str_starts_with($target, 'singular:')) { // legacy alias of all:
            return in_array($kind, ['single', 'home'], true) && ($ctx['post_type'] ?? null) === substr($target, 9);
        }
        if (str_starts_with($target, 'archive:')) {
            return $kind === 'archive' && ($ctx['post_type'] ?? null) === substr($target, 8);
        }
        if (str_starts_with($target, 'tax:')) {
            return $kind === 'archive' && ($ctx['taxonomy'] ?? null) === substr($target, 4);
        }
        if (str_starts_with($target, 'taxonomy:')) { // legacy alias of tax:
            return $kind === 'archive' && ($ctx['taxonomy'] ?? null) === substr($target, 9);
        }
        if (str_starts_with($target, 'term:')) {
            [$tax, $id] = array_pad(explode(':', substr($target, 5), 2), 2, null);

            return $kind === 'archive'
                && ($ctx['taxonomy'] ?? null) === $tax
                && (int) ($ctx['term_id'] ?? 0) === (int) $id;
        }
        if (str_starts_with($target, 'author:')) {
            return $kind === 'archive'
                && ($ctx['archive_type'] ?? null) === 'author'
                && (int) ($ctx['author_id'] ?? 0) === (int) substr($target, 7);
        }
        if (str_starts_with($target, 'post:')) {
            return (int) ($ctx['post_id'] ?? 0) === (int) substr($target, 5);
        }

        return false;
    }
}

if (!function_exists('falcon_normalize_conditions')) {
    /** Normalise stored conditions to a list of ['mode'=>'include|exclude','target'=>string]. */
    function falcon_normalize_conditions($conditions): array
    {
        $out = [];
        foreach ((array) $conditions as $c) {
            if (is_string($c)) {                       // legacy flat target => include
                $out[] = ['mode' => 'include', 'target' => $c];
            } elseif (is_array($c) && !empty($c['target'])) {
                $out[] = ['mode' => ($c['mode'] ?? 'include') === 'exclude' ? 'exclude' : 'include', 'target' => (string) $c['target']];
            }
        }

        return $out;
    }
}

if (!function_exists('falcon_layout_matches')) {
    /**
     * Does a layout apply to the given render context? A layout shows where at
     * least one INCLUDE condition matches and no EXCLUDE condition matches.
     * With no include conditions it applies nowhere.
     */
    function falcon_layout_matches($conditions, array $ctx): bool
    {
        $anyInclude = false;
        $includeMatched = false;
        foreach (falcon_normalize_conditions($conditions) as $c) {
            $matches = falcon_condition_target_matches($c['target'], $ctx);
            if ($c['mode'] === 'exclude') {
                if ($matches) {
                    return false;
                }             // an exclusion always wins
            } else {
                $anyInclude = true;
                if ($matches) {
                    $includeMatched = true;
                }
            }
        }

        return $anyInclude && $includeMatched;
    }
}

if (!function_exists('falcon_layout_assigned_section')) {
    /**
     * Resolve the section that fills a layout slot for the current request.
     *
     * Model ("Global = Pages, Custom = rest"):
     *   • On a PAGE (single 'page' / front page): the Global Layout's explicit
     *     assignment wins, then a matching custom layout, then (header/footer only)
     *     the first published section as a legacy fallback, else the theme default.
     *   • Everywhere else (posts, CPTs, archives, search): only a custom layout
     *     whose conditions match applies; otherwise the theme default (null).
     *
     * Returns a published Post or null (null ⇒ theme renders its own default).
     */
    function falcon_layout_assigned_section(string $slot, string $type)
    {
        $ctx = falcon_layout_context();
        $isPage = in_array($ctx['kind'] ?? null, ['single', 'home'], true)
            && ($ctx['post_type'] ?? null) === 'page';

        // Normalise a stored assignment (legacy int, or ['id','active']) into
        // ['id','active']; the 'active' flag is this layout's own on/off switch.
        $entryOf = function ($v) {
            if (is_array($v) && !empty($v['id'])) {
                return ['id' => (int) $v['id'], 'active' => !array_key_exists('active', $v) || (bool) $v['active']];
            }
            if (is_numeric($v) && (int) $v > 0) {
                return ['id' => (int) $v, 'active' => true];
            }

            return null;
        };
        $resolve = function ($entry) use ($type) {
            if (!$entry || !$entry['active']) {
                return null;
            }

            return Post::where('id', $entry['id'])->where('type', $type)->first();
        };

        // Resolution cascade — a slot renders the FIRST active assignment found:
        //   custom layout (for its targeted content) → Global Layout → theme default.
        // A slot that's toggled OFF or has no section selected resolves to null at that level and
        // falls through to the next, so nothing "resurrects" a disabled section — it just yields.

        // The Global Layout's assignment for this slot, decoded once (used as the fallback below).
        $raw = get_cms_option('falcon_layout_global', null);
        $global = is_string($raw) ? json_decode($raw, true) : $raw;
        $global = is_array($global) ? $global : [];
        $globalSection = function () use ($global, $slot, $entryOf, $resolve) {
            return $resolve($entryOf($global[$slot] ?? null)); // active → section; off/invalid → null
        };

        // 1) PAGE context → the Global Layout owns pages; use it directly (no custom fallthrough).
        if ($isPage) {
            return $globalSection(); // active → section; off/unselected → null → theme default
        }

        // 2) Custom layouts whose conditions match this content — highest priority when active.
        foreach (falcon_get_custom_layouts() as $layout) {
            $conditions = is_array($layout['conditions'] ?? null) ? $layout['conditions'] : [];
            $entry = $entryOf($layout['assignments'][$slot] ?? null);
            if ($entry && falcon_layout_matches($conditions, $ctx)) {
                if ($section = $resolve($entry)) {
                    return $section;
                }
            }
        }

        // 3) No active custom assignment → fall back to the Global Layout's header/footer/etc.
        if ($section = $globalSection()) {
            return $section;
        }

        // 4) Global also off/unselected → theme default.
        return null;
    }
}

if (!function_exists('falcon_layout_slot_off')) {
    /**
     * Whether a Layout slot is EXPLICITLY turned off for the current context — i.e. a section was
     * selected for it but its toggle is inactive. This is the "render nothing" state. It is NOT
     * true when the slot has no section selected at all (that case falls back to the theme default).
     *
     * Three states, distinguished with falcon_layout_assigned_section():
     *   - assigned + active   → assigned_section() returns the section  (slot_off = false)
     *   - assigned + inactive → assigned_section() returns null         (slot_off = TRUE  → nothing)
     *   - not selected        → assigned_section() returns null         (slot_off = false → theme default)
     */
    function falcon_layout_slot_off(string $slot, string $type): bool
    {
        $ctx = falcon_layout_context();
        $isPage = in_array($ctx['kind'] ?? null, ['single', 'home'], true)
            && ($ctx['post_type'] ?? null) === 'page';

        // Mirror falcon_layout_assigned_section(): a valid entry has a section id; missing/0 → null.
        $entryOf = function ($v) {
            if (is_array($v) && !empty($v['id'])) {
                return ['id' => (int) $v['id'], 'active' => !array_key_exists('active', $v) || (bool) $v['active']];
            }
            if (is_numeric($v) && (int) $v > 0) {
                return ['id' => (int) $v, 'active' => true];
            }

            return null;
        };

        // PAGE → the Global Layout's assignment is authoritative.
        if ($isPage) {
            $raw = get_cms_option('falcon_layout_global', null);
            $global = is_string($raw) ? json_decode($raw, true) : $raw;
            $global = is_array($global) ? $global : [];
            if (array_key_exists($slot, $global)) {
                $e = $entryOf($global[$slot]);
                if ($e !== null) {
                    return !$e['active'];
                } // selected → off iff inactive
            }

            return false; // no section selected → not "off" (→ theme default)
        }

        // Custom layouts whose conditions match: first matching layout that HAS this slot selected wins.
        foreach (falcon_get_custom_layouts() as $layout) {
            $conditions = is_array($layout['conditions'] ?? null) ? $layout['conditions'] : [];
            if (!falcon_layout_matches($conditions, $ctx)) {
                continue;
            }
            $e = $entryOf($layout['assignments'][$slot] ?? null);
            if ($e !== null) {
                return !$e['active'];
            } // selected → off iff inactive
        }

        return false; // not selected in any matching layout → theme default
    }
}

if (!function_exists('falcon_layout_is_active')) {
    /**
     * True once the Layout Builder is actually in use — i.e. a Global Layout assignment or any
     * custom layout exists. When active, the site's header/title-bar/footer are controlled by
     * the Layout Builder, so a slot that is disabled or unassigned renders NOTHING (the theme's
     * built-in default chrome is only a fallback for sites that never touched the Layout Builder).
     */
    function falcon_layout_is_active(): bool
    {
        static $active = null;
        if ($active !== null) {
            return $active;
        }

        $global = get_cms_option('falcon_layout_global', null);
        $global = is_string($global) ? json_decode($global, true) : $global;
        if (is_array($global)) {
            foreach ($global as $v) {
                if ((is_array($v) && !empty($v['id'])) || (is_numeric($v) && (int) $v > 0)) {
                    return $active = true;
                }
            }
        }

        $custom = get_cms_option('falcon_layouts', null);
        $custom = is_string($custom) ? json_decode($custom, true) : $custom;
        if (is_array($custom) && !empty($custom)) {
            return $active = true;
        }

        return $active = false;
    }
}

if (!function_exists('get_falcon_header')) {
    function get_falcon_header()
    {
        $header = falcon_layout_assigned_section('header', 'falcon_header');
        if ($header) {
            return _lazy_builder_render_wrapper($header->content ?? '', 'header', 'falcon-builder-header');
        }

        return null;
    }
}

if (!function_exists('get_falcon_footer')) {
    function get_falcon_footer()
    {
        $footer = falcon_layout_assigned_section('footer', 'falcon_footer');
        if ($footer) {
            return _lazy_builder_render_wrapper($footer->content ?? '', 'footer', 'falcon-builder-footer');
        }

        return null;
    }
}

if (!function_exists('get_falcon_page_title_bar')) {
    function get_falcon_page_title_bar()
    {
        $ptb = falcon_layout_assigned_section('page_title_bar', 'falcon_ptb');
        if ($ptb) {
            return _lazy_builder_render_wrapper($ptb->content ?? '', 'div', 'falcon-builder-ptb');
        }

        return null;
    }
}

if (!function_exists('get_falcon_content')) {
    function get_falcon_content()
    {
        $content = falcon_layout_assigned_section('content', 'falcon_content');
        if ($content) {
            return _lazy_builder_render_wrapper($content->content ?? '', 'div', 'falcon-builder-content');
        }

        return null;
    }
}

if (!function_exists('falcon_theme_view')) {
    /**
     * Resolve a theme view name for the active theme, mirroring the frontend
     * controller's resolution: app-level theme → package theme → falcon-theme
     * fallback. Usable anywhere (e.g. the 404 renderer).
     */
    function falcon_theme_view(string $view, ?string $fallback = null): string
    {
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        foreach (["themes.{$activeTheme}.{$view}", "falcon-cms::themes.{$activeTheme}.{$view}", "falcon-cms::themes.falcon-theme.{$view}"] as $candidate) {
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

if (!function_exists('getUnitVal')) {
    function getUnitVal($val, $unit = 'px')
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_numeric($val)) {
            return $val.$unit;
        }

        return $val;
    }
}

if (!function_exists('falcon_visit_page')) {
    /**
     * Human-friendly page label for an analytics visit URL.
     * Strips the scheme + host (works for raw-IP visits too) and shows just the path;
     * for the homepage (root) it shows the site domain instead of a bare "/".
     */
    function falcon_visit_page($url)
    {
        $path = preg_replace('#^https?://[^/]+#i', '', (string) $url);
        if ($path === '' || $path === '/') {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);
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

if (!function_exists('the_lazy_content')) {
    function the_lazy_content($content)
    {
        echo get_lazy_content($content);
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
            $safeOrderby = in_array($args['orderby'], ['created_at', 'updated_at', 'title', 'views', 'menu_order', 'id'])
                ? $args['orderby'] : 'created_at';
            $query->orderBy($safeOrderby, $args['order']);
        }

        if ($args['paginate']) {
            return $query->paginate($args['limit'], ['*'], $args['page_name'] ?? 'page');
        }

        return $query->limit($args['limit'])->offset((int) $args['offset'])->get();
    }
}

if (!function_exists('the_lazy_pagination')) {
    function the_lazy_pagination($items, $view = null)
    {
        if (!($items instanceof LengthAwarePaginator)) {
            return '';
        }

        return $items->links($view);
    }
}

if (!function_exists('the_lazy_loop')) {
    function the_lazy_loop($args = [], $view = 'falcon-cms::frontend.loop')
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

if (!function_exists('get_lazy_post')) {
    function get_lazy_post($slugOrId)
    {
        if (is_numeric($slugOrId)) {
            return Post::find($slugOrId);
        }

        return Post::where('slug', $slugOrId)->where('lang_code', app()->getLocale())->first();
    }
}

if (!function_exists('get_lazy_category_taxonomy')) {
    /**
     * Returns ['type' => 'native'|'product'|'acpt', 'taxonomy_slug' => string|null]
     * for the category taxonomy of a given post type.
     */
    function get_lazy_category_taxonomy($postType)
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

if (!function_exists('get_lazy_categories')) {
    function get_lazy_categories($taxonomy = 'category', $postType = null)
    {
        if ($taxonomy === 'category') {
            $info = get_lazy_category_taxonomy($postType);
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

if (!function_exists('falcon_nav_menu_version')) {
    /**
     * Version token embedded in every cached nav-menu key. Bumping it (via
     * forget_nav_menu_cache) instantly invalidates all cached menus across every
     * location and locale, without needing wildcard cache deletes.
     */
    function falcon_nav_menu_version(): string
    {
        try {
            return (string) Cache::rememberForever(
                'falcon:nav_menu_ver',
                fn () => uniqid('', true)
            );
        } catch (Throwable $e) {
            return '0';
        }
    }
}

if (!function_exists('forget_nav_menu_cache')) {
    /** Invalidate every cached nav menu. Call after any menu / CPT / taxonomy edit. */
    function forget_nav_menu_cache(): void
    {
        try {
            Cache::forever('falcon:nav_menu_ver', uniqid('', true));
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('get_lazy_menu')) {
    function get_lazy_menu($slugOrLocation)
    {
        // Nav menus resolve on every frontend page (header + footer) and each fans
        // out into many queries (per-item post/term lookups + permalinks). Cache the
        // resolved tree per location+locale; the version token lets any menu/CPT/
        // taxonomy edit invalidate all of them at once, with a 10-min TTL backstop.
        // Cache a PURE ARRAY tree (no Eloquent/objects — those don't round-trip
        // through every cache store reliably), then hydrate to stdClass on the way
        // out so the theme keeps its object property access unchanged.
        try {
            $key = 'falcon:nav_menu:'.falcon_nav_menu_version().':'.$slugOrLocation.':'.app()->getLocale();
            $tree = Cache::remember(
                $key,
                now()->addMinutes(10),
                fn () => _falcon_menu_items_to_array(_falcon_resolve_lazy_menu($slugOrLocation))
            );
        } catch (Throwable $e) {
            $tree = _falcon_menu_items_to_array(_falcon_resolve_lazy_menu($slugOrLocation));
        }

        return _falcon_menu_array_to_objects($tree);
    }
}

if (!function_exists('_falcon_menu_items_to_array')) {
    /** Resolved menu items -> plain nested arrays (cache-safe). */
    function _falcon_menu_items_to_array($items): array
    {
        return collect($items)->map(function ($item) {
            $data = method_exists($item, 'getAttributes') ? $item->getAttributes() : (array) $item;
            $url = $item->url ?? ($data['url'] ?? '#');

            // Internal links (page/post/cpt/category) are cached as ROOT-RELATIVE paths
            // so the browser resolves them against whatever domain it's on — the cached
            // value must never freeze the host that happened to populate it. Only
            // user-entered 'custom' items keep their URL verbatim (may be external).
            if (($data['type'] ?? '') !== 'custom' && preg_match('#^https?://#i', $url)) {
                $p = parse_url($url);
                $url = ($p['path'] ?? '/')
                    .(isset($p['query']) ? '?'.$p['query'] : '')
                    .(isset($p['fragment']) ? '#'.$p['fragment'] : '');
            }

            $data['url'] = $url;
            $children = $item->children ?? null;
            $data['children'] = ($children && count($children))
                ? _falcon_menu_items_to_array($children)
                : [];

            return $data;
        })->values()->all();
    }
}

if (!function_exists('_falcon_menu_array_to_objects')) {
    /** Cached array tree -> Collection of stdClass (children as nested Collections). */
    function _falcon_menu_array_to_objects($tree)
    {
        return collect($tree)->map(function ($data) {
            $data = (array) $data;
            $children = $data['children'] ?? [];
            $obj = (object) $data;
            $obj->children = _falcon_menu_array_to_objects($children);

            return $obj;
        })->values();
    }
}

if (!function_exists('_falcon_resolve_lazy_menu')) {
    function _falcon_resolve_lazy_menu($slugOrLocation)
    {
        $query = NavigationMenu::query();

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
            $langSlug = $slugOrLocation.'-'.$currentLocale;
            $menu = (clone $query)->where('slug', $langSlug)->first();
            if ($menu) {
                return this_process_items($menu);
            }
        }

        // Try to find by location AND lang_code
        $menu = (clone $query)->where('lang_code', $currentLocale)->first();

        if (!$menu) {
            // Fallback to location only without lang_code
            $menu = (clone $query)->whereNull('lang_code')->first();
        }

        if (!$menu) {
            return collect();
        }

        return this_process_items($menu);
    }
}

// Internal helper for menu processing (moved logic out of the main function for reuse)
if (!function_exists('this_process_items')) {
    function this_process_items($menu)
    {
        // Fetch active CPTs and Taxonomies to filter items
        $activePostTypes = PostType::where('is_active', true)->pluck('slug')->toArray();
        $activeTaxonomies = CustomTaxonomy::where('is_active', true)->pluck('slug')->toArray();

        // Built-in types are always active
        $activePostTypes[] = 'post';
        $activePostTypes[] = 'page';
        $activePostTypes[] = 'category'; // Default category
        $activePostTypes[] = 'custom';   // Custom links

        $items = $menu->items->filter(function ($item) use ($activePostTypes, $activeTaxonomies) {
            // If it's a post/page/cpt item
            if (!in_array($item->type, ['category', 'custom'])) {
                return in_array($item->type, $activePostTypes);
            }
            // If it's a category/taxonomy item
            if ($item->type === 'category' && $item->object_id) {
                $term = TaxonomyTerm::find($item->object_id);
                if ($term) {
                    return in_array($term->taxonomy_slug, $activeTaxonomies);
                }
                $standardCat = Category::find($item->object_id);

                return (bool) $standardCat;
            }

            return true;
        });

        $cleanItems = function ($items) use (&$cleanItems) {
            return $items->map(function ($item) use ($cleanItems) {
                $currentLocale = app()->getLocale();

                // If it's a post/page/cpt item, find translation
                if (!in_array($item->type, ['category', 'custom']) && $item->object_id) {
                    $post = Post::find($item->object_id);
                    if ($post) {
                        // Find translation in current locale
                        if ($post->lang_code !== $currentLocale) {
                            $translation = $post->getTranslation($currentLocale);
                            if ($translation) {
                                $post = $translation;
                            }
                        }
                        $item->url = get_falcon_permalink($post);
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
    function is_lazy_homepage($post)
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

if (!function_exists('falcon_default_language')) {
    /**
     * The site's default language code, memoized for the request. Called from
     * get_falcon_permalink() which runs many times per page, so this avoids a
     * repeated `cms_languages where is_default` query on every link.
     */
    function falcon_default_language(): string
    {
        static $code = null;
        if ($code !== null) {
            return $code;
        }
        $code = 'en';
        try {
            $dbDefault = DB::table('cms_languages')->where('is_default', true)->value('code');
            if ($dbDefault) {
                $code = $dbDefault;
            }
        } catch (Throwable $e) {
        }

        return $code;
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
        if (!is_array($post) && is_lazy_homepage($post)) {
            $homePageId = get_cms_option('home_page_id');
            // ... (rest of homepage logic)
        }

        // Find actual default language (memoized per request — permalinks are built
        // dozens of times per page and the default language is constant).
        $defaultLang = falcon_default_language();

        // Language prefix logic: If it's not the default language, we MUST add the prefix
        $langPrefix = ($postLang === $defaultLang) ? '' : '/'.$postLang;

        // Homepage check again for safety
        if (!is_array($post) && is_lazy_homepage($post)) {
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

if (!function_exists('clear_page_cache')) {
    function clear_page_cache()
    {
        try {
            Artisan::call('cache:clear');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_gateway_http')) {
    /**
     * Send a payment-gateway HTTP request with a hard timeout so a slow or
     * unreachable gateway can never hang (or 500) the checkout request.
     *
     * $fn receives a pre-configured PendingRequest and returns the Response.
     * Returns null on any connection/timeout failure (logged) — callers must
     * treat a null response as "not verified / failed", never as success.
     */
    function falcon_gateway_http(callable $fn): ?Response
    {
        try {
            return $fn(Http::timeout(15)->connectTimeout(5));
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Payment gateway connection error: '.$e->getMessage());

            return null;
        }
    }
}

if (!function_exists('falcon_geoip')) {
    /**
     * Resolve an IP address to geo/network details via ip-api.com, cached for 30 days.
     * Uses the Laravel HTTP client (with a timeout) rather than file_get_contents, which is
     * often disabled or blocked on production hosts. Returns null values when unresolved.
     *
     * @return array{country: ?string, country_code: ?string, city: ?string, region: ?string, isp: ?string}
     */
    function falcon_geoip(?string $ip): array
    {
        $empty = ['country' => null, 'country_code' => null, 'city' => null, 'region' => null, 'isp' => null];
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)
            || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
            return $empty;
        }

        return Cache::remember('falcon_geoip_'.md5($ip), now()->addDays(30), function () use ($ip, $empty) {
            try {
                $resp = Http::timeout(3)
                    ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,countryCode,city,regionName,isp']);
                if ($resp->ok() && $resp->json('status') === 'success') {
                    return [
                        'country' => $resp->json('country'),
                        'country_code' => $resp->json('countryCode'),
                        'city' => $resp->json('city'),
                        'region' => $resp->json('regionName'),
                        'isp' => $resp->json('isp'),
                    ];
                }
            } catch (Throwable $e) {
            }

            return $empty;
        });
    }
}

if (!function_exists('falcon_activity_log_enabled')) {
    /**
     * Whether activity logging is switched on in Settings → General.
     *
     * Defaults to on: a site upgrading into this option was already logging, and
     * silently stopping would leave a gap in the audit trail nobody asked for.
     */
    function falcon_activity_log_enabled(): bool
    {
        return get_cms_option('activity_log_enabled', '1') === '1';
    }
}

if (!function_exists('falcon_activity_log_cutoff')) {
    /**
     * The moment before which activity logs should be deleted, or null when nothing
     * is due to be removed.
     *
     * The presets are ages ("older than 24 hours"); Custom is an absolute moment the
     * admin picked. Both end up as the same thing — one instant, and everything older
     * than it goes. The custom value is entered and read in the CMS timezone
     * (Settings → General), so a cutoff of "1 Sep, 10:00 PM" means ten at night where
     * the site is, not on whatever clock the server happens to keep.
     */
    function falcon_activity_log_cutoff(): ?Illuminate\Support\Carbon
    {
        if (!falcon_activity_log_enabled() || get_cms_option('activity_log_autoprune', '0') !== '1') {
            return null;
        }

        $retention = (string) get_cms_option('activity_log_retention', '72');

        if ($retention === 'custom') {
            $raw = trim((string) get_cms_option('activity_log_prune_before', ''));
            if ($raw === '') {
                return null;
            }

            try {
                return Illuminate\Support\Carbon::parse($raw, cms_timezone())->utc();
            } catch (Throwable $e) {
                // A malformed stored value must not start deleting from the epoch.
                return null;
            }
        }

        $hours = (int) $retention;

        // Anything unrecognised falls back to the longest preset rather than the
        // shortest: a bad value should keep more history, never less.
        if (!in_array($hours, [24, 48, 72], true)) {
            $hours = 72;
        }

        return cms_now()->subHours($hours)->utc();
    }
}

if (!function_exists('falcon_prune_activity_logs')) {
    /**
     * Delete activity log entries older than the configured cutoff. Returns how many
     * rows went, so a caller can say so rather than leaving the admin guessing.
     *
     * Deleted in chunks: a table left alone for months should not go at it in one
     * statement and lock everyone else out. $maxBatches caps that work for callers
     * running inside a web request; 0 means sweep until there is nothing left.
     */
    function falcon_prune_activity_logs(?Illuminate\Support\Carbon $cutoff = null, int $maxBatches = 0): int
    {
        $cutoff ??= falcon_activity_log_cutoff();

        if (!$cutoff) {
            return 0;
        }

        $total = 0;
        $batches = 0;

        do {
            $count = DB::table('activity_logs')
                ->where('created_at', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $total += $count;
            $batches++;
        } while ($count > 0 && ($maxBatches === 0 || $batches < $maxBatches));

        return $total;
    }
}

if (!function_exists('falcon_prune_activity_logs_throttled')) {
    /**
     * The hourly-throttled prune behind the cron-independent fallback, kept here so
     * it can be exercised without standing up a whole request.
     *
     * The cutoff is resolved BEFORE the lock is claimed, and that order is the whole
     * point: claim first and a request arriving while automatic removal is switched
     * off burns the hour on nothing — then switching it on a minute later removes
     * nothing until that hour is up, which reads exactly like the feature being
     * broken. One batch only; the scheduled command does the full sweep.
     */
    function falcon_prune_activity_logs_throttled(): int
    {
        $cutoff = falcon_activity_log_cutoff();

        if (!$cutoff) {
            return 0;
        }

        if (!Cache::add('falcon_activity_log_prune_lock', 1, now()->addHour())) {
            return 0;
        }

        return falcon_prune_activity_logs($cutoff, 1);
    }
}

if (!function_exists('falcon_log_activity')) {
    function falcon_log_activity($action, $description, $model = null, $properties = [])
    {
        // One choke point for every caller in the package: switched off in settings
        // means no row is written at all, not merely hidden from the screen.
        if (!falcon_activity_log_enabled()) {
            return null;
        }

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
                } catch (Exception $e) {
                }
            }

            return ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->id : null,
                'description' => $description,
                'properties' => $properties,
                'ip_address' => $ip,
                'country' => $country,
                'country_code' => $countryCode,
                'user_agent' => request()->userAgent(),
            ]);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('render_lazy_widgets')) {
    function render_lazy_widgets($area)
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
                    $content = do_lazy_shortcode($content);

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

// --- Hook System Helpers ---

if (!function_exists('add_falcon_action')) {
    function add_falcon_action($tag, $callback, $priority = 10)
    {
        HookManager::getInstance()->addAction($tag, $callback, $priority);
    }
}

if (!function_exists('do_falcon_action')) {
    function do_falcon_action($tag, ...$args)
    {
        HookManager::getInstance()->doAction($tag, ...$args);
    }
}

if (!function_exists('add_falcon_filter')) {
    function add_falcon_filter($tag, $callback, $priority = 10)
    {
        HookManager::getInstance()->addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('apply_falcon_filters')) {
    function apply_falcon_filters($tag, $value, ...$args)
    {
        return HookManager::getInstance()->applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('has_falcon_action')) {
    function has_falcon_action($tag)
    {
        return HookManager::getInstance()->hasAction($tag);
    }
}

if (!function_exists('has_falcon_filter')) {
    function has_falcon_filter($tag)
    {
        return HookManager::getInstance()->hasFilter($tag);
    }
}

if (!function_exists('falcon_is_protected_option')) {
    /**
     * Option keys that must NEVER be written from user-supplied settings input —
     * generic settings saves ($request->except), injected fields, options pages,
     * etc. These are managed internally (licensing / Pro entitlement); letting a
     * crafted request or a registered field overwrite them would forge a license
     * and bypass Pro gating. Internal code writes them through their own services.
     */
    function falcon_is_protected_option($key): bool
    {
        $key = strtolower(trim((string) $key));
        if ($key === '') {
            return false;
        }

        // Any current/future license key or cached license state.
        if (str_starts_with($key, 'falcon_license')) {
            return true;
        }

        $protected = [
            'falcon_grandfathered_features', // grants grandfathered Pro access
        ];

        return in_array($key, $protected, true);
    }
}

if (!function_exists('falcon_safe_url')) {
    /**
     * Return $url only when it uses a safe scheme (http/https) or is a
     * relative/root-relative/protocol-relative path; blocks javascript:, data:,
     * vbscript: and similar. Use wherever a stored field value is placed into an
     * href/src to prevent stored XSS.
     */
    function falcon_safe_url($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        // Relative, root-relative, or protocol-relative URLs are fine.
        if ($url[0] === '/' || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return $url;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false || $scheme === '') {
            return $url; // e.g. "media/foo.jpg" — a relative path, no scheme
        }
        $scheme = strtolower($scheme);

        return ($scheme === 'http' || $scheme === 'https') ? $url : '';
    }
}

if (!function_exists('lazy_render_product_field')) {
    function lazy_render_product_field(array $field): string
    {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $required = !empty($field['required']);
        $value = $field['value'] ?? '';
        $class = $field['class'] ?? '';
        $rows = (int) ($field['rows'] ?? 3);
        $min = $field['min'] ?? null;
        $max = $field['max'] ?? null;
        $options = $field['options'] ?? [];
        $hint = $field['hint'] ?? '';

        // wrapper: HTML tag ('div', 'li', 'p', 'span', false = no wrapper)
        $wrapperTag = $field['wrapper'] ?? 'div';
        $wrapperClass = $field['wrapper_class'] ?? 'mb-4';
        // extra attributes on the wrapper element (e.g. 'data-foo="bar"')
        $wrapperAttrs = $field['wrapper_attrs'] ?? '';

        $baseInput = 'w-full border border-gray-300 rounded-sm px-3 py-2 text-sm focus:outline-none focus:border-gray-500 '.$class;
        $req = $required ? ' required' : '';
        $reqStar = $required ? '<span class="text-red-500 ml-0.5">*</span>' : '';

        $inner = '';

        switch ($type) {
            case 'textarea':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $inner .= '<textarea name="'.e($name).'" rows="'.$rows.'" placeholder="'.e($placeholder).'" class="'.e($baseInput).'"'.$req.'>'.e($value).'</textarea>';
                break;

            case 'select':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $inner .= '<select name="'.e($name).'" class="'.e($baseInput).'"'.$req.'>';
                if ($placeholder) {
                    $inner .= '<option value="">'.e($placeholder).'</option>';
                }
                foreach ($options as $optVal => $optLabel) {
                    $selected = ($value == $optVal) ? ' selected' : '';
                    $inner .= '<option value="'.e($optVal).'"'.$selected.'>'.e($optLabel).'</option>';
                }
                $inner .= '</select>';
                break;

            case 'radio':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $inner .= '<div class="flex flex-wrap gap-3 mt-1">';
                foreach ($options as $optVal => $optLabel) {
                    $checked = ($value == $optVal) ? ' checked' : '';
                    $inner .= '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">';
                    $inner .= '<input type="radio" name="'.e($name).'" value="'.e($optVal).'"'.$checked.$req.' class="accent-primary">';
                    $inner .= e($optLabel).'</label>';
                }
                $inner .= '</div>';
                break;

            case 'checkbox':
                $checked = !empty($field['checked']) ? ' checked' : '';
                $inner .= '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">';
                $inner .= '<input type="checkbox" name="'.e($name).'" value="'.e($value ?: '1').'"'.$checked.' class="accent-primary">';
                $inner .= e($label).$reqStar.'</label>';
                break;

            case 'number':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $minAttr = $min !== null ? ' min="'.e($min).'"' : '';
                $maxAttr = $max !== null ? ' max="'.e($max).'"' : '';
                $inner .= '<input type="number" name="'.e($name).'" value="'.e($value).'" placeholder="'.e($placeholder).'" class="'.e($baseInput).'"'.$minAttr.$maxAttr.$req.'>';
                break;

            case 'hidden':
                return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';

            case 'raw':
                // 'content' key — raw HTML, trusted developer input
                $inner = $field['content'] ?? '';
                break;

            default: // text, email, tel, url, date, etc.
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $minAttr = $min !== null ? ' minlength="'.e($min).'"' : '';
                $maxAttr = $max !== null ? ' maxlength="'.e($max).'"' : '';
                $inner .= '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" placeholder="'.e($placeholder).'" class="'.e($baseInput).'"'.$minAttr.$maxAttr.$req.'>';
                break;
        }

        if ($hint) {
            $inner .= '<p class="text-xs text-gray-400 mt-1">'.e($hint).'</p>';
        }

        // No wrapper
        if (!$wrapperTag) {
            return $inner;
        }

        $tag = preg_replace('/[^a-z0-9]/', '', strtolower($wrapperTag));

        return '<'.$tag.($wrapperClass ? ' class="'.e($wrapperClass).'"' : '').($wrapperAttrs ? ' '.$wrapperAttrs : '').'>'
            .$inner
            .'</'.$tag.'>';
    }
}

if (!function_exists('falcon_render_product_fields')) {
    function falcon_render_product_fields(array $fields): void
    {
        foreach ($fields as $field) {
            echo lazy_render_product_field($field);
        }
    }
}

if (!function_exists('falcon_render_item_custom_fields')) {
    /**
     * Render custom fields for a cart session item (array) or an OrderItem model.
     * Context labels can be overridden via the falcon_custom_field_labels filter.
     *
     * @param  array|object  $item  Cart array item OR OrderItem model
     * @param  string  $context  'cart' | 'checkout' | 'confirmation' | 'admin'
     * @param  string  $wrapClass  CSS class on the wrapper div
     */
    function falcon_render_item_custom_fields($item, string $context = 'cart', string $wrapClass = 'mt-1.5 space-y-0.5'): string
    {
        // Support both cart session array and OrderItem model
        if (is_array($item)) {
            $customFields = $item['meta']['custom_fields'] ?? [];
        } else {
            $meta = is_array($item->meta) ? $item->meta : (json_decode($item->meta ?? '{}', true) ?? []);
            $customFields = $meta['custom_fields'] ?? [];
        }

        $customFields = apply_falcon_filters('lazy_item_custom_fields_display', $customFields, $item, $context);

        if (empty($customFields)) {
            return '';
        }

        // Allow label overrides via filter
        $labels = apply_falcon_filters('falcon_custom_field_labels', [], $context);

        $html = '<div class="'.e($wrapClass).'">';
        foreach ($customFields as $key => $value) {
            if ((string) $value === '') {
                continue;
            }
            $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
            $html .= '<div class="text-[11px] text-gray-500 leading-snug">'
                   .'<span class="font-semibold text-gray-700">'.e($label).':</span> '
                   .e($value)
                   .'</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('falcon_normalize_custom_fields')) {
    /**
     * Normalize a custom builder element definition (registered via falcon_builder_elements)
     * into a flat, keyed fields array the builder + frontend can consume consistently.
     *
     * Handles both the legacy `fields` map and the Avada-style indexed `params` array,
     * auto-generates param_name from heading, normalizes type aliases, and preserves
     * extended keys (condition, options, unit, step, fields/params for repeaters, etc.).
     *
     * @return array<string,array> keyed by field key
     */
    function falcon_normalize_custom_fields(array $custEl): array
    {
        $typeMap = [
            'textfield' => 'text',
            'colorpickeralpha' => 'color',
            'colorpicker' => 'color',
            'textarea_html' => 'wysiwyg',
        ];

        // suffix → apply_as + base stripping (shared with the array param_name sugar)
        $suffixAs = ['_hover_color' => 'hover_color', '_hover_bg' => 'hover_bg', '_color' => 'color', '_bg' => 'bg', '_typo' => '', '_pad' => 'padding', '_margin' => 'margin'];
        $stripBase = function ($k) use ($suffixAs) {
            foreach ($suffixAs as $suf => $as) {
                if (str_ends_with($k, $suf)) {
                    return [substr($k, 0, -strlen($suf)), $as];
                }
            }

            return [$k, ''];
        };

        $slug = fn ($t) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($t)), '_');
        $contentTypes = ['text', 'textfield', 'textarea', 'wysiwyg', 'image', 'media', 'icon', 'button', 'repeater'];

        // Pre-scan content-field keys so array param_name sugar can avoid colliding with them
        $contentKeys = [];
        foreach (($custEl['params'] ?? []) as $p) {
            if (!in_array($p['type'] ?? 'text', $contentTypes, true)) {
                continue;
            }
            $pn = $p['param_name'] ?? null;
            $ck = is_array($pn) ? ($pn[0] ?? null) : $pn;
            if (!$ck && !empty($p['heading'])) {
                $ck = $slug($p['heading']);
            }
            if ($ck) {
                $contentKeys[] = $ck;
            }
        }

        $autoKey = function ($p) use ($slug) {
            $pn = $p['param_name'] ?? null;
            if (is_array($pn) && !empty($pn)) {
                return $pn[0];
            }
            if (!empty($pn)) {
                return $pn;
            }
            if (!empty($p['heading'])) {
                return $slug($p['heading']);
            }

            return null;
        };

        // Start from legacy fields map (already keyed)
        $fields = $custEl['fields'] ?? [];

        foreach (($custEl['params'] ?? []) as $p) {
            $key = $autoKey($p);
            if (!$key) {
                continue;
            }
            $rawType = $p['type'] ?? 'text';

            // Array param_name → sugar for relating one field to many targets
            $applyTo = $p['apply_to'] ?? null;
            $applyAs = $p['apply_as'] ?? null;
            if (is_array($p['param_name'] ?? null) && !empty($p['param_name'])) {
                $entries = $p['param_name'];
                [$b0, $as0] = $stripBase($entries[0]);
                if ($b0 !== $entries[0]) {
                    // Suffixed entries (e.g. title_color) → first is the storage key, strip suffix for targets
                    if ($applyAs === null) {
                        $applyAs = $as0;
                    }
                    if ($applyTo === null) {
                        $applyTo = array_map(fn ($k) => $stripBase($k)[0], $entries);
                    }
                } else {
                    // Bare target names (e.g. ['title','subtitle']) → synthesise a non-colliding storage key
                    $base = !empty($p['heading']) ? $slug($p['heading']) : ('cf_'.substr(md5(implode(',', $entries)), 0, 6));
                    while (in_array($base, $contentKeys, true)) {
                        $base .= '_x';
                    }
                    $key = $base;
                    if ($applyTo === null) {
                        $applyTo = $entries;
                    }
                    if ($applyAs === null) {
                        $nt = $typeMap[$rawType] ?? $rawType;
                        $applyAs = $nt === 'dimensions' ? 'padding' : ($nt === 'color' ? 'color' : '');
                    }
                }
            }

            $fields[$key] = [
                'type' => $typeMap[$rawType] ?? $rawType,
                'raw_type' => $rawType,
                'label' => $p['heading'] ?? $key,
                'default' => $p['value'] ?? '',
                'tab' => $p['tab'] ?? 'general',
                'placeholder' => $p['placeholder'] ?? '',
                'description' => $p['description'] ?? '',
                'options' => $p['options'] ?? [],
                'rows' => $p['rows'] ?? null,
                'min' => $p['min'] ?? null,
                'max' => $p['max'] ?? null,
                'step' => $p['step'] ?? null,
                'unit' => $p['unit'] ?? '',
                'condition' => $p['condition'] ?? null,
                'dynamic' => $p['dynamic'] ?? false,
                'apply_to' => $applyTo,
                'apply_as' => $applyAs,
                // repeater sub-fields (either key works)
                'fields' => $p['fields'] ?? [],
                'params' => $p['params'] ?? [],
            ];
        }

        return $fields;
    }
}

if (!function_exists('falcon_dynamic_config')) {
    /**
     * Build the $config for falcon_resolve_dynamic_value() out of an element's settings.
     *
     * An element can carry a text source AND a link source at once, so the two contexts read
     * separate setting keys — otherwise the link's fallback would double as the text's.
     */
    function falcon_dynamic_config(array $s, string $ctx = 'text'): array
    {
        if ($ctx === 'link') {
            return [
                'link_tax_post_type' => $s['dynamic_link_tax_post_type'] ?? '',
                'link_tax_slug' => $s['dynamic_link_tax_slug'] ?? '',
                'link_tax_which' => $s['dynamic_link_tax_which'] ?? 'first',
                'fallback' => $s['dynamic_link_tax_fallback'] ?? '',
            ];
        }

        return [
            'date_type' => $s['dynamic_date_type'] ?? 'published',
            'date_format' => $s['dynamic_date_format'] ?? '',
            'before' => $s['dynamic_before'] ?? '',
            'after' => $s['dynamic_after'] ?? '',
            'fallback' => $s['dynamic_fallback'] ?? '',
            'excerpt_length' => (int) ($s['dynamic_excerpt_length'] ?? 150),
            'acpt_slug' => $s['dynamic_acpt_slug'] ?? '',
            'tax_post_type' => $s['dynamic_tax_post_type'] ?? '',
            'tax_slug' => $s['dynamic_tax_slug'] ?? '',
            'tax_separator' => $s['dynamic_tax_separator'] ?? '',
            'tax_limit' => (int) ($s['dynamic_tax_limit'] ?? 0),
        ];
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
     * /category/{slug} and /product-category/{slug} archives fall back to a taxonomy_terms
     * lookup — so a custom taxonomy is routed through the prefix matching its post type.
     */
    function falcon_term_archive_url($term, string $taxonomySlug, string $postType = 'post'): string
    {
        $slug = is_object($term) ? ($term->slug ?? '') : (string) $term;
        if ($slug === '') {
            return '';
        }

        $prefixes = [
            'category' => 'category',        'categories' => 'category',
            'tag' => 'tag',             'tags' => 'tag',
            'product-category' => 'product-category', 'product_category' => 'product-category',
            'product-categories' => 'product-category', 'product_categories' => 'product-category',
            'product-tag' => 'product-tag',     'product_tag' => 'product-tag',
            'product-tags' => 'product-tag',     'product_tags' => 'product-tag',
        ];
        $prefix = $prefixes[$taxonomySlug]
            ?? ($postType === 'product' ? 'product-category' : 'category');

        return url('/'.$prefix.'/'.$slug);
    }
}

if (!function_exists('falcon_resolve_dynamic_value')) {
    /**
     * Resolve a dynamic-source key to a real value using the current post context.
     * $config may include: date_type, date_format, before, after, fallback,
     *                      excerpt_length, acpt_slug
     */
    function falcon_resolve_dynamic_value(string $source, $post = null, array $config = [])
    {
        if ($post === null) {
            try {
                $shared = view()->getShared();
                $post = $shared['post'] ?? null;
            } catch (Throwable $e) {
            }
        }

        $before = $config['before'] ?? '';
        $after = $config['after'] ?? '';
        $fallback = $config['fallback'] ?? '';

        $val = '';
        switch ($source) {
            case 'site_name':
            case 'site_title':
                $val = function_exists('get_cms_option') ? (string) get_cms_option('site_title', get_cms_option('site_name', config('app.name', ''))) : config('app.name', '');
                break;
            case 'site_tagline':
                $val = function_exists('get_cms_option') ? (string) get_cms_option('site_description', '') : '';
                break;
            case 'site_url':
                $val = function_exists('url') ? url('/') : config('app.url', '');
                break;
            case 'post_title':
                $val = $post->title ?? '';
                break;
            case 'post_url':
                $val = ($post && function_exists('get_falcon_permalink')) ? get_falcon_permalink($post) : ($post->slug ?? '');
                break;
            case 'post_excerpt':
                if (!$post) {
                    break;
                }
                $ex = $post->excerpt ?? '';
                if (!$ex && function_exists('get_falcon_excerpt')) {
                    $ex = get_falcon_excerpt($post);
                }
                $length = max(1, (int) ($config['excerpt_length'] ?? 150));
                if (!$ex) {
                    $raw = strip_tags($post->content ?? '');
                    $rawTrimmed = ltrim($raw);
                    if ($rawTrimmed && $rawTrimmed[0] !== '[' && $rawTrimmed[0] !== '{') {
                        $ex = Str::limit($rawTrimmed, $length);
                    }
                } else {
                    $ex = Str::limit(strip_tags($ex), $length);
                }
                $val = $ex ? strip_tags($ex) : '';
                break;
            case 'post_date':
                $dateType = $config['date_type'] ?? 'published';
                $dateFormat = $config['date_format'] ?? 'M j, Y';
                if (!$dateFormat) {
                    $dateFormat = 'M j, Y';
                }
                $d = $dateType === 'modified'
                    ? ($post->updated_at ?? null)
                    : ($post->published_at ?? $post->created_at ?? null);
                $val = ($post && $d) ? Carbon::parse($d)->format($dateFormat) : '';
                break;
            case 'post_reading_time':
                if (!$post) {
                    break;
                }
                $words = str_word_count(strip_tags($post->content ?? ''));
                $val = max(1, (int) ceil($words / 200)).' min read';
                break;
            case 'post_id':
                $val = $post ? (string) ($post->id ?? '') : '';
                break;
            case 'post_type':
                $val = $post->type ?? '';
                break;
            case 'post_taxonomy':
                // Both halves must match: the post has to BE the chosen post type, and the terms
                // come from the chosen taxonomy. A mismatch yields '' so the fallback shows,
                // which is what makes one template safe to reuse across post types.
                if (!$post) {
                    break;
                }
                $wantType = (string) ($config['tax_post_type'] ?? '');
                $taxSlug = (string) ($config['tax_slug'] ?? '');
                if ($taxSlug === '') {
                    break;
                }
                if ($wantType !== '' && ($post->type ?? '') !== $wantType) {
                    break;
                }
                $terms = falcon_post_terms($post, $taxSlug);
                if ($terms->isEmpty()) {
                    break;
                }
                $limit = (int) ($config['tax_limit'] ?? 0);
                if ($limit > 0) {
                    $terms = $terms->take($limit);
                }
                $sep = $config['tax_separator'] ?? '';
                if ($sep === '') {
                    $sep = ', ';
                }
                $val = $terms->map(fn ($t) => (string) ($t->name ?? ''))->filter()->implode($sep);
                break;

            case 'taxonomy_url':
                if (!$post) {
                    break;
                }
                $wantType = (string) ($config['link_tax_post_type'] ?? '');
                $taxSlug = (string) ($config['link_tax_slug'] ?? '');
                if ($taxSlug === '') {
                    break;
                }
                if ($wantType !== '' && ($post->type ?? '') !== $wantType) {
                    break;
                }
                $terms = falcon_post_terms($post, $taxSlug);
                if ($terms->isEmpty()) {
                    break;
                }
                $term = ($config['link_tax_which'] ?? 'first') === 'last' ? $terms->last() : $terms->first();
                $val = falcon_term_archive_url($term, $taxSlug, (string) ($post->type ?? 'post'));
                break;

            case 'post_comment_count':
                if (!$post) {
                    break;
                }
                $val = (string) (isset($post->comments_count) ? $post->comments_count : (method_exists($post, 'comments') ? $post->comments()->count() : 0));
                break;
            case 'post_author':
            case 'author_name':
                $val = $post->user->name ?? ($post->author->name ?? '');
                break;
            case 'author_bio':
                $val = $post->user->bio ?? ($post->user->description ?? '');
                break;
            case 'author_url':
                $val = '';
                break;
            case 'author_avatar':
                $av = $post->user->avatar ?? ($post->user->profile_photo_url ?? '');
                if ($av && !str_starts_with($av, 'http') && !str_starts_with($av, '/storage')) {
                    $av = '/storage/'.ltrim($av, '/');
                }
                $val = $av;
                break;
            case 'featured_image':
            case 'feature_image':
                if (!$post) {
                    break;
                }
                $img = $post->featured_image ?? $post->thumbnail ?? '';
                if ($img && !str_starts_with($img, 'http') && !str_starts_with($img, '/storage')) {
                    $img = '/storage/'.ltrim($img, '/');
                }
                $val = $img;
                break;
            case 'logo':
            case 'site_logo':
                $logo = get_cms_option('theme_site_logo', '');
                if ($logo && !str_starts_with($logo, 'http') && !str_starts_with($logo, '/storage')) {
                    $logo = '/storage/'.ltrim($logo, '/');
                }
                $val = $logo;
                break;
            case 'current_date':
                $dateFormat = $config['date_format'] ?? 'M j, Y';
                if (!$dateFormat) {
                    $dateFormat = 'M j, Y';
                }
                $val = now()->format($dateFormat);
                break;
            case 'current_year':
                $val = now()->format('Y');
                break;
            case 'user_name':
                $val = auth()->check() ? (auth()->user()->name ?? '') : '';
                break;
            case 'acpt_custom':
                $slug = $config['acpt_slug'] ?? '';
                if ($slug) {
                    $val = falcon_resolve_dynamic_value('acpt_'.$slug, $post);
                }
                break;
            case 'product_price':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $sale = $sd->sale_price;
                $saleActive = ($sale !== null && $sale !== '' && (empty($sd->sale_ends_at) || Carbon::parse($sd->sale_ends_at)->isFuture()));
                $price = $saleActive ? (float) $sale : (float) ($sd->price ?? 0);
                $val = function_exists('falcon_price_format') ? falcon_price_format($price) : number_format($price, 2);
                break;

            case 'product_regular_price':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $price = (float) ($sd->price ?? 0);
                $val = function_exists('falcon_price_format') ? falcon_price_format($price) : number_format($price, 2);
                break;

            case 'product_sale_price':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $sale = $sd->sale_price;
                $saleActive = ($sale !== null && $sale !== '' && (empty($sd->sale_ends_at) || Carbon::parse($sd->sale_ends_at)->isFuture()));
                if ($saleActive) {
                    $val = function_exists('falcon_price_format') ? falcon_price_format((float) $sale) : number_format((float) $sale, 2);
                }
                break;

            case 'product_sku':
                $val = ($post && $post->shopData) ? (string) ($post->shopData->sku ?? '') : '';
                break;
            case 'product_stock_status':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $isOut = ($sd->stock_status ?? 'instock') === 'outofstock'
                      || (($sd->manage_stock ?? false) && (int) ($sd->stock_quantity ?? 0) <= 0);
                $val = $isOut ? 'Out of stock' : 'In stock';
                break;

            case 'product_stock_quantity':
                $val = ($post && $post->shopData && $post->shopData->stock_quantity !== null)
                    ? (string) (int) $post->shopData->stock_quantity : '';
                break;
            default:
                if (str_starts_with($source, 'acpt_') && $post) {
                    $acptSlug = substr($source, 5);
                    if (function_exists('get_acpt_field')) {
                        $acptVal = get_acpt_field($post->id ?? null, $acptSlug);
                        $val = is_string($acptVal) ? $acptVal : '';
                    }
                }
                break;
        }

        if ($val === '' || $val === null) {
            return $fallback;
        }

        return $before.$val.$after;
    }
}

if (!function_exists('lazy_apply_custom_dynamic')) {
    /**
     * Replace any `{key}_dynamic` setting with the resolved value into `{key}`,
     * so both custom templates and the generic renderer receive final values.
     */
    function lazy_apply_custom_dynamic(array $settings, $post = null): array
    {
        $config = falcon_dynamic_config($settings);
        foreach ($settings as $k => $v) {
            if (is_string($k) && str_ends_with($k, '_dynamic') && !empty($v)) {
                $base = substr($k, 0, -strlen('_dynamic'));
                $settings[$base] = falcon_resolve_dynamic_value($v, $post, $config);
            }
        }

        return $settings;
    }
}

if (!function_exists('lazy_resolve_tokens')) {
    function lazy_resolve_tokens(string $value, $post = null): string
    {
        if (strpos($value, '{lazy:') === false) {
            return $value;
        }

        return preg_replace_callback('/\{lazy:([^}]+)\}/', function ($m) use ($post) {
            return lazy_resolve_token($m[1], $post);
        }, $value);
    }
}

if (!function_exists('lazy_resolve_token')) {
    function lazy_resolve_token(string $token, $post = null): string
    {
        if ($post === null) {
            try {
                $post = view()->getShared()['post'] ?? null;
            } catch (Throwable $e) {
            }
        }
        switch ($token) {
            case 'post_title':
                return $post->title ?? '';
            case 'post_excerpt':
                if (!$post) {
                    return '';
                }
                $ex = $post->excerpt ?? '';
                if (!$ex && function_exists('get_falcon_excerpt')) {
                    $ex = get_falcon_excerpt($post);
                }
                if (!$ex) {
                    $raw = strip_tags($post->content ?? '');
                    $rawTrimmed = ltrim($raw);
                    if ($rawTrimmed && $rawTrimmed[0] !== '[' && $rawTrimmed[0] !== '{') {
                        $ex = Str::limit($rawTrimmed, 150);
                    }
                }

                return $ex ? strip_tags($ex) : '';
            case 'post_id':
                return (string) ($post->id ?? '');
            case 'post_date':
                $d = $post->created_at ?? ($post->published_at ?? null);

                return $d ? Carbon::parse($d)->format('M j, Y') : '';
            case 'post_type':
                return $post->type ?? '';
            case 'post_permalink':
                if (!$post) {
                    return '';
                }

                return function_exists('get_falcon_permalink') ? get_falcon_permalink($post) : ($post->slug ?? '#');
            case 'post_reading_time':
                if (!$post) {
                    return '';
                }

                return max(1, (int) ceil(str_word_count(strip_tags($post->content ?? '')) / 200)).' min read';
            case 'site_title':
                return function_exists('get_cms_option') ? (string) get_cms_option('site_title', config('app.name', '')) : config('app.name', '');
            case 'site_tagline':
                return function_exists('get_cms_option') ? (string) get_cms_option('site_description', '') : '';
            case 'current_date':
                return now()->format('M j, Y');
            case 'current_year':
                return now()->format('Y');
            case 'author_name':
                return $post?->user?->name ?? ($post?->author?->name ?? '');
            case 'user_name':
                return auth()->check() ? auth()->user()->name : '';
            default:
                if (str_starts_with($token, 'acpt_') && $post) {
                    $slug = substr($token, 5);
                    try {
                        $meta = PostMeta::where('post_id', $post->id)
                            ->where('meta_key', $slug)->first();
                        if ($meta) {
                            return (string) $meta->meta_value;
                        }
                    } catch (Throwable $e) {
                    }
                }

                return '';
        }
    }
}

if (!function_exists('falcon_resolve_tokens_in_settings')) {
    function falcon_resolve_tokens_in_settings(array $settings, $post = null): array
    {
        foreach ($settings as $k => &$v) {
            if (is_string($v) && strpos($v, '{lazy:') !== false) {
                $v = lazy_resolve_tokens($v, $post);
            } elseif (is_array($v)) {
                $v = falcon_resolve_tokens_in_settings($v, $post);
            }
        }

        return $settings;
    }
}

if (!function_exists('lazy_custom_element_render')) {
    /**
     * Build the convention-based render data for a custom element — the PHP mirror of the
     * builder canvas (getCustomElementRender). Used by the generic frontend renderer so the
     * front-end output matches the canvas preview 1:1 (incl. prefix relations + hover).
     *
     * Returns: ['wrapperStyle' => string, 'wrapperHoverClass' => string,
     *           'hoverCss' => string, 'items' => [ {kind,key,value,style,hoverClass, url?,target?, rows?,subFields?} ]]
     */
    function lazy_custom_element_render(array $el, array $customDef): array
    {
        $s = $el['settings'] ?? [];
        $elId = $el['id'] ?? uniqid('ce');
        $fields = falcon_normalize_custom_fields($customDef); // keyed, ordered
        $contentTypes = ['text', 'textarea', 'wysiwyg', 'image', 'media', 'icon', 'button', 'repeater', 'date', 'number', 'slider', 'select', 'radio', 'checkbox', 'url', 'link'];
        // A field renders as content unless it's a design modifier (align select/radio, or an apply_to relation).
        $isContent = function (string $k, array $f) use ($contentTypes): bool {
            if (!in_array($f['type'], $contentTypes, true)) {
                return false;
            }
            if (in_array($f['type'], ['select', 'radio'], true) && str_ends_with($k, '_align')) {
                return false;
            }
            if (!empty($f['apply_to'])) {
                return false;
            }

            return true;
        };

        $contentKeys = [];
        foreach ($fields as $k => $f) {
            if ($isContent($k, $f)) {
                $contentKeys[] = $k;
            }
        }

        $unit = fn ($v) => (is_numeric($v) ? $v.'px' : $v);

        // typography CSS decls from a prefix
        $typoFor = function (string $tp) use ($s, $unit): array {
            $css = [];
            if (!empty($s[$tp.'_family']) && $s[$tp.'_family'] !== 'inherit') {
                $css[] = 'font-family:'.$s[$tp.'_family'];
            }
            if (!empty($s[$tp.'_size'])) {
                $css[] = 'font-size:'.$unit($s[$tp.'_size']);
            }
            if (!empty($s[$tp.'_weight'])) {
                $css[] = 'font-weight:'.$s[$tp.'_weight'];
            }
            if (!empty($s[$tp.'_line_height'])) {
                $css[] = 'line-height:'.$s[$tp.'_line_height'];
            }
            if (isset($s[$tp.'_letter_spacing']) && $s[$tp.'_letter_spacing'] !== '') {
                $css[] = 'letter-spacing:'.$unit($s[$tp.'_letter_spacing']);
            }
            if (!empty($s[$tp.'_transform']) && $s[$tp.'_transform'] !== 'none') {
                $css[] = 'text-transform:'.$s[$tp.'_transform'];
            }

            return $css;
        };

        // T/R/B/L shorthand from a prefix, or null
        $edgesFor = function (string $prefix) use ($s): ?string {
            $edges = [];
            $has = false;
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $v = $s[$prefix.'_'.$side] ?? '';
                if ($v === '' || $v === null) {
                    $edges[] = '0';
                } else {
                    $edges[] = $v.($s[$prefix.'_'.$side.'_unit'] ?? 'px');
                    $has = true;
                }
            }

            return $has ? implode(' ', $edges) : null;
        };

        // Assemble inline CSS for a base from its prefix-related modifiers
        $styleFor = function (string $base) use ($s, $typoFor, $edgesFor): string {
            $css = [];
            if (!empty($s[$base.'_color'])) {
                $css[] = 'color:'.$s[$base.'_color'];
            }
            if (!empty($s[$base.'_bg'])) {
                $css[] = 'background-color:'.$s[$base.'_bg'];
            }
            if (!empty($s[$base.'_align'])) {
                $css[] = 'text-align:'.$s[$base.'_align'];
            }
            $css = array_merge($css, $typoFor($base.'_typo'));
            if ($p = $edgesFor($base.'_pad')) {
                $css[] = 'padding:'.$p;
            }
            if ($m = $edgesFor($base.'_margin')) {
                $css[] = 'margin:'.$m;
            }

            return implode(';', $css);
        };

        $hoverDecls = function (string $base) use ($s): array {
            $d = [];
            if (!empty($s[$base.'_hover_color'])) {
                $d[] = 'color:'.$s[$base.'_hover_color'].' !important';
            }
            if (!empty($s[$base.'_hover_bg'])) {
                $d[] = 'background-color:'.$s[$base.'_hover_bg'].' !important';
            }

            return $d;
        };

        // Contribution of an apply_to design field to a target → ['style' => string, 'hover' => array]
        $contribFor = function (array $f) use ($s, $typoFor, $edgesFor): array {
            $type = $f['type'];
            $key = $f['key'];
            $as = $f['apply_as'] ?: ($type === 'dimensions' ? 'padding' : ($type === 'color' ? 'color' : ''));
            $style = [];
            $hover = [];
            if ($type === 'color') {
                $v = $s[$key] ?? '';
                if ($v === '' || $v === null) {
                    return ['style' => '', 'hover' => []];
                }
                if ($as === 'bg') {
                    $style[] = 'background-color:'.$v;
                } elseif ($as === 'hover_color') {
                    $hover[] = 'color:'.$v.' !important';
                } elseif ($as === 'hover_bg') {
                    $hover[] = 'background-color:'.$v.' !important';
                } else {
                    $style[] = 'color:'.$v;
                }
            } elseif ($type === 'typography') {
                $style = $typoFor($key);
            } elseif ($type === 'dimensions') {
                $e = $edgesFor($key);
                if ($e) {
                    $style[] = ($as === 'margin' ? 'margin:' : 'padding:').$e;
                }
            }

            return ['style' => implode(';', $style), 'hover' => $hover];
        };

        $modBase = function (string $key, string $type): ?string {
            if ($type === 'color') {
                if (str_ends_with($key, '_hover_color')) {
                    return substr($key, 0, -12);
                }
                if (str_ends_with($key, '_hover_bg')) {
                    return substr($key, 0, -9);
                }
                if (str_ends_with($key, '_color')) {
                    return substr($key, 0, -6);
                }
                if (str_ends_with($key, '_bg')) {
                    return substr($key, 0, -3);
                }
            }
            if ($type === 'typography' && str_ends_with($key, '_typo')) {
                return substr($key, 0, -5);
            }
            if ($type === 'dimensions') {
                if (str_ends_with($key, '_pad')) {
                    return substr($key, 0, -4);
                }
                if (str_ends_with($key, '_margin')) {
                    return substr($key, 0, -7);
                }
            }
            if (in_array($type, ['select', 'radio'], true) && str_ends_with($key, '_align')) {
                return substr($key, 0, -6);
            }

            return null;
        };

        $hoverCss = '';
        $hcSeq = 0;
        $mkHoverClass = function (array $decls) use (&$hoverCss, &$hcSeq, $elId): string {
            if (empty($decls)) {
                return '';
            }
            $cls = 'lzceh-'.$elId.'-'.($hcSeq++);
            $hoverCss .= '.'.$cls.':hover{'.implode(';', $decls).'}';

            return $cls;
        };

        // Explicit multi-target relations: design fields with `apply_to` style one or more content fields.
        $explicit = []; // base => ['style' => [..], 'hover' => [..]]
        foreach ($fields as $k => $f) {
            if (empty($f['apply_to'])) {
                continue;
            }
            $targets = is_array($f['apply_to']) ? $f['apply_to'] : [$f['apply_to']];
            $c = $contribFor($f + ['key' => $k]);
            foreach ($targets as $t) {
                if (!isset($explicit[$t])) {
                    $explicit[$t] = ['style' => [], 'hover' => []];
                }
                if ($c['style'] !== '') {
                    $explicit[$t]['style'][] = $c['style'];
                }
                if (!empty($c['hover'])) {
                    $explicit[$t]['hover'] = array_merge($explicit[$t]['hover'], $c['hover']);
                }
            }
        }

        $items = [];
        foreach ($fields as $k => $f) {
            if (!$isContent($k, $f)) {
                continue;
            }
            $style = $styleFor($k);
            $hoverD = $hoverDecls($k);
            if (isset($explicit[$k])) {
                if (!empty($explicit[$k]['style'])) {
                    $style = trim($style.';'.implode(';', $explicit[$k]['style']), ';');
                }
                $hoverD = array_merge($hoverD, $explicit[$k]['hover']);
            }
            $hoverClass = $mkHoverClass($hoverD);
            if ($f['type'] === 'repeater') {
                $subDefs = !empty($f['fields']) ? $f['fields'] : ($f['params'] ?? []);
                $subFields = [];
                foreach ($subDefs as $sp) {
                    $sk = $sp['param_name'] ?? trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($sp['heading'] ?? '')), '_');
                    $subFields[] = ['key' => $sk, 'type' => $sp['type'] ?? 'text'];
                }
                $items[] = ['kind' => 'repeater', 'key' => $k, 'style' => $style, 'hoverClass' => $hoverClass,
                    'rows' => (is_array($s[$k] ?? null) ? $s[$k] : []), 'subFields' => $subFields];
            } elseif ($f['type'] === 'button') {
                $items[] = ['kind' => 'button', 'key' => $k, 'value' => $s[$k] ?? '', 'style' => $style, 'hoverClass' => $hoverClass,
                    'url' => $s[$k.'_url'] ?? '', 'target' => $s[$k.'_target'] ?? '_self'];
            } else {
                $val = ($f['type'] === 'checkbox') ? (is_array($s[$k] ?? null) ? implode(', ', $s[$k]) : '') : ($s[$k] ?? null);
                $items[] = ['kind' => $f['type'], 'key' => $k, 'value' => $val, 'style' => $style, 'hoverClass' => $hoverClass];
            }
        }

        // Orphan prefix modifiers (no matching content field, no apply_to) → wrapper
        $wrapperStyle = '';
        $wrapperHoverClass = '';
        foreach ($fields as $k => $f) {
            if (!empty($f['apply_to'])) {
                continue;
            }
            $base = $modBase($k, $f['type']);
            if ($base && !in_array($base, $contentKeys, true)) {
                $ws = $styleFor($base);
                if ($ws) {
                    $wrapperStyle .= ($wrapperStyle ? ';' : '').$ws;
                }
                $hc = $mkHoverClass($hoverDecls($base));
                if ($hc) {
                    $wrapperHoverClass = $hc;
                }
            }
        }

        return compact('wrapperStyle', 'wrapperHoverClass', 'hoverCss', 'items');
    }
}

if (!function_exists('lazy_revision_diff')) {
    /**
     * Produce an HTML line-level diff between two content versions (for the revisions compare page).
     * Builder JSON is converted to readable shortcodes first. Uses an LCS line diff — no external deps.
     */
    function lazy_revision_diff(string $old, string $new): string
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

if (!function_exists('remove_falcon_action')) {
    function remove_falcon_action($tag, $callback, $priority = 10)
    {
        return HookManager::getInstance()->removeAction($tag, $callback, $priority);
    }
}

if (!function_exists('remove_falcon_filter')) {
    function remove_falcon_filter($tag, $callback, $priority = 10)
    {
        return HookManager::getInstance()->removeFilter($tag, $callback, $priority);
    }
}

if (!function_exists('lazy_lang_switcher')) {
    function lazy_lang_switcher($showFlags = true)
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return '';
            }
            $languages = Language::where('status', true)->get();
            if ($languages->count() <= 1) {
                return '';
            }

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
                        $url = get_falcon_permalink($equivalent);
                    }
                }

                $output .= '<a href="'.$url.'" class="flex items-center text-[13px] '.($isActive ? 'font-bold text-blue-600' : 'text-gray-600 hover:text-black').'">';
                if ($showFlags) {
                    $output .= '<span class="mr-1">'.$lang->flag.'</span> ';
                }
                $output .= strtoupper($lang->code);
                $output .= '</a>';
            }
            $output .= '</div>';

            return $output;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('falcon_lang_dropdown')) {
    function falcon_lang_dropdown()
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return '';
            }
            $activeLangs = Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) {
                return '';
            }

            $currentLang = $activeLangs->where('code', app()->getLocale())->first() ?? $activeLangs->first();

            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function ($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) {
                        return true;
                    }

                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) {
                return '';
            }

            $displayMode = get_cms_option('lang_switcher_display', 'both');

            $output = '<div class="relative group inline-block language-switcher-dropdown">';
            $output .= '<button class="flex items-center gap-1.5 text-slate-700 hover:text-primary transition-colors text-[13px] font-bold cursor-pointer" onclick="this.nextElementSibling.classList.toggle(\'hidden\')">';

            $currentLangCode = strtolower($currentLang->code);
            $countryMap = [
                'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                'br' => 'br', 'in' => 'in',
            ];
            $currentFlagCode = $countryMap[$currentLangCode] ?? $currentLangCode;

            if (in_array($displayMode, ['both', 'flag_only'])) {
                $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                $output .= '<img src="'.url('/assets/flags/'.$currentFlagCode.'.png').'" class="w-full h-full object-cover" alt="'.$currentLang->name.'">';
                $output .= '</span>';
            }

            if (in_array($displayMode, ['both', 'text_only'])) {
                $output .= '<span class="uppercase">'.$currentLang->name.'</span>';
            } elseif ($displayMode === 'code_only') {
                $output .= '<span class="uppercase">'.$currentLang->code.'</span>';
            }

            $output .= '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
            $output .= '</button>';
            $output .= '<div class="absolute top-full right-0 mt-2 w-32 bg-white border border-slate-100 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 rounded-md overflow-hidden">';
            $output .= '<ul class="py-1 m-0 list-none">';

            foreach ($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);

                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_falcon_permalink($currentPost);
                    }
                }

                $output .= '<li>';
                $output .= '<a href="'.$url.'" class="flex items-center justify-between gap-2 px-4 py-2 text-[13px] font-medium text-slate-600 hover:text-primary hover:bg-slate-50 transition-all '.($isActive ? 'bg-slate-50 text-primary font-bold' : '').'">';
                $output .= '<div class="flex items-center gap-2">';

                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in',
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                    $output .= '<img src="'.url('/assets/flags/'.$flagCode.'.png').'" class="w-full h-full object-cover" alt="'.$lang->name.'">';
                    $output .= '</span>';
                }

                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span>'.$lang->name.'</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="uppercase">'.$lang->code.'</span>';
                }

                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a></li>';
            }

            $output .= '</ul></div></div>';

            return $output;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('lazy_mobile_lang_switcher')) {
    function lazy_mobile_lang_switcher()
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return '';
            }
            $activeLangs = Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) {
                return '';
            }

            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function ($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) {
                        return true;
                    }

                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) {
                return '';
            }

            $displayMode = get_cms_option('lang_switcher_display', 'both');
            $output = '<div class="grid grid-cols-2 gap-2">';
            foreach ($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);

                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_falcon_permalink($currentPost);
                    }
                }

                $output .= '<a href="'.$url.'" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border '.($isActive ? 'border-primary bg-primary/5 text-primary' : 'border-slate-100 text-slate-600').' transition-all">';
                $output .= '<div class="flex items-center gap-2">';

                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in',
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="w-6 h-4 overflow-hidden rounded-sm flex items-center justify-center shrink-0 border border-slate-100 shadow-sm">';
                    $output .= '<img src="'.url('/assets/flags/'.$flagCode.'.png').'" class="w-full h-full object-cover" alt="'.$lang->name.'">';
                    $output .= '</span>';
                }

                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span class="text-[13px] font-semibold">'.$lang->name.'</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="text-[13px] font-semibold uppercase">'.$lang->code.'</span>';
                }

                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a>';
            }
            $output .= '</div>';

            return $output;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('the_falcon_lang_dropdown')) {
    function the_falcon_lang_dropdown()
    {
        echo falcon_lang_dropdown();
    }
}

if (!function_exists('lazy_search_form')) {
    function lazy_search_form($placeholder = 'Search...')
    {
        $url = route('frontend.search');
        $output = '<form action="'.$url.'" method="GET" class="relative lazy-search-form">';
        $output .= '<input type="text" name="s" placeholder="'.e($placeholder).'" class="w-full bg-slate-50 border border-slate-200 rounded-full px-5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">';
        $output .= '<button type="submit" class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-primary text-white rounded-full text-xs font-bold hover:bg-primary/90 transition-colors uppercase">Search</button>';
        $output .= '</form>';

        return $output;
    }
}

if (!function_exists('the_lazy_search_form')) {
    function the_lazy_search_form($placeholder = 'Search...')
    {
        echo lazy_search_form($placeholder);
    }
}

if (!function_exists('render_lazy_form')) {
    function render_lazy_form($slug)
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

if (!function_exists('add_falcon_shortcode')) {
    /**
     * Register a shortcode handler. Themes and plugins call this to expose
     * `[tag attr="value"]` shortcodes that render on the frontend. The callback
     * receives an associative array of the parsed attributes and returns HTML.
     */
    function add_falcon_shortcode($tag, callable $callback)
    {
        $GLOBALS['__falcon_shortcodes'][$tag] = $callback;
    }
}

if (!function_exists('falcon_parse_shortcode_atts')) {
    /** Parse a shortcode attribute string into an assoc array. Handles &quot; too. */
    function falcon_parse_shortcode_atts($text)
    {
        $atts = [];
        if (!is_string($text) || trim($text) === '') {
            return $atts;
        }
        // key="value" | key='value' | key=value | key=&quot;value&quot;
        if (preg_match_all('/(\w+)\s*=\s*(?:&quot;|["\'])?([^"\'\]\s&]+)(?:&quot;|["\'])?/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $atts[$pair[1]] = $pair[2];
            }
        }

        return $atts;
    }
}

if (!function_exists('falcon_do_shortcodes')) {
    /** Process all registered shortcodes in a content string. */
    function falcon_do_shortcodes($content)
    {
        if (empty($GLOBALS['__falcon_shortcodes']) || !is_string($content) || $content === '') {
            return $content;
        }
        foreach ($GLOBALS['__falcon_shortcodes'] as $tag => $callback) {
            $pattern = '/\['.preg_quote($tag, '/').'(\b[^\]]*)?\]/';
            $content = preg_replace_callback($pattern, function ($m) use ($callback) {
                $atts = falcon_parse_shortcode_atts($m[1] ?? '');

                return (string) call_user_func($callback, $atts);
            }, $content);
        }

        return $content;
    }
}

if (!function_exists('do_lazy_shortcode')) {
    function do_lazy_shortcode($content)
    {
        if (empty($content)) {
            return $content;
        }

        // Match [falcon_form slug="..."] — also accept &quot; (entity-encoded quotes from WYSIWYG editors)
        // Do NOT html_entity_decode the entire string: that would undo Blade's {{ }} escaping and open XSS.
        $content = preg_replace_callback(
            '/\[falcon_form\s+slug=(?:&quot;|["\'])([^"\'&\[\]]+)(?:&quot;|["\'])\s*\]/',
            function ($matches) {
                return render_lazy_form($matches[1]);
            },
            $content
        );

        $shortcodes = [
            '[falcon_search]' => lazy_search_form(),
            '[falcon_lang_dropdown]' => falcon_lang_dropdown(),
        ];
        $content = str_replace(array_keys($shortcodes), array_values($shortcodes), $content);

        // Theme/plugin-registered shortcodes (add_falcon_shortcode).
        return falcon_do_shortcodes($content);
    }
}

if (!function_exists('falcon_translate')) {
    function falcon_translate($text, $targetLang = 'en', $sourceLang = 'auto')
    {
        if (empty($text)) {
            return $text;
        }

        // Map common CMS codes to Google Translate codes
        $map = [
            'jp' => 'ja', 'gb' => 'en', 'in' => 'hi', 'cn' => 'zh-CN', 'kr' => 'ko',
            'ua' => 'uk', 'br' => 'pt', 'sa' => 'ar', 'bd' => 'bn', 'zh' => 'zh-CN',
            'ja' => 'ja', 'ko' => 'ko', 'pt' => 'pt', 'hi' => 'hi',
        ];

        $targetLang = $map[strtolower($targetLang)] ?? $targetLang;
        $sourceLang = $map[strtolower($sourceLang)] ?? $sourceLang;

        try {
            $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl='.$sourceLang.'&tl='.$targetLang.'&dt=t&q='.urlencode($text);

            $options = [
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                ],
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
        } catch (Exception $e) {
        }

        return $text;
    }
}

if (!function_exists('get_lazy_shop_url')) {
    function get_lazy_shop_url()
    {
        $pageId = get_shop_option('shop_shop_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            if ($page) {
                return get_falcon_permalink($page);
            }
        }

        return url('/product');
    }
}

if (!function_exists('get_falcon_cart_url')) {
    function get_falcon_cart_url()
    {
        $pageId = get_shop_option('shop_cart_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            if ($page) {
                return get_falcon_permalink($page);
            }
        }

        return route('shop.cart');
    }
}

if (!function_exists('get_lazy_checkout_url')) {
    function get_lazy_checkout_url()
    {
        $pageId = get_shop_option('shop_checkout_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            if ($page) {
                return get_falcon_permalink($page);
            }
        }

        return route('shop.checkout');
    }
}

if (!function_exists('falcon_price_format')) {
    function falcon_price_format($price, $order = null)
    {
        if ($order && is_object($order) && isset($order->currency_symbol)) {
            $symbol = $order->currency_symbol;
            $position = $order->currency_position ?? 'left';
            $decimals = (int) ($order->decimals ?? 2);
            $thousandSep = $order->thousand_separator ?? ',';
            $decimalSep = $order->decimal_separator ?? '.';
        } else {
            $currencyCode = get_shop_option('shop_currency', 'USD');
            $symbol = EcommerceData::getCurrencySymbol($currencyCode);

            $position = get_shop_option('shop_currency_pos', 'left');
            $decimals = (int) get_shop_option('shop_num_decimals', 2);
            $thousandSep = get_shop_option('shop_thousand_sep', ',');
            $decimalSep = get_shop_option('shop_decimal_sep', '.');
        }

        $formatted = number_format((float) $price, $decimals, $decimalSep, $thousandSep);

        switch ($position) {
            case 'left':
                return $symbol.$formatted;
            case 'right':
                return $formatted.$symbol;
            case 'left_space':
                return $symbol.' '.$formatted;
            case 'right_space':
                return $formatted.' '.$symbol;
            default:
                return $symbol.$formatted;
        }
    }
}

if (!function_exists('get_falcon_cart_count')) {
    function get_falcon_cart_count()
    {
        $cart = session()->get('falcon_cart', []);
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['quantity'] ?? 0;
        }

        return $total;
    }
}

if (!function_exists('get_falcon_cart_subtotal')) {
    function get_falcon_cart_subtotal()
    {
        $cart = session()->get('falcon_cart', []);
        $subtotal = 0;
        foreach ($cart as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal += $price * $item['quantity'];
        }

        return $subtotal;
    }
}

if (!function_exists('get_falcon_cart_shipping')) {
    /**
     * Calculate shipping cost based on subtotal, quantity, and location.
     *
     * @param  string|null  $country  Customer country code
     * @return float
     */
    function get_falcon_cart_shipping($country = null)
    {
        $details = get_falcon_cart_shipping_details($country);

        return $details['cost'];
    }
}

if (!function_exists('falcon_attribute_slug')) {
    /**
     * URL-safe key for an attribute name or value.
     *
     * Str::slug() transliterates what it can (Bengali "নীল" becomes "neel") but returns an empty
     * string for scripts it has no map for — Chinese, emoji, punctuation-only values. Falling
     * back to the lower-cased original keeps those distinct; the slug only has to match itself.
     */
    function falcon_attribute_slug(string $text): string
    {
        $slug = Str::slug($text);

        return $slug !== '' ? $slug : mb_strtolower(trim($text));
    }
}

if (!function_exists('falcon_product_attribute_definitions')) {
    /**
     * The attributes a product declares, normalised out of shop_products.attributes_data.
     *
     * The stored shape is `[{name, values: "Red | Green | Blue", visible: "1", variation: "1"}]`,
     * written by the admin's Attributes tab.
     *
     * @return array<int, array{name: string, values: array<int, string>, visible: bool, variation: bool, filterable: bool}>
     */
    function falcon_product_attribute_definitions($shopData): array
    {
        $raw = $shopData->attributes_data ?? null;

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = is_scalar($entry['name'] ?? null) ? trim((string) $entry['name']) : '';
            if ($name === '') {
                continue;
            }

            $values = [];
            $rawValues = $entry['values'] ?? '';
            foreach (is_array($rawValues) ? $rawValues : explode('|', (string) $rawValues) as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = trim((string) $value);
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            $out[] = [
                'name' => $name,
                'values' => array_values(array_unique($values)),
                'visible' => (string) ($entry['visible'] ?? '') === '1',
                'variation' => (string) ($entry['variation'] ?? '') === '1',
                // Attributes saved before this option existed carry no key, and a shop owner who
                // adds an attribute expects to filter by it — so absent means yes.
                'filterable' => !array_key_exists('filterable', $entry) || (string) $entry['filterable'] === '1',
            ];
        }

        return $out;
    }
}

if (!function_exists('falcon_sync_product_attribute_index')) {
    /**
     * Rebuild one product's rows in the attribute index.
     *
     * Called on every product save. The index is derived data, so this replaces it wholesale
     * rather than patching it — a removed attribute or a renamed value cannot linger.
     */
    function falcon_sync_product_attribute_index($shopData): void
    {
        if (!$shopData || !Schema::hasTable('shop_product_attribute_values')) {
            return;
        }

        $postId = (int) ($shopData->post_id ?? 0);
        if ($postId <= 0) {
            return;
        }

        try {
            $rows = [];
            $seen = ['names' => [], 'values' => []];
            $isVariable = $shopData->isVariable();

            // Claims a slug, appending -2, -3 … when something already took it.
            $unique = static function (string $slug, array &$taken): string {
                if ($slug === '') {
                    return '';
                }
                $candidate = $slug;
                $n = 1;
                while (isset($taken[$candidate])) {
                    $candidate = $slug.'-'.(++$n);
                }
                $taken[$candidate] = true;

                return $candidate;
            };

            // What the variations actually offer, keyed by attribute name.
            $fromVariations = [];
            if ($isVariable) {
                foreach ($shopData->variations()->get(['attributes_data']) as $variation) {
                    $attrs = $variation->attributes_data;
                    if (is_string($attrs)) {
                        $attrs = json_decode($attrs, true);
                    }
                    if (!is_array($attrs)) {
                        continue;
                    }
                    foreach ($attrs as $name => $value) {
                        if (is_scalar($value) && trim((string) $value) !== '') {
                            $fromVariations[trim((string) $name)][] = trim((string) $value);
                        }
                    }
                }
            }

            foreach (falcon_product_attribute_definitions($shopData) as $attribute) {
                if (!$attribute['filterable']) {
                    continue;
                }

                // For a variable product the variations are the honest answer: the parent may
                // still list a colour nobody built a variation for, and filtering to it would
                // surface a product the shopper cannot actually buy. Fall back to the declared
                // list while no variations exist yet.
                $values = $attribute['values'];
                if ($isVariable && $attribute['variation'] && !empty($fromVariations[$attribute['name']])) {
                    $values = array_values(array_unique($fromVariations[$attribute['name']]));
                }
                if (empty($values)) {
                    continue;
                }

                $nameSlug = $unique(falcon_attribute_slug($attribute['name']), $seen['names']);
                if ($nameSlug === '') {
                    continue;
                }
                $seen['values'][$nameSlug] = $seen['values'][$nameSlug] ?? [];
                $sameValue = [];

                foreach ($values as $value) {
                    // "Blue", "blue" and " Blue " are one value — collapse them before slugging so
                    // they do not consume a disambiguation suffix.
                    $normalised = mb_strtolower(trim($value));
                    if ($normalised === '' || isset($sameValue[$normalised])) {
                        continue;
                    }
                    $sameValue[$normalised] = true;

                    // Genuinely different values can still slug the same ("XL" and "XL+" both
                    // reduce to "xl"). Suffixing keeps both filterable instead of silently
                    // dropping whichever came second.
                    $valueSlug = $unique(falcon_attribute_slug($value), $seen['values'][$nameSlug]);
                    if ($valueSlug === '') {
                        continue;
                    }

                    $rows[] = [
                        'post_id' => $postId,
                        'name' => mb_substr($attribute['name'], 0, 60),
                        'name_slug' => mb_substr($nameSlug, 0, 60),
                        'value' => mb_substr($value, 0, 120),
                        'value_slug' => mb_substr($valueSlug, 0, 120),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::transaction(function () use ($postId, $rows) {
                DB::table('shop_product_attribute_values')
                    ->where('post_id', $postId)->delete();

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('shop_product_attribute_values')->insert($chunk);
                }
            });
        } catch (Throwable $e) {
            // A broken index must never stop a shop owner from saving a product.
            Illuminate\Support\Facades\Log::error('Attribute index sync failed for post '.$postId.': '.$e->getMessage());
        }
    }
}

if (!function_exists('falcon_reindex_all_product_attributes')) {
    /**
     * Rebuild the whole attribute index. Used by the install migration and by
     * `php artisan falcon:reindex-attributes`.
     *
     * @return int products processed
     */
    function falcon_reindex_all_product_attributes(): int
    {
        if (!Schema::hasTable('shop_product_attribute_values')
            || !Schema::hasTable('shop_products')) {
            return 0;
        }

        // Products whose post is gone (or whose foreign key was never created) leave orphans.
        DB::table('shop_product_attribute_values')
            ->whereNotIn('post_id', DB::table('posts')->select('id'))
            ->delete();

        $count = 0;
        ProductData::query()
            ->whereNotNull('attributes_data')
            ->chunkById(100, function ($chunk) use (&$count) {
                foreach ($chunk as $shopData) {
                    falcon_sync_product_attribute_index($shopData);
                    $count++;
                }
            });

        return $count;
    }
}

if (!function_exists('falcon_product_filters_active')) {
    /**
     * The archive filters currently in the URL, already validated.
     *
     * Everything is read through this one function so the query, the sidebar and the "active
     * filters" chips can never disagree about what is being filtered.
     *
     * @return array{search: string, min_price: ?float, max_price: ?float, categories: array<int, string>, attributes: array<string, array<int, string>>, in_stock: bool, on_sale: bool}
     */
    function falcon_product_filters_active(): array
    {
        $request = request();

        $price = static function ($value): ?float {
            // Arrays and objects arrive whenever someone hand-edits the query string.
            if (!is_scalar($value) || $value === '' || !is_numeric($value)) {
                return null;
            }
            $value = (float) $value;

            // INF/NAN (e.g. `1e400`) would poison every comparison downstream.
            return is_finite($value) ? max(0.0, $value) : null;
        };

        // Slugs only — they are matched against a column, never interpolated into SQL.
        // Non-scalars (`product_cat[][]=x`) are dropped rather than stringified, so a nested
        // array in the URL cannot reach the view and blow up htmlspecialchars().
        $categories = array_values(array_filter(array_map(
            static fn ($slug) => is_string($slug) ? trim($slug) : '',
            array_filter((array) $request->query('product_cat', []), 'is_scalar')
        )));

        // Attribute filters arrive as `?attr[color][]=blue&attr[size][]=xl`. Both halves are
        // slugs matched against indexed columns, never interpolated anywhere.
        $attributes = [];
        foreach ((array) $request->query('attr', []) as $name => $values) {
            // Bounded on purpose: a hand-written URL with thousands of keys would otherwise turn
            // into thousands of subqueries.
            if (count($attributes) >= 12) {
                break;
            }
            if (!is_string($name)) {
                continue;
            }
            $name = trim($name);
            if ($name === '' || mb_strlen($name) > 60) {
                continue;
            }

            $clean = [];
            foreach (array_filter((array) $values, 'is_scalar') as $value) {
                if (count($clean) >= 60) {
                    break;
                }
                $value = trim((string) $value);
                if ($value !== '' && mb_strlen($value) <= 120) {
                    $clean[] = $value;
                }
            }
            if ($clean) {
                $attributes[$name] = array_values(array_unique($clean));
            }
        }

        // Free-text search. Capped so a pathological term cannot drive an expensive LIKE.
        $search = $request->query('s');
        $search = is_scalar($search) ? trim((string) $search) : '';
        $search = $search !== '' ? mb_substr($search, 0, 120) : '';

        $min = $price($request->query('min_price'));
        $max = $price($request->query('max_price'));

        // A reversed range would silently match nothing; swapping is what the shopper meant.
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'search' => $search,
            'min_price' => $min,
            'max_price' => $max,
            'categories' => $categories,
            'attributes' => $attributes,
            'in_stock' => $request->query('in_stock') === '1',
            'on_sale' => $request->query('on_sale') === '1',
        ];
    }
}

if (!function_exists('falcon_apply_product_filters')) {
    /**
     * Narrow a product query by the archive filters.
     *
     * Deliberately uses whereHas subqueries rather than joins: the sorting code already joins
     * shop_products for price ordering, and a second join on the same table would break the
     * query. Subqueries compose with anything.
     */
    function falcon_apply_product_filters($query, ?array $filters = null)
    {
        $filters = $filters ?? falcon_product_filters_active();

        if (($filters['search'] ?? '') !== '') {
            // `%` and `_` are LIKE wildcards. Left unescaped, a search for "100%" would match
            // everything, and "_" would match any single character — neither is what was typed.
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';

            $query->where(function ($q) use ($term) {
                $q->where('posts.title', 'like', $term)
                    ->orWhere('posts.excerpt', 'like', $term)
                    // SKU matters more than prose in a shop: staff and repeat buyers search by it.
                    ->orWhereHas('shopData', fn ($sd) => $sd->where('sku', 'like', $term))
                    ->orWhereHas('shopData.variations', fn ($v) => $v->where('sku', 'like', $term));
            });
        }

        // Effective price = sale price when there is one, otherwise the regular price.
        //
        // Variable products keep their prices on the variations, not on the parent row — the
        // admin hides the price fields for them — so a parent-only check would drop every
        // variable product from any price filter. Both bounds are applied to the *same* row so
        // "1000-2000" means one variation actually costs that, not that the range merely
        // overlaps the product's spread.
        if ($filters['min_price'] !== null || $filters['max_price'] !== null) {
            $priceBounds = static function ($q) use ($filters) {
                $expr = 'COALESCE(NULLIF(sale_price, 0), price)';

                // The bound value is CAST because PDO sends floats as strings, and the left
                // side here is an expression, which carries no column affinity to coerce them
                // back. MySQL compares them as numbers anyway; SQLite sorts every integer
                // before every string, so `500 >= '200'` came out false and the price filter
                // returned an empty page on every SQLite site.
                $bound = 'CAST(? AS DECIMAL(10,2))';

                if ($filters['min_price'] !== null) {
                    $q->whereRaw($expr.' >= '.$bound, [$filters['min_price']]);
                }
                if ($filters['max_price'] !== null) {
                    $q->whereRaw($expr.' <= '.$bound, [$filters['max_price']]);
                }
            };

            $query->where(function ($outer) use ($priceBounds) {
                $outer->whereHas('shopData', function ($q) use ($priceBounds) {
                    // Excluded so a stale parent price left over from when the product was
                    // simple cannot match on a variable product's behalf.
                    $q->notVariable();
                    $priceBounds($q);
                })
                    ->orWhereHas('shopData', function ($q) use ($priceBounds) {
                        // Gated on the parent type: switching a product back to simple leaves its
                        // old variation rows in place, and those must not match any more.
                        $q->variable()->whereHas('variations', $priceBounds);
                    });
            });
        }

        if (!empty($filters['categories'])) {
            $query->whereHas('productCategories', fn ($q) => $q->whereIn('product_categories.slug', $filters['categories']));
        }

        // Values within one attribute are OR'd, separate attributes are AND'd — picking Red and
        // Blue widens the results, adding a size narrows them. That is what shoppers expect from
        // a layered filter, and each attribute needs its own subquery to express it.
        foreach ($filters['attributes'] ?? [] as $nameSlug => $valueSlugs) {
            $query->whereHas(
                'attributeValues',
                fn ($q) => $q->where('name_slug', $nameSlug)->whereIn('value_slug', $valueSlugs)
            );
        }

        if ($filters['on_sale']) {
            // A variable product is on sale when any one of its variations is.
            $onSale = static fn ($q) => $q->whereNotNull('sale_price')->where('sale_price', '>', 0);

            // Variations carry no end date, but the parent does — and an expired sale must drop
            // out of this filter the moment it ends, not whenever falcon:expire-sales next runs.
            $liveSale = static fn ($q) => $q->whereNotNull('sale_price')
                ->where('sale_price', '>', 0)
                ->where(fn ($w) => $w->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now()));

            $query->where(function ($outer) use ($onSale, $liveSale) {
                $outer->whereHas('shopData', function ($q) use ($liveSale) {
                    $q->notVariable();
                    $liveSale($q);
                })
                    ->orWhereHas('shopData', function ($q) use ($onSale) {
                        $q->variable()->whereHas('variations', $onSale);
                    });
            });
        }

        if ($filters['in_stock']) {
            $threshold = (int) get_shop_option('shop_out_of_stock_threshold', '0');
            $globalManage = get_shop_option('shop_manage_stock', '1') === '1';

            // Mirrors ProductData::isInStock() in SQL. A product with no shop row is treated as
            // available, exactly as the accessor does.

            // The parent's own shelf: what a simple product — and a variation that does not track
            // its own stock — is sold from.
            $parentShelf = static function ($q) use ($threshold, $globalManage) {
                if ($globalManage) {
                    $q->where(fn ($inner) => $inner
                        ->where('manage_stock', 0)
                        ->orWhere('stock_quantity', '>', $threshold)
                        ->orWhereIn('backorders', ['notify', 'yes']));
                }
            };

            $query->where(function ($outer) use ($threshold, $globalManage, $parentShelf) {
                $outer->whereDoesntHave('shopData')
                    ->orWhereHas('shopData', function ($q) use ($threshold, $globalManage, $parentShelf) {
                        $q->where('stock_status', '!=', 'outofstock')
                            ->where(function ($w) use ($threshold, $globalManage, $parentShelf) {
                                // Simple products, and variable ones with no variations built yet.
                                $w->where(function ($simple) use ($parentShelf) {
                                    $simple->where(fn ($t) => $t->notVariable()
                                        ->orWhereDoesntHave('variations'));
                                    $parentShelf($simple);
                                })
                                // A variable product is only as available as its variations.
                                    ->orWhere(function ($variable) use ($threshold, $globalManage, $parentShelf) {
                                        $variable->variable()
                                            ->where(function ($any) use ($threshold, $globalManage, $parentShelf) {
                                                // Backorders are set on the parent, so once they are on,
                                                // any variation still on the shelf can be sold.
                                                $any->where(function ($bo) {
                                                    $bo->whereIn('backorders', ['notify', 'yes'])
                                                        ->whereHas('variations', fn ($v) => $v->where('stock_status', '!=', 'outofstock'));
                                                })
                                                    // A variation holding its own stock.
                                                    ->orWhereHas('variations', function ($v) use ($threshold, $globalManage) {
                                                        $v->where('stock_status', '!=', 'outofstock');
                                                        if ($globalManage) {
                                                            $v->where('manage_stock', 1)->where('stock_quantity', '>', $threshold);
                                                        }
                                                    })
                                                    // A variation that inherits the parent's shelf.
                                                    ->orWhere(function ($inherit) use ($parentShelf) {
                                                        $inherit->whereHas('variations', fn ($v) => $v->where('stock_status', '!=', 'outofstock')
                                                            ->where('manage_stock', 0));
                                                        $parentShelf($inherit);
                                                    });
                                            });
                                    });
                            });
                    });
            });
        }

        return $query;
    }
}

if (!function_exists('falcon_apply_product_sorting')) {
    /**
     * Order a product query. Extracted so the shop page and the taxonomy archives cannot drift
     * apart — they each had their own copy of this switch.
     */
    function falcon_apply_product_sorting($query, ?string $orderby = null)
    {
        $orderby = $orderby ?? request('orderby', 'latest');

        switch ($orderby) {
            case 'price':
            case 'price-desc':
                $direction = $orderby === 'price' ? 'ASC' : 'DESC';

                // Sort a variable product by the cheapest variation when going low-to-high and
                // by the dearest when going high-to-low — matching the range a shopper sees.
                // Without the subquery every variable product sorts as NULL and clumps at one end.
                $agg = $orderby === 'price' ? 'MIN' : 'MAX';
                $expr = 'COALESCE('
                    .'(SELECT '.$agg.'(COALESCE(NULLIF(v.sale_price, 0), v.price))'
                    .' FROM shop_product_variations v WHERE v.product_id = shop_products.id'
                    ." AND (shop_products.type = 'variable' OR shop_products.product_type = 'variable')),"
                    .' COALESCE(NULLIF(shop_products.sale_price, 0), shop_products.price))';

                $query->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                    ->orderByRaw($expr.' '.$direction)
                    ->select('posts.*');
                break;

            case 'rating':
                $query->withCount(['reviews as average_rating' => fn ($q) => $q->select(DB::raw('avg(rating)'))])
                    ->orderBy('average_rating', 'desc');
                break;

            case 'popularity':
                $query->withCount('reviews')->orderBy('reviews_count', 'desc');
                break;

            case 'latest':
            default:
                $query->latest();
                break;
        }

        return $query;
    }
}

if (!function_exists('falcon_product_filter_options')) {
    /**
     * Data the filter sidebar needs: the categories on offer with their counts, and the price
     * range of the products being browsed.
     *
     * Counts come from the unfiltered set so a category never vanishes the moment it is
     * deselected — a filter panel that erases its own options is unusable.
     *
     * @param  callable  $baseQuery  returns a fresh, unfiltered query for this archive
     */
    function falcon_product_filter_options(callable $baseQuery): array
    {
        try {
            $ids = $baseQuery()->pluck('posts.id');

            $categories = DB::table('product_categories')
                ->join('product_category_post', 'product_category_post.product_category_id', '=', 'product_categories.id')
                ->whereIn('product_category_post.post_id', $ids)
                ->groupBy('product_categories.id', 'product_categories.name', 'product_categories.slug')
                ->orderBy('product_categories.name')
                ->get([
                    'product_categories.name',
                    'product_categories.slug',
                    DB::raw('COUNT(DISTINCT product_category_post.post_id) as total'),
                ]);

            // The band has to cover variation prices too, or the placeholders would understate
            // the real range on any shop that sells variable products.
            $bounds = DB::table('shop_products')
                ->leftJoin('shop_product_variations as v', function ($j) {
                    $j->on('v.product_id', '=', 'shop_products.id')
                        ->where(fn ($w) => $w->where('shop_products.type', '=', 'variable')
                            ->orWhere('shop_products.product_type', '=', 'variable'));
                })
                ->whereIn('shop_products.post_id', $ids)
                ->selectRaw(
                    'MIN(COALESCE(NULLIF(v.sale_price, 0), v.price, NULLIF(shop_products.sale_price, 0), shop_products.price)) as min_price,'
                    .' MAX(COALESCE(NULLIF(v.sale_price, 0), v.price, NULLIF(shop_products.sale_price, 0), shop_products.price)) as max_price'
                )
                ->first();

            // Whatever attributes this set of products happens to declare, grouped for the
            // sidebar. Nothing here is hard-coded, so a brand new attribute shows up by itself.
            $attributes = [];
            if (Schema::hasTable('shop_product_attribute_values')) {
                $rows = DB::table('shop_product_attribute_values')
                    ->whereIn('post_id', $ids)
                    ->groupBy('name', 'name_slug', 'value', 'value_slug')
                    ->orderBy('name')
                    ->orderBy('value')
                    ->get([
                        'name', 'name_slug', 'value', 'value_slug',
                        DB::raw('COUNT(DISTINCT post_id) as total'),
                    ]);

                foreach ($rows as $row) {
                    if (!isset($attributes[$row->name_slug])) {
                        $attributes[$row->name_slug] = [
                            'name' => $row->name,
                            'slug' => $row->name_slug,
                            'values' => [],
                        ];
                    }
                    $attributes[$row->name_slug]['values'][] = [
                        'label' => $row->value,
                        'slug' => $row->value_slug,
                        'total' => (int) $row->total,
                    ];
                }
            }

            return [
                'categories' => $categories,
                'attributes' => array_values($attributes),
                'min_price' => $bounds && $bounds->min_price !== null ? (float) $bounds->min_price : 0.0,
                'max_price' => $bounds && $bounds->max_price !== null ? (float) $bounds->max_price : 0.0,
            ];
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Product filter options failed: '.$e->getMessage());

            return ['categories' => collect(), 'attributes' => [], 'min_price' => 0.0, 'max_price' => 0.0];
        }
    }
}

if (!function_exists('falcon_order_discount_lines')) {
    /**
     * Break an order's discount down into named lines the customer can recognise.
     *
     * Orders record a single `discount_total`, which on its own leaves the shopper staring at a
     * gap between the subtotal and the total. Coupon codes live on the order row and promotions
     * in its meta, so both can be named here.
     *
     * Whatever cannot be attributed is emitted as a final "Discount" line, so the figures always
     * reconcile — including for orders placed before promotions existed.
     *
     * @return array<int, array{label:string, note:?string, amount:float}>
     */
    function falcon_order_discount_lines($order): array
    {
        $total = round((float) ($order->discount_total ?? 0), 2);
        if ($total <= 0) {
            return [];
        }

        $lines = [];
        $accounted = 0.0;

        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        foreach ((array) ($meta['promotions'] ?? []) as $promo) {
            $amount = round((float) ($promo['discount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [
                'label' => (string) ($promo['name'] ?? 'Promotion'),
                'note' => $promo['summary'] ?? null,
                'amount' => $amount,
            ];
            $accounted += $amount;
        }

        $remainder = round($total - $accounted, 2);
        if ($remainder <= 0.009) {
            return $lines;
        }

        // Coupons share a single stored figure, so several codes are shown on one line rather
        // than guessing how the money was split between them.
        $codes = array_values(array_filter(array_map('trim', explode(',', (string) ($order->coupon_code ?? '')))));

        $lines[] = [
            'label' => $codes ? 'Coupon'.(count($codes) > 1 ? 's' : '').': '.implode(', ', $codes) : 'Discount',
            'note' => null,
            'amount' => $remainder,
        ];

        return $lines;
    }
}

if (!function_exists('falcon_promotion_matches_item')) {
    /**
     * Does a cart line fall inside a promotion's product / category list?
     *
     * An empty list means "anything", which is what makes a shop-wide rule expressible.
     * Category membership is matched through origin ids so translated duplicates of the same
     * product resolve to the same identity, exactly as the coupon restrictions do.
     */
    function falcon_promotion_matches_item(array $item, string $scope, array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $postId = (int) ($item['id'] ?? 0);
        if ($postId <= 0) {
            return false;
        }

        if ($scope === 'category') {
            static $catCache = [];
            if (!array_key_exists($postId, $catCache)) {
                $catCache[$postId] = DB::table('product_category_post')
                    ->join('product_categories', 'product_category_post.product_category_id', '=', 'product_categories.id')
                    ->where('product_category_post.post_id', $postId)
                    ->selectRaw('COALESCE(product_categories.origin_id, product_categories.id) as identity')
                    ->pluck('identity')->map(fn ($v) => (int) $v)->all();
            }

            return !empty(array_intersect($catCache[$postId], array_map('intval', $ids)));
        }

        static $idCache = [];
        if (!array_key_exists($postId, $idCache)) {
            $idCache[$postId] = (int) (DB::table('posts')
                ->where('id', $postId)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->value('identity') ?: $postId);
        }

        return in_array($idCache[$postId], array_map('intval', $ids), true);
    }
}

if (!function_exists('falcon_promotion_applications')) {
    /**
     * How many times a cart satisfies a promotion's condition.
     *
     * Shared by the discount calculation and the "you qualify" prompt so the two can never
     * disagree about whether an offer is earned.
     *
     * @param  array  $units  cart key => ['item'=>…, 'price'=>float, 'qty'=>int]
     * @param  array  $claimed  cart key => units an earlier promotion already gave away
     */
    function falcon_promotion_applications($promo, array $units, float $subtotal, array $claimed = []): int
    {
        $triggerQty = max(0.0, (float) $promo->trigger_qty);
        $rewardQty = max(1, (int) $promo->reward_qty);

        if ($promo->trigger_type === 'cart_total') {
            $applications = ($triggerQty > 0 && $subtotal >= $triggerQty) ? 1 : 0;
        } elseif ($triggerQty <= 0) {
            $applications = 0;
        } else {
            // Units an earlier promotion already gave away cannot also count towards qualifying
            // for this one — otherwise two "buy 1 get 1" rules on the same two items would make
            // both free, and the shop would be paid nothing.
            $matchedQty = 0;
            foreach ($units as $key => $u) {
                if (falcon_promotion_matches_item($u['item'], $promo->trigger_type, (array) ($promo->trigger_ids ?? []))) {
                    $matchedQty += max(0, $u['qty'] - ($claimed[$key] ?? 0));
                }
            }

            if ($promo->reward_scope === 'same') {
                // "Buy 2 get 1 free" needs three units per round — two paid, one free —
                // otherwise a basket of 2 would hand back both of them.
                $perRound = (int) ceil($triggerQty) + $rewardQty;
                $applications = intdiv($matchedQty, max(1, $perRound));
            } else {
                $applications = (int) floor($matchedQty / $triggerQty);
            }
        }

        if ($promo->max_applications !== null && $promo->max_applications > 0) {
            $applications = min($applications, (int) $promo->max_applications);
        }

        return max(0, $applications);
    }
}

if (!function_exists('falcon_promotion_reward_target')) {
    /**
     * Which pool of items a promotion rewards: [scope, ids].
     *
     * 'same' means the reward comes from whatever triggered the rule. A cart_total trigger has
     * no product list of its own to inherit, so it falls back to "anything".
     *
     * @return array{0: string, 1: array}
     */
    function falcon_promotion_reward_target($promo): array
    {
        if ($promo->reward_scope !== 'same') {
            return [$promo->reward_scope, (array) ($promo->reward_ids ?? [])];
        }

        if ($promo->trigger_type === 'cart_total') {
            return ['product', []];
        }

        return [$promo->trigger_type, (array) ($promo->trigger_ids ?? [])];
    }
}

if (!function_exists('falcon_pending_promotion_offers')) {
    /**
     * Offers the customer has already earned but is not receiving, because the reward item is
     * not in their basket.
     *
     * "Buy 3 phones, get a case free" only discounts a case that is actually in the cart — the
     * shopper has no way of knowing that on their own, so the cart shows a prompt with the
     * qualifying products and a one-click add.
     *
     * @return array<int, array{name:string, summary:string, missing:int, products:array}>
     */
    function falcon_pending_promotion_offers(?array $cart = null): array
    {
        $cart = $cart ?? session()->get('falcon_cart', []);
        if (empty($cart)) {
            return [];
        }

        $subtotal = 0.0;
        $units = [];
        foreach ($cart as $key => $item) {
            $price = (float) ($item['sale_price'] ?? $item['price']);
            $qty = (int) ($item['quantity'] ?? 0);
            $subtotal += $price * $qty;
            if ($qty > 0) {
                $units[$key] = ['item' => $item, 'price' => $price, 'qty' => $qty];
            }
        }

        $offers = [];

        foreach (falcon_active_promotions() as $promo) {
            // 'same'-scope rules reward the very items that triggered them, so there is never
            // anything for the customer to add.
            if ($promo->reward_scope === 'same') {
                continue;
            }

            $applications = falcon_promotion_applications($promo, $units, $subtotal);
            if ($applications < 1) {
                continue;
            }

            [$scope, $ids] = falcon_promotion_reward_target($promo);

            $inCart = 0;
            foreach ($units as $u) {
                if (falcon_promotion_matches_item($u['item'], $scope, $ids)) {
                    $inCart += $u['qty'];
                }
            }

            $wanted = $applications * max(1, (int) $promo->reward_qty);
            $missing = $wanted - $inCart;
            if ($missing < 1) {
                continue;   // already receiving it
            }

            $offers[] = [
                'name' => (string) $promo->name,
                'summary' => str_replace('{missing}', (string) $missing, $promo->customer_message),
                // Tells the view whether the shop supplied its own wording, so the default
                // "add N more item(s)" tail is only appended to the generated text.
                'custom' => trim((string) ($promo->cart_message ?? '')) !== '',
                'missing' => $missing,
                'products' => falcon_promotion_reward_products($scope, $ids),
            ];
        }

        return $offers;
    }
}

if (!function_exists('falcon_promotion_reward_products')) {
    /**
     * Buyable products that would satisfy a reward pool, for the cart prompt.
     * Capped because a category-wide reward could otherwise list the whole catalogue.
     */
    function falcon_promotion_reward_products(string $scope, array $ids, int $limit = 4): array
    {
        try {
            $query = DB::table('posts')
                ->join('shop_products', 'shop_products.post_id', '=', 'posts.id')
                ->where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->whereNull('posts.deleted_at');

            if (!empty($ids)) {
                if ($scope === 'category') {
                    $query->join('product_category_post', 'product_category_post.post_id', '=', 'posts.id')
                        ->whereIn('product_category_post.product_category_id', array_map('intval', $ids));
                } else {
                    $query->whereIn('posts.id', array_map('intval', $ids));
                }
            }

            return $query->distinct()
                ->orderBy('shop_products.price')
                ->limit($limit)
                ->get(['posts.id', 'posts.title', 'posts.slug', 'shop_products.price', 'shop_products.sale_price'])
                ->map(static fn ($r) => [
                    'id' => (int) $r->id,
                    'title' => $r->title,
                    'slug' => $r->slug,
                    'price' => (float) ($r->sale_price ?: $r->price),
                ])->all();
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Promotion reward product lookup failed: '.$e->getMessage());

            return [];
        }
    }
}

if (!function_exists('falcon_active_promotions')) {
    /** Promotions that are live right now, cheapest-priority first. */
    function falcon_active_promotions()
    {
        try {
            if (!Schema::hasTable('shop_promotions')) {
                return collect();
            }

            return Promotion::usable()->get();
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Promotion lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_evaluate_promotions')) {
    /**
     * Work out which promotions the cart currently earns, and what each is worth.
     *
     * Prices are read from the cart lines and every figure is recalculated here on each call —
     * nothing is cached in the session, so a customer cannot hold on to a reward after the
     * qualifying item leaves their basket.
     *
     * Each unit of stock can only be rewarded once: `$claimed` tracks how many units of every
     * line an earlier (higher-priority) rule already discounted, so two overlapping promotions
     * cannot both give away the same phone.
     *
     * @return array<int, array{id:int, name:string, summary:string, discount:float, applications:int}>
     */
    function falcon_evaluate_promotions(?array $cart = null): array
    {
        $cart = $cart ?? session()->get('falcon_cart', []);
        if (empty($cart)) {
            return [];
        }

        $subtotal = 0.0;
        $units = [];   // flattened: one entry per unit, so "cheapest first" is a plain sort
        foreach ($cart as $key => $item) {
            $price = (float) ($item['sale_price'] ?? $item['price']);
            $qty = (int) ($item['quantity'] ?? 0);
            $subtotal += $price * $qty;
            if ($qty > 0) {
                $units[$key] = ['item' => $item, 'price' => $price, 'qty' => $qty];
            }
        }

        $claimed = [];   // cart key => units already given away by an earlier promotion
        $results = [];

        foreach (falcon_active_promotions() as $promo) {
            $rewardQty = max(1, (int) $promo->reward_qty);
            $applications = falcon_promotion_applications($promo, $units, $subtotal, $claimed);
            if ($applications < 1) {
                continue;
            }

            [$scope, $ids] = falcon_promotion_reward_target($promo);

            $pool = [];
            foreach ($units as $key => $u) {
                if (!falcon_promotion_matches_item($u['item'], $scope, $ids)) {
                    continue;
                }
                $available = $u['qty'] - ($claimed[$key] ?? 0);
                for ($i = 0; $i < $available; $i++) {
                    $pool[] = ['key' => $key, 'price' => $u['price']];
                }
            }
            if (empty($pool)) {
                continue;
            }

            // Cheapest first — the customary reading of "get one free" and the safest for the shop.
            usort($pool, static fn ($a, $b) => $a['price'] <=> $b['price']);

            $wanted = $applications * $rewardQty;
            $taken = array_slice($pool, 0, $wanted);
            if (empty($taken)) {
                continue;
            }

            $discount = 0.0;
            foreach ($taken as $unit) {
                $discount += match ($promo->reward_type) {
                    'percent_off' => $unit['price'] * (min(100, max(0, $promo->reward_value)) / 100),
                    'fixed_off' => min($unit['price'], max(0, $promo->reward_value)),
                    default => $unit['price'],   // free_item
                };
                $claimed[$unit['key']] = ($claimed[$unit['key']] ?? 0) + 1;
            }

            if ($discount <= 0) {
                continue;
            }

            $results[] = [
                'id' => (int) $promo->id,
                'name' => (string) $promo->name,
                // The shop's own wording when it wrote some, otherwise the generated summary.
                // {missing} has no meaning once the reward is already applied, so it is dropped.
                'summary' => trim(str_replace('{missing}', '', $promo->customer_message)),
                'discount' => round($discount, 2),
                'applications' => $applications,
                'units' => count($taken),
            ];
        }

        return $results;
    }
}

if (!function_exists('falcon_cart_promotion_total')) {
    /** Total money the active promotions take off this cart. */
    function falcon_cart_promotion_total(?array $cart = null): float
    {
        $total = 0.0;
        foreach (falcon_evaluate_promotions($cart) as $applied) {
            $total += $applied['discount'];
        }

        return round($total, 2);
    }
}

if (!function_exists('falcon_all_coupons')) {
    /**
     * Every active coupon, in the array shape the cart and checkout already speak.
     *
     * Coupons live in the shop_coupons table (the code column is uniquely indexed, and the
     * redemption counter increments atomically). Falls back to the legacy settings blob if the
     * table is missing, so an install that has not run migrations yet still sells.
     *
     * @return array<int, array<string, mixed>>
     */
    function falcon_all_coupons(): array
    {
        try {
            if (Schema::hasTable('shop_coupons')) {
                return Coupon::where('is_active', true)
                    ->orderBy('code')
                    ->get()
                    ->map(static fn ($c) => $c->toCartArray())
                    ->all();
            }
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Coupon lookup failed: '.$e->getMessage());
        }

        $legacy = json_decode((string) get_cms_option('shop_coupons', '[]'), true);

        return is_array($legacy) ? $legacy : [];
    }
}

if (!function_exists('falcon_find_coupon')) {
    /**
     * One coupon by code, case-insensitively, in the cart's array shape. Null when unknown.
     */
    function falcon_find_coupon(?string $code): ?array
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        try {
            if (Schema::hasTable('shop_coupons')) {
                return Coupon::findByCode($code)?->toCartArray();
            }
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Coupon lookup failed: '.$e->getMessage());
        }

        foreach (falcon_all_coupons() as $coupon) {
            if (strtoupper((string) ($coupon['code'] ?? '')) === $code) {
                return $coupon;
            }
        }

        return null;
    }
}

if (!function_exists('falcon_default_customer_country')) {
    /**
     * The country to assume for a customer who has not given an address yet —
     * Shop → General → Default customer location.
     *
     *  'none'       assume nothing (shipping falls back to the flat rate)
     *  'base'       the shop's own country
     *  'geolocate'  the visitor's country, from their IP
     *
     * Returns a value from the shop's own country list (so it lines up with the checkout
     * dropdowns and with shipping zones), or null when there is nothing sensible to assume.
     */
    function falcon_default_customer_country(): ?string
    {
        $mode = (string) get_shop_option('shop_default_customer_location', 'none');

        if ($mode === 'base') {
            $base = (string) get_shop_option('shop_country_state', '');

            return $base !== '' ? $base : null;
        }

        if ($mode !== 'geolocate' || !function_exists('falcon_geoip')) {
            return null;
        }

        $iso2 = falcon_geoip(request()->ip())['country_code'] ?? null;
        if (!$iso2) {
            return null;
        }

        // Map the ISO code onto the shop's *sellable* country list. Matching through
        // countryToIso2() rather than by string keeps suffixed names like
        // "United States (US)" working, and a country the shop does not sell to
        // simply finds no match — better than pre-filling a checkout that would be rejected.
        foreach (EcommerceData::getCountriesWithStates(true) as $value => $label) {
            $candidate = is_string($value) ? $value : $label;
            if (EcommerceData::countryToIso2($candidate) === strtoupper($iso2)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('falcon_customer_shipping_country')) {
    /**
     * The country totals should be calculated against right now: whatever the customer has
     * chosen, otherwise the store's default-location assumption.
     *
     * The resolved default is cached in the session because 'geolocate' costs an outbound
     * lookup; an explicit choice always overwrites the same session key, so a customer's own
     * selection can never be undone by this.
     */
    function falcon_customer_shipping_country(): ?string
    {
        $chosen = session()->get('falcon_shipping_country');
        if (is_string($chosen) && $chosen !== '') {
            return $chosen;
        }

        if (session()->has('falcon_default_country_resolved')) {
            return session()->get('falcon_default_country_resolved') ?: null;
        }

        $default = falcon_default_customer_country();
        session()->put('falcon_default_country_resolved', $default ?? '');

        return $default;
    }
}

if (!function_exists('falcon_shipping_destination')) {
    /**
     * Which address an order is fulfilled to — Shop → Shipping → Default Address Type.
     *
     *  'shipping'      the separate shipping address is the target; its fields are shown up front
     *  'billing'       billing is the target, with a separate shipping address as an opt-in
     *  'force_billing' orders always ship to billing; shipping fields are not offered at all
     *
     * Anything unrecognised (an option edited by hand, say) falls back to 'shipping', which is
     * the most permissive and therefore never blocks a checkout.
     */
    function falcon_shipping_destination(): string
    {
        $value = (string) get_shop_option('shop_shipping_destination', 'shipping');

        return in_array($value, ['shipping', 'billing', 'force_billing'], true) ? $value : 'shipping';
    }
}

if (!function_exists('falcon_allows_separate_shipping_address')) {
    /**
     * May this store accept a shipping address different from billing?
     *
     * Checked on the server for every order, not just used to render the form: under
     * 'force_billing' the goods must go to the address the payment was authorised against,
     * so a hand-crafted POST carrying shipping_* fields has to be ignored rather than trusted.
     */
    function falcon_allows_separate_shipping_address(): bool
    {
        return falcon_shipping_destination() !== 'force_billing';
    }
}

if (!function_exists('falcon_cart_has_free_shipping_coupon')) {
    /**
     * Is a "Free Shipping" coupon currently applied?
     *
     * The Discount Type dropdown has always offered this option, but nothing acted on it —
     * such a coupon took no money off and left shipping fully charged. Shipping costs are
     * resolved server-side, so this is checked where the cost is calculated, not in the view.
     */
    function falcon_cart_has_free_shipping_coupon(): bool
    {
        foreach (session()->get('falcon_coupons', []) as $coupon) {
            if (($coupon['type'] ?? '') === 'free_shipping') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('falcon_shipping_methods')) {
    /**
     * The shipping methods a customer can actually pick for this cart and country.
     *
     * Always keyed by method id, always at least 'delivery'. 'pickup' only appears when Local
     * Pickup is switched on in Shop → Shipping, which is what makes that setting mean something.
     *
     * @return array<string, array{id: string, label: string, cost: float}>
     */
    function falcon_shipping_methods($country = null): array
    {
        $country = $country ?? falcon_customer_shipping_country();
        $delivery = falcon_delivery_shipping_details($country);

        $methods = [
            'delivery' => [
                'id' => 'delivery',
                'label' => $delivery['label'],
                'cost' => (float) $delivery['cost'],
            ],
        ];

        if (get_shop_option('shop_local_pickup_enable') === '1') {
            $methods['pickup'] = [
                'id' => 'pickup',
                'label' => 'Local pickup',
                'cost' => 0.0,
            ];
        }

        // A Free Shipping coupon zeroes every method rather than discounting the cart, so the
        // saving lands on the shipping line where the customer expects to see it.
        if (falcon_cart_has_free_shipping_coupon()) {
            foreach ($methods as $id => $method) {
                $methods[$id]['cost'] = 0.0;
            }
        }

        // "Auto-hide paid shipping options when free delivery is applicable" — once something
        // is free, charging for the alternative is just a way to lose the sale. Only ever
        // removes paid options, so the list can never end up empty.
        if (get_shop_option('shop_calc_hide_paid_when_free') === '1') {
            $free = array_filter($methods, static fn (array $m): bool => $m['cost'] <= 0);
            if (!empty($free)) {
                $methods = $free;
            }
        }

        return $methods;
    }
}

if (!function_exists('falcon_selected_shipping_method')) {
    /**
     * Resolve the customer's chosen shipping method against what is genuinely on offer.
     *
     * The session only ever holds a method *id*; the cost is recalculated here on every call.
     * That is deliberate — a stored or posted price could be tampered with, a re-derived one
     * cannot. An id that is no longer available (pickup switched off mid-session, say) falls
     * back to the first method rather than erroring.
     *
     * @return array{id: string, label: string, cost: float}
     */
    function falcon_selected_shipping_method($country = null): array
    {
        $country = $country ?? falcon_customer_shipping_country();
        $methods = falcon_shipping_methods($country);
        $selected = session()->get('falcon_shipping_method');

        if (is_string($selected) && isset($methods[$selected])) {
            return $methods[$selected];
        }

        return reset($methods);
    }
}

if (!function_exists('get_falcon_cart_shipping_details')) {
    /**
     * Cost + label for the shipping the customer will actually be charged.
     * Delegates to the selected method, so Local Pickup zeroes shipping everywhere at once —
     * cart totals, checkout totals, and the order row written at checkout.
     */
    function get_falcon_cart_shipping_details($country = null)
    {
        $country = $country ?? falcon_customer_shipping_country();

        // Nothing in the basket, nothing to deliver. Without this the flat rate is quoted
        // against an empty cart, so a mini-cart or a "you are ৳X from free delivery" banner
        // reads the shipping charge as the whole total. Checkout itself is guarded separately,
        // so this was never chargeable — just wrong on screen.
        if (empty(session()->get('falcon_cart', []))) {
            return [
                'cost' => 0.0,
                'label' => 'Calculated at checkout',
                'method' => 'delivery',
                'pending' => true,
            ];
        }

        // "Only display shipping fees after a valid address is provided" — with no destination
        // yet there is nothing honest to quote, so nothing is charged either and the cart total
        // matches what the customer is shown. Checkout always has a country (billing_country is
        // required), so a real order is never priced from this branch.
        if (!falcon_shipping_is_calculable($country)) {
            return [
                'cost' => 0.0,
                'label' => 'Calculated at checkout',
                'method' => 'delivery',
                'pending' => true,
            ];
        }

        $method = falcon_selected_shipping_method($country);

        return ['cost' => $method['cost'], 'label' => $method['label'], 'method' => $method['id'], 'pending' => false];
    }
}

if (!function_exists('falcon_shipping_is_calculable')) {
    /** False only while "hide fees until an address is provided" is on and no country is known. */
    function falcon_shipping_is_calculable($country = null): bool
    {
        if (get_shop_option('shop_calc_hide_until_address') !== '1') {
            return true;
        }

        $country = $country ?? falcon_customer_shipping_country();

        return is_string($country) && $country !== '';
    }
}

if (!function_exists('falcon_refresh_cart_prices')) {
    /**
     * Re-read every cart line's price from the catalogue.
     *
     * The cart stores the price that was current when the item went in, and nothing ever looked
     * at it again — so a sale that ended, or a price the shop owner corrected, never reached a
     * cart that already existed. Coupons were already re-checked on every cart load; prices are
     * now held to the same rule.
     *
     * Deliberately conservative in two places:
     *   - a product that has vanished from the catalogue is left untouched rather than silently
     *     repriced or removed, so a lookup failure can never zero out someone's basket;
     *   - a sale price of zero or less is stored as null, because the subtotal treats a present
     *     sale price as authoritative and a literal 0 would hand the item over for free.
     *
     * @return int how many lines actually changed
     */
    function falcon_refresh_cart_prices(): int
    {
        $cart = session()->get('falcon_cart', []);
        if (empty($cart) || !is_array($cart)) {
            return 0;
        }

        $productIds = [];
        $variationIds = [];
        foreach ($cart as $item) {
            if (!empty($item['id'])) {
                $productIds[] = (int) $item['id'];
            }
            if (!empty($item['variation_id'])) {
                $variationIds[] = (int) $item['variation_id'];
            }
        }

        try {
            $products = empty($productIds) ? collect() : DB::table('shop_products')
                ->whereIn('post_id', array_unique($productIds))
                ->get(['post_id', 'price', 'sale_price', 'sale_ends_at'])
                ->keyBy('post_id');

            $variations = empty($variationIds) ? collect() : DB::table('shop_product_variations')
                ->whereIn('id', array_unique($variationIds))
                ->get(['id', 'price', 'sale_price'])
                ->keyBy('id');
        } catch (Throwable $e) {
            // A pricing lookup that fails must not empty or corrupt the basket.
            Illuminate\Support\Facades\Log::error('Cart price refresh failed: '.$e->getMessage());

            return 0;
        }

        $changed = 0;
        foreach ($cart as $key => $item) {
            $source = null;
            if (!empty($item['variation_id'])) {
                $source = $variations[(int) $item['variation_id']] ?? null;
            }
            $parent = $products[(int) ($item['id'] ?? 0)] ?? null;
            $source = $source ?? $parent;

            if (!$source) {
                continue;   // no longer in the catalogue — leave the line exactly as it was
            }

            $price = round((float) $source->price, 2);
            $sale = $source->sale_price !== null ? round((float) $source->sale_price, 2) : null;

            // The scheduled falcon:expire-sale-prices command clears these, but it may not have
            // run yet — an expired sale must not survive in a cart either way.
            $endsAt = $parent->sale_ends_at ?? null;
            if ($sale !== null && $endsAt && strtotime((string) $endsAt) < time()) {
                $sale = null;
            }
            if ($sale !== null && $sale <= 0) {
                $sale = null;
            }

            $oldPrice = round((float) ($item['price'] ?? 0), 2);
            $oldSale = isset($item['sale_price']) && $item['sale_price'] !== null && $item['sale_price'] !== ''
                ? round((float) $item['sale_price'], 2)
                : null;

            if ($oldPrice !== $price || $oldSale !== $sale) {
                $cart[$key]['price'] = $price;
                $cart[$key]['sale_price'] = $sale;
                $changed++;
            }
        }

        if ($changed > 0) {
            session()->put('falcon_cart', $cart);
        }

        return $changed;
    }
}

if (!function_exists('falcon_cart_weight')) {
    /**
     * Total shipping weight of the cart, in the shop's configured weight unit.
     *
     * A variation uses its own weight when one was entered and otherwise inherits the parent
     * product's. Items with no weight at all count as zero rather than blocking the order — a
     * shop that has not filled its weights in yet must still be able to sell.
     *
     * Both lookups are single queries, so the cost does not grow with the size of the cart.
     */
    function falcon_cart_weight(?array $cart = null): float
    {
        $cart = $cart ?? session()->get('falcon_cart', []);
        if (empty($cart)) {
            return 0.0;
        }

        $productIds = [];
        $variationIds = [];
        foreach ($cart as $item) {
            if (!empty($item['id'])) {
                $productIds[] = (int) $item['id'];
            }
            if (!empty($item['variation_id'])) {
                $variationIds[] = (int) $item['variation_id'];
            }
        }

        try {
            $productWeights = empty($productIds) ? collect() : DB::table('shop_products')
                ->whereIn('post_id', array_unique($productIds))
                ->pluck('weight', 'post_id');

            $variationWeights = empty($variationIds) ? collect() : DB::table('shop_product_variations')
                ->whereIn('id', array_unique($variationIds))
                ->pluck('weight', 'id');
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Cart weight lookup failed: '.$e->getMessage());

            return 0.0;
        }

        $total = 0.0;
        foreach ($cart as $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($quantity === 0) {
                continue;
            }

            $weight = null;
            if (!empty($item['variation_id'])) {
                $weight = $variationWeights[(int) $item['variation_id']] ?? null;
            }
            if ($weight === null || $weight === '' || (float) $weight <= 0) {
                $weight = $productWeights[(int) ($item['id'] ?? 0)] ?? null;
            }

            $total += max(0.0, (float) $weight) * $quantity;
        }

        return round($total, 4);
    }
}

if (!function_exists('falcon_delivery_shipping_details')) {
    /** Zone / flat-rate delivery cost — the calculation that existed before pickup was a choice. */
    function falcon_delivery_shipping_details($country = null)
    {
        $subtotal = get_falcon_cart_subtotal();
        $cart = session()->get('falcon_cart', []);
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
                $zoneCountries = (array) ($zone['countries'] ?? []);
                $normalizedZoneCountries = array_map(fn ($c) => str_replace('—', '-', $c), $zoneCountries);

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
                return ['cost' => 0, 'label' => 'Free shipping ('.$zoneName.')'];
            }

            $baseCost = (float) ($matchedZone['cost'] ?? 0);
            $type = $matchedZone['type'] ?? 'order';

            // Banded rates. 'item' bands on how many things are in the cart, 'weight' on how
            // heavy they are — the same rule rows, just measured differently.
            if (in_array($type, ['item', 'weight'], true) && !empty($matchedZone['rules'])) {
                $measure = $type === 'weight' ? falcon_cart_weight() : $itemCount;

                $ruleCost = 0;
                $matchedRule = false;
                foreach ($matchedZone['rules'] as $rule) {
                    // An incomplete or mistyped row must not become free shipping: skip it and
                    // let the zone's base cost apply. A deliberate 0 is numeric, so it survives.
                    if (!is_array($rule)
                        || !isset($rule['cost']) || !is_numeric($rule['cost'])
                        || (isset($rule['min']) && $rule['min'] !== '' && !is_numeric($rule['min']))
                        || (isset($rule['max']) && $rule['max'] !== '' && $rule['max'] !== null && !is_numeric($rule['max']))) {
                        continue;
                    }
                    // Weights are fractional (0.5 kg), item counts are not. Casting a weight
                    // band to int would quietly turn "up to 0.5" into "up to 0".
                    $min = (float) ($rule['min'] ?? 0);
                    $max = (($rule['max'] ?? '') === '' || ($rule['max'] ?? null) === null)
                        ? INF
                        : (float) $rule['max'];

                    if ($measure >= $min && $measure <= $max) {
                        $ruleCost = (float) ($rule['cost'] ?? 0);
                        $matchedRule = true;
                        break;
                    }
                }

                return [
                    'cost' => $matchedRule ? $ruleCost : $baseCost,
                    'label' => $zoneName,
                ];
            }

            return ['cost' => $baseCost, 'label' => $zoneName];
        }

        // 3. Fallback to Global Flat Rate
        return [
            'cost' => (float) get_shop_option('shop_flat_rate_cost', 0),
            'label' => 'Flat rate',
        ];
    }
}

if (!function_exists('falcon_tax_enabled')) {
    /** Shop → Tax → Enable Tax. Everything else in the tax engine is a no-op while this is off. */
    function falcon_tax_enabled(): bool
    {
        return get_shop_option('shop_calc_taxes') === '1';
    }
}

if (!function_exists('falcon_prices_include_tax')) {
    /** True when catalogue prices already contain tax, so tax is extracted rather than added. */
    function falcon_prices_include_tax(): bool
    {
        return get_shop_option('shop_tax_price_entry') === 'inclusive';
    }
}

if (!function_exists('falcon_display_prices_including_tax')) {
    /** Shop → Tax → Display prices in shop. Presentation only; never changes what is charged. */
    function falcon_display_prices_including_tax(): bool
    {
        return falcon_tax_enabled() && get_shop_option('shop_tax_display_shop', 'exclusive') === 'inclusive';
    }
}

if (!function_exists('falcon_tax_country')) {
    /**
     * The address tax is worked out against — Shop → Tax → Calculate Tax Based On.
     *
     * 'billing' falls back to the shipping country before checkout, because the billing address
     * simply isn't known while the customer is still on the cart page.
     */
    function falcon_tax_country(): ?string
    {
        switch ((string) get_shop_option('shop_tax_calculation_basis', 'shipping')) {
            case 'base':
                $base = (string) get_shop_option('shop_country_state', '');

                return $base !== '' ? $base : null;

            case 'billing':
                $billing = session()->get('falcon_billing_country');
                if (is_string($billing) && $billing !== '') {
                    return $billing;
                }
                // fall through
            default:
                return falcon_customer_shipping_country();
        }
    }
}

if (!function_exists('falcon_tax_rate_for')) {
    /**
     * The tax rate that applies to a country, or null if none does.
     *
     * Matching runs most-specific first: an exact row ("Bangladesh - Dhaka"), then the country
     * without its region suffix, then the "*" catch-all. That way a store can set one national
     * rate and override single regions without listing every region.
     *
     * @return array{rate: float, name: string, shipping: bool}|null
     */
    function falcon_tax_rate_for(?string $country): ?array
    {
        if (!falcon_tax_enabled()) {
            return null;
        }

        $rates = get_shop_option('shop_tax_rates', []);
        if (!is_array($rates) || empty($rates)) {
            return null;
        }

        $normalise = static fn (string $v): string => strtolower(trim(str_replace(['—', '–'], '-', $v)));

        $country = $country !== null ? $normalise($country) : '';
        // "Bangladesh - Dhaka" → also try plain "Bangladesh".
        $countryOnly = $country !== '' ? trim(explode(' - ', $country)[0]) : '';

        $exact = $parent = $wildcard = null;

        foreach ($rates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowCountry = $normalise((string) ($row['country'] ?? ''));

            if ($rowCountry === '*') {
                $wildcard = $wildcard ?? $row;
            } elseif ($country !== '' && $rowCountry === $country) {
                $exact = $exact ?? $row;
            } elseif ($countryOnly !== '' && $rowCountry === $countryOnly) {
                $parent = $parent ?? $row;
            }
        }

        $match = $exact ?? $parent ?? $wildcard;
        if (!$match) {
            return null;
        }

        return [
            'rate' => (float) ($match['rate'] ?? 0),
            'name' => trim((string) ($match['name'] ?? '')) ?: 'Tax',
            'shipping' => (string) ($match['shipping'] ?? '0') === '1',
        ];
    }
}

if (!function_exists('falcon_blocked_upload_extensions')) {
    /**
     * File extensions this CMS will never keep, whichever door they arrive through.
     *
     * Two kinds of thing are on the list. Most are executable or server-side scripts: a
     * file the web server would run rather than serve. The rest — svgz, xml, xsl, xslt —
     * are documents that can carry script, which matters because the media library is
     * shared and its files are embedded in pages every visitor loads, from the site's own
     * origin.
     *
     * SVG used to be on this list. It is now handled by falcon_sanitized_upload_extensions()
     * instead: a site decides whether to accept it under Customizer → Performance → Allowed
     * Upload Formats (it is off by default), and what is written to disk is the sanitised
     * markup rather than the file as uploaded. svgz stays here — it is gzipped, so the
     * sanitiser cannot read it.
     *
     * It lives here, once, because there is more than one way into the library: the upload
     * screen and the WordPress media importer both write to it. The importer used to keep
     * its own list, which had drifted to allow SVG.
     *
     * @return array<int, string>
     */
    function falcon_blocked_upload_extensions(): array
    {
        return apply_falcon_filters('falcon_blocked_upload_extensions', [
            // Executed by the server rather than served to the browser.
            'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
            'asp', 'aspx', 'jsp', 'js', 'cgi', 'pl', 'py', 'rb',
            'sh', 'bash', 'exe', 'bat', 'cmd', 'htaccess', 'htpasswd',
            // Documents that can carry script.
            'svgz', 'xml', 'xsl', 'xslt',
        ]);
    }
}

if (!function_exists('falcon_sanitized_upload_extensions')) {
    /**
     * Extensions that may be kept, but only after their contents have been rewritten.
     *
     * These are not blocked and not simply trusted either. A file with one of these
     * extensions is passed through FalconCms\Core\Support\SvgSanitizer before anything
     * reaches disk, and is refused outright if nothing usable survives — so an SVG in the
     * library cannot carry <script>, an event handler or a javascript: link, however it
     * arrived. Turning the format on at all is still a site decision, made under
     * Customizer → Performance → Allowed Upload Formats.
     *
     * @return array<int, string>
     */
    function falcon_sanitized_upload_extensions(): array
    {
        return apply_falcon_filters('falcon_sanitized_upload_extensions', ['svg']);
    }
}

if (!function_exists('falcon_request_memo')) {
    /**
     * A scratchpad that lives exactly as long as the application instance does.
     *
     * Several helpers are asked the same question many times while rendering one page, and
     * memoising the answer is worth it. Doing that in a `static` is what you reach for first
     * and is wrong twice over: under Octane or inside a queue worker the static survives into
     * the next request — serving stale data, and in the case of anything user-scoped, serving
     * one visitor's data to the next — and in tests it carries fixtures across cases.
     *
     * Keyed per caller so two helpers never collide.
     */
    function falcon_request_memo(string $key): ArrayObject
    {
        $binding = 'falcon.memo.'.$key;

        if (!app()->bound($binding)) {
            app()->instance($binding, new ArrayObject);
        }

        return app($binding);
    }
}

if (!function_exists('falcon_product_tax_status')) {
    /**
     * A product's tax status ('taxable' | 'shipping' | 'none'), defaulting to taxable.
     * Results are memoised per request — the cart asks for the same handful of ids repeatedly.
     *
     * The memo lives on the container rather than in a `static`. A static outlives the
     * request in any long-running worker (Octane, a queue process), so it would keep
     * serving a tax status the shop owner has since changed, and would carry one test's
     * fixtures into the next. Bound to the application instance, it dies with it.
     */
    function falcon_product_tax_status($postId): string
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return 'taxable';
        }

        $cache = falcon_request_memo('product_tax_statuses');

        if ($cache->offsetExists($postId)) {
            return $cache[$postId];
        }

        $status = 'taxable';
        try {
            if (Schema::hasColumn('shop_products', 'tax_status')) {
                $found = DB::table('shop_products')
                    ->where('post_id', $postId)
                    ->value('tax_status');
                if (in_array($found, ProductData::TAX_STATUSES, true)) {
                    $status = $found;
                }
            }
        } catch (Throwable $e) {
            // Leave the default; a lookup failure must never block a checkout.
        }

        $cache[$postId] = $status;

        return $status;
    }
}

if (!function_exists('falcon_cart_taxable_subtotal')) {
    /**
     * The part of the cart subtotal that is actually subject to tax.
     * Only 'taxable' products count — 'shipping' items are taxed via the shipping line
     * (if the rate covers shipping) and 'none' items are exempt outright.
     */
    function falcon_cart_taxable_subtotal(): float
    {
        $total = 0.0;

        foreach (session()->get('falcon_cart', []) as $item) {
            if (falcon_product_tax_status($item['id'] ?? 0) !== 'taxable') {
                continue;
            }
            $price = $item['sale_price'] ?? $item['price'];
            $total += (float) $price * (int) ($item['quantity'] ?? 0);
        }

        return $total;
    }
}

if (!function_exists('falcon_cart_discount_total')) {
    /**
     * Total coupon discount for the cart. Shared by the total and the tax base so a discount
     * can never be counted differently in the two places.
     */
    function falcon_cart_discount_total(): float
    {
        $coupons = session()->get('falcon_coupons', []);
        if (empty($coupons)) {
            return 0.0;
        }

        $cart = session()->get('falcon_cart', []);
        $subtotal = get_falcon_cart_subtotal();
        $currentSubtotal = $subtotal;
        $isSequential = (int) get_shop_option('shop_coupon_stacking_policy', '1') === 1;
        $discountTotal = 0.0;

        foreach ($coupons as $coupon) {
            $discount = get_falcon_coupon_discount_amount($coupon, $cart, $isSequential ? $currentSubtotal : $subtotal);
            $discountTotal += $discount;
            $currentSubtotal -= $discount;
        }

        return $discountTotal;
    }
}

if (!function_exists('get_falcon_cart_tax')) {
    /**
     * Tax due on the current cart.
     *
     * Exclusive pricing: tax sits on top of the taxable base.
     * Inclusive pricing: the base already contains it, so the tax is the portion extracted out
     * of that figure — adding it again would charge the customer twice.
     *
     * Coupons shrink the taxable base in proportion to how much of the cart is taxable, so a
     * discount never removes more (or less) tax than the goods it actually applies to.
     */
    function get_falcon_cart_tax()
    {
        if (!falcon_tax_enabled()) {
            return 0.0;
        }

        $rate = falcon_tax_rate_for(falcon_tax_country());
        if (!$rate || $rate['rate'] <= 0) {
            return 0.0;
        }

        $taxableBase = falcon_cart_taxable_subtotal();
        $subtotal = get_falcon_cart_subtotal();
        // Promotions reduce what is actually paid, so they reduce the taxable base alongside coupons.
        $discount = falcon_cart_discount_total() + falcon_cart_promotion_total();

        if ($subtotal > 0 && $discount > 0 && $taxableBase > 0) {
            $taxableBase = max(0.0, $taxableBase - ($discount * ($taxableBase / $subtotal)));
        }

        if ($rate['shipping']) {
            $taxableBase += (float) get_falcon_cart_shipping(falcon_customer_shipping_country());
        }

        if ($taxableBase <= 0) {
            return 0.0;
        }

        $fraction = $rate['rate'] / 100;

        return falcon_prices_include_tax()
            ? $taxableBase - ($taxableBase / (1 + $fraction))
            : $taxableBase * $fraction;
    }
}

if (!function_exists('falcon_cart_tax_label')) {
    /** The name to show next to the tax line ("VAT", "GST", …). */
    function falcon_cart_tax_label(): string
    {
        return falcon_tax_rate_for(falcon_tax_country())['name'] ?? 'Tax';
    }
}

if (!function_exists('falcon_display_price')) {
    /**
     * Adjust a catalogue price for how the shop displays tax.
     *
     * Only ever converts between the two presentations of the same price; the amount actually
     * charged is settled by get_falcon_cart_tax() at checkout, never here.
     */
    function falcon_display_price($price, $postId = null): float
    {
        $price = (float) $price;

        if (!falcon_tax_enabled() || $price <= 0) {
            return $price;
        }

        if ($postId !== null && falcon_product_tax_status($postId) !== 'taxable') {
            return $price;
        }

        // Deliberately the shop's own country, not the visitor's.
        //
        // Catalogue pages are shared and cacheable — PageCacheMiddleware keys purely on the URL —
        // so a rate taken from one visitor's session would be baked into the page everyone else
        // is then served. A fixed base rate keeps every shopper looking at the same figure. What
        // is actually charged is still worked out from the customer's real address at checkout,
        // where the tax line spells the difference out.
        $baseCountry = (string) get_shop_option('shop_country_state', '');
        $rate = falcon_tax_rate_for($baseCountry !== '' ? $baseCountry : null);
        if (!$rate || $rate['rate'] <= 0) {
            return $price;
        }

        $fraction = $rate['rate'] / 100;
        $entryIncl = falcon_prices_include_tax();
        $showIncl = falcon_display_prices_including_tax();

        if ($entryIncl === $showIncl) {
            return $price; // Stored and displayed the same way — nothing to convert.
        }

        return $showIncl ? $price * (1 + $fraction) : $price / (1 + $fraction);
    }
}

if (!function_exists('get_falcon_cart_total')) {
    function get_falcon_cart_total()
    {
        $cart = session()->get('falcon_cart', []);
        $subtotal = get_falcon_cart_subtotal();
        // Resolver rather than the raw session key, so the store's default-location setting
        // feeds the total the same way it feeds the line the customer is shown.
        $shipping = get_falcon_cart_shipping(falcon_customer_shipping_country());
        $tax = get_falcon_cart_tax();

        // Coupons the customer typed in, plus whatever the automatic promotions earned them.
        $totalDiscount = falcon_cart_discount_total() + falcon_cart_promotion_total($cart);

        // Inclusive pricing means the tax is already inside the item prices — adding $tax here
        // would charge it a second time. It is still reported separately for the tax line.
        $total = $subtotal + $shipping - $totalDiscount;
        if (!falcon_prices_include_tax()) {
            $total += $tax;
        }

        return max(0, $total);
    }
}

if (!function_exists('falcon_product_schema')) {
    /**
     * schema.org Product markup for a single product page.
     *
     * This is what puts the price, the availability and the star rating into a Google result
     * instead of a bare blue link. Everything comes from the same helpers the page itself uses,
     * so the structured data cannot advertise a price the shop will not honour — Google treats
     * that as a violation, not a rounding error.
     *
     * @return array<string, mixed>|null null when the post is not a sellable product
     */
    function falcon_product_schema($post): ?array
    {
        $shopData = $post->shopData ?? null;
        if (!$shopData || ($post->type ?? null) !== 'product') {
            return null;
        }

        $currency = get_shop_option('shop_currency', 'USD');
        $url = get_falcon_permalink($post);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $post->title,
            'url' => $url,
        ];

        $description = trim(strip_tags((string) ($shopData->short_description ?: $post->excerpt ?: $post->content)));
        if ($description !== '') {
            $schema['description'] = Str::limit($description, 300, '');
        }

        if (!empty($post->featured_image)) {
            $schema['image'] = str_starts_with($post->featured_image, 'http')
                ? $post->featured_image
                : asset('storage/'.$post->featured_image);
        }

        if (!empty($shopData->sku)) {
            $schema['sku'] = (string) $shopData->sku;
        }

        // A "Brand" attribute is the only place a brand is recorded, so it is the only honest source.
        foreach (falcon_product_attribute_definitions($shopData) as $attribute) {
            if (strcasecmp($attribute['name'], 'brand') === 0 && !empty($attribute['values'])) {
                $schema['brand'] = ['@type' => 'Brand', 'name' => (string) $attribute['values'][0]];
                break;
            }
        }

        $availability = $post->is_in_stock
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        if ($shopData->isVariable()) {
            // One offer per price point would be noise; an aggregate is what Google expects for
            // a product sold in several variants.
            $prices = [];
            foreach ($shopData->variations as $variation) {
                $sale = $variation->sale_price !== null ? (float) $variation->sale_price : 0.0;
                $effective = $sale > 0 ? $sale : (float) $variation->price;
                if ($effective > 0) {
                    $prices[] = round((float) falcon_display_price($effective, $post->id), 2);
                }
            }

            if (!empty($prices)) {
                $schema['offers'] = [
                    '@type' => 'AggregateOffer',
                    'priceCurrency' => $currency,
                    'lowPrice' => number_format(min($prices), 2, '.', ''),
                    'highPrice' => number_format(max($prices), 2, '.', ''),
                    'offerCount' => count($prices),
                    'availability' => $availability,
                    'url' => $url,
                ];
            }
        } else {
            $sale = $shopData->active_sale_price;
            $price = round((float) falcon_display_price($sale !== null ? $sale : (float) $shopData->price, $post->id), 2);

            if ($price > 0) {
                $offer = [
                    '@type' => 'Offer',
                    'priceCurrency' => $currency,
                    'price' => number_format($price, 2, '.', ''),
                    'availability' => $availability,
                    'url' => $url,
                ];

                // Only meaningful while a sale is actually running.
                if ($sale !== null && !empty($shopData->sale_ends_at)) {
                    $offer['priceValidUntil'] = $shopData->sale_ends_at->format('Y-m-d');
                }

                $schema['offers'] = $offer;
            }
        }

        try {
            $reviewCount = $post->reviews()->count();
            if ($reviewCount > 0) {
                $average = (float) $post->reviews()->avg('rating');
                if ($average > 0) {
                    $schema['aggregateRating'] = [
                        '@type' => 'AggregateRating',
                        'ratingValue' => round($average, 1),
                        'reviewCount' => $reviewCount,
                    ];
                }
            }
        } catch (Throwable $e) {
            // Ratings are a bonus; never let them cost the page its markup.
        }

        return apply_falcon_filters('falcon_product_schema', $schema, $post);
    }
}

if (!function_exists('falcon_linked_products')) {
    /**
     * Products the shop owner hand-picked for this one.
     *
     * @param  string  $kind  'upsell' (shown on the product page) or 'cross_sell' (shown in the cart)
     * @return Collection<int, Post>
     */
    function falcon_linked_products($product, string $kind = 'upsell', int $limit = 4)
    {
        $shopData = is_object($product) ? ($product->shopData ?? null) : null;
        if (!$shopData) {
            return collect();
        }

        $ids = $kind === 'cross_sell' ? $shopData->cross_sell_ids : $shopData->upsell_ids;
        $ids = array_values(array_filter(array_map('intval', (array) $ids), fn ($id) => $id > 0));

        if (empty($ids)) {
            return collect();
        }

        try {
            $found = Post::whereIn('posts.id', array_slice($ids, 0, max(1, $limit) * 3))
                ->where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->with(['shopData.variations', 'productCategories'])
                ->get();

            // Keep the order the shop owner chose rather than whatever the database returns.
            return $found->sortBy(fn ($p) => array_search($p->id, $ids, true))->take($limit)->values();
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Linked product lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_related_products')) {
    /**
     * Products related to this one: same category first, topped up with recent products.
     *
     * The templates used to run their own query for this and simply took the four newest products
     * in the shop — so "Related products" under a phone could be a pair of socks. Sharing one
     * helper also means the two single-product templates cannot drift apart again.
     *
     * @return Collection<int, Post>
     */
    function falcon_related_products($product, int $limit = 4)
    {
        $limit = max(1, $limit);

        try {
            $categoryIds = $product->productCategories?->pluck('id')->all() ?? [];

            $base = fn () => Post::where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->where('posts.id', '!=', $product->id)
                ->with(['shopData.variations', 'productCategories']);

            $related = collect();
            if (!empty($categoryIds)) {
                $related = $base()
                    ->whereHas('productCategories', fn ($q) => $q->whereIn('product_categories.id', $categoryIds))
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
            }

            // A product that is alone in its category would otherwise show an empty row.
            if ($related->count() < $limit) {
                $filler = $base()
                    ->whereNotIn('posts.id', $related->pluck('id')->all())
                    ->latest('posts.id')
                    ->limit($limit - $related->count())
                    ->get();

                $related = $related->concat($filler);
            }

            return $related->take($limit)->values();
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Related product lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_cart_cross_sells')) {
    /**
     * Cross-sells for everything currently in the cart, minus what is already in it.
     *
     * @return Collection<int, Post>
     */
    function falcon_cart_cross_sells(int $limit = 4)
    {
        $cart = session()->get('falcon_cart', []);
        if (empty($cart) || !is_array($cart)) {
            return collect();
        }

        $inCart = [];
        $ids = [];
        foreach ($cart as $item) {
            $productId = (int) ($item['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $inCart[] = $productId;

            $shopData = ProductData::where('post_id', $productId)->first(['cross_sell_ids']);
            foreach ((array) ($shopData->cross_sell_ids ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0 && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        // Suggesting something the shopper has already added is noise.
        $ids = array_values(array_diff($ids, $inCart));
        if (empty($ids)) {
            return collect();
        }

        try {
            return Post::whereIn('posts.id', $ids)
                ->where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->with(['shopData.variations', 'productCategories'])
                ->limit($limit)
                ->get();
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Cross-sell lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_is_variable_product')) {
    /**
     * Whether a product is a variable product. ProductData::isVariable() holds the rule —
     * the table has two columns for this and only one of them is written by the admin.
     */
    function falcon_is_variable_product($product): bool
    {
        $sd = $product->shopData ?? null;

        return $sd ? $sd->isVariable() : false;
    }
}

if (!function_exists('falcon_shipping_carriers')) {
    /**
     * Shipping carriers for order tracking, grouped (Local / International).
     * Each value is a tracking-URL template with a {tracking} placeholder ('' = use universal fallback).
     */
    function lazy_shipping_carriers(): array
    {
        return [
            'Local (Bangladesh)' => [
                'Pathao' => 'https://merchant.pathao.com/tracking?consignment_id={tracking}',
                'Steadfast' => 'https://steadfast.com.bd/track/{tracking}',
                'RedX' => 'https://redx.com.bd/track-parcel/?trackingId={tracking}',
                'Paperfly' => '',
                'eCourier' => 'https://ecourier.com.bd/track?tracking_id={tracking}',
                'Sundarban Courier' => '',
                'SA Paribahan' => '',
                'Pickaboo' => '',
                'Delivery Tiger' => '',
            ],
            'International' => [
                'DHL' => 'https://www.dhl.com/track?tracking-id={tracking}',
                'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
                'UPS' => 'https://www.ups.com/track?tracknum={tracking}',
                'USPS' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking}',
                'Aramex' => 'https://www.aramex.com/track/results?ShipmentNumber={tracking}',
                'DPD' => 'https://www.dpd.com/tracking/{tracking}',
                'TNT' => 'https://www.tnt.com/express/en_us/site/tracking.html?searchType=con&cons={tracking}',
                'China Post' => 'https://www.17track.net/en/track?nums={tracking}',
                'India Post' => 'https://www.17track.net/en/track?nums={tracking}',
                'Other' => 'https://www.17track.net/en/track?nums={tracking}',
            ],
        ];
    }
}

if (!function_exists('falcon_wishlist_product_ids')) {
    /**
     * Product IDs in the current user's wishlist (cached per request). Empty for guests.
     */
    function falcon_wishlist_product_ids(): array
    {
        // Container-scoped, never static: this list belongs to one signed-in visitor, and a
        // static would hand it to whoever the worker serves next. See falcon_request_memo().
        $memo = falcon_request_memo('wishlist_product_ids');

        if ($memo->offsetExists('ids')) {
            return $memo['ids'];
        }

        if (!auth()->check()) {
            return $memo['ids'] = [];
        }

        try {
            $ids = Wishlist::where('user_id', auth()->id())->pluck('product_id')->map(fn ($v) => (int) $v)->all();
        } catch (Throwable $e) {
            $ids = [];
        }

        return $memo['ids'] = $ids;
    }
}

if (!function_exists('lazy_in_wishlist')) {
    function lazy_in_wishlist($productId): bool
    {
        return in_array((int) $productId, falcon_wishlist_product_ids(), true);
    }
}

// ── Checkout Field System ──────────────────────────────────────────────────────

if (!function_exists('falcon_standard_checkout_field_names')) {
    /**
     * Returns the field names that map to dedicated order columns.
     * Any field NOT in this list is treated as a custom field and saved to order meta.
     */
    function falcon_standard_checkout_field_names(): array
    {
        return [
            'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
            'billing_address_1',  'billing_address_2',  'billing_city', 'billing_state',
            'billing_postcode',   'billing_country',
            'shipping_first_name', 'shipping_last_name',
            'shipping_address_1',  'shipping_address_2',  'shipping_city', 'shipping_state',
            'shipping_postcode',   'shipping_country',
        ];
    }
}

if (!function_exists('falcon_customer_addresses')) {
    /**
     * The signed-in customer's saved addresses, newest default first.
     *
     * Returns an empty collection for guests and whenever the table is not there yet, so callers
     * never have to guard for either.
     */
    function falcon_customer_addresses()
    {
        if (!auth()->check() || !Schema::hasTable('shop_customer_addresses')) {
            return collect();
        }

        try {
            return CustomerAddress::where('user_id', auth()->id())
                ->orderByDesc('is_default_billing')
                ->orderByDesc('is_default_shipping')
                ->orderBy('id')
                ->get();
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error('Customer address lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_default_customer_address')) {
    /**
     * The address to pre-fill a checkout section with: the one flagged default for that section,
     * otherwise the first saved one, otherwise null.
     */
    function falcon_default_customer_address(string $section = 'billing')
    {
        $addresses = falcon_customer_addresses();
        if ($addresses->isEmpty()) {
            return null;
        }

        $column = $section === 'shipping' ? 'is_default_shipping' : 'is_default_billing';

        return $addresses->firstWhere($column, true) ?: $addresses->first();
    }
}

if (!function_exists('falcon_get_checkout_fields')) {
    /**
     * Returns the sorted field array for 'billing' or 'shipping'.
     * Applies the falcon_billing_fields / lazy_shipping_fields filter so developers
     * can add, remove, or reorder fields from functions.php.
     *
     * Field keys:
     *   name        string   Input name attribute (required)
     *   type        string   text|email|tel|number|password|date|select|country|textarea|checkbox|hidden
     *   label       string|null  Label text; null = no label rendered
     *   required    bool     Adds required rule in placeOrder validation
     *   width       string   'half' = one column, 'full' = spans both columns (default full)
     *   priority    int      Sort order (lower = earlier, default 10)
     *   default     mixed    Default value if old() is empty
     *   placeholder string   Input placeholder
     *   options     array    key=>label pairs for select type
     *   rows        int      Rows for textarea type
     *   rules       string   Custom Laravel validation rule (overrides 'required' default)
     *   class       string   Extra CSS classes on the field wrapper div
     */
    function falcon_get_checkout_fields(string $section): array
    {
        static $defaults = null;

        if ($defaults === null) {
            $user = auth()->user();
            $defaults = [
                'billing' => [
                    ['name' => 'billing_first_name', 'type' => 'text',    'label' => 'First name',       'required' => true,  'width' => 'half', 'priority' => 10,  'default' => $user->first_name ?? ''],
                    ['name' => 'billing_last_name',  'type' => 'text',    'label' => 'Last name',         'required' => true,  'width' => 'half', 'priority' => 20,  'default' => $user->last_name ?? ''],
                    ['name' => 'billing_country',    'type' => 'country', 'label' => 'Country / Region',  'required' => true,  'width' => 'full', 'priority' => 30,  'default' => falcon_customer_shipping_country() ?? ''],
                    ['name' => 'billing_address_1',  'type' => 'text',    'label' => 'Street address',    'required' => true,  'width' => 'full', 'priority' => 40,  'placeholder' => 'House number and street name'],
                    ['name' => 'billing_address_2',  'type' => 'text',    'label' => null,                'required' => false, 'width' => 'full', 'priority' => 50,  'placeholder' => 'Apartment, suite, unit, etc. (optional)'],
                    ['name' => 'billing_city',       'type' => 'text',    'label' => 'Town / City',       'required' => true,  'width' => 'half', 'priority' => 60],
                    ['name' => 'billing_state',      'type' => 'text',    'label' => 'State / Province',  'required' => true,  'width' => 'half', 'priority' => 70],
                    ['name' => 'billing_postcode',   'type' => 'text',    'label' => 'ZIP Code',          'required' => true,  'width' => 'half', 'priority' => 80],
                    ['name' => 'billing_phone',      'type' => 'tel',     'label' => 'Phone',             'required' => true,  'width' => 'half', 'priority' => 90],
                    ['name' => 'billing_email',      'type' => 'email',   'label' => 'Email address',     'required' => true,  'width' => 'full', 'priority' => 100, 'default' => $user->email ?? ''],
                ],
                'shipping' => [
                    ['name' => 'shipping_first_name', 'type' => 'text',    'label' => 'First name',       'required' => true,  'width' => 'half', 'priority' => 10],
                    ['name' => 'shipping_last_name',  'type' => 'text',    'label' => 'Last name',         'required' => true,  'width' => 'half', 'priority' => 20],
                    ['name' => 'shipping_country',    'type' => 'country', 'label' => 'Country / Region',  'required' => true,  'width' => 'full', 'priority' => 30,  'default' => falcon_customer_shipping_country() ?? ''],
                    ['name' => 'shipping_address_1',  'type' => 'text',    'label' => 'Street address',    'required' => true,  'width' => 'full', 'priority' => 40,  'placeholder' => 'House number and street name'],
                    ['name' => 'shipping_address_2',  'type' => 'text',    'label' => null,                'required' => false, 'width' => 'full', 'priority' => 50,  'placeholder' => 'Apartment, suite, unit, etc. (optional)'],
                    ['name' => 'shipping_city',       'type' => 'text',    'label' => 'Town / City',       'required' => true,  'width' => 'half', 'priority' => 60],
                    ['name' => 'shipping_state',      'type' => 'text',    'label' => 'State / Province',  'required' => true,  'width' => 'half', 'priority' => 70],
                    ['name' => 'shipping_postcode',   'type' => 'text',    'label' => 'ZIP Code',          'required' => true,  'width' => 'half', 'priority' => 80],
                ],
            ];
        }

        $fields = $defaults[$section] ?? [];

        // Fill in from the customer's saved address before the theme filter runs, so a site that
        // adds its own fields still sees the values. Done here rather than in the template so it
        // works with JavaScript switched off, and so `old()` input still wins on a failed submit.
        $saved = falcon_default_customer_address($section);
        if ($saved) {
            foreach ($fields as $i => $field) {
                $key = $field['name'] ?? '';
                if (!str_starts_with($key, $section.'_')) {
                    continue;
                }
                $column = substr($key, strlen($section) + 1);
                $value = in_array($column, CustomerAddress::FIELDS, true)
                    ? trim((string) ($saved->{$column} ?? ''))
                    : '';

                if ($value !== '') {
                    $fields[$i]['default'] = $value;
                }
            }
        }

        $fields = apply_falcon_filters("lazy_{$section}_fields", $fields);
        usort($fields, fn ($a, $b) => ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10));

        return $fields;
    }
}

if (!function_exists('falcon_render_checkout_field')) {
    /**
     * Renders a single checkout field inside the 2-column grid.
     * Full-width fields receive md:col-span-2; half-width get one column.
     */
    function falcon_render_checkout_field(array $field): void
    {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? null;
        $req = !empty($field['required']);
        $width = $field['width'] ?? 'full';
        $default = $field['default'] ?? '';
        $ph = $field['placeholder'] ?? '';
        $opts = $field['options'] ?? [];
        $class = $field['class'] ?? '';

        $value = old($name, $default);
        $span = $width === 'half' ? '' : 'md:col-span-2';
        $inp = 'w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none';

        if ($type === 'hidden') {
            echo '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';

            return;
        }

        echo '<div class="space-y-1.5 '.$span.($class ? ' '.e($class) : '').'">';

        if ($label !== null && $label !== '') {
            echo '<label class="text-[14px] font-bold text-heading">'
               .e($label)
               .($req ? ' <span class="text-red-600">*</span>' : '')
               .'</label>';
        }

        if ($type === 'country') {
            $countries = EcommerceData::getCountriesWithStates();
            echo '<select name="'.e($name).'" class="'.$inp.' bg-white cursor-pointer">';
            foreach ($countries as $code => $cname) {
                echo '<option value="'.e($code).'"'.($value == $code ? ' selected' : '').'>'.e($cname).'</option>';
            }
            echo '</select>';
        } elseif ($type === 'select') {
            echo '<select name="'.e($name).'" class="'.$inp.' bg-white">';
            if ($ph) {
                echo '<option value="">'.e($ph).'</option>';
            }
            foreach ($opts as $k => $v) {
                echo '<option value="'.e($k).'"'.($value == $k ? ' selected' : '').'>'.e($v).'</option>';
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            $rows = (int) ($field['rows'] ?? 3);
            echo '<textarea name="'.e($name).'" rows="'.$rows.'" placeholder="'.e($ph).'" class="'.$inp.' resize-none">'.e($value).'</textarea>';
        } elseif ($type === 'checkbox') {
            echo '<label class="flex items-center gap-2 cursor-pointer text-[14px] text-body">'
               .'<input type="checkbox" name="'.e($name).'" value="1" class="w-4 h-4 border-[#ddd] rounded text-primary focus:ring-0"'.($value ? ' checked' : '').'>'
               .($label !== null ? e($label) : '')
               .'</label>';
        } else {
            echo '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" placeholder="'.e($ph).'" class="'.$inp.'">';
        }

        echo '</div>';
    }
}

if (!function_exists('falcon_render_checkout_fields')) {
    /**
     * Renders all checkout fields inside a responsive 2-column grid.
     * Call with the output of falcon_get_checkout_fields().
     */
    function falcon_render_checkout_fields(array $fields): void
    {
        if (empty($fields)) {
            return;
        }
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
        foreach ($fields as $field) {
            falcon_render_checkout_field($field);
        }
        echo '</div>';
    }
}

if (!function_exists('falcon_wishlist_count')) {
    function falcon_wishlist_count(): int
    {
        return count(falcon_wishlist_product_ids());
    }
}

if (!function_exists('falcon_enabled_payment_gateways')) {
    /**
     * Enabled storefront payment gateways (single source of truth for checkout + order processing).
     * Returns: [ id => ['id','title','desc','type' => offline|online] ]
     */
    function falcon_enabled_payment_gateways(): array
    {
        $gateways = [];

        if (get_shop_option('shop_payment_cod_enable') === '1') {
            $gateways['cod'] = [
                'id' => 'cod',
                'title' => get_shop_option('shop_payment_cod_title', 'Cash on Delivery'),
                'desc' => get_shop_option('shop_payment_cod_desc', 'Pay with cash upon delivery.'),
                'type' => 'offline',
            ];
        }
        if (get_shop_option('shop_payment_bank_enable') === '1') {
            $gateways['bank'] = [
                'id' => 'bank',
                'title' => get_shop_option('shop_payment_bank_title', 'Direct Bank Transfer'),
                'desc' => get_shop_option('shop_payment_bank_details', 'Make your payment directly into our bank account.'),
                'type' => 'offline',
            ];
        }
        if (get_shop_option('shop_payment_stripe_enable') === '1' && get_shop_option('shop_payment_stripe_secret')) {
            $gateways['stripe'] = [
                'id' => 'stripe',
                'title' => get_shop_option('shop_payment_stripe_title', 'Credit / Debit Card'),
                'desc' => get_shop_option('shop_payment_stripe_desc', 'Pay securely with your card via Stripe.'),
                'type' => 'online',
            ];
        }
        if (get_shop_option('shop_payment_paypal_enable') === '1' && get_shop_option('shop_payment_paypal_email')) {
            $gateways['paypal'] = [
                'id' => 'paypal',
                'title' => get_shop_option('shop_payment_paypal_title', 'PayPal'),
                'desc' => get_shop_option('shop_payment_paypal_desc', 'Pay via PayPal; you can pay with your card if you don’t have an account.'),
                'type' => 'online',
            ];
        }
        if (get_shop_option('shop_payment_sslcommerz_enable') === '1'
            && get_shop_option('shop_payment_sslcommerz_store_id')
            && get_shop_option('shop_payment_sslcommerz_store_pass')) {
            $gateways['sslcommerz'] = [
                'id' => 'sslcommerz',
                'title' => get_shop_option('shop_payment_sslcommerz_title', 'SSLCommerz'),
                'desc' => get_shop_option('shop_payment_sslcommerz_desc', 'Pay with cards, mobile banking (bKash, Nagad) and net banking via SSLCommerz.'),
                'type' => 'online',
            ];
        }

        return $gateways;
    }
}

if (!function_exists('get_falcon_coupon_discount_amount')) {
    function get_falcon_coupon_discount_amount($coupon, $cart, $calcBaseSubtotal = null)
    {
        $amount = (float) ($coupon['amount'] ?? ($coupon['discount'] ?? 0));
        $couponType = $coupon['type'] ?? 'percent';
        $products = (array) ($coupon['products'] ?? []);
        $categories = (array) ($coupon['categories'] ?? []);

        // Free Shipping takes nothing off the cart — its value lands on the shipping line,
        // which falcon_shipping_methods() zeroes out while such a coupon is applied.
        if ($couponType === 'free_shipping') {
            return 0.0;
        }

        // If NO restrictions, apply to the whole provided subtotal
        if (empty($products) && empty($categories)) {
            $base = $calcBaseSubtotal ?? get_falcon_cart_subtotal();
            if ($couponType === 'percent') {
                return $base * ($amount / 100);
            }
            // fixed_product is a per-item amount. Without this it fell through to the
            // fixed_cart branch below and discounted the amount once for the whole cart,
            // so an unrestricted "৳50 off each item" coupon only ever took off ৳50.
            if ($couponType === 'fixed_product') {
                $units = 0;
                foreach ($cart as $item) {
                    $units += (int) ($item['quantity'] ?? 1);
                }

                return min($amount * $units, $base);
            }

            return min($amount, $base);
        }

        // Fetch origin IDs for restricted products and categories for robust matching
        $restrictedProductOriginIds = [];
        if (!empty($products)) {
            $restrictedProductOriginIds = DB::table('posts')
                ->whereIn('id', $products)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        $restrictedCategoryOriginIds = [];
        if (!empty($categories)) {
            $restrictedCategoryOriginIds = DB::table('taxonomy_terms')
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
            if (!$productId) {
                continue;
            }

            // Check Product Eligibility
            $matchProduct = false;
            if (!empty($restrictedProductOriginIds)) {
                $itemIdentity = DB::table('posts')
                    ->where('id', $productId)
                    ->selectRaw('COALESCE(origin_id, id) as identity')
                    ->value('identity');
                $matchProduct = in_array($itemIdentity, $restrictedProductOriginIds);
            }

            // Check Category Eligibility
            $matchCategory = false;
            if (!empty($restrictedCategoryOriginIds)) {
                $itemCategoryIdentities = DB::table('product_category_post')
                    ->join('product_categories', 'product_category_post.product_category_id', '=', 'product_categories.id')
                    ->where('product_category_post.post_id', $productId)
                    ->selectRaw('COALESCE(product_categories.origin_id, product_categories.id) as identity')
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

if (!function_exists('get_falcon_image_url')) {
    function get_falcon_image_url($path, $default = 'https://via.placeholder.com/300?text=No+Image')
    {
        if (empty($path)) {
            return $default;
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

if (!function_exists('lazy_is_special_menu_item')) {
    /** True when a menu item is one of the Lazy Special Menu widgets. */
    function lazy_is_special_menu_item($type): bool
    {
        return in_array($type, ['special_cart', 'special_search', 'special_wishlist'], true);
    }
}

if (!function_exists('lazy_render_special_menu_item')) {
    /**
     * Render a Lazy Special Menu widget (Cart / Search / Wishlist) inside a navigation menu.
     * Returns a full <li>…</li> string. Reuses existing helpers + the global mini-cart drawer.
     *
     * @param  object  $item  navigation_menu_items row (stdClass)
     * @param  string  $style  inline link style inherited from the menu element
     */
    function lazy_render_special_menu_item($item, string $style = '', bool $isMobile = false, $elId = ''): string
    {
        $type = $item->type ?? '';
        $label = $item->title ?? '';

        $icons = [
            'special_cart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
            'special_search' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
            'special_wishlist' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
        ];
        // The Menu Item Options modal lets an editor pick a FontAwesome icon for every item,
        // special ones included — but this always drew its own fixed SVG regardless, so a
        // chosen icon was saved (and shown correctly on the item's row in that modal) and then
        // silently ignored the moment the menu actually rendered. The fallback SVG is only for
        // an item nobody has picked an icon for, matching how it looked before this existed.
        $icon = !empty($item->icon) ? '<i class="'.e($item->icon).'"></i>' : ($icons[$type] ?? '');

        // Count-badge appearance (NO display property here — Tailwind classes control show/hide,
        // matching the header badge so LazyCart's `hidden`-class toggle keeps working).
        //
        // Background follows the Customizer's Primary Color — the same setting the theme
        // header's own cart badge uses (there via the `bg-primary` Tailwind class, which reads
        // the identical option through the theme's runtime Tailwind config). Read directly
        // rather than assuming a `--primary` CSS variable or Tailwind config is in scope: this
        // HTML can end up wherever the menu element is placed, not only inside the theme's own
        // header markup.
        $badgeColor = get_cms_option('theme_primary_color', '#0091ea');
        $badgeStyle = 'min-width:18px;height:18px;padding:0 5px;margin-left:6px;font-size:11px;font-weight:700;line-height:1;color:#fff;background:'.e($badgeColor).';border-radius:9999px;';
        $badgeCls = 'inline-flex items-center justify-center';
        $iconWrap = 'display:inline-flex;align-items:center;gap:2px;';

        if ($type === 'special_cart') {
            $count = function_exists('get_falcon_cart_count') ? (int) get_falcon_cart_count() : 0;
            $cartUrl = Route::has('shop.cart') ? route('shop.cart') : url('/cart');
            $badge = '<span class="cart-count-badge '.$badgeCls.($count > 0 ? '' : ' hidden').'" style="'.$badgeStyle.'">'.$count.'</span>';

            return '<li class="falcon-menu-item lazy-special-item lazy-special-cart">'
                 .'<a href="'.e($cartUrl).'" class="falcon-menu-link lazy-special-link" style="'.$style.$iconWrap.'" '
                 .'onclick="if(window.LazyCart){LazyCart.open();return false;}" aria-label="'.e($label ?: 'Cart').'">'
                 .$icon.$badge
                 .'</a></li>';
        }

        if ($type === 'special_wishlist') {
            $count = function_exists('falcon_wishlist_count') ? (int) falcon_wishlist_count() : 0;
            $wishUrl = Route::has('shop.wishlist') ? route('shop.wishlist') : url('/wishlist');
            $badge = '<span class="wishlist-count-badge '.$badgeCls.($count > 0 ? '' : ' hidden').'" style="'.$badgeStyle.'">'.$count.'</span>';

            return '<li class="falcon-menu-item lazy-special-item lazy-special-wishlist">'
                 .'<a href="'.e($wishUrl).'" class="falcon-menu-link lazy-special-link" style="'.$style.$iconWrap.'" aria-label="'.e($label ?: 'Wishlist').'">'
                 .$icon.$badge
                 .'</a></li>';
        }

        // special_search — icon toggles a simple search box that drops below the menu.
        $searchUrl = Route::has('frontend.search') ? route('frontend.search') : url('/search');
        $toggle = "var p=this.parentNode.querySelector('.lazy-search-panel');"
                ."var open=p.style.display!=='block';p.style.display=open?'block':'none';"
                ."if(open){var i=p.querySelector('input');if(i){i.focus();}}return false;";
        $panelStyle = 'display:none;position:absolute;top:100%;right:0;margin-top:8px;z-index:9999;'
                    .'background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px;min-width:240px;';

        return '<li class="falcon-menu-item lazy-special-item lazy-special-search" style="position:relative;">'
             .'<a href="#" class="falcon-menu-link lazy-special-link" style="'.$style.$iconWrap.'" '
             .'onclick="'.e($toggle).'" aria-label="'.e($label ?: 'Search').'">'
             .$icon
             .'</a>'
             .'<div class="lazy-search-panel" style="'.$panelStyle.'">'
             .'<form action="'.e($searchUrl).'" method="GET" style="display:flex;align-items:center;gap:6px;">'
             .'<input type="text" name="s" placeholder="Search…" autocomplete="off" '
             .'style="flex:1;height:36px;padding:0 10px;border:1px solid #e5e7eb;border-radius:4px;font-size:14px;outline:none;color:#111;">'
             .'<button type="submit" style="height:36px;padding:0 12px;background:#0091ea;color:#fff;border:none;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer;">Go</button>'
             .'</form>'
             .'</div></li>';
    }
}

/**
 * Register Special Text Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['text_block'] = [
        'type' => 'text_block',
        'name' => 'Text Block',
        'icon' => 'fa fa-align-left',
        'template' => 'falcon-cms::frontend.builder.elements.text-block',
        'fields' => [
            // General
            'content' => ['type' => 'wysiwyg', 'label' => 'Content', 'default' => '<p>your content is here...</p>'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16],
            'fontSizeUnit' => ['type' => 'select', 'label' => 'Unit', 'options' => ['px' => 'px', 'em' => 'em', 'rem' => 'rem'], 'default' => 'px'],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Text Align',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'],
                'default' => 'center',
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
                'default' => 'none',
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
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
            ],
            'cssClass' => ['type' => 'text', 'default' => ''],
            'cssId' => ['type' => 'text', 'default' => ''],
        ],
    ];

    return $elements;
});

if (!function_exists('get_lazy_builder_fonts')) {
    /**
     * Every font family a builder layout uses, so the page can load them.
     *
     * Detection is by KEY NAME, not a fixed list: anything named `fontFamily`, `family`,
     * `*FontFamily` or `*_family` counts. The old hard-coded list silently dropped every
     * key that wasn't on it — Read More, submenu/mobile-menu, Post Meta (`meta_family`) and
     * every ACPT custom field (`<slug>_family`) — so those fonts were chosen in the builder
     * but never loaded on the front-end, and the text fell back to the theme font.
     *
     * The walk is fully recursive over ALL nested arrays (not just columns/elements), so a
     * layout stored inside a setting — a post-card or mega-menu layout — is covered too.
     */
    function get_lazy_builder_fonts($layout, &$fonts = [])
    {
        if (!is_array($layout)) {
            return array_values(array_unique($fonts));
        }

        foreach ($layout as $key => $value) {
            if (is_array($value)) {
                get_lazy_builder_fonts($value, $fonts);

                continue;
            }
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }
            if ($key !== 'fontFamily' && $key !== 'family'
                && !str_ends_with($key, 'FontFamily') && !str_ends_with($key, '_family')) {
                continue;
            }

            $family = trim(trim(explode(',', $value)[0]), " '\"");
            if ($family === '' || strcasecmp($family, 'inherit') === 0) {
                continue;
            }
            $fonts[] = $family;
        }

        return array_values(array_unique($fonts));
    }
}

if (!function_exists('falcon_google_font_url')) {
    /**
     * A Google Fonts stylesheet URL for the given families, or '' when none are loadable.
     *
     * Families are matched against the bundled Google Fonts list first. That matters more
     * than it looks: the css2 endpoint answers **400 for the whole request** if any single
     * family is unknown, so one system font (Arial, Helvetica…) or a stray `sans-serif`
     * anywhere in a layout would stop EVERY font on the page from loading. Filtering keeps
     * one bad value from taking the rest down with it.
     *
     * The full 100–900 range is requested because the builder's weight pickers offer it —
     * asking only for 300+ made Thin/Extra-Light silently render as something else.
     */
    function falcon_google_font_url(array $families, string $weights = '100;200;300;400;500;600;700;800;900'): string
    {
        static $known = null;
        if ($known === null) {
            $known = [];
            foreach (falcon_google_fonts() as $f) {
                if (!empty($f['family'])) {
                    $known[mb_strtolower($f['family'])] = $f['family'];
                }
            }
        }

        $use = [];
        foreach ($families as $family) {
            $family = trim(trim((string) $family), " '\"");
            if ($family === '') {
                continue;
            }
            $canonical = $known[mb_strtolower($family)] ?? null;
            // Unknown to the bundled list: keep it only when the list itself is missing,
            // so a stripped install still behaves like before instead of losing all fonts.
            if ($canonical === null) {
                if ($known) {
                    continue;
                }
                $canonical = $family;
            }
            $use[$canonical] = true;
        }
        if (!$use) {
            return '';
        }

        $parts = array_map(fn ($f) => 'family='.str_replace(' ', '+', $f).':wght@'.$weights, array_keys($use));

        return 'https://fonts.googleapis.com/css2?'.implode('&', $parts).'&display=swap';
    }
}

/**
 * Register Button Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['button'] = [
        'type' => 'button',
        'name' => 'Button',
        'icon' => 'fas fa-toggle-on',
        'template' => 'falcon-cms::frontend.builder.elements.button',
        'fields' => [
            // General
            'text' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Click Here'],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => '#'],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self',
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center',
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
            'bgGradientEndColor' => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientStartPosition' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'bgGradientEndPosition' => ['type' => 'number', 'default' => 100, 'tab' => 'design'],
            'bgGradientType' => ['type' => 'text', 'default' => 'linear', 'tab' => 'design'],
            'bgGradientAngle' => ['type' => 'number', 'default' => 180, 'tab' => 'design'],
            'bgGradientHoverStartColor' => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientHoverEndColor' => ['type' => 'color', 'default' => '#005fa3', 'tab' => 'design'],

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
                'tab' => 'design',
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
        ],
    ];

    return $elements;
});

/**
 * Register Menu Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    // This filter is applied many times per render; the menu dropdown only feeds
    // the builder editor, so resolve the list once per request.
    static $menus = null;
    if ($menus === null) {
        $menus = [];
        try {
            $menus = DB::table('navigation_menus')->pluck('name', 'id')->toArray();
        } catch (Exception $e) {
        }
    }

    $elements['menu'] = [
        'type' => 'menu',
        'name' => 'Menu',
        'icon' => 'fa fa-bars',
        'template' => 'falcon-cms::frontend.builder.elements.menu',
        'fields' => [
            // General
            'menuId' => [
                'type' => 'select',
                'label' => 'Select Menu',
                'options' => $menus,
                'default' => count($menus) > 0 ? array_key_first($menus) : '',
                'tab' => 'design',
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'options' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'],
                'default' => 'horizontal',
                'tab' => 'design',
            ],
            'transitionTime' => [
                'type' => 'range',
                'label' => 'Transition Time (s)',
                'default' => 0.3,
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'tab' => 'design',
            ],
            'submenuSpace' => [
                'type' => 'number',
                'label' => 'Space Between Main Menu and Submenu (px)',
                'default' => 10,
                'tab' => 'design',
            ],
            'showArrows' => [
                'type' => 'select',
                'label' => 'Menu Arrows',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
                'tab' => 'design',
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

            // The current page's menu item. The template has always marked it with an
            // .active class, but nothing ever styled it — so the setting people looked for
            // was simply missing rather than broken. Empty means "leave it as the resting
            // colour", which keeps every existing menu looking exactly as it does now.
            'itemColorActive' => ['type' => 'color', 'label' => 'Item Text Color (Active)', 'default' => '', 'tab' => 'design'],
            'itemBgColorActive' => ['type' => 'color', 'label' => 'Item Background Color (Active)', 'default' => '', 'tab' => 'design'],

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
            // NOTE: showArrows and submenuSpace are declared once, above, on the design tab.
            // They used to be repeated here as well; PHP keeps the last declaration, so the
            // duplicates quietly overrode the originals and moved both controls to this tab.
            'submenuDirection' => ['type' => 'text', 'label' => 'Expand Direction', 'default' => 'right', 'tab' => 'submenu'],
            'submenuTransition' => ['type' => 'text', 'label' => 'Expand Transition', 'default' => 'fade', 'tab' => 'submenu'],
            'submenuMinWidth' => ['type' => 'text', 'label' => 'Min Width', 'default' => '200px', 'tab' => 'submenu'],
            'submenuMaxWidth' => ['type' => 'text', 'label' => 'Max Width', 'default' => '220px', 'tab' => 'submenu'],

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
                'tab' => 'design',
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ],
    ];

    return $elements;
});

/**
 * Register Image Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['image'] = [
        'type' => 'image',
        'name' => 'Image',
        'icon' => 'fa fa-image',
        'template' => 'falcon-cms::frontend.builder.elements.image',
        'fields' => [
            // General
            'url' => ['type' => 'media', 'label' => 'Image URL', 'default' => ''],
            'alt' => ['type' => 'text', 'label' => 'Alt Text', 'default' => ''],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => ''],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Link Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self',
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center',
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
                'tab' => 'design',
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ],
    ];

    return $elements;
});

// Supported social platforms: slug => [label, FontAwesome icon]. Used by the Social Icons
// element to generate one URL field per platform (no icon picking) and to render the icons.
if (!function_exists('falcon_social_platforms')) {
    function falcon_social_platforms(): array
    {
        return [
            'behance' => ['label' => 'Behance',     'icon' => 'fa-brands fa-behance',      'color' => '#1769FF'],
            'blogger' => ['label' => 'Blogger',     'icon' => 'fa-brands fa-blogger-b',    'color' => '#FF5722'],
            'bluesky' => ['label' => 'Bluesky',     'icon' => 'fa-brands fa-bluesky',      'color' => '#0085FF'],
            'deviantart' => ['label' => 'Deviantart',  'icon' => 'fa-brands fa-deviantart',   'color' => '#05CC47'],
            'digg' => ['label' => 'Digg',        'icon' => 'fa-brands fa-digg',         'color' => '#1C1C1C'],
            'discord' => ['label' => 'Discord',     'icon' => 'fa-brands fa-discord',      'color' => '#5865F2'],
            'dribbble' => ['label' => 'Dribbble',    'icon' => 'fa-brands fa-dribbble',     'color' => '#EA4C89'],
            'dropbox' => ['label' => 'Dropbox',     'icon' => 'fa-brands fa-dropbox',      'color' => '#0061FF'],
            'email' => ['label' => 'Email',       'icon' => 'fa fa-envelope',            'color' => '#EA4335'],
            'facebook' => ['label' => 'Facebook',    'icon' => 'fa-brands fa-facebook-f',   'color' => '#1877F2'],
            'flickr' => ['label' => 'Flickr',      'icon' => 'fa-brands fa-flickr',       'color' => '#0063DC'],
            'github' => ['label' => 'GitHub',      'icon' => 'fa-brands fa-github',       'color' => '#181717'],
            'instagram' => ['label' => 'Instagram',   'icon' => 'fa-brands fa-instagram',    'color' => '#E4405F'],
            'linkedin' => ['label' => 'LinkedIn',    'icon' => 'fa-brands fa-linkedin-in',  'color' => '#0A66C2'],
            'medium' => ['label' => 'Medium',      'icon' => 'fa-brands fa-medium',       'color' => '#000000'],
            'phone' => ['label' => 'Phone',       'icon' => 'fa fa-phone',               'color' => '#34A853'],
            'pinterest' => ['label' => 'Pinterest',   'icon' => 'fa-brands fa-pinterest-p',  'color' => '#BD081C'],
            'reddit' => ['label' => 'Reddit',      'icon' => 'fa-brands fa-reddit-alien', 'color' => '#FF4500'],
            'snapchat' => ['label' => 'Snapchat',    'icon' => 'fa-brands fa-snapchat',     'color' => '#FFFC00'],
            'soundcloud' => ['label' => 'SoundCloud',  'icon' => 'fa-brands fa-soundcloud',   'color' => '#FF5500'],
            'spotify' => ['label' => 'Spotify',     'icon' => 'fa-brands fa-spotify',      'color' => '#1DB954'],
            'telegram' => ['label' => 'Telegram',    'icon' => 'fa-brands fa-telegram',     'color' => '#26A5E4'],
            'tiktok' => ['label' => 'TikTok',      'icon' => 'fa-brands fa-tiktok',       'color' => '#000000'],
            'tumblr' => ['label' => 'Tumblr',      'icon' => 'fa-brands fa-tumblr',       'color' => '#36465D'],
            'twitch' => ['label' => 'Twitch',      'icon' => 'fa-brands fa-twitch',       'color' => '#9146FF'],
            'vimeo' => ['label' => 'Vimeo',       'icon' => 'fa-brands fa-vimeo-v',      'color' => '#1AB7EA'],
            'website' => ['label' => 'Website',     'icon' => 'fa fa-globe',               'color' => '#2271b1'],
            'whatsapp' => ['label' => 'WhatsApp',    'icon' => 'fa-brands fa-whatsapp',     'color' => '#25D366'],
            'wordpress' => ['label' => 'WordPress',   'icon' => 'fa-brands fa-wordpress',    'color' => '#21759B'],
            'x_twitter' => ['label' => 'X (Twitter)', 'icon' => 'fa-brands fa-x-twitter',    'color' => '#000000'],
            'youtube' => ['label' => 'YouTube',     'icon' => 'fa-brands fa-youtube',      'color' => '#FF0000'],
        ];
    }
}

// Returns a readable foreground (#111111 / #ffffff) for a given background hex — used so
// brand-coloured boxes keep their icon legible (e.g. white icon on Snapchat yellow → dark).
if (!function_exists('lazy_contrast_color')) {
    function lazy_contrast_color($hex): string
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) < 6) {
            return '#ffffff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $lum > 0.65 ? '#111111' : '#ffffff';
    }
}

// Social Icons — one URL field per platform (General tab). Fill a field and that platform's
// icon shows on the front-end. No icon picking; the icon is fixed per platform.
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $fields = [];
    foreach (falcon_social_platforms() as $key => $p) {
        // social_icon / social_color / social_label: tell the live canvas the fixed icon, brand colour and name.
        $fields[$key] = ['type' => 'text', 'label' => $p['label'].' Link', 'tab' => 'general', 'default' => '',
            'social_icon' => $p['icon'], 'social_color' => $p['color'] ?? '#2271b1', 'social_label' => $p['label']];
    }
    // Design + behaviour
    $fields['target'] = ['type' => 'select', 'label' => 'Open Links In', 'tab' => 'design',
        'options' => ['_blank' => 'New Window', '_self' => 'Same Window'], 'default' => '_blank'];
    $fields['shape'] = ['type' => 'select', 'label' => 'Shape', 'tab' => 'design',
        'options' => ['circle' => 'Circle', 'rounded' => 'Rounded', 'square' => 'Square'], 'default' => 'circle'];
    // Boxed Style: Default/Yes = icons sit in a coloured box; No = plain coloured icons (no box).
    $fields['boxedStyle'] = ['type' => 'radio', 'label' => 'Boxed Style', 'tab' => 'design',
        'options' => ['default' => 'Default', 'yes' => 'Yes', 'no' => 'No'], 'default' => 'default'];
    // Color Type: Default = theme colours; Custom = pick your own; Brand = each platform's official colour.
    $fields['colorType'] = ['type' => 'select', 'label' => 'Color Type', 'tab' => 'design',
        'options' => ['default' => 'Default', 'custom' => 'Custom Colors', 'brand' => 'Brand Colors'], 'default' => 'default'];
    $fields['boxSize'] = ['type' => 'number', 'label' => 'Box Size',  'default' => 38, 'min' => 0, 'tab' => 'design',
        'condition' => ['field' => 'boxedStyle', 'value' => 'no', 'operator' => '!=']];
    $fields['iconSize'] = ['type' => 'number', 'label' => 'Icon Size', 'default' => 18, 'min' => 0, 'tab' => 'design'];
    $fields['gap'] = ['type' => 'number', 'label' => 'Gap',       'default' => 10, 'min' => 0, 'tab' => 'design'];
    $fields['align'] = ['type' => 'select', 'label' => 'Alignment', 'tab' => 'design', 'responsive' => true,
        'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'default' => 'center'];
    // Tooltip showing the platform name on hover. Default = Top; None = no tooltip.
    $fields['tooltipPosition'] = ['type' => 'select', 'label' => 'Tooltip Position', 'tab' => 'design',
        'options' => ['default' => 'Default', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right', 'none' => 'None'], 'default' => 'default'];
    // Colour pickers only matter for "Custom Colors".
    $fields['iconColor'] = ['type' => 'color', 'label' => 'Icon Color',       'default' => '#ffffff', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['bgColor'] = ['type' => 'color', 'label' => 'Background',        'default' => '#2271b1', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['iconHoverColor'] = ['type' => 'color', 'label' => 'Icon Hover Color', 'default' => '#ffffff', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['bgHoverColor'] = ['type' => 'color', 'label' => 'Hover Background',  'default' => '#135e96', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['margin'] = ['type' => 'dimensions', 'label' => 'Margin', 'unit' => 'px', 'tab' => 'design'];
    $fields['visibility'] = ['type' => 'object', 'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true], 'tab' => 'design'];

    $elements['social_icons'] = [
        'type' => 'social_icons',
        'name' => 'Social Icons',
        'icon' => 'fa fa-share-alt',
        'template' => 'falcon-cms::frontend.builder.elements.social-icons',
        'fields' => $fields,
    ];

    return $elements;
});

/**
 * Register the Advanced Search element for Falcon Builder.
 * A smart search bar: choose which post type to search, optional live (AJAX)
 * results dropdown, and an optional category dropdown inside the bar.
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    // Dynamic post-type options (active types). Multi-select; none selected = all content.
    // Resolved once per request — this filter runs many times per render and the
    // options only feed the builder editor UI.
    static $ptOptions = null;
    if ($ptOptions === null) {
        $ptOptions = [];
        try {
            foreach (PostType::where('is_active', true)->orderBy('name')->get() as $pt) {
                $ptOptions[$pt->slug] = $pt->name;
            }
        } catch (Throwable $e) {
            $ptOptions = ['post' => 'Posts', 'page' => 'Pages'];
        }
    }

    $elements['advanced_search'] = [
        'type' => 'advanced_search',
        'name' => 'Advanced Search',
        'icon' => 'fa fa-magnifying-glass',
        'template' => 'falcon-cms::frontend.builder.elements.advanced-search',
        'fields' => [
            // ── General ──
            'searchPostType' => ['type' => 'multiselect', 'label' => 'Search In (none = all content)', 'tab' => 'general', 'options' => $ptOptions, 'default' => [], 'placeholder' => 'All content (select post types)'],
            'placeholder' => ['type' => 'text', 'label' => 'Placeholder Text', 'tab' => 'general', 'default' => 'Search...'],
            'enableLiveSearch' => ['type' => 'toggle', 'label' => 'Live Search (AJAX results)', 'tab' => 'general', 'default' => true],
            'enableCategoryDropdown' => ['type' => 'toggle', 'label' => 'Show Category Dropdown', 'tab' => 'general', 'default' => false],
            'showButton' => ['type' => 'toggle', 'label' => 'Show Search Button', 'tab' => 'general', 'default' => true],
            'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'tab' => 'general', 'default' => 'Search', 'condition' => ['field' => 'showButton', 'value' => true]],

            // ── Design ──
            'accentColor' => ['type' => 'color', 'label' => 'Accent Color', 'tab' => 'design', 'default' => '#0091ea'],
            'bgColor' => ['type' => 'color', 'label' => 'Background', 'tab' => 'design', 'default' => '#ffffff'],
            'textColor' => ['type' => 'color', 'label' => 'Field Text Color', 'tab' => 'design', 'default' => '#1d2327'],
            'placeholderColor' => ['type' => 'color', 'label' => 'Placeholder Color', 'tab' => 'design', 'default' => '#9ca3af'],
            'dropdownTextColor' => ['type' => 'color', 'label' => 'Dropdown Text Color', 'tab' => 'design', 'default' => '#1d2327'],
            'dropdownBgColor' => ['type' => 'color', 'label' => 'Dropdown Background', 'tab' => 'design', 'default' => '#ffffff'],
            'borderColor' => ['type' => 'color', 'label' => 'Border Color', 'tab' => 'design', 'default' => '#e5e7eb'],
            'height' => ['type' => 'number', 'label' => 'Height (px)', 'tab' => 'design', 'default' => 46, 'min' => 28],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius (px)', 'tab' => 'design', 'default' => 6, 'min' => 0],
            'maxWidth' => ['type' => 'number', 'label' => 'Max Width (px, 0 = full)', 'tab' => 'design', 'default' => 0, 'min' => 0],
            'align' => ['type' => 'select', 'label' => 'Alignment', 'tab' => 'design', 'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'default' => 'flex-start'],

            // ── Extras ──
            'visibility' => ['type' => 'object', 'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true], 'tab' => 'design'],
        ],
    ];

    return $elements;
});

if (!function_exists('falcon_licensed')) {
    /**
     * Whether a valid paid Pro license is active on this site — regardless of the
     * freemium grace window or grandfathering. Used to hide the "now freemium /
     * upgrade" banners once the customer has actually licensed the site.
     */
    function falcon_licensed(): bool
    {
        try {
            return app(LicenseGateway::class)->licensed();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro_updates_allowed')) {
    /**
     * Whether Pro UPDATES may be fetched right now. Pro FEATURES are perpetual — a one-time
     * purchase is owned forever (see falcon_pro()) — but new releases are limited to the
     * licence's update window; once it lapses the site keeps every feature and must renew to
     * pull newer versions. Falls back to licensed() for older Pro gateways that predate the
     * update-window methods, and is never relevant to the free core (its updates are ungated).
     */
    function falcon_pro_updates_allowed(): bool
    {
        try {
            $gw = app(LicenseGateway::class);
            if (method_exists($gw, 'updatesAllowed')) {
                return (bool) $gw->updatesAllowed();
            }

            return $gw->licensed();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro_expired')) {
    /**
     * Whether the site has a licensed-but-expired Pro plan — features still work, only the
     * update window has ended. Used to show a "renew for updates" notice (not a lockout).
     */
    function falcon_pro_expired(): bool
    {
        try {
            $gw = app(LicenseGateway::class);

            return $gw->licensed() && method_exists($gw, 'expired') && (bool) $gw->expired();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro')) {
    /**
     * Whether a FalconCMS Pro feature is available. Core uses this to gate paid
     * features and decide when to show an "upgrade to Pro" prompt; the Pro package
     * flips these on once its license validates.
     *
     *   falcon_pro()             → is any Pro license active?
     *   falcon_pro('ecommerce')  → is the e-commerce feature available?
     *
     * Known feature keys: 'ecommerce', 'multilang', 'analytics', 'builder_pro'.
     */
    function falcon_pro(?string $feature = null): bool
    {
        // Features that have graduated into the free core — always available, no license needed.
        if ($feature !== null && in_array($feature, falcon_free_features(), true)) {
            return true;
        }

        try {
            if (app(LicenseGateway::class)->active($feature)) {
                return true;
            }
        } catch (Throwable $e) {
        }

        // Grace window after an upgrade — nothing locks yet.
        if (falcon_freemium_grace_active()) {
            return true;
        }

        // Grandfathered — features already in use before freemium stay free on this install.
        return falcon_feature_grandfathered($feature);
    }
}

if (!function_exists('falcon_upgrade_url')) {
    /** Where every "Upgrade to Pro" call-to-action points (config falcon-options.upgrade_url). */
    function falcon_upgrade_url(): string
    {
        return (string) config('falcon-options.upgrade_url', 'https://falconcms.com/#pricing');
    }
}

if (!function_exists('falcon_freemium_grace_active')) {
    /**
     * Whether the site is still inside its freemium grace window — the transition period
     * (set on upgrade) during which every Pro feature stays unlocked before gating begins.
     */
    function falcon_freemium_grace_active(): bool
    {
        try {
            // Global fixed launch cutoff (same date for every site) — free until
            // this date, Pro features lock after it unless licensed.
            $until = config('falcon-options.freemium_grace_until', '2026-08-01');
            if (!$until) {
                return false;
            }

            return Illuminate\Support\Carbon::now()->lt(Illuminate\Support\Carbon::parse($until));
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_feature_grandfathered')) {
    /**
     * Whether a feature was grandfathered — already in use when the site upgraded into
     * freemium, so it stays free forever on this install. Pass null to ask "is anything
     * grandfathered?".
     */
    function falcon_feature_grandfathered(?string $feature = null): bool
    {
        try {
            $raw = get_cms_option('falcon_grandfathered_features', null);
            $list = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : []);
            $list = is_array($list) ? $list : [];
            if ($feature === null) {
                return !empty($list);
            }

            return in_array($feature, $list, true);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro_editable')) {
    /**
     * Whether a Pro feature is FULLY unlocked for creating/editing — i.e. covered by an active
     * license or the grace window, but NOT merely grandfathered. Grandfathering keeps already-
     * created content working (it still renders), but editing/moving it — or adding more — needs
     * real Pro. The builder uses this to lock Pro elements read-only once the grace window ends.
     */
    function falcon_pro_editable(?string $feature = null): bool
    {
        // Free-core features are fully editable for everyone.
        if ($feature !== null && in_array($feature, falcon_free_features(), true)) {
            return true;
        }

        try {
            if (app(LicenseGateway::class)->active($feature)) {
                return true;
            }
        } catch (Throwable $e) {
        }

        return falcon_freemium_grace_active();
    }
}

if (!function_exists('falcon_free_features')) {
    /**
     * Feature keys that were once Pro but are now part of the free core — available on
     * every site without a licence. E-commerce graduated to free in v2.2. Override via
     * config('falcon-options.free_features') if needed.
     */
    function falcon_free_features(): array
    {
        return (array) config('falcon-options.free_features', ['ecommerce']);
    }
}
