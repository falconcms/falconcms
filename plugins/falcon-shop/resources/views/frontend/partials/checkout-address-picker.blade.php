{{--
    "Use a saved address" picker for one checkout section.

    Purely additive: the fields underneath are already pre-filled server-side from the customer's
    default address, so this only exists to switch to a *different* saved one. With JavaScript off
    the picker is hidden and the default still applies — nothing is lost.

    Expects: $section ('billing' or 'shipping')
--}}
@php
    $pickerSection = $section ?? 'billing';
    $pickerList = falcon_customer_addresses();
    $pickerDefault = falcon_default_customer_address($pickerSection);
@endphp

@if($pickerList->count() > 1 || ($pickerList->count() === 1 && $pickerSection === 'shipping'))
<div class="mb-6 falcon-address-picker" data-section="{{ $pickerSection }}" hidden>
    <label class="block text-[14px] font-semibold text-heading mb-2">Use a saved address</label>
    <select class="w-full border border-[#ddd] rounded-sm px-3 py-2.5 text-[14px] bg-white cursor-pointer outline-none focus:border-primary"
            data-address-picker>
        @foreach($pickerList as $pickerAddress)
            <option value="{{ $pickerAddress->id }}"
                    data-fields="{{ json_encode($pickerAddress->toCheckoutFields($pickerSection)) }}"
                    {{ $pickerDefault && $pickerDefault->id === $pickerAddress->id ? 'selected' : '' }}>
                {{ $pickerAddress->label ?: $pickerAddress->summary }}
            </option>
        @endforeach
        <option value="">Enter a new address&hellip;</option>
    </select>
</div>

@once
<script data-no-defer src="{{ falcon_plugin_asset('falcon-shop', 'frontend/js/checkout-address-picker.js') }}"></script>
@endonce
@endif
