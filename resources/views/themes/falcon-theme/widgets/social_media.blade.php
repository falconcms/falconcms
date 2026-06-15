@php
    $iconStyle  = get_cms_option('theme_social_icon_style', '') ?: 'brand';
    $iconColor  = get_cms_option('theme_social_icon_color',       '') ?: '#ffffff';
    $iconBg     = get_cms_option('theme_social_icon_bg',          '') ?: '#6366f1';
    $iconHColor = get_cms_option('theme_social_icon_hover_color', '') ?: '#ffffff';
    $iconHBg    = get_cms_option('theme_social_icon_hover_bg',    '') ?: '#4338ca';
    $uid        = 'sw' . $widget->id;

    $socials = [
        'facebook'  => ['label' => 'Facebook',    'icon' => 'fab fa-facebook-f',  'color' => '#1877F2'],
        'twitter'   => ['label' => 'X (Twitter)', 'icon' => 'fab fa-x-twitter',   'color' => '#000000'],
        'instagram' => ['label' => 'Instagram',   'icon' => 'fab fa-instagram',   'color' => '#E4405F'],
        'linkedin'  => ['label' => 'LinkedIn',    'icon' => 'fab fa-linkedin-in', 'color' => '#0077B5'],
        'youtube'   => ['label' => 'YouTube',     'icon' => 'fab fa-youtube',     'color' => '#FF0000'],
        'github'    => ['label' => 'GitHub',      'icon' => 'fab fa-github',      'color' => '#333333'],
        'tiktok'    => ['label' => 'TikTok',      'icon' => 'fab fa-tiktok',      'color' => '#010101'],
        'whatsapp'  => ['label' => 'WhatsApp',    'icon' => 'fab fa-whatsapp',    'color' => '#25D366'],
    ];
@endphp

<div class="widget mb-10" id="{{ $uid }}">
    @if(!empty($widget->title))
        <h4 class="widget-title">{{ $widget->title }}</h4>
    @endif

    <div class="flex flex-wrap gap-3">
        @foreach($socials as $key => $meta)
            @php
                $url = !empty($widget->settings[$key]) ? $widget->settings[$key] : get_cms_option('theme_social_' . $key);
                $bg  = $iconStyle === 'brand' ? $meta['color'] : $iconBg;
                $fg  = $iconStyle === 'brand' ? '#ffffff'       : $iconColor;
            @endphp
            @if(!empty($url))
            <a href="{{ $url }}" target="_blank" rel="noopener"
               class="s-icon w-10 h-10 rounded-lg flex items-center justify-center shadow-sm"
               style="background-color:{{ $bg }};color:{{ $fg }};"
               aria-label="{{ $meta['label'] }}"
               data-tip="{{ $meta['label'] }}">
                <i class="{{ $meta['icon'] }} text-base" style="color:inherit;"></i>
            </a>
            @endif
        @endforeach
    </div>
</div>

<style>
#{{ $uid }} .s-icon {
    position: relative;
    transition: background-color .2s ease, color .2s ease,
                box-shadow .2s ease, filter .2s ease;
}
/* Tooltip bubble */
#{{ $uid }} .s-icon::after {
    content: attr(data-tip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15,15,15,.85);
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
    padding: 3px 9px;
    border-radius: 5px;
    pointer-events: none;
    opacity: 0;
    transition: opacity .16s ease;
    font-family: system-ui, -apple-system, sans-serif;
    letter-spacing: .02em;
    line-height: 1.6;
    z-index: 10;
}
/* Tooltip caret */
#{{ $uid }} .s-icon::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 4px);
    left: 50%;
    transform: translateX(-50%);
    border: 4px solid transparent;
    border-top-color: rgba(15,15,15,.85);
    pointer-events: none;
    opacity: 0;
    transition: opacity .16s ease;
    z-index: 10;
}
#{{ $uid }} .s-icon:hover::after,
#{{ $uid }} .s-icon:hover::before {
    opacity: 1;
}
@if($iconStyle === 'custom')
#{{ $uid }} .s-icon:hover {
    background-color: {{ $iconHBg }} !important;
    color: {{ $iconHColor }} !important;
    box-shadow: 0 4px 12px rgba(0,0,0,.16);
}
@else
#{{ $uid }} .s-icon:hover {
    filter: brightness(1.18) saturate(1.1);
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
}
@endif
</style>
