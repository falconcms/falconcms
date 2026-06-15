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
                    <button type="button" onclick="applyCoupon()" class="bg-primary text-white px-6 py-2.5 font-bold text-[14px] hover:bg-primary-hover transition-all uppercase">Apply</button>
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
                    <?php do_falcon_action('lazy_before_billing_fields'); ?>
                    <?php lazy_render_checkout_fields(lazy_get_checkout_fields('billing')); ?>
                    <?php do_falcon_action('lazy_after_billing_fields'); ?>

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
                    <div class="mb-6">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" id="ship-different" name="ship_to_different_address" value="1" {{ old('ship_to_different_address') ? 'checked' : '' }} onchange="document.getElementById('shipping-form').classList.toggle('hidden')" class="w-4 h-4 border-[#ddd] rounded-sm text-primary focus:ring-0">
                            <span class="text-[20px] font-bold text-heading uppercase tracking-tight">Ship to a different address?</span>
                        </label>
                    </div>

                    <div id="shipping-form" class="{{ old('ship_to_different_address') ? '' : 'hidden' }} mb-8 border-t border-[#eee] pt-6">
                        <?php do_falcon_action('lazy_before_shipping_fields'); ?>
                        <?php lazy_render_checkout_fields(lazy_get_checkout_fields('shipping')); ?>
                        <?php do_falcon_action('lazy_after_shipping_fields'); ?>
                    </div>

                    <div class="space-y-2 mt-6">
                        <h2 class="text-[16px] font-bold text-heading mb-4">Order notes (optional)</h2>
                        <textarea name="order_comments" rows="3" placeholder="Notes about your order, e.g. special notes for delivery." class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none resize-none">{{ old('order_comments') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Full Width Order Section -->
            <?php do_falcon_action('lazy_before_checkout_order_review', $cart); ?>
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
                                        {!! apply_falcon_filters('lazy_checkout_item_name',
                                            '<a href="' . route('frontend.show', ['typeOrSlug' => 'product', 'slug' => $item['slug']]) . '" class="hover:text-primary transition-colors">' . e($item['name']) . '</a> <span class="font-bold text-heading">× ' . (int)$item['quantity'] . '</span>',
                                            $item) !!}
                                        {!! lazy_render_item_custom_fields($item, 'checkout') !!}
                                        <?php do_falcon_action('lazy_checkout_item_meta', $item); ?>
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

                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-heading">Shipping</th>
                                <td class="text-right p-4 text-body" id="checkout-shipping">
                                    @php $shipDetails = get_falcon_cart_shipping_details(session('lazy_shipping_country')); @endphp
                                    {{ $shipDetails['label'] }}: <span class="font-bold text-heading">{{ $shipDetails['cost'] > 0 ? falcon_price_format($shipDetails['cost']) : 'Free' }}</span>
                                </td>
                            </tr>

                            @if(get_cms_option('shop_enable_tax') === '1')
                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-heading">Estimated Tax</th>
                                <td class="text-right p-4 font-bold text-heading" id="checkout-tax">{{ falcon_price_format(get_falcon_cart_tax()) }}</td>
                            </tr>
                            @endif

                            @php 
                                $appliedCoupons = session()->get('falcon_coupons', []); 
                                $subtotal = get_falcon_cart_subtotal(); 
                                $currentSubtotal = $subtotal;
                                $isMultipleAllowed = (int)get_shop_option('shop_multi_coupon_policy', '1') === 1;
                            @endphp
                            @foreach($appliedCoupons as $coupon)
                                @php 
                                    $amount = (float)($coupon['amount'] ?? ($coupon['discount'] ?? 0));
                                    $calcBase = $isMultipleAllowed ? $currentSubtotal : $subtotal;
                                    $discount = ($coupon['type'] ?? 'percent') === 'percent' ? $calcBase * ($amount / 100) : $amount;
                                    $currentSubtotal -= $discount;
                                @endphp
                                <tr class="coupon-row bg-emerald-50/10 border-b border-[#eee]">
                                    <th class="text-left p-4 font-bold text-emerald-700 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            Coupon: {{ $coupon['code'] }}
                                        </div>
                                    </th>
                                    <td class="text-right p-4 font-bold text-emerald-700">-{{ falcon_price_format($discount) }}</td>
                                </tr>
                            @endforeach

                            <tr class="bg-[#fcfcfc]">
                                <th class="text-left p-4 font-bold text-heading">Total</th>
                                <td class="text-right p-4 text-[18px] font-bold text-primary" id="checkout-total">{{ falcon_price_format(get_falcon_cart_total()) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <?php do_falcon_action('lazy_after_checkout_order_review', $cart); ?>

                    <!-- Payment Section -->
                    <?php do_falcon_action('lazy_before_checkout_payment', $cart); ?>
                    @php $gateways = lazy_enabled_payment_gateways(); $firstGw = array_key_first($gateways); @endphp
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

                            <?php do_falcon_action('lazy_before_place_order_button', $cart); ?>
                            <button type="submit" @if(empty($gateways)) disabled @endif class="bg-primary text-white px-10 py-4 rounded-sm font-bold text-[16px] hover:bg-primary-hover transition-all shadow-lg uppercase disabled:opacity-50 disabled:cursor-not-allowed">
                                Place order
                            </button>
                            <?php do_falcon_action('lazy_after_place_order_button', $cart); ?>
                        </div>
                    </div>
                    <?php do_falcon_action('lazy_after_checkout_payment', $cart); ?>
                </div>
            </div>
        </form>
        @else
        <div class="bg-white p-20 text-center border border-[#eee] rounded-sm">
            <h2 class="text-[24px] font-bold text-heading mb-4">Your cart is empty</h2>
            <p class="text-[#777] mb-8">Add products to your cart before checking out.</p>
            <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-primary text-white px-8 py-3 rounded-sm font-bold hover:bg-primary-hover transition-colors uppercase">Return to shop</a>
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
            
            // Update Totals
            document.getElementById('checkout-subtotal').innerText = data.subtotal;
            document.getElementById('checkout-shipping').innerText = data.shipping;
            if(document.getElementById('checkout-tax')) document.getElementById('checkout-tax').innerText = data.tax;
            document.getElementById('checkout-total').innerText = data.total;
            
            // Add or update coupon row
            const tbody = document.getElementById('order-review-body');
            const totalRow = tbody.lastElementChild;
            
            const existingRows = tbody.querySelectorAll('.coupon-row');
            existingRows.forEach(row => row.remove());
            
            totalRow.insertAdjacentHTML('beforebegin', data.discount_html);
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
            body: JSON.stringify({ country: country })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                if(shippingText) shippingText.innerHTML = data.shipping;
                if(totalText) totalText.innerHTML = data.total;
            }
        })
        .catch(error => console.error('Checkout Shipping Error:', error));
    }

    if(billingCountry) billingCountry.addEventListener('change', refreshCheckoutShipping);
    if(shippingCountry) shippingCountry.addEventListener('change', refreshCheckoutShipping);
    if(shipToDifferent) shipToDifferent.addEventListener('change', refreshCheckoutShipping);

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
        $allCheckoutHookFields = array_merge(lazy_get_checkout_fields('billing'), lazy_get_checkout_fields('shipping'));
        $extraPersistFields = array_values(array_diff(
            array_column($allCheckoutHookFields, 'name'),
            lazy_standard_checkout_field_names()
        ));
    @endphp
    (function () {
        var KEY = 'lazy_checkout_draft';
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
