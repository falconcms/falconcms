<x-falcon-cms::layouts.admin>
    <x-slot name="title">SEO Audit - FalconCMS</x-slot>

    @php
        $score = $audit['score'];
        $tone = $score >= 80 ? ['#00a32a', 'Good'] : ($score >= 50 ? ['#dba617', 'Needs work'] : ['#d63638', 'Poor']);
        $badge = ['error' => 'bg-[#fcf0f1] text-[#a7272b]', 'warning' => 'bg-[#fcf9e8] text-[#8a6100]', 'info' => 'bg-[#f0f6fc] text-[#135e96]'];
        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        $checks = collect($audit['checks'])->sortBy(fn ($c) => $order[$c['severity']] ?? 3)->values();
    @endphp

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">SEO Audit</h1>
        @include('falcon-cms::admin.settings.nav')

        {{-- The tabs span the screen like every Settings tab; the audit itself keeps a readable width. --}}
        <div class="max-w-[1000px]">

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

        @if($checks->isNotEmpty())
            <div class="flex justify-end gap-3 mb-2 text-[12px]">
                <button type="button" onclick="document.querySelectorAll('.seo-audit-check').forEach(d => d.open = true)" class="text-[#2271b1] hover:underline">Show all pages</button>
                <button type="button" onclick="document.querySelectorAll('.seo-audit-check').forEach(d => d.open = false)" class="text-[#2271b1] hover:underline">Hide all</button>
            </div>
        @endif

        @forelse($checks as $check)
            @php $count = count($check['items']); @endphp
            {{-- Problems to fix open by default; the rest one click away. --}}
            <details class="seo-audit-check group wp-metabox mb-4" @if($check['severity'] === 'error' || $loop->first) open @endif>
                <summary class="wp-metabox-header cursor-pointer flex items-center gap-2 list-none [&::-webkit-details-marker]:hidden">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $badge[$check['severity']] ?? $badge['info'] }}">{{ $check['severity'] === 'error' ? 'Fix' : ($check['severity'] === 'warning' ? 'Important' : 'Tip') }}</span>
                    <span class="font-semibold">{{ $check['label'] }}</span>
                    <span class="text-[#8c8f94] font-normal">({{ $count }})</span>
                    <span class="ml-auto flex items-center gap-1 text-[12px] font-normal text-[#2271b1] whitespace-nowrap">
                        <span class="group-open:hidden">Show {{ $count }} {{ $count === 1 ? 'page' : 'pages' }}</span>
                        <span class="hidden group-open:inline">Hide</span>
                        <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </summary>
                <div class="wp-metabox-content p-0">
                    <p class="px-4 py-3 text-[12px] text-[#646970] border-b border-[#f0f0f1]">{{ $check['help'] }}</p>
                    <table class="w-full text-[13px]">
                        <tbody>
                            @foreach(array_slice($check['items'], 0, 100) as $item)
                                <tr class="border-b border-[#f6f7f7] last:border-0">
                                    <td class="px-4 py-2 max-w-[320px]">
                                        @if(!empty($item['edit']))
                                            <a href="{{ $item['edit'] }}" class="block font-medium text-[#2271b1] hover:underline truncate">{{ $item['title'] }}</a>
                                        @else
                                            <span class="block font-medium text-[#1d2327] truncate">{{ $item['title'] }}</span>
                                        @endif
                                        @if(!empty($item['url']))
                                            <span class="block text-[11px] text-[#8c8f94] truncate">{{ preg_replace('#^https?://[^/]+#', '', $item['url']) ?: '/' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-[#646970]">{{ $item['detail'] }}</td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap space-x-3">
                                        @if(!empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="text-[#646970] hover:text-[#2271b1]">View</a>@endif
                                        @if(!empty($item['edit']))<a href="{{ $item['edit'] }}" class="text-[#2271b1] font-semibold">Fix</a>@endif
                                    </td>
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

        @php $plannerCount = \FalconSeo\Support\KeywordDemand::plannerCount(); @endphp
        {{-- Search demand data for the editor's Keywords tab (Settings → SEO → Keyword research uses it). --}}
        <div class="wp-metabox mt-8" id="keyword-planner-import">
            <div class="wp-metabox-header"><span>Import Google Keyword Planner data</span></div>
            <div class="wp-metabox-content p-4 space-y-3 text-[13px]">
                @if(session('success'))
                    <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-2 text-[12px]">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-2 text-[12px]">{{ session('error') }}</div>
                @endif
                @error('keywords_file')
                    <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-2 text-[12px]">{{ $message }}</div>
                @enderror
                <ol class="list-decimal ml-5 text-[12px] text-[#646970] space-y-1">
                    <li>Open <a href="https://ads.google.com/aw/keywordplanner/home" target="_blank" rel="noopener" class="text-[#2271b1]">Google Keyword Planner</a> (free with a Google Ads account; no ad needs to run).</li>
                    <li>Choose "Discover new keywords" or "Get search volume", enter your topics and set the location and language.</li>
                    <li>Press Download keyword ideas → .csv, and upload the file here. The editor's Keywords tab then shows how many people search for a keyword. Any keyword tool's CSV with a "Keyword" and a volume column works too.</li>
                </ol>
                <form method="POST" action="{{ route('admin.settings.seo.keywords-import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="keywords_file" accept=".csv,.tsv,.txt" required class="text-[12px]">
                    <button type="submit" class="wp-btn-secondary h-8 py-0 px-3 text-[13px]">Import</button>
                </form>
                @if($plannerCount)
                    <form method="POST" action="{{ route('admin.settings.seo.keywords-clear') }}" onsubmit="return confirm('Remove all imported keyword data?')">
                        @csrf
                        <button type="submit" class="text-[12px] text-[#b32d2e] hover:underline">Remove the {{ number_format($plannerCount) }} imported keywords</button>
                    </form>
                @endif
            </div>
        </div>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
