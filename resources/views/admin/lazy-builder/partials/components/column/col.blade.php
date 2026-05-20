<!-- Columns Loop -->
<div v-for="(column, coli) in container.columns" :key="column.id"
     class="column-outer relative"
     :class="['col-' + column.id, (column.settings.hoverType && column.settings.hoverType !== 'none') ? 'hover-effect-' + column.settings.hoverType : '', getVisibilityClasses(column.settings)]"
     :style="columnOuterStyle(container, column, container.columns.length)">

    
    <component :is="column.settings.htmlTag || 'div'" class="column-inner group/col relative"
         :class="[
            activeColi === coli && activeColCi === ci ? 'column-active' : '', 
            isDragging && dragCi === ci && dragColi === coli ? 'dragging-no-transition' : '',
            dragTarget === 'column-' + ci + '-' + coli + '-null-null-null' && dragPosition === 'left' ? 'border-l-4 border-l-blue-500' : '',
            dragTarget === 'column-' + ci + '-' + coli + '-null-null-null' && dragPosition === 'right' ? 'border-r-4 border-r-blue-500' : '',
            (dragTarget === 'column-' + ci + '-' + coli + '-null-null-null' && dragSource?.type === 'element') ? 'ring-2 ring-blue-400 ring-inset bg-blue-50/30' : '',
            column.settings.linkUrl ? 'cursor-pointer' : ''
         ]"
         :style="columnInnerStyle(column, container)"
         @click.stop="if(column.settings.linkUrl && isPreview){ window.open(column.settings.linkUrl, '_blank'); } else { setEditingContext('column', ci, coli) }"
         @mouseenter="setHover('column', ci, coli)"
         @mouseleave="setHover(null)"
         @dragover="onDragOver($event, 'column', ci, coli)"
         @drop="onDrop($event, 'column', ci, coli)">

        <!-- Column Toolbar (Top Left) -->
        <div class="column-left-panel transition-opacity" v-if="!isPreview"
             :class="(activeColi === coli && activeColCi === ci) ? 'opacity-100' : 'opacity-0 group-hover/col:opacity-100'">
            <div class="panel-inner shadow-xl group/panel">
                <div class="panel-btn" @click.stop="setEditingContext('column', ci, coli)">
                    <i class="fa fa-pen"></i><div class="lazy-tooltip">Column Options</div>
                </div>
                <div class="panel-btn" @click.stop="openColumnModal(ci, 'edit')">
                    <i class="fa fa-plus-square"></i><div class="lazy-tooltip">Add Column</div>
                </div>
                
                <div class="flex items-center overflow-hidden max-w-0 opacity-0 group-hover/panel:max-w-[200px] group-hover/panel:opacity-100 transition-all duration-300">
                    <div class="column-label whitespace-nowrap">@{{ formatBasisToFraction(device === 'desktop' ? column.basis : (column['basis_' + device] || column.basis)) }}</div>
                    <div class="panel-btn" @click.stop="duplicateColumn(ci, coli)"><i class="fa fa-copy"></i><div class="lazy-tooltip">Duplicate</div></div>
                    <div class="panel-btn"><i class="fa fa-hdd"></i><div class="lazy-tooltip">Save</div></div>
                    <div class="panel-btn" @click.stop="container.columns.splice(coli, 1)"><i class="fa fa-trash-alt"></i><div class="lazy-tooltip">Delete</div></div>
                    <div class="panel-btn cursor-move" draggable="true" @dragstart="onDragStart($event, 'column', ci, coli)" @dragend="onDragEnd"><i class="fa fa-arrows-alt"></i><div class="lazy-tooltip">Drag</div></div>
                </div>
            </div>
        </div>

        <!-- Column Overlays -->
        <div v-if="!isPreview" class="absolute inset-0 pointer-events-none z-0">
            <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#9c27b0]/5 transition-opacity"
                 :style="{ height: (column.settings.marginTop || 0) + 'px', top: '-' + (column.settings.marginTop || 0) + 'px' }"
                 :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'marginTop')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                 <div class="absolute top-0 left-0 w-full border-t border-dashed border-[#9c27b0]/20"></div>
            </div>
            <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#9c27b0]/5 transition-opacity"
                 :style="{ height: (column.settings.marginBottom || 0) + 'px', bottom: '-' + (column.settings.marginBottom || 0) + 'px' }"
                 :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'marginBottom')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                 <div class="absolute bottom-0 left-0 w-full border-b border-dashed border-[#9c27b0]/20"></div>
            </div>
            <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#0091ea]/5 transition-opacity"
                 :style="{ height: (column.settings.paddingTop || 0) + 'px', top: '0px' }"
                 :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'paddingTop')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                 <div class="absolute bottom-0 left-0 w-full border-b border-dashed border-[#0091ea]/20"></div>
            </div>
            <div class="absolute left-0 right-0 pointer-events-none z-0 bg-[#0091ea]/5 transition-opacity"
                 :style="{ height: (column.settings.paddingBottom || 0) + 'px', bottom: '0px' }"
                 :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'paddingBottom')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                 <div class="absolute top-0 left-0 w-full border-t border-dashed border-[#0091ea]/20"></div>
            </div>
            <div class="absolute top-0 bottom-0 pointer-events-none z-0 bg-[#0091ea]/5 transition-opacity"
                 :style="{ width: (column.settings.paddingLeft || 0) + 'px', left: '0px' }"
                 :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'paddingLeft')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                 <div class="absolute top-0 right-0 h-full border-r border-dashed border-[#0091ea]/20"></div>
            </div>
            <div class="absolute top-0 bottom-0 pointer-events-none z-0 bg-[#0091ea]/5 transition-opacity"
                 :style="{ width: (column.settings.paddingRight || 0) + 'px', right: '0px' }"
                 :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'paddingRight')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
                 <div class="absolute top-0 left-0 h-full border-l border-dashed border-[#0091ea]/20"></div>
            </div>
        </div>


        <!-- Column Handles -->
        <div v-if="!isPreview" class="absolute inset-0 pointer-events-none z-[1500] transition-opacity"
             :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
            
            <div class="absolute top-0.5 left-1/2 -translate-x-1/2 pointer-events-auto flex gap-0.5 items-start">
                <div class="handle-purple group/chmt" @mousedown.stop.prevent="startDrag($event, 'marginTop', ci, coli)">
                    <i class="fa fa-bars"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chmt:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'marginTop' && dragCi === ci && dragColi === coli}">@{{ column.settings.marginTop || 0 }}px</div>
                </div>
                <div class="handle-blue group/chpt" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateY(' + (Number(column.settings.paddingTop || 0) + 2) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'paddingTop', ci, coli)">
                    <i class="fa fa-bars"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chpt:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingTop' && dragCi === ci && dragColi === coli}">@{{ column.settings.paddingTop || 0 }}px</div>
                </div>
            </div>

            <div class="absolute bottom-0.5 left-1/2 -translate-x-1/2 pointer-events-auto flex gap-0.5 items-end">
                <div class="handle-purple group/chmb" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateY(' + (column.settings.marginBottom || 0) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'marginBottom', ci, coli)">
                    <i class="fa fa-bars"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chmb:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'marginBottom' && dragCi === ci && dragColi === coli}">@{{ column.settings.marginBottom || 0 }}px</div>
                </div>
                <div class="handle-blue group/chpb"
                     @mousedown.stop.prevent="startDrag($event, 'paddingBottom', ci, coli)">
                    <i class="fa fa-bars"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chpb:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingBottom' && dragCi === ci && dragColi === coli}">@{{ column.settings.paddingBottom || 0 }}px</div>
                </div>
            </div>

            <div class="absolute left-0.5 top-1/2 -translate-y-1/2 pointer-events-auto flex flex-col gap-0.5 items-start">
                <div class="handle-purple-h group/chml" @mousedown.stop.prevent="startDrag($event, 'columnSpacingLeft', ci, coli)">
                    <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chml:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'columnSpacingLeft' && dragCi === ci && dragColi === coli}">@{{ (column.settings.columnSpacingLeft || 0).toFixed(1) }}%</div>
                </div>
                <div class="handle-blue-h group/chpl" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateX(' + (Number(column.settings.paddingLeft || 0) + 2) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'paddingLeft', ci, coli)">
                    <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chpl:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingLeft' && dragCi === ci && dragColi === coli}">@{{ column.settings.paddingLeft || 0 }}px</div>
                </div>
            </div>

            <div class="absolute right-0.5 top-1/2 -translate-y-1/2 pointer-events-auto flex flex-col gap-0.5 items-end">
                <div class="handle-purple-h group/chmr" @mousedown.stop.prevent="startDrag($event, 'columnSpacingRight', ci, coli)">
                    <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chmr:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'columnSpacingRight' && dragCi === ci && dragColi === coli}">@{{ (column.settings.columnSpacingRight || 0).toFixed(1) }}%</div>
                </div>
                <div class="handle-blue-h group/chpr" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateX(-' + (Number(column.settings.paddingRight || 0) + 2) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'paddingRight', ci, coli)">
                    <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
                    <div class="lazy-tooltip opacity-0 group-hover/chpr:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingRight' && dragCi === ci && dragColi === coli}">@{{ column.settings.paddingRight || 0 }}px</div>
                </div>
            </div>
        </div>

        <!-- Add Element Button: ABSOLUTE CENTER if only nested rows or empty -->
        <div v-if="!isPreview && !column.elements.some(el => el.type !== 'row')" 
             class="absolute inset-0 flex items-center justify-center z-10 transition-opacity pointer-events-none opacity-100">
            <button @click.stop="openElementModal(ci, coli, 'design')" 
                    class="w-8 h-8 bg-[#0091ea] text-white rounded shadow-lg flex items-center justify-center hover:scale-110 transition-all relative group/coladdbtn pointer-events-auto">
                <i class="fa fa-plus text-base pointer-events-none"></i>
                <div class="lazy-tooltip opacity-0 group-hover/coladdbtn:opacity-100" style="top: 100%; margin-top: 10px; display: block !important;">Add Element</div>
            </button>
        </div>
        
        <template v-for="(el, eli) in column.elements" :key="el.id">
        <div v-if="el.type === 'row' && column.settings.contentLayout === 'row'"
             style="flex-basis:100%;width:100%;height:0;overflow:hidden;"></div>
        <div class="relative group/el mb-2"
             @click.stop="setEditingContext('element', ci, coli, eli)"
             :class="[
                (column.settings.contentLayout === 'row' && el.type !== 'row') ? '' : (getResponsiveVal(column.settings, 'contentAlignH', device) && getResponsiveVal(column.settings, 'contentAlignH', device) !== 'stretch' && el.type !== 'title' && el.type !== 'menu' && el.type !== 'text_block' && el.type !== 'special_text' && el.type !== 'button' ? '' : 'w-full'),
                dragTarget === 'element-' + ci + '-' + coli + '-' + eli + '-null-null' && dragPosition === 'top' ? 'border-t-2 border-t-blue-500' : '',
                dragTarget === 'element-' + ci + '-' + coli + '-' + eli + '-null-null' && dragPosition === 'bottom' ? 'border-b-2 border-b-blue-500' : ''
             ]"
             :style="el.type === 'row' ? { width: '100%', maxWidth: '100%' } : {}"
             @dragover="onDragOver($event, 'element', ci, coli, eli)"
             @drop="onDrop($event, 'element', ci, coli, eli)">
            
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.heading')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.title')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.text')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.image')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.button')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.video')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.spacer')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.text-block')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.elements.menu')
            @includeIf('cms-dashboard::admin.lazy-builder.partials.components.nested.row')

            <!-- Custom Registered Blocks -->
            @foreach($customElements ?? [] as $type => $custEl)
                @if(in_array($type, ['text_block', 'button', 'special_text', 'image', 'menu'])) @continue @endif
                @php
                    $firstTextField = null;
                    foreach ($custEl['fields'] ?? [] as $fk => $fv) {
                        if (in_array($fv['type'] ?? 'text', ['text', 'textarea'])) {
                            $firstTextField = $fk;
                            break;
                        }
                    }
                @endphp
                <div v-if="el.type === '{{ $type }}'" class="p-4 text-center border border-dashed border-slate-200 rounded bg-slate-50/50">
                    <i class="{{ $custEl['icon'] ?? 'fa fa-cube' }} text-xl text-slate-400 block mb-1"></i>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">{{ $custEl['name'] ?? $type }}</p>
                    @if($firstTextField)
                        <p class="text-[10px] text-slate-400 mt-1 truncate">{!! "{{ el.settings.{$firstTextField} || '...' }}" !!}</p>
                    @endif
                </div>
            @endforeach

            <!-- Element Toolbar (Top-Center, Compact & Expandable) -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 flex justify-center opacity-0 group-hover/el:opacity-100 transition-all duration-200 z-[1010] hover:z-[1100] pointer-events-none p-1" v-if="!isPreview && el.type !== 'row'">
                <div class="flex items-center bg-[#9c27b0] text-white rounded shadow-xl h-7 px-1 pointer-events-auto group/etbar overflow-hidden max-w-[60px] hover:max-w-[250px] transition-all duration-300 ease-in-out">
                    
                    <!-- Always Visible Part: Edit & Add -->
                    <div class="flex items-center">
                        <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool" 
                             @click.stop="setEditingContext('element', ci, coli, eli)">
                            <i class="fa fa-pen text-[10px]"></i>
                            <div class="lazy-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Edit</div>
                        </div>
                        <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool" 
                             @click.stop="openElementModal(ci, coli, 'design', false, eli + 1)">
                            <i class="fa fa-plus text-[10px]"></i>
                            <div class="lazy-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Add Below</div>
                        </div>
                    </div>

                    <!-- Expandable Part: Move, Duplicate, Delete -->
                    <div class="flex items-center border-l border-white/20 ml-1 pl-1 opacity-0 group-hover/etbar:opacity-100 transition-opacity duration-300">
                        <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-move relative group/etool" 
                             draggable="true" @dragstart="onDragStart($event, 'element', ci, coli, eli)" @dragend="onDragEnd">
                            <i class="fa fa-arrows-alt text-[10px]"></i>
                            <div class="lazy-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Move</div>
                        </div>
                        <div class="w-7 h-7 flex items-center justify-center hover:bg-white/20 rounded cursor-pointer relative group/etool" 
                             @click.stop="duplicateElement(ci, coli, eli)">
                            <i class="fa fa-copy text-[10px]"></i>
                            <div class="lazy-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Duplicate</div>
                        </div>
                        <div class="w-7 h-7 flex items-center justify-center hover:bg-red-500 rounded cursor-pointer relative group/etool text-red-100 hover:text-white" 
                             @click.stop="column.elements.splice(eli, 1)">
                            <i class="fa fa-trash-alt text-[10px]"></i>
                            <div class="lazy-tooltip-v2 opacity-0 group-hover/etool:opacity-100 z-[100] whitespace-nowrap">Delete</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="el.type === 'row' && column.settings.contentLayout === 'row'"
             style="flex-basis:100%;width:100%;height:0;overflow:hidden;"></div>
        </template>
    </component>

    <!-- Left spacing overlay: anchored at column-outer left border, grows inward -->
    <div v-if="!isPreview"
         class="absolute top-0 bottom-0 left-0 pointer-events-none z-[5] bg-[#9c27b0]/5 transition-opacity"
         :style="{ width: ((column.settings.columnSpacingLeft || 0) * 100 / (parseFloat(column.basis) || 100)) + '%' }"
         :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'columnSpacingLeft')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
         <div class="absolute top-0 right-0 h-full border-r border-dashed border-[#9c27b0]/20"></div>
    </div>
    <!-- Right spacing overlay: anchored at column-outer right border, grows inward -->
    <div v-if="!isPreview"
         class="absolute top-0 bottom-0 right-0 pointer-events-none z-[5] bg-[#9c27b0]/5 transition-opacity"
         :style="{ width: ((column.settings.columnSpacingRight || 0) * 100 / (parseFloat(column.basis) || 100)) + '%' }"
         :class="shouldShowGuide('column', ci, coli) ? ( ((activeColi === coli && activeColCi === ci) || (isDragging && dragCi === ci && dragColi === coli && dragType === 'columnSpacingRight')) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
         <div class="absolute top-0 left-0 h-full border-l border-dashed border-[#9c27b0]/20"></div>
    </div>
</div>
