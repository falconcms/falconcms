{{-- Reusable Icon picker (Font Awesome search + Solid/Regular/Brands + grid + preview).
     Vars: $key (setting key), $label (optional), $target (optional JS object path) --}}
@php
    $target = $target ?? 'editingElement.settings';
    $label  = $label  ?? 'Icon';
@endphp
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">{{ $label }}</label>
    <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-hidden">
        <div class="p-2 border-b border-slate-200">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                <input type="text" v-model="searchIconQuery" placeholder="Search icons..."
                       class="w-full pl-8 pr-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
        <div class="flex border-b border-slate-200 bg-slate-100/50">
            <button v-for="tab in ['Solid', 'Regular', 'Brands']" :key="tab"
                    @click="activeIconTab = tab"
                    :class="activeIconTab === tab ? 'text-[#0091ea] bg-white border-b-2 border-b-[#0091ea]' : 'text-slate-400 hover:text-slate-600'"
                    class="flex-1 py-2 text-[10px] font-bold uppercase transition-all">
                @{{ tab }}
            </button>
        </div>
        <div class="h-48 overflow-y-auto p-2 bg-white custom-scrollbar">
            <div class="grid grid-cols-5 gap-1.5">
                <button v-for="icon in filteredIcons" :key="icon"
                        @click="selectIcon({{ $target }}, icon, '{{ $key }}')"
                        :class="{{ $target }}.{{ $key }} === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                        class="aspect-square flex items-center justify-center rounded border transition-all p-1" :title="icon">
                    <i :class="[icon, 'text-base']"></i>
                </button>
            </div>
            <div v-if="filteredIcons.length === 0" class="py-10 text-center text-[10px] text-slate-400">No icons found</div>
        </div>
        <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center text-[#0091ea]">
                    <i :class="{{ $target }}.{{ $key }} || 'fas fa-star'"></i>
                </div>
                <span class="text-[10px] text-slate-500 font-medium truncate max-w-[120px]"
                      v-text="{{ $target }}.{{ $key }} || 'No icon selected'"></span>
            </div>
            <button v-if="{{ $target }}.{{ $key }}" @click="{{ $target }}.{{ $key }} = ''"
                    class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
        </div>
    </div>
</div>
