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
    @php $libLocked = ! falcon_pro_editable('builder_pro'); @endphp

    @if($libLocked)
    <div class="mb-5 flex items-center gap-3 rounded-lg border border-[#f0c47a] bg-[#fdf6e9] px-4 py-3">
        <span class="shrink-0 material-symbols-outlined text-[#c98a1a]" style="font-size:24px">lock</span>
        <div class="flex-1">
            <div class="text-[13.5px] font-bold text-[#5b4a1f]">You're viewing the Library in preview</div>
            <div class="text-[12.5px] text-[#7a663a]">Browse your saved items freely — creating, editing or deleting needs Pro.</div>
        </div>
        <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="shrink-0 rounded-md bg-[#e8912b] px-4 py-2 text-[12.5px] font-bold text-[#171c23] hover:brightness-105 no-underline">Upgrade to Pro</a>
    </div>
    @endif

    {{-- ── Header ── --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-[23px] font-normal text-[#1d2327] mb-0.5">Builder Library</h1>
            <p class="text-[13px] text-[#646970]">Manage saved builder items, post cards, mega menus and off-canvas panels.</p>
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
            <button id="btn-new-off-canvas" onclick="openOffCanvasModal()" style="display:none"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> New Off-Canvas
            </button>
            <form id="btn-import-off-canvas" action="{{ route('admin.falcon-builder.off-canvas.import') }}" method="POST" enctype="multipart/form-data" class="inline" style="display:none">
                @csrf
                <input type="file" name="library_file" accept=".json,application/json" class="hidden" id="oc-import-file" onchange="this.form.submit()">
                <button type="button" onclick="document.getElementById('oc-import-file').click()"
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
            'off_canvas'     => ['label' => 'Off-Canvas',     'icon' => 'side_navigation', 'count' => count($offCanvases)],
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
                    <div class="lib-card relative bg-white border border-[#dcdcde] rounded overflow-hidden group" id="item-{{ $item['id'] }}">
                        @if($libLocked)<span class="absolute top-2 right-2 z-10 flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[9px] font-bold uppercase tracking-wide shadow-sm" title="Pro feature"><span class="material-symbols-outlined" style="font-size:11px">lock</span> Pro</span>@endif
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
                    <div class="lib-card relative bg-white border border-[#dcdcde] rounded overflow-hidden group" id="pcard-{{ $card['id'] }}">
                        @if($libLocked)<span class="absolute top-2 right-2 z-10 flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[9px] font-bold uppercase tracking-wide shadow-sm" title="Pro feature"><span class="material-symbols-outlined" style="font-size:11px">lock</span> Pro</span>@endif
                        <div class="p-4">
                            <p class="text-[13px] font-semibold text-[#1d2327] truncate leading-snug mb-0.5" title="{{ $card['name'] }}">{{ $card['name'] }}</p>
                            <p class="text-[11px] text-[#9ca3af] mb-4">{{ $card['created_at'] }}</p>
                            <div class="flex gap-2">
                                <a href="{{ $libLocked ? '#' : route('admin.falcon-builder.post-cards.builder', $card['id']) }}"
                                   @if($libLocked) onclick="event.preventDefault(); window.showToast && window.showToast('This feature is available in the Pro version.','error');" @endif
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
                    <div class="lib-card relative bg-white border border-[#dcdcde] rounded overflow-hidden group" id="mmcard-{{ $mm['id'] }}">
                        @if($libLocked)<span class="absolute top-2 right-2 z-10 flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[9px] font-bold uppercase tracking-wide shadow-sm" title="Pro feature"><span class="material-symbols-outlined" style="font-size:11px">lock</span> Pro</span>@endif
                        <div class="p-4">
                            <p class="text-[13px] font-semibold text-[#1d2327] truncate leading-snug mb-0.5" title="{{ $mm['name'] }}">{{ $mm['name'] }}</p>
                            <p class="text-[11px] text-[#9ca3af] mb-4">{{ $mm['created_at'] }}</p>
                            <div class="flex gap-2">
                                <a href="{{ $libLocked ? '#' : route('admin.falcon-builder.mega-menus.builder', $mm['id']) }}"
                                   @if($libLocked) onclick="event.preventDefault(); window.showToast && window.showToast('This feature is available in the Pro version.','error');" @endif
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

        {{-- ── Off-Canvas Tab ── --}}
        <div id="panel-off_canvas" class="tab-panel p-6" style="display:none">
            @if(count($offCanvases) === 0)
                <div class="py-20 text-center">
                    <span class="material-symbols-outlined text-[56px] text-[#c3c4c7] block mb-4">side_navigation</span>
                    <p class="text-[15px] font-semibold text-[#50575e] mb-1">No off-canvas panels yet</p>
                    <p class="text-[13px] text-[#9ca3af] mb-6 max-w-[520px] mx-auto">Design slide-in drawers and popups with the builder — a mobile menu, a cart sidebar, a newsletter popup. Open one from any link, button or menu item.</p>
                    <button onclick="openOffCanvasModal()"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">add</span> Create Your First Off-Canvas
                    </button>
                </div>
            @else
                <div class="mb-5 flex items-start gap-3 rounded border border-[#c5d9ed] bg-[#f0f6fb] px-4 py-3 text-[12.5px] text-[#1d4f7a]">
                    <span class="material-symbols-outlined text-[18px] text-[#2271b1] shrink-0">info</span>
                    <div>
                        <strong>How to open a panel:</strong> set any link, button or menu item URL to its trigger (e.g. <code class="px-1 bg-white rounded">#offcanvas-mobile-menu</code>),
                        or pick <em>Open Off-Canvas</em> from a link's dynamic source. A link to <code class="px-1 bg-white rounded">#offcanvas-close</code> inside a panel closes it.
                        A panel only loads on pages that link to it — or that it opens on by itself.
                    </div>
                </div>
                @php
                    $ocPosLabels  = ['left' => 'Left Drawer', 'right' => 'Right Drawer', 'top' => 'Top Bar', 'bottom' => 'Bottom Bar', 'center' => 'Popup'];
                    $ocTrigLabels = ['load' => 'Auto: on load', 'scroll' => 'Auto: on scroll', 'exit' => 'Auto: exit intent'];
                    $ocMinis      = [
                        'left'   => 'left:12px;top:12px;bottom:12px;width:32%;',
                        'right'  => 'right:12px;top:12px;bottom:12px;width:32%;',
                        'top'    => 'left:12px;right:12px;top:12px;height:34%;',
                        'bottom' => 'left:12px;right:12px;bottom:12px;height:34%;',
                        'center' => 'left:32%;right:32%;top:24%;bottom:24%;border-radius:4px;',
                    ];
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    @foreach($offCanvases as $oc)
                    @php
                        $ocS      = \FalconCms\Core\Support\OffCanvas::settings($oc['config']['settings'] ?? []);
                        $ocOn     = $oc['enabled'] ?? true;
                        $ocAnchor = \FalconCms\Core\Support\OffCanvas::anchor($oc);
                        $ocEmpty  = empty($oc['config']['layout']);
                    @endphp
                    <div class="lib-card relative bg-white border border-[#dcdcde] rounded overflow-hidden group {{ $ocOn ? '' : 'opacity-60' }}" id="occard-{{ $oc['id'] }}">
                        @if($libLocked)<span class="absolute top-2 right-2 z-10 flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[9px] font-bold uppercase tracking-wide shadow-sm" title="Pro feature"><span class="material-symbols-outlined" style="font-size:11px">lock</span> Pro</span>@endif

                        {{-- Miniature of where the panel sits on screen --}}
                        <div class="relative h-24 bg-gradient-to-br from-[#f9fafb] to-[#eef0f2] border-b border-[#f0f0f1] overflow-hidden">
                            <div class="absolute inset-3 rounded-sm bg-white/70 border border-[#e2e4e7]"></div>
                            <div class="absolute bg-[#2271b1]/80 group-hover:bg-[#2271b1] transition-colors" style="{{ $ocMinis[$ocS['position']] }}"></div>
                            <span class="absolute bottom-1.5 left-2 text-[10px] font-semibold text-[#50575e] bg-white/90 px-1.5 rounded">{{ $ocPosLabels[$ocS['position']] }}</span>
                            @if($ocS['trigger'] !== 'none')
                            <span class="absolute top-1.5 left-2 text-[10px] font-semibold text-[#8a5a00] bg-[#fdf3dc] px-1.5 rounded flex items-center gap-0.5"><span class="material-symbols-outlined" style="font-size:11px">bolt</span>{{ $ocTrigLabels[$ocS['trigger']] }}</span>
                            @endif
                        </div>

                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2 mb-0.5">
                                <p class="text-[13px] font-semibold text-[#1d2327] truncate leading-snug" title="{{ $oc['name'] }}">{{ $oc['name'] }}</p>
                                <label class="relative inline-flex items-center cursor-pointer shrink-0" title="Enabled — a disabled panel never loads">
                                    <input type="checkbox" class="sr-only" {{ $ocOn ? 'checked' : '' }} onchange="toggleOffCanvas('{{ $oc['id'] }}', this)">
                                    <span class="oc-toggle w-8 h-[18px] rounded-full relative transition-colors {{ $ocOn ? 'bg-[#2271b1]' : 'bg-[#c3c4c7]' }}">
                                        <span class="absolute top-[2px] w-[14px] h-[14px] rounded-full bg-white shadow transition-all" style="left:{{ $ocOn ? '16px' : '2px' }}"></span>
                                    </span>
                                </label>
                            </div>
                            <p class="text-[11px] text-[#9ca3af] mb-2">{{ $oc['created_at'] }}@if($ocEmpty) · <span class="text-[#b26200]">empty — design it</span>@endif</p>

                            <button type="button" onclick="copyOcTrigger(this, '{{ $ocAnchor }}')" title="Copy the trigger link"
                                    class="w-full mb-3 flex items-center justify-between gap-2 px-2 py-1.5 rounded border border-dashed border-[#c3c4c7] bg-[#f9fafb] hover:border-[#2271b1] hover:bg-[#f0f6fb] transition-colors">
                                <code class="text-[11px] text-[#2271b1] truncate">{{ $ocAnchor }}</code>
                                <span class="material-symbols-outlined text-[14px] text-[#646970] oc-copy-icon">content_copy</span>
                            </button>

                            <div class="flex gap-2">
                                <a href="{{ $libLocked ? '#' : route('admin.falcon-builder.off-canvas.builder', $oc['id']) }}"
                                   @if($libLocked) onclick="event.preventDefault(); window.showToast && window.showToast('This feature is available in the Pro version.','error');" @endif
                                   class="flex-1 py-1.5 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">edit</span> Edit
                                </a>
                                <button onclick="duplicateOffCanvas('{{ $oc['id'] }}')" title="Duplicate"
                                        class="py-1.5 px-2 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[13px]">library_add</span>
                                </button>
                                <a href="{{ route('admin.falcon-builder.off-canvas.export', $oc['id']) }}" title="Export as .json"
                                   class="py-1.5 px-2 rounded text-[11px] font-semibold border border-[#c3c4c7] bg-white text-[#50575e] hover:bg-[#f0f0f1] hover:border-[#8c8f94] transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[13px]">download</span>
                                </a>
                                <button onclick="deleteOffCanvas('{{ $oc['id'] }}')" title="Delete"
                                        class="py-1.5 px-2 rounded text-[11px] font-semibold border border-red-100 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white hover:border-red-500 transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[13px]">delete</span>
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

{{-- ════════════════════════════════════════════════════
     Off-Canvas Creation Modal
════════════════════════════════════════════════════ --}}
<div id="offCanvasModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm" style="display:none!important">
    <div class="bg-white w-[95vw] max-w-[680px] max-h-[90vh] flex flex-col rounded-lg shadow-2xl overflow-hidden">

        {{-- Modal Header --}}
        <div class="bg-[#1d2327] text-white px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#72aee6]">side_navigation</span>
                <h3 class="text-[14px] font-bold uppercase tracking-widest">New Off-Canvas</h3>
            </div>
            <button onclick="closeOffCanvasModal()" class="text-white/50 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 overflow-y-auto">
            <label class="block text-[12px] font-bold text-[#1d2327] uppercase tracking-wider mb-2">Name</label>
            <input type="text" id="oc-name" placeholder="e.g. Mobile Menu, Newsletter Popup, Cart Drawer…"
                   class="w-full border border-[#c3c4c7] rounded px-3 py-2.5 text-[13px] text-[#1d2327] focus:outline-none focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1]/20"
                   onkeydown="if(event.key==='Enter') saveOffCanvas()">

            <label class="block text-[12px] font-bold text-[#1d2327] uppercase tracking-wider mt-5 mb-2">Type</label>
            <div class="grid grid-cols-5 gap-2">
                @foreach([
                    'left'   => ['Left', 'left:4px;top:4px;bottom:4px;width:34%;'],
                    'right'  => ['Right', 'right:4px;top:4px;bottom:4px;width:34%;'],
                    'top'    => ['Top', 'left:4px;right:4px;top:4px;height:34%;'],
                    'bottom' => ['Bottom', 'left:4px;right:4px;bottom:4px;height:34%;'],
                    'center' => ['Popup', 'left:26%;right:26%;top:22%;bottom:22%;border-radius:3px;'],
                ] as $ocPos => [$ocLabel, $ocMini])
                <label class="style-opt cursor-pointer">
                    <input type="radio" name="oc-position" value="{{ $ocPos }}" class="sr-only" {{ $ocPos === 'right' ? 'checked' : '' }}>
                    <div class="border-2 border-[#dcdcde] rounded p-2 text-center transition-colors">
                        <div class="relative h-12 bg-[#f0f0f1] rounded-sm mb-1.5">
                            <div class="absolute bg-[#2271b1]" style="{{ $ocMini }}"></div>
                        </div>
                        <span class="text-[11px] font-semibold">{{ $ocLabel }}</span>
                    </div>
                </label>
                @endforeach
            </div>
            <p class="text-[12px] text-[#646970] mt-3">After creating, design the content in the builder, then set size, overlay, animation and auto-open rules under <strong>Panel Options</strong>.</p>
        </div>

        {{-- Modal Footer --}}
        <div class="shrink-0 px-6 py-4 bg-[#f9fafb] border-t border-[#f0f0f1] flex items-center justify-between">
            <button onclick="closeOffCanvasModal()" class="px-5 py-2 text-[13px] font-semibold text-[#50575e] hover:text-[#1d2327] transition-colors">Cancel</button>
            <button onclick="saveOffCanvas()"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">save</span> Create &amp; Design
            </button>
        </div>
    </div>
</div>

<script>
const ALL_TABS = ['containers', 'columns', 'nested_columns', 'elements', 'post_cards', 'mega_menus', 'off_canvas'];

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
    const btnOc = document.getElementById('btn-new-off-canvas');
    const impOc = document.getElementById('btn-import-off-canvas');
    if (btnOc) btnOc.style.display = key === 'off_canvas' ? '' : 'none';
    if (impOc) impOc.style.display = key === 'off_canvas' ? 'inline' : 'none';
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
    @if($libLocked ?? false) window.showToast && window.showToast('This feature is available in the Pro version.', 'error'); return; @endif
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
    @if($libLocked ?? false) window.showToast && window.showToast('This feature is available in the Pro version.', 'error'); return; @endif
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

// ── Off-Canvas ─────────────────────────────────────────
const OC_LOCKED  = @json($libLocked);
const ocHeaders  = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' };
const ocUrls     = {
    update:    '{{ route("admin.falcon-builder.off-canvas.update", ["id" => "__I__"]) }}',
    remove:    '{{ route("admin.falcon-builder.off-canvas.delete", ["id" => "__I__"]) }}',
    duplicate: '{{ route("admin.falcon-builder.off-canvas.duplicate", ["id" => "__I__"]) }}',
};
const ocUrl      = (name, id) => ocUrls[name].replace('__I__', id);
const ocProToast = () => { window.showToast && window.showToast('This feature is available in the Pro version.', 'error'); };

function openOffCanvasModal() {
    if (OC_LOCKED) { ocProToast(); return; }
    const m = document.getElementById('offCanvasModal');
    m.style.removeProperty('display');
    m.style.display = 'flex';
    document.getElementById('oc-name').focus();
}
function closeOffCanvasModal() {
    document.getElementById('offCanvasModal').style.display = 'none';
    document.getElementById('oc-name').value = '';
}
document.getElementById('offCanvasModal').addEventListener('click', e => { if (e.target === document.getElementById('offCanvasModal')) closeOffCanvasModal(); });

function saveOffCanvas() {
    const name = document.getElementById('oc-name').value.trim();
    if (!name) { document.getElementById('oc-name').focus(); return; }
    const position = (document.querySelector('input[name="oc-position"]:checked') || {}).value || 'right';
    fetch('{{ route("admin.falcon-builder.off-canvas.save") }}', {
        method: 'POST', headers: ocHeaders, body: JSON.stringify({ name, position })
    }).then(r => r.json()).then(d => {
        // Straight into the builder: a panel with nothing in it is not worth a stop in the list.
        if (d.success && d.builder_url) window.location.href = d.builder_url;
        else if (d.pro) ocProToast();
    });
}

function toggleOffCanvas(id, input) {
    if (OC_LOCKED) { input.checked = !input.checked; ocProToast(); return; }
    const on = input.checked;
    fetch(ocUrl('update', id), { method: 'PATCH', headers: ocHeaders, body: JSON.stringify({ enabled: on }) })
        .then(r => r.json()).then(d => {
            if (!d.success) { input.checked = !on; if (d.pro) ocProToast(); return; }
            document.getElementById('occard-' + id)?.classList.toggle('opacity-60', !on);
            const track = input.nextElementSibling;
            track.classList.toggle('bg-[#2271b1]', on);
            track.classList.toggle('bg-[#c3c4c7]', !on);
            track.firstElementChild.style.left = on ? '16px' : '2px';
        });
}

function duplicateOffCanvas(id) {
    if (OC_LOCKED) { ocProToast(); return; }
    fetch(ocUrl('duplicate', id), { method: 'POST', headers: ocHeaders })
        .then(r => r.json()).then(d => { if (d.success) location.href = '{{ route("admin.falcon-builder.library") }}?tab=off_canvas'; });
}

function deleteOffCanvas(id) {
    if (!confirm('Delete this off-canvas? Links pointing at it will stop opening anything.')) return;
    fetch(ocUrl('remove', id), { method: 'DELETE', headers: ocHeaders })
        .then(r => r.json()).then(d => { if (d.success) document.getElementById('occard-' + id)?.remove(); else if (d.pro) ocProToast(); });
}

function copyOcTrigger(btn, text) {
    const done = () => {
        const icon = btn.querySelector('.oc-copy-icon');
        if (icon) { icon.textContent = 'check'; setTimeout(() => { icon.textContent = 'content_copy'; }, 1500); }
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
        return;
    }
    const t = document.createElement('textarea');
    t.value = text; document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    t.remove();
}

// Init
switchTab('{{ request()->query("tab", "containers") }}');
</script>
</x-falcon-cms::layouts.admin>
