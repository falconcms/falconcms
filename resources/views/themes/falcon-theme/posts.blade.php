<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dramas') | {{ get_cms_option('site_title', 'FalconCMS') }}</title>
    <script src="{{ asset('vendor/falcon-cms/js/tailwind.min.js') }}"></script>
</head>
<body class="bg-gray-900 text-white p-10">
    <h1 class="text-4xl font-bold mb-10 text-center">Featured Dramas </h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @php $postItems = get_falcon_posts(['post_type' => 'post', 'limit' => 6, 'paginate' => true]); @endphp
        
        @foreach($postItems as $post)
            <div class="bg-gray-800 rounded-xl overflow-hidden shadow-2xl border border-gray-700 hover:border-blue-500 transition-all">
                <img src="{{ str_starts_with($post->featured_image, 'http') ? $post->featured_image : asset('storage/'.$post->featured_image) }}" 
                     class="w-full h-48 object-cover" alt="{{ $post->title }}">
                
                <div class="p-6">
                    {{-- Categories --}}
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach($post->categories as $category)
                            <a href="{{ route('frontend.category', $category->slug) }}" class="bg-blue-600 text-[10px] uppercase font-bold px-2 py-1 rounded hover:bg-blue-700 transition">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>

                    <h5 class="text-xl font-bold mb-2">{{ $post->title }}</h5>
                    <p class="text-gray-400 text-sm mb-4 line-clamp-3">
                        {{ get_falcon_excerpt($post, 100) }}
                    </p>

                    {{-- Tags --}}
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('frontend.tag', $tag->slug) }}" class="text-gray-500 text-xs italic hover:text-blue-400 transition">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>

                    <a href="{{ route('frontend.show', ['typeOrSlug' => $post->type, 'slug' => $post->slug]) }}" 
                       class="inline-block bg-white text-black px-6 py-2 rounded-full font-bold text-sm hover:bg-blue-500 hover:text-white transition">
                       Watch Now
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-12 flex justify-center">
        {!! the_lazy_pagination($postItems) !!}
    </div>
</body>
</html>