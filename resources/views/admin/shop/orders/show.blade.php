<x-cms-dashboard::layouts.admin title="Order #{{ $order->order_number ?: $order->id }}" active-menu="shop">
    <div class="flex justify-between items-center mb-5">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.shop.orders.index') }}" class="wp-btn-secondary h-8 px-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <h1 class="text-[23px] font-normal text-[#1d2327]">Order #{{ $order->order_number ?: $order->id }}</h1>
            <span class="text-[#646970] text-[13px] mt-1">{{ $order->created_at->format('M d, Y \a\t H:i') }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Left Column: Order Details -->
        <div class="lg:col-span-2 space-y-5">
            <!-- Order Items -->
            <div class="wp-metabox">
                <div class="wp-metabox-header">Order Items</div>
                <div class="wp-metabox-content p-0">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#f6f7f7]">
                                <th class="wp-table-header">Item</th>
                                <th class="wp-table-header text-center">Price</th>
                                <th class="wp-table-header text-center">Qty</th>
                                <th class="wp-table-header text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="wp-table-cell">
                                        <div class="flex items-center space-x-3">
                                            @if($item->product && $item->product->featured_image)
                                                <img src="{{ asset('storage/' . $item->product->featured_image) }}" class="w-10 h-10 object-cover rounded border border-[#c3c4c7]">
                                            @else
                                                <div class="w-10 h-10 bg-[#f0f0f1] border border-[#c3c4c7] rounded flex items-center justify-center">
                                                    <span class="material-symbols-outlined text-[#8c8f94] text-[20px]">image</span>
                                                </div>
                                            @endif
                                            <div>
                                                <a href="{{ route('admin.posts.edit', $item->product_id) }}" class="font-semibold text-[#2271b1] hover:underline">{{ $item->product_name }}</a>
                                                @if($item->variation_details)
                                                    <div class="text-[11px] text-[#646970]">{{ $item->variation_details }}</div>
                                                @endif
                                                <div class="text-[11px] text-[#646970]">SKU: {{ $item->product->sku ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="wp-table-cell text-center">{{ lazy_price_format($item->price, $order) }}</td>
                                    <td class="wp-table-cell text-center">× {{ $item->quantity }}</td>
                                    <td class="wp-table-cell text-right font-semibold">{{ lazy_price_format($item->subtotal, $order) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-[#c3c4c7]">
                                <td colspan="2" rowspan="4" class="px-4 py-3 align-top border-r border-[#c3c4c7]">
                                    @if($order->payment_method)
                                        <div class="text-[11px] font-bold uppercase text-[#8c8f94] mb-1">Payment Method</div>
                                        <div class="flex items-center text-[#1d2327]">
                                            <span class="material-symbols-outlined text-[18px] mr-1">payments</span>
                                            <span class="font-semibold">{{ strtoupper($order->payment_method) }}</span>
                                        </div>
                                    @endif

                                    @if(isset($order->shipping_method) && $order->shipping_method)
                                        <div class="text-[11px] font-bold uppercase text-[#8c8f94] mt-4 mb-1">Shipping Method</div>
                                        <div class="flex items-center text-[#1d2327]">
                                            <span class="material-symbols-outlined text-[18px] mr-1">local_shipping</span>
                                            <span class="font-semibold">{{ $order->shipping_method }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-[#646970]">Subtotal:</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ lazy_price_format($order->subtotal, $order) }}</td>
                            </tr>
                            @if($order->shipping_total > 0)
                                <tr>
                                    <td class="px-3 py-2 text-right text-[#646970]">Shipping:</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ lazy_price_format($order->shipping_total, $order) }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td class="px-3 py-2 text-right text-[#646970]">Shipping:</td>
                                    <td class="px-3 py-2 text-right font-semibold">Free</td>
                                </tr>
                            @endif
                            @if($order->tax_total > 0)
                                <tr>
                                    <td class="px-3 py-2 text-right text-[#646970]">Tax:</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ lazy_price_format($order->tax_total, $order) }}</td>
                                </tr>
                            @endif
                            @if($order->coupon_code)
                                <tr>
                                    <td class="px-3 py-2 text-right text-emerald-700 font-bold">Coupons ({{ $order->coupon_code }}):</td>
                                    <td class="px-3 py-2 text-right font-bold text-emerald-700">-{{ lazy_price_format($order->discount_total, $order) }}</td>
                                </tr>
                            @endif
                            <tr class="bg-[#f6f7f7]">
                                <td class="px-3 py-3 text-right font-bold text-[15px]">Total:</td>
                                <td class="px-3 py-3 text-right font-bold text-[18px] text-[#2271b1]">{{ lazy_price_format($order->total, $order) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Customer Notes / Order Notes -->
            <div class="wp-metabox">
                <div class="wp-metabox-header">Order Notes</div>
                <div class="wp-metabox-content">
                    <p class="text-[#1d2327] italic">{{ $order->customer_note ?: 'No notes from customer.' }}</p>
                </div>
            </div>
        </div>

        <!-- Right Column: Sidebar Info -->
        <div class="space-y-5">
            <!-- Actions -->
            <div class="wp-metabox">
                <div class="wp-metabox-header">Order Actions</div>
                <div class="wp-metabox-content">
                    <form action="{{ route('admin.shop.orders.status', $order->id) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-[13px] font-semibold mb-1">Status</label>
                            <select name="status" class="wp-input w-full">
                                <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Processing</option>
                                <option value="on-hold" {{ $order->status === 'on-hold' ? 'selected' : '' }}>On Hold</option>
                                <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                <option value="refunded" {{ $order->status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                                <option value="failed" {{ $order->status === 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                        </div>
                        <button type="submit" class="wp-btn-primary w-full justify-center">Update Status</button>
                    </form>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="wp-metabox">
                <div class="wp-metabox-header">Customer Details</div>
                <div class="wp-metabox-content">
                    <div class="flex items-center space-x-3 mb-4">
                        <img src="https://secure.gravatar.com/avatar/{{ md5(strtolower(trim($order->customer_email))) }}?s=40&d=mm&r=g" class="w-10 h-10 rounded">
                        <div>
                            <div class="font-bold">{{ $order->first_name }} {{ $order->last_name }}</div>
                            <div class="text-[12px] text-[#2271b1]">{{ $order->customer_email }}</div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase text-[#8c8f94]">Billing Address</div>
                            <div class="text-[13px] mt-1 leading-relaxed">
                                {{ $order->address_line_1 }}<br>
                                @if($order->address_line_2) {{ $order->address_line_2 }}<br> @endif
                                {{ $order->city }}, {{ $order->state }} {{ $order->postcode }}<br>
                                {{ $order->country }}
                            </div>
                            <div class="text-[13px] mt-1 font-semibold text-[#1d2327]">
                                <span class="material-symbols-outlined text-[16px] align-middle mr-1">call</span>
                                {{ $order->customer_phone }}
                            </div>
                        </div>

                        @if($order->shipping_address_line_1)
                            <div class="pt-3 border-t border-[#f0f0f1]">
                                <div class="text-[11px] font-bold uppercase text-[#8c8f94]">Shipping Address</div>
                                <div class="text-[13px] mt-1 leading-relaxed">
                                    {{ $order->shipping_first_name }} {{ $order->shipping_last_name }}<br>
                                    {{ $order->shipping_address_line_1 }}<br>
                                    @if($order->shipping_address_line_2) {{ $order->shipping_address_line_2 }}<br> @endif
                                    {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postcode }}<br>
                                    {{ $order->shipping_country }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-cms-dashboard::layouts.admin>
