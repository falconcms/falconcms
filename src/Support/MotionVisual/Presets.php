<?php

namespace FalconCms\Core\Support\MotionVisual;

/**
 * Starting points for a Motion Visual. Picking one in the panel copies its settings in;
 * from there everything is the user's to change, so a preset is only ever a first draft.
 *
 * Plugins and themes add more with the falcon_motion_visual_presets filter
 * (key => ['name' => …, 'description' => …, 'settings' => [mv_* => …, 'items' => [...]]]).
 * Colors left empty follow the visual's main and accent colors.
 */
final class Presets
{
    /** @var array<string, array>|null */
    private static ?array $all = null;

    /** @return array<string, array{name: string, description: string, settings: array}> */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $presets = self::core();
        if (function_exists('apply_falcon_filters')) {
            foreach ((array) apply_falcon_filters('falcon_motion_visual_presets', $presets) as $key => $p) {
                if (is_string($key) && preg_match('/^[a-z0-9-]+$/', $key) && is_array($p)) {
                    $presets[$key] = ['name' => (string) ($p['name'] ?? $key), 'description' => (string) ($p['description'] ?? ''), 'settings' => (array) ($p['settings'] ?? [])];
                }
            }
        }

        return self::$all = $presets;
    }

    public static function flush(): void
    {
        self::$all = null;
    }

    private static function core(): array
    {
        $item = fn (string $shape, float $x, float $y, float $size, string $anim, array $more = []) => $more + [
            'kind' => 'shape', 'shape' => $shape, 'x' => $x, 'y' => $y, 'size' => $size, 'anim' => $anim,
        ];

        // Big Word: the site's name, sized so the whole word fits across the layer whatever its
        // length (an upper-case letter of this weight is about 0.72 of the font size wide).
        $word = trim((string) get_cms_option('site_title', '')) ?: 'Hello';
        $wordSize = round(min(24, 88 / (max(3, mb_strlen($word)) * 0.72)), 1);

        return [
            'floating-dots' => [
                'name' => 'Floating Dots',
                'description' => 'Small dots drifting across the whole area; they step aside for the mouse.',
                'settings' => ['mv_dots' => true, 'mv_dots_count' => 70, 'mv_dots_size' => 3, 'mv_dots_links' => false, 'mv_dots_mouse' => 'repel', 'items' => []],
            ],
            'connected-dots' => [
                'name' => 'Connected Dots',
                'description' => 'A slow network: dots joined by fine lines when they come near each other.',
                'settings' => ['mv_dots' => true, 'mv_dots_count' => 55, 'mv_dots_size' => 2.5, 'mv_dots_links' => true, 'mv_dots_mouse' => 'attract', 'items' => []],
            ],
            'soft-bubbles' => [
                'name' => 'Soft Bubbles',
                'description' => 'Large blurred circles floating slowly in the main and accent colors.',
                'settings' => ['mv_dots' => false, 'items' => [
                    $item('circle', 12, 22, 22, 'float', ['opacity' => 22, 'blur' => 18, 'speed' => 9, 'depth' => 0.6]),
                    $item('circle', 82, 18, 14, 'drift', ['opacity' => 26, 'blur' => 12, 'speed' => 12, 'color' => 'accent', 'depth' => 1.2]),
                    $item('circle', 70, 78, 26, 'float', ['opacity' => 18, 'blur' => 22, 'speed' => 11, 'delay' => 2, 'depth' => 0.4]),
                    $item('circle', 30, 82, 10, 'drift', ['opacity' => 24, 'blur' => 8, 'speed' => 8, 'color' => 'accent', 'delay' => 1, 'depth' => 1.6]),
                    $item('circle', 50, 45, 6, 'pulse', ['opacity' => 30, 'blur' => 4, 'speed' => 6, 'depth' => 2]),
                ]],
            ],
            'geometric' => [
                'name' => 'Geometric',
                'description' => 'Outlined triangles, squares, rings and plus signs, turning and floating.',
                'settings' => ['mv_dots' => false, 'items' => [
                    $item('triangle-outline', 8, 18, 6, 'rotate', ['opacity' => 55, 'speed' => 18, 'depth' => 1.2]),
                    $item('ring', 88, 14, 5, 'float', ['opacity' => 55, 'speed' => 7, 'color' => 'accent', 'depth' => 1.8]),
                    $item('plus', 20, 80, 3.5, 'rotate', ['opacity' => 60, 'speed' => 14, 'depth' => 2]),
                    $item('square-outline', 78, 72, 5.5, 'swing', ['opacity' => 50, 'speed' => 8, 'rot' => 15, 'depth' => 1]),
                    $item('cross', 55, 10, 2.5, 'float', ['opacity' => 50, 'speed' => 6, 'color' => 'accent', 'delay' => 1.5, 'depth' => 2.4]),
                    $item('dot-grid', 93, 48, 6, 'drift', ['opacity' => 35, 'speed' => 12, 'depth' => 0.8]),
                    $item('wave', 4, 52, 7, 'drift', ['opacity' => 45, 'speed' => 10, 'color' => 'accent', 'depth' => 1.4]),
                ]],
            ],
            'blobs' => [
                'name' => 'Blobs',
                'description' => 'Two or three soft organic shapes, slowly turning behind the content.',
                'settings' => ['mv_dots' => false, 'items' => [
                    $item('blob', 15, 30, 34, 'rotate', ['opacity' => 14, 'blur' => 30, 'speed' => 40, 'depth' => 0.5]),
                    $item('blob-2', 85, 70, 38, 'rotate', ['opacity' => 14, 'blur' => 34, 'speed' => 50, 'color' => 'accent', 'depth' => 0.8]),
                    $item('blob', 60, 15, 18, 'drift', ['opacity' => 12, 'blur' => 24, 'speed' => 16, 'depth' => 1.2]),
                ]],
            ],
            'big-word' => [
                'name' => 'Big Word',
                'description' => 'The site name in huge outlined letters, drifting faintly behind the content.',
                'settings' => ['mv_dots' => false, 'items' => [
                    ['kind' => 'text', 'text' => $word, 'style' => 'outline', 'font' => 'sans', 'weight' => 900,
                        'upper' => true, 'x' => 50, 'y' => 55, 'size' => $wordSize, 'anim' => 'drift', 'speed' => 22, 'dist' => 18, 'opacity' => 18, 'depth' => 0.6],
                    $item('circle', 82, 24, 9, 'float', ['opacity' => 20, 'blur' => 10, 'speed' => 9, 'color' => 'accent', 'depth' => 1.4]),
                ]],
            ],
            'custom' => [
                'name' => 'Custom (empty)',
                'description' => 'Start from nothing: add dots, shapes, text and your own images.',
                'settings' => ['mv_dots' => false, 'items' => []],
            ],
        ];
    }
}
