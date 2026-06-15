@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $elemId       = !empty($s['cssId']) ? $s['cssId'] : ('accordion-' . ($el['id'] ?? str_replace('.', '', uniqid('', true))));
    $cssClass     = $s['cssClass'] ?? '';
    $items        = $s['items'] ?? [];
    $defaultOpen  = $s['defaultOpen'] ?? 0;
    $allowMultiple= ($s['allowMultiple'] ?? false) ? 'true' : 'false';
    $iconType     = $s['iconType'] ?? 'plus';
    $iconPosition = $s['iconPosition'] ?? 'right';

    $titleFontSize        = ($s['titleFontSize']       ?? 15) . 'px';
    $titleFontWeight      = $s['titleFontWeight']      ?? '600';
    $titleFontFamily      = $s['titleFontFamily']      ?? 'inherit';
    $titleLetterSpacing   = $s['titleLetterSpacing']   ?? '0px';
    $titleLineHeight      = $s['titleLineHeight']      ?? 1.4;
    $titleTextTransform   = $s['titleTextTransform']   ?? 'none';
    $titleColor           = $s['titleColor']           ?? '#222222';
    $titleBgColor         = $s['titleBgColor']         ?? '#f8fafc';
    $titleActiveBgColor   = $s['titleActiveBgColor']   ?? '#0091ea';
    $titleActiveColor     = $s['titleActiveColor']     ?? '#ffffff';
    $titlePadding         = ($s['titlePadding']        ?? 16) . 'px';

    $contentFontSize      = ($s['contentFontSize']     ?? 14) . 'px';
    $contentFontFamily    = $s['contentFontFamily']    ?? 'inherit';
    $contentLetterSpacing = $s['contentLetterSpacing'] ?? '0px';
    $contentLineHeight    = $s['contentLineHeight']    ?? 1.6;
    $contentColor         = $s['contentColor']         ?? '#555555';
    $contentBgColor       = $s['contentBgColor']       ?? '#ffffff';
    $contentPadding       = ($s['contentPadding']      ?? 16) . 'px';

    $borderColor  = $s['borderColor']  ?? '#e2e8f0';
    $borderRadius = ($s['borderRadius'] ?? 8);
    $itemGap      = ($s['itemGap']     ?? 8) . 'px';

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $outerStyle = "width:100%;margin-top:{$marginTop};margin-bottom:{$marginBottom};";

    // Icon classes
    $iconOpen   = $iconType === 'chevron' ? 'fas fa-chevron-up'  : 'fas fa-minus';
    $iconClosed = $iconType === 'chevron' ? 'fas fa-chevron-down' : 'fas fa-plus';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respId = 'lzr-acc-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$respId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$respId}"],
    ]);
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

<div id="{{ $elemId }}"
     class="lazy-accordion {{ $respId }}{{ $cssClass ? ' '.$cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">

    @foreach($items as $idx => $item)
    @php
        $isOpen       = ((int)$defaultOpen === $idx);
        $titleStyle   = "display:flex;align-items:center;justify-content:space-between;padding:{$titlePadding};font-size:{$titleFontSize};font-weight:{$titleFontWeight};font-family:{$titleFontFamily};letter-spacing:{$titleLetterSpacing};line-height:{$titleLineHeight};text-transform:{$titleTextTransform};cursor:pointer;user-select:none;border:1px solid {$borderColor};";
        $contentStyle = "padding:{$contentPadding};font-size:{$contentFontSize};font-family:{$contentFontFamily};letter-spacing:{$contentLetterSpacing};line-height:{$contentLineHeight};color:{$contentColor};background-color:{$contentBgColor};border:1px solid {$borderColor};border-top:none;";
        if ($isOpen) {
            $titleStyle  .= "color:{$titleActiveColor};background-color:{$titleActiveBgColor};border-radius:{$borderRadius}px {$borderRadius}px 0 0;";
            $contentStyle .= "border-radius:0 0 {$borderRadius}px {$borderRadius}px;";
        } else {
            $titleStyle .= "color:{$titleColor};background-color:{$titleBgColor};border-radius:{$borderRadius}px;";
        }
    @endphp
    <div class="lazy-accordion-item" style="margin-bottom:{{ $itemGap }};">
        <div class="lazy-accordion-header" style="{{ $titleStyle }}" data-open="{{ $isOpen ? 'true' : 'false' }}">
            @if($iconPosition === 'left')
            <span class="lazy-accordion-icon" style="margin-right:10px;flex-shrink:0;">
                <i class="{{ $isOpen ? $iconOpen : $iconClosed }}"></i>
            </span>
            @endif
            <span style="flex:1;">{{ $item['title'] ?? '' }}</span>
            @if($iconPosition !== 'left')
            <span class="lazy-accordion-icon" style="margin-left:10px;flex-shrink:0;">
                <i class="{{ $isOpen ? $iconOpen : $iconClosed }}"></i>
            </span>
            @endif
        </div>
        <div class="lazy-accordion-content" style="{{ $contentStyle }}{{ !$isOpen ? 'display:none;' : '' }}">
            {!! falcon_sanitize_html($item['content'] ?? '') !!}
        </div>
    </div>
    @endforeach

</div>

<script>
(function () {
    var el = document.getElementById('{{ $elemId }}');
    if (!el) return;

    var allowMultiple  = {{ $allowMultiple }};
    var iconType       = '{{ $iconType }}';
    var iconPosition   = '{{ $iconPosition }}';
    var borderRadius   = {{ $borderRadius }};
    var titleColor         = '{{ $titleColor }}';
    var titleBgColor       = '{{ $titleBgColor }}';
    var titleActiveBgColor = '{{ $titleActiveBgColor }}';
    var titleActiveColor   = '{{ $titleActiveColor }}';
    var borderColor        = '{{ $borderColor }}';

    var iconOpen   = iconType === 'chevron' ? 'fas fa-chevron-up'   : 'fas fa-minus';
    var iconClosed = iconType === 'chevron' ? 'fas fa-chevron-down'  : 'fas fa-plus';

    function setHeaderState(header, isOpen) {
        header.setAttribute('data-open', isOpen ? 'true' : 'false');
        if (isOpen) {
            header.style.color            = titleActiveColor;
            header.style.backgroundColor  = titleActiveBgColor;
            header.style.borderRadius     = borderRadius + 'px ' + borderRadius + 'px 0 0';
        } else {
            header.style.color            = titleColor;
            header.style.backgroundColor  = titleBgColor;
            header.style.borderRadius     = borderRadius + 'px';
        }
        var iconEl = header.querySelector('.lazy-accordion-icon i');
        if (iconEl) {
            iconEl.className = isOpen ? iconOpen : iconClosed;
        }
    }

    function setContentState(content, isOpen, borderRadius, borderColor, bgColor) {
        if (isOpen) {
            content.style.display      = '';
            content.style.borderRadius = '0 0 ' + borderRadius + 'px ' + borderRadius + 'px';
        } else {
            content.style.display = 'none';
        }
    }

    var items = el.querySelectorAll('.lazy-accordion-item');
    items.forEach(function (item) {
        var header  = item.querySelector('.lazy-accordion-header');
        var content = item.querySelector('.lazy-accordion-content');
        if (!header || !content) return;

        header.addEventListener('click', function () {
            var isCurrentlyOpen = header.getAttribute('data-open') === 'true';

            if (!allowMultiple) {
                items.forEach(function (otherItem) {
                    var otherHeader  = otherItem.querySelector('.lazy-accordion-header');
                    var otherContent = otherItem.querySelector('.lazy-accordion-content');
                    if (otherHeader && otherContent && otherHeader !== header) {
                        setHeaderState(otherHeader, false);
                        setContentState(otherContent, false, borderRadius, borderColor, '{{ $contentBgColor }}');
                    }
                });
            }

            var willOpen = !isCurrentlyOpen;
            setHeaderState(header, willOpen);
            setContentState(content, willOpen, borderRadius, borderColor, '{{ $contentBgColor }}');
        });
    });
})();
</script>
