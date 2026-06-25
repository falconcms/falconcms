<div class="space-y-6">
    <!-- Alignment -->
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Alignment</label>
            <div class="flex gap-1 items-center">
                <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, '')" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'titleAlign' ? null : 'titleAlign'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'titleAlign'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'left')"
                    :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'left' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Left</button>
            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'center')"
                    :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) === 'center' || !getResponsiveVal(editingElement.settings, 'textAlign', device)) ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Center</button>
            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'right')"
                    :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Right</button>
        </div>
    </div>

    <!-- HTML Heading Tag -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">HTML Heading Tag</label>
        </div>
        <select v-model="editingElement.settings.htmlTag"
                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
            <option value="h1">H1</option>
            <option value="h2">H2</option>
            <option value="h3">H3</option>
            <option value="h4">H4</option>
            <option value="h5">H5</option>
            <option value="h6">H6</option>
            <option value="div">div</option>
            <option value="p">p</option>
        </select>
    </div>

    <!-- Typography -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333]">Typography</label>
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

    <!-- Font Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Font Color</label>
            <button @click="editingElement.settings.titleColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'titleColor')">
                <div :style="{ backgroundColor: editingElement.settings.titleColor }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.titleColor"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px]">
            </div>
        </div>
    </div>

    <!-- Font Hover Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Font Hover Color</label>
            <button @click="editingElement.settings.titleHoverColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'titleHoverColor')">
                <div :style="{ backgroundColor: editingElement.settings.titleHoverColor }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.titleHoverColor"
                       placeholder="None"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px]">
            </div>
        </div>
    </div>

    <!-- Text Shadow -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center">
            <label class="text-[12px] font-bold text-[#333]">Text Shadow</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
            <button @click="editingElement.settings.textShadow = true"
                    :class="editingElement.settings.textShadow ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="px-6 py-1.5 text-[11px] font-bold rounded transition-all">Yes</button>
            <button @click="editingElement.settings.textShadow = false"
                    :class="!editingElement.settings.textShadow ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="px-6 py-1.5 text-[11px] font-bold rounded transition-all">No</button>
        </div>
        <template v-if="editingElement.settings.textShadow">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase mb-1 block">Vertical</label>
                    <input type="text" v-model="editingElement.settings.textShadowV" class="w-full border border-slate-200 rounded px-3 py-2 text-[12px]">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase mb-1 block">Horizontal</label>
                    <input type="text" v-model="editingElement.settings.textShadowH" class="w-full border border-slate-200 rounded px-3 py-2 text-[12px]">
                </div>
            </div>
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-3">Blur Radius</label>
                <div class="flex gap-3 items-center">
                    <input type="text" v-model="editingElement.settings.textShadowBlur" class="w-14 border border-slate-200 rounded py-2 text-center text-[12px]">
                    <input type="range" v-model="editingElement.settings.textShadowBlur" min="0" max="100" class="flex-1 accent-[#0091ea]">
                </div>
            </div>
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-3">Shadow Color</label>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-9 h-9 border border-slate-200 cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'textShadowColor')">
                        <div :style="{ backgroundColor: editingElement.settings.textShadowColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.textShadowColor" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
                </div>
            </div>
        </template>
    </div>

    <!-- Text Stroke -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center">
            <label class="text-[12px] font-bold text-[#333]">Text Stroke</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
            <button @click="editingElement.settings.textStroke = true"
                    :class="editingElement.settings.textStroke ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="px-6 py-1.5 text-[11px] font-bold rounded transition-all">Yes</button>
            <button @click="editingElement.settings.textStroke = false"
                    :class="!editingElement.settings.textStroke ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="px-6 py-1.5 text-[11px] font-bold rounded transition-all">No</button>
        </div>
        <template v-if="editingElement.settings.textStroke">
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-3">Stroke Size</label>
                <div class="flex gap-3 items-center">
                    <input type="text" v-model="editingElement.settings.textStrokeSize" class="w-14 border border-slate-200 rounded py-2 text-center text-[12px]">
                    <input type="range" v-model="editingElement.settings.textStrokeSize" min="0" max="20" class="flex-1 accent-[#0091ea]">
                </div>
            </div>
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-3">Stroke Color</label>
                <div class="flex gap-2 items-center">
                    <div class="rounded-full overflow-hidden w-9 h-9 border border-slate-200 cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'textStrokeColor')">
                        <div :style="{ backgroundColor: editingElement.settings.textStrokeColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.textStrokeColor" class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px]">
                </div>
            </div>
        </template>
    </div>

    <!-- Text Overflow -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Text Overflow</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.textOverflow = 'initial'"
                    :class="editingElement.settings.textOverflow === 'initial' || !editingElement.settings.textOverflow ? 'bg-slate-700 text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 transition-all">Default</button>
            <button @click="editingElement.settings.textOverflow = 'ellipsis'"
                    :class="editingElement.settings.textOverflow === 'ellipsis' ? 'bg-slate-700 text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 transition-all">Ellipsis</button>
            <button @click="editingElement.settings.textOverflow = 'clip'"
                    :class="editingElement.settings.textOverflow === 'clip' ? 'bg-slate-700 text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold transition-all">Clip</button>
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
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'titlePadding' ? null : 'titlePadding'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'titlePadding'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)"><i class="fa fa-desktop text-[11px]"></i></button>
                        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)"><i class="fa fa-tablet-alt text-[11px]"></i></button>
                        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)"><i class="fa fa-mobile-alt text-[11px]"></i></button>
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

    <!-- Margin -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Margin</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'titleMargin' ? null : 'titleMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'titleMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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

    <!-- Gradient Font Color -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center">
            <label class="text-[12px] font-bold text-[#333]">Gradient Font Color</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
            <button @click="editingElement.settings.useGradient = true"
                    :class="editingElement.settings.useGradient ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="px-6 py-1.5 text-[11px] font-bold rounded transition-all">Yes</button>
            <button @click="editingElement.settings.useGradient = false"
                    :class="!editingElement.settings.useGradient ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                    class="px-6 py-1.5 text-[11px] font-bold rounded transition-all">No</button>
        </div>
        <template v-if="editingElement.settings.useGradient">
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Start Color</label>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'gradientStartColor')">
                        <div :style="{ backgroundColor: editingElement.settings.gradientStartColor || editingElement.settings.titleColor || '#222' }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.gradientStartColor"
                           :placeholder="editingElement.settings.titleColor || '#222222'"
                           class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">End Color</label>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'gradientEndColor')">
                        <div :style="{ backgroundColor: editingElement.settings.gradientEndColor || '#2271b1' }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.gradientEndColor"
                           placeholder="#0091ea"
                           class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Angle</label>
                <div class="flex gap-3 items-center">
                    <input type="text" v-model.number="editingElement.settings.gradientAngle"
                           class="w-14 border border-slate-200 rounded py-2 text-center text-[12px]">
                    <input type="range" v-model.number="editingElement.settings.gradientAngle"
                           min="0" max="360" class="flex-1 accent-[#0091ea]">
                    <span class="text-[11px] text-slate-400">°</span>
                </div>
            </div>
        </template>
    </div>

    <!-- Separator -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333]">Separator</label>
        </div>
        <select v-model="editingElement.settings.separator"
                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
            <option value="none">None</option>
            <option value="default">Default</option>
            <option value="solid">Solid</option>
            <option value="double">Double</option>
            <option value="dashed">Dashed</option>
            <option value="dotted">Dotted</option>
        </select>

        <template v-if="editingElement.settings.separator && editingElement.settings.separator !== 'none'">
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Spacing Above Separator (px)</label>
                <div class="flex gap-3 items-center">
                    <input type="text" v-model.number="editingElement.settings.separatorSpacing"
                           class="w-14 border border-slate-200 rounded py-2 text-center text-[12px]">
                    <input type="range" v-model.number="editingElement.settings.separatorSpacing"
                           min="0" max="100" class="flex-1 accent-[#0091ea]">
                </div>
            </div>
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Separator Width (px)</label>
                <div class="flex gap-3 items-center">
                    <input type="text" v-model.number="editingElement.settings.dividerWidth"
                           class="w-14 border border-slate-200 rounded py-2 text-center text-[12px]">
                    <input type="range" v-model.number="editingElement.settings.dividerWidth"
                           min="1" max="500" class="flex-1 accent-[#0091ea]">
                </div>
            </div>
        </template>
    </div>

    <!-- Separator Color (shown when separator is not none) -->
    <div v-if="editingElement.settings.separator && editingElement.settings.separator !== 'none'" class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Separator Color</label>
            <button @click="editingElement.settings.separatorColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 border border-slate-200 cursor-pointer shadow-sm"
                 @click="openColorPicker($event, editingElement.settings, 'separatorColor')">
                <div :style="{ backgroundColor: editingElement.settings.separatorColor || '#2271b1' }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.separatorColor" class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px]">
            </div>
        </div>
    </div>

    <!-- Link Color (only when Link is On) -->
    <div v-if="editingElement.settings.useLink" class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link Color</label>
            <button @click="editingElement.settings.linkColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 border border-slate-200 cursor-pointer shadow-sm"
                 @click="openColorPicker($event, editingElement.settings, 'linkColor')">
                <div :style="{ backgroundColor: editingElement.settings.linkColor }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.linkColor" class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px]">
            </div>
        </div>
    </div>

    <!-- Link Hover Color (only when Link is On) -->
    <div v-if="editingElement.settings.useLink" class="pt-4 border-t border-slate-50 pb-10">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link Hover Color</label>
            <button @click="editingElement.settings.linkHoverColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 border border-slate-200 cursor-pointer shadow-sm"
                 @click="openColorPicker($event, editingElement.settings, 'linkHoverColor')">
                <div :style="{ backgroundColor: editingElement.settings.linkHoverColor }" class="w-full h-full"></div>
            </div>
            <div class="relative flex-1">
                <input type="text" v-model="editingElement.settings.linkHoverColor" class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px]">
            </div>
        </div>
    </div>
    <div v-if="!editingElement.settings.useLink" class="pb-10"></div>
</div>
