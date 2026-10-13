{{--
    SEO's tabs, like Settings'. The core brings Settings and Redirects; plugins add theirs through
    `falcon_seo_tabs` (Falcon SEO: Site Analysis, SEO Audit, Bulk SEO). A tab whose route is not
    registered (its plugin switched off) is left out.
--}}
@php
    $falconSeoTabs = collect(apply_falcon_filters('falcon_seo_tabs', [
        ['label' => 'Settings', 'route' => 'admin.settings.seo', 'order' => 10],
        ['label' => 'Redirects', 'route' => 'admin.redirects.index', 'order' => 90],
    ]))->filter(fn ($tab) => !empty($tab['route']) && \Illuminate\Support\Facades\Route::has($tab['route']))
        ->sortBy(fn ($tab) => $tab['order'] ?? 50)->values();
@endphp
<div class="flex items-center gap-1 border-b border-[#c3c4c7] mb-8 overflow-x-auto overflow-y-hidden [&>a]:flex-shrink-0 [&>a]:whitespace-nowrap">
    @foreach($falconSeoTabs as $tab)
        <a href="{{ route($tab['route']) }}" class="px-4 py-2 text-[14px] {{ request()->routeIs($tab['route']) ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7] border-b-white' : 'text-[#2271b1] hover:text-[#135e96]' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
