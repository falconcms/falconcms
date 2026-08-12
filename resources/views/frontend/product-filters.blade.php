{{--
    Archive filter panel: search, price range, category, attributes, in-stock and on-sale.

    Progressive enhancement, deliberately. The markup is a plain GET form that works with
    JavaScript switched off — every filtered view is a real, shareable URL such as
    `?min_price=0.15&max_price=&product_cat[]=google&in_stock=1`. Where JS is available the
    panel upgrades itself: the Apply button disappears, changes fetch the same URL in the
    background, only the results column is swapped in, and history.pushState keeps the address
    bar (and the back button) honest.

    Expects: $filterOptions (from falcon_product_filter_options()).
    Requires the surrounding template to wrap its results column in #falcon-results.
--}}
@php
    $active   = falcon_product_filters_active();
    $catList  = $filterOptions['categories'] ?? collect();
    $attrList = $filterOptions['attributes'] ?? [];
    $bandMin  = (float) ($filterOptions['min_price'] ?? 0);
    $bandMax  = (float) ($filterOptions['max_price'] ?? 0);
    $hasAny   = $active['search'] !== '' || $active['min_price'] !== null || $active['max_price'] !== null
                || !empty($active['categories']) || !empty($active['attributes'])
                || $active['in_stock'] || $active['on_sale'];

    // Where the handles start: the URL when it says something, the band edges otherwise.
    $curMin = $active['min_price'] !== null ? max($bandMin, min($bandMax, (float) $active['min_price'])) : $bandMin;
    $curMax = $active['max_price'] !== null ? max($bandMin, min($bandMax, (float) $active['max_price'])) : $bandMax;

    // A step proportional to the range: cents for a cheap shop, whole units for an expensive one.
    $span = max(0.0, $bandMax - $bandMin);
    $step = $span < 20 ? 0.01 : ($span < 200 ? 0.1 : 1);

    // Mirrored in JS so the live labels match falcon_price_format() exactly.
    $currency = [
        'symbol'   => \FalconCms\Core\Services\EcommerceData::getCurrencySymbol(get_shop_option('shop_currency', 'USD')),
        'position' => get_shop_option('shop_currency_pos', 'left'),
        'decimals' => (int) get_shop_option('shop_num_decimals', 2),
        'thousand' => get_shop_option('shop_thousand_sep', ','),
        'decimal'  => get_shop_option('shop_decimal_sep', '.'),
    ];
@endphp

<aside class="w-full lg:w-[260px] shrink-0">
    <form method="GET" action="" id="product-filters" class="space-y-8"
          data-band-min="{{ $bandMin }}"
          data-band-max="{{ $bandMax }}"
          data-step="{{ $step }}"
          data-currency="{{ json_encode($currency) }}">

        {{-- Keeps the current sort when a filter is applied. JS rewrites this as the sort changes. --}}
        @if(request('orderby'))
            <input type="hidden" name="orderby" value="{{ request('orderby') }}">
        @endif

        <div class="flex items-center justify-between">
            <h2 class="text-[16px] font-bold text-heading uppercase tracking-tight">Filter</h2>
            <a href="{{ url()->current() }}{{ request('orderby') ? '?orderby=' . urlencode(request('orderby')) : '' }}"
               data-falcon-clear
               class="text-[12px] text-primary hover:underline {{ $hasAny ? '' : 'hidden' }}">Clear all</a>
        </div>

        <div>
            <label for="falcon-product-search" class="block text-[13px] font-bold text-heading mb-3 uppercase tracking-wide">Search</label>
            <div class="relative">
                <input type="search" name="s" id="falcon-product-search" value="{{ $active['search'] }}"
                       placeholder="Search products&hellip;" autocomplete="off"
                       class="w-full border border-gray-300 pl-3 pr-9 py-2 text-[13px] outline-none focus:border-primary">
                <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                </svg>
            </div>
        </div>

        @if($bandMax > $bandMin)
        <div>
            <h3 class="text-[13px] font-bold text-heading mb-4 uppercase tracking-wide">Price</h3>

            <div class="falcon-range text-primary" data-falcon-range>
                <div class="falcon-range__rail"></div>
                <div class="falcon-range__fill" data-range-fill></div>
                <input type="range" data-range-handle="min" aria-label="Minimum price"
                       min="{{ $bandMin }}" max="{{ $bandMax }}" step="{{ $step }}" value="{{ $curMin }}">
                <input type="range" data-range-handle="max" aria-label="Maximum price"
                       min="{{ $bandMin }}" max="{{ $bandMax }}" step="{{ $step }}" value="{{ $curMax }}">
            </div>

            {{-- The real payload. Left empty at the band edges so an untouched slider adds nothing
                 to the URL — `?max_price=` with no value is accepted too. --}}
            <input type="hidden" name="min_price" data-range-value="min"
                   value="{{ $active['min_price'] !== null ? (float) $active['min_price'] : '' }}">
            <input type="hidden" name="max_price" data-range-value="max"
                   value="{{ $active['max_price'] !== null ? (float) $active['max_price'] : '' }}">

            <p class="text-[12px] text-body mt-3">
                <span data-range-label="min">{{ falcon_price_format($curMin) }}</span>
                <span class="text-gray-400 mx-1">&ndash;</span>
                <span data-range-label="max">{{ falcon_price_format($curMax) }}</span>
            </p>
        </div>
        @endif

        @if($catList->count())
        <div>
            <h3 class="text-[13px] font-bold text-heading mb-3 uppercase tracking-wide">Category</h3>
            <div class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
                @foreach($catList as $cat)
                    <label class="flex items-center gap-2 cursor-pointer text-[13px] text-body">
                        <input type="checkbox" name="product_cat[]" value="{{ $cat->slug }}"
                               {{ in_array($cat->slug, $active['categories'], true) ? 'checked' : '' }}
                               class="w-4 h-4 border-gray-300 text-primary focus:ring-0">
                        <span class="flex-grow">{{ $cat->name }}</span>
                        <span class="text-gray-400 text-[12px]">({{ $cat->total }})</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Whatever the shop's products declare, in whatever order. Nothing is hard-coded here,
             so a brand new attribute appears on its own once a product uses it. --}}
        @foreach($attrList as $attribute)
        <div>
            <h3 class="text-[13px] font-bold text-heading mb-3 uppercase tracking-wide">{{ $attribute['name'] }}</h3>
            <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                @foreach($attribute['values'] as $value)
                    <label class="flex items-center gap-2 cursor-pointer text-[13px] text-body">
                        <input type="checkbox" name="attr[{{ $attribute['slug'] }}][]" value="{{ $value['slug'] }}"
                               {{ in_array($value['slug'], $active['attributes'][$attribute['slug']] ?? [], true) ? 'checked' : '' }}
                               class="w-4 h-4 border-gray-300 text-primary focus:ring-0">
                        <span class="flex-grow">{{ $value['label'] }}</span>
                        <span class="text-gray-400 text-[12px]">({{ $value['total'] }})</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endforeach

        <div>
            <h3 class="text-[13px] font-bold text-heading mb-3 uppercase tracking-wide">Availability</h3>
            <div class="space-y-2">
                <label class="flex items-center gap-2 cursor-pointer text-[13px] text-body">
                    <input type="checkbox" name="in_stock" value="1" {{ $active['in_stock'] ? 'checked' : '' }}
                           class="w-4 h-4 border-gray-300 text-primary focus:ring-0">
                    <span>In stock only</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-[13px] text-body">
                    <input type="checkbox" name="on_sale" value="1" {{ $active['on_sale'] ? 'checked' : '' }}
                           class="w-4 h-4 border-gray-300 text-primary focus:ring-0">
                    <span>On sale</span>
                </label>
            </div>
        </div>

        {{-- Only reachable without JavaScript; the enhanced panel hides it. --}}
        <button type="submit" data-falcon-apply
                class="w-full bg-primary text-white py-2.5 text-[12px] font-bold rounded-sm hover:opacity-90 transition-all uppercase tracking-wider">
            Apply filters
        </button>
    </form>
</aside>

<style>
    .falcon-range { position: relative; height: 26px; }
    .falcon-range__rail,
    .falcon-range__fill { position: absolute; top: 11px; height: 3px; border-radius: 3px; }
    .falcon-range__rail { left: 0; right: 0; background: #e5e7eb; }
    .falcon-range__fill { background: currentColor; }

    .falcon-range input[type="range"] {
        position: absolute; top: 0; left: 0; width: 100%; height: 26px; margin: 0;
        background: none; border: 0; padding: 0;
        -webkit-appearance: none; appearance: none;
        /* Only the thumbs are grabbable, so the two stacked inputs don't block each other. */
        pointer-events: none;
    }
    .falcon-range input[type="range"]:focus { outline: none; }
    .falcon-range input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none; appearance: none; pointer-events: auto;
        width: 15px; height: 15px; border-radius: 50%;
        background: #fff; border: 2px solid currentColor; cursor: grab;
        box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .falcon-range input[type="range"]::-moz-range-thumb {
        pointer-events: auto; width: 15px; height: 15px; border-radius: 50%;
        background: #fff; border: 2px solid currentColor; cursor: grab;
        box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .falcon-range input[type="range"]:focus-visible::-webkit-slider-thumb { box-shadow: 0 0 0 3px rgba(0,0,0,.15); }
    .falcon-range input[type="range"]::-moz-range-track { background: none; border: 0; }

    /* JS is driving: the manual Apply button is redundant. */
    #product-filters[data-falcon-js] [data-falcon-apply] { display: none; }

    #falcon-results { transition: opacity .15s ease; }
    #falcon-results[data-falcon-busy] { opacity: .45; pointer-events: none; }
</style>

<script>
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
</script>

