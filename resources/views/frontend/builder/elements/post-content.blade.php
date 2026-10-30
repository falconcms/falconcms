@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');

    $elemId = 'pc-' . str_replace('.', '', uniqid('', true));

    $pc = ".{$elemId}";
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'textAlign',     'sel' => $pc],
        ['prop' => 'marginTop',     'unitProp' => 'marginTopUnit',     'sel' => $pc],
        ['prop' => 'marginRight',   'unitProp' => 'marginRightUnit',   'sel' => $pc],
        ['prop' => 'marginBottom',  'unitProp' => 'marginBottomUnit',  'sel' => $pc],
        ['prop' => 'marginLeft',    'unitProp' => 'marginLeftUnit',    'sel' => $pc],
    ]);

    // Desktop base styles — width:100% and align-self:stretch so it fills column width
    $wrapStyle  = "width:100%;align-self:stretch;";
    $wrapStyle .= "text-align:" . ($s['textAlign'] ?? 'left') . ";";
    $wrapStyle .= "margin-top:"    . ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px') . ";";
    $wrapStyle .= "margin-right:"  . ($s['marginRight']  ?? 0) . ($s['marginRightUnit']  ?? 'px') . ";";
    $wrapStyle .= "margin-bottom:" . ($s['marginBottom'] ?? 8) . ($s['marginBottomUnit'] ?? 'px') . ";";
    $wrapStyle .= "margin-left:"   . ($s['marginLeft']   ?? 0) . ($s['marginLeftUnit']   ?? 'px') . ";";

    // Typography — text-align explicitly inherited so responsive overrides cascade properly
    $typoStyle  = "font-family:"    . ($s['fontFamily']    ?? 'inherit') . ";";
    $typoStyle .= "font-size:"      . getUnitVal($s['fontSize'] ?? 13, $s['fontSizeUnit'] ?? 'px') . ";";
    $typoStyle .= "font-weight:"    . ($s['fontWeight']    ?? '400') . ";";
    $typoStyle .= "line-height:"    . ($s['lineHeight']    ?? '1.6') . ";";
    $typoStyle .= "letter-spacing:" . ($s['letterSpacing'] ?? 0) . ($s['letterSpacingUnit'] ?? 'px') . ";";
    $typoStyle .= "text-transform:" . ($s['textTransform'] ?? 'none') . ";";
    $typoStyle .= "color:"          . ($s['color']          ?? '#6b7280') . ";";
    $typoStyle .= "text-align:inherit;margin:0;";

    // Resolve content
    $contentDisplay = $s['content_display'] ?? 'excerpt';
    $stripHtml      = $s['stripHtml'] ?? true;
    $excerptLength  = max(10, (int)($s['excerptLength'] ?? 120));

    if ($contentDisplay === 'full') {
        // Full content = the post's formatted content, rendered exactly as designed
        // (inline styles, gradients, images and layout are preserved; only <script>
        // and event handlers are removed by the sanitizer). "Strip HTML" applies to
        // the excerpt preview only — stripping a full designed layout to text is
        // never what "Full content" means.
        $output = (string) ($postContent ?? '');
    } else {
        $rawContent = $postExcerpt ?? $postContent ?? '';
        if ($stripHtml) $rawContent = falcon_html_to_text($rawContent);
        $output = mb_strlen($rawContent) > $excerptLength
            ? mb_substr($rawContent, 0, $excerptLength) . '…'
            : $rawContent;
    }

    $cssId  = $s['cssId']    ?? '';
    $cssCls = $s['cssClass'] ?? '';

    $isFull = ($contentDisplay === 'full');
    // Already sanitised when it comes from the layout post context (see
    // _falcon_layout_post_context); sanitising again would strip the elements' scripts.
    $safeOutput = ($isFull && !empty($postContentRendered)) ? $output : falcon_sanitize_html($output);
    // Full content = the post rendered exactly as designed. Break the element out
    // to the full viewport width (full-bleed) so the post's own full-width section
    // backgrounds span edge-to-edge, just like when the page renders on its own.
    // Its inner sections still box their content to the site width themselves.
    // Only full-bleed when the content is ITSELF a builder layout (its own containers
    // self-box to the site width). Plain/classic content has no self-boxing, so full-bleed
    // would ignore the parent container's Site Width — keep it in-flow so it respects it.
    $isBuilderContent = $isFull && (str_contains($output, 'lazy-container') || str_contains($output, 'falcon-builder'));
    // 100vw counts the vertical scroll bar, so on desktop the box came out a scroll bar wider
    // than the page (a horizontal scroll bar) and off-centre. --falcon-sbw is that bar's width,
    // measured by the script below.
    $fullBleedStyle = 'width:calc(100vw - var(--falcon-sbw, 0px));max-width:calc(100vw - var(--falcon-sbw, 0px));margin-left:calc(50% - 50vw + var(--falcon-sbw, 0px) / 2);margin-right:calc(50% - 50vw + var(--falcon-sbw, 0px) / 2);';
@endphp

@if($respCss)
<style>{!! $respCss !!}</style>
@endif

@if($output !== '')
    @if($isFull && $isBuilderContent)
        <div class="element-post-content element-post-content-full {{ $elemId }} {{ $cssCls }} {{ $visibilityClasses }}"
             @if($cssId) id="{{ $cssId }}" @endif
             style="{{ $fullBleedStyle }}">{!! $safeOutput !!}</div>
        <script>
        (function () {
            if (window.__falconSbw) return;
            window.__falconSbw = 1;
            var root = document.documentElement;
            function measure() { root.style.setProperty('--falcon-sbw', Math.max(0, window.innerWidth - root.clientWidth) + 'px'); }
            measure();
            window.addEventListener('resize', measure, { passive: true });
            window.addEventListener('load', measure);
            if (window.ResizeObserver) new ResizeObserver(measure).observe(root);
        })();
        </script>
    @elseif($isFull)
        <div class="element-post-content {{ $elemId }} {{ $cssCls }} {{ $visibilityClasses }}"
             @if($cssId) id="{{ $cssId }}" @endif
             style="{{ $wrapStyle }}">{!! $safeOutput !!}</div>
    @else
        <div class="element-post-content {{ $elemId }} {{ $cssCls }} {{ $visibilityClasses }}"
             @if($cssId) id="{{ $cssId }}" @endif
             style="{{ $wrapStyle }}">
            <p style="{{ $typoStyle }}">{{ $output }}</p>
        </div>
    @endif
@endif
