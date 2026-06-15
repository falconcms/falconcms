<div v-if="el.type === 'counter'" class="element-counter w-full" :class="getVisibilityClasses(el.settings)"
     :style="{
         textAlign: el.settings.textAlign || 'center',
         padding: '8px 0',
         marginTop: (el.settings.marginTop || 0) + (el.settings.marginTopUnit || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px')
     }">
    <div v-if="el.settings.icon"
         :style="{ color: el.settings.iconColor || '#2271b1', fontSize: (el.settings.iconSize || 40) + 'px', marginBottom: '8px' }">
        <i :class="el.settings.icon"></i>
    </div>
    <div :style="{
             color:         el.settings.numberColor       || '#222222',
             fontSize:      el.settings.numberFontSize    || '48px',
             fontWeight:    el.settings.numberFontWeight  || '700',
             fontFamily:    el.settings.numberFontFamily  || 'inherit',
             lineHeight:    el.settings.numberLineHeight  || '1.1',
             letterSpacing: el.settings.numberLetterSpacing || '0px',
             display: 'block'
         }">
        @{{ el.settings.prefix || '' }}@{{ el.settings.endValue ?? 100 }}@{{ el.settings.suffix || '' }}
    </div>
    <div v-if="el.settings.label" :style="{
             color:         el.settings.labelColor        || '#666666',
             fontSize:      el.settings.labelFontSize     || '14px',
             fontWeight:    el.settings.labelFontWeight   || '400',
             fontFamily:    el.settings.labelFontFamily   || 'inherit',
             lineHeight:    el.settings.labelLineHeight   || '1.4',
             letterSpacing: el.settings.labelLetterSpacing || '0px',
             textTransform: el.settings.labelTextTransform || 'none',
             marginTop: '6px'
         }">
        @{{ el.settings.label }}
    </div>
</div>
