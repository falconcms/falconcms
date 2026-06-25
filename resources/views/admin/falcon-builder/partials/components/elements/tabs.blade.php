<div v-if="el.type === 'tabs'"
     class="w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
     }]">

    <template v-if="el.settings.items && el.settings.items.length">

        {{-- Tab Nav --}}
        <div :style="{
                 display: 'flex',
                 justifyContent: el.settings.alignment === 'center' ? 'center' : el.settings.alignment === 'right' ? 'flex-end' : 'flex-start',
                 borderBottom: (!el.settings.style || el.settings.style === 'underline') ? ('2px solid ' + (el.settings.borderColor || '#e2e8f0')) : (el.settings.style === 'boxed' ? ('1px solid ' + (el.settings.borderColor || '#e2e8f0')) : 'none'),
                 flexWrap: 'wrap',
                 gap: el.settings.style === 'pill' ? '6px' : '0',
                 padding: el.settings.style === 'pill' ? '4px 0' : '0',
             }">
            <div v-for="(item, idx) in el.settings.items" :key="item.id || idx"
                 @click.stop="el.settings.defaultActive = idx"
                 :style="{
                     padding: '8px 16px',
                     fontSize: getUnitVal(el.settings.tabFontSize || 14, el.settings.tabFontSizeUnit || 'px'),
                     fontWeight: el.settings.tabFontWeight || '500',
                     fontFamily: el.settings.tabFontFamily || 'inherit',
                     letterSpacing: el.settings.tabLetterSpacing || '0px',
                     cursor: 'pointer',
                     userSelect: 'none',
                     transition: 'all 0.2s',
                     color: el.settings.style === 'pill' && idx === (el.settings.defaultActive ?? 0)
                         ? '#ffffff'
                         : (idx === (el.settings.defaultActive ?? 0) ? (el.settings.activeColor || '#2271b1') : (el.settings.tabColor || '#666666')),
                     borderBottom: (!el.settings.style || el.settings.style === 'underline')
                         ? (idx === (el.settings.defaultActive ?? 0)
                             ? '2px solid ' + (el.settings.activeColor || '#2271b1')
                             : '2px solid transparent')
                         : 'none',
                     marginBottom: (!el.settings.style || el.settings.style === 'underline') ? '-2px' : '0',
                     backgroundColor: el.settings.style === 'pill'
                         ? (idx === (el.settings.defaultActive ?? 0) ? (el.settings.activeColor || '#2271b1') : 'transparent')
                         : (el.settings.style === 'boxed' ? (idx === (el.settings.defaultActive ?? 0) ? (el.settings.contentBgColor || '#ffffff') : 'transparent') : 'transparent'),
                     borderRadius: el.settings.style === 'pill' ? '999px' : (el.settings.style === 'boxed' ? ((el.settings.borderRadius ?? 4) + 'px ' + (el.settings.borderRadius ?? 4) + 'px 0 0') : '0'),
                     border: el.settings.style === 'boxed'
                         ? (idx === (el.settings.defaultActive ?? 0)
                             ? '1px solid ' + (el.settings.borderColor || '#e2e8f0')
                             : '1px solid transparent')
                         : (el.settings.style === 'pill' ? 'none' : 'none'),
                 }">
                @{{ item.label || 'Tab ' + (idx + 1) }}
            </div>
        </div>

        {{-- Tab Content --}}
        <template v-for="(item, idx) in el.settings.items" :key="'panel-' + (item.id || idx)">
            <div v-if="idx === (el.settings.defaultActive ?? 0)"
                 :style="{
                     padding: (el.settings.contentPadding ?? 20) + 'px',
                     fontSize: getUnitVal(el.settings.contentFontSize || 14, el.settings.contentFontSizeUnit || 'px'),
                     fontFamily: el.settings.contentFontFamily || 'inherit',
                     letterSpacing: el.settings.contentLetterSpacing || '0px',
                     lineHeight: el.settings.contentLineHeight || 1.6,
                     color: el.settings.contentColor || '#555555',
                     backgroundColor: el.settings.contentBgColor || '#ffffff',
                     border: '1px solid ' + (el.settings.borderColor || '#e2e8f0'),
                     borderTop: (!el.settings.style || el.settings.style === 'underline') ? 'none' : ('1px solid ' + (el.settings.borderColor || '#e2e8f0')),
                     borderRadius: el.settings.style === 'pill' ? (el.settings.borderRadius ?? 4) + 'px' : '0 0 ' + (el.settings.borderRadius ?? 4) + 'px ' + (el.settings.borderRadius ?? 4) + 'px',
                 }"
                 v-safe-html="item.content || '<p style=\'color:#aaa;font-style:italic;\'>No content yet.</p>'">
            </div>
        </template>

    </template>

    <div v-else class="text-slate-400 text-[11px] font-medium py-3 px-4 bg-slate-50 border border-dashed border-slate-200 rounded text-center">Tabs</div>

</div>
