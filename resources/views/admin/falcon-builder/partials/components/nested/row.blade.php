<div v-if="el.type === 'row'" class="nested-row-outer-wrapper w-full basis-full shrink-0 relative py-4 px-4 bg-slate-50/20 border border-slate-100 rounded-lg mb-2 mt-2 group/nrow shadow-sm"
     @contextmenu.prevent.stop="openCtxMenu($event, 'nested-row', ci, coli, eli)"
     @mouseenter="setHover('nested-row', ci, coli, eli)"
     @mouseleave="setHover(null)">
    <!-- Header/Label for the nested row container -->
    <div v-if="!isPreview" class="absolute top-2 left-2 bg-[#ff9800] text-white text-[8px] px-2 py-0.5 rounded shadow-sm z-[10] font-bold uppercase tracking-wider">Row</div>
    
    <!-- Row Toolbar (Horizontal Top-Right, Premium Red) -->
    <div v-if="!isPreview" class="absolute top-0 right-0 transition-all z-[1050] hover:z-[1200] p-1"
         :class="(hoveredType === 'nested-row' && hoveredCi === ci && hoveredEli === eli) ? 'opacity-100' : 'opacity-0'">
        <div class="bg-[#f44336] flex items-center rounded shadow-xl h-7 px-1 pointer-events-auto group/nrbar overflow-hidden hover:overflow-visible max-w-[60px] hover:max-w-[250px] transition-all duration-300 ease-in-out">

            <!-- Always Visible: Edit & Add -->
            <div class="flex items-center flex-shrink-0">
                <!-- Edit Row -->
                <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool" 
                     @click.stop="setEditingContext('nested-row', ci, coli, eli)">
                    <i class="fa fa-pen text-white text-[10px]"></i>
                    <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Edit Row</div>
                </div>
                <!-- Add (Opens Modal) -->
                <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                     @click.stop="openElementModal(ci, coli, 'nested', false, eli)">
                    <i class="fa fa-plus text-white text-[10px]"></i>
                    <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Add Nested</div>
                </div>
            </div>

            <!-- Expandable: Drag, Duplicate, Delete -->
            <div class="flex items-center border-l border-white/20 ml-1 pl-1 opacity-0 group-hover/nrbar:opacity-100 transition-opacity duration-300">
                <!-- Drag Row -->
                <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-move relative group/etool"
                     draggable="true" @dragstart="onDragStart($event, 'element', ci, coli, eli)" @dragend="onDragEnd">
                    <i class="fa fa-arrows-alt text-white text-[10px]"></i>
                    <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Drag Row</div>
                </div>
                <!-- Duplicate Row -->
                <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                     @click.stop="duplicateNestedRow(ci, coli, eli)">
                    <i class="fa fa-copy text-white text-[10px]"></i>
                    <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Duplicate</div>
                </div>
                <!-- Delete Row -->
                <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool text-white hover:text-red-100"
                     @click.stop="column.elements.splice(eli, 1)">
                    <i class="fa fa-trash-alt text-[10px]"></i>
                    <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Delete Row</div>
                </div>
            </div>
        </div>
    </div>

    <div :style="containerInnerStyle(el)" class="w-full relative">
        <div v-for="(ncol, ncoli) in el.columns" 
             class="column-outer relative"
             :class="['ncol-' + ncol.id, (ncol.settings.hoverType && ncol.settings.hoverType !== 'none') ? 'hover-effect-' + ncol.settings.hoverType : '', getVisibilityClasses(ncol.settings)]"
             :style="columnOuterStyle(el, ncol, el.columns.length)">

            <component :is="'style'" v-if="(!ncol.settings.bgType || ncol.settings.bgType === 'color') && getResponsiveVal(ncol.settings, 'bgHoverColor', device)"
                       v-text="'.ncol-' + ncol.id + '>.column-inner{transition:background-color .25s ease}.ncol-' + ncol.id + ':hover>.column-inner{background-color:' + hexToRgba(getResponsiveVal(ncol.settings, 'bgHoverColor', device), getResponsiveVal(ncol.settings, 'bgHoverColorOpacity', device) !== undefined ? getResponsiveVal(ncol.settings, 'bgHoverColorOpacity', device) : 1) + ' !important}'"></component>

            <!-- Nested Column Inner (Handles Background, Padding, Border, Shadow) -->
            <div class="column-inner group/ncol-inner relative"
                 :class="[
                    (!isPreview && activeColi === ncoli && activeColCi === eli) ? 'nested-column-active' : '',
                    isDragging && dragCi === ci && dragColi === coli && dragEli === eli && dragNcoli === ncoli ? 'dragging-no-transition' : '',
                    dragTarget === 'nested-column-' + ci + '-' + coli + '-' + eli + '-' + ncoli + '-null' && dragPosition === 'left' ? 'border-l-4 border-l-blue-500' : '',
                    dragTarget === 'nested-column-' + ci + '-' + coli + '-' + eli + '-' + ncoli + '-null' && dragPosition === 'right' ? 'border-r-4 border-r-blue-500' : '',
                    (dragTarget === 'nested-column-' + ci + '-' + coli + '-' + eli + '-' + ncoli + '-null' && dragSource?.type === 'element') ? 'ring-2 ring-blue-400 ring-inset bg-blue-50/30' : '',
                    ncol.settings.linkUrl ? 'cursor-pointer' : ''
                 ]"
                 :style="columnInnerStyle(ncol, el)"
                 @click.stop="setEditingContext('nested-column', ci, coli, eli, ncoli)"
                 @contextmenu.prevent.stop="openCtxMenu($event, 'nested-column', ci, coli, eli, ncoli)"
                 @mouseenter="setHover('nested-column', ci, coli, eli, ncoli)"
                 @mouseleave="setHover(null)"
                 @dragover="onDragOver($event, 'nested-column', ci, coli, eli, ncoli)"
                 @drop="onDrop($event, 'nested-column', ci, coli, eli, ncoli)">

                <!-- Nested Column Toolbar (Horizontal Top-Left, Premium Orange) -->
                <div class="absolute top-0 left-0 transition-opacity z-[1000] hover:z-[1100] p-1" v-if="!isPreview"
                     :class="(hoveredType === 'nested-column' && hoveredCi === ci && hoveredColi === coli && hoveredEli === eli && hoveredNcoli === ncoli) ? 'opacity-100' : 'opacity-0'">
                    <div class="bg-[#ff9800] flex items-center rounded shadow-xl h-7 px-1 pointer-events-auto group/ncbar overflow-hidden hover:overflow-visible max-w-[60px] hover:max-w-[280px] transition-all duration-300 ease-in-out">

                        <!-- Always Visible: Edit & Add -->
                        <div class="flex items-center flex-shrink-0">
                            <!-- Edit -->
                            <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                                 @click.stop="setEditingContext('nested-column', ci, coli, eli, ncoli)">
                                <i class="fa fa-pen text-white text-[10px]"></i>
                                <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Column Settings</div>
                            </div>
                            <!-- Add Nested / Element -->
                            <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                                 @click.stop="openElementModal(ci, coli, 'nested', false, eli, ncoli, null, ['design', 'nested'])">
                                <i class="fa fa-plus text-white text-[10px]"></i>
                                <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Add Content</div>
                            </div>
                        </div>

                        <!-- Expandable: Drag, Duplicate, Save, Delete -->
                        <div class="flex items-center border-l border-white/20 ml-1 pl-1 opacity-0 group-hover/ncbar:opacity-100 transition-opacity duration-300">
                            <!-- Drag -->
                            <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-move relative group/etool"
                                 draggable="true" @dragstart="onDragStart($event, 'nested-column', ci, coli, eli, ncoli)" @dragend="onDragEnd">
                                <i class="fa fa-arrows-alt text-white text-[10px]"></i>
                                <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Drag Column</div>
                            </div>
                            <!-- Duplicate -->
                            <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                                 @click.stop="duplicateNestedColumn(ci, coli, eli, ncoli)">
                                <i class="fa fa-copy text-white text-[10px]"></i>
                                <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Duplicate</div>
                            </div>
                            <!-- Save -->
                            <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                                 @click.stop="openLibraryModal('nested_columns', ci, coli, eli, ncoli)">
                                <i class="fa fa-hdd text-white text-[10px]"></i>
                                <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Library</div>
                            </div>
                            <!-- Delete -->
                            <div class="w-6 h-6 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool text-white hover:text-red-200"
                                 @click.stop="el.columns.splice(ncoli, 1)">
                                <i class="fa fa-trash-alt text-[10px]"></i>
                                <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Delete</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overlays -->
                <div v-if="!isPreview" class="absolute inset-0 pointer-events-none z-0">
                    <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#9c27b0]/5 transition-opacity"
                         :style="{ height: (ncol.settings.marginTop || 0) + 'px', top: '-' + (ncol.settings.marginTop || 0) + 'px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragNcoli === ncoli && dragType === 'marginTop')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute top-0 left-0 w-full border-t border-dashed border-[#9c27b0]/20"></div>
                    </div>
                    <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#9c27b0]/5 transition-opacity"
                         :style="{ height: (ncol.settings.marginBottom || 0) + 'px', bottom: '-' + (ncol.settings.marginBottom || 0) + 'px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragNcoli === ncoli && dragType === 'marginBottom')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute bottom-0 left-0 w-full border-b border-dashed border-[#9c27b0]/20"></div>
                    </div>
                    <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#ff9800]/5 transition-opacity"
                         :style="{ height: (ncol.settings.paddingTop || 0) + 'px', top: '0px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragType === 'paddingTop' && dragNcoli === ncoli)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute bottom-0 left-0 w-full border-b border-dashed border-[#ff9800]/30"></div>
                    </div>
                    <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#ff9800]/5 transition-opacity"
                         :style="{ height: (ncol.settings.paddingBottom || 0) + 'px', bottom: '0px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragType === 'paddingBottom' && dragNcoli === ncoli)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute top-0 left-0 w-full border-t border-dashed border-[#ff9800]/30"></div>
                    </div>
                    <div class="absolute top-0 bottom-0 pointer-events-none z-0 bg-[#9c27b0]/5 transition-opacity"
                         :style="{ width: '0px', left: '0px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( (activeColi === ncoli && activeColCi === eli) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute top-0 left-0 h-full border-l border-dashed border-[#9c27b0]/20"></div>
                    </div>
                    <div class="absolute top-0 bottom-0 pointer-events-none z-0 bg-[#9c27b0]/5 transition-opacity"
                         :style="{ width: '0px', right: '0px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( (activeColi === ncoli && activeColCi === eli) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute top-0 right-0 h-full border-r border-dashed border-[#9c27b0]/20"></div>
                    </div>
                    <div class="absolute top-0 bottom-0 pointer-events-none z-0 bg-[#ff9800]/5 transition-opacity"
                         :style="{ width: (ncol.settings.paddingLeft || 0) + 'px', left: '0px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragType === 'paddingLeft' && dragNcoli === ncoli)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute top-0 right-0 h-full border-r border-dashed border-[#ff9800]/30"></div>
                    </div>
                    <div class="absolute top-0 bottom-0 pointer-events-none z-0 bg-[#ff9800]/5 transition-opacity"
                         :style="{ width: (ncol.settings.paddingRight || 0) + 'px', right: '0px' }"
                         :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragType === 'paddingRight' && dragNcoli === ncoli)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                         <div class="absolute top-0 left-0 h-full border-l border-dashed border-[#ff9800]/30"></div>
                    </div>
                </div>

                <!-- Nested Column Handles -->
                <div v-if="!isPreview" class="absolute inset-0 pointer-events-none z-[1500] transition-opacity"
                     :class="shouldShowGuide('nested-column', ci, coli, eli, ncoli) ? ( ((activeColi === ncoli && activeColCi === eli) || (isDragging && dragNcoli === ncoli)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                    
                    <div class="absolute top-0.5 left-1/2 -translate-x-1/2 pointer-events-auto flex gap-0.5 items-start">
                        <div class="handle-purple group/nchmt"
                             @mousedown.stop.prevent="startDrag($event, 'marginTop', ci, coli, eli, ncoli)">
                            <i class="fa fa-bars"></i>
                            <div class="falcon-tooltip-v2 !opacity-100 !visible" v-if="isDragging && dragType === 'marginTop' && dragNcoli === ncoli">@{{ ncol.settings.marginTop || 0 }}px</div>
                            <div class="falcon-tooltip-v2 opacity-0 group-hover/nchmt:opacity-100" v-else>@{{ ncol.settings.marginTop || 0 }}px</div>
                        </div>
                        <div class="handle-orange group/nh"
                             :class="isDragging ? '' : 'transition-all'"
                             :style="{ transform: 'translateY(' + (Number(ncol.settings.paddingTop || 0)) + 'px)' }"
                             @mousedown.stop.prevent="startDrag($event, 'paddingTop', ci, coli, eli, ncoli)">
                            <i class="fa fa-bars"></i>
                            <div class="falcon-tooltip-v2 !opacity-100 !visible" v-if="isDragging && dragType === 'paddingTop' && dragNcoli === ncoli">@{{ ncol.settings.paddingTop || 0 }}px</div>
                            <div class="falcon-tooltip-v2 opacity-0 group-hover/nh:opacity-100" v-else>@{{ ncol.settings.paddingTop || 0 }}px</div>
                        </div>
                    </div>

                    <div class="absolute bottom-0.5 left-1/2 -translate-x-1/2 pointer-events-auto flex gap-0.5 items-end">
                        <div class="handle-purple group/nchmb"
                             :class="isDragging ? '' : 'transition-all'"
                             :style="{ transform: 'translateY(' + (ncol.settings.marginBottom || 0) + 'px)' }"
                             @mousedown.stop.prevent="startDrag($event, 'marginBottom', ci, coli, eli, ncoli)">
                            <i class="fa fa-bars"></i>
                            <div class="falcon-tooltip-v2 !opacity-100 !visible" v-if="isDragging && dragType === 'marginBottom' && dragNcoli === ncoli">@{{ ncol.settings.marginBottom || 0 }}px</div>
                            <div class="falcon-tooltip-v2 opacity-0 group-hover/nchmb:opacity-100" v-else>@{{ ncol.settings.marginBottom || 0 }}px</div>
                        </div>
                        <div class="handle-orange group/nh"
                             :class="isDragging ? '' : 'transition-all'"
                             @mousedown.stop.prevent="startDrag($event, 'paddingBottom', ci, coli, eli, ncoli)">
                            <i class="fa fa-bars"></i>
                            <div class="falcon-tooltip-v2 !opacity-100 !visible" v-if="isDragging && dragType === 'paddingBottom' && dragNcoli === ncoli">@{{ ncol.settings.paddingBottom || 0 }}px</div>
                            <div class="falcon-tooltip-v2 opacity-0 group-hover/nh:opacity-100" v-else>@{{ ncol.settings.paddingBottom || 0 }}px</div>
                        </div>
                    </div>

                    <div class="absolute left-0.5 top-1/2 -translate-y-1/2 pointer-events-auto flex flex-col gap-0.5 items-start">
                        <div class="handle-orange-h group/nchl"
                             :class="isDragging ? '' : 'transition-all'"
                             :style="{ transform: 'translateX(' + (Number(ncol.settings.paddingLeft || 0)) + 'px)' }"
                             @mousedown.stop.prevent="startDrag($event, 'paddingLeft', ci, coli, eli, ncoli)">
                            <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
                            <div class="falcon-tooltip-v2 !opacity-100 !visible" v-if="isDragging && dragType === 'paddingLeft' && dragNcoli === ncoli">@{{ ncol.settings.paddingLeft || 0 }}px</div>
                            <div class="falcon-tooltip-v2 opacity-0 group-hover/nchl:opacity-100" v-else>@{{ ncol.settings.paddingLeft || 0 }}px</div>
                        </div>
                    </div>

                    <div class="absolute right-0.5 top-1/2 -translate-y-1/2 pointer-events-auto flex flex-col gap-0.5 items-end">
                        <div class="handle-orange-h group/nchr"
                             :class="isDragging ? '' : 'transition-all'"
                             :style="{ transform: 'translateX(-' + (Number(ncol.settings.paddingRight || 0)) + 'px)' }"
                             @mousedown.stop.prevent="startDrag($event, 'paddingRight', ci, coli, eli, ncoli)">
                            <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
                            <div class="falcon-tooltip-v2 !opacity-100 !visible" v-if="isDragging && dragType === 'paddingRight' && dragNcoli === ncoli">@{{ ncol.settings.paddingRight || 0 }}px</div>
                            <div class="falcon-tooltip-v2 opacity-0 group-hover/nchr:opacity-100" v-else>@{{ ncol.settings.paddingRight || 0 }}px</div>
                        </div>
                    </div>
                </div>

                <div v-if="!isPreview && ncol.elements.length === 0" class="text-center w-full flex flex-col items-center py-10">
                    <button @click.stop="openElementModal(ci, coli, 'design', true, eli, ncoli)" class="w-8 h-8 bg-[#ff9800] text-white rounded shadow-lg flex items-center justify-center hover:scale-110 transition-all relative group/nadd pointer-events-auto">
                        <i class="fa fa-plus text-base pointer-events-none"></i>
                        <div class="falcon-tooltip-v2 !bottom-auto !top-full !mt-2 opacity-0 group-hover/nadd:opacity-100">Add Element</div>
                    </button>
                </div>
                
                 <div v-for="(nestedEl, nestedEli) in ncol.elements" :key="nestedEl.id"
                      class="relative group/nel"
                      @click.stop="setEditingContext('element', ci, coli, eli, ncoli, nestedEli)"
                      @contextmenu.prevent.stop="openCtxMenu($event, 'nested-element', ci, coli, eli, ncoli, nestedEli)"
                     :class="[
                        (ncol.settings.contentLayout === 'row' && nestedEl.type !== 'row') ? '' : 'w-full',
                        dragTarget === 'element-' + ci + '-' + coli + '-' + eli + '-' + ncoli + '-' + nestedEli && dragPosition === 'top' ? 'border-t-2 border-t-blue-500' : '',
                        dragTarget === 'element-' + ci + '-' + coli + '-' + eli + '-' + ncoli + '-' + nestedEli && dragPosition === 'bottom' ? 'border-b-2 border-b-blue-500' : ''
                     ]"
                     @dragover="onDragOver($event, 'element', ci, coli, eli, ncoli, nestedEli)"
                     @drop="onDrop($event, 'element', ci, coli, eli, ncoli, nestedEli)">
                        
                        <!-- All element types rendered via shared partials (mirrors col.blade.php for full live preview) -->
                        <template v-for="el in [nestedEl]" :key="el.id">
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.counter')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.star-rating')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.gallery')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.heading')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.title')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.text')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.image')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.button')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.video')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.spacer')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.html')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.icon-box')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.icon-list')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.accordion')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.tabs')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.text-block')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.menu')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.card')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.post-content')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.post-meta')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.product-meta')
                            @includeIf('falcon-cms::admin.falcon-builder.partials.components.elements.ticker')
                            <!-- Custom Registered Blocks — convention-based live preview (excludes built-in types) -->
                            @php
                            $builtInTypesNested = "['text_block','special_text','text','button','image','menu','title','heading','spacer','html','counter','star_rating','gallery','accordion','icon_box','icon_list','tabs','video','card','post_grid','post_content','post_meta','product_meta','ticker','row']";
                            @endphp
                            <div v-if="customElements[el.type] !== undefined && !{!! $builtInTypesNested !!}.includes(el.type)"
                                 :style="[{ width: '100%' }, getCustomElementRender(el).wrapperStyle, getCanvasVisibilityStyle(el.settings)]"
                                 :class="['lazy-ce-preview', getCustomElementRender(el).wrapperHoverClass]">
                                <component :is="'style'" v-if="getCustomElementRender(el).hoverCss" v-text="getCustomElementRender(el).hoverCss"></component>
                                <template v-for="(it, ci) in getCustomElementRender(el).items" :key="ci">
                                    <img v-if="(it.kind === 'image' || it.kind === 'media') && it.value" :src="it.value" :style="it.style" :class="it.hoverClass" class="max-w-full h-auto block">
                                    <i v-else-if="it.kind === 'icon' && it.value" :class="[it.value, it.hoverClass]" :style="it.style"></i>
                                    <span v-else-if="it.kind === 'social'" :style="it.style" :class="it.hoverClass" :data-tip="it.tip || null" style="transition:background .25s,color .25s,filter .2s"><i :class="it.icon" :style="it.iconStyle"></i></span>
                                    <button v-else-if="it.kind === 'button'" :style="it.style" :class="it.hoverClass" class="inline-block px-3 py-1.5 rounded bg-[#2271b1] text-white text-[12px] font-semibold" v-text="it.value || 'Button'"></button>
                                    <div v-else-if="it.kind === 'repeater'" :style="it.style" :class="it.hoverClass" class="space-y-1.5">
                                        <div v-for="(row, ri) in it.rows" :key="ri" class="flex items-center gap-2 flex-wrap">
                                            <template v-for="(sf, si) in it.subFields" :key="si">
                                                <img v-if="(sf.type === 'image' || sf.type === 'media') && row[sf.key]" :src="row[sf.key]" class="w-8 h-8 object-cover rounded">
                                                <i v-else-if="sf.type === 'icon' && row[sf.key]" :class="row[sf.key]"></i>
                                                <span v-else-if="row[sf.key]" class="text-[12px]" v-text="row[sf.key]"></span>
                                            </template>
                                        </div>
                                        <p v-if="!it.rows.length" class="text-[10px] text-slate-300 italic">No rows</p>
                                    </div>
                                    <div v-else-if="it.value" :style="it.style" :class="it.hoverClass" v-safe-html="it.value"></div>
                                </template>
                                <p v-if="!getCustomElementRender(el).items.length" class="text-[12px] font-semibold text-slate-400 text-center py-2"
                                   v-text="customElements[el.type]?.name || el.type"></p>
                            </div>
                        </template>

                        <!-- Nested Element Toolbar (Center, Compact & Expandable) -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/nel:opacity-100 transition-all duration-200 z-[1010] hover:z-[1100] pointer-events-none" v-if="!isPreview">
                            <div class="flex items-center bg-[#9c27b0] text-white rounded shadow-xl h-7 px-1 pointer-events-auto group/netbar overflow-hidden hover:overflow-visible max-w-[60px] hover:max-w-[250px] transition-all duration-300 ease-in-out">
                                
                                <!-- Always Visible Part: Edit & Add -->
                                <div class="flex items-center">
                                    <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool" 
                                         @click.stop="setEditingContext('element', ci, coli, eli, ncoli, nestedEli)">
                                        <i class="fa fa-pen text-[10px]"></i>
                                        <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Edit</div>
                                    </div>
                                    <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool" 
                                         @click.stop="openElementModal(ci, coli, 'design', true, eli, ncoli, nestedEli + 1)">
                                        <i class="fa fa-plus text-[10px]"></i>
                                        <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Add Below</div>
                                    </div>
                                </div>

                                <!-- Expandable Part: Move, Duplicate, Delete -->
                                <div class="flex items-center border-l border-white/20 ml-1 pl-1 opacity-0 group-hover/netbar:opacity-100 transition-opacity duration-300">
                                    <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-move relative group/etool" 
                                         draggable="true" @dragstart="onDragStart($event, 'element', ci, coli, eli, ncoli, nestedEli)" @dragend="onDragEnd">
                                        <i class="fa fa-arrows-alt text-[10px]"></i>
                                        <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Move</div>
                                    </div>
                                    <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                                         @click.stop="duplicateNestedElement(ci, coli, eli, ncoli, nestedEli)">
                                        <i class="fa fa-copy text-[10px]"></i>
                                        <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Duplicate</div>
                                    </div>
                                    <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool"
                                         @click.stop="openLibraryModal('elements', ci, coli, eli, ncoli, nestedEli)">
                                        <i class="fa fa-hdd text-[10px]"></i>
                                        <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Library</div>
                                    </div>
                                    <div class="w-7 h-7 flex items-center justify-center hover:bg-red-500 rounded cursor-pointer relative group/etool text-red-100 hover:text-white"
                                         @click.stop="ncol.elements.splice(nestedEli, 1)">
                                        <i class="fa fa-trash-alt text-[10px]"></i>
                                        <div class="falcon-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Delete</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>
