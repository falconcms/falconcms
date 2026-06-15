@extends('falcon-cms::themes.lazy-theme.layouts.app')

@section('title', 'Track Your Order')

@section('content')
@php $primaryColor = get_cms_option('theme_primary_color', '#0d9488'); @endphp
<div class="bg-gray-50 py-16 min-h-screen">
    <div class="container-custom max-w-3xl">

        {{-- Lookup form --}}
        <div class="bg-white rounded-sm shadow-sm border border-gray-100 p-6 mb-6">
            <form action="{{ route('shop.track') }}" method="POST"
                  class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-[12px] font-bold text-heading mb-1">Order Number</label>
                    <input type="text" name="order_number"
                           value="{{ old('order_number', request('order_number')) }}"
                           placeholder="ORD-XXXXXXXX"
                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-sm outline-none focus:border-primary transition" required>
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-heading mb-1">Email</label>
                    <input type="email" name="email"
                           value="{{ old('email', request('email')) }}"
                           placeholder="you@example.com"
                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-sm outline-none focus:border-primary transition" required>
                </div>
                <button type="submit"
                        class="w-full sm:w-auto bg-primary text-white px-6 py-2.5 rounded font-bold text-sm hover:bg-primary-hover transition uppercase whitespace-nowrap">
                    Track Order
                </button>
            </form>
        </div>

        @if(!empty($notFound))
            <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-sm p-4 text-sm text-center">
                No order found with that order number and email. Please check and try again.
            </div>
        @endif

        @if(!empty($order))
        @php
            $isBadStatus = in_array($order->status, ['cancelled','failed']);
            $statusColors = [
                'pending'            => '#d97706', 'processing'         => '#2563eb',
                'confirmed'          => '#0d9488', 'packing'            => '#7c3aed',
                'packed'             => '#7c3aed', 'delivering'         => '#0284c7',
                'delivered'          => '#16a34a', 'completed'          => '#16a34a',
                'on-hold'            => '#6b7280', 'cancelled'          => '#dc2626',
                'partially-refunded' => '#9333ea', 'refunded'           => '#6b7280',
                'failed'             => '#dc2626',
            ];
            $stColor = $statusColors[$order->status] ?? '#6b7280';
            $stLabel = \FalconCms\Core\Models\OrderStatusHistory::label($order->status);
            $timeline = $order->statusHistory ?? collect();
        @endphp

            {{-- Order header --}}
            <div class="bg-white rounded-sm shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="p-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Order</span>
                        <div class="text-xl font-bold text-heading">#{{ $order->order_number }}</div>
                        <div class="text-[13px] text-body mt-0.5">Placed on {{ cms_date($order->created_at, 'M d, Y') }}</div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[12px] font-bold uppercase"
                          style="background:{{ $stColor }}1a;color:{{ $stColor }}">{{ $stLabel }}</span>
                </div>

                {{-- Timeline --}}
                @if($timeline->isNotEmpty())
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-[13px] font-black uppercase tracking-widest text-heading mb-6">Timeline</h3>
                    <div class="space-y-0">
                    @foreach($timeline as $entry)
                        @php
                            $isLast   = $loop->last;
                            $entryBad = in_array($entry->status, ['cancelled','failed']);
                            $eColor   = $entryBad ? '#ef4444' : $primaryColor;
                            $note     = $entry->note ?: \FalconCms\Core\Models\OrderStatusHistory::defaultNote($entry->status);
                            $eLabel   = \FalconCms\Core\Models\OrderStatusHistory::label($entry->status);
                            $showTrack = in_array($entry->status, ['delivering','shipped'])
                                       && ($order->tracking_number || $order->tracking_url);
                            $trackUrl  = $order->tracking_url
                                       ?: route('shop.track', ['order_number' => $order->order_number, 'email' => $order->customer_email]);
                        @endphp
                        <div class="flex items-start">
                            {{-- Date --}}
                            <div class="hidden sm:flex flex-col items-end w-32 flex-shrink-0 pt-0.5 pr-4">
                                <span class="text-[12px] text-gray-500 font-medium leading-tight">{{ cms_date($entry->created_at, 'd M Y') }}</span>
                                <span class="text-[11px] text-gray-400 mt-0.5">{{ cms_date($entry->created_at, 'h:i a') }}</span>
                            </div>
                            {{-- Icon + line --}}
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center z-10"
                                     style="background:{{ $eColor }}">
                                    @if($entryBad)
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                    @else
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </div>
                                @if(!$isLast)
                                    <div class="w-0.5 min-h-[44px] flex-1" style="background:{{ $primaryColor }};opacity:.3"></div>
                                @endif
                            </div>
                            {{-- Content --}}
                            <div class="flex-1 pl-4 {{ $isLast ? 'pb-0' : 'pb-7' }} pt-0.5">
                                <span class="sm:hidden text-[11px] text-gray-400 block mb-0.5">{{ cms_date($entry->created_at, 'd M Y, h:i a') }}</span>
                                <p class="font-bold text-heading text-[14px] leading-snug">{{ $eLabel }}</p>
                                <p class="text-[13px] text-body mt-0.5 leading-relaxed">{{ $note }}</p>
                                @if($showTrack)
                                    <a href="{{ $trackUrl }}" target="_blank"
                                       class="inline-block mt-1 text-[13px] font-semibold underline underline-offset-2 hover:opacity-75"
                                       style="color:{{ $primaryColor }}">Track Order</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
                @endif

                {{-- Tracking info --}}
                @if($order->tracking_number)
                <div class="px-6 pb-6 border-b border-gray-100">
                    <div class="bg-blue-50 border border-blue-100 rounded-sm p-4">
                        <h3 class="text-[12px] font-bold text-heading mb-3 uppercase tracking-wide">Shipment Tracking</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            @if($order->tracking_carrier)
                                <div>
                                    <span class="text-gray-400 text-[11px] uppercase block">Carrier</span>
                                    <strong class="text-heading">{{ $order->tracking_carrier }}</strong>
                                </div>
                            @endif
                            <div>
                                <span class="text-gray-400 text-[11px] uppercase block">Tracking Number</span>
                                <strong class="text-heading">{{ $order->tracking_number }}</strong>
                            </div>
                        </div>
                        @if($order->tracking_url)
                            <a href="{{ $order->tracking_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 mt-4 bg-primary text-white px-5 py-2 rounded-sm text-sm font-bold hover:bg-primary-hover transition">
                                <i data-lucide="truck" class="w-4 h-4"></i> Track Package
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Order items --}}
                @if($order->items->isNotEmpty())
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-[13px] font-black uppercase tracking-widest text-heading mb-4">Order Items</h3>
                    <div class="space-y-3">
                    @foreach($order->items as $item)
                        @php
                            $rawImg = optional($item->product)->featured_image;
                            $img = $rawImg
                                ? (str_starts_with($rawImg, 'http') ? $rawImg : asset('storage/'.$rawImg))
                                : null;
                        @endphp
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded border border-gray-100 overflow-hidden flex-shrink-0 bg-gray-50">
                                @if($img)
                                    <img src="{{ $img }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <i data-lucide="package" class="w-6 h-6"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-heading text-sm leading-snug truncate">{{ $item->product_name }}</p>
                                <p class="text-[12px] text-body mt-0.5">Qty: {{ $item->quantity }}</p>
                            </div>
                            <div class="text-sm font-bold text-heading flex-shrink-0">
                                {{ lazy_price_format($item->subtotal, $order) }}
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
                @endif

                {{-- Totals + summary --}}
                <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span class="text-body">{{ $order->items->count() }} item{{ $order->items->count() === 1 ? '' : 's' }}</span>
                    <div class="text-right">
                        @if($order->discount_total > 0)
                            <div class="text-gray-400 text-[12px]">Discount: -{{ lazy_price_format($order->discount_total, $order) }}</div>
                        @endif
                        @if($order->shipping_total > 0)
                            <div class="text-gray-400 text-[12px]">Shipping: {{ lazy_price_format($order->shipping_total, $order) }}</div>
                        @endif
                        <div class="font-bold text-heading text-[16px] mt-0.5">Total: {{ lazy_price_format($order->total, $order) }}</div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
@stop
