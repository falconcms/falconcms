<div v-if="el.type === 'accordion'"
     class="w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
     }]">

    <template v-if="el.settings.items && el.settings.items.length">
        <div :style="{ display: 'flex', flexDirection: 'column', gap: (el.settings.itemGap ?? 8) + 'px' }">
            <div v-for="(item, idx) in el.settings.items" :key="item.id || idx">

                {{-- Header --}}
                <div @click.stop="el.settings.defaultOpen = (el.settings.defaultOpen === idx ? -1 : idx)"
                     :style="{
                         display: 'flex',
                         alignItems: 'center',
                         justifyContent: 'space-between',
                         padding: (el.settings.titlePadding ?? 16) + 'px',
                         fontSize: getUnitVal(el.settings.titleFontSize || 15, el.settings.titleFontSizeUnit || 'px'),
                         fontWeight: el.settings.titleFontWeight || '600',
                         fontFamily: el.settings.titleFontFamily || 'inherit',
                         letterSpacing: el.settings.titleLetterSpacing || '0px',
                         lineHeight: el.settings.titleLineHeight || 1.4,
                         textTransform: el.settings.titleTextTransform || 'none',
                         color: idx === (el.settings.defaultOpen ?? 0) ? (el.settings.titleActiveColor || '#ffffff') : (el.settings.titleColor || '#222222'),
                         backgroundColor: idx === (el.settings.defaultOpen ?? 0) ? (el.settings.titleActiveBgColor || '#2271b1') : (el.settings.titleBgColor || '#f8fafc'),
                         border: '1px solid ' + (el.settings.borderColor || '#e2e8f0'),
                         borderRadius: idx === (el.settings.defaultOpen ?? 0)
                             ? (el.settings.borderRadius ?? 8) + 'px ' + (el.settings.borderRadius ?? 8) + 'px 0 0'
                             : (el.settings.borderRadius ?? 8) + 'px',
                         cursor: 'pointer',
                         userSelect: 'none',
                     }">
                    <span v-if="el.settings.iconPosition === 'left'" :style="{ marginRight: '10px', flexShrink: 0 }">
                        <i v-if="el.settings.iconType === 'chevron'"
                           :class="idx === (el.settings.defaultOpen ?? 0) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                        <i v-else
                           :class="idx === (el.settings.defaultOpen ?? 0) ? 'fas fa-minus' : 'fas fa-plus'"></i>
                    </span>
                    <span style="flex:1;">@{{ item.title || 'Item ' + (idx + 1) }}</span>
                    <span v-if="!el.settings.iconPosition || el.settings.iconPosition === 'right'" :style="{ marginLeft: '10px', flexShrink: 0 }">
                        <i v-if="el.settings.iconType === 'chevron'"
                           :class="idx === (el.settings.defaultOpen ?? 0) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                        <i v-else
                           :class="idx === (el.settings.defaultOpen ?? 0) ? 'fas fa-minus' : 'fas fa-plus'"></i>
                    </span>
                </div>

                {{-- Content (open item only) --}}
                <div v-if="idx === (el.settings.defaultOpen ?? 0)"
                     :style="{
                         padding: (el.settings.contentPadding ?? 16) + 'px',
                         fontSize: getUnitVal(el.settings.contentFontSize || 14, el.settings.contentFontSizeUnit || 'px'),
                         fontFamily: el.settings.contentFontFamily || 'inherit',
                         letterSpacing: el.settings.contentLetterSpacing || '0px',
                         lineHeight: el.settings.contentLineHeight || 1.6,
                         color: el.settings.contentColor || '#555555',
                         backgroundColor: el.settings.contentBgColor || '#ffffff',
                         border: '1px solid ' + (el.settings.borderColor || '#e2e8f0'),
                         borderTop: 'none',
                         borderRadius: '0 0 ' + (el.settings.borderRadius ?? 8) + 'px ' + (el.settings.borderRadius ?? 8) + 'px',
                     }"
                     v-safe-html="item.content || '<p style=\'color:#aaa;font-style:italic;\'>No content yet.</p>'">
                </div>

            </div>
        </div>
    </template>

    <div v-else class="text-slate-400 text-[11px] font-medium py-3 px-4 bg-slate-50 border border-dashed border-slate-200 rounded text-center">Accordion</div>

</div>
