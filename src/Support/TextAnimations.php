<?php

namespace FalconCms\Core\Support;

/**
 * Looping text animations for the builder's text-bearing elements — one source for
 * both renderers.
 *
 * The builder draws every element twice: the Vue canvas in the admin and the Blade
 * template on the front end. A mode table duplicated between them drifts, so it
 * lives here once and the canvas receives the same arrays through @json().
 *
 * Every mode is pure CSS. A class picks the keyframes (`.fa-tanim-<mode>`) and four
 * custom properties carry the author's timing (`--fa-tanim-dur|delay|iter|ease`);
 * the colour modes read three more (`--fa-tanim-base|accent|mid|g1..g3`). The
 * stylesheet defining them is
 * resources/views/components/frontend/text-anim-styles.blade.php, included by both
 * renderers, so a mode can never animate one way in the canvas and another way on
 * the published page.
 *
 * Settings are shared across elements — `textAnim`, `textAnimTrigger`,
 * `textAnimDuration`, `textAnimDelay`, `textAnimIteration`, `textAnimEasing`,
 * `textAnimColor` — and serialise to `text_anim*` shortcode attributes. What differs
 * per element is only where its text colour lives and which modes make sense on it;
 * that is the ELEMENTS table below.
 */
class TextAnimations
{
    /**
     * mode => [label, duration (ms), easing, accent (reads --fa-tanim-accent?),
     *          clip (paints the glyphs with a clipped background?)]
     *
     * Durations are the resting speed each motion reads best at — a shake wants
     * under a second, a gradient wants four. They are defaults only: the author's
     * `textAnimDuration` always wins.
     *
     * `clip` marks the modes that swap the element's `color` for a background clipped
     * to the glyphs. `paint` is the wider set: every mode that repaints the letters
     * rather than moving the box (the clipping three, plus Colour Cycle).
     *
     * A paint mode must land on a node that is nothing but text. Put one on a Button
     * and it clips the button's own fill down to the letters, erasing it (measured:
     * 8,552 filled pixels to 0). So elements that carry a background of their own
     * declare `textNode` below, and the paint modes go to the inner label instead of
     * the box, while the motion modes still animate the whole thing.
     */
    public const MODES = [
        'swing' => ['label' => 'Swing', 'duration' => 2000, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'float' => ['label' => 'Float', 'duration' => 3000, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'bounce' => ['label' => 'Bounce', 'duration' => 2000, 'easing' => 'ease', 'accent' => false, 'clip' => false, 'paint' => false],
        'flip' => ['label' => '3D Flip', 'duration' => 2500, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'slide-reveal' => ['label' => 'Slide Reveal', 'duration' => 3500, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],

        'pulse' => ['label' => 'Pulse', 'duration' => 2000, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'heartbeat' => ['label' => 'Heartbeat', 'duration' => 1500, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'tada' => ['label' => 'Tada', 'duration' => 1500, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'jello' => ['label' => 'Jello', 'duration' => 1500, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'wobble' => ['label' => 'Wobble', 'duration' => 2000, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],
        'shake' => ['label' => 'Shake', 'duration' => 900, 'easing' => 'ease-in-out', 'accent' => false, 'clip' => false, 'paint' => false],

        'glow' => ['label' => 'Neon Glow', 'duration' => 2000, 'easing' => 'ease-in-out', 'accent' => true, 'clip' => false, 'paint' => false],
        'shine' => ['label' => 'Shine Sweep', 'duration' => 3000, 'easing' => 'linear', 'accent' => true, 'clip' => true, 'paint' => true],
        'shimmer' => ['label' => 'Shimmer', 'duration' => 4500, 'easing' => 'linear', 'accent' => true, 'clip' => true, 'paint' => true],
        'gradient-flow' => ['label' => 'Gradient Flow', 'duration' => 4000, 'easing' => 'linear', 'accent' => true, 'clip' => true, 'paint' => true],
        'color-cycle' => ['label' => 'Color Cycle', 'duration' => 6000, 'easing' => 'linear', 'accent' => false, 'clip' => false, 'paint' => true],
    ];

    /** Select groups, in the order the Extra tab shows them. */
    public const GROUPS = [
        'Motion' => ['swing', 'float', 'bounce', 'flip', 'slide-reveal'],
        'Emphasis' => ['pulse', 'heartbeat', 'tada', 'jello', 'wobble', 'shake'],
        'Light & Color' => ['glow', 'shine', 'shimmer', 'gradient-flow', 'color-cycle'],
    ];

    /**
     * The elements that offer the section, and what each needs from its settings.
     *
     * - colorKeys: where that element keeps its text colour, most specific first.
     *   Only the colour modes read it, to build their gradient from the real ink.
     * - gradient:  the element has a Gradient Text option whose start colour should
     *   stand in as the base (only the Title has one today).
     * - shrink:    'auto' shrink-wraps the box so a transform pivots around the text
     *   rather than the whole column — right for a heading, wrong for a paragraph
     *   block or a full-width button, which is why it is opt-in.
     * - textNode:  the element has a background of its own and a separate text node
     *   inside it. The paint modes go to that node (so the letters shimmer and the
     *   button or card keeps its fill); the motion modes still animate the whole box.
     *   Elements that ARE text — a heading, a paragraph block — leave this false and
     *   take everything on one node.
     */
    public const ELEMENTS = [
        'title' => [
            'colorKeys' => ['titleColor'],
            'gradient' => true,
            'shrink' => 'auto',
            'textNode' => false,
        ],
        'text_block' => [
            'colorKeys' => ['color'],
            'gradient' => false,
            'shrink' => false,
            'textNode' => false,
        ],
        'button' => [
            'colorKeys' => ['customTextColor', 'color'],
            'gradient' => false,
            'shrink' => false,
            // The label span; the anchor keeps its fill, border and hover colours.
            'textNode' => true,
        ],
        'callout' => [
            'colorKeys' => ['titleColor', 'accent'],
            'gradient' => false,
            'shrink' => false,
            // The card's title. Not the body: that is prose with links, code chips and
            // buttons of its own, all carrying colours a clipped gradient would fight.
            'textNode' => true,
        ],
    ];

    /** Easing options offered alongside the modes. */
    public const EASINGS = [
        'ease-in-out' => 'Ease In Out',
        'ease' => 'Ease',
        'ease-in' => 'Ease In',
        'ease-out' => 'Ease Out',
        'linear' => 'Linear',
        'cubic-bezier(0.68, -0.55, 0.27, 1.55)' => 'Back',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function modes(): array
    {
        return self::MODES;
    }

    /** @return array<string, array<string, mixed>> */
    public static function elements(): array
    {
        return self::ELEMENTS;
    }

    /** @return array<string, string> */
    public static function easings(): array
    {
        return self::EASINGS;
    }

    public static function supports(?string $type): bool
    {
        return $type !== null && isset(self::ELEMENTS[$type]);
    }

    public static function isMode(?string $mode): bool
    {
        return $mode !== null && $mode !== '' && isset(self::MODES[$mode]);
    }

    /** True when $mode is offered on $type — unknown types and modes are never offered. */
    public static function isModeFor(?string $type, ?string $mode): bool
    {
        return self::supports($type) && self::isMode($mode);
    }

    /**
     * Which node of the element a mode belongs on: 'text' for a paint mode on an
     * element that has a separate label inside a background, 'box' for everything
     * else. Templates ask for one node at a time, so each gets only its own mode.
     */
    public static function nodeFor(?string $type, ?string $mode): string
    {
        if (!self::isModeFor($type, $mode)) {
            return 'box';
        }

        return (self::ELEMENTS[$type]['textNode'] && self::MODES[$mode]['paint']) ? 'text' : 'box';
    }

    /**
     * The picker's groups for one element. Every element offers every mode now — the
     * paint modes simply land on the label rather than the box — but the method stays
     * so a future element can narrow the list without touching the panel.
     *
     * @return array<string, array<int, string>>
     */
    public static function groupsFor(?string $type): array
    {
        return self::supports($type) ? self::GROUPS : [];
    }

    /** @return array<string, array<string, array<int, string>>> groupsFor() for every element */
    public static function groupsByElement(): array
    {
        $out = [];
        foreach (array_keys(self::ELEMENTS) as $type) {
            $out[$type] = self::groupsFor($type);
        }

        return $out;
    }

    /** True when the element's selected mode paints its glyphs with a clipped background. */
    public static function clipsFor(?string $type, array $s): bool
    {
        $mode = is_string($s['textAnim'] ?? null) ? $s['textAnim'] : '';

        return self::isModeFor($type, $mode) && self::MODES[$mode]['clip'];
    }

    /**
     * True when anything anywhere in a builder layout selects a text animation.
     *
     * The stylesheet is emitted from frontend/builder/render.blade.php rather than
     * from the elements themselves, and cannot be @once-guarded:
     * _lazy_layout_post_context() pre-renders a post's content to build
     * $postContent/$postExcerpt, and that throwaway pass would otherwise consume the
     * guard and leave the visible render with the classes but no keyframes. So the
     * emitter asks this instead — the CSS ships only for a layout that uses it.
     *
     * The walk is deliberately structure-agnostic (containers, columns, nested rows,
     * card layouts) and stops at the first hit.
     *
     * @param  mixed  $node
     */
    public static function layoutHasAnimation($node): bool
    {
        if (!is_array($node)) {
            return false;
        }
        foreach ($node as $key => $value) {
            if ($key === 'textAnim' && is_string($value) && self::isMode($value)) {
                return true;
            }
            if (is_array($value) && self::layoutHasAnimation($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn one element's settings into the classes and custom properties the
     * stylesheet reads.
     *
     * Returns null when the element offers no animation, none is selected, or the
     * selected one belongs on the element's other node — so a caller can skip the
     * extra attributes entirely.
     *
     * @param  array<string, mixed>  $s  the element's settings
     * @param  string  $node  'box' (the element itself) or 'text' (its inner label)
     * @return array{classes: string, vars: array<string, string>}|null
     */
    public static function resolveFor(?string $type, array $s, string $node = 'box'): ?array
    {
        $mode = is_string($s['textAnim'] ?? null) ? $s['textAnim'] : '';
        if (!self::isModeFor($type, $mode) || self::nodeFor($type, $mode) !== $node) {
            return null;
        }
        $def = self::MODES[$mode];
        $el = self::ELEMENTS[$type];

        $classes = ['fa-tanim', 'fa-tanim-'.$mode];

        // A transform needs a shrink-to-fit box to pivot around, but an ellipsis needs
        // the full column width to clip against — so only shrink when that is safe.
        if ($el['shrink'] === 'auto') {
            $overflow = $s['textOverflow'] ?? 'initial';
            if ($overflow !== 'ellipsis' && $overflow !== 'clip') {
                $classes[] = 'fa-tanim-ib';
            }
        }
        if (($s['textAnimTrigger'] ?? 'always') === 'hover') {
            $classes[] = 'fa-tanim-hover';
        }

        $iter = $s['textAnimIteration'] ?? 'infinite';
        $iter = ($iter === 'infinite' || $iter === '' || $iter === null)
            ? 'infinite'
            : (string) max(1, (int) $iter);

        $ease = (is_string($s['textAnimEasing'] ?? null) && $s['textAnimEasing'] !== '')
            ? $s['textAnimEasing']
            : $def['easing'];

        $vars = [
            // A duration under 50ms is indistinguishable from no animation at all, so
            // a hand-edited shortcode cannot silently switch the motion off.
            '--fa-tanim-dur' => max(50, self::posInt($s['textAnimDuration'] ?? null, $def['duration'])).'ms',
            '--fa-tanim-delay' => self::posInt($s['textAnimDelay'] ?? null, 0).'ms',
            '--fa-tanim-iter' => $iter,
            '--fa-tanim-ease' => self::cssToken($ease) ?? $def['easing'],
        ];

        if ($def['accent']) {
            $base = self::baseColor($el, $s);
            $accent = self::cssToken($s['textAnimColor'] ?? null) ?? self::accentFallback($mode, $s);

            $vars['--fa-tanim-base'] = $base;
            $vars['--fa-tanim-accent'] = $accent;

            if ($mode === 'shimmer') {
                // The hero sheen on falconcms.com ramps base → mid → peak → mid → base.
                // Blended here rather than with CSS color-mix(): an engine without it
                // would invalidate the whole gradient and leave transparent glyphs.
                $vars['--fa-tanim-mid'] = self::mixHex($base, $accent, 0.55) ?? $accent;
            }

            if ($mode === 'gradient-flow') {
                $vars['--fa-tanim-g1'] = $base;
                $vars['--fa-tanim-g2'] = $accent;
                $vars['--fa-tanim-g3'] = self::cssToken($s['gradientEndColor'] ?? null) ?? $base;
            }
        }

        return ['classes' => implode(' ', $classes), 'vars' => $vars];
    }

    /** The element's own ink, which the colour modes build their gradient from. */
    private static function baseColor(array $el, array $s): string
    {
        if ($el['gradient'] && !empty($s['useGradient'])) {
            $start = self::cssToken($s['gradientStartColor'] ?? null);
            if ($start !== null) {
                return $start;
            }
        }
        foreach ($el['colorKeys'] as $key) {
            $c = self::cssToken($s[$key] ?? null);
            if ($c !== null) {
                return $c;
            }
        }

        return '#222222';
    }

    /** The accent each colour mode reads best with when the author has not picked one. */
    private static function accentFallback(string $mode, array $s): string
    {
        if ($mode === 'shine') {
            return '#ffffff';
        }
        if ($mode === 'shimmer') {
            return '#ffd9a0';   // the warm highlight the falconcms.com hero sweeps with
        }

        return self::cssToken($s['gradientEndColor'] ?? null) ?? '#0091ea';
    }

    /**
     * Blend two colours, $weight of the way from $a to $b.
     *
     * Returns null unless both are plain hex, which is what the colour picker writes;
     * anything else (a named colour, rgba(), a custom property) simply skips the blend.
     */
    private static function mixHex(string $a, string $b, float $weight): ?string
    {
        $ca = self::hexToRgb($a);
        $cb = self::hexToRgb($b);
        if ($ca === null || $cb === null) {
            return null;
        }

        return sprintf(
            '#%02x%02x%02x',
            (int) round($ca[0] + ($cb[0] - $ca[0]) * $weight),
            (int) round($ca[1] + ($cb[1] - $ca[1]) * $weight),
            (int) round($ca[2] + ($cb[2] - $ca[2]) * $weight)
        );
    }

    /** @return array{int, int, int}|null */
    private static function hexToRgb(string $v): ?array
    {
        $h = ltrim(trim($v), '#');
        if (strlen($h) === 3 && ctype_xdigit($h)) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (strlen($h) !== 6 || !ctype_xdigit($h)) {
            return null;
        }

        return [(int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2))];
    }

    /** A CSS value safe to place in a style attribute, or null when empty or quote-bearing. */
    private static function cssToken($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || strpbrk($v, "\"'<>;{}") !== false) {
            return null;
        }

        return $v;
    }

    private static function posInt($v, int $fallback): int
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return $fallback;
        }

        return max(0, (int) $v);
    }
}
