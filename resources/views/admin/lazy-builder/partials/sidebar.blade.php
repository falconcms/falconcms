<aside class="builder-sidebar flex flex-col" v-if="!isPreview">
    <!-- Mini Tab Icons at Top (WP/Avada Style) -->
    <div class="flex border-b border-slate-100 bg-slate-50/50">
        <button @click="activeTab='settings'" :class="activeTab==='settings' ? 'bg-white border-b-2 border-[#0091ea] text-[#0091ea]' : 'text-slate-400'" class="w-12 h-12 flex items-center justify-center transition-all">
            <i class="fa fa-cog text-sm"></i>
        </button>
        <button @click="activeTab='navigator'" :class="activeTab==='navigator' ? 'bg-white border-b-2 border-[#0091ea] text-[#0091ea]' : 'text-slate-400'" class="flex-1 flex items-center justify-center gap-2 transition-all group">
             <i class="fa fa-caret-down text-[10px] text-slate-400 group-hover:text-[#0091ea]"></i>
             <span class="text-[11px] font-black uppercase tracking-widest text-slate-500 group-hover:text-[#0091ea]">Navigator</span>
        </button>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-y-auto custom-scrollbar bg-white">
        <!-- Elements Tab (Removed) -->

        <!-- Navigator Tab -->
        <div v-show="activeTab==='navigator'" class="animate-fade-in py-2">
            <div v-if="layout.length === 0" class="flex flex-col items-center justify-center py-20 px-10 text-center bg-slate-50/30">
                <div class="w-14 h-14 bg-[#0091ea] rounded-lg shadow-xl shadow-blue-500/20 flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <h3 class="text-[12px] font-black text-slate-800 uppercase tracking-widest mb-2">Navigator</h3>
                <p class="text-[11px] text-slate-400 leading-relaxed font-medium">No content has been added yet.</p>
            </div>
            
            <div v-else class="space-y-0.5">
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
                    <div v-for="(col, coli) in cont.columns" :key="col.id" class="{{ ($postCardMode ?? false) ? '' : 'ml-6 border-l border-slate-100' }}">
                        <div class="flex items-center gap-2 px-4 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                             :class="[editingContext.type === 'column' && editingContext.ci === ci && editingContext.coli === coli ? 'bg-slate-50 border-l-2 border-[#0091ea] -ml-[1px]' : '', navDragOver?.type === 'column' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragSrc?.coli !== coli ? 'border-t-2 border-[#0091ea]' : '']"
                             draggable="true"
                             @dragstart.stop="navDragStart($event, 'column', ci, coli)"
                             @dragover.prevent="navDragOverHandler($event, 'column', ci, coli)"
                             @drop="navDrop($event, 'column', ci, coli)"
                             @dragend="navDragEnd()"
                             @click="setEditingContext('column', ci, coli)">
                            <i class="fa fa-caret-down text-[10px] text-slate-300"></i>
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
                        <div v-for="(el, eli) in col.elements" :key="el.id" class="ml-6 border-l border-slate-50">
                            <!-- Standard Element -->
                            <div v-if="el.type !== 'row'"
                                 class="flex items-center gap-3 px-4 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                 :class="navDragOver?.type === 'element' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragSrc?.eli !== eli ? 'border-t-2 border-[#0091ea]' : ''"
                                 draggable="true"
                                 @dragstart.stop="navDragStart($event, 'element', ci, coli, eli)"
                                 @dragover.prevent="navDragOverHandler($event, 'element', ci, coli, eli)"
                                 @drop="navDrop($event, 'element', ci, coli, eli)"
                                 @dragend="navDragEnd()"
                                 @click="setEditingContext('element', ci, coli, eli)">
                                <i :class="el.icon" class="text-[11px] text-slate-400 w-4 text-center"></i>
                                <span class="text-[14px] text-slate-500 flex-1 capitalize">@{{ (el.type === 'text_block' || el.type === 'special_text') ? 'Text Block' : el.type.replace(/_/g, ' ') }}</span>
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
                                <div class="flex items-center gap-2 px-4 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
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
                                <div v-for="(ncol, ncoli) in el.columns" :key="ncol.id" class="ml-6 border-l border-slate-100">
                                    <div class="flex items-center gap-2 px-4 py-1.5 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                         :class="navDragOver?.type === 'nested-column' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragOver?.ncoli === ncoli && navDragSrc?.ncoli !== ncoli ? 'border-t-2 border-[#0091ea]' : ''"
                                         draggable="true"
                                         @dragstart.stop="navDragStart($event, 'nested-column', ci, coli, eli, ncoli)"
                                         @dragover.prevent="navDragOverHandler($event, 'nested-column', ci, coli, eli, ncoli)"
                                         @drop="navDrop($event, 'nested-column', ci, coli, eli, ncoli)"
                                         @dragend="navDragEnd()"
                                         @click="setEditingContext('nested-column', ci, coli, eli, ncoli)">
                                        <i class="fa fa-caret-down text-[10px] text-slate-300"></i>
                                        <span class="text-[14px] font-bold text-slate-500 flex-1">Nested Column</span>
                                        <div class="flex items-center gap-2 opacity-0 group-hover/line:opacity-100 transition-opacity">
                                            <i @click.stop @mousedown.stop class="fa fa-grip-vertical text-[9px] text-slate-300 hover:text-slate-500 cursor-grab" title="Drag to reorder"></i>
                                            <i @click.stop="openElementModal(ci, coli, 'design', true, eli, ncoli)" class="fa fa-plus text-[9px] text-slate-400 hover:text-[#0091ea]" title="Add Nested Element"></i>
                                            <i @click.stop="setEditingContext('nested-column', ci, coli, eli, ncoli)" class="fa fa-pen text-[9px] text-slate-400 hover:text-[#0091ea]" title="Edit"></i>
                                            <i @click.stop="duplicateNestedColumn(ci, coli, eli, ncoli)" class="fa fa-copy text-[9px] text-slate-400 hover:text-[#0091ea]" title="Duplicate"></i>
                                            <i @click.stop="el.columns.splice(ncoli, 1)" class="fa fa-trash-alt text-[9px] text-slate-400 hover:text-red-500" title="Delete"></i>
                                        </div>
                                    </div>
                                    <!-- Nested Elements -->
                                    <div v-for="(nel, neli) in ncol.elements" :key="nel.id" class="ml-6 border-l border-slate-50">
                                        <div class="flex items-center gap-3 px-4 py-1 hover:bg-slate-50 cursor-pointer group/line transition-all"
                                             :class="navDragOver?.type === 'nested-element' && navDragOver?.ci === ci && navDragOver?.coli === coli && navDragOver?.eli === eli && navDragOver?.ncoli === ncoli && navDragOver?.neli === neli && navDragSrc?.neli !== neli ? 'border-t-2 border-[#0091ea]' : ''"
                                             draggable="true"
                                             @dragstart.stop="navDragStart($event, 'nested-element', ci, coli, eli, ncoli, neli)"
                                             @dragover.prevent="navDragOverHandler($event, 'nested-element', ci, coli, eli, ncoli, neli)"
                                             @drop="navDrop($event, 'nested-element', ci, coli, eli, ncoli, neli)"
                                             @dragend="navDragEnd()"
                                             @click="setEditingContext('element', ci, coli, eli, ncoli, neli)">
                                            <i :class="nel.icon" class="text-[10px] text-slate-400 w-4 text-center"></i>
                                            <span class="text-[14px] text-slate-500 flex-1 capitalize">@{{ (nel.type === 'text_block' || nel.type === 'special_text') ? 'Text Block' : nel.type.replace(/_/g, ' ') }}</span>
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

        <!-- Settings Tab -->
        <div v-show="activeTab==='settings'" class="h-full animate-fade-in flex flex-col">
            <div v-if="editingContext.type === 'container'" :key="'container-' + editingContext.ci" class="h-full">
                @include('cms-dashboard::admin.lazy-builder.partials.components.container.edit-panel')
            </div>
            <div v-else-if="editingContext.type === 'nested-row'" :key="'nested-row-' + editingContext.ci + '-' + editingContext.coli + '-' + editingContext.eli" class="h-full">
                @include('cms-dashboard::admin.lazy-builder.partials.components.container.edit-panel', ['isNestedRow' => true])
            </div>
            <div v-else-if="editingContext.type === 'column' || editingContext.type === 'nested-column'" :key="'column-' + editingContext.ci + '-' + editingContext.coli + '-' + editingContext.eli + '-' + editingContext.ncoli" class="h-full">
                @include('cms-dashboard::admin.lazy-builder.partials.components.column.edit-panel')
            </div>
            <div v-else-if="editingContext.type === 'element'" :key="'element-' + editingContext.ci + '-' + editingContext.coli + '-' + editingContext.eli + '-' + editingContext.neli" class="h-full">
                <!-- Dynamic Element Settings Panel -->
                <div class="flex flex-col h-full bg-white">
                    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[#0091ea] rounded flex items-center justify-center text-white shadow-sm">
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

                    <div class="flex border-b border-white/10 bg-[#0091ea] overflow-hidden">
                        <button @click="editingContext.tab = 'content'" 
                                :class="(editingContext.tab || 'content') === 'content' ? 'bg-[#007cc0] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'" 
                                class="builder-tab-btn py-3 text-[10px] font-black uppercase tracking-widest border-r border-white/10">
                            <span v-if="(editingContext.tab || 'content') === 'content'">General</span>
                            <i v-else class="fa fa-cog text-xs"></i>
                        </button>
                        
                        <button @click="editingContext.tab = 'design'" 
                                :class="editingContext.tab === 'design' ? 'bg-[#007cc0] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'" 
                                class="builder-tab-btn py-3 border-r border-white/10 text-[10px] font-black uppercase tracking-widest transition-all">
                            <template v-if="editingContext.tab === 'design'">
                                <span>@{{ editingElement?.type === 'menu' ? 'Main Menu' : 'Design' }}</span>
                            </template>
                            <template v-else>
                                <i :class="editingElement?.type === 'menu' ? 'fa fa-bars' : 'fa fa-pen'" class="text-xs"></i>
                            </template>
                        </button>

                        <button v-if="editingElement?.type === 'menu'"
                                @click="editingContext.tab = 'submenu'" 
                                :class="editingContext.tab === 'submenu' ? 'bg-[#007cc0] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'" 
                                class="builder-tab-btn py-3 border-r border-white/10 text-[10px] font-black uppercase tracking-widest transition-all">
                            <span v-if="editingContext.tab === 'submenu'">Sub Menu</span>
                            <i v-else class="fa fa-indent text-xs"></i>
                        </button>

                        <button v-if="editingElement?.type === 'menu'"
                                @click="editingContext.tab = 'mobile'"
                                :class="editingContext.tab === 'mobile' ? 'bg-[#007cc0] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'" 
                                class="builder-tab-btn py-3 border-r border-white/10 text-[10px] font-black uppercase tracking-widest transition-all">
                            <span v-if="editingContext.tab === 'mobile'">Mobile</span>
                            <i v-else class="fa fa-mobile-screen text-xs"></i>
                        </button>

                        <button @click="editingContext.tab = 'extras'" 
                                :class="editingContext.tab === 'extras' ? 'bg-[#007cc0] text-white flex-grow-[2]' : 'text-white/70 hover:text-white flex-grow-[1]'" 
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
                                    </div>
                                    <div class="builder-rich-editor-wrapper border border-slate-200 rounded overflow-hidden focus-within:border-[#0091ea] transition-all">
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
                                    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'left')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'left' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Left</button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'center')"
                                                :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) === 'center' || !getResponsiveVal(editingElement.settings, 'textAlign', device)) ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Center</button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'right')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'right' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">Right</button>
                                    </div>
                                </div>

                                <!-- Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
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
                                        <button @click="editingElement.settings.dynamic_source = (editingElement.settings.dynamic_source === 'post_title' ? '' : 'post_title')"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source === 'post_title' ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.dynamic_source === 'post_title'"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#0091ea]/8 border border-[#0091ea]/25 rounded-lg">
                                        <div class="flex items-center gap-2">
                                            <i class="fa fa-heading text-[#0091ea] text-sm"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">Post Title</span>
                                        </div>
                                        <button @click="editingElement.settings.dynamic_source = ''"
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
                                                :class="editingElement.settings.useLink ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">On</button>
                                        <button @click="editingElement.settings.useLink = false"
                                                :class="!editingElement.settings.useLink ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Off</button>
                                    </div>
                                </div>

                                <!-- Link URL Field -->
                                <div v-if="editingElement.settings.useLink">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link URL</label>
                                        <button @click="editingElement.settings.link_dynamic_source = (editingElement.settings.link_dynamic_source === 'post_url' ? '' : 'post_url')"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.link_dynamic_source === 'post_url' ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.link_dynamic_source === 'post_url'"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#0091ea]/8 border border-[#0091ea]/25 rounded-lg">
                                        <div class="flex items-center gap-2">
                                            <i class="fa fa-link text-[#0091ea] text-sm"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">Post URL</span>
                                        </div>
                                        <button @click="editingElement.settings.link_dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-else class="flex">
                                        <input type="text" v-model="editingElement.settings.linkUrl"
                                               placeholder="Select Link"
                                               class="flex-1 border border-slate-200 border-r-0 rounded-l px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                        <button class="bg-white border border-slate-200 rounded-r px-3 text-slate-400 hover:text-[#0091ea] transition-colors">
                                            <i class="fa fa-link text-[12px]"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.useLink && (editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source === 'post_url')">
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
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
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
                                        <button @click="editingElement.settings.dynamic_source = (editingElement.settings.dynamic_source === 'post_title' ? '' : 'post_title')"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source === 'post_title' ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.dynamic_source === 'post_title'"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#0091ea]/8 border border-[#0091ea]/25 rounded-lg">
                                        <div class="flex items-center gap-2">
                                            <i class="fa fa-heading text-[#0091ea] text-sm"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">Post Title</span>
                                        </div>
                                        <button @click="editingElement.settings.dynamic_source = ''"
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
                                        <button @click="editingElement.settings.link_dynamic_source = (editingElement.settings.link_dynamic_source === 'post_url' ? '' : 'post_url')"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.link_dynamic_source === 'post_url' ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.link_dynamic_source === 'post_url'"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#0091ea]/8 border border-[#0091ea]/25 rounded-lg">
                                        <div class="flex items-center gap-2">
                                            <i class="fa fa-link text-[#0091ea] text-sm"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">Post URL</span>
                                        </div>
                                        <button @click="editingElement.settings.link_dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-else class="flex">
                                        <input type="text" v-model="editingElement.settings.linkUrl"
                                               placeholder="https://"
                                               class="flex-1 border border-slate-200 border-r-0 rounded-l px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                        <button class="bg-white border border-slate-200 rounded-r px-3 text-slate-400 hover:text-[#0091ea] transition-colors">
                                            <i class="fa fa-link text-[12px]"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source === 'post_url'">
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
                                    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'left')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'left' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-left text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'center')"
                                                :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) === 'center' || !getResponsiveVal(editingElement.settings, 'textAlign', device)) ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-center text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'right')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'right' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
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
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
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
                                        <button @click="editingElement.settings.dynamic_source = (editingElement.settings.dynamic_source === 'feature_image' ? '' : 'feature_image')"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.dynamic_source === 'feature_image' ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <!-- Feature Image active state -->
                                    <div v-if="editingElement.settings.dynamic_source === 'feature_image'"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#0091ea]/8 border border-[#0091ea]/25 rounded-lg">
                                        <div class="flex items-center gap-2">
                                            <i class="fa fa-image text-[#0091ea] text-sm"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">Feature Image</span>
                                        </div>
                                        <button @click="editingElement.settings.dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <!-- Normal image picker -->
                                    <div v-else>
                                        <div v-if="!editingElement.settings.url"
                                             @click="openMediaModal('url')"
                                             class="w-full aspect-[16/10] border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-[#0091ea] hover:bg-blue-50/30 transition-all group">
                                            <div class="w-10 h-10 bg-[#0091ea] rounded-full flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform">
                                                <i class="fa fa-plus"></i>
                                            </div>
                                        </div>
                                        <div v-else class="space-y-3">
                                            <div class="relative group aspect-[16/10] bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
                                                <img :src="editingElement.settings.url" class="w-full h-full object-cover">
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <button @click="openMediaModal('url')" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-[#0091ea] hover:bg-[#0091ea] hover:text-white transition-all shadow-sm">
                                                        <i class="fa fa-edit text-xs"></i>
                                                    </button>
                                                    <button @click="editingElement.settings.url = ''" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-red-500 hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                                        <i class="fa fa-trash text-xs"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="editingElement.settings.url = ''" class="flex-1 h-9 flex items-center justify-center border border-slate-200 rounded text-[11px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Remove</button>
                                                <button @click="openMediaModal('url')" class="flex-1 h-9 flex items-center justify-center bg-[#0091ea] text-white rounded text-[11px] font-bold hover:bg-[#007cc0] transition-colors">Change</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Alt Text -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Alt Text</label>
                                    </div>
                                    <input type="text" v-model="editingElement.settings.alt"
                                           placeholder="Image description..."
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>

                                <!-- Link URL -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Link URL</label>
                                        <button @click="editingElement.settings.link_dynamic_source = (editingElement.settings.link_dynamic_source === 'post_url' ? '' : 'post_url')"
                                                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                                                :class="editingElement.settings.link_dynamic_source === 'post_url' ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'">
                                            <i class="fa fa-database text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-if="editingElement.settings.link_dynamic_source === 'post_url'"
                                         class="flex items-center justify-between px-3 py-2.5 bg-[#0091ea]/8 border border-[#0091ea]/25 rounded-lg">
                                        <div class="flex items-center gap-2">
                                            <i class="fa fa-link text-[#0091ea] text-sm"></i>
                                            <span class="text-[12px] font-bold text-[#0091ea]">Post URL</span>
                                        </div>
                                        <button @click="editingElement.settings.link_dynamic_source = ''"
                                                class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                                            <i class="fa fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div v-else class="flex">
                                        <input type="text" v-model="editingElement.settings.linkUrl"
                                               placeholder="https://"
                                               class="flex-1 border border-slate-200 border-r-0 rounded-l px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
                                        <button class="bg-white border border-slate-200 rounded-r px-3 text-slate-400 hover:text-[#0091ea] transition-colors">
                                            <i class="fa fa-link text-[12px]"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Link Target -->
                                <div v-if="editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source === 'post_url'">
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
                                    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'left')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'left' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-left text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'center')"
                                                :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) === 'center' || !getResponsiveVal(editingElement.settings, 'textAlign', device)) ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
                                                class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-200 transition-all">
                                            <i class="fa fa-align-center text-sm"></i>
                                        </button>
                                        <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, 'right')"
                                                :class="getResponsiveVal(editingElement.settings, 'textAlign', device) === 'right' ? 'bg-[#0091ea] text-white' : 'text-slate-400'"
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
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
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
                                        No post cards saved yet. <a href="{{ route('admin.lazy-builder.library') }}?tab=post_cards" target="_blank" class="underline">Create one →</a>
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
                                                :class="!editingElement.settings.hide_out_of_stock ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                class="px-5 py-1.5 text-[11px] font-black uppercase rounded transition-all">Show</button>
                                        <button @click="editingElement.settings.hide_out_of_stock = true"
                                                :class="editingElement.settings.hide_out_of_stock ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
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
                                                :class="editingElement.settings.order === 'asc' ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">ASC</button>
                                        <button @click="editingElement.settings.order = 'desc'"
                                                :class="(editingElement.settings.order === 'desc' || !editingElement.settings.order) ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
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
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
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
                                                :class="(editingElement.settings.content_display === 'excerpt' || !editingElement.settings.content_display) ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                class="flex-1 py-1.5 text-[11px] font-black uppercase rounded transition-all">Excerpt</button>
                                        <button @click="editingElement.settings.content_display = 'full'"
                                                :class="editingElement.settings.content_display === 'full' ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
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
                                                    :class="editingElement.settings.stripHtml !== false ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                    class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Yes</button>
                                            <button @click="editingElement.settings.stripHtml = false"
                                                    :class="editingElement.settings.stripHtml === false ? 'bg-[#0091ea] text-white shadow-md' : 'bg-[#0091ea]/20 text-[#0091ea]'"
                                                    class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">No</button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Element Visibility -->
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
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

                            <!-- ══ CUSTOM REGISTERED ELEMENTS ══ -->
                            @foreach($customElements ?? [] as $type => $custEl)
                            @if($type === 'text_block' || $type === 'button' || $type === 'image') @continue @endif
                            <div v-else-if="editingElement?.type === '{{ $type }}'" class="space-y-8">
                                @if($type === 'menu')
                                    @include('cms-dashboard::admin.lazy-builder.partials.components.elements.menu-content')
                                @endif

                                @if($type !== 'menu')
                                    @foreach($custEl['fields'] ?? [] as $fieldKey => $field)
                                    @if(isset($field['tab']) && $field['tab'] === 'design') @continue @endif
                                @if(($field['type'] ?? 'text') === 'text')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <input type="text" v-model="editingElement.settings.{{ $fieldKey }}"
                                           placeholder="{{ $field['placeholder'] ?? '' }}"
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>
                                @elseif($field['type'] === 'textarea')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <textarea v-model="editingElement.settings.{{ $fieldKey }}"
                                              rows="{{ $field['rows'] ?? 4 }}"
                                              placeholder="{{ $field['placeholder'] ?? '' }}"
                                              class="w-full border border-slate-200 rounded p-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea] focus:ring-1 focus:ring-[#0091ea]/10 transition-all"></textarea>
                                </div>
                                @elseif($field['type'] === 'number')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <input type="number" v-model.number="editingElement.settings.{{ $fieldKey }}"
                                           @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                                           @if(isset($field['max'])) max="{{ $field['max'] }}" @endif
                                           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                </div>
                                @elseif($field['type'] === 'select')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <select v-model="editingElement.settings.{{ $fieldKey }}"
                                            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        @foreach($field['options'] ?? [] as $optVal => $optLabel)
                                            <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @elseif($field['type'] === 'toggle')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                                        <button @click="editingElement.settings.{{ $fieldKey }} = true"
                                                :class="editingElement.settings.{{ $fieldKey }} ? 'bg-[#0091ea] text-white shadow-md' : 'text-slate-400'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">On</button>
                                        <button @click="editingElement.settings.{{ $fieldKey }} = false"
                                                :class="!editingElement.settings.{{ $fieldKey }} ? 'bg-white text-slate-600 shadow-sm' : 'text-slate-400'"
                                                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Off</button>
                                    </div>
                                </div>
                                 @elseif($field['type'] === 'media')
                                 <div class="mb-6">
                                     <div class="flex justify-between items-center mb-3">
                                         <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                     </div>

                                     <!-- Empty State -->
                                     <div v-if="!editingElement.settings.{{ $fieldKey }}" 
                                          @click="openMediaModal('{{ $fieldKey }}')"
                                          class="w-full aspect-[16/10] border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-[#0091ea] hover:bg-blue-50/30 transition-all group">
                                         <div class="w-10 h-10 bg-[#0091ea] rounded-full flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform">
                                             <i class="fa fa-plus"></i>
                                         </div>
                                     </div>

                                     <!-- Selected State -->
                                     <div v-else class="space-y-3">
                                         <div class="relative group aspect-[16/10] bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
                                             <img :src="editingElement.settings.{{ $fieldKey }}" class="w-full h-full object-cover">
                                             <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                 <button @click="openMediaModal('{{ $fieldKey }}')" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-[#0091ea] hover:bg-[#0091ea] hover:text-white transition-all shadow-sm">
                                                     <i class="fa fa-edit text-xs"></i>
                                                 </button>
                                                 <button @click="editingElement.settings.{{ $fieldKey }} = ''" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-red-500 hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                                     <i class="fa fa-trash text-xs"></i>
                                                 </button>
                                             </div>
                                         </div>
                                         <div class="flex gap-2">
                                             <button @click="editingElement.settings.{{ $fieldKey }} = ''" class="flex-1 h-9 flex items-center justify-center border border-slate-200 rounded text-[11px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Remove</button>
                                             <button @click="openMediaModal('{{ $fieldKey }}')" class="flex-1 h-9 flex items-center justify-center bg-[#0091ea] text-white rounded text-[11px] font-bold hover:bg-[#007cc0] transition-colors">Edit</button>
                                         </div>
                                     </div>
                                 </div>
                                @elseif($field['type'] === 'color')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <div class="flex gap-2 items-center">
                                        <input type="color" v-model="editingElement.settings.{{ $fieldKey }}"
                                               class="w-10 h-10 border border-slate-200 rounded cursor-pointer p-0.5 shrink-0">
                                        <input type="text" v-model="editingElement.settings.{{ $fieldKey }}"
                                               placeholder="#000000"
                                               class="flex-1 border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                    </div>
                                </div>
                                @elseif($field['type'] === 'image')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <div class="space-y-2">
                                        <div v-if="editingElement.settings.{{ $fieldKey }}" class="border border-slate-100 rounded overflow-hidden">
                                            <img :src="editingElement.settings.{{ $fieldKey }}" class="w-full h-24 object-cover">
                                        </div>
                                        <input type="text" v-model="editingElement.settings.{{ $fieldKey }}"
                                               placeholder="Image URL..."
                                               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                                        <button @click="openMediaModal('{{ $fieldKey }}')"
                                                class="w-full py-2 bg-slate-100 text-slate-600 text-[11px] font-bold rounded hover:bg-[#0091ea] hover:text-white transition-all">
                                            Browse Media Library
                                        </button>
                                    </div>
                                </div>
                                @elseif($field['type'] === 'wysiwyg')
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">{{ $field['label'] ?? $fieldKey }}</label>
                                    </div>
                                    <div class="builder-rich-editor-wrapper">
                                        <textarea :id="'rich-editor-' + editingElement.id + '-' + '{{ $fieldKey }}'" 
                                                  class="builder-rich-editor w-full border border-slate-200 rounded p-3 text-[13px]"
                                                  v-model="editingElement.settings.{{ $fieldKey }}"></textarea>
                                    </div>
                                </div>
                                @endif
                                @endforeach
                                @endif

                                <!-- Default: Element Visibility -->
                                <div v-if="editingElement?.type !== 'menu'" class="pt-4 border-t border-slate-50">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[12px] font-bold text-[#333]">Element Visibility</label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1">
                                        <button @click="editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                                                :class="editingElement.settings.visibility.mobile ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-mobile-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                                                :class="editingElement.settings.visibility.tablet ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-tablet-alt text-sm"></i>
                                        </button>
                                        <button @click="editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                                                :class="editingElement.settings.visibility.desktop ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                                class="py-3 rounded transition-all flex items-center justify-center">
                                            <i class="fa fa-desktop text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Default: CSS Class & ID -->
                                <div v-if="editingElement?.type !== 'menu'" class="grid grid-cols-1 gap-6 pt-4 border-t border-slate-50">
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

                        <!-- ══ DESIGN TAB ══ -->
                        <div v-if="editingContext.tab === 'design'" class="p-5 space-y-6">
                             <!-- Design Settings for Special Text -->
                             <div v-if="editingElement?.type === 'text_block' || editingElement?.type === 'special_text'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.text-block-design')
                             </div>

                             <!-- Design Settings for Title -->
                             <div v-else-if="editingElement?.type === 'title'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.title-design')
                             </div>

                             <!-- Design Settings for Button -->
                             <div v-else-if="editingElement?.type === 'button'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.button-design')
                             </div>

                             <!-- Design Settings for Image -->
                             <div v-else-if="editingElement?.type === 'image'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.image-design')
                             </div>

                             <!-- Design Settings for Menu -->
                             <div v-else-if="editingElement?.type === 'menu'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.menu-design')
                             </div>
                             <!-- Design Settings for Card -->
                             <div v-else-if="editingElement?.type === 'card'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.card-design')
                             </div>
                             <!-- Design Settings for Post Content -->
                             <div v-else-if="editingElement?.type === 'post_content'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.post-content-design')
                             </div>
                             <!-- Custom Elements: empty design tab -->
                             @foreach($customElements ?? [] as $type => $custEl)
                             @if($type === 'text_block') @continue @endif
                             <div v-else-if="editingElement?.type === '{{ $type }}'" class="space-y-6">
                                 <div class="p-4 bg-slate-50 rounded border border-dashed border-slate-200 text-center">
                                     <i class="{{ $custEl['icon'] ?? 'fa fa-cube' }} text-slate-300 text-3xl mb-3 block"></i>
                                     <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest">{{ $custEl['name'] ?? $type }}</p>
                                     <p class="text-[10px] text-slate-400 mt-1">Add design settings via the <code>design_fields</code> key.</p>
                                 </div>
                             </div>
                             @endforeach
                        </div>

                        <!-- ══ SUB MENU TAB ══ -->
                        <div v-if="editingContext.tab === 'submenu'" class="p-5 space-y-6">
                             <div v-if="editingElement?.type === 'menu'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.menu-submenu')
                             </div>
                        </div>

                        <!-- ══ MOBILE TAB ══ -->
                        <div v-if="editingContext.tab === 'mobile'" class="p-5 space-y-6">
                             <div v-if="editingElement?.type === 'menu'" class="space-y-6">
                                 @include('cms-dashboard::admin.lazy-builder.partials.components.elements.menu-mobile')
                             </div>
                        </div>

                        <!-- ══ EXTRAS TAB ══ -->
                        <div v-if="editingContext.tab === 'extras'" class="p-5">
                             <div class="p-4 bg-slate-50 rounded border border-dashed border-slate-200 text-center">
                                 <i class="fa fa-layer-group text-slate-300 text-3xl mb-3 block"></i>
                                 <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest">Advanced Extras</p>
                                 <p class="text-[10px] text-slate-400 mt-1">Animations and advanced controls coming soon.</p>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</aside>
