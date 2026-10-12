<x-falcon-cms::layouts.admin>
    <x-slot name="title">Bulk SEO - FalconCMS</x-slot>

    @php
        // Google shows the title as the page renders it: "Title - Site", unless the title already names the site.
        $siteSuffix = ' '.get_cms_option('seo_separator', '-').' '.get_cms_option('site_title', 'FalconCMS');
    @endphp

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Bulk SEO</h1>
        @include('falcon-cms::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-4 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">{{ $errors->first() }}</div>
        @endif

        <p class="text-[13px] text-[#50575e] mb-4 max-w-[900px]">Every page's search title and description on one screen. Leave a field empty to use the default (the page title, or the excerpt). Only the rows you change are saved.</p>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.settings.seo-bulk') }}" class="flex flex-wrap items-center gap-2 mb-3">
            <select name="type" class="wp-input h-8 py-0 text-[13px]" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach($types as $slug => $name)
                    <option value="{{ $slug }}" @selected($type === $slug)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="missing" class="wp-input h-8 py-0 text-[13px]" onchange="this.form.submit()">
                <option value="">Everything</option>
                <option value="any" @selected($missing === 'any')>Missing title or description</option>
                <option value="title" @selected($missing === 'title')>Missing SEO title</option>
                <option value="description" @selected($missing === 'description')>Missing meta description</option>
                <option value="focus_keyword" @selected($missing === 'focus_keyword')>Missing focus keyword</option>
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search titles..." class="wp-input h-8 text-[13px] w-[220px]">
            <button type="submit" class="wp-btn-secondary h-8 py-0 px-3 text-[13px]">Filter</button>
            {{-- The same pagination as the Posts and Pages lists. --}}
            <div class="ml-auto"><x-falcon-cms::admin.pagination :paginator="$posts" /></div>
        </form>

        <form method="POST" action="{{ route('admin.settings.seo-bulk.update') }}" id="bulk-seo-form" onsubmit="return bulkSeoSubmit()">
            @csrf
            <div class="bg-white border border-[#c3c4c7] overflow-x-auto">
                <table class="w-full text-[13px] min-w-[960px]">
                    <thead>
                        <tr class="border-b border-[#c3c4c7] text-left text-[#1d2327]">
                            <th class="px-3 py-2 font-semibold w-[22%]">Page</th>
                            <th class="px-3 py-2 font-semibold w-[28%]">SEO title</th>
                            <th class="px-3 py-2 font-semibold w-[32%]">Meta description</th>
                            <th class="px-3 py-2 font-semibold w-[13%]">Focus keyword</th>
                            <th class="px-3 py-2 font-semibold text-center w-[5%]" title="Hide this page from search engines">Noindex</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $p)
                            @php
                                $seo = is_array($p->seo_meta) ? $p->seo_meta : [];
                                $edit = $p->type === 'page' && \Illuminate\Support\Facades\Route::has('admin.pages.edit') ? route('admin.pages.edit', $p->id) : route('admin.posts.edit', $p->id);
                            @endphp
                            <tr class="bulk-seo-row border-b border-[#f0f0f1] align-top" data-id="{{ $p->id }}">
                                <td class="px-3 py-2">
                                    <a href="{{ $edit }}" class="font-medium text-[#2271b1] hover:underline break-words">{{ $p->title }}</a>
                                    <div class="text-[11px] text-[#8c8f94]">{{ $types[$p->type] ?? $p->type }}@if($p->status !== 'published') · {{ ucfirst($p->status) }}@endif</div>
                                    <a href="{{ get_falcon_permalink($p) }}" target="_blank" rel="noopener" class="text-[11px] text-[#646970] hover:text-[#2271b1]">View</a>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $p->id }}][seo_title]" value="{{ $seo['title'] ?? '' }}" placeholder="{{ $p->title }}" maxlength="255"
                                           class="bulk-seo-field wp-input w-full text-[13px]" data-min="30" data-ok-min="50" data-ok-max="60" data-site="{{ $siteSuffix }}">
                                    <div class="bulk-seo-count text-[11px] mt-0.5"></div>
                                </td>
                                <td class="px-3 py-2">
                                    <textarea name="rows[{{ $p->id }}][description]" rows="2" maxlength="500" placeholder="Uses the excerpt when empty"
                                              class="bulk-seo-field wp-input w-full text-[13px]" data-min="70" data-ok-min="120" data-ok-max="160">{{ $seo['description'] ?? '' }}</textarea>
                                    <div class="bulk-seo-count text-[11px] mt-0.5"></div>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $p->id }}][focus_keyword]" value="{{ $seo['focus_keyword'] ?? '' }}" maxlength="191"
                                           class="bulk-seo-field bulk-seo-keyword wp-input w-full text-[13px]">
                                    <div class="bulk-seo-keyword-hint text-[11px] mt-0.5"></div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="hidden" name="rows[{{ $p->id }}][noindex]" value="0">
                                    <input type="checkbox" name="rows[{{ $p->id }}][noindex]" value="1" @checked(($seo['noindex'] ?? '0') == '1')
                                           class="bulk-seo-field rounded-sm border-[#8c8f94] mt-2">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-[#646970]">Nothing matches these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between gap-3 mt-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <button type="submit" class="wp-btn-primary h-8 py-0 px-4 text-[13px]">Save changes</button>
                    <span id="bulk-seo-dirty" class="text-[12px] text-[#646970]">No changes yet.</span>
                </div>
                <x-falcon-cms::admin.pagination :paginator="$posts" />
            </div>
        </form>
    </div>

    <script>
        (function () {
            const rows = document.querySelectorAll('.bulk-seo-row');
            const initial = new Map();
            const valueOf = el => el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;

            const count = el => {
                const out = el.parentElement.querySelector('.bulk-seo-count');
                if (!out) return;
                let len = el.value.length || (el.placeholder && el.tagName === 'INPUT' ? el.placeholder.length : 0);
                let using = !el.value.length && el.tagName === 'INPUT' ? ' (page title)' : '';
                // A title is shown as "Title - Site" unless it already names the site.
                const site = el.dataset.site || '';
                const siteName = site.replace(/^\s*\S+\s/, '').toLowerCase();
                if (site && len && !(el.value || el.placeholder).toLowerCase().includes(siteName)) {
                    len += site.length;
                    using += ' with site name';
                }
                const min = +el.dataset.min, okMin = +el.dataset.okMin, okMax = +el.dataset.okMax;
                let color = '#00a32a';
                if (!len) color = '#646970';
                else if (len < min || len > okMax + 10) color = '#d63638';
                else if (len < okMin || len > okMax) color = '#dba617';
                out.style.color = color;
                out.textContent = len ? `${len} characters${using} · ideal ${okMin}-${okMax}` : 'Empty';
            };

            const keywordHint = row => {
                const kw = row.querySelector('.bulk-seo-keyword').value.trim().toLowerCase();
                const titleEl = row.querySelector('[name$="[seo_title]"]');
                const title = (titleEl.value || titleEl.placeholder).toLowerCase();
                const desc = row.querySelector('[name$="[description]"]').value.toLowerCase();
                const hint = row.querySelector('.bulk-seo-keyword-hint');
                if (!kw) { hint.textContent = ''; return; }
                const missing = [!title.includes(kw) && 'title', !desc.includes(kw) && 'description'].filter(Boolean);
                hint.style.color = missing.length ? '#b32d2e' : '#00731a';
                hint.textContent = missing.length ? 'Not in ' + missing.join(' or ') : '✓ In title and description';
            };

            const dirtyRows = () => [...rows].filter(row =>
                [...row.querySelectorAll('.bulk-seo-field')].some(el => initial.get(el) !== valueOf(el)));

            const refresh = () => {
                const n = dirtyRows().length;
                rows.forEach(row => row.classList.toggle('bg-[#fcf9e8]', dirtyRows().includes(row)));
                document.getElementById('bulk-seo-dirty').textContent = n ? `${n} ${n === 1 ? 'row' : 'rows'} changed, not saved yet.` : 'No changes yet.';
            };

            rows.forEach(row => {
                row.querySelectorAll('.bulk-seo-field').forEach(el => {
                    initial.set(el, valueOf(el));
                    count(el);
                    el.addEventListener('input', () => { count(el); keywordHint(row); refresh(); });
                    el.addEventListener('change', refresh);
                });
                keywordHint(row);
            });

            // Only the changed rows are sent: the rest are disabled for the submit.
            window.bulkSeoSubmit = () => {
                const dirty = dirtyRows();
                if (!dirty.length) { window.showToast?.('Nothing has changed.', 'info'); return false; }
                rows.forEach(row => {
                    if (!dirty.includes(row)) row.querySelectorAll('input, textarea').forEach(el => el.disabled = true);
                });
                document.getElementById('bulk-seo-form').dataset.submitting = '1';
                return true;
            };

            window.addEventListener('beforeunload', e => {
                if (dirtyRows().length && !document.getElementById('bulk-seo-form').dataset.submitting) { e.preventDefault(); e.returnValue = ''; }
            });
        })();
    </script>
</x-falcon-cms::layouts.admin>
