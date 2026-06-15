@extends('falcon-cms::themes.lazy-theme.layouts.app')

@section('title', 'Order Confirmation')

@section('content')
<div class="bg-gray-50 py-20 min-h-screen">
    <div class="container-custom">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-10 text-center mb-10">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Thank you for your order!</h1>
            <p class="text-gray-600 text-lg">Your order has been received and is now being processed.</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 bg-gray-50 border-b border-gray-100 flex flex-wrap justify-between gap-6">
                <div>
                    <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Order Number</span>
                    <strong class="text-gray-900">{{ $order->order_number }}</strong>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Date</span>
                    <strong class="text-gray-900">{{ cms_date($order->created_at, 'M d, Y') }}</strong>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total</span>
                    <strong class="text-blue-600 font-bold">{{ falcon_price_format($order->total, $order) }}</strong>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Payment Method</span>
                    <strong class="text-gray-900">Cash on Delivery</strong>
                </div>
            </div>

            <div class="p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6 border-b border-gray-100 pb-4">Order Details</h2>
                
                <table class="w-full text-left mb-8">
                    <thead>
                        <tr class="text-gray-500 text-sm border-b border-gray-100">
                            <th class="pb-3 font-semibold">Product</th>
                            <th class="pb-3 font-semibold text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800">
                        @foreach($order->items as $item)
                        <tr class="border-b border-gray-50">
                            <td class="py-4">
                                {{ $item->product_name }} <strong class="text-gray-900">× {{ $item->quantity }}</strong>
                            </td>
                            <td class="py-4 text-right font-medium">
                                {{ falcon_price_format($item->subtotal, $order) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="text-gray-600 text-sm">
                        <tr>
                            <td class="pt-6 pb-3 font-semibold">Subtotal:</td>
                            <td class="pt-6 pb-3 text-right font-medium">{{ falcon_price_format($order->subtotal, $order) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3 font-semibold">Shipping:</td>
                            <td class="py-3 text-right font-medium">{{ falcon_price_format($order->shipping_total, $order) }}</td>
                        </tr>
                        @if($order->tax_total > 0)
                        <tr>
                            <td class="py-3 font-semibold">Tax:</td>
                            <td class="py-3 text-right font-medium">{{ falcon_price_format($order->tax_total, $order) }}</td>
                        </tr>
                        @endif
                        <tr class="text-gray-900 text-lg border-t border-gray-100">
                            <td class="pt-4 font-bold">Total:</td>
                            <td class="pt-4 text-right font-bold text-blue-600">{{ falcon_price_format($order->total, $order) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mt-10">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Billing Address</h3>
                        <address class="not-italic text-gray-600 leading-relaxed">
                            {{ $order->first_name }} {{ $order->last_name }}<br>
                            {{ $order->address_line_1 }}<br>
                            @if($order->address_line_2) {{ $order->address_line_2 }}<br> @endif
                            {{ $order->city }}, {{ $order->state }} {{ $order->postcode }}<br>
                            {{ $order->country }}<br>
                            <div class="mt-4 text-sm">
                                <span class="block mb-1"><strong class="text-gray-800">Phone:</strong> {{ $order->customer_phone }}</span>
                                <span class="block"><strong class="text-gray-800">Email:</strong> {{ $order->customer_email }}</span>
                            </div>
                        </address>
                    </div>

                    @if($order->shipping_address_line_1)
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Shipping Address</h3>
                        <address class="not-italic text-gray-600 leading-relaxed">
                            {{ $order->shipping_first_name }} {{ $order->shipping_last_name }}<br>
                            {{ $order->shipping_address_line_1 }}<br>
                            @if($order->shipping_address_line_2) {{ $order->shipping_address_line_2 }}<br> @endif
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postcode }}<br>
                            {{ $order->shipping_country }}
                        </address>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="{{ url('/product') }}" class="inline-block bg-gray-900 text-white hover:text-white px-8 py-3 rounded font-bold hover:bg-blue-600 transition-colors duration-300">Continue Shopping</a>
        </div>
    </div>
</div>
@stop
