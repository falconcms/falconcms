<x-falcon-cms::layouts.admin>
    <x-slot name="title">IP Blacklist - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex items-baseline gap-2 mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">IP Blacklist</h1>
            <p class="text-[13px] text-[#646970]">Manage blocked IP addresses from unregistered login attempts.</p>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex justify-end mb-2">
            <form action="{{ route('admin.blacklist.index') }}" method="GET" class="flex gap-1">
                <input type="search" name="s" value="{{ request('s') }}" class="wp-input h-8 px-2 border border-[#8c8f94] focus:border-[#2271b1] outline-none" placeholder="Search IP, Country or Reason...">
                <button type="submit" class="border border-[#8c8f94] text-[#2c3338] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f6f7f7]">Search Blacklist</button>
            </form>
        </div>

        {{-- Hidden Bulk Action Form --}}
        <form id="blacklist-bulk-form" action="{{ route('admin.blacklist.bulk') }}" method="POST" class="hidden">
            @csrf
        </form>

        <div class="flex justify-between items-center mb-2">
            <div class="flex gap-1 items-center">
                <select name="action" form="blacklist-bulk-form" class="wp-input h-8 text-[13px]">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete (Unblock)</option>
                </select>
                <button type="button" onclick="handleBulkBlacklistAction('blacklist-bulk-form', 'action')" class="border border-[#2271b1] text-[#2271b1] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f0f6fa]">Apply</button>
            </div>
            <x-falcon-cms::admin.pagination :paginator="$blockedIps" />
        </div>

        <div class="bg-white border border-[#c3c4c7] shadow-sm">
            <table class="w-full text-left text-[13px] border-collapse">
                <thead>
                    <tr class="border-b border-[#c3c4c7] bg-[#f9f9f9]">
                        <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all"></th>
                        <th class="p-2 font-semibold">IP Address</th>
                        <th class="p-2 font-semibold">Country</th>
                        <th class="p-2 font-semibold">Attempts</th>
                        <th class="p-2 font-semibold">Reason</th>
                        <th class="p-2 font-semibold">Blocked At</th>
                        <th class="p-2 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($blockedIps as $ip)
                        <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7] group">
                            <td class="p-2 text-center"><input type="checkbox" name="ids[]" value="{{ $ip->id }}" form="blacklist-bulk-form" class="ip-checkbox"></td>
                            <td class="p-2 font-semibold text-[#2271b1]">{{ $ip->ip_address }}</td>
                            <td class="p-2">
                                <div class="flex items-center gap-2">
                                    @if($ip->country_code)
                                        <img src="https://flagcdn.com/w20/{{ $ip->country_code }}.png" 
                                             srcset="https://flagcdn.com/w40/{{ $ip->country_code }}.png 2x"
                                             width="20" alt="{{ $ip->country }}">
                                    @endif
                                    <span class="bg-slate-100 px-2 py-0.5 rounded text-[#1d2327] font-medium">{{ $ip->country ?: 'Unknown' }}</span>
                                </div>
                            </td>
                            <td class="p-2">{{ $ip->attempts }}</td>
                            <td class="p-2 text-[#646970]">{{ $ip->reason }}</td>
                            <td class="p-2">{{ cms_date($ip->created_at, 'M d, Y H:i') }}</td>
                            <td class="p-2">
                                <form id="delete-blacklist-{{ $ip->id }}" action="{{ route('admin.blacklist.destroy', $ip->id) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="confirmBlacklistDelete({{ $ip->id }})" class="text-[#b32d2e] hover:underline font-semibold">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center text-slate-500 italic">No blacklisted IPs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-between items-center text-[13px] text-[#2c3338]">
            <div class="flex items-center space-x-2">
                <select name="action2" form="blacklist-bulk-form" class="wp-input h-8 text-[13px]">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete (Unblock)</option>
                </select>
                <button type="button" onclick="handleBulkBlacklistAction('blacklist-bulk-form', 'action2')" class="border border-[#2271b1] text-[#2271b1] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f0f6fa]">Apply</button>
            </div>
            <x-falcon-cms::admin.pagination :paginator="$blockedIps" />
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('select-all')?.addEventListener('click', function() {
            document.querySelectorAll('.ip-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
        });

        window.confirmBlacklistDelete = async function(id) {
            const confirmed = await window.lazyConfirm({
                title: 'Unblock IP',
                message: 'Are you sure you want to unblock this IP address? This will allow requests from this IP again.',
                confirmText: 'Unblock IP',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-blacklist-${id}`).submit();
            }
        };

        window.handleBulkBlacklistAction = async function(formId, selectName) {
            const form = document.getElementById(formId);
            const select = document.querySelector(`select[name="${selectName}"]`);
            const action = select.value;
            const selected = document.querySelectorAll('.ip-checkbox:checked');

            if (!action) return;
            if (selected.length === 0) {
                window.showToast('Please select at least one IP.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.lazyConfirm({
                    title: 'Unblock Multiple IPs',
                    message: `Are you sure you want to unblock ${selected.length} IP addresses?`,
                    confirmText: 'Unblock All Selected',
                    isDanger: true
                });

                if (confirmed) {
                    // Sync action inputs if needed, though they are inside the same form conceptually
                    // In this case they are separate selects but both use form="blacklist-bulk-form"
                    // We need to make sure the form knows which action is being used.
                    const hiddenAction = document.createElement('input');
                    hiddenAction.type = 'hidden';
                    hiddenAction.name = 'action';
                    hiddenAction.value = action;
                    form.appendChild(hiddenAction);
                    form.submit();
                }
            } else {
                form.submit();
            }
        };
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
