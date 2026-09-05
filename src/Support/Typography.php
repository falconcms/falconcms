<?php

namespace FalconCms\Core\Support;

/**
 * The shared typography control, turned into CSS.
 *
 * The Design tab's typography block writes six settings under a prefix —
 * {prefix}_family, _weight, _size, _line_height, _letter_spacing, _transform — and
 * every element that offers it has to turn those into declarations the same way.
 * Written out per element they drift, and they did: a bare number typed into Letter
 * Spacing came out as `letter-spacing: 2`, which is not a length, so the browser threw
 * the declaration away and the field did nothing on any element that used it. Font
 * size was the only one being given a unit.
 *
 * The rule is per property, not per field:
 *   - font-size and letter-spacing are lengths. A bare number needs px.
 *   - line-height is a ratio. A bare number is exactly what it should be, and adding
 *     px to it would set a fixed line box that stops following the font size.
 *   - the rest are keywords or names and are passed through.
 *
 * Empty means "leave it to the preset", so an element the author has not touched still
 * follows whichever preset is chosen.
 */
class Typography
{
    /** Setting suffix to CSS property. */
    private const MAP = [
        'family' => 'font-family',
        'weight' => 'font-weight',
        'size' => 'font-size',
        'line_height' => 'line-height',
        'letter_spacing' => 'letter-spacing',
        'transform' => 'text-transform',
    ];

    /** The properties that are lengths, and so need a unit when given a bare number. */
    private const LENGTHS = ['font-size', 'letter-spacing'];

    /**
     * Build the declarations for one prefix.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function css(array $settings, string $prefix): string
    {
        $out = '';

        foreach (self::MAP as $key => $css) {
            $value = trim((string) ($settings[$prefix.'_'.$key] ?? ''));

            if ($value === '' || $value === 'inherit' || ($value === 'none' && $css === 'text-transform')) {
                continue;
            }

            if (is_numeric($value) && in_array($css, self::LENGTHS, true)) {
                $value .= 'px';
            }

            $out .= $css.': '.$value.'; ';
        }

        return trim($out);
    }
}
