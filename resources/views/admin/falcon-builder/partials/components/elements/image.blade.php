<div v-if="el.type === 'image'"
     class="element-image-wrapper w-full relative group/img-preview"
     :class="[el.settings.cssClass || '', 'image-container-' + el.id, el.settings.hoverType ? 'hover-' + el.settings.hoverType : '']"
     :id="el.settings.cssId || undefined"
     :style="[
         {
            display: 'flex',
            width: '100%',
            justifyContent: (getResponsiveVal(el.settings, 'textAlign', device) || 'center') === 'left' ? 'flex-start' : ((getResponsiveVal(el.settings, 'textAlign', device) || 'center') === 'right' ? 'flex-end' : 'center'),
            marginTop: getUnitVal(getResponsiveVal(el.settings, 'marginTop', device) ?? 0, getResponsiveVal(el.settings, 'marginTopUnit', device) || 'px'),
            marginRight: getUnitVal(getResponsiveVal(el.settings, 'marginRight', device) ?? 0, getResponsiveVal(el.settings, 'marginRightUnit', device) || 'px'),
            marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) ?? 0, getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
            marginLeft: getUnitVal(getResponsiveVal(el.settings, 'marginLeft', device) ?? 0, getResponsiveVal(el.settings, 'marginLeftUnit', device) || 'px')
         },
         getCanvasVisibilityStyle(el.settings)
     ]">
    
    <div class="image-inner relative overflow-hidden"
         :style="{
            width: el.settings.width ? getUnitVal(el.settings.width, el.settings.widthUnit || 'px') : 'auto',
            maxWidth: el.settings.maxWidth ? getUnitVal(el.settings.maxWidth, el.settings.maxWidthUnit || 'px') : '100%',
            borderRadius: getUnitVal(el.settings.borderRadius ?? 0, el.settings.borderRadiusUnit || 'px'),
            borderTopWidth: getUnitVal(el.settings.borderSizeTop ?? 0, 'px'),
            borderRightWidth: getUnitVal(el.settings.borderSizeRight ?? 0, 'px'),
            borderBottomWidth: getUnitVal(el.settings.borderSizeBottom ?? 0, 'px'),
            borderLeftWidth: getUnitVal(el.settings.borderSizeLeft ?? 0, 'px'),
            borderStyle: 'solid',
            borderColor: el.settings.borderColor || 'transparent'
         }">
        
        {{-- The lightbox cannot be demonstrated here — the canvas is an editor, and a
             full-screen overlay in it would be in the way rather than useful — so the
             setting is shown instead. Without it the Yes/No in the panel changes nothing
             an author can see, which reads as a switch that does not work. --}}
        <span v-if="el.settings.lightbox && el.settings.url"
              class="absolute top-2 right-2 z-10 flex items-center gap-1 px-1.5 py-0.5 rounded bg-black/55 text-white text-[9px] font-bold uppercase tracking-wide pointer-events-none"
              title="Clicking this image opens it full size on the published page">
            <i class="fa fa-expand text-[8px]"></i> Lightbox
        </span>

         <img v-if="el.settings.url && !['feature_image','author_avatar','logo'].includes(el.settings.dynamic_source)"
             :src="el.settings.url"
             :alt="el.settings.alt || ''"
             class="block w-full pointer-events-none"
             :style="(el.settings.aspectRatio && el.settings.aspectRatio !== 'none')
                 ? { aspectRatio: el.settings.aspectRatio, objectFit: 'cover', height: 'auto', objectPosition: (el.settings.focusX||50)+'% '+(el.settings.focusY||50)+'%' }
                 : { height: 'auto' }">

        <div v-else-if="el.settings.dynamic_source === 'feature_image'"
             class="bg-[#2271b1]/6 border-2 border-dashed border-[#0091ea]/30 rounded flex flex-col items-center justify-center p-8 text-[#0091ea]">
            <i class="fa fa-image text-4xl mb-2 opacity-60"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Feature Image</span>
        </div>

        <div v-else-if="el.settings.dynamic_source === 'author_avatar'"
             class="bg-[#2271b1]/6 border-2 border-dashed border-[#0091ea]/30 rounded flex flex-col items-center justify-center p-8 text-[#0091ea]">
            <i class="fa fa-user-circle text-4xl mb-2 opacity-60"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Author Avatar</span>
        </div>

        <div v-else-if="el.settings.dynamic_source === 'logo'"
             class="bg-[#2271b1]/6 border-2 border-dashed border-[#0091ea]/30 rounded flex flex-col items-center justify-center p-8 text-[#0091ea]">
            <i class="fa fa-star text-4xl mb-2 opacity-60"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Site Logo</span>
        </div>

        <div v-else
             class="bg-slate-100 border-2 border-dashed border-slate-300 rounded flex flex-col items-center justify-center p-8 text-slate-400">
            <i class="fa fa-image text-4xl mb-2 opacity-50"></i>
            <span class="text-xs font-bold uppercase tracking-widest">No Image Selected</span>
        </div>
    </div>
</div>
