<div v-if="el.type === 'gallery'" class="w-full"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    getUnitVal(getResponsiveVal(el.settings, 'marginTop',    device), getResponsiveVal(el.settings, 'marginTopUnit',    device) || 'px'),
         marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device), getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px')
     }]">

    <!-- Live hover effect (matches front-end): inject scoped :hover CSS since inline styles can't do :hover -->
    <component :is="'style'" v-if="el.settings.images && el.settings.images.length"
               v-text="(el.settings.hoverEffect || 'zoom') === 'zoom' ? ('.lzg-' + el.id + '-img img{transition:transform .4s ease;} .lzg-' + el.id + '-img:hover img{transform:scale(1.07);}') : ''"></component>

    <!-- Grid preview -->
    <div v-if="el.settings.images && el.settings.images.length"
         :style="{
             display: 'grid',
             gridTemplateColumns: 'repeat(' + (device === 'mobile' ? (el.settings.columnsMobile || 1) : (device === 'tablet' ? (el.settings.columnsTablet || 2) : (el.settings.columns || 3))) + ', 1fr)',
             gap: (el.settings.gap !== undefined ? el.settings.gap : 8) + 'px'
         }">
        <div v-for="(img, idx) in el.settings.images" :key="idx">
            <div :class="'lzg-' + el.id + '-img'" :style="{ overflow: 'hidden', borderRadius: (el.settings.borderRadius || 0) + 'px', border: el.settings.imgBorderWidth ? (el.settings.imgBorderWidth + 'px ' + (el.settings.imgBorderStyle || 'solid') + ' ' + (el.settings.imgBorderColor || '#e2e8f0')) : undefined }">
                <div v-if="(el.settings.aspectRatio || 'square') !== 'auto'"
                     :style="{
                         position: 'relative',
                         paddingTop: el.settings.aspectRatio === 'portrait' ? '133.33%' : (el.settings.aspectRatio === 'landscape' ? '56.25%' : '100%'),
                         overflow: 'hidden',
                         background: '#f1f5f9'
                     }">
                    <img v-if="img.url" :src="img.url" :alt="img.alt || ''"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                    <div v-else style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                        <i class="fa fa-image text-slate-300 text-2xl"></i>
                    </div>
                </div>
                <div v-else style="min-height:60px;">
                    <img v-if="img.url" :src="img.url" :alt="img.alt || ''" style="width:100%;height:auto;display:block;">
                    <div v-else style="height:60px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                        <i class="fa fa-image text-slate-300 text-xl"></i>
                    </div>
                </div>
            </div>
            <div v-if="img.caption"
                 :style="{
                     fontFamily:     el.settings.captionFontFamily    || 'inherit',
                     fontSize:       getUnitVal(el.settings.captionFontSize || 13, el.settings.captionFontSizeUnit || 'px'),
                     fontWeight:     el.settings.captionFontWeight    || '400',
                     lineHeight:     el.settings.captionLineHeight    || '1.4',
                     letterSpacing:  el.settings.captionLetterSpacing || '0px',
                     textTransform:  el.settings.captionTextTransform || 'none',
                     color:          el.settings.captionColor         || '#6b7280',
                     textAlign:      el.settings.captionAlign         || 'center',
                     padding:        '4px 2px 0'
                 }">
                @{{ img.caption }}
            </div>
        </div>
    </div>

    <!-- Empty state -->
    <div v-else class="flex flex-col items-center justify-center py-8 border-2 border-dashed border-slate-200 rounded-lg bg-slate-50/50">
        <i class="fa fa-images text-3xl text-slate-300 mb-2"></i>
        <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wide">Gallery</p>
        <p class="text-[10px] text-slate-400 mt-1">Add images in the Content tab</p>
    </div>

</div>
