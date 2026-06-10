@extends('cms-dashboard::themes.lazy-theme.layouts.app')

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
            <form action="{{ route('shop.cart.update') }}" method="POST">
                @csrf
                <div class="overflow-x-auto mb-10">
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
                        <?php do_lazy_action('lazy_before_cart_items', $cart); ?>
                        <tbody class="text-[15px] text-gray-600">
                            @foreach($cart as $key => $item)
                                <?php do_lazy_action('lazy_before_cart_item', $item, $key); ?>
                                <tr class="border-b border-gray-100">
                                    <td class="p-4 border border-gray-100 text-center w-10">
                                        <a href="{{ route('shop.cart.remove', $key) }}" class="text-gray-400 hover:text-red-500 text-xl">&times;</a>
                                    </td>
                                    <td class="p-4 border border-gray-100 w-24">
                                        <a href="{{ route('frontend.show', ['typeOrSlug' => 'product', 'slug' => $item['slug']]) }}">
                                            <img src="{{ get_lazy_image_url($item['thumbnail']) }}" alt="{{ $item['name'] }}" class="w-16 h-16 object-cover border border-gray-100">
                                        </a>
                                    </td>
                                    <td class="p-4 border border-gray-100 font-bold text-primary">
                                        {!! apply_lazy_filters('lazy_cart_item_name',
                                            '<a href="' . get_lazy_permalink($item) . '">' . e($item['name']) . '</a>',
                                            $item, $key) !!}
                                        {!! lazy_render_item_custom_fields($item, 'cart') !!}
                                        <?php do_lazy_action('lazy_cart_item_meta', $item, $key); ?>
                                    </td>
                                    <td class="p-4 border border-gray-100">
                                        {{ lazy_price_format($item['sale_price'] ?: $item['price']) }}
                                    </td>
                                    <td class="p-4 border border-gray-100">
                                        <div class="flex items-center border border-gray-200 rounded-sm h-10 w-fit bg-white overflow-hidden">
                                            <button type="button" onclick="const input = this.nextElementSibling; input.value = Math.max(1, parseInt(input.value) - 1);" class="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-50 border-r border-gray-100 font-bold select-none">-</button>
                                            <input type="text" name="quantity[{{ $key }}]" value="{{ $item['quantity'] }}" readonly class="w-10 h-full text-center border-none focus:ring-0 text-sm font-bold text-gray-800 p-0 cursor-default">
                                            <button type="button" onclick="const input = this.previousElementSibling; input.value = parseInt(input.value) + 1;" class="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-50 border-l border-gray-100 font-bold select-none">+</button>
                                        </div>
                                    </td>
                                    <td class="p-4 border border-gray-100 font-bold text-heading">
                                        {{ lazy_price_format(($item['sale_price'] ?: $item['price']) * $item['quantity']) }}
                                    </td>
                                </tr>
                                <?php do_lazy_action('lazy_after_cart_item', $item, $key); ?>
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
                                        <button type="submit" class="bg-gray-100 text-gray-700 px-8 py-2 text-sm font-bold hover:bg-gray-200 transition-all uppercase {{ get_shop_option('shop_enable_coupons', '1') !== '1' ? 'w-full md:w-auto' : '' }}">Update cart</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="flex flex-col md:flex-row justify-end">
                <div class="w-full md:w-[450px]">
                    <h2 class="text-2xl font-bold text-[#2c3338] mb-6">Cart totals</h2>
                    <table class="w-full border-collapse border border-gray-100 mb-8">
                        <tbody id="cart-totals-body">
                            <tr class="border-b border-gray-100">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700 w-1/3">Subtotal</th>
                                <td class="p-4 font-bold text-heading" id="cart-subtotal">{{ lazy_price_format(get_lazy_cart_subtotal()) }}</td>
                            </tr>
                            <tr class="border-b border-gray-100">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700">Shipping</th>
                                <td class="p-4 text-sm" id="cart-shipping-cell">
                                    <div id="cart-shipping">
                                        @php $shipDetails = get_lazy_cart_shipping_details(session()->get('lazy_shipping_country')); @endphp
                                        @if($shipDetails['cost'] > 0)
                                            {{ $shipDetails['label'] }}: <span class="font-bold text-heading">{{ lazy_price_format($shipDetails['cost']) }}</span>
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
                                                @foreach(\Acme\CmsDashboard\Services\EcommerceData::getCountries() as $code => $name)
                                                    <option value="{{ $code }}" {{ session()->get('lazy_shipping_country') === $code ? 'selected' : '' }}>{{ $name }}</option>
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
                                <td class="p-4 font-bold text-heading" id="cart-tax">{{ lazy_price_format(get_lazy_cart_tax()) }}</td>
                            </tr>
                            @endif
                            
                            @php 
                                $appliedCoupons = session()->get('lazy_coupons', []); 
                                $subtotal = get_lazy_cart_subtotal(); 
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
                                    <td class="p-4 font-bold text-emerald-700">{{ lazy_price_format($discount) }}</td>
                                </tr>
                            @endforeach

                            <tr class="bg-gray-50">
                                <th class="p-4 text-left font-extrabold text-heading">Total</th>
                                <td class="p-4 text-xl font-black text-primary" id="cart-total">{{ lazy_price_format(get_lazy_cart_total()) }}</td>
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
function applyCoupon() {
    const code = document.getElementById('coupon_code_input').value;
    const msgDiv = document.getElementById('coupon-message');
    
    if(!code) return;
    
    msgDiv.innerHTML = 'Applying...';
    msgDiv.className = 'mt-2 text-xs text-blue-600';

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
            document.getElementById('cart-subtotal').innerHTML = data.subtotal;
            document.getElementById('cart-shipping').innerHTML = data.shipping;
            if(document.getElementById('cart-tax')) document.getElementById('cart-tax').innerHTML = data.tax;
            document.getElementById('cart-total').innerHTML = data.total;
            
            // Add or update coupon row
            const tbody = document.getElementById('cart-totals-body');
            const totalRow = tbody.lastElementChild;
            
            // Remove existing coupon rows
            const existingRows = tbody.querySelectorAll('.coupon-row');
            existingRows.forEach(row => row.remove());
            
            // Insert new coupon row before total
            totalRow.insertAdjacentHTML('beforebegin', data.discount_html);
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

function updateShipping() {
    const country = document.getElementById('shipping_country').value;
    const shippingDiv = document.getElementById('cart-shipping');
    const totalDiv = document.getElementById('cart-total');
    
    if(!country) return;
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Updating...';
    btn.disabled = true;

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
            shippingDiv.innerHTML = data.shipping;
            totalDiv.innerHTML = data.total;
        }
    })
    .catch(error => console.error('Shipping Error:', error))
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
@endpush
