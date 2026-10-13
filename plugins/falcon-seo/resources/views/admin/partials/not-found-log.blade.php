{{-- SEO → Redirects: addresses people asked for that do not exist (falcon_redirects_screen). --}}
@php $notFound = \FalconSeo\Support\NotFoundLog::top(50); @endphp
<div class="lg:col-span-3 order-first">
    <div class="wp-metabox">
        <div class="wp-metabox-header flex items-center justify-between">
            <span>404 errors <span class="text-[#8c8f94] font-normal">({{ count($notFound) }})</span></span>
            @if(count($notFound))
                <form action="{{ route('admin.redirects.not-found.forget') }}" method="POST" onsubmit="return confirm('Clear the whole 404 list?')">@csrf
                    <input type="hidden" name="all" value="1">
                    <button class="text-[12px] text-[#646970] hover:text-[#d63638] pr-2">Clear all</button>
                </form>
            @endif
        </div>
        <div class="wp-metabox-content p-0">
            @if(!\FalconSeo\Support\NotFoundLog::enabled())
                <p class="p-4 text-[13px] text-[#646970]">404 logging is turned off in Settings → SEO.</p>
            @elseif(count($notFound) === 0)
                <p class="p-4 text-[13px] text-[#646970]">No missing pages recorded in the last {{ \FalconSeo\Support\NotFoundLog::KEEP_DAYS }} days. Addresses visitors ask for that do not exist show up here, ready to redirect.</p>
            @else
                <div class="overflow-x-auto max-h-[360px] overflow-y-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-[#f6f7f7] text-left text-[#50575e] sticky top-0">
                            <tr><th class="px-4 py-2 font-semibold">Missing address</th><th class="px-4 py-2 font-semibold text-right">Hits</th><th class="px-4 py-2 font-semibold">Last seen</th><th class="px-4 py-2 font-semibold">Came from</th><th class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody>
                            @foreach($notFound as $nf)
                                <tr class="border-t border-[#f0f0f1]">
                                    <td class="px-4 py-2 font-medium text-[#1d2327] break-all">{{ $nf->path }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($nf->hits) }}</td>
                                    <td class="px-4 py-2 text-[#646970] whitespace-nowrap">{{ \Carbon\Carbon::parse($nf->last_seen_at)->diffForHumans() }}</td>
                                    <td class="px-4 py-2 text-[12px] text-[#646970] max-w-[260px] truncate" title="{{ $nf->referrer }}">{{ $nf->referrer ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <a href="{{ route('admin.redirects.index', ['from' => $nf->path]) }}" class="text-[#2271b1] font-semibold">Redirect</a>
                                        <form action="{{ route('admin.redirects.not-found.forget') }}" method="POST" class="inline ml-2">@csrf
                                            <input type="hidden" name="path" value="{{ $nf->path }}">
                                            <button class="text-[#646970] hover:text-[#d63638]">Dismiss</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
