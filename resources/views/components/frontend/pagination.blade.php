{{--
    The one pagination of the site's pages: blog, categories and tags, custom post type
    archives, the shop, search, builder post grids and the shopper's account.

    A Laravel paginator view: `$paginator->links()` renders it on any page outside the admin
    (FrontendPagination middleware), and the_falcon_pagination($items) anywhere. A simple
    paginator (no total) gets only Previous and Next.

    Colour: the theme's primary colour, or set `--falcon-pg-accent` on a parent.
--}}
@if ($paginator->hasPages())
    @once
    <style>
        .falcon-pagination{--fpg-accent:var(--falcon-pg-accent,{{ get_cms_option('theme_primary_color', '#0091ea') }});--fpg-text:#374151;--fpg-muted:#9ca3af;--fpg-line:#e5e7eb;--fpg-bg:#fff;display:flex;justify-content:center;margin:40px 0 8px;width:100%}
        .falcon-pagination ul{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;list-style:none;margin:0;padding:0}
        .falcon-pagination li{margin:0;padding:0}
        .falcon-pagination li>a,.falcon-pagination li>span{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:40px;height:40px;padding:0 12px;border:1px solid var(--fpg-line);border-radius:10px;background:var(--fpg-bg);color:var(--fpg-text);font-size:14px;font-weight:600;line-height:1;text-decoration:none;transition:color .15s,border-color .15s,background-color .15s,box-shadow .15s,transform .15s}
        .falcon-pagination li>a:hover{border-color:var(--fpg-accent);color:var(--fpg-accent);transform:translateY(-1px);box-shadow:0 4px 12px rgba(15,23,42,.08)}
        .falcon-pagination a:focus-visible{outline:2px solid var(--fpg-accent);outline-offset:2px}
        .falcon-pagination li[aria-current="page"]>span{background:var(--fpg-accent);border-color:var(--fpg-accent);color:#fff;box-shadow:0 4px 14px color-mix(in srgb,var(--fpg-accent) 35%,transparent);cursor:default}
        .falcon-pagination .fpg-dots>span{border-color:transparent;background:transparent;color:var(--fpg-muted);min-width:24px;padding:0 4px;cursor:default}
        .falcon-pagination .fpg-off>span{color:var(--fpg-muted);background:transparent;opacity:.6;cursor:not-allowed}
        .falcon-pagination svg{width:16px;height:16px;flex:none}
        @media (max-width:640px){.falcon-pagination .fpg-label{display:none}.falcon-pagination li>a,.falcon-pagination li>span{min-width:36px;height:36px;padding:0 10px;font-size:13px}.falcon-pagination ul{gap:4px}.falcon-pagination .fpg-far{display:none}}
    </style>
    @endonce
    <nav class="falcon-pagination" role="navigation" aria-label="{{ __('Page navigation') }}">
        <ul>
            @if ($paginator->onFirstPage())
                <li class="fpg-off" aria-disabled="true"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg><span class="fpg-label">{{ __('Previous') }}</span></span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg><span class="fpg-label">{{ __('Previous') }}</span></a></li>
            @endif

            @isset($elements)
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="fpg-dots" aria-hidden="true"><span>&hellip;</span></li>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li aria-current="page"><span>{{ $page }}</span></li>
                            @else
                                {{-- On a phone only the first, the last and the pages next to the current one show. --}}
                                <li @if (abs($page - $paginator->currentPage()) > 1 && $page != 1 && $page != ($paginator->lastPage() ?? $page)) class="fpg-far" @endif><a href="{{ $url }}" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @endisset

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}"><span class="fpg-label">{{ __('Next') }}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a></li>
            @else
                <li class="fpg-off" aria-disabled="true"><span><span class="fpg-label">{{ __('Next') }}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span></li>
            @endif
        </ul>
    </nav>
@endif
