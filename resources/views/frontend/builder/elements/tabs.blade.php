@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $elemId        = !empty($s['cssId']) ? $s['cssId'] : ('tabs-' . ($el['id'] ?? str_replace('.', '', uniqid('', true))));
    $cssClass      = $s['cssClass'] ?? '';
    $items         = $s['items'] ?? [];
    $defaultActive = (int)($s['defaultActive'] ?? 0);
    $style         = $s['style']     ?? 'underline';
    $alignment     = $s['alignment'] ?? 'left';

    $tabFontSize      = ($s['tabFontSize']      ?? 14) . 'px';
    $tabFontWeight    = $s['tabFontWeight']    ?? '500';
    $tabFontFamily    = $s['tabFontFamily']    ?? 'inherit';
    $tabLetterSpacing = $s['tabLetterSpacing'] ?? '0px';
    $tabColor         = $s['tabColor']         ?? '#666666';
    $activeColor      = $s['activeColor']      ?? '#0091ea';

    $contentFontSize      = ($s['contentFontSize']     ?? 14) . 'px';
    $contentFontFamily    = $s['contentFontFamily']    ?? 'inherit';
    $contentLetterSpacing = $s['contentLetterSpacing'] ?? '0px';
    $contentLineHeight    = $s['contentLineHeight']    ?? 1.6;
    $contentColor         = $s['contentColor']         ?? '#555555';
    $contentBgColor       = $s['contentBgColor']       ?? '#ffffff';
    $contentPadding       = ($s['contentPadding']      ?? 20) . 'px';

    $borderColor  = $s['borderColor']  ?? '#e2e8f0';
    $borderRadius = (int)($s['borderRadius'] ?? 4);

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $outerStyle = "width:100%;margin-top:{$marginTop};margin-bottom:{$marginBottom};";

    // Nav alignment style
    $alignStyle = 'flex-start';
    if ($alignment === 'center') $alignStyle = 'center';
    if ($alignment === 'right')  $alignStyle = 'flex-end';

    // Nav wrapper style
    if ($style === 'underline') {
        $navStyle = "display:flex;justify-content:{$alignStyle};flex-wrap:wrap;border-bottom:2px solid {$borderColor};";
    } elseif ($style === 'pill') {
        $navStyle = "display:flex;justify-content:{$alignStyle};flex-wrap:wrap;gap:6px;padding:4px 0;";
    } else { // boxed
        $navStyle = "display:flex;justify-content:{$alignStyle};flex-wrap:wrap;border-bottom:1px solid {$borderColor};";
    }

    // Content panel style
    $panelTypography = "font-family:{$contentFontFamily};letter-spacing:{$contentLetterSpacing};line-height:{$contentLineHeight};";
    if ($style === 'pill') {
        $panelStyle = "padding:{$contentPadding};font-size:{$contentFontSize};{$panelTypography}color:{$contentColor};background-color:{$contentBgColor};border:1px solid {$borderColor};border-radius:{$borderRadius}px;";
    } else {
        $panelStyle = "padding:{$contentPadding};font-size:{$contentFontSize};{$panelTypography}color:{$contentColor};background-color:{$contentBgColor};border:1px solid {$borderColor};border-top:none;border-radius:0 0 {$borderRadius}px {$borderRadius}px;";
    }

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respId = 'lzr-tab-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$respId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$respId}"],
    ]);
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

<div id="{{ $elemId }}"
     class="lazy-tabs {{ $respId }}{{ $cssClass ? ' '.$cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">

    {{-- Tab Nav --}}
    <div class="lazy-tabs-nav" style="{{ $navStyle }}">
        @foreach($items as $idx => $item)
        @php
            $isActive = ($idx === $defaultActive);
            $btnTypography = "font-family:{$tabFontFamily};letter-spacing:{$tabLetterSpacing};";
            if ($style === 'underline') {
                $btnStyle = "display:inline-block;padding:8px 16px;font-size:{$tabFontSize};font-weight:{$tabFontWeight};{$btnTypography}cursor:pointer;border:none;background:transparent;margin-bottom:-2px;transition:all 0.2s;";
                $btnStyle .= $isActive
                    ? "color:{$activeColor};border-bottom:2px solid {$activeColor};"
                    : "color:{$tabColor};border-bottom:2px solid transparent;";
            } elseif ($style === 'pill') {
                $btnStyle = "display:inline-block;padding:8px 16px;font-size:{$tabFontSize};font-weight:{$tabFontWeight};{$btnTypography}cursor:pointer;border:none;border-radius:999px;transition:all 0.2s;";
                $btnStyle .= $isActive
                    ? "background-color:{$activeColor};color:#ffffff;"
                    : "background-color:transparent;color:{$tabColor};";
            } else { // boxed
                $btnStyle = "display:inline-block;padding:8px 16px;font-size:{$tabFontSize};font-weight:{$tabFontWeight};{$btnTypography}cursor:pointer;border-radius:{$borderRadius}px {$borderRadius}px 0 0;transition:all 0.2s;";
                $btnStyle .= $isActive
                    ? "border:1px solid {$borderColor};border-bottom-color:{$contentBgColor};background-color:{$contentBgColor};color:{$activeColor};margin-bottom:-1px;"
                    : "border:1px solid transparent;background-color:transparent;color:{$tabColor};";
            }
        @endphp
        <button class="lazy-tab-btn"
                data-target="{{ $elemId }}-panel-{{ $idx }}"
                data-active="{{ $isActive ? 'true' : 'false' }}"
                style="{{ $btnStyle }}">{{ $item['label'] ?? 'Tab ' . ($idx + 1) }}</button>
        @endforeach
    </div>

    {{-- Tab Panels --}}
    @foreach($items as $idx => $item)
    @php $isActive = ($idx === $defaultActive); @endphp
    <div id="{{ $elemId }}-panel-{{ $idx }}"
         class="lazy-tab-panel"
         style="{{ $panelStyle }}{{ !$isActive ? 'display:none;' : '' }}">
        {!! lazy_sanitize_html($item['content'] ?? '') !!}
    </div>
    @endforeach

</div>

<script>
(function () {
    var el = document.getElementById('{{ $elemId }}');
    if (!el) return;

    var tabStyle       = '{{ $style }}';
    var tabColor       = '{{ $tabColor }}';
    var activeColor    = '{{ $activeColor }}';
    var contentBgColor = '{{ $contentBgColor }}';
    var borderColor    = '{{ $borderColor }}';
    var borderRadius   = {{ $borderRadius }};

    var btns   = el.querySelectorAll('.lazy-tab-btn');
    var panels = el.querySelectorAll('.lazy-tab-panel');

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');

            // Deactivate all
            btns.forEach(function (b) {
                b.setAttribute('data-active', 'false');
                if (tabStyle === 'underline') {
                    b.style.color       = tabColor;
                    b.style.borderBottom = '2px solid transparent';
                } else if (tabStyle === 'pill') {
                    b.style.backgroundColor = 'transparent';
                    b.style.color           = tabColor;
                } else { // boxed
                    b.style.border          = '1px solid transparent';
                    b.style.borderBottom    = '1px solid transparent';
                    b.style.backgroundColor = 'transparent';
                    b.style.color           = tabColor;
                    b.style.marginBottom    = '';
                }
            });
            panels.forEach(function (p) { p.style.display = 'none'; });

            // Activate clicked
            btn.setAttribute('data-active', 'true');
            if (tabStyle === 'underline') {
                btn.style.color       = activeColor;
                btn.style.borderBottom = '2px solid ' + activeColor;
            } else if (tabStyle === 'pill') {
                btn.style.backgroundColor = activeColor;
                btn.style.color           = '#ffffff';
            } else { // boxed
                btn.style.border          = '1px solid ' + borderColor;
                btn.style.borderBottom    = '1px solid ' + contentBgColor;
                btn.style.backgroundColor = contentBgColor;
                btn.style.color           = activeColor;
                btn.style.marginBottom    = '-1px';
            }

            var panel = document.getElementById(targetId);
            if (panel) panel.style.display = '';
        });
    });
})();
</script>
