@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visCls = '';
    if (!($v['mobile']  ?? true)) $visCls .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visCls .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visCls .= ' falcon-hide-desktop';

    $galleryId = 'lzg-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));
    $images    = array_values(array_filter($s['images'] ?? [], fn($img) => !empty($img['url'])));
    $colsD     = max(1, (int)($s['columns']       ?? 3));
    $colsT     = max(1, (int)($s['columnsTablet'] ?? 2));
    $colsM     = max(1, (int)($s['columnsMobile'] ?? 1));
    $gap       = max(0, (int)($s['gap']           ?? 8));
    $ratio     = $s['aspectRatio']    ?? 'square';
    $radius    = max(0, (int)($s['borderRadius']  ?? 0));
    $lightbox  = $s['lightbox']       ?? true;
    $hover     = $s['hoverEffect']    ?? 'zoom';

    $capAlign  = $s['captionAlign']         ?? 'center';
    $capFamily = $s['captionFontFamily']    ?? 'inherit';
    $capSize   = getUnitVal($s['captionFontSize'] ?? 13, $s['captionFontSizeUnit'] ?? 'px');
    $capWeight = $s['captionFontWeight']    ?? '400';
    $capLh     = $s['captionLineHeight']    ?? '1.4';
    $capLs     = $s['captionLetterSpacing'] ?? '0px';
    $capTt     = $s['captionTextTransform'] ?? 'none';
    $capColor  = $s['captionColor']         ?? '#6b7280';

    $mt = isset($s['marginTop'])    && $s['marginTop']    !== '' ? $s['marginTop']    . ($s['marginTopUnit']    ?? 'px') : '0px';
    $mb = isset($s['marginBottom']) && $s['marginBottom'] !== '' ? $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px') : '0px';
    $mtT = (isset($s['marginTop_tablet'])    && $s['marginTop_tablet']    !== '' && $s['marginTop_tablet']    !== null) ? $s['marginTop_tablet']    . ($s['marginTopUnit_tablet']    ?? $s['marginTopUnit']    ?? 'px') : $mt;
    $mbT = (isset($s['marginBottom_tablet']) && $s['marginBottom_tablet'] !== '' && $s['marginBottom_tablet'] !== null) ? $s['marginBottom_tablet'] . ($s['marginBottomUnit_tablet'] ?? $s['marginBottomUnit'] ?? 'px') : $mb;
    $mtM = (isset($s['marginTop_mobile'])    && $s['marginTop_mobile']    !== '' && $s['marginTop_mobile']    !== null) ? $s['marginTop_mobile']    . ($s['marginTopUnit_mobile']    ?? $s['marginTopUnit']    ?? 'px') : $mtT;
    $mbM = (isset($s['marginBottom_mobile']) && $s['marginBottom_mobile'] !== '' && $s['marginBottom_mobile'] !== null) ? $s['marginBottom_mobile'] . ($s['marginBottomUnit_mobile'] ?? $s['marginBottomUnit'] ?? 'px') : $mbT;

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    $imgBorderW = max(0, (int)($s['imgBorderWidth'] ?? 0));
    $imgBorderS = $s['imgBorderStyle'] ?? 'solid';
    $imgBorderC = $s['imgBorderColor'] ?? '#e2e8f0';
    $imgBorderCss = $imgBorderW > 0 ? "border:{$imgBorderW}px {$imgBorderS} {$imgBorderC};" : '';

    $ratioPad = match($ratio) {
        'portrait'  => '133.33%',
        'landscape' => '56.25%',
        default     => '100%',
    };
    $capStyle = "font-family:{$capFamily};font-size:{$capSize};font-weight:{$capWeight};line-height:{$capLh};letter-spacing:{$capLs};text-transform:{$capTt};color:{$capColor};text-align:{$capAlign};padding:6px 4px 0;display:block;";
@endphp

@if(!empty($images))
<style>
.{{ $galleryId }}{display:grid;grid-template-columns:repeat({{ $colsD }},1fr);gap:{{ $gap }}px;}
@media(min-width:{{ $bpSm1 }}px) and (max-width:{{ $bpMed }}px){.{{ $galleryId }}{grid-template-columns:repeat({{ $colsT }},1fr);}.{{ $galleryId }}-wrap{margin-top:{{ $mtT }};margin-bottom:{{ $mbT }};}}
@media(max-width:{{ $bpSm }}px){.{{ $galleryId }}{grid-template-columns:repeat({{ $colsM }},1fr);}.{{ $galleryId }}-wrap{margin-top:{{ $mtM }};margin-bottom:{{ $mbM }};}}
.{{ $galleryId }}-img{overflow:hidden;border-radius:{{ $radius }}px;position:relative;{{ $imgBorderCss }}}
@if($ratio !== 'auto')
.{{ $galleryId }}-img-inner{position:relative;padding-top:{{ $ratioPad }};overflow:hidden;}
.{{ $galleryId }}-img-inner img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;}
@else
.{{ $galleryId }}-img img{width:100%;height:auto;display:block;}
@endif
@if($hover === 'zoom')
.{{ $galleryId }}-img img{transition:transform 0.4s ease;}
.{{ $galleryId }}-img:hover img{transform:scale(1.07);}
@endif
@if($lightbox)
.{{ $galleryId }}-img{cursor:pointer;}
@endif
</style>

<div class="lz-gallery {{ $galleryId }}-wrap {{ $s['cssClass'] ?? '' }} {{ $visCls }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="width:100%;margin-top:{{ $mt }};margin-bottom:{{ $mb }};">

    <div class="{{ $galleryId }}">
        @foreach($images as $idx => $img)
        @php $imgUrl = $img['url']; $imgAlt = $img['alt'] ?? ''; $imgCap = $img['caption'] ?? ''; @endphp
        <div>
            <div class="{{ $galleryId }}-img"
                 @if($lightbox) data-lz-gallery="{{ $galleryId }}" data-lz-gallery-idx="{{ $idx }}" data-lz-gallery-url="{{ $imgUrl }}" data-lz-gallery-cap="{{ htmlspecialchars($imgCap) }}" @endif>
                @if($ratio !== 'auto')
                <div class="{{ $galleryId }}-img-inner">
                    <img src="{{ $imgUrl }}" alt="{{ $imgAlt }}" loading="lazy">
                </div>
                @else
                <img src="{{ $imgUrl }}" alt="{{ $imgAlt }}" loading="lazy">
                @endif
            </div>
            @if($imgCap)
            <span style="{{ $capStyle }}">{{ $imgCap }}</span>
            @endif
        </div>
        @endforeach
    </div>

    @if($lightbox)
    @include('falcon-cms::components.frontend.lightbox', ['id' => $galleryId, 'nav' => count($images) > 1])
    @endif

</div>
@endif


