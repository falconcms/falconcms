<x-falcon-cms::layouts.admin>
    <x-slot name="title">Analytics - FalconCMS</x-slot>
    <style>
        .classic-card { background:#fff; border:1px solid #c3c4c7; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px; border-radius:2px; }
        .classic-card-header { padding:10px 15px; border-bottom:1px solid #f0f0f1; display:flex; justify-content:space-between; align-items:center; }
        .classic-card-title { font-size:14px; font-weight:600; color:#1d2327; }
        .classic-stat-box { padding:20px; display:flex; align-items:center; gap:15px; }
        .classic-stat-icon { width:45px; height:45px; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; }
        .classic-stat-value { font-size:21px; font-weight:700; color:#1d2327; line-height:1.2; }
        .classic-stat-label { font-size:13px; color:#646970; font-weight:500; }
        .range-btn { font-size:12px; font-weight:600; padding:5px 12px; border:1px solid #c3c4c7; background:#fff; color:#50575e; border-radius:3px; }
        .range-btn.active { background:#2271b1; border-color:#2271b1; color:#fff; }

        /* ── Custom range calendar ──────────────────────────────────────────────────
           Only days the site has visits for can be picked, so a range can never be drawn
           across nothing. Each pickable day carries a bar showing how busy it was against
           the busiest day in the window, which turns the calendar into the answer to
           "where is there anything worth looking at" before a range is even chosen. */
        .rp { position:absolute; top:calc(100% + 8px); right:0; z-index:50; width:272px;
              background:#fff; border:1px solid #c3c4c7; border-radius:6px;
              box-shadow:0 8px 28px rgba(0,0,0,.14); padding:10px; }
        .rp[hidden] { display:none; }
        .rp-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
        .rp-month { font-size:13px; font-weight:700; color:#1d2327; }
        .rp-nav { width:26px; height:26px; display:flex; align-items:center; justify-content:center;
                  border:1px solid transparent; border-radius:4px; background:none; color:#50575e; cursor:pointer; }
        .rp-nav:hover:not(:disabled) { background:#f0f0f1; }
        .rp-nav:disabled { opacity:.3; cursor:default; }
        .rp-dows, .rp-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
        .rp-dows span { text-align:center; font-size:10px; font-weight:700; color:#8c8f94; padding-bottom:4px; }
        .rp-day { position:relative; height:32px; border:0; background:none; border-radius:4px;
                  font-size:12px; color:#1d2327; cursor:pointer; padding:0; overflow:hidden; }
        .rp-day:disabled { color:#c3c4c7; cursor:default; }
        .rp-day.rp-empty { visibility:hidden; }
        .rp-day:hover:not(:disabled) { background:#f0f6fc; }
        /* How busy the day was, as a bar along the bottom of its cell. */
        .rp-day .rp-heat { position:absolute; left:4px; right:4px; bottom:3px; height:2px;
                           border-radius:2px; background:#2271b1; opacity:.5; }
        .rp-day.in-range { background:#eaf3fb; }
        .rp-day.edge { background:#2271b1 !important; color:#fff; font-weight:700; }
        .rp-day.edge .rp-heat { background:#fff; opacity:.75; }
        .rp-foot { display:flex; align-items:center; justify-content:space-between; gap:8px;
                   margin-top:8px; padding-top:8px; border-top:1px solid #f0f0f1; }
        .rp-picked { font-size:11.5px; color:#50575e; }
        .rp-actions { display:flex; gap:6px; }
        .rp-btn { font-size:11.5px; font-weight:600; padding:4px 10px; border:1px solid #c3c4c7;
                  background:#fff; color:#50575e; border-radius:3px; cursor:pointer; }
        .rp-btn.rp-apply { background:#2271b1; border-color:#2271b1; color:#fff; }
        .rp-btn:disabled { opacity:.45; cursor:default; }
        .rp-note { margin-top:6px; font-size:10.5px; color:#8c8f94; text-align:center; }
    </style>

    @php
        $palette = ['#2271b1','#46b450','#dba617','#d63638','#826eb4','#00a0d2','#e1701a','#7ad03a','#888'];
        // A year of days is a chart nobody reads and a question nobody asks in those words.
        // The button that replaced it asks which days instead.
        $rangeLabels = [1=>'Today', 7=>'7 days', 30=>'30 days', 90=>'90 days'];
        // "vs prev. Today" is not a sentence; the day before is what Today is compared with.
        $rangeCompare = [1=>'yesterday'] + $rangeLabels;
        // A custom window is any number of days, so neither label can be looked up any more.
        // Both fall back to saying the number, which reads the same as the presets do.
        $rangeName = $rangeLabels[$range] ?? ($range . ' days');
        $comparedWith = $range == 1 ? 'yesterday' : 'prev. ' . ($rangeCompare[$range] ?? $range . ' days');
        $seriesCaption = ($isCustom ?? false)
            ? \Carbon\Carbon::parse($rangeFrom)->format('M j') . ' – ' . \Carbon\Carbon::parse($rangeTo)->format('M j, Y')
                . ($range == 1 ? ', by the hour' : ', by the day')
            : ($range == 1 ? 'Today, by the hour' : 'Last ' . $rangeName . ', by the day');
    @endphp

    <div class="p-4 sm:p-6 bg-[#f0f0f1] min-h-screen {{ ($analyticsLocked ?? false) ? 'relative overflow-hidden' : '' }}">

        @if($analyticsLocked ?? false)
        {{-- Locked preview: the sample dashboard renders behind a frosted blur with an unlock CTA. --}}
        <div class="absolute inset-0 z-30 flex items-start justify-center pt-16 px-4"
             style="backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); background: rgba(240,240,241,.5);">
            <div class="text-center bg-white rounded-2xl shadow-2xl border border-[#e2e4e7] px-9 py-9 max-w-md">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-[#fbe9cf] text-[#c98a1a] flex items-center justify-center">
                    <span class="material-symbols-outlined" style="font-size:36px">lock</span>
                </div>
                <div class="inline-block mb-3 text-[11px] font-bold uppercase tracking-wider text-[#c98a1a] bg-[#fbe9cf] px-2.5 py-1 rounded-full">Pro feature</div>
                <h2 class="text-[20px] font-bold text-[#1d2327] mb-2">Unlock your Analytics</h2>
                <p class="text-[13.5px] text-[#646970] mb-6 leading-relaxed">See your site's real visitors, a live world map, traffic sources and engagement — in real time. Upgrade to Pro to turn it on.</p>
                <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="inline-block rounded-md bg-[#e8912b] px-6 py-2.5 text-[13.5px] font-bold text-[#171c23] hover:brightness-105 no-underline shadow">Upgrade to Pro</a>
            </div>
        </div>
        @endif
        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
            <div>
                <h1 class="text-[23px] font-normal text-[#1d2327]">Analytics</h1>
                <nav class="text-[13px] text-[#646970]">Home / Analytics</nav>
            </div>
            <div class="flex items-center gap-1.5 relative">
                @foreach($rangeLabels as $r => $lbl)
                    <a href="{{ route('admin.analytics') }}?range={{ $r }}" class="range-btn {{ (!($isCustom ?? false) && $range == $r) ? 'active' : '' }}">{{ $lbl }}</a>
                @endforeach

                @php
                    // Days the site actually has visits for. The calendar greys out the rest,
                    // so a range can only ever be drawn across days that have something in them.
                    $dayCounts = $availableDates ?? [];
                    $dayKeys = array_keys($dayCounts);
                    $firstDay = $dayKeys[0] ?? null;
                    $lastDay = $dayKeys ? end($dayKeys) : null;
                    $busiest = $dayCounts ? max($dayCounts) : 0;
                @endphp

                <button type="button" id="range-custom-btn"
                        class="range-btn {{ ($isCustom ?? false) ? 'active' : '' }} {{ $firstDay ? '' : 'opacity-50 cursor-not-allowed' }}"
                        @if(!$firstDay) disabled title="There are no visits recorded yet" @endif
                        style="display:inline-flex;align-items:center;gap:5px;">
                    <span class="material-symbols-outlined" style="font-size:15px;line-height:1">date_range</span>
                    <span id="range-custom-label">{{ ($isCustom ?? false) ? \Carbon\Carbon::parse($rangeFrom)->format('M j') . ' – ' . \Carbon\Carbon::parse($rangeTo)->format('M j, Y') : 'Custom' }}</span>
                </button>

                @if($firstDay)
                <div id="range-popover" class="rp" hidden
                     data-days='@json($dayCounts)'
                     data-first="{{ $firstDay }}" data-last="{{ $lastDay }}"
                     data-busiest="{{ $busiest }}"
                     data-from="{{ ($isCustom ?? false) ? $rangeFrom : '' }}"
                     data-to="{{ ($isCustom ?? false) ? $rangeTo : '' }}"
                     data-url="{{ route('admin.analytics') }}">
                    <div class="rp-head">
                        <button type="button" class="rp-nav" data-step="-1" aria-label="Previous month">
                            <span class="material-symbols-outlined" style="font-size:18px">chevron_left</span>
                        </button>
                        <div class="rp-month" id="rp-month"></div>
                        <button type="button" class="rp-nav" data-step="1" aria-label="Next month">
                            <span class="material-symbols-outlined" style="font-size:18px">chevron_right</span>
                        </button>
                    </div>
                    <div class="rp-dows">
                        <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                    </div>
                    <div class="rp-grid" id="rp-grid"></div>
                    <div class="rp-foot">
                        <div class="rp-picked" id="rp-picked">Pick a start date</div>
                        <div class="rp-actions">
                            <button type="button" class="rp-btn" id="rp-clear">Clear</button>
                            <button type="button" class="rp-btn rp-apply" id="rp-apply" disabled>Apply</button>
                        </div>
                    </div>
                    <div class="rp-note">Days with no visits cannot be picked.</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Real-Time (auto-updating) -->
        <style>
            @keyframes rtpulse { 0%{transform:scale(.85);opacity:1} 70%{transform:scale(2.4);opacity:0} 100%{opacity:0} }
            .rt-dot{position:relative;display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;margin-right:7px;vertical-align:middle}
            .rt-dot::after{content:'';position:absolute;inset:0;border-radius:50%;background:#22c55e;animation:rtpulse 1.6s ease-out infinite}
            #rt-pages tr, #rt-feed tr { border-bottom:1px solid #f7f7f7; }
            #rt-pages td, #rt-feed td { padding:8px 16px; }
            .rt-table-head th { padding:8px 16px; font-weight:600; }
        </style>
        <div class="classic-card">
            <div class="classic-card-header">
                <span class="classic-card-title"><span class="rt-dot"></span>Real-Time</span>
                <span class="text-[12px] text-[#646970]">auto-updates · updated <span id="rt-time" class="font-semibold text-[#1d2327]">—</span></span>
            </div>

            {{-- Hero strip: active count + 30-min sparkline --}}
            <div class="p-5 flex flex-wrap items-center gap-6 border-b border-[#f0f0f1]">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-[#22c55e]/10 flex items-center justify-center flex-shrink-0">
                        <span class="rt-dot" style="width:14px;height:14px;margin:0"></span>
                    </div>
                    <div>
                        <div class="text-[40px] font-bold text-[#1d2327] leading-none" id="rt-active">{{ number_format($activeNow) }}</div>
                        <div class="text-[12px] text-[#646970] mt-1">Active users right now <span class="text-[#9ca3af]">· last 5 min</span></div>
                    </div>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <div class="text-[11px] text-[#646970] mb-1 text-right">Visitors per minute · last 30 minutes</div>
                    <div style="height:56px"><canvas id="rt-spark"></canvas></div>
                </div>
            </div>

            {{-- Two live tables with clear column headers --}}
            <div class="grid grid-cols-1 lg:grid-cols-2">
                <div class="lg:border-r border-[#f0f0f1]">
                    <div class="px-4 pt-4 pb-1 text-[13px] font-bold text-[#1d2327]">Active Pages <span class="text-[11px] text-[#9ca3af] font-normal">— pages the people here now have read</span></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px] text-left">
                            <thead class="rt-table-head text-[#646970] border-y border-[#f0f0f1] bg-[#fafafa]">
                                <tr><th>Page</th><th class="text-right">Visitors</th></tr>
                            </thead>
                            <tbody id="rt-pages">
                                <tr><td colspan="2" class="text-center text-[#646970]">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <div class="px-4 pt-4 pb-1 text-[13px] font-bold text-[#1d2327]">Live Visitors <span class="text-[11px] text-[#9ca3af] font-normal">— most recent activity</span></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px] text-left">
                            <thead class="rt-table-head text-[#646970] border-y border-[#f0f0f1] bg-[#fafafa]">
                                <tr><th>Location</th><th>Page</th><th>Device</th><th class="text-right">When</th></tr>
                            </thead>
                            <tbody id="rt-feed">
                                <tr><td colspan="4" class="text-center text-[#646970]">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-2">
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#2271b1]"><span class="material-symbols-outlined text-[24px]">visibility</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($totalVisits) }}</div>
                        <div class="classic-stat-label">Visitors</div>
                        <div class="text-[11px] font-semibold mt-0.5 {{ $visitsChange >= 0 ? 'text-[#46b450]' : 'text-[#d63638]' }}">
                            <span class="material-symbols-outlined text-[12px] align-middle">{{ $visitsChange >= 0 ? 'trending_up' : 'trending_down' }}</span>
                            {{ $visitsChange >= 0 ? '+' : '' }}{{ $visitsChange }}% vs {{ $comparedWith }}
                            <span class="text-[#646970] font-normal">· one per address per day</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#46b450]"><span class="material-symbols-outlined text-[24px]">group</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($pageViews) }}</div>
                        <div class="classic-stat-label">Page Views</div>
                        <div class="text-[11px] text-[#646970] mt-0.5">pages opened by those visitors</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#dba617]"><span class="material-symbols-outlined text-[24px]">today</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($today) }}</div>
                        <div class="classic-stat-label">Visitors Today</div>
                        <div class="text-[11px] text-[#646970] mt-0.5">counted once however often they return</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#826eb4]"><span class="material-symbols-outlined text-[24px]">calendar_month</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($thisMonth) }}</div>
                        <div class="classic-stat-label">Visitors This Month</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Engagement KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-2">
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#22c55e]"><span class="material-symbols-outlined text-[24px]">radio_button_checked</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($activeNow) }}</div>
                        <div class="classic-stat-label">Active Now</div>
                        <div class="text-[11px] text-[#646970] mt-0.5">last 5 minutes</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#0ea5e9]"><span class="material-symbols-outlined text-[24px]">timeline</span></div>
                    <div>
                        <div class="classic-stat-value">{{ is_null($sessions) ? '—' : number_format($sessions) }}</div>
                        <div class="classic-stat-label">Sessions</div>
                        <div class="text-[11px] text-[#646970] mt-0.5">{{ is_null($pagesPerSession) ? 'too many to compute' : $pagesPerSession . ' pages / session' }}</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#f97316]"><span class="material-symbols-outlined text-[24px]">call_missed_outgoing</span></div>
                    <div>
                        <div class="classic-stat-value">{{ is_null($bounceRate) ? '—' : $bounceRate . '%' }}</div>
                        <div class="classic-stat-label">Bounce Rate</div>
                        <div class="text-[11px] text-[#646970] mt-0.5">single-page sessions</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitors by Country — dynamic world map -->
        <style>
            #visitor-map svg { width: 100% !important; height: 360px !important; min-height: 360px !important; max-height: none !important; max-width: none !important; min-width: 0 !important; display: block !important; }
            #visitor-map svg path { transition: fill .15s; }
            #visitor-map svg path:hover { fill: #1d4ed8 !important; }
        </style>
        <div class="classic-card">
            <div class="classic-card-header">
                <span class="classic-card-title">Visitors by Country</span>
            </div>
            <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2">
                    <div id="visitor-map" style="height:360px;width:100%;min-height:360px;position:relative"></div>
                </div>
                <div>
                    <div class="text-[12px] font-semibold text-[#646970] uppercase tracking-wide mb-3">Top countries</div>
                    <div class="space-y-2.5">
                        @forelse(collect($visitorsByCountry)->take(8) as $c)
                        <div class="flex items-center justify-between gap-2 {{ !$loop->first ? 'border-t border-[#f0f0f1] pt-2.5' : '' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <img src="https://flagcdn.com/20x15/{{ strtolower($c['code']) }}.png" width="20" height="15" style="border-radius:2px;flex-shrink:0" alt="{{ $c['code'] }}" onerror="this.style.display='none'">
                                <span class="text-[13px] font-medium text-[#1d2327] truncate">{{ $c['name'] }}</span>
                            </div>
                            <div class="text-[13px] font-bold text-[#1d2327] flex-shrink-0">{{ number_format($c["visitors"]) }} <span class="text-[10px] font-normal text-[#646970]">visitors</span></div>
                        </div>
                        @empty
                        <div class="py-6 text-center text-[13px] text-[#646970]">No geo data yet — visitor countries appear once IPs are resolved.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribution donuts: Channels / New vs Returning / Top Countries -->
        @php
            $newRetSet = collect([
                ['label' => 'New', 'count' => (int) $newVisitors],
                ['label' => 'Returning', 'count' => (int) $returningVisitors],
            ])->filter(fn ($r) => $r['count'] > 0)->values();
            $donutSets = [
                ['title' => 'Traffic Channels', 'id' => 'chart-channels',      'set' => collect($channels)->values()],
                ['title' => 'New vs Returning', 'id' => 'chart-new-returning', 'set' => $newRetSet],
                ['title' => 'Top Countries',    'id' => 'chart-countries',     'set' => collect($topCountries)->values()],
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach($donutSets as $d)
                <div class="classic-card">
                    <div class="classic-card-header"><span class="classic-card-title">{{ $d['title'] }}</span></div>
                    <div class="p-4">
                        @if($d['set']->isEmpty())
                            <div class="py-8 text-center text-[13px] text-[#646970]">No data yet.</div>
                        @else
                            <div style="height:200px"><canvas id="{{ $d['id'] }}"></canvas></div>
                            <div class="mt-3 space-y-1.5">
                                @foreach($d['set']->take(6) as $i => $row)
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="flex items-center gap-2 text-[#1d2327]">
                                            <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $palette[$i % count($palette)] }}"></span>
                                            {{ $row['label'] }}
                                        </span>
                                        <span class="font-semibold text-[#646970]">{{ number_format($row['count']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Traffic over time -->
        <div class="classic-card">
            <div class="classic-card-header">
                <span class="classic-card-title">Traffic Overview</span>
                <span class="text-[12px] text-[#646970]">{{ $seriesCaption }}</span>
            </div>
            <div class="p-4" style="height:320px">
                <canvas id="trafficChart"></canvas>
            </div>
        </div>

        <!-- Distributions: Browser / Device / OS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach(['Browsers' => $browsers, 'Devices' => $devices, 'Operating Systems' => $osDist] as $title => $set)
                <div class="classic-card">
                    <div class="classic-card-header"><span class="classic-card-title">{{ $title }}</span></div>
                    <div class="p-4">
                        @if($set->isEmpty())
                            <div class="py-8 text-center text-[13px] text-[#646970]">No data yet.</div>
                        @else
                            <div style="height:200px"><canvas id="chart-{{ Str::slug($title) }}"></canvas></div>
                            <div class="mt-3 space-y-1.5">
                                @foreach($set->take(5) as $i => $row)
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="flex items-center gap-2 text-[#1d2327]">
                                            <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $palette[$i % count($palette)] }}"></span>
                                            {{ $row['label'] }}
                                        </span>
                                        <span class="font-semibold text-[#646970]">{{ number_format($row['count']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Top pages & Top referrers -->
        <!-- Traffic Sources — named sources (Google / Facebook / Instagram / Direct / other sites) -->
        @php $maxSrc = collect($trafficSources)->max('count') ?: 1; $totalSrc = collect($trafficSources)->sum('count') ?: 1; @endphp
        <div class="classic-card">
            <div class="classic-card-header">
                <span class="classic-card-title">Traffic Sources</span>
                <span class="text-[12px] text-[#646970]">where your visitors come from</span>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                @forelse($trafficSources as $s)
                    <div>
                        <div class="flex items-center justify-between mb-1 gap-2">
                            <span class="flex items-center gap-2 text-[12px] text-[#1d2327] min-w-0">
                                @if($s['domain'])
                                    <img src="https://www.google.com/s2/favicons?domain={{ $s['domain'] }}&sz=32" width="16" height="16" style="border-radius:3px;flex-shrink:0" alt="" onerror="this.style.display='none'">
                                @else
                                    <span class="material-symbols-outlined text-[15px] text-[#646970]" style="flex-shrink:0">public</span>
                                @endif
                                <span class="font-medium truncate">{{ $s['label'] }}</span>
                            </span>
                            <span class="text-[12px] text-[#646970] whitespace-nowrap flex-shrink-0">
                                <strong class="text-[#1d2327]">{{ number_format($s['count']) }}</strong>
                                <span class="text-[#9ca3af]">· {{ round($s['count'] / $totalSrc * 100) }}%</span>
                            </span>
                        </div>
                        <div class="h-1.5 bg-[#f0f0f1] rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ round($s['count'] / $maxSrc * 100) }}%;background:#6366f1"></div>
                        </div>
                    </div>
                @empty
                    <div class="md:col-span-2 py-6 text-center text-[13px] text-[#646970]">No referrer data yet — sources appear as visitors arrive from Google, social, or other sites.</div>
                @endforelse
            </div>
        </div>

        @php $maxPage = $topPages->max('count') ?: 1; $maxRef = $topReferrers->max('count') ?: 1; $host = request()->getSchemeAndHttpHost(); @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="classic-card">
                <div class="classic-card-header"><span class="classic-card-title">Top Pages</span></div>
                <div class="p-4 space-y-3">
                    @forelse($topPages as $p)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[12px] text-[#1d2327] truncate max-w-[80%]" title="{{ $p->url }}">{{ falcon_visit_page($p->url) }}</span>
                                <span class="text-[12px] font-bold text-[#1d2327]">{{ number_format($p->count) }}</span>
                            </div>
                            <div class="h-1.5 bg-[#f0f0f1] rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width:{{ round($p->count / $maxPage * 100) }}%;background:#2271b1"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-[13px] text-[#646970]">No page views yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-card-header"><span class="classic-card-title">Top Referrers</span></div>
                <div class="p-4 space-y-3">
                    @forelse($topReferrers as $r)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[12px] text-[#1d2327] truncate max-w-[80%]">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $r->ref), 50) }}</span>
                                <span class="text-[12px] font-bold text-[#1d2327]">{{ number_format($r->count) }}</span>
                            </div>
                            <div class="h-1.5 bg-[#f0f0f1] rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width:{{ round($r->count / $maxRef * 100) }}%;background:#46b450"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-[13px] text-[#646970]">No referrers yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent visits -->
        <div class="classic-card" style="margin-bottom:0">
            <div class="classic-card-header"><span class="classic-card-title">Recent Visits</span></div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px] text-left">
                    <thead class="text-[#646970] border-b border-[#f0f0f1]">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Page</th>
                            <th class="px-4 py-2.5 font-semibold">Device</th>
                            <th class="px-4 py-2.5 font-semibold">Browser</th>
                            <th class="px-4 py-2.5 font-semibold">OS</th>
                            <th class="px-4 py-2.5 font-semibold">IP</th>
                            <th class="px-4 py-2.5 font-semibold text-right">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $v)
                            <tr class="border-b border-[#f6f7f7] hover:bg-[#f6f7f7]">
                                <td class="px-4 py-2.5 text-[#1d2327] truncate max-w-[260px]" title="{{ $v->url }}">{{ falcon_visit_page($v->url) }}</td>
                                <td class="px-4 py-2.5 text-[#646970] capitalize">{{ $v->device_type ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-[#646970]">{{ $v->browser ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-[#646970]">{{ $v->os ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-[#646970] whitespace-nowrap">
                                    @if($v->country_code)
                                        <img src="https://flagcdn.com/20x15/{{ strtolower($v->country_code) }}.png"
                                             alt="{{ $v->country }}" title="{{ $v->country }}{{ $v->city ? ', ' . $v->city : '' }}"
                                             width="20" height="15" loading="lazy"
                                             style="display:inline-block;vertical-align:middle;border-radius:2px;margin-right:6px">
                                    @endif
                                    {{ $v->ip_address }}
                                    @if($v->country)
                                        <span class="text-[#9ca3af] text-[11px]">({{ $v->country }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-[#646970] text-right whitespace-nowrap">{{ $v->created_at ? \Illuminate\Support\Carbon::parse($v->created_at)->diffForHumans() : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-[#646970]">No visits recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/falcon-cms/js/chart.min.js') }}"></script>
    <script>
        const palette = @json($palette);

        // A line needs two points to be a line. With one — which is what an hourly series has
        // just after midnight, and what the whole day range used to have — nothing was drawn
        // and the card looked broken, so a short series shows its markers instead.
        const trafficPoint = data => (data.filter(v => v !== null && v !== undefined).length <= 2 ? 3.5 : 0);
        const trafficViews = @json($visitsSeries), trafficUniques = @json($uniqueSeries);
        const trafficUnit = @json($seriesUnit ?? 'day');

        new Chart(document.getElementById('trafficChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: @json($labels),
                datasets: [
                    { label: 'Page Views', data: trafficViews, borderColor: '#2271b1', backgroundColor: 'rgba(34,113,177,.06)', fill: true, tension: .4, borderWidth: 2, pointRadius: trafficPoint(trafficViews), pointHoverRadius: 4 },
                    { label: 'Unique Visitors', data: trafficUniques, borderColor: '#46b450', backgroundColor: 'transparent', fill: false, tension: .4, borderWidth: 2, borderDash: [5,5], pointRadius: trafficPoint(trafficUniques), pointHoverRadius: 4 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    // Hours still to come are null, and Chart.js would otherwise list them in
                    // the tooltip as empty rows on hover.
                    tooltip: { filter: item => item.parsed.y !== null }
                },
                scales: {
                    // An hourly chart has a fixed 0–23 axis, so a quiet day still reads as a
                    // day rather than resizing itself around one visit.
                    y: { beginAtZero: true, suggestedMax: trafficUnit === 'hour' ? 4 : undefined, grid: { color: '#f0f0f1' }, ticks: { font: { size: 10 }, precision: 0 } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: trafficUnit === 'hour' ? 8 : 12, autoSkip: true } }
                }
            }
        });

        function donut(id, set) {
            const el = document.getElementById(id);
            if (!el || !set.length) return;
            new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: set.map(r => r.label), datasets: [{ data: set.map(r => r.count), backgroundColor: set.map((_, i) => palette[i % palette.length]), borderWidth: 2, borderColor: '#fff' }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } }
            });
        }
        donut('chart-browsers', @json($browsers));
        donut('chart-devices', @json($devices));
        donut('chart-operating-systems', @json($osDist));
        donut('chart-channels', @json(collect($channels)->values()));
        donut('chart-new-returning', @json($newRetSet ?? collect()));
        donut('chart-countries', @json(collect($topCountries)->values()));

        // ── Real-Time polling ───────────────────────────────────────────────
        (function () {
            const rtUrl = "{{ route('admin.analytics.realtime') }}";
            const sparkEl = document.getElementById('rt-spark');
            let spark = null;
            if (sparkEl && window.Chart) {
                spark = new Chart(sparkEl.getContext('2d'), {
                    type: 'bar',
                    data: { labels: Array.from({ length: 30 }, (_, i) => { const m = 29 - i; return m === 0 ? 'just now' : m + ' min ago'; }), datasets: [{ data: Array(30).fill(0), backgroundColor: '#22c55e', hoverBackgroundColor: '#16a34a', borderRadius: 2, barPercentage: .9, categoryPercentage: 1 }] },
                    options: { responsive: true, maintainAspectRatio: false, animation: false, scales: { x: { display: false }, y: { display: false, beginAtZero: true } }, plugins: { legend: { display: false }, tooltip: { enabled: true, displayColors: false, callbacks: { label: ctx => ctx.parsed.y + ' active user' + (ctx.parsed.y === 1 ? '' : 's') } } } }
                });
            }
            const esc = s => (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const flag = code => code ? '<img src="https://flagcdn.com/16x12/' + code + '.png" width="16" height="12" style="display:inline-block;vertical-align:middle;border-radius:2px;margin-right:5px">' : '';
            async function poll() {
                try {
                    const res = await fetch(rtUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) return;
                    const d = await res.json();
                    const a = document.getElementById('rt-active'); if (a) a.textContent = (d.active || 0).toLocaleString();
                    const t = document.getElementById('rt-time'); if (t) t.textContent = d.time || '';
                    if (spark && d.minutes) { spark.data.datasets[0].data = d.minutes; spark.update('none'); }
                    const pages = d.activePages || [];
                    const pe = document.getElementById('rt-pages');
                    if (pe) pe.innerHTML = pages.length
                        ? pages.map(p => '<tr><td class="text-[#1d2327]"><span class="block truncate max-w-[280px]" title="' + esc(p.path) + '">' + esc(p.path) + '</span></td><td class="text-right font-semibold text-[#646970]">' + p.count + '</td></tr>').join('')
                        : '<tr><td colspan="2" class="text-center text-[#646970]">No active visitors right now.</td></tr>';
                    const feed = d.recent || [];
                    const fe = document.getElementById('rt-feed');
                    if (fe) fe.innerHTML = feed.length
                        ? feed.map(f => '<tr><td class="whitespace-nowrap">' + flag(f.code) + '<span class="text-[#646970]">' + esc(f.country || 'Unknown') + '</span></td><td class="text-[#1d2327]"><span class="block truncate max-w-[160px]" title="' + esc(f.path) + '">' + esc(f.path) + '</span></td><td class="text-[#646970] capitalize">' + esc(f.device || '—') + '</td><td class="text-right text-[#9ca3af] whitespace-nowrap">' + esc(f.ago) + '</td></tr>').join('')
                        : '<tr><td colspan="4" class="text-center text-[#646970]">No recent visits.</td></tr>';
                } catch (e) { /* ignore transient network errors */ }
            }
            poll();
            setInterval(poll, 12000);
        })();
    </script>
    <script>
        // Visitors by Country — inline SVG world map (same proven technique as the dashboard map).
        (function () {
            const el = document.getElementById('visitor-map');
            if (!el) return;
            const data = @json(collect($visitorsByCountry)->keyBy('code'));
            const codes = Object.keys(data);
            if (!codes.length) { el.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#646970;font-size:13px">No geo-located visits yet.</div>'; return; }
            const counts = {}; codes.forEach(c => { counts[c.toUpperCase()] = data[c].visitors; });
            const max = Math.max(1, ...codes.map(c => data[c].visitors));
            const shade = n => '#' + 'bfdbfe'.match(/\w\w/g).map((h, i) => {
                const t = max > 1 ? n / max : 1;
                const v = Math.round(parseInt(h, 16) + (parseInt('1d4ed8'.match(/\w\w/g)[i], 16) - parseInt(h, 16)) * t);
                return v.toString(16).padStart(2, '0');
            }).join('');
            const escp = s => (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const ld = s => new Promise((res, rej) => { const x = document.createElement('script'); x.src = s; x.onload = res; x.onerror = () => rej(new Error('load fail')); document.head.appendChild(x); });
            const base = 'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/';
            const fail = () => { el.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#646970;font-size:13px">Map unavailable — see the list.</div>'; };
            let mapData = null;
            ld(base + 'js/jsvectormap.min.js')
                .then(() => {
                    if (window.jsVectorMap && typeof jsVectorMap.addMap === 'function') {
                        const orig = jsVectorMap.addMap;
                        jsVectorMap.addMap = function (name, obj) { if (obj && obj.paths) mapData = obj; return orig.apply(this, arguments); };
                    }
                    return ld(base + 'maps/world.js');
                })
                .then(() => {
                    const md = mapData;
                    if (!md || !md.paths) { fail(); return; }
                    const inset = (md.insets && md.insets[0]) || { width: 900, height: 440 };
                    const W = Math.ceil(inset.width), H = Math.ceil(inset.height);
                    let paths = '';
                    for (const code in md.paths) {
                        const p = md.paths[code];
                        const has = counts[code] != null;
                        const name = (data[code] && data[code].name) || p.name || code;
                        const title = has ? name + ' (' + counts[code] + ' visit' + (counts[code] === 1 ? '' : 's') + ')' : name;
                        paths += '<path d="' + p.path + '" fill="' + (has ? shade(counts[code]) : '#cbd5e1') + '" stroke="#ffffff" stroke-width="0.5"><title>' + escp(title) + '</title></path>';
                    }
                    const sStyle = 'display:block;width:100%!important;height:360px!important;min-height:360px!important;max-height:none!important;max-width:none!important;min-width:0!important;cursor:grab';
                    const bStyle = 'width:26px;height:26px;line-height:1;text-align:center;font-size:16px;font-weight:700;color:#1d2327;background:#fff;border:1px solid #c3c4c7;border-radius:4px;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.08);padding:0';
                    el.innerHTML =
                        '<svg id="vm-svg" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" style="' + sStyle + '">' + paths + '</svg>' +
                        '<div style="position:absolute;top:8px;left:8px;display:flex;flex-direction:column;gap:5px;z-index:5">' +
                        '<button type="button" id="vm-zin" title="Zoom in" style="' + bStyle + '">+</button>' +
                        '<button type="button" id="vm-zout" title="Zoom out" style="' + bStyle + '">&minus;</button></div>';
                    const svg = el.querySelector('#vm-svg');
                    const m0 = { x: 0, y: 0, w: W, h: H }, vb = { x: 0, y: 0, w: W, h: H };
                    const apply = () => svg.setAttribute('viewBox', vb.x + ' ' + vb.y + ' ' + vb.w + ' ' + vb.h);
                    const clamp = () => { vb.w = Math.min(vb.w, m0.w); vb.h = Math.min(vb.h, m0.h); vb.x = Math.max(m0.x, Math.min(vb.x, m0.x + m0.w - vb.w)); vb.y = Math.max(m0.y, Math.min(vb.y, m0.y + m0.h - vb.h)); };
                    const zoomAt = (f, cx, cy) => { let nw = vb.w * f, nh = vb.h * f; if (nw > m0.w) { nw = m0.w; nh = m0.h; } if (nw < m0.w * 0.12) return; vb.x = cx - (cx - vb.x) * (nw / vb.w); vb.y = cy - (cy - vb.y) * (nh / vb.h); vb.w = nw; vb.h = nh; clamp(); apply(); };
                    const ctr = () => ({ x: vb.x + vb.w / 2, y: vb.y + vb.h / 2 });
                    el.querySelector('#vm-zin').onclick = () => { const p = ctr(); zoomAt(0.65, p.x, p.y); };
                    el.querySelector('#vm-zout').onclick = () => { const p = ctr(); zoomAt(1.55, p.x, p.y); };
                    let drag = false, last = null;
                    svg.addEventListener('mousedown', e => { drag = true; last = { x: e.clientX, y: e.clientY }; svg.style.cursor = 'grabbing'; e.preventDefault(); });
                    window.addEventListener('mouseup', () => { if (drag) { drag = false; svg.style.cursor = 'grab'; } });
                    window.addEventListener('mousemove', e => { if (!drag) return; const r = svg.getBoundingClientRect(); vb.x -= (e.clientX - last.x) / r.width * vb.w; vb.y -= (e.clientY - last.y) / r.height * vb.h; last = { x: e.clientX, y: e.clientY }; clamp(); apply(); });
                    svg.addEventListener('wheel', e => { e.preventDefault(); const r = svg.getBoundingClientRect(); const cx = vb.x + (e.clientX - r.left) / r.width * vb.w; const cy = vb.y + (e.clientY - r.top) / r.height * vb.h; zoomAt(e.deltaY < 0 ? 0.85 : 1.18, cx, cy); }, { passive: false });
                })
                .catch(fail);
        })();
    </script>
    @endpush

    {{-- The custom range calendar. Everything it needs came down on the popover as data
         attributes, so it does not fetch anything and works before the charts have loaded. --}}
    @if(!($analyticsLocked ?? false) && ($availableDates ?? []))
    <script>
    (function () {
        var btn = document.getElementById('range-custom-btn');
        var pop = document.getElementById('range-popover');
        if (!btn || !pop) return;

        var days     = JSON.parse(pop.dataset.days || '{}');   // 'Y-m-d' -> visits
        var first    = pop.dataset.first;
        var last     = pop.dataset.last;
        var busiest  = Math.max(1, parseInt(pop.dataset.busiest, 10) || 1);
        var url      = pop.dataset.url;
        var grid     = document.getElementById('rp-grid');
        var monthEl  = document.getElementById('rp-month');
        var pickedEl = document.getElementById('rp-picked');
        var applyBtn = document.getElementById('rp-apply');

        var from = pop.dataset.from || null;
        var to   = pop.dataset.to   || null;
        // The month on show starts wherever the reader last was, or at the most recent day
        // that has anything in it — never on an empty month they have to navigate out of.
        var cursor = ymToDate(from || last);

        function ymToDate(key) { var p = key.split('-'); return new Date(+p[0], +p[1] - 1, 1); }
        function key(y, m, d) {
            return y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        }
        function pretty(k) {
            var p = k.split('-');
            return new Date(+p[0], +p[1] - 1, +p[2])
                .toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function render() {
            var y = cursor.getFullYear(), m = cursor.getMonth();
            monthEl.textContent = cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

            // Navigation stops at the months that hold the first and last day with data;
            // there is nothing to find beyond them.
            pop.querySelector('[data-step="-1"]').disabled = key(y, m, 1) <= first;
            var lastOfMonth = new Date(y, m + 1, 0).getDate();
            pop.querySelector('[data-step="1"]').disabled = key(y, m, lastOfMonth) >= last;

            var html = '';
            for (var blank = 0; blank < new Date(y, m, 1).getDay(); blank++) {
                html += '<button class="rp-day rp-empty" disabled></button>';
            }
            for (var d = 1; d <= lastOfMonth; d++) {
                var k = key(y, m, d);
                var n = days[k] || 0;
                var cls = 'rp-day';
                if (n) {
                    if (k === from || k === to) cls += ' edge';
                    else if (from && to && k > from && k < to) cls += ' in-range';
                }
                html += '<button class="' + cls + '" data-k="' + k + '"' +
                    (n ? ' title="' + n + ' view' + (n === 1 ? '' : 's') + '"' : ' disabled title="No visits"') + '>' +
                    d +
                    (n ? '<span class="rp-heat" style="opacity:' + (0.25 + 0.65 * Math.min(1, n / busiest)).toFixed(2) + '"></span>' : '') +
                    '</button>';
            }
            grid.innerHTML = html;

            pickedEl.textContent = !from ? 'Pick a start date'
                : (!to ? pretty(from) + ' → pick an end date' : pretty(from) + ' – ' + pretty(to));
            applyBtn.disabled = !(from && to);
        }

        grid.addEventListener('click', function (e) {
            var cell = e.target.closest('.rp-day');
            if (!cell || cell.disabled) return;
            var k = cell.dataset.k;

            // First click starts a range, second finishes it, third starts again. Clicking a
            // day before the start is read as meaning that day as the start, not as an error.
            if (!from || (from && to)) { from = k; to = null; }
            else if (k < from) { to = from; from = k; }
            else { to = k; }
            render();
        });

        pop.addEventListener('click', function (e) {
            var nav = e.target.closest('.rp-nav');
            if (nav && !nav.disabled) {
                cursor.setMonth(cursor.getMonth() + (+nav.dataset.step));
                render();
            }
        });

        document.getElementById('rp-clear').addEventListener('click', function () {
            from = to = null;
            render();
        });

        applyBtn.addEventListener('click', function () {
            if (from && to) window.location = url + '?from=' + from + '&to=' + to;
        });

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            pop.hidden = !pop.hidden;
            if (!pop.hidden) render();
        });
        pop.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { pop.hidden = true; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.hidden = true; });
    })();
    </script>
    @endif
</x-falcon-cms::layouts.admin>
