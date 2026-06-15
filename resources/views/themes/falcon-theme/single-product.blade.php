@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', $post->title)

@section('content')
<?php do_falcon_action('falcon_before_single_product', $post); ?>
<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        <!-- Breadcrumbs -->
        <nav class="text-[14px] text-gray-400 mb-8" aria-label="Breadcrumb">
            <ol class="list-none p-0 inline-flex flex-wrap items-center">
                <li class="flex items-center">
                    <a href="{{ url('/') }}" class="hover:text-heading">Home</a>
                </li>
                @php
                    $primaryCat = $post->productCategories->first();
                    $cat = $primaryCat; // For use in other sections
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
                        <a href="{{ $crumb['url'] }}" class="hover:text-heading">{{ $crumb['name'] }}</a>
                    </li>
                @endforeach
                <li class="flex items-center">
                    <span class="mx-2">/</span>
                    <span class="text-heading font-medium">{{ $post->title }}</span>
                </li>
            </ol>
        </nav>

        <div class="flex flex-col lg:flex-row gap-12 mb-20">
            <?php do_falcon_action('falcon_before_product_images', $post); ?>
            <div class="w-full lg:w-1/2">
                <div class="relative bg-[#f8f8f8] rounded-sm overflow-hidden mb-4 group cursor-zoom-in">
                    @if($post->featured_image)
                        <img id="main-product-image" src="{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-auto object-cover transition-all duration-500 hover:scale-125">
                    @else
                        <img id="main-product-image" src="{{ asset('assets/images/placeholder.jpg') }}" alt="Placeholder" class="w-full h-auto object-cover mix-blend-multiply opacity-70">
                    @endif
                    
                    @if(!$post->is_in_stock)
                        <span class="absolute top-4 right-4 bg-red-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-sm uppercase tracking-wider shadow-lg z-10">Out of Stock</span>
                    @endif
                    @if($post->shopData && $post->shopData->sale_price)
                        <span class="absolute top-4 left-4 bg-sky-100 text-sky-700 text-[13px] font-bold px-3.5 py-1.5 rounded-full shadow uppercase tracking-wide z-10">Sale!</span>
                    @endif
                </div>
                
                @if($post->gallery && count($post->gallery) > 0)
                <div class="grid grid-cols-4 gap-4">
                    <div class="aspect-square cursor-pointer border border-transparent hover:border-primary rounded-sm overflow-hidden bg-[#f8f8f8]" onclick="changeProductImage('{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}')">
                        <img src="{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}" class="w-full h-full object-cover">
                    </div>
                    @foreach($post->gallery as $img)
                    <div class="aspect-square cursor-pointer border border-transparent hover:border-primary rounded-sm overflow-hidden bg-[#f8f8f8]" onclick="changeProductImage('{{ str_starts_with($img, 'http') ? $img : asset('storage/'.$img) }}')">
                        <img src="{{ str_starts_with($img, 'http') ? $img : asset('storage/'.$img) }}" class="w-full h-full object-cover">
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <?php do_falcon_action('falcon_after_product_images', $post); ?>

            <!-- Right: Product Info -->
            <div class="w-full lg:w-1/2 flex flex-col">

                <?php do_falcon_action('lazy_simple_before_product_title', $post); ?>
                {!! apply_falcon_filters('lazy_simple_product_title', '<h1 class="text-[36px] font-bold text-heading mb-4 leading-tight">' . e($post->title) . '</h1>', $post) !!}
                <?php do_falcon_action('lazy_simple_after_product_title', $post); ?>
                
                <?php do_falcon_action('lazy_simple_before_product_price', $post); ?>
                <?php
                    ob_start();
                    if ($post->shopData && $post->shopData->sale_price):
                ?>
                <div class="text-[24px] font-medium text-heading mb-6 flex items-center gap-3">
                    <span class="line-through text-gray-300 font-normal">{{ falcon_price_format($post->shopData->price) }}</span>
                    <span class="text-heading font-bold">{{ falcon_price_format($post->shopData->sale_price) }}</span>
                </div>
                <?php else: ?>
                <div class="text-[24px] font-medium text-heading mb-6 flex items-center gap-3">
                    <span class="text-heading font-bold">{{ falcon_price_format($post->shopData->price ?? 0) }}</span>
                </div>
                <?php endif;
                    $priceHtml = ob_get_clean();
                    echo apply_falcon_filters('lazy_simple_product_price', $priceHtml, $post);
                ?>
                <?php do_falcon_action('lazy_simple_after_product_price', $post); ?>

                @php
                    $stkGlobal = get_shop_option('shop_manage_stock', '1') === '1';
                    $stkFmt    = get_shop_option('shop_stock_display_format', 'always'); // always | low | never
                    $stkLow    = (int) get_shop_option('shop_low_stock_threshold', '2');
                    $stkOut    = (int) get_shop_option('shop_out_of_stock_threshold', '0');
                @endphp
                @if($stkGlobal && $post->shopData && $post->shopData->manage_stock && $stkFmt !== 'never')
                    @php $stkQty = (int) $post->shopData->stock_quantity; @endphp
                    <div class="mb-6 -mt-4">
                    @if($stkQty <= $stkOut)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Out of Stock
                        </span>
                    @elseif($stkQty <= $stkLow)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            {{ $stkQty }} {{ $stkQty == 1 ? 'item' : 'items' }} in stock
                        </span>
                    @elseif($stkFmt === 'always')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            {{ $stkQty }} items in stock
                        </span>
                    @endif
                    </div>
                @endif
                
                <?php do_falcon_action('lazy_simple_before_short_description', $post); ?>
                @if(!empty($post->shopData->short_description))
                {!! apply_falcon_filters('lazy_simple_short_description', '<div class="text-[15px] text-gray-600 mb-8 leading-relaxed">' . $post->shopData->short_description . '</div>', $post) !!}
                @endif
                <?php do_falcon_action('lazy_simple_after_short_description', $post); ?>

                <?php do_falcon_action('lazy_simple_before_add_to_cart_form', $post); ?>
                @if(!$post->is_in_stock)
                <div class="mb-10 pb-8 border-b border-gray-100">
                    @if(has_lazy_action('falcon_simple_out_of_stock_button'))
                        <?php do_falcon_action('falcon_simple_out_of_stock_button', $post); ?>
                    @else
                    <button disabled class="w-full md:w-auto bg-gray-400 text-white px-8 h-11 rounded-sm font-bold text-[14px] cursor-not-allowed uppercase">
                        Out of stock
                    </button>
                    @endif
                </div>
                @else
                <form id="add-to-cart-form" action="{{ route('shop.cart.add') }}" method="POST" class="mb-10 pb-8 border-b border-gray-100">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $post->id }}">
                    <?php do_falcon_action('lazy_simple_add_to_cart_form_top', $post); ?>
                    <?php falcon_render_product_fields(apply_falcon_filters('lazy_simple_product_fields', apply_falcon_filters('falcon_product_fields', [], $post), $post)); ?>

                    <div class="flex items-center gap-4 mt-4">
                        <div class="flex items-center border border-gray-200 rounded-sm h-11 w-20">
                            <input type="number" id="qty" name="quantity" value="1" min="1" class="w-full h-full text-center border-none focus:ring-0 text-[15px] font-medium">
                        </div>
                        <?php do_falcon_action('lazy_simple_before_add_to_cart_button', $post); ?>
                        {!! apply_falcon_filters('lazy_simple_add_to_cart_button', '<button type="submit" id="add-to-cart-btn" class="bg-primary text-white px-8 h-11 rounded-sm font-bold text-[14px] hover:bg-primary-hover transition-colors uppercase flex items-center gap-2"><span>Add to cart</span></button>', $post) !!}
                        <?php do_falcon_action('lazy_simple_after_add_to_cart_button', $post); ?>
                        @include('falcon-cms::themes.falcon-theme.partials.wishlist-button', ['product' => $post])
                    </div>
                    <?php do_falcon_action('lazy_simple_add_to_cart_form_bottom', $post); ?>
                </form>
                @endif
                <?php do_falcon_action('lazy_simple_after_add_to_cart_form', $post); ?>

                <div class="flex items-center gap-2 mb-8 -mt-4">
                    @unless($post->is_in_stock ?? true)
                        @include('falcon-cms::themes.falcon-theme.partials.wishlist-button', ['product' => $post])
                    @endunless
                    <span class="text-[13px] text-gray-500">{{ lazy_in_wishlist($post->id) ? 'Saved to your wishlist' : 'Add to your wishlist to buy later' }}</span>
                </div>

                <?php do_falcon_action('lazy_simple_before_product_meta', $post); ?>
                <div class="text-[13px] text-gray-500 space-y-2">
                    @if($post->shopData && $post->shopData->sku)
                    <p><span class="uppercase font-bold text-gray-800">SKU:</span> {{ $post->shopData->sku }}</p>
                    @endif
                    <p><span class="uppercase font-bold text-gray-800">Category:</span>
                        @php
                            $categories = $post->productCategories;
                        @endphp
                        @foreach($categories as $cat)
                            <a href="{{ url('product-category/' . $cat->getFullSlugPath()) }}" class="text-primary hover:underline">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                        @endforeach
                        @if($categories->isEmpty())
                            <span class="text-gray-400">Uncategorized</span>
                        @endif
                    </p>
                    <?php do_falcon_action('lazy_simple_product_meta_fields', $post); ?>
                </div>
                <?php do_falcon_action('lazy_simple_after_product_meta', $post); ?>
            </div>
        </div>

        <!-- Tabs Section -->
        <?php do_falcon_action('falcon_before_product_description', $post); ?>
        @php
            $reviewsOn = get_shop_option('shop_enable_reviews', '1') === '1';
            $ratingOn  = get_shop_option('shop_enable_review_rating', '1') === '1';
        @endphp
        <div class="mt-16 border-t border-gray-100 pt-10">
            <div class="flex gap-8 mb-8 border-b border-gray-100 tab-headers">
                {!! apply_falcon_filters('falcon_product_description_title', '<button onclick="switchTab(\'description\')" id="tab-btn-description" class="pb-4 text-[14px] font-bold text-heading border-b-2 border-gray-900 uppercase transition-all">Description</button>', $post) !!}
                <button onclick="switchTab('info')" id="tab-btn-info" class="pb-4 text-[14px] font-bold text-gray-400 hover:text-heading uppercase border-b-2 border-transparent transition-all">Additional information</button>
                @if($reviewsOn)
                <button onclick="switchTab('reviews')" id="tab-btn-reviews" class="pb-4 text-[14px] font-bold text-gray-400 hover:text-heading uppercase border-b-2 border-transparent transition-all">Reviews ({{ $post->reviews()->count() }})</button>
                @endif
            </div>
            
            <div id="tab-content-description" class="tab-pane prose max-w-none text-gray-600 text-[15px] leading-relaxed">
                {!! apply_falcon_filters('falcon_product_description', $post->content, $post) !!}
            </div>

            <div id="tab-content-info" class="tab-pane hidden">
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
            <div id="tab-content-reviews" class="tab-pane hidden">
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-sm mb-6 text-[14px]">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- Left: Reviews List -->
                    <div class="space-y-8">
                        <h3 class="text-[18px] font-bold text-heading mb-6 flex items-center gap-3">
                            Reviews ({{ $post->reviews->count() }})
                            @if($ratingOn && $post->reviews->count() > 0)
                                @php $avgRating = round($post->reviews->avg('rating'), 1); @endphp
                                <div class="flex items-center gap-1 border-l border-gray-200 pl-3">
                                    <div class="flex items-center gap-0.5">
                                        @for($i=1; $i<=5; $i++)
                                            <svg class="w-3.5 h-3.5 {{ $i <= $avgRating ? 'text-yellow-400' : 'text-gray-200' }} fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        @endfor
                                    </div>
                                    <span class="text-[14px] font-bold text-heading">{{ $avgRating }}</span>
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
                                                <span class="font-bold text-heading">{{ $review->name }}</span>
                                                <span class="text-gray-400 text-xs">{{ $review->created_at->format('M d, Y') }}</span>
                                            </div>
                                            <button onclick="setReplyTo({{ $review->id }}, '{{ $review->name }}')" class="text-[12px] text-primary font-bold hover:underline">Reply</button>
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
                                                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center font-bold text-primary shrink-0 text-sm">
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
                            <p class="text-gray-500">There are no reviews yet. Be the first to review "{{ $post->title }}"</p>
                        @endforelse
                    </div>

                    <!-- Right: Review Form -->
                    <div class="bg-[#fcfcfc] p-8 rounded-sm border border-gray-100 h-fit sticky top-24">
                        <div id="reply-to-alert" class="hidden bg-blue-50 text-primary px-4 py-2 rounded-sm mb-4 text-[13px] flex justify-between items-center">
                            <span>Replying to <span id="reply-to-name" class="font-bold"></span></span>
                            <button onclick="cancelReply()" class="text-primary hover:text-primary font-bold">×</button>
                        </div>

                        <h3 id="form-title" class="text-[18px] font-bold text-heading mb-2">Add a review</h3>
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

                            <button type="submit" id="review-submit-btn" class="bg-primary text-white px-8 py-3 rounded-sm font-bold text-[13px] hover:bg-primary-hover transition-colors uppercase mt-4">
                                Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <script>
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

            function changeProductImage(src) {
                const mainImg = document.getElementById('main-product-image');
                mainImg.style.opacity = '0';
                setTimeout(() => {
                    mainImg.src = src;
                    mainImg.style.opacity = '1';
                }, 150);
            }

            function switchTab(tab) {
                // Hide all panes
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
                // Show active pane
                document.getElementById('tab-content-' + tab).classList.remove('hidden');
                
                // Update button styles
                document.querySelectorAll('.tab-headers button').forEach(b => {
                    b.classList.remove('text-heading', 'border-gray-900');
                    b.classList.add('text-gray-400', 'border-transparent');
                });
                
                const activeBtn = document.getElementById('tab-btn-' + tab);
                activeBtn.classList.remove('text-gray-400', 'border-transparent');
                activeBtn.classList.add('text-heading', 'border-gray-900');
            }

            // Related-product cards use the global addToCart() from the mini-cart drawer.

            // AJAX Add to Cart (main product)
            document.getElementById('add-to-cart-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = document.getElementById('add-to-cart-btn');
                const btnText = btn.querySelector('span');
                const originalText = btnText.innerText;

                // Loading state
                btn.disabled = true;
                btnText.innerText = 'Adding...';

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
                    btnText.innerText = originalText;

                    if (data.success) {
                        // Refresh and slide out the mini-cart drawer
                        if (window.LazyCart) {
                            LazyCart.refresh().then(() => LazyCart.open());
                        } else {
                            const badge = document.querySelector('.cart-count-badge');
                            if (badge) { badge.innerText = data.cart_count; badge.classList.remove('hidden'); }
                        }
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message || 'Something went wrong!',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btnText.innerText = originalText;
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to add product to cart.',
                        icon: 'error'
                    });
                });
            });

            // Auto-open reviews tab if there is a success message
            @if(session('success'))
                window.onload = function() {
                    switchTab('reviews');
                    document.getElementById('tab-content-reviews').scrollIntoView({ behavior: 'smooth' });
                };
            @endif

            function setReplyTo(id, name) {
                document.getElementById('parent_id').value = id;
                document.getElementById('reply-to-name').innerText = name;
                document.getElementById('reply-to-alert').classList.remove('hidden');
                document.getElementById('form-title').innerText = 'Reply to ' + name;
                
                // Hide star rating for replies as rating is only for main review
                document.getElementById('rating-container').style.display = 'none';
                document.getElementById('rating-value').value = '5'; // Default for replies
                
                document.getElementById('form-title').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function cancelReply() {
                document.getElementById('parent_id').value = '';
                document.getElementById('reply-to-alert').classList.add('hidden');
                document.getElementById('form-title').innerText = 'Add a review';
                
                // Show star rating back
                document.getElementById('rating-container').style.display = 'block';
                document.getElementById('rating-value').value = '5';
            }

            // AJAX Review Submission
            document.getElementById('review-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = document.getElementById('review-submit-btn');
                const originalText = btn.innerText;

                // Loading state
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
                            // Clear form or reload to show review if approved
                            if (data.message.includes('posted successfully')) {
                                location.reload(); // Reload to show new approved review
                            } else {
                                form.reset();
                                cancelReply();
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message || 'Something went wrong!',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerText = originalText;
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to submit review.',
                        icon: 'error'
                    });
                });
            });
        </script>

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
<?php do_falcon_action('falcon_after_product_description', $post); ?>
<?php do_falcon_action('falcon_after_single_product', $post); ?>
@stop