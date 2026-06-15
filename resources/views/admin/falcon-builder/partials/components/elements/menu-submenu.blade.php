<div class="space-y-8 pb-10">
    <!-- Submenu Behavior -->
    <div class="space-y-6">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-slate-400 rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Submenu Behavior</h4>
        </div>

        <div class="space-y-5">
            <!-- Dropdown Carets — hidden when General tab's Menu Arrows > Main is active -->
            <div v-if="!editingElement.settings.arrowScopeObj?.main">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Dropdown Carets</label>
                <p class="text-[10px] text-slate-400 mb-2">
                    <span v-if="editingElement.settings.arrowScopeObj?.submenu">Controls sub-of-sub menu arrows only (Sub Menu is active in General → Menu Arrows).</span>
                    <span v-else>Controls arrows for both Main &amp; Sub menu items.</span>
                </p>
                <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                    <button @click="editingElement.settings.showArrows = 'yes'"
                            :class="(editingElement.settings.showArrows === 'yes' || !editingElement.settings.showArrows) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">Yes</button>
                    <button @click="editingElement.settings.showArrows = 'no'"
                            :class="editingElement.settings.showArrows === 'no' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">No</button>
                </div>
            </div>
            <!-- Info when Dropdown Carets is hidden because Main is active in General tab -->
            <div v-else class="p-3 bg-blue-50 border border-blue-100 rounded text-[10px] text-blue-500 font-bold">
                <i class="fa fa-info-circle mr-1"></i> Main menu arrows are controlled via General → Menu Arrows.
            </div>

            <!-- Expand Direction -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Submenu Expand Direction</label>
                <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                    <button @click="editingElement.settings.submenuDirection = 'left'"
                            :class="editingElement.settings.submenuDirection === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-4 py-1.5 text-[10px] font-black uppercase rounded transition-all">Left</button>
                    <button @click="editingElement.settings.submenuDirection = 'center'"
                            :class="editingElement.settings.submenuDirection === 'center' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-4 py-1.5 text-[10px] font-black uppercase rounded transition-all">Center</button>
                    <button @click="editingElement.settings.submenuDirection = 'right'"
                            :class="(editingElement.settings.submenuDirection === 'right' || !editingElement.settings.submenuDirection) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-4 py-1.5 text-[10px] font-black uppercase rounded transition-all">Right</button>
                </div>
            </div>

            <!-- Expand Transition -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Submenu Expand Transition</label>
                <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                    <button @click="editingElement.settings.submenuTransition = 'fade'"
                            :class="(editingElement.settings.submenuTransition === 'fade' || !editingElement.settings.submenuTransition) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-3 py-1.5 text-[10px] font-black uppercase rounded transition-all">Fade</button>
                    <button @click="editingElement.settings.submenuTransition = 'slide-up'"
                            :class="editingElement.settings.submenuTransition === 'slide-up' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-3 py-1.5 text-[10px] font-black uppercase rounded transition-all">Slide Up</button>
                    <button @click="editingElement.settings.submenuTransition = 'slide-down'"
                            :class="editingElement.settings.submenuTransition === 'slide-down' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-3 py-1.5 text-[10px] font-black uppercase rounded transition-all">Slide Down</button>
                </div>
            </div>

            <!-- Max Width -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Submenu Min Width</label>
                    <input type="text" v-model="editingElement.settings.submenuMinWidth" placeholder="e.g. 200px"
                           @input="parseInt(editingElement.settings.submenuMaxWidth) < parseInt(editingElement.settings.submenuMinWidth) ? (editingElement.settings.submenuMaxWidth = editingElement.settings.submenuMinWidth) : null"
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Submenu Max Width</label>
                    <input type="text" v-model="editingElement.settings.submenuMaxWidth" placeholder="e.g. 300px"
                           @input="parseInt(editingElement.settings.submenuMaxWidth) < parseInt(editingElement.settings.submenuMinWidth) ? (editingElement.settings.submenuMaxWidth = editingElement.settings.submenuMinWidth) : null"
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>

            <!-- Submenu Vertical Offset -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Submenu Vertical Offset (PX)</label>
                    <span class="text-[10px] font-bold text-[#0091ea]">@{{ editingElement.settings.submenuSpace ?? 10 }}px</span>
                </div>
                <input type="range" v-model.number="editingElement.settings.submenuSpace" min="0" max="100" step="1" class="w-full accent-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- Sub-of-Sub Menu Behavior -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-slate-400 rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Sub-of-Sub Menu</h4>
        </div>

        <div class="space-y-5">
            <!-- Direction -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Expand Direction</label>
                <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                    <button @click="editingElement.settings.subSubMenuDirection = 'left'"
                            :class="editingElement.settings.subSubMenuDirection === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">Left</button>
                    <button @click="editingElement.settings.subSubMenuDirection = 'right'"
                            :class="(editingElement.settings.subSubMenuDirection === 'right' || !editingElement.settings.subSubMenuDirection) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                            class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">Right</button>
                </div>
            </div>

            <!-- Horizontal Offset -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Horizontal Offset (PX)</label>
                    <span class="text-[10px] font-bold text-[#0091ea]">@{{ editingElement.settings.subSubMenuOffset ?? 5 }}px</span>
                </div>
                <input type="range" v-model.number="editingElement.settings.subSubMenuOffset" min="0" max="50" step="1" class="w-full accent-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- Submenu Typography -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-slate-400 rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Submenu Typography</h4>
        </div>

        <div class="space-y-5">
            <!-- Font Family -->
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block mb-2">FONT FAMILY</label>
                <select v-model="editingElement.settings.submenuFontFamily"
                        @change="loadBuilderFont(editingElement.settings.submenuFontFamily)"
                        class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                    <option value="inherit">Inherit from Menu</option>
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

            <!-- Font Size, Line Height, Letter Spacing -->
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1 text-center">FONT SIZE</label>
                    <input type="text" v-model="editingElement.settings.submenuFontSize" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-center">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1 text-center">LINE HEIGHT</label>
                    <input type="text" v-model="editingElement.settings.submenuLineHeight" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-center">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1 text-center">LETTER SP.</label>
                    <input type="text" v-model="editingElement.settings.submenuLetterSpacing" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-center">
                </div>
            </div>

            <!-- Text Transform -->
            <div>
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">TEXT TRANSFORM</label>
                <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                    <button @click="editingElement.settings.submenuTextTransform = 'none'" 
                            :class="editingElement.settings.submenuTextTransform === 'none' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'" 
                            class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all uppercase">None</button>
                    <button @click="editingElement.settings.submenuTextTransform = 'uppercase'" 
                            :class="editingElement.settings.submenuTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'" 
                            class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                    <button @click="editingElement.settings.submenuTextTransform = 'lowercase'" 
                            :class="editingElement.settings.submenuTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'" 
                            class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                    <button @click="editingElement.settings.submenuTextTransform = 'capitalize'" 
                            :class="editingElement.settings.submenuTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'" 
                            class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alignment & Padding -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <!-- Item Text Align -->
        <div>
            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-3">Submenu Item Text Align</label>
            <div class="grid grid-cols-4 gap-1">
                <button @click="editingElement.settings.submenuTextAlign = 'left'"
                        :class="editingElement.settings.submenuTextAlign === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400'"
                        class="py-2.5 rounded transition-all flex items-center justify-center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 19h16v2H4v-2zm0-4h11v2H4v-2zm0-4h16v2H4v-2zm0-8h16v2H4V3zm0 4h11v2H4V7z"/></svg>
                </button>
                <button @click="editingElement.settings.submenuTextAlign = 'center'"
                        :class="editingElement.settings.submenuTextAlign === 'center' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400'"
                        class="py-2.5 rounded transition-all flex items-center justify-center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 19h16v2H4v-2zm3-4h10v2H7v-2zm-3-4h16v2H4v-2zm0-8h16v2H4V3zm3 4h10v2H7V7z"/></svg>
                </button>
                <button @click="editingElement.settings.submenuTextAlign = 'right'"
                        :class="editingElement.settings.submenuTextAlign === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400'"
                        class="py-2.5 rounded transition-all flex items-center justify-center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 19h16v2H4v-2zm5-4h11v2H9v-2zm-5-4h16v2H4v-2zm0-8h16v2H4V3zm5 4h11v2H9V7z"/></svg>
                </button>
                <button @click="editingElement.settings.submenuTextAlign = 'justify'"
                        :class="editingElement.settings.submenuTextAlign === 'justify' ? 'bg-[#2271b1] text-white shadow-sm' : 'bg-slate-100 text-slate-400'"
                        class="py-2.5 rounded transition-all flex items-center justify-center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 19h16v2H4v-2zm0-4h16v2H4v-2zm0-4h16v2H4v-2zm0-8h16v2H4V3zm0 4h16v2H4V7z"/></svg>
                </button>
            </div>
        </div>

        <!-- Padding (4 Boxes) -->
        <div>
            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-3">Submenu Item Padding</label>
            <div class="grid grid-cols-4 gap-2">
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuPaddingTop" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Top</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuPaddingRight" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Right</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuPaddingBottom" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Bottom</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuPaddingLeft" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Left</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Border Radius & Shadows -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <!-- Border Radius (4 Boxes) -->
        <div>
            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-3">Submenu Border Radius</label>
            <div class="grid grid-cols-4 gap-2">
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuBorderRadiusTopLeft" class="w-full border border-slate-200 rounded px-1 py-2 text-[11px] text-center">
                    <div class="text-[7px] text-slate-400 font-bold uppercase text-center leading-tight">Top/Left</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuBorderRadiusTopRight" class="w-full border border-slate-200 rounded px-1 py-2 text-[11px] text-center">
                    <div class="text-[7px] text-slate-400 font-bold uppercase text-center leading-tight">Top/Right</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuBorderRadiusBottomRight" class="w-full border border-slate-200 rounded px-1 py-2 text-[11px] text-center">
                    <div class="text-[7px] text-slate-400 font-bold uppercase text-center leading-tight">Bot/Right</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.submenuBorderRadiusBottomLeft" class="w-full border border-slate-200 rounded px-1 py-2 text-[11px] text-center">
                    <div class="text-[7px] text-slate-400 font-bold uppercase text-center leading-tight">Bot/Left</div>
                </div>
            </div>
        </div>

        <!-- Box Shadow -->
        <div>
            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Box Shadow</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                <button @click="editingElement.settings.submenuBoxShadow = 'yes'"
                        :class="editingElement.settings.submenuBoxShadow === 'yes' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-400'"
                        class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">Yes</button>
                <button @click="editingElement.settings.submenuBoxShadow = 'no'"
                        :class="(editingElement.settings.submenuBoxShadow === 'no' || !editingElement.settings.submenuBoxShadow) ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-400'"
                        class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">No</button>
            </div>
        </div>

        <!-- Box Shadow Details -->
        <div v-if="editingElement.settings.submenuBoxShadow === 'yes'" class="space-y-4 p-4 bg-slate-50 rounded border border-slate-100 animate-fade-in">
            <!-- Shadow Color -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">Shadow Color</label>
                    <button @click="editingElement.settings.submenuShadowColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'submenuShadowColor')">
                        <div :style="{ backgroundColor: editingElement.settings.submenuShadowColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.submenuShadowColor" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[11px]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">H-Offset</label>
                    <input type="number" v-model="editingElement.settings.submenuShadowH" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[12px]">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">V-Offset</label>
                    <input type="number" v-model="editingElement.settings.submenuShadowV" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[12px]">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Blur</label>
                    <input type="number" v-model="editingElement.settings.submenuShadowBlur" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[12px]">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Spread</label>
                    <input type="number" v-model="editingElement.settings.submenuShadowSpread" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[12px]">
                </div>
            </div>
        </div>
    </div>

    <!-- Colors -->
    <div class="space-y-6 pt-4 border-t border-slate-50">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
            <div class="w-1.5 h-4 bg-slate-400 rounded-full"></div>
            <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">Submenu Colors</h4>
        </div>

        <div class="space-y-4">
            <!-- Separator Color -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">Submenu Separator Color</label>
                    <button @click="editingElement.settings.submenuSeparatorColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'submenuSeparatorColor')">
                        <div :style="{ backgroundColor: editingElement.settings.submenuSeparatorColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.submenuSeparatorColor" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[11px]">
                </div>
            </div>

            <!-- BG Color -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">Submenu Background Color</label>
                    <button @click="editingElement.settings.submenuBgColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'submenuBgColor')">
                        <div :style="{ backgroundColor: editingElement.settings.submenuBgColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.submenuBgColor" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[11px]">
                </div>
            </div>

            <!-- Text Color -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">Submenu Text Color</label>
                    <button @click="editingElement.settings.submenuTextColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'submenuTextColor')">
                        <div :style="{ backgroundColor: editingElement.settings.submenuTextColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.submenuTextColor" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[11px]">
                </div>
            </div>
            
            <!-- Text Color Hover -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block">Submenu Text Color (Hover)</label>
                    <button @click="editingElement.settings.submenuTextColorHover = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'submenuTextColorHover')">
                        <div :style="{ backgroundColor: editingElement.settings.submenuTextColorHover }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.submenuTextColorHover" class="w-full border border-slate-200 rounded px-3 py-1.5 text-[11px]">
                </div>
            </div>
        </div>
    </div>
</div>
