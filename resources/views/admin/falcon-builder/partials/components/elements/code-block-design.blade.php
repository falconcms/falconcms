{{-- Code Block — Design tab. Theme list comes from CodeHighlighter::themeOptions()
     through FC_CODE_THEME_OPTIONS, so a theme added in PHP shows up here on its own. --}}

<!-- Theme -->
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Color theme</label>
    <select v-model="editingElement.settings.codeTheme" class="wp-input w-full">
        <option v-for="(name, slug) in fcCodeThemeOptions" :key="slug" :value="slug">@{{ name }}</option>
    </select>
</div>

<!-- Type -->
<div class="grid grid-cols-2 gap-2">
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Font size (px)</label>
        <input type="number" min="9" max="32" step="0.5" v-model.number="editingElement.settings.fontSize" class="wp-input w-full">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Line height</label>
        <input type="number" min="1" max="3" step="0.05" v-model.number="editingElement.settings.lineHeight" class="wp-input w-full">
    </div>
</div>

<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Font family</label>
    <input type="text" v-model="editingElement.settings.fontFamily"
           class="wp-input w-full" placeholder="Leave empty for the system monospace stack">
</div>

<!-- Box -->
<div class="grid grid-cols-3 gap-2">
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Padding</label>
        <input type="number" min="0" max="80" v-model.number="editingElement.settings.padding" class="wp-input w-full">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Radius</label>
        <input type="number" min="0" max="40" v-model.number="editingElement.settings.borderRadius" class="wp-input w-full">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Border</label>
        <input type="number" min="0" max="8" v-model.number="editingElement.settings.borderWidth" class="wp-input w-full">
    </div>
</div>

<!-- Margins -->
<div class="grid grid-cols-2 gap-2">
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Margin top</label>
        <input type="number" v-model.number="editingElement.settings.marginTop" class="wp-input w-full">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Margin bottom</label>
        <input type="number" v-model.number="editingElement.settings.marginBottom" class="wp-input w-full">
    </div>
</div>
