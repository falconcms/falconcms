@extends('falcon-cms::themes.lazy-theme.layouts.app')

@section('title', $post->title)

@php
    $variations = $post->shopData?->variations ?? collect();
    $variationsJson = $variations->map(fn($v) => [
        'id'         => $v->id,
        'attributes' => $v->attributes_data ?? [],
        'price'      => $v->price,
        'sale_price' => $v->sale_price,
        'sku'        => $v->sku,
        'image'      => $v->image,
        'in_stock'   => ($v->stock_status ?? 'instock') === 'instock',
        'stock_qty'  => $v->manage_stock ? $v->stock_quantity : null,
    ])->values()->toJson();

    $attributeKeys = [];
    foreach ($variations as $v) {
        foreach (array_keys($v->attributes_data ?? []) as $key) {
            $attributeKeys[] = $key;
        }
    }
    $attributeKeys = array_unique($attributeKeys);

    $attributeOptions = [];
    foreach ($attributeKeys as $key) {
        $opts = [];
        foreach ($variations as $v) {
            $val = ($v->attributes_data ?? [])[$key] ?? null;
            if ($val && !in_array($val, $opts)) $opts[] = $val;
        }
        $attributeOptions[$key] = $opts;
    }
@endphp

@section('content')
<?php do_lazy_action('lazy_before_single_product', $post); ?>
<?php do_lazy_action('lazy_variable_before_single_product', $post); ?>
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
            <?php do_lazy_action('lazy_variable_before_product_images', $post); ?>
            <div class="w-full md:w-1/2">
                <div class="bg-gray-50 rounded-lg overflow-hidden border border-gray-100 shadow-sm relative pt-[100%]">
                    @if($post->thumbnail)
                        <img id="main-image" src="{{ url($post->thumbnail) }}" alt="{{ $post->title }}" class="absolute inset-0 w-full h-full object-cover">
                    @else
                        <div class="absolute inset-0 flex items-center justify-center text-gray-400">No Image</div>
                    @endif
                    @if($post->sale_price)
                        <span id="sale-badge" class="absolute top-4 left-4 bg-sky-100 text-sky-700 text-[14px] font-bold px-3.5 py-1.5 rounded-md uppercase tracking-wide z-10">Sale!</span>
                    @else
                        <span id="sale-badge" class="hidden absolute top-4 left-4 bg-sky-100 text-sky-700 text-[14px] font-bold px-3.5 py-1.5 rounded-md uppercase tracking-wide z-10">Sale!</span>
                    @endif
                    <span id="oos-badge" class="hidden absolute top-4 right-4 bg-red-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-sm uppercase tracking-wider shadow-lg z-10">Out of Stock</span>
                </div>
                @if($post->gallery && count($post->gallery) > 0)
                <div class="grid grid-cols-4 gap-4 mt-4" id="gallery-thumbs">
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
            <?php do_lazy_action('lazy_variable_after_product_images', $post); ?>

            <!-- Product Info -->
            <div class="w-full md:w-1/2 flex flex-col justify-center">

                <!-- Title -->
                <?php do_lazy_action('lazy_variable_before_product_title', $post); ?>
                <?php
                    $productTitleHtml = '<h1 class="text-3xl md:text-4xl font-bold text-heading mb-4">'
                        . e($post->title) . '</h1>';
                    $productTitleHtml = apply_lazy_filters('lazy_variable_product_title', $productTitleHtml, $post);
                    echo $productTitleHtml;
                ?>
                <?php do_lazy_action('lazy_variable_after_product_title', $post); ?>

                <!-- Price (dynamic) -->
                <?php do_lazy_action('lazy_variable_before_product_price', $post); ?>
                <div id="variation-price" class="text-2xl font-medium text-heading mb-6 border-b border-gray-100 pb-6">
                    @if($post->sale_price)
                        <span class="line-through text-gray-400 text-lg mr-2" id="regular-price-display">{{ lazy_price_format($post->price) }}</span>
                        <span class="text-primary" id="sale-price-display">{{ lazy_price_format($post->sale_price) }}</span>
                    @else
                        <span class="text-primary" id="main-price-display">{{ lazy_price_format($post->price ?? 0) }}</span>
                    @endif
                    <span id="price-range-display" class="text-primary hidden"></span>
                </div>
                <?php do_lazy_action('lazy_variable_after_product_price', $post); ?>

                <!-- Stock badge -->
                <div id="variation-stock-badge" class="mb-6 -mt-4 hidden">
                    <span id="variation-stock-text" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"></span>
                </div>

                <!-- Short Description -->
                @php $shortDescription = !empty($post->shopData->short_description) ? $post->shopData->short_description : $post->excerpt; @endphp
                <?php do_lazy_action('lazy_variable_before_short_description', $post); ?>
                @if($shortDescription)
                <?php
                    $shortDescHtml = '<div class="prose text-body mb-8">' . $shortDescription . '</div>';
                    $shortDescHtml = apply_lazy_filters('lazy_variable_short_description', $shortDescHtml, $post);
                    echo $shortDescHtml;
                ?>
                @endif
                <?php do_lazy_action('lazy_variable_after_short_description', $post); ?>

                <!-- Variation Selector + Add to Cart -->
                <?php do_lazy_action('lazy_variable_before_add_to_cart_form', $post); ?>
                <form action="{{ route('shop.cart.add') }}" method="POST" class="mb-10 border-b border-gray-100 pb-10" id="lazy-variable-form">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $post->id }}">
                    <input type="hidden" name="variation_id" id="selected-variation-id" value="">

                    <?php do_lazy_action('lazy_variable_add_to_cart_form_top', $post); ?>

                    <!-- Attribute selectors -->
                    @if(count($attributeKeys) > 0)
                    <div class="space-y-4 mb-6">
                        @foreach($attributeKeys as $attrKey)
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">{{ ucwords(str_replace('_', ' ', $attrKey)) }}: <span class="font-normal text-gray-500" id="selected-{{ Str::slug($attrKey) }}"></span></label>
                            <div class="flex flex-wrap gap-2" id="options-{{ Str::slug($attrKey) }}">
                                @foreach($attributeOptions[$attrKey] as $opt)
                                <button type="button"
                                    class="variation-btn border border-gray-300 rounded px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary hover:text-primary transition"
                                    data-attr="{{ $attrKey }}" data-val="{{ $opt }}">
                                    {{ $opt }}
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <div id="add-to-cart-section" class="flex items-center gap-4 hidden">
                        <div class="flex items-center border border-gray-300 rounded h-12 w-32">
                            <button type="button" class="w-10 h-full text-gray-600 hover:bg-gray-100 transition" onclick="const q=document.getElementById('vqty'); if(q.value>1) q.value--">-</button>
                            <input type="number" id="vqty" name="quantity" value="1" min="1" class="w-12 h-full text-center border-none focus:ring-0 appearance-none font-semibold text-body">
                            <button type="button" class="w-10 h-full text-gray-600 hover:bg-gray-100 transition" onclick="document.getElementById('vqty').value++">+</button>
                        </div>
                        <?php do_lazy_action('lazy_variable_before_add_to_cart_button', $post); ?>
                        <?php
                            $btnHtml = '<button type="submit" id="variable-add-btn" class="flex-grow h-12 bg-primary text-white font-bold rounded hover:bg-primary-hover transition-colors duration-300">Add to Cart</button>';
                            $btnHtml = apply_lazy_filters('lazy_variable_add_to_cart_button', $btnHtml, $post);
                            echo $btnHtml;
                        ?>
                        <?php do_lazy_action('lazy_variable_after_add_to_cart_button', $post); ?>
                    </div>

                    <div id="variation-message" class="text-sm text-gray-500 mt-3"></div>

                    <?php do_lazy_action('lazy_variable_add_to_cart_form_bottom', $post); ?>
                </form>
                <?php do_lazy_action('lazy_variable_after_add_to_cart_form', $post); ?>

                <!-- Product Meta -->
                <?php do_lazy_action('lazy_variable_before_product_meta', $post); ?>
                <div class="text-sm text-gray-500 space-y-2">
                    @if($post->sku)
                    <p><span class="font-bold text-gray-800">SKU:</span> <span id="variation-sku">{{ $post->sku }}</span></p>
                    @endif
                    @if($post->productCategories && $post->productCategories->count())
                    <p><span class="font-bold text-gray-800">Categories:</span>
                        @foreach($post->productCategories as $cat)
                            <a href="{{ url('product-category/' . $cat->getFullSlugPath()) }}" class="hover:text-primary transition">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    </p>
                    @endif
                    <?php do_lazy_action('lazy_variable_product_meta_fields', $post); ?>
                </div>
                <?php do_lazy_action('lazy_variable_after_product_meta', $post); ?>

            </div>
        </div>

        <!-- Description -->
        <?php do_lazy_action('lazy_before_product_description', $post); ?>
        <?php do_lazy_action('lazy_variable_before_product_description', $post); ?>
        @if($post->content)
        <div class="mt-20 border-t border-gray-100 pt-12">
            <?php
                $descTitleHtml = '<h3 class="text-2xl font-bold text-heading mb-8 inline-block border-b-2 border-primary pb-2">Description</h3>';
                $descTitleHtml = apply_lazy_filters('lazy_product_description_title', $descTitleHtml, $post);
                echo $descTitleHtml;
            ?>
            <?php
                $descHtml = '<div class="prose max-w-none text-body">' . $post->content . '</div>';
                $descHtml = apply_lazy_filters('lazy_variable_product_description', $descHtml, $post);
                echo $descHtml;
            ?>
        </div>
        @endif
        <?php do_lazy_action('lazy_after_product_description', $post); ?>
        <?php do_lazy_action('lazy_variable_after_product_description', $post); ?>

    </div>
</div>
<?php do_lazy_action('lazy_after_single_product', $post); ?>
<?php do_lazy_action('lazy_variable_after_single_product', $post); ?>
@stop

@push('scripts')
<script>
(function () {
    var variations   = {!! $variationsJson !!};
    var attrKeys     = @json($attributeKeys);
    var selected     = {};
    var defaultImg   = '{{ $post->thumbnail ? url($post->thumbnail) : '' }}';
    var defaultPrice = '{{ lazy_price_format($post->price ?? 0) }}';
    var defaultSale  = '{{ $post->sale_price ? lazy_price_format($post->sale_price) : '' }}';

    function slugify(s) {
        return s.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    }

    function findVariation() {
        if (Object.keys(selected).length !== attrKeys.length) return null;
        return variations.find(function (v) {
            return attrKeys.every(function (k) { return (v.attributes[k] ?? '') === (selected[k] ?? ''); });
        }) || null;
    }

    function updateUI() {
        var v = findVariation();
        var section = document.getElementById('add-to-cart-section');
        var msg     = document.getElementById('variation-message');
        var badge   = document.getElementById('variation-stock-badge');
        var stockTxt= document.getElementById('variation-stock-text');
        var addBtn  = document.getElementById('variable-add-btn');
        var oosBadge= document.getElementById('oos-badge');
        var saleBadge= document.getElementById('sale-badge');
        var vidInput= document.getElementById('selected-variation-id');

        if (!v) {
            section.classList.add('hidden');
            msg.textContent = Object.keys(selected).length > 0 ? 'This combination is not available.' : '';
            badge.classList.add('hidden');
            oosBadge.classList.add('hidden');
            vidInput.value = '';
            return;
        }

        vidInput.value = v.id;
        section.classList.remove('hidden');

        // Price
        var priceRange = document.getElementById('price-range-display');
        var mainP = document.getElementById('main-price-display');
        var regP  = document.getElementById('regular-price-display');
        var saleP = document.getElementById('sale-price-display');

        if (priceRange) priceRange.classList.add('hidden');
        if (v.sale_price) {
            if (mainP) mainP.style.display = 'none';
            if (regP)  { regP.style.display = ''; regP.textContent = formatPrice(v.price); }
            if (saleP) { saleP.style.display = ''; saleP.textContent = formatPrice(v.sale_price); }
            if (saleBadge) saleBadge.classList.remove('hidden');
        } else {
            if (regP)  regP.style.display = 'none';
            if (saleP) saleP.style.display = 'none';
            if (mainP) { mainP.style.display = ''; mainP.textContent = formatPrice(v.price); }
            if (saleBadge) saleBadge.classList.add('hidden');
        }

        // Image
        if (v.image) {
            document.getElementById('main-image').src = v.image.startsWith('http') ? v.image : '/'+v.image;
        }

        // SKU
        var skuEl = document.getElementById('variation-sku');
        if (skuEl && v.sku) skuEl.textContent = v.sku;

        // Stock
        if (!v.in_stock) {
            badge.classList.remove('hidden');
            stockTxt.textContent = 'Out of stock';
            stockTxt.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800';
            if (addBtn) { addBtn.disabled = true; addBtn.classList.add('opacity-50', 'cursor-not-allowed'); }
            oosBadge.classList.remove('hidden');
        } else {
            if (v.stock_qty !== null) {
                badge.classList.remove('hidden');
                stockTxt.textContent = v.stock_qty + ' in stock';
                stockTxt.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800';
            } else {
                badge.classList.add('hidden');
            }
            if (addBtn) { addBtn.disabled = false; addBtn.classList.remove('opacity-50', 'cursor-not-allowed'); }
            oosBadge.classList.add('hidden');
        }

        msg.textContent = '';
    }

    function formatPrice(num) {
        if (!num) return '';
        // Simple format — matches lazy_price_format logic
        var symbol = '{{ get_shop_option("shop_currency_symbol", "$") }}';
        var pos    = '{{ get_shop_option("shop_currency_pos", "left") }}';
        var dec    = parseInt('{{ get_shop_option("shop_num_decimals", 2) }}') || 2;
        var formatted = parseFloat(num).toFixed(dec);
        return pos === 'left' ? symbol + formatted : formatted + symbol;
    }

    document.querySelectorAll('.variation-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var attr = this.dataset.attr;
            var val  = this.dataset.val;
            selected[attr] = val;

            // Toggle active state for this attribute group
            document.querySelectorAll('[data-attr="' + attr + '"]').forEach(function (b) {
                b.classList.remove('border-primary', 'text-primary', 'bg-primary/5');
            });
            this.classList.add('border-primary', 'text-primary', 'bg-primary/5');

            var labelEl = document.getElementById('selected-' + slugify(attr));
            if (labelEl) labelEl.textContent = val;

            updateUI();
        });
    });
})();
</script>
@endpush
