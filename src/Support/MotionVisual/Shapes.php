<?php

namespace FalconCms\Core\Support\MotionVisual;

/**
 * The shapes a Motion Visual can place: each is the inside of an SVG drawn on a 0–100 square
 * in currentColor, so one color setting paints it whatever it is.
 *
 * Plugins and themes add more with the falcon_motion_visual_shapes filter
 * (key => ['name' => …, 'svg' => …]). The markup is theirs and trusted like a view; what
 * visitors upload never goes through here — an uploaded picture is shown as an <img>.
 */
final class Shapes
{
    /** @var array<string, array{name: string, svg: string}>|null */
    private static ?array $all = null;

    private const CORE = [
        'circle' => ['name' => 'Circle', 'svg' => '<circle cx="50" cy="50" r="50"/>'],
        'ring' => ['name' => 'Ring', 'svg' => '<circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="12"/>'],
        'square' => ['name' => 'Square', 'svg' => '<rect x="4" y="4" width="92" height="92" rx="16"/>'],
        'square-outline' => ['name' => 'Square outline', 'svg' => '<rect x="8" y="8" width="84" height="84" rx="14" fill="none" stroke="currentColor" stroke-width="10"/>'],
        'triangle' => ['name' => 'Triangle', 'svg' => '<path d="M50 6 96 90H4Z" stroke-linejoin="round"/>'],
        'triangle-outline' => ['name' => 'Triangle outline', 'svg' => '<path d="M50 12 90 86H10Z" fill="none" stroke="currentColor" stroke-width="10" stroke-linejoin="round"/>'],
        'diamond' => ['name' => 'Diamond', 'svg' => '<path d="M50 2 98 50 50 98 2 50Z"/>'],
        'plus' => ['name' => 'Plus', 'svg' => '<path d="M38 4h24v34h34v24H62v34H38V62H4V38h34Z"/>'],
        'cross' => ['name' => 'Cross', 'svg' => '<path d="M21 4 50 33 79 4l17 17-29 29 29 29-17 17-29-29-29 29L4 79l29-29L4 21Z"/>'],
        'star' => ['name' => 'Star', 'svg' => '<path d="m50 3 13.6 31.3 34 3.2-25.6 22.6 7.5 33.3L50 76 20.5 93.4 28 60.1 2.4 37.5l34-3.2Z"/>'],
        'hexagon' => ['name' => 'Hexagon', 'svg' => '<path d="M50 3 91 26.5v47L50 97 9 73.5v-47Z"/>'],
        'half-circle' => ['name' => 'Half circle', 'svg' => '<path d="M2 50a48 48 0 0 1 96 0Z"/>'],
        'arc' => ['name' => 'Arc', 'svg' => '<path d="M10 70a40 40 0 0 1 80 0" fill="none" stroke="currentColor" stroke-width="12" stroke-linecap="round"/>'],
        'wave' => ['name' => 'Wave', 'svg' => '<path d="M4 50c8-16 15-16 23 0s15 16 23 0 15-16 23 0 15 16 23 0" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"/>'],
        'zigzag' => ['name' => 'Zigzag', 'svg' => '<path d="m4 62 15-24 15 24 16-24 15 24 16-24 15 24" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>'],
        'dot-grid' => ['name' => 'Dot grid', 'svg' => '<g><circle cx="14" cy="14" r="7"/><circle cx="50" cy="14" r="7"/><circle cx="86" cy="14" r="7"/><circle cx="14" cy="50" r="7"/><circle cx="50" cy="50" r="7"/><circle cx="86" cy="50" r="7"/><circle cx="14" cy="86" r="7"/><circle cx="50" cy="86" r="7"/><circle cx="86" cy="86" r="7"/></g>'],
        'blob' => ['name' => 'Blob', 'svg' => '<path d="M74.5 14.5c11 9 19 24 19 38.5s-6.5 29-19 36.5S42 99 29 92.5 4.5 72 4 56 12.5 23 26 13.5 63.5 5.5 74.5 14.5Z"/>'],
        'blob-2' => ['name' => 'Blob 2', 'svg' => '<path d="M83 22c10 13 14 31 7 46S67 95 49 96 13 87 6 70s1-37 13-51S40-1 55 2s18 7 28 20Z"/>'],
        'squiggle' => ['name' => 'Squiggle', 'svg' => '<path d="M12 80c0-20 26-20 26-40S12 20 12 10M50 90c0-20 26-20 26-40s-26-20-26-40" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"/>'],
        'heart' => ['name' => 'Heart', 'svg' => '<path d="M50 90C20 68 4 52 4 32 4 17 15 7 28 7c10 0 18 6 22 14 4-8 12-14 22-14 13 0 24 10 24 25 0 20-16 36-46 58Z"/>'],
        'sparkle' => ['name' => 'Sparkle', 'svg' => '<path d="M50 0c4 34 16 46 50 50-34 4-46 16-50 50-4-34-16-46-50-50 34-4 46-16 50-50Z"/>'],
        'burst' => ['name' => 'Burst', 'svg' => '<path d="M50 1 59.8 13.3 74.5 7.6 76.9 23.1 92.4 25.5 86.7 40.2 99 50 86.7 59.8 92.4 74.5 76.9 76.9 74.5 92.4 59.8 86.7 50 99 40.2 86.7 25.5 92.4 23.1 76.9 7.6 74.5 13.3 59.8 1 50 13.3 40.2 7.6 25.5 23.1 23.1 25.5 7.6 40.2 13.3Z"/>'],
        'cloud' => ['name' => 'Cloud', 'svg' => '<path d="M25 80a20 20 0 0 1 0-40 28 28 0 0 1 53-6 22 22 0 0 1-3 46Z"/>'],
        'moon' => ['name' => 'Moon', 'svg' => '<path d="M60 4a46 46 0 1 0 36 70A38 38 0 0 1 60 4Z"/>'],
        'sun' => ['name' => 'Sun', 'svg' => '<g><circle cx="50" cy="50" r="22"/><path d="M50 4v13M50 83v13M4 50h13M83 50h13M17.5 17.5l9 9M73.5 73.5l9 9M17.5 82.5l9-9M73.5 26.5l9-9" stroke="currentColor" stroke-width="7" stroke-linecap="round"/></g>'],
        'bolt' => ['name' => 'Lightning', 'svg' => '<path d="M58 2 14 58h30l-8 40 50-60H56Z" stroke-linejoin="round"/>'],
        'leaf' => ['name' => 'Leaf', 'svg' => '<path d="M8 92C8 42 38 10 92 8 90 60 58 92 8 92Z"/>'],
        'flower' => ['name' => 'Flower', 'svg' => '<g><circle cx="50" cy="27" r="23"/><circle cx="73" cy="50" r="23"/><circle cx="50" cy="73" r="23"/><circle cx="27" cy="50" r="23"/></g>'],
        'drop' => ['name' => 'Drop', 'svg' => '<path d="M50 3S16 44 16 64a34 34 0 0 0 68 0C84 44 50 3 50 3Z"/>'],
        'pill' => ['name' => 'Pill', 'svg' => '<rect x="3" y="30" width="94" height="40" rx="20"/>'],
        'pentagon' => ['name' => 'Pentagon', 'svg' => '<path d="M50 2 95.7 35.2 78.2 88.8H21.8L4.3 35.2Z"/>'],
        'octagon' => ['name' => 'Octagon', 'svg' => '<path d="M30 2h40l28 28v40L70 98H30L2 70V30Z"/>'],
        'quarter' => ['name' => 'Quarter circle', 'svg' => '<path d="M4 96V4a92 92 0 0 1 92 92Z"/>'],
        'ring-dashed' => ['name' => 'Dashed ring', 'svg' => '<circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="8" stroke-dasharray="14 10" stroke-linecap="round"/>'],
        // An Archimedean spiral, 3.2 turns, sampled as one smooth polyline.
        'spiral' => ['name' => 'Spiral', 'svg' => '<path d="M52 50L52.4 50.5 52.6 51.1 52.7 51.8 52.6 52.7 52.2 53.5 51.6 54.2 50.8 54.9 49.8 55.4 48.6 55.6 47.4 55.6 46 55.3 44.7 54.7 43.6 53.8 42.5 52.5 41.8 51 41.3 49.3 41.2 47.5 41.5 45.6 42.2 43.7 43.4 42 44.9 40.5 46.8 39.2 49 38.4 51.4 38 53.9 38.1 56.4 38.8 58.8 39.9 60.9 41.6 62.8 43.8 64.1 46.4 65 49.2 65.3 52.3 64.9 55.5 63.9 58.5 62.2 61.4 59.9 64 57.1 66.1 53.8 67.6 50.2 68.4 46.5 68.5 42.7 67.8 39.1 66.3 35.7 64.1 32.8 61.2 30.6 57.7 29 53.7 28.3 49.5 28.4 45 29.4 40.6 31.4 36.5 34.2 32.7 37.7 29.6 41.9 27.1 46.6 25.6 51.6 24.9 56.7 25.4 61.6 26.8 66.3 29.3 70.4 32.7 73.8 36.9 76.4 41.8 77.9 47.2 78.3 52.9 77.6 58.6 75.7 64.1 72.7 69.2 68.6 73.7 63.7 77.3 58.1 79.9 52 81.3 45.6 81.5 39.3 80.4 33.2 78 27.7 74.4 22.9 69.7 19.1 64.1 16.5 57.7 15.3 50.9 15.4 43.8 16.9 36.9 19.8 30.3 24.1 24.4 29.5 19.4 35.8 15.5 42.9 13 50.5 11.9 58.2 12.3 65.7 14.3 72.7 17.9 79 22.8 84.2 28.9 88.2 36 90.6 43.8 91.4 52.1 90.6 60.4 88.1 68.5 83.9 76 78.3 82.6 71.5 87.9 63.6 91.8" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'],
        'infinity' => ['name' => 'Infinity', 'svg' => '<path d="M50 50C40 36 30 28 20 28a22 22 0 0 0 0 44c10 0 20-8 30-22s20-22 30-22a22 22 0 0 1 0 44c-10 0-20-8-30-22Z" fill="none" stroke="currentColor" stroke-width="9"/>'],
        'chevron' => ['name' => 'Chevron', 'svg' => '<path d="M14 32 50 68 86 32" fill="none" stroke="currentColor" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>'],
        'arrow' => ['name' => 'Arrow', 'svg' => '<path d="M10 50h74M58 22l28 28-28 28" fill="none" stroke="currentColor" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>'],
        'cube' => ['name' => 'Cube', 'svg' => '<g fill="none" stroke="currentColor" stroke-width="6" stroke-linejoin="round"><path d="M50 6 90 28v44L50 94 10 72V28Z"/><path d="M10 28 50 50 90 28M50 50v44"/></g>'],
    ];

    /** @return array<string, array{name: string, svg: string}> */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $shapes = self::CORE;
        if (function_exists('apply_falcon_filters')) {
            foreach ((array) apply_falcon_filters('falcon_motion_visual_shapes', $shapes) as $key => $shape) {
                if (is_string($key) && preg_match('/^[a-z0-9-]+$/', $key) && is_array($shape) && !empty($shape['svg'])) {
                    $shapes[$key] = ['name' => (string) ($shape['name'] ?? $key), 'svg' => (string) $shape['svg']];
                }
            }
        }

        return self::$all = $shapes;
    }

    public static function flush(): void
    {
        self::$all = null;
    }

    public static function svg(string $key): string
    {
        return self::all()[$key]['svg'] ?? self::CORE['circle']['svg'];
    }
}
