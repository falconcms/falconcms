<x-falcon-cms::layouts.admin>
    <x-slot name="title">Site Health - FalconCMS</x-slot>

<style>
    .sh-item summary::-webkit-details-marker { display: none; }
    .sh-item[open] .sh-chevron { transform: rotate(180deg); }
    .sh-badge-Security { color: #b32d2e; border-color: #f0b6b6; }
    .sh-badge-Performance { color: #2271b1; border-color: #b6d0ea; }
    .sh-badge-Database { color: #6b4fbb; border-color: #cfc3ee; }
    .sh-badge-Site { color: #50575e; border-color: #c3c4c7; }
    .sh-badge-Plugins { color: #996800; border-color: #f0d9a8; }
    .sh-ring { transition: stroke-dashoffset .8s ease; }
    .sh-sev-critical { background: #fcf0f1; color: #b32d2e; }
    .sh-sev-high { background: #fcf0f1; color: #b32d2e; }
    .sh-sev-medium { background: #fcf9e8; color: #8a6d00; }
    .sh-sev-low { background: #f0f0f1; color: #50575e; }
    .sh-table td, .sh-table th { padding: 8px 10px; border-bottom: 1px solid #f0f0f1; vertical-align: top; text-align: left; }
    .sh-table th { font-weight: 600; color: #1d2327; background: #f6f7f7; }
    .sh-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; }
</style>

<div class="px-2">
    <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Settings</h1>
    @include('falcon-cms::admin.settings.nav')

    {{-- ── Header: overall score ── --}}
    <div class="bg-white border border-[#c3c4c7] rounded-sm shadow-sm mb-6">
        <div class="flex flex-col md:flex-row items-center gap-6 px-6 py-6">
            <div class="relative w-[110px] h-[110px] shrink-0">
                <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="#f0f0f1" stroke-width="12"/>
                    <circle id="sh-ring" class="sh-ring" cx="60" cy="60" r="52" fill="none" stroke="#c3c4c7" stroke-width="12"
                            stroke-linecap="round" stroke-dasharray="326.7" stroke-dashoffset="326.7"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span id="sh-score" class="text-[26px] font-bold text-[#1d2327]">…</span>
                    <span class="text-[10px] uppercase tracking-wider text-[#646970]">health</span>
                </div>
            </div>
            <div class="flex-1 text-center md:text-left">
                <h2 class="text-[20px] font-semibold text-[#1d2327]">Site Health</h2>
                <p id="sh-verdict" class="text-[14px] text-[#646970] mt-1">Running tests…</p>
                <div class="flex flex-wrap justify-center md:justify-start gap-2 mt-3 text-[12px]">
                    <span class="px-2.5 py-1 rounded-full bg-[#fcf0f1] text-[#b32d2e] font-semibold"><span id="sh-count-critical">0</span> critical</span>
                    <span class="px-2.5 py-1 rounded-full bg-[#fcf9e8] text-[#8a6d00] font-semibold"><span id="sh-count-recommended">0</span> recommended</span>
                    <span class="px-2.5 py-1 rounded-full bg-[#edfaef] text-[#00a32a] font-semibold"><span id="sh-count-good">0</span> passed</span>
                    <span id="sh-running" class="px-2.5 py-1 rounded-full bg-[#f0f6fb] text-[#2271b1] font-semibold">
                        <span class="inline-block w-3 h-3 border-2 border-[#2271b1] border-t-transparent rounded-full animate-spin align-[-2px] mr-1"></span>
                        <span id="sh-running-label">running deeper tests</span>
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-2 shrink-0">
                <button type="button" onclick="shRerun()" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 border border-[#2271b1] text-[#2271b1] hover:bg-[#f0f6fb] text-[13px] font-semibold rounded">
                    <span class="material-symbols-outlined text-[16px]">refresh</span> Run again
                </button>
            </div>
        </div>
        <div class="flex border-t border-[#c3c4c7] px-4">
            <button type="button" data-sh-tab="status" class="sh-tab px-5 py-3 text-[13px] font-semibold border-b-2 -mb-px">Status</button>
            <button type="button" data-sh-tab="info" class="sh-tab px-5 py-3 text-[13px] font-semibold border-b-2 -mb-px">Info</button>
        </div>
    </div>

    {{-- ── Status ── --}}
    <div id="sh-panel-status" class="max-w-[960px]">
        <p class="text-[13px] text-[#646970] mb-5">The site health check looks at the server, the database, the configuration, the security of the setup and of the site's own code, and the error log. Every test only reads — nothing is changed.</p>
        @foreach(['critical' => ['Critical issues', 'Fix these first — they put the site, its data or its visitors at risk.'], 'recommended' => ['Recommended improvements', 'Not urgent, but the site will be safer or faster with them.'], 'good' => ['Passed tests', 'Everything here is fine.']] as $key => [$title, $sub])
        <section class="mb-8" id="sh-section-{{ $key }}">
            <h3 class="text-[16px] font-semibold text-[#1d2327]">{{ $title }} <span class="text-[#646970] font-normal">(<span data-sh-count="{{ $key }}">0</span>)</span></h3>
            <p class="text-[12.5px] text-[#646970] mb-3">{{ $sub }}</p>
            <div class="bg-white border border-[#c3c4c7] rounded-sm divide-y divide-[#f0f0f1] {{ $key === 'good' ? 'sh-collapsible' : '' }}" data-sh-list="{{ $key }}">
                <div class="sh-empty px-4 py-3 text-[13px] text-[#646970] italic">None.</div>
            </div>
        </section>
        @endforeach
    </div>

    {{-- ── Info ── --}}
    <div id="sh-panel-info" class="max-w-[960px]" style="display:none">
        <div class="flex items-center justify-between mb-4">
            <p class="text-[13px] text-[#646970]">Everything about how this site is set up. Copy it into a support request so nobody has to ask.</p>
            <button type="button" onclick="shCopyInfo(this)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded shrink-0">
                <span class="material-symbols-outlined text-[16px]">content_copy</span> <span>Copy site info</span>
            </button>
        </div>
        @foreach($info as $section => $rows)
        <details class="sh-item bg-white border border-[#c3c4c7] rounded-sm mb-3" {{ $loop->index < 4 ? 'open' : '' }}>
            <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none">
                <span class="text-[14px] font-semibold text-[#1d2327]">{{ $section }} <span class="text-[#646970] font-normal">({{ count($rows) }})</span></span>
                <span class="material-symbols-outlined sh-chevron text-[#646970] transition-transform">expand_more</span>
            </summary>
            <table class="sh-table w-full text-[13px] border-t border-[#f0f0f1]">
                @foreach($rows as $label => $value)
                <tr>
                    <th class="w-[260px]" scope="row">{{ $label }}</th>
                    <td class="{{ in_array($section, ['Composer packages', 'Directories'], true) || $label === 'Extensions' ? 'sh-code' : '' }}">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</td>
                </tr>
                @endforeach
            </table>
        </details>
        @endforeach
    </div>
</div>

<script>
(function () {
    const CHECKS = @json($checks);
    const ASYNC = @json($asyncTests);
    const INFO = @json($info);
    const TEST_URL = @json(route('admin.settings.site-health.test', ['test' => '__T__']));
    const results = {};   // id => result

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const statusIcon = { critical: ['error', '#d63638'], recommended: ['warning', '#dba617'], good: ['check_circle', '#00a32a'] };

    function detailsHtml(d) {
        if (!d) return '';
        let h = '';
        if (d.files && d.files.length) {
            h += '<ul class="mt-2 space-y-1">' + d.files.map(f => '<li class="sh-code text-[#1d2327]">' + esc(f) + '</li>').join('') + '</ul>';
        }
        if (d.advisories && d.advisories.length) {
            h += '<div class="overflow-x-auto mt-3"><table class="sh-table w-full text-[12.5px] border border-[#f0f0f1]"><tr><th>Package</th><th>Installed</th><th>Advisory</th><th>Severity</th></tr>'
                + d.advisories.map(a => '<tr><td class="sh-code" style="white-space:nowrap">' + esc(a.package) + '</td><td class="sh-code">' + esc(a.installed) + '</td><td>'
                    + (a.link ? '<a class="text-[#2271b1] hover:underline" target="_blank" rel="noopener" href="' + esc(a.link) + '">' + esc(a.title) + '</a>' : esc(a.title))
                    + (a.cve ? ' <span class="sh-code text-[#646970]">' + esc(a.cve) + '</span>' : '')
                    + '<div class="text-[11px] text-[#646970] sh-code">affected: ' + esc(a.affected) + '</div></td>'
                    + '<td><span class="px-1.5 py-0.5 rounded text-[11px] font-semibold sh-sev-' + esc((a.severity || 'medium').toLowerCase()) + '">' + esc(a.severity || 'unknown') + '</span></td></tr>').join('')
                + '</table></div>';
        }
        if (d.findings && d.findings.length) {
            h += '<div class="overflow-x-auto mt-3"><table class="sh-table w-full text-[12.5px] border border-[#f0f0f1]"><tr><th style="width:90px">Severity</th><th>Finding</th><th>Where</th></tr>'
                + d.findings.map(f => '<tr><td><span class="px-1.5 py-0.5 rounded text-[11px] font-semibold uppercase sh-sev-' + esc(f.severity) + '">' + esc(f.severity) + '</span></td>'
                    + '<td><div class="font-semibold text-[#1d2327]">' + esc(f.title) + '</div><div class="text-[#646970] text-[12px]">' + esc(f.why) + '</div>'
                    + '<pre class="sh-code bg-[#f6f7f7] border border-[#f0f0f1] rounded px-2 py-1 mt-1 whitespace-pre-wrap">' + esc(f.code) + '</pre></td>'
                    + '<td class="sh-code text-[#2271b1]">' + esc(f.file) + ':' + esc(f.line) + '</td></tr>').join('')
                + '</table></div>' + (d.truncated ? '<p class="text-[12px] text-[#646970] mt-2">Showing the first 300.</p>' : '');
        }
        if (d.errors && d.errors.length) {
            h += '<div class="overflow-x-auto mt-3"><table class="sh-table w-full text-[12.5px] border border-[#f0f0f1]"><tr><th style="width:80px">Level</th><th>Message</th><th style="width:70px">Times</th><th style="width:150px">Last seen</th></tr>'
                + d.errors.map(e => '<tr' + (e.resolved ? ' style="opacity:.55"' : '') + '><td><span class="px-1.5 py-0.5 rounded text-[11px] font-semibold ' + (e.resolved ? 'bg-[#edfaef] text-[#00a32a]' : (e.level === 'WARNING' ? 'sh-sev-medium' : 'sh-sev-critical')) + '"' + (e.resolved ? ' title="The file behind it changed after it last happened"' : '') + '>' + (e.resolved ? 'FIXED' : esc(e.level)) + '</span></td>'
                    + '<td><div class="sh-code text-[#1d2327]">' + esc(e.message) + '</div>' + (e.where ? '<div class="sh-code text-[#2271b1] mt-1">' + esc(e.where) + '</div>' : '') + '</td>'
                    + '<td class="text-center font-semibold">' + esc(e.count) + '</td><td class="text-[#646970]">' + esc(e.last) + '</td></tr>').join('')
                + '</table></div>';
        }
        return h;
    }

    function itemHtml(r) {
        const [icon, color] = statusIcon[r.status] || statusIcon.recommended;
        return '<details class="sh-item" data-sh-id="' + esc(r.id) + '"' + (r.status === 'critical' ? ' open' : '') + '>'
            + '<summary class="flex items-center gap-3 px-4 py-3 cursor-pointer select-none hover:bg-[#f6f7f7]">'
            + '<span class="material-symbols-outlined text-[20px]" style="color:' + color + '">' + icon + '</span>'
            + '<span class="flex-1 text-[14px] text-[#1d2327]">' + esc(r.label) + '</span>'
            + '<span class="px-2 py-0.5 border rounded text-[11px] font-semibold sh-badge-' + esc(r.category) + '">' + esc(r.category) + '</span>'
            + '<span class="material-symbols-outlined sh-chevron text-[#646970] transition-transform">expand_more</span></summary>'
            + '<div class="px-4 pb-4 pl-[52px] text-[13px] text-[#3c434a] leading-relaxed">'
            + '<p>' + esc(r.description) + '</p>'
            + (r.action ? '<p class="mt-2"><strong class="text-[#1d2327]">What to do:</strong> ' + esc(r.action) + '</p>' : '')
            + detailsHtml(r.details) + '</div></details>';
    }

    function render() {
        const all = Object.values(results);
        ['critical', 'recommended', 'good'].forEach(s => {
            const list = document.querySelector('[data-sh-list="' + s + '"]');
            const items = all.filter(r => r.status === s);
            list.innerHTML = items.length ? items.map(itemHtml).join('') : '<div class="px-4 py-3 text-[13px] text-[#646970] italic">None.</div>';
            document.querySelector('[data-sh-count="' + s + '"]').textContent = items.length;
            document.getElementById('sh-count-' + s).textContent = items.length;
        });
        // Score: passed tests out of all, with a critical issue weighing three times as much.
        const c = all.filter(r => r.status === 'critical').length, w = all.filter(r => r.status === 'recommended').length, g = all.filter(r => r.status === 'good').length;
        const score = all.length ? Math.round(100 * g / (g + w + 3 * c)) : 0;
        const ring = document.getElementById('sh-ring');
        ring.style.strokeDashoffset = 326.7 * (1 - score / 100);
        ring.style.stroke = c ? '#d63638' : (score >= 80 ? '#00a32a' : '#dba617');
        document.getElementById('sh-score').textContent = score + '%';
        document.getElementById('sh-verdict').textContent = c
            ? 'Your site has critical issues that should be fixed as soon as possible.'
            : (w ? 'Your site is in good shape, with a few things that can be improved.' : 'Great — every test passed.');
    }

    async function runAsync() {
        const running = document.getElementById('sh-running');
        running.style.display = '';
        for (const [test, label] of Object.entries(ASYNC)) {
            document.getElementById('sh-running-label').textContent = label + '…';
            try {
                const res = await fetch(TEST_URL.replace('__T__', test), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                (data.results || []).forEach(r => { results[r.id] = r; });
            } catch (e) {
                results[test] = { id: test, label: 'Could not run: ' + label, status: 'recommended', category: 'Site', description: String(e) };
            }
            render();
        }
        running.style.display = 'none';
    }

    window.shRerun = function () {
        Object.keys(results).forEach(k => delete results[k]);
        CHECKS.forEach(r => { results[r.id] = r; });
        render();
        runAsync();
    };

    window.shCopyInfo = function (btn) {
        let text = '### FalconCMS Site Health — ' + new Date().toISOString() + '\n';
        for (const [section, rows] of Object.entries(INFO)) {
            text += '\n## ' + section + '\n';
            for (const [k, v] of Object.entries(rows)) text += k + ': ' + v + '\n';
        }
        const all = Object.values(results).filter(r => r.status !== 'good');
        if (all.length) {
            text += '\n## Issues\n' + all.map(r => '[' + r.status + '] ' + r.label).join('\n') + '\n';
        }
        const done = () => { const s = btn.querySelector('span:last-child'); s.textContent = 'Copied!'; setTimeout(() => { s.textContent = 'Copy site info'; }, 1800); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
        const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        t.remove();
    };

    function tab(name) {
        document.querySelectorAll('.sh-tab').forEach(b => {
            const on = b.dataset.shTab === name;
            b.style.borderColor = on ? '#2271b1' : 'transparent';
            b.style.color = on ? '#2271b1' : '#50575e';
        });
        document.getElementById('sh-panel-status').style.display = name === 'status' ? '' : 'none';
        document.getElementById('sh-panel-info').style.display = name === 'info' ? '' : 'none';
        try { history.replaceState(null, '', name === 'info' ? '#info' : location.pathname); } catch (e) {}
    }
    document.querySelectorAll('.sh-tab').forEach(b => b.addEventListener('click', () => tab(b.dataset.shTab)));
    tab(location.hash === '#info' ? 'info' : 'status');

    window.shRerun();
})();
</script>
</x-falcon-cms::layouts.admin>
