{{-- Editor → SEO panel → Analysis: the page's check-up and score (falcon_seo_metabox_panels). --}}
@php
    $falconSeoCountry = strtolower((string) get_cms_option(\FalconSeo\Support\KeywordDemand::COUNTRY, 'us')) ?: 'us';
    $falconSeoCountry = \FalconSeo\Support\Countries::valid($falconSeoCountry) ? $falconSeoCountry : 'us';
    $falconSeoBing = (bool) \FalconSeo\Support\KeywordDemand::sources()['bing'];
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
        {{-- Searchers in: any number of countries, all of them included; the first chosen is the main market. --}}
        <div class="relative" id="falcon-seo-countries-picker">
            <label class="block text-[11px] font-bold text-[#646970] mb-1">Searchers in</label>
            <button type="button" onclick="falconSeoCountriesToggle()" class="wp-input h-8 py-0 px-2 text-[12px] min-w-[190px] max-w-[280px] text-left flex items-center justify-between gap-2 bg-white">
                <span id="falcon-seo-countries-label" class="truncate"></span><span class="text-[#646970]">▾</span>
            </button>
            <div id="falcon-seo-countries-panel" class="hidden absolute z-50 mt-1 w-[300px] bg-white border border-[#c3c4c7] rounded shadow-lg">
                <div class="p-2 border-b border-[#f0f0f1]">
                    <input type="text" id="falcon-seo-countries-search" class="wp-input w-full h-7 text-[12px]" placeholder="Search {{ count(\FalconSeo\Support\Countries::ALL) }} countries…" oninput="falconSeoCountriesFilter(this.value)">
                    <div class="flex items-center gap-3 mt-1.5 text-[11px]">
                        <button type="button" onclick="falconSeoCountriesAll()" class="text-[#2271b1] hover:underline font-semibold">Select all</button>
                        <button type="button" onclick="falconSeoCountriesPopular()" class="text-[#2271b1] hover:underline">Popular</button>
                        <button type="button" onclick="falconSeoCountriesClear()" class="text-[#2271b1] hover:underline">Clear</button>
                        <span class="ml-auto text-[#646970]" id="falcon-seo-countries-count"></span>
                    </div>
                    <p class="text-[10px] text-[#8c8f94] mt-1">The first one ticked is the main market.</p>
                    {{-- Shown once more countries are ticked than one request covers. --}}
                    <p id="falcon-seo-countries-note" class="hidden mt-2 p-2 rounded bg-[#fcf9e8] border border-[#dba617] text-[11px] text-[#1d2327] leading-snug">
                        Each country is one request to Bing the first time a keyword is checked, so this one keyword costs up to <strong id="falcon-seo-countries-calls"></strong> requests.
                        Bing limits how much its API can be used (it does not say how much). If the limit is reached, the countries not yet checked are marked and can be checked later.
                        Answers are kept for a week: checking the same keyword again costs nothing.
                    </p>
                </div>
                <div class="max-h-[260px] overflow-y-auto py-1 text-[12px]" id="falcon-seo-countries-list">
                    @foreach(array_merge(\FalconSeo\Support\Countries::POPULAR, array_diff(array_keys(\FalconSeo\Support\Countries::ALL), \FalconSeo\Support\Countries::POPULAR)) as $i => $code)
                        @if($i === count(\FalconSeo\Support\Countries::POPULAR))
                            <div class="border-t border-[#f0f0f1] my-1" data-divider></div>
                        @endif
                        <label class="flex items-center gap-2 px-3 py-1 hover:bg-[#f6f7f7] cursor-pointer" data-name="{{ strtolower(\FalconSeo\Support\Countries::name($code)) }} {{ $code }}">
                            <input type="checkbox" value="{{ $code }}" class="rounded-sm border-[#8c8f94]" onchange="falconSeoCountriesPick(this)">
                            <span>{{ \FalconSeo\Support\Countries::name($code) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
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
    const countryName = code => document.querySelector('#falcon-seo-countries-list input[value="' + code + '"]')?.nextElementSibling?.textContent || code.toUpperCase();
    const batchSize = {{ \FalconSeo\Support\KeywordDemand::COUNTRY_BATCH }};
    const popular = @json(\FalconSeo\Support\Countries::POPULAR);
    const allCodes = () => Array.from(document.querySelectorAll('#falcon-seo-countries-list input')).map(i => i.value);
    const storeKey = 'falconSeoCountries';

    // The chosen countries, in the order they were ticked (the first is the main market).
    let chosen = [];
    try { chosen = JSON.parse(localStorage.getItem(storeKey) || '[]').filter(c => typeof c === 'string'); } catch (e) { chosen = []; }
    if (!chosen.length) chosen = [@json($falconSeoCountry)];

    function syncCountries() {
        chosen = chosen.filter(c => document.querySelector('#falcon-seo-countries-list input[value="' + c + '"]'));
        if (!chosen.length) chosen = [@json($falconSeoCountry)];
        document.querySelectorAll('#falcon-seo-countries-list input').forEach(i => { i.checked = chosen.includes(i.value); });
        const label = document.getElementById('falcon-seo-countries-label');
        const total = allCodes().length;
        label.textContent = chosen.length === total ? 'All ' + total + ' countries (' + countryName(chosen[0]) + ' first)'
            : countryName(chosen[0]) + (chosen.length > 1 ? ' +' + (chosen.length - 1) : '');
        document.getElementById('falcon-seo-countries-count').textContent = chosen.length + ' of ' + total;
        const note = document.getElementById('falcon-seo-countries-note');
        note.classList.toggle('hidden', chosen.length <= batchSize);
        document.getElementById('falcon-seo-countries-calls').textContent = chosen.length;
        label.title = chosen.map(countryName).join(', ');
        try { localStorage.setItem(storeKey, JSON.stringify(chosen)); } catch (e) {}
    }
    window.falconSeoCountriesToggle = function () {
        const panel = document.getElementById('falcon-seo-countries-panel');
        panel.classList.toggle('hidden');
        if (!panel.classList.contains('hidden')) document.getElementById('falcon-seo-countries-search').focus();
    };
    window.falconSeoCountriesPick = function (box) {
        if (box.checked) {
            chosen.push(box.value);
        } else {
            chosen = chosen.filter(c => c !== box.value);
        }
        syncCountries();
    };
    window.falconSeoCountriesClear = function () { chosen = []; syncCountries(); };
    // The main market stays first; the rest follow in list order.
    window.falconSeoCountriesAll = function () {
        chosen = [...new Set([...chosen.slice(0, 1), ...allCodes()])];
        syncCountries();
    };
    window.falconSeoCountriesPopular = function () {
        chosen = [...new Set([...chosen.slice(0, 1), ...popular])];
        syncCountries();
    };
    window.falconSeoCountriesFilter = function (q) {
        q = q.trim().toLowerCase();
        document.querySelectorAll('#falcon-seo-countries-list label').forEach(l => { l.style.display = !q || l.dataset.name.includes(q) ? '' : 'none'; });
        document.querySelector('#falcon-seo-countries-list [data-divider]').style.display = q ? 'none' : '';
    };
    document.addEventListener('click', e => {
        const picker = document.getElementById('falcon-seo-countries-picker');
        if (picker && !picker.contains(e.target)) document.getElementById('falcon-seo-countries-panel').classList.add('hidden');
    });
    syncCountries();

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

    // Google's figures for this exact search, by country (Search Console, last 90 days): how often
    // the site showed for it, the clicks and the average position.
    function renderGoogleCountries(g, chosen) {
        if (!g) {
            return '';
        }
        let html = `<div class="mb-3 pb-3 border-b border-[#f0f0f1]"><div class="text-[11px] font-bold text-[#2c3338] mb-1">Google (Search Console), last ${g.days} days</div>`;
        if (g.error) {
            return html + `<p class="text-[11px] text-[#b32d2e]">${esc(g.error)}</p></div>`;
        }
        if (!g.rows.length) {
            return html + `<p class="text-[11px] text-[#646970]">Google has not shown your site for this exact search${chosen.length ? ' in the chosen countries' : ''} yet.</p></div>`;
        }
        const max = Math.max(1, ...g.rows.map(r => r.impressions));
        const shown = g.rows.slice(0, 15);
        html += `<table class="w-full text-[11px]"><tr class="text-[#646970]"><th class="text-left font-normal pr-2">Country</th><th></th>
            <th class="text-right font-normal px-2">Shown</th><th class="text-right font-normal px-2">Clicks</th><th class="text-right font-normal pl-2">Position</th></tr>` +
            shown.map(r => `<tr>
                <td class="py-0.5 pr-2 whitespace-nowrap">${esc(r.name)}</td>
                <td class="w-full"><div class="h-2 rounded bg-[#00a32a]" style="width:${Math.round(r.impressions / max * 100)}%"></div></td>
                <td class="px-2 text-right">${num(r.impressions)}</td><td class="px-2 text-right">${num(r.clicks)}</td>
                <td class="pl-2 text-right ${r.position <= 10 ? 'text-[#00731a]' : (r.position <= 20 ? 'text-[#996800]' : '')}">${r.position}</td></tr>`).join('') + `</table>`;
        if (g.rows.length > shown.length) {
            html += `<p class="text-[10px] text-[#8c8f94] mt-1">${g.rows.length - shown.length} more countries with fewer.</p>`;
        }
        if (g.total) {
            html += `<p class="text-[10px] text-[#8c8f94] mt-1">Worldwide: shown ${num(g.total.impressions)} times, ${num(g.total.clicks)} clicks. Position 1–10 is page one.</p>`;
        }
        return html + `</div>`;
    }

    function renderKeyword(k) {
        if (!k || !k.keyword) {
            return '';
        }
        const anySource = k.sources && (k.sources.planner || k.sources.bing || k.sources.autocomplete);
        let html = `<div class="border border-[#dcdcde] rounded bg-white p-3 mb-4">
            <div class="text-[12px] font-bold text-[#2c3338] mb-2">Keyword: “${esc(k.keyword)}” · ${esc(countryName(k.country))}${(k.countries || []).length > 1 ? ` <span class="font-normal text-[#646970]">(+${k.countries.length - 1} more countries)</span>` : ''}</div>`;
        html += renderGoogleCountries(k.google_countries, k.countries || []);
        // Pro: Google's numbers through DataForSEO, filled in after the render.
        if (window.falconSeoPro) {
            html += '<div id="falcon-seo-dfs" class="mb-3 pb-3 border-b border-[#f0f0f1]"></div>';
        }
        if (!anySource) {
            html += `<p class="text-[12px] text-[#646970]">How often it is searched needs a free source: a Bing Webmaster API key or a Keyword Planner import, in SEO → Settings → Keyword research.</p>`;
        } else {
            html += `<div class="flex flex-wrap gap-4 text-[12px] mb-2">
                <span><span class="text-[#646970]">Google (Keyword Planner):</span> <strong>${k.planner ? num(k.planner.searches_low) + '–' + num(k.planner.searches_high) + '/mo' : '—'}</strong>${k.planner && k.planner.competition ? ' · competition ' + esc(k.planner.competition) : ''}</span>
                <span><span class="text-[#646970]">Bing:</span> <strong>${k.bing ? num(k.bing.impressions) + ' searches (30 days)' : '—'}</strong></span>
            </div>`;
            if (k.volume === 0 || (k.volume !== null && k.volume < 50)) {
                html += `<p class="text-[12px] text-[#b32d2e] mb-2">Very few people search for this phrase here. A more common way of saying it may bring more visitors (see below).</p>`;
            }
            html += renderCountries(k);
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

    // Searches in each chosen country (Bing), as bars, most first.
    function renderCountries(k) {
        const many = (k.countries || []).length > 1;
        if (!many) {
            return `<p class="text-[11px] text-[#646970]">Tick more countries under "Searchers in" to compare how this keyword is searched in each.</p>`;
        }
        if (!k.sources.bing) {
            return `<p class="text-[11px] text-[#646970]">Comparing countries needs the free Bing Webmaster API key (SEO → Settings → Keyword research). Keyword Planner figures cover one market only.</p>`;
        }
        return `<div class="text-[11px] font-bold text-[#2c3338] mt-2 mb-1">Searches on Bing, last 30 days</div>
            <div id="falcon-seo-country-rows" data-keyword="${esc(k.keyword)}" data-countries="${esc(k.countries.map(c => c.country).join(','))}"></div>
            <p class="text-[10px] text-[#8c8f94] mt-1">Bing's own searches: a guide to where demand is, smaller than Google's numbers.</p>`;
    }

    // Searches per country, asked batchSize countries at a time so any number can be compared.
    // The table fills in as each batch answers: most searched first, countries with none folded away.
    let countryRun = 0;
    async function loadCountries() {
        const box = document.getElementById('falcon-seo-country-rows');
        if (!box) return;
        const run = ++countryRun;
        const codes = box.dataset.countries.split(',').filter(Boolean);
        const token = document.querySelector('input[name="_token"]')?.value || '';
        let rows = [];
        const draw = (done) => {
            const sorted = [...rows].sort((a, b) => (b.searches ?? -1) - (a.searches ?? -1));
            const found = sorted.filter(r => r.searches > 0);
            const none = sorted.filter(r => !(r.searches > 0));
            const max = Math.max(1, ...found.map(r => r.searches));
            const row = r => `<tr title="${esc(r.error || '')}">
                <td class="py-0.5 pr-2 whitespace-nowrap">${esc(r.name)}</td>
                <td class="w-full"><div class="h-2 rounded bg-[#2271b1]" style="width:${Math.round((r.searches || 0) / max * 100)}%"></div></td>
                <td class="pl-2 text-right whitespace-nowrap">${r.error ? '<span class="text-[#b32d2e]">error</span>' : num(r.searches)}</td></tr>`;
            const limited = rows.filter(r => r.limited);
            let html = done ? '' : `<div class="text-[11px] text-[#646970] italic mb-1">Checked ${rows.length} of ${codes.length} countries…</div>`;
            if (limited.length) {
                html += `<div class="mb-2 p-2 rounded bg-[#fcf9e8] border border-[#dba617] text-[11px] text-[#1d2327]">
                    <strong>Bing's usage limit is reached for now.</strong> ${limited.length} ${limited.length === 1 ? 'country was' : 'countries were'} not checked.
                    The figures below are kept; press "Analyse page" again later (in an hour or so) to fill in the rest.</div>`;
            }
            html += found.length ? `<table class="w-full text-[11px]">${found.slice(0, 25).map(row).join('')}</table>` : (done ? '<p class="text-[11px] text-[#646970]">No searches on Bing in these countries.</p>' : '');
            if (found.length > 25) {
                html += `<details class="mt-1"><summary class="text-[11px] text-[#2271b1] cursor-pointer">${found.length - 25} more countries</summary><table class="w-full text-[11px]">${found.slice(25).map(row).join('')}</table></details>`;
            }
            const unchecked = none.filter(r => r.limited);
            const empty = none.filter(r => !r.limited);
            if (done && unchecked.length) {
                html += `<details class="mt-1"><summary class="text-[11px] text-[#996800] cursor-pointer">${unchecked.length} not checked yet (limit)</summary>
                    <p class="text-[11px] text-[#646970] mt-1">${unchecked.map(r => esc(r.name)).join(', ')}</p></details>`;
            }
            if (done && empty.length) {
                html += `<details class="mt-1"><summary class="text-[11px] text-[#646970] cursor-pointer">${empty.length} ${empty.length === 1 ? 'country' : 'countries'} with no searches</summary>
                    <p class="text-[11px] text-[#646970] mt-1">${empty.map(r => esc(r.name) + (r.error ? ' (error)' : '')).join(', ')}</p></details>`;
            }
            box.innerHTML = html;
        };
        draw(false);
        for (let i = 0; i < codes.length; i += batchSize) {
            const body = new FormData();
            body.append('_token', token);
            body.append('q', box.dataset.keyword);
            codes.slice(i, i + batchSize).forEach(c => body.append('countries[]', c));
            try {
                const r = await fetch(@json(route('admin.seo.keyword-countries')), {method: 'POST', body, headers: {'Accept': 'application/json'}});
                const d = r.ok ? await r.json() : {rows: codes.slice(i, i + batchSize).map(c => ({country: c, name: countryName(c), searches: null, error: 'request failed (' + r.status + ')'}))};
                rows = rows.concat(d.rows || []);
            } catch (e) {
                rows = rows.concat(codes.slice(i, i + batchSize).map(c => ({country: c, name: countryName(c), searches: null, error: e.message})));
            }
            if (run !== countryRun) return; // a newer analysis started
            // Bing said stop: mark the rest instead of asking for each of them.
            if (rows.some(r => r.limited)) {
                rows = rows.concat(codes.slice(i + batchSize).map(c => ({country: c, name: countryName(c), searches: null, error: 'limit', limited: true})));
                draw(true);
                return;
            }
            draw(i + batchSize >= codes.length);
        }
    }

    // Pro: a weak SEO title or description gets "Suggest with AI" right where it is reported.
    function aiFix(c) {
        if (!window.falconSeoPro || !['title', 'description'].includes(c.id) || !['warning', 'error'].includes(c.status)) return '';
        return ` <button type="button" class="text-[11px] text-[#2271b1] hover:underline ml-1" onclick="switchSeoTab('general'); window.falconSeoPro.suggest('${c.id}')">✨ Suggest with AI</button>`;
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
                    <div class="min-w-0 flex-1"><div class="text-[#1d2327]">${esc(c.label)}${aiFix(c)}</div>
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
        chosen.forEach(c => body.append('countries[]', c));
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
            .then(d => {
                box.className = '';
                box.innerHTML = render(d);
                loadCountries();
                if (window.falconSeoPro && d.keyword && d.keyword.keyword) {
                    window.falconSeoPro.metrics(d.keyword.keyword, (d.keyword.countries || []).map(c => c.country), document.getElementById('falcon-seo-dfs'));
                }
            })
            .catch(e => { box.className = 'text-[13px] text-[#b32d2e]'; box.textContent = e.message; })
            .finally(() => { btn.disabled = false; });
    };

    document.addEventListener('falcon-seo-tab', function (e) {
        if (e.detail.tab === 'analysis' && document.getElementById('falcon-seo-analysis').classList.contains('italic')) {
            falconSeoAnalyse();
        }
    });
})();
</script>
