@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', 'Cart')

@section('content')
<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        <h1 class="text-[36px] font-normal text-[#2c3338] mb-8">Cart</h1>

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
                <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-primary text-white px-8 py-3 rounded-sm font-bold hover:opacity-90 transition-all uppercase text-sm">Return to shop</a>
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
                        <?php do_falcon_action('lazy_before_cart_items', $cart); ?>
                        <tbody id="cart-items-body" class="text-[15px] text-gray-600">
                            @foreach($cart as $key => $item)
                                <?php do_falcon_action('lazy_before_cart_item', $item, $key); ?>
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
                                <?php do_falcon_action('lazy_after_cart_item', $item, $key); ?>
                            @endforeach
                            <tr>
                                <td colspan="6" class="p-4 border border-gray-100">
                                    <div class="flex flex-col md:flex-row justify-between gap-4">
                                        @if(get_shop_option('shop_enable_coupons', '1') === '1')
                                        <div>
                                            <div class="flex gap-2">
                                                <input type="text" id="coupon_code_input" placeholder="Coupon code" class="border border-gray-300 px-4 py-2 text-sm focus:border-primary outline-none min-w-[150px]">
                                                <button type="button" onclick="applyCoupon()" class="bg-gray-100 text-gray-700 px-6 py-2 text-sm font-bold hover:bg-gray-200 transition-all uppercase">Apply coupon</button>
                                            </div>
                                            <div id="coupon-message" class="mt-2 text-xs"></div>
                                        </div>
                                        @endif
                                        <button type="button" id="update-cart-btn" onclick="updateCartAjax()" class="bg-gray-100 text-gray-700 px-8 py-2 text-sm font-bold hover:bg-gray-200 transition-all uppercase {{ get_shop_option('shop_enable_coupons', '1') !== '1' ? 'w-full md:w-auto' : '' }}">Update cart</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>{{-- /#cart-table-wrap --}}
            </form>

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
                                    <div id="cart-shipping">
                                        @php $shipDetails = get_falcon_cart_shipping_details(session()->get('falcon_shipping_country')); @endphp
                                        @if($shipDetails['cost'] > 0)
                                            {{ $shipDetails['label'] }}: <span class="font-bold text-heading">{{ falcon_price_format($shipDetails['cost']) }}</span>
                                        @else
                                            <span class="font-bold text-heading">{{ $shipDetails['label'] }}</span>
                                        @endif
                                    </div>
                                    
                                    @if(get_shop_option('shop_calc_enable_cart_estimator', '1') === '1')
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        <a href="javascript:void(0)" onclick="document.getElementById('shipping-estimator').classList.toggle('hidden')" class="text-primary hover:underline text-[13px] font-semibold flex items-center gap-1">
                                            <i data-lucide="truck" class="w-3 h-3"></i>
                                            Calculate shipping
                                        </a>
                                        <div id="shipping-estimator" class="hidden mt-3 space-y-3">
                                            <select id="shipping_country" class="w-full border border-gray-300 px-3 py-2 text-sm outline-none focus:border-primary">
                                                <option value="">Select a country...</option>
                                                @foreach(\FalconCms\Core\Services\EcommerceData::getCountries() as $code => $name)
                                                    <option value="{{ $code }}" {{ session()->get('falcon_shipping_country') === $code ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" onclick="updateShipping()" class="w-full bg-gray-100 text-gray-700 py-2 text-[12px] font-bold hover:bg-gray-200 transition-all uppercase tracking-wider">Update totals</button>
                                        </div>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @if(get_cms_option('shop_enable_tax') === '1')
                            <tr class="border-b border-gray-100" id="cart-tax-row">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700">Tax</th>
                                <td class="p-4 font-bold text-heading" id="cart-tax">{{ falcon_price_format(get_falcon_cart_tax()) }}</td>
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
                                <tr class="coupon-row bg-emerald-50/10 border-b border-gray-100">
                                    <th class="p-4 bg-gray-50 text-left font-bold text-emerald-700 w-1/3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            Coupon: {{ $coupon['code'] }}
                                            <a href="{{ route('shop.cart.coupon.remove') }}?code={{ urlencode($coupon['code']) }}" class="text-rose-500 hover:text-rose-700 text-[10px] font-normal">[Remove]</a>
                                        </div>
                                    </th>
                                    <td class="p-4 font-bold text-emerald-700">{{ falcon_price_format($discount) }}</td>
                                </tr>
                            @endforeach

                            <tr class="bg-gray-50">
                                <th class="p-4 text-left font-extrabold text-heading">Total</th>
                                <td class="p-4 text-xl font-black text-primary" id="cart-total">{{ falcon_price_format(get_falcon_cart_total()) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <a href="{{ get_lazy_checkout_url() }}" class="block w-full bg-primary text-white text-center py-4 font-bold rounded-sm hover:opacity-90 transition-all uppercase shadow-md shadow-primary/20">Proceed to checkout</a>
                </div>
            </div>
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
    function applyTotals(data) {
        document.getElementById('cart-subtotal').innerHTML = data.subtotal;
        document.getElementById('cart-shipping').innerHTML = data.shipping;
        const taxEl = document.getElementById('cart-tax');
        if (taxEl) taxEl.innerHTML = data.tax ?? '';
        document.getElementById('cart-total').innerHTML = data.total;

        if (data.discount_html !== undefined) {
            const tbody = document.getElementById('cart-totals-body');
            tbody.querySelectorAll('.coupon-row').forEach(r => r.remove());
            tbody.lastElementChild.insertAdjacentHTML('beforebegin', data.discount_html);
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

    // ── Shipping estimator ─────────────────────────────────────────
    window.updateShipping = function () {
        const country = document.getElementById('shipping_country').value;
        if (!country) return;
        const btn = event.target;
        btn.textContent = 'Updating…';
        btn.disabled    = true;

        fetch('{{ route('shop.cart.shipping.update') }}', {
            method: 'POST',
            headers: HEADERS,
            body: JSON.stringify({ country }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cart-shipping').innerHTML = data.shipping;
                document.getElementById('cart-total').innerHTML   = data.total;
            }
        })
        .catch(() => {})
        .finally(() => {
            btn.textContent = 'Update totals';
            btn.disabled    = false;
        });
    };
}());
</script>
@endpush
