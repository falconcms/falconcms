{{-- Section Separator — Design tab.
     Included from partials/sidebar.blade.php (editingElement.type === 'section_separator'). --}}

{{-- 1. Width --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Separator Width</label>
    <div class="flex gap-1">
        <input type="number" v-model.number="editingElement.settings.sepWidth" min="1"
               class="flex-1 min-w-0 border border-slate-200 rounded-l px-2 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        <select v-model="editingElement.settings.sepWidthUnit"
                class="border border-l-0 border-slate-200 rounded-r px-1 py-2 text-[12px] text-slate-500 bg-slate-50 focus:outline-none focus:border-[#0091ea]">
            <option value="%">%</option>
            <option value="px">px</option>
            <option value="vw">vw</option>
        </select>
    </div>
</div>

{{-- Alignment used to sit here. It was dropped from the panel; the renderers still honour
     `sepAlign` so separators saved before the change keep the alignment they were given. --}}

{{-- 2. Weight (border thickness / pattern stroke) — line + pattern families only --}}
<div v-if="!sepIsShape(editingElement.settings.sepStyle)">
    <div class="flex justify-between items-center mb-2">
        <label class="text-[12px] font-bold text-[#333]">Weight</label>
        <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.sepWeight !== undefined ? editingElement.settings.sepWeight : 1 }}px</span>
    </div>
    <div class="flex gap-3 items-center">
        <input type="range" v-model.number="editingElement.settings.sepWeight" min="1" max="30" class="flex-1 accent-[#0091ea]">
        <input type="number" v-model.number="editingElement.settings.sepWeight" min="1" max="100"
               class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
    <p class="text-[11px] text-slate-400 mt-1">Line thickness. For pattern styles it controls the stroke weight — <em>Double</em>, <em>Groove</em> and <em>Ridge</em> need 3px or more to show.</p>
</div>

{{-- 3a. Pattern size — only for the SVG pattern styles --}}
<template v-if="sepIsPattern(editingElement.settings.sepStyle)">
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[12px] font-bold text-[#333]">Pattern Height</label>
            <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.patHeight !== undefined ? editingElement.settings.patHeight : 20 }}px</span>
        </div>
        <div class="flex gap-3 items-center">
            <input type="range" v-model.number="editingElement.settings.patHeight" min="4" max="80" class="flex-1 accent-[#0091ea]">
            <input type="number" v-model.number="editingElement.settings.patHeight" min="1" max="200"
                   class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[12px] font-bold text-[#333]">Pattern Spacing</label>
            <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.patSpacing !== undefined ? editingElement.settings.patSpacing : 20 }}px</span>
        </div>
        <div class="flex gap-3 items-center">
            <input type="range" v-model.number="editingElement.settings.patSpacing" min="4" max="120" class="flex-1 accent-[#0091ea]">
            <input type="number" v-model.number="editingElement.settings.patSpacing" min="1" max="300"
                   class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
        <p class="text-[11px] text-slate-400 mt-1">Width of one repeated tile.</p>
    </div>
</template>

{{-- 3b. Shape size + flipping — for the full-width shape styles and custom SVG --}}
<template v-if="sepIsShape(editingElement.settings.sepStyle) || editingElement.settings.sepStyle === 'custom_svg'">
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[12px] font-bold text-[#333]">Shape Height</label>
            <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.shapeHeight !== undefined ? editingElement.settings.shapeHeight : 60 }}px</span>
        </div>
        <div class="flex gap-3 items-center">
            <input type="range" v-model.number="editingElement.settings.shapeHeight" min="10" max="300" class="flex-1 accent-[#0091ea]">
            <input type="number" v-model.number="editingElement.settings.shapeHeight" min="4" max="600"
                   class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
        <p class="text-[11px] text-slate-400 mt-1">The shape stretches to the full separator width, so height is what controls how bold it reads.</p>
    </div>

    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Flip</label>
        <div class="grid grid-cols-2 gap-2">
            <button @click="editingElement.settings.shapeFlipH = !editingElement.settings.shapeFlipH"
                    :class="editingElement.settings.shapeFlipH ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="py-2 rounded transition-all text-[10px] font-bold uppercase flex items-center justify-center gap-2">
                <i class="fa fa-arrows-left-right text-[11px]"></i> Horizontal
            </button>
            <button @click="editingElement.settings.shapeFlipV = !editingElement.settings.shapeFlipV"
                    :class="editingElement.settings.shapeFlipV ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="py-2 rounded transition-all text-[10px] font-bold uppercase flex items-center justify-center gap-2">
                <i class="fa fa-arrows-up-down text-[11px]"></i> Invert
            </button>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">Invert flips the silhouette upside down — use it when the separator sits above a section instead of below it.</p>
    </div>

    {{-- Custom-SVG-only switches --}}
    <template v-if="editingElement.settings.sepStyle === 'custom_svg'">
        <div>
            <label class="text-[12px] font-bold text-[#333] block mb-2">Custom SVG Colour</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                <button v-for="opt in [{v:true,l:'Separator Colour'},{v:false,l:'Keep Original'}]"
                        :key="String(opt.v)"
                        @click="editingElement.settings.svgRecolor = opt.v"
                        :class="(editingElement.settings.svgRecolor !== false) === opt.v ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                        class="flex-1 py-1.5 rounded transition-all text-[10px] font-bold uppercase">
                    @{{ opt.l }}
                </button>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Parts painted <em>none</em> keep their transparency either way.</p>
        </div>

        <div>
            <label class="text-[12px] font-bold text-[#333] block mb-2">Fit</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                <button v-for="opt in [{v:true,l:'Stretch'},{v:false,l:'Keep Ratio'}]"
                        :key="String(opt.v)"
                        @click="editingElement.settings.svgStretch = opt.v"
                        :class="(editingElement.settings.svgStretch !== false) === opt.v ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                        class="flex-1 py-1.5 rounded transition-all text-[10px] font-bold uppercase">
                    @{{ opt.l }}
                </button>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Stretch fills the full width like the built-in shapes; Keep Ratio centres the artwork instead.</p>
        </div>
    </template>
</template>

{{-- 4. Separator Color --}}
<div>
    <div class="flex justify-between items-center mb-2">
        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Separator Color</label>
        <button @click="editingElement.settings.sepColor = ''" title="Reset"
                class="text-slate-300 hover:text-red-500 transition-colors">
            <i class="fa fa-undo text-[10px]"></i>
        </button>
    </div>
    <div class="flex gap-2 items-center">
        <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
             @click="openColorPicker($event, editingElement.settings, 'sepColor')">
            <div :style="{ backgroundColor: editingElement.settings.sepColor || '#e2e8f0' }" class="w-full h-full"></div>
        </div>
        <div class="relative flex-1">
            <input type="text" v-model="editingElement.settings.sepColor" placeholder="#e2e8f0"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
</div>

{{-- ══ TEXT / ICON STYLING — only when the separator carries content ══ --}}
<template v-if="editingElement.settings.sepContent && editingElement.settings.sepContent !== 'none'">

    <div class="pt-4 border-t border-slate-100">
        <div class="flex justify-between items-center mb-2">
            <label class="text-[12px] font-bold text-[#333]">Content Gap</label>
            <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.contentGap !== undefined ? editingElement.settings.contentGap : 15 }}px</span>
        </div>
        <div class="flex gap-3 items-center">
            <input type="range" v-model.number="editingElement.settings.contentGap" min="0" max="80" class="flex-1 accent-[#0091ea]">
            <input type="number" v-model.number="editingElement.settings.contentGap" min="0" max="200"
                   class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
        <p class="text-[11px] text-slate-400 mt-1">Space between the line and the text / icon.</p>
    </div>

    {{-- Text styling --}}
    <template v-if="editingElement.settings.sepContent === 'text'">
        <div class="pt-4 border-t border-slate-100">
            <label class="text-[12px] font-bold text-[#333] block mb-3">Text Typography</label>
            @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'sep_text'])
        </div>
        <div>
            <div class="flex justify-between items-center mb-2">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Text Color</label>
                <button @click="editingElement.settings.textColor = ''" title="Reset"
                        class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'textColor')">
                    <div :style="{ backgroundColor: editingElement.settings.textColor || '#333333' }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.textColor" placeholder="#333333"
                       class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </template>

    {{-- Icon styling --}}
    <template v-if="editingElement.settings.sepContent === 'icon'">
        <div class="pt-4 border-t border-slate-100">
            <div class="flex justify-between items-center mb-2">
                <label class="text-[12px] font-bold text-[#333]">Icon Size</label>
                <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.iconSize !== undefined ? editingElement.settings.iconSize : 20 }}px</span>
            </div>
            <div class="flex gap-3 items-center">
                <input type="range" v-model.number="editingElement.settings.iconSize" min="8" max="100" class="flex-1 accent-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings.iconSize" min="1" max="300"
                       class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        <div>
            <div class="flex justify-between items-center mb-2">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Icon Color</label>
                <button @click="editingElement.settings.iconColor = ''" title="Reset"
                        class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'iconColor')">
                    <div :style="{ backgroundColor: editingElement.settings.iconColor || '#333333' }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.iconColor" placeholder="#333333"
                       class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        <div>
            <label class="text-[12px] font-bold text-[#333] block mb-2">Icon View</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                <button v-for="opt in [{v:'default',l:'Default'},{v:'stacked',l:'Stacked'},{v:'framed',l:'Framed'}]"
                        :key="opt.v"
                        @click="editingElement.settings.iconView = opt.v"
                        :class="(editingElement.settings.iconView || 'default') === opt.v ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                        class="flex-1 py-1.5 rounded transition-all text-[10px] font-bold uppercase">
                    @{{ opt.l }}
                </button>
            </div>
        </div>

        <template v-if="editingElement.settings.iconView === 'stacked' || editingElement.settings.iconView === 'framed'">
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-2">Shape</label>
                <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                    <button v-for="opt in [{v:'circle',l:'Circle'},{v:'square',l:'Square'}]"
                            :key="opt.v"
                            @click="editingElement.settings.iconShape = opt.v"
                            :class="(editingElement.settings.iconShape || 'circle') === opt.v ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                            class="flex-1 py-1.5 rounded transition-all text-[10px] font-bold uppercase">
                        @{{ opt.l }}
                    </button>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[12px] font-bold text-[#333]">Icon Padding</label>
                    <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.iconPadding !== undefined ? editingElement.settings.iconPadding : 10 }}px</span>
                </div>
                <div class="flex gap-3 items-center">
                    <input type="range" v-model.number="editingElement.settings.iconPadding" min="0" max="60" class="flex-1 accent-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings.iconPadding" min="0" max="200"
                           class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>

            <div v-if="editingElement.settings.iconView === 'stacked'">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Icon Background</label>
                    <button @click="editingElement.settings.iconBgColor = ''" title="Reset"
                            class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'iconBgColor')">
                        <div :style="{ backgroundColor: editingElement.settings.iconBgColor || '#f1f5f9' }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.iconBgColor" placeholder="#f1f5f9"
                           class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>

            <template v-if="editingElement.settings.iconView === 'framed'">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[12px] font-bold text-[#333]">Frame Width</label>
                        <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.iconBorderWidth !== undefined ? editingElement.settings.iconBorderWidth : 1 }}px</span>
                    </div>
                    <div class="flex gap-3 items-center">
                        <input type="range" v-model.number="editingElement.settings.iconBorderWidth" min="0" max="20" class="flex-1 accent-[#0091ea]">
                        <input type="number" v-model.number="editingElement.settings.iconBorderWidth" min="0" max="50"
                               class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Frame Color</label>
                        <button @click="editingElement.settings.iconBorderColor = ''" title="Reset"
                                class="text-slate-300 hover:text-red-500 transition-colors">
                            <i class="fa fa-undo text-[10px]"></i>
                        </button>
                    </div>
                    <div class="flex gap-2 items-center">
                        <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                             @click="openColorPicker($event, editingElement.settings, 'iconBorderColor')">
                            <div :style="{ backgroundColor: editingElement.settings.iconBorderColor || '#e2e8f0' }" class="w-full h-full"></div>
                        </div>
                        <input type="text" v-model="editingElement.settings.iconBorderColor" placeholder="#e2e8f0"
                               class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
            </template>
        </template>

        <div>
            <div class="flex justify-between items-center mb-2">
                <label class="text-[12px] font-bold text-[#333]">Icon Rotate</label>
                <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.iconRotate !== undefined ? editingElement.settings.iconRotate : 0 }}°</span>
            </div>
            <div class="flex gap-3 items-center">
                <input type="range" v-model.number="editingElement.settings.iconRotate" min="-180" max="180" class="flex-1 accent-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings.iconRotate" min="-360" max="360"
                       class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </template>
</template>

{{-- ══ MARGIN ══ --}}
<div class="pt-4 border-t border-slate-100">
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
