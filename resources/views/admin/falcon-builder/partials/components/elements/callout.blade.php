{{-- Callout — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/callout.blade.php. Variants,
     presets and the body markup come from the fcCal* helpers in partials/scripts.blade.php,
     which read the same arrays CalloutStyles and InlineMarkup give the front end, so the
     two renderers cannot drift.

     The body is shown formatted rather than as raw text: an author writing **bold** or a
     bullet into the box needs to see what it becomes, and the text itself is edited in
     the Content tab. --}}
<div v-if="el.type === 'callout'"
     class="w-full"
     :style="getCanvasVisibilityStyle(el.settings)">

    <div :style="fcCalOuterStyle(el)">
        <div v-if="fcCalTitle(el) || fcCalShowIcon(el)" :style="fcCalHeadStyle(el)">
            <i v-if="fcCalShowIcon(el)" :class="fcCalIcon(el)" :style="fcCalIconStyle(el)"></i>
            <span v-if="fcCalTitle(el)" :style="fcCalTitleStyle(el)">@{{ fcCalTitle(el) }}</span>
            <i v-if="el.settings.collapsible" class="fas fa-chevron-down" :style="fcCalChevStyle(el)"></i>
        </div>

        <div v-if="fcCalBody(el)" :style="fcCalBodyStyle(el)" v-html="fcCalBody(el)"></div>

        <div v-if="!fcCalTitle(el) && !fcCalBody(el) && !fcCalShowIcon(el)"
             style="padding:14px;border:1px dashed #c9d0d8;border-radius:8px;color:#79838F;font-size:13px;text-align:center;">
            Empty callout — write a title or some text in the Content tab.
        </div>
    </div>
</div>
