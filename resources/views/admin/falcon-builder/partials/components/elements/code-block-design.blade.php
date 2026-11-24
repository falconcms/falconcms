{{-- Code Block — Design tab. Theme list comes from CodeHighlighter::themeOptions()
     through FC_CODE_THEME_OPTIONS, so a theme added in PHP shows up here on its own. --}}

<!-- Theme -->
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Color theme</label>
    <select v-model="editingElement.settings.codeTheme" class="wp-input w-full">
        <option v-for="(name, slug) in fcCodeThemeOptions" :key="slug" :value="slug">@{{ name }}</option>
    </select>
</div>

{{-- Typography — the shared control every element uses (prefix cb). A block saved
     with the old Font size / Line height / Font family fields has them moved in when it
     is opened (fcCodeTypoMigrate), so it keeps its look. Default family = the system
     monospace stack; empty size = the Customizer's body size. --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Typography</div>
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'cb', 'defaultFont' => "'Monospace'"])
    <p class="text-[11px] text-slate-500">Font family left on Default uses a monospace font. Font size left empty follows the site's body text size.</p>
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
