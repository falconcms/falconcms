<x-falcon-cms::layouts.admin active-menu="plugins">
    <x-slot name="title">Plugins - FalconCMS</x-slot>

    <div class="mb-3 flex items-center gap-3">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Plugins</h1>
        <a href="{{ route('admin.plugins.create') }}" class="wp-btn-outline">Add Plugin</a>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ session('error') }}</div>
    @endif

    {{-- Status tabs + search --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-3">
        <div class="flex flex-wrap items-center text-[13px] text-[#646970]">
            <a href="{{ route('admin.plugins.index') }}" class="{{ !$status ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ $counts['all'] }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.plugins.index', ['status' => 'active']) }}" class="{{ $status === 'active' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Active <span class="text-[#646970]">({{ $counts['active'] }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.plugins.index', ['status' => 'inactive']) }}" class="{{ $status === 'inactive' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Inactive <span class="text-[#646970]">({{ $counts['inactive'] }})</span></a>
            @if($counts['update'] > 0)
                <span class="mx-1 text-[#c3c4c7]">|</span>
                <a href="{{ route('admin.plugins.index', ['status' => 'update']) }}" class="{{ $status === 'update' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Update Available <span class="text-[#646970]">({{ $counts['update'] }})</span></a>
            @endif
        </div>

        <form action="{{ route('admin.plugins.index') }}" method="GET" class="flex items-center gap-1">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <label class="text-[13px] text-[#646970] mr-1">Search installed plugins</label>
            <input type="text" name="s" value="{{ $search }}" class="wp-input h-[30px] w-56">
            <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search</button>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.plugins.bulk') }}" id="plugins-form">
        @csrf

        {{-- Bulk actions bar --}}
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-1">
                <select name="action" class="wp-input py-0 h-[30px] text-[13px]">
                    <option value="">Bulk actions</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="update">Update</option>
                    <option value="delete">Uninstall</option>
                </select>
                <button type="submit" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]"
                        onclick="return confirmBulk()">Apply</button>
            </div>
            <span class="text-[13px] text-[#646970]">{{ count($plugins) }} items</span>
        </div>

        <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-4 text-[13px]">
            <thead>
                <tr>
                    <th class="wp-table-header w-8 text-center pb-0">
                        <input type="checkbox" onclick="document.querySelectorAll('.plg-cb').forEach(c=>c.checked=this.checked)"
                               class="rounded-sm border-[#8c8f94] text-[#2271b1]">
                    </th>
                    <th class="wp-table-header text-left w-[280px]">Plugin</th>
                    <th class="wp-table-header text-left">Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plugins as $slug => $p)
                    @php $active = $p['active']; @endphp
                    {{-- Main row --}}
                    <tr class="{{ $active ? 'bg-[#f0f6fc]' : 'bg-[#fff]' }} {{ $p['update_available'] ? '' : 'border-b border-[#f0f0f1]' }}">
                        <td class="wp-table-cell text-center align-top {{ $active ? 'border-l-4 border-[#2271b1]' : '' }}">
                            <input type="checkbox" name="slugs[]" value="{{ $slug }}" class="plg-cb rounded-sm border-[#8c8f94] text-[#2271b1] mt-1">
                        </td>
                        <td class="wp-table-cell align-top">
                            <strong class="text-[#1d2327] text-[14px]">{{ $p['name'] ?? $slug }}</strong>
                            <div class="mt-1 text-[13px] flex flex-wrap items-center gap-x-1">
                                @if($active)
                                    <a href="#" onclick="event.preventDefault();submitSingle('{{ route('admin.plugins.deactivate', $slug) }}')" class="text-[#2271b1] hover:underline">Deactivate</a>
                                @else
                                    <a href="#" onclick="event.preventDefault();submitSingle('{{ route('admin.plugins.activate', $slug) }}')" class="text-[#2271b1] hover:underline">Activate</a>
                                    <span class="text-[#c3c4c7]">|</span>
                                    <a href="#" onclick="event.preventDefault();confirmDelete('{{ route('admin.plugins.destroy', $slug) }}', '{{ $p['name'] ?? $slug }}')" class="text-[#b32d2e] hover:underline">Delete</a>
                                @endif
                                @if($p['update_available'])
                                    <span class="text-[#c3c4c7]">|</span>
                                    <a href="#" onclick="event.preventDefault();submitSingle('{{ route('admin.plugins.update', $slug) }}')" class="text-[#996800] hover:underline">Update Now</a>
                                @endif
                            </div>
                        </td>
                        <td class="wp-table-cell align-top">
                            @if(!empty($p['description']))
                                <p class="text-[#1d2327]">{{ $p['description'] }}</p>
                            @endif
                            <p class="text-[#646970] mt-1">
                                Version {{ $p['version'] ?? '—' }}
                                @if(!empty($p['author'])) | By {{ $p['author'] }} @endif
                                | <code class="text-[#2271b1]">{{ $slug }}</code>
                            </p>
                        </td>
                    </tr>

                    {{-- Update-available notice row (WordPress style) --}}
                    @if($p['update_available'])
                        <tr class="border-b border-[#f0f0f1]">
                            <td class="{{ $active ? 'border-l-4 border-[#2271b1]' : '' }}"></td>
                            <td colspan="2" class="px-3 pb-3">
                                <div class="bg-[#fcf9e8] border-l-4 border-[#dba617] px-3 py-2 text-[13px] text-[#1d2327] flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[17px] text-[#dba617]">sync</span>
                                    <span>
                                        There is a new version of <strong>{{ $p['name'] ?? $slug }}</strong> available
                                        (installed {{ $p['installed_version'] }}, disk {{ $p['version'] }}).
                                        <a href="#" onclick="event.preventDefault();submitSingle('{{ route('admin.plugins.update', $slug) }}')" class="text-[#2271b1] hover:underline">Update now</a>.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="3" class="wp-table-cell text-center text-[#646970] py-8">
                            No plugins found. <a href="{{ route('admin.plugins.create') }}" class="text-[#2271b1] hover:underline">Add one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </form>

    {{-- Hidden helper forms for single-plugin actions --}}
    <form id="single-action-form" method="POST" class="hidden">@csrf</form>
    <form id="delete-action-form" method="POST" class="hidden">@csrf @method('DELETE')</form>

    @push('scripts')
    <script>
        function submitSingle(url) {
            const f = document.getElementById('single-action-form');
            f.action = url; f.submit();
        }
        function confirmDelete(url, name) {
            if (!confirm('Uninstall ' + name + '? This deletes its files and rolls back its migrations.')) return;
            const f = document.getElementById('delete-action-form');
            f.action = url; f.submit();
        }
        function confirmBulk() {
            const form = document.getElementById('plugins-form');
            const action = form.querySelector('[name=action]').value;
            const checked = form.querySelectorAll('.plg-cb:checked').length;
            if (!action) { alert('Choose a bulk action.'); return false; }
            if (!checked) { alert('Select at least one plugin.'); return false; }
            if (action === 'delete') return confirm('Uninstall ' + checked + ' plugin(s)? Files and data will be removed.');
            return true;
        }
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
