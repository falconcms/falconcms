<?php

namespace FalconCms\Core\Support;

/**
 * Small, safe CSS pieces for builder elements that write their own scoped <style>
 * (Countdown, Social Share): a typography block, a colour, a margin per device.
 *
 * Every value an author typed passes through here before it reaches a stylesheet, so
 * none of them can end the declaration, the rule or the <style> element.
 */
final class ElementStyle
{
    /** The shared typography control's six settings under $prefix, as declarations. */
    public static function typo(array $s, string $prefix): string
    {
        $clean = [];
        foreach (['family', 'weight', 'size', 'line_height', 'letter_spacing', 'transform'] as $key) {
            $raw = $s[$prefix.'_'.$key] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            // A family keeps its quotes (falcon_css_font_family makes it safe); everything
            // else loses anything that could end the declaration. Typography::css then adds
            // px to a bare font size or letter spacing, and leaves weight and line height
            // (a ratio) as numbers.
            $clean[$prefix.'_'.$key] = $key === 'family'
                ? falcon_css_font_family((string) $raw)
                : DualButton::cssValue($raw);
        }
        $css = Typography::css($clean, $prefix);

        return $css === '' ? '' : rtrim($css, '; ').';';
    }

    /** A colour an author typed, or $fallback when it is empty. */
    public static function color($value, string $fallback = ''): string
    {
        $value = DualButton::cssValue($value);

        return $value !== '' ? $value : $fallback;
    }

    /** A setting as a whole number held between $min and $max. */
    public static function int($value, int $default, int $min, int $max): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    /**
     * Margin declarations from a Dimensions field ({key}_top, {key}_top_unit …) for one
     * device: '' is desktop, '_tablet' and '_mobile' the overrides. A side left empty
     * writes nothing, so it keeps what the larger screen had.
     */
    public static function margin(array $s, string $suffix = '', string $key = 'margin'): string
    {
        $css = '';
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $v = $s[$key.'_'.$side.$suffix] ?? '';
            if ($v === '' || $v === null) {
                continue;
            }
            $unit = DualButton::unit($s[$key.'_'.$side.'_unit'.$suffix] ?? ($s[$key.'_'.$side.'_unit'] ?? 'px'));
            $value = is_numeric($v) ? ($v + 0).$unit : DualButton::cssValue($v);
            if ($value !== '') {
                $css .= 'margin-'.$side.':'.$value.';';
            }
        }

        return $css;
    }

    /** One of $allowed, or $default. */
    public static function pick($value, array $allowed, string $default): string
    {
        $value = (string) $value;

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /** A responsive setting for the device the canvas previews ($device null = desktop). */
    public static function forDevice(array $s, string $key, ?string $device, $default = null)
    {
        $order = $device === 'mobile' ? [$key.'_mobile', $key.'_tablet', $key]
            : ($device === 'tablet' ? [$key.'_tablet', $key] : [$key]);
        foreach ($order as $k) {
            if (isset($s[$k]) && $s[$k] !== '') {
                return $s[$k];
            }
        }

        return $default;
    }

    /** The theme's Small and Medium Screen breakpoints, [mobile ends, tablet ends]. */
    public static function breakpoints(): array
    {
        return [
            (int) get_cms_option('theme_small_screen_breakpoint', '800'),
            (int) get_cms_option('theme_medium_screen_breakpoint', '1100'),
        ];
    }

    /** The element's settings with blanks filled from its field defaults. */
    public static function get(array $s, string $key, $default)
    {
        return (isset($s[$key]) && $s[$key] !== '' && $s[$key] !== null) ? $s[$key] : $default;
    }
}
