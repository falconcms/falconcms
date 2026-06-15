@extends('falcon-cms::themes.lazy-theme.layouts.app')

@section('title', $post->title)

@section('content')
    @php
        $isBuilder = $post->editor_type === 'builder'
            || (is_string($post->content) && (
                str_starts_with($post->content, '[')
                || str_starts_with($post->content, '{')
            ));

        // Single Blog options (Customizer → Blog → Single Blog)
        $sFeatured = get_cms_option('theme_single_show_featured', '1') === '1';
        $sAuthor   = get_cms_option('theme_single_show_author', '1') === '1';
        $sDate     = get_cms_option('theme_single_show_date', '1') === '1';
        $sCats     = get_cms_option('theme_single_show_categories', '1') === '1';
        $sShare    = get_cms_option('theme_single_show_share', '0') === '1';
        $sRelated  = get_cms_option('theme_single_show_related', '1') === '1';
        $sComments = get_cms_option('theme_single_show_comments', '1') === '1';
        $permalink = get_falcon_permalink($post);
    @endphp

    @if($isBuilder)
        {{-- Featured image (Single Blog → Show Featured Image) above the builder content --}}
        @if($sFeatured && $post->featured_image)
            <div class="container-custom pt-12">
                <div class="rounded-2xl overflow-hidden shadow-2xl shadow-slate-200/50">
                    <img src="{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}"
                         class="w-full h-auto object-cover" alt="{{ $post->title }}">
                </div>
            </div>
        @endif

        <div class="lazy-content-wrapper">
            {!! get_lazy_content($post->content) !!}
        </div>

        {{-- Categories, tags, share, related and comments at the bottom (outside the builder layout) --}}
        <div class="container-custom py-16">
            @php
                $builderCatInfo = get_lazy_category_taxonomy($post->type);
                $isProduct   = $post->type === 'product';
                $tagRoute    = $isProduct ? 'frontend.product_tag' : 'frontend.tag';
                $postTagsCt  = $isProduct ? ($post->productTags ?? collect()) : ($post->tags ?? collect());
                if ($builderCatInfo['type'] === 'product') {
                    $postCatsCt = $post->productCategories ?? collect();
                } elseif ($builderCatInfo['type'] === 'acpt') {
                    $postCatsCt = $post->taxonomyTerms->where('taxonomy_slug', $builderCatInfo['taxonomy_slug']);
                } else {
                    $postCatsCt = $post->categories ?? collect();
                }
            @endphp
            @if($postCatsCt->isNotEmpty())
                <div class="mt-8 pt-8 border-t border-slate-100 flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-400 mr-2">Categories:</span>
                    @foreach($postCatsCt as $cat)
                        @php
                            if ($builderCatInfo['type'] === 'product') {
                                $catHref = route('frontend.product_category', $cat->getFullSlugPath());
                            } elseif ($builderCatInfo['type'] === 'acpt') {
                                $catHref = route('frontend.show', ['typeOrSlug' => $builderCatInfo['taxonomy_slug'], 'slug' => $cat->slug]);
                            } else {
                                $catHref = route('frontend.category', $cat->slug);
                            }
                        @endphp
                        <a href="{{ $catHref }}" class="px-4 py-2 bg-slate-50 hover:bg-primary hover:text-white text-slate-600 text-xs font-bold rounded-lg transition-all">
                            {{ $cat->name }}
                        </a>
                    @endforeach
                </div>
            @endif
            @if($postTagsCt->isNotEmpty())
                <div class="mt-8 pt-8 border-t border-slate-100 flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-400 mr-2">Tags:</span>
                    @foreach($postTagsCt as $tag)
                        <a href="{{ route($tagRoute, $tag->slug) }}" class="px-4 py-2 bg-slate-50 hover:bg-primary hover:text-white text-slate-600 text-xs font-bold rounded-lg transition-all">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @include('falcon-cms::themes.lazy-theme.partials.single-share', ['post' => $post, 'permalink' => $permalink])
            @include('falcon-cms::themes.lazy-theme.partials.single-related', ['post' => $post])

            @if($sComments)
            <div class="mt-16">
                @include('falcon-cms::themes.lazy-theme.partials.comments')
            </div>
            @endif
        </div>
    @else
        <!-- Main Content Area -->
        @php $sidebarContent = render_lazy_widgets('primary-sidebar'); @endphp
        <div class="py-16 bg-white">
            <div class="container-custom">
                <div class="{{ $sidebarContent ? 'flex flex-col lg:flex-row gap-16 lb-with-sidebar' : '' }}">

                    <!-- Content Column -->
                    <article class="w-full {{ $sidebarContent ? 'lg:w-[70%]' : '' }}">
                        {{-- Post meta — each item toggled from Customizer → Blog → Single Blog --}}
                        @if($sAuthor || $sDate || $sCats)
                        <header class="mb-10">
                            <div class="flex items-center gap-6 text-sm text-slate-400 border-b border-slate-100 pb-8">
                                @if($sAuthor)
                                <span class="flex items-center gap-2">
                                    <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                                    <span class="font-bold text-slate-600">{{ $post->user->name ?? 'Admin' }}</span>
                                </span>
                                @endif
                                @if($sDate)
                                <span class="flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-4 h-4 text-primary"></i>
                                    <span class="font-bold text-slate-600">{{ $post->created_at->format('M d, Y') }}</span>
                                </span>
                                @endif
                                @if($sCats)
                                @php
                                    $postCatInfo = get_lazy_category_taxonomy($post->type);
                                    if ($postCatInfo['type'] === 'product') {
                                        $firstCat   = $post->productCategories->first() ?? null;
                                        $firstCatUrl = $firstCat ? route('frontend.product_category', $firstCat->getFullSlugPath()) : null;
                                    } elseif ($postCatInfo['type'] === 'acpt') {
                                        $firstCat   = $post->taxonomyTerms->where('taxonomy_slug', $postCatInfo['taxonomy_slug'])->first() ?? null;
                                        $firstCatUrl = $firstCat ? route('frontend.show', ['typeOrSlug' => $postCatInfo['taxonomy_slug'], 'slug' => $firstCat->slug]) : null;
                                    } else {
                                        $firstCat   = $post->categories->first() ?? null;
                                        $firstCatUrl = $firstCat ? route('frontend.category', $firstCat->slug) : null;
                                    }
                                @endphp
                                <span class="flex items-center gap-2">
                                    <i data-lucide="folder" class="w-4 h-4 text-primary"></i>
                                    @if($firstCat && $firstCatUrl)
                                        <a href="{{ $firstCatUrl }}" class="font-bold text-slate-600 hover:text-primary transition-colors">{{ $firstCat->name }}</a>
                                    @elseif($firstCat)
                                        <span class="font-bold text-slate-600">{{ $firstCat->name }}</span>
                                    @else
                                        <span class="font-bold text-slate-600">Uncategorized</span>
                                    @endif
                                </span>
                                @endif
                            </div>
                        </header>
                        @endif

                        @if($sFeatured && $post->featured_image)
                            <div class="mb-12 rounded-2xl overflow-hidden shadow-2xl shadow-slate-200/50">
                                <img src="{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}"
                                     class="w-full h-auto object-cover" alt="{{ $post->title }}">
                            </div>
                        @endif

                        <div class="lazy-content-wrapper">
                            @php 
                                $rawContent = do_lazy_shortcode($post->content);
                                $filteredContent = apply_falcon_filters('lazy_the_content', $rawContent, $post);
                            @endphp

                            {!! do_falcon_action('lazy_before_content', $post) !!}
                            <div class="entry-content">
                                {!! $filteredContent !!}
                            </div>
                            {!! do_falcon_action('lazy_after_content', $post) !!}
                        </div>

                        <!-- Tags -->
                        @php
                            $isProductPost2 = $post->type === 'product';
                            $postTagsNB     = $isProductPost2 ? ($post->productTags ?? collect()) : ($post->tags ?? collect());
                            $tagRouteNB     = $isProductPost2 ? 'frontend.product_tag' : 'frontend.tag';
                        @endphp
                        @if($postTagsNB->isNotEmpty())
                            <div class="mt-16 pt-8 border-t border-slate-100 flex items-center gap-3 flex-wrap">
                                <span class="text-xs font-black uppercase tracking-widest text-slate-400 mr-2">Tags:</span>
                                @foreach($postTagsNB as $tag)
                                    <a href="{{ route($tagRouteNB, $tag->slug) }}" class="px-4 py-2 bg-slate-50 hover:bg-primary hover:text-white text-slate-600 text-xs font-bold rounded-lg transition-all">
                                        #{{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Share Buttons -->
                        @include('falcon-cms::themes.lazy-theme.partials.single-share', ['post' => $post, 'permalink' => $permalink])

                        <!-- Related Posts -->
                        @include('falcon-cms::themes.lazy-theme.partials.single-related', ['post' => $post])

                        <!-- Comments Section -->
                        @if($sComments)
                        <div class="mt-24">
                            @include('falcon-cms::themes.lazy-theme.partials.comments')
                        </div>
                        @endif
                    </article>

                    <!-- Sidebar — hidden when empty, post goes full width -->
                    @if($sidebarContent)
                    <aside class="w-full lg:w-[30%] space-y-12 lb-sidebar-widget">
                        {!! $sidebarContent !!}
                    </aside>
                    @endif

                </div>
            </div>
        </div>
    @endif
@stop
