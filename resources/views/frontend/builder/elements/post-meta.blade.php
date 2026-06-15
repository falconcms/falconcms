@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    // Meta item settings
    $metaOrder        = $s['metaOrder']        ?? ['categories', 'tags', 'author', 'date', 'reading_time'];
    $showCategories   = $s['showCategories']   ?? true;
    $categoryTaxonomy = trim($s['categoryTaxonomy'] ?? 'category');
    $showTags         = $s['showTags']         ?? false;
    $tagTaxonomy      = trim($s['tagTaxonomy']      ?? 'tag');
    $showAuthor       = $s['showAuthor']       ?? true;
    $showDate         = $s['showDate']         ?? true;
    $showReadingTime  = $s['showReadingTime']  ?? false;
    $showIcons        = $s['showIcons']        ?? true;
    $limitCategories  = isset($s['limitCategories']) && (int)$s['limitCategories'] > 0 ? (int)$s['limitCategories'] : null;
    $limitTags        = isset($s['limitTags'])        && (int)$s['limitTags']        > 0 ? (int)$s['limitTags']        : null;
    $dateFormat       = $s['dateFormat']       ?? 'M j, Y';
    $separator        = $s['separator']        ?? '·';
    $layout           = $s['layout']           ?? 'inline';
    $isStacked        = $layout === 'stacked';
    $metaAlign        = $s['metaAlign']        ?? 'left';
    $justifyContent   = match($metaAlign) { 'center' => 'center', 'right' => 'flex-end', default => 'flex-start' };

    // Determine post type for smart taxonomy auto-detection
    $postType = isset($post) ? ($post->type ?? 'post') : 'post';

    // Auto-detect: if user left defaults and post is a product, switch to product taxonomies
    if ($postType === 'product') {
        if ($categoryTaxonomy === 'category') $categoryTaxonomy = 'product-category';
        if ($tagTaxonomy === 'tag')           $tagTaxonomy       = 'product-tag';
    }

    $fontFamily     = $s['meta_family']         ?? 'inherit';
    $fontWeight     = $s['meta_weight']         ?? ($s['fontWeight']    ?? '400');
    $fontSize       = $s['meta_size']           ?? (($s['fontSize'] ?? 13) . ($s['fontSizeUnit'] ?? 'px'));
    $lineHeight     = $s['meta_line_height']     ?? 'inherit';
    $letterSpacing  = $s['meta_letter_spacing']  ?? 'normal';
    $textTransform  = $s['meta_transform']       ?? 'none';
    $color       = $s['color']            ?? '#6b7280';
    $linkColor   = $s['linkColor']        ?? '#374151';
    $gap         = ($s['gap']             ?? 12)   . ($s['gapUnit']          ?? 'px');
    $mt  = (isset($s['marginTop'])    && $s['marginTop']    !== '' ? $s['marginTop']    : 0)  . ($s['marginTopUnit']    ?? 'px');
    $mb  = (isset($s['marginBottom']) && $s['marginBottom'] !== '' ? $s['marginBottom'] : 8)  . ($s['marginBottomUnit'] ?? 'px');
    $mtT = (isset($s['marginTop_tablet'])    && $s['marginTop_tablet']    !== '' && $s['marginTop_tablet']    !== null) ? $s['marginTop_tablet']    . ($s['marginTopUnit_tablet']    ?? $s['marginTopUnit']    ?? 'px') : $mt;
    $mbT = (isset($s['marginBottom_tablet']) && $s['marginBottom_tablet'] !== '' && $s['marginBottom_tablet'] !== null) ? $s['marginBottom_tablet'] . ($s['marginBottomUnit_tablet'] ?? $s['marginBottomUnit'] ?? 'px') : $mb;
    $mtM = (isset($s['marginTop_mobile'])    && $s['marginTop_mobile']    !== '' && $s['marginTop_mobile']    !== null) ? $s['marginTop_mobile']    . ($s['marginTopUnit_mobile']    ?? $s['marginTopUnit']    ?? 'px') : $mtT;
    $mbM = (isset($s['marginBottom_mobile']) && $s['marginBottom_mobile'] !== '' && $s['marginBottom_mobile'] !== null) ? $s['marginBottom_mobile'] . ($s['marginBottomUnit_mobile'] ?? $s['marginBottomUnit'] ?? 'px') : $mbT;
    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;
    $pmUid    = 'pm-' . ($el['id'] ?? uniqid());
    $cssClass = $s['cssClass'] ?? '';
    $cssId    = $s['cssId']    ?? '';

    // Post card context — safe fallback outside card loops
    $ctxAuthor     = $postAuthor    ?? null;
    $ctxPublished  = $postPublishedAt ?? $postCreatedAt ?? null;
    $ctxContent    = $postContent   ?? '';

    // --- Term resolver: taxonomy slug → Collection ---
    // Tries Eloquent relation (via map or camelCase), then ACPT taxonomyTerms().
    $resolveTerms = function (string $slug) use ($post): \Illuminate\Support\Collection {
        if (!$slug || !isset($post)) return collect();

        static $cache = [];
        if (isset($cache[$slug])) return $cache[$slug];

        $map = [
            'category'            => 'categories',
            'categories'          => 'categories',
            'tag'                 => 'tags',
            'tags'                => 'tags',
            'product-category'    => 'productCategories',
            'product-categories'  => 'productCategories',
            'product_category'    => 'productCategories',
            'product_categories'  => 'productCategories',
            'product-tag'         => 'productTags',
            'product-tags'        => 'productTags',
            'product_tag'         => 'productTags',
            'product_tags'        => 'productTags',
        ];

        // Try mapped relation name first, then camelCase of the slug
        foreach (array_unique([$map[$slug] ?? null, \Illuminate\Support\Str::camel(str_replace(['-', '.'], '_', $slug))]) as $rel) {
            if (!$rel) continue;
            if (method_exists($post, $rel)) {
                try {
                    $r = $post->{$rel};
                    if ($r instanceof \Illuminate\Support\Collection) {
                        return $cache[$slug] = $r;
                    }
                } catch (\Throwable $e) {}
            }
        }

        // Fallback: ACPT taxonomy_terms table — query directly by taxonomy_slug column
        if (method_exists($post, 'taxonomyTerms')) {
            try {
                // Try exact match, then common slug variations
                $slugVariants = array_unique([$slug, str_replace('-', '_', $slug), str_replace('_', '-', $slug)]);
                $terms = $post->taxonomyTerms()
                    ->whereIn('taxonomy_slug', $slugVariants)
                    ->get();
                if ($terms->isNotEmpty()) return $cache[$slug] = $terms;
            } catch (\Throwable $e) {}
        }

        return $cache[$slug] = collect();
    };

    // Resolve terms (only when slug is non-empty; empty slug = user disabled the field → show nothing)
    $ctxCategories = ($showCategories && $categoryTaxonomy !== '')
        ? $resolveTerms($categoryTaxonomy)
        : collect();
    if ($limitCategories) $ctxCategories = $ctxCategories->take($limitCategories);

    $ctxTags = ($showTags && $tagTaxonomy !== '')
        ? $resolveTerms($tagTaxonomy)
        : collect();
    if ($limitTags) $ctxTags = $ctxTags->take($limitTags);

    // Normalize taxonomy slug → correct singular URL prefix (handles plural/underscore variants saved in settings)
    $urlPrefixMap = [
        'category'           => 'category',
        'categories'         => 'category',
        'tag'                => 'tag',
        'tags'               => 'tag',
        'product-category'   => 'product-category',
        'product-categories' => 'product-category',
        'product_category'   => 'product-category',
        'product_categories' => 'product-category',
        'product-tag'        => 'product-tag',
        'product-tags'       => 'product-tag',
        'product_tag'        => 'product-tag',
        'product_tags'       => 'product-tag',
    ];
    $catUrlPrefix = $urlPrefixMap[$categoryTaxonomy] ?? str_replace('_', '-', $categoryTaxonomy);
    $tagUrlPrefix = $urlPrefixMap[$tagTaxonomy]      ?? str_replace('_', '-', $tagTaxonomy);

    // Author link — uses post type so the archive filters correctly
    $authorUrl = null;
    if ($showAuthor && $ctxAuthor && isset($post) && $post->user_id) {
        $authorUrl = url('/author/' . $post->user_id) . '?type=' . $postType;
    }

    // Date string
    $dateStr = '';
    if ($showDate && $ctxPublished) {
        $dateStr = $dateFormat === 'relative'
            ? \Carbon\Carbon::parse($ctxPublished)->diffForHumans()
            : \Carbon\Carbon::parse($ctxPublished)->format($dateFormat);
    }

    // Reading time (200 wpm)
    $readingTimeStr = '';
    if ($showReadingTime && $ctxContent) {
        $wordCount      = str_word_count(strip_tags($ctxContent));
        $readingTimeStr = max(1, (int) ceil($wordCount / 200)) . ' min read';
    }

    // Build meta item map — resolved content per key
    $metaMap = [];

    if ($showCategories) {
        if ($ctxCategories->isNotEmpty()) {
            $catLinks = $ctxCategories->map(function ($cat) use ($catUrlPrefix) {
                $url = url('/' . $catUrlPrefix . '/' . ($cat->slug ?? ''));
                return '<a href="' . e($url) . '" class="lazy-meta-link">' . e($cat->name) . '</a>';
            })->implode(', ');
            $metaMap['categories'] = ['icon' => 'fa fa-folder-open', 'html' => $catLinks, 'type' => 'categories'];
        }
    }

    if ($showTags) {
        if ($ctxTags->isNotEmpty()) {
            $tagLinks = $ctxTags->map(function ($tag) use ($tagUrlPrefix) {
                $url = url('/' . $tagUrlPrefix . '/' . ($tag->slug ?? ''));
                return '<a href="' . e($url) . '" class="lazy-meta-link">' . e($tag->name) . '</a>';
            })->implode(', ');
            $metaMap['tags'] = ['icon' => 'fa fa-tags', 'html' => $tagLinks, 'type' => 'tags'];
        }
    }

    if ($showAuthor && $ctxAuthor) {
        $authorHtml = $authorUrl
            ? '<a href="' . e($authorUrl) . '" class="lazy-meta-link">' . e($ctxAuthor) . '</a>'
            : e($ctxAuthor);
        $metaMap['author'] = ['icon' => 'fa fa-user', 'html' => $authorHtml, 'type' => 'author'];
    }

    if ($showDate && $dateStr) {
        $metaMap['date'] = ['icon' => 'fa fa-calendar', 'html' => e($dateStr), 'type' => 'date'];
    }

    if ($showReadingTime && $readingTimeStr) {
        $metaMap['reading_time'] = ['icon' => 'fa fa-clock', 'html' => e($readingTimeStr), 'type' => 'reading-time'];
    }

    // Render items in metaOrder order
    $metaItems = [];
    foreach ($metaOrder as $key) {
        if (isset($metaMap[$key])) {
            $metaItems[] = $metaMap[$key];
        }
    }

    $wrapStyle  = "width:100%;align-self:stretch;box-sizing:border-box;";
    $wrapStyle .= "font-family:{$fontFamily};font-size:{$fontSize};font-weight:{$fontWeight};";
    $wrapStyle .= "line-height:{$lineHeight};letter-spacing:{$letterSpacing};text-transform:{$textTransform};";
    $wrapStyle .= "color:{$color};";
    $wrapStyle .= "display:flex;flex-wrap:wrap;";
    $wrapStyle .= "justify-content:{$justifyContent};";
    $wrapStyle .= "align-items:" . ($isStacked ? 'flex-start' : 'center') . ";";
    $wrapStyle .= "flex-direction:" . ($isStacked ? 'column' : 'row') . ";";
    $wrapStyle .= "gap:" . ($isStacked ? '4px' : $gap) . ";";

    // Google Font loading (same pattern as menu element)
    $fontToLoad = null;
    if ($fontFamily !== 'inherit' && $fontFamily !== '') {
        $primaryFamily = trim(explode(',', $fontFamily)[0], " '\"");
        if ($primaryFamily && strtolower($primaryFamily) !== 'inherit') {
            $fontToLoad = $primaryFamily;
        }
    }
@endphp

@if($fontToLoad)
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $fontToLoad) }}:wght@100;200;300;400;500;600;700;800;900&display=swap">
@endif

@if(!empty($metaItems))
{{-- Margin + link colors in scoped class so media-query and hover rules win over inline styles --}}
<style>.{{ $pmUid }}{margin-top:{{ $mt }};margin-bottom:{{ $mb }};}@media(min-width:{{ $bpSm1 }}px) and (max-width:{{ $bpMed }}px){.{{ $pmUid }}{margin-top:{{ $mtT }};margin-bottom:{{ $mbT }};}}@media(max-width:{{ $bpSm }}px){.{{ $pmUid }}{margin-top:{{ $mtM }};margin-bottom:{{ $mbM }};}} .{{ $pmUid }} .lazy-meta-link{color:{{ $color }};text-decoration:none;transition:color .15s;} .{{ $pmUid }} .lazy-meta-item:has(.lazy-meta-link):hover .lazy-meta-link,.{{ $pmUid }} .lazy-meta-item:has(.lazy-meta-link):hover i{color:{{ $linkColor }};transition:color .15s;}</style>
<div class="element-post-meta {{ $pmUid }} {{ $cssClass }} {{ $visibilityClasses }}"
     @if($cssId) id="{{ $cssId }}" @endif
     style="{{ $wrapStyle }}">

    @foreach($metaItems as $idx => $item)
        @if(!$isStacked && $idx > 0 && $separator !== '')
            <span style="color:{{ $color }};opacity:0.5;font-size:0.85em;line-height:1;" aria-hidden="true">{{ $separator }}</span>
        @endif
        <span class="lazy-meta-item lazy-meta-{{ $item['type'] }}"
              style="display:inline-flex;align-items:center;gap:4px;line-height:1.4;">
            @if($showIcons)
                <i class="{{ $item['icon'] }}" style="font-size:0.85em;opacity:0.7;" aria-hidden="true"></i>
            @endif
            <span>{!! $item['html'] !!}</span>
        </span>
    @endforeach

</div>
@endif
