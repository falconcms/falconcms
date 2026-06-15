@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visCls = '';
    if (!($v['mobile']  ?? true)) $visCls .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visCls .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visCls .= ' falcon-hide-desktop';

    $rating    = (float)($s['rating']          ?? 5);
    $maxStars  = max(1, (int)($s['maxStars']   ?? 5));
    $label     = $s['label']                   ?? '';
    $starSize  = (int)($s['starSize']          ?? 24);
    $starColor = $s['starColor']               ?? '#f59e0b';
    $emptyColor= $s['emptyColor']              ?? '#d1d5db';
    $textAlign = $s['textAlign']               ?? 'center';
    $gap       = (int)($s['gap']              ?? 4);
    $lblFamily = $s['labelFontFamily']          ?? 'inherit';
    $lblSize   = $s['labelFontSize']           ?? '13px';
    $lblWeight = $s['labelFontWeight']         ?? '400';
    $lblLh     = $s['labelLineHeight']         ?? '1.4';
    $lblLs     = $s['labelLetterSpacing']      ?? '0px';
    $lblTt     = $s['labelTextTransform']      ?? 'none';
    $lblColor  = $s['labelColor']              ?? '#6b7280';

    $mt = isset($s['marginTop'])    && $s['marginTop']    !== '' ? $s['marginTop']    . ($s['marginTopUnit']    ?? 'px') : '0px';
    $mb = isset($s['marginBottom']) && $s['marginBottom'] !== '' ? $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px') : '0px';
@endphp

<div class="lz-star-rating {{ $s['cssClass'] ?? '' }} {{ $visCls }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="width:100%;max-width:100%;{{ $textAlign !== 'full' ? 'text-align:' . $textAlign . ';' : '' }}margin-top:{{ $mt }};margin-bottom:{{ $mb }};">

    <div style="{{ $textAlign === 'full' ? 'display:flex;width:100%;justify-content:space-evenly;' : 'display:inline-flex;' }}align-items:center;gap:{{ $gap }}px;line-height:1;">
        @for($i = 1; $i <= $maxStars; $i++)
            @php
                $type = $rating >= $i ? 'full' : ($rating >= $i - 0.5 ? 'half' : 'empty');
            @endphp
            @if($type === 'full')
                <span style="color:{{ $starColor }};font-size:{{ $starSize }}px;line-height:1;display:inline-block;">&#9733;</span>
            @elseif($type === 'half')
                <span style="position:relative;display:inline-block;font-size:{{ $starSize }}px;line-height:1;">
                    <span style="color:{{ $emptyColor }};">&#9733;</span>
                    <span style="position:absolute;left:0;top:0;width:50%;overflow:hidden;color:{{ $starColor }};">&#9733;</span>
                </span>
            @else
                <span style="color:{{ $emptyColor }};font-size:{{ $starSize }}px;line-height:1;display:inline-block;">&#9733;</span>
            @endif
        @endfor
    </div>

    @if($label)
    <div style="font-family:{{ $lblFamily }};font-size:{{ $lblSize }};font-weight:{{ $lblWeight }};line-height:{{ $lblLh }};letter-spacing:{{ $lblLs }};text-transform:{{ $lblTt }};color:{{ $lblColor }};margin-top:6px;">{{ $label }}</div>
    @endif

</div>
