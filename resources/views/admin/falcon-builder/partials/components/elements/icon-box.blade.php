<div v-if="el.type === 'icon_box'"
     class="w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
     }]">

    {{-- Layout: top (stacked) --}}
    <div v-if="!el.settings.layout || el.settings.layout === 'top'"
         class="flex flex-col"
         :style="{
             alignItems: el.settings.alignment === 'left' ? 'flex-start' : el.settings.alignment === 'right' ? 'flex-end' : 'center',
             textAlign: el.settings.alignment || 'center',
         }">

        <div :style="{
                 display: 'inline-flex',
                 alignItems: 'center',
                 justifyContent: 'center',
                 boxSizing: 'content-box',
                 width:  el.settings.iconBgColor ? ((el.settings.iconSize || 40) * 2) + 'px' : 'auto',
                 height: el.settings.iconBgColor ? ((el.settings.iconSize || 40) * 2) + 'px' : 'auto',
                 backgroundColor: el.settings.iconBgColor ? hexToRgba(el.settings.iconBgColor, el.settings.iconBgColorOpacity) : 'transparent',
                 borderRadius: (el.settings.iconBorderRadius ?? 50) + 'px',
                 padding: el.settings.iconBgColor ? (el.settings.iconPadding || 0) + 'px' : undefined,
                 marginBottom: (el.settings.iconSpacing || 16) + 'px',
             }">
            <i :class="el.settings.icon || 'fas fa-star'"
               :style="{
                   fontSize: (el.settings.iconSize || 40) + (el.settings.iconSizeUnit || 'px'),
                   color: el.settings.iconColor || '#2271b1',
               }"></i>
        </div>

        <div v-if="el.settings.title || el.settings.description" style="width:100%;">
            <div v-if="el.settings.title"
                 :style="{
                     width: '100%',
                     fontFamily: el.settings.titleFontFamily || 'inherit',
                     fontSize: (el.settings.titleFontSize || 20) + (el.settings.titleFontSizeUnit || 'px'),
                     fontWeight: el.settings.titleFontWeight || '600',
                     color: el.settings.titleColor || '#222222',
                     marginBottom: (el.settings.titleSpacing ?? 8) + 'px',
                     lineHeight: el.settings.titleLineHeight || 1.3,
                     letterSpacing: el.settings.titleLetterSpacing || '0px',
                     textTransform: el.settings.titleTextTransform || 'none',
                     textAlign: el.settings.alignment || 'center',
                 }">@{{ el.settings.title }}</div>
            <div v-if="el.settings.description"
                 :style="{
                     width: '100%',
                     fontFamily: el.settings.descFontFamily || 'inherit',
                     fontSize: (el.settings.descFontSize || 14) + (el.settings.descFontSizeUnit || 'px'),
                     fontWeight: el.settings.descFontWeight || '400',
                     color: el.settings.descColor || '#666666',
                     lineHeight: el.settings.descLineHeight || 1.6,
                     letterSpacing: el.settings.descLetterSpacing || '0px',
                     textTransform: el.settings.descTextTransform || 'none',
                     textAlign: el.settings.alignment || 'center',
                 }">@{{ el.settings.description }}</div>
        </div>

        <div v-else class="text-slate-400 text-[11px] font-medium py-1">Icon Box</div>
    </div>

    {{-- Layout: left or right --}}
    <div v-else
         class="flex gap-4 items-start w-full"
         :style="{ flexDirection: el.settings.layout === 'right' ? 'row-reverse' : 'row' }">

        <div class="flex-shrink-0"
             :style="{
                 display: 'inline-flex',
                 alignItems: 'center',
                 justifyContent: 'center',
                 boxSizing: 'content-box',
                 width:  el.settings.iconBgColor ? ((el.settings.iconSize || 40) * 2) + 'px' : 'auto',
                 height: el.settings.iconBgColor ? ((el.settings.iconSize || 40) * 2) + 'px' : 'auto',
                 backgroundColor: el.settings.iconBgColor ? hexToRgba(el.settings.iconBgColor, el.settings.iconBgColorOpacity) : 'transparent',
                 borderRadius: (el.settings.iconBorderRadius ?? 50) + 'px',
                 padding: el.settings.iconBgColor ? (el.settings.iconPadding || 0) + 'px' : undefined,
             }">
            <i :class="el.settings.icon || 'fas fa-star'"
               :style="{
                   fontSize: (el.settings.iconSize || 40) + (el.settings.iconSizeUnit || 'px'),
                   color: el.settings.iconColor || '#2271b1',
               }"></i>
        </div>

        <div class="flex-1">
            <div v-if="el.settings.title"
                 :style="{
                     fontFamily: el.settings.titleFontFamily || 'inherit',
                     fontSize: (el.settings.titleFontSize || 20) + (el.settings.titleFontSizeUnit || 'px'),
                     fontWeight: el.settings.titleFontWeight || '600',
                     color: el.settings.titleColor || '#222222',
                     marginBottom: (el.settings.titleSpacing ?? 8) + 'px',
                     lineHeight: el.settings.titleLineHeight || 1.3,
                     letterSpacing: el.settings.titleLetterSpacing || '0px',
                     textTransform: el.settings.titleTextTransform || 'none',
                 }">@{{ el.settings.title }}</div>
            <div v-if="el.settings.description"
                 :style="{
                     fontFamily: el.settings.descFontFamily || 'inherit',
                     fontSize: (el.settings.descFontSize || 14) + (el.settings.descFontSizeUnit || 'px'),
                     fontWeight: el.settings.descFontWeight || '400',
                     color: el.settings.descColor || '#666666',
                     lineHeight: el.settings.descLineHeight || 1.6,
                     letterSpacing: el.settings.descLetterSpacing || '0px',
                     textTransform: el.settings.descTextTransform || 'none',
                 }">@{{ el.settings.description }}</div>
        </div>
    </div>
</div>
