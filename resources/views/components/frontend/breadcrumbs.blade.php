@php
    $separator = $separator ?? '/';
    $showHome = $showHome ?? true;
    // The site's one trail (Support\Breadcrumbs): the page's structured data already carries it
    // as a BreadcrumbList, so this component only draws it.
    $items = \FalconCms\Core\Support\Breadcrumbs::trail($post ?? null, $title ?? null);
    if (!$showHome) {
        array_shift($items);
    }
@endphp

@if(count($items) > 1)
<nav class="flex items-center space-x-2 text-sm text-gray-500 mb-6 overflow-x-auto whitespace-nowrap" aria-label="Breadcrumb">
    @foreach($items as $index => $item)
        <div class="flex items-center">
            @if($item['url'] && $index < count($items) - 1)
                <a href="{{ $item['url'] }}" class="hover:text-primary transition-colors">{{ $item['title'] }}</a>
                <span class="mx-2 text-gray-400">{{ $separator }}</span>
            @else
                <span class="text-gray-900 font-medium truncate max-w-[200px]">{{ $item['title'] }}</span>
            @endif
        </div>
    @endforeach
</nav>
@endif
