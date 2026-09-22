<!-- Header Area -->
<header class="main-header w-full sticky top-0 z-[100]">
    <div class="container-custom h-full">
        <div class="flex items-center justify-between h-full">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <img src="{{ get_cms_option('theme_site_logo', asset('vendor/falcon-cms/images/falcon-cms-logo.png')) }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}" class="h-10 w-auto">
                </a>
            </div>

            <!-- Desktop Navigation -->
            {{-- data-falcon-scrollspy: on a landing page, section links are marked as the
                 reader scrolls past them. See components/frontend/menu-scrollspy. --}}
            <nav class="hidden lg:flex items-center gap-8 h-full lb-desktop-nav" data-falcon-scrollspy="text-primary">
                @php
                    $menuItems = get_falcon_menu('header');
                    // Customizer → Menu → Mega Menu. Off, every branch below behaves exactly as
                    // it did: ordinary dropdowns, and a mega menu is something you build in the
                    // Layout builder. This is the theme header only; the builder's own Menu
                    // element is untouched either way.
                    $megaOn = get_cms_option('theme_mega_menu_enabled', '0') === '1';
                @endphp
                @foreach($menuItems as $item)
                    @php
                        // The whole branch, not just this row: a top-level item stays marked
                        // while the reader is on one of its sub-pages.
                        $isActive = falcon_menu_branch_is_active($item->url, $item->children);
                        $itemHoverColor = get_cms_option('theme_menu_hover_color', '#0091ea');

                        // A panel needs sub-items to put in its columns, so an item without any
                        // keeps its plain link however the switches are set.
                        $isMega = $megaOn && !empty($item->mega_enabled) && $item->children->count() > 0;
                        // ?? as well as ?: — a menu row saved before the mega columns existed
                        // has no such property at all, and reading it threw rather than
                        // defaulting, taking the whole header down with it.
                        $megaCols = max(1, min(6, (int) (($item->mega_columns ?? null) ?: 3)));
                        $megaWidthSaved = $item->mega_width ?? '';
                        $megaWidth = in_array($megaWidthSaved, ['full', 'site', 'custom'], true) ? $megaWidthSaved : 'site';
                        $megaCustom = trim((string) ($item->mega_custom_width ?? '')) ?: '720px';
                    @endphp
                    {{-- A mega panel is measured against the header, not against this item — that
                         is what lets it be full- or site-width — so the item must not become the
                         positioning context for it. `group` is unaffected by that, and the panel
                         stays a DOM child, so hovering it still counts as hovering the item. --}}
                    <div class="{{ $isMega ? '' : 'relative' }} group h-full flex items-center">
                        <a href="{{ falcon_anchor_url($item->url) }}" target="{{ $item->target ?? '_self' }}" class="nav-style {{ $isActive ? 'text-primary' : '' }} hover:text-[{{ $itemHoverColor }}] transition-colors flex items-center gap-1">
                            @php
                                $__ic = $item->icon ?? '';
                                $__io = !empty($item->show_only_icon) && $__ic !== '';
                                $__iconHtml = $__ic !== '' ? '<i class="'.e($__ic).'"'.($__io ? ' title="'.e($item->title).'"' : '').'></i>' : '';
                            @endphp
                            {!! $__iconHtml !!}@if(!$__io){{ $item->title }}@endif
                            @if($item->children->count() > 0)
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 group-hover:text-primary transition-colors {{ $isActive ? 'text-primary' : '' }}"></i>
                            @endif
                        </a>
                        
                        @if($isMega)
                            {{-- Mega panel: every sub-item becomes a column. One that has children
                                 of its own heads its column and lists them; one that does not is a
                                 link standing alone. Columns fill in order and wrap. --}}
                            <div class="falcon-mega-panel falcon-mega-{{ $megaWidth }} opacity-0 invisible group-hover:opacity-100 group-hover:visible"
                                 style="--falcon-mega-cols: {{ $megaCols }};@if($megaWidth === 'custom') --falcon-mega-width: {{ $megaCustom }};@endif">
                                <div class="falcon-mega-grid">
                                    @foreach($item->children as $child)
                                        <div class="falcon-mega-col">
                                            @if($child->children->count() > 0)
                                                <a href="{{ falcon_anchor_url($child->url) }}" target="{{ $child->target ?? '_self' }}" class="falcon-mega-heading {{ falcon_menu_branch_is_active($child->url, $child->children) ? 'is-active' : '' }}">
                                                    @if(!empty($child->icon))<i class="{{ $child->icon }}"></i>@endif{{ $child->title }}
                                                </a>
                                                <ul class="falcon-mega-list">
                                                    @foreach($child->children as $grandChild)
                                                        <li>
                                                            <a href="{{ falcon_anchor_url($grandChild->url) }}" target="{{ $grandChild->target ?? '_self' }}" class="falcon-mega-link {{ falcon_menu_is_active($grandChild->url) ? 'is-active' : '' }}">
                                                                @if(!empty($grandChild->icon))<i class="{{ $grandChild->icon }}"></i>@endif{{ $grandChild->title }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <ul class="falcon-mega-list">
                                                    <li>
                                                        <a href="{{ falcon_anchor_url($child->url) }}" target="{{ $child->target ?? '_self' }}" class="falcon-mega-link {{ falcon_menu_is_active($child->url) ? 'is-active' : '' }}">
                                                            @if(!empty($child->icon))<i class="{{ $child->icon }}"></i>@endif{{ $child->title }}
                                                        </a>
                                                    </li>
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @elseif($item->children->count() > 0)
                            <div class="absolute top-full left-0 w-56 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform translate-y-2 group-hover:translate-y-0 z-50"
                                 style="background-color: {{ get_cms_option('theme_dropdown_bg', '#ffffff') }}; border: 1px solid var(--border-color);">
                                <ul class="py-2">
                                    @foreach($item->children as $child)
                                        {{-- The dropdown row for the page you are on had no marked
                                             state at all: every row took the same fixed dropdown
                                             colour, so opening the menu told you nothing about where
                                             you were. It takes the primary colour now, matching the
                                             top-level item that is marked above it. --}}
                                        @php $childActive = falcon_menu_branch_is_active($child->url, $child->children); @endphp
                                        <li class="relative group/sub">
                                            <a href="{{ falcon_anchor_url($child->url) }}" target="{{ $child->target ?? '_self' }}" class="flex items-center justify-between px-5 py-2.5 text-[13px] font-medium hover:bg-slate-50 transition-all"
                                               style="color: {{ $childActive ? 'var(--primary)' : get_cms_option('theme_dropdown_text_color', '#1d2327') }};">
                                                @php
                                                    $__cic = $child->icon ?? '';
                                                    $__cio = !empty($child->show_only_icon) && $__cic !== '';
                                                    $__cIconHtml = $__cic !== '' ? '<i class="'.e($__cic).'"'.($__cio ? ' title="'.e($child->title).'"' : '').'></i>' : '';
                                                @endphp
                                                <span class="flex items-center gap-1.5">{!! $__cIconHtml !!}@if(!$__cio){{ $child->title }}@endif</span>
                                                @if($child->children->count() > 0)
                                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
                                                @endif
                                            </a>
                                            
                                            @if($child->children->count() > 0)
                                                <div class="absolute top-0 left-full w-56 shadow-xl opacity-0 invisible group-hover/sub:opacity-100 group-hover/sub:visible transition-all duration-200 transform translate-x-2 group-hover/sub:translate-x-0 z-50"
                                                     style="background-color: {{ get_cms_option('theme_dropdown_bg', '#ffffff') }}; border: 1px solid var(--border-color);">
                                                    <ul class="py-2">
                                                        @foreach($child->children as $grandChild)
                                                            <li>
                                                                <a href="{{ $grandChild->url }}" target="{{ $grandChild->target ?? '_self' }}" class="block px-5 py-2.5 text-[13px] font-medium hover:bg-slate-50 transition-all"
                                                                   style="color: {{ falcon_menu_is_active($grandChild->url) ? 'var(--primary)' : get_cms_option('theme_dropdown_text_color', '#1d2327') }};">
                                                                    @php
                                                                        $__gic = $grandChild->icon ?? '';
                                                                        $__gio = !empty($grandChild->show_only_icon) && $__gic !== '';
                                                                        $__gIconHtml = $__gic !== '' ? '<i class="'.e($__gic).' mr-1.5"'.($__gio ? ' title="'.e($grandChild->title).'"' : '').'></i>' : '';
                                                                    @endphp
                                                                    {!! $__gIconHtml !!}@if(!$__gio){{ $grandChild->title }}@endif
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-5">
                <!-- Language Switcher -->
                {!! falcon_lang_dropdown() !!}

                <!-- Cart Icon -->
                <a href="{{ route('shop.cart') }}" class="relative group hover:text-primary transition-colors" style="color: inherit;">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    @php $count = get_falcon_cart_count(); @endphp
                    <span class="cart-count-badge absolute -top-2.5 -right-2.5 bg-primary text-white text-[10px] font-black w-4 h-4 flex items-center justify-center rounded-full ring-2 ring-white {{ $count > 0 ? '' : 'hidden' }}">
                        {{ $count }}
                    </span>
                </a>

                <button class="hover:text-primary transition-colors" style="color: inherit;" onclick="document.getElementById('search-bar').classList.toggle('hidden')">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </button>
                
                <button class="lg:hidden hover:text-primary transition-colors lb-mobile-btn" style="color: inherit;" onclick="document.getElementById('mobile-menu').classList.remove('translate-x-full')">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Dropdown Search Bar -->
    <div id="search-bar" class="hidden absolute top-full left-0 w-full bg-white border-b border-slate-100 p-4 shadow-sm z-40">
        <div class="container-custom">
            <form action="{{ route('frontend.search') }}" method="GET" class="relative max-w-2xl mx-auto">
                <input type="text" name="s" placeholder="Search for stories..." class="w-full bg-slate-50 border-none rounded-full px-6 py-3 text-sm focus:ring-2 focus:ring-primary/20">
                <button type="submit" class="absolute right-2 top-1.5 bottom-1.5 px-4 bg-primary text-white rounded-full text-xs font-bold">SEARCH</button>
            </form>
        </div>
    </div>
</header>

<!-- Mobile Menu Overlay -->
<div id="mobile-menu" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[200] transform translate-x-full transition-transform duration-300 lg:hidden lb-mobile-menu">
    <div class="absolute right-0 top-0 h-full w-80 bg-white shadow-2xl flex flex-col">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <span class="text-lg font-bold text-slate-900">Navigation</span>
            <button class="text-slate-500 hover:text-primary transition-colors" onclick="document.getElementById('mobile-menu').classList.add('translate-x-full')">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <!-- Mobile Language Switcher -->
        <div class="p-6 border-b border-slate-100 lg:hidden">
            <p class="text-[12px] font-bold text-slate-400 uppercase tracking-wider mb-3">Select Language</p>
            {!! falcon_mobile_lang_switcher() !!}
        </div>
        <div class="flex-grow overflow-y-auto p-6">
            <nav class="space-y-4" data-falcon-scrollspy="text-primary">
                @foreach($menuItems as $item)
                    @php
                        // Same rule as the desktop header — the branch you are inside is marked.
                        $isActive = falcon_menu_branch_is_active($item->url, $item->children);
                    @endphp
                    <div>
                        @php
                            $__mic = $item->icon ?? '';
                            $__mio = !empty($item->show_only_icon) && $__mic !== '';
                            $__mIconHtml = $__mic !== '' ? '<i class="'.e($__mic).' mr-2"'.($__mio ? ' title="'.e($item->title).'"' : '').'></i>' : '';
                        @endphp
                        <a href="{{ falcon_anchor_url($item->url) }}" target="{{ $item->target ?? '_self' }}" class="text-[15px] font-bold {{ $isActive ? 'text-primary' : 'text-slate-800' }} hover:text-primary block mb-2">{!! $__mIconHtml !!}@if(!$__mio){{ $item->title }}@endif</a>
                        @if($item->children->count() > 0)
                            <div class="pl-4 space-y-2 border-l border-slate-100 ml-1">
                                @foreach($item->children as $child)
                                    @php
                                        // A second-level item with a third level under it is an
                                        // ancestor too, so it is marked when a grandchild is current.
                                        $childActive = falcon_menu_branch_is_active($child->url, $child->children);
                                    @endphp
                                    @php
                                        $__mcic = $child->icon ?? '';
                                        $__mcio = !empty($child->show_only_icon) && $__mcic !== '';
                                        $__mcIconHtml = $__mcic !== '' ? '<i class="'.e($__mcic).' mr-2"'.($__mcio ? ' title="'.e($child->title).'"' : '').'></i>' : '';
                                    @endphp
                                    <a href="{{ falcon_anchor_url($child->url) }}" target="{{ $child->target ?? '_self' }}" class="text-[14px] font-medium {{ $childActive ? 'text-primary' : 'text-slate-600' }} hover:text-primary block">{!! $__mcIconHtml !!}@if(!$__mcio){{ $child->title }}@endif</a>
                                    {{-- Third level. The mobile list stopped at two, which was
                                         survivable while the desktop dropdown was the only place
                                         these appeared — a mega menu is built out of exactly this
                                         level, so leaving it out would hide those links from every
                                         phone. Desktop mega menus stay desktop-only; the links
                                         themselves have to be reachable everywhere. --}}
                                    @if($child->children->count() > 0)
                                        <div class="pl-4 space-y-2 border-l border-slate-100 ml-1 mt-2 mb-1">
                                            @foreach($child->children as $grandChild)
                                                @php
                                                    $__mgic = $grandChild->icon ?? '';
                                                    $__mgio = !empty($grandChild->show_only_icon) && $__mgic !== '';
                                                    $__mgIconHtml = $__mgic !== '' ? '<i class="'.e($__mgic).' mr-2"'.($__mgio ? ' title="'.e($grandChild->title).'"' : '').'></i>' : '';
                                                @endphp
                                                <a href="{{ falcon_anchor_url($grandChild->url) }}" target="{{ $grandChild->target ?? '_self' }}" class="text-[13px] {{ falcon_menu_is_active($grandChild->url) ? 'text-primary' : 'text-slate-500' }} hover:text-primary block">{!! $__mgIconHtml !!}@if(!$__mgio){{ $grandChild->title }}@endif</a>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>
        </div>
    </div>
</div>

@include('falcon-cms::components.frontend.menu-scrollspy')
