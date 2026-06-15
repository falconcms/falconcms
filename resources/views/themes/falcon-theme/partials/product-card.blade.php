{{-- Reusable product card (shop / archive / related products). Expects: $product --}}
<div class="group flex flex-col">
    <div class="relative mb-4">
        <a href="{{ get_falcon_permalink($product) }}" class="block relative pt-[100%] overflow-hidden bg-[#eef1f5]">
            @if($product->featured_image)
                <img src="{{ str_starts_with($product->featured_image, 'http') ? $product->featured_image : asset('storage/'.$product->featured_image) }}" alt="{{ $product->title }}" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-90 group-hover:opacity-100 transition-opacity">
            @else
                <img src="{{ asset('assets/images/placeholder.jpg') }}" alt="Placeholder" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-70">
            @endif
            @if(!$product->is_in_stock)
                <span class="absolute top-3 right-3 bg-red-600 text-white text-[11px] font-bold px-3 py-1 rounded-sm shadow-md uppercase tracking-wider z-10">Out of Stock</span>
            @endif
            @if($product->shopData && $product->shopData->sale_price)
                <span class="absolute top-3 left-3 bg-sky-100 text-sky-700 text-[13px] font-bold px-3 py-1 rounded-full shadow uppercase tracking-wide z-10">Sale!</span>
            @endif
        </a>
        <div class="absolute bottom-3 right-3 z-20">
            @include('falcon-cms::themes.falcon-theme.partials.wishlist-button', ['product' => $product])
        </div>
    </div>
    <div class="flex flex-col flex-grow text-left px-1">
        <div class="text-[12px] text-[#999] mb-0.5">
            @if($product->productCategories && $product->productCategories->count() > 0)
                {{ $product->productCategories->first()->name }}
            @elseif($product->taxonomyTerms && $product->taxonomyTerms->count() > 0)
                {{ $product->taxonomyTerms->first()->name }}
            @else
                Uncategorized
            @endif
        </div>
        <h2 class="text-[15px] font-bold text-heading hover:text-primary transition-colors mb-1 leading-tight">
            <a href="{{ get_falcon_permalink($product) }}">{{ $product->title }}</a>
        </h2>
        <div class="text-heading font-bold text-[14px] mb-3">
            @if($product->shopData && $product->shopData->sale_price)
                <span class="line-through text-[#a5a5a5] font-normal mr-1.5">{{ falcon_price_format($product->shopData->price) }}</span>
                <span>{{ falcon_price_format($product->shopData->sale_price) }}</span>
            @else
                <span>{{ falcon_price_format($product->shopData->price ?? 0) }}</span>
            @endif
        </div>
        <div class="mt-auto flex flex-wrap gap-2">
            @if(!$product->is_in_stock)
                <button disabled class="w-full text-center bg-gray-400 text-white cursor-not-allowed px-4 py-2.5 rounded-[3px] text-[13px] font-semibold uppercase tracking-wider">
                    Out of stock
                </button>
            @elseif(falcon_is_variable_product($product))
                <a href="{{ get_falcon_permalink($product) }}" class="w-full text-center bg-primary text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-primary-hover transition-colors duration-200">
                    Select Options
                </a>
            @else
                <button onclick="addToCart({{ $product->id }})" class="flex-1 bg-primary text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-primary-hover transition-colors duration-200">
                    Add to cart
                </button>
                <a href="{{ get_falcon_permalink($product) }}" class="flex-1 text-center bg-white text-primary border border-primary px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-gray-50 transition-colors duration-200">
                    See Detail
                </a>
            @endif
        </div>
    </div>
</div>
