<!-- Header Area -->
<header class="main-header w-full sticky top-0 z-[100]">
    <div class="container-custom h-full">
        <div class="flex items-center justify-between h-full">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    @if(get_cms_option('theme_site_logo'))
                        <img src="{{ get_cms_option('theme_site_logo') }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}" class="h-10 w-auto">
                    @else
                        <span class="text-2xl font-black tracking-tighter text-slate-900">
                            {{ get_cms_option('site_title', 'Lazy Theme') }}<span class="text-primary">.</span>
                        </span>
                    @endif
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center gap-8 h-full lb-desktop-nav">
                @php $menuItems = get_lazy_menu('header'); @endphp
                @foreach($menuItems as $item)
                    @php 
                        $isActive = (url()->current() == $item->url) || (request()->is(ltrim(parse_url($item->url, PHP_URL_PATH), '/')));
                        $itemHoverColor = get_cms_option('theme_menu_hover_color', '#0091ea');
                    @endphp
                    <div class="relative group h-full flex items-center">
                        <a href="{{ $item->url }}" target="{{ $item->target ?? '_self' }}" class="nav-style {{ $isActive ? 'text-primary' : '' }} hover:text-[{{ $itemHoverColor }}] transition-colors flex items-center gap-1">
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
                        
                        @if($item->children->count() > 0)
                            <div class="absolute top-full left-0 w-56 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform translate-y-2 group-hover:translate-y-0 z-50"
                                 style="background-color: {{ get_cms_option('theme_dropdown_bg', '#ffffff') }}; border: 1px solid var(--border-color);">
                                <ul class="py-2">
                                    @foreach($item->children as $child)
                                        <li class="relative group/sub">
                                            <a href="{{ $child->url }}" target="{{ $child->target ?? '_self' }}" class="flex items-center justify-between px-5 py-2.5 text-[13px] font-medium hover:bg-slate-50 transition-all"
                                               style="color: {{ get_cms_option('theme_dropdown_text_color', '#1d2327') }};">
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
                                                                   style="color: {{ get_cms_option('theme_dropdown_text_color', '#1d2327') }};">
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
            {!! lazy_mobile_lang_switcher() !!}
        </div>
        <div class="flex-grow overflow-y-auto p-6">
            <nav class="space-y-4">
                @foreach($menuItems as $item)
                    @php 
                        $isActive = (url()->current() == $item->url) || (request()->is(ltrim(parse_url($item->url, PHP_URL_PATH), '/')));
                    @endphp
                    <div>
                        @php
                            $__mic = $item->icon ?? '';
                            $__mio = !empty($item->show_only_icon) && $__mic !== '';
                            $__mIconHtml = $__mic !== '' ? '<i class="'.e($__mic).' mr-2"'.($__mio ? ' title="'.e($item->title).'"' : '').'></i>' : '';
                        @endphp
                        <a href="{{ $item->url }}" target="{{ $item->target ?? '_self' }}" class="text-[15px] font-bold {{ $isActive ? 'text-primary' : 'text-slate-800' }} hover:text-primary block mb-2">{!! $__mIconHtml !!}@if(!$__mio){{ $item->title }}@endif</a>
                        @if($item->children->count() > 0)
                            <div class="pl-4 space-y-2 border-l border-slate-100 ml-1">
                                @foreach($item->children as $child)
                                    @php 
                                        $childActive = (url()->current() == $child->url) || (request()->is(ltrim(parse_url($child->url, PHP_URL_PATH), '/')));
                                    @endphp
                                    @php
                                        $__mcic = $child->icon ?? '';
                                        $__mcio = !empty($child->show_only_icon) && $__mcic !== '';
                                        $__mcIconHtml = $__mcic !== '' ? '<i class="'.e($__mcic).' mr-2"'.($__mcio ? ' title="'.e($child->title).'"' : '').'></i>' : '';
                                    @endphp
                                    <a href="{{ $child->url }}" target="{{ $child->target ?? '_self' }}" class="text-[14px] font-medium {{ $childActive ? 'text-primary' : 'text-slate-600' }} hover:text-primary block">{!! $__mcIconHtml !!}@if(!$__mcio){{ $child->title }}@endif</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>
        </div>
    </div>
</div>
