@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' lazy-hide-desktop';

    $tickerId  = !empty($s['cssId'])   ? $s['cssId']   : ('lz-ticker-' . ($el['id'] ?? str_replace('.', '', uniqid('', true))));
    $cssClass  = $s['cssClass'] ?? '';

    $items        = array_filter($s['items'] ?? [], fn($i) => !empty($i['text']));
    $label        = $s['label']      ?? '';
    $speed        = max(1, min(100, (int)($s['speed'] ?? 50)));
    $direction    = $s['direction']  ?? 'left';
    $pauseOnHover = ($s['pauseOnHover'] ?? true) !== false;
    $separator    = $s['separator']  ?? '•';
    $labelAnim    = $s['labelAnimation'] ?? 'blink-dot';
    $textEffect   = $s['textEffect']    ?? 'none';

    $bgColor        = $s['bgColor']        ?? '#1e3a8a';
    $textColor      = $s['textColor']      ?? '#ffffff';
    $labelBgColor   = $s['labelBgColor']   ?? '#ef4444';
    $labelTextColor = $s['labelTextColor'] ?? '#ffffff';
    $fontSize       = $s['fontSize']       ?? '14px';
    $fontWeight     = $s['fontWeight']     ?? '500';
    $height         = max(28, (int)($s['height'] ?? 44));
    $borderRadius   = (int)($s['borderRadius'] ?? 0);
    $marginTop      = $s['marginTop']    ?? 0;
    $marginBottom   = $s['marginBottom'] ?? 0;
    $marginTopUnit    = $s['marginTopUnit']    ?? 'px';
    $marginBottomUnit = $s['marginBottomUnit'] ?? 'px';

    $isVertical = in_array($direction, ['up', 'down']);

    // Speed → scroll duration (100s–5s). Plus 1.5s blank pause.
    // padding-left:100% pushes content to the off-screen right edge at translateX(0%).
    // Text scrolls in from right, exits left, ticker goes blank during pause, then re-enters.
    $scrollDuration = max(5, 105 - $speed);
    $pauseDuration  = 1.5;
    $totalDuration  = round($scrollDuration + $pauseDuration, 2);
    $scrollPct      = round($scrollDuration / $totalDuration * 100, 2);
    $resetPct       = min(99.9, $scrollPct + 0.1);

    if ($isVertical) {
        $fromOffset    = $direction === 'down' ? '-100%' : '0%';
        $toOffset      = $direction === 'down' ? '0%'   : '-100%';
        $transformProp = 'translateY';
        $wrapStyle     = "padding-top:{$height}px;box-sizing:content-box;display:block;white-space:nowrap;";
    } else {
        $fromOffset    = $direction === 'right' ? '-100%' : '0%';
        $toOffset      = $direction === 'right' ? '0%'   : '-100%';
        $transformProp = 'translateX';
        $wrapStyle     = "padding-left:100%;box-sizing:content-box;display:inline-flex;align-items:center;height:100%;white-space:nowrap;";
    }

    $animName = 'lztick-' . ($el['id'] ?? uniqid());
    $runClass = 'lztr-' . preg_replace('/[^a-z0-9]/i', '', $animName);

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respId = 'lzr-tick-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$respId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$respId}"],
    ]);

    // Build per-item HTML
    $parts = [];
    foreach ($items as $item) {
        $txt = e($item['text'] ?? '');
        $parts[] = !empty($item['url'])
            ? '<a href="' . e($item['url']) . '" class="lztick-link">' . $txt . '</a>'
            : '<span>' . $txt . '</span>';
    }
    if (empty($parts)) {
        $parts = ['<span style="opacity:.5">Add items in the builder.</span>'];
    }

    // Build separator HTML
    if ($separator === 'dance') {
        $sep = ' <span class="lztick-sep-dance" aria-hidden="true"><span></span><span></span><span></span></span> ';
    } elseif ($separator !== '') {
        $sep = ' <span class="lztick-sep" aria-hidden="true">' . e($separator) . '</span> ';
    } else {
        $sep = '&nbsp;&nbsp;';
    }

    if ($isVertical) {
        $itemStyle = "height:{$height}px;display:flex;align-items:center;padding:0 20px;box-sizing:border-box;white-space:nowrap;";
        $content   = implode('', array_map(fn($p) => '<div class="lztick-vi" style="' . $itemStyle . '">' . $p . '</div>', $parts));
    } else {
        $content = implode($sep, $parts);
    }
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

<div id="{{ $tickerId }}" class="lztick-wrap {{ $respId }} {{ $visibilityClasses }} {{ $cssClass }}{{ $pauseOnHover ? ' lztick-pause-hover' : '' }}"
     style="width:100%;max-width:100%;background:{{ $bgColor }};color:{{ $textColor }};font-size:{{ $fontSize }};font-weight:{{ $fontWeight }};height:{{ $height }}px;border-radius:{{ $borderRadius }}px;margin-top:{{ $marginTop }}{{ $marginTopUnit }};margin-bottom:{{ $marginBottom }}{{ $marginBottomUnit }};overflow:hidden;display:flex;align-items:center;position:relative;box-sizing:border-box;">

    @if($label)
    <div class="lztick-label lztick-la-{{ $labelAnim }}"
         style="background:{{ $labelBgColor }};color:{{ $labelTextColor }};padding:0 14px;height:100%;display:flex;align-items:center;font-weight:700;white-space:nowrap;flex-shrink:0;font-size:{{ $fontSize }};letter-spacing:.03em;text-transform:uppercase;">
        {{ $label }}
    </div>
    @endif

    <div style="flex:1;overflow:hidden;height:100%;">
        <span class="{{ $runClass }} lztick-te-{{ $textEffect }}" style="{{ $wrapStyle }}">{!! $content !!}</span>
    </div>
</div>

<style>
.{{ $runClass }} {
    animation: {{ $animName }} {{ $totalDuration }}s linear infinite;
    will-change: transform;
}
#{{ $tickerId }}.lztick-pause-hover:hover .{{ $runClass }} {
    animation-play-state: paused;
}
@keyframes {{ $animName }} {
    0%                { transform: {{ $transformProp }}({{ $fromOffset }}); }
    {{ $scrollPct }}% { transform: {{ $transformProp }}({{ $toOffset }}); animation-timing-function: steps(1, end); }
    {{ $resetPct }}%  { transform: {{ $transformProp }}({{ $fromOffset }}); }
    100%              { transform: {{ $transformProp }}({{ $fromOffset }}); }
}
</style>

@once('lztick-global-css')
<style>
.lztick-wrap .lztick-link { color: inherit; text-decoration: none; }
.lztick-wrap .lztick-link:hover { opacity: .75; text-decoration: underline; }
.lztick-wrap .lztick-sep { opacity: .5; margin: 0 4px; }

/* Label animations */
.lztick-la-blink-dot { animation: lztick-label-pulse 1.8s ease-in-out infinite; }
.lztick-la-blink-dot::before {
    content: ''; width: 8px; height: 8px; border-radius: 50%;
    background: currentColor; margin-right: 6px; flex-shrink: 0;
    animation: lztick-dot-blink 1s step-end infinite;
}
.lztick-la-pulse  { animation: lztick-label-pulse  1.8s ease-in-out infinite; }
.lztick-la-flash  { animation: lztick-label-flash  1.2s step-end  infinite; }
.lztick-la-shake  { animation: lztick-label-shake  2.5s ease-in-out infinite; }
.lztick-la-bounce { animation: lztick-label-bounce 1.5s ease-in-out infinite; }
.lztick-la-none   { animation: none; }

@keyframes lztick-dot-blink   { 0%,100%{opacity:1} 50%{opacity:0} }
@keyframes lztick-label-pulse { 0%,100%{filter:brightness(1) saturate(1)} 50%{filter:brightness(1.35) saturate(1.6)} }
@keyframes lztick-label-flash { 0%,49%,100%{opacity:1} 50%,98%{opacity:0.15} }
@keyframes lztick-label-shake {
    0%,100%{transform:translateX(0)} 10%{transform:translateX(-4px)} 20%{transform:translateX(4px)}
    30%{transform:translateX(-3px)} 40%{transform:translateX(3px)}
    50%{transform:translateX(-2px)} 60%{transform:translateX(2px)} 70%,90%{transform:translateX(0)}
}
@keyframes lztick-label-bounce {
    0%,100%{transform:scaleX(1)} 20%{transform:scaleX(1.1)} 40%{transform:scaleX(1)}
    60%{transform:scaleX(1.05)} 80%{transform:scaleX(1)}
}

/* Dancing-dots separator */
.lztick-sep-dance { display:inline-flex; align-items:center; gap:3px; margin:0 10px; vertical-align:middle; }
.lztick-sep-dance > span { display:inline-block; width:4px; height:4px; border-radius:50%; background:currentColor; opacity:.7; }
.lztick-sep-dance > span:nth-child(1) { animation: lztick-ddot .9s ease-in-out infinite 0s; }
.lztick-sep-dance > span:nth-child(2) { animation: lztick-ddot .9s ease-in-out infinite .15s; }
.lztick-sep-dance > span:nth-child(3) { animation: lztick-ddot .9s ease-in-out infinite .3s; }
@keyframes lztick-ddot { 0%,100%{transform:translateY(0);opacity:.5} 50%{transform:translateY(-4px);opacity:1} }

/* Text effects */
.lztick-te-glow { text-shadow: 0 0 10px rgba(255,255,255,0.6); }
.lztick-te-highlight span:not(.lztick-sep):not(.lztick-sep-dance):not(.lztick-sep-dance > span) {
    background: rgba(255,255,255,0.12); padding: 1px 8px; border-radius: 3px;
}
</style>
@endonce
