{{--
    The "Additional information" table on a product page.

    Shared by the simple and variable templates, which carried identical copies of it — and had
    already drifted apart once. Attribute rows honour the "Visible on the product page" checkbox
    from the admin's Attributes tab; before this, that checkbox was stored but never read, so
    ticking it changed nothing.

    Expects: $post
--}}
@php
    $shopData   = $post->shopData ?? null;
    $attributes = $shopData ? collect(falcon_product_attribute_definitions($shopData))
        ->filter(fn ($a) => $a['visible'] && !empty($a['values']))
        : collect();
@endphp

<table class="w-full border-collapse">
    <tbody>
        @if($shopData && $shopData->weight)
        <tr class="border-b border-gray-100">
            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">Weight</th>
            <td class="py-3 text-gray-600">{{ $shopData->weight }} {{ get_shop_option('shop_weight_unit', 'kg') }}</td>
        </tr>
        @endif

        @if($shopData && $shopData->dimensions)
        <tr class="border-b border-gray-100">
            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">Dimensions</th>
            <td class="py-3 text-gray-600">{{ $shopData->dimensions }} {{ get_shop_option('shop_dimensions_unit', 'cm') }}</td>
        </tr>
        @endif

        @foreach($attributes as $attribute)
        <tr class="border-b border-gray-100">
            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">{{ $attribute['name'] }}</th>
            <td class="py-3 text-gray-600">{{ implode(', ', $attribute['values']) }}</td>
        </tr>
        @endforeach

        @if($post->productCategories->count())
        <tr class="border-b border-gray-100">
            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">Category</th>
            <td class="py-3 text-gray-600">
                @foreach($post->productCategories as $cat)
                    {{ $cat->name }}{{ $loop->last ? '' : ', ' }}
                @endforeach
            </td>
        </tr>
        @endif
    </tbody>
</table>
