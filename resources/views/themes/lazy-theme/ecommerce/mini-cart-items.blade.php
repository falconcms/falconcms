{{-- Mini-cart line items (rendered into the off-canvas drawer via AJAX). Expects: $cart --}}
@if(empty($cart))
    <?php do_lazy_action('lazy_mini_cart_empty'); ?>
    <div class="flex flex-col items-center justify-center text-center py-20 px-6">
        <div class="w-16 h-16 rounded-full bg-gray-50 flex items-center justify-center mb-4">
            <i data-lucide="shopping-cart" class="w-7 h-7 text-gray-300"></i>
        </div>
        <p class="text-[15px] font-semibold text-heading mb-1">Your cart is empty</p>
        <p class="text-[13px] text-gray-400">Add some products to get started.</p>
    </div>
@else
    <?php do_lazy_action('lazy_before_mini_cart', $cart); ?>
    <ul class="divide-y divide-gray-100">
        @foreach($cart as $key => $item)
            @php $linePrice = $item['sale_price'] ?: $item['price']; @endphp
            <?php do_lazy_action('lazy_before_mini_cart_item', $item, $key); ?>
            <li class="flex gap-3 py-4 px-5">
                <a href="{{ get_lazy_permalink(['slug' => $item['slug'], 'type' => 'product']) }}" class="shrink-0 block w-16 h-16 rounded-sm overflow-hidden bg-[#eef1f5]">
                    <img src="{{ get_lazy_image_url($item['thumbnail']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                </a>
                <div class="flex-1 min-w-0">
                    {!! apply_lazy_filters('lazy_mini_cart_item_name',
                        '<a href="' . get_lazy_permalink(['slug' => $item['slug'], 'type' => 'product']) . '" class="block text-[13px] font-bold text-heading hover:text-primary leading-snug line-clamp-2">' . e($item['name']) . '</a>',
                        $item, $key) !!}
                    <div class="mt-1 text-[12px] text-gray-500">
                        {{ $item['quantity'] }} &times; <span class="font-semibold text-heading">{{ lazy_price_format($linePrice) }}</span>
                    </div>
                    {!! lazy_render_item_custom_fields($item, 'mini-cart') !!}
                    <?php do_lazy_action('lazy_mini_cart_item_meta', $item, $key); ?>
                </div>
                <div class="flex flex-col items-end justify-between shrink-0">
                    <button type="button" onclick="LazyCart.remove('{{ $key }}')" title="Remove" class="text-gray-300 hover:text-red-500 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                    <span class="text-[13px] font-bold text-heading">{{ lazy_price_format($linePrice * $item['quantity']) }}</span>
                </div>
            </li>
            <?php do_lazy_action('lazy_after_mini_cart_item', $item, $key); ?>
        @endforeach
    </ul>
    <?php do_lazy_action('lazy_after_mini_cart', $cart); ?>
@endif
