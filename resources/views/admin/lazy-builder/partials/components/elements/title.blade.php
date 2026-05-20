<div v-if="el.type === 'title'"
     class="element-title-wrapper"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
         { textAlign: getResponsiveVal(el.settings, 'textAlign', device) || 'center', width: '100%' },
         getCanvasVisibilityStyle(el.settings)
     ]">
    <div :style="{
        paddingTop: getUnitVal(el.settings.paddingTop, el.settings.paddingTopUnit),
        paddingBottom: getUnitVal(el.settings.paddingBottom, el.settings.paddingBottomUnit),
        marginTop: getUnitVal(getResponsiveVal(el.settings, 'marginTop', device), 'px'),
        marginRight: getUnitVal(getResponsiveVal(el.settings, 'marginRight', device), 'px'),
        marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device), 'px'),
        marginLeft: getUnitVal(getResponsiveVal(el.settings, 'marginLeft', device), 'px'),
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
                            + (el.settings.gradientEndColor || '#0091ea') + ')'
                        : 'none',
                    webkitBackgroundClip: !el.settings.useLink && el.settings.useGradient ? 'text' : 'unset',
                    backgroundClip: !el.settings.useLink && el.settings.useGradient ? 'text' : 'unset',
                    textAlign: getResponsiveVal(el.settings, 'textAlign', device) || 'center',
                    fontFamily: el.settings.fontFamily || 'inherit',
                    fontSize: getUnitVal(el.settings.fontSize || 36, el.settings.fontSizeUnit || 'px'),
                    fontWeight: el.settings.fontWeight || '800',
                    lineHeight: el.settings.lineHeight || '1.2',
                    letterSpacing: getUnitVal(el.settings.letterSpacing, 'px'),
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
                    transition: 'color 0.3s ease'
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
                     ? (el.settings.separatorColor || '#0091ea') : 'transparent',
                 borderTop: el.settings.separator !== 'default'
                     ? getUnitVal(el.settings.dividerHeight || 3, 'px') + ' ' + el.settings.separator + ' ' + (el.settings.separatorColor || '#0091ea')
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
