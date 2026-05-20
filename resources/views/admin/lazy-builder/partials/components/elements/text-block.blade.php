<div v-if="el.type === 'text_block' || el.type === 'special_text'"
     class="element-text-block-wrapper"
     :class="[el.settings.cssClass || '', 'text-block-container-' + el.id]"
     :id="el.settings.cssId || undefined"
     :style="[
         {
            width: '100%',
            maxWidth: '100%',
            paddingTop: (getResponsiveVal(el.settings, 'paddingTop', device) ?? 10) + 'px',
            paddingRight: (getResponsiveVal(el.settings, 'paddingRight', device) ?? 0) + 'px',
            paddingBottom: (getResponsiveVal(el.settings, 'paddingBottom', device) ?? 10) + 'px',
            paddingLeft: (getResponsiveVal(el.settings, 'paddingLeft', device) ?? 0) + 'px',
            marginTop: (getResponsiveVal(el.settings, 'marginTop', device) ?? 0) + 'px',
            marginRight: (getResponsiveVal(el.settings, 'marginRight', device) ?? 0) + 'px',
            marginBottom: (getResponsiveVal(el.settings, 'marginBottom', device) ?? 0) + 'px',
            marginLeft: (getResponsiveVal(el.settings, 'marginLeft', device) ?? 0) + 'px',
         },
         getCanvasVisibilityStyle(el.settings)
     ]">
    <div class="text-block-content"
       v-html="el.settings.content || 'your content is here...'"
       @mouseenter="el.isHovered = true"
       @mouseleave="el.isHovered = false"
       style="margin: 0; width: 100%; transition: color 0.3s ease; display: block;">
    </div>
    <component is="style">
        .text-block-container-@{{ el.id }} .text-block-content {
            text-align: @{{ getResponsiveVal(el.settings, 'textAlign', device) || 'center' }} !important;
            color: @{{ (el.isHovered && el.settings.hoverColor) ? el.settings.hoverColor : (el.settings.color || '#333333') }} !important;
            font-family: @{{ el.settings.fontFamily || 'inherit' }} !important;
            font-size: @{{ getUnitVal(el.settings.fontSize || 16, el.settings.fontSizeUnit || 'px') }} !important;
            font-weight: @{{ el.settings.fontWeight || '400' }} !important;
            line-height: @{{ el.settings.lineHeight || '1.5' }} !important;
            letter-spacing: @{{ (el.settings.letterSpacing || 0) + 'px' }} !important;
            text-transform: @{{ el.settings.textTransform || 'none' }} !important;
            margin: 0 !important;
        }
        .text-block-container-@{{ el.id }} ul { list-style-type: disc !important; margin-left: 20px !important; margin-bottom: 15px !important; }
        .text-block-container-@{{ el.id }} ol { list-style-type: decimal !important; margin-left: 20px !important; margin-bottom: 15px !important; }
        .text-block-container-@{{ el.id }} li { margin-bottom: 5px !important; }
    </component>
</div>
