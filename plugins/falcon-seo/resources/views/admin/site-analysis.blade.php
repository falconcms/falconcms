<x-falcon-cms::layouts.admin>
    <x-slot name="title">Site Analysis - FalconCMS</x-slot>

    @php
        $s = $summary;
        $avg = $s['average'];
        $tone = fn ($score) => $score >= 80 ? '#00a32a' : ($score >= 50 ? '#dba617' : '#d63638');
        $gradeName = ['good' => 'Good', 'ok' => 'Needs some work', 'poor' => 'Needs work'];
        $edit = fn ($row) => \FalconSeo\Support\SeoAudit::editUrl((int) $row['post_id'], (string) $row['type']);
        $pro = \FalconSeo\Support\Pro::active();
        $ai = \FalconSeo\Support\AiWriter::available();
        // Pro one-click fixes: what each button does, by problem. [label, method, needs AI]
        $fixes = [
            'description' => [['Fill missing ones from the page text', 'auto', false], ['Write them with AI', 'ai', true]],
            'title' => [['Rewrite them with AI', 'ai', true]],
            'alt' => [['Name them from the file names', 'auto', false], ['Describe them with AI', 'ai', true]],
            'broken_links' => [['Remove the broken links (keep the words)', 'auto', false]],
        ];
    @endphp

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Site Analysis</h1>
        @include('falcon-cms::admin.seo.nav')

        <div class="max-w-[1100px]">
        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-4 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif

        {{-- Summary and "Scan now" --}}
        <div class="wp-metabox mb-6">
            <div class="wp-metabox-content p-5 flex items-center gap-6 flex-wrap">
                @if($avg !== null)
                    <div class="w-[96px] h-[96px] rounded-full flex items-center justify-center shrink-0" style="background: conic-gradient({{ $tone($avg) }} {{ $avg * 3.6 }}deg, #e0e0e0 0)">
                        <div class="w-[78px] h-[78px] rounded-full bg-white flex flex-col items-center justify-center">
                            <span class="text-[26px] font-bold leading-none" style="color: {{ $tone($avg) }}">{{ $avg }}</span>
                            <span class="text-[10px] text-[#646970] uppercase">average</span>
                        </div>
                    </div>
                @endif
                <div class="flex-1 min-w-[260px] text-[13px] text-[#50575e] space-y-1">
                    <p><strong class="text-[#1d2327]">{{ number_format($s['checked']) }}</strong> of {{ number_format($s['pages']) }} pages checked
                        @if($s['last']) · last {{ \Illuminate\Support\Carbon::parse($s['last'])->diffForHumans() }} @endif</p>
                    <p>
                        <span class="text-[#00a32a] font-semibold">{{ $s['grades']['good'] }} good</span> ·
                        <span class="text-[#996800] font-semibold">{{ $s['grades']['ok'] }} need some work</span> ·
                        <span class="text-[#d63638] font-semibold">{{ $s['grades']['poor'] }} need work</span>
                    </p>
                    <p class="text-[12px]">Each page is read as visitors get it, with every link checked. Pages are checked in the background a few at a time,
                        again after you change them and once a week; the editor's Analysis tab updates a page's score too.</p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <div class="flex gap-2">
                        <button type="button" id="site-scan-btn" onclick="falconSiteScan(false)" class="wp-btn-primary h-8 px-4 text-[13px] font-semibold">
                            {{ $s['due'] ? 'Check '.number_format($s['due']).' page(s) now' : 'Up to date' }}
                        </button>
                        <button type="button" onclick="falconSiteScan(true)" class="wp-btn-secondary h-8 px-3 text-[12px]" title="Check every page again, even ones checked recently">Check all again</button>
                    </div>
                    <label class="flex items-center gap-1.5 text-[12px] text-[#646970]"><input type="checkbox" id="site-scan-links" checked class="rounded-sm border-[#8c8f94]"> Check links</label>
                    <span id="site-scan-progress" class="text-[12px] text-[#646970]"></span>
                </div>
            </div>
        </div>

        {{-- Problems across the site --}}
        <h2 class="text-[15px] font-semibold text-[#1d2327] mb-2">Problems across the site</h2>
        @forelse($s['problems'] as $problem)
            @php $count = count($problem['pages']); @endphp
            <details class="group wp-metabox mb-3" @if($problem['id'] === 'broken_links') open @endif>
                <summary class="wp-metabox-header cursor-pointer flex items-center gap-2 list-none [&::-webkit-details-marker]:hidden">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $problem['status'] === 'error' ? 'bg-[#fcf0f1] text-[#a7272b]' : 'bg-[#fcf9e8] text-[#8a6100]' }}">{{ $problem['status'] === 'error' ? 'Fix' : 'Improve' }}</span>
                    <span class="font-semibold">{{ $problem['label'] }}</span>
                    <span class="text-[#8c8f94] font-normal">({{ $count }} {{ $count === 1 ? 'page' : 'pages' }})</span>
                    <span class="ml-auto text-[12px] font-normal text-[#2271b1]"><span class="group-open:hidden">Show</span><span class="hidden group-open:inline">Hide</span></span>
                </summary>
                <div class="wp-metabox-content p-0">
                    @isset($fixes[$problem['id']])
                        <div class="px-4 py-2 bg-[#f6f7f7] border-b border-[#f0f0f1] flex flex-wrap items-center gap-2 text-[12px]">
                            @if($pro)
                                <span class="text-[#646970]">Fix all {{ $count }}:</span>
                                @foreach($fixes[$problem['id']] as [$label, $method, $needsAi])
                                    <button type="button" class="wp-btn-secondary h-7 py-0 px-2 text-[12px]" @disabled($needsAi && !$ai)
                                            title="{{ $needsAi && !$ai ? 'Add a Claude API key in SEO → Settings first' : 'A revision is kept before any content changes' }}"
                                            onclick="falconSiteFix(this, @js($problem['id']), @js($method), @js(array_column($problem['pages'], 'post_id')))">{{ $needsAi ? '✨ ' : '' }}{{ $label }}</button>
                                @endforeach
                                <span class="falcon-site-fix-out text-[#646970]"></span>
                            @else
                                <span class="text-[#646970]">Fix all of these in one click with <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="text-[#2271b1] font-semibold">FalconCMS Pro</a>.</span>
                            @endif
                        </div>
                    @endisset
                    <table class="w-full text-[13px]">
                        <tbody>
                        @foreach($problem['pages'] as $page)
                            <tr class="border-t border-[#f0f0f1] align-top">
                                <td class="px-4 py-2 w-[34%]">
                                    @if($url = $edit($page))<a href="{{ $url }}" class="text-[#2271b1] font-medium">{{ $page['title'] ?: '(no title)' }}</a>@else{{ $page['title'] }}@endif
                                    <div class="text-[11px] text-[#8c8f94] break-all">{{ $page['url'] }}</div>
                                </td>
                                <td class="px-4 py-2 text-[12px] text-[#50575e]">
                                    {{ $page['label'] }}
                                    @if($page['items'])
                                        <ul class="list-disc pl-4 mt-1 text-[11px] text-[#646970] break-all">
                                            @foreach(array_slice($page['items'], 0, 8) as $item)<li>{{ $item }}</li>@endforeach
                                            @if(count($page['items']) > 8)<li>and {{ count($page['items']) - 8 }} more</li>@endif
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @empty
            <div class="wp-metabox mb-6"><div class="wp-metabox-content p-4 text-[13px] text-[#646970]">
                {{ $s['checked'] ? 'No problems found on the pages checked so far.' : 'No page has been checked yet. Press "Check now" to start.' }}
            </div></div>
        @endforelse

        {{-- Every page, lowest score first --}}
        <h2 class="text-[15px] font-semibold text-[#1d2327] mt-6 mb-2">Pages</h2>
        <div class="wp-metabox mb-8">
            <div class="wp-metabox-content p-0 overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#f6f7f7] text-left text-[#50575e]">
                        <tr><th class="px-4 py-2 font-semibold">Page</th><th class="px-4 py-2 font-semibold">Score</th><th class="px-4 py-2 font-semibold">To fix</th><th class="px-4 py-2 font-semibold">Checked</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                    @forelse($s['rows'] as $row)
                        @php $row = (array) $row; @endphp
                        <tr class="border-t border-[#f0f0f1]">
                            <td class="px-4 py-2">
                                @if($url = $edit($row))<a href="{{ $url }}" class="text-[#2271b1] font-medium">{{ $row['title'] ?: '(no title)' }}</a>@else{{ $row['title'] }}@endif
                                <span class="text-[11px] text-[#8c8f94] ml-1">{{ $row['type'] }}</span>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center justify-center w-9 h-6 rounded text-white text-[12px] font-bold" style="background: {{ $tone($row['score']) }}">{{ $row['score'] }}</span>
                                <span class="text-[11px] text-[#646970] ml-1">{{ $gradeName[$row['grade']] ?? '' }}</span>
                            </td>
                            <td class="px-4 py-2 text-[12px] whitespace-nowrap">
                                @if($row['errors'])<span class="text-[#d63638]">{{ $row['errors'] }} {{ $row['errors'] === 1 ? 'error' : 'errors' }}</span>@endif
                                @if($row['warnings'])<span class="text-[#996800] ml-1">{{ $row['warnings'] }} {{ $row['warnings'] === 1 ? 'warning' : 'warnings' }}</span>@endif
                                @if(!$row['errors'] && !$row['warnings'])<span class="text-[#00a32a]">nothing</span>@endif
                            </td>
                            <td class="px-4 py-2 text-[12px] text-[#646970] whitespace-nowrap">
                                {{ $row['analyzed_at'] ? \Illuminate\Support\Carbon::parse($row['analyzed_at'])->diffForHumans() : 'changed, due again' }}
                                @if($row['mode'] === 'editor')<span title="The page could not be fetched, so its content was read instead">· content only</span>@endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.seo.site-analysis.rescan', $row['post_id']) }}" class="inline">@csrf
                                    <button class="text-[12px] text-[#2271b1] hover:underline">Check again</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-[#646970]">No page checked yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>

    <script>
        // Pro "Fix all": the pages a few at a time; each result is listed, then the page reloads to show what is left.
        function falconSiteFix(btn, check, method, ids) {
            const bar = btn.parentNode;
            const out = bar.querySelector('.falcon-site-fix-out');
            const batch = {{ \FalconSeo\Http\Controllers\Admin\SeoProController::FIX_BATCH }};
            let fixed = 0, skipped = [];
            bar.querySelectorAll('button').forEach(b => b.disabled = true);
            const step = (i) => {
                if (i >= ids.length) {
                    out.style.color = '#00731a';
                    out.textContent = `Fixed ${fixed} page(s)` + (skipped.length ? `; ${skipped.length} left as they were (${skipped[0]})` : '') + '. Checking them again…';
                    return fetch(@json(route('admin.seo.site-analysis.scan')), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify({links: 1})})
                        .finally(() => window.location.reload());
                }
                out.textContent = `Fixing ${Math.min(i + batch, ids.length)} of ${ids.length}…`;
                return fetch(@json(route('admin.seo.site-analysis.fix')), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                    body: JSON.stringify({check, method, post_ids: ids.slice(i, i + batch)}),
                }).then(r => r.json().then(d => r.ok ? d : Promise.reject(new Error(d.message || 'The fix stopped.')))).then(d => {
                    (d.results || []).forEach(r => r.fixed ? fixed++ : skipped.push(r.title + ': ' + r.message));
                    return step(i + batch);
                });
            };
            step(0).catch(e => { out.style.color = '#b32d2e'; out.textContent = e.message; bar.querySelectorAll('button').forEach(b => b.disabled = false); });
        }

        // "Check now": the next few pages per request until none are due, then the page reloads with the results.
        function falconSiteScan(all) {
            const btn = document.getElementById('site-scan-btn');
            const out = document.getElementById('site-scan-progress');
            const links = document.getElementById('site-scan-links').checked ? 1 : 0;
            let checked = 0;
            btn.disabled = true;
            out.style.color = '#646970';
            const step = (restart) => fetch(@json(route('admin.seo.site-analysis.scan')), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                body: JSON.stringify({links, restart: restart ? 1 : 0}),
            }).then(r => r.ok ? r.json() : Promise.reject(new Error('The check stopped (' + r.status + ').'))).then(d => {
                checked += d.done;
                out.textContent = `Checked ${checked} page(s), ${d.due} to go…`;
                if (d.due > 0 && d.done > 0) return step(false);
                out.style.color = '#00731a';
                out.textContent = `Done: ${checked} page(s) checked.`;
                window.location.reload();
            });
            step(all).catch(e => { out.style.color = '#b32d2e'; out.textContent = e.message; btn.disabled = false; });
        }
    </script>
</x-falcon-cms::layouts.admin>
