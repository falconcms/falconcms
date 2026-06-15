@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' lazy-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');

    $elemId = 'img-' . str_replace('.', '', uniqid('', true));

    $dynamicSrc = $s['dynamic_source'] ?? '';
    if ($dynamicSrc === 'feature_image') {
        $url = $postFeaturedImage ?? $s['url'] ?? $s['src'] ?? '';
    } elseif ($dynamicSrc === 'author_avatar') {
        $url = $authorAvatar ?? $s['url'] ?? $s['src'] ?? '';
    } elseif ($dynamicSrc === 'logo') {
        $url = get_cms_option('theme_site_logo', '') ?: ($s['url'] ?? $s['src'] ?? '');
    } else {
        $url = $s['url'] ?? $s['src'] ?? '';
    }
    $alt      = $s['alt']        ?? '';
    $linkDynamic = $s['link_dynamic_source'] ?? '';
    $linkUrl = $linkDynamic
        ? (function_exists('falcon_resolve_dynamic_value') ? (falcon_resolve_dynamic_value($linkDynamic, $post ?? null) ?: ($s['linkUrl'] ?? '')) : ($postPermalink ?? $s['linkUrl'] ?? ''))
        : ($s['linkUrl'] ?? '');
    $target   = $s['linkTarget'] ?? '_self';
    $hoverType  = $s['hoverType'] ?? 'none';
    $hoverClass = ($hoverType !== 'none') ? 'hover-' . $hoverType : '';

    $iw = '.image-wrap-' . $elemId;
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'textAlign',    'sel' => $iw],
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => $iw],
        ['prop' => 'marginRight',  'unitProp' => 'marginRightUnit',  'sel' => $iw],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => $iw],
        ['prop' => 'marginLeft',   'unitProp' => 'marginLeftUnit',   'sel' => $iw],
    ]);

    // Desktop wrapper style
    $align = $s['textAlign'] ?? $s['align'] ?? 'center';
    $wrapperStyle = "width:100%;text-align:{$align};";
    if (isset($s['marginTop'])    && $s['marginTop']    !== '') $wrapperStyle .= "margin-top:{$s['marginTop']}" . ($s['marginTopUnit'] ?? 'px') . ";";
    if (isset($s['marginRight'])  && $s['marginRight']  !== '') $wrapperStyle .= "margin-right:{$s['marginRight']}" . ($s['marginRightUnit'] ?? 'px') . ";";
    if (isset($s['marginBottom']) && $s['marginBottom'] !== '') $wrapperStyle .= "margin-bottom:{$s['marginBottom']}" . ($s['marginBottomUnit'] ?? 'px') . ";";
    if (isset($s['marginLeft'])   && $s['marginLeft']   !== '') $wrapperStyle .= "margin-left:{$s['marginLeft']}" . ($s['marginLeftUnit'] ?? 'px') . ";";

    $aspectRatio = $s['aspectRatio'] ?? 'none';
    $hasRatio    = ($aspectRatio && $aspectRatio !== 'none');
    $focusX      = (int)($s['focusX'] ?? 50);
    $focusY      = (int)($s['focusY'] ?? 50);

    // Style for wrapper element (<a> or <div> when hasRatio; applied to <img> when no ratio + no link)
    $elemStyle = "display:inline-block;max-width:100%;vertical-align:middle;overflow:hidden;";
    if (!empty($s['width']))    $elemStyle .= "width:{$s['width']}"    . ($s['widthUnit']    ?? 'px') . ";";
    if (!empty($s['maxWidth'])) $elemStyle .= "max-width:{$s['maxWidth']}" . ($s['maxWidthUnit'] ?? 'px') . ";";

    $br = $s['borderRadius'] ?? '0';
    if ($br !== '' && $br !== '0') {
        $elemStyle .= "border-radius:{$br}" . ($s['borderRadiusUnit'] ?? 'px') . ";";
    }
    $bTop    = (int)($s['borderSizeTop']    ?? 0);
    $bRight  = (int)($s['borderSizeRight']  ?? 0);
    $bBottom = (int)($s['borderSizeBottom'] ?? 0);
    $bLeft   = (int)($s['borderSizeLeft']   ?? 0);
    $bColor  = $s['borderColor'] ?? 'transparent';
    if ($bTop || $bRight || $bBottom || $bLeft) {
        $elemStyle .= "border-style:solid;border-color:{$bColor};";
        if ($bTop)    $elemStyle .= "border-top-width:{$bTop}px;";
        if ($bRight)  $elemStyle .= "border-right-width:{$bRight}px;";
        if ($bBottom) $elemStyle .= "border-bottom-width:{$bBottom}px;";
        if ($bLeft)   $elemStyle .= "border-left-width:{$bLeft}px;";
    }

    if ($hasRatio) {
        $elemStyle .= "aspect-ratio:{$aspectRatio};height:auto;";
        $imgStyle   = "display:block;width:100%;height:100%;object-fit:cover;object-position:{$focusX}% {$focusY}%;";
    } else {
        $elemStyle .= "height:auto;";
        $imgStyle   = "display:block;width:100%;height:auto;";
    }

    $stickyWidth     = $s['stickyWidth']     ?? null;
    $stickyWidthUnit = $s['stickyWidthUnit'] ?? 'px';
    $stickyCss = '';
    if ($stickyWidth !== null && $stickyWidth !== '') {
        $sw = $stickyWidth . $stickyWidthUnit;
        // Transition when inside any sticky col (so the change animates both ways)
        $stickyCss  = ".lazy-sticky-col .image-wrap-{$elemId}>img,.lazy-sticky-col .image-wrap-{$elemId}>a{transition:width 0.4s ease,max-width 0.4s ease}";
        // Width override fires only when JS marks the element as actually stuck
        $stickyCss .= ".lazy-sticky-active .image-wrap-{$elemId}>img,.lazy-sticky-active .image-wrap-{$elemId}>a{width:{$sw}!important;max-width:{$sw}!important}";
    }
@endphp

@if($respCss || $stickyCss)
<style>{!! $respCss . $stickyCss !!}</style>
@endif

<div class="element-image image-wrap-{{ $elemId }} {{ $hoverClass }} {{ $visibilityClasses }}"
     style="{{ $wrapperStyle }}">
    @if($url)
        @if($linkUrl)
            <a href="{{ $linkUrl }}" target="{{ $target }}" style="{{ $elemStyle }}text-decoration:none;">
                <img src="{{ $url }}" alt="{{ $alt }}" style="{{ $imgStyle }}">
            </a>
        @elseif($hasRatio)
            <div style="{{ $elemStyle }}font-size:0;line-height:0;">
                <img src="{{ $url }}" alt="{{ $alt }}" style="{{ $imgStyle }}">
            </div>
        @else
            <img src="{{ $url }}" alt="{{ $alt }}" style="{{ $elemStyle }}">
        @endif
    @else
        <div style="background:#f0f0f1;border:2px dashed #c3c4c7;padding:40px 20px;text-align:center;color:#8c8f94;font-size:13px;border-radius:4px;">
            No image selected
        </div>
    @endif
</div>
