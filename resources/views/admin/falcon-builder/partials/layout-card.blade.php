@php
    /** @var array $layout  ['id','name','is_global','assigned'(slot=>Post|null),'conditions','conditions_ui'] */
    $lid = $layout['id'];
    $isGlobal = $layout['is_global'] ?? false;
    $summary = collect($layout['conditions_ui'] ?? [])->where('mode', 'include')->pluck('label')->all();
    $excludeCount = collect($layout['conditions_ui'] ?? [])->where('mode', 'exclude')->count();
@endphp

<div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,0.04)] rounded-sm overflow-hidden flex flex-col" data-layout-card id="card-{{ $lid }}">

    {{-- STATE: slot list --}}
    <div class="layout-view flex flex-col flex-1">
        <div class="bg-[#1d2327] px-5 py-4 flex items-center justify-between">
            @if($isGlobal)
                <h2 class="text-[15px] font-semibold text-white">{{ $layout['name'] }}</h2>
            @else
                <h2 class="text-[15px] font-semibold text-white layout-name cursor-text hover:bg-white/10 rounded-sm px-1 -mx-1" data-lid="{{ $lid }}" title="Click to rename" onclick="startRenameLayout(this)">{{ $layout['name'] }}</h2>
            @endif
            @unless($isGlobal)
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="openConditionsModal('{{ $lid }}')" title="Set conditions" class="text-white/70 hover:text-white"><span class="material-symbols-outlined text-[18px]">settings</span></button>
                    <button type="button" onclick="deleteLayout('{{ $lid }}', @js($layout['name']))" title="Delete layout" class="text-white/70 hover:text-[#ff8a8a]"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                </div>
            @endunless
        </div>

        <div class="p-4 space-y-2.5 flex-1">
            @foreach($slotMeta as $slot => $m)
                @php $sec = $layout['assigned'][$slot] ?? null; $slotActive = $layout['active'][$slot] ?? true; @endphp
                <div class="flex items-stretch border border-[#e2e4e7] rounded-sm overflow-hidden hover:border-[#2271b1] transition-colors" data-slot="{{ $slot }}">
                    <div class="flex items-center justify-center w-11 bg-[#f6f7f7] text-[#646970] border-r border-[#e2e4e7]">
                        <span class="material-symbols-outlined text-[20px]">{{ $m['icon'] }}</span>
                    </div>
                    @if($sec)
                        <a href="{{ route('admin.falcon-builder', $sec->id) }}" class="flex-1 flex flex-col justify-center px-3 py-1.5 leading-tight hover:text-[#2271b1]">
                            <span class="text-[13px] text-[#1d2327]">{{ $sec->title ?: $m['label'] }}</span>
                            <span class="slot-status text-[10px] uppercase tracking-wide {{ $slotActive ? 'text-[#00a32a]' : 'text-[#8c8f94]' }}" data-slot-label="{{ $m['label'] }}">{{ $slotActive ? 'Active' : 'Inactive' }} · {{ $m['label'] }}</span>
                        </a>
                        <div class="flex items-center gap-1.5 pr-2.5">
                            <label class="relative inline-flex items-center cursor-pointer" title="Turn this section on/off for this layout only">
                                <input type="checkbox" class="sr-only peer" {{ $slotActive ? 'checked' : '' }} onchange="slotToggle('{{ $lid }}', '{{ $slot }}', this)">
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#2271b1]"></div>
                            </label>
                            <button type="button" onclick="openPicker('{{ $lid }}', '{{ $slot }}')" title="Change section" class="text-[#646970] hover:text-[#2271b1]"><span class="material-symbols-outlined text-[20px]">swap_horiz</span></button>
                        </div>
                    @else
                        <button type="button" onclick="openPicker('{{ $lid }}', '{{ $slot }}')" class="flex-1 flex items-center px-3 py-2.5 text-[13px] text-[#646970] hover:text-[#2271b1] text-left">Select {{ $m['label'] }}</button>
                        <button type="button" onclick="openPicker('{{ $lid }}', '{{ $slot }}')" class="flex items-center pr-3 text-[#646970] hover:text-[#2271b1]"><span class="material-symbols-outlined text-[20px]">add</span></button>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="px-5 py-3 border-t border-[#f0f0f1] flex items-center gap-2 text-[12px] text-[#646970]">
            @if($isGlobal)
                <span class="material-symbols-outlined text-[16px] text-[#8c8f94]">public</span>
                Sections here appear globally.
            @elseif(count($summary))
                <span class="w-2 h-2 rounded-full bg-[#00a32a] flex-shrink-0"></span>
                <span class="truncate">{{ implode(', ', $summary) }}@if($excludeCount) · {{ $excludeCount }} excluded @endif</span>
            @else
                <span class="w-2 h-2 rounded-full bg-[#8c8f94] flex-shrink-0"></span>
                No condition selected
            @endif
        </div>
    </div>

    {{-- STATE: section picker --}}
    <div class="layout-picker hidden flex-col flex-1">
        <div class="bg-[#1d2327] px-4 py-3.5 flex items-center gap-2">
            <button type="button" onclick="closeState('{{ $lid }}')" class="text-white/80 hover:text-white" title="Back"><span class="material-symbols-outlined text-[20px] align-middle">arrow_back</span></button>
            <span class="picker-name text-[15px] font-semibold text-white">{{ $layout['name'] }}</span>
            <span class="picker-slot-label text-[12px] text-white/60 ml-auto"></span>
        </div>
        <div class="flex">
            <button type="button" data-tab="new" onclick="pickerTab('{{ $lid }}','new')" class="tab-btn-new flex-1 py-2.5 text-[14px] font-semibold text-white bg-[#2271b1]">New Section</button>
            <button type="button" data-tab="existing" onclick="pickerTab('{{ $lid }}','existing')" class="tab-btn-existing flex-1 py-2.5 text-[14px] font-semibold text-white bg-[#2c92e0]">Existing Section</button>
        </div>
        <div class="picker-new p-5">
            <input type="hidden" class="create-slot">
            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Section Name</label>
            <input type="text" class="create-name wp-input w-full mb-4" onkeydown="if(event.key==='Enter'){event.preventDefault();createSectionAjax('{{ $lid }}');}">
            <button type="button" onclick="createSectionAjax('{{ $lid }}')" class="w-full py-2.5 bg-[#2c92e0] hover:bg-[#2271b1] text-white text-[14px] font-semibold rounded-sm transition-colors">Create New Section</button>
        </div>
        <div class="picker-existing hidden flex-1">
            <ul class="existing-list divide-y divide-[#f0f0f1] max-h-[280px] overflow-y-auto"></ul>
            <p class="existing-empty hidden p-6 text-center text-[13px] text-[#8c8f94]">No sections yet. Create one from the “New Section” tab.</p>
        </div>
    </div>

    @unless($isGlobal)
        <form id="del-{{ $lid }}" action="{{ route('admin.falcon-builder.layout.delete') }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="id" value="{{ $lid }}">
        </form>
    @endunless
</div>
