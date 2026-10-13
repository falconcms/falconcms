{{-- Editor → SEO panel: the Keywords and Internal Linking tabs (falcon_seo_metabox_panels). --}}
{{-- Keywords Tab: ideas from the page's own words, and (with Search Console) Google's searches --}}
<div id="seo-tab-keywords" class="seo-tab-content hidden p-4">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h4 class="text-[13px] font-bold text-[#2c3338] mb-1">Keyword Suggestions</h4>
            <p class="text-[12px] text-[#646970]">What this page is about, read from its words, and where each phrase is still missing. A phrase in the title, a heading and the first paragraph tells search engines clearly what the page covers.</p>
        </div>
        <button type="button" onclick="fetchKeywordSuggestions()" class="wp-btn-secondary h-7 py-0 px-3 text-[12px] whitespace-nowrap">Analyse again</button>
    </div>
    {{-- Search demand: how many people search for a keyword (Keyword Planner import, Bing, suggestions) --}}
    <div class="mb-5 p-3 border border-[#dcdcde] rounded bg-white">
        <div class="text-[12px] font-bold text-[#2c3338] mb-2">Search demand</div>
        <div class="flex gap-2">
            <input type="text" id="keyword-demand-q" class="wp-input flex-1 h-8 text-[13px]" placeholder="A keyword to check, e.g. the focus keyword"
                   onkeydown="if (event.key === 'Enter') { event.preventDefault(); fetchKeywordDemand(); }">
            <button type="button" onclick="fetchKeywordDemand()" class="wp-btn-secondary h-8 py-0 px-3 text-[12px]">Check</button>
        </div>
        <div id="keyword-demand" class="mt-2"></div>
    </div>

    <div id="keyword-suggestions" class="space-y-3">
        <div class="text-[13px] text-gray-500 italic">Open this tab to analyse the page...</div>
    </div>
</div>

{{-- Internal Linking Tab --}}
<div id="seo-tab-internal" class="seo-tab-content hidden p-4">
    <div class="mb-4">
        <h4 class="text-[13px] font-bold text-[#2c3338] mb-1">Internal Link Suggestions</h4>
        <p class="text-[12px] text-[#646970]">Links between related pages help search engines find and understand them. Suggestions use the title and the focus keyword; nothing is added for you.</p>
    </div>
    <div id="internal-links-suggestions" class="space-y-3">
        <div class="text-[13px] text-gray-500 italic">Start typing a title to see suggestions...</div>
    </div>
</div>

<script>
    function fetchKeywordSuggestions() {
        const container = document.getElementById('keyword-suggestions');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        // The rich editor's live content; in builder mode the saved content is analysed instead.
        let content = '';
        if (window.tinymce && tinymce.get('wp-editor') && document.getElementById('editor_type')?.value !== 'builder') {
            content = tinymce.get('wp-editor').getContent();
        } else if (document.getElementById('editor_type')?.value !== 'builder') {
            content = document.querySelector('textarea[name="content"]')?.value || '';
        }
        const body = new FormData();
        body.append('_token', document.querySelector('input[name="_token"]')?.value || '');
        body.append('post_id', "{{ $post->id ?? 0 }}");
        body.append('title', document.querySelector('input[name="title"]')?.value || '');
        body.append('seo_title', document.getElementById('seo-title-input')?.value || '');
        body.append('description', document.getElementById('seo-desc-input')?.value || '');
        body.append('slug', document.querySelector('input[name="slug"]')?.value || "{{ $post->slug ?? '' }}");
        body.append('keyword', document.getElementById('seo-focus-keyword')?.value || '');
        body.append('content', content);

        container.innerHTML = '<div class="text-[13px] text-gray-500 italic">Analysing...</div>';

        const places = {title: 'Title', headings: 'Heading', intro: 'First 100 words', url: 'URL', description: 'Description', content: 'Content'};
        const chips = (inn, keys) => keys.map(k => inn[k]
            ? `<span class="inline-block text-[10px] px-1.5 py-0.5 rounded bg-[#edfaef] text-[#00731a] mr-1 mb-1">✓ ${places[k]}</span>`
            : `<span class="inline-block text-[10px] px-1.5 py-0.5 rounded bg-[#fcf0f1] text-[#b32d2e] mr-1 mb-1">✗ ${places[k]}</span>`).join('');
        const useBtn = phrase => `<span class="flex items-center gap-3">
            <button type="button" data-phrase="${esc(phrase)}" onclick="fetchKeywordDemand(this.dataset.phrase); document.getElementById('keyword-demand-q').scrollIntoView({block: 'center', behavior: 'smooth'})" class="text-[11px] text-[#646970] hover:text-[#2271b1] whitespace-nowrap">Search demand</button>
            <button type="button" data-phrase="${esc(phrase)}" onclick="useFocusKeyword(this.dataset.phrase)" class="text-[11px] text-[#2271b1] hover:underline whitespace-nowrap">Use as focus keyword</button>
        </span>`;

        fetch("{{ route('admin.seo.keyword-suggestions') }}", {method: 'POST', body, headers: {'Accept': 'application/json'}})
            .then(res => res.json())
            .then(data => {
                let html = '';
                const google = data.google || [];
                if (google.length) {
                    html += `<div class="text-[12px] font-bold text-[#2c3338] mb-1">Google already shows this page for <span class="font-normal text-[#646970]">(last ${esc(data.google_days || 90)} days)</span></div>`;
                    html += '<div class="space-y-2 mb-5">' + google.map(g => `
                        <div class="p-2 border rounded ${g.opportunity ? 'border-[#f0c33c] bg-[#fcf9e8]' : 'border-gray-100 bg-gray-50'}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-[13px] font-medium text-[#1d2327] truncate">${esc(g.query)}</div>
                                ${useBtn(g.query)}
                            </div>
                            <div class="text-[11px] text-[#646970] mb-1">Position ${esc(g.position)} · ${esc(g.impressions)} impressions · ${esc(g.clicks)} clicks${g.opportunity ? ' · <strong class="text-[#8a6100]">Close to the top: put these words in the title</strong>' : ''}</div>
                            <div>${chips(g.in, ['title', 'headings', 'description', 'content'])}</div>
                        </div>`).join('') + '</div>';
                } else if (data.google_status === 'no_data') {
                    html += '<div class="text-[12px] text-[#646970] italic mb-4">Google has not shown this page in search results yet, so there are no searches to show. The ideas below come from the page itself.</div>';
                }

                const phrases = data.phrases || [];
                html += '<div class="text-[12px] font-bold text-[#2c3338] mb-1">From the page\'s own words</div>';
                html += phrases.length
                    ? '<div class="space-y-2">' + phrases.map(p => `
                        <div class="p-2 border border-gray-100 rounded bg-gray-50">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-[13px] font-medium text-[#1d2327] truncate">${esc(p.phrase)}</span>
                                    <span class="text-[11px] text-[#646970] whitespace-nowrap">${esc(p.count)}× on the page</span>
                                </div>
                                ${useBtn(p.phrase)}
                            </div>
                            <div class="mt-1 h-1 bg-[#f0f0f1] rounded-full overflow-hidden"><div class="h-full bg-[#2271b1]" style="width:${Math.max(4, Math.min(100, Number(p.score) || 0))}%"></div></div>
                            <div class="mt-1">${chips(p.in, ['title', 'headings', 'intro', 'url', 'description'])}</div>
                        </div>`).join('') + '</div>'
                    : `<div class="text-[13px] text-gray-500 italic">${(data.words || 0) < 50 ? 'Too little text to analyse yet: write at least a few paragraphs.' : 'No phrase repeats often enough to stand out.'}</div>`;
                html += '<p class="text-[11px] text-[#646970] mt-3">The bar shows how strongly a phrase marks this page out from the rest of your site. It says what the page is about, not how many people search for it.</p>';
                container.innerHTML = html;
            })
            .catch(() => {
                container.innerHTML = '<div class="text-[13px] text-[#b32d2e]">Could not analyse the page. Try again.</div>';
            });
    }

    function fetchKeywordDemand(q) {
        const input = document.getElementById('keyword-demand-q');
        if (q !== undefined) input.value = q;
        const query = input.value.trim();
        const box = document.getElementById('keyword-demand');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        const num = n => n >= 1e6 ? (n / 1e6).toFixed(n % 1e6 ? 1 : 0) + 'M' : n >= 1e3 ? (n / 1e3).toFixed(n % 1e3 ? 1 : 0) + 'K' : String(n);
        const google = r => r && r.searches_high != null
            ? (r.searches_low != null && r.searches_low !== r.searches_high ? num(r.searches_low) + '–' + num(r.searches_high) : num(r.searches_high))
            : null;
        if (!query) { box.innerHTML = ''; return; }
        box.innerHTML = '<div class="text-[12px] text-gray-500 italic">Checking...</div>';

        const body = new FormData();
        body.append('_token', document.querySelector('input[name="_token"]')?.value || '');
        body.append('q', query);
        fetch("{{ route('admin.seo.keyword-demand') }}", {method: 'POST', body, headers: {'Accept': 'application/json'}})
            .then(res => res.json())
            .then(d => {
                const s = d.sources || {};
                if (!s.planner && !s.bing && !s.autocomplete) {
                    box.innerHTML = `<div class="text-[12px] text-[#646970]">No source is set up yet. Add a free Bing key, import Google Keyword Planner data, or turn on search suggestions in <a href="{{ route('admin.settings.seo') }}#keyword-research" target="_blank" class="text-[#2271b1]">SEO → Settings → Keyword research</a>.</div>`;
                    return;
                }
                let html = '<div class="flex flex-wrap gap-2 mb-2">';
                if (s.planner) html += `<span class="text-[12px] px-2 py-1 rounded bg-[#f0f6fc] text-[#135e96]">Google: <strong>${google(d.volume) ? esc(google(d.volume)) + ' searches/month' : 'not in your imported data'}</strong>${d.volume?.competition ? ' · ' + esc(d.volume.competition) + ' competition' : ''}</span>`;
                if (s.bing) html += `<span class="text-[12px] px-2 py-1 rounded bg-[#f0f6fc] text-[#135e96]">Bing: <strong>${d.bing ? esc(num(d.bing.impressions)) + ' searches in the last 30 days' : 'no data'}</strong></span>`;
                html += '</div>';
                (d.errors || []).forEach(e => html += `<div class="text-[12px] text-[#b32d2e] mb-1">${esc(e)}</div>`);

                const related = d.related || [];
                if (related.length) {
                    html += '<div class="text-[11px] font-bold text-[#2c3338] mt-2 mb-1">Related searches</div><div class="divide-y divide-[#f0f0f1] border border-[#f0f0f1] rounded">';
                    html += related.map(r => `
                        <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                            <span class="text-[12px] text-[#1d2327] truncate">${esc(r.query)}</span>
                            <span class="flex items-center gap-3 whitespace-nowrap text-[11px] text-[#646970]">
                                ${google(r) ? 'Google ' + esc(google(r)) : ''}${r.bing != null ? (google(r) ? ' · ' : '') + 'Bing ' + esc(num(r.bing)) : ''}
                                <button type="button" data-phrase="${esc(r.query)}" onclick="useFocusKeyword(this.dataset.phrase)" class="text-[#2271b1] hover:underline">Use</button>
                            </span>
                        </div>`).join('') + '</div>';
                }
                const sugg = d.suggestions || [];
                if (sugg.length) {
                    html += '<div class="text-[11px] font-bold text-[#2c3338] mt-2 mb-1">People also type into Google</div><div class="flex flex-wrap gap-1">';
                    html += sugg.map(q => `<button type="button" data-phrase="${esc(q)}" onclick="fetchKeywordDemand(this.dataset.phrase)" class="text-[11px] px-2 py-0.5 rounded border border-[#dcdcde] hover:border-[#2271b1] hover:text-[#2271b1]">${esc(q)}</button>`).join('') + '</div>';
                }
                if (!related.length && !sugg.length && !(d.errors || []).length) {
                    html += '<div class="text-[12px] text-[#646970]">No related searches found for this keyword.</div>';
                }
                box.innerHTML = html;
            })
            .catch(() => { box.innerHTML = '<div class="text-[12px] text-[#b32d2e]">Could not check. Try again.</div>'; });
    }

    function useFocusKeyword(phrase) {
        const input = document.getElementById('seo-focus-keyword');
        if (!input) return;
        input.value = phrase;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        window.showToast?.('Focus keyword set. Save the page to keep it.', 'success');
    }

    function fetchInternalSuggestions() {
        const title = document.querySelector('input[name="title"]')?.value || '';
        const keyword = document.getElementById('seo-focus-keyword')?.value || '';
        const container = document.getElementById('internal-links-suggestions');
        const postId = "{{ $post->id ?? 0 }}";
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

        if (title.length < 3 && keyword.length < 3) {
            container.innerHTML = '<div class="text-[13px] text-gray-500 italic">Enter a title or a focus keyword to see suggestions...</div>';
            return;
        }

        container.innerHTML = '<div class="text-[13px] text-gray-500 italic">Searching for related content...</div>';

        fetch("{{ route('admin.seo.related-posts') }}?s=" + encodeURIComponent(title) + "&keyword=" + encodeURIComponent(keyword) + "&exclude=" + postId)
            .then(res => res.json())
            .then(data => {
                // Whatever the editor holds right now, to mark pages this one already links to.
                const content = (document.querySelector('[name="content"]')?.value || '').toLowerCase();
                const row = (item, linkedHere) => `
                    <div class="p-2 border border-gray-100 rounded bg-gray-50 flex items-center justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-medium text-[#2271b1] truncate">${esc(item.title)}</div>
                            <div class="text-[11px] text-gray-500 truncate">${esc(item.reason)}</div>
                            <div class="text-[11px] text-gray-400 truncate">${esc(item.url)}</div>
                        </div>
                        ${linkedHere ? '<span class="text-[11px] text-green-700 font-semibold whitespace-nowrap">Already linked</span>' : ''}
                        ${item.edit && !item.url_only ? `<a href="${esc(item.edit)}" target="_blank" class="text-[11px] text-[#2271b1] whitespace-nowrap">Open</a>` : ''}
                        <button type="button" data-url="${esc(item.url)}" onclick="copyToClipboard(this.dataset.url)" class="p-1.5 bg-white border border-gray-200 rounded shadow-sm hover:bg-gray-100" title="Copy URL">
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                        </button>
                    </div>`;

                const out = data.outbound || [];
                const inb = data.inbound || [];
                let html = '<div class="text-[12px] font-bold text-[#2c3338] mb-1">Link from this page to</div>';
                html += out.length
                    ? '<div class="space-y-2 mb-4">' + out.map(i => row(i, i.path && content.includes(i.path.toLowerCase()) && i.path !== '/')).join('') + '</div>'
                    : '<div class="text-[13px] text-gray-500 italic mb-4">No related pages found.</div>';
                html += '<div class="text-[12px] font-bold text-[#2c3338] mb-1">Pages that could link here</div>';
                html += inb.length
                    ? '<div class="space-y-2">' + inb.map(i => row(i, false)).join('') + '</div>'
                    : '<div class="text-[13px] text-gray-500 italic">{{ ($post->id ?? null) ? 'None found: set a focus keyword to look for pages that mention it.' : 'Save the page first to see which pages could link to it.' }}</div>';
                container.innerHTML = html;
            });
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            window.showToast('URL copied to clipboard!', 'success');
        });
    }

    // Update suggestions when the title changes, while the tab is open.
    (function () {
        let timer;
        document.querySelector('input[name="title"]')?.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(() => {
                if (!document.getElementById('seo-tab-internal').classList.contains('hidden')) {
                    fetchInternalSuggestions();
                }
            }, 500);
        });
    })();

    document.addEventListener('falcon-seo-tab', function (e) {
        if (e.detail.tab === 'internal') {
            fetchInternalSuggestions();
        }
        if (e.detail.tab === 'keywords') {
            const demand = document.getElementById('keyword-demand-q');
            if (demand && !demand.value) demand.value = document.getElementById('seo-focus-keyword')?.value || '';
            fetchKeywordSuggestions();
        }
    });
</script>
