@php
    $s = $el['settings'] ?? [];

    $dynamicSrc = $s['dynamic_source'] ?? '';
    if ($dynamicSrc && function_exists('lazy_resolve_dynamic_value')) {
        $dynamicConfig = [
            'date_type'      => $s['dynamic_date_type']      ?? 'published',
            'date_format'    => $s['dynamic_date_format']     ?? '',
            'before'         => $s['dynamic_before']          ?? '',
            'after'          => $s['dynamic_after']           ?? '',
            'fallback'       => $s['dynamic_fallback']        ?? '',
            'excerpt_length' => (int)($s['dynamic_excerpt_length'] ?? 150),
            'acpt_slug'      => $s['dynamic_acpt_slug']       ?? '',
        ];
        $resolved = lazy_resolve_dynamic_value($dynamicSrc, $post ?? null, $dynamicConfig);
        $s['content'] = $resolved ? nl2br(e($resolved)) : ($s['content'] ?? '');
    }

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' lazy-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    $elemId   = 'text-block-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $appliedId = !empty($s['cssId']) ? $s['cssId'] : $elemId;
    $hoverColor = $s['hoverColor'] ?? null;

    // Responsive helper — cascades tablet → desktop, mobile → tablet → desktop
    $getRespVal = function(string $prop, string $dev) use ($s) {
        if ($dev === 'mobile') {
            if (isset($s[$prop . '_mobile']) && $s[$prop . '_mobile'] !== '') return (string)$s[$prop . '_mobile'];
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        } elseif ($dev === 'tablet') {
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        }
        return null;
    };

    // Build responsive CSS for tablet and mobile breakpoints
    $respCss = '';
    foreach ([
        ['tablet', "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)"],
        ['mobile', "@media(max-width:{$bpSm}px)"],
    ] as [$rDev, $rMq]) {
        $rules = [];
        $rAlign = $getRespVal('textAlign', $rDev);
        if ($rAlign !== null) $rules[] = "text-align:{$rAlign}!important";

        foreach (['marginTop','marginRight','marginBottom','marginLeft'] as $mProp) {
            $mVal = $getRespVal($mProp, $rDev);
            if ($mVal !== null) {
                $cssProp = strtolower(preg_replace('/([A-Z])/', '-$1', $mProp));
                $unit = $rDev === 'mobile'
                    ? ($s[$mProp . 'Unit_mobile'] ?? $s[$mProp . 'Unit_tablet'] ?? $s[$mProp . 'Unit'] ?? 'px')
                    : ($s[$mProp . 'Unit_tablet'] ?? $s[$mProp . 'Unit'] ?? 'px');
                $rules[] = "{$cssProp}:{$mVal}{$unit}!important";
            }
        }
        foreach (['paddingTop','paddingRight','paddingBottom','paddingLeft'] as $pProp) {
            $pVal = $getRespVal($pProp, $rDev);
            if ($pVal !== null) {
                $cssProp = strtolower(preg_replace('/([A-Z])/', '-$1', $pProp));
                $unit = $rDev === 'mobile'
                    ? ($s[$pProp . 'Unit_mobile'] ?? $s[$pProp . 'Unit_tablet'] ?? $s[$pProp . 'Unit'] ?? 'px')
                    : ($s[$pProp . 'Unit_tablet'] ?? $s[$pProp . 'Unit'] ?? 'px');
                $rules[] = "{$cssProp}:{$pVal}{$unit}!important";
            }
        }
        if (!empty($rules)) {
            $respCss .= "{$rMq}{#{$appliedId}{" . implode(';', $rules) . "}}";
        }
    }

    $wrapperStyles = [
        'width: 100%',
        'max-width: 100%',
        'text-align: ' . ($s['textAlign'] ?? 'center'),
        'padding-top: ' . ($s['paddingTop'] ?? 0) . ($s['paddingTopUnit'] ?? 'px'),
        'padding-right: ' . ($s['paddingRight'] ?? 0) . ($s['paddingRightUnit'] ?? 'px'),
        'padding-bottom: ' . ($s['paddingBottom'] ?? 0) . ($s['paddingBottomUnit'] ?? 'px'),
        'padding-left: ' . ($s['paddingLeft'] ?? 0) . ($s['paddingLeftUnit'] ?? 'px'),
        'margin-top: ' . ($s['marginTop'] ?? 0) . ($s['marginTopUnit'] ?? 'px'),
        'margin-right: ' . ($s['marginRight'] ?? 0) . ($s['marginRightUnit'] ?? 'px'),
        'margin-bottom: ' . ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px'),
        'margin-left: ' . ($s['marginLeft'] ?? 0) . ($s['marginLeftUnit'] ?? 'px'),
        'color: ' . ($s['color'] ?? '#333333'),
        'font-family: ' . ($s['fontFamily'] ?? 'inherit'),
        'font-size: ' . ($s['fontSize'] ?? 16) . ($s['fontSizeUnit'] ?? 'px'),
        'font-weight: ' . ($s['fontWeight'] ?? '400'),
        'line-height: ' . ($s['lineHeight'] ?? '1.5'),
        'letter-spacing: ' . ($s['letterSpacing'] ?? 0) . 'px',
        'text-transform: ' . ($s['textTransform'] ?? 'none'),
    ];

    $contentStyles = [
        'text-align: inherit',
        'margin: 0',
        'width: 100%',
        'transition: color 0.3s ease',
        'color: inherit',
    ];
@endphp

<style>
    #{{ $appliedId }}:hover { color: {{ $hoverColor ?? ($s['color'] ?? '#333333') }} !important; }
    #{{ $appliedId }}:hover p, #{{ $appliedId }}:hover * { color: inherit !important; }
    .text-block-container-{{ $elemId }} .text-block-content,
    .text-block-container-{{ $appliedId }} .text-block-content {
        text-align: inherit !important;
        color: inherit !important;
        font-size: inherit !important;
        font-family: inherit !important;
        font-weight: inherit !important;
        line-height: inherit !important;
        letter-spacing: inherit !important;
        text-transform: inherit !important;
        margin: 0 !important;
        display: block;
        width: 100%;
    }
    .text-block-container-{{ $appliedId }} .text-block-content > p:first-child { margin-top: 0 !important; }
    .text-block-container-{{ $appliedId }} .text-block-content > p:last-child { margin-bottom: 0 !important; }
    .text-block-container-{{ $appliedId }} ul { list-style-type: disc !important; margin-left: 20px !important; margin-bottom: 15px !important; }
    .text-block-container-{{ $appliedId }} ol { list-style-type: decimal !important; margin-left: 20px !important; margin-bottom: 15px !important; }
    .text-block-container-{{ $appliedId }} li { margin-bottom: 5px !important; }
    @if($respCss) {!! $respCss !!} @endif
</style>

<div class="element-text-block-wrapper text-block-container-{{ $appliedId }} {{ $s['cssClass'] ?? '' }} {{ $visibilityClasses }}"
     id="{{ $appliedId }}"
     style="{{ implode('; ', $wrapperStyles) }}">
    <div class="text-block-content" style="{{ implode('; ', $contentStyles) }}">
        {!! $s['content'] ?? '' !!}
    </div>
</div>
