<div v-if="el.type === 'product_meta'"
     class="element-product-meta w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
         {
             width:          '100%',
             marginTop:      getUnitVal(el.settings.marginTop    ?? 0, el.settings.marginTopUnit    || 'px'),
             marginBottom:   getUnitVal(el.settings.marginBottom ?? 0, el.settings.marginBottomUnit || 'px'),
             fontSize:       getUnitVal(el.settings.fontSize ?? 14, el.settings.fontSizeUnit || 'px'),
             fontWeight:     el.settings.fontWeight || '400',
             lineHeight:     'normal',
             display:        'flex',
             flexWrap:       'wrap',
             flexDirection:  (el.settings.layout === 'inline') ? 'row' : 'column',
             gap:            (el.settings.gap ?? 8) + (el.settings.gapUnit || 'px'),
             alignItems:     (el.settings.layout === 'inline')
                                 ? 'center'
                                 : ({ left: 'flex-start', center: 'center', right: 'flex-end' }[el.settings.metaAlign || 'left'] || 'flex-start'),
             justifyContent: (el.settings.layout === 'inline')
                                 ? ({ left: 'flex-start', center: 'center', right: 'flex-end' }[el.settings.metaAlign || 'left'] || 'flex-start')
                                 : 'flex-start',
         },
         getCanvasVisibilityStyle(el.settings)
     ]">

    <template v-for="(row, i) in pmCanvasRows(el.settings)" :key="i">
        <div class="fpm-row" style="display:flex;align-items:center;gap:6px;">
            <span v-if="el.settings.showLabels !== false" :style="{ color: el.settings.labelColor || '#6b7280' }">@{{ row.label }}</span>
            <span :style="{ color: row.color, fontWeight: row.weight }">@{{ row.value }}</span>
        </div>
        <span v-if="el.settings.layout === 'inline' && (el.settings.separator || '') !== '' && i < pmCanvasRows(el.settings).length - 1"
              aria-hidden="true" :style="{ color: el.settings.labelColor || '#6b7280', opacity: .6 }">@{{ el.settings.separator }}</span>
    </template>

    <span v-if="!pmCanvasRows(el.settings).length" style="opacity:.4;font-style:italic;">Product meta — enable a field…</span>
</div>
