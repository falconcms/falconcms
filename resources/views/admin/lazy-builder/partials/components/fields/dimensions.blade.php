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
            @include('falcon-cms::admin.lazy-builder.partials.components.fields.responsive-mode', ['menu' => $dimMenu])
        </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
        @foreach(['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left'] as $side => $sideLabel)
        <div class="flex flex-col gap-1">
            <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">{{ $sideLabel }}</label>
            <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                <input type="number"
                       :value="getResponsiveVal(editingElement.settings, '{{ $key }}_{{ $side }}', device)"
                       @input="setResponsiveVal(editingElement.settings, '{{ $key }}_{{ $side }}', device, $event.target.value === '' ? '' : Number($event.target.value))"
                       placeholder="0" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                <select :value="getResponsiveVal(editingElement.settings, '{{ $key }}_{{ $side }}_unit', device) || '{{ $unit }}'"
                        @change="setResponsiveVal(editingElement.settings, '{{ $key }}_{{ $side }}_unit', device, $event.target.value)"
                        class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                    <option value="px">px</option><option value="em">em</option><option value="rem">rem</option><option value="%">%</option>
                </select>
            </div>
        </div>
        @endforeach
    </div>
</div>
