{{--
    Renders a single field's control (input/widget only — no label, no
    description, no row wrapper). Shared by the card-style options-fields
    partial and the native-style settings rows so field-type handling lives in
    one place. Vars: $field (def array), $value (current value).
--}}
@php
    $name  = $field['name'] ?? null;
    $type  = $field['type'] ?? 'text';
    $value = $value ?? ($field['default'] ?? '');
    $ph    = $field['placeholder'] ?? '';
@endphp
@if($name)
    @switch($type)
        @case('textarea')
            <textarea id="opt-{{ $name }}" name="{{ $name }}" rows="4" placeholder="{{ $ph }}"
                class="w-full rounded border border-[#8c8f94] px-3 py-2 text-[13.5px] focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">{{ $value }}</textarea>
            @break
        @case('checkbox')
            <label class="inline-flex items-center gap-2 cursor-pointer select-none pt-1">
                <input type="checkbox" id="opt-{{ $name }}" name="{{ $name }}" value="1" @checked((string) $value === '1' || $value === true)
                    class="w-4 h-4 accent-[#2271b1] cursor-pointer">
                <span class="text-[13.5px] text-[#1d2327]">{{ $field['checkbox_label'] ?? 'Enable' }}</span>
            </label>
            @break
        @case('select')
            <select id="opt-{{ $name }}" name="{{ $name }}"
                class="w-full sm:w-auto min-w-[200px] rounded border border-[#8c8f94] px-3 h-9 text-[13.5px] bg-white focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">
                @foreach(($field['options'] ?? []) as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                @endforeach
            </select>
            @break
        @case('color')
            <input type="color" id="opt-{{ $name }}" name="{{ $name }}" value="{{ $value ?: '#000000' }}"
                class="h-9 w-16 rounded border border-[#8c8f94] cursor-pointer p-0.5">
            @break
        @case('radio')
            <div class="flex flex-col gap-1.5 pt-1">
                @foreach(($field['options'] ?? []) as $optValue => $optLabel)
                    <label class="inline-flex items-center gap-2 cursor-pointer text-[13.5px] text-[#1d2327]">
                        <input type="radio" name="{{ $name }}" value="{{ $optValue }}" @checked((string) $value === (string) $optValue) class="accent-[#2271b1]">
                        {{ $optLabel }}
                    </label>
                @endforeach
            </div>
            @break
        @case('multiselect')
            @php
                $selected = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
                $selected = array_values(array_map('strval', (array) $selected));
                $msConfig = ['options' => (object) ($field['options'] ?? []), 'selected' => $selected];
            @endphp
            <div class="fms-multiselect" data-field="{{ $name }}" data-config="{{ json_encode($msConfig) }}" data-placeholder="{{ $field['placeholder'] ?? 'Type to search…' }}">
                <div class="fms-control">
                    <div class="fms-chips"></div>
                    <input type="text" class="fms-search" autocomplete="off" placeholder="{{ $field['placeholder'] ?? 'Type to search…' }}">
                </div>
                <div class="fms-dropdown" hidden></div>
            </div>
            @break
        @case('date')
            <input type="date" id="opt-{{ $name }}" name="{{ $name }}" value="{{ $value }}"
                class="rounded border border-[#8c8f94] px-3 h-9 text-[13.5px] focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">
            @break
        @case('image')
            <input type="hidden" id="opt-{{ $name }}" name="{{ $name }}" value="{{ $value }}">
            <div class="flex items-start gap-3">
                <img id="opt-prev-{{ $name }}" src="{{ falcon_safe_url($value) }}" alt="" style="{{ $value ? '' : 'display:none;' }}"
                     class="w-20 h-20 object-cover rounded border border-[#c3c4c7] bg-[#f0f0f1]">
                <div class="flex flex-col gap-1.5">
                    <button type="button" onclick="falconOptPickImage('{{ $name }}')"
                        class="inline-flex items-center gap-1.5 px-3 h-8 border border-[#8c8f94] rounded text-[12.5px] text-[#2271b1] hover:bg-[#f6f7f7] transition-colors">
                        <span class="material-symbols-outlined text-[16px]">image</span> Choose image
                    </button>
                    <button type="button" onclick="falconOptClearImage('{{ $name }}')"
                        class="text-[11.5px] text-[#b32d2e] hover:underline text-left">Remove</button>
                </div>
            </div>
            @break
        @case('file')
            <input type="hidden" id="opt-{{ $name }}" name="{{ $name }}" value="{{ $value }}">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" onclick="falconOptPickFile('{{ $name }}')"
                    class="inline-flex items-center gap-1.5 px-3 h-8 border border-[#8c8f94] rounded text-[12.5px] text-[#2271b1] hover:bg-[#f6f7f7] transition-colors">
                    <span class="material-symbols-outlined text-[16px]">attach_file</span> Choose file
                </button>
                <a id="opt-file-{{ $name }}" href="{{ falcon_safe_url($value) }}" target="_blank" rel="noopener"
                   class="text-[12.5px] text-[#2271b1] underline break-all" style="{{ $value ? '' : 'display:none;' }}">{{ $value ? basename($value) : '' }}</a>
                <button type="button" id="opt-file-rm-{{ $name }}" onclick="falconOptClearFile('{{ $name }}')"
                    class="text-[11.5px] text-[#b32d2e] hover:underline" style="{{ $value ? '' : 'display:none;' }}">Remove</button>
            </div>
            @break
        @case('range')
            @php $rv = ($value !== '' && $value !== null) ? $value : ($field['default'] ?? 0); @endphp
            <div class="flex items-center gap-3 max-w-md">
                <input type="range" id="opt-{{ $name }}" name="{{ $name }}" value="{{ $rv }}"
                    min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? 100 }}" step="{{ $field['step'] ?? 1 }}"
                    class="flex-1 accent-[#2271b1] cursor-pointer" oninput="var o=document.getElementById('optval-{{ $name }}'); if(o)o.textContent=this.value;">
                <span id="optval-{{ $name }}" class="text-[13px] font-mono text-[#1d2327] min-w-[3ch] text-right">{{ $rv }}</span>
            </div>
            @break
        @case('tags')
            @php
                $tagVals = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
                $tagVals = array_values(array_map('strval', (array) $tagVals));
                $optMap = [];
                foreach ($tagVals as $t) { $optMap[$t] = $t; }
                foreach (($field['suggestions'] ?? []) as $s) { $optMap[(string) $s] = (string) $s; }
            @endphp
            <div class="fms-multiselect" data-field="{{ $name }}" data-tags="1" data-config="{{ json_encode(['options' => (object) $optMap, 'selected' => $tagVals]) }}" data-placeholder="{{ $field['placeholder'] ?? 'Type and press Enter…' }}">
                <div class="fms-control"><div class="fms-chips"></div><input type="text" class="fms-search" autocomplete="off" placeholder="{{ $field['placeholder'] ?? 'Type and press Enter…' }}"></div>
                <div class="fms-dropdown" hidden></div>
            </div>
            @break
        @case('wysiwyg')
            <textarea id="opt-{{ $name }}" name="{{ $name }}" class="fms-wysiwyg">{{ $value }}</textarea>
            @break
        @case('repeater')
            @php
                $rows = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
                if (! is_array($rows)) { $rows = []; }
                $subFields = $field['fields'] ?? [];
            @endphp
            <div class="fms-repeater" data-field="{{ $name }}">
                <div class="fms-rows">
                    @foreach($rows as $ri => $row)
                        @include('falcon-cms::admin.partials.repeater-row', ['name' => $name, 'index' => $ri, 'subFields' => $subFields, 'row' => (array) $row])
                    @endforeach
                </div>
                <template class="fms-row-tpl">@include('falcon-cms::admin.partials.repeater-row', ['name' => $name, 'index' => '__INDEX__', 'subFields' => $subFields, 'row' => []])</template>
                <button type="button" class="fms-add-row inline-flex items-center gap-1.5 px-3 h-8 border border-dashed border-[#8c8f94] rounded text-[12.5px] text-[#2271b1] hover:bg-[#f6f7f7] transition-colors">
                    <span class="material-symbols-outlined text-[16px]">add</span> Add {{ $field['button_label'] ?? 'row' }}
                </button>
            </div>
            @break
        @default
            <input type="{{ in_array($type, ['number','email','password','url']) ? $type : 'text' }}"
                id="opt-{{ $name }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $ph }}"
                autocomplete="off"
                class="w-full rounded border border-[#8c8f94] px-3 h-9 text-[13.5px] focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">
    @endswitch
@endif
