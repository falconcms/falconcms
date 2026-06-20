@php
    // Use footer-specific colors; fall back to general Customizer > Colors values when empty.
    $footerBg     = get_cms_option('theme_footer_bg_color')     ?: get_cms_option('theme_body_bg_color',  '#1d2327');
    $footerText   = get_cms_option('theme_footer_text_color')   ?: get_cms_option('theme_text_color',     '#c3c4c7');
    $footerLink   = get_cms_option('theme_footer_link_color')   ?: get_cms_option('theme_link_color',     '#72aee6');
    $footerBorder = get_cms_option('theme_footer_border_color') ?: get_cms_option('theme_heading_color',  '#3c434a');
    $footerPaddingTop    = get_cms_option('theme_footer_padding_top',    '') ?: '40px';
    $footerPaddingBottom = get_cms_option('theme_footer_padding_bottom', '') ?: '40px';

    // Cast to int; use ?: to guard against empty-string corruption from a previous Customizer save bug.
    $footerColumns = (int)(get_cms_option('theme_footer_columns') ?: 4);
    $footerColumns = max(1, min(4, $footerColumns)); // clamp 1–4

    $gridClass = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 md:grid-cols-2',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
    ][$footerColumns] ?? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4';
@endphp

<footer class="main-footer" style="background-color: {{ $footerBg }}; color: {{ $footerText }}; @if((get_cms_option('theme_footer_border_top') ?: '1') == '1') border-top: 1px solid {{ $footerBorder }}; @endif padding-top: {{ $footerPaddingTop }}; padding-bottom: {{ $footerPaddingBottom }};">
    <div class="container-custom">
        <div class="grid {{ $gridClass }} gap-12 mb-16">
            @for($i = 1; $i <= $footerColumns; $i++)
                <div class="col-span-1 footer-column">
                    @php $widgetContent = render_lazy_widgets("footer-{$i}"); @endphp
                    @if($widgetContent)
                        {!! $widgetContent !!}
                    @else
                        @if($i == 1)
                            <a href="{{ url('/') }}" class="flex items-center gap-2 mb-6">
                                <img src="{{ get_cms_option('theme_footer_logo', get_cms_option('theme_site_logo', asset('vendor/falcon-cms/images/falcon-cms-footer-logo.png'))) }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}" class="h-12 w-auto">
                            </a>
                            <p class="text-[14px] leading-relaxed mb-8 opacity-80">
                                {{ get_cms_option('footer_about', 'A clean, fast, and professional CMS. Built for readability and seamless content delivery.') }}
                            </p>

                            {{-- Social Media (hardcoded fallback for column 1) --}}
                            <div class="flex items-center gap-3">
                                @php
                                    $socials = [
                                        ['key' => 'theme_social_facebook',  'icon' => 'fab fa-facebook-f',  'color' => '#1877F2'],
                                        ['key' => 'theme_social_twitter',   'icon' => 'fab fa-x-twitter',   'color' => '#000000'],
                                        ['key' => 'theme_social_instagram', 'icon' => 'fab fa-instagram',   'color' => '#E4405F'],
                                        ['key' => 'theme_social_linkedin',  'icon' => 'fab fa-linkedin-in', 'color' => '#0077B5'],
                                        ['key' => 'theme_social_youtube',   'icon' => 'fab fa-youtube',     'color' => '#FF0000'],
                                        ['key' => 'theme_social_github',    'icon' => 'fab fa-github',      'color' => '#333333'],
                                        ['key' => 'theme_social_tiktok',    'icon' => 'fab fa-tiktok',      'color' => '#010101'],
                                        ['key' => 'theme_social_whatsapp',  'icon' => 'fab fa-whatsapp',    'color' => '#25D366'],
                                    ];
                                @endphp
                                @foreach($socials as $social)
                                    @if($link = get_cms_option($social['key']))
                                        <a href="{{ $link }}" target="_blank" class="w-9 h-9 rounded-lg flex items-center justify-center text-white transition-all hover:scale-110 shadow-sm" style="background-color: {{ $social['color'] }};">
                                            <i class="{{ $social['icon'] }} text-sm"></i>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @elseif($i == 2)
                            <h4 class="font-bold mb-6" style="color: inherit;">Quick Links</h4>
                            <nav class="flex flex-col gap-3">
                                @php $footerMenu = get_lazy_menu('footer'); @endphp
                                @forelse($footerMenu as $item)
                                    <a href="{{ $item->url }}" class="text-[14px] opacity-70 hover:opacity-100 transition-colors" style="color: {{ $footerLink }};">{{ $item->title }}</a>
                                @empty
                                    <a href="{{ url('/') }}" class="text-[14px] opacity-70 hover:opacity-100 transition-colors" style="color: {{ $footerLink }};">Home</a>
                                @endforelse
                            </nav>
                        @endif
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <div style="border-top: 1px solid {{ $footerBorder }};"></div>

    <div class="container-custom">
        <div class="pt-6 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-[13px] opacity-60">
                {!! get_cms_option('theme_footer_copyright', '© ' . date('Y') . ' All rights reserved by Falcon CMS') !!}
            </div>
        </div>
    </div>
</footer>

<style>
    /* Headings in footer widget columns */
    .footer-column h1, .footer-column h2, .footer-column h3,
    .footer-column h4, .footer-column h5, .footer-column h6 {
        color: {{ $footerText }} !important;
        opacity: 0.9;
    }
    /* All regular links in footer widget columns use the footer link color.
       Excludes icon buttons that carry an inline background-color (social media circles). */
    .footer-column a:not([style*="background-color"]) {
        color: {{ $footerLink }} !important;
    }
    /* Force nested spans/li to inherit from the parent <a> so Tailwind classes
       like text-slate-600 don't override the footer link color. */
    .footer-column a:not([style*="background-color"]) span,
    .footer-column a:not([style*="background-color"]) li {
        color: inherit !important;
    }
    /* Widget title uses footer text color */
    .footer-column .widget-title {
        color: {{ $footerText }} !important;
        border-bottom-color: {{ $footerLink }} !important;
    }
</style>
