{{-- Reusable Typography control — identical design to the Title element.
     Variable: $prefix (settings key prefix). Binds to:
       {prefix}_family, {prefix}_weight, {prefix}_size, {prefix}_line_height,
       {prefix}_letter_spacing, {prefix}_transform
--}}
<div class="space-y-4">
    {{-- Font Family --}}
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
        <select v-model="editingElement.settings.{{ $prefix }}_family"
                @change="loadBuilderFont(editingElement.settings.{{ $prefix }}_family)"
                class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            <option value="inherit">Default@{{ themeBodyFont ? ' (' + themeBodyFont + ')' : '' }}</option>
            <template v-for="(fonts, category) in builderFontGroups" :key="category">
                <optgroup :label="category">
                    <option v-for="font in fonts" :key="font.family"
                            :value="font.family + ', ' + (font.category === 'Monospace' ? 'monospace' : (font.category === 'Serif' ? 'serif' : 'sans-serif'))">
                        @{{ font.family }}
                    </option>
                </optgroup>
            </template>
        </select>
    </div>

    {{-- Font Weight --}}
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
        <select v-model="editingElement.settings.{{ $prefix }}_weight"
                class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            <option value="">Default</option>
            <option value="100">Thin 100</option>
            <option value="200">Extra Light 200</option>
            <option value="300">Light 300</option>
            <option value="400">Regular 400</option>
            <option value="500">Medium 500</option>
            <option value="600">Semi Bold 600</option>
            <option value="700">Bold 700</option>
            <option value="800">Extra Bold 800</option>
            <option value="900">Black 900</option>
        </select>
    </div>

    {{-- Size / Line Height / Letter Spacing --}}
    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Font Size</label>
            <input type="text" v-model="editingElement.settings.{{ $prefix }}_size"
                   class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
        </div>
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Hei...</label>
            <input type="text" v-model="editingElement.settings.{{ $prefix }}_line_height"
                   class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
        </div>
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
            <input type="text" v-model="editingElement.settings.{{ $prefix }}_letter_spacing"
                   class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center">
        </div>
    </div>

    {{-- Text Transform --}}
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.{{ $prefix }}_transform = 'none'"
                    :class="(editingElement.settings.{{ $prefix }}_transform || 'none') === 'none' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
            <button @click="editingElement.settings.{{ $prefix }}_transform = 'uppercase'"
                    :class="editingElement.settings.{{ $prefix }}_transform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
            <button @click="editingElement.settings.{{ $prefix }}_transform = 'lowercase'"
                    :class="editingElement.settings.{{ $prefix }}_transform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
            <button @click="editingElement.settings.{{ $prefix }}_transform = 'capitalize'"
                    :class="editingElement.settings.{{ $prefix }}_transform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
        </div>
    </div>
</div>
