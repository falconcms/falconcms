@php
    /*
    |--------------------------------------------------------------------------
    | Section Separator — frontend renderer
    |--------------------------------------------------------------------------
    | Three style families, all driven by FalconCms\Core\Support\SeparatorShapes:
    |   - line     : a CSS border-style rule (solid, double, dashed, …)
    |   - pattern_*: a small SVG tile repeated along X as a background image
    |   - shape_*  : one full-width silhouette stretched to the element width
    |
    | The admin canvas preview receives the exact same tables as JSON, so the two
    | renderers cannot drift. See:
    |   resources/views/admin/falcon-builder/partials/components/elements/section-separator.blade.php
    |   resources/views/admin/falcon-builder/partials/scripts.blade.php  (sepXxx helpers)
    */
    use FalconCms\Core\Support\SeparatorShapes;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    // ── Settings ─────────────────────────────────────────────────────────────
    $sepStyle    = $s['sepStyle']     ?? 'solid';
    $sepWidth    = $s['sepWidth']     ?? 100;
    $sepWidthU   = $s['sepWidthUnit'] ?? '%';
    $sepAlign    = $s['sepAlign']     ?? 'center';
    $sepWeight   = max(1, (float) ($s['sepWeight'] ?? 1));
    $sepColor    = $s['sepColor']     ?? '#e2e8f0';
    $patHeight   = max(1, (int) ($s['patHeight']  ?? 20));
    $patSpacing  = max(1, (int) ($s['patSpacing'] ?? 20));

    $shapeHeight = max(4, (int) ($s['shapeHeight'] ?? 60));
    $shapeFlipH  = !empty($s['shapeFlipH']);
    $shapeFlipV  = !empty($s['shapeFlipV']);

    $svgRecolor  = ($s['svgRecolor'] ?? true) !== false;
    $svgStretch  = ($s['svgStretch'] ?? true) !== false;

    $sepContent  = $s['sepContent']   ?? 'none';
    $sepText     = (string) ($s['sepText'] ?? '');
    $sepIcon     = $s['sepIcon']      ?? '';
    $contentPos  = $s['contentPos']   ?? 'center';
    $contentGap  = (int) ($s['contentGap'] ?? 15);

    $textColor   = $s['textColor']    ?? '#333333';
    $iconSize    = (int) ($s['iconSize'] ?? 20);
    $iconColor   = $s['iconColor']    ?? '#333333';
    $iconRotate  = (int) ($s['iconRotate'] ?? 0);
    $iconView    = $s['iconView']     ?? 'default';
    $iconShape   = $s['iconShape']    ?? 'circle';
    $iconBg      = $s['iconBgColor']  ?? '#f1f5f9';
    $iconBorderC = $s['iconBorderColor'] ?? '#e2e8f0';
    $iconBorderW = (int) ($s['iconBorderWidth'] ?? 1);
    $iconPadding = (int) ($s['iconPadding'] ?? 10);

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $cssClass = $s['cssClass'] ?? '';
    $elemId   = 'separator-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $cssId    = !empty($s['cssId']) ? $s['cssId'] : $elemId;

    // ── Which family? ────────────────────────────────────────────────────────
    $patterns  = SeparatorShapes::patterns();
    $shapes    = SeparatorShapes::shapes();
    $customSvg = $sepStyle === 'custom_svg'
        ? SeparatorShapes::customSvgMarkup($s['customSvg'] ?? '', $svgStretch)
        : '';
    $isCustom  = $customSvg !== '';
    $isPattern = str_starts_with($sepStyle, 'pattern_') && isset($patterns[substr($sepStyle, 8)]);
    $isShape   = str_starts_with($sepStyle, 'shape_')   && isset($shapes[substr($sepStyle, 6)]);
    $hasLine   = $sepStyle !== 'none' && !$isShape && $sepStyle !== 'custom_svg'
               && (str_starts_with($sepStyle, 'pattern_') ? $isPattern : true);

    $hasContent = ($sepContent === 'text' && $sepText !== '')
               || ($sepContent === 'icon' && $sepIcon !== '');

    $justifyMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
    $justify    = $justifyMap[$sepAlign] ?? 'center';

    $outerStyle = 'display:flex; width:100%; align-items:center; justify-content:' . $justify . ';'
                . ' margin-top:' . $marginTop . '; margin-bottom:' . $marginBottom . ';';

    // ── Line / pattern geometry ──────────────────────────────────────────────
    $lineStyle = 'flex-shrink:0; font-size:0; line-height:0;';
    if (!$hasLine) {
        $lineStyle .= ' height:0; border:none;';
    } elseif ($isPattern) {
        [$vbW, $vbH, $inner] = $patterns[substr($sepStyle, 8)];
        $inner = str_replace(['{C}', '{W}'], [$sepColor, (string) ($sepWeight * 2)], $inner);
        $svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $vbW . ' ' . $vbH . '" preserveAspectRatio="none">' . $inner . '</svg>';
        $lineStyle .= ' height:' . $patHeight . 'px; border:none;'
                    . ' background-image:url(data:image/svg+xml,' . rawurlencode($svg) . ');'
                    . ' background-repeat:repeat-x; background-position:center center;'
                    . ' background-size:' . $patSpacing . 'px ' . $patHeight . 'px;';
    } else {
        $lineStyle .= ' height:0; border:none; border-top:' . $sepWeight . 'px ' . $sepStyle . ' ' . $sepColor . ';';
    }

    // Flex ratios so the content can sit left / centre / right on the line.
    $growLeft  = $hasContent && $contentPos === 'left'  ? '0 0 6%' : '1 1 0%';
    $growRight = $hasContent && $contentPos === 'right' ? '0 0 6%' : '1 1 0%';

    $innerStyle = 'display:flex; align-items:center; max-width:100%; width:' . $sepWidth . $sepWidthU . ';'
                . ($hasContent ? ' gap:' . $contentGap . 'px;' : '');

    // ── Shape geometry ───────────────────────────────────────────────────────
    $shapeWrapStyle = 'position:relative; max-width:100%; line-height:0; width:' . $sepWidth . $sepWidthU . ';';
    $shapeSvgStyle  = 'display:block; width:100%; height:' . $shapeHeight . 'px;';
    if ($shapeFlipH || $shapeFlipV) {
        $shapeSvgStyle .= ' transform:scale(' . ($shapeFlipH ? -1 : 1) . ',' . ($shapeFlipV ? -1 : 1) . ');';
    }
    $shapeOverlayStyle = 'position:absolute; top:0; right:0; bottom:0; left:0; display:flex;'
                       . ' align-items:center; justify-content:' . ($justifyMap[$contentPos] ?? 'center') . ';'
                       . ' padding:0 ' . max(12, $contentGap) . 'px; line-height:normal;';

    // ── Content (text / icon) ────────────────────────────────────────────────
    $textStyle = 'display:inline-block; white-space:nowrap; color:' . $textColor . ';'
               . ' font-family:'    . (($s['sep_text_family']         ?? '') ?: 'inherit') . ';'
               . ' font-weight:'    . (($s['sep_text_weight']         ?? '') ?: '400') . ';'
               . ' font-size:'      . (($s['sep_text_size']           ?? '') ?: '15px') . ';'
               . ' line-height:'    . (($s['sep_text_line_height']    ?? '') ?: '1.4') . ';'
               . ' letter-spacing:' . (($s['sep_text_letter_spacing'] ?? '') ?: 'normal') . ';'
               . ' text-transform:' . (($s['sep_text_transform']      ?? '') ?: 'none') . ';';

    $iconStyle = 'font-size:' . $iconSize . 'px; line-height:1; color:' . $iconColor . ';'
               . ($iconRotate ? ' transform:rotate(' . $iconRotate . 'deg);' : '');

    $iconWrapStyle = 'display:inline-flex; align-items:center; justify-content:center; box-sizing:content-box;';
    if ($iconView === 'stacked') {
        $iconWrapStyle .= ' background-color:' . $iconBg . '; padding:' . $iconPadding . 'px;'
                        . ' border-radius:' . ($iconShape === 'circle' ? '50%' : '4px') . ';';
    } elseif ($iconView === 'framed') {
        $iconWrapStyle .= ' border:' . $iconBorderW . 'px solid ' . $iconBorderC . '; padding:' . $iconPadding . 'px;'
                        . ' border-radius:' . ($iconShape === 'circle' ? '50%' : '4px') . ';';
    }
@endphp

<div id="{{ $cssId }}"
     class="element-section-separator falcon-separator{{ $cssClass ? ' ' . $cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">

    @if($isCustom)
        <div class="falcon-separator-shape-wrap" style="{{ $shapeWrapStyle }}">
            <style>{!! SeparatorShapes::customSvgCss($cssId, $sepColor, $svgRecolor) !!}</style>
            <div class="falcon-separator-custom" style="{{ $shapeSvgStyle }}">{!! $customSvg !!}</div>

            @if($hasContent)
                <div class="falcon-separator-content" style="{{ $shapeOverlayStyle }}">
                    @if($sepContent === 'text')
                        <span class="falcon-separator-text" style="{{ $textStyle }}">{!! $sepText !!}</span>
                    @else
                        <span class="falcon-separator-icon-wrap" style="{{ $iconWrapStyle }}">
                            <i class="{{ $sepIcon }}" style="{{ $iconStyle }}"></i>
                        </span>
                    @endif
                </div>
            @endif
        </div>
    @elseif($isShape)
        <div class="falcon-separator-shape-wrap" style="{{ $shapeWrapStyle }}">
            <svg class="falcon-separator-shape"
                 viewBox="0 0 {{ SeparatorShapes::VIEW_W }} {{ SeparatorShapes::VIEW_H }}"
                 preserveAspectRatio="none" aria-hidden="true" focusable="false"
                 style="{{ $shapeSvgStyle }}">
                @foreach($shapes[substr($sepStyle, 6)] as $layer)
                    <path d="{{ $layer['d'] }}" fill="{{ $sepColor }}"@if($layer['o'] < 1) opacity="{{ $layer['o'] }}"@endif></path>
                @endforeach
            </svg>

            @if($hasContent)
                <div class="falcon-separator-content" style="{{ $shapeOverlayStyle }}">
                    @if($sepContent === 'text')
                        <span class="falcon-separator-text" style="{{ $textStyle }}">{!! $sepText !!}</span>
                    @else
                        <span class="falcon-separator-icon-wrap" style="{{ $iconWrapStyle }}">
                            <i class="{{ $sepIcon }}" style="{{ $iconStyle }}"></i>
                        </span>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="falcon-separator-inner" style="{{ $innerStyle }}">
            <div class="falcon-separator-line" style="flex:{{ $growLeft }}; {{ $lineStyle }}"></div>

            @if($hasContent)
                <div class="falcon-separator-content" style="flex:0 0 auto; display:inline-flex; align-items:center; line-height:1;">
                    @if($sepContent === 'text')
                        <span class="falcon-separator-text" style="{{ $textStyle }}">{!! $sepText !!}</span>
                    @else
                        <span class="falcon-separator-icon-wrap" style="{{ $iconWrapStyle }}">
                            <i class="{{ $sepIcon }}" style="{{ $iconStyle }}"></i>
                        </span>
                    @endif
                </div>

                <div class="falcon-separator-line" style="flex:{{ $growRight }}; {{ $lineStyle }}"></div>
            @endif
        </div>
    @endif
</div>
