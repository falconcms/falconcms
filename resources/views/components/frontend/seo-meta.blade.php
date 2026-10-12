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
@if($noindex)
<meta name="robots" content="noindex,{{ $nofollow ? 'nofollow' : 'follow' }}">
@elseif(get_cms_option('seo_large_image_preview', '1') !== '0')
{{-- Lets Google show a large image (Discover, results): it needs this and a 1200px+ image. --}}
<meta name="robots" content="index,{{ $nofollow ? 'nofollow' : 'follow' }},max-image-preview:large,max-snippet:-1,max-video-preview:-1">
@elseif($nofollow)
<meta name="robots" content="index,nofollow">
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

<!-- JSON-LD: the site's organization, the site, this page and its author, as one linked graph -->
@php
    $schema = \FalconCms\Core\Support\StructuredData::graph([
        'site_title' => (string) $siteTitle,
        'title' => $metaText($currentTitle),
        'description' => $metaText($currentDesc),
        'image' => $currentImage,
        'type' => $type,
        'post' => $post ?? null,
    ]);
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
