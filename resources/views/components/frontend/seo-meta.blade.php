@php
    $siteTitle = get_cms_option('site_title', 'FalconCMS');
    $siteDesc = get_cms_option('site_description', '');
    $separator = get_cms_option('seo_separator', '-');
    $defaultImage = get_cms_option('seo_default_image');
    
    $currentTitle = $siteTitle;
    $currentDesc = $siteDesc;
    $currentImage = $defaultImage ? asset('storage/' . $defaultImage) : null;
    // The address without its query string, so sorted, filtered and tracked copies (?orderby=,
    // ?color=, ?utm_source=) all name the one page. Except ?page=: page 2 of an archive lists other
    // posts than page 1, and Google asks each page of a series to name itself.
    $pageNumber = (int) request()->query('page', 1);
    $canonical = url()->current().($pageNumber > 1 ? '?page='.$pageNumber : '');
    $type = 'website';
    $noindex = false;
    $nofollow = false;

    // Meta text is plain text: decode entities a value may already carry (an excerpt or
    // imported title holding "&amp;"), so it is never escaped twice into "&amp;amp;".
    $metaText = fn ($value) => trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    // Escape for a <title> or a double-quoted attribute. A bare "&" stays literal — valid
    // HTML5 and what the editor typed ("Pro & Agency", not "Pro &amp; Agency"); only an "&"
    // that could start a character reference is encoded.
    $meta = fn ($value) => str_replace(['"', '<', '>'], ['&quot;', '&lt;', '&gt;'],
        preg_replace('/&(?=[A-Za-z0-9#])/', '&amp;', $metaText($value)));
    // "Page - Site", unless the page title already names the site ("Pricing | FalconCMS").
    $withSite = fn ($title) => ((string) $siteTitle !== '' && stripos($metaText($title), $metaText($siteTitle)) !== false)
        ? $title
        : $title . " $separator " . $siteTitle;

    // Determine context
    if (isset($post)) {
        $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
        $currentTitle = $withSite($seo['title'] ?? $post->title);
        $currentDesc = $seo['description'] ?? $post->excerpt ?? Str::limit(falcon_html_to_text($post->content), 160);
        
        $ogImage = $seo['og_image'] ?? $post->featured_image;
        if ($ogImage) {
            $currentImage = asset('storage/' . $ogImage);
        }
        
        $canonical = $seo['canonical_url'] ?? $canonical;
        $noindex = ($seo['noindex'] ?? '0') == '1';
        $nofollow = ($seo['nofollow'] ?? '0') == '1';
        $type = ($post->type === 'page') ? 'website' : 'article';
        
        // Social overrides
        $ogTitle = $seo['og_title'] ?? $seo['title'] ?? $post->title;
        $ogDesc = $seo['og_description'] ?? $seo['description'] ?? $currentDesc;
        $twitterTitle = $seo['twitter_title'] ?? $ogTitle;
        $twitterImage = $seo['twitter_image'] ?? $ogImage;
    } elseif (isset($title)) {
        $currentTitle = $withSite($title);
    }

    // Pages that are no use as a search result: the site's own search results, and an archive
    // with nothing in it. Plugins add theirs (the shop: cart, checkout, account).
    $layoutKind = falcon_layout_context()['kind'] ?? null;
    if ($layoutKind === 'search'
        || ($layoutKind === 'archive' && isset($posts) && $posts instanceof \Illuminate\Contracts\Pagination\Paginator && $posts->isEmpty())) {
        $noindex = true;
    }
    $noindex = (bool) apply_falcon_filters('falcon_seo_noindex', $noindex, $post ?? null);

    // Falcon SEO → "Hide this site from search engines" (demo, staging, under construction).
    if (falcon_site_hidden_from_search()) {
        $noindex = true;
        $nofollow = true;
    }

    $ogTitle = $ogTitle ?? $currentTitle;
    $ogDesc = $ogDesc ?? $currentDesc;
    $twitterTitle = $twitterTitle ?? $ogTitle;
    $twitterImage = isset($twitterImage) ? asset('storage/' . $twitterImage) : $currentImage;
@endphp

<!-- Basic Meta Tags -->
<title>{!! $meta($currentTitle) !!}</title>
<meta name="description" content="{!! $meta($currentDesc) !!}">
@if($noindex)
<meta name="robots" content="noindex,{{ $nofollow ? 'nofollow' : 'follow' }}">
@else
@php
    // An indexable page needs no robots tag; plugins add directives (Falcon SEO: large image
    // previews for Discover).
    $robots = (array) apply_falcon_filters('falcon_seo_robots', $nofollow ? ['index', 'nofollow'] : [], $post ?? null);
@endphp
@if($robots)
<meta name="robots" content="{{ implode(',', $robots) }}">
@endif
@endif
<link rel="canonical" href="{{ $canonical }}">
{{-- Plugins add links of their own here (Falcon SEO: hreflang alternates). --}}
{!! do_falcon_action('falcon_seo_head_links', $post ?? null, $noindex) !!}

<!-- Open Graph / Facebook -->
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{!! $meta($ogTitle) !!}">
<meta property="og:description" content="{!! $meta($ogDesc) !!}">
@if($currentImage)
<meta property="og:image" content="{{ $currentImage }}">
@endif

<!-- Twitter -->
@php
    $twitterCardType = get_cms_option('twitter_card_type', 'summary_large_image');
    $twitterHandle   = get_cms_option('twitter_handle', '');
@endphp
<meta property="twitter:card" content="{{ $twitterCardType }}">
<meta property="twitter:url" content="{{ url()->current() }}">
<meta property="twitter:title" content="{!! $meta($twitterTitle) !!}">
<meta property="twitter:description" content="{!! $meta($ogDesc) !!}">
@if($twitterImage)
<meta property="twitter:image" content="{{ $twitterImage }}">
@endif
@if($twitterHandle)
<meta property="twitter:site" content="{{ '@' . ltrim($twitterHandle, '@') }}">
@endif

@php
    // Structured data comes from plugins (Falcon SEO: the organization, the site, this page and
    // its author as one linked graph). Nothing to say, no script.
    $schema = !has_falcon_filter('falcon_seo_schema') ? null : apply_falcon_filters('falcon_seo_schema', null, [
        'site_title' => (string) $siteTitle,
        'title' => $metaText($currentTitle),
        'description' => $metaText($currentDesc),
        'image' => $currentImage,
        'type' => $type,
        'post' => $post ?? null,
        'breadcrumbs' => \FalconCms\Core\Support\Breadcrumbs::trail($post ?? null, $title ?? null),
    ]);
@endphp
@if($schema)
<!-- JSON-LD -->
<script type="application/ld+json">
{!! json_encode($schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endif
