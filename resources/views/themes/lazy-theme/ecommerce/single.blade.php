@extends('cms-dashboard::themes.lazy-theme.layouts.app')

@section('title', $post->title)

@section('content')
<?php do_lazy_action('lazy_before_single_product', $post); ?>
<div class="bg-white py-12 min-h-screen">
    <div class="container-custom">

        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-8" aria-label="Breadcrumb">
            <ol class="list-none p-0 inline-flex">
                <li class="flex items-center">
                    <a href="{{ url('/') }}" class="hover:text-primary transition">Home</a>
                    <svg class="w-3 h-3 mx-3" fill="currentColor" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li class="flex items-center">
                    <a href="{{ url('/product') }}" class="hover:text-primary transition">Shop</a>
                    <svg class="w-3 h-3 mx-3" fill="currentColor" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li class="text-gray-800 font-medium" aria-current="page">{{ $post->title }}</li>
            </ol>
        </nav>

        <div class="flex flex-col md:flex-row gap-12">

            <!-- Product Images -->
            <?php do_lazy_action('lazy_before_product_images', $post); ?>
            <div class="w-full md:w-1/2">
                <div class="bg-gray-50 rounded-lg overflow-hidden border border-gray-100 shadow-sm relative pt-[100%]">
                    @if($post->thumbnail)
                        <img id="main-image" src="{{ url($post->thumbnail) }}" alt="{{ $post->title }}" class="absolute inset-0 w-full h-full object-cover">
                    @else
                        <div class="absolute inset-0 flex items-center justify-center text-gray-400">No Image</div>
                    @endif
                    @if(!$post->is_in_stock)
                        <span class="absolute top-4 right-4 bg-red-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-sm uppercase tracking-wider shadow-lg z-10">Out of Stock</span>
                    @endif
                    @if($post->sale_price)
                        <span class="absolute top-4 left-4 bg-sky-100 text-sky-700 text-[14px] font-bold px-3.5 py-1.5 rounded-md uppercase tracking-wide z-10">Sale!</span>
                    @endif
                </div>
                @if($post->gallery && count($post->gallery) > 0)
                <div class="grid grid-cols-4 gap-4 mt-4">
                    <div class="relative pt-[100%] rounded border border-gray-200 cursor-pointer hover:border-primary overflow-hidden" onclick="document.getElementById('main-image').src='{{ url($post->thumbnail) }}'">
                        <img src="{{ url($post->thumbnail) }}" class="absolute inset-0 w-full h-full object-cover">
                    </div>
                    @foreach($post->gallery as $img)
                    <div class="relative pt-[100%] rounded border border-gray-200 cursor-pointer hover:border-primary overflow-hidden" onclick="document.getElementById('main-image').src='{{ url($img) }}'">
                        <img src="{{ url($img) }}" class="absolute inset-0 w-full h-full object-cover">
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <?php do_lazy_action('lazy_after_product_images', $post); ?>

            <!-- Product Info -->
            <div class="w-full md:w-1/2 flex flex-col justify-center">

                <!-- Title -->
                <?php do_lazy_action('lazy_simple_before_product_title', $post); ?>
                <?php
                    $productTitleHtml = '<h1 class="text-3xl md:text-4xl font-bold text-heading mb-4">'
                        . e($post->title) . '</h1>';
                    $productTitleHtml = apply_lazy_filters('lazy_simple_product_title', $productTitleHtml, $post);
                    echo $productTitleHtml;
                ?>
                <?php do_lazy_action('lazy_simple_after_product_title', $post); ?>

                <!-- Price -->
                <?php do_lazy_action('lazy_simple_before_product_price', $post); ?>
                <?php
                    ob_start();
                ?>
                <div class="text-2xl font-medium text-heading mb-6 border-b border-gray-100 pb-6">
                    @if($post->sale_price)
                        <span class="line-through text-gray-400 text-lg mr-2">{{ lazy_price_format($post->price) }}</span>
                        <span class="text-primary">{{ lazy_price_format($post->sale_price) }}</span>
                    @else
                        <span class="text-primary">{{ lazy_price_format($post->price ?? 0) }}</span>
                    @endif
                </div>
                <?php
                    $priceHtml = ob_get_clean();
                    $priceHtml = apply_lazy_filters('lazy_simple_product_price', $priceHtml, $post);
                    echo $priceHtml;
                ?>
                <?php do_lazy_action('lazy_simple_after_product_price', $post); ?>

                @if($post->shopData && $post->shopData->manage_stock)
                <div class="mb-6 -mt-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                        {{ $post->shopData->stock_quantity }} in stock
                    </span>
                </div>
                @endif

                <!-- Short Description -->
                @php $shortDescription = !empty($post->shopData->short_description) ? $post->shopData->short_description : $post->excerpt; @endphp
                <?php do_lazy_action('lazy_simple_before_short_description', $post); ?>
                @if($shortDescription)
                <?php
                    $shortDescHtml = '<div class="prose text-body mb-8">' . $shortDescription . '</div>';
                    $shortDescHtml = apply_lazy_filters('lazy_simple_short_description', $shortDescHtml, $post);
                    echo $shortDescHtml;
                ?>
                @endif
                <?php do_lazy_action('lazy_simple_after_short_description', $post); ?>

                <!-- Add to Cart -->
                @if(!$post->is_in_stock)
                    <?php do_lazy_action('lazy_simple_before_add_to_cart_form', $post); ?>
                    <div class="mb-10 pb-10 border-b border-gray-100">
                        <?php do_lazy_action('lazy_simple_out_of_stock_button', $post); ?>
                        @if(!has_lazy_action('lazy_simple_out_of_stock_button'))
                        <button disabled class="w-full bg-gray-400 text-white font-bold h-12 rounded cursor-not-allowed uppercase text-sm tracking-wider">
                            Out of stock
                        </button>
                        @endif
                    </div>
                    <?php do_lazy_action('lazy_simple_after_add_to_cart_form', $post); ?>
                @else
                    <?php do_lazy_action('lazy_simple_before_add_to_cart_form', $post); ?>
                    <form action="{{ route('shop.cart.add') }}" method="POST" class="mb-10 border-b border-gray-100 pb-10" id="lazy-add-to-cart-form">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $post->id }}">

                        <?php do_lazy_action('lazy_simple_add_to_cart_form_top', $post); ?>

                        <div class="flex items-center gap-4">
                            <div class="flex items-center border border-gray-300 rounded h-12 w-32">
                                <button type="button" class="w-10 h-full text-gray-600 hover:bg-gray-100 transition" onclick="const q=document.getElementById('qty'); if(q.value>1) q.value--">-</button>
                                <input type="number" id="qty" name="quantity" value="1" min="1" class="w-12 h-full text-center border-none focus:ring-0 appearance-none font-semibold text-body">
                                <button type="button" class="w-10 h-full text-gray-600 hover:bg-gray-100 transition" onclick="document.getElementById('qty').value++">+</button>
                            </div>

                            <?php do_lazy_action('lazy_simple_before_add_to_cart_button', $post); ?>
                            <?php
                                $btnHtml = '<button type="submit" class="flex-grow h-12 bg-primary text-white font-bold rounded hover:bg-primary-hover transition-colors duration-300">Add to Cart</button>';
                                $btnHtml = apply_lazy_filters('lazy_simple_add_to_cart_button', $btnHtml, $post);
                                echo $btnHtml;
                            ?>
                            <?php do_lazy_action('lazy_simple_after_add_to_cart_button', $post); ?>
                        </div>

                        <?php do_lazy_action('lazy_simple_add_to_cart_form_bottom', $post); ?>
                    </form>
                    <?php do_lazy_action('lazy_simple_after_add_to_cart_form', $post); ?>
                @endif

                <!-- Product Meta (SKU, Categories) -->
                <?php do_lazy_action('lazy_simple_before_product_meta', $post); ?>
                <div class="text-sm text-gray-500 space-y-2">
                    @if($post->sku)
                    <p><span class="font-bold text-gray-800">SKU:</span> {{ $post->sku }}</p>
                    @endif
                    @if($post->productCategories && $post->productCategories->count())
                    <p><span class="font-bold text-gray-800">Categories:</span>
                        @foreach($post->productCategories as $cat)
                            <a href="{{ url('product-category/' . $cat->getFullSlugPath()) }}" class="hover:text-primary transition">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    </p>
                    @endif
                    <?php do_lazy_action('lazy_simple_product_meta_fields', $post); ?>
                </div>
                <?php do_lazy_action('lazy_simple_after_product_meta', $post); ?>

            </div>
        </div>

        <!-- Description -->
        <?php do_lazy_action('lazy_before_product_description', $post); ?>
        @if($post->content)
        <div class="mt-20 border-t border-gray-100 pt-12">
            <?php
                $descTitleHtml = '<h3 class="text-2xl font-bold text-heading mb-8 inline-block border-b-2 border-primary pb-2">Description</h3>';
                $descTitleHtml = apply_lazy_filters('lazy_product_description_title', $descTitleHtml, $post);
                echo $descTitleHtml;
            ?>
            <?php
                $descHtml = '<div class="prose max-w-none text-body">' . $post->content . '</div>';
                $descHtml = apply_lazy_filters('lazy_product_description', $descHtml, $post);
                echo $descHtml;
            ?>
        </div>
        @endif
        <?php do_lazy_action('lazy_after_product_description', $post); ?>

    </div>
</div>
<?php do_lazy_action('lazy_after_single_product', $post); ?>
@stop
