<div v-if="el.type === 'html'"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
     }]"
     class="w-full">

    <div v-if="el.settings.htmlContent && el.settings.htmlContent.trim()"
         v-safe-html="el.settings.htmlContent"
         class="lazy-html-block w-full">
    </div>

    <div v-else
         class="w-full flex flex-col items-center justify-center gap-1 border border-dashed border-slate-300 rounded bg-slate-50 py-5 px-3 text-slate-400">
        <i class="fa fa-code text-[20px]"></i>
        <span class="text-[11px] font-medium">HTML / Code Block</span>
        <span class="text-[10px] text-slate-300">Click to edit HTML</span>
    </div>
</div>
