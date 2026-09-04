{{-- Table — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/table.blade.php. Presets and
     cell formatting come from the fcTbl* helpers in partials/scripts.blade.php, which
     read the same arrays TableStyles gives the front end, so the two cannot drift.

     Cells are shown formatted rather than as raw text: an author writing `code` or
     **bold** into the grid needs to see what it becomes, and the grid in the Content
     tab is where the text itself is edited. --}}
<div v-if="el.type === 'table'"
     class="w-full"
     :style="[getCanvasVisibilityStyle(el.settings), fcTblOuterStyle(el)]">

    {{-- The one rule that needs a pseudo-class. Everything else on this canvas is
         painted inline, which cannot express :hover — so row hover worked on the
         published page and did nothing here. --}}
    <component :is="'style'" v-if="fcTblHoverCss(el)" v-text="fcTblHoverCss(el)"></component>

    <div :style="fcTblScrollStyle(el)">
        <table :id="fcTblScopeId(el)" :style="fcTblTableStyle(el)">
            <thead v-if="el.settings.headerRow !== false && fcTblHead(el).length">
                <tr>
                    <th v-for="(cell, i) in fcTblHead(el)" :key="i"
                        :style="fcTblHeadCellStyle(el, i)"
                        v-html="fcTblCellFor(el, cell) || '&nbsp;'"></th>
                </tr>
            </thead>
            <tbody>
                {{-- Two real tags rather than <component :is>. This is an in-DOM
                     template, and the HTML parser only allows <td>, <th> and <tr>
                     inside a table: it hoists anything else clean out of the table
                     before Vue ever sees it. With <component> here the whole tbody
                     came out empty — the header rendered, because it uses a real <th>,
                     and every body cell silently vanished. --}}
                <tr v-for="(row, r) in fcTblBody(el)" :key="r">
                    <template v-for="(cell, c) in row" :key="c">
                        <th v-if="el.settings.headerCol && c === 0"
                            scope="row"
                            :style="fcTblCellStyle(el, r, c)"
                            v-html="fcTblCellFor(el, cell) || '&nbsp;'"></th>
                        <td v-else
                            :style="fcTblCellStyle(el, r, c)"
                            v-html="fcTblCellFor(el, cell) || '&nbsp;'"></td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="el.settings.caption" :style="fcTblCaptionStyle(el)" v-html="fcTblCellFor(el, el.settings.caption)"></div>

    <div v-if="!fcTblHead(el).length && !fcTblBody(el).length"
         style="padding:18px;border:1px dashed #c9d0d8;border-radius:8px;color:#79838F;font-size:13px;text-align:center;">
        Empty table — add rows in the Content tab, or paste a Markdown table.
    </div>
</div>
