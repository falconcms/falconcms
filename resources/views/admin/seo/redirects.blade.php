<x-falcon-cms::layouts.admin>
    <x-slot name="title">Redirection Manager - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Redirection Manager</h1>
        </div>

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Add New Redirect --}}
            <div class="lg:col-span-1">
                <div class="wp-metabox">
                    <div class="wp-metabox-header"><span>Add New Redirect</span></div>
                    <div class="wp-metabox-content p-4">
                        <form action="{{ route('admin.redirects.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Old URL (Path)</label>
                                <input type="text" name="old_url" class="wp-input w-full h-8" placeholder="/old-link" required>
                                <p class="text-[11px] text-[#646970] mt-1">Relative path starting with /</p>
                            </div>
                            <div>
                                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">New URL (Target)</label>
                                <input type="text" name="new_url" class="wp-input w-full h-8" placeholder="/new-link" required>
                                <p class="text-[11px] text-[#646970] mt-1">Can be internal path or full external URL.</p>
                            </div>
                            <div>
                                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Redirect Type</label>
                                <select name="status_code" class="wp-input w-full h-8 py-0">
                                    <option value="301">301 (Permanent)</option>
                                    <option value="302">302 (Temporary)</option>
                                </select>
                            </div>
                            <div class="pt-2">
                                <button type="submit" class="wp-btn-primary w-full h-8 font-semibold">Add Redirect</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Redirects List --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div class="flex items-center gap-3">
                            <div class="text-[12px] text-gray-500">
                                <span class="font-bold text-gray-700">{{ $redirects->total() }}</span> Redirects found
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form action="" method="GET" class="flex items-center gap-2">
                                <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-8 text-[13px] w-48 shadow-sm" placeholder="Search URLs...">
                                <button type="submit" class="wp-btn-secondary h-8 px-3">Search</button>
                            </form>
                        </div>
                    </div>

                    <form action="{{ route('admin.redirects.bulk') }}" method="POST" id="bulk-form">
                        @csrf
                        <div class="p-3 border-b border-gray-100 flex items-center gap-2">
                            <select name="action" class="wp-input h-8 py-0 text-[13px] w-32">
                                <option value="-1">Bulk Actions</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="button" onclick="handleBulkAction('bulk-form')" class="wp-btn-secondary h-8 px-3">Apply</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px] border-collapse">
                                <thead>
                                    <tr class="bg-white">
                                        <th class="w-10 p-4 border-b border-gray-100"><input type="checkbox" id="select-all" class="rounded"></th>
                                        <th class="text-left p-4 border-b border-gray-100 font-bold text-[#1d2327]">Source URL</th>
                                        <th class="text-left p-4 border-b border-gray-100 font-bold text-[#1d2327]">Destination</th>
                                        <th class="w-20 text-center p-4 border-b border-gray-100 font-bold text-[#1d2327]">Type</th>
                                        <th class="w-20 text-center p-4 border-b border-gray-100 font-bold text-[#1d2327]">Hits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($redirects as $redirect)
                                        <tr class="hover:bg-blue-50/30 transition-colors group">
                                            <td class="p-4 border-b border-gray-50 text-center">
                                                <input type="checkbox" name="ids[]" value="{{ $redirect->id }}" class="item-checkbox rounded">
                                            </td>
                                            <td class="p-4 border-b border-gray-50">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded bg-orange-50 flex items-center justify-center">
                                                        <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-[#2271b1] break-all">{{ $redirect->old_url }}</div>
                                                        <div class="flex items-center gap-2 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <form id="delete-redirect-{{ $redirect->id }}" action="{{ route('admin.redirects.destroy', $redirect) }}" method="POST">
                                                                @csrf @method('DELETE')
                                                                <button type="button" onclick="confirmRedirectDelete({{ $redirect->id }})" class="text-[11px] text-[#d63638] hover:underline font-medium">Delete Permanently</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4 border-b border-gray-50">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded bg-green-50 flex items-center justify-center">
                                                        <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                                    </div>
                                                    <span class="text-gray-600 break-all">{{ $redirect->new_url }}</span>
                                                </div>
                                                @if($redirect->last_hit_at)
                                                    <div class="text-[10px] text-gray-400 mt-1 ml-8">Last hit: {{ $redirect->last_hit_at->diffForHumans() }}</div>
                                                @endif
                                            </td>
                                            <td class="p-4 border-b border-gray-50 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $redirect->status_code == 301 ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ $redirect->status_code }}
                                                </span>
                                            </td>
                                            <td class="p-4 border-b border-gray-50 text-center">
                                                <div class="font-bold text-gray-700">{{ number_format($redirect->hits) }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-10 text-center">
                                                <div class="flex flex-col items-center justify-center text-gray-400">
                                                    <svg class="w-12 h-12 mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                                    <p class="italic">No redirection records found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($redirects->hasPages())
                            <div class="p-4 border-t border-gray-100">
                                {{ $redirects->links() }}
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = this.checked);
        });

        window.handleBulkAction = async function(formId) {
            const form = document.getElementById(formId);
            const action = form.querySelector('select[name="action"]').value;
            const selected = form.querySelectorAll('.item-checkbox:checked');

            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one item.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.falconConfirm({
                    title: 'Delete Redirects',
                    message: `Are you sure you want to permanently delete ${selected.length} redirects? This action cannot be undone.`,
                    confirmText: 'Delete Redirects',
                    isDanger: true
                });

                if (confirmed) {
                    form.submit();
                }
            } else {
                form.submit();
            }
        };

        window.confirmRedirectDelete = async function(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Redirect',
                message: 'Are you sure you want to permanently delete this redirect? This action cannot be undone.',
                confirmText: 'Delete Redirect',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-redirect-${id}`).submit();
            }
        };
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
