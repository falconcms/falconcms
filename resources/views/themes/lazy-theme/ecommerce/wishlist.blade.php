@extends('falcon-cms::themes.lazy-theme.layouts.app')

@section('title', 'My Wishlist')

@section('content')
<div class="bg-gray-50 py-16 min-h-screen">
    <div class="container-custom">

        <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-heading">My Wishlist</h1>
                <p class="text-body mt-1">{{ $products->count() }} item{{ $products->count() === 1 ? '' : 's' }} saved.</p>
            </div>
            <a href="{{ get_lazy_shop_url() }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-sm border border-slate-200 bg-white text-slate-600 text-sm font-bold hover:border-primary hover:text-primary transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Continue shopping
            </a>
        </div>

        @if($products->isEmpty())
            <div class="bg-white rounded-sm border border-gray-100 p-16 text-center">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="heart" class="w-8 h-8 text-slate-300"></i>
                </div>
                <p class="text-body mb-5">Your wishlist is empty. Save products you love to find them here later.</p>
                <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-primary text-white px-6 py-2.5 rounded-sm font-bold hover:bg-primary-hover transition uppercase text-sm">Browse products</a>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach($products as $product)
                    @php
                        $img = $product->featured_image
                            ? (str_starts_with($product->featured_image, 'http') ? $product->featured_image : asset('storage/'.$product->featured_image))
                            : null;
                        $sd = $product->shopData;
                        $hasSale = $sd && $sd->sale_price;
                    @endphp
                    <div class="bg-white rounded-sm border border-gray-100 overflow-hidden group flex flex-col">
                        <div class="relative">
                            <form action="{{ route('shop.wishlist.remove') }}" method="POST" class="absolute top-2 right-2 z-10">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button type="submit" title="Remove" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/90 text-rose-500 border border-slate-100 hover:bg-rose-500 hover:text-white transition shadow-sm">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                            <a href="{{ get_lazy_permalink($product) }}" class="block aspect-square bg-[#f4f6f8] overflow-hidden">
                                @if($img)
                                    <img src="{{ $img }}" alt="{{ $product->title }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @endif
                            </a>
                        </div>
                        <div class="p-4 flex flex-col flex-grow">
                            <h3 class="text-sm font-bold text-heading hover:text-primary leading-snug line-clamp-2 mb-2">
                                <a href="{{ get_lazy_permalink($product) }}">{{ $product->title }}</a>
                            </h3>
                            <div class="text-sm font-bold mb-4">
                                @if($hasSale)
                                    <span class="line-through text-slate-400 font-normal mr-1.5">{{ lazy_price_format($sd->price) }}</span>
                                    <span class="text-primary">{{ lazy_price_format($sd->sale_price) }}</span>
                                @else
                                    <span class="text-heading">{{ lazy_price_format($sd->price ?? 0) }}</span>
                                @endif
                            </div>
                            @if(lazy_is_variable_product($product))
                                <a href="{{ get_lazy_permalink($product) }}" class="mt-auto block w-full text-center bg-primary text-white px-4 py-2 rounded-[3px] text-[13px] font-semibold hover:bg-primary-hover transition">Select Options</a>
                            @else
                                <form action="{{ route('shop.cart.add') }}" method="POST" class="mt-auto">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-[3px] text-[13px] font-semibold hover:bg-primary-hover transition">Add to cart</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@stop
