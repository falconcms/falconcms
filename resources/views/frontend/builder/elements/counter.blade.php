@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $counterId = 'lz-counter-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));

    $endValue    = $s['endValue']   ?? 100;
    $startValue  = $s['startValue'] ?? 0;
    $prefix      = $s['prefix']     ?? '';
    $suffix      = $s['suffix']     ?? '';
    $label       = $s['label']      ?? '';
    $duration    = $s['duration']   ?? 2000;
    $decimals    = (int)($s['decimals'] ?? 0);
    $separator   = $s['separator']  ?? '';

    $textAlign  = $s['textAlign']       ?? 'center';
    $numSize    = getUnitVal($s['numberFontSize'] ?? 48, $s['numberFontSizeUnit'] ?? 'px');
    $numWeight  = $s['numberFontWeight'] ?? '700';
    $numColor   = $s['numberColor']      ?? '#222222';
    $numFamily        = $s['numberFontFamily']     ?? 'inherit';
    $numLineHeight    = $s['numberLineHeight']    ?? '1.1';
    $numLetterSpacing = $s['numberLetterSpacing'] ?? '0px';
    $lblSize          = getUnitVal($s['labelFontSize'] ?? 14, $s['labelFontSizeUnit'] ?? 'px');
    $lblWeight        = $s['labelFontWeight']    ?? '400';
    $lblColor         = $s['labelColor']         ?? '#666666';
    $lblFamily        = $s['labelFontFamily']    ?? 'inherit';
    $lblLineHeight    = $s['labelLineHeight']    ?? '1.4';
    $lblLetterSpacing = $s['labelLetterSpacing'] ?? '0px';
    $lblTextTransform = $s['labelTextTransform'] ?? 'none';
    $icon       = $s['icon']             ?? '';
    $iconSize   = ($s['iconSize']        ?? 40) . 'px';
    $iconColor  = $s['iconColor']        ?? '#0091ea';

    $marginTop    = isset($s['marginTop'])    && $s['marginTop']    !== '' ? $s['marginTop']    . ($s['marginTopUnit']    ?? 'px') : '0px';
    $marginBottom = isset($s['marginBottom']) && $s['marginBottom'] !== '' ? $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px') : '0px';

    $wrapperStyle = "width:100%;max-width:100%;text-align:{$textAlign};margin-top:{$marginTop};margin-bottom:{$marginBottom};";

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$counterId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$counterId}"],
    ]);
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif

<div class="lz-counter-wrapper {{ $counterId }} {{ $s['cssClass'] ?? '' }} {{ $visibilityClasses }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="{{ $wrapperStyle }}">

    @if($icon)
    <div class="lz-counter-icon" style="color:{{ $iconColor }};font-size:{{ $iconSize }};margin-bottom:10px;">
        <i class="{{ $icon }}"></i>
    </div>
    @endif

    <div class="lz-counter-number"
         data-end="{{ $endValue }}"
         data-start="{{ $startValue }}"
         data-duration="{{ $duration }}"
         data-decimals="{{ $decimals }}"
         data-separator="{{ $separator }}"
         data-prefix="{{ $prefix }}"
         data-suffix="{{ $suffix }}"
         style="color:{{ $numColor }};font-size:{{ $numSize }};font-weight:{{ $numWeight }};font-family:{{ $numFamily }};line-height:{{ $numLineHeight }};letter-spacing:{{ $numLetterSpacing }};display:block;">
        {{ $prefix }}{{ number_format($endValue, $decimals, '.', $separator) }}{{ $suffix }}
    </div>

    @if($label)
    <div class="lz-counter-label" style="color:{{ $lblColor }};font-size:{{ $lblSize }};font-weight:{{ $lblWeight }};font-family:{{ $lblFamily }};line-height:{{ $lblLineHeight }};letter-spacing:{{ $lblLetterSpacing }};text-transform:{{ $lblTextTransform }};margin-top:6px;">
        {{ $label }}
    </div>
    @endif

</div>

@once('lz-counter-js')
<script>
(function(){
    function runCounter(el){
        var end      = parseFloat(el.dataset.end)      || 0;
        var start    = parseFloat(el.dataset.start)    || 0;
        var dur      = parseInt(el.dataset.duration)   || 2000;
        var dec      = parseInt(el.dataset.decimals)   || 0;
        var sep      = el.dataset.separator            || '';
        var prefix   = el.dataset.prefix               || '';
        var suffix   = el.dataset.suffix               || '';
        var startTs  = null;

        function fmt(n){
            var fixed = n.toFixed(dec);
            if(sep){
                var parts = fixed.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, sep);
                fixed = parts.join('.');
            }
            return prefix + fixed + suffix;
        }

        function step(ts){
            if(!startTs) startTs = ts;
            var progress = Math.min((ts - startTs) / dur, 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = fmt(start + (end - start) * ease);
            if(progress < 1) requestAnimationFrame(step);
            else el.textContent = fmt(end);
        }

        requestAnimationFrame(step);
    }

    function initCounters(){
        document.querySelectorAll('.lz-counter-number:not([data-lz-counted])').forEach(function(el){
            el.dataset.lzCounted = '1';
            new IntersectionObserver(function(entries, obs){
                if(entries[0].isIntersecting){ runCounter(el); obs.disconnect(); }
            }, { threshold: 0.3 }).observe(el);
        });
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', initCounters)
        : initCounters();
})();
</script>
@endonce
