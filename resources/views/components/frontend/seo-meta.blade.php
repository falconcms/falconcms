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

    $ogTitle = $ogTitle ?? $currentTitle;
    $ogDesc = $ogDesc ?? $currentDesc;
    $twitterTitle = $twitterTitle ?? $ogTitle;
    $twitterImage = isset($twitterImage) ? asset('storage/' . $twitterImage) : $currentImage;
@endphp

<!-- Basic Meta Tags -->
<title>{!! $meta($currentTitle) !!}</title>
<meta name="description" content="{!! $meta($currentDesc) !!}">
@if($noindex || $nofollow)
<meta name="robots" content="{{ ($noindex ? 'noindex' : 'index') }},{{ ($nofollow ? 'nofollow' : 'follow') }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
@unless($noindex)
@foreach(falcon_hreflang_links($post ?? null) as $hreflang => $alternate)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $alternate }}">
@endforeach
@endunless

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

<!-- JSON-LD Schema Markup -->
@php
    $schema = [
        "@context" => "https://schema.org",
        "@type" => ($type === 'article' ? 'BlogPosting' : 'WebSite'),
        "headline" => $metaText($currentTitle),
        "description" => $metaText($currentDesc),
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

    // A product page describes a Product, not a WebSite — that is what puts the price, the
    // availability and the star rating into a search result.
    $isProductSchema = false;
    if (isset($post) && function_exists('falcon_product_schema')) {
        $productSchema = falcon_product_schema($post);
        if ($productSchema) {
            $schema = $productSchema;
            $isProductSchema = true;
        }
    }

    // Products fall into the 'article' bucket above, so without this guard the blog fields
    // (author, datePublished) were being appended to the Product — properties it does not have.
    if (isset($post) && $type === 'article' && !$isProductSchema) {
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
