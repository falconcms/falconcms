{{-- Table — Content tab: the grid itself, plus the importer. --}}

<!-- Import -->
<div class="space-y-2">
    <label class="text-[12px] font-bold text-[#333] block">Paste a table</label>
    <textarea v-model="fcTblImportText" rows="5" spellcheck="false"
              class="wp-input w-full text-[11.5px]"
              style="font-family: ui-monospace, Menlo, Consolas, monospace; white-space:pre; overflow-x:auto;"
              placeholder="| Setting | Type | Default |&#10;|:--------|:----:|--------:|&#10;| `preset` | string | `docs` |"></textarea>
    <div class="flex gap-2">
        <button type="button" class="wp-button-secondary flex-1 text-[12px] py-1.5"
                @click="fcTblImport(editingElement)">Import</button>
        <button type="button" class="wp-button-secondary flex-1 text-[12px] py-1.5"
                @click="fcTblImport(editingElement, 'delimited')">Import as CSV / TSV</button>
    </div>
    <p class="text-[11px]" :class="fcTblImportNote ? 'text-[#2271b1]' : 'text-[#666]'">
        <span v-if="fcTblImportNote">@{{ fcTblImportNote }}</span>
        <span v-else>
            Markdown, CSV or a spreadsheet paste. A Markdown alignment row
            (<code>:---:</code>) sets the column alignments. This replaces the grid below.
        </span>
    </p>
</div>

<!-- Structure -->
<div class="space-y-2">
    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.headerRow"> First row is a header
    </label>
    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.headerCol"> First column is a header
    </label>
</div>

<!-- Grid -->
<div>
    <div class="flex items-center justify-between mb-2">
        <label class="text-[12px] font-bold text-[#333]">Cells</label>
        <div class="flex gap-1">
            <button type="button" class="wp-button-secondary text-[11px] px-2 py-1"
                    @click="fcTblAddRow(editingElement)">+ Row</button>
            <button type="button" class="wp-button-secondary text-[11px] px-2 py-1"
                    @click="fcTblAddCol(editingElement)">+ Column</button>
        </div>
    </div>

    <div class="overflow-x-auto border border-[#dcdcde] rounded">
        <table class="w-full text-[12px]" style="border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="width:28px"></th>
                    <th v-for="(col, c) in (editingElement.settings.cols || [])" :key="'h' + c"
                        class="p-1 border-b border-[#e5e5e5] bg-[#f6f7f7]">
                        <div class="flex items-center gap-1 justify-center">
                            <button type="button" title="Move left" class="px-1 text-[#666] hover:text-[#111]"
                                    @click="fcTblMoveCol(editingElement, c, -1)">‹</button>
                            <select :value="fcTblAlign(editingElement, c)"
                                    @change="fcTblSetAlign(editingElement, c, $event.target.value)"
                                    class="wp-input text-[11px] py-0.5 px-1">
                                <option v-for="a in fcTblAlignments" :key="a" :value="a">@{{ a }}</option>
                            </select>
                            <button type="button" title="Move right" class="px-1 text-[#666] hover:text-[#111]"
                                    @click="fcTblMoveCol(editingElement, c, 1)">›</button>
                            <button type="button" title="Delete column" class="px-1 text-[#b32d2e]"
                                    @click="fcTblRemoveCol(editingElement, c)">×</button>
                        </div>
                        <input type="text" :value="col.width"
                               @input="col.width = $event.target.value"
                               class="wp-input w-full text-[11px] mt-1 py-0.5"
                               placeholder="width, e.g. 30%">
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, r) in (editingElement.settings.rows || [])" :key="'r' + r"
                    :class="r === 0 && editingElement.settings.headerRow !== false ? 'bg-[#f6f7f7]' : ''">
                    <td class="align-top p-1 border-b border-[#f0f0f1] text-center">
                        <div class="flex flex-col items-center gap-0.5">
                            <button type="button" title="Move up" class="text-[#666] hover:text-[#111] leading-none"
                                    @click="fcTblMoveRow(editingElement, r, -1)">▲</button>
                            <button type="button" title="Move down" class="text-[#666] hover:text-[#111] leading-none"
                                    @click="fcTblMoveRow(editingElement, r, 1)">▼</button>
                            <button type="button" title="Delete row" class="text-[#b32d2e] leading-none"
                                    @click="fcTblRemoveRow(editingElement, r)">×</button>
                        </div>
                    </td>
                    <td v-for="(cell, c) in row" :key="'c' + c" class="p-1 border-b border-[#f0f0f1]">
                        <textarea :value="cell" rows="1"
                                  @input="fcTblSetCell(editingElement, r, c, $event.target.value)"
                                  class="wp-input w-full text-[12px] py-1"
                                  style="min-width:110px;resize:vertical;"></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="text-[11px] text-[#666] mt-2">
        Cells take <code>`code`</code>, <code>**bold**</code>, <code>*italic*</code>,
        <code>[text](url)</code> and <code>&lt;br&gt;</code>. Anything else is shown as
        written — a cell can never carry script.
    </p>
</div>

<!-- Caption -->
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Caption</label>
    <input type="text" v-model="editingElement.settings.caption" class="wp-input w-full"
           placeholder="Shown under the table">
</div>

<!-- Behaviour -->
<div class="space-y-3">
    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.sortable"> Readers can sort by clicking a header
    </label>
    <p v-if="editingElement.settings.sortable && editingElement.settings.headerRow === false"
       class="text-[11px] text-[#b32d2e]">Sorting needs a header row to click.</p>

    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.stickyHeader"> Header stays visible while scrolling
    </label>

    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Max height (px)</label>
        <input type="number" min="0" step="10" v-model.number="editingElement.settings.maxHeight" class="wp-input w-full">
        <p class="text-[11px] text-[#666] mt-1">0 shows the whole table; above that it scrolls.</p>
    </div>

    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">On small screens</label>
        <select v-model="editingElement.settings.responsive" class="wp-input w-full">
            <option value="scroll">Scroll sideways</option>
            <option value="stack">Stack each row as a card</option>
        </select>
        <p class="text-[11px] text-[#666] mt-1">
            Stacking suits wide tables: side-scrolling hides the first column, the one
            that says what the row is about.
        </p>
    </div>
</div>
