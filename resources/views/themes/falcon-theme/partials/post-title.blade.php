{{-- The entry's title as the page's one H1 when the Page Title Bar does not show it (switched
     off, or its title hidden). Without it a post had no H1 at all. $class sets its look. --}}
@unless(falcon_title_bar_shows_title())
    <h1 class="{{ $class ?? 'text-3xl md:text-5xl font-extrabold text-slate-900 leading-tight tracking-tight mb-6' }}">{{ $post->title }}</h1>
@endunless
