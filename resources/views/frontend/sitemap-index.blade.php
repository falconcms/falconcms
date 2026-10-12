{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- A large site's sitemap.xml: an index of sitemap-1.xml, sitemap-2.xml… (SitemapController::PER_FILE
     addresses each). The declaration is glued from pieces for the reason given in sitemap.blade.php. --}}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($files as $file)
        <sitemap>
            <loc>{{ $file['loc'] }}</loc>
            @if(!empty($file['lastmod']))
            <lastmod>{{ $file['lastmod'] }}</lastmod>
            @endif
        </sitemap>
    @endforeach
</sitemapindex>
