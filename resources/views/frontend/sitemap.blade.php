{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- That declaration is glued together from three pieces so the two characters that open
     a PHP tag never sit next to each other in this file, and it stays on line 1 so nothing
     is emitted ahead of it. Blade compiles a template by running token_get_all() over it
     and applies its directives only to T_INLINE_HTML tokens; where short_open_tag is On
     the tokenizer reads a literal declaration as the start of PHP code, so every line
     below it is passed through uncompiled and the visitor gets a parse error instead of a
     sitemap. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    {{-- Home --}}
    <url>
        <loc>{{ url('/') }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    {{-- Posts and Pages.
         lastmod is optional in the sitemap spec, and a row can reach here without
         timestamps — imported content, or anything written with an INSERT that skipped
         them. Calling ->toAtomString() on that null used to 500 the whole sitemap, so
         the date is emitted only when there is one. --}}
    @foreach($posts as $post)
        @php $modified = $post->updated_at ?? $post->created_at; @endphp
        <url>
            <loc>{{ url($post->slug) }}</loc>
            @if($modified)
            <lastmod>{{ $modified->toAtomString() }}</lastmod>
            @endif
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach

    {{-- Categories --}}
    @foreach($categories as $category)
        <url>
            <loc>{{ route('frontend.category', $category->slug) }}</loc>
            <changefreq>weekly</changefreq>
            <priority>0.6</priority>
        </url>
    @endforeach

    {{-- Tags --}}
    @foreach($tags as $tag)
        <url>
            <loc>{{ route('frontend.tag', $tag->slug) }}</loc>
            <changefreq>weekly</changefreq>
            <priority>0.4</priority>
        </url>
    @endforeach
</urlset>
