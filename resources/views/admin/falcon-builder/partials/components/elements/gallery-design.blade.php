<div class="space-y-5">

    <!-- Columns -->
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Columns</label>
            <div class="flex gap-1 items-center">
                <button @click="device === 'mobile' ? editingElement.settings.columnsMobile = (device === 'desktop' ? 3 : device === 'tablet' ? 2 : 1) : device === 'tablet' ? editingElement.settings.columnsTablet = 2 : editingElement.settings.columns = 3"
                        title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'galCols' ? null : 'galCols'"
                            class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'galCols'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
        <div class="flex gap-3 items-center">
            <input type="number" min="1" max="6"
                   :value="device === 'mobile' ? (editingElement.settings.columnsMobile || 1) : device === 'tablet' ? (editingElement.settings.columnsTablet || 2) : (editingElement.settings.columns || 3)"
                   @input="device === 'mobile' ? editingElement.settings.columnsMobile = Math.min(6, Math.max(1, Number($event.target.value) || 1)) : device === 'tablet' ? editingElement.settings.columnsTablet = Math.min(6, Math.max(1, Number($event.target.value) || 1)) : editingElement.settings.columns = Math.min(6, Math.max(1, Number($event.target.value) || 1))"
                   class="w-12 border border-slate-200 rounded py-2 text-center text-[13px] font-bold focus:outline-none focus:border-[#0091ea]">
            <input type="range" min="1" max="6" step="1"
                   :value="device === 'mobile' ? (editingElement.settings.columnsMobile || 1) : device === 'tablet' ? (editingElement.settings.columnsTablet || 2) : (editingElement.settings.columns || 3)"
                   @input="device === 'mobile' ? editingElement.settings.columnsMobile = Number($event.target.value) : device === 'tablet' ? editingElement.settings.columnsTablet = Number($event.target.value) : editingElement.settings.columns = Number($event.target.value)"
                   class="flex-1 accent-[#0091ea]">
        </div>
    </div>

    <!-- Gap -->
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Gap (px)</label>
        <div class="flex gap-3 items-center">
            <input type="number" v-model.number="editingElement.settings.gap" min="0" max="60"
                   class="w-12 border border-slate-200 rounded py-2 text-center text-[13px] font-bold focus:outline-none focus:border-[#0091ea]">
            <input type="range" v-model.number="editingElement.settings.gap" min="0" max="60" step="2"
                   class="flex-1 accent-[#0091ea]">
        </div>
    </div>

    <!-- Aspect Ratio -->
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Aspect Ratio</label>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.aspectRatio = 'square'"
                    :class="(editingElement.settings.aspectRatio || 'square') === 'square' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">1:1</button>
            <button @click="editingElement.settings.aspectRatio = 'portrait'"
                    :class="editingElement.settings.aspectRatio === 'portrait' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">3:4</button>
            <button @click="editingElement.settings.aspectRatio = 'landscape'"
                    :class="editingElement.settings.aspectRatio === 'landscape' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">16:9</button>
            <button @click="editingElement.settings.aspectRatio = 'auto'"
                    :class="editingElement.settings.aspectRatio === 'auto' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold transition-all">Auto</button>
        </div>
    </div>

    <!-- Border Radius -->
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Border Radius (px)</label>
        <div class="flex gap-3 items-center">
            <input type="number" v-model.number="editingElement.settings.borderRadius" min="0" max="50"
                   class="w-12 border border-slate-200 rounded py-2 text-center text-[13px] font-bold focus:outline-none focus:border-[#0091ea]">
            <input type="range" v-model.number="editingElement.settings.borderRadius" min="0" max="50"
                   class="flex-1 accent-[#0091ea]">
        </div>
    </div>

    <!-- Image Border -->
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Image Border</label>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Width (px)</label>
                <input type="number" v-model.number="editingElement.settings.imgBorderWidth" min="0" max="20"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Style</label>
                <select v-model="editingElement.settings.imgBorderStyle"
                        class="w-full border border-slate-200 rounded px-1 py-2 text-[11px] focus:outline-none focus:border-[#0091ea]">
                    <option value="solid">Solid</option>
                    <option value="dashed">Dashed</option>
                    <option value="dotted">Dotted</option>
                </select>
            </div>
        </div>
        <div class="mt-2">
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Color</label>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'imgBorderColor')">
                    <div :style="{ backgroundColor: editingElement.settings.imgBorderColor || '#e2e8f0' }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.imgBorderColor" placeholder="#e2e8f0"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- Lightbox & Hover -->
    <div class="border-t border-slate-50 pt-4 space-y-3">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Behaviour</label>
        <div class="flex items-center justify-between py-2">
            <span class="text-[12px] text-slate-600">Lightbox on click</span>
            <button @click="editingElement.settings.lightbox = !editingElement.settings.lightbox"
                    :class="editingElement.settings.lightbox ? 'bg-[#2271b1]' : 'bg-slate-200'"
                    class="relative w-10 h-5 rounded-full transition-colors flex-shrink-0">
                <span :class="editingElement.settings.lightbox ? 'translate-x-5' : 'translate-x-0.5'"
                      class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform block"></span>
            </button>
        </div>
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Hover Effect</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.hoverEffect = 'zoom'"
                        :class="(editingElement.settings.hoverEffect || 'zoom') === 'zoom' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Zoom</button>
                <button @click="editingElement.settings.hoverEffect = 'none'"
                        :class="editingElement.settings.hoverEffect === 'none' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold transition-all">None</button>
            </div>
        </div>
    </div>

    <!-- Caption Typography -->
    <div class="border-t border-slate-50 pt-4 space-y-4" v-if="(editingElement.settings.images || []).some(img => img.caption)">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Caption Typography</label>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
            <select v-model="editingElement.settings.captionFontFamily"
                    @change="loadBuilderFont(editingElement.settings.captionFontFamily)"
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

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
            <select v-model="editingElement.settings.captionFontWeight"
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
                <input type="text" v-model="editingElement.settings.captionFontSize" placeholder="13px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Height</label>
                <input type="text" v-model="editingElement.settings.captionLineHeight" placeholder="1.4"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
                <input type="text" v-model="editingElement.settings.captionLetterSpacing" placeholder="0px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.captionTextTransform = 'none'"
                        :class="(editingElement.settings.captionTextTransform || 'none') === 'none' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
                <button @click="editingElement.settings.captionTextTransform = 'uppercase'"
                        :class="editingElement.settings.captionTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                <button @click="editingElement.settings.captionTextTransform = 'lowercase'"
                        :class="editingElement.settings.captionTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                <button @click="editingElement.settings.captionTextTransform = 'capitalize'"
                        :class="editingElement.settings.captionTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Color</label>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'captionColor')">
                    <div :style="{ backgroundColor: editingElement.settings.captionColor }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.captionColor" placeholder="#6b7280"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- Spacing (Responsive) -->
    <div class="border-t border-slate-50 pt-4 space-y-3">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333]">Spacing</label>
            <div class="flex gap-1 items-center">
                <button @click="setResponsiveVal(editingElement.settings, 'marginTop', device, ''); setResponsiveVal(editingElement.settings, 'marginBottom', device, '')" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'galMargin' ? null : 'galMargin'"
                            class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'galMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number"
                           :value="getResponsiveVal(editingElement.settings, 'marginTop', device) ?? 0"
                           @input="setResponsiveVal(editingElement.settings, 'marginTop', device, $event.target.value === '' ? '' : Number($event.target.value))"
                           placeholder="0"
                           class="w-full h-9 px-2 text-[12px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginTopUnit', device) || 'px'"
                            @change="setResponsiveVal(editingElement.settings, 'marginTopUnit', device, $event.target.value)"
                            class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                        <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number"
                           :value="getResponsiveVal(editingElement.settings, 'marginBottom', device) ?? 0"
                           @input="setResponsiveVal(editingElement.settings, 'marginBottom', device, $event.target.value === '' ? '' : Number($event.target.value))"
                           placeholder="0"
                           class="w-full h-9 px-2 text-[12px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginBottomUnit', device) || 'px'"
                            @change="setResponsiveVal(editingElement.settings, 'marginBottomUnit', device, $event.target.value)"
                            class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                        <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

</div>
