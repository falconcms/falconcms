@extends('falcon-cms::themes.falcon-theme.layouts.app')

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
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Your cart is currently empty.</h2>
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
                        <tbody class="text-[15px] text-gray-600">
                            @foreach($cart as $key => $item)
                                <tr class="border-b border-gray-100">
                                    <td class="p-4 border border-gray-100 text-center w-10">
                                        <a href="{{ route('shop.cart.remove', $key) }}" class="text-gray-400 hover:text-red-500 text-xl">&times;</a>
                                    </td>
                                    <td class="p-4 border border-gray-100 w-24">
                                        <img src="{{ get_falcon_image_url($item['thumbnail']) }}" alt="{{ $item['name'] }}" class="w-16 h-16 object-cover border border-gray-100">
                                    </td>
                                    <td class="p-4 border border-gray-100 font-bold text-primary">
                                        <a href="#">{{ $item['name'] }}</a>
                                    </td>
                                    <td class="p-4 border border-gray-100">
                                        {{ falcon_price_format($item['sale_price'] ?: $item['price']) }}
                                    </td>
                                    <td class="p-4 border border-gray-100">
                                        <div class="flex items-center border border-gray-300 w-fit">
                                            <button type="button" onclick="this.nextElementSibling.stepDown();" class="px-2 py-1 hover:bg-gray-100">-</button>
                                            <input type="number" name="quantity[{{ $key }}]" value="{{ $item['quantity'] }}" min="1" class="w-12 text-center border-0 focus:ring-0 p-1 text-sm font-bold">
                                            <button type="button" onclick="this.previousElementSibling.stepUp();" class="px-2 py-1 hover:bg-gray-100">+</button>
                                        </div>
                                    </td>
                                    <td class="p-4 border border-gray-100 font-bold text-gray-900">
                                        {{ falcon_price_format(($item['sale_price'] ?: $item['price']) * $item['quantity']) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="6" class="p-4 border border-gray-100">
                                    <div class="flex flex-col md:flex-row justify-between gap-4">
                                        <div>
                                            <div class="flex gap-2">
                                                <input type="text" id="coupon_code_input" placeholder="Coupon code" class="border border-gray-300 px-4 py-2 text-sm focus:border-primary outline-none min-w-[150px]">
                                                <button type="button" onclick="applyCoupon()" class="bg-gray-100 text-gray-700 px-6 py-2 text-sm font-bold hover:bg-gray-200 transition-all uppercase">Apply coupon</button>
                                            </div>
                                            <div id="coupon-message" class="mt-2 text-xs"></div>
                                        </div>
                                        <button type="submit" class="bg-gray-100 text-gray-700 px-8 py-2 text-sm font-bold hover:bg-gray-200 transition-all uppercase">Update cart</button>
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
                                <td class="p-4 font-bold text-gray-900" id="cart-subtotal">{{ falcon_price_format(get_falcon_cart_subtotal()) }}</td>
                            </tr>
                            <tr class="border-b border-gray-100">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700">Shipping</th>
                                <td class="p-4 text-sm" id="cart-shipping">
                                    @if(get_falcon_cart_shipping() > 0)
                                        Flat rate: <span class="font-bold text-gray-900">{{ falcon_price_format(get_falcon_cart_shipping()) }}</span>
                                    @else
                                        <span class="font-bold text-gray-900">Free shipping</span>
                                    @endif
                                </td>
                            </tr>
                            @if(get_cms_option('shop_enable_tax') === '1')
                            <tr class="border-b border-gray-100" id="cart-tax-row">
                                <th class="p-4 bg-gray-50 text-left font-bold text-gray-700">Tax</th>
                                <td class="p-4 font-bold text-gray-900" id="cart-tax">{{ falcon_price_format(get_falcon_cart_tax()) }}</td>
                            </tr>
                            @endif
                            
                            @php $coupon = session()->get('lazy_coupon'); @endphp
                            @if($coupon)
                                @php 
                                    $subtotal = get_falcon_cart_subtotal();
                                    $discount = $coupon['type'] === 'percent' ? $subtotal * ((float)$coupon['amount'] / 100) : (float)$coupon['amount'];
                                @endphp
                                <tr class="coupon-row bg-emerald-50/30">
                                    <th class="p-4 bg-gray-50/0 text-left font-bold text-emerald-700 flex items-center gap-2">
                                        Coupon: {{ $coupon['code'] }}
                                        <a href="{{ route('shop.cart.coupon.remove') }}" class="text-rose-500 hover:text-rose-700 text-xs font-normal">[Remove]</a>
                                    </th>
                                    <td class="p-4 font-bold text-emerald-700">-{{ falcon_price_format($discount) }}</td>
                                </tr>
                            @endif

                            <tr class="bg-gray-50">
                                <th class="p-4 text-left font-extrabold text-gray-900">Total</th>
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
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ coupon_code: code })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            msgDiv.innerHTML = data.message;
            msgDiv.className = 'mt-2 text-xs text-emerald-600';
            
            // Update Totals
            document.getElementById('cart-subtotal').innerText = data.subtotal;
            document.getElementById('cart-shipping').innerText = data.shipping;
            if(document.getElementById('cart-tax')) document.getElementById('cart-tax').innerText = data.tax;
            document.getElementById('cart-total').innerText = data.total;
            
            // Add or update coupon row
            const tbody = document.getElementById('cart-totals-body');
            const totalRow = tbody.lastElementChild;
            
            // Remove existing coupon rows
            const existingRows = tbody.querySelectorAll('.coupon-row');
            existingRows.forEach(row => row.remove());
            
            // Insert new coupon row before total
            totalRow.insertAdjacentHTML('beforebegin', data.discount_html);
            lucide.createIcons();
        } else {
            msgDiv.innerHTML = data.message;
            msgDiv.className = 'mt-2 text-xs text-rose-600';
        }
    })
    .catch(error => {
        msgDiv.innerHTML = 'Error applying coupon.';
        msgDiv.className = 'mt-2 text-xs text-rose-600';
    });
}
</script>
@endpush
