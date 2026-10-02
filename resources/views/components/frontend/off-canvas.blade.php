@php
    /*
    |--------------------------------------------------------------------------
    | Off-canvas panels
    |--------------------------------------------------------------------------
    | Drawn from the falcon_footer action by FalconCms\Core\Support\OffCanvas, and only
    | with the panels this page can open ($panels). Anything that opens one:
    |   <a href="#offcanvas-{slug}">   a menu item, a builder button, any link
    |   data-falcon-offcanvas="{slug}" any element
    |   #offcanvas-close               closes the open panel (e.g. a button inside it)
    |   FalconOffCanvas.open('{slug}') / .close() / .toggle('{slug}') from script
    | A page reached with #offcanvas-{slug} in its address opens that panel on load.
    */
    use FalconCms\Core\Support\OffCanvas;

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint', '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');

    // The page's <head> loaded fonts and icon sets for what it could see at the time; the
    // panels render after it, so whatever they use beyond that is linked from here.
    $__ocPanels = [];
    $__ocFonts = [];
    foreach ($panels as $__p) {
        $__layout = $__p['config']['layout'] ?? [];
        $__ocFonts = array_merge($__ocFonts, get_falcon_builder_fonts($__layout));
        $__ocPanels[] = [
            'item' => $__p,
            's'    => OffCanvas::settings($__p['config']['settings'] ?? []),
            'html' => is_array($__layout) && $__layout ? _falcon_render_layout($__layout) : '',
        ];
    }
    $__ocFontUrl = $__ocFonts ? falcon_google_font_url(array_unique($__ocFonts)) : '';
    $__ocIcons = function_exists('falcon_icon_set_links')
        ? falcon_icon_set_links(implode('', array_column($__ocPanels, 'html'))) : [];
@endphp
@if($__ocFontUrl)<link rel="stylesheet" href="{{ $__ocFontUrl }}">@endif
@foreach($__ocIcons as $__ocIcon)<link rel="stylesheet" href="{{ $__ocIcon }}">@endforeach

@once
<style>
    .falcon-oc { position: fixed; inset: 0; z-index: 100000; visibility: hidden; pointer-events: none;
        transition: visibility 0s linear var(--oc-dur, 350ms); }
    .falcon-oc.is-open { visibility: visible; transition-delay: 0s; }
    /* The wrapper covers the viewport but never takes a click itself — only the overlay and
       the panel do, so a panel without an overlay leaves the page beside it usable. */
    .falcon-oc.is-open .falcon-oc__overlay, .falcon-oc.is-open .falcon-oc__panel { pointer-events: auto; }
    .falcon-oc__overlay { position: absolute; inset: 0; opacity: 0; transition: opacity var(--oc-dur, 350ms) ease; }
    .falcon-oc.is-open .falcon-oc__overlay { opacity: 1; }
    /* The panel never scrolls itself — its body does — so the close button, pinned to the
       panel, stays in reach however long the content is. */
    .falcon-oc__panel { position: absolute; display: flex; flex-direction: column; overflow: hidden; background: var(--oc-bg, #fff); outline: none;
        transition: transform var(--oc-dur, 350ms) cubic-bezier(.4, 0, .2, 1), opacity var(--oc-dur, 350ms) ease; }
    .falcon-oc__body { flex: 1 1 auto; min-height: 0; width: 100%; overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
    .falcon-oc--shadow .falcon-oc__panel { box-shadow: 0 10px 50px rgba(0, 0, 0, .25); }

    /* Where each panel sits, how big it is and how it slides in are per panel, and per
       device — printed next to the panel by OffCanvas::panelCss(). Fade and zoom only add
       the opacity; the transforms come from there too. */
    .falcon-oc--fade:not(.is-open) .falcon-oc__panel,
    .falcon-oc--zoom:not(.is-open) .falcon-oc__panel { opacity: 0; }

    .falcon-oc__close { position: absolute; top: 12px; right: 12px; z-index: 5; display: flex; align-items: center; justify-content: center;
        width: calc(var(--oc-close-size, 22px) + 14px); height: calc(var(--oc-close-size, 22px) + 14px); padding: 0; border: 0;
        border-radius: 50%; background: transparent; color: var(--oc-close, #1d2327); cursor: pointer; line-height: 1; transition: background .15s, transform .2s; }
    .falcon-oc__close:hover { background: rgba(127, 127, 127, .15); transform: rotate(90deg); }
    .falcon-oc__close:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    .falcon-oc__close svg { width: var(--oc-close-size, 22px); height: var(--oc-close-size, 22px); }

    html.falcon-oc-locked, html.falcon-oc-locked body { overflow: hidden !important; }
    @media (prefers-reduced-motion: reduce) {
        .falcon-oc, .falcon-oc__overlay, .falcon-oc__panel { transition-duration: 0s !important; }
    }
</style>
@endonce

@foreach($__ocPanels as $__oc)
@php
    $item = $__oc['item'];
    $s    = $__oc['s'];
    $slug = $item['slug'];
    $duration = $s['animation'] === 'none' ? 0 : $s['duration'];
    $overlayBg = $s['overlay'] === '1'
        ? (str_starts_with($s['overlay_color'], '#') ? _falcon_hex_to_rgba($s['overlay_color'], $s['overlay_opacity'] / 100) : $s['overlay_color'])
        : 'transparent';
    $overlayStyle = 'background:'.$overlayBg.';';
    if ($s['overlay'] !== '1') {
        $overlayStyle .= 'pointer-events:none;';
    } elseif ($s['overlay_blur'] > 0) {
        $overlayStyle .= 'backdrop-filter:blur('.$s['overlay_blur'].'px);-webkit-backdrop-filter:blur('.$s['overlay_blur'].'px);';
    }
    $devices = array_values(array_filter([
        $s['auto_desktop'] === '1' ? 'desktop' : null,
        $s['auto_tablet'] === '1' ? 'tablet' : null,
        $s['auto_mobile'] === '1' ? 'mobile' : null,
    ]));
    $auto = $s['trigger'] !== 'none' && OffCanvas::pageMatches($s);
    $js = [
        'id'      => $item['id'],
        'lock'    => $s['lock_scroll'] === '1',
        'esc'     => $s['close_esc'] === '1',
        'overlay' => $s['overlay'] === '1' && $s['close_overlay'] === '1',
        'trigger' => $auto ? $s['trigger'] : 'none',
        'delay'   => $s['trigger_delay'],
        'scroll'  => $s['trigger_scroll'],
        'freq'    => $s['frequency'],
        'days'    => $s['frequency_days'],
        'devices' => $devices,
    ];
    $style = '--oc-bg:'.$s['bg_color'].';--oc-dur:'.$duration.'ms;--oc-close:'.$s['close_color'].';';
    $panelCss = OffCanvas::panelCss('#falcon-oc-'.$slug, $s, $bpSm, $bpMed);
@endphp
{!! '<style>'.$panelCss.'</style>' !!}
<div id="falcon-oc-{{ $slug }}"
     class="falcon-oc falcon-oc--{{ $s['animation'] }}{{ $s['shadow'] === '1' ? ' falcon-oc--shadow' : '' }}"
     style="{{ $style }}" data-oc="{{ json_encode($js) }}" aria-hidden="true">
    {{-- No overlay means a non-modal panel: the page beside it stays clickable. --}}
    <div class="falcon-oc__overlay" style="{{ $overlayStyle }}"></div>
    <div class="falcon-oc__panel" role="dialog" aria-modal="true" aria-label="{{ $item['name'] ?? 'Panel' }}" tabindex="-1">
        @if($s['close_button'] === '1')
        <button type="button" class="falcon-oc__close" data-falcon-offcanvas-close aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        @endif
        <div class="falcon-oc__body">{!! $__oc['html'] !!}</div>
    </div>
</div>
@endforeach

@once
<script>
(function () {
    if (window.FalconOffCanvas) return;
    var BP_SM = {{ $bpSm }}, BP_MED = {{ $bpMed }};
    var stack = [];

    function panel(slug) { return slug ? document.getElementById('falcon-oc-' + slug) : null; }
    function conf(p) { try { return JSON.parse(p.getAttribute('data-oc') || '{}'); } catch (e) { return {}; } }
    function slugOf(p) { return p.id.replace(/^falcon-oc-/, ''); }
    function syncLock() {
        var locked = stack.some(function (p) { return conf(p).lock; });
        document.documentElement.classList.toggle('falcon-oc-locked', locked);
    }

    function open(slug, opener) {
        var p = panel(slug);
        if (!p || p.classList.contains('is-open')) return false;
        p._falconOpener = opener || document.activeElement;
        p.classList.add('is-open');
        p.setAttribute('aria-hidden', 'false');
        stack.push(p);
        syncLock();
        var box = p.querySelector('.falcon-oc__panel');
        setTimeout(function () { try { box.focus({ preventScroll: true }); } catch (e) {} }, 30);
        // Sliders, maps and masonry grids measure themselves on resize; inside a panel
        // that was hidden until now they would otherwise keep a zero-width layout.
        setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 60);
        p.dispatchEvent(new CustomEvent('falcon:offcanvas:open', { bubbles: true, detail: { slug: slug } }));
        return true;
    }

    function close(p) {
        p = p || stack[stack.length - 1];
        if (!p || !p.classList.contains('is-open')) return false;
        p.classList.remove('is-open');
        p.setAttribute('aria-hidden', 'true');
        stack = stack.filter(function (x) { return x !== p; });
        syncLock();
        if (p._falconOpener && p._falconOpener.focus) { try { p._falconOpener.focus({ preventScroll: true }); } catch (e) {} }
        if (location.hash === '#offcanvas-' + slugOf(p) && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.search);
        }
        p.dispatchEvent(new CustomEvent('falcon:offcanvas:close', { bubbles: true, detail: { slug: slugOf(p) } }));
        return true;
    }

    function toggle(slug, opener) {
        var p = panel(slug);
        if (!p) return false;
        return p.classList.contains('is-open') ? close(p) : open(slug, opener);
    }

    // The slug a link points at, but only when it points at THIS page — a link to
    // /other-page#offcanvas-x is left to navigate, and that page opens it on load.
    // Trailing slashes are ignored: menus print "#offcanvas-x" as "/page/#offcanvas-x".
    function samePath(a, b) { return a.replace(/\/+$/, '') === b.replace(/\/+$/, ''); }
    function linkSlug(a) {
        var href = a.getAttribute('href') || '';
        var i = href.indexOf('#offcanvas-');
        if (i < 0) return null;
        if (i > 0 && (!samePath(a.pathname, location.pathname) || a.host !== location.host)) return null;
        return decodeURIComponent(href.slice(i + 11));
    }

    document.addEventListener('click', function (e) {
        var t = e.target && e.target.closest ? e.target : null;
        if (!t) return;

        var closer = t.closest('[data-falcon-offcanvas-close]');
        if (closer) { e.preventDefault(); close(closer.closest('.falcon-oc')); return; }

        var overlay = t.closest('.falcon-oc__overlay');
        if (overlay) { var op = overlay.closest('.falcon-oc'); if (conf(op).overlay) close(op); return; }

        var trig = t.closest('[data-falcon-offcanvas], a[href*="#offcanvas-"]');
        if (trig) {
            var slug = trig.getAttribute('data-falcon-offcanvas') || linkSlug(trig);
            if (slug === 'close') { e.preventDefault(); close(trig.closest('.falcon-oc')); return; }
            if (slug && panel(slug)) { e.preventDefault(); toggle(slug, trig); }
            return;
        }

        // An in-page link inside an open panel ("#contact" in a mobile menu) closes the
        // panel so the section it scrolls to is not hidden behind it.
        var a = t.closest('.falcon-oc.is-open a[href^="#"]');
        if (a && a.getAttribute('href').length > 1) close(a.closest('.falcon-oc'));
    });

    document.addEventListener('keydown', function (e) {
        var top = stack[stack.length - 1];
        if (!top) return;
        if (e.key === 'Escape' && conf(top).esc) { close(top); return; }
        if (e.key !== 'Tab') return;
        // Keep keyboard focus inside the open panel.
        var box = top.querySelector('.falcon-oc__panel');
        var f = Array.prototype.filter.call(
            box.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])'),
            function (el) { return el.offsetWidth || el.offsetHeight || el.getClientRects().length; });
        if (!f.length) { e.preventDefault(); box.focus(); return; }
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && (document.activeElement === first || document.activeElement === box)) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    function fromHash() {
        var m = /^#offcanvas-(.+)$/.exec(location.hash);
        if (m && m[1] !== 'close') open(decodeURIComponent(m[1]));
    }
    window.addEventListener('hashchange', fromHash);

    // ── Auto open ───────────────────────────────────────────────────────────
    function device() {
        var w = window.innerWidth;
        return w <= BP_SM ? 'mobile' : (w <= BP_MED ? 'tablet' : 'desktop');
    }
    function seen(c) {
        try {
            if (c.freq === 'session') return sessionStorage.getItem('falcon_oc_' + c.id) === '1';
            if (c.freq === 'days') {
                var at = parseInt(localStorage.getItem('falcon_oc_' + c.id) || '0', 10);
                return at > 0 && (Date.now() - at) < c.days * 86400000;
            }
        } catch (e) {}
        return false;
    }
    function markSeen(c) {
        try {
            if (c.freq === 'session') sessionStorage.setItem('falcon_oc_' + c.id, '1');
            if (c.freq === 'days') localStorage.setItem('falcon_oc_' + c.id, String(Date.now()));
        } catch (e) {}
    }
    function autoOpen(p, c) {
        if (stack.length) return; // never stack a popup on top of a panel the visitor opened
        if (open(slugOf(p))) markSeen(c);
    }
    function arm(p) {
        var c = conf(p);
        if (!c.trigger || c.trigger === 'none') return;
        if ((c.devices || []).indexOf(device()) < 0 || seen(c)) return;
        if (c.trigger === 'load') {
            setTimeout(function () { autoOpen(p, c); }, Math.max(0, c.delay || 0) * 1000);
        } else if (c.trigger === 'scroll') {
            var onScroll = function () {
                var max = document.documentElement.scrollHeight - window.innerHeight;
                var pct = max > 0 ? (window.scrollY / max) * 100 : 100;
                if (pct >= (c.scroll || 50)) { window.removeEventListener('scroll', onScroll); autoOpen(p, c); }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
        } else if (c.trigger === 'exit') {
            var onOut = function (e) {
                if (e.relatedTarget || e.clientY > 0) return;
                document.removeEventListener('mouseout', onOut);
                autoOpen(p, c);
            };
            document.addEventListener('mouseout', onOut);
        }
    }

    function init() {
        fromHash();
        Array.prototype.forEach.call(document.querySelectorAll('.falcon-oc[data-oc]'), arm);
    }
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();

    window.FalconOffCanvas = {
        open: function (slug) { return open(slug); },
        close: function (slug) { return close(slug ? panel(slug) : null); },
        toggle: function (slug) { return toggle(slug); }
    };
})();
</script>
@endonce
