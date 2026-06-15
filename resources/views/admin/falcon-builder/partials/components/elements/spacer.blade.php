<div v-if="el.type === 'spacer'"
     class="w-full"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px')
     }]">

    {{-- Separator line preview --}}
    <div v-if="el.settings.style && el.settings.style !== 'default' && el.settings.style !== 'none' && el.settings.style !== 'no_style'"
         class="flex px-1"
         :style="{
             justifyContent: el.settings.alignment === 'left' ? 'flex-start' : el.settings.alignment === 'right' ? 'flex-end' : 'center'
         }">
        <div class="flex flex-col"
             :style="{ width: (el.settings.separatorWidth || 100) + (el.settings.separatorWidthUnit || '%') }">
            <div :style="{
                     borderTop: (el.settings.borderSize || 1) + 'px ' +
                         (el.settings.style?.includes('dashed') ? 'dashed' : el.settings.style?.includes('dotted') ? 'dotted' : 'solid') +
                         ' ' + (el.settings.separatorColor || '#cccccc')
                 }">
            </div>
            <div v-if="el.settings.style?.includes('double')"
                 :style="{
                     borderTop: (el.settings.borderSize || 1) + 'px ' +
                         (el.settings.style?.includes('dashed') ? 'dashed' : el.settings.style?.includes('dotted') ? 'dotted' : 'solid') +
                         ' ' + (el.settings.separatorColor || '#cccccc'),
                     marginTop: '3px'
                 }">
            </div>
        </div>
    </div>

</div>
