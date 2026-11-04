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
        <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Font size</label>
        <input type="text" v-model="editingElement.settings.fontSize" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
    <div>
        <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line height</label>
        <input type="text" v-model="editingElement.settings.lineHeight" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Font family</label>
    <input type="text" v-model="editingElement.settings.fontFamily"
           class="wp-input w-full" placeholder="Leave empty for the system monospace stack">
</div>

<!-- Padding: one box per side, any CSS size (was one px box: padding) -->
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Padding</label>
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.spacing', ['responsive' => false, 'unit' => false, 'legacy' => 'padding', 'placeholders' => ['Top' => '18', 'Right' => '18', 'Bottom' => '18', 'Left' => '18']])
</div>

<!-- Box -->
<div class="grid grid-cols-2 gap-2">
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
        <input type="text" :value="spDisplay(editingElement.settings, 'marginTop', 'desktop', false)" @input="spInput(editingElement.settings, 'marginTop', 'desktop', $event.target.value, false)" :placeholder="spHint(editingElement.settings, 'marginTop', 'desktop', false, '0')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="wp-input w-full">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Margin bottom</label>
        <input type="text" :value="spDisplay(editingElement.settings, 'marginBottom', 'desktop', false)" @input="spInput(editingElement.settings, 'marginBottom', 'desktop', $event.target.value, false)" :placeholder="spHint(editingElement.settings, 'marginBottom', 'desktop', false, '0')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="wp-input w-full">
    </div>
</div>
