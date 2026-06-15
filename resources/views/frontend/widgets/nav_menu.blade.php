@php
    $menuId   = $widget->settings['menu_id'] ?? null;
    $navItems = collect();

    if ($menuId) {
        $navMenu = \FalconCms\Core\Models\NavigationMenu::with(['items' => fn($q) => $q->orderBy('order')])->find($menuId);
        if ($navMenu) {
            $allItems  = $navMenu->items;
            $navItems  = $allItems->where('parent_id', null)->values();
        }
    }
@endphp

@if($navItems->isNotEmpty())
<div class="widget mb-12">
    @if($widget->title)
        <h4 class="widget-title">{{ $widget->title }}</h4>
    @endif
    <ul class="space-y-1">
        @foreach($navItems as $item)
            @php
                $children = isset($allItems) ? $allItems->where('parent_id', $item->id)->values() : collect();
                $isActive = rtrim(request()->url(), '/') === rtrim($item->url, '/');
            @endphp
            <li>
                <a href="{{ $item->url }}"
                   target="{{ $item->target ?? '_self' }}"
                   class="flex items-center gap-2 text-sm py-1.5 px-2 rounded-lg transition-colors {{ $isActive ? 'text-primary font-bold bg-primary/5' : 'text-slate-600 hover:text-primary hover:bg-slate-50' }}">
                    @if(!empty($item->icon))
                        <i class="{{ $item->icon }} w-4 text-center opacity-60 text-xs"></i>
                    @endif
                    @if(!$item->show_only_icon)
                        <span>{{ $item->title }}</span>
                    @endif
                </a>

                @if($children->isNotEmpty())
                    <ul class="ml-4 mt-0.5 space-y-0.5 border-l-2 border-slate-100 pl-3">
                        @foreach($children as $child)
                            @php $childActive = rtrim(request()->url(), '/') === rtrim($child->url, '/'); @endphp
                            <li>
                                <a href="{{ $child->url }}"
                                   target="{{ $child->target ?? '_self' }}"
                                   class="flex items-center gap-2 text-sm py-1 transition-colors {{ $childActive ? 'text-primary font-bold' : 'text-slate-500 hover:text-primary' }}">
                                    @if(!empty($child->icon))
                                        <i class="{{ $child->icon }} text-xs opacity-60"></i>
                                    @endif
                                    @if(!$child->show_only_icon)
                                        <span>{{ $child->title }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endif
