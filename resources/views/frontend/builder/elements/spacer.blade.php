@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $style_type     = $s['style']           ?? 'default';
    $flexGrow       = (int)($s['flexGrow']  ?? 0);
    $marginTop      = ($s['marginTop']      ?? 0) . ($s['marginTopUnit']       ?? 'px');
    $marginBottom   = ($s['marginBottom']   ?? 0) . ($s['marginBottomUnit']    ?? 'px');
    $separatorWidth = ($s['separatorWidth'] ?? 100) . ($s['separatorWidthUnit'] ?? '%');
    $alignment      = $s['alignment']       ?? 'center';
    $borderSize     = (int)($s['borderSize']     ?? 1);
    $separatorColor = $s['separatorColor']  ?? '#cccccc';
    $cssClass       = $s['cssClass']        ?? '';
    $elemId         = 'spacer-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $cssId          = !empty($s['cssId']) ? $s['cssId'] : $elemId;

    $justifyMap     = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
    $justifyContent = $justifyMap[$alignment] ?? 'center';

    $hasLine   = !in_array($style_type, ['default', 'none', 'no_style', '']);
    $isDouble  = str_contains($style_type, 'double');
    $borderStyle = str_contains($style_type, 'dashed') ? 'dashed'
                 : (str_contains($style_type, 'dotted') ? 'dotted' : 'solid');

    $styleParts = ["display:flex", "flex-direction:column", "align-items:{$justifyContent}", "width:100%", "margin-top:{$marginTop}", "margin-bottom:{$marginBottom}"];
    if ($flexGrow) $styleParts[] = "flex-grow:{$flexGrow}";
    $outerStyle = implode('; ', $styleParts);

    $lineStyle = "width:{$separatorWidth}; border:none; border-top:{$borderSize}px {$borderStyle} {$separatorColor}; margin:0;";
@endphp

<div id="{{ $cssId }}"
     class="element-spacer{{ $cssClass ? ' ' . $cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">
    @if($hasLine)
        <hr style="{{ $lineStyle }}">
        @if($isDouble)
            <hr style="{{ $lineStyle }} margin-top:{{ max(2, $borderSize) }}px;">
        @endif
    @endif
</div>
