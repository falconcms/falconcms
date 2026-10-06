<?php

/**
 * CSS generation for builder elements (gradients, sizes, responsive rules, colours).
 *
 * Loaded by src/helpers.php.
 */
if (!function_exists('falcon_gradient_bg')) {
    /**
     * The background-image value for a node's gradient settings, or null for none.
     *
     * Both colours used to be required: `!empty(start) && !empty(end)`. Pick one and
     * nothing at all was drawn — not the gradient, and not the colour you had just
     * chosen — on the canvas or on the page. Choosing one colour is a perfectly ordinary
     * thing to do, and it plainly means "fill this with that colour", so one colour now
     * paints a flat fill of it and two make the gradient they always did.
     *
     * Returned as a gradient in both cases rather than a background-color, because the
     * caller layers it with a background image underneath and expects a background-image
     * value; a flat fill is a gradient from a colour to itself.
     *
     * @param  array  $s  the node's settings
     * @param  callable  $rgba  fn(?string $hex, $opacity): string — the caller's own
     *                          hex→rgba, so opacity handling stays identical to its
     *                          other colours
     */
    function falcon_gradient_bg(array $s, callable $rgba): ?string
    {
        $startHex = $s['bgGradientStartColor'] ?? '';
        $endHex = $s['bgGradientEndColor'] ?? '';
        if (empty($startHex) && empty($endHex)) {
            return null;
        }

        $fallbackOpacity = $s['bgColorOpacity'] ?? 1;
        $start = !empty($startHex) ? $rgba($startHex, $s['bgGradientStartOpacity'] ?? $fallbackOpacity) : null;
        $end = !empty($endHex) ? $rgba($endHex, $s['bgGradientEndOpacity'] ?? $fallbackOpacity) : null;

        // One colour: a flat fill of it, edge to edge. Fading the missing end to
        // transparent instead would show the container behind it, which is not what
        // picking a single colour asks for.
        if ($start === null || $end === null) {
            $only = $start ?? $end;

            return "linear-gradient({$only} 0%, {$only} 100%)";
        }

        $startPos = $s['bgGradientStartPosition'] ?? 0;
        $endPos = $s['bgGradientEndPosition'] ?? 100;

        if (($s['bgGradientType'] ?? 'linear') === 'linear') {
            $angle = $s['bgGradientAngle'] ?? 180;

            return "linear-gradient({$angle}deg, {$start} {$startPos}%, {$end} {$endPos}%)";
        }

        return "radial-gradient(circle at center, {$start} {$startPos}%, {$end} {$endPos}%)";
    }
}

if (!function_exists('falcon_css_size_to_px')) {
    /**
     * A CSS font-size read as a number of pixels, or null when it is not one we can reason about.
     *
     * The Customizer's Font Size is a free-text box, so what arrives here is whatever the author
     * typed: "40px", "2.5rem", "150%", or a bare "40". Returning the unit alongside the pixel
     * value lets a caller do arithmetic in pixels and still write the answer back in the unit it
     * was given — which matters for rem, where the whole point is that the reader's own browser
     * font size still applies.
     *
     * $contextPx is what a relative unit resolves against: the root size for the body, and the
     * body's own size for a heading sitting inside it.
     */
    function falcon_css_size_to_px(string $size, float $contextPx = 16.0): ?array
    {
        if (!preg_match('/^([0-9]*\.?[0-9]+)\s*(px|rem|em|%)?$/i', trim($size), $m)) {
            return null;
        }

        $n = (float) $m[1];
        $unit = ($m[2] ?? '') !== '' ? strtolower($m[2]) : 'px';

        // A bare number is not valid CSS — the browser drops the whole declaration — so reading
        // it as px is both what the field's own "16px" placeholder implies and the only reading
        // that leaves the author with a size rather than with nothing.
        $perUnit = match ($unit) {
            'rem' => 16.0,      // the theme leaves html's font-size alone, so a rem is 16px
            'em' => $contextPx, // a heading's em resolves against the text it is sitting in
            '%' => $contextPx / 100,
            default => 1.0,
        };

        if ($perUnit <= 0 || $n <= 0) {
            return null;
        }

        return ['px' => $n * $perUnit, 'unit' => $unit, 'per_unit' => $perUnit];
    }
}

if (!function_exists('falcon_fluid_font_size')) {
    /**
     * A fixed font-size rewritten as a fluid clamp(), for the Customizer's Responsive Typography.
     *
     * Two settings drive it: Sensitivity, how far a size may travel, and Minimum Font Size
     * Factor, which sets the floor it travels toward (body size x factor). Between the small and
     * medium screen widths the size slides from that floor up to the size the author set; outside
     * them it rests at one end or the other, so the desktop appearance never changes.
     *
     * The size comes back as written when the feature is off, when the breakpoints are the wrong
     * way round, when the value is one we cannot read, or when it is already at or below the
     * floor — something smaller than the minimum has nowhere to shrink to.
     */
    function falcon_fluid_font_size(
        string $size,
        float $sensitivity,
        float $floorPx,
        int $smallBp,
        int $mediumBp,
        float $contextPx = 16.0
    ): string {
        $size = trim($size);
        $parsed = falcon_css_size_to_px($size, $contextPx);

        if ($parsed === null) {
            return $size;
        }

        // Writing a px value back in the author's own unit, so a size given in rem still answers
        // to the reader's browser font size.
        $write = static fn (float $px): string => $parsed['unit'] === 'px'
            ? rtrim(rtrim(number_format($px, 2, '.', ''), '0'), '.').'px'
            : rtrim(rtrim(number_format($px / $parsed['per_unit'], 4, '.', ''), '0'), '.').$parsed['unit'];

        $max = $parsed['px'];

        if ($sensitivity <= 0 || $mediumBp <= $smallBp || $floorPx <= 0 || $max <= $floorPx) {
            // Still normalised: a bare "40" was never going to render at all.
            return $write($max);
        }

        $sens = min(1.0, max(0.0, $sensitivity));
        $min = round($max - ($max - $floorPx) * $sens, 2);
        $delta = round($max - $min, 2);
        $span = $mediumBp - $smallBp;

        // Only the middle term is px: calc() can multiply a length by a number and divide it by
        // one, but it cannot divide a length by a length, which is what carrying rem through the
        // viewport term would need. The bounds are what the reader sees at either end anyway.
        $minOut = $write($min);
        $maxOut = $write($max);

        return "clamp({$minOut}, calc({$min}px + {$delta} * (100vw - {$smallBp}px) / {$span}), {$maxOut})";
    }
}

if (!function_exists('falcon_icon_box_icon_style')) {
    /**
     * The Icon Box's icon, in both of the states it can be in.
     *
     * The wrapper is only drawn as a box when there is something to draw — a background or a
     * border — and both states have to agree about that, or the icon would shift under the
     * pointer for no reason the author asked for.
     *
     * Hover has to be a stylesheet rule: inline style cannot express :hover, and the normal
     * state is written inline, so the rule carries !important for the same reason the Read
     * More hover already does. Every hover value is optional and falls back to its normal
     * counterpart, so an icon with nothing set on hover renders exactly as it did before
     * there were hover settings at all.
     *
     * @return array{wrap: string, icon: string, css: string}
     */
    function falcon_icon_box_icon_style(array $s, string $scope): array
    {
        // A cleared field stores "" rather than null, and `?? $default` does not catch that.
        $val = static function (string $key, $default) use ($s) {
            $v = $s[$key] ?? null;

            return ($v === null || $v === '') ? $default : $v;
        };

        $unit = (string) $val('iconSizeUnit', 'px');

        // One description of the icon, applied twice with different numbers.
        $draw = static function (array $v) use ($unit): array {
            $borderWidth = (float) $v['borderWidth'];
            $boxed = $v['bg'] !== '' || $borderWidth > 0;

            $wrap = 'display:inline-flex;align-items:center;justify-content:center;';
            if ($boxed) {
                // The box has always been twice the icon's own size, in px whatever unit the
                // size itself is in. Left as it was: changing it would resize every icon box
                // that is already out there.
                $box = ((float) $v['size']) * 2;
                $wrap .= 'box-sizing:content-box;'
                    .'width:'.$box.'px;height:'.$box.'px;'
                    .'background-color:'.($v['bg'] !== '' ? _falcon_hex_to_rgba((string) $v['bg'], (float) $v['bgOpacity']) : 'transparent').';'
                    .'border-radius:'.(float) $v['radius'].'px;'
                    .'padding:'.(float) $v['padding'].'px;'
                    .'border:'.$borderWidth.'px solid '.($borderWidth > 0 ? $v['borderColor'] : 'transparent').';';
            }

            return [
                'wrap' => $wrap,
                'icon' => 'font-size:'.$v['size'].$unit.';color:'.$v['color'].';',
            ];
        };

        $normalValues = [
            'size' => $val('iconSize', 40),
            'color' => $val('iconColor', '#2271b1'),
            'bg' => (string) $val('iconBgColor', ''),
            'bgOpacity' => $val('iconBgColorOpacity', 1),
            'radius' => $val('iconBorderRadius', 50),
            'padding' => $val('iconPadding', 0),
            'borderWidth' => $val('iconBorderWidth', 0),
            'borderColor' => $val('iconBorderColor', '#2271b1'),
        ];

        $normal = $draw($normalValues);

        // Hover falls through to normal per setting, so setting one thing changes one thing.
        $hoverValues = $normalValues;
        $touched = false;
        foreach ([
            'size' => 'iconSizeHover',
            'color' => 'iconColorHover',
            'bg' => 'iconBgColorHover',
            'bgOpacity' => 'iconBgColorHoverOpacity',
            'radius' => 'iconBorderRadiusHover',
            'padding' => 'iconPaddingHover',
            'borderWidth' => 'iconBorderWidthHover',
            'borderColor' => 'iconBorderColorHover',
        ] as $slot => $key) {
            $v = $s[$key] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $hoverValues[$slot] = $v;
            $touched = true;
        }

        // An opacity on its own says nothing without a colour to apply it to.
        if ($touched && $hoverValues['bg'] === '' && $normalValues['bg'] !== '') {
            $hoverValues['bg'] = $normalValues['bg'];
        }

        // Spacing Below (margin under the icon) can also change on hover. The normal value is an
        // inline style on the wrapper, so the hover value needs an !important rule to override it;
        // it falls through to normal when the hover field is empty, like every other hover setting.
        $spacingHover = $s['iconSpacingHover'] ?? null;
        $spacingHoverSet = !($spacingHover === null || $spacingHover === '');

        $css = '';
        if ($touched || $spacingHoverSet) {
            $important = static function (string $style): string {
                $out = '';
                foreach (array_filter(array_map('trim', explode(';', $style))) as $decl) {
                    $out .= $decl.' !important;';
                }

                return $out;
            };
            $css = $scope.' .lazy-icon-box__icon{transition:all .2s ease;}'
                .$scope.' .lazy-icon-box__icon i{transition:all .2s ease;}';
            if ($touched) {
                $h = $draw($hoverValues);
                $css .= $scope.' .lazy-icon-box__icon:hover{'.$important($h['wrap']).'}'
                    .$scope.' .lazy-icon-box__icon:hover i{'.$important($h['icon']).'}';
            }
            if ($spacingHoverSet) {
                $css .= $scope.' .lazy-icon-box__icon:hover{margin-bottom:'.(float) $spacingHover.'px !important;}';
            }
        }

        return ['wrap' => $normal['wrap'], 'icon' => $normal['icon'], 'css' => $css];
    }
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

if (!function_exists('_falcon_hex_to_rgba')) {
    function _falcon_hex_to_rgba(string $hex, float $opacity = 1): string
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

// Returns a readable foreground (#111111 / #ffffff) for a given background hex — used so
// brand-coloured boxes keep their icon legible (e.g. white icon on Snapchat yellow → dark).
if (!function_exists('falcon_contrast_color')) {
    function falcon_contrast_color($hex): string
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
