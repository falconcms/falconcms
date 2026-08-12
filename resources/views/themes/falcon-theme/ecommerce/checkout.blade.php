@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        
        <h1 class="text-[28px] font-bold text-heading mb-8">Checkout</h1>

        @if(count($cart) > 0)
        @if(get_shop_option('shop_enable_coupons', '1') === '1')
        <div class="mb-10 bg-[#f7f6f7] p-6 border-t-2 border-primary flex items-center gap-2 text-[14px] text-body relative" x-data="{ open: false }">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <span>Have a coupon? <a href="#" @click.prevent="open = !open" class="text-primary hover:underline">Click here to enter your code</a></span>
            
            <div x-show="open" x-transition x-cloak class="absolute left-0 top-full mt-2 bg-white border border-[#d3ced2] p-6 z-50 shadow-xl w-full max-w-md">
                <p class="text-[14px] mb-4 text-body">If you have a coupon code, please apply it below.</p>
                <div class="flex gap-2">
                    <input type="text" id="coupon_code_input" placeholder="Coupon code" class="flex-grow border border-[#d3ced2] px-4 py-2.5 text-[14px] outline-none focus:border-primary">
                    <button type="button" onclick="applyCoupon()" class="bg-primary text-white px-6 py-2.5 font-bold text-[14px] hover:opacity-90 transition-all uppercase">Apply</button>
                </div>
                <div id="coupon-message" class="mt-2 text-xs"></div>
            </div>
        </div>
        @endif

        <form action="{{ route('shop.place-order') }}" method="POST">
            @csrf
            
            <div class="flex flex-col md:flex-row gap-12 mb-12">
                <!-- Left Column: Billing Details -->
                <div class="w-full md:w-1/2">
                    <h2 class="text-[20px] font-bold text-heading border-b border-[#eee] pb-4 mb-6 uppercase tracking-tight">Billing details</h2>
                    <?php do_falcon_action('falcon_before_billing_fields'); ?>
                    @include('falcon-cms::frontend.checkout-address-picker', ['section' => 'billing'])
                    <?php falcon_render_checkout_fields(falcon_get_checkout_fields('billing')); ?>
                    @auth
                    <label class="flex items-center gap-2 cursor-pointer text-[13px] text-body mb-4">
                        <input type="checkbox" name="save_address" value="1" checked
                               class="w-4 h-4 border-[#ddd] rounded text-primary focus:ring-0">
                        Save this address to my account for next time
                    </label>
                    @endauth
                    <?php do_falcon_action('falcon_after_billing_fields'); ?>

                    @guest
                    @php $guestCheckout = get_shop_option('shop_enable_guest_checkout', '1') === '1'; @endphp
                    @if(!$guestCheckout)
                    <div class="mb-4 border border-[#ddd] rounded-sm p-4 bg-[#fafafa]">
                        <p class="text-[13px] font-semibold text-heading mb-3">Create an account</p>
                        <p class="text-[13px] text-body mb-3">Set a password to create your account. You'll be able to track orders after checkout.</p>
                        <input type="hidden" name="create_account" value="1">
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-heading">Password <span class="text-red-600">*</span></label>
                            <input type="password" name="account_password" autocomplete="new-password" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none {{ $errors->has('account_password') ? 'border-red-400' : '' }}">
                            @error('account_password')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    @else
                    <div class="mb-4">
                        <label class="flex items-center gap-2 cursor-pointer mb-2 select-none">
                            <input type="checkbox" id="toggle-create-account" {{ old('create_account') ? 'checked' : '' }}
                                   onchange="document.getElementById('create-account-fields').classList.toggle('hidden', !this.checked); document.getElementById('create-account-flag').value = this.checked ? '1' : '';"
                                   class="w-4 h-4 border-[#ddd] rounded-sm">
                            <span class="text-[13px] font-semibold text-heading">Create an account? <span class="font-normal text-body">(optional — track your orders after checkout)</span></span>
                        </label>
                        <input type="hidden" id="create-account-flag" name="create_account" value="{{ old('create_account', '') }}">
                        <div id="create-account-fields" class="{{ old('create_account') ? '' : 'hidden' }} border border-[#ddd] rounded-sm p-4 bg-[#fafafa] mt-2">
                            <div class="space-y-1.5">
                                <label class="text-[14px] font-bold text-heading">Password</label>
                                <input type="password" name="account_password" autocomplete="new-password" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none {{ $errors->has('account_password') ? 'border-red-400' : '' }}">
                                @error('account_password')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    @endif
                    @endguest
                </div>

                <!-- Right Column: Shipping Details -->
                <div class="w-full md:w-1/2">
                    @php
                        // Shop → Shipping → Default Address Type. 'force_billing' removes the option
                        // entirely; 'shipping' opens the fields by default; 'billing' keeps them
                        // collapsed behind the checkbox. The server enforces the same rule on POST.
                        $shipDestination   = falcon_shipping_destination();
                        $allowSeparateShip = falcon_allows_separate_shipping_address();
                        $shipOpenByDefault = old('ship_to_different_address') !== null
                            ? (bool) old('ship_to_different_address')
                            : $shipDestination === 'shipping';
                    @endphp

                    @if($allowSeparateShip)
                    <div class="mb-6">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" id="ship-different" name="ship_to_different_address" value="1" {{ $shipOpenByDefault ? 'checked' : '' }} onchange="document.getElementById('shipping-form').classList.toggle('hidden')" class="w-4 h-4 border-[#ddd] rounded-sm text-primary focus:ring-0">
                            <span class="text-[20px] font-bold text-heading uppercase tracking-tight">Ship to a different address?</span>
                        </label>
                    </div>

                    <div id="shipping-form" class="{{ $shipOpenByDefault ? '' : 'hidden' }} mb-8 border-t border-[#eee] pt-6">
                        <?php do_falcon_action('falcon_before_shipping_fields'); ?>
                        @include('falcon-cms::frontend.checkout-address-picker', ['section' => 'shipping'])
                        <?php falcon_render_checkout_fields(falcon_get_checkout_fields('shipping')); ?>
                        <?php do_falcon_action('falcon_after_shipping_fields'); ?>
                    </div>
                    @else
                    <div class="mb-8 border border-[#eee] rounded-sm p-4 bg-[#fafafa]">
                        <h2 class="text-[16px] font-bold text-heading mb-1">Shipping address</h2>
                        <p class="text-[13px] text-body">This order ships to your billing address.</p>
                    </div>
                    @endif

                    <div class="space-y-2 mt-6">
                        <h2 class="text-[16px] font-bold text-heading mb-4">Order notes (optional)</h2>
                        <textarea name="order_comments" rows="3" placeholder="Notes about your order, e.g. special notes for delivery." class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none resize-none">{{ old('order_comments') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Full Width Order Section -->
            <?php do_falcon_action('falcon_before_checkout_order_review', $cart); ?>
            <div class="mt-12">
                <h2 class="text-[20px] font-bold text-heading mb-6 uppercase tracking-tight">Your order</h2>

                <div class="border border-[#eee] bg-white">
                    <table class="w-full border-collapse text-[14px]">
                        <thead>
                            <tr class="bg-[#fcfcfc] border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-heading">Product</th>
                                <th class="text-right p-4 font-bold text-heading">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="order-review-body">
                            @foreach($cart as $item)
                                <tr class="border-b border-[#eee]">
                                    <td class="p-4 text-body">
                                        {!! apply_falcon_filters('falcon_checkout_item_name',
                                            '<a href="' . route('frontend.show', ['typeOrSlug' => 'product', 'slug' => $item['slug']]) . '" class="hover:text-primary transition-colors">' . e($item['name']) . '</a> <span class="font-bold text-heading">× ' . (int)$item['quantity'] . '</span>',
                                            $item) !!}
                                        {!! falcon_render_item_custom_fields($item, 'checkout') !!}
                                        <?php do_falcon_action('falcon_checkout_item_meta', $item); ?>
                                    </td>
                                    <td class="p-4 text-right font-medium text-heading">
                                        {{ falcon_price_format(($item['sale_price'] ?: $item['price']) * $item['quantity']) }}
                                    </td>
                                </tr>
                            @endforeach
                            
                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-heading">Subtotal</th>
                                <td class="text-right p-4 font-bold text-heading" id="checkout-subtotal">{{ falcon_price_format(get_falcon_cart_subtotal()) }}</td>
                            </tr>

                            @php
                                $shipCountry = falcon_customer_shipping_country();
                                $shipMethods = falcon_shipping_methods($shipCountry);
                                $shipDetails = get_falcon_cart_shipping_details($shipCountry);
                            @endphp
                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-heading align-top">Shipping</th>
                                @if(!empty($shipDetails['pending']))
                                    <td class="text-right p-4 text-body" id="checkout-shipping">
                                        <span class="text-gray-500">Enter your address to see shipping options.</span>
                                    </td>
                                @elseif(count($shipMethods) > 1)
                                    {{-- More than one method on offer (Local Pickup is enabled), so let the
                                         customer choose. The value posted is only an id — the server prices it. --}}
                                    <td class="text-right p-4 text-body">
                                        <div class="space-y-2" id="checkout-shipping-methods">
                                            @foreach($shipMethods as $method)
                                                <label class="flex items-center justify-end gap-2 cursor-pointer">
                                                    <span>{{ $method['label'] }}:
                                                        <span class="font-bold text-heading">{{ $method['cost'] > 0 ? falcon_price_format($method['cost']) : 'Free' }}</span>
                                                    </span>
                                                    <input type="radio" name="shipping_method" value="{{ $method['id'] }}"
                                                           {{ $shipDetails['method'] === $method['id'] ? 'checked' : '' }}
                                                           class="w-4 h-4 text-primary focus:ring-0 border-[#ddd]">
                                                </label>
                                            @endforeach
                                        </div>
                                        <span id="checkout-shipping" class="hidden"></span>
                                    </td>
                                @else
                                    <td class="text-right p-4 text-body" id="checkout-shipping">
                                        {{ $shipDetails['label'] }}: <span class="font-bold text-heading">{{ $shipDetails['cost'] > 0 ? falcon_price_format($shipDetails['cost']) : 'Free' }}</span>
                                    </td>
                                @endif
                            </tr>

                            @php $checkoutTax = falcon_tax_enabled() ? get_falcon_cart_tax() : 0; @endphp
                            @if(falcon_tax_enabled())
                            {{-- Rendered even at zero so the country/address handler can reveal it
                                 the moment a taxed destination is chosen. --}}
                            <tr class="border-b border-[#eee]" id="checkout-tax-row" @if($checkoutTax <= 0) style="display:none" @endif>
                                <th class="text-left p-4 font-bold text-heading">
                                    <span id="checkout-tax-prefix" @unless(falcon_prices_include_tax()) style="display:none" @endunless>Includes </span><span id="checkout-tax-label">{{ falcon_cart_tax_label() }}</span>
                                </th>
                                {{-- See the cart template: inclusive tax is already inside the subtotal. --}}
                                <td class="text-right p-4 {{ falcon_prices_include_tax() ? 'font-normal text-gray-500' : 'font-bold text-heading' }}" id="checkout-tax">{{ falcon_price_format($checkoutTax) }}</td>
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
                                    // See the cart template: shared helper so the row matches the total.
                                    $calcBase = $isMultipleAllowed ? $currentSubtotal : $subtotal;
                                    $discount = get_falcon_coupon_discount_amount($coupon, $cart, $calcBase);
                                    $currentSubtotal -= $discount;
                                @endphp
                                @if($discount > 0 || ($coupon['type'] ?? '') === 'free_shipping')
                                <tr class="coupon-row bg-emerald-50/10 border-b border-[#eee]">
                                    <th class="text-left p-4 font-bold text-emerald-700 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            Coupon: {{ $coupon['code'] }}
                                        </div>
                                    </th>
                                    <td class="text-right p-4 font-bold text-emerald-700">
                                        @if(($coupon['type'] ?? '') === 'free_shipping')
                                            Free shipping
                                        @else
                                            -{{ falcon_price_format($discount) }}
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            @endforeach

                            {{-- See the cart template: automatic promotions, priced by the engine. --}}
                            @foreach(falcon_evaluate_promotions($cart) as $promo)
                                <tr class="promotion-row bg-amber-50/40 border-b border-[#eee]">
                                    <th class="text-left p-4 font-bold text-amber-700">
                                        <div class="flex items-center gap-2">
                                            <span>&#127873;</span>
                                            <span>{{ $promo['name'] }}</span>
                                        </div>
                                        <div class="text-[11px] font-normal text-amber-700/70 mt-0.5">{{ $promo['summary'] }}</div>
                                    </th>
                                    <td class="text-right p-4 font-bold text-amber-700">-{{ falcon_price_format($promo['discount']) }}</td>
                                </tr>
                            @endforeach

                            <tr class="bg-[#fcfcfc]">
                                <th class="text-left p-4 font-bold text-heading">Total</th>
                                <td class="text-right p-4 text-[18px] font-bold text-primary" id="checkout-total">{{ falcon_price_format(get_falcon_cart_total()) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <?php do_falcon_action('falcon_after_checkout_order_review', $cart); ?>

                    <!-- Payment Section -->
                    <?php do_falcon_action('falcon_before_checkout_payment', $cart); ?>
                    @php $gateways = falcon_enabled_payment_gateways(); $firstGw = array_key_first($gateways); @endphp
                    <div class="p-8 border-t border-[#eee]">
                        <div class="max-w-4xl">
                            @if(empty($gateways))
                                <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 mb-8 rounded-sm text-[14px]">
                                    No payment method is currently available. Please contact the store.
                                </div>
                            @else
                            <div class="bg-[#f7f6f7] p-6 mb-8 relative rounded-sm" x-data="{ method: '{{ $firstGw }}' }">
                                <div class="absolute -top-3 left-6 w-0 h-0 border-l-[12px] border-l-transparent border-r-[12px] border-r-transparent border-b-[12px] border-b-[#f7f6f7]"></div>
                                <div class="space-y-3">
                                    @foreach($gateways as $gw)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="payment_method" value="{{ $gw['id'] }}" x-model="method" class="w-4 h-4 text-primary focus:ring-0">
                                            <span class="text-[14px] font-bold text-heading">{{ $gw['title'] }}</span>
                                        </label>
                                        <div x-show="method === '{{ $gw['id'] }}'" x-cloak class="bg-white/50 border border-black/5 p-4 text-[14px] text-body rounded-sm whitespace-pre-line">{{ $gw['desc'] }}</div>
                                        @if($gw['id'] === 'stripe')
                                            <div x-show="method === 'stripe'" x-cloak class="mt-1">
                                                <div id="stripe-card-element" class="bg-white border border-[#ddd] rounded-sm p-3.5"></div>
                                                <div id="stripe-card-errors" class="text-rose-600 text-[12px] mt-2" role="alert"></div>
                                                <p class="text-[11px] text-[#999] mt-2">Test card: 4242 4242 4242 4242 · any future date · any CVC.</p>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <p class="text-[13px] text-[#777] mb-8 leading-relaxed max-w-2xl">
                                Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our <a href="#" class="text-primary hover:underline">privacy policy</a>.
                            </p>

                            <?php do_falcon_action('falcon_before_place_order_button', $cart); ?>
                            <button type="submit" @if(empty($gateways)) disabled @endif class="bg-primary text-white px-10 py-4 rounded-sm font-bold text-[16px] hover:opacity-90 transition-all shadow-lg uppercase disabled:opacity-50 disabled:cursor-not-allowed">
                                Place order
                            </button>
                            <?php do_falcon_action('falcon_after_place_order_button', $cart); ?>
                        </div>
                    </div>
                    <?php do_falcon_action('falcon_after_checkout_payment', $cart); ?>
                </div>
            </div>
        </form>
        @else
        <div class="bg-white p-20 text-center border border-[#eee] rounded-sm">
            <h2 class="text-[24px] font-bold text-heading mb-4">Your cart is empty</h2>
            <p class="text-[#777] mb-8">Add products to your cart before checking out.</p>
            <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-primary text-white px-8 py-3 rounded-sm font-bold hover:opacity-90 hover:text-white transition-colors uppercase">Return to shop</a>
        </div>
        @endif
    </div>
</div>
@stop

@push('scripts')
@if(isset($gateways) && isset($gateways['stripe']))
<script src="https://js.stripe.com/v3/"></script>
@endif
<script>
// Repaints the order-review table from a cart endpoint's payload. Shared by the coupon box and
// the address/shipping handler so both always land on the same numbers, tax line included.
function applyCheckoutTotals(data) {
    const set = (id, html) => { const el = document.getElementById(id); if (el && html !== undefined) el.innerHTML = html; };

    set('checkout-subtotal', data.subtotal);
    set('checkout-shipping', data.shipping);
    set('checkout-total',    data.total);
    set('checkout-tax',       data.tax);
    set('checkout-tax-label', data.tax_label);

    const taxRow = document.getElementById('checkout-tax-row');
    if (taxRow && data.tax_visible !== undefined) taxRow.style.display = data.tax_visible ? '' : 'none';
    // See the cart: inclusive tax must not read as an additive line after a repaint either.
    const taxPrefix = document.getElementById('checkout-tax-prefix');
    const taxCell   = document.getElementById('checkout-tax');
    if (taxPrefix && taxCell && data.tax_included !== undefined) {
        taxPrefix.style.display = data.tax_included ? '' : 'none';
        taxCell.className = 'text-right p-4 ' + (data.tax_included ? 'font-normal text-gray-500' : 'font-bold text-heading');
    }

    // Changing the address can change nothing about promotions, but a coupon apply reuses this
    // painter — keeping the rows here means one place decides what the totals table shows.
    const rowsBody = document.getElementById('order-review-body');
    if (rowsBody && (data.discount_html !== undefined || data.promotion_html !== undefined)) {
        rowsBody.querySelectorAll('.coupon-row, .promotion-row').forEach(r => r.remove());
        const markup = (data.discount_html || '') + (data.promotion_html || '');
        if (markup) rowsBody.lastElementChild.insertAdjacentHTML('beforebegin', markup);
        rowsBody.querySelectorAll('.coupon-row > td, .promotion-row > td').forEach(td => td.classList.add('text-right'));
    }

    const box = document.getElementById('checkout-shipping-methods');
    if (box && Array.isArray(data.methods)) {
        box.style.display = data.shipping_pending ? 'none' : '';
        data.methods.forEach(function (method) {
            const input = box.querySelector('input[value="' + CSS.escape(method.id) + '"]');
            const price = input && input.closest('label') ? input.closest('label').querySelector('span span') : null;
            if (price) price.textContent = method.cost;
            if (input) input.checked = (method.id === data.method);
        });
    }
}

function applyCoupon() {
    const code = document.getElementById('coupon_code_input').value;
    const msgDiv = document.getElementById('coupon-message');
    
    if(!code) return;
    
    msgDiv.innerHTML = 'Applying...';
    msgDiv.className = 'mt-2 text-xs text-primary';

    fetch('{{ route('shop.cart.coupon') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ coupon_code: code })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch(e) {
                    throw new Error(text);
                }
            });
        }
        return response.json();
    })
    .then(data => {
        if(data.success) {
            document.getElementById('coupon_code_input').value = '';
            msgDiv.innerHTML = data.message;
            msgDiv.className = 'mt-2 text-xs text-emerald-600';
            
            applyCheckoutTotals(data);
            
            // Add or update coupon row
            const tbody = document.getElementById('order-review-body');
            const totalRow = tbody.lastElementChild;
            
            tbody.querySelectorAll('.coupon-row, .promotion-row').forEach(row => row.remove());

            totalRow.insertAdjacentHTML('beforebegin', (data.discount_html || '') + (data.promotion_html || ''));
            // Right-align the injected coupon amount to match this page's order table.
            tbody.querySelectorAll('.coupon-row > td').forEach(td => td.classList.add('text-right'));
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } else {
            msgDiv.innerHTML = data.message || 'Error applying coupon.';
            msgDiv.className = 'mt-2 text-xs text-rose-600';
        }
    })
    .catch(error => {
        console.error('Coupon Error:', error);
        msgDiv.innerHTML = error.message.substring(0, 100) || 'Error applying coupon.';
        msgDiv.className = 'mt-2 text-xs text-rose-600';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.querySelector('form[action="{{ route('shop.place-order') }}"]');
    
    // Dynamic Shipping Update on Country Change
    const billingCountry = document.querySelector('select[name="billing_country"]');
    const shippingCountry = document.querySelector('select[name="shipping_country"]');
    const shipToDifferent = document.getElementById('ship-different');

    const shippingMethodBox = document.getElementById('checkout-shipping-methods');

    function selectedShippingMethod() {
        const checked = document.querySelector('input[name="shipping_method"]:checked');
        return checked ? checked.value : null;
    }

    function refreshCheckoutShipping() {
        const country = (shipToDifferent && shipToDifferent.checked) ? (shippingCountry ? shippingCountry.value : '') : (billingCountry ? billingCountry.value : '');
        if(!country) return;

        const shippingText = document.getElementById('checkout-shipping');
        const totalText = document.getElementById('checkout-total');

        fetch('{{ route('shop.cart.shipping.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ country: country, shipping_method: selectedShippingMethod() })
        })
        .then(response => response.json())
        .then(data => {
            if(!data.success) return;

            // The address drives the tax rate as well as the shipping cost, so the whole
            // order-review table is repainted — the tax line used to wait for a page reload.
            applyCheckoutTotals(data);
        })
        .catch(error => console.error('Checkout Shipping Error:', error));
    }

    if(billingCountry) billingCountry.addEventListener('change', refreshCheckoutShipping);
    if(shippingCountry) shippingCountry.addEventListener('change', refreshCheckoutShipping);
    if(shipToDifferent) shipToDifferent.addEventListener('change', refreshCheckoutShipping);
    if(shippingMethodBox) shippingMethodBox.addEventListener('change', refreshCheckoutShipping);

    // Initial check on load
    refreshCheckoutShipping();

    // ── Stripe Elements (inline card) ──────────────────────────────────────────
    let stripe = null, stripeCard = null, stripeMounted = false;
    @if(isset($gateways) && isset($gateways['stripe']))
    const stripePubKey = @json(get_shop_option('shop_payment_stripe_key', ''));
    if (stripePubKey && window.Stripe) {
        stripe = Stripe(stripePubKey);
        stripeCard = stripe.elements().create('card', { hidePostalCode: true });
        const mountStripe = () => {
            if (stripeCard && !stripeMounted && document.getElementById('stripe-card-element')) {
                stripeCard.mount('#stripe-card-element');
                stripeCard.on('change', (ev) => {
                    document.getElementById('stripe-card-errors').textContent = ev.error ? ev.error.message : '';
                });
                stripeMounted = true;
            }
        };
        // Mount when the Stripe method is selected (and if it's selected on load).
        document.querySelectorAll('input[name="payment_method"]').forEach(r => {
            r.addEventListener('change', () => { if (r.value === 'stripe' && r.checked) setTimeout(mountStripe, 50); });
        });
        const preSel = document.querySelector('input[name="payment_method"]:checked');
        if (preSel && preSel.value === 'stripe') setTimeout(mountStripe, 100);
    }
    @endif

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = checkoutForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerText;
            submitBtn.innerText = 'Processing...';
            submitBtn.disabled = true;
            const formData = new FormData(checkoutForm);
            fetch(checkoutForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Stripe inline card → confirm the payment on-page with Stripe Elements.
                if (data.stripe_payment && data.client_secret && stripe && stripeCard) {
                    stripe.confirmCardPayment(data.client_secret, {
                        payment_method: {
                            card: stripeCard,
                            billing_details: {
                                name: (checkoutForm.billing_first_name.value + ' ' + checkoutForm.billing_last_name.value).trim(),
                                email: checkoutForm.billing_email.value
                            }
                        }
                    }).then(function (result) {
                        if (result.error) {
                            document.getElementById('stripe-card-errors').textContent = result.error.message;
                            Swal.fire({ title: 'Payment failed', text: result.error.message, icon: 'error', confirmButtonColor: '{{ get_cms_option('theme_primary_color', '#0091ea') }}' });
                            submitBtn.innerText = originalText;
                            submitBtn.disabled = false;
                        } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                            window.location.href = data.return_url;
                        } else {
                            submitBtn.innerText = originalText;
                            submitBtn.disabled = false;
                        }
                    });
                    return;
                }
                if (data.success && data.redirect) {
                    if (window.clearCheckoutDraft) window.clearCheckoutDraft();
                    window.location.href = data.redirect;
                } else if (data.errors) {
                    let errorList = '<ul class="text-left list-disc pl-5 space-y-1">';
                    Object.keys(data.errors).forEach(key => {
                        errorList += `<li>${data.errors[key][0]}</li>`;
                    });
                    errorList += '</ul>';
                    Swal.fire({
                        title: 'Validation Error',
                        html: errorList,
                        icon: 'error',
                        confirmButtonText: 'Ok',
                        confirmButtonColor: '{{ get_cms_option('theme_primary_color', '#0091ea') }}'
                    });
                    submitBtn.innerText = originalText;
                    submitBtn.disabled = false;
                } else if (data.message) {
                    Swal.fire({ title: 'Error', text: data.message, icon: 'error', confirmButtonColor: '{{ get_cms_option('theme_primary_color', '#0091ea') }}' });
                    submitBtn.innerText = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({ title: 'Error!', text: 'Something went wrong while processing your order. Please try again.', icon: 'error', confirmButtonColor: '{{ get_cms_option('theme_primary_color', '#0091ea') }}' });
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // ── Checkout form persistence (localStorage) ───────────────────────────
    @php
        $allCheckoutHookFields = array_merge(falcon_get_checkout_fields('billing'), falcon_get_checkout_fields('shipping'));
        $extraPersistFields = array_values(array_diff(
            array_column($allCheckoutHookFields, 'name'),
            falcon_standard_checkout_field_names()
        ));
    @endphp
    (function () {
        var KEY = 'falcon_checkout_draft';
        var TEXT  = ['billing_first_name','billing_last_name','billing_email','billing_phone',
                     'billing_address_1','billing_address_2','billing_city','billing_state','billing_postcode',
                     'shipping_first_name','shipping_last_name',
                     'shipping_address_1','shipping_address_2','shipping_city','shipping_state','shipping_postcode',
                     'order_comments'].concat(@json($extraPersistFields));
        var SEL   = ['billing_country','shipping_country'];

        function save() {
            var d = {};
            TEXT.forEach(function(n){ var e=document.querySelector('[name="'+n+'"]'); if(e) d[n]=e.value; });
            SEL.forEach(function(n){ var e=document.querySelector('[name="'+n+'"]'); if(e) d[n]=e.value; });
            var cb=document.querySelector('[name="ship_to_different_address"]'); if(cb) d.ship_diff=cb.checked;
            try{ localStorage.setItem(KEY, JSON.stringify(d)); }catch(e){}
        }

        function restore() {
            var raw; try{ raw=localStorage.getItem(KEY); }catch(e){ return; }
            if(!raw) return;
            var d; try{ d=JSON.parse(raw); }catch(e){ return; }
            // Only fill fields that are currently empty (don't override old() / auth() values from server)
            TEXT.forEach(function(n){
                if(!d[n]) return;
                var e=document.querySelector('[name="'+n+'"]');
                if(e && !e.value.trim()) e.value=d[n];
            });
            SEL.forEach(function(n){
                if(!d[n]) return;
                var e=document.querySelector('[name="'+n+'"]');
                if(e && !e.value) { e.value=d[n]; e.dispatchEvent(new Event('change')); }
            });
            if(d.ship_diff) {
                var cb=document.querySelector('[name="ship_to_different_address"]');
                if(cb && !cb.checked){ cb.checked=true; var sf=document.getElementById('shipping-form'); if(sf) sf.classList.remove('hidden'); }
            }
        }

        function clear() { try{ localStorage.removeItem(KEY); }catch(e){} }
        window.clearCheckoutDraft = clear;

        restore();
        TEXT.concat(SEL).concat(['ship_to_different_address']).forEach(function(n){
            var e=document.querySelector('[name="'+n+'"]');
            if(e){ e.addEventListener('input', save); e.addEventListener('change', save); }
        });
    })();
});
</script>
@endpush
