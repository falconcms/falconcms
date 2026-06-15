@php 
    $searchTerm = request()->query('s'); 
    $highlight = function($text, $term) {
        if (!$term) return $text;
        return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark class="bg-yellow-200 text-slate-900 px-0.5 rounded-sm">$1</mark>', $text);
    };
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10 lb-grid-3">
    @forelse($posts as $post)
        <article class="post-card flex flex-col group overflow-hidden bg-white rounded-2xl border border-slate-100/50 hover:shadow-2xl hover:shadow-primary/5 transition-all duration-500">
            <!-- Image Area -->
            <div class="relative aspect-[16/10] overflow-hidden bg-slate-100">
                <a href="{{ get_falcon_permalink($post) }}" class="block h-full">
                    <img src="{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}" 
                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" 
                         alt="{{ $post->title }}"
                         loading="lazy">
                </a>
                <div class="absolute top-4 left-4">
                    <span class="py-1 px-3 bg-white/90 backdrop-blur-md text-[10px] font-black text-primary rounded-lg uppercase tracking-wider shadow-sm border border-white/20">
                        {{ $post->type }}
                    </span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8 flex flex-col flex-grow">
                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <span>{{ $post->created_at->format('M d, Y') }}</span>
                </div>
                
                <h3 class="text-xl font-bold mb-4 group-hover:text-primary transition-colors leading-tight">
                    <a href="{{ get_falcon_permalink($post) }}">
                        {!! $highlight($post->title, $searchTerm) !!}
                    </a>
                </h3>
                
                <p class="text-slate-500 text-sm mb-6 line-clamp-3 leading-relaxed">
                    @php
                        $description = $post->excerpt ?: get_falcon_excerpt($post, 120);
                    @endphp
                    {!! $highlight($description, $searchTerm) !!}
                </p>

                <div class="mt-auto pt-6 border-t border-slate-50">
                    <a href="{{ get_falcon_permalink($post) }}" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-primary hover:gap-3 transition-all group/btn">
                        <span>Read Story</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </article>
    @empty
        <div class="col-span-full py-20 px-10 text-center bg-white rounded-3xl border-2 border-dashed border-slate-100">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="search-x" class="w-10 h-10 text-slate-300"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-900 mb-2">No results found</h3>
            <p class="text-slate-500 max-w-sm mx-auto">We couldn't find any posts matching your search criteria. Try using different keywords.</p>
            <div class="mt-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 py-3 px-8 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                    <i data-lucide="home" class="w-4 h-4"></i>
                    Back to Home
                </a>
            </div>
        </div>
    @endforelse
</div>
