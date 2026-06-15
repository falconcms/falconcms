<aside class="builder-sidebar flex flex-col" v-if="!isPreview">
    <!-- Tab Header -->
    <div class="flex border-b border-slate-100 bg-slate-50/50">
        <button @click="activeTab='navigator'"
                :class="activeTab==='navigator' ? 'text-[#0091ea] border-b-2 border-[#0091ea]' : 'text-slate-500 hover:text-[#0091ea]'"
                class="flex-1 flex items-center justify-center gap-2 h-11 transition-all group">
             <i class="fa fa-caret-down text-[10px]"></i>
             <span class="text-[11px] font-black uppercase tracking-widest">Navigator</span>
        </button>
        @if($isMegaMenuBuilder ?? false)
        <button @click="activeTab='page_options'"
                :class="activeTab==='page_options' ? 'text-[#0091ea] border-b-2 border-[#0091ea]' : 'text-slate-500 hover:text-[#0091ea]'"
                class="flex-1 flex items-center justify-center gap-1.5 h-11 transition-all border-l border-slate-100">
            <i class="fa fa-cog text-[10px]"></i>
            <span class="text-[11px] font-black uppercase tracking-widest">Page Options</span>
        </button>
        @endif
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-hidden bg-white">
        <!-- Elements Tab (Removed) -->

        <!-- Navigator Tab -->
        <div v-show="activeTab==='navigator'" class="h-full overflow-y-auto custom-scrollbar animate-fade-in py-2">
            <div v-if="layout.length === 0" class="flex flex-col items-center justify-center py-20 px-10 text-center bg-slate-50/30">
                <div class="w-14 h-14 bg-[#2271b1] rounded-lg shadow-xl shadow-blue-500/20 flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <h3 class="text-[12px] font-black text-slate-800 uppercase tracking-widest mb-2">Navigator</h3>
                <p class="text-[11px] text-slate-400 leading-relaxed font-medium">No content has been added yet.</p>
            </div>
            
            <div v-else class="space-y-1.5">
                <!-- Container Loop -->
                <div v-for="(cont, ci) in layout" :key="cont.id" class="group/nav">
                    @if(!($postCardMode ?? false))
                    <!-- Container Row -->
                    <div class="flex items-center gap-2 px-4 py-2 hover:bg-blue-50/50 cursor-pointer group/line transition-all"
                         :class="[editingContext.type === 'container' && editingContext.ci === ci ? 'bg-blue-50' : '', navDragOver?.type === 'container' && navDragOver?.ci === ci && navDragSrc?.ci !== ci ? 'border-t-2 border-[#0091ea]' : '']"
                         draggable="true"
                         @dragstart.stop="navDragStart($event, 'container', ci)"
                         @dragover.prevent="navDragOverHandler($event, 'container', ci)"
                         @drop="navDrop($event, 'container', ci)"
                         @dragend="navDragEnd()"
                         @click="setEditingContext('container', ci)">
                        <i class="fa fa-caret-down text-[10px] text-slate-400"></i>
                        <span class="text-[14px] font-bold text-[#0091ea] flex-1">Container</span>
                        <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                            <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                            <i @click.stop="openColumnModal(ci)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Column"></i>
                            <i @click.stop="setEditingContext('container', ci)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                            <i @click.stop="duplicateContainer(ci)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                            <i @click.stop="layout.splice(ci, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                        </div>
                    </div>
                    @endif

                    <!-- Column Loop -->
                    <div v-for="(col, coli) in cont.columns" :key="col.id" class="{{ ($postCardMode ?? false) ? '' : 'nav-branch' }}">
                        <div class="nav-leaf flex items-center gap-2 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                             :class="[editingContext.type === 'column' && editingContext.ci === ci && editingContext.coli === coli ? 'bg-slate-50 border-l-2 border-[#0091ea] -ml-[1px]' : '', navDragOver?.type === 'column' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragSrc?.coli !== coli ? 'border-t-2 border-[#0091ea]' : '']"
                             draggable="true"
                             @dragstart.stop="navDragStart($event, 'column', ci, coli)"
                             @dragover.prevent="navDragOverHandler($event, 'column', ci, coli)"
                             @drop="navDrop($event, 'column', ci, coli)"
                             @dragend="navDragEnd()"
                             @click="setEditingContext('column', ci, coli)">
                            <i class="fa fa-caret-down text-[10px] text-slate-500"></i>
                            <span class="text-[14px] font-semibold text-slate-700 flex-1">Column @{{ formatBasisToFraction(col.basis) }}</span>
                            <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                                <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                                <i @click.stop="openElementModal(ci, coli)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Element"></i>
                                <i @click.stop="setEditingContext('column', ci, coli)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                                <i @click.stop="duplicateColumn(ci, coli)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                                <i @click.stop="cont.columns.splice(coli, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                            </div>
                        </div>

                        <!-- Elements Loop -->
                        <div v-for="(el, eli) in col.elements" :key="el.id" class="nav-branch nav-branch-2">
                            <!-- Standard Element -->
                            <div v-if="el.type !== 'row'"
                                 class="nav-leaf flex items-center gap-3 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                 :class="navDragOver?.type === 'element' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragSrc?.eli !== eli ? 'border-t-2 border-[#0091ea]' : ''"
                                 draggable="true"
                                 @dragstart.stop="navDragStart($event, 'element', ci, coli, eli)"
                                 @dragover.prevent="navDragOverHandler($event, 'element', ci, coli, eli)"
                                 @drop="navDrop($event, 'element', ci, coli, eli)"
                                 @dragend="navDragEnd()"
                                 @click="setEditingContext('element', ci, coli, eli)">
                                <i :class="el.icon" class="text-[11px] text-slate-400 w-4 text-center"></i>
                                <span class="text-[14px] text-slate-600 flex-1 capitalize">@{{ (el.type === 'text_block' || el.type === 'special_text') ? 'Text Block' : el.type.replace(/_/g, ' ') }}</span>
                                <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                                    <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                                    <i @click.stop="openElementModal(ci, coli, 'design', false, eli + 1)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Below"></i>
                                    <i @click.stop="setEditingContext('element', ci, coli, eli)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                                    <i @click.stop="duplicateElement(ci, coli, eli)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                                    <i @click.stop="col.elements.splice(eli, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                                </div>
                            </div>

                            <!-- Nested Row (Nested Columns) -->
                            <div v-else class="space-y-0.5">
                                <div class="nav-leaf flex items-center gap-2 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                     :class="navDragOver?.type === 'element' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragSrc?.eli !== eli ? 'border-t-2 border-[#0091ea]' : ''"
                                     draggable="true"
                                     @dragstart.stop="navDragStart($event, 'element', ci, coli, eli)"
                                     @dragover.prevent="navDragOverHandler($event, 'element', ci, coli, eli)"
                                     @drop="navDrop($event, 'element', ci, coli, eli)"
                                     @dragend="navDragEnd()"
                                     @click="setEditingContext('nested-row', ci, coli, eli)">
                                    <i class="fa fa-caret-down text-[10px] text-slate-400"></i>
                                    <span class="text-[14px] font-bold text-slate-600 flex-1">Nested Row</span>
                                    <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                                        <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                                        <i @click.stop="openElementModal(ci, coli, 'design', false, eli + 1)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Below"></i>
                                        <i @click.stop="openElementModal(ci, coli, 'nested', true, eli)" class="fa fa-plus-square text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Nested Column"></i>
                                        <i @click.stop="setEditingContext('nested-row', ci, coli, eli)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                                        <i @click.stop="duplicateElement(ci, coli, eli)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                                        <i @click.stop="col.elements.splice(eli, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                                    </div>
                                </div>
                                <!-- Nested Column Loop -->
                                <div v-for="(ncol, ncoli) in el.columns" :key="ncol.id" class="nav-branch">
                                    <div class="nav-leaf flex items-center gap-2 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                         :class="navDragOver?.type === 'nested-column' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragOver?.ncoli === ncoli && navDragSrc?.ncoli !== ncoli ? 'border-t-2 border-[#0091ea]' : ''"
                                         draggable="true"
                                         @dragstart.stop="navDragStart($event, 'nested-column', ci, coli, eli, ncoli)"
                                         @dragover.prevent="navDragOverHandler($event, 'nested-column', ci, coli, eli, ncoli)"
                                         @drop="navDrop($event, 'nested-column', ci, coli, eli, ncoli)"
                                         @dragend="navDragEnd()"
                                         @click="setEditingContext('nested-column', ci, coli, eli, ncoli)">
                                        <i class="fa fa-caret-down text-[10px] text-slate-500"></i>
                                        <span class="text-[14px] font-bold text-slate-600 flex-1">Nested Column</span>
                                        <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                                            <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                                            <i @click.stop="openElementModal(ci, coli, 'design', true, eli, ncoli)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Nested Element"></i>
                                            <i @click.stop="setEditingContext('nested-column', ci, coli, eli, ncoli)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                                            <i @click.stop="duplicateNestedColumn(ci, coli, eli, ncoli)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                                            <i @click.stop="el.columns.splice(ncoli, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                                        </div>
                                    </div>
                                    <!-- Nested Elements -->
                                    <div v-for="(nel, neli) in ncol.elements" :key="nel.id" class="nav-branch nav-branch-2">
                                        <div class="nav-leaf flex items-center gap-3 py-1 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                             :class="navDragOver?.type === 'nested-element' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragOver?.ncoli === ncoli && navDragOver?.neli === neli && navDragSrc?.neli !== neli ? 'border-t-2 border-[#0091ea]' : ''"
                                             draggable="true"
                                             @dragstart.stop="navDragStart($event, 'nested-element', ci, coli, eli, ncoli, neli)"
                                             @dragover.prevent="navDragOverHandler($event, 'nested-element', ci, coli, eli, ncoli, neli)"
                                             @drop="navDrop($event, 'nested-element', ci, coli, eli, ncoli, neli)"
                                             @dragend="navDragEnd()"
                                             @click="setEditingContext('element', ci, coli, eli, ncoli, neli)">
                                            <i :class="nel.icon" class="text-[10px] text-slate-400 w-4 text-center"></i>
                                            <span class="text-[14px] text-slate-600 flex-1 capitalize">@{{ (nel.type === 'text_block' || nel.type === 'special_text') ? 'Text Block' : nel.type.replace(/_/g, ' ') }}</span>
                                            <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                                                <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                                                <i @click.stop="openElementModal(ci, coli, 'design', true, eli, ncoli, neli + 1)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Below"></i>
                                                <i @click.stop="setEditingContext('element', ci, coli, eli, ncoli, neli)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                                                <i @click.stop="duplicateNestedElement(ci, coli, eli, ncoli, neli)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                                                <i @click.stop="ncol.elements.splice(neli, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($isMegaMenuBuilder ?? false)
        <!-- Page Options Tab (Mega Menu Builder only) -->
        <div v-show="activeTab==='page_options'" class="h-full overflow-y-auto animate-fade-in">
            <div class="px-4 py-5 space-y-5">

                {{-- Width Type --}}
                <div>
                    <label class="block text-[11px] font-black text-[#1d2327] uppercase tracking-widest mb-2">
                        Mega Menu Max Width
                        <span class="ml-1 text-slate-400 cursor-help font-normal normal-case tracking-normal"
                              title="Controls the inner content width of the mega menu panel">?</span>
                    </label>
                    <div class="relative">
                        <select id="mm-width-type" class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea] appearance-none bg-white cursor-pointer"
                                onchange="mmPoWidthTypeChange(this.value)">
                            <option value="site_width" {{ ($mmPoSettings['width_type'] ?? 'site_width') === 'site_width' ? 'selected' : '' }}>
                                Site Width ({{ $mmPoSettings['site_width'] ?? 1200 }}px)
                            </option>
                            <option value="full_width" {{ ($mmPoSettings['width_type'] ?? '') === 'full_width' ? 'selected' : '' }}>
                                100% Width
                            </option>
                            <option value="custom" {{ ($mmPoSettings['width_type'] ?? '') === 'custom' ? 'selected' : '' }}>
                                Custom Width
                            </option>
                        </select>
                        <i class="fa fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
                    </div>
                </div>

                {{-- Custom Width (visible only when custom selected) --}}
                <div id="mm-custom-width-row" style="display:{{ ($mmPoSettings['width_type'] ?? 'site_width') === 'custom' ? 'block' : 'none' }}">
                    <label class="block text-[11px] font-black text-[#1d2327] uppercase tracking-widest mb-2">
                        Custom Max Width
                        <span class="ml-1 text-slate-400 cursor-help font-normal normal-case tracking-normal"
                              title="Max width in pixels for the mega menu inner content">?</span>
                    </label>
                    <div class="flex items-center gap-2 mb-2">
                        <input type="number" id="mm-custom-width-input"
                               value="{{ $mmPoSettings['custom_width'] ?? 1200 }}"
                               min="200" max="3000" step="10"
                               class="w-[80px] border border-slate-200 rounded px-3 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]"
                               oninput="document.getElementById('mm-custom-width-slider').value=this.value">
                        <span class="text-[12px] text-slate-400">px</span>
                    </div>
                    <input type="range" id="mm-custom-width-slider"
                           min="200" max="3000" step="10"
                           value="{{ $mmPoSettings['custom_width'] ?? 1200 }}"
                           class="w-full accent-[#0091ea]"
                           oninput="document.getElementById('mm-custom-width-input').value=this.value">
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                        <span>200</span><span>3000</span>
                    </div>
                </div>

                {{-- Save Button --}}
                <button onclick="mmPoSave()"
                        class="w-full bg-[#0091ea] hover:bg-[#0078c8] text-white text-[12px] font-black uppercase tracking-widest py-2.5 rounded transition-colors">
                    <i class="fa fa-save mr-1"></i> Save Settings
                </button>
                <div id="mm-po-saved-msg"
                     style="display:none;"
                     class="text-center text-[12px] font-bold text-[#00a32a] py-1">
                    ✓ Settings saved!
                </div>

            </div>
        </div>

        @endif

        <!-- Settings Tab -->
        <div v-show="activeTab==='settings'" class="h-full animate-fade-in flex flex-col">
            <div v-if="editingContext.type === 'container'" :key="'container-' + editingContext.ci" class="h-full">
                @include('falcon-cms::admin.falcon-builder.partials.components.container.edit-panel')
            </div>
            <div v-else-if="editingContext.type === 'nested-row'" :key="'nested-row-' + editingContext.ci + '-' + editingContext.coli + '-' + editingContext.eli" class="h-full">
                @include('falcon-cms::admin.falcon-builder.partials.components.container.edit-panel', ['isNestedRow' => true])
            </div>
            <div v-else-if="editingContext.type === 'column' || editingContext.type === 'nested-column'" :key="'column-' + editingContext.ci + '-' + editingContext.coli + '-' + editingContext.eli + '-' + editingContext.ncoli" class="h-full">
                @include('falcon-cms::admin.falcon-builder.partials.components.column.edit-panel')
            </div>
            <div v-else-if="editingContext.type === 'element'" :key="'element-' + editingContext.ci + '-' + editingContext.coli + '-' + editingContext.eli + '-' + editingContext.neli" class="h-full">
                <!-- Dynamic Element Settings Panel -->
                <div class="flex flex-col h-full bg-white">
                    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[#2271b1] rounded flex items-center justify-center text-white shadow-sm">
                                <i :class="editingElement?.icon || 'fa fa-cube'" class="text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-800">@{{ editingElement?.name || ( (editingElement?.type === 'text_block' || editingElement?.type === 'special_text') ? 'Text Block' : (editingElement?.type || 'Element').replace(/_/g, ' ') ) }} Settings</h3>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Edit Content & Design</p>
                            </div>
                        </div>
                        <button @click="activeTab='navigator'" class="text-slate-400 hover:text-[#0091ea] transition-colors">
                            <i class="fa fa-times text-sm"></i>
                        </button>
                    </div>

                    <div class="flex border-b border-black/20 bg-[#2271b1] overflow-hidden divide-x divide-black/20">
                        <button @click="editingContext.tab = 'content'"
                                :class="(editingContext.tab || 'content') === 'content' ? 'bg-[#0091EA] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'"
                                class="builder-tab-btn py-3 text-[10px] font-black uppercase tracking-widest">
                            <span v-if="(editingContext.tab || 'content') === 'content'">General</span>
                            <i v-else class="fa fa-cog text-xs"></i>
                        </button>

                        <button @click="editingContext.tab = 'design'"
                                :class="editingContext.tab === 'design' ? 'bg-[#0091EA] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'"
                                class="builder-tab-btn py-3 text-[10px] font-black uppercase tracking-widest transition-all">
                            <template v-if="editingContext.tab === 'design'">
                                <span>@{{ editingElement?.type === 'menu' ? 'Main Menu' : 'Design' }}</span>
                            </template>
                            <template v-else>
                                <i :class="editingElement?.type === 'menu' ? 'fa fa-bars' : 'fa fa-pen'" class="text-xs"></i>
                            </template>
                        </button>

                        <button v-if="editingElement?.type === 'menu'"
                                @click="editingContext.tab = 'submenu'"
                                :class="editingContext.tab === 'submenu' ? 'bg-[#0091EA] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'"
                                class="builder-tab-btn py-3 text-[10px] font-black uppercase tracking-widest transition-all">
                            <span v-if="editingContext.tab === 'submenu'">Sub Menu</span>
                            <i v-else class="fa fa-indent text-xs"></i>
                        </button>

                        <button v-if="editingElement?.type === 'menu'"
                                @click="editingContext.tab = 'mobile'"
                                :class="editingContext.tab === 'mobile' ? 'bg-[#0091EA] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'"
                                class="builder-tab-btn py-3 text-[10px] font-black uppercase tracking-widest transition-all">
                            <span v-if="editingContext.tab === 'mobile'">Mobile</span>
                            <i v-else class="fa fa-mobile-screen text-xs"></i>
                        </button>

                        <button @click="editingContext.tab = 'extras'"
                                :class="editingContext.tab === 'extras' ? 'bg-[#0091EA] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'"
                                class="builder-tab-btn py-3 text-[10px] font-black uppercase tracking-widest transition-all">
                            <span v-if="editingContext.tab === 'extras'">Extras</span>
                            <i v-else class="fa fa-copy text-xs"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar bg-white">
                        <!-- ══ GENERAL TAB (Content) ══ -->
                        <div v-if="(editingContext.tab || 'content') === 'content'" class="p-5 space-y-8">
                            
                            <div v-if="editingElement?.type === 'text_block' || editingElement?.type === 'special_text'" class="space-y-8">
                                <!-- Content Field (Rich Editor) -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Content</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'text', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-show="editingElement.settings.dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'text', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-show="!editingElement.settings.dynamic_source" class="builder-rich-editor-wrapper border border-slate-200 rounded overflow-hidden focus-within:border-[#0091ea] transition-all">
                                        <textarea :id="'rich-editor-' + editingElement.id + '-content'"
                                                  class="builder-rich-editor w-full p-3 text-[13px] min-h-[200px] focus:outline-none"
                                                  v-model="editingElement.settings.content"></textarea>
                                    </div>
                                </div>

                                <!-- Alignment -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Alignment</label>
                                        <div class="flex gap-1 items-center">
                                            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, '')" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                                                <i class="fa fa-undo text-[10px]"></i>
                                            </button>
                                            <div class="relative inline-block">
                                                <button @click="activeResponsiveMenu = activeResponsiveMenu === 'tbAlign' ? null : 'tbAlign'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                                                    <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                                                    <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                                                </button>
                                                <div v-show="activeResponsiveMenu === 'tbAlign'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass" 
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId" 
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="editingElement?.type === 'title'" class="space-y-8">
                                <!-- Title Field -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Title</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'text', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'text', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <textarea v-else v-model="editingElement.settings.title"
                                              rows="4"
                                              placeholder="Enter your title here..."
                                              class="w-full border border-slate-200 rounded p-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea] focus:ring-1 focus:ring-[#0091ea]/10 transition-all resize-none"></textarea>
                                </div>

                                <!-- Title Link Toggle -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Title Link</label>
                                    </div>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                                        <button @click="editingElement.settings.useLink = true"
                                                :class="editingElement.settings.useLink ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">On</button>
                                        <button @click="editingElement.settings.useLink = false"
                                                :class="!editingElement.settings.useLink ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Off</button>
                                    </div>
                                </div>

                                <!-- Link URL Field -->
                                <div v-if="editingElement.settings.useLink">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link URL</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'link_dynamic_source', 'link', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.link_dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.link_dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'link_dynamic_source', 'link', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.link_dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.link_dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.link_dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-else>
                                        <input type="text" v-model="editingElement.settings.linkUrl"
                                               placeholder="Select Link"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.useLink && (editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source)">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link Target</label>
                                    </div>
                                    <select v-model="editingElement.settings.linkTarget"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="_self">Same Window</option>
                                        <option value="_blank">New Window</option>
                                    </select>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass" 
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId" 
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                            </div>

                            <!-- Other Elements Placeholder -->
                            <div v-else-if="editingElement?.type === 'heading' || editingElement?.type === 'text'">
                                 <!-- We can add these later to match the same style -->
                                 <component :is="editingElement?.settingsComponent || 'div'" :settings="editingElement?.settings"></component>
                            </div>

                            <!-- ══ BUTTON CONTENT ══ -->
                            <div v-else-if="editingElement?.type === 'button'" class="space-y-8">
                                <!-- Button Text -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Button Text</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'text', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'text', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <input v-else type="text" v-model="editingElement.settings.text"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Link URL -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link URL</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'link_dynamic_source', 'link', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.link_dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.link_dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'link_dynamic_source', 'link', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.link_dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.link_dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.link_dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-else>
                                        <input type="text" v-model="editingElement.settings.linkUrl"
                                               placeholder="https://"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link Target</label>
                                    </div>
                                    <select v-model="editingElement.settings.linkTarget"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="_self">Same Window</option>
                                        <option value="_blank">New Window</option>
                                    </select>
                                </div>

                                <!-- Alignment -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Alignment</label>
                                        <div class="flex gap-1 items-center">
                                            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, '')" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                                                <i class="fa fa-undo text-[10px]"></i>
                                            </button>
                                            <div class="relative inline-block">
                                                <button @click="activeResponsiveMenu = activeResponsiveMenu === 'btnAlign' ? null : 'btnAlign'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                                                    <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                                                    <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                                                </button>
                                                <div v-show="activeResponsiveMenu === 'btnAlign'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-left text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'center')"
                                                :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) === 'center' || !getResponsiveVal(editingElement.settings, 'textAlign', device)) ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-center text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'right')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center transition-all">
                                            <i class="fa fa-align-right text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                            </div>

                            <!-- ══ IMAGE CONTENT ══ -->
                            <div v-else-if="editingElement?.type === 'image'" class="space-y-8">
                                <!-- Image -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Image</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'image', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <!-- Dynamic source active state -->
                                    <div v-if="editingElement.settings.dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'dynamic_source', 'image', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <!-- Normal image picker -->
                                    <div v-else>
                                        <div v-if="!editingElement.settings.url"
                                             @click="openMediaModal('url')"
                                             class="w-full aspect-[16/10] border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-[#0091ea] hover:bg-blue-50/30 transition-all group">
                                            <div class="w-10 h-10 bg-[#2271b1] rounded-full flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform">
                                                <i class="fa fa-plus"></i>
                                            </div>
                                        </div>
                                        <div v-else class="space-y-3">
                                            <div class="relative group aspect-[16/10] bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
                                                <img :src="editingElement.settings.url" class="w-full h-full object-cover">
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <button @click="openMediaModal('url')" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-[#0091ea] hover:bg-[#1a5a96] hover:text-white transition-all shadow-sm">
                                                        <i class="fa fa-edit text-xs"></i>
                                                    </button>
                                                    <button @click="editingElement.settings.url = ''" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-red-500 hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                                        <i class="fa fa-trash text-xs"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="editingElement.settings.url = ''" class="flex-1 h-9 flex items-center justify-center border border-slate-200 rounded text-[11px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Remove</button>
                                                <button @click="openMediaModal('url')" class="flex-1 h-9 flex items-center justify-center bg-[#2271b1] text-white rounded text-[11px] font-bold hover:bg-[#1a5a96] transition-colors">Change</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aspect Ratio -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Aspect Ratio</label>
                                    <div class="grid grid-cols-5 gap-1">
                                        <button v-for="ar in [{l:'Auto',v:'none'},{l:'16:9',v:'16/9'},{l:'4:3',v:'4/3'},{l:'1:1',v:'1/1'},{l:'3:4',v:'3/4'}]"
                                                :key="ar.v"
                                                @click="editingElement.settings.aspectRatio = ar.v"
                                                :class="(editingElement.settings.aspectRatio || 'none') === ar.v ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                                class="py-1.5 rounded text-[10px] font-bold transition-colors">
                                            @{{ ar.l }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Focus Point -->
                                <div v-if="editingElement.settings.aspectRatio && editingElement.settings.aspectRatio !== 'none'">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2 mt-1">Image Focus Point</label>
                                    <div class="relative rounded overflow-hidden bg-slate-200 select-none"
                                         :class="editingElement.settings._fpDrag ? 'cursor-grabbing' : 'cursor-crosshair'"
                                         style="aspect-ratio:16/9;"
                                         @mousedown.prevent="(function($ev){ editingElement.settings._fpDrag=true; var r=$ev.currentTarget.getBoundingClientRect(); editingElement.settings.focusX=Math.min(100,Math.max(0,Math.round(($ev.clientX-r.left)/r.width*100))); editingElement.settings.focusY=Math.min(100,Math.max(0,Math.round(($ev.clientY-r.top)/r.height*100))); })($event)"
                                         @mousemove.prevent="(function($ev){ if(!editingElement.settings._fpDrag)return; var r=$ev.currentTarget.getBoundingClientRect(); editingElement.settings.focusX=Math.min(100,Math.max(0,Math.round(($ev.clientX-r.left)/r.width*100))); editingElement.settings.focusY=Math.min(100,Math.max(0,Math.round(($ev.clientY-r.top)/r.height*100))); })($event)"
                                         @mouseup="editingElement.settings._fpDrag=false"
                                         @mouseleave="editingElement.settings._fpDrag=false">
                                        <img v-if="editingElement.settings.url"
                                             :src="editingElement.settings.url"
                                             class="w-full h-full pointer-events-none"
                                             :style="{objectFit:'cover',objectPosition:(editingElement.settings.focusX||50)+'% '+(editingElement.settings.focusY||50)+'%'}">
                                        <div v-else class="absolute inset-0 flex items-center justify-center text-slate-400 text-[11px]">No image selected</div>
                                        <div class="absolute pointer-events-none w-5 h-5 rounded-full border-2 border-white shadow-md ring-1 ring-[#0091ea] -translate-x-1/2 -translate-y-1/2"
                                             :style="{left:(editingElement.settings.focusX||50)+'%',top:(editingElement.settings.focusY||50)+'%',background:'rgba(0,145,234,0.5)'}">
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">Hold and drag to set the crop focal point</p>
                                </div>

                                <!-- Alt Text -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-3">Alt Text</label>
                                    <input type="text" v-model="editingElement.settings.alt"
                                           placeholder="Image description..."
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Link URL -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link URL</label>
                                        <button @click.stop="openDynSrcMenu(editingElement.settings, 'link_dynamic_source', 'link', $event)"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.link_dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.link_dynamic_source"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
                                         @click.stop="openDynSrcMenu(editingElement.settings, 'link_dynamic_source', 'link', $event)">
                                        <div class="flex items-center gap-2">
                                            <i :class="['fa', getDynSrcDef(editingElement.settings.link_dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">@{{ getDynSrcDef(editingElement.settings.link_dynamic_source).label }}</span>
                                        </div>
                                        <button @click.stop="editingElement.settings.link_dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-else>
                                        <input type="text" v-model="editingElement.settings.linkUrl"
                                               placeholder="https://"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link Target</label>
                                    </div>
                                    <select v-model="editingElement.settings.linkTarget"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="_self">Same Window</option>
                                        <option value="_blank">New Window</option>
                                    </select>
                                </div>

                                <!-- Alignment -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Alignment</label>
                                        <div class="flex gap-1 items-center">
                                            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, '')" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                                                <i class="fa fa-undo text-[10px]"></i>
                                            </button>
                                            <div class="relative inline-block">
                                                <button @click="activeResponsiveMenu = activeResponsiveMenu === 'imgAlign' ? null : 'imgAlign'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                                                    <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                                                    <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                                                </button>
                                                <div v-show="activeResponsiveMenu === 'imgAlign'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-left text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'center')"
                                                :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) === 'center' || !getResponsiveVal(editingElement.settings, 'textAlign', device)) ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-center text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'right')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center transition-all">
                                            <i class="fa fa-align-right text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                            </div>

                            <!-- ══ CARD CONTENT ══ -->
                            <div v-else-if="editingElement?.type === 'card'" class="space-y-6">

                                <!-- Card -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Card</label>
                                    <select v-model="editingElement.settings.post_card_id"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="">— No card (raw posts) —</option>
                                        <option v-for="card in postCardsList" :key="card.id" :value="card.id">@{{ card.name }}</option>
                                    </select>
                                    <p v-if="!postCardsList.length" class="mt-1.5 text-[11px] text-amber-500">
                                        No post cards saved yet. <a href="{{ route('admin.falcon-builder.library') }}?tab=post_cards" target="_blank" class="underline">Create one →</a>
                                    </p>
                                </div>

                                <!-- Content Source -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Content Source</label>
                                    <select v-model="editingElement.settings.content_source"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="posts">Posts</option>
                                        <option value="terms">Terms</option>
                                        <option value="related">Related</option>
                                        <option value="upsells">Upsells</option>
                                        <option value="cross_sells">Cross-sells</option>
                                    </select>
                                </div>

                                <!-- Taxonomy (visible when Content Source = Terms or Related) -->
                                <div v-if="['terms','related'].includes(editingElement.settings.content_source)">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Taxonomy</label>
                                    <select v-model="editingElement.settings.taxonomy_slug"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="">— Select Taxonomy —</option>
                                        <option v-for="tax in cardTaxonomiesByPostType" :key="tax.slug" :value="tax.slug">@{{ tax.name }}</option>
                                    </select>
                                    <p v-if="!cardTaxonomiesByPostType.length" class="mt-1.5 text-[11px] text-amber-500">
                                        No taxonomies configured for this post type.
                                    </p>
                                    <!-- Include/Exclude dropdowns only for Terms source -->
                                    <template v-if="editingElement.settings.content_source === 'terms' && editingElement.settings.taxonomy_slug && lazyTaxonomyTerms[editingElement.settings.taxonomy_slug]?.length">
                                        <div class="mt-3">
                                            <label class="text-[12px] font-bold text-[#333] flex items-center gap-1.5 mb-1.5">
                                                Include @{{ lazyTaxonomies.find(t => t.slug === editingElement.settings.taxonomy_slug)?.name || 'Terms' }}
                                                <svg v-if="cardPreviewCache[editingElement.id]?.loading" class="animate-spin h-3 w-3 text-[#0091ea]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </label>
                                            <select :key="'tsinc-' + editingElement.settings.taxonomy_slug"
                                                    v-tomselect="{ value: editingElement.settings.taxonomy_include, onChange: v => { editingElement.settings.taxonomy_include = v; fetchCardPreview(editingElement); }, placeholder: 'Select to include…' }"
                                                    multiple class="w-full">
                                                <option v-for="term in lazyTaxonomyTerms[editingElement.settings.taxonomy_slug]" :key="term.id" :value="term.slug">@{{ term.name }}</option>
                                            </select>
                                        </div>
                                        <div class="mt-3">
                                            <label class="text-[12px] font-bold text-[#333] block mb-1.5">
                                                Exclude @{{ lazyTaxonomies.find(t => t.slug === editingElement.settings.taxonomy_slug)?.name || 'Terms' }}
                                            </label>
                                            <select :key="'tsexc-' + editingElement.settings.taxonomy_slug"
                                                    v-tomselect="{ value: editingElement.settings.taxonomy_exclude, onChange: v => { editingElement.settings.taxonomy_exclude = v; fetchCardPreview(editingElement); }, placeholder: 'Select to exclude…' }"
                                                    multiple class="w-full">
                                                <option v-for="term in lazyTaxonomyTerms[editingElement.settings.taxonomy_slug]" :key="term.id" :value="term.slug">@{{ term.name }}</option>
                                            </select>
                                        </div>
                                    </template>
                                </div>

                                <!-- Post Type (dynamic: built-in + active custom CPTs) -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Post Type</label>
                                    <select v-model="editingElement.settings.post_type"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="post">Posts</option>
                                        <option value="page">Pages</option>
                                        <option value="product">Products</option>
                                        <option v-for="cpt in lazyCptList" :key="cpt.slug" :value="cpt.slug">@{{ cpt.name }}</option>
                                    </select>
                                </div>

                                <!-- Posts By -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Posts By</label>
                                    <select v-model="editingElement.settings.posts_by"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="all">All</option>
                                        <option value="category">Categories</option>
                                        <option value="tag">Tags</option>
                                        <option value="custom_field">Custom Field</option>
                                        <option value="post_id">Post ID</option>
                                    </select>
                                    <!-- Post ID: text input -->
                                    <input v-if="editingElement.settings.posts_by === 'post_id'"
                                           type="text" v-model="editingElement.settings.posts_by_value"
                                           placeholder="Post IDs (comma separated)"
                                           class="w-full mt-2 border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    <!-- Custom Field: key + value inputs -->
                                    <template v-if="editingElement.settings.posts_by === 'custom_field'">
                                        <input type="text" v-model="editingElement.settings.posts_by_cf_key"
                                               placeholder="Meta key"
                                               class="w-full mt-2 border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <input type="text" v-model="editingElement.settings.posts_by_cf_value"
                                               placeholder="Meta value"
                                               class="w-full mt-2 border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </template>
                                </div>

                                <!-- Post Status -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Post Status</label>
                                    <div class="space-y-1.5">
                                        <label v-for="status in [{v:'publish',l:'Published'},{v:'draft',l:'Draft'},{v:'pending',l:'Pending'},{v:'private',l:'Private'}]"
                                               :key="status.v"
                                               class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" :value="status.v"
                                                   v-model="editingElement.settings.post_status"
                                                   class="accent-[#0091ea]">
                                            <span class="text-[13px] text-slate-600">@{{ status.l }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Out Of Stock (product only) -->
                                <div v-if="editingElement.settings.post_type === 'product'">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Out Of Stock Products</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                                        <button @click="editingElement.settings.hide_out_of_stock = false"
                                                :class="!editingElement.settings.hide_out_of_stock ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-5 py-1.5 text-[11px] font-black uppercase rounded transition-all">Show</button>
                                        <button @click="editingElement.settings.hide_out_of_stock = true"
                                                :class="editingElement.settings.hide_out_of_stock ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-5 py-1.5 text-[11px] font-black uppercase rounded transition-all">Hide</button>
                                    </div>
                                </div>

                                <!-- Number of Posts (slider) -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Number of Posts</label>
                                        <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.posts_count ?? 6 }}</span>
                                    </div>
                                    <div class="flex gap-3 items-center">
                                        <input type="range" v-model.number="editingElement.settings.posts_count"
                                               min="1" max="48" class="flex-1 accent-[#0091ea]">
                                        <input type="number" v-model.number="editingElement.settings.posts_count"
                                               min="1" max="48"
                                               class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                                <!-- Posts Offset (slider) -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Posts Offset</label>
                                        <span class="text-[12px] text-[#0091ea] font-black">@{{ editingElement.settings.posts_offset ?? 0 }}</span>
                                    </div>
                                    <div class="flex gap-3 items-center">
                                        <input type="range" v-model.number="editingElement.settings.posts_offset"
                                               min="0" max="100" class="flex-1 accent-[#0091ea]">
                                        <input type="number" v-model.number="editingElement.settings.posts_offset"
                                               min="0" max="100"
                                               class="w-14 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                                <!-- Order By -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Order By</label>
                                    <select v-model="editingElement.settings.order_by"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="created_at">Date</option>
                                        <option value="title">Title</option>
                                        <option value="views">Views</option>
                                        <option value="updated_at">Modified</option>
                                        <option value="rand">Random</option>
                                        <option value="menu_order">Menu Order</option>
                                        <option value="price">Price</option>
                                        <option value="popularity">Popularity (sales)</option>
                                        <option value="rating">Average Rating</option>
                                        <option value="recently_purchased">Recently Purchased</option>
                                    </select>
                                </div>

                                <!-- Order (Asc / Desc buttons) -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Order</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.order = 'asc'"
                                                :class="editingElement.settings.order === 'asc' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">ASC</button>
                                        <button @click="editingElement.settings.order = 'desc'"
                                                :class="(editingElement.settings.order === 'desc' || !editingElement.settings.order) ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">DESC</button>
                                    </div>
                                </div>

                                <!-- Pagination Type -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Pagination Type</label>
                                    <select v-model="editingElement.settings.pagination_type"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="none">None</option>
                                        <option value="numbered">Numbered</option>
                                        <option value="load_more">Load More</option>
                                        <option value="infinite">Infinite Scroll</option>
                                    </select>
                                </div>

                                <!-- Nothing Found Message -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Nothing Found Message</label>
                                    <input type="text" v-model="editingElement.settings.nothing_found_message"
                                           placeholder="No posts found."
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Element Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ SPACER ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'spacer'" class="space-y-8">

                                <!-- Style -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Style</label>
                                    <select v-model="editingElement.settings.style"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="default">Default</option>
                                        <option value="none">None</option>
                                        <option value="single_border_solid">Single Border Solid</option>
                                        <option value="double_border_solid">Double Border Solid</option>
                                        <option value="single_border_dashed">Single Border Dashed</option>
                                        <option value="double_border_dashed">Double Border Dashed</option>
                                        <option value="single_border_dotted">Single Border Dotted</option>
                                        <option value="double_border_dotted">Double Border Dotted</option>
                                    </select>
                                </div>

                                <!-- Element Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ HTML BLOCK ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'html'" class="space-y-6">

                                <!-- Code Editor -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333] uppercase">HTML Content</label>
                                        <span class="text-[9px] text-slate-400 uppercase font-semibold bg-slate-100 px-2 py-0.5 rounded">HTML · CSS · JS</span>
                                    </div>
                                    <div id="lazy-html-editor"
                                         class="rounded border border-slate-200"
                                         style="min-height:220px; font-size:12px;"></div>
                                </div>

                                <!-- Element Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ ICON BOX ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'icon_box'" class="space-y-6">

                                <!-- Icon Picker -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Icon</label>
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
                                        <div class="h-48 overflow-y-auto p-2 bg-white custom-scrollbar">
                                            <div class="grid grid-cols-5 gap-1.5">
                                                <button v-for="icon in filteredIcons" :key="icon"
                                                        @click="selectIcon(editingElement.settings, icon)"
                                                        :class="editingElement.settings.icon === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                                                        class="aspect-square flex items-center justify-center rounded border transition-all p-1"
                                                        :title="icon">
                                                    <i :class="[icon, 'text-base']"></i>
                                                </button>
                                            </div>
                                            <div v-if="filteredIcons.length === 0" class="py-10 text-center text-[10px] text-slate-400">No icons found</div>
                                        </div>
                                        <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center"
                                                     :style="{ color: editingElement.settings.iconColor || '#2271b1' }">
                                                    <i :class="editingElement.settings.icon || 'fas fa-star'"></i>
                                                </div>
                                                <span class="text-[10px] text-slate-500 font-medium truncate max-w-[120px]">@{{ editingElement.settings.icon || 'No icon selected' }}</span>
                                            </div>
                                            <button v-if="editingElement.settings.icon" @click="editingElement.settings.icon = ''"
                                                    class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Layout -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Layout</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.layout = 'top'"
                                                :class="(!editingElement.settings.layout || editingElement.settings.layout === 'top') ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                class="flex-1 py-2 rounded transition-all flex items-center justify-center"
                                                title="Top (Icon Above Text)">
                                            <i class="fas fa-arrow-up text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.layout = 'left'"
                                                :class="editingElement.settings.layout === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                class="flex-1 py-2 rounded transition-all flex items-center justify-center"
                                                title="Left (Icon Left of Text)">
                                            <i class="fas fa-arrow-left text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.layout = 'right'"
                                                :class="editingElement.settings.layout === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                class="flex-1 py-2 rounded transition-all flex items-center justify-center"
                                                title="Right (Icon Right of Text)">
                                            <i class="fas fa-arrow-right text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Alignment (only for top layout) -->
                                <div v-if="!editingElement.settings.layout || editingElement.settings.layout === 'top'">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Alignment</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.alignment = 'left'"
                                                :class="editingElement.settings.alignment === 'left' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                class="flex-1 py-2 rounded transition-all flex items-center justify-center"
                                                title="Align Left">
                                            <i class="fas fa-align-left text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.alignment = 'center'"
                                                :class="(!editingElement.settings.alignment || editingElement.settings.alignment === 'center') ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                class="flex-1 py-2 rounded transition-all flex items-center justify-center"
                                                title="Align Center">
                                            <i class="fas fa-align-center text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.alignment = 'right'"
                                                :class="editingElement.settings.alignment === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                class="flex-1 py-2 rounded transition-all flex items-center justify-center"
                                                title="Align Right">
                                            <i class="fas fa-align-right text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Title -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Title</label>
                                    </div>
                                    <input type="text" v-model="editingElement.settings.title"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Description -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Description</label>
                                    </div>
                                    <textarea v-model="editingElement.settings.description" rows="3"
                                              class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea] resize-y"></textarea>
                                </div>

                                <!-- Link URL -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Link URL</label>
                                    </div>
                                    <input type="text" v-model="editingElement.settings.linkUrl"
                                           placeholder="https://"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.linkUrl">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Link Target</label>
                                    <select v-model="editingElement.settings.linkTarget"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <option value="_self">Same Window</option>
                                        <option value="_blank">New Window</option>
                                    </select>
                                </div>

                                <!-- Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ ACCORDION ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'accordion'" class="space-y-6">

                                <!-- Items List -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Accordion Items</label>
                                        <button @click="editingElement.settings.items.push({ id: Date.now() + '_' + editingElement.settings.items.length, title: 'New Item', content: '<p>Add your content here.</p>' })"
                                                class="text-[10px] font-bold text-[#0091ea] hover:underline flex items-center gap-1">
                                            <i class="fa fa-plus text-[9px]"></i> Add Item
                                        </button>
                                    </div>
                                    <div class="space-y-1">
                                        <div v-for="(item, idx) in editingElement.settings.items" :key="item.id || idx"
                                             class="border border-slate-200 rounded overflow-hidden"
                                             draggable="true"
                                             @dragstart="$event.dataTransfer.setData('text/plain', String(idx)); $event.currentTarget.style.opacity='0.4'"
                                             @dragend="$event.currentTarget.style.opacity=''"
                                             @dragover.prevent
                                             @drop.prevent="(function(to){ $event.currentTarget.style.opacity=''; const from=parseInt($event.dataTransfer.getData('text/plain')); if(isNaN(from)||from===to)return; const a=editingElement.settings.items; const m=a.splice(from,1)[0]; a.splice(to,0,m); activeAccordionItem=null; })(idx)">
                                            <!-- Item Row -->
                                            <div class="flex items-center gap-2 px-2 py-2 bg-slate-50 cursor-pointer"
                                                 @click="activeAccordionItem = (activeAccordionItem === idx ? null : idx)">
                                                <i class="fa fa-grip-vertical text-slate-300 text-[11px] cursor-grab"></i>
                                                <span class="flex-1 text-[12px] text-slate-600 truncate">@{{ item.title || 'Item ' + (idx + 1) }}</span>
                                                <button @click.stop="editingElement.settings.items.splice(idx, 1); if(activeAccordionItem === idx) activeAccordionItem = null;"
                                                        class="text-slate-300 hover:text-red-400 transition-colors">
                                                    <i class="fa fa-trash-alt text-[10px]"></i>
                                                </button>
                                                <i :class="activeAccordionItem === idx ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-slate-300 text-[10px]"></i>
                                            </div>
                                            <!-- Expanded Edit Area -->
                                            <div v-if="activeAccordionItem === idx" class="p-3 space-y-3 border-t border-slate-100">
                                                <div>
                                                    <div class="flex justify-between items-center mb-1">
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase">Title</label>
                                                    </div>
                                                    <input type="text" v-model="item.title"
                                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                </div>
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Content (HTML)</label>
                                                    <textarea v-model="item.content" rows="4"
                                                              class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea] resize-y font-mono"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Default Open -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Default Open Item</label>
                                    <p class="text-[10px] text-slate-400 mb-2">Index of item to open by default. Use -1 to start all closed.</p>
                                    <input type="number" min="-1" v-model.number="editingElement.settings.defaultOpen"
                                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Icon Type -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Icon Type</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.iconType = 'plus'"
                                                :class="(!editingElement.settings.iconType || editingElement.settings.iconType === 'plus') ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">
                                            <i class="fas fa-plus mr-1"></i> Plus
                                        </button>
                                        <button @click="editingElement.settings.iconType = 'chevron'"
                                                :class="editingElement.settings.iconType === 'chevron' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">
                                            <i class="fas fa-chevron-down mr-1"></i> Chevron
                                        </button>
                                    </div>
                                </div>

                                <!-- Icon Position -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Icon Position</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.iconPosition = 'left'"
                                                :class="editingElement.settings.iconPosition === 'left' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Left</button>
                                        <button @click="editingElement.settings.iconPosition = 'right'"
                                                :class="(!editingElement.settings.iconPosition || editingElement.settings.iconPosition === 'right') ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Right</button>
                                    </div>
                                </div>

                                <!-- Allow Multiple -->
                                <div class="flex items-center justify-between">
                                    <label class="text-[12px] font-bold text-[#333]">Allow Multiple Open</label>
                                    <button @click="editingElement.settings.allowMultiple = !editingElement.settings.allowMultiple"
                                            :class="editingElement.settings.allowMultiple ? 'bg-[#2271b1]' : 'bg-slate-200'"
                                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200">
                                        <span :class="editingElement.settings.allowMultiple ? 'translate-x-4' : 'translate-x-1'"
                                              class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"></span>
                                    </button>
                                </div>

                                <!-- Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ ICON LIST ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'icon_list'" class="space-y-6">

                                <!-- Items -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">List Items</label>
                                        <button @click="editingElement.settings.items.push({ id: Date.now()+'_'+editingElement.settings.items.length, icon: editingElement.settings.defaultIcon||'fa fa-check', iconColor:'', text:'List item', link:'', linkTarget:'_self' })"
                                                class="text-[10px] font-bold text-[#0091ea] hover:underline flex items-center gap-1">
                                            <i class="fa fa-plus text-[9px]"></i> Add Item
                                        </button>
                                    </div>
                                    <div class="space-y-1">
                                        <div v-for="(item, idx) in editingElement.settings.items" :key="item.id || idx"
                                             class="border border-slate-200 rounded overflow-hidden"
                                             draggable="true"
                                             @dragstart="$event.dataTransfer.setData('text/plain', String(idx)); $event.currentTarget.style.opacity='0.4'"
                                             @dragend="$event.currentTarget.style.opacity=''"
                                             @dragover.prevent
                                             @drop.prevent="(function(to){ $event.currentTarget.style.opacity=''; const from=parseInt($event.dataTransfer.getData('text/plain')); if(isNaN(from)||from===to)return; const a=editingElement.settings.items; const m=a.splice(from,1)[0]; a.splice(to,0,m); activeIconListItem=null; })(idx)">
                                            <!-- Row -->
                                            <div class="flex items-center gap-2 px-2 py-2 bg-slate-50 cursor-pointer"
                                                 @click="activeIconListItem = (activeIconListItem === idx ? null : idx)">
                                                <i class="fa fa-grip-vertical text-slate-300 text-[11px] cursor-grab"></i>
                                                <i :class="item.icon || editingElement.settings.defaultIcon || 'fa fa-check'"
                                                   :style="{color: item.iconColor || editingElement.settings.iconColor || '#2271b1', fontSize:'12px'}"></i>
                                                <span class="flex-1 text-[12px] text-slate-600 truncate">@{{ item.text || 'Item ' + (idx+1) }}</span>
                                                <button @click.stop="editingElement.settings.items.splice(idx,1); if(activeIconListItem===idx) activeIconListItem=null;"
                                                        class="text-slate-300 hover:text-red-400 transition-colors">
                                                    <i class="fa fa-trash-alt text-[10px]"></i>
                                                </button>
                                                <i :class="activeIconListItem===idx ? 'fas fa-chevron-up':'fas fa-chevron-down'" class="text-slate-300 text-[10px]"></i>
                                            </div>
                                            <!-- Expanded -->
                                            <div v-if="activeIconListItem === idx" class="p-3 space-y-3 border-t border-slate-100">
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-2">Icon</label>
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
                                                        <div class="h-36 overflow-y-auto p-2 bg-white custom-scrollbar">
                                                            <div class="grid grid-cols-5 gap-1.5">
                                                                <button v-for="icon in filteredIcons" :key="icon"
                                                                        @click="selectIcon(item, icon)"
                                                                        :class="item.icon === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                                                                        class="aspect-square flex items-center justify-center rounded border transition-all p-1"
                                                                        :title="icon">
                                                                    <i :class="[icon, 'text-base']"></i>
                                                                </button>
                                                            </div>
                                                            <div v-if="filteredIcons.length === 0" class="py-6 text-center text-[10px] text-slate-400">No icons found</div>
                                                        </div>
                                                        <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center"
                                                                     :style="{ color: item.iconColor || editingElement.settings.iconColor || '#2271b1' }">
                                                                    <i :class="item.icon || editingElement.settings.defaultIcon || 'fas fa-check'"></i>
                                                                </div>
                                                                <span class="text-[10px] text-slate-500 font-medium truncate max-w-[120px]">@{{ item.icon || 'Using default' }}</span>
                                                            </div>
                                                            <button v-if="item.icon" @click="item.icon = ''"
                                                                    class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="flex justify-between items-center mb-1.5">
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase">Icon Color (override)</label>
                                                        <button @click="clearColorField(item, 'iconColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                                    </div>
                                                    <div class="flex gap-2 items-center">
                                                        <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                             @click="openColorPicker($event, item, 'iconColor')">
                                                            <div :style="{ backgroundColor: item.iconColor || editingElement.settings.iconColor || '#2271b1' }" class="w-full h-full rounded-full"></div>
                                                        </div>
                                                        <input type="text" v-model="item.iconColor" placeholder="default" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="flex justify-between items-center mb-1">
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase">Text</label>
                                                    </div>
                                                    <input type="text" v-model="item.text"
                                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                </div>
                                                <div>
                                                    <div class="flex justify-between items-center mb-1">
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase">Link (optional)</label>
                                                    </div>
                                                    <input type="text" v-model="item.link" placeholder="https://..."
                                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                </div>
                                                <div v-if="item.link">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Link Target</label>
                                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-0.5">
                                                        <button @click="item.linkTarget='_self'"
                                                                :class="(!item.linkTarget||item.linkTarget==='_self') ? 'bg-[#2271b1] text-white shadow-sm':'text-slate-500 hover:bg-slate-100'"
                                                                class="flex-1 py-1 text-[10px] font-bold rounded transition-all">Same Tab</button>
                                                        <button @click="item.linkTarget='_blank'"
                                                                :class="item.linkTarget==='_blank' ? 'bg-[#2271b1] text-white shadow-sm':'text-slate-500 hover:bg-slate-100'"
                                                                class="flex-1 py-1 text-[10px] font-bold rounded transition-all">New Tab</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ TABS ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'tabs'" class="space-y-6">

                                <!-- Items List -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Tabs</label>
                                        <button @click="editingElement.settings.items.push({ id: Date.now() + '_' + editingElement.settings.items.length, label: 'New Tab', content: '<p>Tab content goes here.</p>' })"
                                                class="text-[10px] font-bold text-[#0091ea] hover:underline flex items-center gap-1">
                                            <i class="fa fa-plus text-[9px]"></i> Add Tab
                                        </button>
                                    </div>
                                    <div class="space-y-1">
                                        <div v-for="(item, idx) in editingElement.settings.items" :key="item.id || idx"
                                             class="border border-slate-200 rounded overflow-hidden"
                                             draggable="true"
                                             @dragstart="$event.dataTransfer.setData('text/plain', String(idx)); $event.currentTarget.style.opacity='0.4'"
                                             @dragend="$event.currentTarget.style.opacity=''"
                                             @dragover.prevent
                                             @drop.prevent="(function(to){ $event.currentTarget.style.opacity=''; const from=parseInt($event.dataTransfer.getData('text/plain')); if(isNaN(from)||from===to)return; const a=editingElement.settings.items; const m=a.splice(from,1)[0]; a.splice(to,0,m); activeTabsItem=null; })(idx)">
                                            <!-- Item Row -->
                                            <div class="flex items-center gap-2 px-2 py-2 bg-slate-50 cursor-pointer"
                                                 @click="activeTabsItem = (activeTabsItem === idx ? null : idx)">
                                                <i class="fa fa-grip-vertical text-slate-300 text-[11px] cursor-grab"></i>
                                                <span class="flex-1 text-[12px] text-slate-600 truncate">@{{ item.label || 'Tab ' + (idx + 1) }}</span>
                                                <button @click.stop="editingElement.settings.items.splice(idx, 1); if(activeTabsItem === idx) activeTabsItem = null;"
                                                        class="text-slate-300 hover:text-red-400 transition-colors">
                                                    <i class="fa fa-trash-alt text-[10px]"></i>
                                                </button>
                                                <i :class="activeTabsItem === idx ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-slate-300 text-[10px]"></i>
                                            </div>
                                            <!-- Expanded Edit Area -->
                                            <div v-if="activeTabsItem === idx" class="p-3 space-y-3 border-t border-slate-100">
                                                <div>
                                                    <div class="flex justify-between items-center mb-1">
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase">Label</label>
                                                    </div>
                                                    <input type="text" v-model="item.label"
                                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                </div>
                                                <div>
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Content (HTML)</label>
                                                    <textarea v-model="item.content" rows="4"
                                                              class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea] resize-y font-mono"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Default Active -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Default Active Tab</label>
                                    <p class="text-[10px] text-slate-400 mb-2">Index of the tab to show by default (0 = first).</p>
                                    <input type="number" min="0" v-model.number="editingElement.settings.defaultActive"
                                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Style -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Tab Style</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.style = 'underline'"
                                                :class="(!editingElement.settings.style || editingElement.settings.style === 'underline') ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Underline</button>
                                        <button @click="editingElement.settings.style = 'pill'"
                                                :class="editingElement.settings.style === 'pill' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Pill</button>
                                        <button @click="editingElement.settings.style = 'boxed'"
                                                :class="editingElement.settings.style === 'boxed' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Boxed</button>
                                    </div>
                                </div>

                                <!-- Alignment -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Tab Alignment</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.alignment = 'left'"
                                                :class="(!editingElement.settings.alignment || editingElement.settings.alignment === 'left') ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 rounded transition-all flex items-center justify-center" title="Left">
                                            <i class="fas fa-align-left text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.alignment = 'center'"
                                                :class="editingElement.settings.alignment === 'center' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 rounded transition-all flex items-center justify-center" title="Center">
                                            <i class="fas fa-align-center text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.alignment = 'right'"
                                                :class="editingElement.settings.alignment === 'right' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 rounded transition-all flex items-center justify-center" title="Right">
                                            <i class="fas fa-align-right text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ POST CONTENT ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'post_content'" class="space-y-8">

                                <!-- Content Display -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Content Display</label>
                                    </div>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.content_display = 'excerpt'"
                                                :class="(editingElement.settings.content_display === 'excerpt' || !editingElement.settings.content_display) ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Excerpt</button>
                                        <button @click="editingElement.settings.content_display = 'full'"
                                                :class="editingElement.settings.content_display === 'full' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Full Content</button>
                                    </div>
                                </div>

                                <!-- Excerpt-only options -->
                                <template v-if="editingElement.settings.content_display === 'excerpt' || !editingElement.settings.content_display">
                                    <!-- Excerpt Length -->
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">Excerpt Length</label>
                                        </div>
                                        <div class="flex gap-3 items-center">
                                            <input type="number" v-model.number="editingElement.settings.excerptLength" min="10" max="1000"
                                                   class="w-20 border border-slate-200 rounded px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                                            <input type="range" v-model.number="editingElement.settings.excerptLength" min="10" max="500" class="flex-1 accent-[#0091ea]">
                                        </div>
                                    </div>

                                    <!-- Strip HTML -->
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">Strip HTML From Post Content</label>
                                        </div>
                                        <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                                            <button @click="editingElement.settings.stripHtml = true"
                                                    :class="editingElement.settings.stripHtml !== false ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                    class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Yes</button>
                                            <button @click="editingElement.settings.stripHtml = false"
                                                    :class="editingElement.settings.stripHtml === false ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                    class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">No</button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Element Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ POST META ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'post_meta'" class="space-y-6">

                                <!-- Meta Items — Reorderable list -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-3">Meta Items &amp; Order</label>
                                    <p class="text-[11px] text-slate-400 mb-3">Use ↑↓ to reorder. Frontend renders in the same order.</p>
                                    <div class="space-y-1.5">
                                        <div v-for="(key, idx) in (editingElement.settings.metaOrder || ['categories','tags','author','date','reading_time'])" :key="key"
                                             class="border border-slate-200 rounded-md overflow-hidden bg-white">

                                            <!-- Row header -->
                                            <div class="flex items-center gap-2 px-2 py-2">
                                                <!-- Up / Down -->
                                                <div class="flex flex-col gap-0.5 flex-shrink-0">
                                                    <button type="button"
                                                            @click="idx > 0 && (editingElement.settings.metaOrder = (() => { const a = [...(editingElement.settings.metaOrder||['categories','tags','author','date','reading_time'])]; a.splice(idx-1,0,a.splice(idx,1)[0]); return a; })())"
                                                            :class="idx === 0 ? 'opacity-20 cursor-not-allowed' : 'hover:bg-[#1a5a96] hover:text-white'"
                                                            class="w-5 h-4 rounded text-[9px] flex items-center justify-center bg-slate-100 text-slate-500 transition-colors">
                                                        <i class="fa fa-caret-up"></i>
                                                    </button>
                                                    <button type="button"
                                                            @click="idx < ((editingElement.settings.metaOrder||[]).length-1) && (editingElement.settings.metaOrder = (() => { const a = [...(editingElement.settings.metaOrder||['categories','tags','author','date','reading_time'])]; a.splice(idx+1,0,a.splice(idx,1)[0]); return a; })())"
                                                            :class="idx === (editingElement.settings.metaOrder||[]).length-1 ? 'opacity-20 cursor-not-allowed' : 'hover:bg-[#1a5a96] hover:text-white'"
                                                            class="w-5 h-4 rounded text-[9px] flex items-center justify-center bg-slate-100 text-slate-500 transition-colors">
                                                        <i class="fa fa-caret-down"></i>
                                                    </button>
                                                </div>

                                                <!-- Show checkbox -->
                                                <input type="checkbox" class="accent-[#0091ea] w-4 h-4 flex-shrink-0"
                                                       :checked="key==='categories' ? editingElement.settings.showCategories!==false : (key==='tags' ? !!editingElement.settings.showTags : (key==='author' ? editingElement.settings.showAuthor!==false : (key==='date' ? editingElement.settings.showDate!==false : !!editingElement.settings.showReadingTime)))"
                                                       @change="key==='categories' ? editingElement.settings.showCategories=$event.target.checked : (key==='tags' ? editingElement.settings.showTags=$event.target.checked : (key==='author' ? editingElement.settings.showAuthor=$event.target.checked : (key==='date' ? editingElement.settings.showDate=$event.target.checked : editingElement.settings.showReadingTime=$event.target.checked)))">

                                                <!-- Icon + Label -->
                                                <i :class="key==='categories'?'fa fa-folder-open':(key==='tags'?'fa fa-tags':(key==='author'?'fa fa-user':(key==='date'?'fa fa-calendar':'fa fa-clock')))"
                                                   class="text-slate-400 text-[12px] w-4 text-center flex-shrink-0"></i>
                                                <span class="text-[13px] text-slate-700 font-medium">
                                                    @{{ key==='categories'?'Categories':(key==='tags'?'Tags':(key==='author'?'Author':(key==='date'?'Publish Date':'Reading Time'))) }}
                                                </span>
                                            </div>

                                            <!-- Sub-options: Categories -->
                                            <template v-if="key==='categories' && editingElement.settings.showCategories!==false">
                                                <div class="px-3 pb-2.5 pt-1.5 border-t border-slate-100 bg-slate-50/60 space-y-2">
                                                    <div>
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Taxonomy Slug</label>
                                                        <input type="text" v-model="editingElement.settings.categoryTaxonomy" placeholder="category"
                                                               class="w-full border border-slate-200 rounded px-2.5 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                                                        <p class="text-[10px] text-slate-400 mt-1">e.g. <code>category</code>, <code>product-category</code></p>
                                                    </div>
                                                    <div>
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Max Categories</label>
                                                        <input type="number" v-model.number="editingElement.settings.limitCategories"
                                                               placeholder="All" min="1" max="20"
                                                               class="w-full border border-slate-200 rounded px-2.5 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                                                        <p class="text-[10px] text-slate-400 mt-1">Leave empty to show all</p>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Sub-options: Tags -->
                                            <template v-if="key==='tags' && editingElement.settings.showTags">
                                                <div class="px-3 pb-2.5 pt-1.5 border-t border-slate-100 bg-slate-50/60 space-y-2">
                                                    <div>
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Taxonomy Slug</label>
                                                        <input type="text" v-model="editingElement.settings.tagTaxonomy" placeholder="tag"
                                                               class="w-full border border-slate-200 rounded px-2.5 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                                                        <p class="text-[10px] text-slate-400 mt-1">e.g. <code>tag</code>, <code>product-tag</code></p>
                                                    </div>
                                                    <div>
                                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Max Tags</label>
                                                        <input type="number" v-model.number="editingElement.settings.limitTags"
                                                               placeholder="All" min="1" max="20"
                                                               class="w-full border border-slate-200 rounded px-2.5 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                                                        <p class="text-[10px] text-slate-400 mt-1">Leave empty to show all</p>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Sub-options: Date format -->
                                            <template v-if="key==='date' && editingElement.settings.showDate!==false">
                                                <div class="px-3 pb-2.5 pt-1.5 border-t border-slate-100 bg-slate-50/60">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Date Format</label>
                                                    <select v-model="editingElement.settings.dateFormat"
                                                            class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                                                        <option value="M j, Y">Jan 9, 2026</option>
                                                        <option value="d M Y">09 Jan 2026</option>
                                                        <option value="d/m/Y">09/01/2026</option>
                                                        <option value="Y-m-d">2026-01-09</option>
                                                        <option value="relative">Relative (2 days ago)</option>
                                                    </select>
                                                </div>
                                            </template>

                                        </div>
                                    </div>
                                </div>

                                <!-- Layout -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Layout</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1">
                                        <button @click="editingElement.settings.layout = 'inline'"
                                                :class="(editingElement.settings.layout || 'inline') === 'inline' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">
                                            <i class="fa fa-grip-horizontal mr-1"></i> Inline
                                        </button>
                                        <button @click="editingElement.settings.layout = 'stacked'"
                                                :class="editingElement.settings.layout === 'stacked' ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">
                                            <i class="fa fa-list mr-1"></i> Stacked
                                        </button>
                                    </div>
                                </div>

                                <!-- Show Icons -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Show Icons</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                                        <button @click="editingElement.settings.showIcons = true"
                                                :class="editingElement.settings.showIcons !== false ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Yes</button>
                                        <button @click="editingElement.settings.showIcons = false"
                                                :class="editingElement.settings.showIcons === false ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">No</button>
                                    </div>
                                </div>

                                <!-- Separator (inline only) -->
                                <template v-if="(editingElement.settings.layout || 'inline') === 'inline'">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Separator</label>
                                        <input type="text" v-model="editingElement.settings.separator" maxlength="5"
                                               placeholder="·"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </template>

                                <!-- Element Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ VIDEO ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'video'" class="space-y-6">

                                <!-- Video Source Type -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Video Source</label>
                                    <div class="grid grid-cols-2 gap-1">
                                        <button @click="editingElement.settings.videoSource = 'youtube'"
                                                :class="(editingElement.settings.videoSource || 'youtube') === 'youtube' ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                                class="py-2 rounded text-[11px] font-bold transition-colors flex items-center justify-center gap-1.5">
                                            <i class="fa fa-play-circle"></i> YouTube / Vimeo
                                        </button>
                                        <button @click="editingElement.settings.videoSource = 'selfhost'"
                                                :class="(editingElement.settings.videoSource || 'youtube') === 'selfhost' ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                                class="py-2 rounded text-[11px] font-bold transition-colors flex items-center justify-center gap-1.5">
                                            <i class="fa fa-upload"></i> Self Host / MP4
                                        </button>
                                    </div>
                                </div>

                                <!-- YouTube / Vimeo URL input -->
                                <div v-if="(editingElement.settings.videoSource || 'youtube') === 'youtube'">
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Video URL</label>
                                    </div>
                                    <input type="text" v-model="editingElement.settings.url"
                                           placeholder="https://youtube.com/watch?v=... or vimeo.com/..."
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    <p class="text-[10px] text-slate-400 mt-1.5">Supports YouTube and Vimeo URLs</p>
                                </div>

                                <!-- Self Host / MP4 upload -->
                                <div v-else>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Video File</label>
                                    <div v-if="!editingElement.settings.url"
                                         @click="openMediaModal('url')"
                                         class="w-full border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center gap-2 py-5 cursor-pointer hover:border-[#0091ea] hover:bg-blue-50/30 transition-all group">
                                        <div class="w-8 h-8 bg-[#2271b1] rounded-full flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform flex-shrink-0">
                                            <i class="fa fa-upload text-xs"></i>
                                        </div>
                                        <span class="text-[12px] text-slate-500 font-medium">Upload or select video</span>
                                    </div>
                                    <div v-else>
                                        <div class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-lg mb-2">
                                            <i class="fa fa-film text-[#0091ea] text-sm flex-shrink-0"></i>
                                            <span class="text-[11px] text-slate-500 truncate flex-1 font-mono">@{{ editingElement.settings.url }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="editingElement.settings.url = ''" class="flex-1 h-9 flex items-center justify-center border border-slate-200 rounded text-[11px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Remove</button>
                                            <button @click="openMediaModal('url')" class="flex-1 h-9 flex items-center justify-center bg-[#2271b1] text-white rounded text-[11px] font-bold hover:bg-[#1a5a96] transition-colors">Change</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aspect Ratio -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Aspect Ratio</label>
                                    <div class="grid grid-cols-4 gap-1">
                                        <button v-for="ratio in ['16-9','4-3','1-1','9-16']" :key="ratio"
                                                @click="editingElement.settings.aspectRatio = ratio"
                                                :class="(editingElement.settings.aspectRatio || '16-9') === ratio ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                                class="py-2 rounded text-[11px] font-bold transition-colors">
                                            @{{ ratio.replace('-',':') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Playback Options -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-3">Playback Options</label>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between py-2 px-3 bg-slate-50 rounded">
                                            <span class="text-[12px] text-slate-600">Show Controls</span>
                                            <button @click="editingElement.settings.controls = editingElement.settings.controls === false ? true : false"
                                                    :class="editingElement.settings.controls !== false ? 'bg-[#2271b1]' : 'bg-slate-200'"
                                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200">
                                                <span :class="editingElement.settings.controls !== false ? 'translate-x-4' : 'translate-x-1'"
                                                      class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"></span>
                                            </button>
                                        </div>
                                        <div class="flex items-center justify-between py-2 px-3 bg-slate-50 rounded">
                                            <span class="text-[12px] text-slate-600">Autoplay</span>
                                            <button @click="editingElement.settings.autoplay = !editingElement.settings.autoplay"
                                                    :class="editingElement.settings.autoplay ? 'bg-[#2271b1]' : 'bg-slate-200'"
                                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200">
                                                <span :class="editingElement.settings.autoplay ? 'translate-x-4' : 'translate-x-1'"
                                                      class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"></span>
                                            </button>
                                        </div>
                                        <div class="flex items-center justify-between py-2 px-3 bg-slate-50 rounded">
                                            <span class="text-[12px] text-slate-600">Muted</span>
                                            <button @click="editingElement.settings.muted = !editingElement.settings.muted"
                                                    :class="editingElement.settings.muted ? 'bg-[#2271b1]' : 'bg-slate-200'"
                                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200">
                                                <span :class="editingElement.settings.muted ? 'translate-x-4' : 'translate-x-1'"
                                                      class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"></span>
                                            </button>
                                        </div>
                                        <div class="flex items-center justify-between py-2 px-3 bg-slate-50 rounded">
                                            <span class="text-[12px] text-slate-600">Loop</span>
                                            <button @click="editingElement.settings.loop = !editingElement.settings.loop"
                                                    :class="editingElement.settings.loop ? 'bg-[#2271b1]' : 'bg-slate-200'"
                                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200">
                                                <span :class="editingElement.settings.loop ? 'translate-x-4' : 'translate-x-1'"
                                                      class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div class="pt-4 border-t border-slate-50">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ GALLERY ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'gallery'" class="space-y-6">

                                <!-- Images list -->
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Images</label>
                                        <span class="text-[10px] text-slate-400">@{{ (editingElement.settings.images || []).length }} image<span v-if="(editingElement.settings.images || []).length !== 1">s</span></span>
                                    </div>

                                    <div class="space-y-2 mb-3">
                                        <div v-for="(img, idx) in (editingElement.settings.images || [])" :key="idx"
                                             class="border border-slate-200 rounded-lg overflow-hidden bg-white"
                                             draggable="true"
                                             @dragstart.stop="galleryDragStart(idx)"
                                             @dragover.prevent
                                             @drop.prevent="galleryDrop(idx)">
                                            <!-- Image row header -->
                                            <div class="flex items-center gap-2 px-2 py-1.5 bg-slate-50 border-b border-slate-100">
                                                <i class="fa fa-grip-vertical text-slate-300 hover:text-slate-500 text-[10px] cursor-grab flex-shrink-0" title="Drag to reorder"></i>
                                                <div class="w-8 h-8 rounded overflow-hidden flex-shrink-0 bg-slate-200 border border-slate-100 cursor-pointer"
                                                     @click="openGalleryImageMedia(idx)">
                                                    <img v-if="img.url" :src="img.url" class="w-full h-full object-cover">
                                                    <div v-else class="w-full h-full flex items-center justify-center">
                                                        <i class="fa fa-image text-slate-300 text-xs"></i>
                                                    </div>
                                                </div>
                                                <span class="flex-1 text-[10px] text-slate-500 font-bold truncate">Image @{{ idx + 1 }}</span>
                                                <div class="flex gap-0.5">
                                                    <button @click="editingElement.settings.images.splice(idx, 1)"
                                                            class="w-6 h-6 rounded flex items-center justify-center text-red-400 hover:bg-red-50 text-[9px] transition-colors">
                                                        <i class="fa fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- URL + Browse + Alt + Caption inputs -->
                                            <div class="p-2 space-y-1.5">
                                                <div class="flex gap-1.5">
                                                    <input type="text" v-model="img.url" placeholder="Image URL..."
                                                           class="flex-1 border border-slate-200 rounded px-2 py-1.5 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                    <button @click="openGalleryImageMedia(idx)"
                                                            class="px-2 py-1.5 bg-slate-100 border border-slate-200 rounded text-[10px] font-bold text-slate-600 hover:bg-[#1a5a96] hover:text-white hover:border-[#0091ea] transition-all whitespace-nowrap flex-shrink-0">
                                                        <i class="fa fa-upload text-[9px]"></i>
                                                    </button>
                                                </div>
                                                <input type="text" v-model="img.alt" placeholder="Alt text..."
                                                       class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                <input type="text" v-model="img.caption" placeholder="Caption (optional)..."
                                                       class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <button @click="editingElement.settings.images.push({url:'',alt:'',caption:''})"
                                                class="flex-1 py-2.5 border-2 border-dashed border-slate-200 rounded-lg text-[11px] font-bold text-slate-500 hover:border-[#0091ea] hover:text-[#0091ea] hover:bg-blue-50/30 transition-all flex items-center justify-center gap-2">
                                            <i class="fa fa-plus text-xs"></i> Add
                                        </button>
                                        <button @click="openGalleryBulkMedia()"
                                                class="flex-1 py-2.5 border-2 border-dashed border-slate-200 rounded-lg text-[11px] font-bold text-slate-500 hover:border-[#0091ea] hover:text-[#0091ea] hover:bg-blue-50/30 transition-all flex items-center justify-center gap-2">
                                            <i class="fa fa-images text-xs"></i> Bulk Upload
                                        </button>
                                    </div>
                                </div>

                                <!-- Caption Alignment -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Caption Alignment</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                        <button @click="editingElement.settings.captionAlign = 'left'"
                                                :class="(editingElement.settings.captionAlign || 'center') === 'left' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Left</button>
                                        <button @click="editingElement.settings.captionAlign = 'center'"
                                                :class="(!editingElement.settings.captionAlign || editingElement.settings.captionAlign === 'center') ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Center</button>
                                        <button @click="editingElement.settings.captionAlign = 'right'"
                                                :class="editingElement.settings.captionAlign === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold transition-all">Right</button>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ STAR RATING ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'star_rating'" class="space-y-6">

                                <!-- Interactive star picker -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-3">Rating</label>
                                    <div class="flex items-center justify-center gap-1 mb-3 py-3 bg-slate-50 rounded-lg">
                                        <template v-for="i in (editingElement.settings.maxStars || 5)" :key="i">
                                            <span style="position:relative;display:inline-block;line-height:1;cursor:pointer;user-select:none;"
                                                  :style="{ fontSize: '32px' }"
                                                  @click="editingElement.settings.rating = $event.offsetX < ($event.currentTarget.offsetWidth / 2) ? i - 0.5 : i">
                                                <span v-if="(editingElement.settings.rating !== undefined ? editingElement.settings.rating : 5) >= i"
                                                      :style="{ color: editingElement.settings.starColor || '#f59e0b' }">★</span>
                                                <span v-else-if="(editingElement.settings.rating !== undefined ? editingElement.settings.rating : 5) >= i - 0.5"
                                                      style="position:relative;display:inline-block;">
                                                    <span :style="{ color: editingElement.settings.emptyColor || '#d1d5db' }">★</span>
                                                    <span style="position:absolute;left:0;top:0;width:50%;overflow:hidden;"
                                                          :style="{ color: editingElement.settings.starColor || '#f59e0b' }">★</span>
                                                </span>
                                                <span v-else :style="{ color: editingElement.settings.emptyColor || '#d1d5db' }">★</span>
                                            </span>
                                        </template>
                                        <span class="ml-2 text-[13px] font-bold text-slate-500">
                                            @{{ editingElement.settings.rating !== undefined ? editingElement.settings.rating : 5 }}
                                        </span>
                                    </div>
                                    <input type="range" v-model.number="editingElement.settings.rating"
                                           min="0" :max="editingElement.settings.maxStars || 5" step="0.5"
                                           class="w-full accent-amber-400">
                                    <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                                        <span>0</span>
                                        <span>@{{ editingElement.settings.maxStars || 5 }}</span>
                                    </div>
                                </div>

                                <!-- Max Stars -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Max Stars</label>
                                    <input type="number" v-model.number="editingElement.settings.maxStars" min="1" max="10"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Label -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Label <span class="text-slate-400 font-normal">(optional)</span></label>
                                    </div>
                                    <input type="text" v-model="editingElement.settings.label" placeholder="e.g. Based on 127 reviews"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Alignment + Gap -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Alignment</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                        <button @click="editingElement.settings.textAlign = 'left'"
                                                :class="(editingElement.settings.textAlign || 'center') === 'left' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Left</button>
                                        <button @click="editingElement.settings.textAlign = 'center'"
                                                :class="(!editingElement.settings.textAlign || editingElement.settings.textAlign === 'center') ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Center</button>
                                        <button @click="editingElement.settings.textAlign = 'right'"
                                                :class="editingElement.settings.textAlign === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Right</button>
                                        <button @click="editingElement.settings.textAlign = 'full'"
                                                :class="editingElement.settings.textAlign === 'full' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold transition-all">Full</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Gap between stars (px)</label>
                                    <input type="number" v-model.number="editingElement.settings.gap" min="0" max="40"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>

                            </div>

                            <!-- ══ COUNTER ELEMENT ══ -->
                            <div v-else-if="editingElement?.type === 'counter'" class="space-y-6">
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">End Value</label>
                                    <input type="number" v-model.number="editingElement.settings.endValue" placeholder="100"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Start Value</label>
                                        <input type="number" v-model.number="editingElement.settings.startValue" placeholder="0"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Decimals</label>
                                        <input type="number" v-model.number="editingElement.settings.decimals" placeholder="0" min="0" max="5"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Prefix</label>
                                        <input type="text" v-model="editingElement.settings.prefix" placeholder="e.g. $"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Suffix</label>
                                        <input type="text" v-model="editingElement.settings.suffix" placeholder="e.g. +"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Label (caption)</label>
                                    <input type="text" v-model="editingElement.settings.label" placeholder="e.g. Happy Clients"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Duration (ms)</label>
                                        <input type="number" v-model.number="editingElement.settings.duration" placeholder="2000" min="200" max="10000" step="100"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Thousands Sep.</label>
                                        <select v-model="editingElement.settings.separator"
                                                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                            <option value="">None</option>
                                            <option value=",">Comma (1,000)</option>
                                            <option value=".">Dot (1.000)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                        </div>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                            </div>

                            <!-- ══ TICKER ══ -->
                            <div v-else-if="editingElement?.type === 'ticker'" class="space-y-6">

                                <!-- Items repeater -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Ticker Items</label>
                                        <button @click="if(!editingElement.settings.items) editingElement.settings.items=[]; editingElement.settings.items.push({text:'',url:''})"
                                                class="text-[11px] px-2 py-1 bg-[#2271b1] text-white rounded hover:bg-[#135e96] transition-colors">+ Add Item</button>
                                    </div>
                                    <div v-if="editingElement.settings.items && editingElement.settings.items.length" class="space-y-2">
                                        <div v-for="(item, idx) in editingElement.settings.items" :key="idx"
                                             class="bg-slate-50 border border-slate-200 rounded p-2 space-y-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <input type="text" v-model="item.text" placeholder="Item text..."
                                                       class="flex-1 border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                <button @click="editingElement.settings.items.splice(idx,1)"
                                                        class="text-red-400 hover:text-red-600 text-[14px] px-1 flex-shrink-0" title="Remove">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </div>
                                            <input type="text" v-model="item.url" placeholder="Link URL (optional)..."
                                                   class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                        </div>
                                    </div>
                                    <p v-else class="text-[11px] text-slate-400 italic py-2 text-center">No items yet. Click "+ Add Item".</p>
                                </div>

                                <!-- Label -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Label Badge <span class="font-normal text-slate-400">(optional)</span></label>
                                    <input type="text" v-model="editingElement.settings.label" placeholder="e.g. BREAKING, LATEST..."
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Label Animation -->
                                <div v-if="editingElement.settings.label">
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Label Animation</label>
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <button v-for="la in [
                                                    {v:'blink-dot', icon:'fa-circle',  label:'Blink Dot'},
                                                    {v:'pulse',     icon:'fa-sun',     label:'Pulse'},
                                                    {v:'flash',     icon:'fa-bolt',    label:'Flash'},
                                                    {v:'shake',     icon:'fa-bell',    label:'Shake'},
                                                    {v:'bounce',    icon:'fa-expand',  label:'Bounce'},
                                                    {v:'none',      icon:'fa-ban',     label:'None'},
                                                ]"
                                                :key="la.v"
                                                @click="editingElement.settings.labelAnimation = la.v"
                                                :class="(editingElement.settings.labelAnimation || 'blink-dot') === la.v
                                                    ? 'bg-[#2271b1] text-white border-[#2271b1]'
                                                    : 'bg-white text-slate-500 border-slate-200 hover:border-[#2271b1] hover:text-[#2271b1]'"
                                                class="flex flex-col items-center gap-1 py-2 px-1 border rounded text-[10px] transition-all">
                                            <i :class="'fa ' + la.icon + ' text-[13px]'"></i>
                                            <span>@{{ la.label }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Separator + Direction in 2 cols -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Separator</label>
                                        <select v-model="editingElement.settings.separator"
                                                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                            <option value="•">• Bullet</option>
                                            <option value="|">| Pipe</option>
                                            <option value="·">· Middle dot</option>
                                            <option value="—">— Dash</option>
                                            <option value="dance">⠿ Dancing Dots</option>
                                            <option value="">None</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">Direction</label>
                                        <select v-model="editingElement.settings.direction"
                                                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                            <option value="left">← Left</option>
                                            <option value="right">→ Right</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Text Effect -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-2">Text Effect</label>
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <button v-for="te in [
                                                    {v:'none',      icon:'fa-minus',      label:'None'},
                                                    {v:'glow',      icon:'fa-star',       label:'Glow'},
                                                    {v:'highlight', icon:'fa-highlighter', label:'Highlight'},
                                                ]"
                                                :key="te.v"
                                                @click="editingElement.settings.textEffect = te.v"
                                                :class="(editingElement.settings.textEffect || 'none') === te.v
                                                    ? 'bg-[#2271b1] text-white border-[#2271b1]'
                                                    : 'bg-white text-slate-500 border-slate-200 hover:border-[#2271b1] hover:text-[#2271b1]'"
                                                class="flex flex-col items-center gap-1 py-2 px-1 border rounded text-[10px] transition-all">
                                            <i :class="'fa ' + te.icon + ' text-[13px]'"></i>
                                            <span>@{{ te.label }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Speed -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[12px] font-bold text-[#333]">Speed</label>
                                        <span class="text-[11px] text-slate-400">@{{ editingElement.settings.speed ?? 50 }} / 100</span>
                                    </div>
                                    <input type="range" min="1" max="100" v-model.number="editingElement.settings.speed"
                                           class="w-full accent-[#0091ea]">
                                    <div class="flex justify-between text-[10px] text-slate-400 mt-0.5">
                                        <span>Slow</span><span>Fast</span>
                                    </div>
                                </div>

                                <!-- Pause on hover -->
                                <div class="flex items-center justify-between">
                                    <label class="text-[12px] font-bold text-[#333]">Pause on Hover</label>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                                        <button @click="editingElement.settings.pauseOnHover = true"
                                                :class="(editingElement.settings.pauseOnHover ?? true) ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-4 py-1.5 text-[11px] font-black uppercase rounded transition-all">On</button>
                                        <button @click="editingElement.settings.pauseOnHover = false"
                                                :class="!(editingElement.settings.pauseOnHover ?? true) ? 'bg-[#2271b1] text-white shadow-md' : 'bg-[#2271b1]/20 text-[#0091ea]'"
                                                class="px-4 py-1.5 text-[11px] font-black uppercase rounded transition-all">Off</button>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <label class="text-[12px] font-bold text-[#333] block mb-3">Element Visibility</label>
                                    <div class="grid grid-cols-3 gap-2"
                                         @click.capture="if (!editingElement.settings.visibility) { editingElement.settings.visibility = { mobile: true, tablet: true, desktop: true }; }">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.mobile !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Mobile">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.tablet !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Tablet">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility && editingElement.settings.visibility.desktop !== false ? 'bg-[#2271b1] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center" title="Desktop">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- CSS Class & ID -->
                                <div class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
                                        <input type="text" v-model="editingElement.settings.cssClass"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                    <div>
                                        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
                                        <input type="text" v-model="editingElement.settings.cssId"
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                            </div>

                            <!-- ══ CUSTOM REGISTERED ELEMENTS ══ -->
                            @foreach($customElements ?? [] as $type => $custEl)
                            @if($type === 'text_block' || $type === 'button' || $type === 'image') @continue @endif
                            @php
                                $__allFields     = lazy_normalize_custom_fields($custEl);
                                $__generalFields = array_filter($__allFields, fn($f) => !isset($f['tab']) || in_array($f['tab'], ['general','content','']));
                            @endphp
                            <div v-else-if="editingElement?.type === '{{ $type }}'" class="space-y-8">
                                @if($type === 'menu')
                                    @include('falcon-cms::admin.falcon-builder.partials.components.elements.menu-content')
                                @endif

                                @if($type !== 'menu')
                                    @foreach($__generalFields as $fieldKey => $field)
                                        @if(!empty($field['condition']))
                                            <template v-if="customFieldVisible(editingElement.settings, {{ Illuminate\Support\Js::from($field['condition']) }})">
                                                @include('falcon-cms::admin.falcon-builder.partials.components.elements.custom-field-renderer', compact('field', 'fieldKey'))
                                            </template>
                                        @else
                                            @include('falcon-cms::admin.falcon-builder.partials.components.elements.custom-field-renderer', compact('field', 'fieldKey'))
                                        @endif
                                    @endforeach
                                @endif

                                <!-- Default: Element Visibility (shared partial) -->
                                <div v-if="editingElement?.type !== 'menu'" class="pt-4 border-t border-slate-50">
                                    @include('falcon-cms::admin.falcon-builder.partials.components.fields.element-visibility')
                                </div>

                                <!-- Default: CSS Class & ID (shared partial) -->
                                <div v-if="editingElement?.type !== 'menu'" class="pt-4 border-t border-slate-50">
                                    @include('falcon-cms::admin.falcon-builder.partials.components.fields.css-attributes')
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <!-- ══ DESIGN TAB ══ -->
                        <div v-if="editingContext.tab === 'design'" class="p-5 space-y-6">
                             <!-- Design Settings for Gallery -->
                             <div v-if="editingElement?.type === 'gallery'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.gallery-design')
                             </div>
                             <!-- Design Settings for Star Rating -->
                             <div v-else-if="editingElement?.type === 'star_rating'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.star-rating-design')
                             </div>

                             <!-- Design Settings for Counter -->
                             <div v-else-if="editingElement?.type === 'counter'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.counter-design')
                             </div>

                             <!-- Design Settings for Special Text -->
                             <div v-else-if="editingElement?.type === 'text_block' || editingElement?.type === 'special_text'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.text-block-design')
                             </div>

                             <!-- Design Settings for Title -->
                             <div v-else-if="editingElement?.type === 'title'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.title-design')
                             </div>

                             <!-- Design Settings for Button -->
                             <div v-else-if="editingElement?.type === 'button'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.button-design')
                             </div>

                             <!-- Design Settings for Image -->
                             <div v-else-if="editingElement?.type === 'image'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.image-design')
                             </div>

                             <!-- Design Settings for Menu -->
                             <div v-else-if="editingElement?.type === 'menu'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.menu-design')
                             </div>
                             <!-- Design Settings for Card -->
                             <div v-else-if="editingElement?.type === 'card'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.card-design')
                             </div>
                             <!-- Design Settings for Spacer -->
                             <div v-else-if="editingElement?.type === 'spacer'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.spacer-design')
                             </div>

                             <!-- Design Settings for HTML Block -->
                             <div v-else-if="editingElement?.type === 'html'" class="space-y-6 pb-10">
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">MARGIN</label>
                                     <div class="grid grid-cols-2 gap-2">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginTop"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginTopUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginBottom"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginBottomUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <!-- Design Settings for Ticker -->
                             <div v-else-if="editingElement?.type === 'ticker'" class="space-y-6 pb-10">
                                 <!-- Colors -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">Colors</label>
                                     <div class="space-y-3">
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Background</label>
                                                 <button @click="clearColorField(editingElement.settings, 'bgColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'bgColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.bgColor || '#1e3a8a' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.bgColor" placeholder="#1e3a8a" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Text Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'textColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'textColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.textColor || '#ffffff' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.textColor" placeholder="#ffffff" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Label Background</label>
                                                 <button @click="clearColorField(editingElement.settings, 'labelBgColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'labelBgColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.labelBgColor || '#ef4444' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.labelBgColor" placeholder="#ef4444" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Label Text</label>
                                                 <button @click="clearColorField(editingElement.settings, 'labelTextColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'labelTextColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.labelTextColor || '#ffffff' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.labelTextColor" placeholder="#ffffff" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- Typography -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">Typography</label>
                                     <div class="grid grid-cols-2 gap-3">
                                         <div>
                                             <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block">Font Size</label>
                                             <input type="text" v-model="editingElement.settings.fontSize" placeholder="14px"
                                                    class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                         <div>
                                             <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block">Font Weight</label>
                                             <select v-model="editingElement.settings.fontWeight"
                                                     class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="300">Light 300</option>
                                                 <option value="400">Normal 400</option>
                                                 <option value="500">Medium 500</option>
                                                 <option value="600">Semibold 600</option>
                                                 <option value="700">Bold 700</option>
                                             </select>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- Size -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">Size</label>
                                     <div class="grid grid-cols-2 gap-3">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[10px] font-bold text-slate-400 uppercase text-center">Height (px)</label>
                                             <input type="number" min="28" max="120" v-model.number="editingElement.settings.height"
                                                    class="w-full border border-slate-200 rounded px-2 h-8 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[10px] font-bold text-slate-400 uppercase text-center">Border Radius</label>
                                             <input type="number" min="0" v-model.number="editingElement.settings.borderRadius"
                                                    class="w-full border border-slate-200 rounded px-2 h-8 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                     </div>
                                 </div>

                                 <!-- Margin -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">Margin</label>
                                     <div class="grid grid-cols-2 gap-2">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginTop"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginTopUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginBottom"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginBottomUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>

                             <!-- Design Settings for Icon Box -->
                             <div v-else-if="editingElement?.type === 'icon_box'" class="space-y-6 pb-10">

                                 <!-- ICON -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">ICON</label>
                                     <div class="space-y-3">
                                         <!-- Icon Size + Spacing: 2-col -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Size</label>
                                                 <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                     <input type="number" min="1" v-model.number="editingElement.settings.iconSize"
                                                            class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                     <select v-model="editingElement.settings.iconSizeUnit"
                                                             class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                         <option value="px">px</option><option value="rem">rem</option>
                                                     </select>
                                                 </div>
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Spacing Below</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.iconSpacing"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Icon Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Icon Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'iconColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'iconColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.iconColor || '#2271b1' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.iconColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <!-- Icon Background Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Background Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'iconBgColor', 'iconBgColorOpacity')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'iconBgColor', 'iconBgColorOpacity')">
                                                     <div :style="{ backgroundColor: hexToRgba(editingElement.settings.iconBgColor, editingElement.settings.iconBgColorOpacity) }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.iconBgColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <!-- Border Radius + Padding: 2-col (when bg set) -->
                                         <div v-if="editingElement.settings.iconBgColor" class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Border Radius</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.iconBorderRadius"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Padding (px)</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.iconPadding"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- TITLE -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">TITLE</label>
                                     <div class="space-y-3">
                                         <!-- Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.titleFontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.titleFontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- HTML Tag + Font Size: 2-col -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">HTML Tag</label>
                                                 <select v-model="editingElement.settings.titleTag"
                                                         class="w-full border border-slate-200 rounded px-1 h-8 text-[11px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                                     <option value="h1">H1</option><option value="h2">H2</option><option value="h3">H3</option>
                                                     <option value="h4">H4</option><option value="h5">H5</option><option value="h6">H6</option>
                                                     <option value="p">P</option><option value="div">DIV</option>
                                                 </select>
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Font Size</label>
                                                 <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                     <input type="number" min="1" v-model.number="editingElement.settings.titleFontSize"
                                                            class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                     <select v-model="editingElement.settings.titleFontSizeUnit"
                                                             class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                         <option value="px">px</option><option value="rem">rem</option>
                                                     </select>
                                                 </div>
                                             </div>
                                         </div>
                                         <!-- Font Weight + Letter Spacing: 2-col -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Weight</label>
                                                 <select v-model="editingElement.settings.titleFontWeight"
                                                         class="w-full border border-slate-200 rounded px-1 h-8 text-[11px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                                     <option value="400">400 Regular</option><option value="500">500 Medium</option>
                                                     <option value="600">600 Semibold</option><option value="700">700 Bold</option>
                                                     <option value="800">800 Extrabold</option><option value="900">900 Black</option>
                                                 </select>
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Letter Spacing</label>
                                                 <input type="text" v-model="editingElement.settings.titleLetterSpacing" placeholder="0px"
                                                        class="w-full border border-slate-200 rounded-md px-2 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Line Height + Spacing Below: 2-col -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Line Height</label>
                                                 <input type="number" min="0" step="0.1" v-model.number="editingElement.settings.titleLineHeight" placeholder="1.3"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Spacing Below</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.titleSpacing"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Text Transform -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Text Transform</label>
                                             <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                                 <button @click="editingElement.settings.titleTextTransform = 'none'"
                                                         :class="(!editingElement.settings.titleTextTransform || editingElement.settings.titleTextTransform === 'none') ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Normal">—</button>
                                                 <button @click="editingElement.settings.titleTextTransform = 'uppercase'"
                                                         :class="editingElement.settings.titleTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Uppercase">AB</button>
                                                 <button @click="editingElement.settings.titleTextTransform = 'lowercase'"
                                                         :class="editingElement.settings.titleTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Lowercase">ab</button>
                                                 <button @click="editingElement.settings.titleTextTransform = 'capitalize'"
                                                         :class="editingElement.settings.titleTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold transition-all" title="Capitalize">Ab</button>
                                             </div>
                                         </div>
                                         <!-- Title Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Title Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'titleColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'titleColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.titleColor || '#222222' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.titleColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- DESCRIPTION -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">DESCRIPTION</label>
                                     <div class="space-y-3">
                                         <!-- Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.descFontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.descFontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- Font Size + Font Weight: 2-col -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Font Size</label>
                                                 <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                     <input type="number" min="1" v-model.number="editingElement.settings.descFontSize"
                                                            class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                     <select v-model="editingElement.settings.descFontSizeUnit"
                                                             class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                         <option value="px">px</option><option value="rem">rem</option>
                                                     </select>
                                                 </div>
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Weight</label>
                                                 <select v-model="editingElement.settings.descFontWeight"
                                                         class="w-full border border-slate-200 rounded px-1 h-8 text-[11px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                                     <option value="400">400 Regular</option><option value="500">500 Medium</option>
                                                     <option value="600">600 Semibold</option><option value="700">700 Bold</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <!-- Line Height + Letter Spacing: 2-col -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Line Height</label>
                                                 <input type="number" min="0" step="0.1" v-model.number="editingElement.settings.descLineHeight"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Letter Spacing</label>
                                                 <input type="text" v-model="editingElement.settings.descLetterSpacing" placeholder="0px"
                                                        class="w-full border border-slate-200 rounded-md px-2 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Text Transform -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Text Transform</label>
                                             <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                                 <button @click="editingElement.settings.descTextTransform = 'none'"
                                                         :class="(!editingElement.settings.descTextTransform || editingElement.settings.descTextTransform === 'none') ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Normal">—</button>
                                                 <button @click="editingElement.settings.descTextTransform = 'uppercase'"
                                                         :class="editingElement.settings.descTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Uppercase">AB</button>
                                                 <button @click="editingElement.settings.descTextTransform = 'lowercase'"
                                                         :class="editingElement.settings.descTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Lowercase">ab</button>
                                                 <button @click="editingElement.settings.descTextTransform = 'capitalize'"
                                                         :class="editingElement.settings.descTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold transition-all" title="Capitalize">Ab</button>
                                             </div>
                                         </div>
                                         <!-- Desc Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Description Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'descColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'descColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.descColor || '#666666' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.descColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- MARGIN -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">MARGIN</label>
                                     <div class="grid grid-cols-2 gap-2">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginTop"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginTopUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginBottom"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginBottomUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                             </div>

                             <!-- Design Settings for Accordion -->
                             <div v-else-if="editingElement?.type === 'accordion'" class="space-y-6 pb-10">

                                 <!-- LAYOUT -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">LAYOUT</label>
                                     <div class="space-y-3">
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Border Radius</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.borderRadius"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Item Gap (px)</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.itemGap"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Border Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Border Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'borderColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'borderColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.borderColor || '#e2e8f0' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.borderColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- HEADER -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">HEADER</label>
                                     <div class="space-y-3">
                                         <!-- Font Size + Padding -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Font Size (px)</label>
                                                 <input type="number" min="1" v-model.number="editingElement.settings.titleFontSize"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Padding (px)</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.titlePadding"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Font Weight -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Weight</label>
                                             <select v-model="editingElement.settings.titleFontWeight"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="400">Regular (400)</option>
                                                 <option value="500">Medium (500)</option>
                                                 <option value="600">Semi Bold (600)</option>
                                                 <option value="700">Bold (700)</option>
                                                 <option value="800">Extra Bold (800)</option>
                                             </select>
                                         </div>
                                         <!-- Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.titleFontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.titleFontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- Letter Spacing + Line Height -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Letter Spacing</label>
                                                 <input type="text" v-model="editingElement.settings.titleLetterSpacing" placeholder="0px"
                                                        class="w-full border border-slate-200 rounded-md px-2 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Line Height</label>
                                                 <input type="number" min="0" step="0.1" v-model.number="editingElement.settings.titleLineHeight" placeholder="1.4"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Text Transform -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Text Transform</label>
                                             <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                                 <button @click="editingElement.settings.titleTextTransform = 'none'"
                                                         :class="(!editingElement.settings.titleTextTransform || editingElement.settings.titleTextTransform === 'none') ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Normal">—</button>
                                                 <button @click="editingElement.settings.titleTextTransform = 'uppercase'"
                                                         :class="editingElement.settings.titleTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Uppercase">AB</button>
                                                 <button @click="editingElement.settings.titleTextTransform = 'lowercase'"
                                                         :class="editingElement.settings.titleTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all" title="Lowercase">ab</button>
                                                 <button @click="editingElement.settings.titleTextTransform = 'capitalize'"
                                                         :class="editingElement.settings.titleTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                                                         class="flex-1 py-2 text-[10px] font-bold transition-all" title="Capitalize">Ab</button>
                                             </div>
                                         </div>
                                         <!-- Colors 2-col grid -->
                                         <div class="grid grid-cols-2 gap-3">
                                             <!-- Title Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Text</label>
                                                     <button @click="clearColorField(editingElement.settings, 'titleColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'titleColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.titleColor || '#222222' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.titleColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                             <!-- Title Bg Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Background</label>
                                                     <button @click="clearColorField(editingElement.settings, 'titleBgColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'titleBgColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.titleBgColor || '#f8fafc' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.titleBgColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                             <!-- Active Text Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Active Text</label>
                                                     <button @click="clearColorField(editingElement.settings, 'titleActiveColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'titleActiveColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.titleActiveColor || '#ffffff' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.titleActiveColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                             <!-- Active Bg Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Active Bg</label>
                                                     <button @click="clearColorField(editingElement.settings, 'titleActiveBgColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'titleActiveBgColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.titleActiveBgColor || '#2271b1' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.titleActiveBgColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- CONTENT -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">CONTENT</label>
                                     <div class="space-y-3">
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Font Size (px)</label>
                                                 <input type="number" min="1" v-model.number="editingElement.settings.contentFontSize"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Padding (px)</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.contentPadding"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Content Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.contentFontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.contentFontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- Letter Spacing + Line Height -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Letter Spacing</label>
                                                 <input type="text" v-model="editingElement.settings.contentLetterSpacing" placeholder="0px"
                                                        class="w-full border border-slate-200 rounded-md px-2 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Line Height</label>
                                                 <input type="number" min="0" step="0.1" v-model.number="editingElement.settings.contentLineHeight" placeholder="1.6"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <div class="grid grid-cols-2 gap-3">
                                             <!-- Content Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Text</label>
                                                     <button @click="clearColorField(editingElement.settings, 'contentColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'contentColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.contentColor || '#555555' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.contentColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                             <!-- Content Bg Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Background</label>
                                                     <button @click="clearColorField(editingElement.settings, 'contentBgColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'contentBgColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.contentBgColor || '#ffffff' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.contentBgColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- SPACING -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">MARGIN</label>
                                     <div class="grid grid-cols-2 gap-2">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginTop"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginTopUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginBottom"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginBottomUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                             </div>

                             <!-- Design Settings for Icon List -->
                             <div v-else-if="editingElement?.type === 'icon_list'" class="space-y-6 pb-10">

                                 <!-- ICON -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">ICON</label>
                                     <div class="space-y-3">
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-2">Default Icon</label>
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
                                                 <div class="h-48 overflow-y-auto p-2 bg-white custom-scrollbar">
                                                     <div class="grid grid-cols-5 gap-1.5">
                                                         <button v-for="icon in filteredIcons" :key="icon"
                                                                 @click="editingElement.settings.defaultIcon = icon"
                                                                 :class="editingElement.settings.defaultIcon === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                                                                 class="aspect-square flex items-center justify-center rounded border transition-all p-1"
                                                                 :title="icon">
                                                             <i :class="[icon, 'text-base']"></i>
                                                         </button>
                                                     </div>
                                                     <div v-if="filteredIcons.length === 0" class="py-10 text-center text-[10px] text-slate-400">No icons found</div>
                                                 </div>
                                                 <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                                                     <div class="flex items-center gap-2">
                                                         <div class="w-7 h-7 bg-white rounded border border-slate-200 flex items-center justify-center"
                                                              :style="{ color: editingElement.settings.iconColor || '#2271b1' }">
                                                             <i :class="editingElement.settings.defaultIcon || 'fas fa-check'"></i>
                                                         </div>
                                                         <span class="text-[10px] text-slate-500 font-medium truncate max-w-[120px]">@{{ editingElement.settings.defaultIcon || 'No icon selected' }}</span>
                                                     </div>
                                                     <button v-if="editingElement.settings.defaultIcon" @click="editingElement.settings.defaultIcon = 'fa fa-check'"
                                                             class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Reset</button>
                                                 </div>
                                             </div>
                                         </div>
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Default Icon Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'iconColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'iconColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.iconColor || '#2271b1' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.iconColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Icon Size (px)</label>
                                             <input type="number" min="8" max="80" v-model.number="editingElement.settings.iconSize"
                                                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Icon Position</label>
                                             <div class="flex bg-slate-50 border border-slate-100 rounded p-0.5">
                                                 <button @click="editingElement.settings.iconPosition='left'"
                                                         :class="(!editingElement.settings.iconPosition||editingElement.settings.iconPosition==='left') ? 'bg-[#2271b1] text-white shadow-sm':'text-slate-500 hover:bg-slate-100'"
                                                         class="flex-1 py-1.5 text-[11px] font-bold rounded transition-all">Left</button>
                                                 <button @click="editingElement.settings.iconPosition='right'"
                                                         :class="editingElement.settings.iconPosition==='right' ? 'bg-[#2271b1] text-white shadow-sm':'text-slate-500 hover:bg-slate-100'"
                                                         class="flex-1 py-1.5 text-[11px] font-bold rounded transition-all">Right</button>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- TEXT -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">TEXT</label>
                                     <div class="space-y-3">
                                         <!-- Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.fontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.fontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- Text Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Text Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'textColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'textColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.textColor || '#333333' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.textColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <!-- Font Size -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Size</label>
                                             <div class="flex gap-1">
                                                 <input type="number" min="8" max="80" v-model.number="editingElement.settings.fontSize"
                                                        class="flex-1 border border-slate-200 rounded px-2 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                                 <select v-model="editingElement.settings.fontSizeUnit"
                                                         class="border border-slate-200 rounded px-2 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                                                     <option>px</option><option>rem</option><option>em</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <!-- Font Weight -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Weight</label>
                                             <select v-model="editingElement.settings.fontWeight"
                                                     class="w-full border border-slate-200 rounded px-2 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="300">Light 300</option>
                                                 <option value="400">Regular 400</option>
                                                 <option value="500">Medium 500</option>
                                                 <option value="600">SemiBold 600</option>
                                                 <option value="700">Bold 700</option>
                                                 <option value="800">ExtraBold 800</option>
                                             </select>
                                         </div>
                                         <!-- Line Height -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Line Height</label>
                                             <input type="text" v-model="editingElement.settings.lineHeight" placeholder="1.5"
                                                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                     </div>
                                 </div>

                                 <!-- SPACING & ALIGNMENT -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">SPACING &amp; ALIGNMENT</label>
                                     <div class="space-y-3">
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Alignment</label>
                                             <div class="flex bg-slate-50 border border-slate-100 rounded p-0.5">
                                                 <button v-for="a in ['left','center','right']" :key="a"
                                                         @click="editingElement.settings.textAlign=a"
                                                         :class="(editingElement.settings.textAlign||'left')===a ? 'bg-[#2271b1] text-white shadow-sm':'text-slate-500 hover:bg-slate-100'"
                                                         class="flex-1 py-1.5 text-[11px] font-bold rounded capitalize transition-all">@{{ a }}</button>
                                             </div>
                                         </div>
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Icon to Text Gap (px)</label>
                                             <input type="number" min="0" max="60" v-model.number="editingElement.settings.gap"
                                                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Item Spacing (px)</label>
                                             <input type="number" min="0" max="80" v-model.number="editingElement.settings.itemSpacing"
                                                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                         <!-- Margin -->
                                         <div>
                                             <div class="flex justify-between items-center mb-2">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Margin</label>
                                                 <div class="flex gap-1 items-center">
                                                     <button @click="['Top','Bottom'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                                     <div class="relative inline-block">
                                                         <button @click="activeResponsiveMenu = activeResponsiveMenu === 'iconListMargin' ? null : 'iconListMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                                                             <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                                                             <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                                                         </button>
                                                         <div v-show="activeResponsiveMenu === 'iconListMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
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
                                                     <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                                                         <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginTop' : 'marginTop_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginTop', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                         <select :value="getResponsiveVal(editingElement.settings, 'marginTopUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginTopUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                                                     </div>
                                                 </div>
                                                 <div class="flex flex-col gap-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                                     <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                                                         <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginBottom' : 'marginBottom_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginBottom', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                         <select :value="getResponsiveVal(editingElement.settings, 'marginBottomUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginBottomUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                             </div>

                             <!-- Design Settings for Tabs -->
                             <div v-else-if="editingElement?.type === 'tabs'" class="space-y-6 pb-10">

                                 <!-- TAB BUTTONS -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">TAB BUTTONS</label>
                                     <div class="space-y-3">
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Font Size (px)</label>
                                                 <input type="number" min="1" v-model.number="editingElement.settings.tabFontSize"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Weight</label>
                                                 <select v-model="editingElement.settings.tabFontWeight"
                                                         class="w-full h-8 border border-slate-200 rounded px-1 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                     <option value="400">Regular (400)</option>
                                                     <option value="500">Medium (500)</option>
                                                     <option value="600">Semi Bold (600)</option>
                                                     <option value="700">Bold (700)</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <!-- Tab Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.tabFontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.tabFontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- Letter Spacing -->
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase">Letter Spacing</label>
                                             <input type="text" v-model="editingElement.settings.tabLetterSpacing" placeholder="0px"
                                                    class="w-full border border-slate-200 rounded-md px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                         <div class="grid grid-cols-2 gap-3">
                                             <!-- Tab Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Tab Color</label>
                                                     <button @click="clearColorField(editingElement.settings, 'tabColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'tabColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.tabColor || '#666666' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.tabColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                             <!-- Active Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Active Color</label>
                                                     <button @click="clearColorField(editingElement.settings, 'activeColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'activeColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.activeColor || '#2271b1' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.activeColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- CONTENT PANEL -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">CONTENT PANEL</label>
                                     <div class="space-y-3">
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Font Size (px)</label>
                                                 <input type="number" min="1" v-model.number="editingElement.settings.contentFontSize"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Padding (px)</label>
                                                 <input type="number" min="0" v-model.number="editingElement.settings.contentPadding"
                                                        class="w-full h-8 px-2 text-[11px] text-center border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <!-- Content Font Family -->
                                         <div>
                                             <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Font Family</label>
                                             <select v-model="editingElement.settings.contentFontFamily"
                                                     @change="loadBuilderFont(editingElement.settings.contentFontFamily)"
                                                     class="w-full border border-slate-200 rounded px-2 h-8 text-[11px] focus:outline-none focus:border-[#0091ea]">
                                                 <option value="inherit">Default</option>
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
                                         <!-- Letter Spacing + Line Height -->
                                         <div class="grid grid-cols-2 gap-2">
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Letter Spacing</label>
                                                 <input type="text" v-model="editingElement.settings.contentLetterSpacing" placeholder="0px"
                                                        class="w-full border border-slate-200 rounded-md px-2 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                             <div class="flex flex-col gap-1">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase text-center">Line Height</label>
                                                 <input type="number" min="0" step="0.1" v-model.number="editingElement.settings.contentLineHeight" placeholder="1.6"
                                                        class="w-full border border-slate-200 rounded-md px-1 h-8 text-[11px] text-center focus:outline-none focus:border-[#0091ea]">
                                             </div>
                                         </div>
                                         <div class="grid grid-cols-2 gap-3">
                                             <!-- Content Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Text Color</label>
                                                     <button @click="clearColorField(editingElement.settings, 'contentColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'contentColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.contentColor || '#555555' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.contentColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                             <!-- Content Bg Color -->
                                             <div>
                                                 <div class="flex justify-between items-center mb-1">
                                                     <label class="text-[9px] font-bold text-slate-400 uppercase">Bg Color</label>
                                                     <button @click="clearColorField(editingElement.settings, 'contentBgColor')" class="text-slate-300 hover:text-red-500"><i class="fa fa-undo text-[10px]"></i></button>
                                                 </div>
                                                 <div class="flex gap-1.5 items-center">
                                                     <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer flex-shrink-0"
                                                          @click="openColorPicker($event, editingElement.settings, 'contentBgColor')">
                                                         <div :style="{ backgroundColor: editingElement.settings.contentBgColor || '#ffffff' }" class="w-full h-full rounded-full"></div>
                                                     </div>
                                                     <input type="text" v-model="editingElement.settings.contentBgColor" class="w-full border border-slate-200 rounded px-2 py-1 text-[10px]">
                                                 </div>
                                             </div>
                                         </div>
                                         <!-- Border Color -->
                                         <div>
                                             <div class="flex justify-between items-center mb-1.5">
                                                 <label class="text-[9px] font-bold text-slate-400 uppercase">Border Color</label>
                                                 <button @click="clearColorField(editingElement.settings, 'borderColor')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                                             </div>
                                             <div class="flex gap-2 items-center">
                                                 <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                                                      @click="openColorPicker($event, editingElement.settings, 'borderColor')">
                                                     <div :style="{ backgroundColor: editingElement.settings.borderColor || '#e2e8f0' }" class="w-full h-full rounded-full"></div>
                                                 </div>
                                                 <input type="text" v-model="editingElement.settings.borderColor" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">
                                             </div>
                                         </div>
                                         <!-- Border Radius -->
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase">Border Radius (px)</label>
                                             <input type="number" min="0" v-model.number="editingElement.settings.borderRadius"
                                                    class="w-full h-8 px-2 text-[11px] border border-slate-200 rounded-md focus:outline-none focus:border-[#0091ea]">
                                         </div>
                                     </div>
                                 </div>

                                 <!-- SPACING -->
                                 <div class="pt-4 border-t border-slate-50">
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">MARGIN</label>
                                     <div class="grid grid-cols-2 gap-2">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginTop"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginTopUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginBottom"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginBottomUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                             </div>

                             <!-- Design Settings for Video -->
                             <div v-else-if="editingElement?.type === 'video'" class="space-y-6 pb-10">

                                 <!-- SPACING -->
                                 <div>
                                     <label class="text-[12px] font-bold text-[#333] uppercase mb-3 block">Margin</label>
                                     <div class="grid grid-cols-2 gap-2">
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginTop"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginTopUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div class="flex flex-col gap-1">
                                             <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                                             <div class="flex border border-slate-200 rounded-md overflow-hidden">
                                                 <input type="number" v-model.number="editingElement.settings.marginBottom"
                                                        class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                                                 <select v-model="editingElement.settings.marginBottomUnit"
                                                         class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                                                     <option value="px">px</option><option value="rem">rem</option><option value="%">%</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                             </div>

                             <!-- Design Settings for Post Content -->
                             <div v-else-if="editingElement?.type === 'post_content'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.post-content-design')
                             </div>
                             <!-- Design Settings for Post Meta -->
                             <div v-else-if="editingElement?.type === 'post_meta'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.post-meta-design')
                             </div>
                             <!-- Custom Elements: design tab fields -->
                             @foreach($customElements ?? [] as $type => $custEl)
                             @if($type === 'text_block') @continue @endif
                             @php
                                 $__allFields2    = lazy_normalize_custom_fields($custEl);
                                 $__designFields2 = array_filter($__allFields2, fn($f) => isset($f['tab']) && $f['tab'] === 'design');
                             @endphp
                             <div v-else-if="editingElement?.type === '{{ $type }}'" class="space-y-6">
                                 @if(!empty($__designFields2))
                                     @foreach($__designFields2 as $fieldKey => $field)
                                         @if(!empty($field['condition']))
                                             <template v-if="customFieldVisible(editingElement.settings, {{ Illuminate\Support\Js::from($field['condition']) }})">
                                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.custom-field-renderer', compact('field', 'fieldKey'))
                                             </template>
                                         @else
                                             @include('falcon-cms::admin.falcon-builder.partials.components.elements.custom-field-renderer', compact('field', 'fieldKey'))
                                         @endif
                                     @endforeach
                                 @else
                                     <div class="p-4 bg-slate-50 rounded border border-dashed border-slate-200 text-center">
                                         <i class="{{ $custEl['icon'] ?? 'fa fa-cube' }} text-slate-300 text-xl mb-2 block"></i>
                                         <p class="text-[11px] text-slate-400">No design fields defined for this element.</p>
                                     </div>
                                 @endif

                                 <!-- CSS Class & ID (standard — applies to every custom element; hidden for advanced_search) -->
                                 <div v-if="editingElement.type !== 'advanced_search'" class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
                                     <div>
                                         <div class="flex justify-between items-center mb-3">
                                             <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
                                         </div>
                                         <input type="text" v-model="editingElement.settings.cssClass"
                                                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                     </div>
                                     <div>
                                         <div class="flex justify-between items-center mb-3">
                                             <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
                                         </div>
                                         <input type="text" v-model="editingElement.settings.cssId"
                                                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                     </div>
                                 </div>
                             </div>
                             @endforeach
                        </div>

                        <!-- ══ SUB MENU TAB ══ -->
                        <div v-if="editingContext.tab === 'submenu'" class="p-5 space-y-6">
                             <div v-if="editingElement?.type === 'menu'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.menu-submenu')
                             </div>
                        </div>

                        <!-- ══ MOBILE TAB ══ -->
                        <div v-if="editingContext.tab === 'mobile'" class="p-5 space-y-6">
                             <div v-if="editingElement?.type === 'menu'" class="space-y-6">
                                 @include('falcon-cms::admin.falcon-builder.partials.components.elements.menu-mobile')
                             </div>
                        </div>

                        <!-- ══ EXTRAS TAB (shared partial) ══ -->
                        <div v-if="editingContext.tab === 'extras' && editingElement" class="p-5">
                            @include('falcon-cms::admin.falcon-builder.partials.components.fields.extra-options')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Dynamic Source Picker (database-icon / blue-pill system) ══ -->
    <template v-if="dynSrcMenu.open">
        <div class="fixed inset-0 z-[9998]" @click.stop="dynSrcMenu.open = false"></div>

        <!-- ── Config panel: sub-fields for selected source ── -->
        <div v-if="dynSrcMenu.showConfig"
             class="fixed z-[9999] bg-white border border-slate-200 rounded-xl shadow-2xl"
             style="width:270px"
             :style="{ top: dynSrcMenu.y + 'px', left: dynSrcMenu.x + 'px' }"
             @click.stop>
            <div class="px-2.5 py-2.5 border-b border-slate-100 flex items-center gap-2">
                <button @click="dynSrcMenu.showConfig = false"
                        class="w-6 h-6 flex items-center justify-center text-slate-400 hover:text-slate-700 rounded-md hover:bg-slate-100 flex-shrink-0 transition-all">
                    <i class="fa fa-arrow-left text-[10px]"></i>
                </button>
                <div class="flex items-center gap-1.5 flex-1 min-w-0">
                    <i :class="['fa', getDynSrcDef(dynSrcMenu.configKey).icon, 'text-[10px] text-[#0091ea] flex-shrink-0']"></i>
                    <span class="text-[12px] font-bold text-slate-700 truncate">@{{ getDynSrcDef(dynSrcMenu.configKey).label }}</span>
                </div>
                <button @click="dynSrcMenu.open = false" class="text-slate-300 hover:text-slate-500 w-5 h-5 flex items-center justify-center flex-shrink-0">
                    <i class="fa fa-times text-[9px]"></i>
                </button>
            </div>
            <div class="p-3 space-y-3 overflow-y-auto" style="max-height:320px">
                <template v-for="field in getDynSrcDef(dynSrcMenu.configKey).subFields" :key="field.key">
                    <div>
                        <label class="text-[10px] font-semibold text-slate-500 block mb-1 uppercase tracking-wide">@{{ field.label }}</label>
                        <select v-if="field.type === 'select'"
                                v-model="dynSrcMenu.settings[field.key]"
                                class="w-full text-[12px] border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white focus:outline-none focus:border-[#0091ea] focus:ring-1 focus:ring-[#0091ea]/20">
                            <option v-for="opt in field.options" :key="opt.value" :value="opt.value">@{{ opt.label }}</option>
                        </select>
                        <input v-else-if="field.type === 'number'"
                               type="number"
                               v-model="dynSrcMenu.settings[field.key]"
                               :placeholder="field.placeholder || ''"
                               class="w-full text-[12px] border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#0091ea] focus:ring-1 focus:ring-[#0091ea]/20">
                        <input v-else
                               type="text"
                               v-model="dynSrcMenu.settings[field.key]"
                               :placeholder="field.placeholder || ''"
                               class="w-full text-[12px] border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#0091ea] focus:ring-1 focus:ring-[#0091ea]/20">
                        <p v-if="field.required && !dynSrcMenu.settings[field.key]"
                           class="text-[10px] text-amber-500 mt-0.5">Required</p>
                    </div>
                </template>
            </div>
            <div class="border-t border-slate-100 p-2">
                <button @click="clearDynSource()"
                        class="w-full flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium text-red-400 hover:bg-red-50 rounded-lg transition-all">
                    <i class="fa fa-times-circle text-[10px]"></i> Remove Dynamic
                </button>
            </div>
        </div>

        <!-- ── List panel: all options grouped ── -->
        <div v-else
             class="fixed z-[9999] bg-white border border-slate-200 rounded-xl shadow-2xl"
             style="width:252px"
             :style="{ top: dynSrcMenu.y + 'px', left: dynSrcMenu.x + 'px' }"
             @click.stop>
            <div class="px-3 py-2.5 border-b border-slate-100 flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Dynamic Source</span>
                <button @click="dynSrcMenu.open = false" class="text-slate-300 hover:text-slate-500 w-5 h-5 flex items-center justify-center">
                    <i class="fa fa-times text-[9px]"></i>
                </button>
            </div>
            <div class="py-1 overflow-y-auto" style="max-height:330px">
                <template v-for="group in getDynSrcGroups(dynSrcMenu.ctx)" :key="group.name">
                    <div class="px-3 pt-2.5 pb-0.5 text-[9px] font-bold text-slate-400 uppercase tracking-wider">@{{ group.name }}</div>
                    <button v-for="opt in group.items" :key="opt.key"
                            @click="selectDynSource(opt.key)"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left transition-all"
                            :class="dynSrcMenu.settings?.[dynSrcMenu.sourceKey] === opt.key ? 'bg-[#2271b1]/10 text-[#0091ea]' : 'text-slate-600 hover:bg-slate-50'">
                        <i :class="['fa', opt.icon, 'text-[10px] w-3.5 flex-shrink-0']"></i>
                        <span class="text-[12px] font-medium flex-1">@{{ opt.label }}</span>
                        <i v-if="opt.subFields && opt.subFields.length" class="fa fa-chevron-right text-[9px] text-slate-300 flex-shrink-0"></i>
                        <i v-else-if="dynSrcMenu.settings?.[dynSrcMenu.sourceKey] === opt.key" class="fa fa-check text-[9px] text-[#0091ea] flex-shrink-0"></i>
                    </button>
                </template>
            </div>
            <div v-if="dynSrcMenu.settings?.[dynSrcMenu.sourceKey]" class="border-t border-slate-100 p-1.5">
                <button @click="clearDynSource()"
                        class="w-full flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium text-red-400 hover:bg-red-50 rounded-lg transition-all">
                    <i class="fa fa-times-circle text-[10px]"></i> Remove Dynamic
                </button>
            </div>
        </div>
    </template>
</aside>

