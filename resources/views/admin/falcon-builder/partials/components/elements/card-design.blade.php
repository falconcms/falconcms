{{-- 1. Layout --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Layout</label>
    <div class="grid grid-cols-4 gap-1.5">
        <button v-for="opt in [{v:'grid',i:'fa-th-large',l:'Grid'},{v:'list',i:'fa-list',l:'List'},{v:'masonry',i:'fa-columns',l:'Masonry'},{v:'carousel',i:'fa-film',l:'Carousel'}]"
                :key="opt.v"
                @click="editingElement.settings.layout = opt.v"
                :class="editingElement.settings.layout === opt.v ? 'bg-[#2271b1] text-white border-[#0091ea]' : 'bg-white text-slate-500 border-slate-200 hover:border-[#0091ea]/50'"
                class="flex flex-col items-center justify-center gap-1 py-2.5 border rounded transition-all text-center">
            <i :class="'fa ' + opt.i" class="text-sm"></i>
            <span class="text-[10px] font-bold">@{{ opt.l }}</span>
        </button>
    </div>
</div>

{{-- Carousel Options --}}
<div v-if="editingElement.settings.layout === 'carousel'" class="pt-4 border-t border-slate-50">
    <label class="text-[12px] font-bold text-[#333] block mb-3">Carousel Options</label>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Autoplay</span>
            <button @click="editingElement.settings.carousel_autoplay = !editingElement.settings.carousel_autoplay"
                    :class="editingElement.settings.carousel_autoplay ? 'bg-[#2271b1]' : 'bg-slate-200'"
                    class="relative w-9 h-5 rounded-full transition-colors">
                <span :class="editingElement.settings.carousel_autoplay ? 'translate-x-4' : 'translate-x-0.5'"
                      class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform block"></span>
            </button>
        </div>
        <div v-if="editingElement.settings.carousel_autoplay" class="pl-1">
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Speed</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_autoplay_speed ?? 3000 }}ms</span>
            </div>
            <div class="flex gap-3 items-center">
                <input type="range" v-model.number="editingElement.settings.carousel_autoplay_speed"
                       min="500" max="10000" step="500" class="flex-1 accent-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings.carousel_autoplay_speed"
                       min="500" max="10000" step="500"
                       class="w-20 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Show Arrows</span>
            <button @click="editingElement.settings.carousel_arrows = !(editingElement.settings.carousel_arrows ?? true)"
                    :class="(editingElement.settings.carousel_arrows ?? true) ? 'bg-[#2271b1]' : 'bg-slate-200'"
                    class="relative w-9 h-5 rounded-full transition-colors">
                <span :class="(editingElement.settings.carousel_arrows ?? true) ? 'translate-x-4' : 'translate-x-0.5'"
                      class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform block"></span>
            </button>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Show Dots</span>
            <button @click="editingElement.settings.carousel_dots = !(editingElement.settings.carousel_dots ?? true)"
                    :class="(editingElement.settings.carousel_dots ?? true) ? 'bg-[#2271b1]' : 'bg-slate-200'"
                    class="relative w-9 h-5 rounded-full transition-colors">
                <span :class="(editingElement.settings.carousel_dots ?? true) ? 'translate-x-4' : 'translate-x-0.5'"
                      class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform block"></span>
            </button>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Infinite Loop</span>
            <button @click="editingElement.settings.carousel_loop = !editingElement.settings.carousel_loop"
                    :class="editingElement.settings.carousel_loop ? 'bg-[#2271b1]' : 'bg-slate-200'"
                    class="relative w-9 h-5 rounded-full transition-colors">
                <span :class="editingElement.settings.carousel_loop ? 'translate-x-4' : 'translate-x-0.5'"
                      class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform block"></span>
            </button>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Equal Height</span>
            <button @click="editingElement.settings.carousel_equal_height = !editingElement.settings.carousel_equal_height"
                    :class="editingElement.settings.carousel_equal_height ? 'bg-[#2271b1]' : 'bg-slate-200'"
                    class="relative w-9 h-5 rounded-full transition-colors">
                <span :class="editingElement.settings.carousel_equal_height ? 'translate-x-4' : 'translate-x-0.5'"
                      class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform block"></span>
            </button>
        </div>
    </div>
</div>

{{-- Items Per Slide (carousel only) --}}
<div v-if="editingElement.settings.layout === 'carousel'" class="pt-4 border-t border-slate-50">
    <label class="text-[12px] font-bold text-[#333] block mb-3">Items Per Slide</label>
    <div class="flex flex-col gap-4">

        {{-- Desktop --}}
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <i class="fa fa-desktop mr-1"></i>Desktop
                </label>
                <span class="text-[12px] font-bold text-[#0091ea]">@{{ editingElement.settings.items_per_slide ?? 1 }}</span>
            </div>
            <div class="flex gap-2 items-center">
                <input type="range" v-model.number="editingElement.settings.items_per_slide"
                       min="1" max="6" step="1" class="flex-1 accent-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings.items_per_slide"
                       min="1" max="6"
                       class="w-12 border border-slate-200 rounded px-1 py-1.5 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        {{-- Tablet --}}
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <i class="fa fa-tablet-alt mr-1"></i>Tablet
                </label>
                <span class="text-[12px] font-bold"
                      :class="(editingElement.settings.items_per_slide_tablet || 0) === 0 ? 'text-slate-300' : 'text-[#0091ea]'">
                    @{{ (editingElement.settings.items_per_slide_tablet || 0) === 0 ? '= auto' : editingElement.settings.items_per_slide_tablet }}
                </span>
            </div>
            <div class="flex gap-2 items-center">
                <input type="range" v-model.number="editingElement.settings.items_per_slide_tablet"
                       min="0" max="6" step="1" class="flex-1 accent-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings.items_per_slide_tablet"
                       min="0" max="6"
                       class="w-12 border border-slate-200 rounded px-1 py-1.5 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <p class="text-[10px] text-slate-300 mt-1">0 = auto (2 per slide on tablet)</p>
        </div>

        {{-- Mobile --}}
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <i class="fa fa-mobile-alt mr-1"></i>Mobile
                </label>
                <span class="text-[12px] font-bold"
                      :class="(editingElement.settings.items_per_slide_mobile || 0) === 0 ? 'text-slate-300' : 'text-[#0091ea]'">
                    @{{ (editingElement.settings.items_per_slide_mobile || 0) === 0 ? '= auto' : editingElement.settings.items_per_slide_mobile }}
                </span>
            </div>
            <div class="flex gap-2 items-center">
                <input type="range" v-model.number="editingElement.settings.items_per_slide_mobile"
                       min="0" max="6" step="1" class="flex-1 accent-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings.items_per_slide_mobile"
                       min="0" max="6"
                       class="w-12 border border-slate-200 rounded px-1 py-1.5 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <p class="text-[10px] text-slate-300 mt-1">0 = auto (1 per slide on mobile)</p>
        </div>

    </div>
</div>

{{-- Arrow Style (carousel only) --}}
<div v-if="editingElement.settings.layout === 'carousel' && (editingElement.settings.carousel_arrows ?? true)" class="pt-4 border-t border-slate-50">
    <label class="text-[12px] font-bold text-[#333] block mb-3">Arrow Style</label>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Background</span>
            <div class="checkerboard rounded overflow-hidden w-9 h-7 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'carousel_arrow_bg')">
                <div :style="{ backgroundColor: editingElement.settings.carousel_arrow_bg || '#ffffff' }" class="w-full h-full"></div>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Icon Color</span>
            <div class="checkerboard rounded overflow-hidden w-9 h-7 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'carousel_arrow_icon_color')">
                <div :style="{ backgroundColor: editingElement.settings.carousel_arrow_icon_color || '#374151' }" class="w-full h-full"></div>
            </div>
        </div>
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Size</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_arrow_size ?? 40 }}px</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.carousel_arrow_size" min="24" max="72" step="1" class="w-full accent-[#0091ea]">
        </div>
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Gap to slider edge</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_arrow_offset ?? 8 }}px</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.carousel_arrow_offset" min="-24" max="40" step="1" class="w-full accent-[#0091ea]">
        </div>
    </div>
</div>

{{-- Dot Style (carousel only) --}}
<div v-if="editingElement.settings.layout === 'carousel' && (editingElement.settings.carousel_dots ?? true)" class="pt-4 border-t border-slate-50">
    <label class="text-[12px] font-bold text-[#333] block mb-3">Dot Style</label>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Dot Color</span>
            <div class="checkerboard rounded overflow-hidden w-9 h-7 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'carousel_dot_color')">
                <div :style="{ backgroundColor: editingElement.settings.carousel_dot_color || '#cbd5e1' }" class="w-full h-full"></div>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-600 font-semibold">Active Color</span>
            <div class="checkerboard rounded overflow-hidden w-9 h-7 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'carousel_dot_active_color')">
                <div :style="{ backgroundColor: editingElement.settings.carousel_dot_active_color || '#2271b1' }" class="w-full h-full"></div>
            </div>
        </div>
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Dot Size</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_dot_size ?? 8 }}px</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.carousel_dot_size" min="4" max="20" step="1" class="w-full accent-[#0091ea]">
        </div>
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Border Width</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_dot_border ?? 0 }}px</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.carousel_dot_border" min="0" max="6" step="1" class="w-full accent-[#0091ea]">
        </div>
        <div class="flex items-center justify-between" v-if="(editingElement.settings.carousel_dot_border ?? 0) > 0">
            <span class="text-[12px] text-slate-600 font-semibold">Border Color</span>
            <div class="checkerboard rounded overflow-hidden w-9 h-7 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'carousel_dot_border_color')">
                <div :style="{ backgroundColor: editingElement.settings.carousel_dot_border_color || '#ffffff' }" class="w-full h-full"></div>
            </div>
        </div>
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Gap Between Dots</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_dot_gap ?? 6 }}px</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.carousel_dot_gap" min="0" max="24" step="1" class="w-full accent-[#0091ea]">
        </div>
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-slate-400 uppercase">Gap to slider</label>
                <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.carousel_dots_offset ?? 14 }}px</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.carousel_dots_offset" min="0" max="60" step="1" class="w-full accent-[#0091ea]">
        </div>
    </div>
</div>

{{-- 2. Card Alignment (align-items within each grid row) --}}
<div v-if="editingElement.settings.layout !== 'carousel'">
    <label class="text-[12px] font-bold text-[#333] block mb-2">Card Alignment</label>
    <div class="grid grid-cols-2 gap-2">
        <button @click="editingElement.settings.card_alignment = 'flex-start'"
                :class="(editingElement.settings.card_alignment === 'flex-start' || editingElement.settings.card_alignment === 'start' || editingElement.settings.card_alignment === 'left' || !editingElement.settings.card_alignment) ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                class="py-2 rounded transition-colors flex items-center justify-center relative group/btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="5" y="4" width="3" height="10" rx="0.5"/>
                <rect x="10.5" y="4" width="3" height="14" rx="0.5"/>
                <rect x="16" y="4" width="3" height="8" rx="0.5"/>
            </svg>
            <div class="falcon-tooltip-v2 opacity-0 group-hover/btn:opacity-100 z-[100] whitespace-nowrap">Align Top</div>
        </button>
        <button @click="editingElement.settings.card_alignment = 'center'"
                :class="editingElement.settings.card_alignment === 'center' ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                class="py-2 rounded transition-colors flex items-center justify-center relative group/btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="5" y="7" width="3" height="10" rx="0.5"/>
                <rect x="10.5" y="5" width="3" height="14" rx="0.5"/>
                <rect x="16" y="8" width="3" height="8" rx="0.5"/>
            </svg>
            <div class="falcon-tooltip-v2 opacity-0 group-hover/btn:opacity-100 z-[100] whitespace-nowrap">Align Center</div>
        </button>
        <button @click="editingElement.settings.card_alignment = 'flex-end'"
                :class="(editingElement.settings.card_alignment === 'flex-end' || editingElement.settings.card_alignment === 'end' || editingElement.settings.card_alignment === 'right') ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                class="py-2 rounded transition-colors flex items-center justify-center relative group/btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="5" y="10" width="3" height="10" rx="0.5"/>
                <rect x="10.5" y="6" width="3" height="14" rx="0.5"/>
                <rect x="16" y="12" width="3" height="8" rx="0.5"/>
            </svg>
            <div class="falcon-tooltip-v2 opacity-0 group-hover/btn:opacity-100 z-[100] whitespace-nowrap">Align Bottom</div>
        </button>
        <button @click="editingElement.settings.card_alignment = 'stretch'"
                :class="(editingElement.settings.card_alignment === 'stretch' || editingElement.settings.card_alignment === '') ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                class="py-2 rounded transition-colors flex items-center justify-center relative group/btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 3l3 3h-2v12h2l-3 3-3-3h2V6H9l3-3z"/>
            </svg>
            <div class="falcon-tooltip-v2 opacity-0 group-hover/btn:opacity-100 z-[100] whitespace-nowrap">Stretch</div>
        </button>
    </div>
</div>

{{-- 3. Number of Columns (responsive) — hidden for carousel and list (list is always 1 column) --}}
<div v-if="editingElement.settings.layout !== 'carousel' && editingElement.settings.layout !== 'list'">
    <label class="text-[12px] font-bold text-[#333] block mb-2">Number of Columns</label>
    <div class="grid grid-cols-3 gap-2">
        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1"><i class="fa fa-desktop mr-1"></i>Desktop</label>
            <input type="number" v-model.number="editingElement.settings.columns"
                   min="1" max="6"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1"><i class="fa fa-tablet-alt mr-1"></i>Tablet</label>
            <input type="number" v-model.number="editingElement.settings.columns_tablet"
                   min="1" max="4"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1"><i class="fa fa-mobile-alt mr-1"></i>Mobile</label>
            <input type="number" v-model.number="editingElement.settings.columns_mobile"
                   min="1" max="2"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
</div>

{{-- 4. Column Spacing --}}
<div>
    <div class="flex justify-between items-center mb-2">
        <label class="text-[12px] font-bold text-[#333]">
            @{{ editingElement.settings.layout === 'carousel' ? 'Slide Gap' : 'Column Spacing' }}
        </label>
        <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.column_spacing ?? 24 }}px</span>
    </div>
    <div class="flex gap-3 items-center">
        <input type="range" v-model.number="editingElement.settings.column_spacing"
               min="0" max="80" class="flex-1 accent-[#0091ea]">
        <input type="number" v-model.number="editingElement.settings.column_spacing"
               min="0" max="80"
               class="w-16 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- 5. Row Spacing — hidden for carousel --}}
<div v-if="editingElement.settings.layout !== 'carousel'">
    <div class="flex justify-between items-center mb-2">
        <label class="text-[12px] font-bold text-[#333]">Row Spacing</label>
        <span class="text-[12px] text-slate-400 font-bold">@{{ editingElement.settings.row_spacing ?? 24 }}px</span>
    </div>
    <div class="flex gap-3 items-center">
        <input type="range" v-model.number="editingElement.settings.row_spacing"
               min="0" max="80" class="flex-1 accent-[#0091ea]">
        <input type="number" v-model.number="editingElement.settings.row_spacing"
               min="0" max="80"
               class="w-16 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- 6. Margin --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-3">Margin</label>
    <div class="grid grid-cols-2 gap-3">
        @foreach([['marginTop','Top'],['marginRight','Right'],['marginBottom','Bottom'],['marginLeft','Left']] as [$key,$lbl])
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

{{-- Element Visibility --}}
<div class="pt-4 border-t border-slate-50">
    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
    <div class="grid grid-cols-3 gap-1">
        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                :class="editingElement.settings.visibility.mobile ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                class="py-3 rounded transition-all flex items-center justify-center">
            <i class="fa fa-mobile-alt text-sm"></i>
        </button>
        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                :class="editingElement.settings.visibility.tablet ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                class="py-3 rounded transition-all flex items-center justify-center">
            <i class="fa fa-tablet-alt text-sm"></i>
        </button>
        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                :class="editingElement.settings.visibility.desktop ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                class="py-3 rounded transition-all flex items-center justify-center">
            <i class="fa fa-desktop text-sm"></i>
        </button>
    </div>
</div>

{{-- CSS Class & ID --}}
<div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
        <input type="text" v-model="editingElement.settings.cssClass"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
        <input type="text" v-model="editingElement.settings.cssId"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    </div>
</div>
