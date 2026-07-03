<div v-if="el.type === 'breadcrumb'"
     class="element-breadcrumb-wrapper"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
        {
            textAlign: el.settings.textAlign || 'left',
            width: '100%',
            minWidth: 0,
            maxWidth: '100%',
            boxSizing: 'border-box',
            paddingTop: getUnitVal(el.settings.paddingTop, el.settings.paddingTopUnit || 'px'),
            paddingRight: getUnitVal(el.settings.paddingRight, el.settings.paddingRightUnit || 'px'),
            paddingBottom: getUnitVal(el.settings.paddingBottom, el.settings.paddingBottomUnit || 'px'),
            paddingLeft: getUnitVal(el.settings.paddingLeft, el.settings.paddingLeftUnit || 'px'),
            marginTop: getUnitVal(el.settings.marginTop, el.settings.marginTopUnit || 'px'),
            marginRight: getUnitVal(el.settings.marginRight, el.settings.marginRightUnit || 'px'),
            marginBottom: getUnitVal(el.settings.marginBottom, el.settings.marginBottomUnit || 'px'),
            marginLeft: getUnitVal(el.settings.marginLeft, el.settings.marginLeftUnit || 'px'),
        },
        getCanvasVisibilityStyle(el.settings)
     ]">
    <nav class="element-breadcrumb"
         :style="{
            fontFamily: el.settings.fontFamily || 'inherit',
            fontSize: /[a-zA-Z%]/.test(String(el.settings.fontSize || '')) ? String(el.settings.fontSize) : getUnitVal(el.settings.fontSize || 14, el.settings.fontSizeUnit || 'px'),
            fontWeight: el.settings.fontWeight || '400',
            color: el.settings.color || '#6b7280',
            lineHeight: el.settings.lineHeight || 1.6,
            letterSpacing: /[a-zA-Z%]/.test(String(el.settings.letterSpacing || '')) ? String(el.settings.letterSpacing) : (el.settings.letterSpacing ? el.settings.letterSpacing + 'px' : 'normal'),
            textTransform: el.settings.textTransform || 'none',
            display: 'flex',
            flexWrap: 'wrap',
            alignItems: 'center',
            gap: '6px',
            width: '100%',
            maxWidth: '100%',
            minWidth: 0,
            justifyContent: el.settings.textAlign === 'right' ? 'flex-end' : (el.settings.textAlign === 'center' ? 'center' : 'flex-start')
         }">
        <template v-if="el.settings.showHome !== false">
            <span :style="{ color: el.settings.linkColor || el.settings.color || '#6b7280' }">@{{ el.settings.homeLabel || 'Home' }}</span>
            <span :style="{ color: el.settings.separatorColor || '#9ca3af' }">@{{ el.settings.separator || '/' }}</span>
        </template>
        <span :style="{ color: el.settings.linkColor || el.settings.color || '#6b7280' }">Sample Category</span>
        <span :style="{ color: el.settings.separatorColor || '#9ca3af' }">@{{ el.settings.separator || '/' }}</span>
        <span v-if="el.settings.showCurrent !== false"
              :style="{ color: el.settings.currentColor || '#111827', fontWeight: '500' }">Current Page</span>
    </nav>
</div>
