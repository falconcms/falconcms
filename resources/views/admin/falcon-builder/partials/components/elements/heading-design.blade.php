{{-- Heading — Design tab.

     Every key below is one the renderer already reads. The element's typography is flat
     — fontSize, fontWeight, lineHeight, letterSpacing, textTransform, color — rather than
     prefixed the way the shared typography control writes, so the fields are spelled out
     here instead of including that partial. Renaming them to fit the shared control would
     have been tidier and would have silently blanked the typography on every heading
     already on a site. --}}

{{-- ══ TYPOGRAPHY ══ --}}
<div class="space-y-4">
    <label class="text-[12px] font-bold text-[#333] block">Typography</label>

    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
        <font-select v-model="editingElement.settings.fontFamily" @change="loadBuilderFont($event)"
                     :font-groups="builderFontGroups" :theme-font="themeHeadingFont"></font-select>
    </div>

    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
        <select v-model="editingElement.settings.fontWeight"
                class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            <option value="">Default</option>
            <option v-for="w in ['100','200','300','400','500','600','700','800','900']" :key="w" :value="w" v-text="w"></option>
        </select>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Font Size</label>
            <div class="flex gap-1">
                <input type="number" v-model.number="editingElement.settings.fontSize" placeholder="30"
                       class="w-full min-w-0 border border-slate-200 rounded-l px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                <select v-model="editingElement.settings.fontSizeUnit"
                        class="border border-l-0 border-slate-200 rounded-r px-1 py-2 text-[11px] text-slate-500 bg-slate-50 focus:outline-none">
                    <option value="px">px</option>
                    <option value="em">em</option>
                    <option value="rem">rem</option>
                    <option value="%">%</option>
                </select>
            </div>
        </div>
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Hei...</label>
            <input type="text" v-model="editingElement.settings.lineHeight" placeholder="1.2"
                   class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
            <div class="flex gap-1">
                <input type="number" v-model.number="editingElement.settings.letterSpacing" placeholder="0"
                       class="w-full min-w-0 border border-slate-200 rounded-l px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                <select v-model="editingElement.settings.letterSpacingUnit"
                        class="border border-l-0 border-slate-200 rounded-r px-1 py-2 text-[11px] text-slate-500 bg-slate-50 focus:outline-none">
                    <option value="px">px</option>
                    <option value="em">em</option>
                </select>
            </div>
        </div>
    </div>

    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button v-for="opt in [{ v: 'none', t: 'Normal' }, { v: 'uppercase', t: 'AB' }, { v: 'lowercase', t: 'ab' }, { v: 'capitalize', t: 'Ab' }]"
                    :key="opt.v"
                    @click="editingElement.settings.textTransform = opt.v"
                    :class="(editingElement.settings.textTransform || 'none') === opt.v ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 last:border-r-0 transition-all"
                    v-text="opt.t"></button>
        </div>
    </div>
</div>

{{-- ══ COLOUR ══ --}}
<div class="pt-4 border-t border-slate-50">
    <div class="flex justify-between items-center mb-2">
        <label class="text-[12px] font-bold text-[#333]">Color</label>
        <button @click="editingElement.settings.color = ''" title="Back to the theme's colour"
                class="text-slate-300 hover:text-red-500 transition-colors">
            <i class="fa fa-undo text-[10px]"></i>
        </button>
    </div>
    <div class="flex gap-2 items-center">
        <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
             @click="openColorPicker($event, editingElement.settings, 'color')">
            <div :style="{ backgroundColor: editingElement.settings.color || '#222222' }" class="w-full h-full"></div>
        </div>
        <input type="text" v-model="editingElement.settings.color" placeholder="#222222"
               class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- ══ SPACING ══ --}}
@foreach([['margin', 'Margin'], ['padding', 'Padding']] as [$box, $boxLabel])
<div class="pt-4 border-t border-slate-50">
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $boxLabel }}</label>
        <div class="flex gap-1 items-center">
            <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, '{{ $box }}' + s, device, ''))"
                    title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
            <div class="relative inline-block">
                <button @click="activeResponsiveMenu = activeResponsiveMenu === 'hd{{ $box }}' ? null : 'hd{{ $box }}'"
                        class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                    <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                    <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                </button>
                <div v-show="activeResponsiveMenu === 'hd{{ $box }}'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                    <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)">
                        <i class="fa fa-desktop text-[11px]"></i>
                    </button>
                    <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)">
                        <i class="fa fa-tablet-alt text-[11px]"></i>
                    </button>
                    <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)">
                        <i class="fa fa-mobile-alt text-[11px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-4 gap-2">
        @foreach(['Top', 'Right', 'Bottom', 'Left'] as $side)
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">{{ $side }}</label>
            <input type="number"
                   :value="getResponsiveVal(editingElement.settings, '{{ $box }}{{ $side }}', device)"
                   @input="setResponsiveVal(editingElement.settings, '{{ $box }}{{ $side }}', device, $event.target.value === '' ? '' : Number($event.target.value))"
                   class="w-full border border-slate-200 rounded px-1.5 py-2 text-[12px] text-center text-slate-600 focus:outline-none focus:border-[#0091ea]">
        </div>
        @endforeach
    </div>
    <select :value="getResponsiveVal(editingElement.settings, '{{ $box }}TopUnit', device) || 'px'"
            @change="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, '{{ $box }}' + s + 'Unit', device, $event.target.value))"
            class="mt-2 w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-slate-500 bg-slate-50 focus:outline-none focus:border-[#0091ea]">
        <option value="px">px</option>
        <option value="%">%</option>
        <option value="em">em</option>
        <option value="rem">rem</option>
    </select>
</div>
@endforeach
