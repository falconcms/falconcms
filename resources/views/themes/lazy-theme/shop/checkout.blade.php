@extends('falcon-cms::themes.lazy-theme.layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        
        <h1 class="text-[28px] font-bold text-[#2c3338] mb-8">Checkout</h1>

        @if(count($cart) > 0)
        <!-- Coupon Bar -->
        <div class="mb-8 bg-[#f7f6f7] border-t-[3px] border-[#1363df] p-4 text-[14px] text-[#515151]">
            <p>
                <i data-lucide="info" class="w-4 h-4 inline-block mr-1 text-[#1363df]"></i>
                Have a coupon? <button onclick="const f = document.getElementById('coupon-form'); f.style.display = f.style.display === 'none' ? 'block' : 'none';" class="text-[#1363df] hover:underline">Click here to enter your code</button>
            </p>
        </div>

        <div id="coupon-form" style="display: none;" class="mb-8 border border-[#d3ced2] p-6 rounded-[3px]">
            <p class="text-[14px] text-[#515151] mb-4">If you have a coupon code, please apply it below.</p>
            <div class="flex gap-2 max-w-md">
                <input type="text" id="coupon_code_input" placeholder="Coupon code" class="flex-grow border border-[#d3ced2] px-4 py-2.5 text-[14px] outline-none focus:border-[#1363df]">
                <button type="button" onclick="applyCoupon()" class="bg-[#ebe9eb] text-[#515151] px-6 py-2.5 rounded-[3px] font-bold text-[14px] hover:bg-[#dfdcde] transition-colors">Apply coupon</button>
            </div>
            <div id="coupon-message" class="mt-2 text-xs"></div>
        </div>

        <form action="{{ route('shop.place-order') }}" method="POST">
            @csrf
            
            <div class="flex flex-col md:flex-row gap-12 mb-12">
                <!-- Left Column: Billing Details -->
                <div class="w-full md:w-1/2">
                    <h2 class="text-[20px] font-bold text-[#2c3338] border-b border-[#eee] pb-4 mb-6 uppercase tracking-tight">Billing details</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">First name <span class="text-red-600">*</span></label>
                            <input type="text" name="billing_first_name" value="{{ old('billing_first_name', auth()->user()->first_name ?? '') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">Last name <span class="text-red-600">*</span></label>
                            <input type="text" name="billing_last_name" value="{{ old('billing_last_name', auth()->user()->last_name ?? '') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                    </div>

                    <div class="space-y-1.5 mb-4">
                        <label class="text-[14px] font-bold text-[#2c3338]">Country / Region <span class="text-red-600">*</span></label>
                        <select name="billing_country" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] bg-white focus:border-[#1363df] outline-none cursor-pointer">
                            @foreach(\FalconCms\Core\Services\EcommerceData::getCountriesWithStates() as $code => $name)
                                <option value="{{ $code }}" {{ old('billing_country') == $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2 mb-4">
                        <label class="text-[14px] font-bold text-[#2c3338]">Street address <span class="text-red-600">*</span></label>
                        <input type="text" name="billing_address_1" value="{{ old('billing_address_1') }}" placeholder="House number and street name" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none mb-2">
                        <input type="text" name="billing_address_2" value="{{ old('billing_address_2') }}" placeholder="Apartment, suite, unit, etc. (optional)" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">Town / City <span class="text-red-600">*</span></label>
                            <input type="text" name="billing_city" value="{{ old('billing_city') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">State / Province <span class="text-red-600">*</span></label>
                            <input type="text" name="billing_state" value="{{ old('billing_state') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">ZIP Code <span class="text-red-600">*</span></label>
                            <input type="text" name="billing_postcode" value="{{ old('billing_postcode') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">Phone <span class="text-red-600">*</span></label>
                            <input type="text" name="billing_phone" value="{{ old('billing_phone') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                    </div>

                    <div class="space-y-1.5 mb-4">
                        <label class="text-[14px] font-bold text-[#2c3338]">Email address <span class="text-red-600">*</span></label>
                        <input type="email" name="billing_email" value="{{ old('billing_email', auth()->user()->email ?? '') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                    </div>

                    @guest
                    @php $guestCheckout = get_shop_option('shop_enable_guest_checkout', '1') === '1'; @endphp
                    @if(!$guestCheckout)
                    <div class="mb-4 border border-[#ddd] rounded-[3px] p-4 bg-[#f9f9f9]">
                        <p class="text-[13px] font-semibold text-[#2c3338] mb-3">Create an account</p>
                        <p class="text-[13px] text-[#515151] mb-3">Set a password to create your account. You'll be able to track orders after checkout.</p>
                        <input type="hidden" name="create_account" value="1">
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">Password <span class="text-red-600">*</span></label>
                            <input type="password" name="account_password" autocomplete="new-password" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none {{ $errors->has('account_password') ? 'border-red-400' : '' }}">
                            @error('account_password')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    @else
                    <div class="mb-4">
                        <label class="flex items-center gap-2 cursor-pointer mb-2 select-none">
                            <input type="checkbox" id="toggle-create-account" {{ old('create_account') ? 'checked' : '' }}
                                   onchange="document.getElementById('create-account-fields').classList.toggle('hidden', !this.checked); document.getElementById('create-account-flag').value = this.checked ? '1' : '';"
                                   class="w-4 h-4 border-[#ddd] rounded-sm">
                            <span class="text-[13px] font-semibold text-[#2c3338]">Create an account? <span class="font-normal text-[#646970]">(optional — track your orders after checkout)</span></span>
                        </label>
                        <input type="hidden" id="create-account-flag" name="create_account" value="{{ old('create_account', '') }}">
                        <div id="create-account-fields" class="{{ old('create_account') ? '' : 'hidden' }} border border-[#ddd] rounded-[3px] p-4 bg-[#f9f9f9] mt-2">
                            <div class="space-y-1.5">
                                <label class="text-[14px] font-bold text-[#2c3338]">Password</label>
                                <input type="password" name="account_password" autocomplete="new-password" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none {{ $errors->has('account_password') ? 'border-red-400' : '' }}">
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
                            <input type="checkbox" id="ship-different" name="ship_to_different_address" value="1" {{ old('ship_to_different_address') ? 'checked' : '' }} onchange="document.getElementById('shipping-form').classList.toggle('hidden')" class="w-4 h-4 border-[#ddd] rounded-sm text-[#1363df] focus:ring-0">
                            <span class="text-[20px] font-bold text-[#2c3338] uppercase tracking-tight">Ship to a different address?</span>
                        </label>
                    </div>

                    <div id="shipping-form" class="{{ old('ship_to_different_address') ? '' : 'hidden' }} space-y-4 mb-8 border-t border-[#eee] pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[14px] font-bold text-[#2c3338]">First name <span class="text-red-600">*</span></label>
                                <input type="text" name="shipping_first_name" value="{{ old('shipping_first_name') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[14px] font-bold text-[#2c3338]">Last name <span class="text-red-600">*</span></label>
                                <input type="text" name="shipping_last_name" value="{{ old('shipping_last_name') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">Country / Region <span class="text-red-600">*</span></label>
                            <select name="shipping_country" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] bg-white focus:border-[#1363df] outline-none">
                                @foreach(\FalconCms\Core\Services\EcommerceData::getCountriesWithStates() as $code => $name)
                                    <option value="{{ $code }}" {{ old('shipping_country') == $code ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[14px] font-bold text-[#2c3338]">Street address <span class="text-red-600">*</span></label>
                            <input type="text" name="shipping_address_1" value="{{ old('shipping_address_1') }}" placeholder="House number and street name" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none mb-2">
                            <input type="text" name="shipping_address_2" value="{{ old('shipping_address_2') }}" placeholder="Apartment, suite, unit, etc. (optional)" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[14px] font-bold text-[#2c3338]">Town / City <span class="text-red-600">*</span></label>
                                <input type="text" name="shipping_city" value="{{ old('shipping_city') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[14px] font-bold text-[#2c3338]">State / Province <span class="text-red-600">*</span></label>
                                <input type="text" name="shipping_state" value="{{ old('shipping_state') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[14px] font-bold text-[#2c3338]">ZIP Code <span class="text-red-600">*</span></label>
                            <input type="text" name="shipping_postcode" value="{{ old('shipping_postcode') }}" class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none">
                        </div>
                    </div>

                    <div class="space-y-2 mt-6">
                        <h2 class="text-[16px] font-bold text-[#2c3338] mb-4">Order notes (optional)</h2>
                        <textarea name="order_comments" rows="3" placeholder="Notes about your order, e.g. special notes for delivery." class="w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-[#1363df] outline-none resize-none">{{ old('order_comments') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Full Width Order Section -->
            <div class="mt-12">
                <h2 class="text-[20px] font-bold text-[#2c3338] mb-6 uppercase tracking-tight">Your order</h2>
                
                <div class="border border-[#eee] bg-white">
                    <table class="w-full border-collapse text-[14px]">
                        <thead>
                            <tr class="bg-[#fcfcfc] border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-[#2c3338]">Product</th>
                                <th class="text-right p-4 font-bold text-[#2c3338]">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="order-review-body">
                            @foreach($cart as $item)
                                <tr class="border-b border-[#eee]">
                                    <td class="p-4 text-[#515151]">
                                        {{ $item['name'] }} <span class="font-bold text-[#2c3338]">× {{ $item['quantity'] }}</span>
                                    </td>
                                    <td class="p-4 text-right font-medium text-[#2c3338]">
                                        {{ falcon_price_format(($item['sale_price'] ?: $item['price']) * $item['quantity']) }}
                                    </td>
                                </tr>
                            @endforeach
                            
                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-[#2c3338]">Subtotal</th>
                                <td class="text-right p-4 font-bold text-[#2c3338]" id="checkout-subtotal">{{ falcon_price_format(get_falcon_cart_subtotal()) }}</td>
                            </tr>

                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-[#2c3338]">Shipping</th>
                                <td class="text-right p-4 text-[#515151]" id="checkout-shipping">
                                    Flat rate: <span class="font-bold text-[#2c3338]">{{ get_falcon_cart_shipping() > 0 ? falcon_price_format(get_falcon_cart_shipping()) : 'Free' }}</span>
                                </td>
                            </tr>

                            @if(get_cms_option('shop_enable_tax') === '1')
                            <tr class="border-b border-[#eee]">
                                <th class="text-left p-4 font-bold text-[#2c3338]">Estimated Tax</th>
                                <td class="text-right p-4 font-bold text-[#2c3338]" id="checkout-tax">{{ falcon_price_format(get_falcon_cart_tax()) }}</td>
                            </tr>
                            @endif

                            @php $coupon = session()->get('lazy_coupon'); @endphp
                            @if($coupon)
                                @php 
                                    $subtotal = get_falcon_cart_subtotal();
                                    $discount = $coupon['type'] === 'percent' ? $subtotal * ((float)$coupon['amount'] / 100) : (float)$coupon['amount'];
                                @endphp
                                <tr class="coupon-row bg-emerald-50/30">
                                    <th class="text-left p-4 font-bold text-emerald-700">Coupon: {{ $coupon['code'] }}</th>
                                    <td class="text-right p-4 font-bold text-emerald-700">-{{ falcon_price_format($discount) }}</td>
                                </tr>
                            @endif

                            <tr class="bg-[#fcfcfc]">
                                <th class="text-left p-4 font-bold text-[#2c3338]">Total</th>
                                <td class="text-right p-4 text-[18px] font-bold text-[#1363df]" id="checkout-total">{{ falcon_price_format(get_falcon_cart_total()) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Payment Section -->
                    <div class="p-8 border-t border-[#eee]">
                        <div class="max-w-4xl">
                            <div class="bg-[#f7f6f7] p-6 mb-8 relative rounded-sm">
                                <div class="absolute -top-3 left-6 w-0 h-0 border-l-[12px] border-l-transparent border-r-[12px] border-r-transparent border-b-[12px] border-b-[#f7f6f7]"></div>
                                
                                <div class="space-y-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="payment_method" value="cod" checked class="w-4 h-4 text-[#1363df] focus:ring-0">
                                        <span class="text-[14px] font-bold text-[#2c3338]">Cash on delivery</span>
                                    </label>
                                    <div class="bg-white/50 border border-black/5 p-4 text-[14px] text-[#515151] rounded-sm">
                                        Pay with cash upon delivery.
                                    </div>
                                </div>
                            </div>
                            
                            <p class="text-[13px] text-[#777] mb-8 leading-relaxed max-w-2xl">
                                Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our <a href="#" class="text-[#1363df] hover:underline">privacy policy</a>.
                            </p>

                            <button type="submit" class="bg-[#1363df] text-white px-10 py-4 rounded-sm font-bold text-[16px] hover:bg-[#005ba6] transition-all shadow-lg shadow-blue-200 uppercase">
                                Place order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        @else
        <div class="bg-white p-20 text-center border border-[#eee] rounded-sm">
            <h2 class="text-[24px] font-bold text-[#2c3338] mb-4">Your cart is empty</h2>
            <p class="text-[#777] mb-8">Add products to your cart before checking out.</p>
            <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-[#1363df] text-white px-8 py-3 rounded-sm font-bold hover:bg-[#005ba6] transition-colors uppercase">Return to shop</a>
        </div>
        @endif
    </div>
</div>
@stop

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

document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.querySelector('form[action="{{ route('shop.place-order') }}"]');
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
                        confirmButtonColor: '#1363df'
                    });
                    submitBtn.innerText = originalText;
                    submitBtn.disabled = false;
                } else if (data.message) {
                    Swal.fire({ title: 'Error', text: data.message, icon: 'error', confirmButtonColor: '#1363df' });
                    submitBtn.innerText = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({ title: 'Error!', text: 'Something went wrong while processing your order. Please try again.', icon: 'error', confirmButtonColor: '#1363df' });
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // ── Checkout form persistence (localStorage) ───────────────────────────
    (function () {
        var KEY = 'lazy_checkout_draft';
        var TEXT  = ['billing_first_name','billing_last_name','billing_email','billing_phone',
                     'billing_address_1','billing_address_2','billing_city','billing_state','billing_postcode',
                     'shipping_first_name','shipping_last_name',
                     'shipping_address_1','shipping_address_2','shipping_city','shipping_state','shipping_postcode',
                     'order_comments'];
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
                if(cb && !cb.checked){ cb.checked=true; var sf=document.getElementById('shipping-form'); if(sf) sf.style.display='block'; }
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
