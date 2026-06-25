<div v-if="el.type === 'ticker'"
     class="element-ticker w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
         {
             marginTop:    getUnitVal(el.settings.marginTop    ?? 0, el.settings.marginTopUnit    || 'px'),
             marginBottom: getUnitVal(el.settings.marginBottom ?? 0, el.settings.marginBottomUnit || 'px'),
         },
         getCanvasVisibilityStyle(el.settings)
     ]">

    <div :style="{
             width:        '100%',
             boxSizing:    'border-box',
             display:      'flex',
             alignItems:   'center',
             overflow:     'hidden',
             background:   el.settings.bgColor     || '#1e3a8a',
             color:        el.settings.textColor    || '#ffffff',
             fontSize:     getUnitVal(el.settings.fontSize || 14, el.settings.fontSizeUnit || 'px'),
             lineHeight:   'normal',
             fontWeight:   el.settings.fontWeight   || '500',
             height:       (el.settings.height ?? 44) + 'px',
             borderRadius: (el.settings.borderRadius ?? 0) + 'px',
             position:     'relative',
         }">

        {{-- Label badge --}}
        <div v-if="el.settings.label"
             class="lztick-label"
             :class="'lztick-la-' + (el.settings.labelAnimation || 'blink-dot')"
             :style="{
                 background:    el.settings.labelBgColor   || '#ef4444',
                 color:         el.settings.labelTextColor  || '#ffffff',
                 padding:       '0 14px',
                 height:        '100%',
                 display:       'flex',
                 alignItems:    'center',
                 fontWeight:    '700',
                 whiteSpace:    'nowrap',
                 flexShrink:    '0',
                 fontSize:      getUnitVal(el.settings.fontSize || 14, el.settings.fontSizeUnit || 'px'),
                 textTransform: 'uppercase',
                 letterSpacing: '.03em',
             }">
            @{{ el.settings.label }}
        </div>

        {{-- Canvas preview: seamless loop (two copies, wrapper animates 0%→-50%) so text is always visible.
             Use a capped fast speed so editors see motion immediately without waiting. --}}
        <div style="flex:1;overflow:hidden;height:100%;">
            <template v-if="el.settings.items && el.settings.items.filter(i => i.text).length">
                <div :class="['lztick-w-' + el.id, 'lztick-te-' + (el.settings.textEffect || 'none')]"
                     :style="{
                         display: 'inline-flex', alignItems: 'center', height: '100%', whiteSpace: 'nowrap',
                         willChange: 'transform',
                         animation: 'lztick-canvas-' + (el.settings.direction === 'right' ? 'right' : 'left')
                                  + ' ' + Math.min(15, Math.max(3, 105 - (el.settings.speed ?? 50))) + 's linear infinite'
                     }">
                    {{-- Copy 1 --}}
                    <span style="flex-shrink:0;display:inline-flex;align-items:center;white-space:nowrap;padding-right:40px;height:100%;">
                        <template v-for="(item, idx) in el.settings.items.filter(i => i.text)" :key="'a-'+idx">
                            <span style="white-space:nowrap;">@{{ item.text }}</span>
                            <template v-if="idx < el.settings.items.filter(i => i.text).length - 1">
                                <span v-if="el.settings.separator === 'dance'" class="lztick-sep-dance" aria-hidden="true" :style="{ margin: '0 ' + (el.settings.itemSpacing ?? 12) + 'px' }">
                                    <span></span><span></span><span></span>
                                </span>
                                <span v-else :style="{ opacity: .5, margin: '0 ' + (el.settings.itemSpacing ?? 12) + 'px' }">@{{ el.settings.separator !== '' ? (el.settings.separator || '•') : '' }}</span>
                            </template>
                        </template>
                    </span>
                    {{-- Copy 2 for seamless loop --}}
                    <span aria-hidden="true" style="flex-shrink:0;display:inline-flex;align-items:center;white-space:nowrap;padding-right:40px;height:100%;">
                        <template v-for="(item, idx) in el.settings.items.filter(i => i.text)" :key="'b-'+idx">
                            <span style="white-space:nowrap;">@{{ item.text }}</span>
                            <template v-if="idx < el.settings.items.filter(i => i.text).length - 1">
                                <span v-if="el.settings.separator === 'dance'" class="lztick-sep-dance" aria-hidden="true" :style="{ margin: '0 ' + (el.settings.itemSpacing ?? 12) + 'px' }">
                                    <span></span><span></span><span></span>
                                </span>
                                <span v-else :style="{ opacity: .5, margin: '0 ' + (el.settings.itemSpacing ?? 12) + 'px' }">@{{ el.settings.separator !== '' ? (el.settings.separator || '•') : '' }}</span>
                            </template>
                        </template>
                    </span>
                </div>
            </template>
            <span v-else style="opacity:.4;font-style:italic;padding:0 16px;display:flex;align-items:center;height:100%;">Add ticker items…</span>
        </div>
    </div>
</div>
