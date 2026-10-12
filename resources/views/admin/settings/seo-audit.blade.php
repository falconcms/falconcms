<x-falcon-cms::layouts.admin>
    <x-slot name="title">SEO Audit - FalconCMS</x-slot>

    @php
        $score = $audit['score'];
        $tone = $score >= 80 ? ['#00a32a', 'Good'] : ($score >= 50 ? ['#dba617', 'Needs work'] : ['#d63638', 'Poor']);
        $badge = ['error' => 'bg-[#fcf0f1] text-[#a7272b]', 'warning' => 'bg-[#fcf9e8] text-[#8a6100]', 'info' => 'bg-[#f0f6fc] text-[#135e96]'];
        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        $checks = collect($audit['checks'])->sortBy(fn ($c) => $order[$c['severity']] ?? 3)->values();
    @endphp

    <div class="px-2 max-w-[1000px]">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">SEO Audit</h1>
        @include('falcon-cms::admin.settings.nav')

        <div class="wp-metabox mb-6">
            <div class="wp-metabox-content p-5 flex items-center gap-6 flex-wrap">
                <div class="w-[96px] h-[96px] rounded-full flex items-center justify-center shrink-0" style="background: conic-gradient({{ $tone[0] }} {{ $score * 3.6 }}deg, #e0e0e0 0)">
                    <div class="w-[78px] h-[78px] rounded-full bg-white flex flex-col items-center justify-center">
                        <span class="text-[26px] font-bold leading-none" style="color: {{ $tone[0] }}">{{ $score }}</span>
                        <span class="text-[10px] text-[#646970] uppercase">{{ $tone[1] }}</span>
                    </div>
                </div>
                <div class="flex-1 min-w-[240px] text-[13px] text-[#50575e]">
                    <p class="mb-1"><strong class="text-[#1d2327]">{{ number_format($audit['checked']) }}</strong> published pages and posts checked.
                        {{ $checks->isEmpty() ? 'Nothing to fix.' : $checks->count().' kind(s) of issue found, most important first.' }}</p>
                    <p class="text-[12px]">The audit only reads your content; fixing is done in each page's editor. Broken links visitors hit are listed under
                        <a href="{{ route('admin.redirects.index') }}" class="text-[#2271b1]">Redirects</a>.</p>
                </div>
            </div>
        </div>

        @forelse($checks as $check)
            <details class="wp-metabox mb-4" @if($loop->first) open @endif>
                <summary class="wp-metabox-header cursor-pointer flex items-center gap-2">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $badge[$check['severity']] ?? $badge['info'] }}">{{ $check['severity'] === 'error' ? 'Fix' : ($check['severity'] === 'warning' ? 'Important' : 'Tip') }}</span>
                    <span class="font-semibold">{{ $check['label'] }}</span>
                    <span class="text-[#8c8f94] font-normal">({{ count($check['items']) }})</span>
                </summary>
                <div class="wp-metabox-content p-0">
                    <p class="px-4 py-3 text-[12px] text-[#646970] border-b border-[#f0f0f1]">{{ $check['help'] }}</p>
                    <table class="w-full text-[13px]">
                        <tbody>
                            @foreach(array_slice($check['items'], 0, 100) as $item)
                                <tr class="border-b border-[#f6f7f7] last:border-0">
                                    <td class="px-4 py-2 font-medium text-[#1d2327] max-w-[320px] truncate">{{ $item['title'] }}</td>
                                    <td class="px-4 py-2 text-[#646970]">{{ $item['detail'] }}</td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">@if(!empty($item['edit']))<a href="{{ $item['edit'] }}" class="text-[#2271b1] font-semibold">Fix</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(count($check['items']) > 100)
                        <p class="px-4 py-2 text-[12px] text-[#646970]">…and {{ count($check['items']) - 100 }} more.</p>
                    @endif
                </div>
            </details>
        @empty
            <div class="wp-metabox"><div class="wp-metabox-content p-6 text-center text-[14px] text-[#00712a]">Everything checked looks good. 🎉</div></div>
        @endforelse
    </div>
</x-falcon-cms::layouts.admin>
