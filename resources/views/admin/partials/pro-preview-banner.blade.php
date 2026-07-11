{{-- "Browse but locked" preview banner. Pass $feature (e.g. 'ecommerce') and optional $previewTitle. --}}
@if(! falcon_pro_editable($feature ?? 'builder_pro'))
<div class="mb-5 flex items-center gap-3 rounded-lg border border-[#f0c47a] bg-[#fdf6e9] px-4 py-3">
    <span class="shrink-0 material-symbols-outlined text-[#c98a1a]" style="font-size:24px">lock</span>
    <div class="flex-1">
        <div class="text-[13.5px] font-bold text-[#5b4a1f]">{{ $previewTitle ?? "You're viewing this in preview" }}</div>
        <div class="text-[12.5px] text-[#7a663a]">Look around freely — creating, editing or deleting needs Pro.</div>
    </div>
    <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="shrink-0 rounded-md bg-[#e8912b] px-4 py-2 text-[12.5px] font-bold text-[#171c23] hover:brightness-105 no-underline">Upgrade to Pro</a>
</div>
@endif
