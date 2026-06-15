@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $elemId    = !empty($s['cssId']) ? $s['cssId'] : ('icon-box-' . ($el['id'] ?? str_replace('.', '', uniqid('', true))));
    $cssClass  = $s['cssClass'] ?? '';
    $layout    = $s['layout']    ?? 'top';
    $alignment = $s['alignment'] ?? 'center';
    $icon      = $s['icon']      ?? 'fas fa-star';
    $title     = $s['title']     ?? '';
    $desc      = $s['description'] ?? '';
    $linkUrl   = $s['linkUrl']   ?? '';
    $linkTarget = $s['linkTarget'] ?? '_self';

    $iconSize      = ($s['iconSize']   ?? 40) . ($s['iconSizeUnit']   ?? 'px');
    $iconColor     = $s['iconColor']   ?? '#0091ea';
    $iconBgColor   = $s['iconBgColor'] ?? '';
    $iconBgOpacity = $s['iconBgColorOpacity'] ?? 1;
    $iconRadius    = ($s['iconBorderRadius'] ?? 50) . 'px';
    $iconSpacing   = ($s['iconSpacing']  ?? 16) . 'px';
    $iconPadding   = ($s['iconPadding']  ?? 0) . 'px';

    $titleTag           = in_array($s['titleTag'] ?? 'h3', ['h1','h2','h3','h4','h5','h6','p','div']) ? $s['titleTag'] : 'h3';
    $titleFontFamily    = $s['titleFontFamily'] ?? 'inherit';
    $titleSize          = ($s['titleFontSize'] ?? 20) . ($s['titleFontSizeUnit'] ?? 'px');
    $titleWeight        = $s['titleFontWeight'] ?? '600';
    $titleColor         = $s['titleColor']  ?? '#222222';
    $titleGap           = ($s['titleSpacing'] ?? 8) . 'px';
    $titleLineHeight    = $s['titleLineHeight'] ?? 1.3;
    $titleLetterSpacing = $s['titleLetterSpacing'] ?? '0px';
    $titleTransform     = $s['titleTextTransform'] ?? 'none';

    $descFontFamily    = $s['descFontFamily'] ?? 'inherit';
    $descSize          = ($s['descFontSize']  ?? 14) . ($s['descFontSizeUnit'] ?? 'px');
    $descWeight        = $s['descFontWeight'] ?? '400';
    $descColor         = $s['descColor']   ?? '#666666';
    $descLH            = $s['descLineHeight'] ?? 1.6;
    $descLetterSpacing = $s['descLetterSpacing'] ?? '0px';
    $descTransform     = $s['descTextTransform'] ?? 'none';

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respId = 'lzr-ib-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$respId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$respId}"],
    ]);

    // Icon wrapper style
    if ($iconBgColor) {
        $rawSize    = (int)($s['iconSize'] ?? 40);
        $wrapSize   = ($rawSize * 2) . 'px';
        $iconWrapStyle = "display:inline-flex;align-items:center;justify-content:center;box-sizing:content-box;width:{$wrapSize};height:{$wrapSize};background-color:{$iconBgColor};border-radius:{$iconRadius};padding:{$iconPadding};";
    } else {
        $iconWrapStyle = "display:inline-flex;align-items:center;justify-content:center;";
    }

    $titleStyle = "font-family:{$titleFontFamily};font-size:{$titleSize};font-weight:{$titleWeight};color:{$titleColor};margin:0 0 {$titleGap} 0;line-height:{$titleLineHeight};letter-spacing:{$titleLetterSpacing};text-transform:{$titleTransform};";
    $descStyle  = "font-family:{$descFontFamily};font-size:{$descSize};font-weight:{$descWeight};color:{$descColor};line-height:{$descLH};letter-spacing:{$descLetterSpacing};text-transform:{$descTransform};margin:0;";

    $outerStyle = "width:100%;margin-top:{$marginTop};margin-bottom:{$marginBottom};";
    $tag      = $linkUrl ? 'a' : 'div';
    $tagAttrs = $linkUrl ? " href=\"{$linkUrl}\" target=\"{$linkTarget}\"" : '';
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

@if($layout === 'top')
<div id="{{ $elemId }}"
     class="lazy-icon-box lazy-icon-box--top {{ $respId }}{{ $cssClass ? ' '.$cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}text-align:{{ $alignment }};">
    <{{ $tag }}{{ $tagAttrs }}
        class="lazy-icon-box__inner"
        style="display:flex;flex-direction:column;width:100%;align-items:{{ $alignment === 'left' ? 'flex-start' : ($alignment === 'right' ? 'flex-end' : 'center') }};text-decoration:none;color:inherit;">
        @if($icon)
        <div class="lazy-icon-box__icon" style="{{ $iconWrapStyle }}margin-bottom:{{ $iconSpacing }};">
            <i class="{{ $icon }}" style="font-size:{{ $iconSize }};color:{{ $iconColor }};"></i>
        </div>
        @endif
        @if($title)
        <{{ $titleTag }} class="lazy-icon-box__title" style="width:100%;text-align:{{ $alignment }};{{ $titleStyle }}">{{ $title }}</{{ $titleTag }}>
        @endif
        @if($desc)
        <p class="lazy-icon-box__desc" style="width:100%;text-align:{{ $alignment }};{{ $descStyle }}">{{ $desc }}</p>
        @endif
    </{{ $tag }}>
</div>
@else
<div id="{{ $elemId }}"
     class="lazy-icon-box lazy-icon-box--{{ $layout }} {{ $respId }}{{ $cssClass ? ' '.$cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">
    <{{ $tag }}{{ $tagAttrs }}
        class="lazy-icon-box__inner"
        style="display:flex;flex-direction:{{ $layout === 'right' ? 'row-reverse' : 'row' }};width:100%;align-items:flex-start;gap:16px;text-decoration:none;color:inherit;">
        @if($icon)
        <div class="lazy-icon-box__icon" style="{{ $iconWrapStyle }}flex-shrink:0;">
            <i class="{{ $icon }}" style="font-size:{{ $iconSize }};color:{{ $iconColor }};"></i>
        </div>
        @endif
        <div class="lazy-icon-box__content" style="flex:1;">
            @if($title)
            <{{ $titleTag }} class="lazy-icon-box__title" style="{{ $titleStyle }}">{{ $title }}</{{ $titleTag }}>
            @endif
            @if($desc)
            <p class="lazy-icon-box__desc" style="{{ $descStyle }}">{{ $desc }}</p>
            @endif
        </div>
    </{{ $tag }}>
</div>
@endif
