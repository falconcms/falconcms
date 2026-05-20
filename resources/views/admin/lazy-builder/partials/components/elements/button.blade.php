<div v-if="el.type === 'button'"
     class="element-button-wrapper"
     :class="[el.settings.cssClass || '', 'button-container-' + el.id]"
     :id="el.settings.cssId || undefined"
     :style="[
         {
            display: 'flex',
            width: '100%',
            justifyContent: (getResponsiveVal(el.settings, 'textAlign', device) || 'center') === 'left' ? 'flex-start' : ((getResponsiveVal(el.settings, 'textAlign', device) || 'center') === 'right' ? 'flex-end' : 'center'),
            marginTop: getUnitVal(getResponsiveVal(el.settings, 'marginTop', device) ?? 10, 'px'),
            marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) ?? 10, 'px')
         },
         getCanvasVisibilityStyle(el.settings)
     ]">
    
    <a href="javascript:void(0)"
       :id="'btn-preview-' + el.id"
       @mouseenter="el.isHovered = true"
       @mouseleave="el.isHovered = false"
       :style="{
           display: el.settings.buttonSpan ? 'block' : 'inline-block',
           width: el.settings.buttonSpan ? '100%' : 'auto',
           paddingTop: getUnitVal(getResponsiveVal(el.settings, 'paddingTop', device), 'px'),
           paddingBottom: getUnitVal(getResponsiveVal(el.settings, 'paddingBottom', device), 'px'),
           paddingLeft: getUnitVal(getResponsiveVal(el.settings, 'paddingLeft', device), 'px'),
           paddingRight: getUnitVal(getResponsiveVal(el.settings, 'paddingRight', device), 'px'),
           marginTop: getUnitVal(el.settings.marginTopInner ?? 0, 'px'),
           marginBottom: getUnitVal(el.settings.marginBottomInner ?? 0, 'px'),
           marginLeft: getUnitVal(getResponsiveVal(el.settings, 'marginLeft', device) ?? 0, 'px'),
           marginRight: getUnitVal(getResponsiveVal(el.settings, 'marginRight', device) ?? 0, 'px'),
           backgroundColor: (el.settings.buttonStyle === 'custom' && el.settings.bgGradientStartColor && el.settings.bgGradientEndColor) ? 'transparent' : (el.isHovered ? hexToRgba(el.settings.hoverBgColor || '#007cc0', el.settings.hoverBgColorOpacity) : hexToRgba(el.settings.bgColor || '#0091ea', el.settings.bgColorOpacity)),
           backgroundImage: (el.settings.buttonStyle === 'custom' && el.settings.bgGradientStartColor && el.settings.bgGradientEndColor)
                ? (el.isHovered
                    ? (el.settings.bgGradientType === 'radial'
                        ? `radial-gradient(circle at center, ${hexToRgba(el.settings.bgGradientHoverStartColor || el.settings.bgGradientStartColor, el.settings.bgGradientHoverStartOpacity ?? el.settings.bgGradientStartOpacity)} ${el.settings.bgGradientStartPosition ?? 0}%, ${hexToRgba(el.settings.bgGradientHoverEndColor || el.settings.bgGradientEndColor, el.settings.bgGradientHoverEndOpacity ?? el.settings.bgGradientEndOpacity)} ${el.settings.bgGradientEndPosition ?? 100}%)`
                        : `linear-gradient(${el.settings.bgGradientAngle ?? 180}deg, ${hexToRgba(el.settings.bgGradientHoverStartColor || el.settings.bgGradientStartColor, el.settings.bgGradientHoverStartOpacity ?? el.settings.bgGradientStartOpacity)} ${el.settings.bgGradientStartPosition ?? 0}%, ${hexToRgba(el.settings.bgGradientHoverEndColor || el.settings.bgGradientEndColor, el.settings.bgGradientHoverEndOpacity ?? el.settings.bgGradientEndOpacity)} ${el.settings.bgGradientEndPosition ?? 100}%)`)
                    : (el.settings.bgGradientType === 'radial'
                        ? `radial-gradient(circle at center, ${hexToRgba(el.settings.bgGradientStartColor, el.settings.bgGradientStartOpacity)} ${el.settings.bgGradientStartPosition ?? 0}%, ${hexToRgba(el.settings.bgGradientEndColor, el.settings.bgGradientEndOpacity)} ${el.settings.bgGradientEndPosition ?? 100}%)`
                        : `linear-gradient(${el.settings.bgGradientAngle ?? 180}deg, ${hexToRgba(el.settings.bgGradientStartColor, el.settings.bgGradientStartOpacity)} ${el.settings.bgGradientStartPosition ?? 0}%, ${hexToRgba(el.settings.bgGradientEndColor, el.settings.bgGradientEndOpacity)} ${el.settings.bgGradientEndPosition ?? 100}%)`)
                )
                : 'none',
           color: el.isHovered ? hexToRgba(el.settings.hoverColor || '#ffffff', el.settings.hoverColorOpacity) : hexToRgba(el.settings.color || '#ffffff', el.settings.colorOpacity),
           borderRadius: getUnitVal(el.settings.borderRadius ?? 5, 'px'),
           borderTopWidth: getUnitVal(el.settings.borderSizeTop ?? 0, 'px'),
           borderRightWidth: getUnitVal(el.settings.borderSizeRight ?? 0, 'px'),
           borderBottomWidth: getUnitVal(el.settings.borderSizeBottom ?? 0, 'px'),
           borderLeftWidth: getUnitVal(el.settings.borderSizeLeft ?? 0, 'px'),
           borderStyle: 'solid',
           borderColor: el.settings.borderColor || '#000000',
           fontFamily: el.settings.fontFamily || 'inherit',
           fontSize: getUnitVal(el.settings.fontSize, 'px'),
           fontWeight: el.settings.fontWeight || '600',
           lineHeight: el.settings.lineHeight || 'normal',
           letterSpacing: getUnitVal(el.settings.letterSpacing ?? 0, 'px'),
           textTransform: el.settings.textTransform || 'none',
           textDecoration: 'none',
           transition: 'all 0.3s ease',
           textAlign: 'center'
       }">
        <i v-if="el.settings.icon && el.settings.iconPosition !== 'right'" :class="[el.settings.icon, 'mr-2']"></i>
        @{{ el.settings.text || 'Click Here' }}
        <i v-if="el.settings.icon && el.settings.iconPosition === 'right'" :class="[el.settings.icon, 'ml-2']"></i>
    </a>
</div>
