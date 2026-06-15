{{-- 1. Flex Grow --}}
<div>
    <div class="flex justify-between items-center mb-2">
        <label class="text-[12px] font-bold text-[#333]">Flex Grow</label>
        <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.flexGrow ?? 0 }}</span>
    </div>
    <div class="flex gap-3 items-center">
        <input type="range" v-model.number="editingElement.settings.flexGrow"
               min="0" max="10" class="flex-1 accent-[#0091ea]">
        <input type="number" v-model.number="editingElement.settings.flexGrow"
               min="0" max="10"
               class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- 2. Margin Top & Bottom --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-3">Margin</label>
    <div class="grid grid-cols-2 gap-3">
        @foreach([['marginTop','Top'],['marginBottom','Bottom']] as [$key,$lbl])
        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">{{ $lbl }}</label>
            <div class="flex gap-1">
                <input type="number" v-model.number="editingElement.settings.{{ $key }}"
                       class="flex-1 min-w-0 border border-slate-200 rounded-l px-2 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                <select v-model="editingElement.settings.{{ $key }}Unit"
                        class="border border-l-0 border-slate-200 rounded-r px-1 py-2 text-[12px] text-slate-500 bg-slate-50 focus:outline-none focus:border-[#0091ea]">
                    <option value="px">px</option>
                    <option value="%">%</option>
                    <option value="em">em</option>
                    <option value="rem">rem</option>
                </select>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- 3. Separator Width --}}
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Separator Width</label>
        <div class="flex gap-1">
            <input type="number" v-model.number="editingElement.settings.separatorWidth"
                   min="1"
                   class="flex-1 min-w-0 border border-slate-200 rounded-l px-2 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
            <select v-model="editingElement.settings.separatorWidthUnit"
                    class="border border-l-0 border-slate-200 rounded-r px-1 py-2 text-[12px] text-slate-500 bg-slate-50 focus:outline-none focus:border-[#0091ea]">
                <option value="%">%</option>
                <option value="px">px</option>
            </select>
        </div>
    </div>

    {{-- 4. Alignment --}}
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Alignment</label>
        <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
            <button v-for="opt in [{v:'left',i:'fa-align-left'},{v:'center',i:'fa-align-center'},{v:'right',i:'fa-align-right'}]"
                    :key="opt.v"
                    @click="editingElement.settings.alignment = opt.v"
                    :class="(editingElement.settings.alignment === opt.v || (!editingElement.settings.alignment && opt.v === 'center')) ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="flex-1 py-1.5 rounded transition-all flex items-center justify-center">
                <i :class="'fa ' + opt.i" class="text-xs"></i>
            </button>
        </div>
    </div>

    {{-- 5. Border Size --}}
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[12px] font-bold text-[#333]">Border Size</label>
            <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.borderSize ?? 1 }}px</span>
        </div>
        <div class="flex gap-3 items-center">
            <input type="range" v-model.number="editingElement.settings.borderSize"
                   min="1" max="20" class="flex-1 accent-[#0091ea]">
            <input type="number" v-model.number="editingElement.settings.borderSize"
                   min="1" max="20"
                   class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>

    {{-- 6. Separator Color --}}
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Separator Color</label>
            <button @click="editingElement.settings.separatorColor = ''" title="Reset"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'separatorColor')">
                <div :style="{ backgroundColor: editingElement.settings.separatorColor || '#cccccc' }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.separatorColor"
                       placeholder="#cccccc"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

