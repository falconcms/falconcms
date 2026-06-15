@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $htmlContent  = $s['htmlContent']  ?? '';
    $cssClass     = $s['cssClass']     ?? '';
    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $elemId = !empty($s['cssId']) ? $s['cssId'] : ('html-' . ($el['id'] ?? str_replace('.', '', uniqid('', true))));
    $outerStyle = "width:100%;margin-top:{$marginTop};margin-bottom:{$marginBottom};";

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respId = 'lzr-html-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$respId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$respId}"],
    ]);
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

@if($htmlContent)
<div id="{{ $elemId }}"
     class="lazy-html-block {{ $respId }}{{ $cssClass ? ' ' . $cssClass : '' }}{{ $visibilityClasses }}"
     style="{{ $outerStyle }}">
    {!! lazy_sanitize_html($htmlContent) !!}
</div>
@endif
