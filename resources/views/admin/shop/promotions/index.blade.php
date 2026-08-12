<x-falcon-cms::layouts.admin pro-lock-feature="ecommerce" title="Promotions">
    <x-falcon-cms::admin.delete-modal />

    <div class="flex items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327] mr-3">Promotions</h1>
        <a href="{{ route('admin.shop.promotions.create') }}" class="wp-btn-secondary px-2 py-0.5 text-[12px] bg-white hover:bg-[#f6f7f7] border-[#2271b1] text-[#2271b1] leading-normal">Add New</a>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('error') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <div class="mb-4 p-3 bg-[#f0f6fc] border-l-4 border-[#72aee6] text-[13px] text-[#1d2327] leading-relaxed">
        Promotions apply <strong>automatically</strong> &mdash; the customer does not type a code.
        Use a <a href="{{ route('admin.shop.settings') }}?tab=coupons" class="text-[#2271b1]">coupon</a> when you want a code instead.
    </div>

    {{-- Status links + search, matching the Pages screen. --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-4">
        <div class="flex items-center text-[13px] text-[#646970]">
            <a href="{{ route('admin.shop.promotions.index') }}" class="{{ !request('status') ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ $allCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.shop.promotions.index', ['status' => 'active']) }}" class="{{ request('status') == 'active' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Running <span class="text-[#646970]">({{ $activeCount }})</span></a>
            @if($scheduledCount > 0)
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.shop.promotions.index', ['status' => 'scheduled']) }}" class="{{ request('status') == 'scheduled' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Scheduled <span class="text-[#646970]">({{ $scheduledCount }})</span></a>
            @endif
            @if($expiredCount > 0)
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.shop.promotions.index', ['status' => 'expired']) }}" class="{{ request('status') == 'expired' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Expired <span class="text-[#646970]">({{ $expiredCount }})</span></a>
            @endif
            @if($inactiveCount > 0)
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.shop.promotions.index', ['status' => 'inactive']) }}" class="{{ request('status') == 'inactive' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Inactive <span class="text-[#646970]">({{ $inactiveCount }})</span></a>
            @endif
        </div>

        <form action="{{ route('admin.shop.promotions.index') }}" method="GET" class="flex items-center space-x-1 w-full md:w-auto">
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px] flex-grow md:w-48" placeholder="">
            <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search Promotions</button>
        </form>
    </div>

    <form id="promotions-filter" method="POST" action="{{ route('admin.shop.promotions.bulk') }}">
    @csrf

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-2">
        <div class="flex items-center space-x-1">
            <select name="action" class="wp-input py-0 h-[30px] text-[13px]">
                <option value="-1">Bulk actions</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="delete">Delete</option>
            </select>
            <button type="button" onclick="handleBulkAction('promotions-filter')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
        </div>

        <x-falcon-cms::admin.pagination :paginator="$promotions" />
    </div>

    <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-4">
        <thead>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0"><input type="checkbox" id="cb-select-all-1" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left">Name</th>
                <th class="wp-table-header text-left">Rule</th>
                <th class="wp-table-header text-left w-[110px]">Status</th>
                <th class="wp-table-header text-left w-[170px]">Runs</th>
                <th class="wp-table-header text-left w-[90px]">Used</th>
            </tr>
        </thead>
        <tbody>
            @forelse($promotions as $idx => $item)
                @php
                    $expired = $item->ends_at && $item->ends_at->isPast();
                    $pending = $item->starts_at && $item->starts_at->isFuture();
                    $spent   = $item->usage_limit && $item->usage_count >= $item->usage_limit;
                @endphp
                <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group">
                    <td class="wp-table-cell text-center"><input type="checkbox" name="promotion_ids[]" value="{{ $item->id }}" class="cb-select-item rounded-sm border-[#8c8f94] text-[#2271b1]"></td>
                    <td class="wp-table-cell align-top text-[14px] text-left">
                        <strong>
                            <a href="{{ route('admin.shop.promotions.edit', $item->id) }}" class="text-[#2271b1] hover:text-[#135e96]">{{ $item->name }}</a>
                            @unless($item->is_active) <span class="font-normal text-[#646970]"> &mdash; Inactive</span> @endunless
                        </strong>
                        <div class="text-[12px] text-[#646970] mt-0.5">priority {{ $item->priority }}</div>
                        <div class="invisible group-hover:visible mt-1 text-[13px] space-x-1">
                            <a href="{{ route('admin.shop.promotions.edit', $item->id) }}" class="text-[#2271b1] hover:underline">Edit</a>
                            <span class="text-[#c3c4c7]">|</span>
                            <button type="button" onclick="deletePromotion({{ $item->id }})" class="text-[#b32d2e] hover:text-[#8a2424] hover:underline cursor-pointer">Delete</button>
                        </div>
                    </td>
                    <td class="wp-table-cell text-[#646970] text-left">
                        {{ $item->summary }}
                        @if($item->cart_message)
                            <div class="text-[12px] text-[#646970] italic mt-0.5">&ldquo;{{ $item->cart_message }}&rdquo;</div>
                        @endif
                    </td>
                    <td class="wp-table-cell text-left">
                        @if(!$item->is_active)
                            <span class="text-[#646970]">Inactive</span>
                        @elseif($spent)
                            <span class="text-[#d63638]">Limit reached</span>
                        @elseif($expired)
                            <span class="text-[#d63638]">Expired</span>
                        @elseif($pending)
                            <span class="text-[#dba617]">Scheduled</span>
                        @else
                            <span class="text-[#00a32a] font-semibold">Running</span>
                        @endif
                    </td>
                    <td class="wp-table-cell text-[#2c3338] text-left text-[12px]">
                        {{ $item->starts_at ? cms_date($item->starts_at, 'Y/m/d') : 'Any time' }}<br>
                        <span class="text-[#646970]">&rarr; {{ $item->ends_at ? cms_date($item->ends_at, 'Y/m/d') : 'No end' }}</span>
                    </td>
                    <td class="wp-table-cell text-[#646970] text-left">
                        {{ $item->usage_count }}{{ $item->usage_limit ? ' / ' . $item->usage_limit : '' }}
                    </td>
                </tr>
            @empty
                <tr class="bg-[#fff]">
                    <td colspan="6" class="wp-table-cell text-center py-6">
                        @if(request('s') || request('status'))
                            No promotions match this filter.
                        @else
                            No promotions yet.
                            <a href="{{ route('admin.shop.promotions.create') }}" class="text-[#2271b1]">Create your first one</a>
                            &mdash; for example &ldquo;buy 2, get 1 free&rdquo;.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0 border-t"><input type="checkbox" id="cb-select-all-2" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left border-t">Name</th>
                <th class="wp-table-header text-left border-t">Rule</th>
                <th class="wp-table-header text-left border-t">Status</th>
                <th class="wp-table-header text-left border-t">Runs</th>
                <th class="wp-table-header text-left border-t">Used</th>
            </tr>
        </tfoot>
    </table>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-2">
        <div class="flex items-center space-x-2">
            <select name="action2" class="wp-input py-0 h-[30px] text-[13px]">
                <option value="-1">Bulk actions</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="delete">Delete</option>
            </select>
            <button type="button" onclick="handleBulkAction('promotions-filter', 'action2')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
        </div>

        <x-falcon-cms::admin.pagination :paginator="$promotions" />
    </div>
    </form>

    @foreach($promotions as $item)
        <form id="delete-promotion-{{ $item->id }}" action="{{ route('admin.shop.promotions.destroy', $item->id) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach

    <script>
        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function (master) {
            master.addEventListener('change', function () {
                const isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(function (item) { item.checked = isChecked; });
                document.getElementById('cb-select-all-1').checked = isChecked;
                document.getElementById('cb-select-all-2').checked = isChecked;
            });
        });

        // Deleting a promotion never touches orders that already used it, so the confirmation
        // says so rather than implying past discounts are at risk.
        window.deletePromotion = async function (id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete promotion',
                message: 'Delete this promotion? Orders that already used it keep their discount.',
                confirmText: 'Delete',
                isDanger: true,
            });
            if (confirmed) document.getElementById('delete-promotion-' + id).submit();
        };

        window.handleBulkAction = async function (formId, selectName = 'action') {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');

            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one promotion.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.falconConfirm({
                    title: 'Delete promotions',
                    message: `Delete ${selected.length} promotion(s)? Orders that already used them keep their discount.`,
                    confirmText: 'Delete',
                    isDanger: true,
                });
                if (!confirmed) return;
            }

            form.submit();
        };
    </script>
</x-falcon-cms::layouts.admin>
