@php $pcMode = ($postCardMode ?? false); @endphp
@if($pcMode)
<div class="relative w-full"
     :class="[getVisibilityClasses(container.settings)]"
     :style="containerStyle(container, ci)"
     @click.stop="activeCi = ci"
     @mouseenter="setHover('container', ci)"
     @mouseleave="setHover(null)">
@else
<div class="container-row relative group/cont"
     :class="[
        (!isPreview && editingCi === ci) ? 'container-active' : '',
        isDragging && dragCi === ci && !isColumnDrag ? 'dragging-no-transition' : 'transition-all',
        dragTarget === 'container-' + ci + '-null-null-null-null' && dragPosition === 'top' ? 'border-t-4 border-t-blue-500' : '',
        dragTarget === 'container-' + ci + '-null-null-null-null' && dragPosition === 'bottom' ? 'border-b-4 border-b-blue-500' : '',
        (dragTarget === 'container-' + ci + '-null-null-null-null' && dragSource?.type === 'column') ? 'ring-2 ring-blue-400 ring-inset bg-blue-50/20' : '',
        getVisibilityClasses(container.settings)
     ]"
     :style="containerStyle(container, ci)"
     @click.stop="activeCi = ci"
     @contextmenu.prevent.stop="openCtxMenu($event, 'container', ci)"
     @mouseenter="setHover('container', ci)"
     @mouseleave="setHover(null)"
     @dragover="onDragOver($event, 'container', ci)"
     @drop="onDrop($event, 'container', ci)">
@endif
    
    @if(!$pcMode)
    <!-- Container Overlays (Margin/Padding) -->
    <div v-if="!isPreview">
        <div class="absolute left-0 right-0 pointer-events-auto z-0 bg-[#9c27b0]/10 transition-opacity"
             :style="{ height: (container.settings.marginTop || 0) + 'px', top: '-' + (container.settings.marginTop || 0) + 'px' }"
             :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && dragType === 'marginTop' && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
             <div class="absolute top-0 left-0 w-full border-t border-dashed border-[#9c27b0]/40"></div>
        </div>
        <div class="absolute left-0 right-0 pointer-events-auto z-0 bg-[#9c27b0]/10 transition-opacity"
             :style="{ height: (container.settings.marginBottom || 0) + 'px', bottom: '-' + (container.settings.marginBottom || 0) + 'px' }"
             :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && dragType === 'marginBottom' && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
             <div class="absolute bottom-0 left-0 w-full border-b border-dashed border-[#9c27b0]/40"></div>
        </div>
        <div class="absolute left-0 right-0 pointer-events-auto z-0 bg-[#2271b1]/5 transition-opacity"
             :style="{ height: (container.settings.paddingTop || 0) + 'px', top: '0px' }"
             :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && dragType === 'paddingTop' && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
             <div class="absolute bottom-0 left-0 w-full border-b border-dashed border-[#0091ea]/30"></div>
        </div>
        <div class="absolute left-0 right-0 pointer-events-auto z-0 bg-[#2271b1]/5 transition-opacity"
             :style="{ height: (container.settings.paddingBottom || 0) + 'px', bottom: '0px' }"
             :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && dragType === 'paddingBottom' && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
             <div class="absolute top-0 left-0 w-full border-t border-dashed border-[#0091ea]/30"></div>
        </div>
        <div class="absolute top-0 bottom-0 pointer-events-auto z-0 bg-[#2271b1]/5 transition-opacity"
             :style="{ width: (container.settings.paddingLeft || 0) + 'px', left: '0px' }"
             :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && dragType === 'paddingLeft' && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
             <div class="absolute top-0 right-0 h-full border-r border-dashed border-[#0091ea]/30"></div>
        </div>
        <div class="absolute top-0 bottom-0 pointer-events-auto z-0 bg-[#2271b1]/5 transition-opacity"
             :style="{ width: (container.settings.paddingRight || 0) + 'px', right: '0px' }"
             :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && dragType === 'paddingRight' && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
             <div class="absolute top-0 left-0 h-full border-l border-dashed border-[#0091ea]/30"></div>
        </div>
    </div>
    @endif

    @if(!$pcMode)
    <!-- Container Handles -->
    <div v-if="!isPreview" class="container-handles transition-opacity"
         :class="shouldShowGuide('container', ci) ? ( (editingCi === ci || (isDragging && dragCi === ci && !isColumnDrag)) ? 'opacity-100' : 'opacity-0' ) : 'hidden'">
        <div class="handle-top flex gap-0.5">
            <div class="handle-blue group/hpt" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateY(' + (container.settings.paddingTop || 0) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'paddingTop', ci)">
                <i class="fa fa-bars"></i>
                <div class="lazy-tooltip opacity-0 group-hover/hpt:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingTop' && dragCi === ci && !isColumnDrag}">@{{ container.settings.paddingTop || 0 }}px</div>
            </div>
            <div class="handle-purple group/hmt" @mousedown.stop.prevent="startDrag($event, 'marginTop', ci)">
                <i class="fa fa-bars"></i>
                <div class="lazy-tooltip opacity-0 group-hover/hmt:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'marginTop' && dragCi === ci && !isColumnDrag}">@{{ container.settings.marginTop || 0 }}px</div>
            </div>
        </div>
        <div class="handle-bottom flex gap-0.5">
            <div class="handle-blue group/hpb"
                 @mousedown.stop.prevent="startDrag($event, 'paddingBottom', ci)">
                <i class="fa fa-bars"></i>
                <div class="lazy-tooltip opacity-0 group-hover/hpb:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingBottom' && dragCi === ci && !isColumnDrag}">@{{ container.settings.paddingBottom || 0 }}px</div>
            </div>
            <div class="handle-purple group/hmb" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateY(' + (container.settings.marginBottom || 0) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'marginBottom', ci)">
                <i class="fa fa-bars"></i>
                <div class="lazy-tooltip opacity-0 group-hover/hmb:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'marginBottom' && dragCi === ci && !isColumnDrag}">@{{ container.settings.marginBottom || 0 }}px</div>
            </div>
        </div>
        <div class="handle-left group/hpl" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateX(' + (container.settings.paddingLeft || 0) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'paddingLeft', ci)">
            <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
            <div class="lazy-tooltip opacity-0 group-hover/hpl:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingLeft' && dragCi === ci && !isColumnDrag}">@{{ container.settings.paddingLeft || 0 }}px</div>
        </div>
        <div class="handle-right group/hpr" :class="isDragging ? '' : 'transition-all'" :style="{ transform: 'translateX(-' + (container.settings.paddingRight || 0) + 'px)' }" @mousedown.stop.prevent="startDrag($event, 'paddingRight', ci)">
            <i class="fa fa-bars" style="transform: rotate(90deg);"></i>
            <div class="lazy-tooltip opacity-0 group-hover/hpr:opacity-100" :class="{'opacity-100!': isDragging && dragType === 'paddingRight' && dragCi === ci && !isColumnDrag}">@{{ container.settings.paddingRight || 0 }}px</div>
        </div>
    </div>
    @endif

    @if(!$pcMode)
    <!-- Container Toolbar -->
    <div class="container-right-panel transition-opacity" v-if="!isPreview"
         :class="(editingCi === ci || (isDragging && dragCi === ci)) ? 'opacity-100' : 'opacity-0'">
        <div class="panel-inner shadow-xl group/panel">
            <div class="flex items-center overflow-hidden max-w-0 opacity-0 group-hover/panel:max-w-[200px] group-hover/panel:opacity-100 group-hover/panel:overflow-visible transition-all duration-300">
                <div class="panel-btn cursor-move" draggable="true" @dragstart="onDragStart($event, 'container', ci)" @dragend="onDragEnd"><i class="fa fa-arrows-alt"></i><div class="lazy-tooltip">Drag</div></div>
                <div class="panel-btn" @click.stop="layout.splice(ci,1)"><i class="fa fa-trash-alt"></i><div class="lazy-tooltip">Delete</div></div>
                <div class="panel-btn" @click.stop="openLibraryModal('containers', ci)"><i class="fa fa-hdd"></i><div class="lazy-tooltip">Library</div></div>
                <div class="panel-btn" @click.stop="duplicateContainer(ci)"><i class="fa fa-copy"></i><div class="lazy-tooltip">Duplicate</div></div>
            </div>
            <div class="panel-btn" @click.stop="setEditingContext('container', ci)">
                <i class="fa fa-pen"></i><div class="lazy-tooltip">Edit</div>
            </div>
            <div class="panel-btn" @click.stop="addContainer(ci + 1)"><i class="fa fa-plus-square"></i><div class="lazy-tooltip">Add</div></div>
        </div>
    </div>
    @endif

    @if(!$pcMode)
    <!-- Sticky Badge -->
    <div v-if="!isPreview && container.settings && container.settings.sticky && (device === 'desktop' ? container.settings.stickyDesktop !== false : device === 'tablet' ? container.settings.stickyTablet !== false : container.settings.stickyMobile !== false)"
         class="absolute top-0 left-0 bg-[#2271b1] text-white text-[9px] px-2 py-0.5 z-10 flex items-center gap-1 rounded-br-sm select-none pointer-events-none">
        <i class="fa fa-thumbtack"></i> Sticky
    </div>
    <!-- Global Badge -->
    <div v-if="!isPreview && container.settings && container.settings.global_id"
         class="absolute top-0 right-0 bg-[#7c3aed] text-white text-[9px] px-2 py-0.5 z-10 flex items-center gap-1 rounded-bl-sm select-none pointer-events-none">
        <i class="fa fa-globe"></i> Global
    </div>
    <!-- Conditional Visibility Badge -->
    <div v-if="!isPreview && container.settings && container.settings.vis_condition"
         class="absolute bottom-0 left-0 bg-[#f59e0b] text-white text-[9px] px-2 py-0.5 z-10 flex items-center gap-1 rounded-tr-sm select-none pointer-events-none">
        <i class="fa fa-eye-slash"></i>
        @{{ {logged_in:'Logged In Only', logged_out:'Logged Out Only', schedule:'Scheduled'}[container.settings.vis_condition] || '' }}
    </div>
    @endif

    <!-- Container Content Box -->
    <div class="mx-auto w-full flex relative" :style="containerInnerStyle(container)">
        
        <div v-if="container.columns.length === 0 && !isPreview" 
             class="absolute inset-0 flex items-center justify-center z-[100] transition-opacity"
             :class="(editingCi === ci || (isDragging && dragCi === ci) || (hoveredType === 'container' && hoveredCi === ci)) ? 'opacity-100' : 'opacity-0'">
            <button @click.stop="openColumnModal(ci, 'edit')" 
                    class="w-8 h-8 bg-[#2271b1] text-white rounded shadow-lg flex items-center justify-center hover:scale-110 transition-all relative group/addbtn pointer-events-auto">
                <i class="fa fa-plus text-base pointer-events-none"></i>
                <div class="lazy-tooltip opacity-0 group-hover/addbtn:opacity-100" style="top: 100%; margin-top: 10px; display: block !important;">Add Column Layout</div>
            </button>
        </div>

        @include('falcon-cms::admin.lazy-builder.partials.components.column.col')
    </div>
</div>
