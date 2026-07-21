{{-- One repeater row. Vars: $name (repeater option key), $index (row key), $subFields, $row (values). --}}
<div class="fms-row relative border border-[#e0e2e5] rounded bg-[#fafbfc] p-3 pr-9 mb-2">
    <button type="button" class="fms-row-remove absolute top-2 right-2 w-6 h-6 grid place-items-center rounded text-[#b32d2e] hover:bg-[#fdecec]" aria-label="Remove row" title="Remove">
        <span class="material-symbols-outlined text-[18px]">close</span>
    </button>

    @foreach($subFields as $sf)
        @php
            $sn  = $sf['name'] ?? null;
            $st  = $sf['type'] ?? 'text';
            $sl  = $sf['label'] ?? \Illuminate\Support\Str::headline($sn ?? '');
            $sv  = $row[$sn] ?? ($sf['default'] ?? '');
            $sph = $sf['placeholder'] ?? '';
            $key = $name . '[' . $index . '][' . $sn . ']';
        @endphp
        @continue(!$sn)
        <div class="mb-2 last:mb-0">
            <label class="block text-[12px] font-semibold text-[#50575e] mb-1">{{ $sl }}</label>
            @switch($st)
                @case('textarea')
                    <textarea name="{{ $key }}" rows="2" placeholder="{{ $sph }}"
                        class="w-full rounded border border-[#8c8f94] px-2.5 py-1.5 text-[13px] focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">{{ $sv }}</textarea>
                    @break
                @case('checkbox')
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked((string) $sv === '1' || $sv === true) class="w-4 h-4 accent-[#2271b1]">
                        <span class="text-[13px] text-[#1d2327]">{{ $sf['checkbox_label'] ?? 'Enable' }}</span>
                    </label>
                    @break
                @case('select')
                    <select name="{{ $key }}"
                        class="w-full rounded border border-[#8c8f94] px-2.5 h-8 text-[13px] bg-white focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">
                        @foreach(($sf['options'] ?? []) as $ov => $ol)
                            <option value="{{ $ov }}" @selected((string) $sv === (string) $ov)>{{ $ol }}</option>
                        @endforeach
                    </select>
                    @break
                @case('color')
                    <input type="color" name="{{ $key }}" value="{{ $sv ?: '#000000' }}" class="h-8 w-14 rounded border border-[#8c8f94] cursor-pointer p-0.5">
                    @break
                @default
                    <input type="{{ in_array($st, ['number','email','url','date']) ? $st : 'text' }}" name="{{ $key }}" value="{{ $sv }}" placeholder="{{ $sph }}" autocomplete="off"
                        class="w-full rounded border border-[#8c8f94] px-2.5 h-8 text-[13px] focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">
            @endswitch
        </div>
    @endforeach
</div>
