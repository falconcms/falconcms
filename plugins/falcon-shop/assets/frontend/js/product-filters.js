function falconInitProductFilters() {
    var panel = document.getElementById('product-filters');
    var results = document.getElementById('falcon-results');
    if (!panel || !results || !window.history.pushState || !window.fetch) return;

    panel.setAttribute('data-falcon-js', '');

    var band = { min: parseFloat(panel.dataset.bandMin), max: parseFloat(panel.dataset.bandMax) };
    var step = parseFloat(panel.dataset.step) || 1;
    var money = JSON.parse(panel.dataset.currency || '{}');

    // ---- price formatting, mirroring falcon_price_format() ----------------------------------
    function formatPrice(value) {
        var decimals = money.decimals || 0;
        var parts = Math.abs(value).toFixed(decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, money.thousand || '');
        var n = (value < 0 ? '-' : '') + parts.join(money.decimal || '.');
        switch (money.position) {
            case 'right':       return n + money.symbol;
            case 'left_space':  return money.symbol + ' ' + n;
            case 'right_space': return n + ' ' + money.symbol;
            default:            return money.symbol + n;
        }
    }

    // ---- dual-handle price slider -----------------------------------------------------------
    var range = panel.querySelector('[data-falcon-range]');
    var handles = {}, labels = {}, payload = {}, fill = null;

    if (range) {
        fill = range.querySelector('[data-range-fill]');
        ['min', 'max'].forEach(function (side) {
            handles[side] = range.querySelector('[data-range-handle="' + side + '"]');
            labels[side] = panel.querySelector('[data-range-label="' + side + '"]');
            payload[side] = panel.querySelector('[data-range-value="' + side + '"]');
        });

        var paintRange = function () {
            var lo = parseFloat(handles.min.value);
            var hi = parseFloat(handles.max.value);
            var spread = band.max - band.min || 1;

            fill.style.left = ((lo - band.min) / spread * 100) + '%';
            fill.style.right = ((band.max - hi) / spread * 100) + '%';
            labels.min.textContent = formatPrice(lo);
            labels.max.textContent = formatPrice(hi);

            // Both thumbs sit on top of each other at the ends; raise whichever can still move.
            handles.min.style.zIndex = lo >= band.max - step ? 4 : 3;
            handles.max.style.zIndex = lo >= band.max - step ? 3 : 4;

            // An untouched edge contributes nothing to the URL.
            payload.min.value = lo > band.min ? lo : '';
            payload.max.value = hi < band.max ? hi : '';
        };

        // The handles must not cross; each one pushes against the other.
        handles.min.addEventListener('input', function () {
            if (parseFloat(handles.min.value) > parseFloat(handles.max.value)) {
                handles.min.value = handles.max.value;
            }
            paintRange();
        });
        handles.max.addEventListener('input', function () {
            if (parseFloat(handles.max.value) < parseFloat(handles.min.value)) {
                handles.max.value = handles.min.value;
            }
            paintRange();
        });

        paintRange();
    }

    // ---- URL <-> panel ----------------------------------------------------------------------
    function currentUrl() {
        var params = new URLSearchParams();
        new FormData(panel).forEach(function (value, key) {
            if (value !== '' && value !== null) params.append(key, value);
        });
        var qs = params.toString();
        return location.pathname + (qs ? '?' + qs : '');
    }

    function restoreFromUrl() {
        var params = new URLSearchParams(location.search);

        // Deliberately generic: every checkbox is restored by its own name and value, so a filter
        // added later (another attribute, a brand, a rating) needs no change here at all.
        panel.querySelectorAll('input[type="checkbox"][name]').forEach(function (box) {
            box.checked = params.getAll(box.name).indexOf(box.value) !== -1;
        });
        panel.querySelectorAll('input[type="search"][name], input[type="text"][name]').forEach(function (field) {
            field.value = params.get(field.name) || '';
        });
        setOrderby(params.get('orderby'));

        if (range) {
            var lo = parseFloat(params.get('min_price'));
            var hi = parseFloat(params.get('max_price'));
            handles.min.value = isFinite(lo) ? Math.min(Math.max(lo, band.min), band.max) : band.min;
            handles.max.value = isFinite(hi) ? Math.min(Math.max(hi, band.min), band.max) : band.max;
            paintRange();
            // Keep the hand-typed value (`0.15`) rather than the step-snapped thumb position.
            if (isFinite(lo)) payload.min.value = lo;
            if (isFinite(hi)) payload.max.value = hi;
        }
        syncClearLink();
    }

    function setOrderby(value) {
        var field = panel.querySelector('input[name="orderby"]');
        if (!value) { if (field) field.remove(); return; }
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'orderby';
            panel.appendChild(field);
        }
        field.value = value;
    }

    function syncClearLink() {
        var link = panel.querySelector('[data-falcon-clear]');
        if (!link) return;
        var params = new URLSearchParams(currentUrl().split('?')[1] || '');
        params.delete('orderby');
        link.classList.toggle('hidden', params.toString() === '');
    }

    // ---- fetch + swap -----------------------------------------------------------------------
    var inflight = null;

    // The address bar changes first and the fetch follows, so a slow network never leaves the
    // URL lagging behind the panel — and there is exactly one history entry per navigation.
    function navigate(url, options) {
        history.pushState({ falcon: true }, '', url);
        go(url, options);
    }

    function go(url, options) {
        options = options || {};
        if (inflight) inflight.abort();
        inflight = new AbortController();

        results.setAttribute('data-falcon-busy', '');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: inflight.signal
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('#falcon-results');
                if (!fresh) throw new Error('results block missing');

                results.innerHTML = fresh.innerHTML;
                results.removeAttribute('data-falcon-busy');
                wireResults();
                syncClearLink();

                // Injected markup never runs its own <script>, so anything that paints itself on
                // load has to be told again — the wishlist heart is a lucide placeholder.
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }

                if (options.scroll) results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                // A broken fetch must never leave the shopper stuck on stale results.
                window.location.href = url;
            });
    }

    // Debounced so dragging a handle fires one request, not one per pixel.
    var pending = null;
    function scheduleFilter(delay) {
        clearTimeout(pending);
        pending = setTimeout(function () {
            syncClearLink();
            navigate(currentUrl(), {});
        }, delay);
    }

    // ---- events -----------------------------------------------------------------------------
    panel.addEventListener('change', function (e) {
        if (e.target.type === 'checkbox') scheduleFilter(0);
    });
    panel.addEventListener('input', function (e) {
        if (e.target.type === 'range') scheduleFilter(350);
        // Longer than the slider: a search box is read letter by letter, and firing on every
        // keystroke would send a request for each prefix of the word.
        if (e.target.type === 'search' || e.target.type === 'text') scheduleFilter(500);
    });
    panel.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.target.type === 'search' || e.target.type === 'text')) {
            e.preventDefault();
            scheduleFilter(0);
        }
    });
    panel.addEventListener('submit', function (e) {
        e.preventDefault();
        scheduleFilter(0);
    });

    panel.addEventListener('click', function (e) {
        var clear = e.target.closest('[data-falcon-clear]');
        if (!clear) return;
        e.preventDefault();
        clearTimeout(pending);
        navigate(clear.href, { scroll: false });
        restoreFromUrl();   // the panel is never re-rendered, so reset it by hand
    });

    // Pagination links, the sort dropdown and the "Clear filters" button live inside the results
    // block, so they are re-bound after every swap.
    function wireResults() {
        var sort = results.querySelector('#sorting-form select[name="orderby"]');
        if (sort) {
            sort.onchange = null; // drop the inline this.form.submit() fallback
            sort.addEventListener('change', function () {
                setOrderby(sort.value === 'latest' ? null : sort.value);
                navigate(currentUrl(), { scroll: true });
            });
        }
    }

    results.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link || link.target) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#') return;

        var target;
        try { target = new URL(link.href, location.origin); } catch (err) { return; }
        if (target.origin !== location.origin || target.pathname !== location.pathname) return;

        // Detected by the URL, not by the markup: the two archive templates render pagination
        // differently (Laravel's default nav here, hand-rolled arrows there).
        var isPagination = target.searchParams.has('page');
        var isClear = link.hasAttribute('data-falcon-clear');
        if (!isPagination && !isClear) return;         // product links navigate normally

        e.preventDefault();
        clearTimeout(pending);
        navigate(link.href, { scroll: isPagination });
        restoreFromUrl();
    });

    window.addEventListener('popstate', function () {
        clearTimeout(pending);
        restoreFromUrl();
        go(location.href, { scroll: false });   // history already moved; just refill the results
    });

    wireResults();
    syncClearLink();
}

// This partial is included *above* the results column, so at parse time #falcon-results does not
// exist yet — without waiting for the document the panel would silently never upgrade.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', falconInitProductFilters);
} else {
    falconInitProductFilters();
}
