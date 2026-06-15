<x-falcon-cms::layouts.admin>
    <x-slot name="title">Languages - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-6">Languages</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Left: Add New Language & Settings -->
            <div class="md:col-span-1 space-y-8">
                <div>
                    <h2 class="text-[14px] font-bold text-[#1d2327] mb-3">Switcher Settings</h2>
                    <div class="bg-white p-4 border border-gray-200 rounded-sm shadow-sm">
                        <form action="{{ route('admin.languages.settings.update') }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Switcher Display Mode</label>
                                <select name="lang_switcher_display" class="w-full text-[13px] border-gray-300 rounded-sm focus:ring-[#2271b1]">
                                    <option value="both" {{ $displayMode == 'both' ? 'selected' : '' }}>Flag and Name</option>
                                    <option value="text_only" {{ $displayMode == 'text_only' ? 'selected' : '' }}>Text Only (Name)</option>
                                    <option value="flag_only" {{ $displayMode == 'flag_only' ? 'selected' : '' }}>Flag Only</option>
                                    <option value="code_only" {{ $displayMode == 'code_only' ? 'selected' : '' }}>ISO Code Only</option>
                                </select>
                            </div>
                            <button type="submit" class="wp-btn-primary w-full">Save Settings</button>
                        </form>
                    </div>
                </div>

                <div>
                    <h2 class="text-[14px] font-bold text-[#1d2327] mb-3">Add New Language</h2>
                    <div class="bg-white p-4 border border-gray-200 rounded-sm shadow-sm">
                        <form action="{{ route('admin.languages.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="sync_mode" value="1">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 max-h-[350px] overflow-y-auto mb-4 border border-gray-100 p-3 rounded-sm">
                                @foreach($topCountries as $country)
                                    @php 
                                        $isSelected = $languages->contains('code', $country['code']);
                                    @endphp
                                    <label class="flex items-center group cursor-pointer {{ $isSelected ? 'opacity-50 cursor-not-allowed' : '' }}">
                                        <input type="checkbox" name="countries[]" value="{{ json_encode($country) }}" 
                                               {{ $isSelected ? 'checked disabled' : '' }}
                                               class="mr-2 rounded-sm border-gray-300 text-[#2271b1] focus:ring-[#2271b1]">
                                        <span class="text-[16px] mr-1">{{ $country['flag'] }}</span>
                                        <span class="text-[12px] text-[#2c3338] group-hover:text-black truncate">
                                            {{ $country['name'] }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="wp-btn-primary w-full">Enable Selected Languages</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Languages List -->
            <div class="md:col-span-2">
                <h2 class="text-[14px] font-bold text-[#1d2327] mb-3">Active Languages</h2>
                <table class="wp-list-table w-full bg-white border border-[#c3c4c7] border-collapse">
                    <thead>
                        <tr class="bg-white border-b border-[#c3c4c7]">
                            <th class="text-left p-2 text-[13px] font-bold text-[#2c3338]">Flag</th>
                            <th class="text-left p-2 text-[13px] font-bold text-[#2c3338]">Name</th>
                            <th class="text-left p-2 text-[13px] font-bold text-[#2c3338]">Code</th>
                            <th class="text-left p-2 text-[13px] font-bold text-[#2c3338]">Default</th>
                            <th class="text-right p-2 text-[13px] font-bold text-[#2c3338]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($languages as $lang)
                        <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7]">
                            <td class="p-2 text-[20px]">{{ $lang->flag }}</td>
                            <td class="p-2 text-[13px] font-bold text-[#2271b1]">{{ $lang->name }}</td>
                            <td class="p-2 text-[13px] text-gray-600"><code>{{ $lang->code }}</code></td>
                            <td class="p-2 text-[13px]">
                                @if($lang->is_default)
                                    <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase">Default</span>
                                @else
                                    <form action="{{ route('admin.languages.set-default', $lang->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-[#2271b1] hover:underline text-[12px]">Set as default</button>
                                    </form>
                                @endif
                            </td>
                            <td class="p-2 text-right">
                                @if(!$lang->is_default)
                                <form id="delete-lang-{{ $lang->id }}" action="{{ route('admin.languages.destroy', $lang->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="confirmLangDelete({{ $lang->id }})" class="text-red-600 hover:underline text-[12px]">Delete</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        window.confirmLangDelete = async function(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Language',
                message: 'Are you sure you want to disable and delete this language? This will remove all translations for this language.',
                confirmText: 'Delete Language',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-lang-${id}`).submit();
            }
        };
    </script>
</x-falcon-cms::layouts.admin>
