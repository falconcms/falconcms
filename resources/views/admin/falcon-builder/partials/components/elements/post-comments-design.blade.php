{{-- Comments — Design tab. An empty colour follows the theme (its primary colour for the accent). --}}

{{-- ══ COLOURS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Colors</div>

    @foreach([
        ['accentColor', 'Accent (button, links, avatars)', 'var(--primary)'],
        ['buttonTextColor', 'Button text', '#ffffff'],
        ['titleColor', 'Headings', '#111827'],
        ['nameColor', 'Names and labels', '#111827'],
        ['textColor', 'Comment text', '#4b5563'],
        ['metaColor', 'Dates', '#9ca3af'],
        ['cardBg', 'Comment background', 'none'],
        ['borderColor', 'Borders', '#e5e7eb'],
        ['inputBg', 'Field background', '#ffffff'],
    ] as [$key, $label, $hint])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }}</label>
            <button @click="editingElement.settings.{{ $key }} = ''" title="Back to the default"
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

{{-- ══ BOX ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Box</div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Corner radius</label>
            <input type="number" v-model.number="editingElement.settings.radius" min="0" max="40" placeholder="8"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Space between</label>
            <input type="number" v-model.number="editingElement.settings.itemGap" min="0" max="80" placeholder="28"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
</div>

{{-- ══ TYPOGRAPHY ══ --}}
<div class="space-y-5">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Typography</div>
    <div>
        <div class="text-[11px] font-bold text-slate-500 mb-2">Headings</div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'cm_title'])
    </div>
    <div>
        <div class="text-[11px] font-bold text-slate-500 mb-2">Comment text</div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'cm_body'])
    </div>
</div>

{{-- ══ MARGIN (responsive) ══ --}}
<div class="pt-4 border-t border-slate-50">
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">Margin</label>
        <div class="flex gap-1 items-center">
            <button @click="['Top','Bottom'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
            <div class="relative inline-block">
                <button @click="activeResponsiveMenu = activeResponsiveMenu === 'cmMargin' ? null : 'cmMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                    <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                    <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                </button>
                <div v-show="activeResponsiveMenu === 'cmMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                    <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)">
                        <i class="fa fa-desktop text-[11px]"></i>
                    </button>
                    <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)">
                        <i class="fa fa-tablet-alt text-[11px]"></i>
                    </button>
                    <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)">
                        <i class="fa fa-mobile-alt text-[11px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
        @foreach(['Top', 'Bottom'] as $side)
        <div class="flex flex-col gap-1">
            <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">{{ $side }}</label>
            <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'margin{{ $side }}' : 'margin{{ $side }}_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'margin{{ $side }}', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                <select :value="getResponsiveVal(editingElement.settings, 'margin{{ $side }}Unit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'margin{{ $side }}Unit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option><option value="em">em</option></select>
            </div>
        </div>
        @endforeach
    </div>
</div>
