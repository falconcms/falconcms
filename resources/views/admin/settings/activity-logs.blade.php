<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Activity Logs - FalconCMS</x-slot>

    <div class="px-2">
        <div class="mb-4 flex items-center">
            <h1 class="text-[23px] font-normal text-[#1d2327] inline-block mr-3">Settings</h1>
        </div>

        @include('cms-dashboard::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
                <p>{{ session('success') }}</p>
                <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-4">
            <div class="flex flex-wrap items-center text-[13px] text-[#646970]">
                <a href="{{ route('admin.settings.activity-logs') }}" class="{{ !request('action') ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ \Acme\CmsDashboard\Models\ActivityLog::count() }})</span></a>
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.settings.activity-logs', ['action' => 'created']) }}" class="{{ request('action') == 'created' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Created <span class="text-[#646970]">({{ \Acme\CmsDashboard\Models\ActivityLog::where('action', 'created')->count() }})</span></a>
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.settings.activity-logs', ['action' => 'updated']) }}" class="{{ request('action') == 'updated' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Updated <span class="text-[#646970]">({{ \Acme\CmsDashboard\Models\ActivityLog::where('action', 'updated')->count() }})</span></a>
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.settings.activity-logs', ['action' => 'deleted']) }}" class="{{ request('action') == 'deleted' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Deleted <span class="text-[#646970]">({{ \Acme\CmsDashboard\Models\ActivityLog::where('action', 'deleted')->count() }})</span></a>
            </div>

            <form action="{{ route('admin.settings.activity-logs') }}" method="GET" class="flex items-center space-x-1 w-full md:w-auto">
                @if(request('action')) <input type="hidden" name="action" value="{{ request('action') }}"> @endif
                @if(request('user_id')) <input type="hidden" name="user_id" value="{{ request('user_id') }}"> @endif
                <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px] flex-grow md:w-48" placeholder="">
                <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search Logs</button>
            </form>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center space-x-1">
                    <select name="bulk_action_top" id="bulk_action_top" class="wp-input py-0 h-[30px] text-[13px]">
                        <option value="">Bulk actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="button" onclick="submitBulkAction('bulk_action_top')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
                </div>

                <form action="{{ route('admin.settings.activity-logs') }}" method="GET" class="flex items-center space-x-1 ml-0 md:ml-4">
                    @if(request('s')) <input type="hidden" name="s" value="{{ request('s') }}"> @endif
                    @if(request('action')) <input type="hidden" name="action" value="{{ request('action') }}"> @endif
                    <select name="user_id" class="wp-input py-0 h-[30px] text-[13px]">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Filter</button>
                </form>
            </div>
            
            <x-cms-dashboard::admin.pagination :paginator="$logs" />
        </div>

        <form action="{{ route('admin.settings.activity-logs.bulk') }}" method="POST" id="main-logs-form">
            @csrf
            <input type="hidden" name="bulk_action" id="hidden-bulk-action">

        <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)]">
            <table class="wp-list-table w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="wp-table-header w-[40px] text-center"><input type="checkbox" id="select-all-logs" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                        <th class="wp-table-header w-[150px]">Date</th>
                        <th class="wp-table-header w-[150px]">User</th>
                        <th class="wp-table-header w-[120px]">Action</th>
                        <th class="wp-table-header">Description</th>
                        <th class="wp-table-header w-[180px]">Location & IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f0f0f1]">
                    @forelse($logs as $idx => $log)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} hover:bg-[#f0f0f1] transition-colors group">
                            <td class="wp-table-cell text-center align-top">
                                <input type="checkbox" name="log_ids[]" value="{{ $log->id }}" class="log-checkbox rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]">
                            </td>
                            <td class="wp-table-cell align-top">
                                <span class="font-medium">{{ cms_date($log->created_at, 'Y/m/d') }}</span><br>
                                <span class="text-[11px] text-[#646970]">{{ cms_date($log->created_at, 'g:i a') }}</span>
                            </td>
                            <td class="wp-table-cell align-top">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-[#2271b1] text-white flex items-center justify-center text-[11px] font-bold">
                                        {{ strtoupper(substr($log->user->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-medium block text-[#2271b1]">{{ $log->user->name ?? 'System' }}</span>
                                        <span class="text-[10px] text-[#646970]">{{ $log->user->email ?? '' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="wp-table-cell align-top">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider
                                    {{ $log->action === 'created' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $log->action === 'updated' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $log->action === 'deleted' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $log->action === 'settings_updated' ? 'bg-purple-100 text-purple-700' : '' }}
                                    {{ !in_array($log->action, ['created', 'updated', 'deleted', 'settings_updated']) ? 'bg-gray-100 text-gray-700' : '' }}
                                ">
                                    {{ str_replace('_', ' ', $log->action) }}
                                </span>
                            </td>
                            <td class="wp-table-cell align-top">
                                <p class="text-[14px] text-[#2c3338] mb-1 font-medium">{{ $log->description }}</p>
                                <div class="invisible group-hover:visible mt-1 text-[13px] space-x-1">
                                    <button type="button" onclick="deleteSingleLog({{ $log->id }})" class="text-[#b32d2e] hover:text-[#8a2424] hover:underline cursor-pointer">Delete</button>
                                </div>
                                @if(!empty($log->model_type))
                                    <span class="text-[11px] text-[#646970] italic block mt-1">
                                        {{ class_basename($log->model_type) }} #{{ $log->model_id }}
                                    </span>
                                @endif
                            </td>
                            <td class="wp-table-cell align-top">
                                <div class="flex flex-col gap-1">
                                    @if($log->country_code)
                                        <div class="flex items-center gap-1.5" title="{{ $log->country }}">
                                            <span class="text-[12px] font-medium text-[#2c3338]">{{ $log->country }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-1.5 text-[#646970]">
                                            <span class="text-[12px]">Local/Unknown</span>
                                        </div>
                                    @endif
                                    <code class="text-[11px] bg-[#f0f0f1] px-1 py-0.5 rounded w-fit text-[#646970]">{{ $log->ip_address }}</code>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-[#646970] italic">No activity logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th class="wp-table-header w-[40px] text-center border-t"><input type="checkbox" id="select-all-logs-footer" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                        <th class="wp-table-header w-[150px] border-t">Date</th>
                        <th class="wp-table-header w-[150px] border-t">User</th>
                        <th class="wp-table-header w-[120px] border-t">Action</th>
                        <th class="wp-table-header border-t">Description</th>
                        <th class="wp-table-header w-[180px] border-t">Location & IP</th>
                    </tr>
                </tfoot>
            </table>
        </form>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 mt-2 gap-2">
            <div class="flex items-center space-x-2">
                <select id="bulk_action_bottom" class="wp-input py-0 h-[30px] text-[13px]">
                    <option value="">Bulk actions</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" onclick="submitBulkAction('bulk_action_bottom')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
            </div>
            
            <x-cms-dashboard::admin.pagination :paginator="$logs" />
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="delete-confirm-modal" class="fixed inset-0 z-[9999] hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-2xl w-full max-w-md transform transition-all animate-in fade-in zoom-in duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-center text-[#1d2327] mb-2" id="modal-title">Confirm Deletion</h3>
                    <p class="text-sm text-center text-[#646970] mb-6" id="modal-message">Are you sure you want to delete these activity logs? This action cannot be undone.</p>
                    <div class="flex justify-center gap-3">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-[#2c3338] bg-white border border-[#c3c4c7] rounded hover:bg-[#f6f7f7] transition-colors">
                            Cancel
                        </button>
                        <button type="button" id="confirm-delete-btn" class="px-4 py-2 text-sm font-medium text-white bg-[#d63638] rounded hover:bg-[#b32d2e] transition-colors">
                            Delete Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('select-all-logs');
                const selectAllFooter = document.getElementById('select-all-logs-footer');
                const logCheckboxes = document.querySelectorAll('.log-checkbox');

                function toggleAll(checked) {
                    logCheckboxes.forEach(checkbox => {
                        checkbox.checked = checked;
                    });
                    if (selectAll) selectAll.checked = checked;
                    if (selectAllFooter) selectAllFooter.checked = checked;
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        toggleAll(this.checked);
                    });
                }

                if (selectAllFooter) {
                    selectAllFooter.addEventListener('change', function() {
                        toggleAll(this.checked);
                    });
                }

                // Update select-all state based on individual checkboxes
                logCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(logCheckboxes).every(cb => cb.checked);
                        const someChecked = Array.from(logCheckboxes).some(cb => cb.checked);
                        
                        if (selectAll) {
                            selectAll.checked = allChecked;
                            selectAll.indeterminate = someChecked && !allChecked;
                        }
                        if (selectAllFooter) {
                            selectAllFooter.checked = allChecked;
                            selectAllFooter.indeterminate = someChecked && !allChecked;
                        }
                    });
                });

                let pendingAction = null;
                let pendingSelectId = null;
                let pendingLogId = null;

                window.closeDeleteModal = function() {
                    document.getElementById('delete-confirm-modal').classList.add('hidden');
                    pendingAction = null;
                    pendingSelectId = null;
                    pendingLogId = null;
                };

                window.submitBulkAction = function(selectId) {
                    const action = document.getElementById(selectId).value;
                    if (!action) return;
                    
                    const selected = document.querySelectorAll('.log-checkbox:checked');
                    if (selected.length === 0) {
                        window.showToast('Please select at least one item.', 'warning');
                        return;
                    }

                    pendingAction = 'bulk';
                    pendingSelectId = selectId;
                    
                    document.getElementById('modal-title').innerText = 'Bulk Delete Logs';
                    document.getElementById('modal-message').innerText = `Are you sure you want to delete ${selected.length} selected logs? This action cannot be undone.`;
                    document.getElementById('delete-confirm-modal').classList.remove('hidden');
                };

                window.deleteSingleLog = function(id) {
                    pendingAction = 'single';
                    pendingLogId = id;

                    document.getElementById('modal-title').innerText = 'Delete Log';
                    document.getElementById('modal-message').innerText = 'Are you sure you want to delete this activity log? This action cannot be undone.';
                    document.getElementById('delete-confirm-modal').classList.remove('hidden');
                };

                document.getElementById('confirm-delete-btn').addEventListener('click', function() {
                    const form = document.getElementById('main-logs-form');
                    
                    if (pendingAction === 'bulk') {
                        const actionValue = document.getElementById(pendingSelectId).value;
                        document.getElementById('hidden-bulk-action').value = actionValue;
                        form.submit();
                    } else if (pendingAction === 'single') {
                        document.getElementById('hidden-bulk-action').value = 'delete';
                        // Uncheck all and check only this one
                        document.querySelectorAll('.log-checkbox').forEach(cb => cb.checked = false);
                        const checkbox = form.querySelector(`.log-checkbox[value="${pendingLogId}"]`);
                        if (checkbox) checkbox.checked = true;
                        form.submit();
                    }
                });
            });
        </script>
    @endpush
</x-cms-dashboard::layouts.admin>
