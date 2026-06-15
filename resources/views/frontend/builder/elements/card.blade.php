@php
    $s = $el['settings'] ?? [];

    // Visibility
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visCls = '';
    if (!($v['mobile']  ?? true)) $visCls .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visCls .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visCls .= ' falcon-hide-desktop';

    // Load post card config
    $postCardId = $s['post_card_id'] ?? '';
    $cardCfg    = [];
    if ($postCardId) {
        $rawCards = get_cms_option('falcon_post_cards', '[]');
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

    $posts = get_falcon_posts($queryArgs);

    // Grid / spacing settings
    $layout = $s['layout'] ?? 'grid';
    if ($layout === 'carousel') {
        // Use items_per_slide if explicitly saved; fall back to columns for old saved pages
        $cols   = isset($s['items_per_slide'])
                    ? max(1, (int)$s['items_per_slide'])
                    : max(1, (int)($s['columns'] ?? 1));
        $rawT   = (int)($s['items_per_slide_tablet'] ?? 0);
        $rawM   = (int)($s['items_per_slide_mobile'] ?? 0);
        // 0 = smart auto: tablet shows up to 2, mobile shows 1 (never more than desktop)
        $colsTablet = $rawT > 0 ? $rawT : min($cols, 2);
        $colsMobile = $rawM > 0 ? $rawM : 1;
    } else {
        $cols       = max(1, (int)($s['columns']        ?? 3));
        $colsTablet = max(1, (int)($s['columns_tablet'] ?? 2));
        $colsMobile = max(1, (int)($s['columns_mobile'] ?? 1));
    }
    // Builder canvas preview: the canvas browser is always desktop-width, so media queries can't
    // reflect the device toggle. Force the chosen device's count for ALL layouts (carousel
    // items-per-slide AND grid/masonry columns). On the real front-end $previewDevice is null.
    if (($previewDevice ?? null) === 'tablet')      { $cols = $colsTablet; $colsTablet = $cols; $colsMobile = $cols; }
    elseif (($previewDevice ?? null) === 'mobile')  { $cols = $colsMobile; $colsTablet = $cols; $colsMobile = $cols; }
    $colSpacing   = max(0, (int)($s['column_spacing'] ?? 24));
    $rowSpacing   = max(0, (int)($s['row_spacing']    ?? 24));
    $cardAlign  = $s['card_alignment'] ?? 'stretch';
    $alignMap   = ['start' => 'flex-start', 'end' => 'flex-end', 'left' => 'flex-start', 'right' => 'flex-end'];
    $cardAlign  = $alignMap[$cardAlign] ?? $cardAlign;
    $alignItems = in_array($cardAlign, ['flex-start','center','flex-end','stretch']) ? $cardAlign : 'stretch';
    $bpSm         = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed        = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1        = $bpSm + 1;
    $showArrows       = $layout === 'carousel' && ($s['carousel_arrows'] ?? true);
    $showDots         = $layout === 'carousel' && ($s['carousel_dots'] ?? true);
    $carouselAutoplay = $layout === 'carousel' && ($s['carousel_autoplay'] ?? false);
    $autoplaySpeed    = max(500, (int)($s['carousel_autoplay_speed'] ?? 3000));
    $carouselLoop     = $layout === 'carousel' && ($s['carousel_loop'] ?? false);
    $equalHeight      = $layout === 'carousel' && !empty($s['carousel_equal_height']);
    // Arrow styling
    $arrowBg        = $s['carousel_arrow_bg']         ?? '#ffffff';
    $arrowIconColor = $s['carousel_arrow_icon_color'] ?? '#374151';
    $arrowSize      = max(20, (int)($s['carousel_arrow_size'] ?? 40));
    $arrowOffset    = (int)($s['carousel_arrow_offset'] ?? 8);
    $arrowIconSize  = max(10, (int)round($arrowSize * 0.38));
    // Dot styling
    $dotColor       = $s['carousel_dot_color']        ?? '#cbd5e1';
    $dotActiveColor = $s['carousel_dot_active_color']  ?? '#2271b1';
    $dotSize        = max(4, (int)($s['carousel_dot_size'] ?? 8));
    $dotBorder      = max(0, (int)($s['carousel_dot_border'] ?? 0));
    $dotBorderColor = $s['carousel_dot_border_color'] ?? '#ffffff';
    $dotGap         = max(0, (int)($s['carousel_dot_gap'] ?? 6));
    $dotsOffset     = max(0, (int)($s['carousel_dots_offset'] ?? 14));
    $dotActiveW     = (int)round($dotSize * 2.75);
    $dotBorderCss   = $dotBorder > 0 ? ($dotBorder . 'px solid ' . $dotBorderColor) : 'none';

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
        // Slide widths are computed in CSS (like the grid layout) so exactly N cards show per view
        // at every breakpoint — reliable, no flash, no dependence on JS measuring the container.
        $cwD = "calc((100% - " . max(0, ($cols       - 1) * $colSpacing) . "px) / {$cols})";
        $cwT = "calc((100% - " . max(0, ($colsTablet - 1) * $colSpacing) . "px) / {$colsTablet})";
        $cwM = "calc((100% - " . max(0, ($colsMobile - 1) * $colSpacing) . "px) / {$colsMobile})";
        // min-width:0 everywhere: the carousel is a flex item; without it, flexbox min-content
        // sizing forces the track wider than its column and it won't shrink on tablet/mobile.
        // Padding on all sides insets the slides from the wrap edges, so edge cards' shadows show
        // inside the padding (not cut on left/right/top/bottom). The horizontal clip sits at the
        // padding box and the gap (> padding) keeps the next off-screen slide hidden — no sliver.
        // overflow-y:visible lets hover-lift breathe. (overflow:hidden = fallback for old browsers.)
        $gCss  = "#{$gridId}-wrap{position:relative;overflow:hidden;overflow-x:clip;overflow-y:visible;min-width:0;max-width:100%;padding:16px}";
        // Equal Height ON → stretch all slides to the tallest; OFF → natural height, top-aligned.
        $alignC = $equalHeight ? 'stretch' : 'flex-start';
        $gCss .= "#{$gridId}{display:flex;align-items:{$alignC};min-width:0;transition:transform .5s cubic-bezier(.22,.61,.36,1);will-change:transform;cursor:grab;touch-action:pan-y}";
        $gCss .= "#{$gridId}.lz-grabbing{cursor:grabbing;user-select:none}";
        $gCss .= "#{$gridId}>*{flex:0 0 {$cwD};width:{$cwD};min-width:0;margin-right:{$colSpacing}px;box-sizing:border-box}";
        $gCss .= "#{$gridId}>*:last-child{margin-right:0}";
        $gCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$gridId}>*{flex:0 0 {$cwT};width:{$cwT}}}";
        $gCss .= "@media(max-width:{$bpSm}px){#{$gridId}>*{flex:0 0 {$cwM};width:{$cwM}}}";
        $gCss .= "#{$gridId} img{-webkit-user-drag:none}";
        // The card grid is itself a flex item in its column — let it shrink to the column width.
        $gCss .= ".lazy-card-grid{min-width:0;max-width:100%}";
        // Equal Height: make the whole card chain fill the stretched slide height.
        // The builder card-inner uses flex-wrap + align-content:flex-start (packs to top), so we
        // override align-content:stretch (high specificity via the #id) to let the column stretch.
        if ($equalHeight) {
            $gCss .= "#{$gridId}>*{display:flex;flex-direction:column}";
            $gCss .= "#{$gridId}>*>.lazy-container{flex:1;display:flex;flex-direction:column}";
            $gCss .= "#{$gridId}>*>.lazy-container>.lazy-container-inner{flex:1 1 auto!important;align-content:stretch!important;height:auto!important}";
            $gCss .= "#{$gridId}>*>.lazy-container .lazy-column{align-self:stretch!important;height:auto}";
            $gCss .= "#{$gridId}>*>.lazy-container .lazy-column-inner{flex:1 1 auto!important}";
            $gCss .= "#{$gridId}>article.lazy-post-card{flex:1;display:flex;flex-direction:column}";
            $gCss .= "#{$gridId}>article.lazy-post-card>div:last-child{flex:1}";
        }
    } else {
        // grid or list (list already forced $cols=1)
        // Compute explicit px/% values in PHP to produce simple, unambiguous CSS calc()
        $bD  = round(100 / $cols,        6);
        $bT  = round(100 / $colsTablet,  6);
        $bM  = round(100 / $colsMobile,  6);
        $gD  = $cols        > 1 ? round($colSpacing * ($cols        - 1) / $cols,        4) : 0;
        $gT  = $colsTablet  > 1 ? round($colSpacing * ($colsTablet  - 1) / $colsTablet,  4) : 0;
        $gM  = $colsMobile  > 1 ? round($colSpacing * ($colsMobile  - 1) / $colsMobile,  4) : 0;
        $wD  = $gD  > 0 ? "calc({$bD}% - {$gD}px)"  : "{$bD}%";
        $wT  = $gT  > 0 ? "calc({$bT}% - {$gT}px)"  : "{$bT}%";
        $wM  = $gM  > 0 ? "calc({$bM}% - {$gM}px)"  : "{$bM}%";
        $gCss  = "#{$gridId}{display:flex;flex-wrap:wrap;gap:{$rowSpacing}px {$colSpacing}px;align-items:{$alignItems}}";
        $gCss .= "#{$gridId}>*{flex:0 0 {$wD};min-width:0;box-sizing:border-box}";
        $gCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$gridId}>*{flex-basis:{$wT}}}";
        $gCss .= "@media(max-width:{$bpSm}px){#{$gridId}>*{flex-basis:{$wM}}}";
        if ($alignItems === 'stretch') {
            // Same equal-height chain as the carousel: override the card-inner's
            // align-content:flex-start so the column (and its background) fills the row height.
            $gCss .= "#{$gridId}>div{display:flex;flex-direction:column}";
            $gCss .= "#{$gridId}>div>.lazy-container{flex:1;display:flex;flex-direction:column}";
            $gCss .= "#{$gridId}>div>.lazy-container>.lazy-container-inner{flex:1 1 auto!important;align-content:stretch!important;height:auto!important}";
            $gCss .= "#{$gridId}>div>.lazy-container .lazy-column{align-self:stretch!important;height:auto}";
            $gCss .= "#{$gridId}>div>.lazy-container .lazy-column-inner{flex:1 1 auto!important}";
            $gCss .= "#{$gridId} article.lazy-post-card{display:flex;flex-direction:column}";
            $gCss .= "#{$gridId} article.lazy-post-card>div{flex:1}";
        }
    }
    $gCss .= "#{$gridId} .container-custom{padding-left:0!important;padding-right:0!important}";
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
/* Carousel controls */
.lz-cz-arrow{transition:filter .2s,box-shadow .2s,transform .2s}
.lz-cz-arrow:hover{filter:brightness(.93);box-shadow:0 5px 16px rgba(0,0,0,.22)!important;transform:translateY(-50%) scale(1.08)!important}
.lz-cz-arrow:active{transform:translateY(-50%) scale(.96)!important}
.lz-cz-arrow:focus-visible{outline:2px solid #2271b1;outline-offset:2px}
#{{ $gridId }}-wrap:focus{outline:none}
.lz-cz-dot{transition:width .3s,background .3s,opacity .2s}
.lz-cz-dot:hover{opacity:.7}
</style>

<div class="lazy-card-grid {{ $visCls }} {{ $cssCls }}"
     @if($cssId) id="{{ $cssId }}" @endif
     style="{{ $marginStyle }}">
    @if($layout === 'carousel')
    <div id="{{ $gridId }}-wrap">
        @if($showArrows)
        {{-- Prev arrow --}}
        <button type="button" aria-label="Previous" id="{{ $gridId }}-prev" class="lz-cz-arrow" onclick="lzSlider('{{ $gridId }}','prev')"
                style="position:absolute;left:{{ $arrowOffset }}px;top:50%;transform:translateY(-50%);z-index:10;width:{{ $arrowSize }}px;height:{{ $arrowSize }}px;border-radius:50%;background:{{ $arrowBg }};border:1.5px solid rgba(0,0,0,.08);box-shadow:0 2px 10px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;">
            <svg width="{{ $arrowIconSize }}" height="{{ $arrowIconSize }}" viewBox="0 0 24 24" fill="none" stroke="{{ $arrowIconColor }}" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        {{-- Next arrow --}}
        <button type="button" aria-label="Next" id="{{ $gridId }}-next" class="lz-cz-arrow" onclick="lzSlider('{{ $gridId }}','next')"
                style="position:absolute;right:{{ $arrowOffset }}px;top:50%;transform:translateY(-50%);z-index:10;width:{{ $arrowSize }}px;height:{{ $arrowSize }}px;border-radius:50%;background:{{ $arrowBg }};border:1.5px solid rgba(0,0,0,.08);box-shadow:0 2px 10px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;">
            <svg width="{{ $arrowIconSize }}" height="{{ $arrowIconSize }}" viewBox="0 0 24 24" fill="none" stroke="{{ $arrowIconColor }}" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        @endif
    @endif
    <div id="{{ $gridId }}">
        @forelse($posts as $post)
        @php
            $permalink  = get_falcon_permalink($post);
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
        <div style="break-inside:avoid{{ $alignItems === 'stretch' ? ';display:flex;flex-direction:column' : '' }}">
            @include('falcon-cms::frontend.builder.render', ['layout' => $cardLayout, 'cardStretch' => $alignItems === 'stretch'])
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
    </div>{{-- end wrap --}}
    @if($showDots && $carouselTotal > 1)
    {{-- One round dot per slide position — built dynamically by JS so the count is correct per breakpoint --}}
    <div id="{{ $gridId }}-dots" style="display:flex;justify-content:center;align-items:center;gap:{{ $dotGap }}px;margin-top:{{ $dotsOffset }}px"></div>
    @endif
    <script>
    (function(){
        var perView       = {{ $slidesPerView }};
        var perViewTablet = {{ $colsTablet }};
        var perViewMobile = {{ $colsMobile }};
        var bpMed = {{ $bpMed }};
        var bpSm  = {{ $bpSm }};
        var gap   = {{ $colSpacing }};
        var loop  = {{ $carouselLoop ? 'true' : 'false' }};
        var autoplay      = {{ $carouselAutoplay ? 'true' : 'false' }};
        var autoplaySpeed = {{ $autoplaySpeed }};
        var dotColor  = '{{ $dotColor }}', dotActive = '{{ $dotActiveColor }}';
        var dotSize   = {{ $dotSize }}, dotBorder = {{ $dotBorder }}, dotBorderColor = '{{ $dotBorderColor }}';
        var id    = '{{ $gridId }}';
        var track = document.getElementById(id);
        if (!track || track.dataset.lzCarousel) return;
        track.dataset.lzCarousel = '1';
        var wrap  = track.parentElement;
        var total = track.children.length;
        var idx   = 0, timer = null;

        function per()    { var w = window.innerWidth; return Math.max(1, w <= bpSm ? perViewMobile : (w <= bpMed ? perViewTablet : perView)); }
        function maxIdx() { return Math.max(0, total - per()); }
        // Slide width is set by CSS; measure the first slide to get the px step for the transform.
        function step()   { var c = track.children[0]; return c ? c.getBoundingClientRect().width + gap : 0; }

        // Normal round dots — one per slide position. Count = positions = total - perView + 1
        // (e.g. 5 slides shown 1-at-a-time → 5 dots). Rebuilt when the breakpoint changes.
        function dotCss(on) {
            return 'width:' + dotSize + 'px;height:' + dotSize + 'px;border-radius:50%;padding:0;cursor:pointer;box-sizing:border-box;'
                + 'border:' + (dotBorder > 0 ? (dotBorder + 'px solid ' + dotBorderColor) : 'none') + ';'
                + 'background:' + (on ? dotActive : dotColor) + ';'
                + 'transform:scale(' + (on ? 1.3 : 1) + ');transition:background .25s,transform .25s;';
        }
        function buildDots() {
            var dotsEl = document.getElementById(id + '-dots');
            if (!dotsEl) return;
            var count = maxIdx() + 1;
            if (count <= 1) { dotsEl.style.display = 'none'; dotsEl.innerHTML = ''; dotsEl.dataset.count = '0'; return; }
            dotsEl.style.display = 'flex';
            if (dotsEl.dataset.count === String(count)) return; // already built for this breakpoint
            dotsEl.innerHTML = '';
            for (var i = 0; i < count; i++) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'lz-cz-dot';
                b.setAttribute('aria-label', 'Go to slide ' + (i + 1));
                b.style.cssText = dotCss(i === idx);
                (function (n) { b.addEventListener('click', function () { goTo(n); bump(); }); })(i);
                dotsEl.appendChild(b);
            }
            dotsEl.dataset.count = String(count);
        }

        function render(animate) {
            var sw = step(), m = maxIdx();
            if (idx < 0) idx = loop ? m : 0;
            if (idx > m) idx = loop ? 0 : m;
            track.style.transition = (animate === false) ? 'none' : '';
            track.style.transform  = 'translateX(-' + (idx * sw) + 'px)';
            var prev = document.getElementById(id + '-prev'), next = document.getElementById(id + '-next');
            if (prev) prev.style.opacity = (!loop && idx === 0) ? '0.35' : '1';
            if (next) next.style.opacity = (!loop && idx >= m)  ? '0.35' : '1';
            var dotsEl = document.getElementById(id + '-dots');
            if (dotsEl) {
                for (var i = 0; i < dotsEl.children.length; i++) {
                    var on = (i === idx);
                    dotsEl.children[i].style.background = on ? dotActive : dotColor;
                    dotsEl.children[i].style.transform  = 'scale(' + (on ? 1.3 : 1) + ')';
                }
            }
        }
        function goTo(n) { idx = n; render(true); }
        function next()  { var m = maxIdx(); goTo(idx >= m ? (loop ? 0 : m) : idx + 1); }
        function prev()  { var m = maxIdx(); goTo(idx <= 0 ? (loop ? m : 0) : idx - 1); }

        // Autoplay: advance one slide and wrap to the start, so each dot lights up in turn.
        function autoTick() { var m = maxIdx(); goTo(idx >= m ? 0 : idx + 1); }
        function start() { if (autoplay && !timer && total > per()) timer = setInterval(autoTick, autoplaySpeed); }
        function stop()  { if (timer) { clearInterval(timer); timer = null; } }
        function bump()  { stop(); start(); }
        wrap.addEventListener('mouseenter', stop);
        wrap.addEventListener('mouseleave', start);
        wrap.addEventListener('focusin', stop);
        wrap.addEventListener('focusout', start);

        // Per-instance handlers + global dispatchers (used by the inline arrow/dot onclick)
        window['lzSlider_' + id]     = function(dir) { dir === 'next' ? next() : prev(); bump(); };
        window['lzSliderGoTo_' + id] = function(n)   { goTo(n * per()); bump(); };
        window.lzSlider     = window.lzSlider     || function(g, d) { var f = window['lzSlider_' + g];     if (f) f(d); };
        window.lzSliderGoTo = window.lzSliderGoTo || function(g, n) { var f = window['lzSliderGoTo_' + g]; if (f) f(n); };

        // Keyboard (when the carousel is focused)
        wrap.tabIndex = 0;
        wrap.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft')  { prev(); bump(); e.preventDefault(); }
            if (e.key === 'ArrowRight') { next(); bump(); e.preventDefault(); }
        });

        // Drag / swipe (mouse + touch via Pointer Events)
        var dragging = false, startX = 0, startTf = 0, moved = 0;
        track.addEventListener('pointerdown', function(e) {
            if (e.button && e.button !== 0) return;
            dragging = true; moved = 0; startX = e.clientX;
            startTf = -(idx * step());
            track.classList.add('lz-grabbing'); track.style.transition = 'none'; stop();
            try { track.setPointerCapture(e.pointerId); } catch (_) {}
        });
        track.addEventListener('pointermove', function(e) {
            if (!dragging) return;
            moved = e.clientX - startX;
            track.style.transform = 'translateX(' + (startTf + moved) + 'px)';
        });
        function endDrag() {
            if (!dragging) return;
            dragging = false; track.classList.remove('lz-grabbing');
            var threshold = Math.max(40, (step() - gap) * 0.18);
            if (moved <= -threshold) idx++; else if (moved >= threshold) idx--;
            render(true); start();
        }
        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);
        // Swallow the click that follows a real drag so card links don't fire mid-swipe
        track.addEventListener('click', function(e) {
            if (Math.abs(moved) > 6) { e.preventDefault(); e.stopPropagation(); }
        }, true);

        window.addEventListener('resize', function() { buildDots(); render(false); });

        // Init once the wrapper has a real width (handles deferred/hidden containers)
        (function init() {
            if (wrap.offsetWidth > 0) { buildDots(); render(false); start(); }
            else requestAnimationFrame(init);
        })();
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
