{{-- Where a sticky column or container lets go (its "Stop sticking" setting). Printed by
     each such node, not @once: the Content element pre-renders a post's layout in a
     throwaway pass that would spend it. A second copy only rescans for new nodes. --}}
<script>
(function () {
    if (window.__falconStickyEnd) { window.__falconStickyEnd(); return; }

    var items = [];

    function sticks(el) {
        var p = window.getComputedStyle(el).position;
        return p === 'sticky' || p === '-webkit-sticky';
    }

    // "#id", ".class" or a selector; anything else is a heading's text, matched whole
    // first and then by its start, outside the sticky box itself.
    function findStop(want, el) {
        want = String(want || '').trim();
        if (!want) return null;
        if (/^[#.\[]/.test(want)) {
            try { return document.querySelector(want); } catch (e) { return null; }
        }
        var norm = function (t) { return String(t || '').replace(/\s+/g, ' ').trim().toLowerCase(); };
        var key = norm(want), heads = Array.prototype.filter.call(document.querySelectorAll('h1,h2,h3,h4,h5,h6'), function (h) { return !el.contains(h); });
        var hit = null;
        heads.some(function (h) { if (norm(h.textContent) === key) { hit = h; return true; } return false; });
        if (!hit) heads.some(function (h) { if (norm(h.textContent).indexOf(key) === 0) { hit = h; return true; } return false; });
        // No such heading: a bare tag name, such as footer.
        if (!hit) { try { hit = document.querySelector(want); } catch (e) { hit = null; } }
        return hit;
    }

    // Where the box would sit without sticking, from its parent's top. Measured with
    // sticking switched off for a moment; taken from the parent, it holds while images
    // above load and the page grows.
    function measure(it) {
        var el = it.el, par = el.parentElement;
        if (!par) return;
        var v = el.style.getPropertyValue('position'), pr = el.style.getPropertyPriority('position');
        el.style.setProperty('position', 'static', 'important');
        it.rel = el.getBoundingClientRect().top - par.getBoundingClientRect().top;
        if (v) el.style.setProperty('position', v, pr); else el.style.removeProperty('position');
    }

    function release(it) {
        if (!it.set) return;
        it.el.style.removeProperty('top');
        it.set = false;
    }

    function update() {
        items.forEach(function (it) {
            var el = it.el;
            // Sticky can be off on this device.
            if (!sticks(el)) { release(it); return; }
            var want = it.top;
            if (it.mode === 'element') {
                if (it.stop === undefined) it.stop = findStop(it.value, el);
                // The box's bottom never crosses the stop element's top.
                if (it.stop && !el.contains(it.stop)) want = Math.min(it.top, it.stop.getBoundingClientRect().top - el.offsetHeight);
            } else if (el.parentElement) {
                // How far the reader has scrolled since the box stuck.
                var past = it.top - (el.parentElement.getBoundingClientRect().top + it.rel);
                if (past > it.value) want = it.top - (past - it.value);
            }
            if (want !== it.top) {
                // !important: a container's own sticky rule carries it.
                el.style.setProperty('top', Math.round(want) + 'px', 'important');
                it.set = true;
            } else release(it);
        });
    }

    var ro = ('ResizeObserver' in window) ? new ResizeObserver(function () { items.forEach(measure); update(); }) : null;

    function scan() {
        document.querySelectorAll('.lazy-sticky-col[data-sticky-end]:not([data-sticky-end-init])').forEach(function (el) {
            el.setAttribute('data-sticky-end-init', '');
            var mode = el.getAttribute('data-sticky-end');
            var raw = el.getAttribute('data-sticky-end-value') || '';
            var it = { el: el, mode: mode, value: mode === 'distance' ? (parseInt(raw, 10) || 0) : raw,
                top: parseInt(el.getAttribute('data-sticky-top'), 10) || 0, rel: 0, set: false };
            measure(it);
            items.push(it);
            if (ro && el.parentElement) ro.observe(el.parentElement);
        });
        update();
    }
    window.__falconStickyEnd = scan;

    var ticking = false;
    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () { ticking = false; update(); });
    }, { passive: true });
    window.addEventListener('resize', function () {
        items.forEach(function (it) { release(it); it.stop = undefined; measure(it); });
        update();
    });
    window.addEventListener('load', function () {
        items.forEach(function (it) { it.stop = undefined; measure(it); });
        update();
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
})();
</script>
