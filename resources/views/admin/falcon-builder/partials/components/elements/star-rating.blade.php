<div v-if="el.type === 'star_rating'" class="w-full py-2"
     :style="[getCanvasVisibilityStyle(el.settings), {
         width: '100%',
         textAlign:    el.settings.textAlign !== 'full' ? (el.settings.textAlign || 'center') : undefined,
         marginTop:    (el.settings.marginTop    !== undefined ? el.settings.marginTop    : 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom !== undefined ? el.settings.marginBottom : 0) + (el.settings.marginBottomUnit || 'px')
     }]">

    <div :style="{
             display:         el.settings.textAlign === 'full' ? 'flex' : 'inline-flex',
             width:           el.settings.textAlign === 'full' ? '100%' : undefined,
             justifyContent:  el.settings.textAlign === 'full' ? 'space-evenly' : undefined,
             alignItems:      'center',
             gap:             (el.settings.gap !== undefined ? el.settings.gap : 4) + 'px',
             lineHeight:      '1'
         }">
        <template v-for="i in (el.settings.maxStars || 5)" :key="i">
            <span v-if="(el.settings.rating !== undefined ? el.settings.rating : 5) >= i"
                  style="line-height:1;display:inline-block;"
                  :style="{ color: el.settings.starColor || '#f59e0b', fontSize: (el.settings.starSize || 24) + 'px' }">★</span>
            <span v-else-if="(el.settings.rating !== undefined ? el.settings.rating : 5) >= i - 0.5"
                  style="position:relative;display:inline-block;line-height:1;"
                  :style="{ fontSize: (el.settings.starSize || 24) + 'px' }">
                <span :style="{ color: el.settings.emptyColor || '#d1d5db' }">★</span>
                <span style="position:absolute;left:0;top:0;width:50%;overflow:hidden;"
                      :style="{ color: el.settings.starColor || '#f59e0b' }">★</span>
            </span>
            <span v-else
                  style="line-height:1;display:inline-block;"
                  :style="{ color: el.settings.emptyColor || '#d1d5db', fontSize: (el.settings.starSize || 24) + 'px' }">★</span>
        </template>
    </div>

    <div v-if="el.settings.label" style="margin-top:5px;"
         :style="{
             fontFamily:     el.settings.labelFontFamily    || 'inherit',
             fontSize:       getUnitVal(el.settings.labelFontSize || 13, el.settings.labelFontSizeUnit || 'px'),
             fontWeight:     el.settings.labelFontWeight    || '400',
             lineHeight:     el.settings.labelLineHeight    || '1.4',
             letterSpacing:  el.settings.labelLetterSpacing || '0px',
             textTransform:  el.settings.labelTextTransform || 'none',
             color:          el.settings.labelColor         || '#6b7280'
         }">
        @{{ el.settings.label }}
    </div>
</div>
