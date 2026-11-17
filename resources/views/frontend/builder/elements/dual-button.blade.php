@php
    /*
    |--------------------------------------------------------------------------
    | Dual Button — frontend renderer
    |--------------------------------------------------------------------------
    | Two calls to action drawn as one pair. The settings, their defaults, the
    | stylesheet and the mapping from a setting to a CSS custom property all live
    | in FalconCms\Core\Support\DualButton; this view only places the values. The
    | stylesheet itself is printed once per layout by frontend/builder/render.
    |
    | Canvas twin:
    |   resources/views/admin/falcon-builder/partials/components/elements/dual-button.blade.php
    */
    use FalconCms\Core\Support\DualButton;
    use FalconCms\Core\Support\Typography;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $buttons   = DualButton::buttons($s, $post ?? null);
    $connector = DualButton::connector($s, count($buttons));

    $uid = 'fdb-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));

    // Desktop values sit on the wrapper's style attribute; a tablet or mobile value is a
    // media query. An !important custom property in a stylesheet outranks the inline one,
    // which is what lets a media query override a value written on the element.
    $style = 'width:100%;';
    foreach (DualButton::MARGIN as $side => $default) {
        // Blank is the default (the field's placeholder says so); no margin is a typed 0.
        $margin = $s['margin'.$side] ?? '';
        $margin = DualButton::cssValue(getUnitVal(
            $margin === '' ? $default : $margin,
            DualButton::unit($s['margin'.$side.'Unit'] ?? 'px')
        ));
        $style .= 'margin-'.strtolower($side).':'.$margin.';';
    }
    foreach (DualButton::vars($s) as $prop => $value) {
        $style .= $prop.':'.$value.';';
    }
    $style .= Typography::css($s, 'db');

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    $respProps = [];
    foreach (DualButton::MARGIN as $side => $default) {
        $respProps[] = ['prop' => 'margin'.$side, 'unitProp' => 'margin'.$side.'Unit', 'sel' => '.'.$uid];
    }
    foreach (DualButton::PADDING as $side => [$prop]) {
        $respProps[] = ['prop' => 'padding'.$side, 'unitProp' => 'padding'.$side.'Unit', 'sel' => '.'.$uid, 'css' => $prop];
    }
    // Border width, corners, icon gap. These have no unit key: a bare number is px.
    foreach (DualButton::RESPONSIVE as $key) {
        $respProps[] = ['prop' => $key, 'unitProp' => $key.'Unit', 'sel' => '.'.$uid, 'css' => DualButton::VARS[$key][0]];
    }

    // falcon_elem_resp_css() only strips quotes and angle brackets; a value typed into a
    // size box could still end the rule with ; or }. Each one is cleaned the way the
    // desktop values are before it gets there.
    $respSettings = $s;
    foreach ($respProps as $rp) {
        foreach (['_tablet', '_mobile'] as $dev) {
            if (isset($respSettings[$rp['prop'].$dev])) {
                $respSettings[$rp['prop'].$dev] = DualButton::cssValue($respSettings[$rp['prop'].$dev]);
            }
            if (isset($respSettings[$rp['unitProp'].$dev])) {
                $respSettings[$rp['unitProp'].$dev] = DualButton::unit($respSettings[$rp['unitProp'].$dev]);
            }
        }
    }
    $respCss = falcon_elem_resp_css($respSettings, $bpSm, $bpMed, $respProps);

    foreach ([
        'tablet' => "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)",
        'mobile' => "@media(max-width:{$bpSm}px)",
    ] as $dev => $mq) {
        $align = $s['align_'.$dev] ?? '';
        // Mobile follows tablet when it has no value of its own, as everywhere else.
        if ($align === '' && $dev === 'mobile') {
            $align = $s['align_tablet'] ?? '';
        }
        if (isset(DualButton::ALIGNMENTS[$align])) {
            $respCss .= "{$mq}{.{$uid}{--fdb-justify:".DualButton::ALIGNMENTS[$align].'!important}}';
        }
    }
@endphp

{{-- Nothing at all when both buttons were cleared: an empty pair would be an invisible
     box that still takes its margins. --}}
@if($buttons)
@if($respCss)<style>{!! $respCss !!}</style>@endif
<div class="{{ implode(' ', DualButton::classes($s)) }} {{ $uid }} {{ $s['cssClass'] ?? '' }}{{ $visibilityClasses }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="{{ $style }}">
    <div class="fdb-group"@if(count($buttons) > 1) role="group"@endif>
        @foreach($buttons as $i => $button)
            @php
                $tag = $button['url'] !== '' ? 'a' : 'span';
                $cell = 'fdb-cell' . ($loop->first ? ' fdb-cell--first' : '') . ($loop->last ? ' fdb-cell--last' : '');
            @endphp
            <span class="{{ $cell }}">
                <{{ $tag }} class="fdb-btn fdb-btn--{{ $button['side'] }}"
                    @if($tag === 'a') href="{{ $button['url'] }}" @if($button['target'] === '_blank') target="_blank" rel="noopener" @endif @endif>
                    @if($button['icon'] && $button['iconPosition'] === 'left')<i class="{{ $button['icon'] }}" aria-hidden="true"></i>@endif
                    @if($button['text'] !== '')<span class="fdb-label">{{ $button['text'] }}</span>@endif
                    @if($button['icon'] && $button['iconPosition'] === 'right')<i class="{{ $button['icon'] }}" aria-hidden="true"></i>@endif
                </{{ $tag }}>
                @if($loop->first && $connector !== '')<span class="fdb-or" aria-hidden="true">{{ $connector }}</span>@endif
            </span>
        @endforeach
    </div>
</div>
@endif
