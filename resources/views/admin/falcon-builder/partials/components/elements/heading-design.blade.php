<div class="space-y-6">
    <!-- Typography -->
    <div class="space-y-4">
        <label class="text-[12px] font-bold text-[#333] block">Typography</label>

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

        <!-- Font Weight -->
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
            <select v-model="editingElement.settings.fontWeight"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <option value="300">Light 300</option>
                <option value="400">Regular 400</option>
                <option value="500">Medium 500</option>
                <option value="600">Semi Bold 600</option>
                <option value="700">Bold 700 (Default)</option>
                <option value="800">Extra Bold 800</option>
                <option value="900">Black 900</option>
            </select>
        </div>

        <!-- Font Size / Line Height / Letter Spacing -->
        <div>
            <div class="flex justify-between items-center mb-2">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Size &amp; Spacing</label>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Font Size</label>
                    <input type="text" v-model="editingElement.settings.fontSize"
                           placeholder="30px"
                           class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
                    <div class="flex gap-0.5 mt-1">
                        <button v-for="u in ['px','rem','em']" :key="u"
                                @click="editingElement.settings.fontSize = (parseFloat(editingElement.settings.fontSize) || 30) + u"
                                class="flex-1 text-[9px] py-0.5 border border-slate-200 rounded text-slate-400 hover:bg-[#2271b1] hover:text-white hover:border-[#2271b1] transition-all">
                            @{{ u }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Height</label>
                    <input type="text" v-model="editingElement.settings.lineHeight"
                           placeholder="1.2"
                           class="w-full border border-slate-200 rounded px-2 py-2 text-[11px] text-center">
                </div>
                <div>
                    <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter Spacing</label>
                    <input type="text" v-model="editingElement.settings.letterSpacing"
                           placeholder="0"
                           class="w-full border border-slate-200 rounded px-2 py-2 text-[11px] text-center">
                </div>
            </div>
        </div>

        <!-- Text Transform -->
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

    <!-- Font Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Font Color</label>
            <button @click="editingElement.settings.color = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'color')">
                <div :style="{ backgroundColor: editingElement.settings.color || '#222222' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.color"
                   placeholder="#222222"
                   class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>

    <!-- Padding -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Padding</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'padding' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'hdPadding' ? null : 'hdPadding'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'hdPadding'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)"><i class="fa fa-desktop text-[11px]"></i></button>
                        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)"><i class="fa fa-tablet-alt text-[11px]"></i></button>
                        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)"><i class="fa fa-mobile-alt text-[11px]"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingTop' : 'paddingTop_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingTop', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingTopUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingTopUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Right</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingRight' : 'paddingRight_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingRight', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingRightUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingRightUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingBottom' : 'paddingBottom_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingBottom', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingBottomUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingBottomUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Left</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingLeft' : 'paddingLeft_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingLeft', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingLeftUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingLeftUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- Margin -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Margin</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'hdMargin' ? null : 'hdMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'hdMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)"><i class="fa fa-desktop text-[11px]"></i></button>
                        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)"><i class="fa fa-tablet-alt text-[11px]"></i></button>
                        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)"><i class="fa fa-mobile-alt text-[11px]"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginTop' : 'marginTop_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginTop', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginTopUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginTopUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Right</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginRight' : 'marginRight_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginRight', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginRightUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginRightUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginBottom' : 'marginBottom_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginBottom', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginBottomUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginBottomUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Left</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginLeft' : 'marginLeft_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginLeft', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginLeftUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginLeftUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
        </div>
    </div>
</div>
