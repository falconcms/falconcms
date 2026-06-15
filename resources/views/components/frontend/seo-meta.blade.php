@php
    $siteTitle = get_cms_option('site_title', 'FalconCMS');
    $siteDesc = get_cms_option('site_description', '');
    $separator = get_cms_option('seo_separator', '-');
    $defaultImage = get_cms_option('seo_default_image');
    
    $currentTitle = $siteTitle;
    $currentDesc = $siteDesc;
    $currentImage = $defaultImage ? asset('storage/' . $defaultImage) : null;
    $canonical = url()->current();
    $type = 'website';
    $noindex = false;
    $nofollow = false;

    // Determine context
    if (isset($post)) {
        $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
        $currentTitle = ($seo['title'] ?? $post->title) . " $separator " . $siteTitle;
        $currentDesc = $seo['description'] ?? $post->excerpt ?? Str::limit(strip_tags($post->content), 160);
        
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
        $currentTitle = $title . " $separator " . $siteTitle;
    }

    $ogTitle = $ogTitle ?? $currentTitle;
    $ogDesc = $ogDesc ?? $currentDesc;
    $twitterTitle = $twitterTitle ?? $ogTitle;
    $twitterImage = isset($twitterImage) ? asset('storage/' . $twitterImage) : $currentImage;
@endphp

<!-- Basic Meta Tags -->
<title>{{ $currentTitle }}</title>
<meta name="description" content="{{ $currentDesc }}">
@if($noindex || $nofollow)
<meta name="robots" content="{{ ($noindex ? 'noindex' : 'index') }},{{ ($nofollow ? 'nofollow' : 'follow') }}">
@endif
<link rel="canonical" href="{{ $canonical }}">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDesc }}">
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
<meta property="twitter:title" content="{{ $twitterTitle }}">
<meta property="twitter:description" content="{{ $ogDesc }}">
@if($twitterImage)
<meta property="twitter:image" content="{{ $twitterImage }}">
@endif
@if($twitterHandle)
<meta property="twitter:site" content="@{{ $twitterHandle }}">
@endif

<!-- JSON-LD Schema Markup -->
@php
    $schema = [
        "@context" => "https://schema.org",
        "@type" => ($type === 'article' ? 'BlogPosting' : 'WebSite'),
        "headline" => $currentTitle,
        "description" => $currentDesc,
        "url" => url()->current(),
        "publisher" => [
            "@type" => "Organization",
            "name" => $siteTitle,
            "logo" => [
                "@type" => "ImageObject",
                "url" => asset('favicon.ico')
            ]
        ]
    ];

    if ($currentImage) {
        $schema['image'] = $currentImage;
    }

    if (isset($post) && $type === 'article') {
        $schema['datePublished'] = $post->published_at ? $post->published_at->toIso8601String() : $post->created_at->toIso8601String();
        $schema['dateModified'] = $post->updated_at->toIso8601String();
        $schema['author'] = [
            "@type" => "Person",
            "name" => $post->author->name ?? 'Admin'
        ];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
