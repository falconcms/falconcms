@php
    $separator = $separator ?? '/';
    $showHome = $showHome ?? true;
    $items = [];
    if ($showHome) {
        $items[] = ['title' => 'Home', 'url' => url('/')];
    }

    if (isset($post)) {
        if ($post->type !== 'post' && $post->type !== 'page') {
            $postType = \FalconCms\Core\Models\PostType::where('slug', $post->type)->first();
            if ($postType) {
                $items[] = ['title' => $postType->name, 'url' => route('frontend.show', $post->type)];
            }
        }
        
        if ($post->type === 'post' && $post->categories->isNotEmpty()) {
            $cat = $post->categories->first();
            $items[] = ['title' => $cat->name, 'url' => route('frontend.category', $cat->slug)];
        }

        $items[] = ['title' => $post->title, 'url' => null];
    } elseif (isset($title)) {
        $items[] = ['title' => $title, 'url' => null];
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

{{-- Schema.org BreadcrumbList --}}
@php
    $breadcrumbList = [
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => []
    ];

    foreach ($items as $index => $item) {
        $breadcrumbList['itemListElement'][] = [
            "@type" => "ListItem",
            "position" => $index + 1,
            "name" => $item['title'],
            "item" => $item['url'] ?? url()->current()
        ];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($breadcrumbList, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endif
