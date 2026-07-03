<div class="space-y-6">
    <!-- Alignment -->
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Alignment</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.textAlign = 'left'"
                    :class="(editingElement.settings.textAlign === 'left' || !editingElement.settings.textAlign) ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Left</button>
            <button @click="editingElement.settings.textAlign = 'center'"
                    :class="editingElement.settings.textAlign === 'center' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Center</button>
            <button @click="editingElement.settings.textAlign = 'right'"
                    :class="editingElement.settings.textAlign === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Right</button>
        </div>
    </div>

    <!-- Typography -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333] uppercase">Typography</label>
        </div>

        <!-- Font Family -->
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
            <select v-model="editingElement.settings.fontFamily"
                    @change="loadBuilderFont(editingElement.settings.fontFamily)"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <option value="inherit">Default@{{ themeBodyFont ? ' (' + themeBodyFont + ')' : '' }}</option>
                <template v-for="(fonts, category) in builderFontGroups" :key="category">
                    <optgroup :label="category">
                        <option v-for="font in fonts" :key="font.family"
                                :value="font.family + ', ' + (font.category === 'Monospace' ? 'monospace' : (font.category === 'Serif' ? 'serif' : 'sans-serif'))">
                            @{{ font.family }}
                        </option>
                    </optgroup>
                </template>
            </select>
        </div>

        <!-- Font Weight (dynamic variants) -->
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
            <select v-model="editingElement.settings.fontWeight"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <option v-for="v in titleFontVariants" :key="v" :value="v">@{{ ({
                    '100':'Thin 100','200':'Extra Light 200','300':'Light 300',
                    '400':'Regular 400','500':'Medium 500','600':'Semi Bold 600',
                    '700':'Bold 700','800':'Extra Bold 800','900':'Black 900'
                })[v] || ('Weight ' + v) }}</option>
            </select>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Font Size</label>
                <input type="text" v-model="editingElement.settings.fontSize"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Hei...</label>
                <input type="text" v-model="editingElement.settings.lineHeight"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
                <input type="text" v-model="editingElement.settings.letterSpacing"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.textTransform = 'none'"
                        :class="(editingElement.settings.textTransform === 'none' || !editingElement.settings.textTransform) ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
                <button @click="editingElement.settings.textTransform = 'uppercase'"
                        :class="editingElement.settings.textTransform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                <button @click="editingElement.settings.textTransform = 'lowercase'"
                        :class="editingElement.settings.textTransform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                <button @click="editingElement.settings.textTransform = 'capitalize'"
                        :class="editingElement.settings.textTransform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
            </div>
        </div>
    </div>

    <!-- Text (Base) Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Text Color</label>
            <button @click="editingElement.settings.color = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'color')">
                <div :style="{ backgroundColor: editingElement.settings.color || '#6b7280' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.color" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
        </div>
    </div>

    <!-- Link Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link Color</label>
            <button @click="editingElement.settings.linkColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'linkColor')">
                <div :style="{ backgroundColor: editingElement.settings.linkColor || '#6b7280' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.linkColor" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
        </div>
    </div>

    <!-- Link Hover Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link Hover Color</label>
            <button @click="editingElement.settings.linkHoverColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'linkHoverColor')">
                <div :style="{ backgroundColor: editingElement.settings.linkHoverColor || '#2271b1' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.linkHoverColor" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
        </div>
    </div>

    <!-- Separator Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Separator Color</label>
            <button @click="editingElement.settings.separatorColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'separatorColor')">
                <div :style="{ backgroundColor: editingElement.settings.separatorColor || '#9ca3af' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.separatorColor" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
        </div>
    </div>

    <!-- Current Page Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Current Page Color</label>
            <button @click="editingElement.settings.currentColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'currentColor')">
                <div :style="{ backgroundColor: editingElement.settings.currentColor || '#111827' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.currentColor" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
        </div>
    </div>

    <!-- Padding -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Padding</label>
            <button @click="['Top','Right','Bottom','Left'].forEach(s => editingElement.settings['padding' + s] = 0)" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <template v-for="side in ['Top','Right','Bottom','Left']" :key="'pad' + side">
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">@{{ side }}</label>
                    <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                        <input type="number" min="0" v-model.number="editingElement.settings['padding' + side]" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                        <select v-model="editingElement.settings['padding' + side + 'Unit']" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Margin -->
    <div class="pt-4 border-t border-slate-50 pb-10">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Margin</label>
            <button @click="['Top','Right','Bottom','Left'].forEach(s => editingElement.settings['margin' + s] = 0)" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <template v-for="side in ['Top','Right','Bottom','Left']" :key="'mar' + side">
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">@{{ side }}</label>
                    <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                        <input type="number" v-model.number="editingElement.settings['margin' + side]" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                        <select v-model="editingElement.settings['margin' + side + 'Unit']" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
