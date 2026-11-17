{{-- Dual Button — Design tab.

     A colour left empty is an answer, not a gap: no background is transparent (that is an
     outline or a text button), no border colour follows the background, and an empty
     hover colour keeps the resting one. Each field's placeholder says which.

     The looks at the top are starting points. Choosing one writes ordinary settings —
     fcDbApplyPreset() — and nothing remembers which was chosen, so every value stays
     editable and a later preset change in the code cannot restyle a published page.
     The highlighted look is read back from the settings (fcDbActivePreset), so it is
     still marked after a reload and lets go once a colour is changed by hand. --}}

{{-- ══ LOOKS ══ --}}
<div>
    <div class="flex items-center justify-between mb-2">
        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Start from a look</label>
        <span class="text-[10px] font-semibold text-slate-400"
              v-text="fcDbActivePreset(editingElement) ? 'Current: ' + fcDbPresets[fcDbActivePreset(editingElement)].name : 'Custom'"></span>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <button v-for="(preset, slug) in fcDbPresets" :key="slug"
                @click="fcDbApplyPreset(editingElement, slug)"
                :aria-pressed="fcDbActivePreset(editingElement) === slug ? 'true' : 'false'"
                :class="fcDbActivePreset(editingElement) === slug
                    ? 'border-[#0091ea] bg-[#e6f4fb] ring-1 ring-[#0091ea]'
                    : 'border-slate-200 hover:border-[#0091ea]'"
                class="relative border rounded px-2 py-2.5 transition-colors text-left">            <span class="flex items-stretch justify-center h-6 mb-1.5 pointer-events-none"
                  :style="{ gap: Math.min(6, preset.gap) + 'px' }">
                <span v-for="side in ['primary', 'secondary']" :key="side"
                      :style="fcDbPresetSwatch(preset, side)"></span>
            </span>
            <span class="text-[11px] font-semibold block text-center"
                  :class="fcDbActivePreset(editingElement) === slug ? 'text-[#0091ea]' : 'text-slate-600'"
                  v-text="preset.name"></span>
        </button>
    </div>
    <p class="text-[11px] text-slate-500 mt-1.5">Sets the colours, gap and corners below. Labels and links are left alone.</p>
</div>

{{-- ══ COLOURS ══ --}}
@foreach([['primary', 'First button'], ['secondary', 'Second button']] as [$side, $sideLabel])
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">{{ $sideLabel }}</div>

    @foreach([
        ['Bg', 'Background', 'transparent'],
        ['Color', 'Text', 'inherit'],
        ['HoverBg', 'Background on hover', 'no change'],
        ['HoverColor', 'Text on hover', 'no change'],
    ] as [$suffix, $label, $emptyMeans])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }}</label>
            <button @click="editingElement.settings.{{ $side.$suffix }} = ''" title="Clear"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, '{{ $side.$suffix }}')">
                <div :style="{ backgroundColor: editingElement.settings.{{ $side.$suffix }} || 'transparent' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $side.$suffix }}"
                   placeholder="{{ $emptyMeans }}"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach
</div>
@endforeach

{{-- ══ CONNECTOR ══ --}}
<div v-if="editingElement.settings.showConnector" class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Connector badge</div>

    @foreach([['connectorBg', 'Background', '#ffffff'], ['connectorColor', 'Text', '#1d2327']] as [$key, $label, $fallback])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }}</label>
            <button @click="editingElement.settings.{{ $key }} = ''" title="Clear"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, '{{ $key }}')">
                <div :style="{ backgroundColor: editingElement.settings.{{ $key }} || '{{ $fallback }}' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $key }}"
                   placeholder="{{ $fallback }}"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Size</label>
        <input type="text"
               :value="spDisplay(editingElement.settings, 'connectorSize', 'desktop', false)"
               @input="spInput(editingElement.settings, 'connectorSize', 'desktop', $event.target.value, false)"
               placeholder="30" title="Any CSS size: 30, 30px, 2rem"
               class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- ══ SHAPE ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Shape</div>

    {{-- Border width, corners and icon gap: one text box each, like Padding — any CSS size
         ("2", "2px", "0.5rem"), a bare number is px, and each is set per device. --}}
    <div>
        <div class="flex justify-between items-center mb-1.5">
            <label class="text-[11px] font-bold text-slate-500">Size</label>
            <div class="flex gap-1 items-center">
                <button @click="['borderWidth','radius','iconGap'].forEach(k => setResponsiveVal(editingElement.settings, k, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                @include('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => 'fdbShape'])
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2">
            @foreach([
                ['borderWidth', 'Border', '2'],
                ['radius', 'Corners', '6'],
                ['iconGap', 'Icon gap', '8'],
            ] as [$key, $label, $hint])
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block text-center">{{ $label }}</label>
                <input type="text"
                       :value="spDisplay(editingElement.settings, '{{ $key }}', device, false)"
                       @input="spInput(editingElement.settings, '{{ $key }}', device, $event.target.value, false)"
                       :placeholder="spHint(editingElement.settings, '{{ $key }}', device, false, '{{ $hint }}')"
                       title="Any CSS size: 2, 2px, 0.5rem"
                       class="w-full min-w-0 border border-slate-200 rounded px-1 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            @endforeach
        </div>
        <p class="text-[11px] text-slate-500 mt-1.5">Corners at 999 make a pill. A joined pair rounds only its outer corners.</p>
    </div>

    {{-- Border colours, beside the width they belong to. Empty is an answer here too: the
         border takes the button's background, so a width alone draws nothing you can see. --}}
    @foreach([
        ['BorderColor', 'Border color', 'same as background'],
        ['HoverBorderColor', 'Border color on hover', 'hover background'],
    ] as [$suffix, $groupLabel, $emptyMeans])
    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-2">{{ $groupLabel }}</label>
        <div class="space-y-2">
            @foreach([['primary', 'First'], ['secondary', 'Second']] as [$side, $sideLabel])
            <div class="flex gap-2 items-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase w-12 shrink-0">{{ $sideLabel }}</span>
                <div class="checkerboard rounded-full overflow-hidden w-8 h-8 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, '{{ $side.$suffix }}')">
                    <div :style="{ backgroundColor: editingElement.settings.{{ $side.$suffix }} || 'transparent' }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.{{ $side.$suffix }}"
                       placeholder="{{ $emptyMeans }}"
                       class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <button @click="editingElement.settings.{{ $side.$suffix }} = ''" title="Clear"
                        class="text-slate-300 hover:text-red-500 transition-colors shrink-0">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">On hover</label>
        <select v-model="editingElement.settings.hoverEffect"
                class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
            <option value="none">Colours only</option>
            <option value="lift">Lift</option>
            <option value="grow">Grow</option>
        </select>
    </div>

    {{-- Padding: one box per side, any CSS size, per device --}}
    <div>
        <div class="flex justify-between items-center mb-1.5">
            <label class="text-[11px] font-bold text-slate-500">Padding</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Right','Bottom','Left'].forEach(s => setResponsiveVal(editingElement.settings, 'padding' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa fa-undo text-[10px]"></i>
                </button>
                @include('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => 'fdbPadding'])
            </div>
        </div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.spacing', [
            'key' => 'padding',
            'placeholders' => ['Top' => '14', 'Right' => '28', 'Bottom' => '14', 'Left' => '28'],
        ])
    </div>
</div>

{{-- ══ TYPOGRAPHY ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Typography</div>
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'db'])
</div>

{{-- ══ MARGIN (responsive) ══ --}}
<div class="pt-4 border-t border-slate-50">
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">Margin</label>
        <div class="flex gap-1 items-center">
            <button @click="['Top','Bottom'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset Value" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
            @include('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => 'fdbMargin'])
        </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
        @foreach(['Top', 'Bottom'] as $side)
        <div class="flex flex-col gap-1">
            <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">{{ $side }}</label>
            <input type="text" :value="spDisplay(editingElement.settings, 'margin{{ $side }}', device, true)" @input="spInput(editingElement.settings, 'margin{{ $side }}', device, $event.target.value, true)" :placeholder="spHint(editingElement.settings, 'margin{{ $side }}', device, true, '10')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
        @endforeach
    </div>
</div>
