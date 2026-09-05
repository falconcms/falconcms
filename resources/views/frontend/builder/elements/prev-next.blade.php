@php
    /*
    |--------------------------------------------------------------------------
    | Previous / Next — frontend renderer
    |--------------------------------------------------------------------------
    | Where the two links come from is the whole element, and it lives in
    | FalconCms\Core\Support\PrevNext: a menu flattened into reading order, or the
    | pages of a type in the order that type implies, with either side overridable
    | by hand. The canvas is handed the answers for every source through @json(), so
    | the preview shows the real neighbours rather than a sample. See:
    |   resources/views/admin/falcon-builder/partials/components/elements/prev-next.blade.php
    |
    | Rendered on the server, unlike the Table of Contents: this element depends on
    | which page it is on, and the server is the only place that is known.
    */
    use FalconCms\Core\Support\PrevNext;
    use FalconCms\Core\Support\Typography;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $preset = PrevNext::preset($s['preset'] ?? 'cards');

    $g = function (string $key) use ($s, $preset) {
        $val = $s[$key] ?? null;

        return ($val === null || $val === '') ? ($preset[$key] ?? '') : $val;
    };

    // Only on a page that carries a Table of Contents. That is what marks a page as one
    // of a sequence — on a landing page there is nothing to step through — and the
    // builder shows the element as inactive under the same rule, so the two agree.
    $active = PrevNext::pageHasToc($post ?? null);

    $pair = $active ? PrevNext::resolve($s, $post ?? null) : ['prev' => null, 'next' => null];
    $prev = $pair['prev'];
    $next = $pair['next'];

    $prevLabel = array_key_exists('prevLabel', $s) ? trim((string) $s['prevLabel']) : 'Previous';
    $nextLabel = array_key_exists('nextLabel', $s) ? trim((string) $s['nextLabel']) : 'Next';
    $showTitles = ($s['showTitles'] ?? true) !== false;
    $showArrows = ($s['showArrows'] ?? true) !== false;

    $typo = fn (string $prefix) => Typography::css($s, $prefix);
    $labelTypo = $typo('pn_label');
    $titleTypo = $typo('pn_title');

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $uid    = 'fc-pn-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    $elemId = !empty($s['cssId']) ? $s['cssId'] : null;
@endphp

{{-- Nothing at all when there is nowhere to go.

     A page that is not in the sequence — a landing page, the first draft of something
     not yet in the menu — would otherwise render an empty pair of boxes under its own
     content, and an author would have to work out for themselves that the element was
     working and the menu was not. Rendering nothing is the same answer the Table of
     Contents gives, and for the same reason. --}}
@if($prev || $next)
<div class="falcon-prev-next{{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }}"
     @if($elemId) id="{{ $elemId }}" @endif
     style="width:100%;margin-top:{{ $marginTop }};margin-bottom:{{ $marginBottom }};">

    <style>
        #{{ $uid }} {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: {{ (int) $g('gap') }}px;
            align-items: stretch;
        }
        {{-- One link keeps its own side. A lone Next belongs on the right, where the
             reader is looking for it, not sitting where Previous would have been. --}}
        #{{ $uid }} .fc-pn-link:only-child { grid-column: span 1; }
        #{{ $uid }} .fc-pn-next:only-child { grid-column: 2; }

        #{{ $uid }} .fc-pn-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: {{ (int) $g('padY') }}px {{ (int) $g('padX') }}px;
            background: {{ $g('bg') }};
            border-radius: {{ (int) $g('radius') }}px;
            @if((int) $g('borderWidth') > 0) border: {{ (int) $g('borderWidth') }}px solid {{ $g('borderColor') }}; @endif
            text-decoration: none;
            transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
        }
        #{{ $uid }} .fc-pn-next { flex-direction: row-reverse; text-align: right; }
        @if($g('hoverBorder') && $g('hoverBorder') !== 'transparent')
        #{{ $uid }} .fc-pn-link:hover {
            border-color: {{ $g('hoverBorder') }};
            @if((int) $g('borderWidth') > 0) transform: translateY(-1px); @endif
        }
        @endif

        #{{ $uid }} .fc-pn-arrow {
            flex: 0 0 auto;
            color: {{ $g('arrowColor') }};
            font-size: 14px;
            line-height: 1;
            font-style: normal;
        }
        #{{ $uid }} .fc-pn-text { min-width: 0; }
        #{{ $uid }} .fc-pn-label {
            display: block;
            color: {{ $g('labelColor') }};
            font-size: {{ $g('labelSize') }}px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 3px;
            @if($labelTypo) {{ $labelTypo }} @endif
        }
        #{{ $uid }} .fc-pn-title {
            display: block;
            color: {{ $g('titleColor') }};
            font-size: {{ $g('titleSize') }}px;
            font-weight: {{ $g('titleWeight') }};
            line-height: 1.35;
            {{-- Long page titles are normal in documentation, and two of them wrapping to
                 three lines each turns a footer into a wall. One line, cut cleanly. --}}
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            @if($titleTypo) {{ $titleTypo }} @endif
        }

        {{-- Side by side is the point of this element — one link left, one right — so it
             only stacks where there is genuinely no room for two. --}}
        @media (max-width: 640px) {
            #{{ $uid }} { grid-template-columns: 1fr; }
            #{{ $uid }} .fc-pn-next:only-child { grid-column: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            #{{ $uid }} .fc-pn-link { transition: none; }
            #{{ $uid }} .fc-pn-link:hover { transform: none; }
        }
    </style>

    <nav id="{{ $uid }}" aria-label="{{ $prevLabel !== '' || $nextLabel !== '' ? 'Page navigation' : 'Previous and next page' }}">
        @foreach(['prev' => $prev, 'next' => $next] as $side => $entry)
            @continue(!$entry)
            @php
                $label = $side === 'prev' ? $prevLabel : $nextLabel;
                $arrow = $side === 'prev' ? '&larr;' : '&rarr;';
            @endphp
            <a class="fc-pn-link fc-pn-{{ $side }}" href="{{ falcon_anchor_url($entry['url']) }}"
               rel="{{ $side === 'prev' ? 'prev' : 'next' }}">
                @if($showArrows)<i class="fc-pn-arrow" aria-hidden="true">{!! $arrow !!}</i>@endif
                <span class="fc-pn-text">
                    @if($label !== '')<span class="fc-pn-label">{{ $label }}</span>@endif
                    @if($showTitles)<span class="fc-pn-title">{{ $entry['title'] }}</span>@endif
                </span>
            </a>
        @endforeach
    </nav>
</div>
@endif
