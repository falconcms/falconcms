@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', $post->title)

@section('content')
@php
    $featuredImage = $post->featured_image ? (str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image)) : asset('assets/images/placeholder.jpg');
    $gallery = $post->gallery ?: [];
    
    // Process variations and attributes
    $variations = collect($post->shopData->variations ?? [])->filter(fn($v) => $v['enabled'] ?? true);
    $allVariationAttrs = [];
    foreach($variations as $v) {
        foreach($v['attributes_data'] ?? [] as $k => $val) {
            if (!empty($val)) {
                $allVariationAttrs[$k][] = trim($val);
            }
        }
    }
    
    // Filter attributes to only show those that have at least one active variation value
    $attributes = collect($post->shopData->attributes_data ?? [])
        ->filter(fn($a) => ($a['variation'] ?? false) && isset($allVariationAttrs[$a['name']]));
@endphp

<?php do_falcon_action('lazy_before_single_product', $post); ?>
<?php do_falcon_action('lazy_variable_before_single_product', $post); ?>
<div class="bg-white py-12 min-h-screen font-sans" x-data="variableProductHandler()">
    <div class="container-custom">
        <!-- Breadcrumbs -->
        <nav class="text-[14px] text-gray-400 mb-8" aria-label="Breadcrumb">
            <ol class="list-none p-0 inline-flex flex-wrap items-center">
                <li class="flex items-center">
                    <a href="{{ url('/') }}" class="hover:text-gray-900">Home</a>
                </li>
                @php
                    $primaryCat = $post->productCategories->first();
                    $breadcrumb = [];
                    if ($primaryCat) {
                        $term = $primaryCat;
                        while ($term) {
                            $breadcrumb[] = [
                                'name' => $term->name,
                                'url' => url('product-category/' . $term->getFullSlugPath())
                            ];
                            $term = $term->parent;
                        }
                        $breadcrumb = array_reverse($breadcrumb);
                    }
                @endphp
                @foreach($breadcrumb as $crumb)
                    <li class="flex items-center">
                        <span class="mx-2">/</span>
                        <a href="{{ $crumb['url'] }}" class="hover:text-gray-900">{{ $crumb['name'] }}</a>
                    </li>
                @endforeach
                <li class="flex items-center">
                    <span class="mx-2">/</span>
                    <span class="text-gray-900 font-medium">{{ $post->title }}</span>
                </li>
            </ol>
        </nav>

        <div class="flex flex-col lg:flex-row gap-12 mb-20">
            <!-- Left: Product Images -->
            <?php do_falcon_action('lazy_before_product_images', $post); ?>
            <div class="w-full lg:w-1/2">
                <div class="relative bg-[#f8f8f8] rounded-sm overflow-hidden mb-4 group cursor-zoom-in">
                    <img id="main-product-image" :src="currentImage" :alt="'{{ $post->title }}'" class="w-full h-auto object-cover transition-all duration-500 hover:scale-125">
                    
                    <template x-if="selectedVariation && selectedVariation.stock_status === 'outofstock'">
                        <span class="absolute top-4 left-4 bg-red-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-sm uppercase tracking-wider shadow-lg z-10">Out of Stock</span>
                    </template>

                    <template x-if="onSale && (!selectedVariation || selectedVariation.stock_status !== 'outofstock')">
                        <span class="absolute top-4 left-4 bg-sky-100 text-sky-700 text-[13px] font-bold px-3.5 py-1.5 rounded-full shadow uppercase tracking-wide z-10">Sale!</span>
                    </template>
                </div>
                
                @if(count($gallery) > 0 || $post->featured_image)
                <div class="grid grid-cols-4 gap-4">
                    <div class="aspect-square cursor-pointer border-2 rounded-sm overflow-hidden bg-[#f8f8f8]" 
                         :class="isDefaultImage() ? 'border-blue-500' : 'border-transparent'"
                         @click="setBaseImage('{{ $featuredImage }}')">
                        <img src="{{ $featuredImage }}" class="w-full h-full object-cover">
                    </div>
                    @foreach($gallery as $img)
                    @php $imgUrl = str_starts_with($img, 'http') ? $img : asset('storage/'.$img); @endphp
                    <div class="aspect-square cursor-pointer border-2 rounded-sm overflow-hidden bg-[#f8f8f8]" 
                         :class="currentImage === '{{ $imgUrl }}' ? 'border-blue-500' : 'border-transparent'"
                         @click="setBaseImage('{{ $imgUrl }}')">
                        <img src="{{ $imgUrl }}" class="w-full h-full object-cover">
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <?php do_falcon_action('lazy_after_product_images', $post); ?>

            <!-- Right: Product Info -->
            <div class="w-full lg:w-1/2 flex flex-col">
                <?php do_falcon_action('lazy_variable_before_product_title', $post); ?>
                {!! apply_falcon_filters('lazy_variable_product_title', '<h1 class="text-[36px] font-bold text-[#2c3338] mb-4 leading-tight">' . e($post->title) . '</h1>', $post) !!}
                <?php do_falcon_action('lazy_variable_after_product_title', $post); ?>
                
                <?php do_falcon_action('lazy_variable_before_product_price', $post); ?>
                <div class="text-[28px] font-medium text-gray-900 mb-6 flex items-center gap-3">
                    <template x-if="selectedVariation && selectedVariation.price">
                        <div class="flex items-center gap-3">
                            <template x-if="selectedVariation.sale_price">
                                <div class="flex items-center gap-3">
                                    <span class="line-through text-gray-300 font-normal" x-text="formatPrice(selectedVariation.price)"></span>
                                    <span class="text-gray-900 font-bold" x-text="formatPrice(selectedVariation.sale_price)"></span>
                                </div>
                            </template>
                            <template x-if="!selectedVariation.sale_price">
                                <span class="text-gray-900 font-bold" x-text="formatPrice(selectedVariation.price)"></span>
                            </template>
                        </div>
                    </template>
                    <template x-if="!selectedVariation">
                        <span class="text-gray-900 font-bold">{{ $post->shopData->price_range ?? falcon_price_format($post->shopData->price ?? 0) }}</span>
                    </template>
                </div>
                <?php do_falcon_action('lazy_variable_after_product_price', $post); ?>

                <!-- Dynamic Stock Status for Variations -->
                <div class="mb-6 -mt-4" x-show="selectedVariation" x-cloak>
                    <!-- If Stock Management is ON -->
                    <template x-if="selectedVariation && selectedVariation.manage_stock">
                        <div>
                            <template x-if="parseInt(selectedVariation.stock_quantity) <= 0">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-600 border border-red-100">
                                    Out of Stock
                                </span>
                            </template>
                            <template x-if="parseInt(selectedVariation.stock_quantity) > 0 && parseInt(selectedVariation.stock_quantity) <= 5">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-100">
                                    Only <span x-text="selectedVariation.stock_quantity" class="mx-1"></span> left in stock!
                                </span>
                            </template>
                            <template x-if="parseInt(selectedVariation.stock_quantity) > 5">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wider">
                                    In Stock
                                </span>
                            </template>
                        </div>
                    </template>
                    
                    <!-- If Stock Management is OFF -->
                    <template x-if="selectedVariation && !selectedVariation.manage_stock">
                        <div>
                            <template x-if="selectedVariation.stock_status === 'instock'">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wider">
                                    In Stock
                                </span>
                            </template>
                            <template x-if="selectedVariation.stock_status === 'outofstock'">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-600 border border-red-100">
                                    Out of Stock
                                </span>
                            </template>
                        </div>
                    </template>
                </div>

                <?php do_falcon_action('lazy_variable_before_short_description', $post); ?>
                @if(!empty($post->shopData->short_description))
                {!! apply_falcon_filters('lazy_variable_short_description', '<div class="text-[15px] text-gray-600 mb-8 leading-relaxed">' . $post->shopData->short_description . '</div>', $post) !!}
                @endif
                <?php do_falcon_action('lazy_variable_after_short_description', $post); ?>

                <!-- Variations Selection -->
                <div class="space-y-8 mb-10 p-6 bg-[#fcfcfc] border border-gray-100 rounded-sm shadow-sm">
                    @foreach($attributes as $attr)
                    @php 
                        $attrName = $attr['name'];
                        $values = array_unique($allVariationAttrs[$attrName] ?? []);
                        $isColor = str_contains(strtolower($attrName), 'color');
                    @endphp
                    <div class="flex flex-col gap-3">
                        <label class="text-[12px] font-bold text-gray-800 uppercase tracking-wider flex justify-between">
                            <span>{{ $attrName }}</span>
                            <span class="text-blue-600 normal-case font-medium" x-text="selections['{{ $attrName }}'] || ''"></span>
                        </label>
                        
                        @if($isColor)
                        <div class="flex flex-wrap gap-3">
                            @foreach($values as $val)
                            @php 
                                $isValidColor = preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $val) || in_array(strtolower($val), ['red', 'blue', 'green', 'yellow', 'black', 'white', 'gray', 'purple', 'orange', 'pink', 'brown', 'cyan', 'magenta', 'lime', 'maroon', 'navy', 'olive', 'teal', 'silver', 'gold']);
                            @endphp
                            <button @click="selections['{{ $attrName }}'] = '{{ $val }}'" 
                                    :class="selections['{{ $attrName }}'] === '{{ $val }}' ? 'border-blue-600 ring-2 ring-blue-100 scale-110' : 'border-gray-200'"
                                    class="w-10 h-10 rounded-full border-2 p-0.5 transition-all focus:outline-none overflow-hidden group relative flex items-center justify-center bg-white"
                                    title="{{ $val }}">
                                @if($isValidColor)
                                    <span class="block w-full h-full rounded-full shadow-inner" style="background-color: {{ $val }};"></span>
                                @else
                                    <span class="text-[10px] leading-none text-center font-bold text-gray-500 uppercase">{{ substr($val, 0, 3) }}</span>
                                @endif
                                <span class="sr-only">{{ $val }}</span>
                            </button>
                            @endforeach
                        </div>
                        @else
                        <div class="flex flex-wrap gap-2">
                            @foreach($values as $val)
                            <button @click="selections['{{ $attrName }}'] = '{{ $val }}'" 
                                    :class="selections['{{ $attrName }}'] === '{{ $val }}' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-400'"
                                    class="px-4 py-2 text-[13px] font-medium border rounded-sm transition-all focus:outline-none">
                                {{ $val }}
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                    
                    <div x-show="selectedVariation" x-transition class="pt-4 mt-4 border-t border-gray-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-[12px] font-bold text-gray-800 uppercase">Availability:</span>
                            <span :class="selectedVariation.stock_status === 'instock' ? 'text-emerald-600' : 'text-red-600'" class="text-[14px] font-medium" x-text="selectedVariation.stock_status === 'instock' ? 'In Stock' : 'Out of Stock'"></span>
                        </div>
                        <div x-show="selectedVariation.manage_stock && selectedVariation.stock_quantity > 0" class="text-gray-500 text-[12px] italic">
                            Only <span x-text="selectedVariation.stock_quantity"></span> left!
                        </div>
                    </div>
                    
                    <button x-show="Object.keys(selections).length > 0" @click="clearSelections()" class="text-[11px] text-gray-400 hover:text-red-500 uppercase font-bold tracking-tighter transition-colors">
                        Reset Selection
                    </button>
                </div>

                <!-- Simple & Reliable Interaction Area -->
                <?php do_falcon_action('lazy_variable_before_add_to_cart_form', $post); ?>
                <div class="space-y-6 pt-6 border-t border-gray-100 mb-10 pb-8 border-b border-gray-100">
                    <form id="add-to-cart-form" action="{{ route('shop.cart.add') }}" method="POST" class="w-full">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $post->id }}">
                        <input type="hidden" name="variation_id" :value="selectedVariation ? selectedVariation.id : ''">
                        <?php do_falcon_action('lazy_variable_add_to_cart_form_top', $post); ?>
                        <?php lazy_render_product_fields(apply_falcon_filters('lazy_variable_product_fields', apply_falcon_filters('lazy_product_fields', [], $post), $post)); ?>

                        <div class="flex items-center gap-3 w-full mt-4">
                            <!-- Standard Quantity Box -->
                            <div class="flex items-center border border-gray-200 rounded-sm h-12 bg-white overflow-hidden">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-10 h-full flex items-center justify-center text-gray-500 hover:bg-gray-50 border-r border-gray-100 font-bold text-lg select-none">-</button>
                                <input type="text" name="quantity" x-model="qty" readonly class="w-12 h-full text-center border-none focus:ring-0 text-[15px] font-bold text-gray-800 p-0 cursor-default">
                                <button type="button" @click="qty = parseInt(qty) + 1" class="w-10 h-full flex items-center justify-center text-gray-500 hover:bg-gray-50 border-l border-gray-100 font-bold text-lg select-none">+</button>
                            </div>

                            <?php do_falcon_action('lazy_variable_before_add_to_cart_button', $post); ?>
                            {!! apply_falcon_filters('lazy_variable_add_to_cart_button',
                                '<button type="submit" id="add-to-cart-btn" x-show="selectedVariation" :disabled="selectedVariation && selectedVariation.stock_status !== \'instock\'" :class="(selectedVariation && selectedVariation.stock_status !== \'instock\') ? \'bg-gray-100 text-gray-400 cursor-not-allowed\' : \'bg-[#1363df] hover:bg-[#005ba6] text-white hover:text-white\'" class="flex-1 h-12 rounded-sm font-bold text-[14px] transition-all uppercase flex items-center justify-center px-8"><span x-text="selectedVariation && selectedVariation.stock_status === \'instock\' ? \'Add to cart\' : \'Out of Stock\'">Add to cart</span></button>',
                                $post) !!}
                            <?php do_falcon_action('lazy_variable_after_add_to_cart_button', $post); ?>

                            <!-- Placeholder when nothing selected -->
                            <div x-show="!selectedVariation" class="flex-1 h-12 rounded-sm bg-gray-50 border border-dashed border-gray-200 text-gray-400 font-medium text-[13px] flex items-center justify-center px-8">
                                Please select options above
                            </div>
                        </div>
                        <?php do_falcon_action('lazy_variable_add_to_cart_form_bottom', $post); ?>
                    </form>
                </div>
                <?php do_falcon_action('lazy_variable_after_add_to_cart_form', $post); ?>

                <?php do_falcon_action('lazy_variable_before_product_meta', $post); ?>
                <div class="text-[13px] text-gray-500 space-y-3 mt-6">
                    <div x-show="selectedVariation && selectedVariation.sku"><span class="uppercase font-bold text-gray-800">SKU:</span> <span x-text="selectedVariation.sku" class="ml-2 bg-gray-100 px-2 py-0.5 rounded text-gray-600"></span></div>
                    <div x-show="!selectedVariation && '{{ $post->shopData->sku }}'"><span class="uppercase font-bold text-gray-800">SKU:</span> <span class="ml-2">{{ $post->shopData->sku }}</span></div>
                    <div><span class="uppercase font-bold text-gray-800">Category:</span>
                        <span class="ml-2">
                        @php
                            $categories = $post->productCategories;
                        @endphp
                        @foreach($categories as $cat)
                            <a href="{{ url('product-category/' . $cat->getFullSlugPath()) }}" class="text-blue-600 hover:underline">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                        @endforeach
                        </span>
                    </div>
                    <?php do_falcon_action('lazy_variable_product_meta_fields', $post); ?>
                </div>
                <?php do_falcon_action('lazy_variable_after_product_meta', $post); ?>
            </div>
        </div>

        <!-- Tabs Section -->
        <?php do_falcon_action('lazy_before_product_description', $post); ?>
        @php
            $reviewsOn = get_shop_option('shop_enable_reviews', '1') === '1';
            $ratingOn  = get_shop_option('shop_enable_review_rating', '1') === '1';
        @endphp
        <div class="mt-16 border-t border-gray-100 pt-10">
            <div class="flex gap-8 mb-8 border-b border-gray-100 tab-headers">
                <button @click="activeTab = 'description'" :class="activeTab === 'description' ? 'text-gray-900 border-gray-900' : 'text-gray-400 border-transparent'" class="pb-4 text-[14px] font-bold uppercase border-b-2 transition-all">Description</button>
                <button @click="activeTab = 'info'" :class="activeTab === 'info' ? 'text-gray-900 border-gray-900' : 'text-gray-400 border-transparent'" class="pb-4 text-[14px] font-bold uppercase border-b-2 transition-all">Additional information</button>
                @if($reviewsOn)
                <button @click="activeTab = 'reviews'" :class="activeTab === 'reviews' ? 'text-gray-900 border-gray-900' : 'text-gray-400 border-transparent'" class="pb-4 text-[14px] font-bold uppercase border-b-2 transition-all">Reviews ({{ $post->reviews()->count() }})</button>
                @endif
            </div>
            
            <div x-show="activeTab === 'description'" class="prose max-w-none text-gray-600 text-[15px] leading-relaxed">
                {!! apply_falcon_filters('lazy_variable_product_description', apply_falcon_filters('lazy_product_description', $post->content, $post), $post) !!}
            </div>

            <div x-show="activeTab === 'info'" x-cloak>
                <table class="w-full border-collapse">
                    <tbody>
                        @if($post->shopData && $post->shopData->weight)
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">Weight</th>
                            <td class="py-3 text-gray-600">{{ $post->shopData->weight }} {{ get_shop_option('shop_weight_unit', 'kg') }}</td>
                        </tr>
                        @endif
                        @if($post->shopData && $post->shopData->dimensions)
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">Dimensions</th>
                            <td class="py-3 text-gray-600">{{ $post->shopData->dimensions }} {{ get_shop_option('shop_dimensions_unit', 'cm') }}</td>
                        </tr>
                        @endif
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 w-1/4 text-gray-800 font-bold uppercase text-[12px]">Category</th>
                            <td class="py-3 text-gray-600">
                                @foreach($post->productCategories as $cat)
                                    {{ $cat->name }}{{ $loop->last ? '' : ', ' }}
                                @endforeach
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if($reviewsOn)
            <div x-show="activeTab === 'reviews'" x-cloak>
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-sm mb-6 text-[14px]">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- Left: Reviews List -->
                    <div class="space-y-8">
                        <h3 class="text-[18px] font-bold text-gray-900 mb-6 flex items-center gap-3">
                            Reviews ({{ $post->reviews->count() }})
                            @if($ratingOn && $post->reviews->count() > 0)
                                @php $avgRating = round($post->reviews->avg('rating'), 1); @endphp
                                <div class="flex items-center gap-1 border-l border-gray-200 pl-3">
                                    <div class="flex items-center gap-0.5">
                                        @for($i=1; $i<=5; $i++)
                                            <svg class="w-3.5 h-3.5 {{ $i <= $avgRating ? 'text-yellow-400' : 'text-gray-200' }} fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        @endfor
                                    </div>
                                    <span class="text-[14px] font-bold text-gray-900">{{ $avgRating }}</span>
                                </div>
                            @endif
                        </h3>
                        @forelse($post->reviews as $review)
                            <div class="pb-6 border-b border-gray-50 last:border-0">
                                <div class="flex gap-4 mb-4">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-400 shrink-0">
                                        {{ substr($review->name, 0, 1) }}
                                    </div>
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-gray-900">{{ $review->name }}</span>
                                                <span class="text-gray-400 text-xs">{{ $review->created_at->format('M d, Y') }}</span>
                                            </div>
                                            <button onclick="setReplyTo({{ $review->id }}, '{{ $review->name }}')" class="text-[12px] text-blue-600 font-bold hover:underline">Reply</button>
                                        </div>
                                        <div class="flex items-center gap-1 mb-2">
                                            @for($i=1; $i<=5; $i++)
                                                <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-200' }} fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            @endfor
                                        </div>
                                        <p class="text-[14px] text-gray-600 leading-relaxed">{{ $review->comment }}</p>
                                    </div>
                                </div>

                                <!-- Nested Replies -->
                                @if($review->replies->count() > 0)
                                    <div class="ml-16 mt-4 space-y-6 border-l-2 border-gray-50 pl-6">
                                        @foreach($review->replies as $reply)
                                            <div class="flex gap-4">
                                                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center font-bold text-blue-300 shrink-0 text-sm">
                                                    {{ substr($reply->name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-bold text-gray-800 text-[13px]">{{ $reply->name }}</span>
                                                        <span class="text-gray-400 text-[11px]">{{ $reply->created_at->format('M d, Y') }}</span>
                                                    </div>
                                                    <p class="text-[13px] text-gray-600 leading-relaxed">{{ $reply->comment }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500 text-[14px]">There are no reviews yet. Be the first to review "{{ $post->title }}"</p>
                        @endforelse
                    </div>

                    <!-- Right: Review Form -->
                    <div class="bg-[#fcfcfc] p-8 rounded-sm border border-gray-100 h-fit sticky top-24">
                        <div id="reply-to-alert" class="hidden bg-blue-50 text-blue-700 px-4 py-2 rounded-sm mb-4 text-[13px] flex justify-between items-center">
                            <span>Replying to <span id="reply-to-name" class="font-bold"></span></span>
                            <button onclick="cancelReply()" class="text-blue-400 hover:text-blue-700 font-bold">×</button>
                        </div>

                        <h3 id="form-title" class="text-[18px] font-bold text-gray-900 mb-2">Add a review</h3>
                        <p class="text-[13px] text-gray-500 mb-6">Your email address will not be published. Required fields are marked *</p>
                        
                        <form id="review-form" action="{{ route('shop.review.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="post_id" value="{{ $post->id }}">
                            <input type="hidden" name="parent_id" id="parent_id" value="">
                            
                            <div id="rating-container" @unless($ratingOn) style="display:none" @endunless>
                                <input type="hidden" name="rating" id="rating-value" value="{{ $ratingOn ? '5' : '' }}">
                                @if($ratingOn)
                                <label class="block text-[13px] font-bold text-gray-700 uppercase mb-2">Your rating *</label>
                                <div class="flex gap-1 text-gray-300 rating-stars">
                                    @for($i=1; $i<=5; $i++)
                                        <button type="button" onclick="setRating({{ $i }})" class="star-btn transition-colors {{ $i <= 5 ? 'text-yellow-400' : '' }}" data-value="{{ $i }}">
                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        </button>
                                    @endfor
                                </div>
                                @endif
                            </div>

                            <div>
                                <label class="block text-[13px] font-bold text-gray-700 uppercase mb-2">Your review *</label>
                                <textarea name="comment" rows="6" required class="w-full border border-gray-200 rounded-sm p-3 text-[14px] focus:ring-0 focus:border-gray-900 outline-none"></textarea>
                            </div>

                            @guest
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[13px] font-bold text-gray-700 uppercase mb-2">Name *</label>
                                    <input type="text" name="name" required class="w-full border border-gray-200 rounded-sm p-3 text-[14px] focus:ring-0 focus:border-gray-900 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[13px] font-bold text-gray-700 uppercase mb-2">Email *</label>
                                    <input type="email" name="email" required class="w-full border border-gray-200 rounded-sm p-3 text-[14px] focus:ring-0 focus:border-gray-900 outline-none">
                                </div>
                            </div>
                            @endguest

                            <button type="submit" id="review-submit-btn" class="bg-[#1363df] text-white px-8 py-3 rounded-sm font-bold text-[13px] hover:bg-[#005ba6] transition-colors uppercase mt-4">
                                Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Related Products Section -->
        @php
            $related = \FalconCms\Core\Models\Post::where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->where('posts.id', '!=', $post->id)
                ->with('shopData')
                ->latest('posts.id')
                ->limit(4)
                ->get();
        @endphp
        @if($related->count() > 0)
        <div class="mt-24">
            <h2 class="text-[32px] font-bold text-heading mb-10">Related products</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-12">
                @foreach($related as $item)
                    @include('falcon-cms::themes.falcon-theme.partials.product-card', ['product' => $item])
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>
<?php do_falcon_action('lazy_after_product_description', $post); ?>
<?php do_falcon_action('lazy_variable_after_single_product', $post); ?>
<?php do_falcon_action('lazy_after_single_product', $post); ?>

<style>
    [x-cloak] { display: none !important; }
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
</style>

<script>
    function variableProductHandler() {
        return {
            activeTab: 'description',
            selections: {},
            qty: 1,
            variations: @json($variations->values()),
            selectedVariation: null,
            currentImage: '{{ $featuredImage }}',
            defaultImage: '{{ $featuredImage }}',
            currencySymbol: '{{ \FalconCms\Core\Services\EcommerceData::getCurrencySymbol(get_shop_option("shop_currency", "USD")) }}',
            currencyPosition: '{{ get_shop_option("shop_currency_pos", "left") }}',
            priceDecimals: {{ (int)get_shop_option("shop_num_decimals", 2) }},
            thousandSeparator: '{{ get_shop_option("shop_thousand_sep", ",") }}',
            decimalSeparator: '{{ get_shop_option("shop_decimal_sep", ".") }}',
            onSale: false,

            init() {
                this.$watch('selections', (value) => {
                    this.matchVariation();
                }, { deep: true });
            },

            formatPrice(price) {
                if (!price) return '';
                let formatted = parseFloat(price).toFixed(this.priceDecimals);
                let parts = formatted.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);
                formatted = parts.join(this.decimalSeparator);

                switch (this.currencyPosition) {
                    case 'left': return this.currencySymbol + formatted;
                    case 'right': return formatted + this.currencySymbol;
                    case 'left_space': return this.currencySymbol + ' ' + formatted;
                    case 'right_space': return formatted + ' ' + this.currencySymbol;
                    default: return this.currencySymbol + formatted;
                }
            },

            isDefaultImage() {
                return this.currentImage === this.defaultImage;
            },

            setBaseImage(url) {
                this.defaultImage = url;
                if (!this.selectedVariation || !this.selectedVariation.image) {
                    this.currentImage = url;
                }
            },

            clearSelections() {
                this.selections = {};
                this.selectedVariation = null;
                this.currentImage = this.defaultImage;
                this.onSale = false;
            },

            matchVariation() {
                const requiredAttrs = @json($attributes->pluck('name'));
                
                const allSelected = requiredAttrs.every(attr => !!this.selections[attr]);
                
                if (!allSelected) {
                    this.selectedVariation = null;
                    this.currentImage = this.defaultImage;
                    this.onSale = false;
                    return;
                }

                const match = this.variations.find(v => {
                    return requiredAttrs.every(attr => {
                        return String(v.attributes_data[attr]).trim() === String(this.selections[attr]).trim();
                    });
                });

                if (match) {
                    this.selectedVariation = match;
                    this.onSale = !!match.sale_price;
                    if (match.image) {
                        this.currentImage = '/storage/' + match.image;
                    } else {
                        this.currentImage = this.defaultImage;
                    }
                } else {
                    this.selectedVariation = null;
                    this.currentImage = this.defaultImage;
                    this.onSale = false;
                }
            }
        }
    }

    // Review System Functions
    function setRating(n) {
        document.getElementById('rating-value').value = n;
        const stars = document.querySelectorAll('.rating-stars .star-btn');
        stars.forEach((s, index) => {
            if (index < n) {
                s.classList.add('text-yellow-400');
                s.classList.remove('text-gray-300');
            } else {
                s.classList.remove('text-yellow-400');
                s.classList.add('text-gray-300');
            }
        });
    }

    function setReplyTo(id, name) {
        document.getElementById('parent_id').value = id;
        document.getElementById('reply-to-name').innerText = name;
        document.getElementById('reply-to-alert').classList.remove('hidden');
        document.getElementById('form-title').innerText = 'Reply to ' + name;
        document.getElementById('rating-container').style.display = 'none';
        document.getElementById('rating-value').value = '5';
        document.getElementById('form-title').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function cancelReply() {
        document.getElementById('parent_id').value = '';
        document.getElementById('reply-to-alert').classList.add('hidden');
        document.getElementById('form-title').innerText = 'Add a review';
        document.getElementById('rating-container').style.display = 'block';
        document.getElementById('rating-value').value = '5';
    }

    // AJAX Add to Cart (variable product) -> open mini-cart drawer
    document.addEventListener('DOMContentLoaded', function() {
        const cartForm = document.getElementById('add-to-cart-form');
        if (cartForm) {
            cartForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = document.getElementById('add-to-cart-btn');
                const label = btn ? btn.querySelector('span') : null;
                const original = label ? label.innerText : '';
                if (btn) btn.disabled = true;
                if (label) label.innerText = 'Adding...';

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form)
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (btn) btn.disabled = false;
                    if (label) label.innerText = original;
                    if (ok && data.success) {
                        if (window.LazyCart) { LazyCart.refresh().then(() => LazyCart.open()); }
                    } else if (window.Swal) {
                        Swal.fire({ title: 'Error!', text: data.message || 'Could not add to cart.', icon: 'error' });
                    }
                })
                .catch(() => {
                    if (btn) btn.disabled = false;
                    if (label) label.innerText = original;
                    if (window.Swal) Swal.fire({ title: 'Error!', text: 'Could not add to cart.', icon: 'error' });
                });
            });
        }
    });

    // AJAX Review Submission
    document.addEventListener('DOMContentLoaded', function() {
        const reviewForm = document.getElementById('review-form');
        if (reviewForm) {
            reviewForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = document.getElementById('review-submit-btn');
                const originalText = btn.innerText;

                btn.disabled = true;
                btn.innerText = 'Submitting...';

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerText = originalText;

                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            if (data.message.includes('posted successfully')) {
                                location.reload();
                            } else {
                                form.reset();
                                cancelReply();
                            }
                        });
                    } else {
                        Swal.fire({ title: 'Error!', text: data.message || 'Something went wrong!', icon: 'error' });
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerText = originalText;
                    Swal.fire({ title: 'Error!', text: 'Failed to submit review.', icon: 'error' });
                });
            });
        }
    });
</script>
@stop
