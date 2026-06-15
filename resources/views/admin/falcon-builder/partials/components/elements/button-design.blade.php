<div class="space-y-6 pb-10">
    <!-- Button Style Toggle -->
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">Button Style</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
            <button @click="editingElement.settings.buttonStyle = 'default'"
                    :class="editingElement.settings.buttonStyle !== 'custom' ? 'bg-white text-slate-600 shadow-sm' : 'text-slate-400'"
                    class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Default</button>
            <button @click="editingElement.settings.buttonStyle = 'custom'"
                    :class="editingElement.settings.buttonStyle === 'custom' ? 'bg-[#2271b1] text-white shadow-md' : 'text-slate-400'"
                    class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Custom</button>
        </div>
    </div>

    <!-- Default Color Options (Shown when Style is Default) -->
    <div v-if="editingElement.settings.buttonStyle !== 'custom'" class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333] uppercase">Colors (Solid)</label>
        </div>
        <div class="space-y-4">
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-[9px] font-bold text-slate-400 uppercase block">BG Color</label>
                    <button @click="clearColorField(editingElement.settings, 'bgColor', 'bgColorOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'bgColor', 'bgColorOpacity')">
                        <div :style="{ backgroundColor: hexToRgba(editingElement.settings.bgColor, editingElement.settings.bgColorOpacity) }" class="w-full h-full rounded-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.bgColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                </div>
            </div>
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-[9px] font-bold text-slate-400 uppercase block">Text Color</label>
                    <button @click="clearColorField(editingElement.settings, 'color', 'colorOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'color', 'colorOpacity')">
                        <div :style="{ backgroundColor: hexToRgba(editingElement.settings.color, editingElement.settings.colorOpacity) }" class="w-full h-full rounded-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.color" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Gradient Options (Shown when Style is Custom) -->
    <div v-if="editingElement.settings.buttonStyle === 'custom'" class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333] uppercase">Gradient Colors</label>
        </div>

        <!-- Start Color -->
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase block">Gradient Start Color</label>
                <button @click="clearColorField(editingElement.settings, 'bgGradientStartColor', 'bgGradientStartOpacity')" 
                        class="text-slate-300 hover:text-red-500 transition-colors" title="Reset">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                     @click="openColorPicker($event, editingElement.settings, 'bgGradientStartColor', 'bgGradientStartOpacity')">
                    <div :style="{ backgroundColor: hexToRgba(editingElement.settings.bgGradientStartColor, editingElement.settings.bgGradientStartOpacity) }" class="w-full h-full rounded-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.bgGradientStartColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
            </div>
        </div>

        <!-- End Color -->
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase block">Gradient End Color</label>
                <button @click="clearColorField(editingElement.settings, 'bgGradientEndColor', 'bgGradientEndOpacity')" 
                        class="text-slate-300 hover:text-red-500 transition-colors" title="Reset">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                     @click="openColorPicker($event, editingElement.settings, 'bgGradientEndColor', 'bgGradientEndOpacity')">
                    <div :style="{ backgroundColor: hexToRgba(editingElement.settings.bgGradientEndColor, editingElement.settings.bgGradientEndOpacity) }" class="w-full h-full rounded-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.bgGradientEndColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
            </div>
        </div>

        <!-- Start Position -->
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Gradient Start Position</label>
                <span class="text-[10px] text-slate-400">@{{ editingElement.settings.bgGradientStartPosition || 0 }}</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.bgGradientStartPosition" min="0" max="100" class="w-full accent-[#0091ea]">
        </div>

        <!-- End Position -->
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Gradient End Position</label>
                <span class="text-[10px] text-slate-400">@{{ editingElement.settings.bgGradientEndPosition || 100 }}</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.bgGradientEndPosition" min="0" max="100" class="w-full accent-[#0091ea]">
        </div>

        <!-- Gradient Type -->
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Gradient Type</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-full">
                <button @click="editingElement.settings.bgGradientType = 'linear'"
                        :class="editingElement.settings.bgGradientType === 'linear' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-1 text-[10px] font-bold uppercase rounded transition-all">Linear</button>
                <button @click="editingElement.settings.bgGradientType = 'radial'"
                        :class="editingElement.settings.bgGradientType === 'radial' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-1 text-[10px] font-bold uppercase rounded transition-all">Radial</button>
            </div>
        </div>

        <!-- Gradient Angle -->
        <div v-if="editingElement.settings.bgGradientType === 'linear'">
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Gradient Angle</label>
                <span class="text-[10px] text-slate-400">@{{ editingElement.settings.bgGradientAngle || 180 }}°</span>
            </div>
            <input type="range" v-model.number="editingElement.settings.bgGradientAngle" min="0" max="360" class="w-full accent-[#0091ea]">
        </div>
    </div>

    <!-- Hover Color Options -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333] uppercase">Hover Colors</label>
        </div>
        
        <!-- Text Hover Color (Common) -->
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase block">Text Hover Color</label>
                <button @click="clearColorField(editingElement.settings, 'hoverColor', 'hoverColorOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                     @click="openColorPicker($event, editingElement.settings, 'hoverColor', 'hoverColorOpacity')">
                    <div :style="{ backgroundColor: hexToRgba(editingElement.settings.hoverColor, editingElement.settings.hoverColorOpacity) }" class="w-full h-full rounded-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.hoverColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
            </div>
        </div>

        <!-- Default BG Hover (Solid) -->
        <div v-if="editingElement.settings.buttonStyle !== 'custom'">
            <div class="flex justify-between items-center mb-1.5">
                <label class="text-[9px] font-bold text-slate-400 uppercase block">BG Hover Color (Solid)</label>
                <button @click="clearColorField(editingElement.settings, 'hoverBgColor', 'hoverBgColorOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                     @click="openColorPicker($event, editingElement.settings, 'hoverBgColor', 'hoverBgColorOpacity')">
                    <div :style="{ backgroundColor: hexToRgba(editingElement.settings.hoverBgColor, editingElement.settings.hoverBgColorOpacity) }" class="w-full h-full rounded-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.hoverBgColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
            </div>
        </div>

        <!-- Custom Gradient Hover -->
        <div v-if="editingElement.settings.buttonStyle === 'custom'" class="space-y-4">
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-[9px] font-bold text-slate-400 uppercase block">Hover Start</label>
                    <button @click="clearColorField(editingElement.settings, 'bgGradientHoverStartColor', 'bgGradientHoverStartOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'bgGradientHoverStartColor', 'bgGradientHoverStartOpacity')">
                        <div :style="{ backgroundColor: hexToRgba(editingElement.settings.bgGradientHoverStartColor, editingElement.settings.bgGradientHoverStartOpacity) }" class="w-full h-full rounded-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.bgGradientHoverStartColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                </div>
            </div>
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-[9px] font-bold text-slate-400 uppercase block">Hover End</label>
                    <button @click="clearColorField(editingElement.settings, 'bgGradientHoverEndColor', 'bgGradientHoverEndOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'bgGradientHoverEndColor', 'bgGradientHoverEndOpacity')">
                        <div :style="{ backgroundColor: hexToRgba(editingElement.settings.bgGradientHoverEndColor, editingElement.settings.bgGradientHoverEndOpacity) }" class="w-full h-full rounded-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.bgGradientHoverEndColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                </div>
            </div>
        </div>
    </div>

    <!-- Margin -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">Margin</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'btnMargin' ? null : 'btnMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'btnMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginTop' : 'marginTop_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginTop', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginTopUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginTopUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Right</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginRight' : 'marginRight_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginRight', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginRightUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginRightUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginBottom' : 'marginBottom_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginBottom', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginBottomUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginBottomUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Left</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginLeft' : 'marginLeft_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginLeft', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginLeftUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginLeftUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- Padding -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">Padding</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'padding' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'btnPadding' ? null : 'btnPadding'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'btnPadding'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingTop' : 'paddingTop_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingTop', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingTopUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingTopUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Right</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingRight' : 'paddingRight_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingRight', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingRightUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingRightUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingBottom' : 'paddingBottom_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingBottom', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingBottomUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingBottomUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Left</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" min="0" v-model.number="editingElement.settings[device === 'desktop' ? 'paddingLeft' : 'paddingLeft_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'paddingLeft', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'paddingLeftUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'paddingLeftUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- Typography (Restored to Text Block Style) -->
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

        <!-- Font Weight -->
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

        <!-- Grid for Size, Line Height, Letter Spacing -->
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

        <!-- Text Transform -->
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.textTransform = 'none'"
                        :class="editingElement.settings.textTransform === 'none' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
                <button @click="editingElement.settings.textTransform = 'initial'"
                        :class="editingElement.settings.textTransform === 'initial' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">—</button>
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

    <!-- Border Size -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">Border Size</label>
        </div>
        <div class="grid grid-cols-4 gap-2">
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Top</label>
                <input type="number" v-model.number="editingElement.settings.borderSizeTop" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Right</label>
                <input type="number" v-model.number="editingElement.settings.borderSizeRight" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Bottom</label>
                <input type="number" v-model.number="editingElement.settings.borderSizeBottom" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Left</label>
                <input type="number" v-model.number="editingElement.settings.borderSizeLeft" class="w-full border border-slate-200 rounded py-2 text-center text-[12px]">
            </div>
        </div>
    </div>

    <!-- Border Color (Conditional) -->
    <div v-if="editingElement.settings.borderSizeTop || editingElement.settings.borderSizeRight || editingElement.settings.borderSizeBottom || editingElement.settings.borderSizeLeft"
         class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-1.5">
            <label class="text-[9px] font-bold text-slate-400 uppercase block">Border Color</label>
            <button @click="clearColorField(editingElement.settings, 'borderColor', 'borderColorOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                 @click="openColorPicker($event, editingElement.settings, 'borderColor', 'borderColorOpacity')">
                <div :style="{ backgroundColor: hexToRgba(editingElement.settings.borderColor, editingElement.settings.borderColorOpacity) }" class="w-full h-full rounded-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.borderColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
        </div>
    </div>

    <!-- Border Radius -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-1.5">
            <label class="text-[12px] font-bold text-[#333] uppercase">Border Radius</label>
            <span class="text-[10px] text-slate-400">@{{ editingElement.settings.borderRadius || 0 }}px</span>
        </div>
        <input type="range" v-model.number="editingElement.settings.borderRadius" min="0" max="100" class="w-full accent-[#0091ea]">
    </div>

    <!-- Button Size & Span -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Button Size</label>
            <select v-model="editingElement.settings.buttonSize" 
                    @change="applyButtonSize(editingElement.settings.buttonSize)"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[11px]">
                <option value="small">Small</option>
                <option value="medium">Medium</option>
                <option value="large">Large</option>
                <option value="extra-large">Extra Large</option>
            </select>
        </div>
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Full Width (Span)</label>
            <div class="flex w-[100px] bg-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.buttonSpan = true"
                        :class="editingElement.settings.buttonSpan ? 'bg-[#2271b1] text-white' : 'text-slate-500 hover:bg-slate-200'"
                        class="flex-1 py-1.5 text-[10px] font-bold transition-colors">Yes</button>
                <button @click="editingElement.settings.buttonSpan = false"
                        :class="!editingElement.settings.buttonSpan ? 'bg-slate-200 text-slate-500' : 'text-slate-500 hover:bg-slate-200'"
                        class="flex-1 py-1.5 text-[10px] font-bold transition-colors">No</button>
            </div>
        </div>
    </div>

    <!-- Inline Icon Picker -->
    <div class="pt-4 border-t border-slate-50 space-y-3">
        <label class="text-[12px] font-bold text-[#333] uppercase">Button Icon</label>
        
        <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-hidden">
            <!-- Search -->
            <div class="p-2 border-b border-slate-200">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                    <input type="text" v-model="searchIconQuery" placeholder="Search icons..." 
                           class="w-full pl-8 pr-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="flex border-b border-slate-200 bg-slate-100/50">
                <button v-for="tab in ['Solid', 'Regular', 'Brands']" :key="tab"
                        @click="activeIconTab = tab"
                        :class="activeIconTab === tab ? 'text-[#0091ea] bg-white border-b-2 border-b-[#0091ea]' : 'text-slate-400 hover:text-slate-600'"
                        class="flex-1 py-2 text-[10px] font-bold uppercase transition-all">
                    @{{ tab }}
                </button>
            </div>
            
            <!-- Icon Grid -->
            <div class="h-48 overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-200 bg-white">
                <div class="grid grid-cols-5 gap-1.5">
                    <button v-for="icon in filteredIcons" :key="icon"
                            @click="selectIcon(editingElement.settings, icon)"
                            :class="editingElement.settings.icon === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                            class="aspect-square flex items-center justify-center rounded border transition-all p-1">
                        <i :class="[icon, 'text-base']"></i>
                    </button>
                </div>
                <div v-if="filteredIcons.length === 0" class="py-10 text-center text-[10px] text-slate-400">
                    No icons found
                </div>
            </div>
            
            <!-- Selected Preview -->
            <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center text-[#0091ea]">
                        <i :class="editingElement.settings.icon || 'fas fa-plus'"></i>
                    </div>
                    <span class="text-[10px] text-slate-500 font-medium truncate max-w-[100px]">@{{ editingElement.settings.icon || 'No icon' }}</span>
                </div>
                <button v-if="editingElement.settings.icon" @click="editingElement.settings.icon = ''" class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
            </div>
        </div>

        <!-- Icon Position -->
        <div v-if="editingElement.settings.icon" class="mt-4">
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Icon Position</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-full">
                <button @click="editingElement.settings.iconPosition = 'left'"
                        :class="editingElement.settings.iconPosition === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-1.5 text-[10px] font-bold uppercase rounded transition-all">Left</button>
                <button @click="editingElement.settings.iconPosition = 'right'"
                        :class="editingElement.settings.iconPosition === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-1.5 text-[10px] font-bold uppercase rounded transition-all">Right</button>
            </div>
        </div>
    </div>
</div>

