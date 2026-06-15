<div class="space-y-8 pb-10">
    <!-- Collapse to Mobile Breakpoint -->
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <label class="text-[12px] font-bold text-slate-700">Collapse to Mobile Breakpoint</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.mobileCollapseBreakpoint = 'none'"
                    :class="editingElement.settings.mobileCollapseBreakpoint === 'none' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                    class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all"
                    title="Never">
                <i class="fa fa-times text-xs"></i>
            </button>
            <button @click="editingElement.settings.mobileCollapseBreakpoint = 'mobile'"
                    :class="editingElement.settings.mobileCollapseBreakpoint === 'mobile' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                    class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all"
                    title="Small">
                <i class="fa fa-mobile-alt text-xs"></i>
            </button>
            <button @click="editingElement.settings.mobileCollapseBreakpoint = 'tablet'"
                    :class="(editingElement.settings.mobileCollapseBreakpoint === 'tablet' || !editingElement.settings.mobileCollapseBreakpoint) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                    class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all"
                    title="Medium">
                <i class="fa fa-tablet-alt text-xs"></i>
            </button>
            <button @click="editingElement.settings.mobileCollapseBreakpoint = 'desktop'"
                    :class="editingElement.settings.mobileCollapseBreakpoint === 'desktop' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                    class="flex-1 py-2.5 flex items-center justify-center transition-all"
                    title="Large">
                <i class="fa fa-desktop text-xs"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Mode -->
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <label class="text-[12px] font-bold text-slate-700">Mobile Menu Mode</label>
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
            <button @click="editingElement.settings.mobileMenuMode = 'collapsed'"
                    :class="(editingElement.settings.mobileMenuMode === 'collapsed' || !editingElement.settings.mobileMenuMode) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                    class="px-5 py-2 text-[11px] font-bold rounded transition-all">Collapsed</button>
            <button @click="editingElement.settings.mobileMenuMode = 'expanded'"
                    :class="editingElement.settings.mobileMenuMode === 'expanded' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                    class="px-5 py-2 text-[11px] font-bold rounded transition-all">Expanded</button>
        </div>
    </div>

    <!-- Trigger Options -->
    <div class="space-y-8 animate-fade-in pt-4 border-t border-slate-50">
        
        <!-- Mobile Menu Expand Mode -->
        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Expand Mode</label>
            </div>
            <select v-model="editingElement.settings.mobileMenuExpandMode" 
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                <option value="full-width-static">Full Width - Static</option>
                <option value="full-width-absolute">Full Width - Absolute</option>
                <option value="sidebar">Sidebar</option>
            </select>
        </div>

        <!-- Sidebar Side (Conditional) -->
        <div v-if="editingElement.settings.mobileMenuExpandMode === 'sidebar'" class="space-y-3 animate-fade-in">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Sidebar Orientation</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                <button @click="editingElement.settings.mobileMenuSidebarSide = 'left'"
                        :class="(editingElement.settings.mobileMenuSidebarSide === 'left' || !editingElement.settings.mobileMenuSidebarSide) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-1.5 text-[10px] font-black uppercase rounded transition-all">Left</button>
                <button @click="editingElement.settings.mobileMenuSidebarSide = 'right'"
                        :class="editingElement.settings.mobileMenuSidebarSide === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-1.5 text-[10px] font-black uppercase rounded transition-all">Right</button>
            </div>
        </div>

        <!-- Mobile Menu Trigger Expand Icon -->
        <div class="space-y-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">Mobile Menu Trigger Expand Icon</label>
            <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-hidden">
                <div class="p-2 border-b border-slate-200">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                        <input type="text" v-model="searchIconQuery" placeholder="Search icons..."
                               class="w-full pl-8 pr-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
                <div class="flex border-b border-slate-200 bg-slate-100/50">
                    <button v-for="tab in ['Solid', 'Regular', 'Brands']" :key="tab"
                            @click="activeIconTab = tab"
                            :class="activeIconTab === tab ? 'text-[#0091ea] bg-white border-b-2 border-b-[#0091ea]' : 'text-slate-400 hover:text-slate-600'"
                            class="flex-1 py-2 text-[10px] font-bold uppercase transition-all">
                        @{{ tab }}
                    </button>
                </div>
                <div class="h-40 overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-200 bg-white">
                    <div class="grid grid-cols-5 gap-1.5">
                        <button v-for="icon in filteredIcons" :key="icon"
                                @click="selectIcon(editingElement.settings, icon, 'mobileMenuTriggerExpandIcon')"
                                :class="editingElement.settings.mobileMenuTriggerExpandIcon === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                                class="aspect-square flex items-center justify-center rounded border transition-all p-1">
                            <i :class="[icon, 'text-base']"></i>
                        </button>
                    </div>
                </div>
                <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center text-[#0091ea]">
                            <i :class="editingElement.settings.mobileMenuTriggerExpandIcon || 'fas fa-plus'"></i>
                        </div>
                        <span class="text-[10px] text-slate-500 font-medium truncate max-w-[100px]">@{{ editingElement.settings.mobileMenuTriggerExpandIcon || 'No icon' }}</span>
                    </div>
                    <button v-if="editingElement.settings.mobileMenuTriggerExpandIcon" @click="editingElement.settings.mobileMenuTriggerExpandIcon = ''" class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Trigger Collapse Icon -->
        <div class="space-y-3">
            <label class="text-[12px] font-bold text-[#333] uppercase">Mobile Menu Trigger Collapse Icon</label>
            <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-hidden">
                <div class="p-2 border-b border-slate-200">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                        <input type="text" v-model="searchIconQuery" placeholder="Search icons..."
                               class="w-full pl-8 pr-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
                <div class="flex border-b border-slate-200 bg-slate-100/50">
                    <button v-for="tab in ['Solid', 'Regular', 'Brands']" :key="tab"
                            @click="activeIconTab = tab"
                            :class="activeIconTab === tab ? 'text-[#0091ea] bg-white border-b-2 border-b-[#0091ea]' : 'text-slate-400 hover:text-slate-600'"
                            class="flex-1 py-2 text-[10px] font-bold uppercase transition-all">
                        @{{ tab }}
                    </button>
                </div>
                <div class="h-40 overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-200 bg-white">
                    <div class="grid grid-cols-5 gap-1.5">
                        <button v-for="icon in filteredIcons" :key="icon"
                                @click="selectIcon(editingElement.settings, icon, 'mobileMenuTriggerCollapseIcon')"
                                :class="editingElement.settings.mobileMenuTriggerCollapseIcon === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                                class="aspect-square flex items-center justify-center rounded border transition-all p-1">
                            <i :class="[icon, 'text-base']"></i>
                        </button>
                    </div>
                </div>
                <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center text-[#0091ea]">
                            <i :class="editingElement.settings.mobileMenuTriggerCollapseIcon || 'fas fa-plus'"></i>
                        </div>
                        <span class="text-[10px] text-slate-500 font-medium truncate max-w-[100px]">@{{ editingElement.settings.mobileMenuTriggerCollapseIcon || 'No icon' }}</span>
                    </div>
                    <button v-if="editingElement.settings.mobileMenuTriggerCollapseIcon" @click="editingElement.settings.mobileMenuTriggerCollapseIcon = ''" class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
                </div>
            </div>
        </div>

        <!-- Options below only shown when at least one icon is selected -->
        <template v-if="editingElement.settings.mobileMenuTriggerExpandIcon || editingElement.settings.mobileMenuTriggerCollapseIcon">

        <!-- Mobile Menu Trigger Padding -->
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Trigger Padding</label>
            </div>
            <div class="grid grid-cols-4 gap-2">
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuTriggerPaddingTop" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Top</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuTriggerPaddingRight" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Right</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuTriggerPaddingBottom" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Bottom</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuTriggerPaddingLeft" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Left</div>
                </div>
            </div>
        </div>

        <!-- Trigger to Menu Spacing -->
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Trigger to Menu Spacing</label>
            </div>
            <div class="flex items-center gap-4">
                <input type="number" v-model="editingElement.settings.mobileMenuTriggerSpacing"
                       class="w-16 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                <input type="range" v-model="editingElement.settings.mobileMenuTriggerSpacing" min="0" max="50" step="1"
                       class="flex-1 accent-[#0091ea]">
            </div>
        </div>

        <!-- Trigger Colors -->
        <div class="space-y-5 pt-2">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Trigger Background Color</label>
                    <button @click="editingElement.settings.mobileMenuTriggerBgColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'mobileMenuTriggerBgColor')">
                        <div :style="{ backgroundColor: editingElement.settings.mobileMenuTriggerBgColor }" class="w-full h-full border border-slate-200 rounded-full"></div>
                    </div>
                    <div class="flex-1 relative">
                        <input type="text" v-model="editingElement.settings.mobileMenuTriggerBgColor" class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Trigger Text Color</label>
                    <button @click="editingElement.settings.mobileMenuTriggerTextColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'mobileMenuTriggerTextColor')">
                        <div :style="{ backgroundColor: editingElement.settings.mobileMenuTriggerTextColor }" class="w-full h-full border border-slate-200 rounded-full"></div>
                    </div>
                    <div class="flex-1 relative">
                        <input type="text" v-model="editingElement.settings.mobileMenuTriggerTextColor" class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Trigger Text -->
        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Trigger Text</label>
            </div>
            <input type="text" v-model="editingElement.settings.mobileMenuTriggerText" placeholder="e.g. Menu"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>

        <!-- Mobile Menu Trigger Font Size -->
        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Trigger Font Size</label>
            </div>
            <input type="text" v-model="editingElement.settings.mobileMenuTriggerFontSize" placeholder="e.g. 16px"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>

        <!-- Mobile Menu Trigger Horizontal Align -->
        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Trigger Horizontal Align</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden w-fit">
                <button @click="editingElement.settings.mobileMenuTriggerHorizontalAlign = 'flex-start'"
                        :class="(editingElement.settings.mobileMenuTriggerHorizontalAlign === 'flex-start' || !editingElement.settings.mobileMenuTriggerHorizontalAlign) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-3 flex items-center justify-center border-r border-slate-100 transition-all">
                    <i class="fa fa-align-left"></i>
                </button>
                <button @click="editingElement.settings.mobileMenuTriggerHorizontalAlign = 'center'"
                        :class="editingElement.settings.mobileMenuTriggerHorizontalAlign === 'center' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-3 flex items-center justify-center border-r border-slate-100 transition-all">
                    <i class="fa fa-align-center"></i>
                </button>
                <button @click="editingElement.settings.mobileMenuTriggerHorizontalAlign = 'flex-end'"
                        :class="editingElement.settings.mobileMenuTriggerHorizontalAlign === 'flex-end' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-3 flex items-center justify-center transition-all">
                    <i class="fa fa-align-right"></i>
                </button>
            </div>
        </div>

        </template>

    </div>

    <!-- Common Options -->
    <div class="space-y-6 pt-6 border-t border-slate-50">
        <div class="flex items-center gap-2">
            <div class="w-1 h-3 bg-[#2271b1] rounded-full"></div>
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Common Styling</span>
        </div>

        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Mobile Menu Item Minimum Height</label>
            </div>
            <div class="flex items-center gap-4">
                <input type="number" v-model="editingElement.settings.mobileMenuItemMinHeight" 
                    class="w-16 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                <input type="range" v-model="editingElement.settings.mobileMenuItemMinHeight" min="30" max="150" step="1" 
                    class="flex-1 accent-[#0091ea]">
            </div>
        </div>

        <!-- Mobile Menu Item Padding -->
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Mobile Menu Item Padding</label>
            </div>
            <div class="grid grid-cols-4 gap-2">
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuItemPaddingTop" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Top</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuItemPaddingRight" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Right</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuItemPaddingBottom" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Bottom</div>
                </div>
                <div class="space-y-1">
                    <input type="number" v-model="editingElement.settings.mobileMenuItemPaddingLeft" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                    <div class="text-[8px] text-slate-400 font-bold uppercase text-center">Left</div>
                </div>
            </div>
        </div>

        <!-- Text Align -->
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Mobile Menu Text Align</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden w-fit">
                <button @click="editingElement.settings.mobileMenuTextAlign = 'left'"
                        :class="(editingElement.settings.mobileMenuTextAlign === 'left' || !editingElement.settings.mobileMenuTextAlign) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-6 py-2.5 text-[11px] font-bold border-r border-slate-100 transition-all">Left</button>
                <button @click="editingElement.settings.mobileMenuTextAlign = 'center'"
                        :class="editingElement.settings.mobileMenuTextAlign === 'center' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-6 py-2.5 text-[11px] font-bold border-r border-slate-100 transition-all">Center</button>
                <button @click="editingElement.settings.mobileMenuTextAlign = 'right'"
                        :class="editingElement.settings.mobileMenuTextAlign === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-6 py-2.5 text-[11px] font-bold transition-all">Right</button>
            </div>
        </div>

        <!-- Indent Submenus -->
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Mobile Menu Indent Submenus</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden w-fit">
                <button @click="editingElement.settings.mobileMenuIndentSubmenus = 'on'"
                        :class="(editingElement.settings.mobileMenuIndentSubmenus === 'on' || !editingElement.settings.mobileMenuIndentSubmenus) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-2.5 text-[11px] font-bold border-r border-slate-100 transition-all">On</button>
                <button @click="editingElement.settings.mobileMenuIndentSubmenus = 'off'"
                        :class="editingElement.settings.mobileMenuIndentSubmenus === 'off' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-8 py-2.5 text-[11px] font-bold transition-all">Off</button>
            </div>
        </div>

        <!-- Typography Section -->
        <div class="pt-6 border-t border-slate-100 space-y-6">
            <div class="flex items-center gap-2">
                <div class="w-1 h-3 bg-[#2271b1] rounded-full"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mobile Menu Typography</span>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Font Family</label>
                    <select v-model="editingElement.settings.mobileMenuFontFamily" 
                            class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                        <option value="inherit">Default</option>
                        <optgroup v-for="(fonts, category) in builderFontGroups" :label="category">
                            <option v-for="font in fonts" :value="font.family">@{{ font.family }}</option>
                        </optgroup>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Font Size</label>
                        <input type="text" v-model="editingElement.settings.mobileMenuFontSize" placeholder="e.g. 16px" 
                               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Font Weight</label>
                        <select v-model="editingElement.settings.mobileMenuFontWeight" 
                                class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                            <option value="100">100 - Thin</option>
                            <option value="200">200 - Extra Light</option>
                            <option value="300">300 - Light</option>
                            <option value="400">400 - Normal</option>
                            <option value="500">500 - Medium</option>
                            <option value="600">600 - Semi Bold</option>
                            <option value="700">700 - Bold</option>
                            <option value="800">800 - Extra Bold</option>
                            <option value="900">900 - Black</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Line Height</label>
                        <input type="text" v-model="editingElement.settings.mobileMenuLineHeight" placeholder="e.g. 1.5" 
                               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Letter Spacing</label>
                        <input type="text" v-model="editingElement.settings.mobileMenuLetterSpacing" placeholder="e.g. 0.5px" 
                               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Text Transform</label>
                    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                        <button @click="editingElement.settings.mobileMenuTextTransform = 'none'"
                                :class="(editingElement.settings.mobileMenuTextTransform === 'none' || !editingElement.settings.mobileMenuTextTransform) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                                class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all uppercase">None</button>
                        <button @click="editingElement.settings.mobileMenuTextTransform = 'uppercase'"
                                :class="editingElement.settings.mobileMenuTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                                class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                        <button @click="editingElement.settings.mobileMenuTextTransform = 'lowercase'"
                                :class="editingElement.settings.mobileMenuTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                                class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                        <button @click="editingElement.settings.mobileMenuTextTransform = 'capitalize'"
                                :class="editingElement.settings.mobileMenuTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                                class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colors Section -->
        <div class="pt-6 border-t border-slate-100 space-y-6">
            <div class="flex items-center gap-2">
                <div class="w-1 h-3 bg-[#2271b1] rounded-full"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mobile Menu Colors</span>
            </div>

            <div class="space-y-5">
                <!-- Mobile Menu Separator -->
                <div>
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Mobile Menu Separator</label>
                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                        <button @click="editingElement.settings.mobileSeparatorEnabled = 'yes'"
                                :class="(editingElement.settings.mobileSeparatorEnabled === 'yes' || !editingElement.settings.mobileSeparatorEnabled) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                                class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">Yes</button>
                        <button @click="editingElement.settings.mobileSeparatorEnabled = 'no'"
                                :class="editingElement.settings.mobileSeparatorEnabled === 'no' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                                class="px-6 py-1.5 text-[10px] font-black uppercase rounded transition-all">No</button>
                    </div>
                </div>

                <!-- Separator Color — enabled only when separator is on -->
                <div v-if="editingElement.settings.mobileSeparatorEnabled !== 'no'">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Separator Color</label>
                        <button @click="editingElement.settings.mobileMenuSeparatorColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                            <i class="fa fa-undo text-[10px]"></i>
                        </button>
                    </div>
                    <div class="flex gap-2 items-center">
                        <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                             @click="openColorPicker($event, editingElement.settings, 'mobileMenuSeparatorColor')">
                            <div :style="{ backgroundColor: editingElement.settings.mobileMenuSeparatorColor }" class="w-full h-full border border-slate-200 rounded-full"></div>
                        </div>
                        <div class="flex-1 relative">
                            <input type="text" v-model="editingElement.settings.mobileMenuSeparatorColor" class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                        </div>
                    </div>
                </div>

                <!-- Background Colors -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">BG Color</label>
                            <button @click="editingElement.settings.mobileMenuBgColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'mobileMenuBgColor')">
                                <div :style="{ backgroundColor: editingElement.settings.mobileMenuBgColor }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.mobileMenuBgColor" class="w-full border border-slate-200 rounded px-2 py-2 text-[11px] focus:outline-none focus:border-[#0091ea]">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">BG Hover</label>
                            <button @click="editingElement.settings.mobileMenuBgColorHover = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'mobileMenuBgColorHover')">
                                <div :style="{ backgroundColor: editingElement.settings.mobileMenuBgColorHover }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.mobileMenuBgColorHover" class="w-full border border-slate-200 rounded px-2 py-2 text-[11px] focus:outline-none focus:border-[#0091ea]">
                        </div>
                    </div>
                </div>

                <!-- Text Colors -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Text Color</label>
                            <button @click="editingElement.settings.mobileMenuTextColor = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'mobileMenuTextColor')">
                                <div :style="{ backgroundColor: editingElement.settings.mobileMenuTextColor }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.mobileMenuTextColor" class="w-full border border-slate-200 rounded px-2 py-2 text-[11px] focus:outline-none focus:border-[#0091ea]">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block">Text Hover</label>
                            <button @click="editingElement.settings.mobileMenuTextColorHover = ''" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                                <i class="fa fa-undo text-[10px]"></i>
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <div class="checkerboard rounded overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                 @click="openColorPicker($event, editingElement.settings, 'mobileMenuTextColorHover')">
                                <div :style="{ backgroundColor: editingElement.settings.mobileMenuTextColorHover }" class="w-full h-full"></div>
                            </div>
                            <input type="text" v-model="editingElement.settings.mobileMenuTextColorHover" class="w-full border border-slate-200 rounded px-2 py-2 text-[11px] focus:outline-none focus:border-[#0091ea]">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
