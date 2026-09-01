{{-- Section Separator — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/section-separator.blade.php.
     All style maths lives in the sepXxx() helpers in partials/scripts.blade.php,
     which read the same geometry tables as the frontend renderer. --}}
<div v-if="el.type === 'section_separator'"
     class="w-full"
     :style="[getCanvasVisibilityStyle(el.settings), sepOuterStyle(el)]">

    {{-- Custom SVG: the user's own artwork, sanitised on import and again on the server --}}
    <div v-if="sepIsCustom(el)" :id="sepScopeId(el)" :style="sepShapeWrapStyle(el)">
        <component :is="'style'" v-text="sepCustomCss(el)"></component>
        <div class="falcon-separator-custom" :style="sepShapeSvgStyle(el)" v-html="sepCustomMarkup(el)"></div>

        <div v-if="sepHasContent(el)" :style="sepShapeOverlayStyle(el)">
            <span v-if="el.settings.sepContent === 'text'"
                  :style="sepTextStyle(el)"
                  v-html="el.settings.sepText"></span>
            <span v-else :style="sepIconWrapStyle(el)">
                <i :class="el.settings.sepIcon" :style="sepIconStyle(el)"></i>
            </span>
        </div>
    </div>

    {{-- Shape family: one full-width silhouette, content overlaid on top --}}
    <div v-else-if="sepIsShape(el.settings.sepStyle)" :style="sepShapeWrapStyle(el)">
        {{-- viewBox MUST be a literal attribute, never a Vue binding. This is an in-DOM
             template, and the HTML parser only case-corrects SVG attributes it recognises:
             `viewBox` is fixed up, but `:viewBox` is lowercased to `:viewbox`, so Vue would
             set an attribute SVG ignores — leaving the shape with no viewBox and therefore
             no scaling to the element width. --}}
        <svg viewBox="0 0 {{ \FalconCms\Core\Support\SeparatorShapes::VIEW_W }} {{ \FalconCms\Core\Support\SeparatorShapes::VIEW_H }}"
             preserveAspectRatio="none" aria-hidden="true"
             :style="sepShapeSvgStyle(el)">
            <path v-for="(layer, li) in sepShapeLayers(el)" :key="li"
                  :d="layer.d" :fill="el.settings.sepColor || '#e2e8f0'"
                  :opacity="layer.o < 1 ? layer.o : null"></path>
        </svg>

        <div v-if="sepHasContent(el)" :style="sepShapeOverlayStyle(el)">
            <span v-if="el.settings.sepContent === 'text'"
                  :style="sepTextStyle(el)"
                  v-html="el.settings.sepText"></span>
            <span v-else :style="sepIconWrapStyle(el)">
                <i :class="el.settings.sepIcon" :style="sepIconStyle(el)"></i>
            </span>
        </div>
    </div>

    {{-- Line / pattern family: line — content — line --}}
    <div v-else class="falcon-separator-inner" :style="sepInnerStyle(el)">
        <div :style="sepLineStyle(el, 'left')"></div>

        <template v-if="sepHasContent(el)">
            <div style="flex:0 0 auto; display:inline-flex; align-items:center; line-height:1;">
                <span v-if="el.settings.sepContent === 'text'"
                      :style="sepTextStyle(el)"
                      v-html="el.settings.sepText"></span>
                <span v-else :style="sepIconWrapStyle(el)">
                    <i :class="el.settings.sepIcon" :style="sepIconStyle(el)"></i>
                </span>
            </div>

            <div :style="sepLineStyle(el, 'right')"></div>
        </template>
    </div>

</div>
