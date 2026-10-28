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
        // A plugin's own tab ([id => label]); its panel goes in at falcon_shop_settings_form_bottom.
        // The id goes into the Alpine expressions below, so only a plain slug is taken.
        foreach ((array) apply_falcon_filters('falcon_shop_settings_tabs', []) as $tabId => $tabLabel) {
            if (is_string($tabId) && preg_match('/^[a-z0-9_-]+$/', $tabId) && is_string($tabLabel) && !isset($shopTabs[$tabId])) {
                $shopTabs[$tabId] = $tabLabel;
            }
        }
        $activeNavTab = request('tab', session('active_shop_tab', 'general'));
    @endphp

    @foreach($shopTabs as $key => $label)
    {{-- Active state is driven solely by Alpine (:class); a static Blade active
         class here would linger after switching tabs (Alpine can't remove it),
         leaving two tabs highlighted at once. --}}
    <a href="{{ route('admin.shop.settings') }}?tab={{ $key }}"
       @click="tab = '{{ $key }}'; $nextTick(() => window.history.replaceState({}, '', '?tab={{ $key }}')); $event.preventDefault()"
       :class="tab === '{{ $key }}' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
       class="px-4 py-2 text-[14px]">
        {{ $label }}
    </a>
    @endforeach
</div>
