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
                $rules[] = "{$cssProp}:{$mVal}px!important";
            }
        }
        foreach (['paddingTop','paddingRight','paddingBottom','paddingLeft'] as $pProp) {
            $pVal = $getRespVal($pProp, $rDev);
            if ($pVal !== null) {
                $cssProp = strtolower(preg_replace('/([A-Z])/', '-$1', $pProp));
                $rules[] = "{$cssProp}:{$pVal}px!important";
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
        'padding-top: ' . ($s['paddingTop'] ?? 10) . 'px',
        'padding-right: ' . ($s['paddingRight'] ?? 0) . 'px',
        'padding-bottom: ' . ($s['paddingBottom'] ?? 10) . 'px',
        'padding-left: ' . ($s['paddingLeft'] ?? 0) . 'px',
        'margin-top: ' . ($s['marginTop'] ?? 0) . 'px',
        'margin-right: ' . ($s['marginRight'] ?? 0) . 'px',
        'margin-bottom: ' . ($s['marginBottom'] ?? 0) . 'px',
        'margin-left: ' . ($s['marginLeft'] ?? 0) . 'px',
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
