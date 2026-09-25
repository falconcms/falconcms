<div v-if="el.type === 'icon_box'"
     class="w-full"
     :class="['lzib-' + el.id, el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
     }]">

    {{-- Live hover preview (matches the front-end rule): inline styles can't express :hover,
         so the Read More hover colour needs a scoped stylesheet here too — without it the
         setting looked dead in the builder even though the front-end honoured it. --}}
    <component :is="'style'" v-if="el.settings.readMoreText && el.settings.readMoreHoverColor"
               v-text="'.lzib-' + el.id + ' .lazy-icon-box__more:hover{color:' + el.settings.readMoreHoverColor + ' !important;}'"></component>

    {{-- The icon's hover state, by the same means and for the same reason: the normal state is
         an inline style, so only a rule can override it, and only !important can beat it. The
         string is built to match falcon_icon_box_icon_style() declaration for declaration. --}}
    <component :is="'style'" v-if="iconBoxHoverCss(el.settings, '.lzib-' + el.id)"
               v-text="iconBoxHoverCss(el.settings, '.lzib-' + el.id)"></component>

    {{-- Layout: top (stacked) --}}
    <div v-if="!el.settings.layout || el.settings.layout === 'top'"
         class="flex flex-col"
         :style="{
             alignItems: el.settings.alignment === 'left' ? 'flex-start' : el.settings.alignment === 'right' ? 'flex-end' : 'center',
             textAlign: el.settings.alignment || 'center',
         }">

        <div class="lazy-icon-box__icon"
             :style="[iconBoxIconStyle(el.settings), { marginBottom: (el.settings.iconSpacing || 16) + 'px' }]">
            <i :style="iconBoxGlyphStyle(el.settings)" :class="el.settings.icon || 'fas fa-star'"></i>
        </div>

        <div v-if="el.settings.title || el.settings.description || el.settings.readMoreText" style="width:100%;">
            <div v-if="el.settings.title"
                 :style="{
                     width: '100%',
                     fontFamily: el.settings.titleFontFamily || 'inherit',
                     fontSize: getUnitVal(el.settings.titleFontSize || 20, el.settings.titleFontSizeUnit || 'px'),
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
                     fontSize: getUnitVal(el.settings.descFontSize || 14, el.settings.descFontSizeUnit || 'px'),
                     fontWeight: el.settings.descFontWeight || '400',
                     color: el.settings.descColor || '#666666',
                     lineHeight: el.settings.descLineHeight || 1.6,
                     letterSpacing: el.settings.descLetterSpacing || '0px',
                     textTransform: el.settings.descTextTransform || 'none',
                     textAlign: el.settings.alignment || 'center',
                 }">@{{ el.settings.description }}</div>
            <div v-if="el.settings.readMoreText"
                 class="lazy-icon-box__more"
                 :style="{
                     fontFamily: el.settings.readMoreFontFamily || 'inherit',
                     fontSize: getUnitVal(el.settings.readMoreFontSize || 13, el.settings.readMoreFontSizeUnit || 'px'),
                     fontWeight: el.settings.readMoreFontWeight || '600',
                     color: el.settings.readMoreColor || el.settings.iconColor || '#2271b1',
                     lineHeight: el.settings.readMoreLineHeight || 1.4,
                     letterSpacing: el.settings.readMoreLetterSpacing || '0px',
                     textTransform: el.settings.readMoreTextTransform || 'none',
                     marginTop: (el.settings.readMoreSpacing ?? 12) + 'px',
                     display:'inline-flex', alignItems:'center',
                     gap: (el.settings.readMoreArrowGap ?? 6) + 'px',
                 }">@{{ el.settings.readMoreText }} <span v-if="el.settings.readMoreArrow !== false">&rarr;</span></div>
        </div>

        <div v-else class="text-slate-400 text-[11px] font-medium py-1">Icon Box</div>
    </div>

    {{-- Layout: left or right --}}
    <div v-else
         class="flex gap-4 items-start w-full"
         :style="{ flexDirection: el.settings.layout === 'right' ? 'row-reverse' : 'row' }">

        <div class="flex-shrink-0 lazy-icon-box__icon" :style="iconBoxIconStyle(el.settings)">
            <i :style="iconBoxGlyphStyle(el.settings)" :class="el.settings.icon || 'fas fa-star'"></i>
        </div>

        <div class="flex-1">
            <div v-if="el.settings.title"
                 :style="{
                     fontFamily: el.settings.titleFontFamily || 'inherit',
                     fontSize: getUnitVal(el.settings.titleFontSize || 20, el.settings.titleFontSizeUnit || 'px'),
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
                     fontSize: getUnitVal(el.settings.descFontSize || 14, el.settings.descFontSizeUnit || 'px'),
                     fontWeight: el.settings.descFontWeight || '400',
                     color: el.settings.descColor || '#666666',
                     lineHeight: el.settings.descLineHeight || 1.6,
                     letterSpacing: el.settings.descLetterSpacing || '0px',
                     textTransform: el.settings.descTextTransform || 'none',
                 }">@{{ el.settings.description }}</div>
            <div v-if="el.settings.readMoreText"
                 class="lazy-icon-box__more"
                 :style="{
                     fontFamily: el.settings.readMoreFontFamily || 'inherit',
                     fontSize: getUnitVal(el.settings.readMoreFontSize || 13, el.settings.readMoreFontSizeUnit || 'px'),
                     fontWeight: el.settings.readMoreFontWeight || '600',
                     color: el.settings.readMoreColor || el.settings.iconColor || '#2271b1',
                     lineHeight: el.settings.readMoreLineHeight || 1.4,
                     letterSpacing: el.settings.readMoreLetterSpacing || '0px',
                     textTransform: el.settings.readMoreTextTransform || 'none',
                     marginTop: (el.settings.readMoreSpacing ?? 12) + 'px',
                     display:'inline-flex', alignItems:'center',
                     gap: (el.settings.readMoreArrowGap ?? 6) + 'px',
                 }">@{{ el.settings.readMoreText }} <span v-if="el.settings.readMoreArrow !== false">&rarr;</span></div>
        </div>
    </div>
</div>
