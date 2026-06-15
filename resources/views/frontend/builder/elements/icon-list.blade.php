@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $items        = $s['items']        ?? [];
    $iconSize     = (int)($s['iconSize']  ?? 14);
    $iconColor    = $s['iconColor']    ?? '#0091ea';
    $iconPosition = $s['iconPosition'] ?? 'left';
    $gap          = (int)($s['gap']        ?? 10);
    $itemSpacing  = (int)($s['itemSpacing'] ?? 10);
    $textColor    = $s['textColor']    ?? '#333333';
    $fontSize     = ($s['fontSize']    ?? 15) . ($s['fontSizeUnit'] ?? 'px');
    $fontWeight   = $s['fontWeight']   ?? '400';
    $fontFamily   = $s['fontFamily']   ?? 'inherit';
    $lineHeight   = $s['lineHeight']   ?? '1.5';
    $textAlign    = $s['textAlign']    ?? 'left';
    $marginTop    = ($s['marginTop']   ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');
    $cssClass     = $s['cssClass']     ?? '';
    $cssId        = !empty($s['cssId']) ? $s['cssId'] : null;

    $flexDir = $iconPosition === 'right' ? 'row-reverse' : 'row';
    $justify = $textAlign === 'center' ? 'center' : ($iconPosition === 'right' ? ($textAlign === 'right' ? 'flex-start' : 'flex-end') : ($textAlign === 'right' ? 'flex-end' : 'flex-start'));
    $wrapStyle = "width:100%;margin-top:{$marginTop};margin-bottom:{$marginBottom};";

    $elemId = 'icon-list-' . str_replace('.', '', uniqid('', true));
    $bpSm   = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed  = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');

    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".icon-list-{$elemId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".icon-list-{$elemId}"],
    ]);
@endphp

@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif
<div class="element-icon-list icon-list-{{ $elemId }} {{ $cssClass }} {{ $visibilityClasses }}"
     @if($cssId) id="{{ $cssId }}" @endif
     style="{{ $wrapStyle }}">
    <ul style="list-style:none;padding:0;margin:0;">
        @foreach($items as $idx => $item)
            @php
                $itemIcon      = !empty($item['icon']) ? $item['icon'] : ($s['defaultIcon'] ?? 'fa fa-check');
                $itemIconColor = !empty($item['iconColor']) ? $item['iconColor'] : $iconColor;
                $itemText      = $item['text']      ?? '';
                $itemLink      = $item['link']      ?? '';
                $itemTarget    = $item['linkTarget'] ?? '_self';
                $isLast        = $idx === count($items) - 1;
                $liStyle       = $isLast ? '' : "margin-bottom:{$itemSpacing}px;";
                $flexStyle     = "display:flex;align-items:center;flex-direction:{$flexDir};justify-content:{$justify};gap:{$gap}px;";
                $iconStyle     = "color:{$itemIconColor};font-size:{$iconSize}px;flex-shrink:0;width:{$iconSize}px;text-align:center;line-height:{$lineHeight};";
                $textStyle     = "color:{$textColor};font-size:{$fontSize};font-weight:{$fontWeight};font-family:{$fontFamily};line-height:{$lineHeight};";
            @endphp
            <li style="{{ $liStyle }}">
                @if($itemLink)
                    <a href="{{ $itemLink }}" target="{{ $itemTarget }}"
                       style="{{ $flexStyle }}text-decoration:none;">
                        <i class="{{ $itemIcon }}" style="{{ $iconStyle }}"></i>
                        <span style="{{ $textStyle }}">{{ $itemText }}</span>
                    </a>
                @else
                    <span style="{{ $flexStyle }}">
                        <i class="{{ $itemIcon }}" style="{{ $iconStyle }}"></i>
                        <span style="{{ $textStyle }}">{{ $itemText }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
