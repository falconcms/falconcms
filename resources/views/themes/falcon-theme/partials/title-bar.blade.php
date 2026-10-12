@php
    // Resolve the title from whatever the current view provides:
    //  - archives pass $title, single post/page/product share $current_post.
    $tbTitle = '';
    if (!empty($title)) {
        $tbTitle = $title;
    } elseif (!empty($current_post) && is_object($current_post)) {
        $tbTitle = $current_post->title ?? '';
    } elseif (!empty($post) && is_object($post)) {
        $tbTitle = $post->title ?? '';
    }
    $tbTitle = trim((string) $tbTitle);

    $path   = trim(request()->path(), '/');
    $isHome = ($path === '' || $path === '/');

    $tbShow = get_cms_option('theme_title_bar_enabled', '1') === '1'
              && $tbTitle !== ''
              && !$isHome;

    if ($tbShow) {
        $showTitle = get_cms_option('theme_title_bar_show_title', '1') === '1';
        $showCrumb = get_cms_option('theme_title_bar_show_breadcrumb', '1') === '1';
        $align     = get_cms_option('theme_title_bar_align', 'left');
        $alignCls  = $align === 'center' ? 'items-center text-center'
                   : ($align === 'right' ? 'items-end text-right' : 'items-start text-left');
        $bg        = get_cms_option('theme_title_bar_bg_color', '#f7f8fa');
        $tc        = get_cms_option('theme_title_bar_text_color', '#1d2327');
        $cc        = get_cms_option('theme_title_bar_breadcrumb_color', '#6b7280');
        $size      = get_cms_option('theme_title_bar_title_size', '32px');
        $pt        = get_cms_option('theme_title_bar_padding_top', '40px');
        $pb        = get_cms_option('theme_title_bar_padding_bottom', '40px');
        $border    = get_cms_option('theme_title_bar_border_bottom', '1') === '1';
        $bcolor    = get_cms_option('theme_title_bar_border_color', '#e5e7eb');

        $barStyle = "background:{$bg};padding-top:{$pt};padding-bottom:{$pb};"
                  . ($border ? "border-bottom:1px solid {$bcolor};" : '');
    }
@endphp

@if($tbShow && ($showTitle || $showCrumb))
<div class="lazy-title-bar" style="{{ $barStyle }}">
    <div class="container-custom flex flex-col gap-2 {{ $alignCls }}">
        @if($showTitle)
            <h1 class="lazy-title-bar-title" style="color:{{ $tc }};font-size:{{ $size }};line-height:1.2;margin:0;font-weight:700;">{{ $tbTitle }}</h1>
        @endif
        @if($showCrumb)
            {{-- The site's one breadcrumb trail: the same one Google reads from the page's structured data. --}}
            @php
                $tbCrumbs = \FalconCms\Core\Support\Breadcrumbs::trail(
                    (!empty($current_post) && is_object($current_post)) ? $current_post : ((!empty($post) && is_object($post)) ? $post : null),
                    $tbTitle
                ) ?: [['title' => 'Home', 'url' => url('/')], ['title' => $tbTitle, 'url' => null]];
            @endphp
            <nav class="lazy-title-bar-breadcrumb text-[14px]" style="color:{{ $cc }};" aria-label="Breadcrumb">
                @foreach($tbCrumbs as $crumb)
                    @if(!$loop->last)
                        <a href="{{ $crumb['url'] }}" style="color:{{ $cc }};" class="hover:opacity-75 transition-opacity">{{ $crumb['title'] }}</a>
                        <span class="mx-1.5 opacity-60">/</span>
                    @else
                        <span style="color:{{ $tc }};" aria-current="page">{{ $crumb['title'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif
    </div>
</div>
@endif
