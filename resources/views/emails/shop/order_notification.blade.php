<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 40px 20px;
        }
        .container {
            max-width: 620px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0284c7 0%, #0091ea 100%);
            color: #ffffff;
            padding: 35px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 15px;
        }
        .content {
            padding: 35px 35px 25px 35px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        .status-pending { background-color: #fef3c7; color: #d97706; }
        .status-processing { background-color: #e0e7ff; color: #4f46e5; }
        .status-completed { background-color: #d1fae5; color: #059669; }
        .status-cancelled { background-color: #fee2e2; color: #dc2626; }
        .status-default { background-color: #f1f5f9; color: #64748b; }

        .order-meta {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
        }
        .order-meta table {
            width: 100%;
            font-size: 14px;
        }
        .order-meta td {
            padding: 4px 0;
        }
        .order-meta .label {
            color: #64748b;
            font-weight: 600;
        }
        .order-meta .value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f8fafc;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .items-table th.text-right { text-align: right; }
        .items-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14.5px;
        }
        .items-table td.text-right { text-align: right; }
        .product-name { font-weight: 600; color: #0f172a; margin: 0 0 4px 0; }
        .product-qty { color: #64748b; font-size: 13.5px; }
        
        .totals-table {
            width: 100%;
            margin-bottom: 35px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 16px;
            font-size: 14.5px;
            color: #475569;
        }
        .totals-table tr.grand-total td {
            border-top: 2px solid #cbd5e1;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            padding-top: 14px;
        }
        .totals-table td.text-right { text-align: right; font-weight: 600; }

        .addresses {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 30px;
        }
        .address-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .address-box h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin: 0 0 10px 0;
        }
        .address-content {
            font-size: 14px;
            color: #334155;
            line-height: 1.7;
        }

        .customer-note {
            background-color: #f1f5f9;
            border-left: 4px solid #0091ea;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .customer-note h4 { margin: 0 0 6px 0; color: #0f172a; font-size: 14px; }

        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
        .footer a {
            color: #0091ea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

@php
    $shopName = get_cms_option('site_name', get_shop_option('shop_store_name', 'Lazy Shop'));
    $shopEmail = get_cms_option('site_email', 'support@' . request()->getHost());
@endphp

<div class="wrapper">
    <div class="container">
        <!-- Header Image (if any) -->
        @if(get_shop_option('shop_email_header_image'))
        <div style="text-align: center; background: #fff; padding: 25px 20px 10px 20px;">
            <img src="{{ get_shop_option('shop_email_header_image') }}" alt="{{ $shopName }}" style="max-height: 80px; max-width: 100%; height: auto;">
        </div>
        @endif

        <!-- Header -->
        <div class="header">
            <h1>{{ $shopName }}</h1>
            <p>{{ $notificationType === 'placed' ? 'Thank You For Your Order!' : 'Order Status Update' }}</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">Hi {{ $order->first_name }},</div>

            @if($notificationType === 'placed')
                <p style="margin-bottom: 25px;">We have received your order <strong>#{{ $order->order_number }}</strong> and are currently getting it ready. You will receive another notification once your order status updates.</p>
            @else
                <p style="margin-bottom: 20px;">Your order <strong>#{{ $order->order_number }}</strong> status has been updated to:</p>
                @php
                    $badgeClass = 'status-default';
                    if ($order->status === 'pending') $badgeClass = 'status-pending';
                    elseif ($order->status === 'processing') $badgeClass = 'status-processing';
                    elseif ($order->status === 'completed') $badgeClass = 'status-completed';
                    elseif (in_array($order->status, ['cancelled', 'refunded', 'failed'])) $badgeClass = 'status-cancelled';
                @endphp
                <div class="status-badge {{ $badgeClass }}">{{ ucfirst($order->status) }}</div>
                
                @if($order->status === 'completed')
                    <p style="margin-bottom: 25px; color: #059669; font-weight: 600;">Good news! Your order is completed and fulfilled. Thank you for shopping with us!</p>
                @elseif($order->status === 'processing')
                    <p style="margin-bottom: 25px;">We are actively preparing your items. We'll let you know once it's on its way.</p>
                @endif
            @endif

            <!-- Order Meta details -->
            <div class="order-meta">
                <table>
                    <tr>
                        <td class="label">Order Number:</td>
                        <td class="value">#{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Order Date:</td>
                        <td class="value">{{ $order->created_at->format('F j, Y, g:i a') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Payment Method:</td>
                        <td class="value">{{ strtoupper($order->payment_method) }}</td>
                    </tr>
                    @if($order->shipping_method)
                    <tr>
                        <td class="label">Shipping Method:</td>
                        <td class="value">{{ $order->shipping_method }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            @if($order->customer_note)
            <div class="customer-note">
                <h4>Customer Note:</h4>
                <p style="margin: 0;">{{ $order->customer_note }}</p>
            </div>
            @endif

            <h3 style="font-size: 16px; color: #0f172a; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Order Details</h3>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right" style="width: 120px;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <div class="product-name">{{ $item->product_name }}</div>
                                <div class="product-qty">Qty: {{ $item->quantity }}</div>
                            </td>
                            <td class="text-right">
                                @php
                                    $symbol = $order->currency_symbol ?? '$';
                                    $subtotal = number_format($item->subtotal, $order->decimals ?? 2, $order->decimal_separator ?? '.', $order->thousand_separator ?? ',');
                                @endphp
                                <strong>{{ ($order->currency_position === 'right') ? $subtotal . $symbol : $symbol . $subtotal }}</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Totals Table -->
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">
                        @php
                            $symbol = $order->currency_symbol ?? '$';
                            $subtotalVal = number_format($order->subtotal, $order->decimals ?? 2, $order->decimal_separator ?? '.', $order->thousand_separator ?? ',');
                        @endphp
                        {{ ($order->currency_position === 'right') ? $subtotalVal . $symbol : $symbol . $subtotalVal }}
                    </td>
                </tr>
                
                @if($order->discount_total > 0)
                <tr>
                    <td style="color: #059669;">Discount @if($order->coupon_code) ({{ $order->coupon_code }}) @endif</td>
                    <td class="text-right" style="color: #059669;">
                        @php
                            $discountVal = number_format($order->discount_total, $order->decimals ?? 2, $order->decimal_separator ?? '.', $order->thousand_separator ?? ',');
                        @endphp
                        -{{ ($order->currency_position === 'right') ? $discountVal . $symbol : $symbol . $discountVal }}
                    </td>
                </tr>
                @endif

                <tr>
                    <td>Shipping</td>
                    <td class="text-right">
                        @php
                            $shippingVal = number_format($order->shipping_total, $order->decimals ?? 2, $order->decimal_separator ?? '.', $order->thousand_separator ?? ',');
                        @endphp
                        {{ ($order->currency_position === 'right') ? $shippingVal . $symbol : $symbol . $shippingVal }}
                    </td>
                </tr>

                @if($order->tax_total > 0)
                <tr>
                    <td>Tax</td>
                    <td class="text-right">
                        @php
                            $taxVal = number_format($order->tax_total, $order->decimals ?? 2, $order->decimal_separator ?? '.', $order->thousand_separator ?? ',');
                        @endphp
                        {{ ($order->currency_position === 'right') ? $taxVal . $symbol : $symbol . $taxVal }}
                    </td>
                </tr>
                @endif

                <tr class="grand-total">
                    <td>Total</td>
                    <td class="text-right text-emerald-600">
                        @php
                            $totalVal = number_format($order->total, $order->decimals ?? 2, $order->decimal_separator ?? '.', $order->thousand_separator ?? ',');
                        @endphp
                        {{ ($order->currency_position === 'right') ? $totalVal . $symbol : $symbol . $totalVal }}
                    </td>
                </tr>
            </table>

            <!-- Addresses side-by-side -->
            <div class="addresses">
                <div class="address-box">
                    <h3>Billing Address</h3>
                    <div class="address-content">
                        <strong>{{ $order->first_name }} {{ $order->last_name }}</strong><br>
                        {{ $order->address_line_1 }}<br>
                        @if($order->address_line_2) {{ $order->address_line_2 }}<br> @endif
                        {{ $order->city }}, {{ $order->state }} {{ $order->postcode }}<br>
                        {{ $order->country }}<br>
                        <span style="color: #64748b;">Phone: {{ $order->customer_phone }}</span><br>
                        <span style="color: #64748b;">Email: {{ $order->customer_email }}</span>
                    </div>
                </div>

                <div class="address-box" style="padding-left: 15px;">
                    <h3>Shipping Address</h3>
                    <div class="address-content">
                        @if($order->shipping_first_name)
                            <strong>{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</strong><br>
                            {{ $order->shipping_address_line_1 }}<br>
                            @if($order->shipping_address_line_2) {{ $order->shipping_address_line_2 }}<br> @endif
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postcode }}<br>
                            {{ $order->shipping_country }}
                        @else
                            <em style="color: #64748b;">Same as billing address</em>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 15px 0; font-weight: 600; color: #334155;">{{ get_shop_option('shop_email_footer_text', 'Thank you for shopping with us!') }}</p>
            <p style="margin: 0 0 10px 0;">If you have any questions or concerns regarding this order, please contact us at <a href="mailto:{{ $shopEmail }}">{{ $shopEmail }}</a>.</p>
            <p style="margin: 0; font-size: 12px; opacity: 0.8;">&copy; {{ date('Y') }} {{ $shopName }}. All rights reserved.</p>
        </div>
    </div>
</div>

</body>
</html>
