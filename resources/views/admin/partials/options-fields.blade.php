{{-- Card-style option rows. Vars: $fields (array of field defs), $values (assoc name=>value). --}}
@foreach(($fields ?? []) as $field)
    @php
        $name  = $field['name'] ?? null;
        $label = $field['label'] ?? \Illuminate\Support\Str::headline($name ?? '');
        $value = $values[$name] ?? ($field['default'] ?? '');
    @endphp
    @continue(!$name)
    <div class="flex flex-col sm:flex-row gap-2 sm:gap-6 px-5 py-4 border-b border-[#f0f0f1] last:border-b-0">
        <label for="opt-{{ $name }}" class="sm:w-52 shrink-0 text-[14px] font-semibold text-[#1d2327] pt-1.5">{{ $label }}</label>
        <div class="flex-1 min-w-0">
            @include('falcon-cms::admin.partials.field-control', ['field' => $field, 'value' => $value])

            @php $fieldDesc = $field['description'] ?? $field['help'] ?? null; @endphp
            @if($fieldDesc)
                <p class="mt-1.5 text-[12px] leading-relaxed text-[#646970]">{{ $fieldDesc }}</p>
            @endif
        </div>
    </div>
@endforeach
