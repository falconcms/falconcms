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
                                    @if(!empty($p['settings_url']))
                                        <a href="{{ $p['settings_url'] }}" class="text-[#2271b1] hover:underline">Settings</a>
                                        <span class="text-[#c3c4c7]">|</span>
                                    @endif
                                    <a href="#" onclick="event.preventDefault();submitSingle('{{ route('admin.plugins.deactivate', $slug) }}')" class="text-[#2271b1] hover:underline">Deactivate</a>
                                @elseif(!empty($p['missing_dependencies']))
                                    {{-- Activating would only be refused: say what it needs instead. --}}
                                    <span class="text-[#996800]">Requires {{ implode(', ', $p['missing_dependencies']) }} (install and activate it first)</span>
                                    @if(empty($p['bundled']))
                                        <span class="text-[#c3c4c7]">|</span>
                                        <a href="#" onclick="event.preventDefault();confirmDelete(this)" data-url="{{ route('admin.plugins.destroy', $slug) }}" data-name="{{ $p['name'] ?? $slug }}" data-slug="{{ $slug }}" data-keeps="{{ is_string($p['data'] ?? null) ? $p['data'] : '' }}" class="text-[#b32d2e] hover:underline">Delete</a>
                                    @endif
                                @else
                                    <a href="#" onclick="event.preventDefault();submitSingle('{{ route('admin.plugins.activate', $slug) }}')" class="text-[#2271b1] hover:underline">Activate</a>
                                    @if(empty($p['bundled']))
                                        <span class="text-[#c3c4c7]">|</span>
                                        <a href="#" onclick="event.preventDefault();confirmDelete(this)" data-url="{{ route('admin.plugins.destroy', $slug) }}" data-name="{{ $p['name'] ?? $slug }}" data-slug="{{ $slug }}" data-keeps="{{ is_string($p['data'] ?? null) ? $p['data'] : '' }}" class="text-[#b32d2e] hover:underline">Delete</a>
                                    @endif
                                @endif
                                {{-- A bundled plugin ships with the CMS and updates with it: no delete, no separate update. --}}
                                @if(empty($p['bundled']) && $p['update_available'])
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
    {{-- Uninstall: the data stays unless "delete its data" is ticked and the slug typed (PluginController::destroy checks both). --}}
    <div id="uninstall-dialog" class="hidden fixed inset-0 z-[100000] bg-black/50 items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="uninstall-title">
        <form id="delete-action-form" method="POST" class="bg-white rounded-sm shadow-xl w-full max-w-[520px] p-6 text-[13px] text-[#1d2327]">
            @csrf @method('DELETE')
            <h2 id="uninstall-title" class="text-[17px] font-semibold mb-3">Uninstall <span data-u="name"></span>?</h2>
            <p class="mb-2">Its files are removed. <strong>Its data is kept</strong>: activate it again (or install it again) and everything is as you left it.</p>
            <p class="mb-4 text-[#646970]">A plugin that comes with FalconCMS Pro is put back in this list on the next page load, ready to activate.</p>
            <label class="flex items-start gap-2 p-3 border border-[#dcdcde] rounded-sm bg-[#fcf0f1] cursor-pointer">
                <input type="checkbox" name="delete_data" value="1" class="mt-0.5" onchange="uninstallSync()">
                <span><strong class="text-[#b32d2e]">Also delete all of its data.</strong> <span data-u="keeps"></span> This cannot be undone: only a backup brings it back.</span>
            </label>
            <div data-u="confirm" class="hidden mt-3">
                <label for="uninstall-confirm" class="block mb-1">Type <code class="bg-[#f0f0f1] px-1" data-u="slug"></code> to confirm:</label>
                <input id="uninstall-confirm" name="confirm_slug" type="text" autocomplete="off" class="wp-input w-full" oninput="uninstallSync()">
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" class="wp-btn-secondary" onclick="uninstallClose()">Cancel</button>
                <button type="submit" data-u="go" class="px-3 h-[32px] rounded-sm bg-[#b32d2e] text-white font-semibold disabled:opacity-40 disabled:cursor-not-allowed">Uninstall</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function submitSingle(url) {
            const f = document.getElementById('single-action-form');
            f.action = url; f.submit();
        }
        function confirmDelete(link) {
            const d = document.getElementById('uninstall-dialog'), f = document.getElementById('delete-action-form'), u = (k) => f.querySelector('[data-u="' + k + '"]');
            f.action = link.dataset.url; f.reset();
            u('name').textContent = link.dataset.name;
            u('slug').textContent = link.dataset.slug;
            u('keeps').textContent = link.dataset.keeps ? 'That is: ' + link.dataset.keeps + '.' : 'Its settings and its own tables.';
            f.dataset.slug = link.dataset.slug;
            uninstallSync();
            d.classList.remove('hidden'); d.classList.add('flex');
            f.querySelector('[data-u="go"]').focus();
        }
        // deleting the data needs the slug typed; keeping it needs nothing more
        function uninstallSync() {
            const f = document.getElementById('delete-action-form'), wipe = f.delete_data.checked, go = f.querySelector('[data-u="go"]');
            f.querySelector('[data-u="confirm"]').classList.toggle('hidden', !wipe);
            go.disabled = wipe && f.confirm_slug.value.trim() !== f.dataset.slug;
            go.textContent = wipe ? 'Uninstall and delete its data' : 'Uninstall';
        }
        function uninstallClose() { const d = document.getElementById('uninstall-dialog'); d.classList.add('hidden'); d.classList.remove('flex'); }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') uninstallClose(); });
        function confirmBulk() {
            const form = document.getElementById('plugins-form');
            const action = form.querySelector('[name=action]').value;
            const checked = form.querySelectorAll('.plg-cb:checked').length;
            if (!action) { alert('Choose a bulk action.'); return false; }
            if (!checked) { alert('Select at least one plugin.'); return false; }
            if (action === 'delete') return confirm('Uninstall ' + checked + ' plugin(s)? Their files are removed; their data is kept, so activating them again brings everything back. (To delete the data of a plugin, uninstall it on its own.)');
            return true;
        }
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
