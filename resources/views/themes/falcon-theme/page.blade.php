@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', $post->title)

@section('content')
    @php
        $isBuilder = $post->editor_type === 'builder' || (is_string($post->content) && (str_starts_with($post->content, '[') || str_starts_with($post->content, '{')));
    @endphp

    @if($isBuilder)
        <div class="lazy-content-wrapper">
            {!! get_lazy_content($post->content) !!}
        </div>
    @else
        {{-- Page title + breadcrumb are provided solely by the Customizer Title Bar (Appearance → Customize → Title Bar). --}}
        <!-- Page Content -->
        <section class="py-20 bg-white">
            <div class="container-custom">
                <div class="prose prose-lg prose-slate max-w-none">
                    <div class="lazy-content-wrapper">
                        {!! do_lazy_shortcode($post->content) !!}
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection
