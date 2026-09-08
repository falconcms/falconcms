@php
    $s = $el['settings'] ?? [];

    $dynamicSrc  = $s['dynamic_source']      ?? '';
    $linkDynamic = $s['link_dynamic_source'] ?? '';
    $dynamicConfig = falcon_dynamic_config($s);
    $buttonText  = $dynamicSrc
        ? (function_exists('falcon_resolve_dynamic_value') ? (falcon_resolve_dynamic_value($dynamicSrc, $post ?? null, $dynamicConfig) ?: ($s['text'] ?? 'Click Here')) : ($postTitle ?? $s['text'] ?? 'Click Here'))
        : ($s['text'] ?? 'Click Here');
    $resolvedLinkUrl = $linkDynamic
        ? (function_exists('falcon_resolve_dynamic_value') ? (falcon_resolve_dynamic_value($linkDynamic, $post ?? null, falcon_dynamic_config($s, 'link')) ?: ($s['linkUrl'] ?? '')) : ($postPermalink ?? $s['linkUrl'] ?? ''))
        : ($s['linkUrl'] ?? '');

    // Render an <a> whenever the Link URL has any value (including a bare "#").
    // Only a truly empty field renders a plain <span> with no anchor.
    $hasLink = $resolvedLinkUrl !== null && trim((string) $resolvedLinkUrl) !== '';
    $linkTag = $hasLink ? 'a' : 'span';

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    $elemId = 'btn-' . str_replace('.', '', uniqid('', true));
    $appliedId = !empty($s['cssId']) ? $s['cssId'] : $elemId;

    $hexToRgba = function(string $hex, $opacity = null): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) !== 6) return $hex ? "#{$hex}" : 'transparent';
        [$r, $g, $b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
        if ($opacity === null || $opacity === '' || (float)$opacity >= 1) return "#{$hex}";
        return "rgba({$r},{$g},{$b}," . (float)$opacity . ")";
    };

    $getRespVal = function(string $prop, string $dev) use ($s) {
        if ($dev === 'mobile') {
            if (isset($s[$prop . '_mobile']) && $s[$prop . '_mobile'] !== '') return (string)$s[$prop . '_mobile'];
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        } elseif ($dev === 'tablet') {
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        }
        return null;
    };

    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',     'unitProp' => 'marginTopUnit',     'sel' => ".button-container-{$elemId}"],
        ['prop' => 'marginBottom',  'unitProp' => 'marginBottomUnit',  'sel' => ".button-container-{$elemId}"],
        ['prop' => 'marginLeft',    'unitProp' => 'marginLeftUnit',    'sel' => "#{$appliedId}"],
        ['prop' => 'marginRight',   'unitProp' => 'marginRightUnit',   'sel' => "#{$appliedId}"],
        ['prop' => 'paddingTop',    'unitProp' => 'paddingTopUnit',    'sel' => "#{$appliedId}"],
        ['prop' => 'paddingRight',  'unitProp' => 'paddingRightUnit',  'sel' => "#{$appliedId}"],
        ['prop' => 'paddingBottom', 'unitProp' => 'paddingBottomUnit', 'sel' => "#{$appliedId}"],
        ['prop' => 'paddingLeft',   'unitProp' => 'paddingLeftUnit',   'sel' => "#{$appliedId}"],
    ]);
    // Full Width (Span) decides what Alignment means, so it is read once and named.
    $isSpan = (bool) ($s['buttonSpan'] ?? false);
    $align  = $s['textAlign'] ?? 'center';

    // textAlign needs its value transformed, so it is written here rather than through
    // falcon_elem_resp_css(). Which property it lands on follows the desktop rule above:
    // the label inside a full-width button, the button within its row otherwise.
    foreach ([
        ['tablet', "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)"],
        ['mobile', "@media(max-width:{$bpSm}px)"],
    ] as [$rDev, $rMq]) {
        $rAlign = $getRespVal('textAlign', $rDev);
        if ($rAlign !== null) {
            if ($isSpan) {
                $respCss .= "{$rMq}{#{$appliedId}{text-align:{$rAlign}!important}}";
            } else {
                $jc = $rAlign === 'left' ? 'flex-start' : ($rAlign === 'right' ? 'flex-end' : 'center');
                $respCss .= "{$rMq}{.button-container-{$elemId}{justify-content:{$jc}!important}}";
            }
        }
    }

    $wrapperStyles = [
        'display' => 'flex',
        'width' => '100%',
        // Defaulted, not assumed: a button saved before this setting existed has no textAlign
        // at all, and reading it raised a warning on every render of such a button.
        //
        // Alignment means two different things depending on Full Width (Span). A button that
        // is only as wide as its label is placed by moving it within the row, which is this
        // justify-content. A full-width button already fills the row, so there is nowhere to
        // move it to and the setting looked broken — there, Alignment moves the label inside
        // the button instead (text-align, set below).
        'justify-content' => $isSpan
            ? 'center'
            : ($align === 'left' ? 'flex-start' : ($align === 'right' ? 'flex-end' : 'center')),
        'margin-top' => getUnitVal($s['marginTop'] ?? 10, $s['marginTopUnit'] ?? 'px'),
        'margin-bottom' => getUnitVal($s['marginBottom'] ?? 10, $s['marginBottomUnit'] ?? 'px'),
    ];

    $btnStyles = [
        'display' => $isSpan ? 'block' : 'inline-block',
        'width' => $isSpan ? '100%' : 'auto',
        'padding-top' => getUnitVal($s['paddingTop'] ?? 12, $s['paddingTopUnit'] ?? 'px'),
        'padding-bottom' => getUnitVal($s['paddingBottom'] ?? 12, $s['paddingBottomUnit'] ?? 'px'),
        'padding-left' => getUnitVal($s['paddingLeft'] ?? 30, $s['paddingLeftUnit'] ?? 'px'),
        'padding-right' => getUnitVal($s['paddingRight'] ?? 30, $s['paddingRightUnit'] ?? 'px'),
        'margin-left' => getUnitVal($s['marginLeft'] ?? 0, $s['marginLeftUnit'] ?? 'px'),
        'margin-right' => getUnitVal($s['marginRight'] ?? 0, $s['marginRightUnit'] ?? 'px'),
        'background-color' => (($s['buttonStyle'] ?? 'default') === 'custom' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor'])) ? 'transparent' : $hexToRgba($s['bgColor'] ?? '#0091ea', $s['bgColorOpacity'] ?? null),
        'background-image' => (($s['buttonStyle'] ?? 'default') === 'custom' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor']))
            ? (($s['bgGradientType'] ?? 'linear') === 'radial'
                ? "radial-gradient(circle at center, " . $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? null) . " " . ($s['bgGradientStartPosition'] ?? 0) . "%, " . $hexToRgba($s['bgGradientEndColor'], $s['bgGradientEndOpacity'] ?? null) . " " . ($s['bgGradientEndPosition'] ?? 100) . "%)"
                : "linear-gradient(" . ($s['bgGradientAngle'] ?? 180) . "deg, " . $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? null) . " " . ($s['bgGradientStartPosition'] ?? 0) . "%, " . $hexToRgba($s['bgGradientEndColor'], $s['bgGradientEndOpacity'] ?? null) . " " . ($s['bgGradientEndPosition'] ?? 100) . "%)")
            : 'none',
        'color' => $hexToRgba((($s['buttonStyle'] ?? 'default') === 'custom' && !empty($s['customTextColor'])) ? $s['customTextColor'] : ($s['color'] ?? '#ffffff'), (($s['buttonStyle'] ?? 'default') === 'custom' && !empty($s['customTextColor'])) ? ($s['customTextColorOpacity'] ?? null) : ($s['colorOpacity'] ?? null)),
        'border-radius' => getUnitVal($s['borderRadius'] ?? 5, 'px'),
        'border-top-width' => getUnitVal($s['borderSizeTop'] ?? 0, 'px'),
        'border-right-width' => getUnitVal($s['borderSizeRight'] ?? 0, 'px'),
        'border-bottom-width' => getUnitVal($s['borderSizeBottom'] ?? 0, 'px'),
        'border-left-width' => getUnitVal($s['borderSizeLeft'] ?? 0, 'px'),
        'border-style' => 'solid',
        'border-color' => $hexToRgba($s['borderColor'] ?? '#000000', $s['borderColorOpacity'] ?? null),
        'font-family' => $s['fontFamily'] ?? 'inherit',
        'font-size' => preg_match('/[a-zA-Z%]/', (string)($s['fontSize'] ?? '')) ? (string)($s['fontSize'] ?? '16px') : (($s['fontSize'] ?? 16) . ($s['fontSizeUnit'] ?? 'px')),
        'font-weight' => $s['fontWeight'] ?? '600',
        'line-height' => $s['lineHeight'] ?? 'normal',
        'letter-spacing' => getUnitVal($s['letterSpacing'] ?? 0, $s['letterSpacingUnit'] ?? 'px'),
        'text-transform' => $s['textTransform'] ?? 'none',
        'text-decoration' => 'none',
        'transition' => 'all 0.3s ease',
        'cursor' => $hasLink ? 'pointer' : 'default',
        // See the note on justify-content above: on a full-width button this is what
        // Alignment moves. On a button sized to its label there is nothing to move, and
        // centring the label is what it has always done.
        'text-align' => $isSpan ? $align : 'center',
    ];

    $isCustom = ($s['buttonStyle'] ?? 'default') === 'custom';
    $hoverColor   = $hexToRgba($s['hoverColor']   ?? '#ffffff', $s['hoverColorOpacity']   ?? null);
    $hoverBgColor = $hexToRgba($s['hoverBgColor'] ?? '#007cc0', $s['hoverBgColorOpacity'] ?? null);
    $hoverStart = $hexToRgba($s['bgGradientHoverStartColor'] ?? '#007cc0', $s['bgGradientHoverStartOpacity'] ?? null);
    $hoverEnd   = $hexToRgba($s['bgGradientHoverEndColor']   ?? '#005fa3', $s['bgGradientHoverEndOpacity']   ?? null);
    $icon = $s['icon'] ?? '';
    $iconPos = $s['iconPosition'] ?? 'left';

    // Looping text animation (Extra tab), split across two nodes. The motion modes ride
    // the anchor so the whole button moves; the modes that repaint the glyphs ride the
    // label span instead, because clipping a gradient to the letters on the anchor would
    // take the button's own fill with it.
    $textAnim = \FalconCms\Core\Support\TextAnimations::resolveFor('button', $s);
    if ($textAnim) {
        foreach ($textAnim['vars'] as $prop => $val) {
            $btnStyles[$prop] = $val;
        }
    }
    $labelAnim = \FalconCms\Core\Support\TextAnimations::resolveFor('button', $s, 'text');
    $labelAnimStyle = '';
    if ($labelAnim) {
        foreach ($labelAnim['vars'] as $prop => $val) {
            $labelAnimStyle .= $prop.': '.$val.';';
        }
    }

    // Hover Border. Every part is optional and an empty one means "keep what the border
    // already has" — which is what a button saved before these controls existed says for all
    // of them, so nothing changes under such a button.
    $hoverBorderColor = !empty($s['hoverBorderColor'])
        ? $hexToRgba($s['hoverBorderColor'], $s['hoverBorderColorOpacity'] ?? null)
        : null;

    $hoverBorderCss = '';
    foreach (['Top' => 'top', 'Right' => 'right', 'Bottom' => 'bottom', 'Left' => 'left'] as $sideKey => $side) {
        $w = $s['hoverBorderSize'.$sideKey] ?? '';
        // 0 is a real answer — "no border on this edge when hovered" — so only an unset or
        // blank field falls through to the resting width.
        if ($w === '' || $w === null) {
            continue;
        }
        $hoverBorderCss .= "border-{$side}-width: ".getUnitVal($w, 'px').' !important; ';
    }

    // Hover Animation. The button already carries `transition: all .3s ease`, so each of
    // these only has to state the resting and hovered ends. Motion is skipped for readers
    // who have asked their system for less of it.
    $hoverAnimations = [
        'lift' => ['rest' => 'transform:translateY(0)', 'hover' => 'transform:translateY(-4px); box-shadow:0 10px 20px rgba(0,0,0,0.18)'],
        'sink' => ['rest' => 'transform:translateY(0)', 'hover' => 'transform:translateY(3px); box-shadow:0 2px 6px rgba(0,0,0,0.14)'],
        'grow' => ['rest' => 'transform:scale(1)', 'hover' => 'transform:scale(1.06)'],
        'shrink' => ['rest' => 'transform:scale(1)', 'hover' => 'transform:scale(0.94)'],
        'glow' => ['rest' => 'box-shadow:0 0 0 rgba(0,0,0,0)', 'hover' => 'box-shadow:0 0 18px 2px currentColor'],
        'pulse' => ['rest' => '', 'hover' => 'animation:falcon-btn-pulse 0.9s ease-in-out infinite'],
    ];
    $hoverAnim = $hoverAnimations[$s['hoverAnimation'] ?? 'none'] ?? null;

    // The resting half is an inline style on the button, and an inline style beats a
    // stylesheet rule however the two are ordered — so a plain `transform` in the :hover
    // block lost to it and the button never moved, while the box-shadow beside it (which
    // has no inline counterpart) worked. Marked important, like every other hover
    // declaration in this block.
    $hoverAnimCss = '';
    if ($hoverAnim) {
        foreach (array_filter(array_map('trim', explode(';', $hoverAnim['hover']))) as $decl) {
            $hoverAnimCss .= $decl.' !important; ';
        }
    }

    if ($hoverAnim && $hoverAnim['rest'] !== '') {
        foreach (explode(';', $hoverAnim['rest']) as $decl) {
            if (str_contains($decl, ':')) {
                [$prop, $val] = explode(':', $decl, 2);
                $btnStyles[trim($prop)] = trim($val);
            }
        }
    }

    $hoverBgImage = 'none';
    if ($isCustom && !empty($s['bgGradientStartColor'])) {
         if (($s['bgGradientType'] ?? 'linear') === 'radial') {
             $hoverBgImage = "radial-gradient(circle at center, {$hoverStart} " . ($s['bgGradientStartPosition'] ?? 0) . "%, {$hoverEnd} " . ($s['bgGradientEndPosition'] ?? 100) . "%)";
         } else {
             $hoverBgImage = "linear-gradient(" . ($s['bgGradientAngle'] ?? 180) . "deg, {$hoverStart} " . ($s['bgGradientStartPosition'] ?? 0) . "%, {$hoverEnd} " . ($s['bgGradientEndPosition'] ?? 100) . "%)";
         }
    }
@endphp

<style>
    #{{ $appliedId }}:hover {
        @if($isCustom)
            background-image: {{ $hoverBgImage }} !important;
            background-color: transparent !important;
        @else
            background-color: {{ $hoverBgColor }} !important;
            background-image: none !important;
        @endif
        color: {{ $hoverColor }} !important;
        @if($hoverBorderColor) border-color: {{ $hoverBorderColor }} !important; @endif
        @if($hoverBorderCss) {!! $hoverBorderCss !!} @endif
        @if($hoverAnimCss) {!! $hoverAnimCss !!} @endif
    }
    @if(($s['hoverAnimation'] ?? 'none') === 'pulse')
    @keyframes falcon-btn-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }
    @endif
    @if($hoverAnim)
    @media (prefers-reduced-motion: reduce) {
        #{{ $appliedId }}, #{{ $appliedId }}:hover { transform: none !important; animation: none !important; }
    }
    @endif
    @if($respCss) {!! $respCss !!} @endif
</style>

<div class="element-button-wrapper fa-tanim-host button-container-{{ $elemId }} {{ $s['cssClass'] ?? '' }} {{ $visibilityClasses }}"
     style="{{ collect($wrapperStyles)->map(fn($v, $k) => "$k: $v")->implode('; ') }}">
    <{{ $linkTag }}
       @if($hasLink) href="{{ $resolvedLinkUrl }}" target="{{ $s['linkTarget'] ?? '_self' }}" @endif
       id="{{ $appliedId }}"
       @if($textAnim) class="{{ $textAnim['classes'] }}" @endif
       style="{{ collect($btnStyles)->map(fn($v, $k) => "$k: $v")->implode('; ') }}">
        @if($icon && $iconPos !== 'right')
            <i class="{{ $icon }} mr-2"></i>
        @endif
        @if($labelAnim)<span class="{{ $labelAnim['classes'] }}" style="{{ $labelAnimStyle }}">{{ $buttonText }}</span>@else{{ $buttonText }}@endif
        @if($icon && $iconPos === 'right')
            <i class="{{ $icon }} ml-2"></i>
        @endif
    </{{ $linkTag }}>
</div>
