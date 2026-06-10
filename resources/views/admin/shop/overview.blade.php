<x-cms-dashboard::layouts.admin title="Shop Overview" active-menu="shop">
    @php
        $money = fn ($v) => $currency . number_format((float) $v, 2);
        $presets = ['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'month' => 'This Month', 'year' => 'This Year', 'all' => 'All Time'];
        $rangeLabel = $preset === 'custom'
            ? ($from ? $from->format('M d, Y') : '—') . ' – ' . $to->format('M d, Y')
            : ($presets[$preset] ?? 'Last 30 Days');
    @endphp

    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Shop Overview</h1>
        <span class="text-[13px] text-[#646970]">Showing: <strong>{{ $rangeLabel }}</strong></span>
    </div>

    {{-- Date range filter --}}
    <div class="bg-white border border-[#c3c4c7] rounded-sm p-4 mb-5">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @foreach($presets as $key => $label)
                <a href="{{ route('admin.shop.overview', ['range' => $key]) }}"
                   class="px-3 py-1.5 rounded text-[12px] font-semibold border transition-colors {{ $preset === $key ? 'bg-[#2271b1] text-white border-[#2271b1]' : 'bg-white text-[#50575e] border-[#c3c4c7] hover:border-[#2271b1] hover:text-[#2271b1]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form action="{{ route('admin.shop.overview') }}" method="GET" class="flex flex-wrap items-end gap-3 pt-3 border-t border-[#f0f0f1]">
            <div>
                <label class="block text-[11px] font-semibold text-[#646970] mb-1">From</label>
                <input type="date" name="from" value="{{ request('from', $from ? $from->format('Y-m-d') : '') }}" class="wp-input h-8 text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#646970] mb-1">To</label>
                <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" class="wp-input h-8 text-[13px]">
            </div>
            <button type="submit" class="wp-btn-primary h-8">Apply Custom Range</button>
        </form>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        @php
            $cards = [
                ['label' => 'Orders',        'value' => number_format($stats['total_orders']),   'icon' => 'receipt_long',       'color' => '#2271b1'],
                ['label' => 'Net Revenue',   'value' => $money($stats['net_revenue']),           'icon' => 'payments',           'color' => '#46b450'],
                ['label' => 'Gross Revenue', 'value' => $money($stats['gross_revenue']),         'icon' => 'account_balance',    'color' => '#3858e9'],
                ['label' => 'Refunded',      'value' => $money($stats['total_refunded']),        'icon' => 'currency_exchange',  'color' => '#8c44db'],
                ['label' => 'Avg Order',     'value' => $money($stats['avg_order']),             'icon' => 'trending_up',        'color' => '#dba617'],
                ['label' => 'Pending',       'value' => number_format($stats['pending']),        'icon' => 'schedule',           'color' => '#d63638'],
            ];
        @endphp
        @foreach($cards as $c)
            <div class="bg-white border border-[#c3c4c7] rounded-sm p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-[18px]" style="color:{{ $c['color'] }}">{{ $c['icon'] }}</span>
                    <span class="text-[11px] font-semibold text-[#646970] uppercase tracking-wide">{{ $c['label'] }}</span>
                </div>
                <div class="text-[20px] font-bold text-[#1d2327] leading-tight">{{ $c['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Orders table --}}
        <div class="lg:col-span-2 bg-white border border-[#c3c4c7] rounded-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[#f0f0f1] flex flex-wrap justify-between items-center gap-2">
                <span class="font-semibold text-[#1d2327]">Orders in this range</span>
                <div class="flex items-center gap-3">
                    <form action="{{ route('admin.shop.overview') }}" method="GET" class="flex items-center gap-1.5">
                        <input type="hidden" name="range" value="{{ $preset }}">
                        @if(request('from'))<input type="hidden" name="from" value="{{ request('from') }}">@endif
                        @if(request('to'))<input type="hidden" name="to" value="{{ request('to') }}">@endif
                        <input type="text" name="s" value="{{ request('s') }}" placeholder="Search orders..." class="wp-input h-7 text-[12px] w-44">
                        <button type="submit" class="wp-btn-secondary h-7 text-[12px]">Search</button>
                        @if(request('s'))
                            <a href="{{ route('admin.shop.overview', array_filter(['range' => $preset, 'from' => request('from'), 'to' => request('to')])) }}" class="text-[11px] text-[#646970] hover:text-[#2271b1]">Clear</a>
                        @endif
                    </form>
                    <a href="{{ route('admin.shop.orders.index') }}" class="text-[12px] text-[#2271b1] hover:underline whitespace-nowrap">View all →</a>
                </div>
            </div>
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-[#f6f7f7] text-left text-[#646970]">
                        <th class="px-4 py-2 font-semibold">Order</th>
                        <th class="px-4 py-2 font-semibold">Date</th>
                        <th class="px-4 py-2 font-semibold">Status</th>
                        <th class="px-4 py-2 font-semibold text-right">Total</th>
                        <th class="px-4 py-2 font-semibold text-right">Refunded</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $statusColors = [
                            'pending' => 'bg-[#ffb900] text-black', 'processing' => 'bg-[#2271b1] text-white',
                            'completed' => 'bg-[#46b450] text-white', 'cancelled' => 'bg-[#d63638] text-white',
                            'partially-refunded' => 'bg-[#8c8f94] text-white', 'refunded' => 'bg-[#646970] text-white',
                            'on-hold' => 'bg-[#ffb900] text-black', 'failed' => 'bg-[#d63638] text-white',
                        ];
                    @endphp
                    @forelse($orders as $order)
                        <tr class="border-t border-[#f0f0f1] hover:bg-[#f6f7f7]">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.shop.orders.show', $order->id) }}" class="text-[#2271b1] font-bold hover:underline">#{{ $order->order_number ?: $order->id }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-[#646970]">{{ cms_date($order->created_at, 'M d, Y') }}<div class="text-[11px]">{{ cms_date($order->created_at, 'H:i') }}</div></td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusColors[$order->status] ?? 'bg-gray-200 text-gray-800' }}">{{ str_replace('-', ' ', $order->status) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-bold">{{ lazy_price_format($order->total, $order) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if(($order->refunded_amount ?? 0) > 0)
                                    <span class="text-[#8c44db] font-semibold">{{ lazy_price_format($order->refunded_amount, $order) }}</span>
                                @else
                                    <span class="text-[#c3c4c7]">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-[#646970] italic">No orders in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-[#f0f0f1] flex justify-end">{{ $orders->links('cms-dashboard::components.admin.pagination') }}</div>
        </div>

        {{-- Status breakdown --}}
        <div class="bg-white border border-[#c3c4c7] rounded-sm overflow-hidden h-fit">
            <div class="px-4 py-3 border-b border-[#f0f0f1] font-semibold text-[#1d2327]">Order Status</div>
            <div class="p-4 space-y-3">
                @php
                    $statusMeta = [
                        'pending' => ['Pending', '#dba617'], 'processing' => ['Processing', '#2271b1'],
                        'completed' => ['Completed', '#46b450'], 'cancelled' => ['Cancelled', '#d63638'],
                        'partially-refunded' => ['Partially Refunded', '#8c44db'], 'refunded' => ['Refunded', '#646970'],
                        'on-hold' => ['On Hold', '#646970'], 'failed' => ['Failed', '#d63638'],
                    ];
                    $alwaysShow = ['partially-refunded', 'refunded'];
                    $totalForPct = max(1, $stats['total_orders']);
                @endphp
                @foreach($statusMeta as $key => [$label, $color])
                    @php $cnt = $statusCounts[$key] ?? 0; @endphp
                    @if($cnt > 0 || in_array($key, $alwaysShow, true))
                        <div>
                            <div class="flex justify-between text-[12px] mb-1">
                                <span class="font-medium text-[#1d2327]">{{ $label }}</span>
                                <span class="font-bold">{{ $cnt }}</span>
                            </div>
                            <div class="h-1.5 bg-[#f0f0f1] rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width:{{ round($cnt / $totalForPct * 100) }}%;background:{{ $color }}"></div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-cms-dashboard::layouts.admin>
