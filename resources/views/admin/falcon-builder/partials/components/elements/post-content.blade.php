<div v-if="el.type === 'post_content'"
     class="w-full"
     :style="[
         {
             textAlign:    getResponsiveVal(el.settings, 'textAlign', device) || 'left',
             marginTop:    getUnitVal(getResponsiveVal(el.settings, 'marginTop',    device) ?? 0, getResponsiveVal(el.settings, 'marginTopUnit',    device) || 'px'),
             marginRight:  getUnitVal(getResponsiveVal(el.settings, 'marginRight',  device) ?? 0, getResponsiveVal(el.settings, 'marginRightUnit',  device) || 'px'),
             marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) ?? 0, getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
             marginLeft:   getUnitVal(getResponsiveVal(el.settings, 'marginLeft',   device) ?? 0, getResponsiveVal(el.settings, 'marginLeftUnit',   device) || 'px'),
         },
         getCanvasVisibilityStyle(el.settings)
     ]">

    <p :style="{
            fontFamily:    el.settings.fontFamily || 'inherit',
            fontSize:      getUnitVal(el.settings.fontSize || 13, el.settings.fontSizeUnit || 'px'),
            fontWeight:    el.settings.fontWeight || '400',
            lineHeight:    el.settings.lineHeight || '1.6',
            letterSpacing: /[a-zA-Z%]/.test(String(el.settings.letterSpacing ?? '')) ? String(el.settings.letterSpacing) : ((el.settings.letterSpacing ?? 0) + (el.settings.letterSpacingUnit || 'px')),
            color:         el.settings.color || '#6b7280',
            textTransform: el.settings.textTransform || 'none',
            margin:        '0 0 5px',
            overflow:      'hidden',
            maxHeight:     el.settings.content_display === 'full' ? '4.8em' : '3.0em',
        }">
        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum. Donec in efficitur leo, in commodo felis. Nullam volutpat porta mattis. Proin tincidunt mi quis quam facilisis, et elementum eros venenatis.
    </p>

    <div class="flex items-center gap-1.5 mt-0.5"
         style="font-size:10px;font-weight:700;color:#94a3b8;">
        <template v-if="el.settings.content_display === 'full'">
            <i class="fa fa-paragraph opacity-50"></i>
            <span>Full Content</span>
        </template>
        <template v-else>
            <i class="fa fa-scissors opacity-50"></i>
            <span>@{{ el.settings.excerptLength || 120 }} chars</span>
            <span v-if="el.settings.stripHtml !== false" style="color:#0091ea;opacity:.6;">· Strip HTML</span>
        </template>
    </div>

</div>
