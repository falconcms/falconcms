{{--
    A Motion Visual (Column / Container → Extra → Motion Visual): a layer across the whole
    column or container, behind its content or in front of it.

    Given $mv (MotionVisuals::resolve — every value already checked), $where, $editor, $uid.

    The shapes animate with CSS alone, so they move without the script and cost the page
    almost nothing; the script draws the dots and moves layers with the pointer. Reduced
    motion stops both. Every item carries data-mv-item, which is how the builder's handles
    find what they drag.
--}}
@php
    $bpSm = (int) get_cms_option('theme_small_screen_breakpoint', '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $hideCss = '';
    if ($mv['mv_mobile'] === 'hide') {
        $hideCss .= '@media (max-width:'.$bpSm.'px){#'.$uid.'{display:none!important}}';
    }
    if ($mv['mv_tablet'] === 'hide') {
        $hideCss .= '@media (min-width:'.($bpSm + 1).'px) and (max-width:'.$bpMed.'px){#'.$uid.'{display:none!important}}';
    }
    $speed = (float) $mv['mv_speed'];
    $shapes = \FalconCms\Core\Support\MotionVisual\Shapes::all();
@endphp
@if($hideCss && !$editor){!! '<style>'.$hideCss.'</style>' !!}@endif
@include('falcon-cms::frontend.motion-visual.base-styles')
<div id="{{ $uid }}" class="falcon-mv falcon-mv--{{ $mv['mv_layer'] }} falcon-mv--{{ $where }}{{ $editor ? ' falcon-mv--editor' : '' }}"
     data-falcon-mv="{{ $mv['mv_preset'] }}"
     data-mv-mouse="{{ $mv['mv_mouse'] && !$editor ? '1' : '0' }}"
     data-mv-speed="{{ $speed }}"
     data-mv-tablet="{{ $mv['mv_tablet'] }}" data-mv-mobile="{{ $mv['mv_mobile'] }}"
     data-mv-bp-sm="{{ $bpSm }}" data-mv-bp-med="{{ $bpMed }}"
     @if($mv['mv_dots'])
     data-mv-dots="1" data-mv-dots-count="{{ $mv['mv_dots_count'] }}" data-mv-dots-size="{{ $mv['mv_dots_size'] }}"
     data-mv-dots-color="{{ $mv['mv_dots_color'] }}" data-mv-dots-speed="{{ $mv['mv_dots_speed'] }}"
     data-mv-dots-links="{{ $mv['mv_dots_links'] ? '1' : '0' }}" data-mv-dots-link-dist="{{ $mv['mv_dots_link_dist'] }}"
     data-mv-dots-mouse="{{ $editor ? 'none' : $mv['mv_dots_mouse'] }}"
     @endif
     style="@if($mv['mv_opacity'] < 100)opacity:{{ $mv['mv_opacity'] / 100 }};@endif"
     aria-hidden="true">
    @if($mv['mv_dots'])<canvas class="falcon-mv__dots"></canvas>@endif
    @foreach($mv['items'] as $item)
        @php $isText = $item['kind'] === 'text'; @endphp
        <div class="falcon-mv__item{{ $isText ? ' falcon-mv__item--text' : '' }}" data-mv-item="{{ $item['id'] }}" data-mv-depth="{{ $item['depth'] }}"
             style="left:{{ $item['x'] }}%;top:{{ $item['y'] }}%;{{ $isText ? 'font-size:'.$item['size'].'cqw' : 'width:'.$item['size'].'%' }}">
            <div class="falcon-mv__anim falcon-mv__anim--{{ $item['anim'] }}"
                 style="--mv-dur:{{ round($item['speed'] / $speed, 3) }}s;--mv-dist:{{ $item['dist'] }}px;--mv-d:{{ $item['dist'] }};animation-delay:-{{ $item['delay'] }}s">
                <div class="falcon-mv__shape{{ $item['random'] ? ' falcon-mv__shape--random' : '' }}" style="rotate:{{ $item['rot'] }}deg;color:{{ $item['color'] }};opacity:{{ $item['opacity'] / 100 }};@if($item['blur'] > 0)filter:blur({{ $item['blur'] }}px);@endif @if($item['random'])animation-duration:{{ $item['rdur'] }}s;animation-delay:-{{ $item['rdelay'] }}s;animation-direction:{{ $item['rreverse'] ? 'reverse' : 'normal' }};@endif">
                    @if($isText)
                        <span class="falcon-mv__text falcon-mv__text--{{ $item['style'] }}"
                              style="font-family:{{ \FalconCms\Core\Support\MotionVisual\MotionVisuals::FONTS[$item['font']] }};font-weight:{{ $item['weight'] }};letter-spacing:{{ $item['spacing'] }}em;@if($item['upper'])text-transform:uppercase;@endif--mv-c1:{{ $item['color'] }};--mv-c2:{{ $item['color2'] }}">{{ $item['text'] }}</span>
                    @elseif($item['kind'] === 'image')
                        @if($item['src'] !== '')<img src="{{ $item['src'] }}" alt="" loading="lazy" decoding="async" draggable="false">@endif
                    @else
                        <svg viewBox="0 0 100 100" fill="currentColor" preserveAspectRatio="xMidYMid meet">{!! $shapes[$item['shape']]['svg'] ?? '' !!}</svg>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
