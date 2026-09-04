{{-- Code Block — Content tab.
     Language and theme lists come from FalconCms\Core\Support\CodeHighlighter via the
     FC_CODE_*_OPTIONS constants in partials/scripts.blade.php, so adding a language
     there makes it appear here with no edit to this file. --}}

<!-- Code -->
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Code</label>
    <textarea v-model="editingElement.settings.code"
              rows="12" spellcheck="false"
              class="wp-input w-full text-[12px]"
              style="font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; line-height:1.6; white-space:pre; overflow-wrap:normal; overflow-x:auto;"
              placeholder="Paste your code here"></textarea>
    <p class="text-[11px] text-[#666] mt-1">Pasted exactly as written — no indentation is added or removed.</p>
</div>

<!-- Language -->
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Language</label>
    <select v-model="editingElement.settings.language" class="wp-input w-full">
        <option v-for="(name, slug) in fcCodeLangOptions" :key="slug" :value="slug">@{{ name }}</option>
    </select>
</div>

<!-- Window bar -->
<div class="space-y-3">
    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.showChrome"> Show window bar
    </label>

    <template v-if="editingElement.settings.showChrome">
        <div>
            <label class="text-[12px] font-bold text-[#333] block mb-2">File name</label>
            <input type="text" v-model="editingElement.settings.filename"
                   class="wp-input w-full" placeholder="app/Greeter.php">
        </div>
        <label class="flex items-center gap-2 text-[12px] text-[#333]">
            <input type="checkbox" v-model="editingElement.settings.chromeDots"> Show traffic-light dots
        </label>
        <label class="flex items-center gap-2 text-[12px] text-[#333]">
            <input type="checkbox" v-model="editingElement.settings.showLangTag"> Show language name
        </label>
    </template>
</div>

<!-- Lines -->
<div class="space-y-3">
    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.showLineNumbers"> Show line numbers
    </label>

    <div v-if="editingElement.settings.showLineNumbers">
        <label class="text-[12px] font-bold text-[#333] block mb-2">First line number</label>
        <input type="number" min="1" v-model.number="editingElement.settings.startLine" class="wp-input w-full">
    </div>

    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Highlight lines</label>
        <input type="text" v-model="editingElement.settings.highlightLines"
               class="wp-input w-full" placeholder="3, 7-9">
        <p class="text-[11px] text-[#666] mt-1">Numbers and ranges, counted from the first line number above.</p>
    </div>

    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.wrapLines"> Wrap long lines
    </label>
    <p v-if="!editingElement.settings.wrapLines" class="text-[11px] text-[#666]">
        Long lines scroll sideways inside the block.
    </p>

    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Max height (px)</label>
        <input type="number" min="0" step="10" v-model.number="editingElement.settings.maxHeight" class="wp-input w-full">
        <p class="text-[11px] text-[#666] mt-1">0 shows the whole snippet; above that it scrolls.</p>
    </div>
</div>

<!-- Copy button -->
<div class="space-y-3">
    <label class="flex items-center gap-2 text-[12px] text-[#333]">
        <input type="checkbox" v-model="editingElement.settings.showCopy"> Show copy button
    </label>

    <template v-if="editingElement.settings.showCopy !== false">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-2">Label</label>
                <input type="text" v-model="editingElement.settings.copyLabel" class="wp-input w-full" placeholder="Copy">
            </div>
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-2">After copying</label>
                <input type="text" v-model="editingElement.settings.copiedLabel" class="wp-input w-full" placeholder="Copied!">
            </div>
        </div>
        <p class="text-[11px] text-[#666]">
            Copies the original code, without line numbers. Appears on hover, and always on touch devices.
        </p>
    </template>
</div>

<!-- Typing reveal -->
<div class="space-y-3">
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Typing reveal</label>
        <select v-model="editingElement.settings.typeMode" class="wp-input w-full">
            <option value="none">None</option>
            <option value="typewriter">Typewriter — character by character</option>
            <option value="lines">Line by line</option>
        </select>
        <p class="text-[11px] text-[#666] mt-1">
            Plays on the live page, not in the editor. This is separate from the entrance
            animation in the Design tab — both can run together.
        </p>
    </div>

    <template v-if="editingElement.settings.typeMode && editingElement.settings.typeMode !== 'none'">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-2">Speed (ms)</label>
                <input type="number" min="1" max="500" v-model.number="editingElement.settings.typeSpeed" class="wp-input w-full">
            </div>
            <div>
                <label class="text-[12px] font-bold text-[#333] block mb-2">Starts</label>
                <select v-model="editingElement.settings.typeStart" class="wp-input w-full">
                    <option value="view">When scrolled into view</option>
                    <option value="load">On page load</option>
                </select>
            </div>
        </div>
        <label v-if="editingElement.settings.typeMode === 'typewriter'"
               class="flex items-center gap-2 text-[12px] text-[#333]">
            <input type="checkbox" v-model="editingElement.settings.typeCaret"> Blinking cursor
        </label>

        <p class="text-[11px] text-[#666]">
            The canvas plays it once when you pick a mode or change the speed, not while
            you type. Visitors who ask for reduced motion see the finished code straight away.
        </p>
    </template>
</div>
