<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @php
        $mobileZoom = get_cms_option('theme_mobile_zoom', '1') == '1' ? 'yes' : 'no';
        $viewportContent = "width=device-width, initial-scale=1";
        if ($mobileZoom === 'no') {
            $viewportContent .= ", user-scalable=no";
        }
    @endphp
    <meta name="viewport" content="{{ $viewportContent }}">
    <!-- SEO Meta Tags -->
    @include('falcon-cms::components.frontend.seo-meta')
    
    @php
        // Prepare Theme Options
        $primaryColor = get_cms_option('theme_primary_color', '#0091ea');
        $secondaryColor = get_cms_option('theme_secondary_color', '#1d2327');
        $bodyBgColor = get_cms_option('theme_body_bg_color', '#ffffff');
        $textColor = get_cms_option('theme_text_color', '#1d2327');
        $linkColor = get_cms_option('theme_link_color', '#0091ea');
        $linkHoverColor = get_cms_option('theme_link_hover_color', '#007ac1');
        // Button hover colour = a darker shade of the primary colour. Buttons use white text,
        // so hovering must keep a dark-enough background (the link-hover colour was unsuitable
        // for button backgrounds). Derived from the customizer primary colour, so it stays dynamic.
        $primaryHover = (function ($hex) {
            $hex = ltrim((string) $hex, '#');
            if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
            if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) { return '#0a74c4'; }
            $r = max(0, hexdec(substr($hex, 0, 2)) - 26);
            $g = max(0, hexdec(substr($hex, 2, 2)) - 26);
            $b = max(0, hexdec(substr($hex, 4, 2)) - 26);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        })($primaryColor);
        $headingColor = get_cms_option('theme_heading_color', '#1d2327');
        $siteWidth = get_cms_option('theme_site_width', '1240px');
        $favicon = get_cms_option('theme_site_favicon');
        
        // Typography Processing
        $bodyTypo = json_decode(get_cms_option('theme_typography_body'), true) ?: ['family' => 'Inter', 'variant' => '400', 'size' => '15px'];
        $h1Typo = json_decode(get_cms_option('theme_typography_h1'), true);
        $navTypo = json_decode(get_cms_option('theme_typography_nav'), true);
        
        // Collect fonts to load
        $fontsToLoad = [$bodyTypo['family'] ?? 'Inter'];
        if (isset($h1Typo['family'])) $fontsToLoad[] = $h1Typo['family'];
        if (isset($navTypo['family'])) $fontsToLoad[] = $navTypo['family'];
        
        // Add builder fonts if we are on a post/page with builder content
        if (isset($post) && !empty($post->content)) {
            $isBuilder = $post->editor_type === 'builder' || (is_string($post->content) && (str_starts_with($post->content, '[') || str_starts_with($post->content, '{')));
            if ($isBuilder) {
                $layout = is_string($post->content) ? json_decode($post->content, true) : $post->content;
                if (is_array($layout)) {
                    $builderFonts = get_lazy_builder_fonts($layout);
                    $fontsToLoad = array_merge($fontsToLoad, $builderFonts);
                }
            }
        }
        
        $fontsToLoad = array_unique(array_filter($fontsToLoad));
        $googleFontsUrl = "https://fonts.googleapis.com/css2?family=" . implode('&family=', array_map(fn($f) => str_replace(' ', '+', $f) . ':wght@300;400;500;600;700;800;900', $fontsToLoad)) . "&display=swap";

        // Layout Type
        $layoutType = get_cms_option('theme_layout_type', 'boxed');
    @endphp

    <!-- Favicon -->
    @if($favicon)
        <link rel="icon" type="image/x-icon" href="{{ $favicon }}">
    @endif

    <!-- Dynamic Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $googleFontsUrl }}" rel="stylesheet">
    
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/font-awesome.all.min.css') }}">

    <!-- Tailwind -->
    <script src="{{ asset('vendor/falcon-cms/js/tailwind.min.js') }}"></script>
    <!-- Alpine.js -->
    <script defer src="{{ asset('vendor/falcon-cms/js/alpine.min.js') }}"></script>
    <!-- SweetAlert2 -->
    <script src="{{ asset('vendor/falcon-cms/js/sweetalert2.all.min.js') }}"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '{{ $primaryColor }}',
                        'primary-hover': '{{ $primaryHover }}',
                        secondary: '{{ $secondaryColor }}',
                        heading: '{{ $headingColor }}',
                        body: '{{ $textColor }}',
                        link: '{{ $linkColor }}',
                        'link-hover': '{{ $linkHoverColor }}',
                    },
                    fontFamily: {
                        sans: ['{{ $bodyTypo["family"] }}', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        :root {
            --primary: {{ $primaryColor }};
            --primary-hover: {{ $primaryHover }};
            --text-main: {{ $textColor }};
            --text-heading: {{ $headingColor }};
            --text-muted: #666666;
            --bg-body: {{ $bodyBgColor }};
            --bg-alt: #f5f7f9;
            --border-color: #e8e8e8;
            --site-width: {{ is_numeric($siteWidth) ? $siteWidth . 'px' : $siteWidth }};
            
            /* Typography Variables */
            --body-font: '{{ $bodyTypo["family"] }}', sans-serif;
            --body-size: {{ $bodyTypo["size"] ?? '15px' }};
            --body-lh: {{ $bodyTypo["line_height"] ?? '1.6' }};
        }

        [x-cloak] { display: none !important; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--body-font);
            font-size: var(--body-size);
            line-height: var(--body-lh);
            
            @if(get_cms_option('theme_body_bg_image'))
                background-image: url('{{ get_cms_option("theme_body_bg_image") }}');
                background-position: {{ get_cms_option("theme_body_bg_position", "center center") }};
                background-size: {{ get_cms_option("theme_body_bg_size", "cover") }};
                background-repeat: {{ get_cms_option("theme_body_bg_repeat", "no-repeat") }};
                background-attachment: {{ get_cms_option("theme_body_bg_attachment", "scroll") }};
            @endif
        }

        /* Container & Padding */
        .container-custom, .page-container {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            @if($layoutType === 'boxed')
                max-width: var(--site-width) !important;
                padding-left: 20px;
                padding-right: 20px;
            @else
                max-width: 100% !important;
                padding-left: {{ get_cms_option('theme_100_width_padding', '30px') }};
                padding-right: {{ get_cms_option('theme_100_width_padding', '30px') }};
            @endif
        }

        /* Header/footer side padding — defaults to 0 to match builder sections */
        @php $headerSidePad = get_cms_option('theme_header_side_padding', '0px'); @endphp
        .main-header .container-custom,
        .main-footer .container-custom {
            padding-left: {{ $headerSidePad }} !important;
            padding-right: {{ $headerSidePad }} !important;
        }
        /* Always keep at least 16px breathing room on small screens */
        @media (max-width: 640px) {
            .main-header .container-custom,
            .main-footer .container-custom {
                padding-left: max(16px, {{ $headerSidePad }}) !important;
                padding-right: max(16px, {{ $headerSidePad }}) !important;
            }
        }

        main {
            padding-top: {{ get_cms_option('theme_page_padding_top', '60px') }};
            padding-bottom: {{ get_cms_option('theme_page_padding_bottom', '60px') }};
        }

        a { color: {{ $linkColor }}; transition: color 0.2s; }
        a:hover { color: {{ $linkHoverColor }}; }

        /* Typography Tags */
        @php
            // Responsive (fluid) typography — driven by the Customizer "Responsive Typography"
            // controls: Sensitivity (how aggressively headings shrink) + Minimum Font Size Factor
            // (the floor = body font-size * factor).
            $typoSensitivity   = (float) get_cms_option('theme_typography_sensitivity', '0.6');
            $minFontSizeFactor = (float) get_cms_option('theme_font_size_factor', '1.50');
            $rtSmallBP  = (int) get_cms_option('theme_small_screen_breakpoint', '800');
            $rtMediumBP = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
            $rtBodyTypo = json_decode(get_cms_option('theme_typography_body'), true);
            $rtBodyPx   = (float) preg_replace('/[^0-9.]/', '', (string)($rtBodyTypo['size'] ?? '15'));
            if ($rtBodyPx <= 0) $rtBodyPx = 15;

            // Turn a fixed desktop px size into a fluid clamp(). Returns the raw size
            // unchanged when RT is disabled, the unit isn't px, the breakpoints are
            // misconfigured, or the heading is already at/below the floor — so the
            // desktop appearance (>= medium breakpoint) never changes.
            $fluidFontSize = function ($sizeStr) use ($typoSensitivity, $minFontSizeFactor, $rtSmallBP, $rtMediumBP, $rtBodyPx) {
                $sizeStr = trim((string) $sizeStr);
                if ($typoSensitivity <= 0 || $rtMediumBP <= $rtSmallBP) return $sizeStr;
                if (!preg_match('/^([0-9.]+)px$/', $sizeStr, $m)) return $sizeStr;
                $max   = (float) $m[1];
                $floor = $rtBodyPx * $minFontSizeFactor;
                if ($max <= $floor) return $sizeStr;
                $sens  = min(1, max(0, $typoSensitivity));
                $min   = round($max - ($max - $floor) * $sens, 2);
                $delta = round($max - $min, 2);
                $span  = $rtMediumBP - $rtSmallBP;
                return "clamp({$min}px, calc({$min}px + {$delta} * (100vw - {$rtSmallBP}px) / {$span}), {$max}px)";
            };

            $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'nav'];
            $headingTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
            foreach($tags as $tag) {
                $optionName = "theme_typography_{$tag}";
                $typo = json_decode(get_cms_option($optionName), true);
                if($typo) {
                    echo "{$tag}, .{$tag}-style { ";
                    if(isset($typo['family'])) echo "font-family: '{$typo['family']}', sans-serif; ";
                    if(isset($typo['size'])) {
                        $sizeOut = in_array($tag, $headingTags, true) ? $fluidFontSize($typo['size']) : $typo['size'];
                        echo "font-size: {$sizeOut}; ";
                    }
                    if(isset($typo['variant'])) echo "font-weight: {$typo['variant']}; ";
                    if(isset($typo['line_height'])) echo "line-height: {$typo['line_height']}; ";
                    if(isset($typo['letter_spacing'])) echo "letter-spacing: {$typo['letter_spacing']}; ";
                    if(isset($typo['text_transform'])) echo "text-transform: {$typo['text_transform']}; ";
                    if(isset($typo['text_decoration'])) echo "text-decoration: {$typo['text_decoration']}; ";
                    if(isset($typo['font_style'])) echo "font-style: {$typo['font_style']}; ";
                    echo "color: var(--text-heading); }\n";
                }
            }
        @endphp

        /* Astra-style Header Customization */
        .main-header {
            background: {{ get_cms_option('theme_header_bg_color', '#ffffff') }};
            color: {{ get_cms_option('theme_header_text_color', '#1d2327') }};
            height: {{ get_cms_option('theme_header_height', '80px') }};
            padding-top: {{ get_cms_option('theme_header_padding_top', '0px') }};
            padding-bottom: {{ get_cms_option('theme_header_padding_bottom', '0px') }};
            @if(get_cms_option('theme_header_border_bottom', '1') == '1')
                border-bottom: 1px solid {{ get_cms_option('theme_header_border_color', '#e8e8e8') }};
            @else
                border-bottom: none;
            @endif
        }

        @if(get_cms_option('theme_header_sticky', '0') == '1')
        .main-header {
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        @endif

        /* RESPONSIVE SETTINGS */
        @php
            $responsiveEnabled = get_cms_option('theme_responsive_design', '1') == '1';
            $gridBP = get_cms_option('theme_grid_breakpoint', '1000');
            $headerBP = get_cms_option('theme_header_breakpoint', '800');
            $contentBP = get_cms_option('theme_content_breakpoint', '800');
            $sidebarBP = get_cms_option('theme_sidebar_breakpoint', '800');
            $smallBP = get_cms_option('theme_small_screen_breakpoint', '800');
            $mediumBP = get_cms_option('theme_medium_screen_breakpoint', '1100');
        @endphp

        :root {
            --grid-bp: {{ $gridBP }}px;
            --header-bp: {{ $headerBP }}px;
            --content-bp: {{ $contentBP }}px;
            --sidebar-bp: {{ $sidebarBP }}px;
            --small-bp: {{ $smallBP }}px;
            --medium-bp: {{ $mediumBP }}px;
        }

        @if(!$responsiveEnabled)
            body { min-width: var(--site-width); overflow-x: auto; }
            .container-custom, .page-container { width: var(--site-width) !important; max-width: none !important; }
        @endif

        @if($responsiveEnabled)
        /* Header: desktop nav ↔ mobile hamburger switch at $headerBP */
        @media (min-width: {{ (int)$headerBP + 1 }}px) {
            .lb-desktop-nav { display: flex !important; }
            .lb-mobile-btn, .lb-mobile-menu { display: none !important; }
        }
        @media (max-width: {{ $headerBP }}px) {
            .lb-desktop-nav { display: none !important; }
            .lb-mobile-btn  { display: flex !important; }
        }
        /* Content + sidebar: stack vertically below $contentBP */
        @media (min-width: {{ (int)$contentBP + 1 }}px) {
            .lb-with-sidebar         { flex-direction: row !important; }
            .lb-sidebar-widget       { flex-shrink: 0 !important; }
            .lb-sidebar-order-first  { order: 1 !important; }
            .lb-main-order-second    { order: 2 !important; }
        }
        @media (max-width: {{ $contentBP }}px) {
            .lb-with-sidebar { flex-direction: column !important; }
        }
        /* Blog grid: collapse columns at $gridBP */
        @media (max-width: 640px) {
            .lb-grid-2,.lb-grid-3,.lb-grid-4 { grid-template-columns: 1fr !important; }
        }
        @media (min-width: 641px) and (max-width: {{ $gridBP }}px) {
            .lb-grid-3,.lb-grid-4 { grid-template-columns: repeat(2,minmax(0,1fr)) !important; }
        }
        @media (min-width: {{ (int)$gridBP + 1 }}px) {
            .lb-grid-3 { grid-template-columns: repeat(3,minmax(0,1fr)) !important; }
            .lb-grid-4 { grid-template-columns: repeat(4,minmax(0,1fr)) !important; }
        }
        @endif

        /* Widget title used by all sidebar/footer widgets */
        .widget-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-heading);
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary);
            display: inline-block;
        }

        /* Responsive Typography: headings (h1–h6) are emitted as fluid clamp() sizes
           above, controlled by the Sensitivity + Minimum Font Size Factor settings. */

        /* PRIORITY CUSTOM CSS */
        {!! get_cms_option('theme_custom_css') !!}
    </style>
    
    @yield('styles')
    {!! do_falcon_action('falcon_head') !!}

    {{-- PRIORITY HEAD SCRIPT --}}
    @if(get_cms_option('theme_head_script'))
        <script>{!! get_cms_option('theme_head_script') !!}</script>
    @endif
</head>
@php
    $bodyClasses = "antialiased selection:bg-primary selection:text-white";
@endphp
<body class="{{ $bodyClasses }}">

@if($customHeader = get_falcon_header())
    {!! $customHeader !!}
@else
    @include('falcon-cms::themes.falcon-theme.partials.header')
@endif

@include('falcon-cms::themes.falcon-theme.partials.title-bar')

<main class="flex-grow">
    @yield('content')
</main>

@if($customFooter = get_falcon_footer())
    {!! $customFooter !!}
@else
    @include('falcon-cms::themes.falcon-theme.partials.footer')
@endif

    {{-- Off-canvas mini cart --}}
    @include('falcon-cms::themes.falcon-theme.partials.mini-cart')

    <!-- Scripts -->
    <script src="{{ asset('vendor/falcon-cms/js/lucide.min.js') }}"></script>
    <script>
        lucide.createIcons();
    </script>
    @stack('scripts')
    {!! do_falcon_action('falcon_footer') !!}

    {{-- PRIORITY FOOTER SCRIPT --}}
    @if(get_cms_option('theme_footer_script'))
        <script>{!! get_cms_option('theme_footer_script') !!}</script>
    @endif
</body>
</html>
