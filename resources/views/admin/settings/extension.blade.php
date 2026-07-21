{{--
    Renders theme/plugin-registered inline fields into a native settings screen.
    Echoed from a screen's falcon_*_settings_form_bottom hook, so it lives inside
    the native <form> and saves through that screen's controller.

    Vars:
      $screen       — 'general' | 'seo' | 'api' | 'integrations' | 'shop'
      $optionPrefix — prepended when reading a field's stored value. Screens that
                      namespace their keys (Shop saves every key as "shop_<name>")
                      pass that prefix so values round-trip.
      $alpineTabVar — when set (e.g. 'tab' for Shop), the screen has its own
                      client-side tab UI: fields are grouped by their target tab
                      and each group is wrapped so it shows only for that tab.
                      When null, fields render as flat native rows at the bottom.

    Fields render as native settings rows (no card / no background), matching the
    surrounding fields.
--}}
@php
    $screen       = $screen ?? 'general';
    $optionPrefix = $optionPrefix ?? '';
    $alpineTabVar = $alpineTabVar ?? null;
    $ext          = app(\FalconCms\Core\Support\SettingsExtension::class);

    $valuesFor = function ($fields) use ($optionPrefix) {
        $values = [];
        foreach ($fields as $f) {
            $values[$f['name']] = get_cms_option($optionPrefix . $f['name'], $f['default'] ?? '');
        }
        return $values;
    };
@endphp

@if($alpineTabVar)
    {{-- Screen with its own tab UI (Shop): render each field group inside the
         matching tab panel so it only shows when that tab is active. --}}
    @php $groups = $ext->fieldsForScreenGrouped($screen); @endphp
    @if($groups->isNotEmpty())
        @foreach($groups as $tabId => $groupFields)
            <div x-show="{{ $alpineTabVar }} === '{{ $tabId }}'" x-transition>
                @include('falcon-cms::admin.partials.settings-fields-native', [
                    'fields' => $groupFields->values()->all(),
                    'values' => $valuesFor($groupFields),
                ])
            </div>
        @endforeach

        @include('falcon-cms::admin.partials.options-fields-assets')
    @endif
@elseif($ext->hasInline($screen))
    {{-- Flat inline screens (General/SEO/API/Integrations). --}}
    @php $fields = $ext->inlineFields($screen); @endphp
    @include('falcon-cms::admin.partials.settings-fields-native', [
        'fields' => $fields->all(),
        'values' => $valuesFor($fields),
    ])

    @include('falcon-cms::admin.partials.options-fields-assets')
@endif
