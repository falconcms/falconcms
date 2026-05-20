<div class="space-y-6">
    <!-- Margin -->
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">MARGIN</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'textMargin' ? null : 'textMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'textMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#0091ea] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)">
                            <i class="fa fa-desktop text-[11px]"></i>
                        </button>
                        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#0091ea] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)">
                            <i class="fa fa-tablet-alt text-[11px]"></i>
                        </button>
                        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#0091ea] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)">
                            <i class="fa fa-mobile-alt text-[11px]"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-4 gap-2">
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Top</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'marginTop' : 'marginTop_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Right</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'marginRight' : 'marginRight_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Bottom</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'marginBottom' : 'marginBottom_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Left</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'marginLeft' : 'marginLeft_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
        </div>
    </div>

    <!-- Padding -->
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">PADDING</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'padding' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'textPadding' ? null : 'textPadding'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'textPadding'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#0091ea] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)">
                            <i class="fa fa-desktop text-[11px]"></i>
                        </button>
                        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#0091ea] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)">
                            <i class="fa fa-tablet-alt text-[11px]"></i>
                        </button>
                        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#0091ea] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)">
                            <i class="fa fa-mobile-alt text-[11px]"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-4 gap-2">
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Top</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'paddingTop' : 'paddingTop_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Right</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'paddingRight' : 'paddingRight_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Bottom</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'paddingBottom' : 'paddingBottom_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Left</label>
                <input type="text" v-model="editingElement.settings[device === 'desktop' ? 'paddingLeft' : 'paddingLeft_' + device]" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
        </div>
    </div>

    <!-- Typography -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333] uppercase">TYPOGRAPHY</label>
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
                        :class="editingElement.settings.textTransform === 'none' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
                <button @click="editingElement.settings.textTransform = 'initial'"
                        :class="editingElement.settings.textTransform === 'initial' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">—</button>
                <button @click="editingElement.settings.textTransform = 'uppercase'"
                        :class="editingElement.settings.textTransform === 'uppercase' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                <button @click="editingElement.settings.textTransform = 'lowercase'"
                        :class="editingElement.settings.textTransform === 'lowercase' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                <button @click="editingElement.settings.textTransform = 'capitalize'"
                        :class="editingElement.settings.textTransform === 'capitalize' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
            </div>
        </div>
    </div>

    <!-- Font Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">FONT COLOR</label>
            <button @click="editingElement.settings.color = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'color')">
                <div :style="{ backgroundColor: editingElement.settings.color }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.color"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px]">
            </div>
        </div>
    </div>

    <!-- Font Hover Color -->
    <div class="pt-4 border-t border-slate-50 pb-10">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">FONT HOVER COLOR</label>
            <button @click="editingElement.settings.hoverColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'hoverColor')">
                <div :style="{ backgroundColor: editingElement.settings.hoverColor }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.hoverColor"
                       placeholder="None"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px]">
            </div>
        </div>
    </div>
</div>
