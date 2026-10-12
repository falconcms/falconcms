{{--
    The author box under a post: who wrote it, with their photo, bio and profiles, and a link
    to everything else they wrote. Readers and search engines both weigh who stands behind
    content. Shown only for an author who has written a bio, so a site whose users have none
    looks exactly as before; Customizer → Blog → Single Blog can turn it off.
--}}
@php
    $boxAuthor = $post->user ?? null;
    $boxBio = trim(strip_tags((string) ($boxAuthor->bio ?? '')));
    $boxOn = get_cms_option('theme_single_show_author_box', '1') === '1' && !in_array($post->type, ['page', 'product'], true);
@endphp
@if($boxOn && $boxAuthor && $boxBio !== '')
    @php
        $boxAvatar = $boxAuthor->avatar ? (str_starts_with($boxAuthor->avatar, 'http') ? $boxAuthor->avatar : asset('storage/'.ltrim($boxAuthor->avatar, '/'))) : null;
        $boxLinks = array_filter(['Facebook' => $boxAuthor->facebook_url ?? null, 'X' => $boxAuthor->twitter_url ?? null, 'LinkedIn' => $boxAuthor->linkedin_url ?? null]);
    @endphp
    <aside class="falcon-author-box mt-12 p-6 rounded-2xl bg-slate-50 border border-slate-100 flex gap-5 items-start" aria-label="About the author">
        @if($boxAvatar)
            <img src="{{ $boxAvatar }}" alt="{{ $boxAuthor->name }}" width="72" height="72" class="w-[72px] h-[72px] rounded-full object-cover shrink-0" loading="lazy">
        @endif
        <div class="min-w-0">
            <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-1">Written by</div>
            <a href="{{ route('frontend.author', $boxAuthor->id) }}" class="text-lg font-bold text-slate-900 hover:text-primary" rel="author">{{ $boxAuthor->name }}</a>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $boxBio }}</p>
            @if($boxLinks)
                <div class="mt-3 flex gap-4 text-sm">
                    @foreach($boxLinks as $label => $href)
                        <a href="{{ $href }}" target="_blank" rel="noopener me" class="font-semibold text-slate-500 hover:text-primary">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </aside>
@endif
