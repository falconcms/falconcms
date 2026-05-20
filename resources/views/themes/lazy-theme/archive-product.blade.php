@extends('cms-dashboard::themes.lazy-theme.layouts.app')

@section('title', $title ?? 'Shop')

@section('content')
<style>
    /* WooCommerce-style Pagination Overrides for Laravel Default Tailwind Pagination */
    nav[role="navigation"] { display: flex; align-items: center; justify-content: flex-start; margin-top: 2rem; }
    nav[role="navigation"] > div:first-child { display: none; } /* Hide the 'Showing X to Y...' text from pagination block */
    nav[role="navigation"] > div:last-child { display: flex; width: auto; }
    nav[role="navigation"] .relative.inline-flex { 
        padding: 0; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;
        font-size: 14px; border-radius: 2px; margin-right: 6px; border: 1px solid #b3d4f0; color: #0070cd; background: white; text-decoration: none;
    }
    nav[role="navigation"] span[aria-current="page"] > span {
        background-color: #0070cd !important; color: white !important; border-color: #0070cd !important; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    }
    nav[role="navigation"] a.relative:hover { background-color: #f0f6fc; }
    nav[role="navigation"] svg { width: 16px; height: 16px; }
</style>

<div class="bg-white py-12 min-h-screen font-sans">
    <div class="container-custom">
        <h1 class="text-[36px] md:text-[42px] font-normal text-[#2c3338] mb-8">{{ $title ?? 'Shop' }}</h1>

        <div class="flex flex-col md:flex-row justify-between items-center mb-8 text-[14px] text-[#777]">
            <div class="mb-4 md:mb-0">
                @if($posts->count() > 0)
                    Showing {{ $posts->firstItem() }}&ndash;{{ $posts->lastItem() }} of {{ $posts->total() }} results
                @else
                    Showing all results
                @endif
            </div>
            <div>
                <form action="" method="GET" id="sorting-form">
                    <select name="orderby" class="border-0 bg-transparent focus:ring-0 text-[#777] cursor-pointer text-[14px] font-normal p-0 pr-6" onchange="this.form.submit()">
                        <option value="latest" {{ request('orderby') == 'latest' ? 'selected' : '' }}>Default sorting</option>
                        <option value="popularity" {{ request('orderby') == 'popularity' ? 'selected' : '' }}>Sort by popularity</option>
                        <option value="rating" {{ request('orderby') == 'rating' ? 'selected' : '' }}>Sort by average rating</option>
                        <option value="latest" {{ request('orderby') == 'latest' ? 'selected' : '' }}>Sort by latest</option>
                        <option value="price" {{ request('orderby') == 'price' ? 'selected' : '' }}>Sort by price: low to high</option>
                        <option value="price-desc" {{ request('orderby') == 'price-desc' ? 'selected' : '' }}>Sort by price: high to low</option>
                    </select>
                </form>
            </div>
        </div>

        @if($posts->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-12">
            @foreach($posts as $product)
                <div class="group flex flex-col">
                    <a href="{{ get_lazy_permalink($product) }}" class="block relative pt-[100%] overflow-hidden bg-[#eef1f5] mb-4">
                        @if($product->featured_image)
                            <img src="{{ str_starts_with($product->featured_image, 'http') ? $product->featured_image : asset('storage/'.$product->featured_image) }}" alt="{{ $product->title }}" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-90 group-hover:opacity-100 transition-opacity">
                        @else
                            <img src="{{ asset('assets/images/placeholder.jpg') }}" alt="Placeholder" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-70">
                        @endif
                        @if(!$product->is_in_stock)
                            <span class="absolute top-3 right-3 bg-red-600 text-white text-[11px] font-bold px-3 py-1 rounded-sm shadow-md uppercase tracking-wider z-10">Out of Stock</span>
                        @endif
                        @if($product->shopData && $product->shopData->sale_price)
                            <span class="absolute top-3 left-3 bg-white text-[#555] text-[11px] font-medium px-2.5 py-0.5 rounded-full shadow-sm z-10">Sale!</span>
                        @endif
                    </a>
                    <div class="flex flex-col flex-grow text-left px-1">
                        <div class="text-[12px] text-[#999] mb-0.5">
                            @if($product->taxonomyTerms && $product->taxonomyTerms->count() > 0)
                                {{ $product->taxonomyTerms->first()->name }}
                            @elseif($product->categories && $product->categories->count() > 0)
                                {{ $product->categories->first()->name }}
                            @else
                                Uncategorized
                            @endif
                        </div>
                        <h2 class="text-[15px] font-bold text-[#2c3338] hover:text-[#0070cd] transition-colors mb-1 leading-tight">
                            <a href="{{ get_lazy_permalink($product) }}">{{ $product->title }}</a>
                        </h2>
                        <div class="text-[#333] font-bold text-[14px] mb-3">
                            @if($product->shopData && $product->shopData->sale_price)
                                <span class="line-through text-[#a5a5a5] font-normal mr-1.5">{{ lazy_price_format($product->shopData->price) }}</span>
                                <span>{{ lazy_price_format($product->shopData->sale_price) }}</span>
                            @else
                                <span>{{ lazy_price_format($product->shopData->price ?? 0) }}</span>
                            @endif
                        </div>
                        <div class="mt-auto flex flex-wrap gap-2">
                            @if(!$product->is_in_stock)
                                <button disabled class="w-full text-center bg-gray-400 text-white cursor-not-allowed px-4 py-2.5 rounded-[3px] text-[13px] font-semibold uppercase tracking-wider">
                                    Out of stock
                                </button>
                            @elseif($product->shopData && $product->shopData->product_type === 'variable')
                                <a href="{{ get_lazy_permalink($product) }}" class="w-full text-center bg-[#1363df] text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-[#005ba6] transition-colors duration-200">
                                    Select Options
                                </a>
                            @else
                                <button onclick="addToCart({{ $product->id }})" class="flex-1 bg-[#0070cd] text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-[#005ba6] transition-colors duration-200">
                                    Add to cart
                                </button>
                                <a href="{{ get_lazy_permalink($product) }}" class="flex-1 text-center bg-white text-[#0070cd] border border-[#0070cd] px-4 py-2.5 rounded-[3px] text-[13px] font-semibold hover:bg-gray-50 transition-colors duration-200">
                                    See Detail
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex justify-start">
            {{ $posts->links() }}
        </div>
        @else
        <div class="bg-white p-10 text-center text-[#777]">
            <p class="text-lg mb-4">No products found.</p>
            <a href="{{ url('/') }}" class="inline-block bg-[#0070cd] text-white px-6 py-2 rounded hover:bg-[#005ba6] transition">Return to Home</a>
        </div>
        @endif
    </div>
</div>

<script>
function addToCart(productId) {
    const loadingSwal = Swal.fire({
        title: 'Adding to cart...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('{{ route('shop.cart.add') }}', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json', 
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(err => { throw err; });
        }
        return res.json();
    })
    .then(data => {
        loadingSwal.close();
        if(data.success) {
            Swal.fire({
                title: 'Added!',
                text: 'Product added to cart successfully.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#0070cd',
                confirmButtonText: 'View Cart',
                cancelButtonText: 'Continue Shopping',
                background: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('shop.cart') }}';
                }
            });
            updateCartBadge(data.cart_count);
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Error adding to cart',
                icon: 'error',
                confirmButtonColor: '#0070cd'
            });
        }
    })
    .catch(err => {
        loadingSwal.close();
        Swal.fire({
            title: 'Error',
            text: 'Could not add product to cart. Please try again.',
            icon: 'error',
            confirmButtonColor: '#0070cd'
        });
    });
}

function updateCartBadge(count) {
    document.querySelectorAll('.cart-count-badge').forEach(badge => {
        badge.textContent = count;
        if(count > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    });
}
</script>
@stop
