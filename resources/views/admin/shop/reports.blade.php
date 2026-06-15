<x-falcon-cms::layouts.admin title="Sales Reports" active-menu="shop">
    @php
        $currency  = get_shop_option('shop_currency_symbol', '$');
        $money     = fn($v) => $currency . number_format((float) $v, 2);
        $exportBase = route('admin.shop.reports.export');
        $dateFrom   = request('from', $fromRaw->format('Y-m-d'));
        $dateTo     = request('to', $toRaw->format('Y-m-d'));
    @endphp

    <h1 class="text-[23px] font-normal text-[#1d2327] mb-6">Sales Reports</h1>

    {{-- Date range + period filter --}}
    <div class="bg-white border border-[#c3c4c7] rounded-sm p-4 mb-6">
        <form action="{{ route('admin.shop.reports.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-[#646970] mb-1">From</label>
                <input type="date" name="from" value="{{ $dateFrom }}" class="wp-input h-8 text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#646970] mb-1">To</label>
                <input type="date" name="to" value="{{ $dateTo }}" class="wp-input h-8 text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#646970] mb-1">Group By</label>
                <select name="period" class="wp-input h-8 text-[13px] py-0">
                    <option value="daily"   {{ $period === 'daily'   ? 'selected' : '' }}>Daily</option>
                    <option value="weekly"  {{ $period === 'weekly'  ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>Monthly</option>
                </select>
            </div>
            <button type="submit" class="wp-btn-primary h-8">Apply</button>
            <a href="{{ route('admin.shop.reports.index') }}" class="wp-btn h-8 text-[13px] flex items-center">Reset</a>
        </form>
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach([
            ['Total Revenue',     $money($summary->total_revenue ?? 0),     'payments'],
            ['Total Orders',      number_format($summary->total_orders ?? 0),    'shopping_bag'],
            ['Unique Customers',  number_format($summary->unique_customers ?? 0), 'group'],
        ] as [$label, $value, $icon])
        <div class="bg-white border border-[#c3c4c7] rounded-sm p-5 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-[#f0f6fc] flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[#2271b1] text-[20px]">{{ $icon }}</span>
            </div>
            <div>
                <p class="text-[22px] font-bold text-[#1d2327] leading-none">{{ $value }}</p>
                <p class="text-[12px] text-[#646970] mt-1">{{ $label }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Revenue Chart --}}
    <div class="bg-white border border-[#c3c4c7] rounded-sm p-5 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-[14px] font-semibold text-[#1d2327]">Revenue by Period</h2>
            <a href="{{ $exportBase }}?type=revenue&from={{ $dateFrom }}&to={{ $dateTo }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-[#2271b1] border border-[#c3c4c7] rounded hover:border-[#2271b1] transition">
                <span class="material-symbols-outlined text-[14px]">download</span> Export CSV
            </a>
        </div>
        @if($revenue->isEmpty())
            <p class="text-[13px] text-[#646970] py-6 text-center">No revenue data for this period.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-[#f0f0f1]">
                        <th class="text-left py-2 px-3 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Period</th>
                        <th class="text-right py-2 px-3 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Orders</th>
                        <th class="text-right py-2 px-3 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f0f0f1]">
                    @foreach($revenue as $row)
                    <tr class="hover:bg-[#f6f7f7]">
                        <td class="py-2.5 px-3 font-medium text-[#1d2327]">{{ $row->period }}</td>
                        <td class="py-2.5 px-3 text-right text-[#646970]">{{ number_format($row->orders) }}</td>
                        <td class="py-2.5 px-3 text-right font-semibold text-[#1d2327]">{{ $money($row->revenue) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Top Selling Products --}}
        <div class="bg-white border border-[#c3c4c7] rounded-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-[14px] font-semibold text-[#1d2327]">Top Selling Products</h2>
                <a href="{{ $exportBase }}?type=products&from={{ $dateFrom }}&to={{ $dateTo }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-[#2271b1] border border-[#c3c4c7] rounded hover:border-[#2271b1] transition">
                    <span class="material-symbols-outlined text-[14px]">download</span> Export CSV
                </a>
            </div>
            @if($topProducts->isEmpty())
                <p class="text-[13px] text-[#646970] py-6 text-center">No product data for this period.</p>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-[#f0f0f1]">
                            <th class="text-left py-2 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Product</th>
                            <th class="text-right py-2 px-2 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Units</th>
                            <th class="text-right py-2 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0f0f1]">
                        @foreach($topProducts as $row)
                        <tr class="hover:bg-[#f6f7f7]">
                            <td class="py-2.5">
                                <a href="{{ route('admin.posts.edit', $row->product_id) }}" class="text-[#2271b1] hover:text-[#135e96] font-medium">{{ $row->product_name }}</a>
                            </td>
                            <td class="py-2.5 px-2 text-right text-[#646970]">{{ number_format($row->units_sold) }}</td>
                            <td class="py-2.5 text-right font-semibold text-[#1d2327]">{{ $money($row->revenue) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Customer LTV --}}
        <div class="bg-white border border-[#c3c4c7] rounded-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-[14px] font-semibold text-[#1d2327]">Customer Lifetime Value</h2>
                <a href="{{ $exportBase }}?type=customers&from={{ $dateFrom }}&to={{ $dateTo }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-[#2271b1] border border-[#c3c4c7] rounded hover:border-[#2271b1] transition">
                    <span class="material-symbols-outlined text-[14px]">download</span> Export CSV
                </a>
            </div>
            @if($topCustomers->isEmpty())
                <p class="text-[13px] text-[#646970] py-6 text-center">No customer data found.</p>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-[#f0f0f1]">
                            <th class="text-left py-2 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Customer</th>
                            <th class="text-right py-2 px-2 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">Orders</th>
                            <th class="text-right py-2 text-[11px] font-semibold text-[#646970] uppercase tracking-wider">LTV</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0f0f1]">
                        @foreach($topCustomers as $row)
                        <tr class="hover:bg-[#f6f7f7]">
                            <td class="py-2.5">
                                <p class="font-medium text-[#1d2327]">{{ $row->customer_name ?: '—' }}</p>
                                <p class="text-[11px] text-[#646970]">{{ $row->customer_email }}</p>
                            </td>
                            <td class="py-2.5 px-2 text-right text-[#646970]">{{ number_format($row->order_count) }}</td>
                            <td class="py-2.5 text-right font-semibold text-[#1d2327]">{{ $money($row->lifetime_value) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</x-falcon-cms::layouts.admin>
