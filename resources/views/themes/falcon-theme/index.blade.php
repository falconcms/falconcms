@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', get_cms_option('site_title', 'Lazy Panda'))

@section('content')
@php
    use Illuminate\Support\Str;

    // Blog options (Customizer → Blog → General)
    $blogLayout = get_cms_option('theme_blog_layout', 'grid');
    $blogCols   = (int) get_cms_option('theme_blog_columns', '3');
    $showFeat   = get_cms_option('theme_blog_show_featured', '1') === '1';
    $showExc    = get_cms_option('theme_blog_show_excerpt', '1') === '1';
    $excLen     = max(5, (int) get_cms_option('theme_blog_excerpt_length', '25'));
    $showMeta   = get_cms_option('theme_blog_show_meta', '1') === '1';
    $readMore   = get_cms_option('theme_blog_read_more_text', 'Read More');
    $sidebar    = get_cms_option('theme_blog_sidebar', 'right'); // left | right | none
    if (!in_array($sidebar, ['left', 'right', 'none'], true)) $sidebar = 'right';

    // Grid columns — the selected count is the max columns (steps down responsively on smaller screens).
    $gridClass = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 sm:grid-cols-2',
        3 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
    ][$blogCols] ?? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3';

    $posts = get_falcon_posts(['post_type' => 'post', 'limit' => 9, 'paginate' => true]);

    $imgUrl = function ($p) {
        if (empty($p->featured_image)) return null;
        return str_starts_with($p->featured_image, 'http') ? $p->featured_image : asset('storage/' . $p->featured_image);
    };

    // Plain text from a post's content — handles builder (JSON) layouts by pulling text-bearing values.
    $plainFromContent = function ($p) {
        $c = $p->content;
        if (!is_string($c) || trim($c) === '') return '';
        $t = ltrim($c);
        if ($t[0] === '[' || $t[0] === '{') {
            $layout = json_decode($c, true);
            if (!is_array($layout)) return '';
            $text = '';
            $walk = function ($node) use (&$walk, &$text) {
                if (!is_array($node)) return;
                foreach ($node as $k => $v) {
                    if (is_array($v)) { $walk($v); }
                    elseif (is_string($v) && in_array($k, ['content', 'text', 'title', 'heading', 'description', 'caption', 'subtitle'], true)) {
                        $text .= ' ' . strip_tags($v);
                    }
                }
            };
            $walk($layout);
            return trim(preg_replace('/\s+/', ' ', $text));
        }
        return strip_tags($c);
    };

    // Excerpt: use the excerpt field if set, otherwise plain text from content — limited by Excerpt Length (words).
    $makeExcerpt = function ($p) use ($excLen, $plainFromContent) {
        $t = trim((string) ($p->excerpt ?? ''));
        if ($t === '') $t = $plainFromContent($p);
        return Str::words($t, $excLen, '…');
    };
@endphp

<section class="py-12 bg-white min-h-screen">
    <div class="container-custom">
        <div class="flex flex-col gap-10 {{ $sidebar !== 'none' ? 'lg:flex-row lb-with-sidebar' : '' }}">

            {{-- ===== Main content ===== --}}
            <main class="w-full flex-1 {{ $sidebar === 'left' ? 'lg:order-2 lb-main-order-second' : '' }}">
                @if($posts->count() > 0)
                    @if($blogLayout === 'list')
                        {{-- List layout --}}
                        <div class="flex flex-col gap-8">
                            @foreach($posts as $post)
                                <article class="post-card group flex flex-col sm:flex-row gap-6 bg-white rounded-2xl border border-slate-100 hover:shadow-xl hover:shadow-primary/5 transition-all duration-300 overflow-hidden">
                                    @if($showFeat && $imgUrl($post))
                                        <a href="{{ get_falcon_permalink($post) }}" class="block sm:w-64 flex-shrink-0 aspect-[16/10] overflow-hidden bg-slate-100">
                                            <img src="{{ $imgUrl($post) }}" alt="{{ $post->title }}" loading="lazy"
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        </a>
                                    @endif
                                    <div class="flex flex-col flex-grow p-6 sm:pl-0">
                                        @if($showMeta)
                                            <div class="flex items-center gap-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">
                                                <span>{{ $post->created_at->format('M d, Y') }}</span>
                                                <span class="text-slate-200">•</span>
                                                <span>{{ $post->user->name ?? 'Admin' }}</span>
                                            </div>
                                        @endif
                                        <h3 class="text-xl font-bold mb-3 leading-tight text-heading group-hover:text-primary transition-colors">
                                            <a href="{{ get_falcon_permalink($post) }}">{{ $post->title }}</a>
                                        </h3>
                                        @if($showExc)
                                            <p class="text-slate-500 text-sm mb-5 leading-relaxed">
                                                {{ $makeExcerpt($post) }}
                                            </p>
                                        @endif
                                        <a href="{{ get_falcon_permalink($post) }}" class="mt-auto inline-flex items-center gap-2 text-[12px] font-black uppercase tracking-widest text-primary hover:gap-3 transition-all w-fit">
                                            <span>{{ $readMore }}</span>
                                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        {{-- Grid layout --}}
                        <div class="grid {{ $gridClass }} gap-8 lb-grid-{{ $blogCols }}">
                            @foreach($posts as $post)
                                <article class="post-card flex flex-col group overflow-hidden bg-white rounded-2xl border border-slate-100 hover:shadow-xl hover:shadow-primary/5 transition-all duration-300">
                                    @if($showFeat && $imgUrl($post))
                                        <div class="relative aspect-[16/10] overflow-hidden bg-slate-100">
                                            <a href="{{ get_falcon_permalink($post) }}" class="block h-full">
                                                <img src="{{ $imgUrl($post) }}" alt="{{ $post->title }}" loading="lazy"
                                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                            </a>
                                        </div>
                                    @endif
                                    <div class="p-6 flex flex-col flex-grow">
                                        @if($showMeta)
                                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">
                                                <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                                <span>{{ $post->created_at->format('M d, Y') }}</span>
                                            </div>
                                        @endif
                                        <h3 class="text-lg font-bold mb-3 leading-tight text-heading group-hover:text-primary transition-colors">
                                            <a href="{{ get_falcon_permalink($post) }}">{{ $post->title }}</a>
                                        </h3>
                                        @if($showExc)
                                            <p class="text-slate-500 text-sm mb-5 line-clamp-3 leading-relaxed">
                                                {{ $makeExcerpt($post) }}
                                            </p>
                                        @endif
                                        <a href="{{ get_falcon_permalink($post) }}" class="mt-auto pt-4 border-t border-slate-50 inline-flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-primary hover:gap-3 transition-all">
                                            <span>{{ $readMore }}</span>
                                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    {{-- Pagination (simple, theme-matched) --}}
                    @if($posts->hasPages())
                        @php
                            $cur = $posts->currentPage();
                            $last = $posts->lastPage();
                            $start = max(1, $cur - 1);
                            $end = min($last, $cur + 1);
                            $pgBase = 'w-9 h-9 flex items-center justify-center rounded-lg text-sm transition';
                            $pgLink = $pgBase . ' border border-slate-200 text-slate-600 hover:border-primary hover:text-primary';
                            $pgOff  = $pgBase . ' border border-slate-100 text-slate-300 cursor-default';
                        @endphp
                        <nav class="mt-14 flex items-center justify-center gap-1.5" aria-label="Pagination">
                            @if($posts->onFirstPage())
                                <span class="{{ $pgOff }}"><i data-lucide="chevron-left" class="w-4 h-4"></i></span>
                            @else
                                <a href="{{ $posts->previousPageUrl() }}" class="{{ $pgLink }}" aria-label="Previous"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            @endif

                            @if($start > 1)
                                <a href="{{ $posts->url(1) }}" class="{{ $pgLink }}">1</a>
                                @if($start > 2)<span class="px-1 text-slate-300">…</span>@endif
                            @endif

                            @for($p = $start; $p <= $end; $p++)
                                @if($p == $cur)
                                    <span class="{{ $pgBase }} bg-primary text-white font-bold">{{ $p }}</span>
                                @else
                                    <a href="{{ $posts->url($p) }}" class="{{ $pgLink }}">{{ $p }}</a>
                                @endif
                            @endfor

                            @if($end < $last)
                                @if($end < $last - 1)<span class="px-1 text-slate-300">…</span>@endif
                                <a href="{{ $posts->url($last) }}" class="{{ $pgLink }}">{{ $last }}</a>
                            @endif

                            @if($posts->hasMorePages())
                                <a href="{{ $posts->nextPageUrl() }}" class="{{ $pgLink }}" aria-label="Next"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            @else
                                <span class="{{ $pgOff }}"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
                            @endif
                        </nav>
                    @endif
                @else
                    <div class="py-20 px-10 text-center bg-white rounded-3xl border-2 border-dashed border-slate-100">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i data-lucide="file-text" class="w-10 h-10 text-slate-300"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-heading mb-2">No posts yet</h3>
                        <p class="text-slate-500">Check back soon for new stories.</p>
                    </div>
                @endif
            </main>

            {{-- ===== Sidebar (Left / Right / None — Customizer → Blog → General → Sidebar) ===== --}}
            @if($sidebar !== 'none')
            <aside class="w-full lg:w-[320px] flex-shrink-0 space-y-8 lb-sidebar-widget {{ $sidebar === 'left' ? 'lg:order-1 lb-sidebar-order-first' : '' }}">

                {{-- Search --}}
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                    <h4 class="text-[13px] font-black uppercase tracking-widest text-heading mb-4">Search</h4>
                    <form action="{{ route('frontend.search') }}" method="GET" class="flex items-center gap-2">
                        <input type="text" name="s" value="{{ request('s') }}" placeholder="Search posts..."
                               class="flex-grow px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-primary transition">
                        <button type="submit" class="w-10 h-10 flex-shrink-0 flex items-center justify-center bg-primary text-white rounded-lg hover:bg-primary-hover transition">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>

                {{-- Categories --}}
                @php $categories = get_lazy_categories(); @endphp
                @if($categories->count() > 0)
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                    <h4 class="text-[13px] font-black uppercase tracking-widest text-heading mb-4">Categories</h4>
                    <ul class="space-y-1">
                        @foreach($categories as $cat)
                            <li>
                                <a href="{{ route('frontend.category', $cat->slug) }}"
                                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-white hover:text-primary transition group/cat">
                                    <span>{{ $cat->name }}</span>
                                    <span class="text-[11px] font-bold text-slate-400 bg-white group-hover/cat:bg-slate-50 rounded-full px-2 py-0.5 border border-slate-100">{{ $cat->posts_count ?? 0 }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Recent Posts --}}
                @php $recent = get_falcon_posts(['post_type' => 'post', 'limit' => 5]); @endphp
                @if(count($recent) > 0)
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                    <h4 class="text-[13px] font-black uppercase tracking-widest text-heading mb-4">Recent Posts</h4>
                    <ul class="space-y-4">
                        @foreach($recent as $rp)
                            <li class="flex items-center gap-3">
                                <a href="{{ get_falcon_permalink($rp) }}" class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden bg-slate-100">
                                    @if($imgUrl($rp))
                                        <img src="{{ $imgUrl($rp) }}" alt="{{ $rp->title }}" loading="lazy" class="w-full h-full object-cover">
                                    @endif
                                </a>
                                <div class="min-w-0">
                                    <a href="{{ get_falcon_permalink($rp) }}" class="block text-sm font-bold text-heading hover:text-primary transition leading-snug line-clamp-2">{{ $rp->title }}</a>
                                    <span class="text-[11px] text-slate-400">{{ $rp->created_at->format('M d, Y') }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Tags --}}
                @php
                    try { $tags = \FalconCms\Core\Models\Tag::orderBy('name')->take(20)->get(); }
                    catch (\Throwable $e) { $tags = collect(); }
                @endphp
                @if($tags->count() > 0)
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                    <h4 class="text-[13px] font-black uppercase tracking-widest text-heading mb-4">Tags</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tags as $tag)
                            <a href="{{ route('frontend.tag', $tag->slug) }}"
                               class="px-3 py-1 bg-white border border-slate-100 rounded-full text-[12px] text-slate-600 hover:bg-primary hover:text-white hover:border-primary transition">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

            </aside>
            @endif
        </div>
    </div>
</section>
@stop
