@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', $title ?? 'Archive')

@section('content')
@php
    $isProductArchive = ($archivePostType ?? 'post') === 'product';
    $primaryColor     = get_cms_option('theme_primary_color', '#0091ea');
    $searchTerm       = request()->query('s');
    $highlight = function($text, $term) {
        if (!$term) return e($text);
        $parts = preg_split('/(' . preg_quote($term, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) <= 1) return e($text);
        $out = '';
        foreach ($parts as $i => $part) {
            $out .= ($i % 2 === 1)
                ? '<mark style="background:#fef08a;color:inherit;padding:0 2px;border-radius:2px;">' . e($part) . '</mark>'
                : e($part);
        }
        return $out;
    };
@endphp

<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        <!-- Breadcrumbs -->
        <nav class="text-[14px] text-gray-400 mb-6" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-heading">Home</a> / <span>{{ $title }}</span>
        </nav>

        <h1 class="text-[36px] md:text-[42px] font-normal text-heading mb-8">{{ $title }}</h1>

        <div class="flex flex-col md:flex-row justify-between items-center mb-8 text-[14px] text-[#777]">
            <div class="mb-4 md:mb-0">
                @if($posts->count() > 0)
                    Showing {{ $posts->firstItem() }}&ndash;{{ $posts->lastItem() }} of {{ $posts->total() }} results
                @else
                    Showing all results
                @endif
            </div>
            @if($isProductArchive)
            <div>
                <form action="" method="GET" id="sorting-form">
                    @if(request('s')) <input type="hidden" name="s" value="{{ request('s') }}"> @endif
                    <select name="orderby" class="border border-gray-200 rounded-sm bg-white focus:ring-0 focus:border-gray-300 text-[#777] cursor-pointer text-[14px] font-normal pl-3 pr-8 py-2" onchange="this.form.submit()">
                        <option value="latest" {{ request('orderby') == 'latest' ? 'selected' : '' }}>Default sorting</option>
                        <option value="popularity" {{ request('orderby') == 'popularity' ? 'selected' : '' }}>Sort by popularity</option>
                        <option value="rating" {{ request('orderby') == 'rating' ? 'selected' : '' }}>Sort by average rating</option>
                        <option value="price" {{ request('orderby') == 'price' ? 'selected' : '' }}>Sort by price: low to high</option>
                        <option value="price-desc" {{ request('orderby') == 'price-desc' ? 'selected' : '' }}>Sort by price: high to low</option>
                    </select>
                </form>
            </div>
            @endif
        </div>

        @if($posts->count() > 0)

        {{-- ═══ PRODUCT ARCHIVE ═══ --}}
        @if($isProductArchive)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-12 lb-grid-4">
            @foreach($posts as $product)
                <div class="group flex flex-col">
                    <a href="{{ get_falcon_permalink($product) }}" class="block relative pt-[100%] overflow-hidden bg-[#eef1f5] mb-4">
                        @if($product->featured_image)
                            <img src="{{ str_starts_with($product->featured_image, 'http') ? $product->featured_image : asset('storage/'.$product->featured_image) }}" alt="{{ $product->title }}" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-90 group-hover:opacity-100 transition-opacity">
                        @else
                            <img src="{{ asset('assets/images/placeholder.jpg') }}" alt="Placeholder" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-70">
                        @endif
                        @if($product->shopData && $product->shopData->sale_price)
                            <span class="absolute top-3 left-3 bg-sky-100 text-sky-700 text-[13px] font-bold px-3 py-1 rounded-full shadow uppercase tracking-wide z-10">Sale!</span>
                        @endif
                    </a>
                    <div class="flex flex-col flex-grow text-left px-1">
                        <div class="text-[12px] text-[#999] mb-0.5">
                            @php
                                $cat = null;
                                try { $cat = $product->productCategories->first() ?: $product->taxonomyTerms()->whereIn('taxonomy_slug', ['product_category','product_cat'])->first(); } catch(\Throwable $e) {}
                            @endphp
                            {{ $cat->name ?? 'Product' }}
                        </div>
                        <h2 class="text-[15px] font-bold text-heading hover:text-primary transition-colors mb-1 leading-tight">
                            <a href="{{ get_falcon_permalink($product) }}">{{ $product->title }}</a>
                        </h2>
                        <div class="text-heading font-bold text-[14px] mb-3">
                            @if($product->shopData && $product->shopData->sale_price)
                                <span class="line-through text-[#a5a5a5] font-normal mr-1.5">{{ falcon_price_format($product->shopData->price) }}</span>
                                <span>{{ falcon_price_format($product->shopData->sale_price) }}</span>
                            @else
                                <span>{{ falcon_price_format($product->shopData->price ?? 0) }}</span>
                            @endif
                        </div>
                        <div class="mt-auto flex flex-wrap gap-2">
                            @if(falcon_is_variable_product($product))
                                <a href="{{ get_falcon_permalink($product) }}" class="w-full text-center bg-primary text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-primary-hover transition-colors duration-200">
                                    Select Options
                                </a>
                            @else
                                <button onclick="addToCart({{ $product->id }})" class="flex-1 bg-primary text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-primary-hover transition-colors duration-200">
                                    Add to cart
                                </button>
                                <a href="{{ get_falcon_permalink($product) }}" class="flex-1 text-center bg-white text-primary border border-primary px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-gray-50 transition-colors duration-200">
                                    See Detail
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ═══ POST / CPT / AUTHOR ARCHIVE ═══ --}}
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 lb-grid-3">
            @foreach($posts as $postItem)
            @php
                $permalink  = get_falcon_permalink($postItem);
                $imgSrc     = $postItem->featured_image ?? null;
                if ($imgSrc && !str_starts_with($imgSrc, 'http')) $imgSrc = asset('storage/' . $imgSrc);
                $rawContent = $postItem->content ?? '';
                $trimmedC   = ltrim($rawContent);
                $rawExcerpt = '';
                if (!empty($trimmedC)) {
                    if ($trimmedC[0] === '[' || $trimmedC[0] === '{') {
                        try {
                            $containers = json_decode($trimmedC, true) ?? [];
                            $textParts  = [];
                            $extractText = null;
                            $extractText = function(array $elements) use (&$extractText, &$textParts) {
                                foreach ($elements as $el) {
                                    $type = $el['type'] ?? '';
                                    $s    = $el['settings'] ?? [];
                                    if (in_array($type, ['text_block', 'heading', 'title', 'special_text'])) {
                                        $t = strip_tags($s['content'] ?? $s['text'] ?? '');
                                        if ($t) $textParts[] = trim($t);
                                    }
                                    foreach ($el['rows'] ?? [] as $row) {
                                        foreach ($row['columns'] ?? [] as $col) {
                                            $extractText($col['elements'] ?? []);
                                        }
                                    }
                                    foreach ($el['columns'] ?? [] as $col) {
                                        $extractText($col['elements'] ?? []);
                                    }
                                }
                            };
                            $extractText($containers);
                            $rawExcerpt = implode(' ', $textParts);
                        } catch (\Throwable $e) {}
                    } else {
                        $rawExcerpt = strip_tags($rawContent);
                    }
                }
                $excerpt    = !empty($postItem->excerpt)
                    ? $postItem->excerpt
                    : (mb_strlen($rawExcerpt) > 140 ? mb_substr($rawExcerpt, 0, 140) . '…' : $rawExcerpt);
                $dateStr    = optional($postItem->published_at ?? $postItem->created_at)->format('M j, Y') ?? '';
                $authorName = optional($postItem->user)->name ?? '';
                $postCats   = $postItem->categories ?? collect();
            @endphp
            <article class="flex flex-col rounded-lg overflow-hidden border border-gray-100 hover:shadow-md transition-shadow duration-200">
                <a href="{{ $permalink }}" class="block relative pt-[56%] overflow-hidden bg-gray-100">
                    @if($imgSrc)
                        <img src="{{ $imgSrc }}" alt="{{ $postItem->title }}"
                             class="absolute inset-0 w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                    @else
                        <div class="absolute inset-0 flex items-center justify-center bg-gray-100">
                            <i class="fa fa-image text-3xl text-gray-300"></i>
                        </div>
                    @endif
                </a>
                <div class="flex flex-col flex-grow p-5">
                    @if($postCats->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach($postCats->take(3) as $cat)
                        <a href="{{ url('/category/' . ($cat->slug ?? '')) }}"
                           style="color:{{ $primaryColor }};background:{{ $primaryColor }}18;text-decoration:none;"
                           class="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full">{{ $cat->name }}</a>
                        @endforeach
                    </div>
                    @endif
                    <h2 class="text-[17px] font-bold text-heading leading-snug mb-2">
                        <a href="{{ $permalink }}" class="hover:text-primary transition-colors">{!! $highlight($postItem->title, $searchTerm) !!}</a>
                    </h2>
                    @if($excerpt)
                    <p class="text-[14px] text-gray-500 leading-relaxed mb-4 flex-grow">{!! $highlight($excerpt, $searchTerm) !!}</p>
                    @endif
                    <div class="mt-auto pt-3 border-t border-gray-100 flex items-center gap-3 text-[13px] text-gray-400">
                        @if($authorName)
                        <span class="flex items-center gap-1">
                            <i class="fa fa-user text-[11px] opacity-60"></i> {{ $authorName }}
                        </span>
                        @endif
                        @if($dateStr)
                        <span class="flex items-center gap-1">
                            <i class="fa fa-calendar text-[11px] opacity-60"></i> {{ $dateStr }}
                        </span>
                        @endif
                        <a href="{{ $permalink }}" class="ml-auto text-[12px] font-semibold" style="color:{{ $primaryColor }}">Read more →</a>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        @endif

        {{-- ═══ PAGINATION ═══ --}}
        @if($posts->hasPages())
        <div class="mt-10 flex items-center gap-1.5 flex-wrap">
            {{-- Prev --}}
            @if($posts->onFirstPage())
                <span class="w-9 h-9 inline-flex items-center justify-center border border-gray-200 rounded-[3px] text-gray-300 cursor-not-allowed select-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @else
                <a href="{{ $posts->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center border border-gray-200 rounded-[3px] text-gray-500 hover:border-[{{ $primaryColor }}] hover:text-[{{ $primaryColor }}] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif

            {{-- Page numbers with ellipsis --}}
            @php
                $cur  = $posts->currentPage();
                $last = $posts->lastPage();
                $range = [];
                for ($p = 1; $p <= $last; $p++) {
                    if ($p === 1 || $p === $last || abs($p - $cur) <= 2) {
                        $range[] = $p;
                    }
                }
                $range = array_unique($range);
                sort($range);
            @endphp
            @php $prevPage = null; @endphp
            @foreach($range as $page)
                @if($prevPage !== null && $page - $prevPage > 1)
                    <span class="w-9 h-9 inline-flex items-center justify-center text-gray-400 text-[13px] select-none">…</span>
                @endif
                @if($page === $cur)
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded-[3px] text-[13px] font-semibold text-white select-none"
                          style="background:{{ $primaryColor }};border:1px solid {{ $primaryColor }}">{{ $page }}</span>
                @else
                    <a href="{{ $posts->url($page) }}"
                       class="w-9 h-9 inline-flex items-center justify-center border border-gray-200 rounded-[3px] text-[13px] text-gray-600 hover:border-[{{ $primaryColor }}] hover:text-[{{ $primaryColor }}] transition-colors">{{ $page }}</a>
                @endif
                @php $prevPage = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($posts->hasMorePages())
                <a href="{{ $posts->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center border border-gray-200 rounded-[3px] text-gray-500 hover:border-[{{ $primaryColor }}] hover:text-[{{ $primaryColor }}] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="w-9 h-9 inline-flex items-center justify-center border border-gray-200 rounded-[3px] text-gray-300 cursor-not-allowed select-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
        @endif

        @else
        <div class="bg-white p-10 text-center text-[#777]">
            <p class="text-lg mb-4">No results found.</p>
            <a href="{{ url('/') }}" class="inline-block bg-primary text-white px-6 py-2 rounded hover:bg-primary-hover transition">Return to Home</a>
        </div>
        @endif
    </div>
</div>

@if($isProductArchive)
<script>
function addToCart(productId) {
    const loadingSwal = Swal.fire({ title: 'Adding to cart...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('{{ route('shop.cart.add') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(res => res.ok ? res.json() : res.json().then(e => Promise.reject(e)))
    .then(data => {
        loadingSwal.close();
        if (data.success) {
            Swal.fire({
                title: 'Added!', text: 'Product added to cart successfully.', icon: 'success',
                showCancelButton: true, confirmButtonColor: '{{ $primaryColor }}',
                confirmButtonText: 'View Cart', cancelButtonText: 'Continue Shopping', background: '#ffffff'
            }).then(r => { if (r.isConfirmed) window.location.href = '{{ route('shop.cart') }}'; });
            document.querySelectorAll('.cart-count-badge').forEach(b => {
                b.textContent = data.cart_count;
                b.classList.toggle('hidden', !data.cart_count);
            });
        } else {
            Swal.fire({ title: 'Error', text: data.message || 'Error adding to cart', icon: 'error', confirmButtonColor: '{{ $primaryColor }}' });
        }
    })
    .catch(() => {
        loadingSwal.close();
        Swal.fire({ title: 'Error', text: 'Could not add product to cart.', icon: 'error', confirmButtonColor: '{{ $primaryColor }}' });
    });
}
</script>
@endif
@stop
