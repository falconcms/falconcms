<x-falcon-cms::layouts.admin title="Layout">
    @php
        $slotMeta = [
            'header'         => ['icon' => 'web_asset',          'label' => 'Header'],
            'page_title_bar' => ['icon' => 'title',              'label' => 'Page Title Bar'],
            'content'        => ['icon' => 'article',            'label' => 'Content'],
            'footer'         => ['icon' => 'bottom_panel_open',  'label' => 'Footer'],
        ];
        $slotSectionsJson = collect($sections ?? [])->map(function ($list) {
            return $list->map(fn ($s) => ['id' => $s->id, 'title' => $s->title ?: 'Untitled', 'status' => $s->status])->values();
        })->toArray();
        $layoutAssignedJson = collect($layouts ?? [])->mapWithKeys(function ($l) {
            return [$l['id'] => collect($l['assigned'])->map(fn ($s) => $s?->id)->toArray()];
        })->toArray();
        $layoutConditionsJson = collect($layouts ?? [])->mapWithKeys(fn ($l) => [$l['id'] => ($l['conditions_ui'] ?? [])])->toArray();
    @endphp

    <div class="p-6 bg-[#f0f0f1] min-h-screen">

        @if(session('success'))
            <div class="bg-white border-l-4 border-[#00a32a] p-3 mb-4 shadow-[0_1px_1px_rgba(0,0,0,0.04)] text-[13px] text-[#1d2327]">
                <p>{{ session('success') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div class="bg-white border-l-4 border-[#d63638] p-3 mb-4 shadow-[0_1px_1px_rgba(0,0,0,0.04)] text-[13px] text-[#1d2327]">
                @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
            </div>
        @endif

        {{-- ── Create panel ─────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,0.04)] rounded-sm p-6">
                <h1 class="text-[26px] font-semibold text-[#1d2327] mb-2">Layout Builder</h1>
                <p class="text-[13.5px] leading-relaxed text-[#50575e] mb-5">Create a new layout which you can then assign layout sections to and set layout conditions.</p>
                <form action="{{ route('admin.falcon-builder.layout.create') }}" method="POST">
                    @csrf
                    <label class="block text-[13px] text-[#1d2327] mb-1.5">Layout Name</label>
                    <input type="text" name="name" required placeholder="Enter Layout Name" class="wp-input w-full mb-4">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[14px] font-semibold rounded transition-colors shadow-sm">Create New Layout</button>
                </form>
            </div>
        </div>

        {{-- ── Layout cards ─────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 items-start">
            @foreach($layouts as $layout)
                @include('falcon-cms::admin.falcon-builder.partials.layout-card', ['layout' => $layout, 'slotMeta' => $slotMeta, 'sections' => $sections])
            @endforeach
        </div>
    </div>

    {{-- Shared forms --}}
    <form id="assign-form" action="{{ route('admin.falcon-builder.section.assign') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="layout" id="assign-layout"><input type="hidden" name="slot" id="assign-slot"><input type="hidden" name="section_id" id="assign-section-id">
    </form>
    <form id="delete-section-form" action="{{ route('admin.falcon-builder.section.delete') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="section_id" id="delete-section-id">
    </form>

    {{-- ── Layout Conditions modal ──────────────────────────────────────── --}}
    <div id="conditions-modal" class="fixed inset-0 z-[9999] hidden bg-black/40">
        <div class="absolute inset-4 md:inset-y-[100px] md:inset-x-[200px] bg-white rounded-sm shadow-2xl flex flex-col overflow-hidden">
            <div class="bg-[#1d2327] px-5 py-3.5 flex items-center justify-between flex-shrink-0">
                <h2 class="text-white text-[16px] font-semibold">Layout Conditions</h2>
                <button type="button" onclick="closeConditionsModal()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined text-[22px]">close</span></button>
            </div>
            <div class="flex flex-1 min-h-0">
                <div id="cond-tabs" class="w-44 flex-shrink-0 border-r border-[#e2e4e7] overflow-y-auto py-2 bg-white"></div>
                <div id="cond-content" class="flex-1 overflow-y-auto p-5 bg-white"></div>
                <div class="w-72 flex-shrink-0 border-l border-[#e2e4e7] bg-[#f6f7f7] overflow-y-auto">
                    <div class="px-4 py-3 border-b border-[#e2e4e7]"><h3 class="text-[14px] font-semibold text-[#1d2327]">Manage Conditions</h3></div>
                    <div class="p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8c8f94] mb-2">Include</p>
                        <div id="cond-include" class="space-y-2 mb-5"></div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8c8f94] mb-2">Exclude</p>
                        <div id="cond-exclude" class="space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const slotSections   = @json($slotSectionsJson);
        const slotLabels     = @json(collect($slotMeta)->map(fn($m) => $m['label']));
        const slotIcons      = @json(collect($slotMeta)->map(fn($m) => $m['icon']));
        const URL_SECTION_CREATE = @json(route('admin.falcon-builder.section.create'));
        const layoutAssigned = @json($layoutAssignedJson);
        const condTabs       = @json($conditionTabs);
        const layoutConditions = @json($layoutConditionsJson);
        const CSRF = '{{ csrf_token() }}';
        const URL_CONDITIONS = @json(route('admin.falcon-builder.layout.conditions'));
        const URL_COND_ITEMS = @json(route('admin.falcon-builder.condition-items'));
        const URL_RENAME     = @json(route('admin.falcon-builder.layout.rename'));
        const URL_SLOT_TOGGLE = @json(route('admin.falcon-builder.slot.toggle'));
        let activePicker = { layout: null, slot: null };

        const card = (lid) => document.getElementById('card-' + lid);
        function showState(lid, state) {
            const c = card(lid);
            ['.layout-view', '.layout-picker', '.layout-conditions'].forEach(sel => {
                const el = c.querySelector(sel); if (!el) return;
                const on = ('.' + state) === sel; el.classList.toggle('hidden', !on); el.classList.toggle('flex', on);
            });
        }
        function toast(msg, type = 'success') { if (window.showToast) window.showToast(msg, type); }

        // ── Section picker (in-card) ─────────────────────────────────────
        window.openPicker = function (lid, slot) {
            activePicker = { layout: lid, slot: slot };
            const c = card(lid);
            c.querySelector('.create-slot').value = slot;
            c.querySelector('.picker-slot-label').textContent = slotLabels[slot] || '';
            const list = c.querySelector('.existing-list'), empty = c.querySelector('.existing-empty');
            const items = slotSections[slot] || [], assignedId = (layoutAssigned[lid] || {})[slot];
            list.innerHTML = '';
            if (items.length === 0) { empty.classList.remove('hidden'); }
            else {
                empty.classList.add('hidden');
                items.forEach(s => {
                    const isCurrent = assignedId && Number(assignedId) === Number(s.id);
                    const li = document.createElement('li'); li.className = 'flex items-center';
                    li.innerHTML = `<button type="button" class="pick flex-1 text-left pl-5 pr-2 py-3 hover:bg-[#f6f7f7] flex items-center justify-between">
                        <span class="text-[13px] ${isCurrent ? 'text-[#2271b1] font-semibold' : 'text-[#3c434a]'}">${s.title.replace(/</g,'&lt;')}</span>
                        <span class="text-[10px] uppercase tracking-wide ${s.status === 'published' ? 'text-[#00a32a]' : 'text-[#8c8f94]'}">${isCurrent ? 'In use' : (s.status === 'published' ? 'Active' : 'Draft')}</span></button>
                        <button type="button" class="del px-3 py-3 text-[#b32d2e] hover:text-[#d63638]" title="Delete section"><span class="material-symbols-outlined text-[18px] align-middle">delete</span></button>`;
                    li.querySelector('.pick').addEventListener('click', () => selectExisting(s.id));
                    li.querySelector('.del').addEventListener('click', () => deleteSectionConfirm(s.id, s.title));
                    list.appendChild(li);
                });
            }
            pickerTab(lid, 'new'); showState(lid, 'layout-picker');
        };
        window.closeState = function (lid) { showState(lid, 'layout-view'); };
        window.pickerTab = function (lid, which) {
            const c = card(lid), isNew = which === 'new';
            c.querySelector('.picker-new').classList.toggle('hidden', !isNew);
            c.querySelector('.picker-existing').classList.toggle('hidden', isNew);
            const nb = c.querySelector('.tab-btn-new'), eb = c.querySelector('.tab-btn-existing');
            nb.classList.toggle('bg-[#2271b1]', isNew); nb.classList.toggle('bg-[#2c92e0]', !isNew);
            eb.classList.toggle('bg-[#2271b1]', !isNew); eb.classList.toggle('bg-[#2c92e0]', isNew);
        };
        window.selectExisting = function (sectionId) {
            if (!activePicker.layout) return;
            document.getElementById('assign-layout').value = activePicker.layout;
            document.getElementById('assign-slot').value = activePicker.slot;
            document.getElementById('assign-section-id').value = sectionId;
            document.getElementById('assign-form').submit();
        };

        // Create a new section via AJAX (no navigation). It's created + assigned to
        // the slot; the user then clicks the section to open the page builder.
        window.createSectionAjax = function (lid) {
            const c = card(lid);
            const slot = c.querySelector('.create-slot').value;
            const nameEl = c.querySelector('.create-name');
            const name = (nameEl.value || '').trim();
            if (!name) { nameEl.focus(); toast('Please enter a section name.', 'error'); return; }
            const btn = c.querySelector('.picker-new button');
            if (btn) btn.disabled = true;
            fetch(URL_SECTION_CREATE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ layout: lid, slot: slot, name: name }),
            }).then(r => r.json()).then(d => {
                if (btn) btn.disabled = false;
                if (!d.ok) { toast(d.message || 'Could not create section.', 'error'); return; }
                const sec = d.section;
                (slotSections[slot] = slotSections[slot] || []).push({ id: sec.id, title: sec.title, status: sec.status });
                (layoutAssigned[lid] = layoutAssigned[lid] || {})[slot] = sec.id;
                setSlotAssigned(lid, slot, sec);
                nameEl.value = '';
                closeState(lid);
                toast(d.message || 'Section created.', 'success');
            }).catch(() => { if (btn) btn.disabled = false; toast('Could not create section.', 'error'); });
        };

        // Rebuild a slot row into its "assigned" state after an AJAX create.
        window.setSlotAssigned = function (lid, slot, sec) {
            const row = card(lid).querySelector('.layout-view [data-slot="' + slot + '"]');
            if (!row) return;
            const icon = slotIcons[slot] || 'article';
            const label = slotLabels[slot] || '';
            const title = String(sec.title || label).replace(/</g, '&lt;');
            row.innerHTML =
                '<div class="flex items-center justify-center w-11 bg-[#f6f7f7] text-[#646970] border-r border-[#e2e4e7]"><span class="material-symbols-outlined text-[20px]">' + icon + '</span></div>' +
                '<a href="' + sec.edit_url + '" class="flex-1 flex flex-col justify-center px-3 py-1.5 leading-tight hover:text-[#2271b1]">' +
                    '<span class="text-[13px] text-[#1d2327]">' + title + '</span>' +
                    '<span class="slot-status text-[10px] uppercase tracking-wide text-[#00a32a]" data-slot-label="' + label + '">Active · ' + label + '</span>' +
                '</a>' +
                '<div class="flex items-center gap-1.5 pr-2.5">' +
                    '<label class="relative inline-flex items-center cursor-pointer" title="Turn this section on/off for this layout only">' +
                        '<input type="checkbox" class="sr-only peer" checked onchange="slotToggle(\'' + lid + '\',\'' + slot + '\',this)">' +
                        '<div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#2271b1]"></div>' +
                    '</label>' +
                    '<button type="button" onclick="openPicker(\'' + lid + '\',\'' + slot + '\')" title="Change section" class="text-[#646970] hover:text-[#2271b1]"><span class="material-symbols-outlined text-[20px]">swap_horiz</span></button>' +
                '</div>';
        };
        window.deleteSectionConfirm = async function (sectionId, title) {
            const ok = window.falconConfirm ? await window.falconConfirm({ title: 'Delete Section', message: `Delete the section “${title}”? It will be removed from any layout that uses it. This cannot be undone.`, confirmText: 'Delete', isDanger: true }) : confirm(`Delete “${title}”?`);
            if (!ok) return;
            document.getElementById('delete-section-id').value = sectionId;
            document.getElementById('delete-section-form').submit();
        };
        // Inline rename: click the name → edit in place → blur/Enter saves via AJAX.
        window.startRenameLayout = function (el) {
            if (el.querySelector('input')) return;               // already editing
            const lid = el.dataset.lid;
            const current = el.textContent.trim();
            const input = document.createElement('input');
            input.type = 'text';
            input.value = current;
            input.maxLength = 255;
            input.className = 'text-[15px] font-semibold text-[#1d2327] bg-white rounded-sm px-2 py-0.5 outline-none';
            input.style.width = Math.min(300, Math.max(120, current.length * 9 + 24)) + 'px';
            el.textContent = '';
            el.classList.remove('cursor-text', 'hover:bg-white/10');
            el.appendChild(input);
            input.focus();
            input.select();

            let done = false;
            const finish = (save) => {
                if (done) return;
                done = true;
                const name = input.value.trim();
                el.classList.add('cursor-text', 'hover:bg-white/10');
                if (!save || name === '' || name === current) {
                    el.textContent = current;
                    return;
                }
                el.textContent = name;
                const cardEl = card(lid);
                const picker = cardEl && cardEl.querySelector('.picker-name');
                if (picker) picker.textContent = name;
                fetch(URL_RENAME, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ id: lid, name: name }),
                }).then(r => r.json()).then(d => {
                    if (d.ok) { toast('Layout renamed.', 'success'); }
                    else { el.textContent = current; if (picker) picker.textContent = current; toast(d.message || 'Rename failed.', 'error'); }
                }).catch(() => { el.textContent = current; if (picker) picker.textContent = current; toast('Rename failed.', 'error'); });
            };
            input.addEventListener('blur', () => finish(true));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
                else if (e.key === 'Escape') { done = true; el.textContent = current; el.classList.add('cursor-text', 'hover:bg-white/10'); }
            });
        };

        // AJAX per-layout slot on/off — no page reload.
        window.slotToggle = function (lid, slot, checkbox) {
            const want = checkbox.checked;
            checkbox.disabled = true;
            fetch(URL_SLOT_TOGGLE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ layout: lid, slot: slot }),
            }).then(r => r.json()).then(d => {
                checkbox.disabled = false;
                if (!d.ok) { checkbox.checked = !want; toast(d.message || 'Could not update.', 'error'); return; }
                checkbox.checked = !!d.active;
                const row = checkbox.closest('.items-stretch');
                const st = row && row.querySelector('.slot-status');
                if (st) {
                    st.textContent = (d.active ? 'Active' : 'Inactive') + ' · ' + (st.dataset.slotLabel || '');
                    st.classList.toggle('text-[#00a32a]', !!d.active);
                    st.classList.toggle('text-[#8c8f94]', !d.active);
                }
                toast(d.message || 'Updated.', 'success');
            }).catch(() => { checkbox.disabled = false; checkbox.checked = !want; toast('Could not update.', 'error'); });
        };

        window.deleteLayout = async function (lid, name) {
            const ok = window.falconConfirm ? await window.falconConfirm({ title: 'Delete Layout', message: `Delete the layout “${name}”? Its section assignments and conditions will be removed (the sections themselves stay).`, confirmText: 'Delete', isDanger: true }) : confirm(`Delete the layout “${name}”?`);
            if (ok) document.getElementById('del-' + lid).submit();
        };

        // ── Layout Conditions modal ──────────────────────────────────────
        let condLayout = null;        // layout id being edited
        let condState = [];           // [{mode,target,label}]
        let condActiveTab = null;
        const itemState = {};         // tab key -> {page, s, loaded:[]}

        const modal = () => document.getElementById('conditions-modal');
        const findCond = (t) => condState.find(c => c.target === t);

        window.openConditionsModal = function (lid) {
            condLayout = lid;
            condState = JSON.parse(JSON.stringify(layoutConditions[lid] || []));
            for (const k in itemState) delete itemState[k];
            renderCondTabs();
            selectCondTab(condTabs[0] ? condTabs[0].key : null);
            renderManage();
            modal().classList.remove('hidden');
        };
        window.closeConditionsModal = function () { modal().classList.add('hidden'); };
        modal().addEventListener('click', e => { if (e.target === modal()) closeConditionsModal(); });

        function renderCondTabs() {
            const el = document.getElementById('cond-tabs'); el.innerHTML = '';
            condTabs.forEach(tab => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'w-full text-left px-5 py-2.5 text-[14px] ' + (tab.key === condActiveTab ? 'bg-[#f0f6fb] text-[#2271b1] font-semibold' : 'text-[#3c434a] hover:bg-[#f6f7f7]');
                b.textContent = tab.label;
                b.addEventListener('click', () => selectCondTab(tab.key));
                el.appendChild(b);
            });
        }
        function selectCondTab(key) { condActiveTab = key; renderCondTabs(); renderCondContent(); }

        function toggleRow(target, label) {
            const cur = findCond(target);
            return `<div class="flex items-center gap-1.5 py-1.5">
                <button type="button" data-t="${encodeURIComponent(target)}" data-m="include" class="cbtn w-6 h-6 rounded border flex items-center justify-center ${cur && cur.mode==='include' ? 'bg-[#00a32a] border-[#00a32a] text-white' : 'border-[#c3c4c7] text-[#c3c4c7] hover:text-[#00a32a]'}"><span class="material-symbols-outlined text-[16px]">check</span></button>
                <button type="button" data-t="${encodeURIComponent(target)}" data-m="exclude" class="cbtn w-6 h-6 rounded border flex items-center justify-center ${cur && cur.mode==='exclude' ? 'bg-[#d63638] border-[#d63638] text-white' : 'border-[#c3c4c7] text-[#c3c4c7] hover:text-[#d63638]'}"><span class="material-symbols-outlined text-[16px]">close</span></button>
                <span class="text-[13px] text-[#3c434a] ml-1">${label.replace(/</g,'&lt;')}</span>
            </div>`;
        }
        function bindToggles(scope) {
            scope.querySelectorAll('.cbtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const target = decodeURIComponent(btn.dataset.t);
                    const mode = btn.dataset.m;
                    const label = btn.closest('div').querySelector('span.text-\\[13px\\]')?.textContent || target;
                    setCondition(target, mode, label);
                });
            });
        }
        function renderCondContent() {
            const tab = condTabs.find(t => t.key === condActiveTab);
            const el = document.getElementById('cond-content');
            el.innerHTML = '';
            if (!tab) return;
            (tab.blocks || []).forEach(block => {
                if (block.type === 'toggle') {
                    const w = document.createElement('div');
                    w.innerHTML = toggleRow(block.target, block.label);
                    const node = w.firstElementChild;
                    el.appendChild(node);
                    bindToggles(node);
                } else if (block.type === 'group') {
                    const box = document.createElement('div');
                    box.className = 'mt-3 mb-1 border border-[#e2e4e7] rounded-sm';
                    box.innerHTML = `<div class="px-3 py-2 bg-[#f6f7f7] border-b border-[#e2e4e7] text-[12px] font-semibold text-[#50575e]">${(block.label||'').replace(/</g,'&lt;')}</div>
                        <div class="p-3">
                            ${block.search ? '<input type="text" class="cond-search wp-input w-full mb-2" placeholder="Search…">' : ''}
                            <div class="cond-items space-y-1"></div>
                            <button type="button" class="cond-more hidden w-full mt-2 py-1.5 text-[12px] text-white bg-[#2271b1] hover:bg-[#135e96] rounded-sm">Load more</button>
                        </div>`;
                    el.appendChild(box);
                    setupGroup(box, block.source || {}, !!block.search);
                }
            });
        }
        function setupGroup(box, source, hasSearch) {
            const searchEl = box.querySelector('.cond-search');
            const itemsEl = box.querySelector('.cond-items');
            const moreEl = box.querySelector('.cond-more');
            let page = 1, term = '';
            const load = (reset) => {
                if (reset) { page = 1; itemsEl.innerHTML = ''; }
                const qs = new URLSearchParams({ kind: source.kind || 'post_type', key: source.key || '', s: term, page: page });
                fetch(`${URL_COND_ITEMS}?${qs.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json()).then(d => {
                        (d.items || []).forEach(it => { const w = document.createElement('div'); w.innerHTML = toggleRow(it.target, it.label); const node = w.firstElementChild; itemsEl.appendChild(node); bindToggles(node); });
                        moreEl.classList.toggle('hidden', !d.has_more);
                        if (!(d.items || []).length && page === 1) itemsEl.innerHTML = '<p class="text-[12px] text-[#8c8f94] italic px-1">None found.</p>';
                    });
            };
            if (searchEl) { let t; searchEl.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { term = searchEl.value.trim(); load(true); }, 250); }); }
            moreEl.addEventListener('click', () => { page++; load(false); });
            load(true);
        }

        function setCondition(target, mode, label) {
            const cur = findCond(target);
            if (cur && cur.mode === mode) {
                condState = condState.filter(c => c.target !== target);   // toggle off
            } else {
                condState = condState.filter(c => c.target !== target);
                condState.push({ mode, target, label });
            }
            renderCondContent(); renderManage(); saveConditions();
        }

        function renderManage() {
            const inc = document.getElementById('cond-include'), exc = document.getElementById('cond-exclude');
            const chip = (c) => {
                const d = document.createElement('div');
                d.className = 'flex items-center justify-between bg-white border border-[#e2e4e7] rounded-sm px-3 py-2';
                d.innerHTML = `<span class="text-[13px] text-[#3c434a] truncate">${c.label.replace(/</g,'&lt;')}</span><button type="button" class="text-[#8c8f94] hover:text-[#d63638] ml-2"><span class="material-symbols-outlined text-[18px]">close</span></button>`;
                d.querySelector('button').addEventListener('click', () => { condState = condState.filter(x => !(x.target === c.target && x.mode === c.mode)); renderCondContent(); renderManage(); saveConditions(); });
                return d;
            };
            inc.innerHTML = ''; exc.innerHTML = '';
            const incs = condState.filter(c => c.mode === 'include'), excs = condState.filter(c => c.mode === 'exclude');
            if (!incs.length) inc.innerHTML = '<p class="text-[12px] text-[#8c8f94] italic">Nothing included yet.</p>';
            incs.forEach(c => inc.appendChild(chip(c)));
            if (!excs.length) exc.innerHTML = '<p class="text-[12px] text-[#8c8f94] italic">Nothing excluded.</p>';
            excs.forEach(c => exc.appendChild(chip(c)));
        }

        let saveTimer = null;
        function saveConditions() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                fetch(URL_CONDITIONS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ id: condLayout, conditions: condState.map(c => ({ mode: c.mode, target: c.target })) }),
                }).then(r => r.json()).then(d => {
                    if (d.ok) {
                        layoutConditions[condLayout] = d.conditions || [];
                        updateCardSummary(condLayout);
                        toast('Conditions saved.', 'success');
                    } else { toast(d.message || 'Could not save conditions.', 'error'); }
                }).catch(() => toast('Could not save conditions.', 'error'));
            }, 200);
        }
        function updateCardSummary(lid) {
            const c = card(lid); if (!c) return;
            const footer = c.querySelector('.layout-view > div:last-child'); if (!footer) return;
            const list = layoutConditions[lid] || [];
            const incs = list.filter(x => x.mode === 'include').map(x => x.label);
            const exc = list.filter(x => x.mode === 'exclude').length;
            const dot = incs.length ? 'bg-[#00a32a]' : 'bg-[#8c8f94]';
            const text = incs.length ? (incs.join(', ') + (exc ? ` · ${exc} excluded` : '')) : 'No condition selected';
            footer.innerHTML = `<span class="w-2 h-2 rounded-full ${dot} flex-shrink-0"></span><span class="truncate">${text.replace(/</g,'&lt;')}</span>`;
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConditionsModal(); });
    </script>
</x-falcon-cms::layouts.admin>
