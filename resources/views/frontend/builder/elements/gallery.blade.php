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
    $capSize   = $s['captionFontSize']      ?? '13px';
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
    <div id="lz-lb-{{ $galleryId }}" class="lz-lightbox" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;">
        <div class="lz-lightbox-bg" style="position:absolute;inset:0;background:rgba(0,0,0,0.92);cursor:pointer;"></div>
        <button onclick="lzGalleryClose('{{ $galleryId }}')" style="position:absolute;top:20px;right:24px;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;line-height:1;padding:4px;">&#10005;</button>
        <button onclick="lzGalleryNav('{{ $galleryId }}',-1)" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;padding:12px 16px;border-radius:4px;">&#10094;</button>
        <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:12px;max-width:90vw;">
            <img class="lz-lb-img" src="" style="max-width:90vw;max-height:80vh;object-fit:contain;border-radius:4px;display:block;">
            <div class="lz-lb-cap" style="color:#ccc;font-size:14px;text-align:center;max-width:600px;padding:0 16px;"></div>
        </div>
        <button onclick="lzGalleryNav('{{ $galleryId }}',1)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;padding:12px 16px;border-radius:4px;">&#10095;</button>
    </div>
    @endif

</div>
@endif

@once('lz-gallery-js')
<script>
(function(){
    var _lzg={};
    window.lzGalleryClose=function(gid){
        var lb=document.getElementById('lz-lb-'+gid);
        if(lb){lb.style.display='none';document.body.style.overflow='';}
    };
    window.lzGalleryNav=function(gid,dir){
        var g=_lzg[gid];if(!g)return;
        var keys=Object.keys(g.imgs).map(Number).sort(function(a,b){return a-b;});
        var ci=keys.indexOf(g.cur);
        g.cur=keys[((ci+dir)%keys.length+keys.length)%keys.length];
        var lb=document.getElementById('lz-lb-'+gid);
        lb.querySelector('.lz-lb-img').src=g.imgs[g.cur].u;
        lb.querySelector('.lz-lb-cap').textContent=g.imgs[g.cur].c||'';
    };
    function _lzOpen(gid,idx){
        var g=_lzg[gid];if(!g||g.imgs[idx]===undefined)return;
        g.cur=idx;
        var lb=document.getElementById('lz-lb-'+gid);
        lb.querySelector('.lz-lb-img').src=g.imgs[idx].u;
        lb.querySelector('.lz-lb-cap').textContent=g.imgs[idx].c||'';
        lb.style.display='flex';
        document.body.style.overflow='hidden';
    }
    function _lzInit(){
        document.querySelectorAll('[data-lz-gallery]').forEach(function(el){
            if(el.dataset.lzGalleryInit)return;
            el.dataset.lzGalleryInit='1';
            var gid=el.dataset.lzGallery;
            var idx=parseInt(el.dataset.lzGalleryIdx);
            if(!_lzg[gid])_lzg[gid]={imgs:{},cur:0};
            _lzg[gid].imgs[idx]={u:el.dataset.lzGalleryUrl,c:el.dataset.lzGalleryCap||''};
            el.addEventListener('click',function(){_lzOpen(gid,idx);});
        });
        document.querySelectorAll('.lz-lightbox .lz-lightbox-bg').forEach(function(bg){
            if(bg.dataset.lzBgInit)return;
            bg.dataset.lzBgInit='1';
            bg.addEventListener('click',function(){
                var lb=bg.closest('.lz-lightbox');
                if(lb){lb.style.display='none';document.body.style.overflow='';}
            });
        });
    }
    document.readyState==='loading'
        ?document.addEventListener('DOMContentLoaded',_lzInit)
        :_lzInit();
})();
</script>
@endonce
