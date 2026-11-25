@php
    /*
    |--------------------------------------------------------------------------
    | Countdown — renderer (page and builder canvas)
    |--------------------------------------------------------------------------
    | Settings, deadlines and the designs' stylesheet live in
    | FalconCms\Core\Support\Countdown. The numbers are worked out here so the page reads
    | right before any script runs; components/frontend/countdown-runtime ticks it.
    |
    | The builder canvas fetches this same template ('preview' => 'server'), with
    | $builderPreview set and $previewDevice the device being previewed. In the canvas the
    | countdown ticks (the builder loads the runtime) but never hides itself or leaves the
    | page at zero.
    |
    | Visibility, CSS class and ID are put on the wrapper around this by
    | frontend/builder/column (custom elements share that wrapper).
    */
    use FalconCms\Core\Support\Countdown;
    use FalconCms\Core\Support\ElementStyle;

    $s = $s ?? ($el['settings'] ?? []);
    $s = is_array($s) ? $s : [];
    $isPreview = !empty($builderPreview);
    $device = $isPreview ? ($previewDevice ?? null) : null;

    $cfg = Countdown::config($s);
    $state = Countdown::state($cfg, (int) floor(microtime(true) * 1000));
    $uid = 'fcd-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    [$bpSm, $bpMed] = ElementStyle::breakpoints();

    // A fixed deadline that has already passed is settled here, on the server: the page
    // can be hidden or show its message before any script runs, and stays so in the cache.
    $expiredNow = $state['expired'] && !$isPreview;
    $hidden = $expiredNow && $cfg['expire'] === 'hide';
    $showMessage = $expiredNow && $cfg['expire'] === 'message';

    $design = $cfg['design'];
    $separator = ElementStyle::pick($s['separator'] ?? 'auto', ['auto', 'colon', 'none'], 'auto');
    $showSep = $separator === 'colon' || ($separator === 'auto' && in_array($design, ['flip', 'minimal'], true));

    $size = ElementStyle::int($s['item_size'] ?? null, 96, 40, 240);
    $sizeMobile = ElementStyle::int($s['item_size_mobile'] ?? null, 68, 32, 200);
    if ($device === 'mobile') {
        $size = $sizeMobile;
    }
    $ringW = ElementStyle::int($s['ring_width'] ?? null, 6, 1, 20);

    $align = (string) ElementStyle::forDevice($s, 'align', $device, 'center');
    $align = in_array($align, ['flex-start', 'center', 'flex-end'], true) ? $align : 'center';

    $vars = '--fcd-size:'.$size.'px;'
        .'--fcd-gap:'.ElementStyle::int($s['gap'] ?? null, 14, 0, 60).'px;'
        .'--fcd-radius:'.ElementStyle::int($s['radius'] ?? null, 12, 0, 60).'px;'
        .'--fcd-ring-w:'.$ringW.'px;'
        .'--fcd-justify:'.$align.';';
    foreach ([
        'num_color' => '--fcd-num', 'label_color' => '--fcd-label', 'box_bg' => '--fcd-bg',
        'box_border' => '--fcd-border', 'ring_color' => '--fcd-ring', 'ring_track' => '--fcd-track',
        'sep_color' => '--fcd-sep', 'msg_color' => '--fcd-msg',
    ] as $key => $prop) {
        $value = ElementStyle::color($s[$key] ?? '');
        if ($value !== '') {
            $vars .= $prop.':'.$value.';';
        }
    }

    $css = Countdown::css();
    $css .= "#{$uid}{{$vars}" . ElementStyle::margin($s) . '}';
    if ($device === 'tablet' || $device === 'mobile') {
        $css .= "#{$uid}{" . ElementStyle::margin($s, '_tablet') . ($device === 'mobile' ? ElementStyle::margin($s, '_mobile') : '') . '}';
    }
    $numTypo = ElementStyle::typo($s, 'num_typo');
    $labelTypo = ElementStyle::typo($s, 'label_typo');
    $msgTypo = ElementStyle::typo($s, 'msg_typo');
    if ($numTypo !== '') $css .= "#{$uid} .fcd-num,#{$uid} .fcd-sep{{$numTypo}}";
    if ($labelTypo !== '') $css .= "#{$uid} .fcd-label{{$labelTypo}}";
    if ($msgTypo !== '') $css .= "#{$uid} .fcd-expired{{$msgTypo}}";

    if (!$isPreview) {
        $bpSm1 = $bpSm + 1;
        $tabletAlign = ElementStyle::forDevice($s, 'align', 'tablet', $align);
        $mobileAlign = ElementStyle::forDevice($s, 'align', 'mobile', $tabletAlign);
        $css .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$uid}{" . ElementStyle::margin($s, '_tablet')
            . (in_array($tabletAlign, ['flex-start', 'center', 'flex-end'], true) ? "--fcd-justify:{$tabletAlign};" : '') . '}}';
        $css .= "@media(max-width:{$bpSm}px){#{$uid}{--fcd-size:{$sizeMobile}px;" . ElementStyle::margin($s, '_tablet') . ElementStyle::margin($s, '_mobile')
            . (in_array($mobileAlign, ['flex-start', 'center', 'flex-end'], true) ? "--fcd-justify:{$mobileAlign};" : '') . '}}';
    }

    // Everything the runtime needs, and nothing it would have to guess.
    $data = [
        'id' => $uid,
        'mode' => $cfg['mode'],
        'end' => $cfg['endMs'],
        'ever' => $cfg['everSeconds'],
        'restart' => $cfg['everRestart'],
        'time' => $cfg['dailyTime'],
        'days' => $cfg['dailyDays'],
        'tz' => $cfg['tz'],
        'units' => $cfg['units'],
        'zero' => $cfg['zero'],
        'expire' => $cfg['expire'],
        'url' => $cfg['url'],
        'design' => $design,
        'preview' => $isPreview,
    ];

    $topUnit = $cfg['units'][0] ?? null;
    $limits = ['hours' => 24, 'minutes' => 60, 'seconds' => 60];
    $labelFor = function (string $unit) use ($cfg): string {
        $label = $cfg['labels'][$unit] ?? '';
        if ($cfg['labelMode'] === 'short') {
            return mb_strtolower(mb_substr($label !== '' ? $label : $unit, 0, 1));
        }
        return $label;
    };
@endphp
@unless($hidden)
{!! '<style>' . $css . '</style>' !!}
<div id="{{ $uid }}"
     class="fcd fcd--{{ $design }} fcd--labels-{{ $cfg['labelMode'] }}"
     data-fcd="{{ json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
    <div class="fcd-units" role="timer" aria-atomic="true" @if($showMessage) hidden @endif>
        @foreach($cfg['units'] as $i => $unit)
            @if($i > 0 && $showSep)<span class="fcd-sep" aria-hidden="true">:</span>@endif
            @php
                $value = $state['values'][$unit] ?? 0;
                $pct = $unit === $topUnit ? ($value > 0 ? 100 : 0) : min(100, ($value / ($limits[$unit] ?? 60)) * 100);
            @endphp
            <div class="fcd-item" data-unit="{{ $unit }}">
                @if($design === 'circles')
                <svg class="fcd-ring" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
                    <circle class="fcd-track" cx="50" cy="50" r="46"></circle>
                    <circle class="fcd-bar" cx="50" cy="50" r="46" pathLength="100" stroke-dasharray="100" stroke-dashoffset="{{ round(100 - $pct, 2) }}"></circle>
                </svg>
                @endif
                <span class="fcd-num">{{ Countdown::pad($value, $cfg['zero']) }}</span>
                @if($cfg['labelMode'] !== 'hide' && $labelFor($unit) !== '')
                <span class="fcd-label">{{ $labelFor($unit) }}</span>
                @endif
            </div>
        @endforeach
    </div>
    @if($cfg['expire'] === 'message')
    <div class="fcd-expired" @unless($showMessage) hidden @endunless>{{ $cfg['message'] }}</div>
    @endif
    @if($isPreview && ($cfg['mode'] === 'fixed' && $cfg['endMs'] === null))
    <div style="margin-top:10px;text-align:center;color:#94a3b8;font-size:12px;">Pick the end date in General → Ends at.</div>
    @elseif($isPreview && $state['expired'])
    <div style="margin-top:10px;text-align:center;color:#94a3b8;font-size:12px;">This date has passed. On the page: {{ ['message' => 'the message shows', 'hide' => 'the countdown is hidden', 'redirect' => 'visitors are sent to the link', 'zero' => 'it stays at zero'][$cfg['expire']] }}.</div>
    @endif
</div>
@if(!$isPreview)
@include('falcon-cms::components.frontend.countdown-runtime')
@endif
@endunless
