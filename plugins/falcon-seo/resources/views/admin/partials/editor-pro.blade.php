{{--
    Editor → SEO panel, Pro: "Suggest with AI" beside the SEO title and meta description, and
    Google's own keyword numbers (DataForSEO) in the Analysis tab. Nothing here without Pro.
--}}
@php
    $falconSeoAi = \FalconSeo\Support\AiWriter::available();
    $falconSeoDfs = \FalconSeo\Support\DataForSeo::available();
@endphp
@if(\FalconSeo\Support\Pro::active())
<script>
(function () {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const num = n => n === null || n === undefined ? '—' : Number(n).toLocaleString();
    const token = () => document.querySelector('input[name="_token"]')?.value || '';
    const editorContent = () => {
        if (window.tinymce && tinymce.get('wp-editor') && document.getElementById('editor_type')?.value !== 'builder') return tinymce.get('wp-editor').getContent();
        return document.getElementById('editor_type')?.value !== 'builder' ? (document.querySelector('textarea[name="content"]')?.value || '') : '';
    };

    window.falconSeoPro = {
        ai: @json($falconSeoAi),
        dfs: @json($falconSeoDfs),
        dfsBatch: {{ \FalconSeo\Support\DataForSeo::COUNTRY_BATCH }},
        // Google numbers are billed per country: the first few chosen countries only.
        dfsMax: 10,

        /** Ask Claude for options and show them under the field; a click fills it. */
        suggest(field) {
            const input = document.getElementById(field === 'title' ? 'seo-title-input' : 'seo-desc-input');
            let box = document.getElementById('falcon-seo-ai-' + field);
            if (!box) {
                box = document.createElement('div');
                box.id = 'falcon-seo-ai-' + field;
                box.className = 'mt-2';
                input.parentNode.insertBefore(box, input.nextSibling);
            }
            if (!this.ai) {
                box.innerHTML = '<p class="text-[12px] text-[#b32d2e]">Add a Claude API key under SEO → Settings → AI writing first.</p>';
                return;
            }
            box.innerHTML = '<p class="text-[12px] text-[#646970] italic">Claude is writing…</p>';
            const body = new FormData();
            body.append('_token', token());
            body.append('field', field);
            body.append('post_id', "{{ $post->id ?? 0 }}");
            body.append('title', document.querySelector('input[name="title"]')?.value || '');
            body.append('keyword', document.getElementById('seo-focus-keyword')?.value || '');
            body.append('current', input.value || '');
            body.append('content', editorContent());
            fetch(@json(route('admin.seo.ai-suggest')), {method: 'POST', body, headers: {'Accept': 'application/json'}})
                .then(r => r.json().then(d => r.ok ? d : Promise.reject(new Error(d.message || 'The suggestion failed.'))))
                .then(d => {
                    box.innerHTML = '<div class="border border-[#dcdcde] rounded bg-[#f6f7f7] p-2 space-y-1">'
                        + (d.options || []).map((o, i) => `<button type="button" data-i="${i}" class="block w-full text-left p-2 rounded bg-white border border-transparent hover:border-[#2271b1]">
                            <span class="block text-[13px] text-[#1d2327]">${esc(o.text)}</span>
                            <span class="block text-[11px] text-[#646970]">${o.text.length} characters · ${esc(o.why)}</span></button>`).join('')
                        + '<p class="text-[11px] text-[#646970] px-1">Click one to use it, then save the page.</p></div>';
                    box.querySelectorAll('button[data-i]').forEach(b => b.addEventListener('click', () => {
                        input.value = d.options[b.dataset.i].text;
                        input.dispatchEvent(new Event('input', {bubbles: true}));
                        box.innerHTML = '';
                    }));
                })
                .catch(e => { box.innerHTML = `<p class="text-[12px] text-[#b32d2e]">${esc(e.message)}</p>`; });
        },

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

    // "Suggest with AI" beside the SEO title and meta description.
    document.addEventListener('DOMContentLoaded', function () {
        ['title', 'desc'].forEach(key => {
            const counter = document.getElementById('seo-' + key + '-count');
            if (!counter || document.getElementById('falcon-seo-ai-btn-' + key)) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'falcon-seo-ai-btn-' + key;
            btn.className = 'text-[11px] text-[#2271b1] hover:underline mr-3';
            btn.textContent = '✨ Suggest with AI';
            btn.addEventListener('click', () => window.falconSeoPro.suggest(key === 'title' ? 'title' : 'description'));
            counter.parentNode.insertBefore(btn, counter);
        });
    });
})();
</script>
@endif
