@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', 'Cart')

@section('content')
<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        <h1 class="text-[36px] font-normal text-[#2c3338] mb-8">Cart</h1>

        @if(function_exists('falcon_freemium_grace_active') && falcon_freemium_grace_active() && ! (function_exists('falcon_licensed') && falcon_licensed()))
            <div class="border border-amber-300 bg-amber-50 text-amber-900 p-4 mb-8 rounded flex items-start gap-3 text-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <div>
                    <strong>Action needed:</strong> The online store (cart &amp; checkout) is a Pro feature.
                    @php $graceUntil = config('falcon-options.freemium_grace_until'); @endphp
                    @if($graceUntil)
                        You can keep using it until <strong>{{ \Illuminate\Support\Carbon::parse($graceUntil)->format('M j, Y') }}</strong>, after which cart &amp; checkout will be locked.
                    @else
                        It will be locked soon.
                    @endif
                    Please <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="font-semibold underline">upgrade to Pro</a> to keep selling.
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="bg-blue-50 border-t-2 border-blue-500 p-4 mb-8 text-blue-800 text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                {{ session('success') }}
            </div>
        @endif

        {{-- cart toast --}}
        <div id="cart-toast" style="display:none" class="fixed top-6 right-6 z-50 items-center gap-3 bg-white border border-gray-200 shadow-lg rounded px-5 py-3 text-sm font-medium text-gray-700">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 shrink-0"></i>
            <span id="cart-toast-msg"></span>
        </div>

        @if(empty($cart))
            <div class="py-20 text-center border border-dashed border-gray-200 rounded">
                <div class="mb-6 opacity-20">
                    <i data-lucide="shopping-cart" class="w-20 h-20 mx-auto"></i>
                </div>
                <h2 class="text-2xl font-bold text-heading mb-2">Your cart is currently empty.</h2>
                <p class="text-gray-500 mb-8">Before you proceed to checkout you must add some products to your shopping cart.</p>
                <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-primary text-white px-8 py-3 rounded-sm font-bold hover:opacity-90 hover:text-white transition-all uppercase text-sm">Return to shop</a>
            </div>
        @else
            <form id="cart-form" action="{{ route('shop.cart.update') }}" method="POST">
                @csrf
                <div class="overflow-x-auto mb-10 relative" id="cart-table-wrap">
                    {{-- loading overlay --}}
                    <div id="cart-loader" style="display:none" class="absolute inset-0 bg-white/70 z-10 items-center justify-center">
                        <svg class="animate-spin w-9 h-9 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </div>
                    <table class="w-full text-left border-collapse border border-gray-100">
                        <thead>
                            <tr class="bg-gray-50 text-[14px] font-bold text-gray-700 uppercase tracking-wider">
                                <th class="p-4 border border-gray-100"></th>
                                <th class="p-4 border border-gray-100"></th>
                                <th class="p-4 border border-gray-100">Product</th>
                                <th class="p-4 border border-gray-100">Price</th>
                                <th class="p-4 border border-gray-100">Quantity</th>
                                <th class="p-4 border border-gray-100">Subtotal</th>
                            </tr>
                        </thead>
                        <?php do_falcon_action('falcon_before_cart_items', $cart); ?>
                        <tbody id="cart-items-body" class="text-[15px] text-gray-600">
                            @foreach($cart as $key => $item)
                                <?php do_falcon_action('falcon_before_cart_item', $item, $key); ?>
                                <tr class="border-b border-gray-100 cart-item-row" data-key="{{ $key }}">
                                    <td class="p-4 border border-gray-100 text-center w-10">
                                        <button type="button" onclick="removeCartItem('{{ $key }}', this)" class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                                    </td>
                                    <td class="p-4 border border-gray-100 w-24">
                                        <a href="{{ route('frontend.show', ['typeOrSlug' => 'product', 'slug' => $item['slug']]) }}">
                                            <img src="{{ get_falcon_image_url($item['thumbnail']) }}" alt="{{ $item['name'] }}" class="w-16 h-16 object-cover border border-gray-100">
                                        </a>
                                    </td>
                                    <td class="p-4 border border-gray-100 font-bold text-primary">
                                        {!! apply_falcon_filters('falcon_cart_item_name',
                                            '<a href="' . get_falcon_permalink($item) . '">' . e($item['name']) . '</a>',
                                            $item, $key) !!}
                                        {!! falcon_render_item_custom_fields($item, 'cart') !!}
                                        @php
                                            // Looked up live: stock can run out between adding to the
                                            // cart and checking out, and the customer should see that
                                            // before they pay rather than after.
                                            $cartShopData = \FalconCms\Core\Models\ProductData::where('post_id', $item['id'])->first();
                                        @endphp
                                        @if($cartShopData && $cartShopData->showsBackorderNotice())
                                            <div class="text-[12px] text-amber-700 font-normal mt-1">Available on backorder</div>
                                        @endif
                                        <?php do_falcon_action('falcon_cart_item_meta', $item, $key); ?>
                                    </td>
                                    <td class="p-4 border border-gray-100">
                                        {{ falcon_price_format($item['sale_price'] ?: $item['price']) }}
                                    </td>
                                    <td class="p-4 border border-gray-100">
                                        <div class="flex items-center border border-gray-200 rounded-sm h-10 w-fit bg-white overflow-hidden">
                                            <button type="button" onclick="stepQty(this, -1)" class="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-50 border-r border-gray-100 font-bold select-none">-</button>
                                            <input type="text" name="quantity[{{ $key }}]" value="{{ $item['quantity'] }}" readonly class="w-10 h-full text-center border-none focus:ring-0 text-sm font-bold text-gray-800 p-0 cursor-default">
                                            <button type="button" onclick="stepQty(this, 1)" class="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-50 border-l border-gray-100 font-bold select-none">+</button>
                                        </div>
                                    </td>
                                    <td class="p-4 border border-gray-100 font-bold text-heading item-subtotal">
                                        {{ falcon_price_format(($item['sale_price'] ?: $item['price']) * $item['quantity']) }}
                                    </td>
                                </tr>
                                <?php do_falcon_action('falcon_after_cart_item', $item, $key); ?>
                            @endforeach
                            <tr>
                                <td colspan="6" class="p-4 border border-gray-100">
                                    <div class="flex flex-col md:flex-row justify-between gap-4">
                                        @if(get_shop_option('shop_enable_coupons', '1') === '1')
                                        <div>
                                            <div class="flex gap-2">
                                                <input type="text" id="coupon_code_input" placeholder="Coupon code" class="border border-gray-300 px-4 py-2 text-sm focus:border-primary outline-none min-w-[150px]">
                                                <button type="button" onclick="applyCoupon()" class="bg-primary text-white px-6 py-2 text-sm font-bold hover:opacity-90 transition-all uppercase">Apply coupon</button>
                                            </div>
                                            <div id="coupon-message" class="mt-2 text-xs"></div>
                                        </div>
                                        @endif
                                        {{-- md:ml-auto, not justify-between: with coupons switched off this button is
                                             the row's only child, and justify-between parks a lone child at the start.
                                             An auto left margin keeps it on the right either way. --}}
                                        <button type="button" id="update-cart-btn" onclick="updateCartAjax()" class="bg-primary text-white px-8 py-2 text-sm font-bold hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed transition-all uppercase md:ml-auto {{ get_shop_option('shop_enable_coupons', '1') !== '1' ? 'w-full md:w-auto' : '' }}">Update cart</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>{{-- /#cart-table-wrap --}}
            </form>

            {{-- Container kept even when empty so the AJAX repaint has somewhere to write:
                 a quantity change can newly qualify the cart for an offer. --}}
            <div id="cart-promo-offers">
                @include('falcon-cms::frontend.promotion-offers', ['offers' => falcon_pending_promotion_offers($cart)])
            </div>

            <div class="flex flex-col md:flex-row justify-end">
                <div class="w-full md:w-[450px]">
                    <h2 class="text-2xl font-bold text-[#2c3338] mb-6">Cart totals</h2>
                    <table class="w-full border-collapse border border-gray-100 mb-8">
                        <tbody id="cart-totals-body">
                            <tr class="border-b border-gray-100">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700 w-1/3">Subtotal</th>
                                <td class="p-4 font-bold text-heading" id="cart-subtotal">{{ falcon_price_format(get_falcon_cart_subtotal()) }}</td>
                            </tr>
                            <tr class="border-b border-gray-100">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700">Shipping</th>
                                <td class="p-4 text-sm" id="cart-shipping-cell">
                                    @php
                                        $shipCountry = falcon_customer_shipping_country();
                                        $shipMethods = falcon_shipping_methods($shipCountry);
                                        $shipDetails = get_falcon_cart_shipping_details($shipCountry);
                                    @endphp
                                    <div id="cart-shipping">
                                        @if(!empty($shipDetails['pending']))
                                            <span class="text-gray-500">Enter your address to see shipping options.</span>
                                        @elseif($shipDetails['cost'] > 0)
                                            {{ $shipDetails['label'] }}: <span class="font-bold text-heading">{{ falcon_price_format($shipDetails['cost']) }}</span>
                                        @else
                                            <span class="font-bold text-heading">{{ $shipDetails['label'] }}</span>
                                        @endif
                                    </div>

                                    @if(empty($shipDetails['pending']) && count($shipMethods) > 1)
                                        {{-- Local Pickup is enabled, so the cart offers the choice too and the
                                             totals below follow it. Only the id is sent; the server prices it. --}}
                                        <div class="mt-3 space-y-2" id="cart-shipping-methods">
                                            @foreach($shipMethods as $method)
                                                <label class="flex items-center gap-2 cursor-pointer text-[13px]">
                                                    <input type="radio" name="cart_shipping_method" value="{{ $method['id'] }}"
                                                           {{ $shipDetails['method'] === $method['id'] ? 'checked' : '' }}
                                                           onchange="updateShipping()"
                                                           class="w-4 h-4 text-primary focus:ring-0 border-gray-300">
                                                    <span>{{ $method['label'] }} &mdash;
                                                        <span class="font-bold text-heading">{{ $method['cost'] > 0 ? falcon_price_format($method['cost']) : 'Free' }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(get_shop_option('shop_calc_enable_cart_estimator', '1') === '1')
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        <a href="javascript:void(0)" onclick="document.getElementById('shipping-estimator').classList.toggle('hidden')" class="text-primary hover:underline text-[13px] font-semibold flex items-center gap-1">
                                            <i data-lucide="truck" class="w-3 h-3"></i>
                                            Calculate shipping
                                        </a>
                                        {{-- Open when a destination is already known: the <select> below keeps its
                                             selection across reloads, but a collapsed panel hid that from the customer. --}}
                                        <div id="shipping-estimator" class="{{ $shipCountry ? '' : 'hidden' }} mt-3 space-y-3">
                                            <select id="shipping_country" class="w-full border border-gray-300 px-3 py-2 text-sm outline-none focus:border-primary">
                                                <option value="">Select a country...</option>
                                                @foreach(\FalconCms\Core\Services\EcommerceData::getCountries() as $code => $name)
                                                    <option value="{{ $code }}" {{ $shipCountry === $code ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" id="cart-shipping-update-btn" onclick="updateShipping()" class="w-full bg-primary text-white py-2.5 text-[12px] font-bold rounded-sm hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed transition-all uppercase tracking-wider shadow-sm shadow-primary/20">Update totals</button>
                                        </div>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @php $cartTax = falcon_tax_enabled() ? get_falcon_cart_tax() : 0; @endphp
                            @if(falcon_tax_enabled())
                            {{-- Always in the DOM, hidden at zero: changing the country changes the
                                 tax rate, and the estimator has to be able to show/hide this row
                                 without rebuilding the table. --}}
                            <tr class="border-b border-gray-100" id="cart-tax-row" @if($cartTax <= 0) style="display:none" @endif>
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700">
                                    <span id="cart-tax-prefix" @unless(falcon_prices_include_tax()) style="display:none" @endunless>Includes </span><span id="cart-tax-label">{{ falcon_cart_tax_label() }}</span>
                                </th>
                                {{-- Muted in inclusive mode: the amount is already inside the subtotal, so it must
                                     not read like another line being added to the total. --}}
                                <td class="p-4 {{ falcon_prices_include_tax() ? 'font-normal text-gray-500' : 'font-bold text-heading' }}" id="cart-tax">{{ falcon_price_format($cartTax) }}</td>
                            </tr>
                            @endif
                            
                            @php 
                                $appliedCoupons = session()->get('falcon_coupons', []); 
                                $subtotal = get_falcon_cart_subtotal(); 
                                $currentSubtotal = $subtotal;
                                $isMultipleAllowed = (int)get_shop_option('shop_coupon_stacking_policy', '1') === 1;
                            @endphp
                            @foreach($appliedCoupons as $coupon)
                                @php
                                    // Same helper the order total uses. The old inline formula ignored each
                                    // coupon's product/category restriction and did not understand
                                    // fixed_product or free_shipping, so the row could disagree with the total.
                                    $calcBase = $isMultipleAllowed ? $currentSubtotal : $subtotal;
                                    $discount = get_falcon_coupon_discount_amount($coupon, $cart, $calcBase);
                                    $currentSubtotal -= $discount;
                                @endphp
                                @if($discount > 0 || ($coupon['type'] ?? '') === 'free_shipping')
                                <tr class="coupon-row bg-emerald-50/10 border-b border-gray-100">
                                    <th class="p-4 bg-gray-50 text-left font-bold text-emerald-700 w-1/3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            Coupon: {{ $coupon['code'] }}
                                            <a href="{{ route('shop.cart.coupon.remove') }}?code={{ urlencode($coupon['code']) }}" class="text-rose-500 hover:text-rose-700 text-[10px] font-normal">[Remove]</a>
                                        </div>
                                    </th>
                                    <td class="p-4 font-bold text-emerald-700">
                                        @if(($coupon['type'] ?? '') === 'free_shipping')
                                            Free shipping
                                        @else
                                            -{{ falcon_price_format($discount) }}
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            @endforeach

                            {{-- Automatic promotions — no code to type, the engine decides from the cart. --}}
                            @foreach(falcon_evaluate_promotions($cart) as $promo)
                                <tr class="promotion-row bg-amber-50/40 border-b border-gray-100">
                                    <th class="p-4 bg-gray-50 text-left font-bold text-amber-700 w-1/3">
                                        <div class="flex items-center gap-2">
                                            <span>&#127873;</span>
                                            <span>{{ $promo['name'] }}</span>
                                        </div>
                                        <div class="text-[11px] font-normal text-amber-700/70 mt-0.5">{{ $promo['summary'] }}</div>
                                    </th>
                                    <td class="p-4 font-bold text-amber-700">-{{ falcon_price_format($promo['discount']) }}</td>
                                </tr>
                            @endforeach

                            <tr class="bg-gray-50">
                                <th class="p-4 text-left font-extrabold text-heading">Total</th>
                                <td class="p-4 text-xl font-black text-primary" id="cart-total">{{ falcon_price_format(get_falcon_cart_total()) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <a href="{{ get_lazy_checkout_url() }}" class="block w-full bg-primary text-white text-center py-4 font-bold rounded-sm hover:opacity-90 hover:text-white transition-all uppercase shadow-md shadow-primary/20">Proceed to checkout</a>
                </div>
            </div>

            {{-- Cross-sells: what goes alongside what is already in the basket. Only shown when
                 the shop owner picked something, and never for items already in the cart. --}}
            @include('falcon-cms::frontend.product-row', [
                'products'   => falcon_cart_cross_sells(),
                'heading'    => 'You may be interested in',
                'subheading' => 'Goes well with what you have chosen.',
            ])
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const CSRF = '{{ csrf_token() }}';
    const HEADERS = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': CSRF,
    };

    // ── Loader ─────────────────────────────────────────────────────
    function loaderShow() { document.getElementById('cart-loader').style.display = 'flex'; }
    function loaderHide() { document.getElementById('cart-loader').style.display = 'none'; }

    // ── Toast ──────────────────────────────────────────────────────
    let toastTimer;
    function showCartToast(msg, isError) {
        // Use SweetAlert2 via LazyCart if available
        if (window.LazyCart && typeof LazyCart.toast === 'function') {
            LazyCart.toast(msg, isError ? 'error' : 'success');
            return;
        }
        const toast = document.getElementById('cart-toast');
        const icon  = toast.querySelector('[data-lucide]');
        document.getElementById('cart-toast-msg').textContent = msg;
        icon.setAttribute('data-lucide', isError ? 'alert-circle' : 'check-circle');
        icon.className = 'w-4 h-4 shrink-0 ' + (isError ? 'text-rose-500' : 'text-emerald-500');
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [icon] });
        toast.style.display = 'flex';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    // ── Sync mini-cart badge + drawer ──────────────────────────────
    function syncMiniCart(count) {
        if (window.LazyCart) {
            LazyCart.setBadges(count);
            // Refresh drawer content silently (doesn't open it)
            LazyCart.refresh();
        }
    }

    // ── Shared totals updater ──────────────────────────────────────
    // Single place that paints the totals table. Every cart endpoint returns the same payload,
    // so a country change, a quantity change and a coupon all end up perfectly in sync — the
    // page after this runs looks exactly like a fresh reload.
    function applyTotals(data) {
        const set = (id, html) => { const el = document.getElementById(id); if (el && html !== undefined) el.innerHTML = html; };

        set('cart-subtotal', data.subtotal);
        set('cart-shipping', data.shipping);
        set('cart-total',    data.total);

        // Tax: amount, label and whether the row belongs on screen at all — the rate follows
        // the country, so it can appear or vanish as the customer changes the estimator.
        set('cart-tax',       data.tax);
        set('cart-tax-label', data.tax_label);
        const taxRow = document.getElementById('cart-tax-row');
        if (taxRow && data.tax_visible !== undefined) {
            taxRow.style.display = data.tax_visible ? '' : 'none';
        }
        // Inclusive tax is part of the subtotal, so it is shown muted and prefixed with
        // "Includes" — styled here as well as server-side, or an AJAX repaint would lose it.
        const taxPrefix = document.getElementById('cart-tax-prefix');
        const taxCell   = document.getElementById('cart-tax');
        if (taxPrefix && taxCell && data.tax_included !== undefined) {
            taxPrefix.style.display = data.tax_included ? '' : 'none';
            taxCell.className = 'p-4 ' + (data.tax_included ? 'font-normal text-gray-500' : 'font-bold text-heading');
        }

        // Shipping method radios: re-price them and keep the checked one in step with the server.
        const box = document.getElementById('cart-shipping-methods');
        if (box && Array.isArray(data.methods)) {
            box.style.display = data.shipping_pending ? 'none' : '';
            data.methods.forEach(function (m) {
                const input = box.querySelector('input[value="' + CSS.escape(m.id) + '"]');
                const price = input && input.closest('label') ? input.closest('label').querySelector('span span') : null;
                if (price) price.textContent = m.cost;
                if (input) input.checked = (m.id === data.method);
            });
        }

        // Coupon rows then promotion rows, both rebuilt from the server's markup. Promotions can
        // appear or vanish purely from a quantity change, so they are repainted every time.
        const tbody = document.getElementById('cart-totals-body');
        if (tbody && (data.discount_html !== undefined || data.promotion_html !== undefined)) {
            tbody.querySelectorAll('.coupon-row, .promotion-row').forEach(r => r.remove());
            const markup = (data.discount_html || '') + (data.promotion_html || '');
            if (markup) tbody.lastElementChild.insertAdjacentHTML('beforebegin', markup);
        }

        // Qualifying for an offer can start or stop purely from a quantity change, so the
        // prompt is re-rendered from the server on every cart update rather than left stale
        // until the next full page load.
        const offerBox = document.getElementById('cart-promo-offers');
        if (offerBox && data.offer_html !== undefined) {
            offerBox.innerHTML = data.offer_html;
        }

        if (Array.isArray(data.item_subtotals) || (data.item_subtotals && typeof data.item_subtotals === 'object')) {
            Object.entries(data.item_subtotals).forEach(function ([key, value]) {
                const row = document.querySelector('.cart-item-row[data-key="' + CSS.escape(key) + '"] .item-subtotal');
                if (row) row.innerHTML = value;
            });
        }

        syncMiniCart(data.cart_count ?? 0);
    }

    // ── +/- stepper ────────────────────────────────────────────────
    window.stepQty = function (btn, delta) {
        const input = delta === -1 ? btn.nextElementSibling : btn.previousElementSibling;
        input.value = Math.max(1, parseInt(input.value) + delta);
    };

    // ── Update cart ────────────────────────────────────────────────
    window.updateCartAjax = function () {
        const btn = document.getElementById('update-cart-btn');
        const quantities = {};
        document.querySelectorAll('#cart-items-body input[name^="quantity["]').forEach(input => {
            quantities[input.name.slice(9, -1)] = parseInt(input.value, 10);
        });

        loaderShow();
        btn.textContent = 'Updating…';
        btn.disabled    = true;

        fetch('{{ route('shop.cart.update') }}', {
            method: 'POST',
            headers: HEADERS,
            body: JSON.stringify({ quantity: quantities }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.item_subtotals) {
                    Object.entries(data.item_subtotals).forEach(([key, sub]) => {
                        const row = document.querySelector(`.cart-item-row[data-key="${CSS.escape(key)}"]`);
                        if (row) row.querySelector('.item-subtotal').innerHTML = sub;
                    });
                }
                applyTotals(data);
                showCartToast(data.message || 'Cart updated!', false);
            } else {
                showCartToast(data.message || 'Could not update cart.', true);
            }
        })
        .catch(() => showCartToast('Could not update cart.', true))
        .finally(() => {
            loaderHide();
            btn.textContent = 'Update cart';
            btn.disabled    = false;
        });
    };

    // ── Remove item ────────────────────────────────────────────────
    window.removeCartItem = function (key, btn) {
        const row = btn.closest('.cart-item-row');
        loaderShow();
        row.style.opacity      = '0.4';
        row.style.pointerEvents = 'none';

        fetch('{{ route('shop.cart.remove', '__KEY__') }}'.replace('__KEY__', encodeURIComponent(key)), {
            method: 'POST',
            headers: HEADERS,
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                row.remove();
                applyTotals(data);
                showCartToast(data.message || 'Item removed.', false);
                if ((data.cart_count ?? 1) === 0) setTimeout(() => location.reload(), 700);
            } else {
                showCartToast(data.message || 'Could not remove item.', true);
                row.style.opacity = '';
                row.style.pointerEvents = '';
            }
        })
        .catch(() => {
            row.style.opacity = '';
            row.style.pointerEvents = '';
            showCartToast('Could not remove item.', true);
        })
        .finally(() => loaderHide());
    };

    // ── Apply coupon ───────────────────────────────────────────────
    window.applyCoupon = function () {
        const code   = document.getElementById('coupon_code_input').value.trim();
        const msgDiv = document.getElementById('coupon-message');
        if (!code) return;

        msgDiv.innerHTML = 'Applying…';
        msgDiv.className = 'mt-2 text-xs text-blue-600';

        fetch('{{ route('shop.cart.coupon') }}', {
            method: 'POST',
            headers: HEADERS,
            body: JSON.stringify({ coupon_code: code }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('coupon_code_input').value = '';
                msgDiv.innerHTML = data.message;
                msgDiv.className = 'mt-2 text-xs text-emerald-600';
                applyTotals(data);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } else {
                msgDiv.innerHTML = data.message || 'Error applying coupon.';
                msgDiv.className = 'mt-2 text-xs text-rose-600';
            }
        })
        .catch(() => {
            msgDiv.innerHTML = 'Error applying coupon.';
            msgDiv.className = 'mt-2 text-xs text-rose-600';
        });
    };

    // ── Mini-cart remove sync ──────────────────────────────────────
    window.addEventListener('falconCartItemRemoved', function (e) {
        const { key, ...data } = e.detail;
        const row = document.querySelector(`.cart-item-row[data-key="${CSS.escape(key)}"]`);
        if (row) row.remove();
        applyTotals(data);
        if ((data.cart_count ?? 1) === 0) setTimeout(() => location.reload(), 500);
    });

    // ── Promotion reward: add the qualifying item ──────────────────
    // Goes through the ordinary add-to-cart endpoint (stock checks and all), then reloads so the
    // promotion engine re-prices the basket on the server rather than in the page.
    window.addPromoReward = function (productId, btn) {
        if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }
        fetch('{{ route('shop.cart.add') }}', {
            method: 'POST',
            headers: HEADERS,
            body: JSON.stringify({ product_id: productId, quantity: 1 }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); return; }
            showCartToast(data.message || 'Could not add that item.', true);
            if (btn) { btn.disabled = false; btn.textContent = '+ Add'; }
        })
        .catch(() => {
            showCartToast('Could not add that item.', true);
            if (btn) { btn.disabled = false; btn.textContent = '+ Add'; }
        });
    };

    // ── Shipping estimator + method picker ─────────────────────────
    // Called both by the "Update totals" button and by the pickup/delivery radios, so it can't
    // assume either control is on the page: the estimator is an optional setting and the radios
    // only render when Local Pickup is enabled.
    window.updateShipping = function () {
        const countrySelect = document.getElementById('shipping_country');
        const country       = countrySelect ? countrySelect.value : '';
        const methodInput   = document.querySelector('input[name="cart_shipping_method"]:checked');
        const method        = methodInput ? methodInput.value : null;

        // Nothing to tell the server about.
        if (!country && !method) return;

        const btn = document.getElementById('cart-shipping-update-btn');
        if (btn) { btn.textContent = 'Updating…'; btn.disabled = true; }

        fetch('{{ route('shop.cart.shipping.update') }}', {
            method: 'POST',
            headers: HEADERS,
            body: JSON.stringify({ country: country, shipping_method: method }),
        })
        .then(r => r.json())
        .then(data => {
            // Changing the country moves the tax rate too, so the whole table is repainted
            // rather than just the shipping line.
            if (data.success) applyTotals(data);
        })
        .catch(() => {})
        .finally(() => {
            if (btn) { btn.textContent = 'Update totals'; btn.disabled = false; }
        });
    };
}());
</script>
@endpush
