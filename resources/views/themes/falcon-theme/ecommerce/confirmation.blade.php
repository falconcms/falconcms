@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', 'Order Confirmation')

@section('content')
    <?php do_falcon_action('lazy_before_order_confirmation', $order); ?>
    <div class="bg-gray-50 py-20 min-h-screen font-sans">
        <div class="container-custom">
            <div class="bg-white rounded-sm shadow-sm border border-gray-100 p-10 text-center mb-10">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-heading mb-2">Thank you for your order!</h1>
                <p class="text-body text-lg mb-6">Your order has been received and is now being processed.</p>
                <a href="{{ route('shop.track', ['order_number' => $order->order_number, 'email' => $order->customer_email]) }}"
                   class="inline-flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-sm font-bold text-sm hover:bg-primary-hover transition uppercase">
                    <i data-lucide="truck" class="w-4 h-4"></i> Track your order
                </a>
            </div>

            <div class="bg-white rounded-sm shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 bg-gray-50 border-b border-gray-100 flex flex-wrap justify-between gap-6">
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Order
                            Number</span>
                        <strong class="text-heading">{{ $order->order_number }}</strong>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Date</span>
                        <strong class="text-heading">{{ cms_date($order->created_at, 'M d, Y') }}</strong>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total</span>
                        <strong class="text-primary font-bold">{{ falcon_price_format($order->total, $order) }}</strong>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Payment
                            Method</span>
                        @php
                            $orderGateways = lazy_enabled_payment_gateways();
                            $pmLabel =
                                $orderGateways[$order->payment_method]['title'] ??
                                ucwords(str_replace('_', ' ', $order->payment_method ?: 'N/A'));
                        @endphp
                        <strong class="text-heading">{{ $pmLabel }}</strong>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Payment
                            Status</span>
                        @if ($order->paid_at)
                            <strong class="text-green-600">Paid</strong>
                        @else
                            <strong class="text-amber-600">Awaiting Payment</strong>
                        @endif
                    </div>
                </div>

                {{-- Bank transfer instructions --}}
                @if ($order->payment_method === 'bank' && get_shop_option('shop_payment_bank_details'))
                    <div class="px-8 pt-6">
                        <div class="bg-blue-50 border border-blue-100 rounded-sm p-5">
                            <h3 class="text-sm font-bold text-heading mb-2 uppercase tracking-wide">Bank Transfer Details
                            </h3>
                            <p class="text-[13px] text-body whitespace-pre-line leading-relaxed">
                                {{ get_shop_option('shop_payment_bank_details') }}</p>
                            <p class="text-[12px] text-gray-500 mt-3">Please use your order number
                                <strong>{{ $order->order_number }}</strong> as the payment reference.</p>
                        </div>
                    </div>
                @endif

                <div class="p-8">
                    <h2 class="text-xl font-bold text-heading mb-6 border-b border-gray-100 pb-4 uppercase tracking-tight">
                        Order Details</h2>

                    <table class="w-full text-left mb-8 text-[14px]">
                        <thead>
                            <tr class="text-gray-500 border-b border-gray-100">
                                <th class="pb-3 font-bold uppercase tracking-wider">Product</th>
                                <th class="pb-3 font-bold text-right uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="text-body">
                            @foreach ($order->items as $item)
                                <tr class="border-b border-gray-50">
                                    <td class="py-4">
                                        {!! apply_falcon_filters('lazy_order_confirmation_item_name',
                                            e($item->product_name) . ' <strong class="text-heading">× ' . (int)$item->quantity . '</strong>',
                                            $item, $order) !!}
                                        {!! lazy_render_item_custom_fields($item, 'confirmation') !!}
                                        <?php do_falcon_action('lazy_order_confirmation_item_meta', $item, $order); ?>
                                    </td>
                                    <td class="py-4 text-right font-medium">
                                        {{ falcon_price_format($item->subtotal, $order) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="text-body">
                            <tr>
                                <td class="pt-6 pb-3 font-bold">Subtotal:</td>
                                <td class="pt-6 pb-3 text-right font-bold">{{ falcon_price_format($order->subtotal, $order) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 font-bold">Shipping:</td>
                                <td class="py-3 text-right font-bold">
                                    {{ falcon_price_format($order->shipping_total, $order) }}</td>
                            </tr>
                            @if ($order->tax_total > 0)
                                <tr>
                                    <td class="py-3 font-bold">Tax:</td>
                                    <td class="py-3 text-right font-bold">
                                        {{ falcon_price_format($order->tax_total, $order) }}</td>
                                </tr>
                            @endif
                            @if ($order->coupon_code)
                                <tr>
                                    <td class="py-3 font-bold text-emerald-700 text-sm">Coupons
                                        ({{ $order->coupon_code }}):</td>
                                    <td class="py-3 text-right font-bold text-emerald-700">
                                        {{ falcon_price_format($order->discount_total, $order) }}</td>
                                </tr>
                            @endif
                            <tr class="text-heading text-lg border-t border-gray-100">
                                <td class="pt-4 font-black">Total:</td>
                                <td class="pt-4 text-right font-black text-primary">
                                    {{ falcon_price_format($order->total, $order) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mt-10">
                        <div>
                            <h3
                                class="text-lg font-bold text-heading mb-4 border-b border-gray-100 pb-2 uppercase tracking-tight">
                                Billing Address</h3>
                            <address class="not-italic text-body leading-relaxed">
                                {{ $order->first_name }} {{ $order->last_name }}<br>
                                {{ $order->address_line_1 }}<br>
                                @if ($order->address_line_2)
                                    {{ $order->address_line_2 }}<br>
                                @endif
                                {{ $order->city }}, {{ $order->state }} {{ $order->postcode }}<br>
                                {{ $order->country }}<br>
                                <div class="mt-4 text-sm">
                                    <span class="block mb-1"><strong class="text-heading">Phone:</strong>
                                        {{ $order->customer_phone }}</span>
                                    <span class="block"><strong class="text-heading">Email:</strong>
                                        {{ $order->customer_email }}</span>
                                </div>
                            </address>
                            @php $confCheckoutMeta = $order->meta['checkout_fields'] ?? []; @endphp
                            @if(!empty($confCheckoutMeta))
                                @php $confLabels = apply_falcon_filters('lazy_checkout_field_labels', []); @endphp
                                <div class="mt-4 pt-4 border-t border-gray-100 space-y-1 text-sm text-body">
                                    @foreach($confCheckoutMeta as $cKey => $cVal)
                                        @if($cVal)
                                        <div>
                                            <strong class="text-heading">{{ $confLabels[$cKey] ?? ucwords(str_replace('_', ' ', $cKey)) }}:</strong>
                                            {{ $cVal }}
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($order->shipping_address_line_1)
                            <div>
                                <h3
                                    class="text-lg font-bold text-heading mb-4 border-b border-gray-100 pb-2 uppercase tracking-tight">
                                    Shipping Address</h3>
                                <address class="not-italic text-body leading-relaxed">
                                    {{ $order->shipping_first_name }} {{ $order->shipping_last_name }}<br>
                                    {{ $order->shipping_address_line_1 }}<br>
                                    @if ($order->shipping_address_line_2)
                                        {{ $order->shipping_address_line_2 }}<br>
                                    @endif
                                    {{ $order->shipping_city }}, {{ $order->shipping_state }}
                                    {{ $order->shipping_postcode }}<br>
                                    {{ $order->shipping_country }}
                                </address>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Order Timeline ────────────────────────────────────────────── --}}
            @php $timeline = $order->statusHistory; @endphp
            @if($timeline->isNotEmpty())
            <div class="bg-white rounded-sm shadow-sm border border-gray-100 mt-6 p-8">
                <h2 class="text-xl font-bold text-heading mb-8 border-b border-gray-100 pb-4">Timeline</h2>

                <div class="space-y-0">
                @foreach($timeline as $entry)
                    @php
                        $isLast  = $loop->last;
                        $isBad   = in_array($entry->status, ['cancelled', 'failed']);
                        $label   = \FalconCms\Core\Models\OrderStatusHistory::label($entry->status);
                        $note    = $entry->note ?: \FalconCms\Core\Models\OrderStatusHistory::defaultNote($entry->status);
                        $showTrack = in_array($entry->status, ['delivering', 'shipped'])
                                   && ($order->tracking_number || $order->tracking_url);
                        $trackUrl  = $order->tracking_url
                                   ?: route('shop.track', ['order_number' => $order->order_number, 'email' => $order->customer_email]);
                    @endphp
                    <div class="flex items-start gap-0 {{ $isLast ? '' : 'mb-0' }}">

                        {{-- Date column --}}
                        <div class="hidden sm:flex flex-col items-end justify-start w-36 flex-shrink-0 pt-0.5 pr-4">
                            <span class="text-[13px] text-gray-500 font-medium leading-tight">{{ cms_date($entry->created_at, 'd M Y') }}</span>
                            <span class="text-[12px] text-gray-400 mt-0.5">{{ cms_date($entry->created_at, 'h:i a') }}</span>
                        </div>

                        {{-- Icon + connecting line --}}
                        <div class="flex flex-col items-center flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 z-10"
                                 style="background-color: {{ $isBad ? '#ef4444' : get_cms_option('theme_primary_color','#0d9488') }}">
                                @if($isBad)
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </div>
                            @if(!$isLast)
                                <div class="w-0.5 flex-1 min-h-[48px]" style="background-color: {{ get_cms_option('theme_primary_color','#0d9488') }}; opacity:.35"></div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 pl-4 {{ $isLast ? 'pb-0' : 'pb-8' }} pt-0.5">
                            <span class="sm:hidden text-[11px] text-gray-400 block mb-0.5">{{ cms_date($entry->created_at, 'd M Y, h:i a') }}</span>
                            <p class="font-bold text-heading text-[15px] leading-snug">{{ $label }}</p>
                            <p class="text-[14px] text-body mt-1 leading-relaxed">{{ $note }}</p>
                            @if($showTrack)
                                <a href="{{ $trackUrl }}" target="_blank"
                                   class="inline-block mt-1.5 text-[14px] font-semibold underline underline-offset-2 hover:opacity-75"
                                   style="color: {{ get_cms_option('theme_primary_color','#0d9488') }}">
                                    Track Order
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>
    <?php do_falcon_action('lazy_after_order_confirmation', $order); ?>
@stop
