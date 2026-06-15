<div v-if="el.type === 'text'" class="element-text" :class="getVisibilityClasses(el.settings)">
    <div v-safe-html="el.settings.content || 'Start typing your content here...'" 
         :style="{ 
             textAlign: el.settings.textAlign,
             fontSize: getUnitVal(el.settings.fontSize, el.settings.fontSizeUnit)
         }"
         class="prose prose-slate max-w-none"></div>
</div>
