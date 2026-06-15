<!-- Dimension Settings -->
<div class="space-y-6">
    <div class="bg-slate-50/50 p-4 rounded-lg border border-slate-100">
        <h4 class="text-[11px] font-black uppercase tracking-widest text-[#0091ea] mb-4">Dimension</h4>
        
        <div class="space-y-4">
            <!-- Width -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">WIDTH</label>
                    <div class="flex bg-white border border-slate-200 rounded p-0.5">
                        <button @click="editingElement.settings.widthUnit = 'px'" :class="editingElement.settings.widthUnit === 'px' ? 'bg-slate-100' : ''" class="px-2 py-0.5 text-[9px] font-bold rounded">PX</button>
                        <button @click="editingElement.settings.widthUnit = '%'" :class="editingElement.settings.widthUnit === '%' ? 'bg-slate-100' : ''" class="px-2 py-0.5 text-[9px] font-bold rounded">%</button>
                    </div>
                </div>
                <input type="number" v-model="editingElement.settings.width" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            </div>

            <!-- Max Width -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">MAX WIDTH</label>
                    <div class="flex bg-white border border-slate-200 rounded p-0.5">
                        <button @click="editingElement.settings.maxWidthUnit = 'px'" :class="editingElement.settings.maxWidthUnit === 'px' ? 'bg-slate-100' : ''" class="px-2 py-0.5 text-[9px] font-bold rounded">PX</button>
                        <button @click="editingElement.settings.maxWidthUnit = '%'" :class="editingElement.settings.maxWidthUnit === '%' ? 'bg-slate-100' : ''" class="px-2 py-0.5 text-[9px] font-bold rounded">%</button>
                    </div>
                </div>
                <input type="number" v-model="editingElement.settings.maxWidth" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            </div>

            <!-- Sticky Width -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">STICKY WIDTH</label>
                    <div class="flex bg-white border border-slate-200 rounded p-0.5">
                        <button @click="editingElement.settings.stickyWidthUnit = 'px'" :class="(editingElement.settings.stickyWidthUnit || 'px') === 'px' ? 'bg-slate-100' : ''" class="px-2 py-0.5 text-[9px] font-bold rounded">PX</button>
                        <button @click="editingElement.settings.stickyWidthUnit = '%'" :class="editingElement.settings.stickyWidthUnit === '%' ? 'bg-slate-100' : ''" class="px-2 py-0.5 text-[9px] font-bold rounded">%</button>
                    </div>
                </div>
                <input type="number" v-model="editingElement.settings.stickyWidth" placeholder="auto" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                <p class="text-[10px] text-slate-400 mt-1">Applied when parent col/container is sticky</p>
            </div>

        </div>
    </div>

    <!-- Spacing & Border -->
    <div class="bg-slate-50/50 p-4 rounded-lg border border-slate-100">
        <h4 class="text-[11px] font-black uppercase tracking-widest text-[#0091ea] mb-4">Spacing & Style</h4>
        
        <div class="space-y-4">
            <!-- Margins -->
            <div>
                <div class="flex justify-between items-center mb-3">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">MARGIN</label>
                    <div class="flex gap-1 items-center">
                        <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                            <i class="fa fa-undo text-[10px]"></i>
                        </button>
                        <div class="relative inline-block">
                            <button @click="activeResponsiveMenu = activeResponsiveMenu === 'imgMargin' ? null : 'imgMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                                <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                                <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                            </button>
                            <div v-show="activeResponsiveMenu === 'imgMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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

            <!-- Border Radius -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">BORDER RADIUS</label>
                <input type="number" v-model="editingElement.settings.borderRadius" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px]">
            </div>

            <!-- Border Settings -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">BORDER COLOR</label>
                    <button @click="editingElement.settings.borderColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'borderColor')">
                        <div :style="{ backgroundColor: editingElement.settings.borderColor }" class="w-full h-full"></div>
                    </div>
                    <div class="relative flex-1">
                        <input type="text" v-model="editingElement.settings.borderColor"
                               placeholder="#000000"
                               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px]">
                    </div>
                </div>
            </div>

            <!-- Border Size -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">BORDER SIZE</label>
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

            <!-- Hover Effect -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">HOVER EFFECT</label>
                <select v-model="editingElement.settings.hoverType"
                        class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                    <option value="none">None</option>
                    <option value="zoom-in">Zoom In</option>
                    <option value="zoom-out">Zoom Out</option>
                    <option value="lift">Lift</option>
                    <option value="shadow">Shadow</option>
                    <option value="opacity">Opacity</option>
                </select>
            </div>
        </div>
    </div>
</div>
