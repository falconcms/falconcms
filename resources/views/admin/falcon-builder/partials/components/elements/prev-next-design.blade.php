{{-- Previous / Next — Design tab.

     The preset list comes from PrevNext::presetOptions(). Every value below overrides its
     preset's and is left empty until it does, so switching preset still moves anything the
     author has not touched; the placeholder on each field shows what the preset gives it. --}}

{{-- ══ PRESET ══ --}}
<div>
    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Preset</label>
    <select v-model="editingElement.settings.preset" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <option v-for="(name, slug) in fcPnPresetOptions" :key="slug" :value="slug">@{{ name }}</option>
    </select>
</div>

{{-- ══ COLOURS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Colors</div>

    @foreach([
        ['bg', 'Background'],
        ['borderColor', 'Border'],
        ['hoverBorder', 'Border on hover'],
        ['labelColor', 'Label'],
        ['titleColor', 'Page title'],
        ['arrowColor', 'Arrow'],
    ] as [$key, $label])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }}</label>
            <button @click="editingElement.settings.{{ $key }} = ''" title="Back to the preset"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, '{{ $key }}')">
                <div :style="{ backgroundColor: fcPnVal(editingElement, '{{ $key }}') }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $key }}"
                   :placeholder="fcPnVal(editingElement, '{{ $key }}')"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach
</div>

{{-- ══ BOX ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Box</div>

    <div class="grid grid-cols-2 gap-3">
        @foreach([
            ['borderWidth', 'Border width'],
            ['radius', 'Corner radius'],
            ['padY', 'Padding ↕'],
            ['padX', 'Padding ↔'],
            ['gap', 'Gap between'],
        ] as [$key, $label])
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">{{ $label }}</label>
            <input type="number" v-model.number="editingElement.settings.{{ $key }}" min="0" max="80"
                   :placeholder="fcPnVal(editingElement, '{{ $key }}')"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        @endforeach
    </div>
</div>

{{-- ══ TYPOGRAPHY ══ --}}
<div class="space-y-5">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Typography</div>

    <div>
        <div class="text-[11px] font-bold text-slate-500 mb-2">Label</div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'pn_label'])
    </div>
    <div>
        <div class="text-[11px] font-bold text-slate-500 mb-2">Page title</div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'pn_title'])
    </div>
</div>
