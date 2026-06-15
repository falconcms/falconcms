{{-- Reusable On/Off toggle.
     Vars: $key (setting key), $label (optional), $target (optional JS object path) --}}
@php
    $target = $target ?? 'editingElement.settings';
    $label  = $label  ?? '';
@endphp
<div>
    @if($label)
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $label }}</label>
    </div>
    @endif
    <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
        <button @click="{{ $target }}.{{ $key }} = true"
                :class="{{ $target }}.{{ $key }} ? 'bg-[#2271b1] text-white shadow-md' : 'text-slate-400'"
                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">On</button>
        <button @click="{{ $target }}.{{ $key }} = false"
                :class="!{{ $target }}.{{ $key }} ? 'bg-[#2271b1] text-white shadow-md' : 'text-slate-400'"
                class="px-6 py-1.5 text-[11px] font-black uppercase rounded transition-all">Off</button>
    </div>
</div>
