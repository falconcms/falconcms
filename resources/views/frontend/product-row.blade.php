{{--
    A titled row of product cards — related products, upsells, cross-sells.

    Each of those was going to need the same heading + 4-column grid, so they share this instead
    of three near-identical copies drifting apart the way the two archive templates did.

    Expects: $products (collection), $heading (string)
    Optional: $subheading
--}}
@if(($products ?? collect())->count() > 0)
<div class="mt-24">
    <h2 class="text-[32px] font-bold text-heading {{ !empty($subheading) ? 'mb-2' : 'mb-10' }}">{{ $heading }}</h2>

    @if(!empty($subheading))
        <p class="text-body text-[15px] mb-10">{{ $subheading }}</p>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-12">
        @foreach($products as $rowProduct)
            @include('falcon-cms::themes.falcon-theme.partials.product-card', ['product' => $rowProduct])
        @endforeach
    </div>
</div>
@endif
