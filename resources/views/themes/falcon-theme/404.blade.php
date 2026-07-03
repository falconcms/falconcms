@php falcon_layout_context(['kind' => '404']); @endphp
@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', 'Page Not Found')

@section('content')
    {{-- Default 404. A custom Layout targeting "404 Page" with a Content section
         will replace this automatically via the layout's content hook. --}}
    <section class="max-w-3xl mx-auto text-center px-4 py-24 md:py-32">
        <div class="text-[110px] md:text-[140px] font-extrabold leading-none text-[#e2e4e7] select-none">404</div>
        <h1 class="text-[26px] md:text-[32px] font-semibold text-[#1d2327] -mt-4">Oops! Page not found</h1>
        <p class="text-[15px] text-[#646970] mt-3 max-w-xl mx-auto">
            The page you’re looking for doesn’t exist or may have been moved.
        </p>
        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#2271b1] hover:bg-[#135e96] text-white text-[14px] font-semibold rounded transition-colors">
                Back to Home
            </a>
            <a href="{{ function_exists('route') && \Illuminate\Support\Facades\Route::has('frontend.search') ? route('frontend.search') : url('/?s=') }}" class="inline-flex items-center gap-2 px-6 py-3 border border-[#c3c4c7] text-[#1d2327] text-[14px] font-semibold rounded hover:bg-[#f6f7f7] transition-colors">
                Search the site
            </a>
        </div>
    </section>
@endsection
