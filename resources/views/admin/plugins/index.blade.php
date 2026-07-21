<x-falcon-cms::layouts.admin active-menu="plugins">
    <x-slot name="title">Plugins - FalconCMS</x-slot>

    <div class="mb-4 flex items-center">
        <h1 class="text-[23px] font-normal text-[#1d2327] inline-block mr-3">Plugins</h1>
        <a href="{{ route('admin.plugins.create') }}" class="wp-btn-outline">Add New</a>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('error') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-4">
        <div class="flex flex-wrap items-center text-[13px] text-[#646970]">
            <a href="{{ route('admin.plugins.index') }}" class="{{ !$status ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ $counts['all'] }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.plugins.index', ['status' => 'active']) }}" class="{{ $status === 'active' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Active <span class="text-[#646970]">({{ $counts['active'] }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.plugins.index', ['status' => 'inactive']) }}" class="{{ $status === 'inactive' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Inactive <span class="text-[#646970]">({{ $counts['inactive'] }})</span></a>
        </div>

        <form action="{{ route('admin.plugins.index') }}" method="GET" class="flex items-center space-x-1 w-full md:w-auto">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="text" name="s" value="{{ $search }}" class="wp-input h-[30px] flex-grow md:w-48" placeholder="">
            <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search Plugins</button>
        </form>
    </div>

    <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-4">
        <thead>
            <tr>
                <th class="wp-table-header text-left">Plugin</th>
                <th class="wp-table-header text-left w-[110px]">Version</th>
                <th class="wp-table-header text-left w-[130px]">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plugins as $slug => $p)
                <tr class="{{ $loop->even ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group align-top">
                    <td class="wp-table-cell align-top text-[14px] text-left">
                        <strong>
                            <span class="text-[#1d2327]">{{ $p['name'] ?? $slug }}</span>
                            @if($p['active']) <span class="font-normal text-[#00713f]"> — Active</span> @endif
                            @if($p['update_available']) <span class="font-normal text-[#996800]"> — Update available</span> @endif
                        </strong>
                        @if(!empty($p['description']))
                            <div class="text-[13px] text-[#50575e] mt-0.5 max-w-[560px]">{{ $p['description'] }}</div>
                        @endif
                        <div class="text-[12px] text-[#8a8f94] mt-1">
                            <code>{{ $slug }}</code>@if(!empty($p['author'])) · by {{ $p['author'] }} @endif
                        </div>

                        {{-- Row actions (WordPress-style, revealed on hover) --}}
                        <div class="invisible group-hover:visible mt-1.5 text-[13px] flex flex-wrap items-center gap-x-1 gap-y-1">
                            @if($p['active'])
                                <form action="{{ route('admin.plugins.deactivate', $slug) }}" method="POST" class="inline">@csrf
                                    <button class="text-[#2271b1] hover:underline cursor-pointer">Deactivate</button>
                                </form>
                            @else
                                <form action="{{ route('admin.plugins.activate', $slug) }}" method="POST" class="inline">@csrf
                                    <button class="text-[#2271b1] hover:underline cursor-pointer">Activate</button>
                                </form>
                            @endif

                            @if($p['update_available'])
                                <span class="text-[#c3c4c7]">|</span>
                                <form action="{{ route('admin.plugins.update', $slug) }}" method="POST" class="inline">@csrf
                                    <button class="text-[#996800] hover:underline cursor-pointer">Update</button>
                                </form>
                            @endif

                            <span class="text-[#c3c4c7]">|</span>
                            <form action="{{ route('admin.plugins.destroy', $slug) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Uninstall {{ $p['name'] ?? $slug }}? This deletes its files and rolls back its migrations.')">
                                @csrf @method('DELETE')
                                <button class="text-[#b32d2e] hover:text-[#8a2424] hover:underline cursor-pointer">Uninstall</button>
                            </form>
                        </div>
                    </td>
                    <td class="wp-table-cell align-top text-[13px] text-[#50575e]">{{ $p['version'] ?? '—' }}</td>
                    <td class="wp-table-cell align-top">
                        @if($p['active'])
                            <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#00713f]"><span class="w-2 h-2 rounded-full bg-[#00a854]"></span> Active</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[12px] text-[#646970]"><span class="w-2 h-2 rounded-full bg-[#c3c4c7]"></span> Inactive</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="wp-table-cell text-center text-[13px] text-[#646970] py-8">
                        No plugins found. <a href="{{ route('admin.plugins.create') }}" class="text-[#2271b1] hover:underline">Add one</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-falcon-cms::layouts.admin>
