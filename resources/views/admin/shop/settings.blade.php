<x-cms-dashboard::layouts.admin title="Shop Settings" active-menu="shop">
    <div class="px-2" x-data="{ 
        tab: localStorage.getItem('active_shop_tab') || '{{ session('active_shop_tab', 'general') }}', 
        sellingLocations: '{{ get_shop_option('shop_selling_locations', 'all') }}',
        shippingLocations: '{{ get_shop_option('shop_shipping_locations', 'all') }}',
        manageStock: {{ get_shop_option('shop_manage_stock', '1') === '1' ? 'true' : 'false' }}
    }" x-init="$watch('tab', val => localStorage.setItem('active_shop_tab', val))">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Shop Settings</h1>
        
        @include('cms-dashboard::admin.shop.nav')

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.shop.settings.save') }}" method="POST" class="max-w-[800px]">
            @csrf
            
            <div x-show="tab === 'general'" x-transition>
                <!-- Store Address Section -->
                <h3 class="text-[16px] font-semibold text-[#1d2327] mb-2">Store Address</h3>
                <p class="text-[12px] text-[#646970] mb-6">This is where your business is located. Tax rates and shipping rates will use this address.</p>
                
                <table class="w-full border-separate border-spacing-y-6">
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Address Line 1</label>
                        </th>
                        <td>
                            <input type="text" name="address_line_1" value="{{ get_shop_option('shop_address_line_1') }}" class="wp-input w-[400px] h-8 shadow-sm">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Address Line 2</label>
                        </th>
                        <td>
                            <input type="text" name="address_line_2" value="{{ get_shop_option('shop_address_line_2') }}" class="wp-input w-[400px] h-8 shadow-sm">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">City</label>
                        </th>
                        <td>
                            <input type="text" name="city" value="{{ get_shop_option('shop_city') }}" class="wp-input w-[400px] h-8 shadow-sm">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Country / State</label>
                        </th>
                        <td>
                            <select name="country_state" id="country_state" class="wp-input w-[400px]">
                                <option value="">Select a country / state...</option>
                                @foreach($countries as $val => $label)
                                    <option value="{{ $val }}" {{ get_shop_option('shop_country_state') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Postcode / ZIP</label>
                        </th>
                        <td>
                            <input type="text" name="postcode" value="{{ get_shop_option('shop_postcode') }}" class="wp-input w-[400px] h-8 shadow-sm">
                        </td>
                    </tr>

                    <!-- General Options Section -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-4 mb-2">General options</h3></td></tr>
                    
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Selling location(s)</label>
                        </th>
                        <td>
                            <select name="selling_locations" x-model="sellingLocations" class="wp-input w-[400px] h-8 py-0">
                                <option value="all">Sell to all countries</option>
                                <option value="all_except">Sell to all countries, except for...</option>
                                <option value="specific">Sell to specific countries</option>
                            </select>
                        </td>
                    </tr>

                    <tr x-show="sellingLocations === 'specific'" x-transition>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Sell to specific countries</label>
                        </th>
                        <td>
                            <select name="selling_specific_countries[]" id="selling_specific_countries" multiple class="wp-input w-[400px]">
                                @foreach($countries as $val => $label)
                                    @php $selected = get_shop_option('shop_selling_specific_countries', []); @endphp
                                    <option value="{{ $val }}" {{ in_array($val, (array)$selected) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>

                    <tr x-show="sellingLocations === 'all_except'" x-transition>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Sell to all countries, except for...</label>
                        </th>
                        <td>
                            <select name="selling_except_countries[]" id="selling_except_countries" multiple class="wp-input w-[400px]">
                                @foreach($countries as $val => $label)
                                    @php $selected = get_shop_option('shop_selling_except_countries', []); @endphp
                                    <option value="{{ $val }}" {{ in_array($val, (array)$selected) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Shipping location(s)</label>
                        </th>
                        <td>
                            <select name="shipping_locations" x-model="shippingLocations" class="wp-input w-[400px] h-8 py-0">
                                <option value="all">Ship to all countries you sell to</option>
                                <option value="all_countries">Ship to all countries</option>
                                <option value="specific">Ship to specific countries only</option>
                                <option value="disabled">Disable shipping & shipping calculations</option>
                            </select>
                        </td>
                    </tr>

                    <tr x-show="shippingLocations === 'specific'" x-transition>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Ship to specific countries</label>
                        </th>
                        <td>
                            <select name="shipping_specific_countries[]" id="shipping_specific_countries" multiple class="wp-input w-[400px]">
                                @foreach($countries as $val => $label)
                                    @php $selected = get_shop_option('shop_shipping_specific_countries', []); @endphp
                                    <option value="{{ $val }}" {{ in_array($val, (array)$selected) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Default customer location</label>
                        </th>
                        <td>
                            <select name="default_customer_location" class="wp-input w-[400px] h-8 py-0">
                                <option value="none" {{ get_shop_option('shop_default_customer_location') === 'none' ? 'selected' : '' }}>No location by default</option>
                                <option value="base" {{ get_shop_option('shop_default_customer_location') === 'base' ? 'selected' : '' }}>Shop country/region</option>
                                <option value="geolocate" {{ get_shop_option('shop_default_customer_location') === 'geolocate' ? 'selected' : '' }}>Geolocate</option>
                            </select>
                        </td>
                    </tr>

                    <!-- Taxes and Coupons -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-4 mb-2">Taxes and coupons</h3></td></tr>
                    
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Enable taxes</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="calc_taxes" value="0">
                                <input type="checkbox" name="calc_taxes" value="1" {{ get_shop_option('shop_calc_taxes') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] text-[#1d2327]">Enable tax rates and calculations</span>
                            </label>
                        </td>
                    </tr>

                    <!-- Currency Options Section -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-4 mb-2">Currency options</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Currency</label>
                        </th>
                        <td>
                            <select name="currency" id="shop_currency" class="wp-input w-[400px]">
                                @foreach($currencies as $val => $label)
                                    <option value="{{ $val }}" {{ get_shop_option('shop_currency') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Currency position</label>
                        </th>
                        <td>
                            <select name="currency_pos" class="wp-input w-[200px] h-8 py-0">
                                <option value="left" {{ get_shop_option('shop_currency_pos', 'left') === 'left' ? 'selected' : '' }}>Left</option>
                                <option value="right" {{ get_shop_option('shop_currency_pos') === 'right' ? 'selected' : '' }}>Right</option>
                                <option value="left_space" {{ get_shop_option('shop_currency_pos') === 'left_space' ? 'selected' : '' }}>Left with space</option>
                                <option value="right_space" {{ get_shop_option('shop_currency_pos') === 'right_space' ? 'selected' : '' }}>Right with space</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Thousand separator</label>
                        </th>
                        <td>
                            <input type="text" name="thousand_sep" value="{{ get_shop_option('shop_thousand_sep', ',') }}" class="wp-input w-[100px] h-8 shadow-sm text-center">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Decimal separator</label>
                        </th>
                        <td>
                            <input type="text" name="decimal_sep" value="{{ get_shop_option('shop_decimal_sep', '.') }}" class="wp-input w-[100px] h-8 shadow-sm text-center">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Number of decimals</label>
                        </th>
                        <td>
                            <input type="number" name="num_decimals" value="{{ get_shop_option('shop_num_decimals', '2') }}" class="wp-input w-[100px] h-8 shadow-sm text-center">
                        </td>
                    </tr>
                </table>
            </div>

            <div x-show="tab === 'products'" x-transition>
                <table class="w-full border-separate border-spacing-y-6">
                    <!-- Shop pages -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mb-2">Shop pages</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Shop page</label>
                        </th>
                        <td>
                            <select name="shop_page_id" id="shop_page_id" class="wp-input w-[400px]">
                                <option value="">Select a page...</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page->id }}" {{ get_shop_option('shop_shop_page_id') == $page->id ? 'selected' : '' }}>{{ $page->title }}</option>
                                @endforeach
                            </select>
                            <p class="text-[12px] text-[#646970] mt-1">The base page can also be used in your product permalinks.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Cart page</label>
                        </th>
                        <td>
                            <select name="cart_page_id" id="cart_page_id" class="wp-input w-[400px]">
                                <option value="">Select a page...</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page->id }}" {{ get_shop_option('shop_cart_page_id') == $page->id ? 'selected' : '' }}>{{ $page->title }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Checkout page</label>
                        </th>
                        <td>
                            <select name="checkout_page_id" id="checkout_page_id" class="wp-input w-[400px]">
                                <option value="">Select a page...</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page->id }}" {{ get_shop_option('shop_checkout_page_id') == $page->id ? 'selected' : '' }}>{{ $page->title }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Account page</label>
                        </th>
                        <td>
                            <select name="account_page_id" id="account_page_id" class="wp-input w-[400px]">
                                <option value="">Select a page...</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page->id }}" {{ get_shop_option('shop_account_page_id') == $page->id ? 'selected' : '' }}>{{ $page->title }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>

                    <!-- Measurements -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mb-2">Measurements</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Weight unit</label>
                        </th>
                        <td>
                            <select name="weight_unit" class="wp-input w-[200px] h-8 py-0">
                                <option value="kg" {{ get_shop_option('shop_weight_unit', 'kg') === 'kg' ? 'selected' : '' }}>kg</option>
                                <option value="g" {{ get_shop_option('shop_weight_unit') === 'g' ? 'selected' : '' }}>g</option>
                                <option value="lbs" {{ get_shop_option('shop_weight_unit') === 'lbs' ? 'selected' : '' }}>lbs</option>
                                <option value="oz" {{ get_shop_option('shop_weight_unit') === 'oz' ? 'selected' : '' }}>oz</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Dimensions unit</label>
                        </th>
                        <td>
                            <select name="dimensions_unit" class="wp-input w-[200px] h-8 py-0">
                                <option value="cm" {{ get_shop_option('shop_dimensions_unit', 'cm') === 'cm' ? 'selected' : '' }}>cm</option>
                                <option value="mm" {{ get_shop_option('shop_dimensions_unit') === 'mm' ? 'selected' : '' }}>mm</option>
                                <option value="in" {{ get_shop_option('shop_dimensions_unit') === 'in' ? 'selected' : '' }}>in</option>
                                <option value="yd" {{ get_shop_option('shop_dimensions_unit') === 'yd' ? 'selected' : '' }}>yd</option>
                            </select>
                        </td>
                    </tr>

                    <!-- Reviews -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-4 mb-2">Reviews</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Enable reviews</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="enable_reviews" value="0">
                                <input type="checkbox" name="enable_reviews" value="1" {{ get_shop_option('shop_enable_reviews', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] text-[#1d2327]">Enable product reviews</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Product ratings</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="enable_review_rating" value="0">
                                <input type="checkbox" name="enable_review_rating" value="1" {{ get_shop_option('shop_enable_review_rating', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] text-[#1d2327]">Enable star rating on reviews</span>
                            </label>
                        </td>
                    </tr>

                    <!-- Inventory -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-4 mb-2">Inventory</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Manage stock</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="manage_stock" value="0">
                                <input type="checkbox" name="manage_stock" x-model="manageStock" value="1" class="w-4 h-4 mr-2">
                                <span class="text-[14px] text-[#1d2327]">Enable stock management</span>
                            </label>
                        </td>
                    </tr>

                    <template x-if="manageStock">
                        <tr x-transition>
                            <th scope="row" class="w-[200px] text-left align-top pt-2">
                                <label class="text-[14px] font-semibold text-[#1d2327]">Hold stock (minutes)</label>
                            </th>
                            <td>
                                <input type="number" name="hold_stock" value="{{ get_shop_option('shop_hold_stock', '60') }}" class="wp-input w-[100px] h-8 shadow-sm text-center">
                                <p class="text-[12px] text-[#646970] mt-1">Hold stock (for unpaid orders) for x minutes. When this limit is reached, the pending order will be cancelled. Leave blank to disable.</p>
                            </td>
                        </tr>
                    </template>

                    <template x-if="manageStock">
                        <tr x-transition>
                            <th scope="row" class="w-[200px] text-left align-top pt-2">
                                <label class="text-[14px] font-semibold text-[#1d2327]">Notifications</label>
                            </th>
                            <td>
                                <div class="space-y-3">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="hidden" name="notification_low_stock" value="0">
                                        <input type="checkbox" name="notification_low_stock" value="1" {{ get_shop_option('shop_notification_low_stock', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                        <span class="text-[14px] text-[#1d2327]">Enable low stock notifications</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="hidden" name="notification_no_stock" value="0">
                                        <input type="checkbox" name="notification_no_stock" value="1" {{ get_shop_option('shop_notification_no_stock', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                        <span class="text-[14px] text-[#1d2327]">Enable out of stock notifications</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Low stock threshold</label>
                        </th>
                        <td>
                            <input type="number" name="low_stock_threshold" value="{{ get_shop_option('shop_low_stock_threshold', '2') }}" class="wp-input w-[100px] h-8 shadow-sm text-center">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Out of stock threshold</label>
                        </th>
                        <td>
                            <input type="number" name="out_of_stock_threshold" value="{{ get_shop_option('shop_out_of_stock_threshold', '0') }}" class="wp-input w-[100px] h-8 shadow-sm text-center">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Stock display format</label>
                        </th>
                        <td>
                            <select name="stock_display_format" class="wp-input w-[400px] h-8 py-0">
                                <option value="always" {{ get_shop_option('shop_stock_display_format', 'always') === 'always' ? 'selected' : '' }}>Always show quantity remaining in stock</option>
                                <option value="low" {{ get_shop_option('shop_stock_display_format') === 'low' ? 'selected' : '' }}>Only show quantity remaining in stock when low</option>
                                <option value="never" {{ get_shop_option('shop_stock_display_format') === 'never' ? 'selected' : '' }}>Never show stock amount</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <div x-show="tab === 'payments'" x-transition>
                <div class="space-y-8">
                    <!-- Cash on Delivery -->
                    <div class="border border-[#c3c4c7] rounded p-4 bg-[#f6f7f7]">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#c3c4c7]">
                            <h3 class="text-[16px] font-semibold text-[#1d2327]">Cash on Delivery</h3>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="payment_cod_enable" value="0">
                                <input type="checkbox" name="payment_cod_enable" value="1" {{ get_shop_option('shop_payment_cod_enable') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] font-medium">Enable</span>
                            </label>
                        </div>
                        <table class="w-full border-separate border-spacing-y-4">
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">Title</label>
                                </th>
                                <td>
                                    <input type="text" name="payment_cod_title" value="{{ get_shop_option('shop_payment_cod_title', 'Cash on Delivery') }}" class="wp-input w-full h-8 shadow-sm">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">Description</label>
                                </th>
                                <td>
                                    <textarea name="payment_cod_desc" class="wp-input w-full h-20 p-2 shadow-sm">{{ get_shop_option('shop_payment_cod_desc', 'Pay with cash upon delivery.') }}</textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Direct Bank Transfer -->
                    <div class="border border-[#c3c4c7] rounded p-4 bg-[#f6f7f7]">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#c3c4c7]">
                            <h3 class="text-[16px] font-semibold text-[#1d2327]">Direct Bank Transfer</h3>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="payment_bank_enable" value="0">
                                <input type="checkbox" name="payment_bank_enable" value="1" {{ get_shop_option('shop_payment_bank_enable') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] font-medium">Enable</span>
                            </label>
                        </div>
                        <table class="w-full border-separate border-spacing-y-4">
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">Account Details</label>
                                </th>
                                <td>
                                    <textarea name="payment_bank_details" class="wp-input w-full h-24 p-2 shadow-sm" placeholder="Bank Name: ...&#10;Account Number: ...&#10;SWIFT: ...">{{ get_shop_option('shop_payment_bank_details') }}</textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- PayPal -->
                    <div class="border border-[#c3c4c7] rounded p-4 bg-[#f6f7f7]">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#c3c4c7]">
                            <h3 class="text-[16px] font-semibold text-[#1d2327]">PayPal Standard</h3>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="payment_paypal_enable" value="0">
                                <input type="checkbox" name="payment_paypal_enable" value="1" {{ get_shop_option('shop_payment_paypal_enable') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] font-medium">Enable</span>
                            </label>
                        </div>
                        <table class="w-full border-separate border-spacing-y-4">
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">PayPal Email</label>
                                </th>
                                <td>
                                    <input type="email" name="payment_paypal_email" value="{{ get_shop_option('shop_payment_paypal_email') }}" class="wp-input w-full h-8 shadow-sm">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">Sandbox Mode</label>
                                </th>
                                <td>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="payment_paypal_sandbox" value="0">
                                        <input type="checkbox" name="payment_paypal_sandbox" value="1" {{ get_shop_option('shop_payment_paypal_sandbox') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                        <span class="text-[14px]">Enable PayPal Sandbox (testing)</span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Stripe -->
                    <div class="border border-[#c3c4c7] rounded p-4 bg-[#f6f7f7]">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#c3c4c7]">
                            <h3 class="text-[16px] font-semibold text-[#1d2327]">Stripe</h3>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="payment_stripe_enable" value="0">
                                <input type="checkbox" name="payment_stripe_enable" value="1" {{ get_shop_option('shop_payment_stripe_enable') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] font-medium">Enable</span>
                            </label>
                        </div>
                        <table class="w-full border-separate border-spacing-y-4">
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">Publishable Key</label>
                                </th>
                                <td>
                                    <input type="text" name="payment_stripe_key" value="{{ get_shop_option('shop_payment_stripe_key') }}" class="wp-input w-full h-8 shadow-sm">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" class="w-[200px] text-left align-top pt-2">
                                    <label class="text-[14px] font-semibold text-[#1d2327]">Secret Key</label>
                                </th>
                                <td>
                                    <input type="password" name="payment_stripe_secret" value="{{ get_shop_option('shop_payment_stripe_secret') }}" class="wp-input w-full h-8 shadow-sm">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div x-show="tab === 'shipping'" x-transition 
                 x-data="{ 
                    shippingZones: {{ json_encode(get_shop_option('shop_shipping_zones', [])) }}
                 }">
                <table class="w-full border-separate border-spacing-y-6">
                    <!-- Global Shipping -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mb-2">Global Shipping</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Flat Rate Shipping Cost</label>
                        </th>
                        <td>
                            <input type="number" step="0.01" name="flat_rate_cost" value="{{ get_shop_option('shop_flat_rate_cost', '0') }}" class="wp-input w-[200px] h-8 shadow-sm">
                            <p class="text-[12px] text-[#646970] mt-1">Default shipping cost if no other rules apply.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Free Shipping Above</label>
                        </th>
                        <td>
                            <input type="number" step="0.01" name="free_shipping_threshold" value="{{ get_shop_option('shop_free_shipping_threshold', '0') }}" class="wp-input w-[200px] h-8 shadow-sm">
                            <p class="text-[12px] text-[#646970] mt-1">Enter 0 to disable free shipping threshold.</p>
                        </td>
                    </tr>

                    <!-- Zone Based Shipping -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-6 mb-2">Advanced Shipping Zones</h3></td></tr>
                    <tr>
                        <td colspan="2">
                            <div class="space-y-4">
                                <template x-for="(zone, index) in shippingZones" :key="index">
                                    <div class="border border-[#c3c4c7] rounded bg-white shadow-sm overflow-hidden mb-6">
                                        <div class="bg-[#f6f7f7] px-4 py-2 border-b border-[#c3c4c7] flex items-center justify-between">
                                            <div class="flex items-center gap-4 flex-1">
                                                <input type="text" :name="'shipping_zones['+index+'][name]'" x-model="zone.name" placeholder="Zone Name (e.g. Domestic)" class="wp-input h-7 text-[13px] font-semibold w-[300px]">
                                                <span class="text-[12px] text-[#646970]">Zone ID: <span x-text="index + 1"></span></span>
                                            </div>
                                            <button type="button" @click="shippingZones.splice(index, 1)" class="text-red-500 hover:text-red-700 text-[12px] flex items-center gap-1 font-semibold">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Remove Zone
                                            </button>
                                        </div>
                                        <div class="p-6 space-y-6">
                                            <!-- Countries Selection - Full Width -->
                                            <div>
                                                <label class="block text-[14px] font-semibold text-[#1d2327] mb-1">Countries / Regions in this zone</label>
                                                <div x-init="
                                                    const ts = new TomSelect($el.querySelector('select'), {
                                                        plugins: ['remove_button', 'dropdown_input'],
                                                        maxOptions: 1000,
                                                        placeholder: 'Select countries...',
                                                        onItemAdd: function() { this.setTextboxValue(''); },
                                                        onChange: function(val) { 
                                                            zone.countries = val ? (Array.isArray(val) ? val : val.split(',')) : [];
                                                        }
                                                    });
                                                    // Sync initial state
                                                    if(zone.countries) {
                                                        ts.setValue(zone.countries);
                                                    }
                                                ">
                                                    <select :name="'shipping_zones['+index+'][countries][]'" multiple class="wp-input w-full">
                                                        @foreach($allowedCountries as $code => $country)
                                                            <option value="{{ $code }}">{{ $country }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <p class="text-[12px] text-[#646970] mt-2 italic">Select the regions where these shipping rules will apply. You can group multiple countries or states together into a single shipping zone.</p>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pt-6 border-t border-gray-100">
                                                <!-- Calculation Settings -->
                                                <div class="space-y-6">
                                                    <div>
                                                        <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Base Shipping Cost</label>
                                                        <input type="number" step="0.01" :name="'shipping_zones['+index+'][cost]'" x-model="zone.cost" class="wp-input w-full h-8 shadow-sm">
                                                        <p class="text-[11px] text-[#646970] mt-1">The fundamental shipping fee applied to any order within this zone, regardless of quantity.</p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Calculation Type</label>
                                                        <select :name="'shipping_zones['+index+'][type]'" x-model="zone.type" class="wp-input w-full h-8 py-0">
                                                            <option value="order">Flat Rate (Per Order)</option>
                                                            <option value="item">Quantity Based (Per Item Range)</option>
                                                        </select>
                                                        <p class="text-[11px] text-[#646970] mt-1">Choose 'Flat Rate' for a one-time fee per order, or 'Quantity Based' to define costs for different product count ranges.</p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Free Shipping Threshold</label>
                                                        <input type="number" step="0.01" :name="'shipping_zones['+index+'][free_threshold]'" x-model="zone.free_threshold" placeholder="0 for none" class="wp-input w-full h-8 shadow-sm">
                                                        <p class="text-[11px] text-[#646970] mt-1">Enable free shipping when the order subtotal reaches this amount in this specific zone. Set to 0 to disable.</p>
                                                    </div>
                                                </div>

                                                <!-- Quantity Rules - Expanded -->
                                                <div x-show="zone.type === 'item'" class="md:col-span-2 border border-[#e5e7eb] rounded-lg bg-[#f9fafb] p-5 space-y-4">
                                                    <div class="flex items-center justify-between border-b border-[#e5e7eb] pb-2">
                                                        <div>
                                                            <label class="block text-[12px] font-bold text-[#374151] uppercase tracking-wider">Quantity Based Rules</label>
                                                            <p class="text-[11px] text-[#646970]">Define shipping costs based on the number of items in the cart for more precise logistics pricing.</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="space-y-3">
                                                        <template x-for="(rule, rIndex) in (zone.rules || (zone.rules = []))" :key="rIndex">
                                                            <div class="flex items-center gap-3 bg-white p-3 rounded-md border border-[#e5e7eb] shadow-sm">
                                                                <div class="flex-1 grid grid-cols-3 gap-4">
                                                                    <div>
                                                                        <label class="block text-[11px] font-medium text-[#646970] mb-1">Min Qty</label>
                                                                        <input type="number" :name="'shipping_zones['+index+'][rules]['+rIndex+'][min]'" x-model="rule.min" class="wp-input w-full h-8 text-[13px]">
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[11px] font-medium text-[#646970] mb-1">Max Qty</label>
                                                                        <input type="number" :name="'shipping_zones['+index+'][rules]['+rIndex+'][max]'" x-model="rule.max" placeholder="∞" class="wp-input w-full h-8 text-[13px]">
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[11px] font-medium text-[#646970] mb-1">Shipping Cost</label>
                                                                        <input type="number" step="0.01" :name="'shipping_zones['+index+'][rules]['+rIndex+'][cost]'" x-model="rule.cost" class="wp-input w-full h-8 text-[13px]">
                                                                    </div>
                                                                </div>
                                                                <button type="button" @click="zone.rules.splice(rIndex, 1)" class="mt-5 text-red-400 hover:text-red-600 p-1 transition-colors">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <button type="button" @click="if(!zone.rules) zone.rules = []; zone.rules.push({min: '', max: '', cost: ''})" class="wp-btn px-4 h-8 text-[12px] flex items-center gap-2 font-semibold">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        Add New Range
                                                    </button>
                                                    <div class="bg-blue-50 p-3 rounded text-[11px] text-blue-700 leading-relaxed">
                                                        <strong>Pro Tip:</strong> Use quantity ranges to incentivize bulk purchases. For example, 1-5 items = $10, while 6-10 items could be $15 total (effectively reducing the cost per item). Leave Max Qty empty for an open-ended upper limit.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="shippingZones.length === 0" class="border border-dashed border-[#c3c4c7] rounded p-12 text-center bg-[#fbfbfc]">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#c3c4c7] mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2 2 2 0 012 2v.1a2 2 0 01.458 1.341l-1.458 1.458a2 2 0 01-1.414.586H15a2 2 0 00-2 2v1a2 2 0 01-2 2H8m-5 1a9 9 0 1118 0 9 9 0 01-18 0z" />
                                    </svg>
                                    <p class="text-[#646970] text-[15px] mb-6">No shipping zones have been created yet. Define zones to specify location-based rates.</p>
                                    <button type="button" @click="shippingZones.push({name: '', countries: [], cost: '0', type: 'order', free_threshold: '0', rules: []})" class="wp-btn-primary px-6 h-10 font-semibold shadow-md">
                                        Create First Shipping Zone
                                    </button>
                                </div>

                                <div x-show="shippingZones.length > 0" class="flex justify-start">
                                    <button type="button" @click="shippingZones.push({name: '', countries: [], cost: '0', type: 'order', free_threshold: '0', rules: []})" class="wp-btn-primary px-5 h-9 flex items-center gap-2 font-semibold">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        Add Shipping Zone
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Local Pickup -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-6 mb-2">Local Pickup</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Enable Local Pickup</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="local_pickup_enable" value="0">
                                <input type="checkbox" name="local_pickup_enable" value="1" {{ get_shop_option('shop_local_pickup_enable') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px]">Allow customers to pick up orders themselves</span>
                            </label>
                        </td>
                    </tr>
                    <!-- Delivery Calculations -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-10 mb-2">Shipping Estimator</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Calculations</label>
                        </th>
                        <td>
                            <div class="space-y-3">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="calc_enable_cart_estimator" value="0">
                                    <input type="checkbox" name="calc_enable_cart_estimator" value="1" {{ get_shop_option('shop_calc_enable_cart_estimator') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Allow shipping cost estimation in the shopping cart</span>
                                </label>
                                <br>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="calc_hide_until_address" value="0">
                                    <input type="checkbox" name="calc_hide_until_address" value="1" {{ get_shop_option('shop_calc_hide_until_address') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Only display shipping fees after a valid address is provided</span>
                                </label>
                                <br>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="calc_hide_paid_when_free" value="0">
                                    <input type="checkbox" name="calc_hide_paid_when_free" value="1" {{ get_shop_option('shop_calc_hide_paid_when_free') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Auto-hide paid shipping options when free delivery is applicable</span>
                                </label>
                            </div>
                            <p class="text-[12px] text-[#646970] mt-2 italic">Control how and when shipping rates are calculated and displayed to your customers during the checkout process.</p>
                        </td>
                    </tr>

                    <!-- Shipping Destination -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-8 mb-2">Delivery Fulfillment Target</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Default Address Type</label>
                        </th>
                        <td>
                            <div class="space-y-3">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="shipping_destination" value="shipping" {{ get_shop_option('shop_shipping_destination', 'shipping') === 'shipping' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Use the customer's shipping address as the primary target</span>
                                </label>
                                <br>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="shipping_destination" value="billing" {{ get_shop_option('shop_shipping_destination') === 'billing' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Set the billing address as the initial delivery choice</span>
                                </label>
                                <br>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="shipping_destination" value="force_billing" {{ get_shop_option('shop_shipping_destination') === 'force_billing' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Mandatory shipping to the provided billing address (Disables shipping fields)</span>
                                </label>
                            </div>
                            <p class="text-[12px] text-[#646970] mt-2 italic">Decide which address should be prioritized for order fulfillment and whether customers can choose a separate shipping address.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div x-show="tab === 'tax'" x-transition
                 x-data="{ 
                    taxRates: {{ json_encode(get_shop_option('shop_tax_rates', [])) }},
                    enableTax: {{ get_shop_option('shop_calc_taxes', '0') === '1' ? 'true' : 'false' }}
                 }">
                <table class="w-full border-separate border-spacing-y-6">
                    <!-- Tax Enable -->
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Enable Tax</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="hidden" name="calc_taxes" value="0">
                                <input type="checkbox" name="calc_taxes" value="1" x-model="enableTax" class="w-4 h-4 mr-2">
                                <span class="text-[14px]">Enable tax calculations and display</span>
                            </label>
                            <p class="text-[12px] text-[#646970] mt-1">Check this to enable tax calculations at checkout and manage tax rates below.</p>
                        </td>
                    </tr>

                    <!-- Advanced Tax Options (Conditional) -->
                    <tbody x-show="enableTax" x-transition>
                        <!-- Calculation Basis -->
                        <tr>
                            <th scope="row" class="w-[200px] text-left align-top pt-2">
                                <label class="text-[14px] font-semibold text-[#1d2327]">Calculate Tax Based On</label>
                            </th>
                            <td>
                                <select name="tax_calculation_basis" class="wp-input w-[300px] h-8 py-0">
                                    <option value="shipping" {{ get_shop_option('shop_tax_calculation_basis') === 'shipping' ? 'selected' : '' }}>Customer shipping address</option>
                                    <option value="billing" {{ get_shop_option('shop_tax_calculation_basis') === 'billing' ? 'selected' : '' }}>Customer billing address</option>
                                    <option value="base" {{ get_shop_option('shop_tax_calculation_basis') === 'base' ? 'selected' : '' }}>Shop base address</option>
                                </select>
                                <p class="text-[12px] text-[#646970] mt-1">Determines which address is used to calculate tax rates.</p>
                            </td>
                        </tr>

                        <!-- Price Display -->
                        <tr>
                            <th scope="row" class="w-[200px] text-left align-top pt-2">
                                <label class="text-[14px] font-semibold text-[#1d2327]">Price Display Settings</label>
                            </th>
                            <td>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[13px] font-semibold mb-1">Prices entered with tax</label>
                                        <select name="tax_price_entry" class="wp-input w-[300px] h-8 py-0">
                                            <option value="exclusive" {{ get_shop_option('shop_tax_price_entry') === 'exclusive' ? 'selected' : '' }}>No, I will enter prices exclusive of tax</option>
                                            <option value="inclusive" {{ get_shop_option('shop_tax_price_entry') === 'inclusive' ? 'selected' : '' }}>Yes, I will enter prices inclusive of tax</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[13px] font-semibold mb-1">Display prices in shop</label>
                                        <select name="tax_display_shop" class="wp-input w-[300px] h-8 py-0">
                                            <option value="exclusive" {{ get_shop_option('shop_tax_display_shop', 'exclusive') === 'exclusive' ? 'selected' : '' }}>Excluding tax</option>
                                            <option value="inclusive" {{ get_shop_option('shop_tax_display_shop') === 'inclusive' ? 'selected' : '' }}>Including tax</option>
                                        </select>
                                    </div>
                                </div>
                                <p class="text-[12px] text-[#646970] mt-2">Control how product prices are entered in the backend and displayed to your customers.</p>
                            </td>
                        </tr>

                        <!-- Dynamic Tax Rates -->
                        <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-10 mb-2">Custom Tax Rates</h3></td></tr>
                        <tr>
                            <td colspan="2">
                                <div class="border border-[#c3c4c7] rounded bg-white overflow-hidden shadow-sm">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="bg-[#f6f7f7] border-b border-[#c3c4c7]">
                                            <tr>
                                                <th class="px-4 py-2 text-[12px] font-bold text-[#1d2327] uppercase">Country / Region</th>
                                                <th class="px-4 py-2 text-[12px] font-bold text-[#1d2327] uppercase w-[120px]">Rate (%)</th>
                                                <th class="px-4 py-2 text-[12px] font-bold text-[#1d2327] uppercase">Tax Name</th>
                                                <th class="px-4 py-2 text-[12px] font-bold text-[#1d2327] uppercase w-[100px]">Shipping</th>
                                                <th class="px-4 py-2 text-[12px] font-bold text-[#1d2327] uppercase w-[50px]"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(rate, index) in taxRates" :key="index">
                                                <tr class="border-b border-[#f0f0f1] hover:bg-[#fbfbfc]">
                                                    <td class="px-4 py-3">
                                                        <select :name="'tax_rates['+index+'][country]'" x-model="rate.country" class="wp-input w-full h-8 py-0">
                                                            <option value="*">All Countries (*)</option>
                                                            @foreach($countries as $code => $country)
                                                                <option value="{{ $code }}">{{ $country }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input type="number" step="0.0001" :name="'tax_rates['+index+'][rate]'" x-model="rate.rate" class="wp-input w-full h-8 shadow-sm">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input type="text" :name="'tax_rates['+index+'][name]'" x-model="rate.name" placeholder="VAT / GST" class="wp-input w-full h-8 shadow-sm">
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <input type="hidden" :name="'tax_rates['+index+'][shipping]'" value="0">
                                                        <input type="checkbox" :name="'tax_rates['+index+'][shipping]'" value="1" x-model="rate.shipping" class="w-4 h-4">
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button type="button" @click="taxRates.splice(index, 1)" class="text-red-500 hover:text-red-700">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="taxRates.length === 0">
                                                <td colspan="5" class="px-4 py-8 text-center text-[#646970] italic">
                                                    No specific tax rates defined. Taxes will not be applied.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="bg-[#f6f7f7] px-4 py-2 border-t border-[#c3c4c7]">
                                        <button type="button" @click="taxRates.push({country: '*', rate: '0', name: 'Tax', shipping: true})" class="wp-btn px-4 h-8 text-[12px] flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Tax Rate
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-4 p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-700 text-[13px] leading-relaxed shadow-sm rounded">
                                    <strong>How Tax Rates Work:</strong> 
                                    <ul class="list-disc ml-5 mt-2 space-y-1">
                                        <li><strong>Country:</strong> Select a specific region or use "*" for a global fallback rate.</li>
                                        <li><strong>Rate (%):</strong> Enter the percentage (e.g., 15.0000 for 15%). Support up to 4 decimal places.</li>
                                        <li><strong>Tax Name:</strong> The label customers will see (e.g., "VAT", "GST", "Sales Tax").</li>
                                        <li><strong>Shipping:</strong> Toggle whether this tax should also apply to shipping costs.</li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Coupons Tab -->
            <div x-show="tab === 'coupons'" x-transition
                 x-data="{ 
                    coupons: {{ json_encode(get_shop_option('shop_coupons', [])) }}
                 }">
                <table class="w-full border-separate border-spacing-y-6">
                    <!-- Global Coupon Settings -->
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">General Settings</label>
                        </th>
                        <td>
                            <div class="space-y-3">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="enable_coupons" value="1" {{ (int)get_shop_option('shop_enable_coupons', '1') == 1 ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Enable the use of coupon codes</span>
                                </label>
                                <br>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="multi_coupon_policy" value="1" {{ (int)get_shop_option('shop_coupon_stacking_policy', '1') == 1 ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Allow multiple coupons per order (Applied sequentially)</span>
                                </label>
                            </div>
                            <p class="text-[12px] text-[#646970] mt-2 italic">Global controls for your discount system. Decide if customers can use multiple codes at once.</p>
                        </td>
                    </tr>

                    <!-- Advanced Coupon Management -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-6 mb-2">Advanced Coupon Management</h3></td></tr>
                    <tr>
                        <td colspan="2">
                            <div class="space-y-6">
                                <template x-for="(coupon, index) in coupons" :key="index">
                                    <div class="border border-[#c3c4c7] rounded bg-white shadow-sm relative group" x-init="coupon.collapsed = (typeof coupon.collapsed !== 'undefined') ? (coupon.collapsed === 'true' || coupon.collapsed === true) : true">
                                        <input type="hidden" :name="'coupons['+index+'][collapsed]'" x-model="coupon.collapsed">
                                        <!-- Card Header / Coupon Code -->
                                        <div class="bg-white px-6 py-5 border-b border-[#c3c4c7] flex justify-between items-center rounded-t cursor-pointer hover:bg-gray-50/80 transition-all duration-300 group/header" @click="coupon.collapsed = !coupon.collapsed">
                                            <div class="flex items-center gap-5 flex-1 mr-10">
                                                <div class="flex items-center justify-center w-10 h-10 rounded-full border transition-all duration-300" 
                                                     :class="coupon.collapsed ? 'bg-gray-50 border-gray-200 text-gray-400' : 'bg-blue-50 border-blue-100 text-blue-600 shadow-sm shadow-blue-100'">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-500" :class="coupon.collapsed ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-3 mb-1">
                                                        <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[9px] font-bold uppercase tracking-wider border border-blue-100">Coupon</span>
                                                        <template x-if="coupon.expiry">
                                                            <div class="flex gap-2">
                                                                <template x-if="coupon.expiry < new Date().toISOString().split('T')[0]">
                                                                    <span class="px-2 py-0.5 bg-red-50 text-red-600 rounded text-[9px] font-bold uppercase tracking-wider border border-red-100">Expired</span>
                                                                </template>
                                                                <template x-if="coupon.expiry === new Date().toISOString().split('T')[0]">
                                                                    <span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded text-[9px] font-bold uppercase tracking-wider border border-amber-100">Expires Today</span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-[18px] font-black uppercase tracking-[0.15em] transition-colors duration-300" 
                                                              :class="coupon.collapsed ? (coupon.expiry && coupon.expiry < new Date().toISOString().split('T')[0] ? 'text-red-400' : 'text-gray-700') : 'text-blue-600'"
                                                              x-text="coupon.code || 'UNNAMED_COUPON'"></span>
                                                        <template x-if="!coupon.code">
                                                            <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-1 rounded-full border border-red-100 uppercase tracking-tighter">Required</span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3" @click.stop>
                                                <button type="button" @click="coupons.splice(index, 1)" class="group/del text-gray-300 hover:text-red-500 p-2.5 rounded-full transition-all hover:bg-red-50">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform group-hover/del:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Card Body -->
                                        <div x-show="!coupon.collapsed" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="p-8 space-y-8 bg-white border-t border-gray-50">
                                            <!-- Row 0: Code Editor -->
                                            <div class="bg-gray-50/50 p-6 rounded-lg border border-gray-100 mb-2">
                                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-3 tracking-widest">Update Coupon Code</label>
                                                <input type="text" :name="'coupons['+index+'][code]'" x-model="coupon.code" placeholder="e.g. SUMMER50" class="wp-input w-full h-11 text-[18px] font-black uppercase tracking-[0.2em] text-blue-700 bg-white border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all rounded-md">
                                            </div>
                                            <!-- Row 1: Type, Amount, Expiry -->
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                <div class="space-y-2">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327]">Discount Type</label>
                                                    <select :name="'coupons['+index+'][type]'" x-model="coupon.type" class="wp-input w-full h-9 py-0 text-[13px]">
                                                        <option value="percent">Percentage Discount (%)</option>
                                                        <option value="fixed_cart">Fixed Cart Discount</option>
                                                        <option value="fixed_product">Fixed Product Discount</option>
                                                        <option value="free_shipping">Free Shipping</option>
                                                    </select>
                                                </div>
                                                <div class="space-y-2" x-show="coupon.type !== 'free_shipping'">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327]">Coupon Amount / Value</label>
                                                    <div class="flex items-center">
                                                        <input type="number" step="0.01" :name="'coupons['+index+'][amount]'" x-model="coupon.amount" placeholder="0.00" class="wp-input w-full h-9 text-[14px] font-semibold rounded-r-none border-r-0">
                                                        <span class="h-9 px-3 flex items-center justify-center bg-[#f0f0f1] border border-[#8c8f94] rounded-r text-[12px] font-bold text-[#646970] min-w-[35px]" x-text="coupon.type === 'percent' ? '%' : '$'"></span>
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327]">Expiry Date</label>
                                                    <input type="date" :name="'coupons['+index+'][expiry]'" x-model="coupon.expiry" class="wp-input w-full h-9 text-[13px]">
                                                </div>
                                            </div>

                                            <!-- Row 2: Restrictions -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-50">
                                                <div class="space-y-2">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327]">Minimum Spend Required</label>
                                                    <input type="number" step="0.01" :name="'coupons['+index+'][min_spend]'" x-model="coupon.min_spend" placeholder="0.00" class="wp-input w-full h-9 text-[13px]">
                                                    <p class="text-[11px] text-[#646970] italic">The minimum cart subtotal needed to use this coupon.</p>
                                                </div>
                                                <div class="space-y-2">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327]">Usage Limit Per User</label>
                                                    <input type="number" :name="'coupons['+index+'][usage_limit]'" x-model="coupon.usage_limit" placeholder="1" class="wp-input w-full h-9 text-[13px]">
                                                    <p class="text-[11px] text-[#646970] italic">How many times a single customer can use this code.</p>
                                                </div>
                                            </div>

                                            <!-- Advanced Toggle -->
                                            <div class="pt-4 mt-4 border-t border-gray-50 flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-1.5 h-1.5 rounded-full" :class="(coupon.products?.length > 0 || coupon.categories?.length > 0) ? 'bg-blue-500' : 'bg-gray-300'"></div>
                                                    <span class="text-[12px] font-medium text-gray-500">Advanced restrictions are <span x-text="(coupon.products?.length > 0 || coupon.categories?.length > 0) ? 'active' : 'inactive'"></span></span>
                                                </div>
                                                <button type="button" 
                                                        @click="coupon.show_advanced = !coupon.show_advanced" 
                                                        class="flex items-center gap-2 px-3 py-1.5 bg-white border border-[#c3c4c7] hover:border-blue-500 rounded text-[12px] font-semibold text-[#2c3338] transition-all hover:text-blue-600 shadow-sm">
                                                    <span x-text="coupon.show_advanced ? 'Hide Advanced Options' : 'Show Advanced Options'"></span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300" :class="coupon.show_advanced ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <!-- Row 3: Product/Category Selection -->
                                            <div x-show="coupon.show_advanced" x-transition class="space-y-4 pt-4 border-t border-gray-50">
                                                <div class="space-y-2">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327] flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                        Restrict to Specific Products
                                                    </label>
                                                    <div x-init="
                                                        const ts = new TomSelect($el.querySelector('select'), {
                                                            plugins: ['remove_button', 'dropdown_input'],
                                                            placeholder: 'Search and select products...',
                                                            maxOptions: 1000,
                                                            dropdownParent: 'body',
                                                            onItemAdd: function() { this.setTextboxValue(''); },
                                                            onChange: function(val) { 
                                                                coupon.products = val ? (Array.isArray(val) ? val : val.split(',')) : [];
                                                            }
                                                        });
                                                        if(coupon.products) ts.setValue(coupon.products);
                                                    ">
                                                        <select :name="'coupons['+index+'][products][]'" multiple class="wp-input w-full">
                                                            @foreach($products as $product)
                                                                <option value="{{ $product->id }}">{{ $product->title }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <p class="text-[11px] text-[#646970] mt-1 italic">Select specific products that this coupon can be applied to. If left empty, the coupon will apply to all products in the cart.</p>
                                                </div>

                                                <div class="space-y-2">
                                                    <label class="block text-[12px] font-semibold text-[#1d2327] flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                                        Restrict to Specific Categories
                                                    </label>
                                                    <div x-init="
                                                        const ts = new TomSelect($el.querySelector('select'), {
                                                            plugins: ['remove_button', 'dropdown_input'],
                                                            placeholder: 'Search and select categories...',
                                                            maxOptions: 1000,
                                                            dropdownParent: 'body',
                                                            onItemAdd: function() { this.setTextboxValue(''); },
                                                            onChange: function(val) { 
                                                                coupon.categories = val ? (Array.isArray(val) ? val : val.split(',')) : [];
                                                            }
                                                        });
                                                        if(coupon.categories) ts.setValue(coupon.categories);
                                                    ">
                                                        <select :name="'coupons['+index+'][categories][]'" multiple class="wp-input w-full">
                                                            @foreach($categories as $category)
                                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <p class="text-[11px] text-[#646970] mt-1 italic">Select specific product categories that this coupon can be applied to. The discount will only be applied to items belonging to these categories.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="coupons.length === 0" class="border-2 border-dashed border-[#c3c4c7] rounded-lg p-10 text-center bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-[#c3c4c7] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                    </svg>
                                    <p class="text-[#646970] italic">No coupons created yet. Start by adding a new discount code below.</p>
                                </div>

                                <button type="button" @click="coupons.push({code: '', type: 'percent', amount: '', min_spend: '', usage_limit: '1', expiry: '', products: [], categories: []})" class="wp-btn px-6 h-10 text-[14px] flex items-center gap-2 font-bold bg-[#f6f7f7] border-[#c3c4c7] hover:bg-white transition-all shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add New Coupon
                                </button>
                            </div>
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-3 bg-blue-50 border-l-4 border-blue-400 text-blue-700 text-[11px] leading-relaxed rounded shadow-sm">
                                    <strong>Discount Types:</strong><br>
                                    • <strong>Percentage:</strong> Subtracts a % from the total.<br>
                                    • <strong>Fixed Cart:</strong> Subtracts a flat amount from the entire cart.<br>
                                    • <strong>Free Shipping:</strong> Removes all shipping costs.
                                </div>
                                <div class="p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-700 text-[11px] leading-relaxed rounded shadow-sm">
                                    <strong>Pro Tip:</strong> Use 'Usage Limit' to prevent abuse. Setting it to '1' ensures a customer can only use a specific code once. Combine with 'Min Spend' for high-value promotions.
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Email and Account Tab -->
            <div x-show="tab === 'emails_accounts'" x-transition>
                <table class="w-full border-separate border-spacing-y-6">
                    <!-- Guest & Account Checkout -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mb-2">Account creation & checkout</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Guest checkout</label>
                        </th>
                        <td>
                            <div class="space-y-3">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="checkout_guest_enable" value="0">
                                    <input type="checkbox" name="checkout_guest_enable" value="1" {{ get_shop_option('shop_checkout_guest_enable', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Allow customers to place orders without an account</span>
                                </label>
                                <br>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="checkout_login_enable" value="0">
                                    <input type="checkbox" name="checkout_login_enable" value="1" {{ get_shop_option('shop_checkout_login_enable', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Allow customers to log into an existing account during checkout</span>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Account creation</label>
                        </th>
                        <td>
                            <div class="space-y-3">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="checkout_create_account" value="0">
                                    <input type="checkbox" name="checkout_create_account" value="1" {{ get_shop_option('shop_checkout_create_account', '1') === '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                    <span class="text-[14px]">Allow customers to create an account during checkout</span>
                                </label>
                            </div>
                        </td>
                    </tr>

                    <!-- Email Settings -->
                    <tr><td colspan="2"><h3 class="text-[16px] font-semibold text-[#1d2327] mt-8 mb-2">Email sender options</h3></td></tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">"From" name</label>
                        </th>
                        <td>
                            <input type="text" name="email_from_name" value="{{ get_shop_option('shop_email_from_name', config('app.name', 'Lazy Panda Shop')) }}" class="wp-input w-[400px] h-8 shadow-sm">
                            <p class="text-[12px] text-[#646970] mt-1">How the sender name appears in outgoing transactional emails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">"From" address</label>
                        </th>
                        <td>
                            <input type="email" name="email_from_address" value="{{ get_shop_option('shop_email_from_address', 'store@' . request()->getHost()) }}" class="wp-input w-[400px] h-8 shadow-sm">
                            <p class="text-[12px] text-[#646970] mt-1">How the sender email appears in outgoing transactional emails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Admin notification email</label>
                        </th>
                        <td>
                            <input type="email" name="email_admin_recipient" value="{{ get_shop_option('shop_email_admin_recipient', auth()->user()->email ?? '') }}" class="wp-input w-[400px] h-8 shadow-sm">
                            <p class="text-[12px] text-[#646970] mt-1">Recipient email address for new order notifications and low stock alerts.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Email header image URL</label>
                        </th>
                        <td>
                            <input type="text" name="email_header_image" value="{{ get_shop_option('shop_email_header_image') }}" class="wp-input w-[400px] h-8 shadow-sm" placeholder="https://example.com/logo.png">
                            <p class="text-[12px] text-[#646970] mt-1">URL to an image you want to show at the top of order emails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Email footer text</label>
                        </th>
                        <td>
                            <input type="text" name="email_footer_text" value="{{ get_shop_option('shop_email_footer_text', 'Thank you for shopping with us!') }}" class="wp-input w-[400px] h-8 shadow-sm">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="pt-6 border-t border-gray-100 mt-6">
                <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        .ts-wrapper.wp-input {
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            height: auto !important;
            min-height: 32px !important;
        }
        .ts-control {
            border: 1px solid #8c8f94 !important;
            border-radius: 3px !important;
            padding: 4px 12px !important;
            font-size: 14px !important;
            color: #1d2327 !important;
            background-color: #fff !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.07) inset !important;
            min-height: 32px !important;
            display: flex !important;
            align-items: center !important;
        }
        .ts-wrapper.focus .ts-control {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: none !important;
        }
        .ts-dropdown {
            font-size: 14px !important;
            border: 1px solid #2271b1 !important;
            border-top: none !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            border-radius: 0 0 3px 3px !important;
            z-index: 9999 !important;
        }
        .ts-dropdown .active {
            background-color: #2271b1 !important;
            color: #fff !important;
        }
        .ts-dropdown .option {
            padding: 8px 12px !important;
        }
        .ts-control input {
            font-size: 14px !important;
        }
        /* Dropdown Search Box Style */
        .ts-dropdown .dropdown-input-wrap {
            padding: 8px !important;
            border-bottom: 1px solid #e8e8e8 !important;
            background-color: #f6f7f7 !important;
        }
        .ts-dropdown .dropdown-input {
            border: 1px solid #8c8f94 !important;
            border-radius: 3px !important;
            padding: 6px 10px !important;
            font-size: 13px !important;
            width: 100% !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.07) inset !important;
        }
        .ts-dropdown .dropdown-input:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: none !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const config = {
                plugins: ['dropdown_input'],
                create: false,
                render: {
                    no_results: function(data, escape) {
                        return '<div class="no-results">No results found for "' + escape(data.input) + '"</div>';
                    }
                }
            };

            new TomSelect('#country_state', {
                ...config,
                placeholder: 'Select a country / state...',
                maxOptions: 1000,
                sortField: { field: "text", direction: "asc" }
            });
            new TomSelect('#shop_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#cart_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#checkout_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#selling_specific_countries', {
                ...config,
                plugins: ['dropdown_input', 'remove_button'],
                placeholder: 'Select specific countries...',
                maxOptions: 1000
            });
            new TomSelect('#selling_except_countries', {
                ...config,
                plugins: ['dropdown_input', 'remove_button'],
                placeholder: 'Select countries to exclude...',
                maxOptions: 1000
            });
            new TomSelect('#shipping_specific_countries', {
                ...config,
                plugins: ['dropdown_input', 'remove_button'],
                placeholder: 'Select specific countries...',
                maxOptions: 1000
            });
            new TomSelect('#shop_currency', {
                ...config,
                placeholder: 'Select a currency...',
                maxOptions: 500
            });
        });
    </script>
    @endpush
</x-cms-dashboard::layouts.admin>
