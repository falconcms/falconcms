<?php

namespace FalconCms\Core\Support;

/**
 * The few Tailwind utilities content most often carries, written as CSS on the server: arbitrary
 * colours and sizes ("hover:text-[#0091ea]", "text-[15px]"), the text-size scale, the spacing
 * scale, alignment, font weight, leading and rounding, each with the sm/md/lg/xl/2xl breakpoints
 * and the hover/focus/active states. Compiled CSS then covers a page with such classes without
 * loading the 400 KB runtime build. Anything else is returned as left over.
 */
final class TailwindLite
{
    private const SCREENS = ['sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280, '2xl' => 1536];

    private const STATES = ['hover' => ':hover', 'focus' => ':focus', 'active' => ':active', 'focus-visible' => ':focus-visible',
        'focus-within' => ':focus-within', 'first' => ':first-child', 'last' => ':last-child', 'disabled' => ':disabled'];

    private const TEXT_SIZES = ['xs' => ['0.75rem', '1rem'], 'sm' => ['0.875rem', '1.25rem'], 'base' => ['1rem', '1.5rem'],
        'lg' => ['1.125rem', '1.75rem'], 'xl' => ['1.25rem', '1.75rem'], '2xl' => ['1.5rem', '2rem'], '3xl' => ['1.875rem', '2.25rem'],
        '4xl' => ['2.25rem', '2.5rem'], '5xl' => ['3rem', '1'], '6xl' => ['3.75rem', '1'], '7xl' => ['4.5rem', '1'],
        '8xl' => ['6rem', '1'], '9xl' => ['8rem', '1']];

    private const WEIGHTS = ['thin' => 100, 'extralight' => 200, 'light' => 300, 'normal' => 400, 'medium' => 500,
        'semibold' => 600, 'bold' => 700, 'extrabold' => 800, 'black' => 900];

    private const LEADING = ['none' => '1', 'tight' => '1.25', 'snug' => '1.375', 'normal' => '1.5', 'relaxed' => '1.625', 'loose' => '2'];

    private const ROUNDED = ['none' => '0px', 'sm' => '0.125rem', '' => '0.25rem', 'md' => '0.375rem', 'lg' => '0.5rem',
        'xl' => '0.75rem', '2xl' => '1rem', '3xl' => '1.5rem', 'full' => '9999px'];

    /** Spacing-like utilities: class prefix => CSS properties. */
    private const SPACING = [
        'm' => ['margin'], 'mx' => ['margin-left', 'margin-right'], 'my' => ['margin-top', 'margin-bottom'],
        'mt' => ['margin-top'], 'mr' => ['margin-right'], 'mb' => ['margin-bottom'], 'ml' => ['margin-left'],
        'ms' => ['margin-inline-start'], 'me' => ['margin-inline-end'],
        'p' => ['padding'], 'px' => ['padding-left', 'padding-right'], 'py' => ['padding-top', 'padding-bottom'],
        'pt' => ['padding-top'], 'pr' => ['padding-right'], 'pb' => ['padding-bottom'], 'pl' => ['padding-left'],
        'ps' => ['padding-inline-start'], 'pe' => ['padding-inline-end'],
        'gap' => ['gap'], 'gap-x' => ['column-gap'], 'gap-y' => ['row-gap'],
        'w' => ['width'], 'h' => ['height'], 'min-w' => ['min-width'], 'min-h' => ['min-height'], 'max-h' => ['max-height'],
        'top' => ['top'], 'right' => ['right'], 'bottom' => ['bottom'], 'left' => ['left'], 'inset' => ['inset'],
    ];

    /**
     * @param  list<string>  $classes
     * @return array{css: string, leftover: list<string>}
     */
    public static function generate(array $classes): array
    {
        $groups = [];   // order => list of rules
        $leftover = [];
        foreach ($classes as $class) {
            $rule = self::rule($class);
            if ($rule === null) {
                $leftover[] = $class;

                continue;
            }
            [$order, $css] = $rule;
            $groups[$order][] = $css;
        }
        ksort($groups);

        return ['css' => implode('', array_merge(...array_values($groups ?: [[]]))), 'leftover' => $leftover];
    }

    /** @return array{0: int, 1: string}|null sort order (states after base, screens last, by width) and the CSS */
    private static function rule(string $class): ?array
    {
        $parts = self::splitVariants($class);
        $base = array_pop($parts);
        $important = str_starts_with($base, '!');
        $base = ltrim($base, '!');

        $declarations = self::declarations($base);
        if ($declarations === null) {
            return null;
        }

        $pseudo = '';
        $media = null;
        $order = 0;
        foreach ($parts as $variant) {
            if (isset(self::SCREENS[$variant]) && $media === null) {
                $media = self::SCREENS[$variant];
                $order = 100 + self::SCREENS[$variant];
            } elseif (isset(self::STATES[$variant])) {
                $pseudo .= self::STATES[$variant];
                $order = max($order, 10);
            } else {
                return null; // dark:, group-hover:, [&>p]: … are the runtime's business
            }
        }

        $body = implode(';', array_map(fn ($d) => $d.($important ? ' !important' : ''), $declarations));
        $css = '.'.self::escape($class).$pseudo.'{'.$body.'}';
        if ($media !== null) {
            $css = '@media (min-width:'.$media.'px){'.$css.'}';
        }

        return [$order, $css];
    }

    /** @return list<string>|null CSS declarations for a utility (no variants), or null when unknown */
    private static function declarations(string $base): ?array
    {
        $negative = str_starts_with($base, '-');
        $name = ltrim($base, '-');

        // Arbitrary values: text-[#0091ea], bg-[rgb(…)], p-[12px], rounded-[10px] …
        if (preg_match('/^([a-z-]+)-\[(.+)\]$/', $name, $m)) {
            $value = str_replace('_', ' ', $m[2]);
            if (preg_match('/[;{}<>]/', $value)) {
                return null;
            }
            $isColor = (bool) preg_match('/^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|var\(--)/i', $value);

            return match (true) {
                $m[1] === 'text' => [$isColor ? 'color:'.$value : 'font-size:'.$value],
                $m[1] === 'bg' && $isColor => ['background-color:'.$value],
                $m[1] === 'border' && $isColor => ['border-color:'.$value],
                $m[1] === 'border' && !$isColor => ['border-width:'.$value],
                $m[1] === 'leading' => ['line-height:'.$value],
                $m[1] === 'tracking' => ['letter-spacing:'.$value],
                $m[1] === 'font' && preg_match('/^\d{3}$/', $value) === 1 => ['font-weight:'.$value],
                $m[1] === 'rounded' => ['border-radius:'.$value],
                $m[1] === 'max-w' => ['max-width:'.$value],
                isset(self::SPACING[$m[1]]) => array_map(fn ($p) => $p.':'.($negative ? 'calc('.$value.' * -1)' : $value), self::SPACING[$m[1]]),
                default => null,
            };
        }

        if (preg_match('/^text-(xs|sm|base|lg|xl|[2-9]xl)$/', $name, $m)) {
            [$size, $leading] = self::TEXT_SIZES[$m[1]];

            return ['font-size:'.$size, 'line-height:'.$leading];
        }
        if (preg_match('/^text-(left|center|right|justify|start|end)$/', $name, $m)) {
            return ['text-align:'.$m[1]];
        }
        if (preg_match('/^font-([a-z]+)$/', $name, $m) && isset(self::WEIGHTS[$m[1]])) {
            return ['font-weight:'.self::WEIGHTS[$m[1]]];
        }
        if (preg_match('/^leading-([a-z]+|\d+)$/', $name, $m)) {
            return isset(self::LEADING[$m[1]]) ? ['line-height:'.self::LEADING[$m[1]]]
                : (ctype_digit($m[1]) ? ['line-height:'.($m[1] * 0.25).'rem'] : null);
        }
        if (preg_match('/^rounded(?:-(none|sm|md|lg|xl|2xl|3xl|full))?$/', $name, $m)) {
            return ['border-radius:'.self::ROUNDED[$m[1] ?? '']];
        }
        if (preg_match('/^opacity-(\d{1,3})$/', $name, $m) && (int) $m[1] <= 100) {
            return ['opacity:'.((int) $m[1] / 100)];
        }

        // The spacing scale: mt-5, px-0.5, w-1/2, h-full, gap-x-4, -mt-2 …
        if (preg_match('/^((?:gap-[xy])|(?:min|max)-[wh]|[a-z]+)-(.+)$/', $name, $m) && isset(self::SPACING[$m[1]])) {
            $value = self::spacingValue($m[1], $m[2]);
            if ($value === null) {
                return null;
            }
            if ($negative) {
                $value = $value === '0px' ? '0px' : '-'.$value;
            }

            return array_map(fn ($p) => $p.':'.$value, self::SPACING[$m[1]]);
        }

        return null;
    }

    private static function spacingValue(string $prefix, string $value): ?string
    {
        if ($value === '0') {
            return '0px';
        }
        if ($value === 'px') {
            return '1px';
        }
        if (preg_match('/^\d+(\.5)?$/', $value) && (float) $value <= 96) {
            return rtrim(rtrim(number_format((float) $value * 0.25, 3, '.', ''), '0'), '.').'rem';
        }
        if ($value === 'auto' && !str_starts_with($prefix, 'p') && !str_starts_with($prefix, 'gap')) {
            return 'auto';
        }
        $sizing = in_array($prefix, ['w', 'h', 'min-w', 'min-h', 'max-h', 'top', 'right', 'bottom', 'left', 'inset'], true);
        if ($sizing && preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $m) && (int) $m[2] > 0) {
            return rtrim(rtrim(number_format((int) $m[1] / (int) $m[2] * 100, 6, '.', ''), '0'), '.').'%';
        }
        if ($sizing && $value === 'full') {
            return '100%';
        }
        if (in_array($prefix, ['w', 'min-w'], true) && $value === 'screen') {
            return '100vw';
        }
        if (in_array($prefix, ['h', 'min-h', 'max-h'], true) && $value === 'screen') {
            return '100vh';
        }

        return null;
    }

    /** "md:hover:text-[#fff]" → ["md", "hover", "text-[#fff]"], colons inside brackets kept. */
    private static function splitVariants(string $class): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        foreach (str_split($class) as $ch) {
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
            }
            if ($ch === ':' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $ch;
        }
        $parts[] = $current;

        return $parts;
    }

    /** A class name as a CSS selector: everything but letters, digits, "-" and "_" escaped. */
    public static function escape(string $class): string
    {
        return preg_replace_callback('/[^A-Za-z0-9_-]/', fn ($m) => '\\'.$m[0], $class) ?? $class;
    }
}
