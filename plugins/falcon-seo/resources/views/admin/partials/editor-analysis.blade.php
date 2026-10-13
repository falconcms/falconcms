{{-- Editor → SEO panel → Analysis: the page's check-up and score (falcon_seo_metabox_panels). --}}
@php
    $falconSeoCountry = strtolower((string) get_cms_option(\FalconSeo\Support\KeywordDemand::COUNTRY, 'us')) ?: 'us';
    $falconSeoCountries = ['us' => 'United States', 'gb' => 'United Kingdom', 'ca' => 'Canada', 'au' => 'Australia', 'in' => 'India',
        'bd' => 'Bangladesh', 'pk' => 'Pakistan', 'de' => 'Germany', 'fr' => 'France', 'es' => 'Spain', 'it' => 'Italy', 'nl' => 'Netherlands',
        'br' => 'Brazil', 'mx' => 'Mexico', 'ae' => 'United Arab Emirates', 'sa' => 'Saudi Arabia', 'sg' => 'Singapore', 'my' => 'Malaysia',
        'id' => 'Indonesia', 'ph' => 'Philippines', 'ng' => 'Nigeria', 'za' => 'South Africa', 'jp' => 'Japan'];
    if (!isset($falconSeoCountries[$falconSeoCountry])) {
        $falconSeoCountries = [$falconSeoCountry => strtoupper($falconSeoCountry)] + $falconSeoCountries;
    }
    $falconSeoPublished = ($post->status ?? null) === 'published';
@endphp
<div id="seo-tab-analysis" class="seo-tab-content hidden p-4">
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label class="block text-[11px] font-bold text-[#646970] mb-1">Analyse</label>
            <select id="falcon-seo-analysis-mode" class="wp-input h-8 py-0 text-[12px]">
                <option value="live" @selected($falconSeoPublished)>The published page</option>
                <option value="editor" @selected(!$falconSeoPublished)>What is in the editor</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-[#646970] mb-1">Searchers in</label>
            <select id="falcon-seo-analysis-country" class="wp-input h-8 py-0 text-[12px]">
                @foreach($falconSeoCountries as $code => $name)
                    <option value="{{ $code }}" @selected($code === $falconSeoCountry)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-1.5 text-[12px] text-[#2c3338] pb-1.5">
            <input type="checkbox" id="falcon-seo-analysis-links" checked class="rounded-sm border-[#8c8f94]"> Check links
        </label>
        <button type="button" onclick="falconSeoAnalyse()" id="falcon-seo-analysis-btn" class="wp-btn-primary h-8 px-4 text-[12px] font-semibold ml-auto">Analyse page</button>
    </div>
    <div id="falcon-seo-analysis" class="text-[13px] text-gray-500 italic">Press "Analyse page" for a full check-up: headings, images, links, the focus keyword and a score out of 100.</div>
</div>

<script>
(function () {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const num = n => n === null || n === undefined ? '—' : Number(n).toLocaleString();
    const icons = {
        good: '<span class="text-[#00a32a] font-bold" title="Good">✓</span>',
        warning: '<span class="text-[#dba617] font-bold" title="Could be better">!</span>',
        error: '<span class="text-[#d63638] font-bold" title="Problem">✗</span>',
        info: '<span class="text-[#72aee6] font-bold" title="Note">i</span>',
    };
    const gradeColour = {good: '#00a32a', ok: '#dba617', poor: '#d63638'};
    const countryName = code => document.querySelector('#falcon-seo-analysis-country option[value="' + code + '"]')?.textContent || code.toUpperCase();

    function editorContent() {
        if (window.tinymce && tinymce.get('wp-editor') && document.getElementById('editor_type')?.value !== 'builder') {
            return tinymce.get('wp-editor').getContent();
        }
        return document.getElementById('editor_type')?.value !== 'builder' ? (document.querySelector('textarea[name="content"]')?.value || '') : '';
    }

    function ring(score, grade) {
        const c = gradeColour[grade] || '#646970', r = 26, len = 2 * Math.PI * r;
        return `<svg width="68" height="68" viewBox="0 0 68 68" aria-label="Score ${score} out of 100">
            <circle cx="34" cy="34" r="${r}" fill="none" stroke="#f0f0f1" stroke-width="7"/>
            <circle cx="34" cy="34" r="${r}" fill="none" stroke="${c}" stroke-width="7" stroke-linecap="round"
                stroke-dasharray="${len}" stroke-dashoffset="${len * (1 - score / 100)}" transform="rotate(-90 34 34)"/>
            <text x="34" y="39" text-anchor="middle" font-size="17" font-weight="700" fill="${c}">${score}</text></svg>`;
    }

    function renderKeyword(k) {
        if (!k || !k.keyword) {
            return '';
        }
        const anySource = k.sources && (k.sources.planner || k.sources.bing || k.sources.autocomplete);
        let html = `<div class="border border-[#dcdcde] rounded bg-white p-3 mb-4">
            <div class="text-[12px] font-bold text-[#2c3338] mb-2">Keyword: “${esc(k.keyword)}” · ${esc(countryName(k.country))}</div>`;
        if (!anySource) {
            html += `<p class="text-[12px] text-[#646970]">How often it is searched needs a free source: a Bing Webmaster API key or a Keyword Planner import, in Settings → SEO → Keyword research.</p>`;
        } else {
            html += `<div class="flex flex-wrap gap-4 text-[12px] mb-2">
                <span><span class="text-[#646970]">Google (Keyword Planner):</span> <strong>${k.planner ? num(k.planner.searches_low) + '–' + num(k.planner.searches_high) + '/mo' : '—'}</strong>${k.planner && k.planner.competition ? ' · competition ' + esc(k.planner.competition) : ''}</span>
                <span><span class="text-[#646970]">Bing:</span> <strong>${k.bing ? num(k.bing.impressions) + ' searches (30 days)' : '—'}</strong></span>
            </div>`;
            if (k.volume === 0 || (k.volume !== null && k.volume < 50)) {
                html += `<p class="text-[12px] text-[#b32d2e] mb-2">Very few people search for this phrase here. A more common way of saying it may bring more visitors (see below).</p>`;
            }
            if (k.sources.bing) {
                html += `<button type="button" onclick="falconSeoCompareCountries(${esc(JSON.stringify(k.keyword))})" class="text-[11px] text-[#2271b1] hover:underline">Compare countries (Bing)</button>
                    <div id="falcon-seo-countries" class="mt-2"></div>`;
            }
            if ((k.suggestions || []).length) {
                html += `<div class="text-[11px] text-[#646970] mt-2">People also type: ${k.suggestions.slice(0, 8).map(esc).join(' · ')}</div>`;
            }
        }
        (k.errors || []).forEach(e => html += `<p class="text-[11px] text-[#b32d2e] mt-1">${esc(e)}</p>`);
        if ((k.alternatives || []).length) {
            html += `<div class="text-[12px] font-bold text-[#2c3338] mt-3 mb-1">Keywords worth considering</div><div class="space-y-1">` +
                k.alternatives.map(a => `<div class="flex items-center justify-between gap-2 text-[12px] py-1 border-t border-[#f0f0f1]">
                    <div class="min-w-0"><div class="font-medium text-[#1d2327] truncate">${esc(a.phrase)}</div><div class="text-[11px] text-[#646970]">${esc(a.why)}</div></div>
                    <button type="button" data-phrase="${esc(a.phrase)}" onclick="useFocusKeyword(this.dataset.phrase)" class="text-[11px] text-[#2271b1] hover:underline whitespace-nowrap">Use as focus keyword</button>
                </div>`).join('') + `</div>`;
        }
        return html + `</div>`;
    }

    function render(d) {
        let html = `<div class="flex items-center gap-4 mb-4 p-3 border border-[#dcdcde] rounded bg-white">
            ${ring(d.score, d.grade)}
            <div class="text-[12px]">
                <div class="text-[14px] font-bold text-[#1d2327]">${d.grade === 'good' ? 'Good' : (d.grade === 'ok' ? 'Needs some work' : 'Needs work')}</div>
                <div class="text-[#646970]">${num(d.stats.words)} words · ${d.stats.h1} H1 · ${d.stats.h2} H2 · ${d.stats.h3} H3 · ${d.stats.images} images · ${d.stats.internal_links} internal / ${d.stats.external_links} external links</div>
                ${d.mode === 'live' ? `<div class="text-[11px] text-[#646970] mt-0.5">Read from <a href="${esc(d.url)}" target="_blank" rel="noopener" class="text-[#2271b1]">${esc(d.url)}</a></div>` : ''}
            </div>
        </div>`;
        if (d.notice) {
            html += `<p class="text-[12px] text-[#646970] mb-3">${esc(d.notice)}</p>`;
        }
        html += renderKeyword(d.keyword);
        d.groups.forEach(g => {
            const problems = g.checks.filter(c => c.status === 'error' || c.status === 'warning').length;
            html += `<details class="mb-2 border border-[#dcdcde] rounded bg-white" ${problems ? 'open' : ''}>
                <summary class="cursor-pointer px-3 py-2 text-[12px] font-bold text-[#2c3338] flex justify-between">
                    <span>${esc(g.label)}</span><span class="font-normal ${problems ? 'text-[#b32d2e]' : 'text-[#00a32a]'}">${problems ? problems + ' to fix' : 'All good'}</span>
                </summary><div class="px-3 pb-2">` +
                g.checks.map(c => `<div class="flex gap-2 py-1.5 border-t border-[#f0f0f1] text-[12px]">
                    <div class="w-4 text-center">${icons[c.status] || ''}</div>
                    <div class="min-w-0 flex-1"><div class="text-[#1d2327]">${esc(c.label)}</div>
                        ${c.detail ? `<div class="text-[11px] text-[#646970] break-words">${esc(c.detail)}</div>` : ''}
                        ${(c.items || []).length ? `<ul class="text-[11px] text-[#50575e] list-disc pl-4 mt-1 break-all">${c.items.map(i => `<li>${esc(i)}</li>`).join('')}</ul>` : ''}
                    </div></div>`).join('') + `</div></details>`;
        });
        return html;
    }

    window.falconSeoAnalyse = function () {
        const box = document.getElementById('falcon-seo-analysis');
        const btn = document.getElementById('falcon-seo-analysis-btn');
        const body = new FormData();
        body.append('_token', document.querySelector('input[name="_token"]')?.value || '');
        body.append('post_id', "{{ $post->id ?? 0 }}");
        body.append('mode', document.getElementById('falcon-seo-analysis-mode').value);
        body.append('country', document.getElementById('falcon-seo-analysis-country').value);
        body.append('check_links', document.getElementById('falcon-seo-analysis-links').checked ? '1' : '0');
        body.append('keyword', document.getElementById('seo-focus-keyword')?.value || '');
        body.append('title', document.querySelector('input[name="title"]')?.value || '');
        body.append('seo_title', document.getElementById('seo-title-input')?.value || '');
        body.append('description', document.getElementById('seo-desc-input')?.value || '');
        body.append('slug', document.querySelector('input[name="slug"]')?.value || "{{ $post->slug ?? '' }}");
        body.append('content', editorContent());

        btn.disabled = true;
        box.className = 'text-[13px] text-gray-500 italic';
        box.textContent = 'Analysing… (checking links can take a few seconds)';
        fetch(@json(route('admin.seo.page-analysis')), {method: 'POST', body, headers: {'Accept': 'application/json'}})
            .then(r => r.ok ? r.json() : Promise.reject(new Error('The analysis failed (' + r.status + ').')))
            .then(d => { box.className = ''; box.innerHTML = render(d); })
            .catch(e => { box.className = 'text-[13px] text-[#b32d2e]'; box.textContent = e.message; })
            .finally(() => { btn.disabled = false; });
    };

    // How the keyword is searched across countries (Bing; cached a week per country).
    window.falconSeoCompareCountries = function (keyword) {
        const out = document.getElementById('falcon-seo-countries');
        const codes = Array.from(document.querySelectorAll('#falcon-seo-analysis-country option')).map(o => o.value).slice(0, 12);
        out.innerHTML = '<div class="text-[11px] text-[#646970] italic">Asking Bing…</div>';
        const token = document.querySelector('input[name="_token"]')?.value || '';
        Promise.all(codes.map(code => {
            const body = new FormData();
            body.append('_token', token);
            body.append('q', keyword);
            body.append('country', code);
            return fetch(@json(route('admin.seo.keyword-demand')), {method: 'POST', body, headers: {'Accept': 'application/json'}})
                .then(r => r.json()).then(d => ({code, n: d.bing ? d.bing.impressions : null})).catch(() => ({code, n: null}));
        })).then(rows => {
            rows.sort((a, b) => (b.n ?? -1) - (a.n ?? -1));
            const max = Math.max(1, ...rows.map(r => r.n || 0));
            out.innerHTML = '<table class="w-full text-[11px]">' + rows.map(r => `<tr>
                <td class="py-0.5 pr-2 whitespace-nowrap">${esc(countryName(r.code))}</td>
                <td class="w-full"><div class="h-2 rounded bg-[#2271b1]" style="width:${Math.round((r.n || 0) / max * 100)}%"></div></td>
                <td class="pl-2 text-right whitespace-nowrap">${r.n === null ? '—' : num(r.n)}</td></tr>`).join('') + '</table>';
        });
    };

    document.addEventListener('falcon-seo-tab', function (e) {
        if (e.detail.tab === 'analysis' && document.getElementById('falcon-seo-analysis').classList.contains('italic')) {
            falconSeoAnalyse();
        }
    });
})();
</script>
