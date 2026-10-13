<x-falcon-cms::layouts.admin>
    <x-slot name="title">Web Vitals - FalconCMS</x-slot>

    @php
        $labels = [
            'lcp' => ['Largest Contentful Paint', 'LCP', 'How long until the main content shows.'],
            'inp' => ['Interaction to Next Paint', 'INP', 'How fast the page answers a tap or click.'],
            'cls' => ['Cumulative Layout Shift', 'CLS', 'How much the page jumps while loading.'],
            'ttfb' => ['Time to First Byte', 'TTFB', 'How long the server takes to answer.'],
        ];
        $colors = ['good' => '#00a32a', 'needs' => '#dba617', 'poor' => '#d63638'];
        $words = ['good' => 'Good', 'needs' => 'Needs improvement', 'poor' => 'Poor'];
        $format = fn ($metric, $v) => $v === null ? '—' : ($metric === 'cls' ? number_format((float) $v, 2) : ($v >= 1000 ? number_format($v / 1000, 1).' s' : $v.' ms'));
        $url = fn (array $q) => route('admin.analytics.vitals', array_filter(array_merge(['range' => $days, 'device' => $device], $q), fn ($v) => $v !== '' && $v !== null));
    @endphp

    <div class="px-2 max-w-[1100px]">
        <div class="flex items-center gap-3 flex-wrap mb-1">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Web Vitals</h1>
            <a href="{{ route('admin.analytics') }}" class="text-[13px] text-[#2271b1] hover:underline">← Analytics</a>
        </div>
        <p class="text-[13px] text-[#646970] mb-4 max-w-[820px]">How fast your pages are for the visitors who actually open them, measured in their browsers. Each figure is the 75th percentile, as Google judges it: three in four visits were at least this fast. Google uses LCP, INP and CLS in ranking.</p>

        @unless($enabled)
            <div class="bg-[#fcf9e8] border-l-4 border-[#dba617] p-3 mb-4 text-[13px] text-[#1d2327]">
                Measuring is off. Turn on <strong>Measure Real Visitor Speed</strong> in
                <a href="{{ route('admin.customizer.index') }}" class="text-[#2271b1] underline">Appearance → Customize → Performance</a>; figures appear as visitors browse.
            </div>
        @endunless

        <div class="flex items-center gap-2 flex-wrap mb-4">
            @foreach([1 => 'Today', 7 => '7 days', 28 => '28 days', 90 => '90 days'] as $r => $label)
                <a href="{{ $url(['range' => $r]) }}" class="px-3 py-1 rounded border text-[13px] {{ $days === $r ? 'bg-[#2271b1] text-white border-[#2271b1]' : 'bg-white border-[#c3c4c7] text-[#2c3338] hover:border-[#2271b1]' }}">{{ $label }}</a>
            @endforeach
            <span class="mx-2 text-[#c3c4c7]">|</span>
            @foreach(['' => 'All devices', 'm' => 'Mobile', 'd' => 'Desktop'] as $d => $label)
                <a href="{{ $url(['device' => $d]) }}" class="px-3 py-1 rounded border text-[13px] {{ $device === $d ? 'bg-[#2271b1] text-white border-[#2271b1]' : 'bg-white border-[#c3c4c7] text-[#2c3338] hover:border-[#2271b1]' }}">{{ $label }}</a>
            @endforeach
            <span class="ml-auto text-[12px] text-[#646970]">{{ number_format($report['samples']) }} page {{ $report['samples'] === 1 ? 'view' : 'views' }} measured</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach($labels as $metric => [$name, $short, $help])
                @php
                    $m = $report['metrics'][$metric];
                    $total = max(1, $m['good'] + $m['needs'] + $m['poor']);
                    $color = $colors[$m['rating']] ?? '#646970';
                @endphp
                <div class="bg-white border border-[#c3c4c7] rounded p-4">
                    <div class="text-[12px] font-semibold text-[#646970] uppercase">{{ $short }}{{ $metric === 'ttfb' ? '' : ' · Core Web Vital' }}</div>
                    <div class="text-[28px] font-bold leading-tight mt-1" style="color: {{ $color }}">{{ $format($metric, $m['p75']) }}</div>
                    <div class="text-[12px] font-semibold mb-2" style="color: {{ $color }}">{{ $m['rating'] ? $words[$m['rating']] : 'No data yet' }}</div>
                    <div class="flex h-2 rounded overflow-hidden bg-[#f0f0f1]" title="Good {{ $m['good'] }} · Needs improvement {{ $m['needs'] }} · Poor {{ $m['poor'] }}">
                        @foreach(['good', 'needs', 'poor'] as $band)
                            <span style="width: {{ round($m[$band] / $total * 100, 1) }}%; background: {{ $colors[$band] }}"></span>
                        @endforeach
                    </div>
                    <div class="text-[11px] text-[#646970] mt-2">{{ $help }} Good is {{ $metric === 'cls' ? '≤ 0.1' : '≤ '.$format($metric, \FalconSeo\Support\WebVitals::THRESHOLDS[$metric][0]) }}.</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white border border-[#c3c4c7] rounded">
            <div class="px-4 py-3 border-b border-[#f0f0f1]">
                <h2 class="text-[14px] font-semibold text-[#1d2327]">Pages, slowest first</h2>
                <p class="text-[12px] text-[#646970]">Pages with at least three measured visits. Fix the red ones first: they are slow for most of their visitors.</p>
            </div>
            @if($report['pages'] === [])
                <div class="p-4 text-[13px] text-[#646970]">Not enough visits measured yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="text-left text-[#1d2327] border-b border-[#f0f0f1]">
                                <th class="px-4 py-2 font-semibold">Page</th>
                                <th class="px-4 py-2 font-semibold text-right">Visits</th>
                                <th class="px-4 py-2 font-semibold text-right">LCP</th>
                                <th class="px-4 py-2 font-semibold text-right">INP</th>
                                <th class="px-4 py-2 font-semibold text-right">CLS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['pages'] as $page)
                                <tr class="border-b border-[#f6f7f7] last:border-0">
                                    <td class="px-4 py-2 max-w-[460px] truncate"><a href="{{ url($page['path']) }}" target="_blank" rel="noopener" class="text-[#2271b1] hover:underline">{{ $page['path'] }}</a></td>
                                    <td class="px-4 py-2 text-right text-[#646970]">{{ number_format($page['samples']) }}</td>
                                    @foreach(['lcp', 'inp', 'cls'] as $metric)
                                        <td class="px-4 py-2 text-right font-semibold whitespace-nowrap" style="color: {{ $colors[$page[$metric.'_rating']] ?? '#646970' }}">{{ $format($metric, $page[$metric]) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <p class="text-[12px] text-[#646970] mt-3">For what slows a page down and how to fix it, run it through the Search Console plugin's Speed tab or <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener" class="text-[#2271b1]">PageSpeed Insights</a>.</p>
    </div>
</x-falcon-cms::layouts.admin>
