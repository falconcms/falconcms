{{-- Table — Design tab.

     The preset list comes from TableStyles::presetOptions() via FC_TBL_PRESET_OPTIONS,
     so a preset added in PHP appears here on its own. Every colour and number below
     overrides its preset's value and is left empty until it does, which is why
     switching preset still moves anything the author has not touched. The placeholder
     on each field shows what the preset is currently giving it. --}}

{{-- ══ PRESET ══ --}}
<div>
    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Preset</label>
    <select v-model="editingElement.settings.preset" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <option v-for="(name, slug) in fcTblPresetOptions" :key="slug" :value="slug">@{{ name }}</option>
    </select>
</div>

{{-- ══ COLOURS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Colors</div>

    @foreach([
        ['headerBg', 'Header background'],
        ['headerColor', 'Header text'],
        ['textColor', 'Body text'],
        ['bodyBg', 'Body background'],
        ['borderColor', 'Border'],
        ['stripeBg', 'Stripe'],
        ['hoverBg', 'Row hover'],
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
                <div :style="{ backgroundColor: fcTblVal(editingElement, '{{ $key }}') }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $key }}"
                   :placeholder="fcTblVal(editingElement, '{{ $key }}')"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach
</div>

{{-- ══ ICONS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Cell icons</div>
    <p class="text-[11px] text-slate-500 -mt-2">
        Type <code>[check]</code> or <code>[cross]</code> in a cell for a tick or a cross,
        or <code>[icon fas fa-star]</code> for any other.
    </p>

    @foreach([['iconYesColor', 'Tick', '#3E7D4F'], ['iconNoColor', 'Cross', '#B0392B']] as [$key, $label, $fallback])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }} color</label>
            <button @click="editingElement.settings.{{ $key }} = ''" title="Reset"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, '{{ $key }}')">
                <div :style="{ backgroundColor: editingElement.settings.{{ $key }} || '{{ $fallback }}' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $key }}" placeholder="{{ $fallback }}"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach
</div>

{{-- ══ HIGHLIGHT ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Highlight</div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Rows</label>
            <input type="text" v-model="editingElement.settings.highlightRows" placeholder="2, 5-6"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Columns</label>
            <input type="text" v-model="editingElement.settings.highlightCols" placeholder="3"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    <p class="text-[11px] text-slate-500 -mt-2">
        Counted the way the table reads: row 1 is the first row under the header.
    </p>

    @foreach([['highlightBg', 'Highlight background', 'rgba(232,145,43,.10)'], ['highlightColor', 'Highlight text', 'leave empty to keep']] as [$key, $label, $hint])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }}</label>
            <button @click="editingElement.settings.{{ $key }} = ''" title="Reset"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, '{{ $key }}')">
                <div :style="{ backgroundColor: editingElement.settings.{{ $key }} || 'transparent' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $key }}" placeholder="{{ $hint }}"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach
</div>

{{-- ══ HEADER TYPOGRAPHY ══ --}}
<div class="space-y-3">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Header typography</div>
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'tbl_head'])
</div>

{{-- ══ BODY TYPOGRAPHY ══ --}}
<div class="space-y-3">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Body typography</div>
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'tbl_body'])
</div>

{{-- ══ BORDERS & SPACING ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Borders &amp; spacing</div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Borders</label>
        <select v-model="editingElement.settings.borders" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            <option value="">Preset default (@{{ fcTblVal(editingElement, 'borders') }})</option>
            <option value="all">Every cell</option>
            <option value="horizontal">Between rows only</option>
            <option value="none">None</option>
        </select>
    </div>

    <div class="flex flex-col gap-2">
        <label class="flex items-center gap-2 text-[12px] text-slate-700">
            <input type="checkbox" v-model="editingElement.settings.stripe"> Striped rows
        </label>
        <label class="flex items-center gap-2 text-[12px] text-slate-700">
            <input type="checkbox" v-model="editingElement.settings.hover"> Highlight the row under the cursor
        </label>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Pad Y</label>
            <input type="number" min="0" max="40" v-model.number="editingElement.settings.cellPaddingY"
                   :placeholder="fcTblVal(editingElement, 'cellPaddingY')"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Pad X</label>
            <input type="number" min="0" max="40" v-model.number="editingElement.settings.cellPaddingX"
                   :placeholder="fcTblVal(editingElement, 'cellPaddingX')"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Radius</label>
            <input type="number" min="0" max="30" v-model.number="editingElement.settings.radius"
                   :placeholder="fcTblVal(editingElement, 'radius')"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Margin top</label>
            <input type="number" v-model.number="editingElement.settings.marginTop"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Margin bottom</label>
            <input type="number" v-model.number="editingElement.settings.marginBottom"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
</div>
