<x-cms-dashboard::layouts.admin title="Orders" active-menu="shop">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Orders</h1>
    </div>

    <div class="bg-white border border-[#c3c4c7] shadow-sm">
        <div class="p-3 border-b border-[#c3c4c7] flex justify-between items-center bg-[#f6f7f7]">
            <div class="flex items-center space-x-2">
                <select class="wp-input h-8 py-0 text-[13px]" onchange="window.location.href='?status=' + this.value">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <form action="" method="GET" class="flex items-center space-x-2">
                <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-8 text-[13px]" placeholder="Search orders...">
                <button type="submit" class="wp-btn-secondary h-8">Search</button>
            </form>
        </div>

        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-[#f6f7f7]">
                    <th class="wp-table-header w-12 text-center">#</th>
                    <th class="wp-table-header">Order</th>
                    <th class="wp-table-header">Date</th>
                    <th class="wp-table-header">Status</th>
                    <th class="wp-table-header">Total</th>
                    <th class="wp-table-header text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr class="hover:bg-[#f6f7f7] transition-colors">
                        <td class="wp-table-cell text-center text-[#646970]">{{ $order->id }}</td>
                        <td class="wp-table-cell">
                            <a href="{{ route('admin.shop.orders.show', $order->id) }}" class="text-[#2271b1] font-bold hover:text-[#135e96]">
                                #{{ $order->order_number ?: $order->id }} {{ $order->billing_first_name }} {{ $order->billing_last_name }}
                            </a>
                            <div class="text-[11px] text-[#646970]">{{ $order->billing_email }}</div>
                        </td>
                        <td class="wp-table-cell text-[#646970]">
                            {{ $order->created_at->format('M d, Y') }}
                            <div class="text-[11px]">{{ $order->created_at->format('H:i') }}</div>
                        </td>
                        <td class="wp-table-cell">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-[#ffb900] text-black',
                                    'processing' => 'bg-[#2271b1] text-white',
                                    'completed' => 'bg-[#46b450] text-white',
                                    'cancelled' => 'bg-[#d63638] text-white',
                                    'on-hold' => 'bg-[#ffb900] text-black',
                                    'refunded' => 'bg-[#646970] text-white',
                                    'failed' => 'bg-[#d63638] text-white',
                                ];
                                $color = $statusColors[$order->status] ?? 'bg-gray-200 text-gray-800';
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $color }}">
                                {{ str_replace('-', ' ', $order->status) }}
                            </span>
                        </td>
                        <td class="wp-table-cell font-bold">
                            {{ lazy_price_format($order->total, $order) }}
                        </td>
                        <td class="wp-table-cell text-right">
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('admin.shop.orders.show', $order->id) }}" class="text-[#2271b1] hover:text-[#135e96]" title="View Order">
                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="wp-table-cell text-center py-10 text-[#646970] italic">
                            No orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($orders->hasPages())
            <div class="p-3 border-t border-[#c3c4c7] bg-[#f6f7f7]">
                {{ $orders->links('cms-dashboard::components.admin.pagination') }}
            </div>
        @endif
    </div>
</x-cms-dashboard::layouts.admin>
