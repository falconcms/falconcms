<div v-if="el.type === 'heading'"
     class="element-heading"
     :class="[getVisibilityClasses(el.settings), el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="{
         textAlign: getResponsiveVal(el.settings, 'textAlign', device) || 'left',
         marginTop:    getUnitVal(getResponsiveVal(el.settings, 'marginTop',    device), getResponsiveVal(el.settings, 'marginTopUnit',    device) || 'px') || '0px',
         marginRight:  getUnitVal(getResponsiveVal(el.settings, 'marginRight',  device), getResponsiveVal(el.settings, 'marginRightUnit',  device) || 'px') || '0px',
         marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device), getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px') || '0px',
         marginLeft:   getUnitVal(getResponsiveVal(el.settings, 'marginLeft',   device), getResponsiveVal(el.settings, 'marginLeftUnit',   device) || 'px') || '0px',
         paddingTop:    getUnitVal(getResponsiveVal(el.settings, 'paddingTop',    device), getResponsiveVal(el.settings, 'paddingTopUnit',    device) || 'px') || '0px',
         paddingRight:  getUnitVal(getResponsiveVal(el.settings, 'paddingRight',  device), getResponsiveVal(el.settings, 'paddingRightUnit',  device) || 'px') || '0px',
         paddingBottom: getUnitVal(getResponsiveVal(el.settings, 'paddingBottom', device), getResponsiveVal(el.settings, 'paddingBottomUnit', device) || 'px') || '0px',
         paddingLeft:   getUnitVal(getResponsiveVal(el.settings, 'paddingLeft',   device), getResponsiveVal(el.settings, 'paddingLeftUnit',   device) || 'px') || '0px',
     }">
    <component :is="el.settings.tag || 'h2'"
        :style="{
            margin: '0',
            padding: '0',
            textAlign: getResponsiveVal(el.settings, 'textAlign', device) || 'left',
            fontSize: el.settings.fontSize ? (/[a-zA-Z%]/.test(String(el.settings.fontSize)) ? String(el.settings.fontSize) : String(el.settings.fontSize) + (el.settings.fontSizeUnit || 'px')) : '30px',
            fontWeight: el.settings.fontWeight || '700',
            lineHeight: el.settings.lineHeight || '1.2',
            letterSpacing: getUnitVal(el.settings.letterSpacing, el.settings.letterSpacingUnit),
            color: el.settings.color || '#222222',
            textTransform: el.settings.textTransform || 'none',
        }">
        @{{ el.settings.title || 'New Heading' }}
    </component>
</div>
