{{--
    Renders fields as native settings table rows (label left, control right) so
    theme/plugin fields blend into the native Settings pages — no card, no
    background. Vars: $fields (array of field defs), $values (assoc name=>value).
--}}
<table class="w-full border-separate border-spacing-y-6">
    @foreach(($fields ?? []) as $field)
        @php
            $name  = $field['name'] ?? null;
            $label = $field['label'] ?? \Illuminate\Support\Str::headline($name ?? '');
            $value = $values[$name] ?? ($field['default'] ?? '');
            $desc  = $field['description'] ?? $field['help'] ?? null;
        @endphp
        @continue(!$name)
        <tr>
            <th scope="row" class="w-[200px] text-left align-top pt-2">
                <label for="opt-{{ $name }}" class="text-[14px] font-semibold text-[#1d2327]">{{ $label }}</label>
            </th>
            <td>
                <div class="max-w-[500px]">
                    @include('falcon-cms::admin.partials.field-control', ['field' => $field, 'value' => $value])
                </div>
                @if($desc)
                    <p class="text-[12px] text-[#646970] mt-1.5">{{ $desc }}</p>
                @endif
            </td>
        </tr>
    @endforeach
</table>
