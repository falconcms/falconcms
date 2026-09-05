@php
    /*
    |--------------------------------------------------------------------------
    | Callout — frontend renderer
    |--------------------------------------------------------------------------
    | Variants and presets come from FalconCms\Core\Support\CalloutStyles, and the
    | body's markup from FalconCms\Core\Support\InlineMarkup — the same one the
    | Table's cells use. The admin canvas receives the same arrays as JSON and
    | mirrors the same rules, so the two renderers cannot drift. See:
    |   resources/views/admin/falcon-builder/partials/components/elements/callout.blade.php
    |   resources/views/admin/falcon-builder/partials/scripts.blade.php  (fcCal* helpers)
    |
    | The body is plain text with a small markup — paragraphs, bullets, `code`,
    | **bold**, *italic*, [text](url), [button …] — escaped before those rules run,
    | so a callout can never carry script and no sanitiser has to sit between the
    | editor and the page.
    */
    use FalconCms\Core\Support\CalloutStyles;
    use FalconCms\Core\Support\InlineMarkup;
    use FalconCms\Core\Support\Typography;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $variant = CalloutStyles::variant($s['variant'] ?? 'note');
    $preset  = CalloutStyles::preset($s['preset'] ?? 'bar');

    // Every preset and variant value stays overridable; they only supply the default.
    // An empty string counts as "not set", not as a value — the Design tab's reset
    // button writes '', and with a plain ?? that reached the stylesheet as `color: ;`,
    // dropping the rule and the preset's colour with it.
    $g = function (string $key) use ($s, $preset) {
        $val = $s[$key] ?? null;

        return ($val === null || $val === '') ? ($preset[$key] ?? '') : $val;
    };

    $accent = trim((string) ($s['accent'] ?? '')) ?: $variant['accent'];
    $tint   = trim((string) ($s['bgColor'] ?? '')) ?: $variant['tint'];
    $titleColor = trim((string) ($s['titleColor'] ?? '')) ?: $variant['ink'];
    $bodyColor  = trim((string) ($s['bodyColor'] ?? '')) ?: '#3C4652';

    $fill = $g('fill');                       // tint | none | white
    $background = $fill === 'tint' ? $tint : ($fill === 'white' ? '#FFFFFF' : 'transparent');

    // The title is a real choice: an author who clears it wants no title, so only a
    // title that was never set at all falls back to the variant's name.
    $title = array_key_exists('title', $s) ? trim((string) $s['title']) : CalloutStyles::defaultTitle($s['variant'] ?? 'note');

    $iconStyle = $s['iconStyle'] ?? $g('iconStyle');   // plain | badge | header | none
    $icon = CalloutStyles::safeIcon($s['icon'] ?? '') ?: $variant['icon'];
    $showIcon = $iconStyle !== 'none' && $icon !== '';

    $collapsible = !empty($s['collapsible']);
    $openByDefault = ($s['openByDefault'] ?? true) !== false;

    $body = InlineMarkup::blocks($s['body'] ?? '');

    // Typography, from the shared control every other element uses. Empty means
    // "leave it to the preset", so an element that has never been touched still follows
    // whichever preset is chosen. The unit rules live in Typography so that every
    // element applies them the same way.
    $typo = fn (string $prefix) => Typography::css($s, $prefix);
    $titleTypo = $typo('cal_title');
    $bodyTypo  = $typo('cal_body');

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $uid    = 'fc-cal-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    $elemId = !empty($s['cssId']) ? $s['cssId'] : null;

    $hasContent = $title !== '' || trim($body) !== '' || $showIcon;

    // Looping text animation (Extra tab), split across two nodes. The motion modes ride
    // the card itself (not the outer wrapper, which only carries the element's margins);
    // the modes that repaint the glyphs ride the title, because clipping a gradient to
    // the letters on the card would take its background and border with it.
    $textAnim = \FalconCms\Core\Support\TextAnimations::resolveFor('callout', $s);
    $textAnimStyle = '';
    if ($textAnim) {
        foreach ($textAnim['vars'] as $prop => $val) {
            $textAnimStyle .= $prop.': '.$val.';';
        }
    }
    $titleAnim = \FalconCms\Core\Support\TextAnimations::resolveFor('callout', $s, 'text');
    $titleAnimStyle = '';
    if ($titleAnim) {
        foreach ($titleAnim['vars'] as $prop => $val) {
            $titleAnimStyle .= $prop.': '.$val.';';
        }
    }
@endphp

@if($hasContent)
<div class="falcon-callout fa-tanim-host{{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }}"
     @if($elemId) id="{{ $elemId }}" @endif
     style="width:100%;margin-top:{{ $marginTop }};margin-bottom:{{ $marginBottom }};">

    <style>
        #{{ $uid }} {
            --fc-cal-accent: {{ $accent }};
            display: block;
            background: {{ $background }};
            border-radius: {{ (int) $g('radius') }}px;
            padding: {{ (int) $g('padY') }}px {{ (int) $g('padX') }}px;
            @if((int) $g('borderWidth') > 0) border: {{ (int) $g('borderWidth') }}px solid {{ $accent }}33; @endif
            @if((int) $g('barWidth') > 0) border-left: {{ (int) $g('barWidth') }}px solid var(--fc-cal-accent); @endif
            @if(!empty($preset['shadow'])) box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px -16px rgba(16,24,40,.24); @endif
        }
        {{-- A <details> lays its own marker before the summary in most browsers and is
             display:list-item in some; both are replaced by the chevron below. --}}
        #{{ $uid }} > summary::-webkit-details-marker { display: none; }
        #{{ $uid }} > summary { list-style: none; }
        #{{ $uid }} .fc-cal-head {
            display: flex; align-items: center; gap: {{ (int) $g('gap') }}px;
            @if($title !== '' && trim($body) !== '') margin-bottom: 8px; @endif
        }
        #{{ $uid }} .fc-cal-title {
            color: {{ $titleColor }};
            font-size: {{ $g('titleSize') }}px;
            font-weight: {{ $g('titleWeight') }};
            line-height: 1.35;
            margin: 0;
            @if($titleTypo) {{ $titleTypo }} @endif
        }
        #{{ $uid }} .fc-cal-icon {
            flex: 0 0 auto;
            color: var(--fc-cal-accent);
            font-size: {{ $g('iconSize') }}px;
            line-height: 1;
            font-style: normal;
        }
        @if($iconStyle === 'badge')
        #{{ $uid }} .fc-cal-icon {
            width: {{ (int) $g('iconSize') + 16 }}px; height: {{ (int) $g('iconSize') + 16 }}px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: transparent;
            background: color-mix(in srgb, var(--fc-cal-accent) 16%, transparent);
        }
        @endif
        @if($iconStyle === 'header')
        {{-- The Solid header preset paints the title row in the accent and sits it flush
             against the top of the box, which is why the padding is cancelled here and
             put back on the body instead. --}}
        #{{ $uid }} {
            padding: 0;
            overflow: hidden;
            @if((int) $g('borderWidth') > 0) border-color: {{ $accent }}44; @endif
        }
        #{{ $uid }} .fc-cal-head {
            margin: 0;
            padding: 9px {{ (int) $g('padX') }}px;
            background: var(--fc-cal-accent);
        }
        #{{ $uid }} .fc-cal-title, #{{ $uid }} .fc-cal-icon, #{{ $uid }} .fc-cal-chev { color: #FFFFFF; }
        #{{ $uid }} .fc-cal-body { padding: {{ (int) $g('padY') }}px {{ (int) $g('padX') }}px; }
        @endif
        #{{ $uid }} .fc-cal-body {
            color: {{ $bodyColor }};
            font-size: {{ $g('bodySize') }}px;
            line-height: 1.65;
            @if($bodyTypo) {{ $bodyTypo }} @endif
        }
        {{-- The body's own block styles, from CalloutStyles so the canvas can apply the
             very same rules — it has to, because the admin's CSS reset strips list
             markers and paragraph margins from everything and the two previews would
             otherwise disagree about whether a bullet is a bullet. --}}
        {!! CalloutStyles::bodyCss('#'.$uid.' .fc-cal-body', $accent, $bodyColor) !!}

        @if($collapsible)
        #{{ $uid }} > summary { cursor: pointer; }
        #{{ $uid }} .fc-cal-chev {
            margin-left: auto;
            flex: 0 0 auto;
            color: {{ $titleColor }};
            opacity: .55;
            font-size: 12px;
            transition: transform .18s ease;
        }
        #{{ $uid }}[open] .fc-cal-chev { transform: rotate(90deg); }
        @endif

        @media (prefers-reduced-motion: reduce) {
            #{{ $uid }} .fc-cal-chev { transition: none; }
        }
    </style>

    @php
        // <details> is the whole collapsible behaviour — it opens, it closes, it is
        // keyboard-reachable and a browser's find-in-page can open it, none of which a
        // div and a click handler get for free. A non-collapsible callout is a plain
        // div, because a <details open> that can never close still announces itself as
        // a disclosure to a screen reader.
        $tag = $collapsible ? 'details' : 'div';
    @endphp

    <{{ $tag }} id="{{ $uid }}" class="fc-cal fc-cal-{{ $s['variant'] ?? 'note' }}{{ $textAnim ? ' '.$textAnim['classes'] : '' }}"
        @if($textAnimStyle) style="{{ $textAnimStyle }}" @endif
        @if($collapsible && $openByDefault) open @endif
        @if(!$collapsible) role="note" @endif>

        @if($title !== '' || $showIcon)
            @if($collapsible)
            <summary class="fc-cal-head">
                @if($showIcon)<i class="fc-cal-icon {{ $icon }}" aria-hidden="true"></i>@endif
                <span class="fc-cal-title{{ $titleAnim ? ' '.$titleAnim['classes'] : '' }}"
                      @if($titleAnimStyle) style="{{ $titleAnimStyle }}" @endif>{{ $title !== '' ? $title : $variant['name'] }}</span>
                <i class="fc-cal-chev fas fa-chevron-right" aria-hidden="true"></i>
            </summary>
            @else
            <div class="fc-cal-head">
                @if($showIcon)<i class="fc-cal-icon {{ $icon }}" aria-hidden="true"></i>@endif
                @if($title !== '')<p class="fc-cal-title{{ $titleAnim ? ' '.$titleAnim['classes'] : '' }}"
                    @if($titleAnimStyle) style="{{ $titleAnimStyle }}" @endif>{{ $title }}</p>@endif
            </div>
            @endif
        @endif

        @if(trim($body) !== '')
        <div class="fc-cal-body">{!! $body !!}</div>
        @endif
    </{{ $tag }}>
</div>
@endif
