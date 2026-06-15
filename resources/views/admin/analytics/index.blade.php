<x-cms-dashboard::layouts.admin>
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
    </style>

    @php
        $palette = ['#2271b1','#46b450','#dba617','#d63638','#826eb4','#00a0d2','#e1701a','#7ad03a','#888'];
        $rangeLabels = [7=>'7 days', 30=>'30 days', 90=>'90 days', 365=>'1 year'];
    @endphp

    <div class="p-4 sm:p-6 bg-[#f0f0f1] min-h-screen">
        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
            <div>
                <h1 class="text-[23px] font-normal text-[#1d2327]">Analytics</h1>
                <nav class="text-[13px] text-[#646970]">Home / Analytics</nav>
            </div>
            <div class="flex items-center gap-1.5">
                @foreach($rangeLabels as $r => $lbl)
                    <a href="{{ route('admin.analytics') }}?range={{ $r }}" class="range-btn {{ $range == $r ? 'active' : '' }}">{{ $lbl }}</a>
                @endforeach
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-2">
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#2271b1]"><span class="material-symbols-outlined text-[24px]">visibility</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($totalVisits) }}</div>
                        <div class="classic-stat-label">Total Visits</div>
                        <div class="text-[11px] font-semibold mt-0.5 {{ $visitsChange >= 0 ? 'text-[#46b450]' : 'text-[#d63638]' }}">
                            <span class="material-symbols-outlined text-[12px] align-middle">{{ $visitsChange >= 0 ? 'trending_up' : 'trending_down' }}</span>
                            {{ $visitsChange >= 0 ? '+' : '' }}{{ $visitsChange }}% vs prev. {{ $rangeLabels[$range] }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#46b450]"><span class="material-symbols-outlined text-[24px]">group</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($uniqueVisitors) }}</div>
                        <div class="classic-stat-label">Unique Visitors</div>
                        <div class="text-[11px] text-[#646970] mt-0.5">by IP address</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#dba617]"><span class="material-symbols-outlined text-[24px]">today</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($today) }}</div>
                        <div class="classic-stat-label">Visits Today</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#826eb4]"><span class="material-symbols-outlined text-[24px]">calendar_month</span></div>
                    <div>
                        <div class="classic-stat-value">{{ number_format($thisMonth) }}</div>
                        <div class="classic-stat-label">This Month</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Traffic over time -->
        <div class="classic-card">
            <div class="classic-card-header">
                <span class="classic-card-title">Traffic Overview</span>
                <span class="text-[12px] text-[#646970]">Last {{ $rangeLabels[$range] }}</span>
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
        @php $maxPage = $topPages->max('count') ?: 1; $maxRef = $topReferrers->max('count') ?: 1; $host = request()->getSchemeAndHttpHost(); @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="classic-card">
                <div class="classic-card-header"><span class="classic-card-title">Top Pages</span></div>
                <div class="p-4 space-y-3">
                    @forelse($topPages as $p)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[12px] text-[#1d2327] truncate max-w-[80%]" title="{{ $p->url }}">{{ \Illuminate\Support\Str::after($p->url, $host) ?: $p->url }}</span>
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
                                <td class="px-4 py-2.5 text-[#1d2327] truncate max-w-[260px]" title="{{ $v->url }}">{{ \Illuminate\Support\Str::after($v->url, $host) ?: $v->url }}</td>
                                <td class="px-4 py-2.5 text-[#646970] capitalize">{{ $v->device_type ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-[#646970]">{{ $v->browser ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-[#646970]">{{ $v->os ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-[#646970]">{{ $v->ip_address }}</td>
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
    <script src="{{ asset('vendor/cms-dashboard/js/chart.min.js') }}"></script>
    <script>
        const palette = @json($palette);

        new Chart(document.getElementById('trafficChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: @json($labels),
                datasets: [
                    { label: 'Visits', data: @json($visitsSeries), borderColor: '#2271b1', backgroundColor: 'rgba(34,113,177,.06)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                    { label: 'Unique Visitors', data: @json($uniqueSeries), borderColor: '#46b450', backgroundColor: 'transparent', fill: false, tension: .4, borderWidth: 2, borderDash: [5,5], pointRadius: 0, pointHoverRadius: 4 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f0f1' }, ticks: { font: { size: 10 }, precision: 0 } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 12, autoSkip: true } }
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
    </script>
    @endpush
</x-cms-dashboard::layouts.admin>
