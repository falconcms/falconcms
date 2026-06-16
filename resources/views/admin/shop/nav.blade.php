<div class="flex flex-wrap items-center gap-1 border-b border-[#c3c4c7] mb-8">
    @php
        $shopTabs = [
            'general'        => 'General',
            'products'       => 'Product & Inventory',
            'payments'       => 'Payments',
            'shipping'       => 'Shipping',
            'tax'            => 'Tax',
            'coupons'        => 'Coupons',
            'emails_accounts'=> 'Email and Account',
        ];
        $activeNavTab = request('tab', session('active_shop_tab', 'general'));
    @endphp

    @foreach($shopTabs as $key => $label)
    @php $isActive = $activeNavTab === $key; @endphp
    <a href="{{ route('admin.shop.settings') }}?tab={{ $key }}"
       @click="tab = '{{ $key }}'; $nextTick(() => window.history.replaceState({}, '', '?tab={{ $key }}')); $event.preventDefault()"
       :class="tab === '{{ $key }}' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
       class="px-4 py-2 text-[14px] {{ $isActive ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]' }}">
        {{ $label }}
    </a>
    @endforeach
</div>
