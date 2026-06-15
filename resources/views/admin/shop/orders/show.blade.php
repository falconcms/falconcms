<x-falcon-cms::layouts.admin title="Order #{{ $order->order_number ?: $order->id }}" active-menu="shop">
    <div class="flex justify-between items-center mb-5">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.shop.orders.index') }}" class="wp-btn-secondary h-8 px-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <h1 class="text-[23px] font-normal text-[#1d2327]">Order #{{ $order->order_number ?: $order->id }}</h1>
            <span class="text-[#646970] text-[13px] mt-1">{{ cms_date($order->created_at, 'M d, Y \a\t H:i') }}</span>
        </div>
        <div class="flex items-center space-x-2">
            @if(in_array($order->status, ['completed', 'partially-refunded']))
                <a href="{{ route('admin.shop.orders.invoice', $order->id) }}" target="_blank" class="wp-btn-secondary h-8 flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    <span>Print Invoice</span>
                </a>
            @else
                <span class="text-[12px] text-[#646970] italic">Invoice available after the order is completed.</span>
            @endif
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
                                                <?php do_falcon_action('lazy_admin_order_item_meta', $item); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="wp-table-cell text-center">{{ falcon_price_format($item->price, $order) }}</td>
                                    <td class="wp-table-cell text-center">× {{ $item->quantity }}</td>
                                    <td class="wp-table-cell text-right font-semibold">{{ falcon_price_format($item->subtotal, $order) }}</td>
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
                                <td class="px-3 py-2 text-right font-semibold">{{ falcon_price_format($order->subtotal, $order) }}</td>
                            </tr>
                            @if($order->shipping_total > 0)
                                <tr>
                                    <td class="px-3 py-2 text-right text-[#646970]">Shipping:</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ falcon_price_format($order->shipping_total, $order) }}</td>
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
                                    <td class="px-3 py-2 text-right font-semibold">{{ falcon_price_format($order->tax_total, $order) }}</td>
                                </tr>
                            @endif
                            @if($order->coupon_code)
                                <tr>
                                    <td class="px-3 py-2 text-right text-emerald-700 font-bold">Coupons ({{ $order->coupon_code }}):</td>
                                    <td class="px-3 py-2 text-right font-bold text-emerald-700">-{{ falcon_price_format($order->discount_total, $order) }}</td>
                                </tr>
                            @endif
                            <tr class="bg-[#f6f7f7]">
                                <td class="px-3 py-3 text-right font-bold text-[15px]">Total:</td>
                                <td class="px-3 py-3 text-right font-bold text-[18px] text-[#2271b1]">{{ falcon_price_format($order->total, $order) }}</td>
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
                                <option value="pending"            {{ $order->status === 'pending'            ? 'selected' : '' }}>Pending</option>
                                <option value="processing"         {{ $order->status === 'processing'         ? 'selected' : '' }}>Processing</option>
                                <option value="confirmed"          {{ $order->status === 'confirmed'          ? 'selected' : '' }}>Confirmed</option>
                                <option value="packing"            {{ $order->status === 'packing'            ? 'selected' : '' }}>Packing</option>
                                <option value="packed"             {{ $order->status === 'packed'             ? 'selected' : '' }}>Packed</option>
                                <option value="delivering"         {{ $order->status === 'delivering'         ? 'selected' : '' }}>Delivering</option>
                                <option value="delivered"          {{ $order->status === 'delivered'          ? 'selected' : '' }}>Delivered</option>
                                <option value="completed"          {{ $order->status === 'completed'          ? 'selected' : '' }}>Completed</option>
                                <option value="on-hold"            {{ $order->status === 'on-hold'            ? 'selected' : '' }}>On Hold</option>
                                <option value="cancelled"          {{ $order->status === 'cancelled'          ? 'selected' : '' }}>Cancelled</option>
                                <option value="partially-refunded" {{ $order->status === 'partially-refunded' ? 'selected' : '' }}>Partially Refunded</option>
                                <option value="refunded"           {{ $order->status === 'refunded'           ? 'selected' : '' }}>Refunded</option>
                                <option value="failed"             {{ $order->status === 'failed'             ? 'selected' : '' }}>Failed</option>
                            </select>
                            @if($order->payment_method === 'stripe' && $order->paid_at)
                                <p class="text-[11px] text-[#646970] mt-1.5 leading-relaxed">
                                    <span class="material-symbols-outlined align-middle" style="font-size:13px !important;">info</span>
                                    Setting this to <strong>Refunded</strong> will automatically refund the payment via Stripe.
                                </p>
                            @endif
                        </div>

                        <div class="mb-4 pt-4 border-t border-[#f0f0f1]">
                            <label class="block text-[13px] font-semibold mb-2">Shipment Tracking</label>

                            <label class="block text-[11px] text-[#646970] mb-1">Carrier</label>
                            <select name="tracking_carrier" id="trk-carrier" class="wp-input w-full mb-3 text-[13px]">
                                <option value="">— Select carrier —</option>
                                @foreach(lazy_shipping_carriers() as $group => $carriers)
                                    <optgroup label="{{ $group }}">
                                        @foreach($carriers as $name => $tpl)
                                            <option value="{{ $name }}" data-url="{{ $tpl }}" {{ $order->tracking_carrier === $name ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>

                            <label class="block text-[11px] text-[#646970] mb-1">Tracking Number</label>
                            <div class="flex gap-2 mb-3">
                                <input type="text" name="tracking_number" id="trk-number" value="{{ $order->tracking_number }}" placeholder="Tracking number" class="wp-input flex-1 text-[13px]">
                                <button type="button" id="trk-generate" class="wp-btn-secondary text-[12px] whitespace-nowrap px-2" title="Auto-generate a tracking number">Generate</button>
                            </div>

                            <label class="block text-[11px] text-[#646970] mb-1">Tracking URL</label>
                            <input type="url" name="tracking_url" id="trk-url" value="{{ $order->tracking_url }}" placeholder="Auto-filled from carrier (editable)" class="wp-input w-full text-[13px]">
                            <p class="text-[11px] text-[#646970] mt-1.5">Shown to the customer on the order tracking page.</p>
                        </div>

                        <div class="mb-4 pt-4 border-t border-[#f0f0f1]">
                            <label class="block text-[13px] font-semibold mb-1">Timeline Note <span class="font-normal text-[#646970]">(optional)</span></label>
                            <textarea name="timeline_note" rows="2" placeholder="Add a message shown on the customer's order timeline…" class="wp-input w-full text-[13px] resize-none"></textarea>
                            <p class="text-[11px] text-[#646970] mt-1">Visible to the customer on their order page.</p>
                        </div>

                        <button type="submit" class="wp-btn-primary w-full justify-center">Update Order</button>

                        @push('scripts')
                        <script>
                        (function () {
                            var orderNo = @json($order->order_number ?: ('ORD' . $order->id));
                            var carrier = document.getElementById('trk-carrier');
                            var number  = document.getElementById('trk-number');
                            var urlIn   = document.getElementById('trk-url');
                            var genBtn  = document.getElementById('trk-generate');
                            if (!carrier || !number || !urlIn) return;

                            var fallbackTpl = 'https://www.17track.net/en/track?nums={tracking}';

                            function carrierTpl() {
                                var opt = carrier.options[carrier.selectedIndex];
                                var t = opt ? (opt.getAttribute('data-url') || '') : '';
                                return t || fallbackTpl;
                            }
                            function buildUrl() {
                                if (!number.value.trim()) return '';
                                return carrierTpl().replace('{tracking}', encodeURIComponent(number.value.trim()));
                            }
                            function genNumber() {
                                var base = (orderNo || 'ORD').replace(/[^A-Za-z0-9]/g, '').slice(-6).toUpperCase();
                                var rand = Math.random().toString(36).slice(2, 7).toUpperCase();
                                return 'TRK-' + base + '-' + rand;
                            }

                            genBtn && genBtn.addEventListener('click', function () {
                                number.value = genNumber();
                                if (carrier.value) urlIn.value = buildUrl();
                            });
                            // When carrier changes, refresh the URL from its template if a number exists.
                            carrier.addEventListener('change', function () {
                                if (number.value.trim()) urlIn.value = buildUrl();
                            });
                        })();
                        </script>
                        @endpush
                    </form>
                </div>
            </div>

            {{-- Refund (Stripe paid orders only) --}}
            @if($order->payment_method === 'stripe' && $order->paid_at)
                @php
                    $refunded  = (float) ($order->refunded_amount ?? 0);
                    $remaining = max(0, (float) $order->total - $refunded);
                @endphp
                <div class="wp-metabox">
                    <div class="wp-metabox-header">Refund (Stripe)</div>
                    <div class="wp-metabox-content">
                        <div class="text-[12px] text-[#3c434a] space-y-1 mb-3">
                            <div class="flex justify-between"><span>Order total</span><strong>{{ falcon_price_format($order->total, $order) }}</strong></div>
                            <div class="flex justify-between"><span>Refunded</span><strong class="text-[#646970]">{{ falcon_price_format($refunded, $order) }}</strong></div>
                            <div class="flex justify-between"><span>Remaining</span><strong class="text-[#2271b1]">{{ falcon_price_format($remaining, $order) }}</strong></div>
                        </div>
                        @if($remaining > 0)
                            <form action="{{ route('admin.shop.orders.refund', $order->id) }}" method="POST"
                                  onsubmit="return confirm('Refund this amount via Stripe? This cannot be undone.');">
                                @csrf
                                <label class="block text-[12px] font-semibold mb-1">Amount to refund</label>
                                <input type="number" name="refund_amount" step="0.01" min="0.01" max="{{ $remaining }}"
                                       value="{{ number_format($remaining, 2, '.', '') }}"
                                       class="wp-input w-full mb-1">
                                <p class="text-[11px] text-[#646970] mb-3">Max {{ falcon_price_format($remaining, $order) }}. Leave full amount for a complete refund.</p>
                                <button type="submit" class="wp-btn-secondary w-full justify-center border-[#d63638] text-[#d63638] hover:bg-[#d63638] hover:text-white">Refund via Stripe</button>
                            </form>
                        @else
                            <p class="text-[12px] text-emerald-600 font-semibold">Fully refunded.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Refund History --}}
            @if(($order->refunded_amount ?? 0) > 0)
                <div class="wp-metabox">
                    <div class="wp-metabox-header">Refund History</div>
                    <div class="wp-metabox-content">
                        @php $refundLog = is_array($order->refund_log) ? $order->refund_log : []; @endphp
                        @if(!empty($refundLog))
                            <div class="space-y-3">
                                @foreach(array_reverse($refundLog) as $entry)
                                    <div class="flex items-start justify-between gap-3 pb-3 border-b border-[#f0f0f1] last:border-0 last:pb-0">
                                        <div>
                                            <div class="text-[13px] font-bold text-[#8c44db]">{{ falcon_price_format($entry['amount'] ?? 0, $order) }}</div>
                                            <div class="text-[11px] text-[#646970]">
                                                {{ cms_date($entry['at'] ?? now(), 'M d, Y · h:i A') }}
                                            </div>
                                            <div class="text-[10px] text-[#a7aaad]">
                                                via {{ ucfirst($entry['gateway'] ?? 'manual') }} · by {{ $entry['by'] ?? 'Admin' }}
                                            </div>
                                        </div>
                                        <span class="material-symbols-outlined text-[18px] text-[#8c44db]">currency_exchange</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-3 pt-3 border-t border-[#dcdcde] flex justify-between text-[13px]">
                                <span class="font-semibold text-[#3c434a]">Total refunded</span>
                                <strong class="text-[#8c44db]">{{ falcon_price_format($order->refunded_amount, $order) }}</strong>
                            </div>
                        @else
                            {{-- Legacy refunds with no per-entry log --}}
                            <div class="flex justify-between text-[13px]">
                                <span class="font-semibold text-[#3c434a]">Total refunded</span>
                                <strong class="text-[#8c44db]">{{ falcon_price_format($order->refunded_amount, $order) }}</strong>
                            </div>
                            <p class="text-[11px] text-[#a7aaad] mt-1">Detailed refund log not available for this order.</p>
                        @endif
                    </div>
                </div>
            @endif

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

                        @php $checkoutMeta = $order->meta['checkout_fields'] ?? []; @endphp
                        @if(!empty($checkoutMeta))
                            @php $coLabels = apply_falcon_filters('lazy_checkout_field_labels', []); @endphp
                            <div class="pt-3 border-t border-[#f0f0f1]">
                                <div class="text-[11px] font-bold uppercase text-[#8c8f94] mb-1.5">Additional Info</div>
                                <div class="space-y-1">
                                    @foreach($checkoutMeta as $coKey => $coVal)
                                        @if($coVal)
                                        <div class="text-[13px]">
                                            <span class="font-semibold text-[#1d2327]">
                                                {{ $coLabels[$coKey] ?? ucwords(str_replace('_', ' ', $coKey)) }}:
                                            </span>
                                            {{ $coVal }}
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
