@php
    /*
     | Content Box — a repeater element that renders its items in one of eight layouts.
     |
     | The canvas twin lives at admin/falcon-builder/partials/components/elements/content-box.blade.php
     | and must mirror every value here; the two rendering systems are independent.
     |
     | Layout families:
     |   classic-*   icon + title + content, four arrangements
     |   clean-*     no icon chrome, typography-led
     |   timeline-*  items threaded on a rail of dots, vertical or horizontal
     */
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $items  = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout = $s['boxLayout'] ?? 'classic-title';
    $isTimelineV = $layout === 'timeline-vertical';
    $isTimelineH = $layout === 'timeline-horizontal';
    $isTimeline  = $isTimelineV || $isTimelineH;

    // Columns. A vertical timeline is always a single column; a horizontal one puts every
    // item on the rail, so its column count follows the item count unless one is set.
    $columns = max(1, (int) ($s['columns'] ?? 1));
    if ($isTimelineV) $columns = 1;
    // A horizontal rail only reads as a timeline when every item sits on it, so anything
    // below 2 columns means "one column per item" — which is what the panel hint promises.
    if ($isTimelineH) $columns = ((int) ($s['columns'] ?? 0)) > 1 ? (int) $s['columns'] : max(1, count($items));

    $align       = in_array($s['alignment'] ?? 'left', ['left', 'center', 'right'], true) ? ($s['alignment'] ?? 'left') : 'left';
    $flexAlign   = $align === 'center' ? 'center' : ($align === 'right' ? 'flex-end' : 'flex-start');
    $iconSide    = ($s['contentAlignment'] ?? 'left') === 'right' ? 'right' : 'left';
    $linkType    = $s['linkType']   ?? 'text';        // text | button
    $linkArea    = $s['linkArea']   ?? 'link';        // link | box
    $linkTarget  = $s['linkTarget'] ?? '_self';

    $colGap = (int) ($s['columnGap'] ?? 30);
    $rowGap = (int) ($s['rowGap']    ?? 30);

    // ── Icon ──────────────────────────────────────────────────────────────────
    $iconSize     = (int) ($s['iconSize'] ?? 32);
    $iconColor    = $s['iconColor']   ?? '#2271b1';
    $iconBgColor  = $s['iconBgColor'] ?? '';
    $iconRadius   = (int) ($s['iconBorderRadius'] ?? 50);
    $iconPadding  = (int) ($s['iconPadding'] ?? 0);
    $iconSpacing  = (int) ($s['iconSpacing'] ?? 16);

    // ── Title ─────────────────────────────────────────────────────────────────
    $titleTag = in_array($s['titleTag'] ?? 'h3', ['h1','h2','h3','h4','h5','h6','p','div'], true) ? ($s['titleTag'] ?? 'h3') : 'h3';
    $titleStyle = 'font-family:' . ($s['titleFontFamily'] ?? 'inherit') . ';'
        . 'font-size:' . getUnitVal($s['titleFontSize'] ?? 20, $s['titleFontSizeUnit'] ?? 'px') . ';'
        . 'font-weight:' . ($s['titleFontWeight'] ?? '600') . ';'
        . 'color:' . ($s['titleColor'] ?? '#222222') . ';'
        . 'line-height:' . ($s['titleLineHeight'] ?? 1.3) . ';'
        . 'letter-spacing:' . ($s['titleLetterSpacing'] ?? '0px') . ';'
        . 'text-transform:' . ($s['titleTextTransform'] ?? 'none') . ';'
        . 'margin:0 0 ' . (isset($s['titleSpacing']) && $s['titleSpacing'] !== '' ? (int) $s['titleSpacing'] : 10) . 'px 0;';

    // ── Content ───────────────────────────────────────────────────────────────
    $contentStyle = 'font-family:' . ($s['contentFontFamily'] ?? 'inherit') . ';'
        . 'font-size:' . getUnitVal($s['contentFontSize'] ?? 15, $s['contentFontSizeUnit'] ?? 'px') . ';'
        . 'font-weight:' . ($s['contentFontWeight'] ?? '400') . ';'
        . 'color:' . ($s['contentColor'] ?? '#666666') . ';'
        . 'line-height:' . ($s['contentLineHeight'] ?? 1.6) . ';'
        . 'letter-spacing:' . ($s['contentLetterSpacing'] ?? '0px') . ';margin:0;';

    // ── Read More ─────────────────────────────────────────────────────────────
    $linkColor      = $s['linkColor'] ?? ($iconColor ?: '#2271b1');
    $linkHoverColor = $s['linkHoverColor'] ?? '';
    $linkGap        = isset($s['linkSpacing']) && $s['linkSpacing'] !== '' ? (int) $s['linkSpacing'] : 14;
    $linkArrow      = ($s['linkArrow'] ?? true) !== false;
    $linkBase = 'font-family:' . ($s['linkFontFamily'] ?? 'inherit') . ';'
        . 'font-size:' . getUnitVal($s['linkFontSize'] ?? 14, $s['linkFontSizeUnit'] ?? 'px') . ';'
        . 'font-weight:' . ($s['linkFontWeight'] ?? '600') . ';'
        . 'text-transform:' . ($s['linkTextTransform'] ?? 'none') . ';'
        . 'text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-top:' . $linkGap . 'px;';
    $linkStyle = $linkType === 'button'
        ? $linkBase . 'color:' . ($s['linkButtonTextColor'] ?? '#ffffff') . ';background:' . $linkColor . ';'
            . 'padding:' . (int) ($s['linkButtonPaddingY'] ?? 10) . 'px ' . (int) ($s['linkButtonPaddingX'] ?? 20) . 'px;'
            . 'border-radius:' . (int) ($s['linkButtonRadius'] ?? 4) . 'px;'
        : $linkBase . 'color:' . $linkColor . ';';

    // ── Box chrome ────────────────────────────────────────────────────────────
    $boxBg      = $s['boxBgColor'] ?? '';
    // Padding. Turning on a background or a border makes the item read as a box, and a box
    // whose content sits flush on the border looks broken — so styling one implies room
    // inside it unless a padding is set explicitly (0 included).
    $padExplicit  = isset($s['boxPadding']) && $s['boxPadding'] !== '';
    $boxHasChrome = ($s['boxBgColor'] ?? '') !== '' || (int) ($s['boxBorderWidth'] ?? 0) > 0;
    $boxPadding = isset($s['boxPadding']) && $s['boxPadding'] !== ''
        ? (int) $s['boxPadding']
        : (($layout === 'classic-boxed' || $boxHasChrome) ? 30 : 0);
    $boxRadius  = (int) ($s['boxBorderRadius'] ?? 0);
    $boxBorderW = (int) ($s['boxBorderWidth'] ?? 0);
    $boxBorderC = $s['boxBorderColor'] ?? '#e5e7eb';
    $boxShadow  = $s['boxShadow'] ?? 'none';
    // Distance from the item's outer edge to its content — what the timeline rail has to
    // reach back across when the box is padded or bordered.
    $boxInset   = $boxPadding + $boxBorderW;
    $shadowCss  = match ($boxShadow) {
        'small'  => 'box-shadow:0 1px 4px rgba(0,0,0,.08);',
        'medium' => 'box-shadow:0 4px 14px rgba(0,0,0,.10);',
        'large'  => 'box-shadow:0 12px 30px rgba(0,0,0,.14);',
        default  => '',
    };

    // ── Timeline rail ─────────────────────────────────────────────────────────
    $tlLineColor = $s['timelineLineColor'] ?? '#e5e7eb';
    $tlLineWidth = max(1, (int) ($s['timelineLineWidth'] ?? 2));
    $tlLineStyle = $s['timelineLineStyle'] ?? 'solid';       // solid | dashed | dotted
    $tlDotSize   = max(6, (int) ($s['timelineDotSize'] ?? 16));
    $tlDotColor  = $s['timelineDotColor'] ?? ($iconColor ?: '#2271b1');
    $tlDotBorder = (int) ($s['timelineDotBorderWidth'] ?? 0);
    $tlDotBorderC = $s['timelineDotBorderColor'] ?? '#ffffff';
    $tlGap       = (int) ($s['timelineGap'] ?? 22);          // rail → body distance
    // Icon sitting IN the dot: it needs its own colour, because the plain icon colour is what
    // the dot is filled with by default — same on same is invisible.
    $tlIconColor = $s['timelineIconColor'] ?? '#ffffff';
    // Every dot is the same size or the rail wouldn't line up, so the box is decided once for
    // the whole element: a bare marker when no item has an icon, otherwise big enough to hold
    // one with breathing room. A glyph is as tall as its font-size, so a dot merely equal to
    // the icon size let the glyph burst out of the circle.
    $tlHasIcon   = false;
    foreach ($items as $__it) {
        if (is_array($__it) && (trim($__it['icon'] ?? '') !== '' || trim($__it['image'] ?? '') !== '')) { $tlHasIcon = true; break; }
    }
    $tlDotBox    = $isTimeline
        ? ($tlHasIcon ? max($tlDotSize, $iconSize + $iconPadding * 2 + 16) : $tlDotSize)
        : $tlDotSize;
    // Keep the glyph inside the circle even when the dot is smaller than the icon setting.
    $tlIconSize  = max(8, min($iconSize, $tlDotBox - 12));

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');
    $cssClass = $s['cssClass'] ?? '';
    $cssId    = !empty($s['cssId']) ? $s['cssId'] : null;

    $uid    = 'fcb-' . str_replace('.', '', uniqid('', true));
    $bpSm   = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed  = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1  = $bpSm + 1;

    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$uid}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$uid}"],
    ]);

    // Grid + rail CSS. Multi-column grids collapse to two columns on tablet and one on
    // mobile, which is what keeps a 4-up row of boxes readable on a phone.
    $css  = ".{$uid}{margin-top:{$marginTop};margin-bottom:{$marginBottom};}";
    $css .= ".{$uid} .fcb-grid{display:grid;grid-template-columns:repeat({$columns},minmax(0,1fr));column-gap:{$colGap}px;row-gap:{$rowGap}px;}";
    // Base display for an item. It matters because Link Area = "Entire Content Box" makes each
    // item an <a>, which is inline by default. This is a stylesheet rule (not inline) so the
    // layouts that need flex can still override it — inline would have beaten them.
    $css .= ".{$uid} .fcb-item{display:block;}";
    if ($columns > 1) {
        $tabletCols = min($columns, 2);
        $css .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){.{$uid} .fcb-grid{grid-template-columns:repeat({$tabletCols},minmax(0,1fr));}}";
        $css .= "@media(max-width:{$bpSm}px){.{$uid} .fcb-grid{grid-template-columns:1fr;}}";
    }
    if ($linkHoverColor !== '') {
        $css .= ".{$uid} .fcb-more:hover{color:" . ($linkType === 'button' ? ($s['linkButtonTextColor'] ?? '#ffffff') : $linkHoverColor) . " !important;";
        if ($linkType === 'button') $css .= "background:{$linkHoverColor} !important;";
        $css .= "}";
    }
    if ($isTimelineV) {
        // The connector runs from the dot down through the row gap into the next item.
        $css .= ".{$uid} .fcb-item{position:relative;display:flex;align-items:flex-start;gap:{$tlGap}px;}";
        $css .= ".{$uid} .fcb-rail{position:relative;flex:0 0 {$tlDotBox}px;width:{$tlDotBox}px;align-self:stretch;}";
        // The connector has to cross this item's bottom inset, the row gap and the next item's
        // top inset — otherwise adding box padding leaves a break between the segments.
        $lineReach = $rowGap + 2 * $boxInset;
        $css .= ".{$uid} .fcb-line{position:absolute;left:50%;transform:translateX(-50%);top:{$tlDotBox}px;bottom:-{$lineReach}px;border-left:{$tlLineWidth}px {$tlLineStyle} {$tlLineColor};}";
        $css .= ".{$uid} .fcb-item:last-child .fcb-line{display:none;}";
    }
    if ($isTimelineH) {
        $css .= ".{$uid} .fcb-item{position:relative;}";
        $railOut = $boxInset + (int) round($colGap / 2);
        $css .= ".{$uid} .fcb-rail-h{display:flex;align-items:center;margin:0 -{$railOut}px {$tlGap}px;}";
        $css .= ".{$uid} .fcb-seg{flex:1 1 auto;border-top:{$tlLineWidth}px {$tlLineStyle} {$tlLineColor};}";
        // The rail should read as one continuous line, so the outer halves are hidden.
        // Trim the rail at the START and END of every ROW, not just of the whole list — with
        // more items than columns the rail wraps, and :first-child/:last-child alone left the
        // pulled-out segments hanging past the grid on each row.
        $segTrim = function (int $cols) use ($uid) {
            return ".{$uid} .fcb-item:nth-child({$cols}n+1) .fcb-seg-l,"
                 . ".{$uid} .fcb-item:nth-child({$cols}n) .fcb-seg-r,"
                 . ".{$uid} .fcb-item:last-child .fcb-seg-r{visibility:hidden;}";
        };
        $css .= $segTrim($columns);
        // The grid collapses at the breakpoints, so the row edges move with it.
        if ($columns > 1) {
            $css .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){" . $segTrim(min($columns, 2)) . "}";
            $css .= "@media(max-width:{$bpSm}px){" . $segTrim(1) . "}";
        }
    }
    $css .= ".{$uid} .fcb-content>*:first-child{margin-top:0;}.{$uid} .fcb-content>*:last-child{margin-bottom:0;}";

    // ── Per-part renderers, shared by every layout ────────────────────────────
    $renderIcon = function (array $item, string $extra = '') use ($iconSize, $iconColor, $iconBgColor, $iconRadius, $iconPadding) {
        $icon  = trim($item['icon'] ?? '');
        $image = trim($item['image'] ?? '');
        if ($icon === '' && $image === '') return '';
        $color = !empty($item['iconColor']) ? $item['iconColor'] : $iconColor;
        $inner = $image !== ''
            ? '<img src="' . e($image) . '" alt="" style="width:' . $iconSize . 'px;height:' . $iconSize . 'px;object-fit:contain;display:block;">'
            : '<i class="' . e($icon) . '" style="font-size:' . $iconSize . 'px;color:' . $color . ';line-height:1;"></i>';
        $wrap = 'display:inline-flex;align-items:center;justify-content:center;box-sizing:content-box;flex-shrink:0;';
        if ($iconBgColor !== '') {
            $box   = $iconSize * 2;
            $wrap .= "width:{$box}px;height:{$box}px;background-color:{$iconBgColor};border-radius:{$iconRadius}px;padding:{$iconPadding}px;";
        }
        return '<div class="fcb-icon" style="' . $extra . $wrap . '">' . $inner . '</div>';
    };
    // The icon inside a timeline dot is a different job from the standalone icon: it must not
    // carry the icon-background box, it takes the dot's contrast colour, and it is sized to
    // fit the circle. Reusing $renderIcon here is what made the dots look broken.
    $renderDotIcon = function (array $item) use ($tlIconSize, $tlIconColor) {
        $icon  = trim($item['icon'] ?? '');
        $image = trim($item['image'] ?? '');
        if ($image !== '') {
            return '<img src="' . e($image) . '" alt="" style="width:' . $tlIconSize . 'px;height:' . $tlIconSize . 'px;object-fit:contain;display:block;">';
        }
        if ($icon === '') return '';
        $color = !empty($item['iconColor']) ? $item['iconColor'] : $tlIconColor;
        return '<i class="' . e($icon) . '" style="font-size:' . $tlIconSize . 'px;color:' . $color . ';line-height:1;"></i>';
    };
    $renderTitle = function (array $item) use ($titleTag, $titleStyle) {
        $t = trim($item['title'] ?? '');
        return $t === '' ? '' : '<' . $titleTag . ' class="fcb-title" style="' . $titleStyle . '">' . e($t) . '</' . $titleTag . '>';
    };
    $renderContent = function (array $item) use ($contentStyle) {
        $c = trim($item['content'] ?? '');
        return $c === '' ? '' : '<div class="fcb-content" style="' . $contentStyle . '">' . falcon_sanitize_html($c) . '</div>';
    };
    // Rendered whenever the item carries Read More text — the setting decides how, not whether.
    // With the whole box as the link (or no URL) it stays a <span>: the item is already an <a>,
    // and an anchor inside an anchor is invalid HTML that browsers silently unnest.
    $renderMore = function (array $item) use ($linkStyle, $linkArrow, $linkArea, $linkTarget) {
        $text = trim($item['linkText'] ?? '');
        if ($text === '') return '';
        $url   = trim($item['linkUrl'] ?? '');
        $arrow = $linkArrow ? ' <span aria-hidden="true">&rarr;</span>' : '';
        if ($url === '' || $linkArea === 'box') {
            return '<span class="fcb-more" style="' . $linkStyle . '">' . e($text) . $arrow . '</span>';
        }
        return '<a class="fcb-more" href="' . e($url) . '" target="' . e($linkTarget) . '" style="' . $linkStyle . '">' . e($text) . $arrow . '</a>';
    };
    $itemBoxStyle = function (array $item) use ($boxBg, $boxPadding, $padExplicit, $boxRadius, $boxBorderW, $boxBorderC, $shadowCss) {
        $bg = !empty($item['bgColor']) ? $item['bgColor'] : $boxBg;
        $st = 'box-sizing:border-box;';
        if ($bg !== '')        $st .= "background-color:{$bg};";
        // A per-item background is chrome as well, so it earns the same breathing room.
        $pad = ($boxPadding === 0 && $bg !== '' && !$padExplicit) ? 30 : $boxPadding;
        if ($pad > 0)          $st .= "padding:{$pad}px;";
        if ($boxRadius > 0)    $st .= "border-radius:{$boxRadius}px;";
        if ($boxBorderW > 0)   $st .= "border:{$boxBorderW}px solid {$boxBorderC};";
        return $st . $shadowCss;
    };
@endphp

@if($respCss || $css)<style>{!! $respCss . $css !!}</style>@endif

<div class="element-content-box {{ $uid }}{{ $cssClass ? ' ' . $cssClass : '' }}{{ $visibilityClasses }}"
     @if($cssId) id="{{ $cssId }}" @endif
     style="width:100%;">
    <div class="fcb-grid">
        @foreach($items as $idx => $item)
            @php
                $item     = is_array($item) ? $item : [];
                $itemUrl  = trim($item['linkUrl'] ?? '');
                $boxLink  = $linkArea === 'box' && $itemUrl !== '';
                $wrapTag  = $boxLink ? 'a' : 'div';
                $wrapAttr = $boxLink ? ' href="' . e($itemUrl) . '" target="' . e($linkTarget) . '"' : '';
                $baseStyle = $itemBoxStyle($item) . ($boxLink ? 'text-decoration:none;color:inherit;' : '');
                $iconHtml    = $renderIcon($item);
                $titleHtml   = $renderTitle($item);
                $contentHtml = $renderContent($item);
                $moreHtml    = $renderMore($item);
            @endphp

            {{-- ── TIMELINE VERTICAL: rail of dots on one side, body beside it ── --}}
            @if($isTimelineV)
                <{{ $wrapTag }}{!! $wrapAttr !!} class="fcb-item" style="{{ $baseStyle }}">
                    <div class="fcb-rail">
                        <span class="fcb-dot" style="display:flex;align-items:center;justify-content:center;width:{{ $tlDotBox }}px;height:{{ $tlDotBox }}px;border-radius:50%;background:{{ $tlDotColor }};box-sizing:border-box;{{ $tlDotBorder > 0 ? 'border:' . $tlDotBorder . 'px solid ' . $tlDotBorderC . ';' : '' }}">
                            {!! $renderDotIcon($item) !!}
                        </span>
                        <span class="fcb-line"></span>
                    </div>
                    <div style="flex:1 1 auto;min-width:0;text-align:{{ $align }};">
                        {!! $titleHtml !!}{!! $contentHtml !!}{!! $moreHtml !!}
                    </div>
                </{{ $wrapTag }}>

            {{-- ── TIMELINE HORIZONTAL: rail across the top, body underneath ── --}}
            @elseif($isTimelineH)
                <{{ $wrapTag }}{!! $wrapAttr !!} class="fcb-item" style="{{ $baseStyle }}text-align:{{ $align }};">
                    <div class="fcb-rail-h">
                        <span class="fcb-seg fcb-seg-l"></span>
                        <span class="fcb-dot" style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:{{ $tlDotBox }}px;height:{{ $tlDotBox }}px;border-radius:50%;background:{{ $tlDotColor }};box-sizing:border-box;{{ $tlDotBorder > 0 ? 'border:' . $tlDotBorder . 'px solid ' . $tlDotBorderC . ';' : '' }}">
                            {!! $renderDotIcon($item) !!}
                        </span>
                        <span class="fcb-seg fcb-seg-r"></span>
                    </div>
                    {!! $titleHtml !!}{!! $contentHtml !!}{!! $moreHtml !!}
                </{{ $wrapTag }}>

            {{-- ── CLASSIC ICON ON SIDE: icon column beside the text column ── --}}
            @elseif($layout === 'classic-side')
                <{{ $wrapTag }}{!! $wrapAttr !!} class="fcb-item" style="{{ $baseStyle }}display:flex;gap:{{ $iconSpacing }}px;align-items:flex-start;flex-direction:{{ $iconSide === 'right' ? 'row-reverse' : 'row' }};">
                    {!! $iconHtml !!}
                    <div style="flex:1 1 auto;min-width:0;text-align:{{ $align }};">
                        {!! $titleHtml !!}{!! $contentHtml !!}{!! $moreHtml !!}
                    </div>
                </{{ $wrapTag }}>

            {{-- ── CLASSIC ICON WITH TITLE: icon sits inline with the heading ── --}}
            @elseif($layout === 'classic-title')
                <{{ $wrapTag }}{!! $wrapAttr !!} class="fcb-item" style="{{ $baseStyle }}text-align:{{ $align }};">
                    <div style="display:flex;align-items:center;gap:{{ $iconSpacing }}px;justify-content:{{ $flexAlign }};margin-bottom:{{ isset($s['titleSpacing']) && $s['titleSpacing'] !== '' ? (int) $s['titleSpacing'] : 10 }}px;">
                        {!! $iconHtml !!}
                        @php $inlineTitle = str_replace('margin:0 0 ', 'margin:0 0 0', $titleStyle); @endphp
                        @if(trim($item['title'] ?? '') !== '')
                            <{{ $titleTag }} class="fcb-title" style="{{ $titleStyle }}margin:0;">{{ $item['title'] }}</{{ $titleTag }}>
                        @endif
                    </div>
                    {!! $contentHtml !!}{!! $moreHtml !!}
                </{{ $wrapTag }}>

            {{-- ── CLEAN LAYOUT HORIZONTAL: title column | content column ── --}}
            @elseif($layout === 'clean-horizontal')
                <{{ $wrapTag }}{!! $wrapAttr !!} class="fcb-item" style="{{ $baseStyle }}display:flex;gap:{{ max($iconSpacing, 20) }}px;align-items:flex-start;">
                    <div style="flex:0 0 34%;max-width:34%;text-align:{{ $align }};">
                        @if($iconHtml !== ''){!! $renderIcon($item, 'margin-bottom:' . $iconSpacing . 'px;') !!}@endif
                        @if(trim($item['title'] ?? '') !== '')
                            <{{ $titleTag }} class="fcb-title" style="{{ $titleStyle }}margin:0;">{{ $item['title'] }}</{{ $titleTag }}>
                        @endif
                    </div>
                    <div style="flex:1 1 auto;min-width:0;text-align:{{ $align }};">
                        {!! $contentHtml !!}{!! $moreHtml !!}
                    </div>
                </{{ $wrapTag }}>

            {{-- ── CLASSIC ON TOP / BOXED / CLEAN VERTICAL: stacked ── --}}
            @else
                <{{ $wrapTag }}{!! $wrapAttr !!} class="fcb-item" style="{{ $baseStyle }}display:flex;flex-direction:column;align-items:{{ $flexAlign }};text-align:{{ $align }};">
                    @if($layout !== 'clean-vertical' && $iconHtml !== ''){!! $renderIcon($item, 'margin-bottom:' . $iconSpacing . 'px;') !!}@endif
                    <div style="width:100%;">
                        {!! $titleHtml !!}{!! $contentHtml !!}{!! $moreHtml !!}
                    </div>
                </{{ $wrapTag }}>
            @endif
        @endforeach
    </div>
</div>
