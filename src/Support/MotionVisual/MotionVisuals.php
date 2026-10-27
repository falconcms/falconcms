<?php

namespace FalconCms\Core\Support\MotionVisual;

use FalconCms\Core\Support\CustomFonts\CustomFonts;
use Throwable;

/**
 * Motion Visual: an animated layer across a whole column or container (Column / Container →
 * Extra → Motion Visual), behind its content or in front of it.
 *
 * Two parts, together or alone:
 *  - dots: a canvas of drifting dots (count, size, color, speed, lines between near ones,
 *    and what the mouse does to them);
 *  - items: shapes from the library (Shapes) and the user's own pictures, each placed,
 *    sized, turned, colored and given an animation (float, rotate, pulse, …).
 *
 * Every setting is a flat mv_* scalar, because the shortcode converter keeps scalars on a
 * round trip and drops arrays. The item list is therefore one string, mv_items: the list as
 * JSON, URL-encoded (encodeURIComponent in the builder), which holds no quote or bracket a
 * shortcode attribute could trip over.
 */
final class MotionVisuals
{
    /** The settings a visual has, with their defaults. */
    public const SHARED = [
        'mv_enabled' => true,       // the panel's switch; off keeps every setting but draws nothing
        'mv_preset' => '',          // the preset it started from; empty = no visual
        'mv_layer' => 'behind',     // behind | front (front lets clicks through)
        'mv_mouse' => true,         // dots and items answer the pointer
        'mv_speed' => 1,            // overall speed
        'mv_opacity' => 100,
        'mv_tablet' => 'simple',    // full | simple (fewer dots, no pointer) | static | hide
        'mv_mobile' => 'simple',
        'mv_primary' => '',         // empty: the theme's primary color
        'mv_accent' => '',
        'mv_dots' => false,
        'mv_dots_count' => 60,
        'mv_dots_size' => 3,
        'mv_dots_color' => '',      // empty: the main color
        'mv_dots_speed' => 0.6,
        'mv_dots_links' => false,
        'mv_dots_link_dist' => 120,
        'mv_dots_mouse' => 'repel', // none | repel | attract
        'mv_items' => '',
    ];

    public const ANIMATIONS = ['none', 'float', 'drift', 'rotate', 'pulse', 'swing', 'orbit', 'bounce'];

    /** Fonts a text item can use: the page's own, or a family every device has. */
    public const FONTS = [
        'inherit' => 'inherit',
        'sans' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif',
        'serif' => 'ui-serif,Georgia,Cambria,"Times New Roman",serif',
        'mono' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace',
    ];

    /** fill: solid; outline: the letters drawn as lines; gradient: main color into accent. */
    public const TEXT_STYLES = ['fill', 'outline', 'gradient'];

    public const MAX_TEXT = 200;

    /**
     * The third color choice beside Main and Accent: the color keeps changing by itself.
     * Items animate it in CSS; the dots in the script, each dot on its own.
     */
    public const RANDOM = 'random';

    private const CHOICES = [
        'mv_layer' => ['behind', 'front'],
        'mv_tablet' => ['full', 'simple', 'static', 'hide'],
        'mv_mobile' => ['full', 'simple', 'static', 'hide'],
        'mv_dots_mouse' => ['none', 'repel', 'attract'],
    ];

    private const RANGES = [
        'mv_speed' => [0.25, 3],
        'mv_opacity' => [5, 100],
        'mv_dots_count' => [5, 250],
        'mv_dots_size' => [0.5, 14],
        'mv_dots_speed' => [0.05, 4],
        'mv_dots_link_dist' => [40, 260],
    ];

    /** Most items a visual keeps; more is a slideshow, not a background. */
    public const MAX_ITEMS = 40;

    /**
     * A column's or container's settings read as a Motion Visual, every value checked:
     * choices kept to what exists, numbers to their range, colors to colors, pictures to
     * site paths or http(s) addresses. Null when it has none.
     */
    public static function resolve(array $settings): ?array
    {
        // Switched off: nothing at all reaches the page — no markup, so no styles, and the
        // footer hook finds no visual and adds no script. The settings stay for switching back.
        if ((string) ($settings['mv_preset'] ?? '') === '' || self::off($settings['mv_enabled'] ?? true)) {
            return null;
        }
        $mv = [];
        foreach (self::SHARED as $key => $default) {
            $v = $settings[$key] ?? $default;
            if (is_bool($default)) {
                $v = ($v === '' || $v === null) ? $default : !($v === false || $v === 'false' || $v === '0' || $v === 0);
            } elseif (isset(self::CHOICES[$key])) {
                $v = in_array($v, self::CHOICES[$key], true) ? $v : $default;
            } elseif (isset(self::RANGES[$key])) {
                [$lo, $hi] = self::RANGES[$key];
                $v = (float) (is_numeric($v) ? max($lo, min($hi, (float) $v)) : $default);
            }
            $mv[$key] = $v;
        }
        $mv['mv_preset'] = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $mv['mv_preset'])) ?: 'custom';

        $theme = self::color((string) get_cms_option('theme_primary_color', '#0091ea')) ?: '#005ab4';
        $mv['mv_primary'] = self::color((string) $mv['mv_primary']) ?: $theme;
        $mv['mv_accent'] = self::color((string) $mv['mv_accent']) ?: '#f59e0b';
        $mv['mv_dots_color'] = self::paint((string) $mv['mv_dots_color'], $mv) ?: $mv['mv_primary'];
        $mv['items'] = array_map(fn ($i) => self::item($i, $mv), self::decodeItems($settings['mv_items'] ?? ''));

        return $mv;
    }

    /** The item list from mv_items: the URL-encoded JSON the builder writes, or an array. */
    public static function decodeItems($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode(rawurldecode($raw), true);
        }
        if (!is_array($raw)) {
            return [];
        }

        return array_slice(array_values(array_filter($raw, 'is_array')), 0, self::MAX_ITEMS);
    }

    /** The other way, for presets and tests: what the builder stores in mv_items. */
    public static function encodeItems(array $items): string
    {
        return rawurlencode((string) json_encode(array_values($items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** One item, checked and filled in. */
    private static function item(array $i, array $mv): array
    {
        $num = function ($v, float $lo, float $hi, float $d): float {
            return is_numeric($v) ? round(max($lo, min($hi, (float) $v)), 3) : $d;
        };
        $kind = in_array($i['kind'] ?? '', ['image', 'text'], true) ? $i['kind'] : 'shape';
        $shape = (string) ($i['shape'] ?? 'circle');
        $anim = (string) ($i['anim'] ?? 'float');
        $color = self::paint((string) ($i['color'] ?? ''), $mv) ?: $mv['mv_primary'];
        $id = preg_match('/^[a-z0-9]{1,24}$/i', (string) ($i['id'] ?? '')) ? (string) $i['id'] : substr(md5(json_encode($i)), 0, 8);
        $random = $color === self::RANDOM;
        // Random color: each item gets its own pace and starting point (from its id, so a page
        // looks the same on every load), so they never change color in step with each other.
        $h = crc32($id);
        $rdur = round((12 + $h % 9) / (float) $mv['mv_speed'], 2);

        return [
            'id' => $id,
            'random' => $random,
            'rdur' => $rdur,
            'rdelay' => round(($h >> 4) % 100 / 100 * $rdur, 2),
            'rreverse' => (bool) (($h >> 11) & 1), // and half of them turn the other way round the wheel
            'kind' => $kind,
            'shape' => isset(Shapes::all()[$shape]) ? $shape : 'circle',
            'src' => $kind === 'image' ? self::imageUrl((string) ($i['src'] ?? '')) : '',
            'x' => $num($i['x'] ?? null, -20, 120, 50),
            'y' => $num($i['y'] ?? null, -20, 120, 50),
            'size' => $num($i['size'] ?? null, 0.5, 120, 8),
            'rot' => $num($i['rot'] ?? null, -360, 360, 0),
            'color' => $random ? 'var(--mv-rc)' : $color,
            'opacity' => $num($i['opacity'] ?? null, 0, 100, 100),
            'blur' => $num($i['blur'] ?? null, 0, 80, 0),
            'anim' => in_array($anim, self::ANIMATIONS, true) ? $anim : 'float',
            'speed' => $num($i['speed'] ?? null, 0.5, 120, 8),
            'dist' => $num($i['dist'] ?? null, 0, 300, 24),
            'delay' => $num($i['delay'] ?? null, 0, 60, 0),
            'depth' => $num($i['depth'] ?? null, -4, 4, 1),
        ] + ($kind === 'text' ? self::text($i, $mv, $color, $num) : []);
    }

    /**
     * A text item: plain text only (it is printed escaped), in a few fonts and three looks.
     * Its size is the font size as a share of the layer's width, so it keeps its place in the
     * design from a wide desktop to a phone.
     */
    private static function text(array $i, array $mv, string $color, callable $num): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($i['text'] ?? ''));
        $text = trim(mb_substr(strip_tags($text), 0, self::MAX_TEXT));
        $font = (string) ($i['font'] ?? 'inherit');
        $style = (string) ($i['style'] ?? 'fill');
        $weight = (int) $num($i['weight'] ?? null, 100, 900, 800);

        return [
            'text' => $text !== '' ? $text : 'Text',
            'font' => isset(self::FONTS[$font]) ? $font : (CustomFonts::isCustom($font) ? CustomFonts::find($font)['family'] : 'inherit'),
            'weight' => $weight - $weight % 100,
            'style' => in_array($style, self::TEXT_STYLES, true) ? $style : 'fill',
            'spacing' => $num($i['spacing'] ?? null, -0.1, 0.6, 0),
            'upper' => !empty($i['upper']) && $i['upper'] !== 'false',
            // A gradient runs from the item's color into the other of the visual's two colors.
            // Random: the gradient's far end is a second changing color, half a turn away.
            'color2' => $color === self::RANDOM ? 'var(--mv-rc2)' : ($color === $mv['mv_accent'] ? $mv['mv_primary'] : $mv['mv_accent']),
        ];
    }

    /** The markup for a column or container, or '' when it has no visual. */
    public static function render(array $settings, string $where = 'column', bool $editor = false): string
    {
        $mv = self::resolve($settings);
        if (!$mv || (!$mv['mv_dots'] && !$mv['items'] && !$editor)) {
            return '';
        }
        try {
            return view('falcon-cms::frontend.motion-visual.visual', [
                'mv' => $mv,
                'where' => $where === 'container' ? 'container' : 'column',
                'editor' => $editor,
                'uid' => 'fmv-'.substr(md5(serialize($mv).$where.random_int(0, PHP_INT_MAX)), 0, 8),
            ])->render();
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    /** What the builder's panel needs. */
    public static function editorConfig(): array
    {
        return [
            'shared' => self::SHARED,
            'animations' => self::ANIMATIONS,
            'fonts' => array_keys(self::FONTS),
            // Uploaded fonts (Customizer → Typography → Custom Font), by family name.
            'customFonts' => array_column(CustomFonts::catalog(), 'family'),
            'textStyles' => self::TEXT_STYLES,
            'siteTitle' => (string) get_cms_option('site_title', ''),
            'maxItems' => self::MAX_ITEMS,
            'presets' => array_map(function ($p) {
                $s = $p['settings'];
                $s['mv_items'] = self::encodeItems($s['items'] ?? []);
                unset($s['items']);

                return ['name' => $p['name'], 'description' => $p['description'], 'settings' => $s];
            }, Presets::all()),
            'shapes' => array_map(fn ($s) => ['name' => $s['name'], 'svg' => $s['svg']], Shapes::all()),
            'themePrimary' => self::color((string) get_cms_option('theme_primary_color', '#0091ea')) ?: '#005ab4',
        ];
    }

    /**
     * The script tag, for a page that has a visual. Printed once at the end of the page by
     * the footer hook, which can see everything drawn above it.
     */
    public static function assetsForPage(string $pageHtml): string
    {
        if (!str_contains($pageHtml, 'data-falcon-mv=')) {
            return '';
        }
        $file = public_path('vendor/falcon-cms/js/motion-visual.js');
        $v = is_file($file) ? (string) filemtime($file) : '1';

        return '<script src="'.e(asset('vendor/falcon-cms/js/motion-visual.js')).'?v='.$v.'" defer></script>';
    }

    /** A switch stored as false, or as what a shortcode round trip makes of false. */
    private static function off($v): bool
    {
        return $v === false || $v === 0 || $v === '0' || $v === 'false';
    }

    /** "primary" / "accent" name the visual's two colors; otherwise a hex color or nothing. */
    private static function paint(string $c, array $mv): string
    {
        $c = trim($c);
        if ($c === 'primary' || $c === 'accent') {
            return $mv['mv_'.$c];
        }
        if ($c === self::RANDOM) {
            return self::RANDOM;
        }

        return self::color($c);
    }

    /** #abc, #aabbcc or #aabbccdd; anything else is no color. */
    private static function color(string $c): string
    {
        $c = trim($c);

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c) ? strtolower($c) : '';
    }

    /** A site path or an http(s) address; anything else (javascript:, data:) is dropped. */
    private static function imageUrl(string $u): string
    {
        $u = trim($u);

        return ($u !== '' && (preg_match('#^https?://#i', $u) || ($u[0] === '/' && !str_starts_with($u, '//'))) && !preg_match('/["\'<>\s]/', $u)) ? $u : '';
    }
}
