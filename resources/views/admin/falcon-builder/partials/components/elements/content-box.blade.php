{{-- Content Box — canvas preview. Mirrors frontend/builder/elements/content-box.blade.php;
     any value changed there has to change here too (the two renderers are independent).
     Grid + timeline rails and the Read More hover need real CSS rules, so they are injected
     as a scoped stylesheet — inline styles can't express :hover, ::first-child or media queries. --}}
<div v-if="el.type === 'content_box'"
     class="element-content-box w-full"
     :class="['fcb-c-' + el.id, el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[
         {
             marginTop:    getUnitVal(getResponsiveVal(el.settings, 'marginTop',    device) || 0, getResponsiveVal(el.settings, 'marginTopUnit',    device) || 'px'),
             marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) || 0, getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
             // The canvas wraps every element in line-height:0. Children that don't set their own
             // (the icon wrapper, the Read More link) then get a zero-height line box and spill
             // outside the item's padding and border. The front-end inherits the theme's line
             // height instead, which is why it looked right there and broken here.
             lineHeight: 'normal',
         },
         getCanvasVisibilityStyle(el.settings)
     ]">

    <component :is="'style'" v-text="contentBoxCss(el)"></component>

    <div class="fcb-grid" :style="{
            display: 'grid',
            gridTemplateColumns: 'repeat(' + contentBoxCols(el) + ', minmax(0, 1fr))',
            columnGap: (el.settings.columnGap ?? 30) + 'px',
            rowGap: (el.settings.rowGap ?? 30) + 'px',
         }">
        <div v-for="(item, idx) in (el.settings.items || [])" :key="item.id || idx"
             class="fcb-item"
             :style="[contentBoxItemStyle(el, item), contentBoxItemLayoutStyle(el)]">

            {{-- Timeline vertical: rail column, then the body --}}
            <template v-if="el.settings.boxLayout === 'timeline-vertical'">
                <div class="fcb-rail" :style="{ position:'relative', flex:'0 0 ' + contentBoxDot(el) + 'px', width: contentBoxDot(el) + 'px', alignSelf:'stretch' }">
                    <span class="fcb-dot" :style="contentBoxDotStyle(el)">
                        <i v-if="item.icon && !item.image" :class="item.icon" :style="contentBoxDotIcon(el, item)"></i>
                        <img v-else-if="item.image" :src="item.image" :style="contentBoxDotIcon(el, item)">
                    </span>
                    <span class="fcb-line"></span>
                </div>
                <div :style="{ flex:'1 1 auto', minWidth:0, textAlign: el.settings.alignment || 'left' }">
                    <component :is="el.settings.titleTag || 'h3'" v-if="item.title" class="fcb-title" :style="contentBoxTitleStyle(el)">@{{ item.title }}</component>
                    <div v-if="item.content" class="fcb-content" :style="contentBoxContentStyle(el)" v-safe-html="item.content"></div>
                    <span v-if="item.linkText" class="fcb-more" :style="contentBoxMoreStyle(el)">@{{ item.linkText }} <span v-if="el.settings.linkArrow !== false">&rarr;</span></span>
                </div>
            </template>

            {{-- Timeline horizontal: rail across the top, body underneath --}}
            <template v-else-if="el.settings.boxLayout === 'timeline-horizontal'">
                <div class="fcb-rail-h" :style="{ display:'flex', alignItems:'center', marginBottom:(el.settings.timelineGap ?? 22)+'px' }">
                    <span class="fcb-seg fcb-seg-l" style="flex:1 1 auto"></span>
                    <span class="fcb-dot" :style="[contentBoxDotStyle(el), { flex:'0 0 auto' }]">
                        <i v-if="item.icon && !item.image" :class="item.icon" :style="contentBoxDotIcon(el, item)"></i>
                        <img v-else-if="item.image" :src="item.image" :style="contentBoxDotIcon(el, item)">
                    </span>
                    <span class="fcb-seg fcb-seg-r" style="flex:1 1 auto"></span>
                </div>
                <component :is="el.settings.titleTag || 'h3'" v-if="item.title" class="fcb-title" :style="contentBoxTitleStyle(el)">@{{ item.title }}</component>
                <div v-if="item.content" class="fcb-content" :style="contentBoxContentStyle(el)" v-safe-html="item.content"></div>
                <span v-if="item.linkText" class="fcb-more" :style="contentBoxMoreStyle(el)">@{{ item.linkText }} <span v-if="el.settings.linkArrow !== false">&rarr;</span></span>
            </template>

            {{-- Classic icon on side --}}
            <template v-else-if="el.settings.boxLayout === 'classic-side'">
                <div v-if="item.icon || item.image" class="fcb-icon" :style="contentBoxIconWrapStyle(el)">
                    <i v-if="item.icon && !item.image" :class="item.icon" :style="contentBoxIconStyle(el, item)"></i>
                    <img v-else-if="item.image" :src="item.image" :style="{ width:(el.settings.iconSize||32)+'px', height:(el.settings.iconSize||32)+'px', objectFit:'contain', display:'block' }">
                </div>
                <div :style="{ flex:'1 1 auto', minWidth:0, textAlign: el.settings.alignment || 'left' }">
                    <component :is="el.settings.titleTag || 'h3'" v-if="item.title" class="fcb-title" :style="contentBoxTitleStyle(el)">@{{ item.title }}</component>
                    <div v-if="item.content" class="fcb-content" :style="contentBoxContentStyle(el)" v-safe-html="item.content"></div>
                    <span v-if="item.linkText" class="fcb-more" :style="contentBoxMoreStyle(el)">@{{ item.linkText }} <span v-if="el.settings.linkArrow !== false">&rarr;</span></span>
                </div>
            </template>

            {{-- Classic icon with title (icon inline with the heading) --}}
            <template v-else-if="el.settings.boxLayout === 'classic-title'">
                <div :style="{ display:'flex', alignItems:'center', gap:(el.settings.iconSpacing ?? 16)+'px', justifyContent: contentBoxFlexAlign(el), marginBottom:(el.settings.titleSpacing ?? 10)+'px' }">
                    <div v-if="item.icon || item.image" class="fcb-icon" :style="contentBoxIconWrapStyle(el)">
                        <i v-if="item.icon && !item.image" :class="item.icon" :style="contentBoxIconStyle(el, item)"></i>
                        <img v-else-if="item.image" :src="item.image" :style="{ width:(el.settings.iconSize||32)+'px', height:(el.settings.iconSize||32)+'px', objectFit:'contain', display:'block' }">
                    </div>
                    <component :is="el.settings.titleTag || 'h3'" v-if="item.title" class="fcb-title" :style="[contentBoxTitleStyle(el), { margin: 0 }]">@{{ item.title }}</component>
                </div>
                <div v-if="item.content" class="fcb-content" :style="contentBoxContentStyle(el)" v-safe-html="item.content"></div>
                <span v-if="item.linkText" class="fcb-more" :style="contentBoxMoreStyle(el)">@{{ item.linkText }} <span v-if="el.settings.linkArrow !== false">&rarr;</span></span>
            </template>

            {{-- Clean layout horizontal: title column | content column --}}
            <template v-else-if="el.settings.boxLayout === 'clean-horizontal'">
                <div :style="{ flex:'0 0 34%', maxWidth:'34%', textAlign: el.settings.alignment || 'left' }">
                    <div v-if="item.icon || item.image" class="fcb-icon" :style="[contentBoxIconWrapStyle(el), { marginBottom:(el.settings.iconSpacing ?? 16)+'px' }]">
                        <i v-if="item.icon && !item.image" :class="item.icon" :style="contentBoxIconStyle(el, item)"></i>
                        <img v-else-if="item.image" :src="item.image" :style="{ width:(el.settings.iconSize||32)+'px', height:(el.settings.iconSize||32)+'px', objectFit:'contain', display:'block' }">
                    </div>
                    <component :is="el.settings.titleTag || 'h3'" v-if="item.title" class="fcb-title" :style="[contentBoxTitleStyle(el), { margin: 0 }]">@{{ item.title }}</component>
                </div>
                <div :style="{ flex:'1 1 auto', minWidth:0, textAlign: el.settings.alignment || 'left' }">
                    <div v-if="item.content" class="fcb-content" :style="contentBoxContentStyle(el)" v-safe-html="item.content"></div>
                    <span v-if="item.linkText" class="fcb-more" :style="contentBoxMoreStyle(el)">@{{ item.linkText }} <span v-if="el.settings.linkArrow !== false">&rarr;</span></span>
                </div>
            </template>

            {{-- Classic on top / boxed / clean vertical: stacked --}}
            <template v-else>
                <div v-if="(item.icon || item.image) && el.settings.boxLayout !== 'clean-vertical'" class="fcb-icon"
                     :style="[contentBoxIconWrapStyle(el), { marginBottom:(el.settings.iconSpacing ?? 16)+'px' }]">
                    <i v-if="item.icon && !item.image" :class="item.icon" :style="contentBoxIconStyle(el, item)"></i>
                    <img v-else-if="item.image" :src="item.image" :style="{ width:(el.settings.iconSize||32)+'px', height:(el.settings.iconSize||32)+'px', objectFit:'contain', display:'block' }">
                </div>
                <div style="width:100%">
                    <component :is="el.settings.titleTag || 'h3'" v-if="item.title" class="fcb-title" :style="contentBoxTitleStyle(el)">@{{ item.title }}</component>
                    <div v-if="item.content" class="fcb-content" :style="contentBoxContentStyle(el)" v-safe-html="item.content"></div>
                    <span v-if="item.linkText" class="fcb-more" :style="contentBoxMoreStyle(el)">@{{ item.linkText }} <span v-if="el.settings.linkArrow !== false">&rarr;</span></span>
                </div>
            </template>
        </div>

        <div v-if="!el.settings.items || !el.settings.items.length"
             class="text-slate-400 text-[11px] font-medium py-4 text-center border border-dashed border-slate-200 rounded">
            Content Box — add an item in the settings panel
        </div>
    </div>
</div>
