{{--
    Editor → SEO panel, Pro: Google's own keyword numbers (DataForSEO) in the Analysis tab.
    Nothing here without Pro. ("Suggest with AI" beside the SEO title and description is the
    Falcon AI plugin's.)
--}}
@php
    $falconSeoDfs = \FalconSeo\Support\DataForSeo::available();
@endphp
@if(\FalconSeo\Support\Pro::active())
<script>
(function () {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const num = n => n === null || n === undefined ? '—' : Number(n).toLocaleString();
    const token = () => document.querySelector('input[name="_token"]')?.value || '';

    window.falconSeoPro = {
        dfs: @json($falconSeoDfs),
        dfsBatch: {{ \FalconSeo\Support\DataForSeo::COUNTRY_BATCH }},
        // Google numbers are billed per country: the first few chosen countries only.
        dfsMax: 10,

        /** Google's numbers for the keyword in the chosen countries, a few countries per request. */
        async metrics(keyword, countries, el) {
            if (!el) return;
            if (!this.dfs) {
                el.innerHTML = '<p class="text-[11px] text-[#646970]">Google\'s own search numbers and difficulty for each country: add a DataForSEO login under SEO → Settings → Keyword data.</p>';
                return;
            }
            const codes = countries.slice(0, this.dfsMax);
            let rows = [];
            const draw = (done) => {
                const sorted = [...rows].sort((a, b) => (b.searches ?? -1) - (a.searches ?? -1));
                const diff = d => d === null ? '—' : `<span style="color:${d < 30 ? '#00a32a' : d < 60 ? '#996800' : '#d63638'}">${d}</span>`;
                el.innerHTML = `<div class="text-[11px] font-bold text-[#2c3338] mb-1">Google (DataForSEO)</div>`
                    + (done ? '' : `<div class="text-[11px] text-[#646970] italic mb-1">Checked ${rows.length} of ${codes.length} countries…</div>`)
                    + (sorted.length ? `<table class="w-full text-[11px]"><tr class="text-[#646970]"><th class="text-left font-normal">Country</th><th class="text-right font-normal">Searches/mo</th><th class="text-right font-normal">CPC</th><th class="text-right font-normal">Competition</th><th class="text-right font-normal" title="0–100: how hard it is to reach page one">Difficulty</th></tr>`
                        + sorted.map(r => `<tr title="${esc(r.error || '')}"><td class="py-0.5">${esc(r.name)}</td>
                            <td class="text-right">${r.error ? '<span class="text-[#b32d2e]">error</span>' : num(r.searches)}</td>
                            <td class="text-right">${r.cpc === null ? '—' : '$' + r.cpc.toFixed(2)}</td>
                            <td class="text-right">${esc(r.competition || '—')}</td><td class="text-right">${diff(r.difficulty)}</td></tr>`).join('') + '</table>' : '')
                    + (countries.length > codes.length ? `<p class="text-[10px] text-[#8c8f94] mt-1">Google numbers for the first ${codes.length} countries (each is a billed DataForSEO request).</p>` : '');
            };
            draw(false);
            for (let i = 0; i < codes.length; i += this.dfsBatch) {
                const body = new FormData();
                body.append('_token', token());
                body.append('q', keyword);
                codes.slice(i, i + this.dfsBatch).forEach(c => body.append('countries[]', c));
                try {
                    const r = await fetch(@json(route('admin.seo.keyword-metrics')), {method: 'POST', body, headers: {'Accept': 'application/json'}});
                    const d = await r.json();
                    rows = rows.concat(r.ok ? (d.rows || []) : codes.slice(i, i + this.dfsBatch).map(c => ({country: c, name: c.toUpperCase(), searches: null, cpc: null, competition: null, difficulty: null, error: d.message || 'failed'})));
                } catch (e) {
                    rows = rows.concat(codes.slice(i, i + this.dfsBatch).map(c => ({country: c, name: c.toUpperCase(), searches: null, cpc: null, competition: null, difficulty: null, error: e.message})));
                }
                draw(i + this.dfsBatch >= codes.length);
            }
        },
    };

})();
</script>
@endif
