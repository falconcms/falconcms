@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $url      = $s['url'] ?? $s['videoUrl'] ?? '';
    $autoplay = !empty($s['autoplay']) ? 1 : 0;
    $muted    = !empty($s['muted'])    ? 1 : 0;
    $loop     = !empty($s['loop'])     ? 1 : 0;
    $controls = isset($s['controls'])  ? (int)(bool)$s['controls'] : 1;
    $aspect   = $s['aspectRatio']      ?? '16-9';

    $ytId    = null;
    $vimeoId = null;

    if ($url) {
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $ytId = $m[1];
        } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            $vimeoId = $m[1];
        }
    }

    // Build embed URL (for autoplay-on or iframe-direct cases)
    $embedUrl = '';
    if ($ytId) {
        $qp = [];
        if ($autoplay)  $qp[] = 'autoplay=1';
        if ($autoplay || $muted) $qp[] = 'mute=1';
        if ($loop)      $qp[] = 'loop=1&playlist=' . $ytId;
        if (!$controls) $qp[] = 'controls=0';
        $embedUrl = 'https://www.youtube.com/embed/' . $ytId . ($qp ? '?' . implode('&', $qp) : '');
    } elseif ($vimeoId) {
        $qp = [];
        if ($autoplay)  $qp[] = 'autoplay=1';
        if ($autoplay || $muted) $qp[] = 'muted=1';
        if ($loop)      $qp[] = 'loop=1';
        if (!$controls) $qp[] = 'controls=0';
        $embedUrl = 'https://player.vimeo.com/video/' . $vimeoId . ($qp ? '?' . implode('&', $qp) : '');
    }

    // Build click-to-play URL (no forced autoplay — user's click is the gesture)
    $clickUrl = '';
    if ($ytId) {
        $qp = [];
        if ($loop)      $qp[] = 'loop=1&playlist=' . $ytId;
        if (!$controls) $qp[] = 'controls=0';
        $clickUrl = 'https://www.youtube.com/embed/' . $ytId . ($qp ? '?' . implode('&', $qp) : '');
    } elseif ($vimeoId) {
        $qp = [];
        if ($loop)      $qp[] = 'loop=1';
        if (!$controls) $qp[] = 'controls=0';
        $clickUrl = 'https://player.vimeo.com/video/' . $vimeoId . ($qp ? '?' . implode('&', $qp) : '');
    }

    $paddingMap    = ['16-9' => '56.25%', '4-3' => '75%', '1-1' => '100%', '9-16' => '177.78%'];
    $paddingBottom = $paddingMap[$aspect] ?? '56.25%';

    $wrapperStyle = 'width:100%;';
    if (isset($s['marginTop'])    && $s['marginTop']    !== '') $wrapperStyle .= 'margin-top:'    . $s['marginTop']    . ($s['marginTopUnit']    ?? 'px') . ';';
    if (isset($s['marginBottom']) && $s['marginBottom'] !== '') $wrapperStyle .= 'margin-bottom:' . $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px') . ';';

    $thumbnailUrl = $ytId ? 'https://img.youtube.com/vi/' . $ytId . '/hqdefault.jpg' : null;

    $boxStyle    = 'position:relative;width:100%;padding-bottom:' . $paddingBottom . ';height:0;overflow:hidden;';
    $iframeStyle = 'position:absolute;top:0;left:0;width:100%;height:100%;border:0;';
    $playBtnWrap = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;';
@endphp

@php
    $elemId    = !empty($s['cssId'])    ? $s['cssId']    : '';
    $elemClass = !empty($s['cssClass']) ? $s['cssClass'] : '';
@endphp

<div class="element-video {{ $elemClass }} {{ $visibilityClasses }}"
     @if($elemId) id="{{ $elemId }}" @endif
     style="{{ $wrapperStyle }}">

    @if($url)

        @if($ytId)

            @if($autoplay)
                {{-- Autoplay ON: direct iframe --}}
                <div style="{{ $boxStyle }}">
                    <iframe src="{{ $embedUrl }}"
                            style="{{ $iframeStyle }}"
                            allowfullscreen
                            allow="autoplay; encrypted-media; picture-in-picture"></iframe>
                </div>

            @else
                {{-- Autoplay OFF: thumbnail + click-to-play --}}
                <div style="{{ $boxStyle }}background:#000;cursor:pointer;"
                     onclick="(function(c){var f=document.createElement('iframe');f.setAttribute('src','{{ $clickUrl }}');f.setAttribute('style','{{ $iframeStyle }}');f.setAttribute('allowfullscreen','1');f.setAttribute('allow','autoplay;encrypted-media;picture-in-picture');c.innerHTML='';c.appendChild(f);})(this)">
                    <img src="{{ $thumbnailUrl }}"
                         alt=""
                         loading="lazy"
                         style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;display:block;">
                    <div style="{{ $playBtnWrap }}">
                        <div style="width:68px;height:48px;background:#ff0000;border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,0.55);">
                            <svg viewBox="0 0 68 48" width="68" height="48"><path fill="#fff" d="M26.5 16l17 8-17 8z"/></svg>
                        </div>
                    </div>
                </div>
            @endif

        @elseif($vimeoId)

            @if($autoplay)
                {{-- Autoplay ON: direct iframe --}}
                <div style="{{ $boxStyle }}">
                    <iframe src="{{ $embedUrl }}"
                            style="{{ $iframeStyle }}"
                            allowfullscreen
                            allow="autoplay; encrypted-media; picture-in-picture"></iframe>
                </div>

            @else
                {{-- Autoplay OFF: Vimeo placeholder + click-to-play --}}
                <div style="{{ $boxStyle }}background:#1ab7ea;cursor:pointer;"
                     onclick="(function(c){var f=document.createElement('iframe');f.setAttribute('src','{{ $clickUrl }}');f.setAttribute('style','{{ $iframeStyle }}');f.setAttribute('allowfullscreen','1');f.setAttribute('allow','autoplay;encrypted-media;picture-in-picture');c.innerHTML='';c.appendChild(f);})(this)">
                    <div style="{{ $playBtnWrap }}">
                        <div style="width:68px;height:48px;background:rgba(0,0,0,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <svg viewBox="0 0 68 48" width="68" height="48"><path fill="#fff" d="M26.5 16l17 8-17 8z"/></svg>
                        </div>
                    </div>
                </div>
            @endif

        @else
            {{-- Direct video file --}}
            <video style="width:100%;display:block;"
                   @if($controls) controls @endif
                   @if($autoplay) autoplay @endif
                   @if($muted || $autoplay) muted @endif
                   @if($loop) loop @endif
                   playsinline>
                <source src="{{ $url }}">
            </video>
        @endif

    @else
        <div style="background:#1d2327;padding:60px 20px;text-align:center;color:#8c8f94;font-size:13px;border-radius:4px;">
            No video URL set
        </div>
    @endif

</div>
