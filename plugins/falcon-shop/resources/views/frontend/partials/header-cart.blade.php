{{-- Header cart icon with its count badge, added by the shop plugin at falcon_header_actions. --}}
<a href="{{ route('shop.cart') }}" class="relative group hover:text-primary transition-colors" style="color: inherit;">
    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
    @php $count = get_falcon_cart_count(); @endphp
    <span class="cart-count-badge absolute -top-2.5 -right-2.5 bg-primary text-white text-[10px] font-black flex items-center justify-center rounded-full ring-2 ring-white {{ $count > 0 ? '' : 'hidden' }}" style="min-width:18px;height:18px;padding:0 2px;box-sizing:border-box;">
        {{ $count }}
    </span>
</a>
