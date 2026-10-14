<x-falcon-cms::layouts.admin>
    <x-slot name="title">Rank Tracker - FalconCMS</x-slot>

    @php
        $countries = \FalconSeo\Support\Countries::ALL;
        $popular = \FalconSeo\Support\Countries::POPULAR;
        $features = \FalconSeo\Support\RankTracker::FEATURES;
        $posTone = fn ($p) => $p === null ? 'text-[#8c8f94]' : ($p <= 3 ? 'text-[#00731a]' : ($p <= 10 ? 'text-[#2271b1]' : ($p <= 20 ? 'text-[#8a6100]' : 'text-[#b32d2e]')));
        $kdTone = fn ($d) => $d === null ? 'text-[#8c8f94]' : ($d < 30 ? 'text-[#00731a]' : ($d < 60 ? 'text-[#8a6100]' : 'text-[#b32d2e]'));
        $countrySelect = function (string $name, string $selected) use ($countries, $popular) {
            $html = '<select name="'.$name.'" class="wp-input h-[32px] text-[13px]">';
            $html .= '<optgroup label="Popular">';
            foreach ($popular as $code) {
                $html .= '<option value="'.$code.'"'.($code === $selected ? ' selected' : '').'>'.e($countries[$code] ?? strtoupper($code)).'</option>';
            }
            $html .= '</optgroup><optgroup label="All countries">';
            foreach ($countries as $code => $label) {
                $html .= '<option value="'.$code.'"'.(!in_array($selected, $popular, true) && $code === $selected ? ' selected' : '').'>'.e($label).'</option>';
            }

            return $html.'</optgroup></select>';
        };
        // A small line of the last checks: lower is better, so position 1 is at the top.
        $spark = function (array $history) {
            $points = array_slice($history, -12);
            if (count(array_filter($points, fn ($p) => $p !== null)) < 2) {
                return '';
            }
            $w = 84; $h = 22; $n = count($points);
            $path = '';
            foreach ($points as $i => $p) {
                $x = $n > 1 ? round($i * $w / ($n - 1), 1) : 0;
                $y = $p === null ? $h : round(min(100, $p) / 100 * $h, 1);
                $path .= ($i ? ' L' : 'M').$x.' '.$y;
            }

            return '<svg width="'.$w.'" height="'.$h.'" viewBox="0 -1 '.$w.' '.($h + 2).'" class="inline-block align-middle"><path d="'.$path.'" fill="none" stroke="#2271b1" stroke-width="1.5"/></svg>';
        };
    @endphp

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Rank Tracker</h1>
        @include('falcon-cms::admin.seo.nav')

        <div class="max-w-[1200px]">
        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-4 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">{{ $errors->first() }}</div>
        @endif

        @if(!$available)
            <div class="wp-metabox">
                <div class="wp-metabox-content p-6 text-[13px] text-[#2c3338] space-y-3">
                    <p class="text-[15px] font-semibold">Where does the site rank in Google, keyword by keyword?</p>
                    <p>The Rank Tracker looks your keywords up in Google every week or every day, in the countries you choose, on desktop or mobile: the position, the page that ranks, how it moved, and what else the results show (featured snippet, People also ask, videos). Its Competitor gap lists the keywords a competitor ranks for and you do not.</p>
                    @if(!$pro)
                        <p>It is part of <strong>FalconCMS Pro</strong>, with your own <a href="https://dataforseo.com" target="_blank" rel="noopener" class="text-[#2271b1]">DataForSEO</a> account (pay as you go: a check costs a fraction of a cent).</p>
                    @else
                        <p>Add your DataForSEO login and API password in <a href="{{ route('admin.settings.seo') }}#pro" class="text-[#2271b1]">SEO → Settings</a> to start. DataForSEO is pay as you go: a check costs a fraction of a cent.</p>
                    @endif
                </div>
            </div>
        @else
            @if($ownDomain === '')
                <div class="bg-[#fcf9e8] border-l-4 border-[#dba617] p-3 mb-4 text-[13px] text-[#1d2327]">{{ \FalconSeo\Support\DataForSeo::NO_DOMAIN }}</div>
            @endif
            {{-- Tabs --}}
            <div class="flex items-center gap-1 border-b border-[#c3c4c7] mb-4 text-[13px]">
                @foreach(['tracked' => 'Tracked keywords', 'gap' => 'Competitor gap'] as $key => $label)
                    <a href="{{ route('admin.seo.rank-tracker', ['tab' => $key === 'tracked' ? null : $key]) }}"
                       class="px-4 py-2 -mb-px border-b-2 font-semibold {{ $tab === $key ? 'border-[#2271b1] text-[#2271b1]' : 'border-transparent text-[#50575e] hover:text-[#2271b1]' }}">{{ $label }}</a>
                @endforeach
            </div>

            @if($tab === 'tracked')
                {{-- Summary --}}
                @if($summary['total'])
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
                        @foreach([['Tracked', $summary['total'], '#50575e'], ['Top 3', $summary['top3'], '#00a32a'], ['Top 10', $summary['top10'], '#2271b1'],
                                  ['Top 100', $summary['top100'], '#8a6100'], ['Moved up', $summary['up'], '#00a32a'], ['Moved down', $summary['down'], '#d63638']] as [$label, $value, $color])
                            <div class="wp-metabox !mb-0"><div class="wp-metabox-content px-4 py-3" style="border-top:3px solid {{ $color }}">
                                <div class="text-[11px] font-semibold uppercase text-[#646970]">{{ $label }}</div>
                                <div class="text-[22px] font-bold text-[#1d2327]">{{ $value }}</div>
                            </div></div>
                        @endforeach
                    </div>
                @endif

                {{-- Add keywords --}}
                <div class="wp-metabox mb-4">
                    <div class="wp-metabox-content p-4">
                        <form method="POST" action="{{ route('admin.seo.rank-tracker.store') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1 min-w-[260px]">
                                <label class="block text-[12px] font-semibold text-[#50575e] mb-1">Keywords to track (one per line, up to {{ \FalconSeo\Support\RankTracker::MAX_ADD }} at a time)</label>
                                <textarea name="keywords" rows="2" class="wp-input w-full text-[13px]" placeholder="laravel cms&#10;best page builder" required>{{ old('keywords') }}</textarea>
                            </div>
                            <div>
                                <label class="block text-[12px] font-semibold text-[#50575e] mb-1">Country</label>
                                {!! $countrySelect('country', $country) !!}
                            </div>
                            <div>
                                <label class="block text-[12px] font-semibold text-[#50575e] mb-1">Device</label>
                                <select name="device" class="wp-input h-[32px] text-[13px]">
                                    @foreach(\FalconSeo\Support\RankTracker::DEVICES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="wp-btn-primary h-[32px] px-4">Track</button>
                        </form>
                        <div class="flex flex-wrap items-center gap-3 mt-3 pt-3 border-t border-[#f0f0f1] text-[12px] text-[#646970]">
                            <form method="POST" action="{{ route('admin.seo.rank-tracker.settings') }}" class="flex items-center gap-2">
                                @csrf
                                <span>Check each keyword</span>
                                <select name="frequency" class="wp-input h-[28px] text-[12px]" onchange="this.form.submit()">
                                    @foreach(\FalconSeo\Support\RankTracker::FREQUENCIES as $value => $label)
                                        <option value="{{ $value }}" @selected($frequency === $value)>{{ strtolower($label) }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <span>Each check is one DataForSEO request (a fraction of a cent). Checks run in the background, a few an hour.</span>
                            @if($rows)
                                <form method="POST" action="{{ route('admin.seo.rank-tracker.check') }}" class="ml-auto">@csrf
                                    <button class="wp-btn-secondary h-[28px] px-3 text-[12px]">Check due keywords now</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- The list --}}
                <div class="wp-metabox">
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="bg-[#f6f7f7] text-[#50575e] text-left border-b border-[#e0e0e0]">
                                    <th class="px-3 py-2 font-semibold">Keyword</th>
                                    <th class="px-3 py-2 font-semibold text-right">Position</th>
                                    <th class="px-3 py-2 font-semibold text-right">Change</th>
                                    <th class="px-3 py-2 font-semibold text-right">Best</th>
                                    <th class="px-3 py-2 font-semibold">Trend</th>
                                    <th class="px-3 py-2 font-semibold text-right" title="Google's monthly searches in that country">Searches</th>
                                    <th class="px-3 py-2 font-semibold text-right" title="Keyword difficulty, 0–100: how hard the top 10 is to reach">KD</th>
                                    <th class="px-3 py-2 font-semibold">Ranking page · results also show</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7] align-top">
                                        <td class="px-3 py-2">
                                            <div class="font-semibold text-[#1d2327]">{{ $row['keyword'] }}</div>
                                            <div class="text-[11px] text-[#646970]">{{ \FalconSeo\Support\Countries::name($row['country']) }} · {{ \FalconSeo\Support\RankTracker::DEVICES[$row['device']] ?? $row['device'] }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right font-bold text-[15px] {{ $posTone($row['position']) }}">
                                            @if($row['checked_at'] === null)
                                                <span class="text-[12px] font-normal text-[#8c8f94]">waiting</span>
                                            @elseif($row['error'])
                                                <span class="text-[12px] font-normal text-[#b32d2e]" title="{{ $row['error'] }}">failed</span>
                                            @else
                                                {{ $row['position'] ?? '100+' }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            @if($row['entered'])
                                                <span class="text-[#00731a] text-[12px] font-semibold">new</span>
                                            @elseif($row['lost'])
                                                <span class="text-[#b32d2e] text-[12px] font-semibold">lost</span>
                                            @elseif($row['change'])
                                                <span class="font-semibold {{ $row['change'] > 0 ? 'text-[#00731a]' : 'text-[#b32d2e]' }}">{{ $row['change'] > 0 ? '▲' : '▼' }} {{ abs($row['change']) }}</span>
                                            @else
                                                <span class="text-[#c3c4c7]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right text-[#50575e]">{{ $row['best'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{!! $spark($row['history']) !!}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['searches'] === null ? '—' : number_format($row['searches']) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold {{ $kdTone($row['difficulty']) }}">{{ $row['difficulty'] ?? '—' }}</td>
                                        <td class="px-3 py-2 max-w-[360px]">
                                            @if($row['url'])
                                                <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="text-[#2271b1] text-[12px] break-all">{{ parse_url($row['url'], PHP_URL_PATH) ?: '/' }}</a>
                                            @elseif($row['checked_at'] && !$row['error'])
                                                <span class="text-[12px] text-[#646970]">Not in the top 100.@if($row['leader']) First: {{ $row['leader'] }}@endif</span>
                                            @endif
                                            @if($row['features'])
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($row['features'] as $feature)
                                                        @php $owned = $feature === 'featured_snippet' && $row['snippet_owned']; @endphp
                                                        <span class="rounded px-1.5 py-[1px] text-[11px] {{ $owned ? 'bg-[#edfaef] text-[#00731a] font-semibold' : 'bg-[#f0f0f1] text-[#50575e]' }}"
                                                              title="{{ $owned ? 'The featured snippet is yours' : 'The results show this' }}">{{ $owned ? '★ ' : '' }}{{ $features[$feature] ?? ucfirst(str_replace('_', ' ', $feature)) }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            <form method="POST" action="{{ route('admin.seo.rank-tracker.check') }}" class="inline">@csrf
                                                <input type="hidden" name="id" value="{{ $row['id'] }}">
                                                <button class="text-[12px] text-[#2271b1] hover:underline" title="Look it up in Google now">Check</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.seo.rank-tracker.destroy', $row['id']) }}" class="inline ml-2" onsubmit="return confirm('Stop tracking “{{ e($row['keyword']) }}”? Its history goes too.')">@csrf @method('DELETE')
                                                <button class="text-[12px] text-[#b32d2e] hover:underline">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="px-3 py-8 text-center text-[#646970]">No keywords yet. Add the searches you want to rank for, and the country where your visitors are.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                {{-- Competitor gap --}}
                <div class="wp-metabox mb-4">
                    <div class="wp-metabox-content p-4">
                        <form method="POST" action="{{ route('admin.seo.rank-tracker.gap') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1 min-w-[240px]">
                                <label class="block text-[12px] font-semibold text-[#50575e] mb-1">Competitor's domain</label>
                                <input type="text" name="competitor" value="{{ $competitor }}" class="wp-input w-full h-[32px] text-[13px]" placeholder="competitor.com" required>
                            </div>
                            <div>
                                <label class="block text-[12px] font-semibold text-[#50575e] mb-1">Country</label>
                                {!! $countrySelect('country', $country) !!}
                            </div>
                            <button class="wp-btn-primary h-[32px] px-4">Find the gap</button>
                        </form>
                        <p class="text-[12px] text-[#646970] mt-2">Keywords the competitor ranks for in Google and {{ $ownDomain ?: 'this site' }} does not, most searched first. One DataForSEO request; the answer is kept for a week.</p>
                    </div>
                </div>

                @if($gap !== null)
                    <div class="wp-metabox">
                        <div class="wp-metabox-header px-4 py-2 text-[13px] font-semibold">{{ count($gap) }} keyword(s) {{ $competitor }} ranks for and you do not · {{ \FalconSeo\Support\Countries::name($country) }}</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="bg-[#f6f7f7] text-[#50575e] text-left border-b border-[#e0e0e0]">
                                        <th class="px-3 py-2 font-semibold">Keyword</th>
                                        <th class="px-3 py-2 font-semibold text-right">Searches</th>
                                        <th class="px-3 py-2 font-semibold text-right">KD</th>
                                        <th class="px-3 py-2 font-semibold text-right">Their position</th>
                                        <th class="px-3 py-2 font-semibold">Their page</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($gap as $item)
                                        <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7]">
                                            <td class="px-3 py-2 font-semibold text-[#1d2327]">{{ $item['keyword'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ $item['searches'] === null ? '—' : number_format($item['searches']) }}</td>
                                            <td class="px-3 py-2 text-right font-semibold {{ $kdTone($item['difficulty']) }}">{{ $item['difficulty'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right {{ $posTone($item['position']) }}">{{ $item['position'] ?? '—' }}</td>
                                            <td class="px-3 py-2 max-w-[340px]">
                                                @if($item['url'])<a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="text-[#2271b1] text-[12px] break-all">{{ parse_url($item['url'], PHP_URL_PATH) ?: '/' }}</a>@endif
                                            </td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                                @if(isset($tracked[$item['keyword'].'|'.$country]))
                                                    <span class="text-[12px] text-[#00731a]">✓ Tracked</span>
                                                @else
                                                    <form method="POST" action="{{ route('admin.seo.rank-tracker.store') }}" class="inline">@csrf
                                                        <input type="hidden" name="keywords" value="{{ $item['keyword'] }}">
                                                        <input type="hidden" name="country" value="{{ $country }}">
                                                        <button class="text-[12px] text-[#2271b1] hover:underline">Track</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-3 py-8 text-center text-[#646970]">No gap found: you rank for what {{ $competitor }} ranks for here, or DataForSEO has no data for it yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($competitor !== '')
                    <p class="text-[13px] text-[#646970]">Press “Find the gap” to look it up.</p>
                @endif
            @endif
        @endif
        </div>
    </div>
</x-falcon-cms::layouts.admin>
