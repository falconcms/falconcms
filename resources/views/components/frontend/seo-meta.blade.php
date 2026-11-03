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
    // /index.php is the front controller, not a page: it named itself as a second home page.
    $canonical = preg_replace('#/index\.php(?=/|$)#', '', url()->current()).($pageNumber > 1 ? '?page='.$pageNumber : '');
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
        // The first that says something: the SEO field, the excerpt, then the post's own words.
        // A builder page's content is JSON, and read as HTML it printed its settings
        // ([{"id":…,"settings":…) as the description on every page nobody wrote one for.
        $plainText = function_exists('falcon_post_plain_text') ? falcon_post_plain_text($post) : falcon_html_to_text($post->content);
        $currentDesc = trim((string) ($seo['description'] ?? '')) ?: (trim((string) ($post->excerpt ?? '')) ?: Str::limit(trim(preg_replace('/\s+/u', ' ', $plainText)), 160));
        
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
        // A taxonomy archive: its own description (the site's would repeat on every archive),
        // and a tag says it is one, so a tag and a category of one name ("Laravel CMS") do not
        // share a title.
        $archiveCtx = falcon_layout_context();
        $archiveTax = ($archiveCtx['kind'] ?? null) === 'archive' ? ($archiveCtx['taxonomy'] ?? null) : null;
        $isTagArchive = in_array($archiveTax, ['post_tag', 'product_tag'], true);
        $currentTitle = $withSite($isTagArchive ? 'Posts tagged '.$title : $title);
        if ($archiveTax && !empty($archiveCtx['term_id'])) {
            $termModel = [
                'category' => \FalconCms\Core\Models\Category::class,
                'post_tag' => \FalconCms\Core\Models\Tag::class,
                'product_category' => \FalconCms\Core\Models\ProductCategory::class,
                'product_tag' => \FalconCms\Core\Models\ProductTag::class,
            ][$archiveTax] ?? \FalconCms\Core\Models\TaxonomyTerm::class;
            try {
                $termDesc = class_exists($termModel) ? trim(strip_tags((string) ($termModel::find($archiveCtx['term_id'])->description ?? ''))) : '';
            } catch (\Throwable $e) {
                $termDesc = '';
            }
            $currentDesc = $termDesc !== ''
                ? $termDesc
                : ($isTagArchive ? 'Posts tagged '.$title : $title).': articles and guides from '.$siteTitle.'.';
        }
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
<meta name="twitter:card" content="{{ $twitterCardType }}">
<meta name="twitter:url" content="{{ url()->current() }}">
<meta name="twitter:title" content="{!! $meta($twitterTitle) !!}">
<meta name="twitter:description" content="{!! $meta($ogDesc) !!}">
@if($twitterImage)
<meta name="twitter:image" content="{{ $twitterImage }}">
@endif
@if($twitterHandle)
<meta name="twitter:site" content="{{ '@' . ltrim($twitterHandle, '@') }}">
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
