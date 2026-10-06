<?php

/**
 * Icon sets, Font Awesome and Google Fonts.
 *
 * Loaded by src/helpers.php.
 */

use Illuminate\Support\Facades\Cache;

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

        $file = __DIR__.'/../../public/'.ltrim(str_replace('css/', 'assets/css/', $def['asset']), '/');
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

if (!function_exists('falcon_icon_inline_sets')) {
    /**
     * Every icon stylesheet the CMS ships that can be cut down to the icons a page uses: Font
     * Awesome plus the extra sets. `glyph` says how one icon's rule looks: "before" for
     * `.ri-home-line::before{content:…}`, "var" for Font Awesome's `.fa-house{--fa:…}`.
     *
     * @return array<string,array{class:string,asset:string,glyph:string}>
     */
    function falcon_icon_inline_sets(): array
    {
        $sets = ['fontawesome' => ['class' => 'fa-', 'asset' => 'css/font-awesome.all.min.css', 'glyph' => 'var']];
        foreach (falcon_icon_sets() as $set => $def) {
            $sets[$set] = ['class' => $def['class'], 'asset' => $def['asset'], 'glyph' => 'before'];
        }

        return $sets;
    }
}

if (!function_exists('falcon_icon_set_parts')) {
    /**
     * One icon stylesheet split into what every page needs (the @font-face rules, the base
     * classes, sizing and animation helpers) and one rule per icon, keyed by icon class.
     *
     * A page that shows two Remix icons should not download all 3,000: with these parts,
     * falcon_icon_set_inline_css() emits only the rules a page uses. Parsed once per file
     * version and cached, since every page asks for the same split.
     *
     * @return array{base:string,glyphs:array<string,string>}
     */
    function falcon_icon_set_parts(string $set): array
    {
        $def = falcon_icon_inline_sets()[$set] ?? null;
        $file = $def ? __DIR__.'/../../public/'.str_replace('css/', 'assets/css/', $def['asset']) : '';
        if (!$def || !is_file($file)) {
            return ['base' => '', 'glyphs' => []];
        }

        $parse = function () use ($file, $def) {
            $css = preg_replace('#/\*.*?\*/#s', '', (string) @file_get_contents($file)) ?? '';
            // the stylesheet is served from vendor/falcon-cms/css/, so its fonts sit at ../webfonts/
            $fonts = rtrim(asset('vendor/falcon-cms/webfonts'), '/').'/';
            $glyphSelector = $def['glyph'] === 'var'
                ? '/^\.('.$def['class'].'[a-z0-9-]+)$/i'
                : '/^\.('.$def['class'].'[a-z0-9-]+)\s*::?before$/i';

            $base = '';
            $glyphs = [];
            $len = strlen($css);
            $depth = 0;
            $start = 0;
            for ($i = 0; $i < $len; $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } elseif ($css[$i] === '}' && --$depth === 0) {
                    $block = trim(substr($css, $start, $i - $start + 1));
                    $start = $i + 1;
                    $brace = strpos($block, '{');
                    $prelude = trim(substr($block, 0, $brace));
                    $body = substr($block, $brace);

                    if (stripos($prelude, '@font-face') === 0) {
                        // swap: the text and layout show at once; the icon fills in when its font lands
                        $body = preg_replace('/font-display\s*:\s*[a-z]+\s*;?/i', '', $body);
                        $body = '{font-display:swap;'.ltrim($body, '{');
                        $body = str_replace(['url("../webfonts/', "url('../webfonts/", 'url(../webfonts/'], ['url("'.$fonts, "url('".$fonts, 'url('.$fonts], $body);
                        $base .= '@font-face'.$body;

                        continue;
                    }

                    $selectors = array_map('trim', explode(',', $prelude));
                    $icons = [];
                    foreach ($selectors as $selector) {
                        if (preg_match($glyphSelector, $selector, $m)) {
                            $icons[] = strtolower($m[1]);
                        }
                    }
                    $isGlyph = $prelude !== '' && $prelude[0] !== '@' && count($icons) === count($selectors)
                        && ($def['glyph'] !== 'var' || str_contains($body, '--fa'));
                    if ($isGlyph) {
                        // aliases share one rule (.fa-mail-reply,.fa-reply{…}): each name maps to it
                        foreach ($icons as $icon) {
                            $glyphs[$icon] = $prelude.$body;
                        }
                    } else {
                        $base .= $prelude.$body; // base classes, size/rotate/spin helpers, @keyframes
                    }
                }
            }

            return ['base' => $base, 'glyphs' => $glyphs];
        };

        $key = 'falcon_icon_parts_'.$set.'_'.(int) @filemtime($file).'_'.md5(asset('vendor/falcon-cms/webfonts'));
        try {
            return Cache::remember($key, 86400, $parse);
        } catch (Throwable $e) {
            return $parse();
        }
    }
}

if (!function_exists('falcon_icon_set_inline_css')) {
    /**
     * The CSS for just the icons a chunk of rendered HTML uses, from the given sets (all of
     * them by default), ready to go in a <style> tag. A set the HTML uses no icon from adds
     * nothing.
     *
     * The whole HTML is scanned, not only class attributes, so an icon named in an Alpine
     * expression, a data attribute or an inline script (an accordion's open/closed icon) is
     * kept too. Used by "Inline Only the Icons in Use" (HtmlOptimizeMiddleware).
     *
     * @param  list<string>|null  $sets
     */
    function falcon_icon_set_inline_css(string $html, ?array $sets = null): string
    {
        $css = '';
        foreach (falcon_icon_inline_sets() as $set => $def) {
            if ($sets !== null && !in_array($set, $sets, true)) {
                continue;
            }
            if (!preg_match_all('/(?<![a-z0-9_-])('.$def['class'].'[a-z0-9-]+)/i', $html, $m)) {
                continue;
            }
            $parts = falcon_icon_set_parts($set);
            $used = array_intersect_key($parts['glyphs'], array_flip(array_map('strtolower', array_unique($m[1]))));
            if ($used) {
                $css .= $parts['base'].implode('', array_unique($used));
            }
        }

        return $css;
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
        $file = __DIR__.'/../../public/assets/css/font-awesome.all.min.css';
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
        $file = __DIR__.'/../../resources/google-fonts.json';
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
        $file = __DIR__.'/../../resources/font-awesome-icons.json';
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

if (!function_exists('get_falcon_builder_fonts')) {
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
    function get_falcon_builder_fonts($layout, &$fonts = [])
    {
        if (!is_array($layout)) {
            return array_values(array_unique($fonts));
        }

        foreach ($layout as $key => $value) {
            if (is_array($value)) {
                get_falcon_builder_fonts($value, $fonts);

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

if (!function_exists('get_falcon_builder_font_weights')) {
    /**
     * Every font weight a builder layout actually uses, so the page can ask Google for only
     * those instead of all nine (100–900) per family.
     *
     * Detection is by key name, like get_falcon_builder_fonts(): anything named `fontWeight`,
     * `variant`, `*Weight` or `*weight`. Values map 'bold' → 700 and 'normal' → 400; only whole
     * hundreds 100–900 are kept. The caller unions these with the theme's own typography weights
     * and with 400/700 as a floor, so a weight that is used is always present — and because the
     * result is a subset of the full 100–900 the page requested before, no font can start failing.
     */
    function get_falcon_builder_font_weights($layout, array &$weights = []): array
    {
        if (!is_array($layout)) {
            return array_values(array_unique($weights));
        }
        foreach ($layout as $key => $value) {
            if (is_array($value)) {
                get_falcon_builder_font_weights($value, $weights);

                continue;
            }
            if (!is_string($key)) {
                continue;
            }
            if ($key !== 'variant' && $key !== 'fontWeight'
                && !str_ends_with($key, 'Weight') && !str_ends_with($key, 'weight')) {
                continue;
            }
            $w = is_string($value) ? strtolower(trim($value)) : $value;
            // Named weights map to their CSS number so a font set to e.g. "Light" or "Semi Bold"
            // keeps that weight instead of being dropped (a bare (int) cast would make it 0).
            $named = [
                'thin' => 100, 'hairline' => 100,
                'extralight' => 200, 'extra-light' => 200, 'ultralight' => 200, 'ultra-light' => 200,
                'light' => 300,
                'normal' => 400, 'regular' => 400, 'book' => 400, '' => 400,
                'medium' => 500,
                'semibold' => 600, 'semi-bold' => 600, 'demibold' => 600, 'demi-bold' => 600,
                'bold' => 700,
                'extrabold' => 800, 'extra-bold' => 800, 'ultrabold' => 800, 'ultra-bold' => 800,
                'black' => 900, 'heavy' => 900,
            ];
            if (is_string($w)) {
                $w = $named[str_replace(' ', '', $w)] ?? $w;
            } elseif ($w === null) {
                $w = 400;
            }
            $w = (int) $w;
            if ($w >= 100 && $w <= 900) {
                $weights[] = $w;
            }
        }

        return array_values(array_unique($weights));
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
