@php
    $s = $el['settings'] ?? [];
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' lazy-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    $elemId = 'btn-' . str_replace('.', '', uniqid('', true));
    $appliedId = !empty($s['cssId']) ? $s['cssId'] : $elemId;

    $hexToRgba = function(string $hex, $opacity = null): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) !== 6) return $hex ? "#{$hex}" : 'transparent';
        [$r, $g, $b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
        if ($opacity === null || $opacity === '' || (float)$opacity >= 1) return "#{$hex}";
        return "rgba({$r},{$g},{$b}," . (float)$opacity . ")";
    };

    $getRespVal = function(string $prop, string $dev) use ($s) {
        if ($dev === 'mobile') {
            if (isset($s[$prop . '_mobile']) && $s[$prop . '_mobile'] !== '') return (string)$s[$prop . '_mobile'];
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        } elseif ($dev === 'tablet') {
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        }
        return null;
    };

    $respCss = '';
    foreach ([
        ['tablet', "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)"],
        ['mobile', "@media(max-width:{$bpSm}px)"],
    ] as [$rDev, $rMq]) {
        $wrapRules = [];
        $btnRules  = [];

        $rAlign = $getRespVal('textAlign', $rDev);
        if ($rAlign !== null) {
            $jc = $rAlign === 'left' ? 'flex-start' : ($rAlign === 'right' ? 'flex-end' : 'center');
            $wrapRules[] = "justify-content:{$jc}!important";
        }
        foreach (['marginTop', 'marginBottom'] as $mProp) {
            $mVal = $getRespVal($mProp, $rDev);
            if ($mVal !== null) {
                $cssProp = strtolower(preg_replace('/([A-Z])/', '-$1', $mProp));
                $wrapRules[] = "{$cssProp}:{$mVal}px!important";
            }
        }
        foreach (['marginLeft', 'marginRight', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft'] as $bProp) {
            $bVal = $getRespVal($bProp, $rDev);
            if ($bVal !== null) {
                $cssProp = strtolower(preg_replace('/([A-Z])/', '-$1', $bProp));
                $btnRules[] = "{$cssProp}:{$bVal}px!important";
            }
        }
        if (!empty($wrapRules)) {
            $respCss .= "{$rMq}{.button-container-{$elemId}{" . implode(';', $wrapRules) . "}}";
        }
        if (!empty($btnRules)) {
            $respCss .= "{$rMq}{#{$appliedId}{" . implode(';', $btnRules) . "}}";
        }
    }

    $wrapperStyles = [
        'display' => 'flex',
        'width' => '100%',
        'justify-content' => $s['textAlign'] === 'left' ? 'flex-start' : ($s['textAlign'] === 'right' ? 'flex-end' : 'center'),
        'margin-top' => getUnitVal($s['marginTop'] ?? 10, 'px'),
        'margin-bottom' => getUnitVal($s['marginBottom'] ?? 10, 'px'),
    ];

    $btnStyles = [
        'display' => ($s['buttonSpan'] ?? false) ? 'block' : 'inline-block',
        'width' => ($s['buttonSpan'] ?? false) ? '100%' : 'auto',
        'padding-top' => getUnitVal($s['paddingTop'] ?? 12, 'px'),
        'padding-bottom' => getUnitVal($s['paddingBottom'] ?? 12, 'px'),
        'padding-left' => getUnitVal($s['paddingLeft'] ?? 30, 'px'),
        'padding-right' => getUnitVal($s['paddingRight'] ?? 30, 'px'),
        'margin-left' => getUnitVal($s['marginLeft'] ?? 0, 'px'),
        'margin-right' => getUnitVal($s['marginRight'] ?? 0, 'px'),
        'background-color' => (($s['buttonStyle'] ?? 'default') === 'custom' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor'])) ? 'transparent' : $hexToRgba($s['bgColor'] ?? '#0091ea', $s['bgColorOpacity'] ?? null),
        'background-image' => (($s['buttonStyle'] ?? 'default') === 'custom' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor']))
            ? (($s['bgGradientType'] ?? 'linear') === 'radial'
                ? "radial-gradient(circle at center, " . $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? null) . " " . ($s['bgGradientStartPosition'] ?? 0) . "%, " . $hexToRgba($s['bgGradientEndColor'], $s['bgGradientEndOpacity'] ?? null) . " " . ($s['bgGradientEndPosition'] ?? 100) . "%)"
                : "linear-gradient(" . ($s['bgGradientAngle'] ?? 180) . "deg, " . $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? null) . " " . ($s['bgGradientStartPosition'] ?? 0) . "%, " . $hexToRgba($s['bgGradientEndColor'], $s['bgGradientEndOpacity'] ?? null) . " " . ($s['bgGradientEndPosition'] ?? 100) . "%)")
            : 'none',
        'color' => $hexToRgba($s['color'] ?? '#ffffff', $s['colorOpacity'] ?? null),
        'border-radius' => getUnitVal($s['borderRadius'] ?? 5, 'px'),
        'border-top-width' => getUnitVal($s['borderSizeTop'] ?? 0, 'px'),
        'border-right-width' => getUnitVal($s['borderSizeRight'] ?? 0, 'px'),
        'border-bottom-width' => getUnitVal($s['borderSizeBottom'] ?? 0, 'px'),
        'border-left-width' => getUnitVal($s['borderSizeLeft'] ?? 0, 'px'),
        'border-style' => 'solid',
        'border-color' => $hexToRgba($s['borderColor'] ?? '#000000', $s['borderColorOpacity'] ?? null),
        'font-family' => $s['fontFamily'] ?? 'inherit',
        'font-size' => getUnitVal($s['fontSize'] ?? 16, 'px'),
        'font-weight' => $s['fontWeight'] ?? '600',
        'line-height' => $s['lineHeight'] ?? 'normal',
        'letter-spacing' => getUnitVal($s['letterSpacing'] ?? 0, 'px'),
        'text-transform' => $s['textTransform'] ?? 'none',
        'text-decoration' => 'none',
        'transition' => 'all 0.3s ease',
        'cursor' => 'pointer',
        'text-align' => 'center',
    ];

    $isCustom = ($s['buttonStyle'] ?? 'default') === 'custom';
    $hoverColor   = $hexToRgba($s['hoverColor']   ?? '#ffffff', $s['hoverColorOpacity']   ?? null);
    $hoverBgColor = $hexToRgba($s['hoverBgColor'] ?? '#007cc0', $s['hoverBgColorOpacity'] ?? null);
    $hoverStart = $hexToRgba($s['bgGradientHoverStartColor'] ?? '#007cc0', $s['bgGradientHoverStartOpacity'] ?? null);
    $hoverEnd   = $hexToRgba($s['bgGradientHoverEndColor']   ?? '#005fa3', $s['bgGradientHoverEndOpacity']   ?? null);
    $icon = $s['icon'] ?? '';
    $iconPos = $s['iconPosition'] ?? 'left';

    $hoverBgImage = 'none';
    if ($isCustom && !empty($s['bgGradientStartColor'])) {
         if (($s['bgGradientType'] ?? 'linear') === 'radial') {
             $hoverBgImage = "radial-gradient(circle at center, {$hoverStart} " . ($s['bgGradientStartPosition'] ?? 0) . "%, {$hoverEnd} " . ($s['bgGradientEndPosition'] ?? 100) . "%)";
         } else {
             $hoverBgImage = "linear-gradient(" . ($s['bgGradientAngle'] ?? 180) . "deg, {$hoverStart} " . ($s['bgGradientStartPosition'] ?? 0) . "%, {$hoverEnd} " . ($s['bgGradientEndPosition'] ?? 100) . "%)";
         }
    }
@endphp

<style>
    #{{ $appliedId }}:hover {
        @if($isCustom)
            background-image: {{ $hoverBgImage }} !important;
            background-color: transparent !important;
        @else
            background-color: {{ $hoverBgColor }} !important;
            background-image: none !important;
        @endif
        color: {{ $hoverColor }} !important;
    }
    @if($respCss) {!! $respCss !!} @endif
</style>

<div class="element-button-wrapper button-container-{{ $elemId }} {{ $s['cssClass'] ?? '' }} {{ $visibilityClasses }}"
     style="{{ collect($wrapperStyles)->map(fn($v, $k) => "$k: $v")->implode('; ') }}">
    <a href="{{ $s['linkUrl'] ?? '#' }}" 
       id="{{ $appliedId }}"
       target="{{ $s['linkTarget'] ?? '_self' }}"
       style="{{ collect($btnStyles)->map(fn($v, $k) => "$k: $v")->implode('; ') }}">
        @if($icon && $iconPos !== 'right')
            <i class="{{ $icon }} mr-2"></i>
        @endif
        {{ $s['text'] ?? 'Click Here' }}
        @if($icon && $iconPos === 'right')
            <i class="{{ $icon }} ml-2"></i>
        @endif
    </a>
</div>
