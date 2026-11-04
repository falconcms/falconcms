{{-- Reusable Dimensions control (Top/Right/Bottom/Left) with unit + responsive mode.
     Vars: $key (setting key prefix), $label (optional), $unit (optional default unit) --}}
@php
    $label = $label ?? 'Spacing';
    $unit  = $unit  ?? 'px';
    $dimMenu = 'cfdim_' . $key;
@endphp
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $label }}</label>
        <div class="flex gap-1 items-center">
            <button @click="['_top','_right','_bottom','_left'].forEach(s => setResponsiveVal(editingElement.settings, '{{ $key }}' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
            @include('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => $dimMenu])
        </div>
    </div>
    {{-- One box per side, like Font Size: "20", "2rem", "5%". Stored as before: the number in
         {key}_top and its unit in {key}_top_unit, so the plugin reading them needs no change. --}}
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.spacing', [
        'key' => $key,
        'sides' => ['Top' => '_top', 'Right' => '_right', 'Bottom' => '_bottom', 'Left' => '_left'],
        'unit' => '_unit',
        'defUnit' => $unit,
    ])
</div>
