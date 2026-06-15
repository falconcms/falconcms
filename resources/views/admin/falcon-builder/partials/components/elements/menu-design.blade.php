<div class="space-y-8 pb-10">
    <!-- Layout Settings -->
    <div class="space-y-6">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-[#2271b1] rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Layout Settings</h4>
        </div>

        <div class="space-y-6">
            <!-- Minimum Height -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">MINIMUM HEIGHT (PX)</label>
                <input type="number" v-model="editingElement.settings.minHeight" 
                       placeholder="e.g. 60"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            </div>

            <!-- Align Items -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">ALIGN ITEMS</label>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    <button @click="editingElement.settings.alignItems = 'flex-start'"
                            :class="(editingElement.settings.alignItems === 'flex-start' || !editingElement.settings.alignItems) ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Align Top">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="5" y="4" width="3" height="10" rx="0.5"/>
                            <rect x="10.5" y="4" width="3" height="14" rx="0.5"/>
                            <rect x="16" y="4" width="3" height="8" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.alignItems = 'center'"
                            :class="editingElement.settings.alignItems === 'center' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Align Center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="5" y="7" width="3" height="10" rx="0.5"/>
                            <rect x="10.5" y="5" width="3" height="14" rx="0.5"/>
                            <rect x="16" y="8" width="3" height="8" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.alignItems = 'flex-end'"
                            :class="editingElement.settings.alignItems === 'flex-end' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Align Bottom">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="5" y="10" width="3" height="10" rx="0.5"/>
                            <rect x="10.5" y="6" width="3" height="14" rx="0.5"/>
                            <rect x="16" y="12" width="3" height="8" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.alignItems = 'stretch'"
                            :class="editingElement.settings.alignItems === 'stretch' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Stretch">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 3l3 3h-2v12h2l-3 3-3-3h2V6H9l3-3z" fill="currentColor"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Justification (Flex Justify Content) -->
            <div class="pt-2">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">JUSTIFICATION</label>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="editingElement.settings.justification = 'flex-start'"
                            :class="(editingElement.settings.justification === 'flex-start' || !editingElement.settings.justification) ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Justify Left">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="4" y="5" width="8" height="3" rx="0.5"/>
                            <rect x="4" y="10.5" width="14" height="3" rx="0.5"/>
                            <rect x="4" y="16" width="11" height="3" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.justification = 'center'"
                            :class="editingElement.settings.justification === 'center' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Justify Center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="8" y="5" width="8" height="3" rx="0.5"/>
                            <rect x="5" y="10.5" width="14" height="3" rx="0.5"/>
                            <rect x="6.5" y="16" width="11" height="3" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.justification = 'flex-end'"
                            :class="editingElement.settings.justification === 'flex-end' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Justify Right">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="12" y="5" width="8" height="3" rx="0.5"/>
                            <rect x="6" y="10.5" width="14" height="3" rx="0.5"/>
                            <rect x="9" y="16" width="11" height="3" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.justification = 'space-between'"
                            :class="editingElement.settings.justification === 'space-between' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Space Between">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 16v-3h12v3l4-4-4-4v3H6V8l-4 4 4 4z" fill="currentColor"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.justification = 'space-around'"
                            :class="editingElement.settings.justification === 'space-around' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Space Around">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="4" y="6" width="16" height="3" rx="0.5"/>
                            <rect x="4" y="11" width="16" height="3" rx="0.5"/>
                            <rect x="4" y="16" width="16" height="3" rx="0.5"/>
                        </svg>
                    </button>
                    <button @click="editingElement.settings.justification = 'space-evenly'"
                            :class="editingElement.settings.justification === 'space-evenly' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'"
                            class="py-2 rounded transition-all flex items-center justify-center relative group/btn h-10" title="Space Evenly">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="4" y="9.5" width="16" height="5" rx="0.5"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Typography Settings -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-[#2271b1] rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Typography Settings</h4>
        </div>

        <div class="space-y-6">
            <!-- Font Family -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">FONT FAMILY</label>
                <select v-model="editingElement.settings.fontFamily"
                        @change="loadBuilderFont(editingElement.settings.fontFamily)"
                        class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
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

            <!-- Font Size & Weight -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">FONT SIZE</label>
                    <input type="text" v-model="editingElement.settings.fontSize" 
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">FONT WEIGHT</label>
                    <select v-model="editingElement.settings.fontWeight"
                            class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                        <option v-for="v in titleFontVariants" :key="v" :value="v">@{{ ({
                            '100':'Thin 100','200':'Extra Light 200','300':'Light 300',
                            '400':'Regular 400','500':'Medium 500','600':'Semi Bold 600',
                            '700':'Bold 700','800':'Extra Bold 800','900':'Black 900'
                        })[v] || ('Weight ' + v) }}</option>
                    </select>
                </div>
            </div>

            <!-- Line Height & Letter Spacing -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">LINE HEIGHT</label>
                    <input type="text" v-model="editingElement.settings.lineHeight" 
                           placeholder="e.g. 1.5"
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">LETTER SPACING</label>
                    <input type="text" v-model="editingElement.settings.letterSpacing" 
                           placeholder="e.g. 1px"
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>

            <!-- Text Transform -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">TEXT TRANSFORM</label>
                <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                    <button @click="editingElement.settings.textTransform = 'none'"
                            :class="editingElement.settings.textTransform === 'none' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all uppercase">None</button>
                    <button @click="editingElement.settings.textTransform = 'uppercase'"
                            :class="editingElement.settings.textTransform === 'uppercase' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                    <button @click="editingElement.settings.textTransform = 'lowercase'"
                            :class="editingElement.settings.textTransform === 'lowercase' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                    <button @click="editingElement.settings.textTransform = 'capitalize'"
                            :class="editingElement.settings.textTransform === 'capitalize' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Item Styling -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-[#2271b1] rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Menu Item Styling</h4>
        </div>

        <div class="space-y-6">
            <!-- Padding (4 Boxes) -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">ITEM PADDING (T | R | B | L)</label>
                <div class="grid grid-cols-4 gap-2">
                    <div class="space-y-1">
                        <input type="number" v-model="editingElement.settings.itemPaddingTop" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Top">
                        <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Top</div>
                    </div>
                    <div class="space-y-1">
                        <input type="number" v-model="editingElement.settings.itemPaddingRight" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Right">
                        <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Right</div>
                    </div>
                    <div class="space-y-1">
                        <input type="number" v-model="editingElement.settings.itemPaddingBottom" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Bottom">
                        <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Bottom</div>
                    </div>
                    <div class="space-y-1">
                        <input type="number" v-model="editingElement.settings.itemPaddingLeft" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Left">
                        <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Left</div>
                    </div>
                </div>
            </div>

            <!-- Spacing & Border Radius -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">ITEM SPACING</label>
                    <input type="number" v-model="editingElement.settings.itemSpacing" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">BORDER RADIUS</label>
                    <input type="number" v-model="editingElement.settings.itemBorderRadius" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>

            <!-- Transition Slider -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">HOVER TRANSITION (S)</label>
                    <span class="text-[10px] font-bold text-[#0091ea]">@{{ editingElement.settings.itemTransition }}s</span>
                </div>
                <input type="range" v-model="editingElement.settings.itemTransition" min="0" max="2" step="0.1" class="w-full accent-[#0091ea]">
            </div>

            <!-- Item Colors -->
            <div class="space-y-4">
                <!-- Background Colors -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">BG COLOR</label>
                            <button @click="editingElement.settings.itemBgColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'itemBgColor')">
                                <div :style="{ backgroundColor: editingElement.settings.itemBgColor }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.itemBgColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">BG HOVER</label>
                            <button @click="editingElement.settings.itemBgColorHover = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'itemBgColorHover')">
                                <div :style="{ backgroundColor: editingElement.settings.itemBgColorHover }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.itemBgColorHover" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                        </div>
                    </div>
                </div>

                <!-- Text Colors -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">TEXT COLOR</label>
                            <button @click="editingElement.settings.itemColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'itemColor')">
                                <div :style="{ backgroundColor: editingElement.settings.itemColor }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.itemColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">TEXT HOVER</label>
                            <button @click="editingElement.settings.itemColorHover = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'itemColorHover')">
                                <div :style="{ backgroundColor: editingElement.settings.itemColorHover }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.itemColorHover" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Border Styling (Normal & Hover) -->
            <div class="space-y-6">
                <!-- Normal Border -->
                <div class="space-y-4">
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">ITEM BORDER (NORMAL)</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeTop" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Top">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Top</div>
                            </div>
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeRight" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Right">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Right</div>
                            </div>
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeBottom" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Bottom">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Bottom</div>
                            </div>
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeLeft" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Left">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Left</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">BORDER COLOR (NORMAL)</label>
                            <button @click="editingElement.settings.itemBorderColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'itemBorderColor')">
                                <div :style="{ backgroundColor: editingElement.settings.itemBorderColor }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.itemBorderColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                        </div>
                    </div>
                </div>

                <!-- Hover Border -->
                <div class="space-y-4 pt-4 border-t border-slate-50">
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">ITEM BORDER (HOVER)</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeTopHover" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Top Hover">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Top</div>
                            </div>
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeRightHover" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Right Hover">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Right</div>
                            </div>
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeBottomHover" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Bottom Hover">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Bottom</div>
                            </div>
                            <div class="space-y-1">
                                <input type="number" v-model="editingElement.settings.itemBorderSizeLeftHover" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]" title="Left Hover">
                                <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Left</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">BORDER COLOR (HOVER)</label>
                            <button @click="editingElement.settings.itemBorderColorHover = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'itemBorderColorHover')">
                                <div :style="{ backgroundColor: editingElement.settings.itemBorderColorHover }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.itemBorderColorHover" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Icon (position + gap between icon and label) -->
    <div class="space-y-6">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-[#2271b1] rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Menu Icon</h4>
        </div>
        <div class="space-y-6">
            <!-- Icon Position -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">ICON POSITION</label>
                <div class="flex bg-slate-100 rounded-lg p-1">
                    <button type="button" @click="editingElement.settings.menuIconPosition = 'left'"
                            :class="(editingElement.settings.menuIconPosition || 'left') === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-500'"
                            class="flex-1 py-2 text-[11px] font-bold rounded transition-all">Left</button>
                    <button type="button" @click="editingElement.settings.menuIconPosition = 'right'"
                            :class="editingElement.settings.menuIconPosition === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-500'"
                            class="flex-1 py-2 text-[11px] font-bold rounded transition-all">Right</button>
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5">Show the icon before (Left) or after (Right) the menu label.</p>
            </div>
            <!-- Icon Gap -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">ICON GAP (PX)</label>
                <input type="number" min="0" max="60" v-model="editingElement.settings.menuIconGap"
                       placeholder="6"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                <p class="text-[10px] text-slate-400 mt-1.5">Space between the icon and the menu label.</p>
            </div>
        </div>
    </div>
</div>
