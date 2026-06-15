<div v-if="el.type === 'title'"
     class="element-title-wrapper"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
         { textAlign: getResponsiveVal(el.settings, 'textAlign', device) || 'center', width: '100%' },
         getCanvasVisibilityStyle(el.settings)
     ]">
    <div :style="{
        paddingTop: getUnitVal(getResponsiveVal(el.settings, 'paddingTop', device), getResponsiveVal(el.settings, 'paddingTopUnit', device) || 'px'),
        paddingRight: getUnitVal(getResponsiveVal(el.settings, 'paddingRight', device), getResponsiveVal(el.settings, 'paddingRightUnit', device) || 'px'),
        paddingBottom: getUnitVal(getResponsiveVal(el.settings, 'paddingBottom', device), getResponsiveVal(el.settings, 'paddingBottomUnit', device) || 'px'),
        paddingLeft: getUnitVal(getResponsiveVal(el.settings, 'paddingLeft', device), getResponsiveVal(el.settings, 'paddingLeftUnit', device) || 'px'),
        marginTop: getUnitVal(getResponsiveVal(el.settings, 'marginTop', device), getResponsiveVal(el.settings, 'marginTopUnit', device) || 'px'),
        marginRight: getUnitVal(getResponsiveVal(el.settings, 'marginRight', device), getResponsiveVal(el.settings, 'marginRightUnit', device) || 'px'),
        marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device), getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
        marginLeft: getUnitVal(getResponsiveVal(el.settings, 'marginLeft', device), getResponsiveVal(el.settings, 'marginLeftUnit', device) || 'px'),
    }">
        <a :href="el.settings.useLink && el.settings.linkUrl
                    ? (el.settings.linkUrl.match(/^(https?:\/\/|\/\/|\/|#|tel:|mailto:)/i)
                        ? el.settings.linkUrl
                        : 'https://' + el.settings.linkUrl)
                    : 'javascript:void(0)'"
           :target="el.settings.useLink && el.settings.linkUrl ? (el.settings.linkTarget || '_self') : undefined"
           @mouseenter="el.isHovered = true"
           @mouseleave="el.isHovered = false"
           :style="{
               pointerEvents: el.settings.useLink ? 'auto' : 'none',
               textDecoration: 'none',
               color: el.settings.useLink
                   ? (el.isHovered
                       ? (el.settings.linkHoverColor || el.settings.linkColor || 'inherit')
                       : (el.settings.linkColor || 'inherit'))
                   : 'inherit',
               display: 'block',
               transition: 'color 0.3s ease'
           }">

            <component :is="el.settings.htmlTag || 'h2'"
                @mouseenter="el.isTextHovered = true"
                @mouseleave="el.isTextHovered = false"
                :style="{
                    color: el.settings.useLink ? 'inherit'
                         : (el.settings.useGradient ? 'transparent'
                            : (el.isTextHovered && el.settings.titleHoverColor
                               ? el.settings.titleHoverColor
                               : (el.settings.titleColor || '#222'))),
                    webkitTextFillColor: !el.settings.useLink && el.settings.useGradient ? 'transparent' : undefined,
                    backgroundImage: !el.settings.useLink && el.settings.useGradient
                        ? 'linear-gradient(' + (el.settings.gradientAngle || 90) + 'deg, '
                            + (el.settings.gradientStartColor || el.settings.titleColor || '#222') + ', '
                            + (el.settings.gradientEndColor || '#2271b1') + ')'
                        : 'none',
                    webkitBackgroundClip: !el.settings.useLink && el.settings.useGradient ? 'text' : 'unset',
                    backgroundClip: !el.settings.useLink && el.settings.useGradient ? 'text' : 'unset',
                    textAlign: getResponsiveVal(el.settings, 'textAlign', device) || 'center',
                    fontFamily: el.settings.fontFamily || 'inherit',
                    fontSize: /[a-zA-Z%]/.test(String(getResponsiveVal(el.settings, 'fontSize', device) || '')) ? String(getResponsiveVal(el.settings, 'fontSize', device)) : getUnitVal(getResponsiveVal(el.settings, 'fontSize', device) || 36, getResponsiveVal(el.settings, 'fontSizeUnit', device) || 'px'),
                    fontWeight: el.settings.fontWeight || '800',
                    lineHeight: getResponsiveVal(el.settings, 'lineHeight', device) || '1.2',
                    letterSpacing: /[a-zA-Z%]/.test(String(getResponsiveVal(el.settings, 'letterSpacing', device) || '')) ? String(getResponsiveVal(el.settings, 'letterSpacing', device)) : getUnitVal(getResponsiveVal(el.settings, 'letterSpacing', device), getResponsiveVal(el.settings, 'letterSpacingUnit', device) || 'px'),
                    textTransform: el.settings.textTransform || 'none',
                    textShadow: el.settings.textShadow
                        ? (el.settings.textShadowH || 0) + 'px '
                            + (el.settings.textShadowV || 0) + 'px '
                            + (el.settings.textShadowBlur || 0) + 'px '
                            + (el.settings.textShadowColor || 'rgba(0,0,0,0.2)')
                        : 'none',
                    webkitTextStroke: el.settings.textStroke
                        ? (el.settings.textStrokeSize || 1) + 'px ' + (el.settings.textStrokeColor || '#000')
                        : 'none',
                    textOverflow: el.settings.textOverflow || 'initial',
                    whiteSpace: (el.settings.textOverflow === 'ellipsis' || el.settings.textOverflow === 'clip') ? 'nowrap' : 'normal',
                    overflow: (el.settings.textOverflow === 'ellipsis' || el.settings.textOverflow === 'clip') ? 'hidden' : 'visible',
                    margin: '0',
                    transition: 'color 0.3s ease',
                    pointerEvents: 'auto'
                }"
                class="main-title">@{{ el.settings.title || 'Your Awesome Title' }}</component>
        </a>

        <!-- Separator -->
        <div v-if="el.settings.separator && el.settings.separator !== 'none'"
             :style="{
                 display: 'block',
                 width: getUnitVal(el.settings.dividerWidth || 60, 'px'),
                 height: el.settings.separator === 'default'
                     ? getUnitVal(el.settings.dividerHeight || 3, 'px') : '0',
                 backgroundColor: el.settings.separator === 'default'
                     ? (el.settings.separatorColor || '#2271b1') : 'transparent',
                 borderTop: el.settings.separator !== 'default'
                     ? getUnitVal(el.settings.dividerHeight || 3, 'px') + ' ' + el.settings.separator + ' ' + (el.settings.separatorColor || '#2271b1')
                     : 'none',
                 marginTop: getUnitVal(el.settings.separatorSpacing ?? 20, 'px'),
                 marginBottom: '0',
                 marginLeft: getResponsiveVal(el.settings, 'textAlign', device) === 'center' ? 'auto'
                     : (getResponsiveVal(el.settings, 'textAlign', device) === 'right' ? 'auto' : '0'),
                 marginRight: getResponsiveVal(el.settings, 'textAlign', device) === 'right' ? '0'
                     : (getResponsiveVal(el.settings, 'textAlign', device) === 'center' ? 'auto' : '0'),
                 borderRadius: el.settings.separator === 'default' ? '10px' : '0'
             }"
             class="title-divider"></div>
    </div>
</div>
