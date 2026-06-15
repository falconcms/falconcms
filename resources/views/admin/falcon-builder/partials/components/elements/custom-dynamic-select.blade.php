{{-- Dynamic source blue-pill for custom element fields.
     Variables: $dynKey (settings key holding source), $dynCtx ('text'|'link'|'image') --}}
<div class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
     @click.stop="openDynSrcMenu(editingElement.settings, '{{ $dynKey }}', '{{ $dynCtx ?? 'text' }}', $event)">
    <div class="flex items-center gap-2">
        <i :class="['fa', getDynSrcDef(editingElement.settings.{{ $dynKey }}).icon, 'text-[#0091ea] text-sm']"></i>
        <span class="text-[12px] font-bold text-[#0091ea]">{!! '{{ getDynSrcDef(editingElement.settings.' . $dynKey . ').label }}' !!}</span>
    </div>
    <button @click.stop="editingElement.settings.{{ $dynKey }} = ''"
            class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
        <i class="fa fa-times text-[10px]"></i>
    </button>
</div>
