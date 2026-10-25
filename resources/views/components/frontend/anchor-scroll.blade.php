{{--
    Smooth scrolling to a section of this page, for every link on it: a builder button, a
    text link, an image link — anything whose address is #section or this-page#section.

    The landing-page menu (components/frontend/menu-scrollspy) had this first and keeps its
    own click handling for marking the current item, but it scrolls with the same
    window.falconScrollTo defined here, so a menu item and a button to the same section
    move the page the same way.

    Why not scrollIntoView({behavior: 'smooth'}): the browser works out where to stop once,
    at the click. Anything that moves after that — a lazy image above the section loading
    mid-scroll, a sticky header that shrinks once the page leaves the top — and it stops in
    the wrong place, or under the header. Here the destination is measured again on every
    frame, so the page arrives where the section is when it arrives, not where it was.

    Listens on window, after everything listening on document, and steps aside when a click
    was already handled (e.defaultPrevented): the off-canvas triggers, the table of
    contents and the menu all take their own clicks first.
--}}
<script>
(function () {
    if (window.__falconAnchorScroll) return;
    window.__falconAnchorScroll = true;

    var norm = function (p) { return p.replace(/\/+$/, '') || '/'; };
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /** How much of the top of the window a sticky or fixed header covers right now. */
    function headerOffset() {
        var h = 0;
        document.querySelectorAll('.main-header, .falcon-builder-header, [data-falcon-sticky-header]').forEach(function (el) {
            var pos = window.getComputedStyle(el).position;
            if (pos !== 'sticky' && pos !== 'fixed') return;
            var r = el.getBoundingClientRect();
            // Only one that is actually on screen at the top: a hidden mobile header, or one
            // that has slid away, covers nothing.
            if (r.height > 0 && r.bottom > 0 && r.top <= 1) h = Math.max(h, r.bottom);
        });
        return Math.round(h);
    }

    function destination(target) {
        var max = document.documentElement.scrollHeight - window.innerHeight;
        var y = target.getBoundingClientRect().top + window.scrollY - headerOffset();
        return Math.max(0, Math.min(y, max));
    }

    // A page that sets scroll-behavior: smooth in its CSS would smooth every single step
    // of the animation again and turn it into a stutter, so steps are always instant.
    function jump(y) {
        var html = document.documentElement, was = html.style.scrollBehavior;
        html.style.scrollBehavior = 'auto';
        window.scrollTo(0, y);
        html.style.scrollBehavior = was;
    }

    var running = null;
    var touched = false;

    function stop() {
        if (running) { window.cancelAnimationFrame(running.frame); running = null; }
    }

    // The reader takes over: a wheel, a touch or a key ends the animation where it is,
    // instead of fighting them for the page.
    ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(function (type) {
        window.addEventListener(type, function () { touched = true; if (running) stop(); }, { passive: true });
    });

    /**
     * Scroll so the target's top sits just below the sticky header.
     * Duration follows the distance, so a short hop is quick and a long one is not a blur.
     */
    window.falconScrollTo = function (target, opts) {
        if (!target) return;
        opts = opts || {};
        stop();

        var start = window.scrollY;
        if (reduced || opts.smooth === false) {
            jump(destination(target));
            return;
        }

        var distance = Math.abs(destination(target) - start);
        var duration = Math.max(350, Math.min(1100, 250 + distance * 0.35));
        var begun = null;
        var ease = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };

        var run = { frame: 0 };
        running = run;

        function step(now) {
            if (running !== run) return;
            if (begun === null) begun = now;
            var t = Math.min(1, (now - begun) / duration);
            var to = destination(target);
            jump(Math.round(start + (to - start) * ease(t)));

            if (t < 1) {
                run.frame = window.requestAnimationFrame(step);
                return;
            }
            running = null;
            // One more look a moment later: a header that changes size as the page leaves
            // the top does so after the scroll that caused it.
            window.setTimeout(function () {
                if (running) return;
                var again = destination(target);
                if (Math.abs(window.scrollY - again) > 2) jump(again);
            }, 120);
        }
        run.frame = window.requestAnimationFrame(step);
    };

    /**
     * The address for a section of this page, always with the slash before the fragment:
     * /pricing/#plans, never /pricing#plans. The same spelling falcon_anchor_url() gives the
     * links on the server, so the address bar and the menu's links agree.
     */
    window.falconSectionUrl = function (id) {
        var path = window.location.pathname;
        if (path.charAt(path.length - 1) !== '/') path += '/';
        return path + window.location.search + '#' + id;
    };

    /** The element a link points at, when it is a section of this very page; otherwise null. */
    window.falconAnchorTarget = function (a) {
        var href = a.getAttribute('href');
        if (!href || href.indexOf('#') === -1) return null;
        var url;
        try { url = new URL(href, window.location.href); } catch (e) { return null; }
        if (url.origin !== window.location.origin || norm(url.pathname) !== norm(window.location.pathname)) return null;
        if (url.search !== window.location.search) return null;
        if (url.hash.length < 2) return null;

        var raw = url.hash.slice(1);
        try { raw = decodeURIComponent(raw); } catch (e) { /* keep it as written */ }
        var el = document.getElementById(raw) || document.getElementById(raw.trim());
        // A section that is not drawn (a closed tab, a hidden device-only copy) has nowhere
        // to scroll to; the browser's own handling is left to it.
        return el && el.getClientRects().length ? el : null;
    };

    window.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.hasAttribute('download')) return;
        var t = (a.getAttribute('target') || '').toLowerCase();
        if (t && t !== '_self') return;
        // Links that belong to a widget's own behaviour, not to navigation.
        if (a.getAttribute('role') === 'tab' || a.hasAttribute('data-toggle') || a.hasAttribute('data-bs-toggle')) return;

        var target = window.falconAnchorTarget(a);
        if (!target) return;

        e.preventDefault();
        window.falconScrollTo(target);
        // The address bar names the section, without the jump assigning location.hash causes.
        var url = window.falconSectionUrl(target.id);
        if (window.location.pathname + window.location.search + window.location.hash !== url && window.history && window.history.pushState) {
            window.history.pushState(null, '', url);
        }
    });

    // Arriving on /page#section from somewhere else: the browser has already jumped, with the
    // section's top under the sticky header. Put it where a click would, without animating a
    // position the reader is already looking at — and again once images have moved things.
    function landed() {
        if (window.location.hash.length < 2) return null;
        var raw = window.location.hash.slice(1);
        try { raw = decodeURIComponent(raw); } catch (e) { /* keep it as written */ }
        var el = document.getElementById(raw);
        return el && el.getClientRects().length ? el : null;
    }
    function settle() {
        // Never pull back a reader who has already started scrolling on their own.
        var el = landed();
        if (el && !running && !touched) window.falconScrollTo(el, { smooth: false });
    }
    if (document.readyState === 'complete') {
        settle();
    } else {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', settle);
        else settle();
        window.addEventListener('load', settle);
    }

    // Back and forward between sections the reader visited by clicking.
    window.addEventListener('popstate', function () {
        var el = landed();
        if (el) window.falconScrollTo(el);
    });
})();
</script>
