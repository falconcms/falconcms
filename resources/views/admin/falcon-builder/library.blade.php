<x-falcon-cms::layouts.admin title="Builder Library">
<style>
.lib-tab-btn { border-bottom: 2px solid transparent; transition: all .15s; }
.lib-tab-btn.active { border-color: #2271b1; color: #2271b1; }
.lib-tab-btn:not(.active) { color: #50575e; }
.lib-tab-btn:not(.active):hover { color: #1d2327; border-color: #c3c4c7; }
.lib-card { transition: all .18s; }
.lib-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.10); border-color: #2271b1; transform: translateY(-2px); }
.field-toggle input:checked ~ .toggle-track { background: #2271b1; }
.toggle-track { transition: background .2s; }
.pc-layout-opt input:checked ~ div { border-color: #2271b1; background: #f0f6fb; }
.style-opt input:checked ~ div { border-color: #2271b1; background: #f0f6fb; color: #2271b1; }
</style>

<div class="p-6 bg-[#f0f0f1] min-h-screen">

    {{-- ── Header ── --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-[23px] font-normal text-[#1d2327] mb-0.5">Builder Library</h1>
            <p class="text-[13px] text-[#646970]">Manage saved builder items, post cards, and mega menus.</p>
        </div>
        <div class="flex items-center gap-3">
            <nav class="text-[12px] text-[#646970]">Falcon Builder / Library</nav>
            <button id="btn-new-post-card" onclick="openCardModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> New Post Card
            </button>
            <form id="btn-import-post-card" action="{{ route('admin.falcon-builder.post-cards.import') }}" method="POST" enctype="multipart/form-data" class="inline">
                @csrf
                <input type="file" name="library_file" accept=".json,application/json" class="hidden" id="pc-import-file" onchange="this.form.submit()">
                <button type="button" onclick="document.getElementById('pc-import-file').click()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] text-[13px] font-semibold rounded transition-colors">
                    <span class="material-symbols-outlined text-[16px]">upload</span> Import
                </button>
            </form>
            <button id="btn-new-mega-menu" onclick="openMegaMenuModal()" style="display:none"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> New Mega Menu
            </button>
            <form id="btn-import-mega-menu" action="{{ route('admin.falcon-builder.mega-menus.import') }}" method="POST" enctype="multipart/form-data" class="inline" style="display:none">
                @csrf
                <input type="file" name="library_file" accept=".json,application/json" class="hidden" id="mm-import-file" onchange="this.form.submit()">
                <button type="button" onclick="document.getElementById('mm-import-file').click()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] text-[13px] font-semibold rounded transition-colors">
                    <span class="material-symbols-outlined text-[16px]">upload</span> Import
                </button>
            </form>
        </div>
    </div>

    {{-- ── Tabs ── --}}
    @php
        $tabs = [
            'containers'     => ['label' => 'Containers',     'icon' => 'table_chart',   'count' => count($library['containers'])],
            'columns'        => ['label' => 'Columns',        'icon' => 'view_column',   'count' => count($library['columns'])],
            'nested_columns' => ['label' => 'Nested Columns', 'icon' => 'grid_view',     'count' => count($library['nested_columns'])],
            'elements'       => ['label' => 'Elements',       'icon' => 'widgets',       'count' => count($library['elements'])],
            'post_cards'     => ['label' => 'Post Cards',     'icon' => 'style',         'count' => count($postCards)],
            'mega_menus'     => ['label' => 'Mega Menus',     'icon' => 'view_quilt',    'count' => count($megaMenus)],
        ];
        $libraryItems = [
            'containers'     => $library['containers'],
            'columns'        => $library['columns'],
            'nested_columns' => $library['nested_columns'],
            'elements'       => $library['elements'],
        ];
    @endphp

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-2.5 rounded text-[13px]">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-2.5 rounded text-[13px]">{{ session('error') }}</div>
    @endif

    <div class="bg-white border border-[#c3c4c7] rounded-sm shadow-sm overflow-hidden">

        {{-- Tab Bar --}}
        <div class="flex border-b border-[#c3c4c7] px-4 bg-[#f9fafb]">
            @foreach($tabs as $key => $tab)
            <button onclick="switchTab('{{ $key }}')" id="tab-{{ $key }}"
                    class="lib-tab-btn flex items-center gap-2 px-5 py-3.5 text-[13px] font-semibold -mb-px">
                <span class="material-symbols-outlined text-[16px]">{{ $tab['icon'] }}</span>
                {{ $tab['label'] }}
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold
                             {{ $tab['count'] > 0 ? 'bg-[#2271b1]/10 text-[#2271b1]' : 'bg-[#f0f0f1] text-[#646970]' }}">
                    {{ $tab['count'] }}
                </span>
            </button>
            @endforeach
        </div>

        {{-- ── Library Tabs (Containers / Columns / Nested / Elements) ── --}}
        @foreach($libraryItems as $key => $items)
        @php $meta = $tabs[$key]; @endphp
        <div id="panel-{{ $key }}" class="tab-panel p-6" style="display:none">
            @if(count($items) === 0)
                <div class="py-20 text-center">
                    <span class="material-symbols-outlined text-[56px] text-[#c3c4c7] block mb-4">inventory_2</span>
                    <p class="text-[15px] font-semibold text-[#50575e] mb-1">No saved {{ strtolower($meta['label']) }} yet</p>
                    <p class="text-[13px] text-[#9ca3af]">Use the <strong>Library</strong> icon on any {{ rtrim(strtolower($meta['label']), 's') }} toolbar in the builder.</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
                    @foreach($items as $item)
                    <div class="lib-card bg-white border border-[#dcdcde] rounded overflow-hidden group" id="item-{{ $item['id'] }}">
                        <div class="bg-gradient-to-br from-[#f9fafb] to-[#f0f0f1] h-28 flex items-center justify-center border-b border-[#f0f0f1]">
                            <span class="material-symbols-outlined text-[44px] text-[#c3c4c7] group-hover:text-[#2271b1]/40 transition-colors">{{ $meta['icon'] }}</span>
                        </div>
                        <div class="p-3">
                            <p class="text-[13px] font-semibold text-[#1d2327] truncate leading-snug" title="{{ $item['name'] }}">{{ $item['name'] }}</p>
                            <p class="text-[11px] text-[#9ca3af] mt-0.5 mb-3">{{ $item['created_at'] }}</p>
                            <button onclick="deleteLibItem('{{ $key }}', '{{ $item['id'] }}')"
                                    class="w-full py-1.5 rounded text-[11px] font-semibold border border-red-100 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white hover:border-red-500 transition-colors flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-[13px]">delete</span> Delete
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
        @endforeach

        {{-- ── Post Cards Tab ── --}}
        <div id="panel-post_cards" class="tab-panel p-6" style="display:none">
            @if(count($postCards) === 0)
                <div class="py-20 text-center">
                    <span class="material-symbols-outlined text-[56px] text-[#c3c4c7] block mb-4">style</span>
                    <p class="text-[15px] font-semibold text-[#50575e] mb-1">No post cards yet</p>
                    <p class="text-[13px] text-[#9ca3af] mb-6">Design reusable card layouts for displaying posts in grids and lists.</p>
                    <button onclick="openCardModal()"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">add</span> Create Your First Post Card
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    @foreach($postCards as $card)
                    @php $cfg = $card['config']; @endphp
                    <div class="lib-card bg-white border border-[#dcdcde] rounded overflow-hidden group" id="pcard-{{ $card['id'] }}">
                        <div class="p-4">
                            <p class="text-[13px] font-semibold text-[#1d2327] truncate leading-snug mb-0.5" title="{{ $card['name'] }}">{{ $card['name'] }}</p>
                            <p class="text-[11px] text-[#9ca3af] mb-4">{{ $card['created_at'] }}</p>
                            <div class="flex gap-2">
                                <a href="{{ route('admin.falcon-builder.post-cards.builder', $card['id']) }}"
                                   class="flex-1 py-1.5 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">edit</span> Edit
                                </a>
                                <a href="{{ route('admin.falcon-builder.post-cards.export', $card['id']) }}" title="Export as .json"
                                   class="py-1.5 px-2 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[13px]">download</span>
                                </a>
                                <button onclick="deletePostCard('{{ $card['id'] }}')"
                                        class="flex-1 py-1.5 rounded text-[11px] font-semibold border border-red-100 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white hover:border-red-500 transition-colors flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">delete</span> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Mega Menus Tab ── --}}
        <div id="panel-mega_menus" class="tab-panel p-6" style="display:none">
            @if(count($megaMenus) === 0)
                <div class="py-20 text-center">
                    <span class="material-symbols-outlined text-[56px] text-[#c3c4c7] block mb-4">view_quilt</span>
                    <p class="text-[15px] font-semibold text-[#50575e] mb-1">No mega menus yet</p>
                    <p class="text-[13px] text-[#9ca3af] mb-6">Design full-width dropdown panels for your header navigation. Assign them to menu items via Appearance → Menus.</p>
                    <button onclick="openMegaMenuModal()"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">add</span> Create Your First Mega Menu
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    @foreach($megaMenus as $mm)
                    <div class="lib-card bg-white border border-[#dcdcde] rounded overflow-hidden group" id="mmcard-{{ $mm['id'] }}">
                        <div class="p-4">
                            <p class="text-[13px] font-semibold text-[#1d2327] truncate leading-snug mb-0.5" title="{{ $mm['name'] }}">{{ $mm['name'] }}</p>
                            <p class="text-[11px] text-[#9ca3af] mb-4">{{ $mm['created_at'] }}</p>
                            <div class="flex gap-2">
                                <a href="{{ route('admin.falcon-builder.mega-menus.builder', $mm['id']) }}"
                                   class="flex-1 py-1.5 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">edit</span> Edit
                                </a>
                                <a href="{{ route('admin.falcon-builder.mega-menus.export', $mm['id']) }}" title="Export as .json"
                                   class="py-1.5 px-2 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[13px]">download</span>
                                </a>
                                <button onclick="deleteMegaMenu('{{ $mm['id'] }}')"
                                        class="flex-1 py-1.5 rounded text-[11px] font-semibold border border-red-100 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white hover:border-red-500 transition-colors flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">delete</span> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     Post Card Creation Modal
════════════════════════════════════════════════════ --}}
<div id="cardModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm" style="display:none!important">
    <div class="bg-white w-[95vw] max-w-[680px] max-h-[90vh] flex flex-col rounded-lg shadow-2xl overflow-hidden">

        {{-- Modal Header --}}
        <div class="bg-[#1d2327] text-white px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#72aee6]">style</span>
                <h3 class="text-[14px] font-bold uppercase tracking-widest">New Post Card</h3>
            </div>
            <button onclick="closeCardModal()" class="text-white/50 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="p-6">
            <label class="block text-[12px] font-bold text-[#1d2327] uppercase tracking-wider mb-2">Card Name</label>
            <input type="text" id="pc-name" placeholder="e.g. Blog Card, Product Card…"
                   class="w-full border border-[#c3c4c7] rounded px-3 py-2.5 text-[13px] text-[#1d2327] focus:outline-none focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1]/20">
        </div>

        {{-- Modal Footer --}}
        <div class="shrink-0 px-6 py-4 bg-[#f9fafb] border-t border-[#f0f0f1] flex items-center justify-between">
            <button onclick="closeCardModal()" class="px-5 py-2 text-[13px] font-semibold text-[#50575e] hover:text-[#1d2327] transition-colors">Cancel</button>
            <button onclick="savePostCard()"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">save</span> Save Post Card
            </button>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════
     Mega Menu Creation Modal
════════════════════════════════════════════════════ --}}
<div id="megaMenuModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm" style="display:none!important">
    <div class="bg-white w-[95vw] max-w-[680px] max-h-[90vh] flex flex-col rounded-lg shadow-2xl overflow-hidden">

        {{-- Modal Header --}}
        <div class="bg-[#1d2327] text-white px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#72aee6]">view_quilt</span>
                <h3 class="text-[14px] font-bold uppercase tracking-widest">New Mega Menu</h3>
            </div>
            <button onclick="closeMegaMenuModal()" class="text-white/50 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="p-6">
            <label class="block text-[12px] font-bold text-[#1d2327] uppercase tracking-wider mb-2">Mega Menu Name</label>
            <input type="text" id="mm-name" placeholder="e.g. Products Mega Menu, Categories Mega Menu…"
                   class="w-full border border-[#c3c4c7] rounded px-3 py-2.5 text-[13px] text-[#1d2327] focus:outline-none focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1]/20"
                   onkeydown="if(event.key==='Enter') saveMegaMenu()">
            <p class="text-[12px] text-[#646970] mt-2">After creating, design the layout in the builder. Then assign it to a top-level menu item via Appearance → Menus → Options.</p>
        </div>

        {{-- Modal Footer --}}
        <div class="shrink-0 px-6 py-4 bg-[#f9fafb] border-t border-[#f0f0f1] flex items-center justify-between">
            <button onclick="closeMegaMenuModal()" class="px-5 py-2 text-[13px] font-semibold text-[#50575e] hover:text-[#1d2327] transition-colors">Cancel</button>
            <button onclick="saveMegaMenu()"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">save</span> Create &amp; Design
            </button>
        </div>
    </div>
</div>

<script>
const ALL_TABS = ['containers', 'columns', 'nested_columns', 'elements', 'post_cards', 'mega_menus'];

function switchTab(key) {
    ALL_TABS.forEach(t => {
        const panel = document.getElementById('panel-' + t);
        const btn   = document.getElementById('tab-' + t);
        if (!panel || !btn) return;
        panel.style.display = t === key ? 'block' : 'none';
        btn.classList.toggle('active', t === key);
    });
    // Toggle header action buttons
    const btnCard = document.getElementById('btn-new-post-card');
    const btnMega = document.getElementById('btn-new-mega-menu');
    const impCard = document.getElementById('btn-import-post-card');
    const impMega = document.getElementById('btn-import-mega-menu');
    if (btnCard) btnCard.style.display = key === 'post_cards' ? '' : 'none';
    if (btnMega) btnMega.style.display = key === 'mega_menus' ? '' : 'none';
    if (impCard) impCard.style.display = key === 'post_cards' ? 'inline' : 'none';
    if (impMega) impMega.style.display = key === 'mega_menus' ? 'inline' : 'none';
}

function deleteLibItem(type, id) {
    if (!confirm('Delete this item from the library?')) return;
    fetch('{{ route("admin.falcon-builder.library.delete", ["type" => "__T__", "id" => "__I__"]) }}'
        .replace('__T__', type).replace('__I__', id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if (d.success) document.getElementById('item-' + id)?.remove(); });
}

function deletePostCard(id) {
    if (!confirm('Delete this post card?')) return;
    fetch('{{ route("admin.falcon-builder.post-cards.delete", ["id" => "__I__"]) }}'.replace('__I__', id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if (d.success) document.getElementById('pcard-' + id)?.remove(); });
}

// ── Modal ──────────────────────────────────────────────
function openCardModal() {
    const m = document.getElementById('cardModal');
    m.style.removeProperty('display');
    m.style.display = 'flex';
}
function closeCardModal() {
    document.getElementById('cardModal').style.display = 'none';
}
document.getElementById('cardModal').addEventListener('click', e => { if (e.target === document.getElementById('cardModal')) closeCardModal(); });

function savePostCard() {
    const name = document.getElementById('pc-name').value.trim();
    if (!name) { document.getElementById('pc-name').focus(); return; }

    fetch('{{ route("admin.falcon-builder.post-cards.save") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ name, config: {} })
    }).then(r => r.json()).then(d => {
        if (d.success) { closeCardModal(); location.reload(); }
    });
}


// ── Mega Menu Modal ────────────────────────────────────
function openMegaMenuModal() {
    const m = document.getElementById('megaMenuModal');
    m.style.removeProperty('display');
    m.style.display = 'flex';
    document.getElementById('mm-name').focus();
}
function closeMegaMenuModal() {
    document.getElementById('megaMenuModal').style.display = 'none';
    document.getElementById('mm-name').value = '';
}
document.getElementById('megaMenuModal').addEventListener('click', e => { if (e.target === document.getElementById('megaMenuModal')) closeMegaMenuModal(); });

function saveMegaMenu() {
    const name = document.getElementById('mm-name').value.trim();
    if (!name) { document.getElementById('mm-name').focus(); return; }

    fetch('{{ route("admin.falcon-builder.mega-menus.save") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ name, config: {} })
    }).then(r => r.json()).then(d => {
        if (d.success) { closeMegaMenuModal(); location.reload(); }
    });
}

function deleteMegaMenu(id) {
    if (!confirm('Delete this mega menu?')) return;
    fetch('{{ route("admin.falcon-builder.mega-menus.delete", ["id" => "__I__"]) }}'.replace('__I__', id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if (d.success) document.getElementById('mmcard-' + id)?.remove(); });
}

// Init
switchTab('{{ request()->query("tab", "containers") }}');
</script>
</x-falcon-cms::layouts.admin>
