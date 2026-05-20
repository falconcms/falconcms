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

    $titleRespId = 'lte-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));

    $getRespVal = function(string $prop, string $dev) use ($s): ?string {
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
        $rWrapperRules = [];
        $rHeadingRules = [];
        $rAlign = $getRespVal('textAlign', $rDev);
        if ($rAlign !== null) {
            $rWrapperRules[] = "text-align:{$rAlign}!important";
            $rHeadingRules[] = "text-align:{$rAlign}!important";
        }
        foreach (['marginTop', 'marginRight', 'marginBottom', 'marginLeft'] as $mProp) {
            $mVal = $getRespVal($mProp, $rDev);
            if ($mVal !== null) {
                $cssProp = strtolower(preg_replace('/([A-Z])/', '-$1', $mProp));
                $rWrapperRules[] = "{$cssProp}:{$mVal}px!important";
            }
        }
        if (!empty($rWrapperRules)) {
            $respCss .= "{$rMq}{.{$titleRespId}{" . implode(';', $rWrapperRules) . "}}";
        }
        if (!empty($rHeadingRules)) {
            $respCss .= "{$rMq}{.{$titleRespId} .main-title{" . implode(';', $rHeadingRules) . "}}";
        }
    }

    $wrapperStyles = [
        'align-self: stretch',
        'text-align: ' . ($s['textAlign'] ?? 'center'),
        'padding-top: ' . ($s['paddingTop'] ?? 20) . 'px',
        'padding-bottom: ' . ($s['paddingBottom'] ?? 20) . 'px',
        'margin-top: ' . ($s['marginTop'] ?? 0) . 'px',
        'margin-right: ' . ($s['marginRight'] ?? 0) . 'px',
        'margin-bottom: ' . ($s['marginBottom'] ?? 0) . 'px',
        'margin-left: ' . ($s['marginLeft'] ?? 0) . 'px',
    ];

    $titleStyles = [
        'font-family: ' . ($s['fontFamily'] ?? 'inherit'),
        'font-size: ' . ($s['fontSize'] ?? 36) . ($s['fontSizeUnit'] ?? 'px'),
        'font-weight: ' . ($s['fontWeight'] ?? '800'),
        'line-height: ' . ($s['lineHeight'] ?? '1.2'),
        'letter-spacing: ' . ($s['letterSpacing'] ?? 0) . 'px',
        'text-transform: ' . ($s['textTransform'] ?? 'none'),
        'text-align: ' . ($s['textAlign'] ?? 'center'),
        'margin: 0',
        'transition: color 0.3s ease',
    ];

    $useLink = !empty($s['useLink']) && !empty($s['linkUrl']);

    if ($useLink) {
        $titleStyles[] = 'color: inherit';
    } elseif (!empty($s['useGradient'])) {
        $startColor = $s['gradientStartColor'] ?? $s['titleColor'] ?? '#222';
        $endColor   = $s['gradientEndColor']   ?? '#0091ea';
        $angle      = $s['gradientAngle']      ?? 90;
        $titleStyles[] = "background-image: linear-gradient({$angle}deg, {$startColor}, {$endColor})";
        $titleStyles[] = "-webkit-background-clip: text";
        $titleStyles[] = "background-clip: text";
        $titleStyles[] = "color: transparent";
        $titleStyles[] = "-webkit-text-fill-color: transparent";
    } else {
        $titleStyles[] = 'color: ' . ($s['titleColor'] ?? '#222');
    }

    if (!empty($s['textShadow'])) {
        $h    = $s['textShadowH']    ?? 0;
        $vSh  = $s['textShadowV']    ?? 0;
        $blur = $s['textShadowBlur'] ?? 0;
        $col  = $s['textShadowColor'] ?? 'rgba(0,0,0,0.2)';
        $titleStyles[] = "text-shadow: {$h}px {$vSh}px {$blur}px {$col}";
    }

    if (!empty($s['textStroke'])) {
        $size  = $s['textStrokeSize']  ?? 1;
        $color = $s['textStrokeColor'] ?? '#000';
        $titleStyles[] = "-webkit-text-stroke: {$size}px {$color}";
    }

    if (!empty($s['textOverflow']) && $s['textOverflow'] !== 'initial') {
        $titleStyles[] = "text-overflow: {$s['textOverflow']}";
        $titleStyles[] = "white-space: nowrap";
        $titleStyles[] = "overflow: hidden";
    }

    // Separator
    $separator = $s['separator'] ?? 'none';
    $dividerStyles = [];
    if ($separator !== 'none') {
        $separatorSpacing = $s['separatorSpacing'] ?? 20;
        $align = $s['textAlign'] ?? 'center';
        $marginStr = $separatorSpacing . 'px ' . ($align === 'center' ? 'auto 0' : ($align === 'right' ? '0 0 auto' : '0 0'));

        $dividerStyles = [
            'display'      => 'block',
            'width'        => ($s['dividerWidth'] ?? 60) . 'px',
            'margin'       => $marginStr,
        ];

        if ($separator === 'default') {
            $dividerStyles['height']           = ($s['dividerHeight'] ?? 3) . 'px';
            $dividerStyles['background-color'] = $s['separatorColor'] ?? '#0091ea';
            $dividerStyles['border-radius']    = '10px';
        } else {
            $dividerStyles['height']           = '0';
            $dividerStyles['background-color'] = 'transparent';
            $dividerStyles['border-top']       = ($s['dividerHeight'] ?? 3) . 'px ' . $separator . ' ' . ($s['separatorColor'] ?? '#0091ea');
        }
    }

    $htmlTag = $s['htmlTag'] ?? 'h2';

    // Auto-prefix link URL
    $linkUrl = $s['linkUrl'] ?? '';
    if ($linkUrl && !preg_match('/^(https?:\/\/|\/\/|\/|#|tel:|mailto:)/i', $linkUrl)) {
        $linkUrl = 'https://' . $linkUrl;
    }

    $linkColor      = $s['linkColor']      ?? 'inherit';
    $linkHoverColor = $s['linkHoverColor'] ?? $linkColor;
    $linkId = 'title-link-' . uniqid();

    $titleHoverColor = !$useLink ? ($s['titleHoverColor'] ?? null) : null;
    $titleHoverColor = ($titleHoverColor && trim($titleHoverColor) !== '') ? $titleHoverColor : null;
    $titleElemId = $titleHoverColor ? ('title-h-' . uniqid()) : '';
@endphp

@if($useLink)
<style>
    #{{ $linkId }} { color: {{ $linkColor }} !important; text-decoration: none; display: block; transition: color 0.3s ease; }
    #{{ $linkId }}:hover { color: {{ $linkHoverColor }} !important; }
</style>
@endif
@if($titleHoverColor)
<style>
    @if(empty($s['useGradient']))
    #{{ $titleElemId }} { transition: color 0.3s ease; }
    #{{ $titleElemId }}:hover { color: {{ $titleHoverColor }} !important; }
    @else
    #{{ $titleElemId }}:hover { color: {{ $titleHoverColor }} !important; -webkit-text-fill-color: {{ $titleHoverColor }} !important; background-image: none !important; }
    @endif
</style>
@endif
@if($respCss)
<style>{!! $respCss !!}</style>
@endif

<div class="element-title-wrapper {{ $titleRespId }} {{ $s['cssClass'] ?? '' }} {{ $visibilityClasses }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="{{ implode('; ', $wrapperStyles) }}">

    @if($useLink)
        <a href="{{ $linkUrl }}" id="{{ $linkId }}" target="{{ $s['linkTarget'] ?? '_self' }}">
    @endif

    <{{ $htmlTag }} class="main-title"@if($titleElemId) id="{{ $titleElemId }}"@endif style="{{ implode('; ', $titleStyles) }}">
        {{ $s['title'] ?? 'Your Awesome Title' }}
    </{{ $htmlTag }}>

    @if($useLink)
        </a>
    @endif

    @if($separator !== 'none')
        <div class="title-divider" style="{{ collect($dividerStyles)->map(fn($v, $k) => "$k: $v")->implode('; ') }}"></div>
    @endif
</div>
