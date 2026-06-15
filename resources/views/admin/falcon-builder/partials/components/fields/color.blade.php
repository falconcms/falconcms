{{-- Reusable Color control.
     Vars: $key (setting key), $label (optional), $default (optional hex), $target (optional JS object path) --}}
@php
    $target  = $target  ?? 'editingElement.settings';
    $label   = $label   ?? 'Color';
    $default = $default ?? '#000000';
@endphp
<div>
    <div class="flex justify-between items-center mb-1.5">
        <label class="text-[12px] font-bold text-[#333]">{{ $label }}</label>
        <button @click="clearColorField({{ $target }}, '{{ $key }}')" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
            <i class="fa fa-undo text-[10px]"></i>
        </button>
    </div>
    <div class="flex gap-2 items-center">
        <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
             @click="openColorPicker($event, {{ $target }}, '{{ $key }}')">
            <div :style="{ backgroundColor: {{ $target }}.{{ $key }} || '{{ $default }}' }" class="w-full h-full rounded-full"></div>
        </div>
        <input type="text" v-model="{{ $target }}.{{ $key }}"
               placeholder="{{ $default }}"
               class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] focus:outline-none focus:border-[#0091ea]">
    </div>
</div>
