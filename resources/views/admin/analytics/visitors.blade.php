<x-falcon-cms::layouts.admin active-menu="analytics">
    <x-slot name="title">Visitor Log - FalconCMS</x-slot>
    <style>
        .vl-card { background:#fff; border:1px solid #c3c4c7; box-shadow:0 1px 1px rgba(0,0,0,.04); border-radius:2px; }
        .range-btn { font-size:12px; font-weight:600; padding:5px 12px; border:1px solid #c3c4c7; background:#fff; color:#50575e; border-radius:3px; text-decoration:none; display:inline-block; }
        .range-btn.active { background:#2271b1; border-color:#2271b1; color:#fff; }
        .vl-input { border:1px solid #c3c4c7; border-radius:3px; font-size:13px; padding:6px 10px; background:#fff; color:#1d2327; }
        .vl-input:focus { outline:none; border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; }
        .vl-table { width:100%; border-collapse:collapse; font-size:12.5px; }
        .vl-table th { text-align:left; font-weight:600; color:#646970; padding:9px 14px; border-bottom:1px solid #f0f0f1; white-space:nowrap; background:#f9fafb; }
        .vl-table td { padding:9px 14px; border-bottom:1px solid #f6f7f7; color:#50575e; vertical-align:top; }
        .vl-table tr:hover td { background:#f6f7f7; }
        .vl-ip { font-family:ui-monospace,Menlo,Consolas,monospace; color:#1d2327; }
    </style>

    @php
        $rangeLabels = [1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'];
        // Carry the active filters onto the range links, so switching range keeps the country
        // and search a visitor set, not resets them.
        $carry = array_filter(['country' => $country, 'q' => $q]);
        $rangeUrl = fn ($r) => route('admin.analytics.visitors').'?'.http_build_query(['range' => $r] + $carry);
        $exportUrl = route('admin.analytics.visitors').'?'.http_build_query(
            array_filter(($isCustom ? ['from' => $rangeFrom, 'to' => $rangeTo] : ['range' => $range]) + $carry) + ['export' => 'csv']
        );
        $windowLabel = $isCustom
            ? \Carbon\Carbon::parse($rangeFrom)->format('M j') . ' – ' . \Carbon\Carbon::parse($rangeTo)->format('M j, Y')
            : ($rangeLabels[$range] ?? $range . ' days');
    @endphp

    <div class="p-4 sm:p-6 bg-[#f0f0f1] min-h-screen">
        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
            <div>
                <h1 class="text-[23px] font-normal text-[#1d2327]">Visitor Log</h1>
                <nav class="text-[13px] text-[#646970]">
                    <a href="{{ route('admin.analytics') }}" class="text-[#2271b1] hover:underline no-underline">Analytics</a> / Visitor Log
                </nav>
            </div>
            <div class="flex items-center gap-1.5 relative">
                @foreach($rangeLabels as $r => $lbl)
                    <a href="{{ $rangeUrl($r) }}" class="range-btn {{ (!$isCustom && $range == $r) ? 'active' : '' }}">{{ $lbl }}</a>
                @endforeach

                <button type="button" id="vl-custom-btn" class="range-btn {{ $isCustom ? 'active' : '' }}" style="display:inline-flex;align-items:center;gap:5px;cursor:pointer">
                    <span class="material-symbols-outlined" style="font-size:15px;line-height:1">date_range</span>
                    <span>{{ $isCustom ? $windowLabel : 'Custom' }}</span>
                </button>

                {{-- A plain from/to date panel — the controller already reads ?from=&to=. --}}
                <div id="vl-custom-panel" hidden
                     style="position:absolute;top:calc(100% + 8px);right:0;z-index:50;background:#fff;border:1px solid #c3c4c7;border-radius:6px;box-shadow:0 8px 28px rgba(0,0,0,.14);padding:12px;width:230px">
                    <label class="block text-[11px] font-bold text-[#646970] uppercase mb-1">From</label>
                    <input type="date" id="vl-from" max="{{ now()->timezone(cms_timezone())->toDateString() }}" value="{{ $isCustom ? $rangeFrom : '' }}" class="vl-input w-full mb-2">
                    <label class="block text-[11px] font-bold text-[#646970] uppercase mb-1">To</label>
                    <input type="date" id="vl-to" max="{{ now()->timezone(cms_timezone())->toDateString() }}" value="{{ $isCustom ? $rangeTo : '' }}" class="vl-input w-full mb-3">
                    <div class="flex justify-end gap-2">
                        <button type="button" id="vl-custom-cancel" class="range-btn" style="cursor:pointer">Cancel</button>
                        <button type="button" id="vl-custom-apply" class="range-btn active" style="cursor:pointer">Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter bar -->
        <form method="GET" action="{{ route('admin.analytics.visitors') }}" class="vl-card p-3 mb-4 flex flex-wrap items-center gap-2">
            @if($isCustom)
                <input type="hidden" name="from" value="{{ $rangeFrom }}">
                <input type="hidden" name="to" value="{{ $rangeTo }}">
            @else
                <input type="hidden" name="range" value="{{ $range }}">
            @endif

            <input type="text" name="q" value="{{ $q }}" placeholder="Search IP, page or referrer…" class="vl-input flex-1 min-w-[200px]">

            <select name="country" class="vl-input">
                <option value="">All countries</option>
                @foreach($countries as $c)
                    <option value="{{ $c['code'] }}" {{ $country === $c['code'] ? 'selected' : '' }}>{{ $c['name'] }}</option>
                @endforeach
            </select>

            <button type="submit" class="range-btn active" style="cursor:pointer">Filter</button>
            @if($q !== '' || $country !== '')
                @php $clearUrl = route('admin.analytics.visitors').'?'.http_build_query($isCustom ? ['from' => $rangeFrom, 'to' => $rangeTo] : ['range' => $range]); @endphp
                <a href="{{ $clearUrl }}" class="range-btn">Clear</a>
            @endif

            <span class="ml-auto text-[13px] text-[#646970]">
                <strong class="text-[#1d2327]">{{ number_format($total) }}</strong> visit{{ $total === 1 ? '' : 's' }} · {{ $windowLabel }}
            </span>
            <a href="{{ $exportUrl }}" class="range-btn" style="display:inline-flex;align-items:center;gap:5px;">
                <span class="material-symbols-outlined" style="font-size:15px;line-height:1">download</span> Export CSV
            </a>
        </form>

        <!-- Table -->
        <div class="vl-card overflow-x-auto">
            <table class="vl-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>IP Address</th>
                        <th>Country / City</th>
                        <th>Page</th>
                        <th>Referrer</th>
                        <th>Device</th>
                        <th>Browser</th>
                        <th>OS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $v)
                        <tr>
                            <td class="whitespace-nowrap text-[#1d2327]">
                                {{ $v->created_at ? \Illuminate\Support\Carbon::parse($v->created_at)->timezone(cms_timezone())->format('M j, Y') : '—' }}
                                <span class="text-[#9ca3af]">{{ $v->created_at ? \Illuminate\Support\Carbon::parse($v->created_at)->timezone(cms_timezone())->format('g:i A') : '' }}</span>
                            </td>
                            <td class="vl-ip whitespace-nowrap">{{ $v->ip_address ?: '—' }}</td>
                            <td class="whitespace-nowrap">
                                @if($v->country_code)
                                    <img src="https://flagcdn.com/20x15/{{ strtolower($v->country_code) }}.png"
                                         alt="{{ $v->country }}" width="20" height="15" loading="lazy"
                                         style="display:inline-block;vertical-align:middle;border-radius:2px;margin-right:6px">
                                @endif
                                @if($v->country)
                                    {{ $v->country }}@if($v->city)<span class="text-[#9ca3af]">, {{ $v->city }}</span>@endif
                                @else
                                    <span class="text-[#9ca3af]">Unknown</span>
                                @endif
                            </td>
                            <td class="text-[#1d2327]"><span class="block truncate max-w-[220px]" title="{{ $v->url }}">{{ falcon_visit_page($v->url) }}</span></td>
                            <td><span class="block truncate max-w-[160px]" title="{{ $v->referrer }}">{{ $v->referrer ? \Illuminate\Support\Str::of($v->referrer)->after('://')->before('/') : 'Direct' }}</span></td>
                            <td class="capitalize">{{ $v->device_type ?: '—' }}</td>
                            <td>{{ $v->browser ?: '—' }}</td>
                            <td>{{ $v->os ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-[#646970]" style="padding:40px">No visits match this range or filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($visits->hasPages())
            <div class="mt-4">{{ $visits->links() }}</div>
        @endif
    </div>

    <script>
    (function () {
        const btn = document.getElementById('vl-custom-btn');
        const panel = document.getElementById('vl-custom-panel');
        const from = document.getElementById('vl-from');
        const to = document.getElementById('vl-to');
        const apply = document.getElementById('vl-custom-apply');
        const cancel = document.getElementById('vl-custom-cancel');
        if (!btn) return;

        const toggle = (show) => { panel.hidden = (show === undefined) ? !panel.hidden : !show; };
        btn.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
        cancel.addEventListener('click', () => toggle(false));
        panel.addEventListener('click', (e) => e.stopPropagation());
        document.addEventListener('click', () => toggle(false));

        // Carry the current country + search so changing the range keeps the filter.
        const carried = {!! json_encode(array_filter(['country' => $country, 'q' => $q])) !!};
        apply.addEventListener('click', () => {
            if (!from.value || !to.value) { alert('Pick both a start and an end date.'); return; }
            const params = new URLSearchParams(carried);
            params.set('from', from.value);
            params.set('to', to.value);
            window.location = '{{ route('admin.analytics.visitors') }}?' + params.toString();
        });
    })();
    </script>
</x-falcon-cms::layouts.admin>
