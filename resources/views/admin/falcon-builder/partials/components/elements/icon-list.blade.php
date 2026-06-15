<div v-if="el.type === 'icon_list'"
     class="element-icon-list w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
         {
             marginTop:    getUnitVal(getResponsiveVal(el.settings, 'marginTop',    device) ?? 0, getResponsiveVal(el.settings, 'marginTopUnit',    device) || 'px'),
             marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) ?? 0, getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
         },
         getCanvasVisibilityStyle(el.settings)
     ]">
    <ul style="list-style:none;padding:0;margin:0;">
        <li v-for="(item, idx) in el.settings.items" :key="item.id || idx"
            :style="{
                display:       'flex',
                alignItems:    'center',
                flexDirection: (el.settings.iconPosition === 'right') ? 'row-reverse' : 'row',
                justifyContent:(el.settings.textAlign === 'center') ? 'center' : (el.settings.iconPosition === 'right' ? ((el.settings.textAlign === 'right') ? 'flex-start' : 'flex-end') : ((el.settings.textAlign === 'right') ? 'flex-end' : 'flex-start')),
                gap:           (el.settings.gap ?? 10) + 'px',
                marginBottom:  idx < el.settings.items.length - 1 ? (el.settings.itemSpacing ?? 10) + 'px' : '0',
            }">
            <i :class="item.icon || el.settings.defaultIcon || 'fa fa-check'"
               :style="{
                   color:      item.iconColor || el.settings.iconColor || '#2271b1',
                   fontSize:   (el.settings.iconSize || 14) + 'px',
                   flexShrink: 0,
                   width:      (el.settings.iconSize || 14) + 'px',
                   textAlign:  'center',
                   lineHeight: el.settings.lineHeight || '1.5',
               }">
            </i>
            <span :style="{
                color:      el.settings.textColor  || '#333333',
                fontSize:   getUnitVal(el.settings.fontSize || 15, el.settings.fontSizeUnit || 'px'),
                fontWeight: el.settings.fontWeight || '400',
                fontFamily: el.settings.fontFamily || 'inherit',
                lineHeight: el.settings.lineHeight || '1.5',
            }">@{{ item.text || 'List item' }}</span>
        </li>
    </ul>
</div>
