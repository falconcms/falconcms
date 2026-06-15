{{-- Reusable responsive device-mode dropdown (desktop/tablet/mobile).
     Var: $menu (unique menu id used with activeResponsiveMenu) --}}
@php $menu = $menu ?? 'respmenu'; @endphp
<div class="relative inline-block">
    <button @click="activeResponsiveMenu = activeResponsiveMenu === '{{ $menu }}' ? null : '{{ $menu }}'"
            class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
    </button>
    <div v-show="activeResponsiveMenu === '{{ $menu }}'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Desktop"><i class="fa fa-desktop text-[11px]"></i></button>
        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Tablet"><i class="fa fa-tablet-alt text-[11px]"></i></button>
        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Mobile"><i class="fa fa-mobile-alt text-[11px]"></i></button>
    </div>
</div>
