{{-- Related posts grid — shown only when Customizer → Blog → Single Blog → Show Related Posts is on. --}}
@if(get_cms_option('theme_single_show_related', '1') === '1')
@php
    try {
        $relCatInfo = get_lazy_category_taxonomy($post->type);
        $relQ = \FalconCms\Core\Models\Post::where('type', $post->type)
            ->where('status', 'published')
            ->where('id', '!=', $post->id)
            ->where('lang_code', app()->getLocale());

        if ($relCatInfo['type'] === 'native') {
            $relCatIds = $post->categories->pluck('id');
            if ($relCatIds->count()) {
                $relQ->whereHas('categories', fn($q) => $q->whereIn('categories.id', $relCatIds));
            }
        } elseif ($relCatInfo['type'] === 'product') {
            $relCatIds = $post->productCategories->pluck('id');
            if ($relCatIds->count()) {
                $relQ->whereHas('productCategories', fn($q) => $q->whereIn('product_categories.id', $relCatIds));
            }
        } elseif ($relCatInfo['type'] === 'acpt') {
            $relTermIds = $post->taxonomyTerms
                ->where('taxonomy_slug', $relCatInfo['taxonomy_slug'])
                ->pluck('id');
            if ($relTermIds->count()) {
                $relQ->whereHas('taxonomyTerms', fn($q) => $q->whereIn('taxonomy_terms.id', $relTermIds));
            }
        }

        $related = $relQ->latest()->take(3)->get();
        if ($related->isEmpty()) {
            $related = \FalconCms\Core\Models\Post::where('type', $post->type)
                ->where('status', 'published')
                ->where('id', '!=', $post->id)
                ->where('lang_code', app()->getLocale())
                ->latest()->take(3)->get();
        }
    } catch (\Throwable $e) { $related = collect(); }
@endphp
@if($related->isNotEmpty())
<div class="mt-16 pt-10 border-t border-slate-100">
    <h3 class="text-xl font-bold text-heading mb-8">Related Posts</h3>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        @foreach($related as $rel)
            @php $relImg = $rel->featured_image ? (str_starts_with($rel->featured_image, 'http') ? $rel->featured_image : asset('storage/'.$rel->featured_image)) : null; @endphp
            <a href="{{ get_lazy_permalink($rel) }}" class="group block bg-white rounded-xl border border-slate-100 overflow-hidden hover:shadow-lg hover:shadow-primary/5 transition-all">
                @if($relImg)
                    <div class="aspect-[16/10] bg-slate-100 overflow-hidden">
                        <img src="{{ $relImg }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="{{ $rel->title }}" loading="lazy">
                    </div>
                @endif
                <div class="p-4">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $rel->created_at->format('M d, Y') }}</span>
                    <h4 class="text-sm font-bold text-heading group-hover:text-primary transition-colors leading-snug mt-1 line-clamp-2">{{ $rel->title }}</h4>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endif
@endif
