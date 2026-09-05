{{-- Callout — Design tab.

     The style list comes from CalloutStyles::presetOptions() via FC_CAL_PRESET_OPTIONS.
     Every colour and number below overrides its preset's value and is left empty until
     it does, which is why switching style still moves anything the author has not
     touched; the placeholder on each field shows what the preset is giving it. --}}

{{-- ══ STYLE ══ --}}
<div>
    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Style</label>
    <select v-model="editingElement.settings.preset" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <option v-for="(name, slug) in fcCalPresetOptions" :key="slug" :value="slug">@{{ name }}</option>
    </select>
    <p class="text-[11px] text-slate-500 mt-2">
        Applies to every type, so a page's callouts look like a set. What each one means
        is the Type in the Content tab.
    </p>
</div>

{{-- ══ ICON ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Icon</div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Shape</label>
        <select v-model="editingElement.settings.iconStyle" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            <option value="">Follow the style</option>
            <option value="plain">Plain</option>
            <option value="badge">In a circle</option>
            <option value="header">Filled header bar</option>
            <option value="none">No icon</option>
        </select>
    </div>

    {{-- The builder's own icon picker, the same one the Icon Box and the Section
         Separator use, rather than a box to type a class name into. An author who knows
         the class already has nothing to gain from typing it, and one who does not had
         no way to find out what was available.

         Empty is still meaningful here and stays reachable through the picker's Clear:
         it means the icon that belongs to the chosen type, which is why the preview is
         told to show that icon rather than the generic star. --}}
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.icon', [
        'key' => 'icon',
        'label' => 'Icon',
        'fallback' => "fcCalVariantIcon(editingElement.settings.variant || 'note')",
        'emptyLabel' => "'Uses the ' + (fcCalVariantOptions[editingElement.settings.variant || 'note'] || 'Note') + ' icon'",
    ])

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Icon size</label>
        <input type="number" v-model.number="editingElement.settings.iconSize" min="10" max="48"
               :placeholder="fcCalVal(editingElement, 'iconSize')"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- ══ COLOURS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Colors</div>
    <p class="text-[11px] text-slate-500 -mt-2">
        Empty follows the type. The accent is the bar, the icon, the links and the buttons.
    </p>

    @foreach([
        ['accent', 'Accent'],
        ['bgColor', 'Background'],
        ['titleColor', 'Title'],
        ['bodyColor', 'Text'],
    ] as [$key, $label])
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-[11px] font-bold text-slate-500">{{ $label }}</label>
            <button @click="editingElement.settings.{{ $key }} = ''" title="Back to the type"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, '{{ $key }}')">
                <div :style="{ backgroundColor: fcCalColorPreview(editingElement, '{{ $key }}') }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.{{ $key }}"
                   :placeholder="fcCalColorPreview(editingElement, '{{ $key }}')"
                   class="flex-1 min-w-0 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    @endforeach
</div>

{{-- ══ BOX ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Box</div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Fill</label>
        <select v-model="editingElement.settings.fill" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            <option value="">Follow the style</option>
            <option value="tint">Tinted</option>
            <option value="white">White</option>
            <option value="none">None</option>
        </select>
    </div>

    <div class="grid grid-cols-2 gap-3">
        @foreach([
            ['barWidth', 'Left bar'],
            ['borderWidth', 'Border'],
            ['radius', 'Corner radius'],
            ['gap', 'Icon gap'],
            ['padY', 'Padding ↕'],
            ['padX', 'Padding ↔'],
        ] as [$key, $label])
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">{{ $label }}</label>
            <input type="number" v-model.number="editingElement.settings.{{ $key }}" min="0" max="80"
                   :placeholder="fcCalVal(editingElement, '{{ $key }}')"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        @endforeach
    </div>
</div>

{{-- ══ TYPOGRAPHY ══ --}}
<div class="space-y-5">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Typography</div>

    {{-- No size fields of their own here. The two typography blocks below each carry a
         Font Size, and two controls for one property is one control too many: an author
         sets one, sees nothing move because the other is still winning, and has no way
         to tell which is which. The style's own size remains the default. --}}

    <div>
        <div class="text-[11px] font-bold text-slate-500 mb-2">Title</div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'cal_title'])
    </div>
    <div>
        <div class="text-[11px] font-bold text-slate-500 mb-2">Text</div>
        @include('falcon-cms::admin.falcon-builder.partials.components.fields.typography', ['prefix' => 'cal_body'])
    </div>
</div>
