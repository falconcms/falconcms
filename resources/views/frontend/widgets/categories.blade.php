<div class="widget mb-12">
    @if($widget->title)
        <h4 class="widget-title">{{ $widget->title }}</h4>
    @endif
    <ul class="space-y-3">
        @php
            $postType = $widget->settings['post_type'] ?? 'auto';
            if ($postType === 'auto') {
                $currentPost = view()->shared('current_post');
                $postType = $currentPost ? $currentPost->type : 'post';
            }
            $categories = get_falcon_categories('category', $postType);
            $showCount  = ($widget->settings['show_count'] ?? '1') === '1';
            $catInfo    = get_falcon_category_taxonomy($postType);
        @endphp
        @foreach($categories as $cat)
            <li>
                @php
                    if ($catInfo['type'] === 'product') {
                        $catUrl = route('frontend.product_category', $cat->getFullSlugPath());
                    } elseif ($catInfo['type'] === 'acpt') {
                        $catUrl = route('frontend.show', ['typeOrSlug' => $catInfo['taxonomy_slug'], 'slug' => $cat->slug]);
                    } else {
                        $catUrl = route('frontend.category', $cat->slug);
                    }
                @endphp
                <a href="{{ $catUrl }}" class="flex items-center justify-between group">
                    <span class="text-sm text-slate-600 group-hover:text-primary transition-colors">{{ $cat->name }}</span>
                    @if($showCount)
                        <span class="text-[11px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ $cat->posts_count ?? 0 }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
