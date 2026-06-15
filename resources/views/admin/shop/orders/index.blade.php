<x-falcon-cms::layouts.admin title="Orders" active-menu="shop">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Orders</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-5 text-[13px] text-[#1d2327]">
            {{ session('success') }}
        </div>
    @endif

    <!-- Search Section (Above everything) -->
    <div class="mb-4 flex justify-end">
        <form action="" method="GET" class="flex items-center space-x-2">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-8 text-[13px] w-64" placeholder="Search orders...">
            <button type="submit" class="wp-btn-secondary h-8">Search Orders</button>
        </form>
    </div>

    <div class="bg-white border border-[#c3c4c7] shadow-sm">
        <form action="{{ route('admin.shop.orders.bulk') }}" method="POST" id="bulk-form">
            @csrf
            <!-- Top Bulk Actions & Pagination -->
            <div class="p-3 border-b border-[#c3c4c7] flex flex-wrap gap-3 justify-between items-center bg-[#f6f7f7]">
                <div class="flex items-center space-x-2">
                    <select name="action" class="wp-input h-8 py-0 text-[13px] w-48 bulk-action-select">
                        <option value="">Bulk Actions</option>
                        <option value="status_pending">Mark as Pending</option>
                        <option value="status_processing">Mark as Processing</option>
                        <option value="status_on-hold">Mark as On Hold</option>
                        <option value="status_completed">Mark as Completed</option>
                        <option value="delete">Delete Permanently</option>
                    </select>
                    <button type="submit" class="wp-btn-secondary h-8">Apply</button>

                    <div class="h-6 w-[1px] bg-gray-300 mx-2"></div>

                    <select class="wp-input h-8 py-0 text-[13px]" onchange="window.location.href='?status=' + this.value">
                        <option value="">All Statuses</option>
                        @foreach(['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'partially-refunded', 'refunded', 'failed'] as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('-', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="hidden md:block">
                    {{ $orders->links('falcon-cms::components.admin.pagination') }}
                </div>
            </div>

            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#f6f7f7]">
                        <th class="wp-table-header w-10 text-center">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="wp-table-header">Order ID</th>
                        <th class="wp-table-header">Date</th>
                        <th class="wp-table-header">Status</th>
                        <th class="wp-table-header">Total</th>
                        <th class="wp-table-header">Refunded</th>
                        <th class="wp-table-header text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="hover:bg-[#f6f7f7] transition-colors {{ !$order->is_read ? 'bg-[#fcf5f5]' : '' }}">
                            <td class="wp-table-cell text-center">
                                <input type="checkbox" name="ids[]" value="{{ $order->id }}" class="order-checkbox rounded border-gray-300">
                            </td>
                            <td class="wp-table-cell">
                                <a href="{{ route('admin.shop.orders.show', $order->id) }}" class="text-[#2271b1] font-bold hover:text-[#135e96]">
                                    #{{ $order->order_number ?: $order->id }}
                                </a>
                            </td>
                            <td class="wp-table-cell text-[#646970]">
                                {{ cms_date($order->created_at, 'M d, Y') }}
                                <div class="text-[11px]">{{ cms_date($order->created_at, 'H:i') }}</div>
                            </td>
                            <td class="wp-table-cell">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-[#ffb900] text-black',
                                        'processing' => 'bg-[#2271b1] text-white',
                                        'completed' => 'bg-[#46b450] text-white',
                                        'cancelled' => 'bg-[#d63638] text-white',
                                        'on-hold' => 'bg-[#ffb900] text-black',
                                        'partially-refunded' => 'bg-[#8c8f94] text-white',
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
                                {{ falcon_price_format($order->total, $order) }}
                            </td>
                            <td class="wp-table-cell">
                                @if(($order->refunded_amount ?? 0) > 0)
                                    @php $fullyRef = (float) $order->refunded_amount >= (float) $order->total - 0.001; @endphp
                                    <span class="font-bold {{ $fullyRef ? 'text-[#646970]' : 'text-[#8c44db]' }}">{{ falcon_price_format($order->refunded_amount, $order) }}</span>
                                    <div class="text-[10px] {{ $fullyRef ? 'text-[#646970]' : 'text-[#8c44db]' }}">{{ $fullyRef ? 'Full amount' : 'Partial' }}</div>
                                @else
                                    <span class="text-[#c3c4c7]">—</span>
                                @endif
                            </td>
                            <td class="wp-table-cell text-right">
                                <div class="flex justify-end space-x-3">
                                    <a href="{{ route('admin.shop.orders.show', $order->id) }}" class="text-[#2271b1] hover:text-[#135e96]" title="View Order">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    @if(in_array($order->status, ['completed', 'partially-refunded']))
                                        <a href="{{ route('admin.shop.orders.invoice', $order->id) }}" target="_blank" class="text-[#2271b1] hover:text-[#135e96]" title="Print Invoice">
                                            <span class="material-symbols-outlined text-[20px]">print</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="wp-table-cell text-center py-10 text-[#646970] italic">
                                No orders found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Bottom Bulk Actions & Pagination -->
            <div class="p-3 border-t border-[#c3c4c7] flex flex-wrap gap-3 justify-between items-center bg-[#f6f7f7]">
                <div class="flex items-center space-x-2">
                    <select name="action_bottom" class="wp-input h-8 py-0 text-[13px] w-48 bulk-action-select">
                        <option value="">Bulk Actions</option>
                        <option value="status_pending">Mark as Pending</option>
                        <option value="status_processing">Mark as Processing</option>
                        <option value="status_on-hold">Mark as On Hold</option>
                        <option value="status_completed">Mark as Completed</option>
                        <option value="delete">Delete Permanently</option>
                    </select>
                    <button type="submit" class="wp-btn-secondary h-8">Apply</button>
                </div>
                
                <div>
                    {{ $orders->links('falcon-cms::components.admin.pagination') }}
                </div>
            </div>
        </form>
    </div>

    <x-falcon-cms::admin.delete-modal />

    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('bulk-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const topSelect = this.querySelector('select[name="action"]');
            const bottomSelect = this.querySelector('select[name="action_bottom"]');
            
            let action = topSelect.value;
            if (!action && bottomSelect) action = bottomSelect.value;
            
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
            if (checkedCount === 0) {
                alert('Please select at least one order.');
                return;
            }

            topSelect.value = action;

            if (action === 'delete') {
                const confirmed = await window.lazyConfirm({
                    title: 'Delete Orders',
                    message: `Are you sure you want to permanently delete ${checkedCount} selected orders? This action cannot be undone.`,
                    confirmText: 'Delete Permanently',
                    isDanger: true
                });
                
                if (confirmed) {
                    this.submit();
                }
            } else {
                this.submit();
            }
        });

        const selects = document.querySelectorAll('.bulk-action-select');
        selects.forEach(select => {
            select.addEventListener('change', function() {
                selects.forEach(s => s.value = this.value);
            });
        });
    </script>
</x-falcon-cms::layouts.admin>
