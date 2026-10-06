{{-- Off-canvas mini-cart drawer, added by the shop plugin to every storefront page
     (falcon_after_footer, or falcon_footer for a theme without it). --}}
<div id="mini-cart-root" class="fixed inset-0 z-[9999] invisible" aria-hidden="true">
    <!-- Backdrop -->
    <div id="mini-cart-overlay" class="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300" onclick="LazyCart.close()"></div>

    <!-- Panel -->
    <aside id="mini-cart-panel"
           class="absolute top-0 right-0 h-full w-[88%] max-w-[400px] bg-white shadow-2xl flex flex-col translate-x-full transition-transform duration-300 ease-out"
           role="dialog" aria-modal="true" aria-label="Shopping cart">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-[16px] font-bold text-heading flex items-center gap-2">
                <i data-lucide="shopping-bag" class="w-5 h-5 text-primary"></i>
                Your Cart <span id="mini-cart-count" class="text-[13px] font-semibold text-gray-400">(0)</span>
            </h2>
            <button type="button" onclick="LazyCart.close()" class="text-gray-400 hover:text-heading transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Items (filled via AJAX) -->
        <div id="mini-cart-items" class="flex-1 overflow-y-auto">
            <div class="flex items-center justify-center py-20">
                <i data-lucide="loader-circle" class="w-6 h-6 text-gray-300 animate-spin"></i>
            </div>
        </div>

        <!-- Footer -->
        <div id="mini-cart-footer" class="border-t border-gray-100 px-5 py-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[14px] text-gray-500">Subtotal</span>
                <span id="mini-cart-subtotal" class="text-[18px] font-black text-heading">{{ falcon_price_format(0) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('shop.cart') }}" class="text-center bg-white text-primary border border-primary px-4 py-2.5 rounded-[3px] text-[13px] font-semibold uppercase tracking-wider hover:bg-gray-50 hover:text-primary transition-colors">View Cart</a>
                <a href="{{ route('shop.checkout') }}" class="text-center bg-primary text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold uppercase tracking-wider hover:opacity-90 hover:text-white transition-colors">Checkout</a>
            </div>
        </div>
    </aside>
</div>


<script>
window.FalconShopConfig = Object.assign(window.FalconShopConfig || {}, {
    routes: {
        add: @json(route('shop.cart.add')),
        fragment: @json(route('shop.cart.fragment')),
        removeTpl: @json(route('shop.cart.remove', ['key' => '__KEY__'])),
    },
    csrf: @json(csrf_token()),
});
</script>
<script src="{{ falcon_plugin_asset('falcon-shop', 'frontend/js/mini-cart.js') }}"></script>
