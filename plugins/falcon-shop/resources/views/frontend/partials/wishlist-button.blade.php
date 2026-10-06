@php $wlPid = $product->id ?? ($productId ?? 0); $wlActive = falcon_in_wishlist($wlPid); @endphp
<button type="button"
        class="lazy-wishlist-btn{{ $wlActive ? ' is-active' : '' }}"
        data-product-id="{{ $wlPid }}"
        title="{{ $wlActive ? 'In your wishlist' : 'Add to wishlist' }}"
        aria-label="Add to wishlist">
    <i data-lucide="heart"></i>
</button>

@once
<link rel="stylesheet" href="{{ falcon_plugin_asset('falcon-shop', 'frontend/css/wishlist.css') }}">
<script>
(window.FalconShopConfig = window.FalconShopConfig || {}).wishlist = {
    toggle: @json(route('shop.wishlist.toggle')),
    csrf: @json(csrf_token()),
};
</script>
<script data-no-defer src="{{ falcon_plugin_asset('falcon-shop', 'frontend/js/wishlist.js') }}"></script>
@endonce
