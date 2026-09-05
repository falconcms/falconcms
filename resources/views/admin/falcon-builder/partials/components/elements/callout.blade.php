{{-- Callout — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/callout.blade.php. Variants,
     presets and the body markup come from the fcCal* helpers in partials/scripts.blade.php,
     which read the same arrays CalloutStyles and InlineMarkup give the front end, so the
     two renderers cannot drift.

     The body is shown formatted rather than as raw text: an author writing **bold** or a
     bullet into the box needs to see what it becomes, and the text itself is edited in
     the Content tab. --}}
<div v-if="el.type === 'callout'"
     class="w-full fa-tanim-host"
     :style="getCanvasVisibilityStyle(el.settings)">

    {{-- The body's paragraphs and lists need a stylesheet, not inline styles: they are
         rendered from a string of HTML, so there is no element here to bind a style to.
         Same trick the Table uses for its hover rule. --}}
    <component :is="'style'" v-if="fcCalBodyCss(el)" v-text="fcCalBodyCss(el)"></component>

    <div :id="fcCalScopeId(el)"
         :class="textAnimClass(el)"
         :key="'tanim-' + (el.settings.textAnim || 'none')"
         :style="[fcCalOuterStyle(el), textAnimVars(el)]">
        <div v-if="fcCalTitle(el) || fcCalShowIcon(el)" :style="fcCalHeadStyle(el)">
            <i v-if="fcCalShowIcon(el)" :class="fcCalIcon(el)" :style="fcCalIconStyle(el)"></i>
            <span v-if="fcCalTitle(el)"
                  :class="textAnimClass(el, 'text')"
                  :style="[fcCalTitleStyle(el), textAnimVars(el, 'text')]"
                  :key="'tanim-text-' + (el.settings.textAnim || 'none')">@{{ fcCalTitle(el) }}</span>
            <i v-if="el.settings.collapsible" class="fas fa-chevron-down" :style="fcCalChevStyle(el)"></i>
        </div>

        <div v-if="fcCalBody(el)" class="fc-cal-body" :style="fcCalBodyStyle(el)" v-html="fcCalBody(el)"></div>

        <div v-if="!fcCalTitle(el) && !fcCalBody(el) && !fcCalShowIcon(el)"
             style="padding:14px;border:1px dashed #c9d0d8;border-radius:8px;color:#79838F;font-size:13px;text-align:center;">
            Empty callout — write a title or some text in the Content tab.
        </div>
    </div>
</div>
