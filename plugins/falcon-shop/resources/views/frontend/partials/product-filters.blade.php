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

<link rel="stylesheet" href="{{ falcon_plugin_asset('falcon-shop', 'frontend/css/product-filters.css') }}">

<script data-no-defer src="{{ falcon_plugin_asset('falcon-shop', 'frontend/js/product-filters.js') }}"></script>

