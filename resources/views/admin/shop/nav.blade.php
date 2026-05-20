<div class="flex items-center gap-1 border-b border-[#c3c4c7] mb-8">
    <button type="button" 
            @click="tab = 'general'" 
            :class="tab === 'general' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        General
    </button>
    <button type="button" 
            @click="tab = 'products'" 
            :class="tab === 'products' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        Product & Inventory
    </button>
    <button type="button" 
            @click="tab = 'payments'" 
            :class="tab === 'payments' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        Payments
    </button>
    <button type="button" 
            @click="tab = 'shipping'" 
            :class="tab === 'shipping' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        Shipping
    </button>
    <button type="button" 
            @click="tab = 'tax'" 
            :class="tab === 'tax' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        Tax
    </button>
    <button type="button" 
            @click="tab = 'coupons'" 
            :class="tab === 'coupons' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        Coupons
    </button>
    <button type="button" 
            @click="tab = 'emails_accounts'" 
            :class="tab === 'emails_accounts' ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]'"
            class="px-4 py-2 text-[14px]">
        Email and Account
    </button>
</div>
