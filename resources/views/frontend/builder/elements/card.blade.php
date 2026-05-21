@php
    $s = $el['settings'] ?? [];

    // Visibility
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visCls = '';
    if (!($v['mobile']  ?? true)) $visCls .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visCls .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visCls .= ' lazy-hide-desktop';

    // Load post card config
    $postCardId = $s['post_card_id'] ?? '';
    $cardCfg    = [];
    if ($postCardId) {
        $rawCards = get_cms_option('lazy_post_cards', '[]');
        $allCards = json_decode($rawCards, true) ?: [];
        foreach ($allCards as $c) {
            if (($c['id'] ?? '') === $postCardId) { $cardCfg = $c['config'] ?? []; break; }
        }
    }

    // Resolve post type
    $postType = $s['post_type'] ?? 'post';

    // Build query args
    $queryArgs = [
        'post_type' => $postType,
        'limit'     => max(1, (int)($s['posts_count'] ?? 6)),
        'offset'    => max(0, (int)($s['posts_offset'] ?? 0)),
        'order'     => $s['order']    ?? 'desc',
        'orderby'   => $s['order_by'] ?? 'created_at',
    ];

    // Post status
    if (!empty($s['post_status']) && is_array($s['post_status'])) {
        $mapped = array_map(fn($st) => $st === 'publish' ? 'published' : $st, $s['post_status']);
        $queryArgs['status'] = count($mapped) === 1 ? $mapped[0] : $mapped;
    }

    // Posts By filter
    $postsBy    = $s['posts_by']       ?? 'all';
    $postsByVal = $s['posts_by_value'] ?? '';
    switch ($postsBy) {
        case 'category':
            if ($postsByVal) $queryArgs['category'] = $postsByVal;
            else             $queryArgs['has_categories'] = true;
            break;
        case 'tag':
            if ($postsByVal) $queryArgs['tag'] = $postsByVal;
            else             $queryArgs['has_tags'] = true;
            break;
        case 'author':  if ($postsByVal) $queryArgs['author']  = $postsByVal; break;
        case 'search':  if ($postsByVal) $queryArgs['search']  = $postsByVal; break;
        case 'post_id': if ($postsByVal) $queryArgs['post_id'] = $postsByVal; break;
    }

    // Taxonomy filter (content_source = terms)
    $contentSource = $s['content_source'] ?? 'posts';
    if ($contentSource === 'terms' && !empty($s['taxonomy_slug'])) {
        $taxSlug = $s['taxonomy_slug'];
        $include = array_values(array_filter(is_array($s['taxonomy_include'] ?? '') ? $s['taxonomy_include'] : explode(',', $s['taxonomy_include'] ?? '')));
        $exclude = array_values(array_filter(is_array($s['taxonomy_exclude'] ?? '') ? $s['taxonomy_exclude'] : explode(',', $s['taxonomy_exclude'] ?? '')));
        if ($postType === 'post' && $taxSlug === 'category') {
            if (!empty($include)) $queryArgs['category']         = implode(',', $include);
            if (!empty($exclude)) $queryArgs['category_exclude'] = implode(',', $exclude);
        } elseif ($postType === 'post' && $taxSlug === 'tag') {
            if (!empty($include)) $queryArgs['tag']         = implode(',', $include);
            if (!empty($exclude)) $queryArgs['tag_exclude'] = implode(',', $exclude);
        } else {
            $queryArgs['taxonomy_slug'] = $taxSlug;
            if (!empty($include)) $queryArgs['taxonomy_include'] = $include;
            if (!empty($exclude)) $queryArgs['taxonomy_exclude'] = $exclude;
        }
    }

    // Stable ID derived from settings so paginated requests regenerate the same gridId/pageParam
    $gridId         = 'card-' . substr(md5(json_encode($s)), 0, 12);

    // Pagination
    $paginationType = $s['pagination_type'] ?? 'none';
    if ($paginationType !== 'none') {
        $pageParam = 'cp_' . substr(md5($gridId), 0, 8);
        $currentPage = max(1, (int)request()->input($pageParam, 1));
        \Illuminate\Pagination\Paginator::currentPageResolver(fn() => $currentPage);
        $queryArgs['paginate']   = true;
        $queryArgs['page_name']  = $pageParam;
        $queryArgs['offset']     = 0; // offset not used with paginate
    }

    $posts = get_lazy_posts($queryArgs);

    // Grid / spacing settings
    $layout       = $s['layout']       ?? 'grid';
    $cols         = max(1, (int)($s['columns']        ?? 3));
    $colsTablet   = max(1, (int)($s['columns_tablet'] ?? 2));
    $colsMobile   = max(1, (int)($s['columns_mobile'] ?? 1));
    $colSpacing   = max(0, (int)($s['column_spacing'] ?? 24));
    $rowSpacing   = max(0, (int)($s['row_spacing']    ?? 24));
    $cardAlign  = $s['card_alignment'] ?? 'stretch';
    // Map legacy values
    $alignMap   = ['left' => 'flex-start', 'right' => 'flex-end', 'start' => 'flex-start', 'end' => 'flex-end'];
    $cardAlign  = $alignMap[$cardAlign] ?? $cardAlign;
    $alignItems = in_array($cardAlign, ['flex-start','center','flex-end','stretch']) ? $cardAlign : 'stretch';
    $bpSm         = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed        = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1        = $bpSm + 1;

    // For list layout force single column
    if ($layout === 'list') { $cols = 1; $colsTablet = 1; $colsMobile = 1; }

    // Margin
    $marginStyle  = "margin-top:"    . ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px') . ";";
    $marginStyle .= "margin-right:"  . ($s['marginRight']  ?? 0) . ($s['marginRightUnit']  ?? 'px') . ";";
    $marginStyle .= "margin-bottom:" . ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px') . ";";
    $marginStyle .= "margin-left:"   . ($s['marginLeft']   ?? 0) . ($s['marginLeftUnit']   ?? 'px') . ";";

    // Post card builder layout
    $cardLayout = is_array($cardCfg['layout'] ?? null) ? $cardCfg['layout'] : [];

    // Legacy card config fields
    $cardStyle    = $cardCfg['card_style']      ?? 'shadow';
    $hoverEffect  = $cardCfg['hover_effect']    ?? 'lift';
    $imageRatio   = $cardCfg['image_ratio']     ?? '16/9';
    $showImage    = $cardCfg['show_image']      ?? true;
    $showTitle    = $cardCfg['show_title']      ?? true;
    $showExcerpt  = $cardCfg['show_excerpt']    ?? true;
    $showDate     = $cardCfg['show_date']       ?? true;
    $showAuthor   = $cardCfg['show_author']     ?? false;
    $showCats     = $cardCfg['show_categories'] ?? false;
    $showReadMore = $cardCfg['show_read_more']  ?? true;
    $readMoreText = $cardCfg['read_more_text']  ?? 'Read More';

    $ratioParts   = explode('/', $imageRatio);
    $ratioPadding = (count($ratioParts) === 2 && (int)$ratioParts[0] > 0)
        ? round(((int)$ratioParts[1] / (int)$ratioParts[0]) * 100, 2) . '%'
        : '56.25%';

    $cardStyleCss = match($cardStyle) {
        'flat'  => 'border border-[#e5e7eb]',
        'boxed' => 'border border-[#e5e7eb] p-4',
        default => 'shadow-md',
    };

    $hoverCss = match($hoverEffect) {
        'lift'    => 'lazy-card-lift',
        'border'  => 'lazy-card-border',
        'scale'   => 'lazy-card-scale',
        'fade'    => 'lazy-card-fade',
        default   => '',
    };

    $nothingMsg = $s['nothing_found_message'] ?? 'No posts found.';
    $cssId      = $s['cssId']    ?? '';
    $cssCls     = $s['cssClass'] ?? '';
@endphp

@php
    // Build grid CSS in PHP to avoid Blade @directive conflicts inside <style>
    if ($layout === 'masonry') {
        $gCss  = "#{$gridId}{columns:{$cols};column-gap:{$colSpacing}px}";
        $gCss .= "#{$gridId}>*{break-inside:avoid;margin-bottom:{$rowSpacing}px}";
        $gCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$gridId}{columns:{$colsTablet}}}";
        $gCss .= "@media(max-width:{$bpSm}px){#{$gridId}{columns:{$colsMobile}}}";
    } elseif ($layout === 'carousel') {
        $gCss  = "#{$gridId}-wrap{position:relative;overflow:hidden}";
        $gCss .= "#{$gridId}{display:flex;transition:transform .4s cubic-bezier(.25,.46,.45,.94);will-change:transform}";
        $gCss .= "#{$gridId}>*{flex:0 0 calc(100%/{$cols} - {$colSpacing}px*({$cols}-1)/{$cols});margin-right:{$colSpacing}px}";
        $gCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$gridId}>*{flex:0 0 calc(100%/{$colsTablet} - {$colSpacing}px*({$colsTablet}-1)/{$colsTablet})}}";
        $gCss .= "@media(max-width:{$bpSm}px){#{$gridId}>*{flex:0 0 calc(100%/{$colsMobile} - {$colSpacing}px*({$colsMobile}-1)/{$colsMobile})}}";
    } else {
        // grid or list (list already forced $cols=1)
        $gCss  = "#{$gridId}{display:grid;grid-template-columns:repeat({$cols},1fr);column-gap:{$colSpacing}px;row-gap:{$rowSpacing}px;align-items:{$alignItems}}";
        $gCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$gridId}{grid-template-columns:repeat({$colsTablet},1fr)}}";
        $gCss .= "@media(max-width:{$bpSm}px){#{$gridId}{grid-template-columns:repeat({$colsMobile},1fr)}}";
    }
    // Strip the builder's default horizontal column gap padding so Column Spacing is the sole gap control.
    // Vertical padding (top/bottom) is intentionally preserved for content breathing room.
    $gCss .= "#{$gridId} .lazy-column{padding-left:0!important;padding-right:0!important}";
    $gCss .= "#{$gridId} .lazy-column-inner{padding-left:0!important;padding-right:0!important}";
@endphp
<style>
{!! $gCss !!}
.lazy-card-lift{transition:transform .2s,box-shadow .2s}
.lazy-card-lift:hover{transform:translateY(-6px);box-shadow:0 12px 28px rgba(0,0,0,.12)}
.lazy-card-border{transition:border-color .2s}
.lazy-card-border:hover{border-color:#2271b1!important}
.lazy-card-scale{transition:transform .2s}
.lazy-card-scale:hover{transform:scale(1.03)}
.lazy-card-fade img{transition:opacity .3s}
.lazy-card-fade:hover img{opacity:.75}
</style>

<div class="lazy-card-grid {{ $visCls }} {{ $cssCls }}"
     @if($cssId) id="{{ $cssId }}" @endif
     style="{{ $marginStyle }}">
    @if($layout === 'carousel')
    <div id="{{ $gridId }}-wrap" style="position:relative">
        {{-- Prev arrow --}}
        <button id="{{ $gridId }}-prev" onclick="lzSlider('{{ $gridId }}','prev')"
                style="position:absolute;left:-18px;top:50%;transform:translateY(-50%);z-index:10;width:36px;height:36px;border-radius:50%;background:#fff;border:1.5px solid #e5e7eb;box-shadow:0 2px 8px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        {{-- Next arrow --}}
        <button id="{{ $gridId }}-next" onclick="lzSlider('{{ $gridId }}','next')"
                style="position:absolute;right:-18px;top:50%;transform:translateY(-50%);z-index:10;width:36px;height:36px;border-radius:50%;background:#fff;border:1.5px solid #e5e7eb;box-shadow:0 2px 8px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    @endif
    <div id="{{ $gridId }}">
        @forelse($posts as $post)
        @php
            $permalink  = get_lazy_permalink($post);
            $imgSrc     = $post->featured_image ?? null;
            if ($imgSrc && !str_starts_with($imgSrc, 'http')) $imgSrc = asset('storage/' . $imgSrc);
            $__rawContent    = $post->content ?? '';
            $__decoded       = json_decode($__rawContent, true);
            $__isBuilderJson = is_array($__decoded);
            if ($__isBuilderJson) {
                $__extractText = function(array $items) use (&$__extractText): string {
                    $out = '';
                    foreach ($items as $item) {
                        $t = $item['type'] ?? '';
                        if (in_array($t, ['text_block', 'special_text'])) {
                            $out .= ' ' . strip_tags($item['settings']['content'] ?? '');
                        } elseif ($t === 'title') {
                            $out .= ' ' . ($item['settings']['title'] ?? '');
                        }
                        foreach ($item['columns'] ?? [] as $col) {
                            $out .= ' ' . $__extractText($col['elements'] ?? []);
                        }
                    }
                    return trim($out);
                };
                $__plainContent = $__extractText($__decoded);
            } else {
                $__plainContent = strip_tags($__rawContent);
            }
            $rawExcerpt = $__plainContent;
            $excerpt    = $post->excerpt ?? (mb_strlen($rawExcerpt) > 120 ? mb_substr($rawExcerpt, 0, 120) . '…' : $rawExcerpt);
            $dateStr    = $post->published_at ? $post->published_at->format('M j, Y') : $post->created_at->format('M j, Y');
            $authorName = optional($post->user)->name ?? '';
            $categories = $post->categories ?? collect();
            // Post card builder context variables
            $postFeaturedImage = $imgSrc;
            $postPermalink     = $permalink;
            $postTitle         = $post->title ?? '';
            $postContent       = $__plainContent;
            $postExcerpt       = $post->excerpt ?? null;
            $postPublishedAt   = $post->published_at ?? null;
            $postCreatedAt     = $post->created_at ?? null;
            $postAuthor        = optional($post->user)->name ?? '';
            $postCategories    = $post->categories ?? collect();
        @endphp
        @if(!empty($cardLayout))
        <div style="break-inside:avoid">
            @include('cms-dashboard::frontend.builder.render', ['layout' => $cardLayout])
        </div>
        @else
        <article class="lazy-post-card {{ $cardStyleCss }} {{ $hoverCss }} rounded-lg overflow-hidden bg-white" style="break-inside:avoid">

            @if($showImage && $imgSrc)
            <a href="{{ $permalink }}" class="block overflow-hidden">
                <div style="position:relative;padding-bottom:{{ $ratioPadding }};overflow:hidden;">
                    <img src="{{ $imgSrc }}" alt="{{ $post->title }}"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                </div>
            </a>
            @endif

            <div class="{{ $cardStyle === 'boxed' ? '' : 'p-5' }}">
                @if($showCats && $categories->count())
                <div class="flex flex-wrap gap-1 mb-2">
                    @foreach($categories->take(3) as $cat)
                    <a href="{{ url('/category/' . ($cat->slug ?? '')) }}"
                       style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#2271b1;background:#f0f6fb;padding:2px 8px;border-radius:99px;text-decoration:none;">
                        {{ $cat->name }}
                    </a>
                    @endforeach
                </div>
                @endif

                @if($showTitle)
                <h3 style="font-size:16px;font-weight:700;line-height:1.35;margin:0 0 8px;">
                    <a href="{{ $permalink }}" style="color:inherit;text-decoration:none;">{{ $post->title }}</a>
                </h3>
                @endif

                @if($showExcerpt && $excerpt)
                <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 10px;">{{ $excerpt }}</p>
                @endif

                @if($showDate || $showAuthor)
                <div style="font-size:11px;color:#9ca3af;font-weight:600;display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    @if($showDate)
                    <span>{{ $dateStr }}</span>
                    @endif
                    @if($showAuthor && $authorName)
                    <span>· {{ $authorName }}</span>
                    @endif
                </div>
                @endif

                @if($showReadMore)
                <a href="{{ $permalink }}"
                   style="display:inline-block;font-size:12px;font-weight:700;color:#2271b1;text-decoration:none;border:1.5px solid #2271b1;padding:5px 14px;border-radius:5px;transition:background .2s,color .2s;"
                   onmouseover="this.style.background='#2271b1';this.style.color='#fff';"
                   onmouseout="this.style.background='';this.style.color='#2271b1';">
                    {{ $readMoreText }}
                </a>
                @endif
            </div>
        </article>
        @endif
        @empty
        <div style="grid-column:1/-1;padding:40px;text-align:center;color:#9ca3af;font-size:13px;border:2px dashed #e5e7eb;border-radius:8px;">
            {{ $nothingMsg }}
        </div>
        @endforelse
    </div>

    @if($layout === 'carousel')
    @php $carouselTotal = count($posts); $slidesPerView = $cols; @endphp
    {{-- Dots --}}
    @if($carouselTotal > $slidesPerView)
    <div id="{{ $gridId }}-dots" style="display:flex;justify-content:center;gap:6px;margin-top:14px">
        @for($d = 0; $d < ceil($carouselTotal / $slidesPerView); $d++)
        <button onclick="lzSliderGoTo('{{ $gridId }}',{{ $d }})"
                id="{{ $gridId }}-dot-{{ $d }}"
                style="width:{{ $d===0?'22':'8' }}px;height:8px;border-radius:4px;background:{{ $d===0?'#2271b1':'#cbd5e1' }};border:none;cursor:pointer;padding:0;transition:all .3s"></button>
        @endfor
    </div>
    @endif
    </div>{{-- end wrap --}}
    <script>
    (function(){
        var perView = {{ $slidesPerView }};
        var perViewTablet = {{ $colsTablet }};
        var perViewMobile = {{ $colsMobile }};
        var bpMed = {{ $bpMed }};
        var bpSm  = {{ $bpSm }};
        var gap   = {{ $colSpacing }};
        var id    = '{{ $gridId }}';
        var track = document.getElementById(id);
        if (!track) return;
        var total = track.children.length;
        var idx   = 0;

        function getPer() {
            var w = window.innerWidth;
            return w <= bpSm ? perViewMobile : (w <= bpMed ? perViewTablet : perView);
        }
        function getSlideW() {
            var per = getPer();
            return (track.parentElement.offsetWidth - gap * (per - 1)) / per;
        }
        function goTo(n) {
            var per  = getPer();
            var max  = Math.max(0, total - per);
            idx = Math.max(0, Math.min(n, max));
            var sw   = getSlideW();
            track.style.transform = 'translateX(-' + (idx * (sw + gap)) + 'px)';
            // update arrows
            var prev = document.getElementById(id + '-prev');
            var next = document.getElementById(id + '-next');
            if (prev) prev.style.opacity = idx === 0 ? '0.35' : '1';
            if (next) next.style.opacity = idx >= max ? '0.35' : '1';
            // update dots
            var dots = document.getElementById(id + '-dots');
            if (dots) {
                var dotEls = dots.children;
                for (var i = 0; i < dotEls.length; i++) {
                    var active = i === Math.floor(idx / per) || (idx >= max && i === dotEls.length - 1);
                    dotEls[i].style.width    = active ? '22px' : '8px';
                    dotEls[i].style.background = active ? '#2271b1' : '#cbd5e1';
                }
            }
            // resize each card
            var sw2 = getSlideW();
            Array.from(track.children).forEach(function(c){ c.style.flex = '0 0 ' + sw2 + 'px'; c.style.marginRight = gap + 'px'; });
        }
        window['lzSlider'] = window['lzSlider'] || function(gid, dir) {
            if (gid !== id) { var f = window['lzSlider_' + gid]; if (f) f(dir); return; }
            goTo(dir === 'next' ? idx + getPer() : idx - getPer());
        };
        window['lzSliderGoTo'] = window['lzSliderGoTo'] || function(gid, n) {
            if (gid !== id) { var f = window['lzSliderGoTo_' + gid]; if (f) f(n); return; }
            goTo(n * getPer());
        };
        window['lzSlider_' + id]     = function(dir) { goTo(dir === 'next' ? idx + getPer() : idx - getPer()); };
        window['lzSliderGoTo_' + id] = function(n)   { goTo(n * getPer()); };
        window.addEventListener('resize', function(){ goTo(idx); });
        goTo(0);
    })();
    </script>
    @endif

    @php $isPaginated = $paginationType !== 'none' && ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator) && $layout !== 'carousel'; @endphp
    @if($isPaginated)

        @if($paginationType === 'numbered')
        @php
            $curPage  = $posts->currentPage();
            $lastPage = $posts->lastPage();
            $window   = 2; // pages each side of current
            $pages    = [];
            for ($i = max(1, $curPage - $window); $i <= min($lastPage, $curPage + $window); $i++) $pages[] = $i;
        @endphp
        @if($lastPage > 1)
        <style>
        .lz-pagination{display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;margin-top:28px;}
        .lz-pagination a,.lz-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all .18s;border:1.5px solid transparent;}
        .lz-pagination a{background:#f8fafc;color:#374151;border-color:#e5e7eb;}
        .lz-pagination a:hover{background:#2271b1;color:#fff;border-color:#2271b1;}
        .lz-pagination .lz-pg-active{background:#2271b1;color:#fff;border-color:#2271b1;cursor:default;}
        .lz-pagination .lz-pg-dots{color:#9ca3af;border:none;background:transparent;cursor:default;}
        .lz-pagination .lz-pg-prev,.lz-pagination .lz-pg-next{font-size:16px;}
        </style>
        <nav class="lz-pagination">
            @if($posts->currentPage() > 1)
            <a class="lz-pg-prev" href="{{ $posts->previousPageUrl() }}">&#8249;</a>
            @endif

            @if($pages[0] > 1)
                <a href="{{ $posts->url(1) }}">1</a>
                @if($pages[0] > 2)<span class="lz-pg-dots">…</span>@endif
            @endif

            @foreach($pages as $p)
                @if($p === $curPage)
                <span class="lz-pg-active">{{ $p }}</span>
                @else
                <a href="{{ $posts->url($p) }}">{{ $p }}</a>
                @endif
            @endforeach

            @if(end($pages) < $lastPage)
                @if(end($pages) < $lastPage - 1)<span class="lz-pg-dots">…</span>@endif
                <a href="{{ $posts->url($lastPage) }}">{{ $lastPage }}</a>
            @endif

            @if($posts->hasMorePages())
            <a class="lz-pg-next" href="{{ $posts->nextPageUrl() }}">&#8250;</a>
            @endif
        </nav>
        @endif

        @elseif($paginationType === 'load_more')
        @if($posts->hasMorePages())
        <style>
        .lz-lm-wrap{text-align:center;margin-top:32px;}
        .lz-lm-btn{display:inline-flex;align-items:center;gap:8px;background:#fff;color:#2271b1;padding:11px 32px;font-weight:700;border-radius:50px;border:2px solid #2271b1;cursor:pointer;font-size:13px;letter-spacing:.02em;transition:all .2s;}
        .lz-lm-btn:hover:not(:disabled){background:#2271b1;color:#fff;}
        .lz-lm-btn:disabled{opacity:.6;cursor:not-allowed;}
        .lz-lm-spinner{width:14px;height:14px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;display:none;animation:lzSpin .6s linear infinite;}
        .lz-lm-btn.loading .lz-lm-spinner{display:inline-block;}
        .lz-lm-btn.loading .lz-lm-label{display:none;}
        @keyframes lzSpin{to{transform:rotate(360deg);}}
        </style>
        <div id="lmw-{{ $gridId }}" class="lz-lm-wrap">
            <button id="lmbtn-{{ $gridId }}" data-next="{{ $posts->nextPageUrl() }}" class="lz-lm-btn">
                <span class="lz-lm-spinner"></span>
                <span class="lz-lm-label">Load More</span>
            </button>
        </div>
        <script>
        (function(){
            var btn  = document.getElementById('lmbtn-{{ $gridId }}');
            var wrap = document.getElementById('lmw-{{ $gridId }}');
            var grid = document.getElementById('{{ $gridId }}');
            if (!btn || !grid) return;
            async function loadMore() {
                var url = btn.dataset.next;
                if (!url) { if (wrap) wrap.style.display='none'; return; }
                btn.disabled = true; btn.classList.add('loading');
                try {
                    var r   = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    var doc = new DOMParser().parseFromString(await r.text(), 'text/html');
                    var src = doc.getElementById('{{ $gridId }}');
                    if (src) Array.from(src.children).forEach(function(c){ grid.appendChild(c.cloneNode(true)); });
                    var nb  = doc.getElementById('lmbtn-{{ $gridId }}');
                    if (nb && nb.dataset.next) {
                        btn.dataset.next = nb.dataset.next;
                        btn.disabled = false; btn.classList.remove('loading');
                    } else { if (wrap) wrap.style.display='none'; }
                } catch(e) { btn.disabled = false; btn.classList.remove('loading'); }
            }
            btn.addEventListener('click', loadMore);
        })();
        </script>
        @endif

        @elseif($paginationType === 'infinite')
        <style>
        .lz-inf-loader{text-align:center;padding:16px;opacity:0;transition:opacity .3s;}
        .lz-inf-loader.visible{opacity:1;}
        .lz-inf-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#2271b1;margin:0 3px;animation:lzBounce 1.2s infinite ease-in-out both;}
        .lz-inf-dot:nth-child(1){animation-delay:-.32s;}
        .lz-inf-dot:nth-child(2){animation-delay:-.16s;}
        @keyframes lzBounce{0%,80%,100%{transform:scale(0);}40%{transform:scale(1);}}
        </style>
        <div id="lmw-{{ $gridId }}" data-next="{{ $posts->hasMorePages() ? $posts->nextPageUrl() : '' }}" style="height:1px;margin-top:24px;"></div>
        <div id="lmloader-{{ $gridId }}" class="lz-inf-loader">
            <span class="lz-inf-dot"></span><span class="lz-inf-dot"></span><span class="lz-inf-dot"></span>
        </div>
        <script>
        (function(){
            var sentinel = document.getElementById('lmw-{{ $gridId }}');
            var loader   = document.getElementById('lmloader-{{ $gridId }}');
            if (!sentinel) return;
            var loading = false;
            async function loadNext() {
                var url = sentinel.dataset.next;
                if (loading || !url) return;
                loading = true;
                if (loader) loader.classList.add('visible');
                try {
                    var r    = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    var doc  = new DOMParser().parseFromString(await r.text(), 'text/html');
                    var src  = doc.getElementById('{{ $gridId }}');
                    var grid = document.getElementById('{{ $gridId }}');
                    if (src && grid) Array.from(src.children).forEach(function(c){ grid.appendChild(c.cloneNode(true)); });
                    var ns = doc.getElementById('lmw-{{ $gridId }}');
                    sentinel.dataset.next = (ns && ns.dataset.next) ? ns.dataset.next : '';
                    if (!sentinel.dataset.next) obs.disconnect();
                } catch(e) {}
                loading = false;
                if (loader) loader.classList.remove('visible');
            }
            var obs = new IntersectionObserver(function(e){ if (e[0].isIntersecting) loadNext(); }, {rootMargin:'300px'});
            obs.observe(sentinel);
        })();
        </script>
        @endif

    @endif
</div>
