{{-- The lightbox, shared by the Gallery and the Image element.

     Variables:
       $id   a unique id for this lightbox — the trigger's data-lz-gallery must match it
       $nav  whether to draw the previous/next arrows (a single image has nowhere to go)

     Triggers opt in by carrying data-lz-gallery, data-lz-gallery-idx,
     data-lz-gallery-url and data-lz-gallery-cap; the script below finds them itself, so
     an element only has to mark its images and include this. --}}
@php
    $nav = $nav ?? true;
@endphp

<div id="lz-lb-{{ $id }}" class="lz-lightbox" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;">
    <div class="lz-lightbox-bg" style="position:absolute;inset:0;background:rgba(0,0,0,0.92);cursor:pointer;"></div>
    <button onclick="lzGalleryClose('{{ $id }}')" aria-label="Close" style="position:absolute;top:20px;right:24px;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;line-height:1;padding:4px;">&#10005;</button>
    @if($nav)
    <button onclick="lzGalleryNav('{{ $id }}',-1)" aria-label="Previous" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;padding:12px 16px;border-radius:4px;">&#10094;</button>
    @endif
    <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:12px;max-width:90vw;">
        <img class="lz-lb-img" src="" alt="" style="max-width:90vw;max-height:80vh;object-fit:contain;border-radius:4px;display:block;">
        <div class="lz-lb-cap" style="color:#ccc;font-size:14px;text-align:center;max-width:600px;padding:0 16px;"></div>
    </div>
    @if($nav)
    <button onclick="lzGalleryNav('{{ $id }}',1)" aria-label="Next" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;padding:12px 16px;border-radius:4px;">&#10095;</button>
    @endif
</div>

<script>
(function(){
    /* Guarded by a window flag rather than by Blade's render-once directive, which this
       codebase cannot rely on: the theme layout renders content twice per request — once
       to scan it for icon libraries — so the directive has already fired by the time the
       visible pass runs and the script never reaches the page. The Code Block element
       shipped without its script for exactly that reason. A flag is checked by the
       browser at run time, which a second render cannot fool.

       The cost is this script appearing once per lightbox on the page. It is a kilobyte,
       and it is the difference between a lightbox that opens and one that does nothing. */
    if(window.__falconLightbox)return;
    window.__falconLightbox=true;

    var _lzg={};
    /* Locking body alone is not enough: on most pages it is <html> that scrolls, so the
       page went on scrolling behind the overlay and kept its scrollbar, leaving a strip
       of the page down the right-hand edge. Both are locked, and both are given back. */
    function _lzLock(on){
        var v=on?'hidden':'';
        document.body.style.overflow=v;
        document.documentElement.style.overflow=v;
    }
    window.lzGalleryClose=function(gid){
        var lb=document.getElementById('lz-lb-'+gid);
        if(lb){lb.style.display='none';_lzLock(false);}
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
        _lzLock(true);
    }
    /* Move every lightbox to the end of <body> before anything else.

       A `position: fixed` box is only fixed to the viewport while no ancestor has a
       transform, a filter or a will-change — any of those makes that ancestor the
       containing block instead, and the "full screen" overlay is then the size of
       whatever it happens to sit inside. The builder's own entrance animations set
       `will-change: opacity, transform` on a wrapper around every animated element, so an
       image inside one opened into a lightbox the size of that image's column: the
       backdrop covered a corner of the page and the picture spilled out of it. It looked
       like the lightbox was not working, which is exactly what it was.

       Reparenting is the fix that does not depend on knowing what the page wraps things
       in. The close and next buttons call functions on window, so nothing breaks by
       moving. */
    function _lzHoist(){
        if(!document.body)return;
        document.querySelectorAll('.lz-lightbox').forEach(function(lb){
            if(lb.dataset.lzHoisted)return;
            lb.dataset.lzHoisted='1';
            document.body.appendChild(lb);
        });
    }
    function _lzInit(){
        _lzHoist();
        document.querySelectorAll('[data-lz-gallery]').forEach(function(el){
            if(el.dataset.lzGalleryInit)return;
            el.dataset.lzGalleryInit='1';
            var gid=el.dataset.lzGallery;
            var idx=parseInt(el.dataset.lzGalleryIdx);
            if(!_lzg[gid])_lzg[gid]={imgs:{},cur:0};
            _lzg[gid].imgs[idx]={u:el.dataset.lzGalleryUrl,c:el.dataset.lzGalleryCap||''};
            el.addEventListener('click',function(){_lzOpen(gid,idx);});
            /* A trigger you can reach with a keyboard. Without this the lightbox is
               mouse-only, and the image it hides is unreachable for anyone who is not
               using one. */
            if(!el.hasAttribute('tabindex'))el.setAttribute('tabindex','0');
            if(!el.hasAttribute('role'))el.setAttribute('role','button');
            el.addEventListener('keydown',function(ev){
                if(ev.key==='Enter'||ev.key===' '){ev.preventDefault();_lzOpen(gid,idx);}
            });
        });
        document.querySelectorAll('.lz-lightbox .lz-lightbox-bg').forEach(function(bg){
            if(bg.dataset.lzBgInit)return;
            bg.dataset.lzBgInit='1';
            bg.addEventListener('click',function(){
                var lb=bg.closest('.lz-lightbox');
                if(lb){lb.style.display='none';_lzLock(false);}
            });
        });
    }
    /* Escape closes whichever lightbox is open — the first thing anyone tries. */
    document.addEventListener('keydown',function(ev){
        if(ev.key!=='Escape')return;
        document.querySelectorAll('.lz-lightbox').forEach(function(lb){
            if(lb.style.display!=='none'){lb.style.display='none';_lzLock(false);}
        });
    });
    /* Images added after load — a builder preview, a lazy-loaded section — are picked up
       when they arrive rather than only at DOMContentLoaded. */
    if(window.MutationObserver){
        new MutationObserver(function(){_lzInit();}).observe(document.documentElement,{childList:true,subtree:true});
    }
    document.readyState==='loading'
        ?document.addEventListener('DOMContentLoaded',_lzInit)
        :_lzInit();
})();
</script>
