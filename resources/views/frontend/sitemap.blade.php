{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- That declaration is glued together from three pieces so the two characters that open
     a PHP tag never sit next to each other in this file, and it stays on line 1 so nothing
     is emitted ahead of it. Blade compiles a template by running token_get_all() over it
     and applies its directives only to T_INLINE_HTML tokens; where short_open_tag is On
     the tokenizer reads a literal declaration as the start of PHP code, so every line
     below it is passed through uncompiled and the visitor gets a parse error instead of a
     sitemap. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    {{-- Home first, then posts of each included type, categories and tags (SitemapController::entries).
         lastmod is optional in the sitemap spec and only emitted when the row has a date.
         Each post lists the images on its page for Google Images, the featured image first. --}}
    @foreach($entries as $entry)
        <url>
            <loc>{{ $entry['loc'] }}</loc>
            @if(!empty($entry['lastmod']))
            <lastmod>{{ $entry['lastmod'] }}</lastmod>
            @endif
            <changefreq>{{ $entry['changefreq'] }}</changefreq>
            <priority>{{ $entry['priority'] }}</priority>
            @foreach($entry['images'] ?? [] as $image)
            <image:image><image:loc>{{ $image }}</image:loc></image:image>
            @endforeach
        </url>
    @endforeach
</urlset>
