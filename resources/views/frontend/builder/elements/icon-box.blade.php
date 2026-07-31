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

    // ── Link mode: which part becomes the link (box | icon | title). Defaults to
    //    "box" so existing icon boxes (whole box clickable) keep working. ──────────
    $linkUrl    = $s['linkUrl']    ?? '';
    $linkTarget = $s['linkTarget'] ?? '_self';
    $linkMode   = $s['linkMode']   ?? 'box';
    $hasLink    = $linkUrl !== '';
    $linkBox    = $hasLink && $linkMode === 'box';
    $linkIcon   = $hasLink && $linkMode === 'icon';
    $linkTitle  = $hasLink && $linkMode === 'title';

    // ── Read More / Learn More link (shown under the description) ─────────────────
    $readMoreText  = trim($s['readMoreText'] ?? '');
    $readMoreUrl   = $s['readMoreUrl']   ?? '';

    $iconSize      = ($s['iconSize']   ?? 40) . ($s['iconSizeUnit']   ?? 'px');
    $iconColor     = $s['iconColor']   ?? '#0091ea';
    $iconBgColor   = $s['iconBgColor'] ?? '';
    $iconBgOpacity = $s['iconBgColorOpacity'] ?? 1;
    $iconRadius    = ($s['iconBorderRadius'] ?? 50) . 'px';
    $iconSpacing   = ($s['iconSpacing']  ?? 16) . 'px';
    $iconPadding   = ($s['iconPadding']  ?? 0) . 'px';
    $readMoreColor = $s['readMoreColor'] ?? ($iconColor ?: '#2271b1');

    $titleTag           = in_array($s['titleTag'] ?? 'h3', ['h1','h2','h3','h4','h5','h6','p','div']) ? ($s['titleTag'] ?? 'h3') : 'h3';
    $titleFontFamily    = $s['titleFontFamily'] ?? 'inherit';
    $titleSize          = getUnitVal($s['titleFontSize'] ?? 20, $s['titleFontSizeUnit'] ?? 'px');
    $titleWeight        = $s['titleFontWeight'] ?? '600';
    $titleColor         = $s['titleColor']  ?? '#222222';
    $titleGap           = ($s['titleSpacing'] ?? 8) . 'px';
    $titleLineHeight    = $s['titleLineHeight'] ?? 1.3;
    $titleLetterSpacing = $s['titleLetterSpacing'] ?? '0px';
    $titleTransform     = $s['titleTextTransform'] ?? 'none';

    $descFontFamily    = $s['descFontFamily'] ?? 'inherit';
    $descSize          = getUnitVal($s['descFontSize'] ?? 14, $s['descFontSizeUnit'] ?? 'px');
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

    // Icon wrapper style (shared by top / left / right)
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

    // ── Reusable part renderers (keep link-mode + read-more logic in one place) ───
    $renderIcon = function ($extra = '') use ($icon, $iconWrapStyle, $iconSize, $iconColor, $linkIcon, $linkUrl, $linkTarget) {
        if ($icon === '') return '';
        $node = '<div class="lazy-icon-box__icon" style="' . $extra . $iconWrapStyle . '"><i class="' . e($icon) . '" style="font-size:' . $iconSize . ';color:' . $iconColor . ';"></i></div>';
        if ($linkIcon) $node = '<a href="' . e($linkUrl) . '" target="' . e($linkTarget) . '" style="text-decoration:none;display:inline-flex;">' . $node . '</a>';
        return $node;
    };
    $renderTitle = function ($extra = '') use ($titleTag, $titleStyle, $title, $linkTitle, $linkUrl, $linkTarget) {
        if ($title === '') return '';
        $o = $linkTitle ? '<a href="' . e($linkUrl) . '" target="' . e($linkTarget) . '" style="text-decoration:none;color:inherit;">' : '';
        $c = $linkTitle ? '</a>' : '';
        return '<' . $titleTag . ' class="lazy-icon-box__title" style="' . $extra . $titleStyle . '">' . $o . e($title) . $c . '</' . $titleTag . '>';
    };
    $renderDesc = function ($extra = '') use ($descStyle, $desc) {
        if ($desc === '') return '';
        return '<p class="lazy-icon-box__desc" style="' . $extra . $descStyle . '">' . e($desc) . '</p>';
    };
    $renderMore = function ($extra = '') use ($readMoreText, $readMoreUrl, $readMoreColor) {
        if ($readMoreText === '') return '';
        $st = $extra . 'font-weight:600;color:' . $readMoreColor . ';text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-top:12px;';
        if ($readMoreUrl !== '') return '<a href="' . e($readMoreUrl) . '" class="lazy-icon-box__more" style="' . $st . '">' . e($readMoreText) . ' <span aria-hidden="true">&rarr;</span></a>';
        return '<span class="lazy-icon-box__more" style="' . $st . '">' . e($readMoreText) . '</span>';
    };

    // Whole-box link wrapper (link mode = box)
    $innerTag   = $linkBox ? 'a' : 'div';
    $innerAttrs = $linkBox ? ' href="' . e($linkUrl) . '" target="' . e($linkTarget) . '"' : '';
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

@if($layout === 'top')
<div id="{{ $elemId }}"
     class="lazy-icon-box lazy-icon-box--top {{ $respId }}{{ $cssClass ? ' '.$cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}text-align:{{ $alignment }};">
    <{{ $innerTag }}{!! $innerAttrs !!}
        class="lazy-icon-box__inner"
        style="display:flex;flex-direction:column;width:100%;align-items:{{ $alignment === 'left' ? 'flex-start' : ($alignment === 'right' ? 'flex-end' : 'center') }};text-decoration:none;color:inherit;">
        {!! $renderIcon('margin-bottom:' . $iconSpacing . ';') !!}
        {!! $renderTitle('width:100%;text-align:' . $alignment . ';') !!}
        {!! $renderDesc('width:100%;text-align:' . $alignment . ';') !!}
        {!! $renderMore() !!}
    </{{ $innerTag }}>
</div>
@else
{{-- left / right --}}
<div id="{{ $elemId }}"
     class="lazy-icon-box lazy-icon-box--{{ $layout }} {{ $respId }}{{ $cssClass ? ' '.$cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">
    <{{ $innerTag }}{!! $innerAttrs !!}
        class="lazy-icon-box__inner"
        style="display:flex;flex-direction:{{ $layout === 'right' ? 'row-reverse' : 'row' }};width:100%;align-items:flex-start;gap:16px;text-decoration:none;color:inherit;">
        {!! $renderIcon('flex-shrink:0;') !!}
        <div class="lazy-icon-box__content" style="flex:1;min-width:0;">
            {!! $renderTitle() !!}
            {!! $renderDesc() !!}
            {!! $renderMore() !!}
        </div>
    </{{ $innerTag }}>
</div>
@endif
