@php
    $s = $el['settings'] ?? [];

    $dynamicSrc  = $s['dynamic_source']      ?? '';
    $linkDynamic = $s['link_dynamic_source'] ?? '';
    $dynamicConfig = [
        'date_type'      => $s['dynamic_date_type']      ?? 'published',
        'date_format'    => $s['dynamic_date_format']     ?? '',
        'before'         => $s['dynamic_before']          ?? '',
        'after'          => $s['dynamic_after']           ?? '',
        'fallback'       => $s['dynamic_fallback']        ?? '',
        'excerpt_length' => (int)($s['dynamic_excerpt_length'] ?? 150),
        'acpt_slug'      => $s['dynamic_acpt_slug']       ?? '',
    ];
    $titleText   = $dynamicSrc
        ? (function_exists('falcon_resolve_dynamic_value') ? (falcon_resolve_dynamic_value($dynamicSrc, $post ?? null, $dynamicConfig) ?: ($s['title'] ?? 'Your Awesome Title')) : ($postTitle ?? $s['title'] ?? 'Your Awesome Title'))
        : ($s['title'] ?? 'Your Awesome Title');
    $resolvedLinkUrl = $linkDynamic
        ? (function_exists('falcon_resolve_dynamic_value') ? (falcon_resolve_dynamic_value($linkDynamic, $post ?? null) ?: ($s['linkUrl'] ?? '')) : ($postPermalink ?? $s['linkUrl'] ?? ''))
        : ($s['linkUrl'] ?? '');

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    $titleRespId = 'lte-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));

    $w = ".{$titleRespId}";
    $h = ".{$titleRespId} .main-title";
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'textAlign',     'sel' => $w],
        ['prop' => 'marginTop',     'unitProp' => 'marginTopUnit',     'sel' => $w],
        ['prop' => 'marginRight',   'unitProp' => 'marginRightUnit',   'sel' => $w],
        ['prop' => 'marginBottom',  'unitProp' => 'marginBottomUnit',  'sel' => $w],
        ['prop' => 'marginLeft',    'unitProp' => 'marginLeftUnit',    'sel' => $w],
        ['prop' => 'paddingTop',    'unitProp' => 'paddingTopUnit',    'sel' => $w],
        ['prop' => 'paddingRight',  'unitProp' => 'paddingRightUnit',  'sel' => $w],
        ['prop' => 'paddingBottom', 'unitProp' => 'paddingBottomUnit', 'sel' => $w],
        ['prop' => 'paddingLeft',   'unitProp' => 'paddingLeftUnit',   'sel' => $w],
        ['prop' => 'textAlign',     'sel' => $h],
        ['prop' => 'fontSize',      'unitProp' => 'fontSizeUnit',      'sel' => $h],
        ['prop' => 'lineHeight',    'sel' => $h],
        ['prop' => 'letterSpacing', 'unitProp' => 'letterSpacingUnit', 'sel' => $h],
        ['prop' => 'fontWeight',    'sel' => $h],
    ]);

    $wrapperStyles = [
        'align-self: stretch',
        'text-align: ' . ($s['textAlign'] ?? 'center'),
        'padding-top: ' . ($s['paddingTop'] ?? 20) . ($s['paddingTopUnit'] ?? 'px'),
        'padding-right: ' . ($s['paddingRight'] ?? 0) . ($s['paddingRightUnit'] ?? 'px'),
        'padding-bottom: ' . ($s['paddingBottom'] ?? 20) . ($s['paddingBottomUnit'] ?? 'px'),
        'padding-left: ' . ($s['paddingLeft'] ?? 0) . ($s['paddingLeftUnit'] ?? 'px'),
        'margin-top: ' . ($s['marginTop'] ?? 0) . ($s['marginTopUnit'] ?? 'px'),
        'margin-right: ' . ($s['marginRight'] ?? 0) . ($s['marginRightUnit'] ?? 'px'),
        'margin-bottom: ' . ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px'),
        'margin-left: ' . ($s['marginLeft'] ?? 0) . ($s['marginLeftUnit'] ?? 'px'),
    ];

    $fsRaw = $s['fontSize'] ?? 36;
    $fsCSS = preg_match('/[a-zA-Z%]/', (string)$fsRaw) ? (string)$fsRaw : ($fsRaw . ($s['fontSizeUnit'] ?? 'px'));

    $titleStyles = [
        'font-family: ' . ($s['fontFamily'] ?? 'inherit'),
        'font-size: ' . $fsCSS,
        'font-weight: ' . ($s['fontWeight'] ?? '800'),
        'line-height: ' . ($s['lineHeight'] ?? '1.2'),
        'letter-spacing: ' . ($s['letterSpacing'] ?? 0) . ($s['letterSpacingUnit'] ?? 'px'),
        'text-transform: ' . ($s['textTransform'] ?? 'none'),
        'text-align: ' . ($s['textAlign'] ?? 'center'),
        'margin: 0',
        'transition: color 0.3s ease',
    ];

    $useLink = !empty($s['useLink']) && ($resolvedLinkUrl !== '' || $linkDynamic === 'post_url');

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
        $titleStyles[] = 'color: ' . ($s['titleColor'] ?: '#222');
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

    $htmlTag = in_array($s['htmlTag'] ?? 'h2', ['h1','h2','h3','h4','h5','h6','div','p','span']) ? ($s['htmlTag'] ?? 'h2') : 'h2';

    // Auto-prefix link URL
    $linkUrl = $resolvedLinkUrl;
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
    #{{ $titleElemId }} { color: {{ $s['titleColor'] ?: '#222' }}; transition: color 0.3s ease; }
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
        {{ $titleText }}
    </{{ $htmlTag }}>

    @if($useLink)
        </a>
    @endif

    @if($separator !== 'none')
        <div class="title-divider" style="{{ collect($dividerStyles)->map(fn($v, $k) => "$k: $v")->implode('; ') }}"></div>
    @endif
</div>
