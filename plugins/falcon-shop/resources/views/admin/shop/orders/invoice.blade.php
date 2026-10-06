<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $order->order_number ?: $order->id }} | {{ get_cms_option('site_title', 'FalconCMS') }}</title>
    <link rel="stylesheet" href="{{ falcon_plugin_asset('falcon-shop', 'admin/css/invoice.css') }}">
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <span>Print Invoice</span>
    </button>

    @php
        $shopName = get_shop_option('shop_store_name') ?: get_cms_option('site_name', 'Falcon');
        $refunded = (float) ($order->refunded_amount ?? 0);
        $isFullRefund    = $order->status === 'refunded' || ($refunded > 0 && $refunded >= (float) $order->total - 0.001);
        $isPartialRefund = !$isFullRefund && $refunded > 0;
        $isCancelled     = $order->status === 'cancelled';
        $isFailed        = $order->status === 'failed';
        // For a fully-refunded order treat the whole total as refunded, even if no per-refund amount was recorded.
        $effectiveRefunded = $isFullRefund ? (float) $order->total : $refunded;

        $stampText = null;
        if ($isFullRefund)        { $badgeClass = 'refunded';  $badgeText = 'Refunded';           $stampText = 'Refunded'; }
        elseif ($isCancelled)     { $badgeClass = 'cancelled'; $badgeText = 'Cancelled';          $stampText = 'Cancelled'; }
        elseif ($isFailed)        { $badgeClass = 'failed';    $badgeText = 'Failed';             $stampText = 'Failed'; }
        elseif ($isPartialRefund) { $badgeClass = 'partial';   $badgeText = 'Partially Refunded'; }
        elseif ($order->paid_at || $order->status === 'completed') { $badgeClass = 'paid'; $badgeText = 'Paid'; }
        else                      { $badgeClass = 'pending';   $badgeText = 'Payment Pending'; }
    @endphp
    <div class="invoice-container">
        @if($stampText)
            <div class="inv-stamp">{{ $stampText }}</div>
        @endif
        <header>
            <div class="logo-section">
                <h1>{{ $shopName }}</h1>
                <p style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                    {{ get_shop_option('shop_address_line_1') }}<br>
                    {{ get_shop_option('shop_city') }}, {{ get_shop_option('shop_postcode') }}
                </p>
            </div>
            <div class="invoice-details">
                <h2>{{ apply_falcon_filters('falcon_invoice_title', 'Invoice', $order) }}</h2>
                <p>#{{ $order->order_number ?: $order->id }}</p>
                <p>Date: {{ cms_date($order->created_at, 'M d, Y') }}</p>
                <span class="inv-badge {{ $badgeClass }}">{{ $badgeText }}</span>
            </div>
        </header>

        <div class="address-section">
            <div class="address-box">
                <h3>Billing To</h3>
                <p><strong>{{ $order->first_name }} {{ $order->last_name }}</strong></p>
                <p>{{ $order->address_line_1 }}</p>
                @if($order->address_line_2) <p>{{ $order->address_line_2 }}</p> @endif
                <p>{{ $order->city }}, {{ $order->state }} {{ $order->postcode }}</p>
                <p>{{ $order->country }}</p>
                <p>Email: {{ $order->customer_email }}</p>
                <p>Phone: {{ $order->customer_phone }}</p>
                @php
                    $invCheckoutMeta = $order->meta['checkout_fields'] ?? [];
                    $invCoLabels     = apply_falcon_filters('falcon_checkout_field_labels', [], 'invoice');
                @endphp
                @foreach($invCheckoutMeta as $iKey => $iVal)
                    @if($iVal)
                    <p style="margin-top:4px">
                        <strong>{{ $invCoLabels[$iKey] ?? ucwords(str_replace('_', ' ', $iKey)) }}:</strong>
                        {{ $iVal }}
                    </p>
                    @endif
                @endforeach
            </div>
            @if($order->shipping_address_line_1)
            <div class="address-box">
                <h3>Shipping To</h3>
                <p><strong>{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</strong></p>
                <p>{{ $order->shipping_address_line_1 }}</p>
                @if($order->shipping_address_line_2) <p>{{ $order->shipping_address_line_2 }}</p> @endif
                <p>{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postcode }}</p>
                <p>{{ $order->shipping_country }}</p>
            </div>
            @endif
        </div>

        <table class="order-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: center;">Price</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $invoiceLabels = apply_falcon_filters('falcon_custom_field_labels', [], 'invoice'); @endphp
                @foreach($order->items as $item)
                <tr>
                    <td>
                        <div style="font-weight: 600;">{{ $item->product_name }}</div>
                        @if($item->variation_details)
                            <div style="font-size: 11px; color: #6b7280;">{{ $item->variation_details }}</div>
                        @endif
                        @foreach($item->meta['custom_fields'] ?? [] as $cfKey => $cfVal)
                            @if($cfVal)
                            <div style="font-size: 11px; color: #6b7280;">
                                {{ $invoiceLabels[$cfKey] ?? ucwords(str_replace('_', ' ', $cfKey)) }}: {{ $cfVal }}
                            </div>
                            @endif
                        @endforeach
                    </td>
                    <td style="text-align: center;">{{ falcon_price_format($item->price, $order) }}</td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">{{ falcon_price_format($item->subtotal, $order) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td>{{ falcon_price_format($order->subtotal, $order) }}</td>
                </tr>
                <tr>
                    <td>Shipping ({{ $order->shipping_method ?: 'Standard' }})</td>
                    <td>{{ $order->shipping_total > 0 ? falcon_price_format($order->shipping_total, $order) : 'Free' }}</td>
                </tr>
                @if($order->tax_total > 0)
                <tr>
                    <td>Tax</td>
                    <td>{{ falcon_price_format($order->tax_total, $order) }}</td>
                </tr>
                @endif
                {{-- Itemised so an invoice states which coupon or promotion produced the discount. --}}
                @foreach(falcon_order_discount_lines($order) as $line)
                <tr style="color: #059669;">
                    <td>{{ $line['label'] }}@if($line['note'])<br><span style="font-size:11px;opacity:.75">{{ $line['note'] }}</span>@endif</td>
                    <td>-{{ falcon_price_format($line['amount'], $order) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total</td>
                    <td>{{ falcon_price_format($order->total, $order) }}</td>
                </tr>
                @if($effectiveRefunded > 0)
                @php $netTotal = max(0, (float) $order->total - $effectiveRefunded); @endphp
                <tr style="color: #b91c1c;">
                    <td>Refunded</td>
                    <td>-{{ falcon_price_format($effectiveRefunded, $order) }}</td>
                </tr>
                <tr style="font-weight: 700;">
                    <td style="padding-top: 10px;">{{ $isFullRefund ? 'Amount Due' : 'Net Total' }}</td>
                    <td style="padding-top: 10px;">{{ falcon_price_format($netTotal, $order) }}</td>
                </tr>
                @endif
            </table>
        </div>

        @if($order->customer_note)
        <div style="margin-top: 40px; padding: 20px; background: #f9fafb; border-radius: 8px;">
            <h3 style="font-size: 12px; text-transform: uppercase; color: #9ca3af; margin-top: 0;">Customer Note</h3>
            <p style="font-size: 14px; margin: 0; font-style: italic;">"{{ $order->customer_note }}"</p>
        </div>
        @endif

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>&copy; {{ date('Y') }} {{ $shopName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
