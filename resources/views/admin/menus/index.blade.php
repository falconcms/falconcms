<x-falcon-cms::layouts.admin title="Menus">
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/font-awesome.all.min.css') }}">
    <x-falcon-cms::admin.delete-modal />
    <div class="mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Menus</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    <!-- Select Menu Bar -->
    <div class="bg-[#f6f7f7] border border-[#dcdcde] p-3 mb-5 flex flex-wrap items-center text-[13px] gap-3">
        <label class="font-medium">Select a menu to edit:</label>
        <form action="{{ route('admin.menus.index') }}" method="GET" class="flex items-center gap-2">
            <select name="menu" class="wp-input h-7 py-0 px-2 min-w-[150px]" onchange="this.form.submit()">
                <option value="">— Select —</option>
                @foreach($menus as $m)
                    <option value="{{ $m->id }}" {{ (isset($menu) && $menu->id == $m->id) ? 'selected' : '' }}>{{ $m->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="wp-btn-secondary h-7 px-3">Select</button>
        </form>
        <span class="text-[#646970]">or</span>
        <button onclick="document.getElementById('create-form').classList.toggle('hidden')" class="text-[#2271b1] hover:underline">create a new menu</button>
    </div>

    <!-- Create Menu -->
    <div id="create-form" class="hidden bg-white border border-[#dcdcde] p-4 mb-5 shadow-sm">
        <form action="{{ route('admin.menus.store') }}" method="POST">
            @csrf
            <div class="flex items-center gap-3">
                <input name="name" class="wp-input w-64 h-8" placeholder="Menu Name" required>
                <div class="flex items-center gap-4 text-[13px] text-[#646970]">
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" name="is_header" value="1"> Header Menu
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" name="is_footer" value="1"> Footer Menu
                    </label>
                </div>
                <button class="wp-btn-primary h-8 px-4">Create Menu</button>
                <button type="button" onclick="document.getElementById('create-form').classList.add('hidden')" class="text-[#b32d2e] text-[13px]">Cancel</button>
            </div>
        </form>
    </div>

    @if($menu)
    <div class="flex flex-col lg:flex-row gap-5">

        <!-- LEFT: Add Items -->
        <div class="w-full lg:w-[280px] shrink-0 space-y-3">
            <p class="text-[14px] font-bold">Add menu items</p>

            @php
            $accordions = [
                ['key'=>'pages',      'label'=>'Pages',        'items'=>$pages,      'titleField'=>'title', 'slugField'=>'slug'],
                ['key'=>'posts',      'label'=>'Posts',        'items'=>$posts,      'titleField'=>'title', 'slugField'=>'slug'],
                ['key'=>'categories', 'label'=>'Categories',   'items'=>$categories, 'titleField'=>'name',  'slugField'=>'slug'],
            ];
            @endphp

            @foreach($accordions as $i => $acc)
            <div class="wp-metabox mb-0">
                <div class="wp-metabox-header flex justify-between items-center lazy-acc-header"
                     onclick="toggleAcc('{{ $acc['key'] }}')">
                    <span>{{ $acc['label'] }}</span>
                    <svg id="acc-icon-{{ $acc['key'] }}" class="w-4 h-4 {{ $i===0 ? 'rotate-180' : '' }} transition-transform"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
                <div id="acc-{{ $acc['key'] }}" class="{{ $i===0 ? '' : 'hidden' }} wp-metabox-content p-3" data-acc="{{ $acc['key'] }}">
                    <div class="max-h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-[#fcfcfc] space-y-1 text-[13px]">
                        @forelse($acc['items'] as $it)
                        @php
                            $url = url($it->{$acc['slugField']});
                            if ($acc['key'] === 'categories') {
                                $url = url('category/' . $it->getFullSlugPath());
                            }
                        @endphp
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]"
                                data-title="{{ $it->{$acc['titleField']} }}"
                                data-url="{{ $url }}"
                                data-type="{{ $acc['key'] === 'categories' ? 'category' : rtrim($acc['key'], 's') }}"
                                data-oid="{{ $it->id }}">
                            {{ $it->{$acc['titleField']} }}
                        </label>
                        @empty
                        <p class="text-[#646970] italic text-[12px]">None found.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-between items-center">
                        <label class="text-[12px] text-[#2271b1] cursor-pointer">
                            <input type="checkbox" onchange="selectAll(this,'{{ $acc['key'] }}')" class="mr-1">Select All
                        </label>
                        <button onclick="addChecked(this)" class="wp-btn-secondary h-7 py-0 px-3 text-[12px]">Add to Menu</button>
                    </div>
                </div>
            </div>
            @endforeach

            {{-- Dynamic CPTs --}}
            @foreach($cptData as $cpt)
            <div class="wp-metabox mb-0">
                <div class="wp-metabox-header flex justify-between items-center lazy-acc-header"
                     onclick="toggleAcc('{{ $cpt['key'] }}')">
                    <span>{{ $cpt['label'] }}</span>
                    <svg id="acc-icon-{{ $cpt['key'] }}" class="w-4 h-4 transition-transform"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
                <div id="acc-{{ $cpt['key'] }}" class="hidden wp-metabox-content p-3" data-acc="{{ $cpt['key'] }}">
                    <div class="max-h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-[#fcfcfc] space-y-1 text-[13px]">
                        @forelse($cpt['items'] as $it)
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]"
                                data-title="{{ $it->title }}"
                                data-url="{{ url($cpt['type'] . '/' . $it->slug) }}"
                                data-type="{{ $cpt['type'] }}"
                                data-oid="{{ $it->id }}">
                            {{ $it->title }}
                        </label>
                        @empty
                        <p class="text-[#646970] italic text-[12px]">None found.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-between items-center">
                        <label class="text-[12px] text-[#2271b1] cursor-pointer">
                            <input type="checkbox" onchange="selectAll(this,'{{ $cpt['key'] }}')" class="mr-1">Select All
                        </label>
                        <button onclick="addChecked(this)" class="wp-btn-secondary h-7 py-0 px-3 text-[12px]">Add to Menu</button>
                    </div>
                </div>
            </div>
            @endforeach

            {{-- Dynamic Custom Taxonomies --}}
            @foreach($taxonomyData as $tax)
            <div class="wp-metabox mb-0">
                <div class="wp-metabox-header flex justify-between items-center lazy-acc-header"
                     onclick="toggleAcc('{{ $tax['key'] }}')">
                    <span>{{ $tax['label'] }}</span>
                    <svg id="acc-icon-{{ $tax['key'] }}" class="w-4 h-4 transition-transform"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
                <div id="acc-{{ $tax['key'] }}" class="hidden wp-metabox-content p-3" data-acc="{{ $tax['key'] }}">
                    <div class="max-h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-[#fcfcfc] space-y-1 text-[13px]">
                        @forelse($tax['items'] as $it)
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]"
                                data-title="{{ $it->name }}"
                                data-url="{{ url($tax['slug'] . '/' . $it->getFullSlugPath()) }}"
                                data-type="category"
                                data-oid="{{ $it->id }}"
                                data-source="{{ $tax['label'] }}">
                            {{ $it->name }}
                        </label>
                        @empty
                        <p class="text-[#646970] italic text-[12px]">None found.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-between items-center">
                        <label class="text-[12px] text-[#2271b1] cursor-pointer">
                            <input type="checkbox" onchange="selectAll(this,'{{ $tax['key'] }}')" class="mr-1">Select All
                        </label>
                        <button onclick="addChecked(this)" class="wp-btn-secondary h-7 py-0 px-3 text-[12px]">Add to Menu</button>
                    </div>
                </div>
            </div>
            @endforeach

            <!-- Product Categories -->
            <div class="wp-metabox mb-0">
                <div class="wp-metabox-header flex justify-between items-center lazy-acc-header" onclick="toggleAcc('product_categories')">
                    <span>Product Categories</span>
                    <svg id="acc-icon-product_categories" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div id="acc-product_categories" class="hidden wp-metabox-content p-3" data-acc="product_categories">
                    <div class="max-h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-[#fcfcfc] space-y-1 text-[13px]">
                        @forelse($productCategories as $pc)
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]"
                                data-title="{{ $pc->name }}"
                                data-url="{{ url('product-category/' . $pc->getFullSlugPath()) }}"
                                data-type="custom">
                            {{ $pc->name }}
                        </label>
                        @empty
                        <p class="text-[#646970] italic text-[12px]">None found.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-between items-center">
                        <label class="text-[12px] text-[#2271b1] cursor-pointer">
                            <input type="checkbox" onchange="selectAll(this,'product_categories')" class="mr-1">Select All
                        </label>
                        <button onclick="addChecked(this)" class="wp-btn-secondary h-7 py-0 px-3 text-[12px]">Add to Menu</button>
                    </div>
                </div>
            </div>

            <!-- Falcon Special Menu (dynamic ecommerce widgets) -->
            <div class="wp-metabox mb-0">
                <div class="wp-metabox-header flex justify-between items-center lazy-acc-header" onclick="toggleAcc('special')">
                    <span>Falcon Special Menu</span>
                    <svg id="acc-icon-special" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div id="acc-special" class="hidden wp-metabox-content p-3" data-acc="special">
                    <div class="max-h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-[#fcfcfc] space-y-1 text-[13px]">
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]" data-title="Cart" data-url="#" data-type="special_cart">
                            Cart
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]" data-title="Search" data-url="#" data-type="special_search">
                            Search
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer hover:text-[#2271b1]">
                            <input type="checkbox" class="item-cb rounded-sm border-[#8c8f94]" data-title="Wishlist" data-url="#" data-type="special_wishlist">
                            Wishlist
                        </label>
                    </div>
                    <div class="flex justify-between items-center">
                        <label class="text-[12px] text-[#2271b1] cursor-pointer">
                            <input type="checkbox" onchange="selectAll(this,'special')" class="mr-1">Select All
                        </label>
                        <button onclick="addChecked(this)" class="wp-btn-secondary h-7 py-0 px-3 text-[12px]">Add to Menu</button>
                    </div>
                </div>
            </div>

            <!-- Custom Link -->
            <div class="wp-metabox mb-0">
                <div class="wp-metabox-header flex justify-between items-center lazy-acc-header" onclick="toggleAcc('custom')">
                    <span>Custom Links</span>
                    <svg id="acc-icon-custom" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div id="acc-custom" class="hidden wp-metabox-content p-3 space-y-3 text-[13px]" data-acc="custom">
                    <div>
                        <label class="block text-[12px] text-[#646970] mb-1">URL</label>
                        <input id="cl-url" class="wp-input w-full h-7" placeholder="https:// or #" value="#">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#646970] mb-1">Link Text</label>
                        <input id="cl-title" class="wp-input w-full h-7">
                    </div>
                    <div class="text-right">
                        <button onclick="addCustom()" class="wp-btn-secondary h-7 py-0 px-3 text-[12px]">Add to Menu</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Accordion toggle — self-contained & placed right after the cards so it works
             reliably even if the larger script at the bottom of the page is interrupted. --}}
        <script>
        (function(){
            var ACC_STORE = 'lazyMenuAccordions';
            function getAccState(){ try { return JSON.parse(localStorage.getItem(ACC_STORE) || '{}') || {}; } catch(e){ return {}; } }
            function saveAccState(key, isOpen){ var s = getAccState(); s[key] = isOpen; try { localStorage.setItem(ACC_STORE, JSON.stringify(s)); } catch(e){} }

            window.toggleAcc = function(key){
                var body = document.getElementById('acc-' + key), icon = document.getElementById('acc-icon-' + key);
                if (!body) return;
                body.classList.toggle('hidden');
                var isOpen = !body.classList.contains('hidden');
                if (icon) icon.style.transform = isOpen ? 'rotate(180deg)' : '';
                saveAccState(key, isOpen);
            };

            window.selectAll = function(cb, key){
                document.querySelectorAll('#acc-' + key + ' .item-cb').forEach(function(c){ c.checked = cb.checked; });
            };

            function restoreAccordions(){
                var s = getAccState();
                document.querySelectorAll('.wp-metabox-content[data-acc]').forEach(function(body){
                    var key = body.getAttribute('data-acc');
                    if (!(key in s)) return;
                    var open = !!s[key];
                    body.classList.toggle('hidden', !open);
                    var icon = document.getElementById('acc-icon-' + key);
                    if (icon) icon.style.transform = open ? 'rotate(180deg)' : '';
                });
            }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', restoreAccordions);
            else restoreAccordions();
        })();
        </script>

        <!-- RIGHT: Menu Structure -->
        <div class="w-full lg:w-1/2">
            <form id="save-form" action="{{ route('admin.menus.update', $menu->id) }}" method="POST">
                @csrf @method('PUT')
                <input type="hidden" name="menu_items" id="menu-payload">
                <input type="hidden" name="name" id="name-hidden" value="{{ $menu->name }}">

                <div class="bg-white border border-[#dcdcde] shadow-sm">
                    <!-- Header -->
                    <div class="bg-[#f6f7f7] border-b border-[#dcdcde] p-3 flex flex-wrap justify-between items-center gap-3">
                        <div class="flex items-center gap-3">
                            <label class="text-[13px] font-bold">Menu Name</label>
                            <input id="menu-name" value="{{ $menu->name }}"
                                   oninput="document.getElementById('name-hidden').value=this.value"
                                   class="wp-input h-8 w-56 text-[13px]">
                        </div>
                        <button type="button" onclick="doSave()" class="wp-btn-primary h-8 px-4">Save Menu</button>
                    </div>

                    <!-- Body -->
                    <div class="p-4">
                        <p class="text-[13px] text-[#646970] mb-1">
                            Drag items to reorder. Use <strong>→</strong> to make an item a sub-item of the one above it. Use <strong>←</strong> to move it back.
                        </p>
                        <p class="text-[12px] text-[#646970] mb-4 border-b pb-3">Max nesting depth: 2 levels (parent → child → grandchild).</p>

                        <div id="empty-note" class="bg-[#fcfcfc] border-2 border-dashed border-[#dfdfdf] rounded p-10 text-center text-[#646970] text-[13px] hidden">
                            Your menu is empty. Add items from the left column.
                        </div>

                        <!-- Flat sortable list -->
                        <ul id="menu-list" class="list-none p-0 m-0 min-h-[10px] mb-8"></ul>

                        <!-- Menu Settings -->
                        <div class="mt-8 pt-4 border-t border-[#dfdfdf]">
                            <p class="text-[14px] font-bold mb-3">Menu Settings</p>
                            <div class="flex flex-col gap-2 text-[13px] text-[#1d2327]">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="is_header" value="1" {{ $menu->is_header ? 'checked' : '' }} class="rounded-sm border-[#8c8f94]">
                                    <span>Header Menu</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="is_footer" value="1" {{ $menu->is_footer ? 'checked' : '' }} class="rounded-sm border-[#8c8f94]">
                                    <span>Footer Menu</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-[#f6f7f7] border-t border-[#dcdcde] p-3 flex justify-between items-center">
                        <button type="button" onclick="doDelete()" class="text-[#b32d2e] text-[13px] underline">Delete Menu</button>
                        <button type="button" onclick="doSave()" class="wp-btn-primary h-8 px-4">Save Menu</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @else
    <div class="bg-white border-2 border-dashed border-[#dfdfdf] rounded p-20 text-center shadow-sm">
        <svg class="w-16 h-16 text-[#c3c4c7] mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <h2 class="text-[18px] font-medium text-[#2c3338] mb-2">No Menu Selected</h2>
        <p class="text-[14px] text-[#646970] mb-6">Select a menu from the dropdown above, or create a new one.</p>
        <button onclick="document.getElementById('create-form').classList.toggle('hidden')" class="wp-btn-primary px-5 py-2">Create New Menu</button>
    </div>
    @endif

    {{-- Menu item Options modal: icon picker + show-only-icon toggle + mega menu --}}
    @php
        $megaMenusList = json_decode(get_cms_option('lazy_mega_menus', '[]'), true) ?: [];
    @endphp
    <div id="mi-options-modal" style="display:none;position:fixed;inset:0;z-index:100000;align-items:center;justify-content:center;padding:20px;">
        <div onclick="closeItemOptions()" style="position:absolute;inset:0;background:rgba(0,0,0,.5);"></div>
        <div style="position:relative;background:#fff;width:100%;max-width:560px;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.3);display:flex;flex-direction:column;max-height:85vh;">
            <div style="padding:14px 18px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="font-size:15px;font-weight:700;color:#1d2327;margin:0;">Menu Item Options</h3>
                <button type="button" onclick="closeItemOptions()" style="border:none;background:none;font-size:20px;line-height:1;cursor:pointer;color:#646970;">&times;</button>
            </div>
            <div style="padding:16px 18px;overflow-y:auto;">
                {{-- Mega Menu section — only for top-level (depth=0) items --}}
                <div id="mi-mega-menu-section" style="margin-bottom:18px;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <span class="material-symbols-outlined" style="font-size:18px;color:#2271b1;">view_quilt</span>
                        <strong style="font-size:13px;color:#1d2327;">Mega Menu</strong>
                        <span style="font-size:11px;color:#646970;font-style:italic;">Desktop only</span>
                    </div>
                    <p style="font-size:12px;color:#646970;margin-bottom:10px;">Assign a mega menu layout to this top-level item. It replaces the standard dropdown on desktop.</p>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <select id="mi-mega-menu-select" onchange="setItemMegaMenu(this.value)"
                                style="flex:1;height:34px;border:1px solid #c3c4c7;border-radius:4px;padding:0 10px;font-size:13px;color:#1d2327;background:#fff;">
                            <option value="">— None —</option>
                            @foreach($megaMenusList as $mm)
                            <option value="{{ $mm['id'] }}">{{ $mm['name'] }}</option>
                            @endforeach
                        </select>
                        <a id="mi-mega-menu-edit-link" href="#" target="_blank" style="display:none;font-size:12px;color:#2271b1;white-space:nowrap;text-decoration:none;">
                            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">open_in_new</span> Edit Layout
                        </a>
                    </div>
                </div>
                <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px;font-size:13px;color:#1d2327;">
                    <span><strong>Show only Icon?</strong><br><span style="font-size:11px;color:#646970;">Yes = show only the icon in the menu; No = show both icon and title</span></span>
                    <input type="checkbox" id="mi-show-only-icon" onchange="setItemShowOnlyIcon(this.checked)" style="width:18px;height:18px;flex-shrink:0;">
                </label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px;">
                    <span style="font-weight:700;text-transform:uppercase;font-size:11px;color:#1d2327;">Selected:</span>
                    <span id="mi-selected-icon-preview" style="font-size:18px;color:#2271b1;min-width:22px;text-align:center;"></span>
                    <button type="button" onclick="setItemIcon('')" style="font-size:11px;color:#b32d2e;border:none;background:none;cursor:pointer;text-decoration:underline;">Clear</button>
                </div>
                <input type="text" id="mi-icon-search" oninput="renderIconGrid(this.value)" placeholder="Search icons (cart, home, user, search...)" class="wp-input" style="width:100%;height:34px;font-size:13px;margin-bottom:12px;">
                <div id="mi-icon-grid" style="display:grid;grid-template-columns:repeat(8,1fr);gap:6px;max-height:300px;overflow-y:auto;"></div>
            </div>
            <div style="padding:12px 18px;border-top:1px solid #e5e7eb;text-align:right;">
                <button type="button" onclick="closeItemOptions()" class="wp-btn-primary" style="height:32px;">Done</button>
            </div>
        </div>
    </div>

    <style>
        /* Accordion headers use their own toggle (toggleAcc); the class is intentionally NOT
           "cursor-pointer" so the global metabox-collapse script does not also bind to them. */
        .lazy-acc-header          { cursor: pointer; }
        #menu-list li             { list-style: none; }
        .mi-wrap                  { margin-bottom: 4px; }
        .mi-bar                   { display:flex; align-items:center; justify-content:space-between;
                                    padding:9px 12px; background:#fff; border:1px solid #dcdcde;
                                    cursor:move; user-select:none; }
        .mi-bar:hover             { background:#f9f9f9; }
        .mi-settings              { padding:14px 16px; border:1px solid #dcdcde; border-top:0; background:#f9f9f9; display:none; }
        .mi-settings.open         { display:block; }
        /* depth indentation */
        .mi-depth-1               { margin-left:32px; }
        .mi-depth-2               { margin-left:64px; }
        /* depth badge */
        .depth-badge              { font-size:11px; color:#646970; font-style:italic; margin-left:6px; }
        /* drag states */
        .sortable-ghost           { opacity:.4; background:#e8f0fe !important; border:1px dashed #2271b1 !important; }
        .sortable-chosen          { box-shadow:0 3px 10px rgba(34,113,177,.25); }
        /* indent controls */
        .indent-btn               { border:1px solid #c3c4c7; background:#f6f7f7; padding:2px 7px;
                                    border-radius:2px; font-size:12px; cursor:pointer; line-height:1.6; }
        .indent-btn:hover         { background:#e5e5e5; }
        /* orphaned items */
        .mi-bar.orphaned          { border-color:#d63638; background:#fff8f8; }
        .orphan-notice            { padding:7px 12px; font-size:12px; color:#d63638;
                                    background:#fff0f0; border:1px solid #d63638; border-top:0;
                                    display:flex; align-items:center; gap:6px; }
        .mi-bar.is-draft          { border-color:#dba617; background:#fffdf5; }
        .draft-notice             { padding:7px 12px; font-size:12px; color:#856404;
                                     background:#fff3cd; border:1px solid #ffeeba; border-top:0;
                                     display:flex; align-items:center; gap:6px; }
    </style>

    <script src="{{ asset('vendor/falcon-cms/js/sortable.min.js') }}"></script>
    <script>
    /* ──────────────────────────────────
       Data: flat array, each item has depth (0|1|2)
    ────────────────────────────────── */
    let items = {!! $menuItemsJson !!};
    let uid = Date.now();
    const newId = () => 'n' + (uid++);

    /* ──────────────────────────────────
       Accordion
    ────────────────────────────────── */
    /* toggleAcc / selectAll / accordion-state persistence are defined in the small
       self-contained script placed right after the cards (above), so they keep working
       even if this larger script is interrupted on a slow/dev server. */

    /* ──────────────────────────────────
       Add items
    ────────────────────────────────── */
    function addChecked(btn) {
        // Scope to the card the button belongs to, so each "Add to Menu" only adds
        // the items selected within THAT card (not across all cards).
        const scope = (btn && btn.closest('.wp-metabox-content')) || document;
        const cbs = scope.querySelectorAll('.item-cb:checked');
        if (!cbs.length) { window.showToast('Please select at least one item from this section.', 'warning'); return; }
        cbs.forEach(cb => {
            items.push({
                id: newId(),
                title: cb.dataset.title,
                url:   cb.dataset.url,
                type:  cb.dataset.type,
                object_id: cb.dataset.oid || null,
                source_label: cb.dataset.source || null,
                mega_menu_id: null,
                depth: 0
            });
            cb.checked = false;
        });
        render();
    }

    function addCustom() {
        const title = document.getElementById('cl-title').value.trim();
        const url   = document.getElementById('cl-url').value.trim() || '#';
        if (!title) { window.showToast('Please enter link text.', 'warning'); return; }
        items.push({ id: newId(), title, url, type: 'custom', depth: 0 });
        document.getElementById('cl-title').value = '';
        document.getElementById('cl-url').value   = '#';
        render();
    }

    /* ──────────────────────────────────
       Depth controls
    ────────────────────────────────── */
    function indent(id) {
        const idx = items.findIndex(i => i.id === id);
        if (idx <= 0) return;                           // can't indent first item
        const prev = items[idx - 1];
        const cur  = items[idx];
        if (cur.depth >= 2) return;                     // max depth
        if (cur.depth > prev.depth) return;             // can only go 1 deeper
        cur.depth++;
        render();
    }

    function outdent(id) {
        const idx = items.findIndex(i => i.id === id);
        if (idx < 0) return;
        if (items[idx].depth <= 0) return;
        items[idx].depth--;
        // Fix any children that would now be deeper than allowed
        for (let j = idx + 1; j < items.length; j++) {
            if (items[j].depth <= items[idx].depth) break;
            items[j].depth = Math.max(0, items[j].depth - 1);
        }
        render();
    }

    function removeItem(id) {
        items = items.filter(i => i.id !== id);
        render();
    }

    function updateField(id, field, val) {
        const item = items.find(i => i.id === id);
        if (item) item[field] = val;
    }

    /* ──────────────────────────────────
       Menu item Options (icon picker + show-only-icon)
    ────────────────────────────────── */
    const MI_ICONS = [
        'fa fa-house','fa fa-home','fa fa-bars','fa fa-grip','fa fa-table-cells','fa fa-list','fa fa-bars-staggered',
        'fa fa-magnifying-glass','fa fa-search','fa fa-cart-shopping','fa fa-bag-shopping','fa fa-basket-shopping','fa fa-store',
        'fa fa-heart','fa fa-star','fa fa-user','fa fa-users','fa fa-circle-user','fa fa-right-to-bracket','fa fa-right-from-bracket',
        'fa fa-gear','fa fa-sliders','fa fa-bell','fa fa-envelope','fa fa-phone','fa fa-location-dot','fa fa-map',
        'fa fa-tag','fa fa-tags','fa fa-folder','fa fa-folder-open','fa fa-file','fa fa-file-lines','fa fa-newspaper','fa fa-book',
        'fa fa-image','fa fa-images','fa fa-camera','fa fa-video','fa fa-music','fa fa-play','fa fa-podcast',
        'fa fa-info','fa fa-circle-info','fa fa-circle-question','fa fa-headset','fa fa-comment','fa fa-comments',
        'fa fa-gift','fa fa-truck','fa fa-box','fa fa-credit-card','fa fa-wallet','fa fa-percent','fa fa-fire','fa fa-bolt',
        'fa fa-globe','fa fa-language','fa fa-calendar','fa fa-clock','fa fa-bookmark','fa fa-thumbs-up','fa fa-share-nodes',
        'fa fa-arrow-right','fa fa-arrow-left','fa fa-chevron-right','fa fa-chevron-down','fa fa-angles-right','fa fa-link','fa fa-paperclip',
        'fa fa-laptop','fa fa-mobile','fa fa-desktop','fa fa-headphones','fa fa-keyboard','fa fa-plug','fa fa-wifi','fa fa-camera-retro',
        'fa fa-shirt','fa fa-gem','fa fa-crown','fa fa-utensils','fa fa-mug-hot','fa fa-cake-candles','fa fa-leaf','fa fa-seedling',
        'fa fa-dumbbell','fa fa-heart-pulse','fa fa-pills','fa fa-stethoscope','fa fa-graduation-cap','fa fa-briefcase','fa fa-building',
        'fab fa-facebook-f','fab fa-instagram','fab fa-x-twitter','fab fa-whatsapp','fab fa-youtube','fab fa-linkedin-in','fab fa-tiktok','fab fa-telegram','fab fa-pinterest-p'
    ];
    let miOptionsItemId = null;

    const MEGA_MENUS_DATA = @json($megaMenusList);
    const MEGA_MENU_BUILDER_BASE = '{{ route("admin.falcon-builder.mega-menus.builder", ["id" => "__ID__"]) }}';

    function openItemOptions(id) {
        miOptionsItemId = id;
        const item = items.find(i => i.id === id);
        if (!item) return;
        document.getElementById('mi-show-only-icon').checked = !!item.show_only_icon;
        document.getElementById('mi-icon-search').value = '';
        miUpdateSelectedPreview();
        renderIconGrid('');

        // Mega menu section: only show for top-level (depth=0) items
        const mmSection = document.getElementById('mi-mega-menu-section');
        if (mmSection) {
            const isTopLevel = (item.depth || 0) === 0;
            mmSection.style.display = isTopLevel ? '' : 'none';
            if (isTopLevel) {
                const sel = document.getElementById('mi-mega-menu-select');
                if (sel) sel.value = item.mega_menu_id || '';
                miUpdateMegaMenuEditLink(item.mega_menu_id || '');
            }
        }

        document.getElementById('mi-options-modal').style.display = 'flex';
    }

    function closeItemOptions() {
        document.getElementById('mi-options-modal').style.display = 'none';
        const reopen = miOptionsItemId;
        miOptionsItemId = null;
        render(); // refresh the Options button preview in the list
        if (reopen) toggleSettings(reopen); // keep that item's panel expanded
    }

    function miUpdateSelectedPreview() {
        const item = items.find(i => i.id === miOptionsItemId);
        const prev = document.getElementById('mi-selected-icon-preview');
        if (!prev) return;
        prev.innerHTML = (item && item.icon) ? `<i class="${esc(item.icon)}"></i>` : '<span style="font-size:12px;color:#646970;">none</span>';
    }

    function setItemIcon(icon) {
        if (!miOptionsItemId) return;
        updateField(miOptionsItemId, 'icon', icon);
        miUpdateSelectedPreview();
        renderIconGrid(document.getElementById('mi-icon-search').value || '');
    }

    function setItemShowOnlyIcon(checked) {
        if (!miOptionsItemId) return;
        updateField(miOptionsItemId, 'show_only_icon', !!checked);
    }

    function setItemMegaMenu(mmId) {
        if (!miOptionsItemId) return;
        updateField(miOptionsItemId, 'mega_menu_id', mmId || null);
        miUpdateMegaMenuEditLink(mmId);
    }

    function miUpdateMegaMenuEditLink(mmId) {
        const link = document.getElementById('mi-mega-menu-edit-link');
        if (!link) return;
        if (mmId) {
            link.href = MEGA_MENU_BUILDER_BASE.replace('__ID__', mmId);
            link.style.display = '';
        } else {
            link.style.display = 'none';
        }
    }

    function renderIconGrid(query) {
        const grid = document.getElementById('mi-icon-grid');
        if (!grid) return;
        const item = items.find(i => i.id === miOptionsItemId);
        const cur = item ? item.icon : '';
        const q = (query || '').toLowerCase().trim();
        const list = q ? MI_ICONS.filter(ic => ic.toLowerCase().includes(q)) : MI_ICONS;
        grid.innerHTML = list.map(ic => {
            const active = (ic === cur);
            return `<button type="button" onclick="setItemIcon('${ic}')" title="${ic}"
                style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;border:1px solid ${active ? '#2271b1' : '#e5e7eb'};background:${active ? '#eaf3fb' : '#fff'};border-radius:6px;cursor:pointer;font-size:16px;color:${active ? '#2271b1' : '#555'};">
                <i class="${ic}"></i></button>`;
        }).join('') || '<p style="grid-column:1/-1;text-align:center;color:#646970;font-size:12px;padding:20px;">No icons found.</p>';
    }

    function toggleSettings(id) {
        const panel = document.getElementById('s-' + id);
        if (!panel) return;
        panel.classList.toggle('open');
        const icon = document.getElementById('ti-' + id);
        if (icon) icon.style.transform = panel.classList.contains('open') ? 'rotate(180deg)' : '';
    }

    /* ──────────────────────────────────
       Render
    ────────────────────────────────── */
    const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    const typeLabel = t => ({ custom:'Custom Link', page:'Page', post:'Post', category:'Category', special_cart:'Cart Widget', special_search:'Search Widget', special_wishlist:'Wishlist Widget' }[t] || t);

    function render() {
        const list    = document.getElementById('menu-list');
        const empty   = document.getElementById('empty-note');
        if (!list) return;

        list.innerHTML = '';

        if (!items.length) {
            empty && empty.classList.remove('hidden');
            return;
        }
        empty && empty.classList.add('hidden');

        items.forEach((item, idx) => {
            const li = document.createElement('li');
            li.dataset.id = item.id;

            const depthClass = item.depth > 0 ? `mi-depth-${item.depth}` : '';
            const depthBadge = item.depth === 1 ? '— sub item' : item.depth === 2 ? '— sub-sub item' : '';
            const orphaned   = !!item.orphaned;
            const isDraft    = !!item.is_draft;
            const isInactiveTax = !!item.is_inactive_tax;

            const canIndent  = idx > 0 && item.depth < 2 && item.depth <= items[idx-1].depth;
            const canOutdent = item.depth > 0;

            const typeMap = { custom:'Custom Link', page:'Page', post:'Post', category:'Category', special_cart:'Cart Widget', special_search:'Search Widget', special_wishlist:'Wishlist Widget' };
            
            let statusNotice = '';
            if (isInactiveTax) {
                statusNotice = `<div class="orphan-notice">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    This taxonomy is currently <strong>Deactivated</strong>. Please remove it from the menu.
                  </div>`;
            } else if (orphaned) {
                statusNotice = `<div class="orphan-notice">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    This item has been removed from ${typeMap[item.type]||item.type}. Please remove it from the menu.
                  </div>`;
            } else if (isDraft) {
                statusNotice = `<div class="draft-notice">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    This item is currently a <strong>Draft</strong>. It will not be visible on the site.
                  </div>`;
            }

            li.innerHTML = `
            <div class="mi-wrap ${depthClass}">
                <div class="mi-bar ${orphaned ? 'orphaned' : (isDraft ? 'is-draft' : '')}">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <svg width="16" height="16" fill="none" stroke="${orphaned ? '#d63638' : (isDraft ? '#dba617' : '#9ca3af')}" stroke-width="2" viewBox="0 0 24 24"><path d="M4 8h16M4 16h16"/></svg>
                        <span style="font-size:13px;font-weight:600;color:${orphaned ? '#d63638' : (isDraft ? '#856404' : '#2c3338')};">${esc(item.title)}</span>
                        ${depthBadge ? `<span class="depth-badge">${depthBadge}</span>` : ''}
                        ${isDraft ? `<span class="bg-[#fff3cd] text-[#856404] px-1 rounded text-[10px] font-bold border border-[#ffeeba] ml-1">DRAFT</span>` : ''}
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:11px;color:#8c8f94;">${item.source_label ? esc(item.source_label) : typeLabel(item.type)}</span>
                        ${item.mega_menu_id ? `<span style="font-size:10px;background:#eaf3fb;color:#2271b1;border:1px solid #c3d9ef;border-radius:3px;padding:1px 5px;font-weight:700;">MEGA</span>` : ''}
                        ${canOutdent ? `<button type="button" class="indent-btn" onclick="outdent('${esc(item.id)}')" title="Outdent">←</button>` : ''}
                        ${canIndent  ? `<button type="button" class="indent-btn" onclick="indent('${esc(item.id)}')"  title="Indent">→</button>` : ''}
                        <button type="button" onclick="toggleSettings('${esc(item.id)}')" style="color:#646970;border:none;background:none;cursor:pointer;padding:2px;">
                            <svg id="ti-${esc(item.id)}" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition:transform .2s"><path d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                </div>
                ${statusNotice}
                <div class="mi-settings" id="s-${esc(item.id)}">
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:#1d2327;margin-bottom:4px;">Navigation Label</label>
                        <input type="text" class="wp-input" style="width:100%;height:32px;font-size:13px;"
                               value="${esc(item.title)}"
                               oninput="updateField('${esc(item.id)}','title',this.value); this.closest('.mi-wrap').querySelector('span[style]').textContent=this.value">
                    </div>
                    ${item.type === 'custom' ? `
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:#1d2327;margin-bottom:4px;">URL</label>
                        <input type="text" class="wp-input" style="width:100%;height:32px;font-size:13px;"
                               value="${esc(item.url)}"
                               oninput="updateField('${esc(item.id)}','url',this.value)">
                    </div>` : `
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:#1d2327;margin-bottom:4px;">URL</label>
                        <a href="${esc(item.url)}" style="font-size:13px;color:#2271b1;">${esc(item.url)}</a>
                    </div>`}
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <button type="button" onclick="openItemOptions('${esc(item.id)}')" class="wp-btn-secondary" style="height:30px;font-size:12px;display:inline-flex;align-items:center;gap:6px;">
                            <i class="fa fa-sliders-h"></i> Options
                            ${item.icon ? `<i class="${esc(item.icon)}" style="color:#2271b1;"></i>` : ''}
                            ${item.show_only_icon ? `<span style="font-size:10px;color:#646970;">(icon only)</span>` : ''}
                        </button>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#1d2327;cursor:pointer;user-select:none;">
                            <input type="checkbox" ${item.target === '_blank' ? 'checked' : ''}
                                   onchange="updateField('${esc(item.id)}','target',this.checked?'_blank':'_self')"
                                   style="width:15px;height:15px;flex-shrink:0;">
                            Open in New Tab
                        </label>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid #dfdfdf;">
                        <button type="button" onclick="removeItem('${esc(item.id)}')" style="color:#b32d2e;font-size:12px;text-decoration:underline;border:none;background:none;cursor:pointer;">Remove</button>
                        <button type="button" onclick="toggleSettings('${esc(item.id)}')" style="color:#2271b1;font-size:12px;border:none;background:none;cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </div>`;
            list.appendChild(li);
        });

        initSortable();
    }

    /* ──────────────────────────────────
       SortableJS — reorder only (depth unchanged)
    ────────────────────────────────── */
    let sortable = null;
    function initSortable() {
        if (sortable) sortable.destroy();
        sortable = Sortable.create(document.getElementById('menu-list'), {
            animation: 150,
            handle: '.mi-bar',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd(evt) {
                const moved = items.splice(evt.oldIndex, 1)[0];
                items.splice(evt.newIndex, 0, moved);
                // Re-render to update indent/outdent button visibility
                render();
            }
        });
    }

    /* ──────────────────────────────────
       Save — flat → nested tree
    ────────────────────────────────── */
    function flatToNested(flat) {
        const root = [];
        const stack = []; // stack of {depth, children}

        flat.forEach(item => {
            const node = { id: item.id, title: item.title, url: item.url, type: item.type, object_id: item.object_id || null, icon: item.icon || '', show_only_icon: !!item.show_only_icon, target: item.target || '_self', mega_menu_id: item.mega_menu_id || null, children: [] };
            const depth = item.depth || 0;

            if (depth === 0) {
                root.push(node);
                stack.length = 0;
                stack.push(node);
            } else {
                // Walk back the stack to find parent at depth-1
                while (stack.length > depth) stack.pop();
                const parent = stack[stack.length - 1];
                if (parent) parent.children.push(node);
                else root.push(node);     // fallback
                stack.push(node);
            }
        });
        return root;
    }

    function doSave() {
        document.getElementById('menu-payload').value = JSON.stringify(flatToNested(items));
        document.getElementById('save-form').submit();
    }

    async function doDelete() {
        const confirmed = await window.falconConfirm({
            title: 'Delete Menu',
            message: 'Are you sure you want to delete this menu permanently? This action cannot be undone.',
            confirmText: 'Delete Menu',
            isDanger: true
        });

        if (!confirmed) return;

        const f = document.createElement('form');
        f.method = 'POST';
        f.action = '{{ $menu ? route("admin.menus.destroy", $menu->id) : "" }}';
        f.innerHTML = '{!! csrf_field() !!}{!! method_field("DELETE") !!}';
        document.body.appendChild(f);
        f.submit();
    }

    /* Boot */
    document.addEventListener('DOMContentLoaded', render);
    </script>
</x-falcon-cms::layouts.admin>
