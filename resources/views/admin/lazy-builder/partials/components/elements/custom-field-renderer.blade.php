{{-- Renders a single custom element field in the builder settings panel.
     Variables: $field (array), $fieldKey (string)
     Supported types: text/textfield, textarea, number, select, radio, toggle, checkbox,
       color, media/image, url, link, date, icon, slider, dimensions, typography, wysiwyg, repeater
     Extra keys: condition, dynamic (bool → enables dynamic source toggle), unit, min/max/step, options
--}}
@php
    $ft = $field['type'] ?? 'text';
    $ftMap = ['textfield'=>'text','colorpickeralpha'=>'color','colorpicker'=>'color','textarea_html'=>'wysiwyg'];
    if (isset($ftMap[$ft])) $ft = $ftMap[$ft];
    $fieldLabel = $field['label'] ?? $field['heading'] ?? $fieldKey;
    $fieldDesc  = $field['description'] ?? '';
    $allowDynamic = !empty($field['dynamic']);
    $dynKey = $fieldKey . '_dynamic';
@endphp

{{-- ── TEXT ─────────────────────────────────────────────────────────────────── --}}
@if(in_array($ft, ['text', 'textfield']))
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
        @if($allowDynamic)
        <button @click.stop="openDynSrcMenu(editingElement.settings, '{{ $dynKey }}', 'text', $event)"
                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                :class="editingElement.settings.{{ $dynKey }} ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                title="Dynamic source">
            <i class="fa fa-database text-[10px]"></i>
        </button>
        @endif
    </div>
    @if($allowDynamic)
    <template v-if="editingElement.settings.{{ $dynKey }}">
        <div class="mb-2">
            @include('cms-dashboard::admin.lazy-builder.partials.components.elements.custom-dynamic-select', ['dynKey' => $dynKey, 'dynCtx' => 'text'])
        </div>
    </template>
    <input v-else type="text" v-model="editingElement.settings.{{ $fieldKey }}"
           placeholder="{{ $field['placeholder'] ?? '' }}"
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    @else
    <input type="text" v-model="editingElement.settings.{{ $fieldKey }}"
           placeholder="{{ $field['placeholder'] ?? '' }}"
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    @endif
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── TEXTAREA ──────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'textarea')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <textarea v-model="editingElement.settings.{{ $fieldKey }}"
              rows="{{ $field['rows'] ?? 4 }}"
              placeholder="{{ $field['placeholder'] ?? '' }}"
              class="w-full border border-slate-200 rounded p-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea] focus:ring-1 focus:ring-[#0091ea]/10 transition-all"></textarea>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── NUMBER ───────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'number')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <input type="number" v-model.number="editingElement.settings.{{ $fieldKey }}"
           @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
           @if(isset($field['max'])) max="{{ $field['max'] }}" @endif
           @if(isset($field['step'])) step="{{ $field['step'] }}" @endif
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── SLIDER ───────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'slider')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
        <span class="text-[12px] font-bold text-[#0091ea]"
              v-text="(editingElement.settings.{{ $fieldKey }} ?? {{ $field['value'] ?? 0 }}) + '{{ $field['unit'] ?? '' }}'"></span>
    </div>
    <input type="range" v-model.number="editingElement.settings.{{ $fieldKey }}"
           min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? 100 }}" step="{{ $field['step'] ?? 1 }}"
           class="w-full h-1.5 appearance-none rounded-full bg-slate-200 accent-[#0091ea] cursor-pointer">
    <div class="flex justify-between text-[9px] text-slate-400 mt-1">
        <span>{{ $field['min'] ?? 0 }}{{ $field['unit'] ?? '' }}</span>
        <span>{{ $field['max'] ?? 100 }}{{ $field['unit'] ?? '' }}</span>
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── SELECT ───────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'select')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
        @if(!empty($field['responsive']))
            @include('cms-dashboard::admin.lazy-builder.partials.components.fields.responsive-mode', ['menu' => 'cfsel_' . $fieldKey])
        @endif
    </div>
    @if(!empty($field['responsive']))
    {{-- Responsive: per-device value via getResponsiveVal/setResponsiveVal --}}
    <select :value="getResponsiveVal(editingElement.settings, '{{ $fieldKey }}', device)"
            @change="setResponsiveVal(editingElement.settings, '{{ $fieldKey }}', device, $event.target.value)"
            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        @foreach($field['options'] ?? [] as $optVal => $optLabel)
            <option value="{{ $optVal }}">{{ $optLabel }}</option>
        @endforeach
    </select>
    @else
    <select v-model="editingElement.settings.{{ $fieldKey }}"
            class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        @foreach($field['options'] ?? [] as $optVal => $optLabel)
            <option value="{{ $optVal }}">{{ $optLabel }}</option>
        @endforeach
    </select>
    @endif
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── MULTISELECT (token dropdown via TomSelect) ───────────────────────────── --}}
@elseif(in_array($ft, ['multiselect', 'multi_select']))
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <select multiple
            v-tomselect="{ value: Array.isArray(editingElement.settings.{{ $fieldKey }}) ? editingElement.settings.{{ $fieldKey }} : [], placeholder: '{{ e($field['placeholder'] ?? 'Select…') }}', onChange: (v) => { editingElement.settings.{{ $fieldKey }} = Array.isArray(v) ? v : (v ? [v] : []) } }"
            class="w-full text-[13px]">
        @foreach($field['options'] ?? [] as $optVal => $optLabel)
            <option value="{{ $optVal }}">{{ $optLabel }}</option>
        @endforeach
    </select>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── RADIO ────────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'radio')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach($field['options'] ?? [] as $optVal => $optLabel)
        <button @click="editingElement.settings.{{ $fieldKey }} = '{{ $optVal }}'"
                :class="editingElement.settings.{{ $fieldKey }} === '{{ $optVal }}' ? 'bg-[#0091ea] text-white border-[#0091ea]' : 'bg-white text-slate-600 border-slate-200 hover:border-[#0091ea]'"
                class="px-4 py-1.5 text-[12px] font-semibold rounded border transition-all">
            {{ $optLabel }}
        </button>
        @endforeach
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── CHECKBOX ─────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'checkbox')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <div class="space-y-2">
        @foreach($field['options'] ?? [] as $optVal => $optLabel)
        <label class="flex items-center gap-2 cursor-pointer group">
            <input type="checkbox" value="{{ $optVal }}"
                   v-model="editingElement.settings.{{ $fieldKey }}"
                   class="w-4 h-4 rounded border-slate-300 accent-[#0091ea] cursor-pointer">
            <span class="text-[13px] text-slate-600 group-hover:text-slate-800">{{ $optLabel }}</span>
        </label>
        @endforeach
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── TOGGLE ───────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'toggle')
<div>
    @include('cms-dashboard::admin.lazy-builder.partials.components.fields.toggle', ['key' => $fieldKey, 'label' => $fieldLabel])
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── COLOR ────────────────────────────────────────────────────────────────── --}}
@elseif(in_array($ft, ['color', 'colorpicker', 'colorpickeralpha']))
<div>
    @include('cms-dashboard::admin.lazy-builder.partials.components.fields.color', ['key' => $fieldKey, 'label' => $fieldLabel, 'default' => $field['default'] ?? '#000000'])
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── MEDIA / IMAGE ────────────────────────────────────────────────────────── --}}
@elseif(in_array($ft, ['media', 'image']))
<div class="mb-2">
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <div v-if="!editingElement.settings.{{ $fieldKey }}"
         @click="openMediaModal('{{ $fieldKey }}')"
         class="w-full aspect-[16/10] border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-[#0091ea] hover:bg-blue-50/30 transition-all group">
        <div class="w-10 h-10 bg-[#0091ea] rounded-full flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform">
            <i class="fa fa-plus"></i>
        </div>
    </div>
    <div v-else class="space-y-3">
        <div class="relative group aspect-[16/10] bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
            <img :src="editingElement.settings.{{ $fieldKey }}" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button @click="openMediaModal('{{ $fieldKey }}')" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-[#0091ea] hover:bg-[#0091ea] hover:text-white transition-all shadow-sm">
                    <i class="fa fa-edit text-xs"></i>
                </button>
                <button @click="editingElement.settings.{{ $fieldKey }} = ''" class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-red-500 hover:bg-red-500 hover:text-white transition-all shadow-sm">
                    <i class="fa fa-trash text-xs"></i>
                </button>
            </div>
        </div>
        <div class="flex gap-2">
            <button @click="editingElement.settings.{{ $fieldKey }} = ''" class="flex-1 h-9 flex items-center justify-center border border-slate-200 rounded text-[11px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Remove</button>
            <button @click="openMediaModal('{{ $fieldKey }}')" class="flex-1 h-9 flex items-center justify-center bg-[#0091ea] text-white rounded text-[11px] font-bold hover:bg-[#007cc0] transition-colors">Edit</button>
        </div>
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── URL (text + inline upload, optional dynamic) ─────────────────────────── --}}
@elseif($ft === 'url')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
        @if($allowDynamic)
        <button @click.stop="openDynSrcMenu(editingElement.settings, '{{ $dynKey }}', 'link', $event)"
                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                :class="editingElement.settings.{{ $dynKey }} ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                title="Dynamic source">
            <i class="fa fa-database text-[10px]"></i>
        </button>
        @endif
    </div>
    @php
        $__urlBox = '<input type="text" v-model="editingElement.settings.' . $fieldKey . '" placeholder="' . e($field['placeholder'] ?? 'https://...') . '" class="flex-1 border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">';
    @endphp
    @if($allowDynamic)
    <template v-if="editingElement.settings.{{ $dynKey }}">
        <div class="mb-2">
            @include('cms-dashboard::admin.lazy-builder.partials.components.elements.custom-dynamic-select', ['dynKey' => $dynKey, 'dynCtx' => 'link'])
        </div>
    </template>
    <div v-else class="flex gap-2 items-center">
        {!! $__urlBox !!}
        <button @click="openMediaModal('{{ $fieldKey }}')" title="Upload / Browse"
                class="shrink-0 w-9 h-9 flex items-center justify-center bg-slate-100 border border-slate-200 rounded hover:bg-[#0091ea] hover:text-white hover:border-[#0091ea] text-slate-500 transition-all">
            <i class="fa fa-upload text-xs"></i>
        </button>
    </div>
    @else
    <div class="flex gap-2 items-center">
        {!! $__urlBox !!}
        <button @click="openMediaModal('{{ $fieldKey }}')" title="Upload / Browse"
                class="shrink-0 w-9 h-9 flex items-center justify-center bg-slate-100 border border-slate-200 rounded hover:bg-[#0091ea] hover:text-white hover:border-[#0091ea] text-slate-500 transition-all">
            <i class="fa fa-upload text-xs"></i>
        </button>
    </div>
    @endif
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── LINK (URL + target, optional dynamic) ────────────────────────────────── --}}
@elseif($ft === 'link')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
        @if($allowDynamic)
        <button @click.stop="openDynSrcMenu(editingElement.settings, '{{ $dynKey }}', 'link', $event)"
                class="w-6 h-6 flex items-center justify-center rounded border transition-all"
                :class="editingElement.settings.{{ $dynKey }} ? 'bg-[#0091ea]/10 text-[#0091ea] border-[#0091ea]/30' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                title="Dynamic source">
            <i class="fa fa-database text-[10px]"></i>
        </button>
        @endif
    </div>
    @if($allowDynamic)
    <template v-if="editingElement.settings.{{ $dynKey }}">
        <div class="mb-2">
            @include('cms-dashboard::admin.lazy-builder.partials.components.elements.custom-dynamic-select', ['dynKey' => $dynKey, 'dynCtx' => 'link'])
        </div>
    </template>
    @endif
    <div @if($allowDynamic) v-else @endif class="space-y-2">
        <input type="text" v-model="editingElement.settings.{{ $fieldKey }}"
               placeholder="{{ $field['placeholder'] ?? 'https://...' }}"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        <div class="flex items-center gap-2">
            <span class="text-[11px] text-slate-500 shrink-0">Open in:</span>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-0.5">
                <button @click="editingElement.settings.{{ $fieldKey }}_target = '_self'"
                        :class="editingElement.settings.{{ $fieldKey }}_target !== '_blank' ? 'bg-white text-slate-700 shadow-sm' : 'text-slate-400'"
                        class="px-3 py-1 text-[11px] font-semibold rounded transition-all">Same tab</button>
                <button @click="editingElement.settings.{{ $fieldKey }}_target = '_blank'"
                        :class="editingElement.settings.{{ $fieldKey }}_target === '_blank' ? 'bg-white text-slate-700 shadow-sm' : 'text-slate-400'"
                        class="px-3 py-1 text-[11px] font-semibold rounded transition-all">New tab</button>
            </div>
        </div>
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── BUTTON (label + url + target) ────────────────────────────────────────── --}}
@elseif($ft === 'button')
<div>
    @include('cms-dashboard::admin.lazy-builder.partials.components.fields.button', ['key' => $fieldKey, 'label' => $fieldLabel, 'placeholder' => $field['placeholder'] ?? 'Click here'])
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── DATE ─────────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'date')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <input type="date" v-model="editingElement.settings.{{ $fieldKey }}"
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── ICON picker (shared control — matches Icon Box element) ─────────────── --}}
@elseif($ft === 'icon')
<div>
    @include('cms-dashboard::admin.lazy-builder.partials.components.fields.icon', ['key' => $fieldKey, 'label' => $fieldLabel])
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── DIMENSIONS (shared control — units + responsive) ─────────────────────── --}}
@elseif($ft === 'dimensions')
<div>
    @include('cms-dashboard::admin.lazy-builder.partials.components.fields.dimensions', ['key' => $fieldKey, 'label' => $fieldLabel, 'unit' => $field['unit'] ?? 'px'])
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── TYPOGRAPHY (shared control — identical to Title element) ─────────────── --}}
@elseif($ft === 'typography')
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    @include('cms-dashboard::admin.lazy-builder.partials.components.fields.typography', ['prefix' => $fieldKey])
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── WYSIWYG ──────────────────────────────────────────────────────────────── --}}
@elseif(in_array($ft, ['wysiwyg', 'textarea_html']))
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
    </div>
    <div class="builder-rich-editor-wrapper">
        <textarea :id="'rich-editor-' + editingElement.id + '-' + '{{ $fieldKey }}'"
                  class="builder-rich-editor w-full border border-slate-200 rounded p-3 text-[13px]"
                  v-model="editingElement.settings.{{ $fieldKey }}"></textarea>
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

{{-- ── REPEATER ─────────────────────────────────────────────────────────────── --}}
@elseif($ft === 'repeater')
@php
    $subFields = [];
    $subTypeMap = ['textfield'=>'text','colorpickeralpha'=>'color','colorpicker'=>'color','textarea_html'=>'wysiwyg'];
    foreach (($field['fields'] ?? $field['params'] ?? []) as $sf) {
        $sfType  = $subTypeMap[$sf['type'] ?? 'text'] ?? ($sf['type'] ?? 'text');
        $sfKey   = $sf['param_name'] ?? trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($sf['heading'] ?? 'field')), '_');
        $sfLabel = $sf['heading'] ?? $sfKey;
        $sfOpts  = $sf['options'] ?? [];
        $subFields[] = compact('sfType', 'sfKey', 'sfLabel', 'sfOpts', 'sf');
    }
@endphp
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $fieldLabel }}</label>
        <button @click="(Array.isArray(editingElement.settings.{{ $fieldKey }}) ? editingElement.settings.{{ $fieldKey }} : (editingElement.settings.{{ $fieldKey }} = [])).push({ _open: true })"
                class="text-[11px] font-bold text-[#0091ea] hover:underline flex items-center gap-1">
            <i class="fa fa-plus text-[9px]"></i> Add Row
        </button>
    </div>

    <div v-if="Array.isArray(editingElement.settings.{{ $fieldKey }}) && editingElement.settings.{{ $fieldKey }}.length" class="space-y-2">
        <div v-for="(row, rowIdx) in editingElement.settings.{{ $fieldKey }}" :key="rowIdx"
             class="border border-slate-200 rounded-lg bg-white overflow-hidden">
            <div class="flex items-center justify-between px-3 py-2 bg-slate-50 border-b border-slate-100 cursor-pointer"
                 @click="row._open = !row._open">
                <span class="text-[11px] font-bold text-slate-500">
                    <i class="fa fa-chevron-down text-[9px] transition-transform mr-1" :style="{ transform: row._open ? 'rotate(180deg)' : 'rotate(0deg)' }"></i>
                    Row @{{ rowIdx + 1 }}
                    @if(count($subFields) > 0)
                    @php $__firstSf = $subFields[0]['sfKey']; @endphp
                    <span class="font-normal text-slate-400 ml-1" v-if="row.{{ $__firstSf }}" v-text="'— ' + String(row.{{ $__firstSf }} || '').slice(0,30)"></span>
                    @endif
                </span>
                <div class="flex items-center gap-2">
                    <button @click.stop="rowIdx > 0 && editingElement.settings.{{ $fieldKey }}.splice(rowIdx - 1, 0, editingElement.settings.{{ $fieldKey }}.splice(rowIdx, 1)[0])" class="text-slate-300 hover:text-[#0091ea] text-[10px]"><i class="fa fa-chevron-up"></i></button>
                    <button @click.stop="rowIdx < editingElement.settings.{{ $fieldKey }}.length - 1 && editingElement.settings.{{ $fieldKey }}.splice(rowIdx + 1, 0, editingElement.settings.{{ $fieldKey }}.splice(rowIdx, 1)[0])" class="text-slate-300 hover:text-[#0091ea] text-[10px]"><i class="fa fa-chevron-down"></i></button>
                    <button @click.stop="editingElement.settings.{{ $fieldKey }}.splice(rowIdx,1)" class="text-slate-300 hover:text-red-500 text-[10px]"><i class="fa fa-trash"></i></button>
                </div>
            </div>
            <div v-show="row._open !== false" class="p-3 space-y-3">
                @foreach($subFields as $sf)
                @php ['sfType'=>$sfType,'sfKey'=>$sfKey,'sfLabel'=>$sfLabel,'sfOpts'=>$sfOpts,'sf'=>$sfRaw] = $sf; @endphp
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1.5">{{ $sfLabel }}</label>
                    @if(in_array($sfType, ['text', 'textfield']))
                    <input type="text" v-model="row.{{ $sfKey }}" placeholder="{{ $sfRaw['placeholder'] ?? '' }}"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                    @elseif($sfType === 'textarea')
                    <textarea v-model="row.{{ $sfKey }}" rows="{{ $sfRaw['rows'] ?? 2 }}"
                              class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]"></textarea>
                    @elseif($sfType === 'number')
                    <input type="number" v-model.number="row.{{ $sfKey }}"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                    @elseif(in_array($sfType, ['color', 'colorpicker', 'colorpickeralpha']))
                    <div class="flex gap-2 items-center">
                        <div class="checkerboard rounded-full overflow-hidden w-7 h-7 border border-slate-200 cursor-pointer shrink-0"
                             @click="openColorPicker($event, row, '{{ $sfKey }}')">
                            <div :style="{ backgroundColor: row.{{ $sfKey }} || '#000000' }" class="w-full h-full rounded-full"></div>
                        </div>
                        <input type="text" v-model="row.{{ $sfKey }}" placeholder="#000000"
                               class="flex-1 border border-slate-200 rounded px-2 py-1.5 text-[11px] focus:outline-none focus:border-[#0091ea]">
                    </div>
                    @elseif(in_array($sfType, ['image', 'media']))
                    <div v-if="!row.{{ $sfKey }}" @click="openMediaModalForTarget(row, '{{ $sfKey }}')"
                         class="w-full h-20 border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-[#0091ea] hover:bg-blue-50/30 transition-all group">
                        <i class="fa fa-plus text-slate-400 group-hover:text-[#0091ea]"></i>
                    </div>
                    <div v-else class="space-y-1.5">
                        <div class="relative group h-20 bg-slate-100 rounded overflow-hidden border border-slate-200">
                            <img :src="row.{{ $sfKey }}" class="w-full h-full object-cover">
                            <button @click="row.{{ $sfKey }} = ''" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"><i class="fa fa-trash"></i></button>
                        </div>
                        <button @click="openMediaModalForTarget(row, '{{ $sfKey }}')"
                                class="w-full py-1.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded hover:bg-[#0091ea] hover:text-white transition-all">Change</button>
                    </div>
                    @elseif($sfType === 'url')
                    <div class="flex gap-1.5 items-center">
                        <input type="text" v-model="row.{{ $sfKey }}" placeholder="https://..."
                               class="flex-1 border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                        <button @click="openMediaModalForTarget(row, '{{ $sfKey }}')"
                                class="shrink-0 w-8 h-8 flex items-center justify-center bg-slate-100 border border-slate-200 rounded hover:bg-[#0091ea] hover:text-white text-slate-400 transition-all"><i class="fa fa-upload text-xs"></i></button>
                    </div>
                    @elseif($sfType === 'toggle')
                    <div class="flex bg-slate-50 border border-slate-100 rounded p-0.5 w-fit">
                        <button @click="row.{{ $sfKey }} = true" :class="row.{{ $sfKey }} ? 'bg-[#0091ea] text-white shadow-sm' : 'text-slate-400'" class="px-4 py-1 text-[10px] font-black uppercase rounded transition-all">On</button>
                        <button @click="row.{{ $sfKey }} = false" :class="!row.{{ $sfKey }} ? 'bg-white text-slate-600 shadow-sm' : 'text-slate-400'" class="px-4 py-1 text-[10px] font-black uppercase rounded transition-all">Off</button>
                    </div>
                    @elseif($sfType === 'select')
                    <select v-model="row.{{ $sfKey }}" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                        @foreach($sfOpts as $ov => $ol)<option value="{{ $ov }}">{{ $ol }}</option>@endforeach
                    </select>
                    @elseif($sfType === 'icon')
                    <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-hidden">
                        <div class="p-2 border-b border-slate-200">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                                <input type="text" v-model="searchIconQuery" placeholder="Search icons..."
                                       class="w-full pl-8 pr-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded focus:outline-none focus:border-[#0091ea]">
                            </div>
                        </div>
                        <div class="flex border-b border-slate-200 bg-slate-100/50">
                            <button v-for="tab in ['Solid', 'Regular', 'Brands']" :key="tab"
                                    @click="activeIconTab = tab"
                                    :class="activeIconTab === tab ? 'text-[#0091ea] bg-white border-b-2 border-b-[#0091ea]' : 'text-slate-400 hover:text-slate-600'"
                                    class="flex-1 py-1.5 text-[10px] font-bold uppercase transition-all">
                                @{{ tab }}
                            </button>
                        </div>
                        <div class="h-32 overflow-y-auto p-2 bg-white custom-scrollbar">
                            <div class="grid grid-cols-6 gap-1">
                                <button v-for="icon in filteredIcons" :key="icon"
                                        @click="selectIcon(row, icon, '{{ $sfKey }}')"
                                        :class="row.{{ $sfKey }} === icon ? 'border-[#0091ea] bg-blue-50 text-[#0091ea]' : 'border-slate-100 text-slate-600 hover:border-[#0091ea]'"
                                        class="aspect-square flex items-center justify-center rounded border transition-all p-1" :title="icon">
                                    <i :class="[icon, 'text-sm']"></i>
                                </button>
                            </div>
                            <div v-if="filteredIcons.length === 0" class="py-6 text-center text-[10px] text-slate-400">No icons found</div>
                        </div>
                        <div class="p-2 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 bg-white rounded border border-slate-200 flex items-center justify-center text-[#0091ea]">
                                    <i :class="row.{{ $sfKey }} || 'fas fa-star'" class="text-xs"></i>
                                </div>
                                <span class="text-[10px] text-slate-500 font-medium truncate max-w-[100px]" v-text="row.{{ $sfKey }} || 'No icon'"></span>
                            </div>
                            <button v-if="row.{{ $sfKey }}" @click="row.{{ $sfKey }} = ''" class="text-[10px] text-red-400 hover:text-red-500 font-bold uppercase">Clear</button>
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div v-else class="text-center py-4 border border-dashed border-slate-200 rounded-lg">
        <p class="text-[11px] text-slate-400">No rows yet. Click <strong>+ Add Row</strong> to start.</p>
    </div>
    @if($fieldDesc)<p class="text-[10px] text-slate-400 mt-1">{{ $fieldDesc }}</p>@endif
</div>

@endif
