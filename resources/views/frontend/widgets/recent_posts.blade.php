<div class="widget mb-12">
    @if($widget->title)
        <h4 class="widget-title">{{ $widget->title }}</h4>
    @endif
    <div class="space-y-6">
        @php
            $limit    = $widget->settings['limit']     ?? 5;
            $postType = $widget->settings['post_type'] ?? 'auto';
            if ($postType === 'auto') {
                $currentPost = view()->shared('current_post');
                $postType = $currentPost ? $currentPost->type : 'post';
            }
            $recentPosts = get_falcon_posts(['limit' => $limit, 'post_type' => $postType]);
        @endphp
        @foreach($recentPosts as $recent)
            <div class="flex gap-4 group">
                @if($recent->featured_image)
                    <div class="w-16 h-16 shrink-0 bg-slate-50 rounded overflow-hidden border border-slate-100">
                        <img src="{{ str_starts_with($recent->featured_image, 'http') ? $recent->featured_image : asset('storage/'.$recent->featured_image) }}" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500" alt="{{ $recent->title }}">
                    </div>
                @endif
                <div>
                    <h5 class="text-sm font-bold leading-snug group-hover:text-primary transition-colors">
                        <a href="{{ get_falcon_permalink($recent) }}">{{ $recent->title }}</a>
                    </h5>
                    <p class="text-[10px] font-bold text-slate-400 uppercase mt-2 tracking-widest">{{ $recent->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
