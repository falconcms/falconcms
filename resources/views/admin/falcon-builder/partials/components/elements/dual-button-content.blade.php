{{-- Dual Button — Content tab.

     The two buttons are the same form twice, so it is written once and looped. Each field
     works the way the Button element's does: the database icon binds the text or the link
     to a dynamic source, Link Target appears once there is a link, and the icon is picked
     from the full grid with its position below it.

     A dynamic source's options are written by the picker under fixed names (dynamic_before,
     dynamic_link_tax_slug …), so each button hands it an object of its own —
     fcDbDyn(el, side) → settings.{side}Dynamic — and the two never overwrite each other.
     Reading for display goes through (… || {}), so drawing the panel never writes.

     The pill labels use v-text, not a mustache: Blade copies an escaped mustache out as
     it stands, so a Blade echo of $dyn inside one reached Vue unexpanded and broke the
     whole canvas. --}}

@foreach([['primary', 'First Button'], ['secondary', 'Second Button']] as [$side, $sideLabel])
@php $dyn = "(editingElement.settings.{$side}Dynamic || {})"; @endphp
{{-- ══ ONE BUTTON ══ --}}
<div class="space-y-6 {{ $side === 'secondary' ? 'pt-6 border-t border-slate-100' : '' }}">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">{{ $sideLabel }}</div>

    {{-- Text --}}
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Button Text</label>
            <button @click.stop="openDynSrcMenu(fcDbDyn(editingElement, '{{ $side }}'), 'dynamic_source', 'text', $event)"
                    class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                    :class="{{ $dyn }}.dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                    title="Dynamic source">
                <i class="fa fa-database text-[10px]"></i>
            </button>
        </div>
        <div v-if="{{ $dyn }}.dynamic_source"
             class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
             @click.stop="openDynSrcMenu(fcDbDyn(editingElement, '{{ $side }}'), 'dynamic_source', 'text', $event)">
            <div class="flex items-center gap-2">
                <i :class="['fa', getDynSrcDef({{ $dyn }}.dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                <span class="text-[12px] font-bold text-[#0091ea]" v-text="getDynSrcDef({{ $dyn }}.dynamic_source).label"></span>
            </div>
            <button @click.stop="fcDbDyn(editingElement, '{{ $side }}').dynamic_source = ''"
                    class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                <i class="fa fa-times text-[10px]"></i>
            </button>
        </div>
        <input v-else type="text" v-model="editingElement.settings.{{ $side }}Text"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    </div>

    {{-- Link URL --}}
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link URL</label>
            <button @click.stop="openDynSrcMenu(fcDbDyn(editingElement, '{{ $side }}'), 'link_dynamic_source', 'link', $event)"
                    class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                    :class="{{ $dyn }}.link_dynamic_source ? 'bg-[#2271b1]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                    title="Dynamic source">
                <i class="fa fa-database text-[10px]"></i>
            </button>
        </div>
        <div v-if="{{ $dyn }}.link_dynamic_source"
             class="flex items-center justify-between px-3 py-2.5 bg-[#2271b1]/8 border border-[#0091ea]/25 rounded-lg cursor-pointer select-none"
             @click.stop="openDynSrcMenu(fcDbDyn(editingElement, '{{ $side }}'), 'link_dynamic_source', 'link', $event)">
            <div class="flex items-center gap-2">
                <i :class="['fa', getDynSrcDef({{ $dyn }}.link_dynamic_source).icon, 'text-[#0091ea] text-sm']"></i>
                <span class="text-[12px] font-bold text-[#0091ea]" v-text="getDynSrcDef({{ $dyn }}.link_dynamic_source).label"></span>
            </div>
            <button @click.stop="fcDbDyn(editingElement, '{{ $side }}').link_dynamic_source = ''"
                    class="w-5 h-5 flex items-center justify-center text-[#0091ea]/50 hover:text-red-500 transition-colors rounded">
                <i class="fa fa-times text-[10px]"></i>
            </button>
        </div>
        <input v-else type="text" v-model="editingElement.settings.{{ $side }}Url" placeholder="https://"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
    </div>

    {{-- Link Target --}}
    <div v-if="editingElement.settings.{{ $side }}Url || {{ $dyn }}.link_dynamic_source">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link Target</label>
        </div>
        <select v-model="editingElement.settings.{{ $side }}Target"
                class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
            <option value="_self">Same Window</option>
            <option value="_blank">New Window</option>
        </select>
    </div>

    {{-- Icon --}}
    <div class="space-y-3">
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.icon', ['key' => $side.'Icon', 'label' => 'Button Icon', 'fallback' => "'fas fa-plus'"])

        <div v-if="editingElement.settings.{{ $side }}Icon">
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Icon Position</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-full">
                <button @click="editingElement.settings.{{ $side }}IconPosition = 'left'"
                        :class="editingElement.settings.{{ $side }}IconPosition !== 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-1.5 text-[10px] font-bold uppercase rounded transition-all">Left</button>
                <button @click="editingElement.settings.{{ $side }}IconPosition = 'right'"
                        :class="editingElement.settings.{{ $side }}IconPosition === 'right' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-1.5 text-[10px] font-bold uppercase rounded transition-all">Right</button>
            </div>
        </div>
    </div>
</div>
@endforeach
<p class="text-[11px] text-slate-500 -mt-4">Clear a button's text and icon to show only the other one.</p>

{{-- ══ CONNECTOR ══ --}}
<div class="space-y-3">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Connector</div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.showConnector" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show a badge between the two buttons</span>
    </label>

    <div v-if="editingElement.settings.showConnector">
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Badge text</label>
        <input type="text" v-model="editingElement.settings.connectorText" placeholder="OR" maxlength="6"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">Two or three letters — "OR", "VS", "&amp;". It is decoration: screen readers skip it.</p>
    </div>
</div>

{{-- ══ LAYOUT ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Layout</div>

    <div>
        <div class="flex justify-between items-center mb-1.5">
            <label class="text-[11px] font-bold text-slate-500">Alignment</label>
            @include('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => 'fdbAlign'])
        </div>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            @foreach(['left' => 'fa-align-left', 'center' => 'fa-align-center', 'right' => 'fa-align-right'] as $alignValue => $alignIcon)
            <button @click="setResponsiveVal(editingElement.settings, 'align', device, '{{ $alignValue }}')"
                    :class="(getResponsiveVal(editingElement.settings, 'align', device) || 'center') === '{{ $alignValue }}' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] transition-all" title="{{ ucfirst($alignValue) }}">
                <i class="fa {{ $alignIcon }}"></i>
            </button>
            @endforeach
        </div>
        <p v-if="editingElement.settings.fullWidth" class="text-[11px] text-slate-500 mt-1.5">Full width is on, so there is no room left to align within.</p>
    </div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Gap between the buttons</label>
        <input type="text"
               :value="spDisplay(editingElement.settings, 'gap', 'desktop', false)"
               @input="spInput(editingElement.settings, 'gap', 'desktop', $event.target.value, false)"
               placeholder="0" title="Any CSS size: 12, 12px, 1rem"
               class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">0 joins the two into one shape. Anything more sets them side by side.</p>
    </div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.equalWidth" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Both buttons the same width</span>
    </label>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.fullWidth" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Fill the column's width</span>
    </label>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Stack one above the other</label>
        <select v-model="editingElement.settings.stackOn"
                class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
            <option value="never">Never — always side by side</option>
            <option value="mobile">On mobile</option>
            <option value="tablet">On tablet and mobile</option>
        </select>
    </div>
</div>

<div class="pt-4 border-t border-slate-50">
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.element-visibility')
</div>

<div class="pt-4 border-t border-slate-50">
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.css-attributes')
</div>
