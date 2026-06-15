<style>
    @media (max-width: {{ get_cms_option('theme_small_screen_breakpoint', '800') }}px) { 
        .lazy-hide-mobile { display: none !important; } 
        .lazy-column {
            flex-basis: 100% !important;
            max-width: 100% !important;
            width: 100% !important;
        }
    }
    @media (min-width: {{ (int)get_cms_option('theme_small_screen_breakpoint', '800') + 1 }}px) and (max-width: {{ get_cms_option('theme_medium_screen_breakpoint', '1100') }}px) { .lazy-hide-tablet { display: none !important; } }
    @media (min-width: {{ (int)get_cms_option('theme_medium_screen_breakpoint', '1100') + 1 }}px) { .lazy-hide-desktop { display: none !important; } }
    .lazy-hide-all { display: none !important; }
</style>
@php
    $s     = $column['settings'] ?? [];
    $basisRaw = $column['basis'] ?? null;
    if ($basisRaw === null) {
        $totalColumns = max(1, count($container['columns'] ?? [1]));
        $basisRaw = (100 / $totalColumns) . '%';
    }

    $flexBasis = $basisRaw;
    $totalCols = count($container['columns'] ?? [1]);
    $gapVal = intval($container['settings']['columnGap'] ?? 0);
    
    if ($basisRaw === 'auto') {
        $flexBasis = 'auto';
    } elseif (is_string($basisRaw) && strpos($basisRaw, '%') !== false) {
        if ($totalCols === 1) {
            $flexBasis = $basisRaw;
        } else {
            $subtract = ($gapVal * ($totalCols - 1)) / $totalCols;
            $flexBasis = "calc({$basisRaw} - {$subtract}px)";
        }
    } elseif (is_numeric($basisRaw)) {
        if ($totalCols === 1) {
            $flexBasis = "{$basisRaw}%";
        } else {
            $subtract = ($gapVal * ($totalCols - 1)) / $totalCols;
            $flexBasis = "calc({$basisRaw}% - {$subtract}px)";
        }
    }

    $flexGrow = (isset($s['flexGrow']) && $s['flexGrow'] !== '') ? $s['flexGrow'] : 0;
    $flexShrink = (isset($s['flexShrink']) && $s['flexShrink'] !== '') ? $s['flexShrink'] : 0;
    $maxWidth = $flexBasis === 'auto' ? 'none' : $flexBasis;

    // Device Visibility (Responsive)
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' lazy-hide-desktop';
    if (!($v['mobile'] ?? true) && !($v['tablet'] ?? true) && !($v['desktop'] ?? true)) {
        $visibilityClasses = ' lazy-hide-all';
    }

    $containerAlign = $container['settings']['alignItems'] ?? 'stretch';
    $colAlignment = (!empty($s['alignment']) && $s['alignment'] !== 'default') ? $s['alignment'] : 'default';
    $heightMode = $container['settings']['height'] ?? 'auto';
    $hasDefinedHeight = in_array($heightMode, ['full', 'custom'], true);
    $isEmpty = empty($column['elements']);
    
    // Comprehensive stretch detection logic
    $shouldStretch = ($colAlignment === 'stretch') 
                     || (in_array($colAlignment, ['', 'default', null], true) && $containerAlign === 'stretch');

    $globalGap = $container['settings']['columnGap'] ?? 0;
    // Use CSS custom property --lc-col-gap (set responsively by container) for default column spacing
    $pLeft  = (isset($s['columnSpacingLeft'])  && $s['columnSpacingLeft']  !== '') ? $s['columnSpacingLeft']  . '%' : 'calc(var(--lc-col-gap, ' . $globalGap . ') * 1%)';
    $pRight = (isset($s['columnSpacingRight']) && $s['columnSpacingRight'] !== '') ? $s['columnSpacingRight'] . '%' : 'calc(var(--lc-col-gap, ' . $globalGap . ') * 1%)';

    $finalAlignSelf = ($colAlignment && $colAlignment !== 'default') ? $colAlignment : $containerAlign;
    $isDefaultAlign = in_array($colAlignment, ['', 'default', null], true);
    $colCid = 'lcc-' . ($column['id'] ?? str_replace('.', '', uniqid('', true)));

    $outerStyles = [
        "flex-basis: {$flexBasis}",
        "flex-shrink: " . ($s['flexShrink'] ?? '0'),
        "max-width: {$maxWidth}",
        "width: " . ($flexBasis === '100%' || $flexBasis === 'auto' ? '100%' : $flexBasis),
        "padding-left: {$pLeft}",
        "padding-right: {$pRight}",
        'display: flex',
        'flex-direction: column',
    ];
    if (!$isDefaultAlign) {
        $outerStyles[] = "flex-grow: " . ($shouldStretch ? '1' : ($s['flexGrow'] ?? '0'));
        $outerStyles[] = "min-height: " . ($shouldStretch ? ($isEmpty ? '100px' : 'auto') : 'auto');
        $outerStyles[] = "align-self: {$finalAlignSelf}";
    }

    $innerStyles = [
        'width: 100%',
        'flex-grow: 1',
        'margin: 0',
        'padding-top: '    . ($s['paddingTop']    ?? 0) . ($s['paddingTopUnit']    ?? 'px'),
        'padding-right: '  . ($s['paddingRight']  ?? 0) . ($s['paddingRightUnit']  ?? 'px'),
        'padding-bottom: ' . ($s['paddingBottom'] ?? 0) . ($s['paddingBottomUnit'] ?? 'px'),
        'padding-left: '   . ($s['paddingLeft']   ?? 0) . ($s['paddingLeftUnit']   ?? 'px'),
        'align-items: stretch',
    ];

    if (isset($s['marginTop']) && $s['marginTop'] !== '') $outerStyles[] = 'margin-top: ' . $s['marginTop'] . ($s['marginTopUnit'] ?? 'px');
    if (isset($s['marginBottom']) && $s['marginBottom'] !== '') $outerStyles[] = 'margin-bottom: ' . $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px');

    $innerStyles = [
        'padding-top: '    . ($s['paddingTop']    ?? 0) . ($s['paddingTopUnit'] ?? 'px'),
        'padding-bottom: ' . ($s['paddingBottom'] ?? 0) . ($s['paddingBottomUnit'] ?? 'px'),
        'padding-left: '   . ($s['paddingLeft']   ?? 0) . ($s['paddingLeftUnit'] ?? 'px'),
        'padding-right: '  . ($s['paddingRight']  ?? 0) . ($s['paddingRightUnit'] ?? 'px'),
        'box-sizing: border-box',
    ];
    if (!$isDefaultAlign) {
        array_unshift($innerStyles,
            'min-height: ' . ($shouldStretch ? 'auto' : '8px'),
            'flex: ' . ($shouldStretch ? '1 1 auto' : '0 1 auto'),
            'flex-grow: ' . ($shouldStretch ? '1' : '0')
        );
    }
    if (isset($s['marginLeft']) && $s['marginLeft'] !== '') $innerStyles[] = 'margin-left: ' . $s['marginLeft'] . ($s['marginLeftUnit'] ?? 'px');
    if (isset($s['marginRight']) && $s['marginRight'] !== '') $innerStyles[] = 'margin-right: ' . $s['marginRight'] . ($s['marginRightUnit'] ?? 'px');

    $contentLayout = ($s['contentLayout'] ?? '') ?: 'column';
    if ($contentLayout && $contentLayout !== 'block') {
        $innerStyles[] = 'display: flex';
        $innerStyles[] = 'flex-wrap: wrap';
        $innerStyles[] = 'flex-direction: ' . ($contentLayout === 'row' ? 'row' : 'column');
        $gw = intval($s['gapWidth']  ?? 0);
        $gh = intval($s['gapHeight'] ?? 0);
        if ($gw > 0 || $gh > 0) $innerStyles[] = 'gap: ' . $gh . 'px ' . $gw . 'px';
        if ($contentLayout === 'row') {
            if (!empty($s['contentAlignH'])) $innerStyles[] = 'justify-content: ' . $s['contentAlignH'];
            if (!empty($s['contentAlignV'])) $innerStyles[] = 'align-items: '     . $s['contentAlignV'];
        } else {
            if (!empty($s['contentAlignV'])) $innerStyles[] = 'justify-content: ' . $s['contentAlignV'];
            // align-items for column mode is emitted to $colCss below with !important
            // so that @media responsive overrides can take effect
        }
    } elseif ($contentLayout === 'block') {
        $innerStyles[] = 'display: block';
    }

    if (!empty($s['textColor']))   $innerStyles[] = 'color: '            . $s['textColor'];

    $hexToRgba = function($hex, $opacity) {
        if (empty($hex) || $hex === 'transparent') return 'transparent';
        if (strpos($hex, 'rgba') !== false) return $hex;
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return "rgba($r, $g, $b, $opacity)";
    };

    // Background Logic — bgType determines which layer is active
    $bgType = $s['bgType'] ?? 'color';

    // BG Color — only when bgType is 'color'
    if ($bgType === 'color' && !empty($s['bgColor'])) {
        $innerStyles[] = "background-color: " . $hexToRgba($s['bgColor'], $s['bgColorOpacity'] ?? 1);
    }

    $bgImages = [];
    // Gradient — only when bgType is 'gradient'
    if ($bgType === 'gradient' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor'])) {
        $gType = $s['bgGradientType'] ?? 'linear';
        $angle = $s['bgGradientAngle'] ?? 180;
        $start = $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? 1);
        $end   = $hexToRgba($s['bgGradientEndColor'],   $s['bgGradientEndOpacity']   ?? 1);
        $startPos = $s['bgGradientStartPosition'] ?? 0;
        $endPos = $s['bgGradientEndPosition'] ?? 100;
        if ($gType === 'linear') {
            $bgImages[] = "linear-gradient({$angle}deg, {$start} {$startPos}%, {$end} {$endPos}%)";
        } else {
            $bgImages[] = "radial-gradient(circle at center, {$start} {$startPos}%, {$end} {$endPos}%)";
        }
    }

    // BG Image — works when bgType is 'image' or 'gradient' (not 'color')
    $colBgLazyUrl = null;
    if ($bgType !== 'color' && !empty($s['bgImage'])) {
        $innerStyles[] = "background-position: " . ($s['bgImagePosition'] ?? 'center center');
        $innerStyles[] = "background-repeat: " . ($s['bgImageRepeat'] ?? 'no-repeat');
        $innerStyles[] = "background-size: " . ($s['bgImageSize'] ?? 'cover');
        $innerStyles[] = "background-attachment: " . (($s['bgImageParallax'] ?? 'none') === 'fixed' ? 'fixed' : 'scroll');
        if (!empty($s['bgImageBlendMode']) && $s['bgImageBlendMode'] !== 'normal') {
            $innerStyles[] = "background-blend-mode: {$s['bgImageBlendMode']}";
        }
        if (!empty($s['bgImageSkipLazy'])) {
            // Skip lazy loading — apply immediately via inline CSS
            $bgImages[] = "url('{$s['bgImage']}')";
        } else {
            // Lazy load — JS IntersectionObserver will apply when element enters viewport
            $colBgLazyUrl = "url('{$s['bgImage']}')";
        }
    }

    if (!empty($bgImages)) {
        $innerStyles[] = "background-image: " . implode(', ', $bgImages);
    }
    if (isset($s['fontSize']) && $s['fontSize'] !== '') $innerStyles[] = 'font-size: ' . $s['fontSize'] . ($s['fontSizeUnit'] ?? 'px');
    if (!empty($s['fontWeight'])) $innerStyles[] = 'font-weight: '           . $s['fontWeight'];
    if (!empty($s['lineHeight'])) $innerStyles[] = 'line-height: '           . $s['lineHeight'];
    if (isset($s['letterSpacing']) && $s['letterSpacing'] !== '') $innerStyles[] = 'letter-spacing: ' . $s['letterSpacing'] . ($s['letterSpacingUnit'] ?? 'px');
    if (!empty($s['textAlign']))  $innerStyles[] = 'text-align: '             . $s['textAlign'];

    foreach (['Top', 'Right', 'Bottom', 'Left'] as $side) {
        $val = intval($s['borderSize' . $side] ?? 0);
        if ($val > 0) {
            $innerStyles[] = 'border-' . strtolower($side) . ': ' . $val . 'px solid ' . ($s['borderColor'] ?? '#000000');
        }
    }
    foreach (['TopLeft' => 'top-left', 'TopRight' => 'top-right', 'BottomRight' => 'bottom-right', 'BottomLeft' => 'bottom-left'] as $k => $css) {
        $val = $s['borderRadius' . $k] ?? null;
        if ($val !== null && $val !== '') $innerStyles[] = 'border-' . $css . '-radius: ' . $val . ($s['borderRadius' . $k . 'Unit'] ?? 'px');
    }
    if (!empty($s['boxShadow'])) {
        $inset = ($s['boxShadowStyle'] ?? '') === 'inner' ? 'inset ' : '';
        $innerStyles[] = 'box-shadow: ' . $inset
            . intval($s['boxShadowPositionHorizontal'] ?? 0) . 'px '
            . intval($s['boxShadowPositionVertical']   ?? 0) . 'px '
            . intval($s['boxShadowBlurRadius']         ?? 0) . 'px '
            . intval($s['boxShadowSpreadRadius']       ?? 0) . 'px '
            . ($s['boxShadowColor'] ?? '#000000');
    }
    if (!empty($s['zIndex']))   $outerStyles[] = 'z-index: ' . $s['zIndex'];
    if (!empty($s['overflow']) && $s['overflow'] !== 'default') $innerStyles[] = 'overflow: ' . $s['overflow'];
    if (!empty($s['maxHeight'])) $innerStyles[] = 'max-height: ' . $s['maxHeight'];

    // Per-column responsive CSS for default-alignment columns
    $colCss = '';
    if ($isDefaultAlign) {
        $cs       = $container['settings'] ?? [];
        $colBp    = (int) get_cms_option('theme_small_screen_breakpoint', '800');
        $colBpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
        $colBpSm1 = $colBp + 1;

        $getColAlign = function(string $device) use ($cs): string {
            if ($device === 'mobile') {
                if (isset($cs['alignItems_mobile']) && $cs['alignItems_mobile'] !== '') return $cs['alignItems_mobile'];
                if (isset($cs['alignItems_tablet']) && $cs['alignItems_tablet'] !== '') return $cs['alignItems_tablet'];
            } elseif ($device === 'tablet') {
                if (isset($cs['alignItems_tablet']) && $cs['alignItems_tablet'] !== '') return $cs['alignItems_tablet'];
            }
            return $cs['alignItems'] ?? 'stretch';
        };

        $hasColAlignOvr = function(string $device) use ($cs): bool {
            if ($device === 'tablet') return isset($cs['alignItems_tablet']) && $cs['alignItems_tablet'] !== '';
            return (isset($cs['alignItems_mobile']) && $cs['alignItems_mobile'] !== '')
                || (isset($cs['alignItems_tablet']) && $cs['alignItems_tablet'] !== '');
        };

        $buildColRules = function(string $align) use ($s, $isEmpty): array {
            $stretch = ($align === 'stretch');
            return [
                'outer' => [
                    "align-self:{$align}!important",
                    "flex-grow:" . ($stretch ? '1' : ($s['flexGrow'] ?? '0')) . "!important",
                    "min-height:" . ($stretch ? ($isEmpty ? '100px' : 'auto') : 'auto') . "!important",
                ],
                'inner' => [
                    "min-height:" . ($stretch ? 'auto' : '8px') . "!important",
                    "flex:" . ($stretch ? '1 1 auto' : '0 1 auto') . "!important",
                    "flex-grow:" . ($stretch ? '1' : '0') . "!important",
                ],
            ];
        };

        $innerSel = ".{$colCid}>.lazy-column-inner,.{$colCid}>a>.lazy-column-inner";
        $dRules = $buildColRules($containerAlign);
        $colCss .= ".{$colCid}{" . implode(';', $dRules['outer']) . "}";
        $colCss .= "{$innerSel}{" . implode(';', $dRules['inner']) . "}";

        if ($hasColAlignOvr('tablet')) {
            $tRules = $buildColRules($getColAlign('tablet'));
            $colCss .= "@media(min-width:{$colBpSm1}px) and (max-width:{$colBpMed}px){";
            $colCss .= ".{$colCid}{" . implode(';', $tRules['outer']) . "}";
            $colCss .= "{$innerSel}{" . implode(';', $tRules['inner']) . "}";
            $colCss .= "}";
        }

        if ($hasColAlignOvr('mobile')) {
            $mRules = $buildColRules($getColAlign('mobile'));
            $colCss .= "@media(max-width:{$colBp}px){";
            $colCss .= ".{$colCid}{" . implode(';', $mRules['outer']) . "}";
            $colCss .= "{$innerSel}{" . implode(';', $mRules['inner']) . "}";
            $colCss .= "}";
        }
    }

    // Per-column responsive overrides: alignment, contentAlignH, contentAlignV
    $rBp    = (int) get_cms_option('theme_small_screen_breakpoint', '800');
    $rBpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $rBpSm1 = $rBp + 1;
    $rInnerSel = ".{$colCid}>.lazy-column-inner,.{$colCid}>a>.lazy-column-inner";

    // Base column-mode align-items in stylesheet so @media rules below can override it
    if ($contentLayout !== 'row' && $contentLayout !== 'block' && !empty($s['contentAlignH'])) {
        $colCss .= "{$rInnerSel}{align-items:{$s['contentAlignH']}!important}";
        // Explicit tablet rule so theme tablet CSS can't override per-column alignment
        $tabAlignH = (isset($s['contentAlignH_tablet']) && $s['contentAlignH_tablet'] !== '')
            ? $s['contentAlignH_tablet']
            : $s['contentAlignH'];
        $colCss .= "@media(min-width:{$rBpSm1}px) and (max-width:{$rBpMed}px){{$rInnerSel}{align-items:{$tabAlignH}!important}}";
    }

    $getColRespOvr = function(string $prop, string $dev) use ($s): ?string {
        if ($dev === 'mobile') {
            if (isset($s[$prop . '_mobile']) && $s[$prop . '_mobile'] !== '') return (string)$s[$prop . '_mobile'];
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        } elseif ($dev === 'tablet') {
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string)$s[$prop . '_tablet'];
        }
        return null;
    };
    foreach ([['tablet', "@media(min-width:{$rBpSm1}px) and (max-width:{$rBpMed}px)"], ['mobile', "@media(max-width:{$rBp}px)"]] as [$rDev, $rMq]) {
        $rOuter = []; $rInner = [];
        $rAlign = $getColRespOvr('alignment', $rDev);
        if ($rAlign && $rAlign !== 'default') {
            $rOuter[] = "align-self:{$rAlign}!important";
            $rStretch = ($rAlign === 'stretch');
            $rOuter[] = 'flex-grow:' . ($rStretch ? '1' : '0') . '!important';
            $rInner[] = 'flex:' . ($rStretch ? '1 1 auto' : '0 1 auto') . '!important';
            $rInner[] = 'flex-grow:' . ($rStretch ? '1' : '0') . '!important';
        }
        $rAlignH = $getColRespOvr('contentAlignH', $rDev);
        $rAlignV = $getColRespOvr('contentAlignV', $rDev);
        if ($rAlignH || $rAlignV) {
            $rCl = $contentLayout ?: 'column';
            if ($rCl === 'row') {
                if ($rAlignH) $rInner[] = "justify-content:{$rAlignH}!important";
                if ($rAlignV) $rInner[] = "align-items:{$rAlignV}!important";
            } else {
                if ($rAlignV) $rInner[] = "justify-content:{$rAlignV}!important";
                if ($rAlignH) $rInner[] = "align-items:{$rAlignH}!important";
            }
        }
        // Responsive margin (outer element) — !important needed to override inline styles
        foreach (['marginTop', 'marginBottom'] as $mProp) {
            $mVal = $getColRespOvr($mProp, $rDev);
            if ($mVal !== null) {
                $mUnit = $getColRespOvr($mProp . 'Unit', $rDev) ?? ($s[$mProp . 'Unit'] ?? 'px');
                $rOuter[] = strtolower(preg_replace('/([A-Z])/', '-$1', $mProp)) . ':' . $mVal . $mUnit . '!important';
            }
        }
        // Responsive padding + marginLeft/Right (inner element) — !important needed to override inline styles
        foreach (['paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight', 'marginLeft', 'marginRight'] as $pProp) {
            $pVal = $getColRespOvr($pProp, $rDev);
            if ($pVal !== null) {
                $pUnit = $getColRespOvr($pProp . 'Unit', $rDev) ?? ($s[$pProp . 'Unit'] ?? 'px');
                $rInner[] = strtolower(preg_replace('/([A-Z])/', '-$1', $pProp)) . ':' . $pVal . $pUnit . '!important';
            }
        }
        // Responsive background color (only when bgType is 'color')
        if ($bgType === 'color') {
            $rBgColor   = $getColRespOvr('bgColor', $rDev);
            $rBgOpacity = $getColRespOvr('bgColorOpacity', $rDev);
            if ($rBgColor !== null || $rBgOpacity !== null) {
                $effColor   = $rBgColor   ?? ($s['bgColor']   ?? '');
                $effOpacity = $rBgOpacity !== null ? (float)$rBgOpacity : ($s['bgColorOpacity'] ?? 1);
                if ($effColor) $rInner[] = 'background-color:' . $hexToRgba($effColor, $effOpacity) . '!important';
            }
        }
        // Responsive background image properties (position, repeat, size, blend)
        if ($bgType !== 'color') {
            foreach ([
                ['bgImagePosition', 'background-position'],
                ['bgImageRepeat',   'background-repeat'],
                ['bgImageSize',     'background-size'],
                ['bgImageBlendMode','background-blend-mode'],
            ] as [$bgProp, $bgCss]) {
                $bgVal = $getColRespOvr($bgProp, $rDev);
                if ($bgVal !== null) $rInner[] = "{$bgCss}:{$bgVal}!important";
            }
            // Responsive background image URL (bgImage_tablet / bgImage_mobile)
            $rBgImg = $getColRespOvr('bgImage', $rDev);
            if ($rBgImg !== null) {
                $rBgImgParts = [];
                // Preserve gradient overlay if bgType is 'gradient'
                if ($bgType === 'gradient' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor'])) {
                    $gType = $s['bgGradientType'] ?? 'linear';
                    $gAng  = $s['bgGradientAngle'] ?? 180;
                    $gS    = $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? 1);
                    $gE    = $hexToRgba($s['bgGradientEndColor'],   $s['bgGradientEndOpacity']   ?? 1);
                    $gSP   = $s['bgGradientStartPosition'] ?? 0;
                    $gEP   = $s['bgGradientEndPosition']   ?? 100;
                    $rBgImgParts[] = $gType === 'linear'
                        ? "linear-gradient({$gAng}deg, {$gS} {$gSP}%, {$gE} {$gEP}%)"
                        : "radial-gradient(circle at center, {$gS} {$gSP}%, {$gE} {$gEP}%)";
                }
                $rBgImgParts[] = "url('{$rBgImg}')";
                $rInner[] = 'background-image:' . implode(', ', $rBgImgParts) . '!important';
            }
        }
        // Responsive border size (inner)
        foreach (['Top' => 'top', 'Right' => 'right', 'Bottom' => 'bottom', 'Left' => 'left'] as $bSide => $bCss) {
            $bVal = $getColRespOvr('borderSize' . $bSide, $rDev);
            if ($bVal !== null) {
                $rInner[] = "border-{$bCss}-width:{$bVal}px!important";
                $rInner[] = "border-style:solid!important";
            }
        }
        // Responsive border color (inner)
        $rBorderColor = $getColRespOvr('borderColor', $rDev);
        if ($rBorderColor !== null) $rInner[] = "border-color:{$rBorderColor}!important";
        // Responsive border radius (inner)
        foreach ([
            'TopLeft'     => 'top-left',
            'TopRight'    => 'top-right',
            'BottomRight' => 'bottom-right',
            'BottomLeft'  => 'bottom-left',
        ] as $rKey => $rCss) {
            $rRad = $getColRespOvr('borderRadius' . $rKey, $rDev);
            if ($rRad !== null) {
                $rUnit = $getColRespOvr('borderRadius' . $rKey . 'Unit', $rDev) ?? ($s['borderRadius' . $rKey . 'Unit'] ?? 'px');
                $rInner[] = "border-{$rCss}-radius:{$rRad}{$rUnit}!important";
            }
        }
        // Responsive box shadow — boolean needs direct check (false !== '' in PHP but (string)false === '')
        $bsShadowOvr = null;
        if ($rDev === 'mobile') {
            if (isset($s['boxShadow_mobile'])) $bsShadowOvr = $s['boxShadow_mobile'];
            elseif (isset($s['boxShadow_tablet'])) $bsShadowOvr = $s['boxShadow_tablet'];
        } elseif ($rDev === 'tablet') {
            if (isset($s['boxShadow_tablet'])) $bsShadowOvr = $s['boxShadow_tablet'];
        }
        if ($bsShadowOvr !== null) {
            if ($bsShadowOvr) {
                $bsH  = intval($getColRespOvr('boxShadowPositionHorizontal', $rDev) ?? ($s['boxShadowPositionHorizontal'] ?? 0));
                $bsV  = intval($getColRespOvr('boxShadowPositionVertical',   $rDev) ?? ($s['boxShadowPositionVertical']   ?? 0));
                $bsBl = intval($getColRespOvr('boxShadowBlurRadius',         $rDev) ?? ($s['boxShadowBlurRadius']         ?? 0));
                $bsSp = intval($getColRespOvr('boxShadowSpreadRadius',       $rDev) ?? ($s['boxShadowSpreadRadius']       ?? 0));
                $bsC  = $getColRespOvr('boxShadowColor', $rDev) ?? ($s['boxShadowColor'] ?? '#000000');
                $bsIn = ($getColRespOvr('boxShadowStyle', $rDev) ?? ($s['boxShadowStyle'] ?? '')) === 'inner' ? 'inset ' : '';
                $rInner[] = "box-shadow:{$bsIn}{$bsH}px {$bsV}px {$bsBl}px {$bsSp}px {$bsC}!important";
            } else {
                $rInner[] = "box-shadow:none!important";
            }
        } elseif (!empty($s['boxShadow'])) {
            $bsSubChanged = false;
            foreach (['boxShadowPositionHorizontal', 'boxShadowPositionVertical', 'boxShadowBlurRadius', 'boxShadowSpreadRadius', 'boxShadowColor', 'boxShadowStyle'] as $bsSub) {
                if ($getColRespOvr($bsSub, $rDev) !== null) { $bsSubChanged = true; break; }
            }
            if ($bsSubChanged) {
                $bsH  = intval($getColRespOvr('boxShadowPositionHorizontal', $rDev) ?? ($s['boxShadowPositionHorizontal'] ?? 0));
                $bsV  = intval($getColRespOvr('boxShadowPositionVertical',   $rDev) ?? ($s['boxShadowPositionVertical']   ?? 0));
                $bsBl = intval($getColRespOvr('boxShadowBlurRadius',         $rDev) ?? ($s['boxShadowBlurRadius']         ?? 0));
                $bsSp = intval($getColRespOvr('boxShadowSpreadRadius',       $rDev) ?? ($s['boxShadowSpreadRadius']       ?? 0));
                $bsC  = $getColRespOvr('boxShadowColor', $rDev) ?? ($s['boxShadowColor'] ?? '#000000');
                $bsIn = ($getColRespOvr('boxShadowStyle', $rDev) ?? ($s['boxShadowStyle'] ?? '')) === 'inner' ? 'inset ' : '';
                $rInner[] = "box-shadow:{$bsIn}{$bsH}px {$bsV}px {$bsBl}px {$bsSp}px {$bsC}!important";
            }
        }
        // Responsive z-index (outer)
        $rZIdx = $getColRespOvr('zIndex', $rDev);
        if ($rZIdx !== null) $rOuter[] = "z-index:{$rZIdx}!important";
        // Responsive overflow (inner)
        $rOvf = $getColRespOvr('overflow', $rDev);
        if ($rOvf !== null && $rOvf !== 'default') $rInner[] = "overflow:{$rOvf}!important";

        if ($rOuter || $rInner) {
            $colCss .= "{$rMq}{";
            if ($rOuter) $colCss .= ".{$colCid}{" . implode(';', $rOuter) . "}";
            if ($rInner) $colCss .= "{$rInnerSel}{" . implode(';', $rInner) . "}";
            $colCss .= "}";
        }
    }

    // Sticky column CSS
    if (!empty($s['sticky'])) {
        $stickyOffset  = isset($s['stickyOffset'])  ? (int) $s['stickyOffset']  : 0;
        $stickyZIndex  = isset($s['stickyZIndex'])  ? (int) $s['stickyZIndex']  : 99;
        $stickyDesktop = $s['stickyDesktop'] ?? true;
        $stickyTablet  = $s['stickyTablet']  ?? true;
        $stickyMobile  = $s['stickyMobile']  ?? true;
        $sOn  = "position:sticky;top:{$stickyOffset}px;z-index:{$stickyZIndex};";
        $sOff = "position:static!important;top:auto!important;z-index:auto!important;";
        $colBp    = (int) get_cms_option('theme_small_screen_breakpoint', '800');
        $colBpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
        $colBpSm1 = $colBp + 1;
        if ($stickyDesktop) $colCss .= ".{$colCid}{{$sOn}}";
        if ($stickyTablet !== $stickyDesktop) {
            $rule = $stickyTablet ? str_replace(';', '!important;', $sOn) : $sOff;
            $colCss .= "@media(min-width:{$colBpSm1}px) and (max-width:{$colBpMed}px){.{$colCid}{{$rule}}}";
        }
        if ($stickyMobile !== $stickyTablet) {
            $rule = $stickyMobile ? str_replace(';', '!important;', $sOn) : $sOff;
            $colCss .= "@media(max-width:{$colBp}px){.{$colCid}{{$rule}}}";
        }
        $stickyBgColor   = $s['stickyBgColor']        ?? '';
        $stickyBgOpacity = (float)($s['stickyBgColorOpacity'] ?? 1);
        if ($stickyBgColor !== '') {
            $stickyBgRgba = $hexToRgba($stickyBgColor, $stickyBgOpacity);
            $colCss .= ".{$colCid}{transition:background-color 0.3s ease;}";
            $colCss .= ".lazy-sticky-active.{$colCid}{background-color:{$stickyBgRgba}!important;}";
        }
    }

    // Responsive width overrides (per-column)
    $basisTablet = $column['basis_tablet'] ?? null;
    $basisMobile = $column['basis_mobile'] ?? null;
    if ($basisTablet !== null && $basisTablet !== '') {
        $gapSub = $totalCols > 1 ? ($gapVal * ($totalCols - 1)) / $totalCols : 0;
        if ($basisTablet === 'auto') { $tabFb = 'auto'; $tabMw = 'none'; }
        elseif (strpos((string)$basisTablet, '%') !== false) { $tabFb = $totalCols > 1 ? "calc({$basisTablet} - {$gapSub}px)" : $basisTablet; $tabMw = $tabFb; }
        else { $tabFb = $totalCols > 1 ? "calc({$basisTablet}% - {$gapSub}px)" : "{$basisTablet}%"; $tabMw = $tabFb; }
        $colCss .= "@media(min-width:{$rBpSm1}px) and (max-width:{$rBpMed}px){.{$colCid}{flex-basis:{$tabFb}!important;max-width:{$tabMw}!important;width:{$tabFb}!important}}";
    }
    if ($basisMobile !== null && $basisMobile !== '') {
        $gapSub = $totalCols > 1 ? ($gapVal * ($totalCols - 1)) / $totalCols : 0;
        if ($basisMobile === 'auto') { $mobFb = 'auto'; $mobMw = 'none'; }
        elseif (strpos((string)$basisMobile, '%') !== false) { $mobFb = $totalCols > 1 ? "calc({$basisMobile} - {$gapSub}px)" : $basisMobile; $mobMw = $mobFb; }
        else { $mobFb = $totalCols > 1 ? "calc({$basisMobile}% - {$gapSub}px)" : "{$basisMobile}%"; $mobMw = $mobFb; }
        $colCss .= "@media(max-width:{$rBp}px){.lazy-column.{$colCid}{flex-basis:{$mobFb}!important;max-width:{$mobMw}!important;width:{$mobFb}!important}}";
    }

    $htmlTag = in_array($s['htmlTag'] ?? 'div', ['div','section','article','aside','main','header','footer','nav','span']) ? ($s['htmlTag'] ?? 'div') : 'div';
    $link = !empty($s['linkUrl']) ? $s['linkUrl'] : null;
@endphp

@php
    $hoverClass = (!empty($s['hoverType']) && $s['hoverType'] !== 'none') ? 'hover-effect-' . $s['hoverType'] : '';
@endphp

{!! $colCss ? '<style>' . $colCss . '</style>' : '' !!}

@if(!empty($s['sticky']))
@once('lazy-sticky-observer-js')
<script>
(function(){
    function initLazyStickyObservers(){
        document.querySelectorAll('.lazy-sticky-col:not([data-sticky-init])').forEach(function(el){
            el.dataset.stickyInit='1';
            var cs=getComputedStyle(el);
            if(cs.position!=='sticky'&&cs.position!=='-webkit-sticky')return;
            var top=parseInt(cs.top)||0;
            new IntersectionObserver(function(entries){
                el.classList.toggle('lazy-sticky-active',entries[0].intersectionRatio<1);
            },{threshold:[1],rootMargin:'-'+(top+1)+'px 0px 0px 0px'}).observe(el);
        });
    }
    document.readyState==='loading'
        ?document.addEventListener('DOMContentLoaded',initLazyStickyObservers)
        :initLazyStickyObservers();
})();
</script>
@endonce
@endif

@php
    $colVisCondition = $s['vis_condition'] ?? '';
    $colShowByCondition = true;
    if ($colVisCondition === 'logged_in') {
        $colShowByCondition = auth()->check();
    } elseif ($colVisCondition === 'logged_out') {
        $colShowByCondition = !auth()->check();
    } elseif ($colVisCondition === 'schedule') {
        $colNow = time();
        $colFrom = !empty($s['vis_date_from']) ? strtotime($s['vis_date_from']) : null;
        $colTo   = !empty($s['vis_date_to'])   ? strtotime($s['vis_date_to'])   : null;
        $colShowByCondition = (!$colFrom || $colNow >= $colFrom) && (!$colTo || $colNow <= $colTo);
    }
    $colAnimType = $s['anim_type'] ?? '';
@endphp
@if($colShowByCondition)
<{{ $htmlTag }} class="lazy-column {{ $colCid }} {{ $hoverClass }} {{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }} {{ !empty($s['sticky']) ? 'lazy-sticky-col' : '' }}{{ $colAnimType ? ' lz-anim lz-anim-' . $colAnimType : '' }}"
    @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
    style="{{ implode('; ', $outerStyles) }}"@if($colAnimType) data-anim-duration="{{ $s['anim_duration'] ?? 600 }}" data-anim-delay="{{ $s['anim_delay'] ?? 0 }}" data-anim-easing="{{ $s['anim_easing'] ?? 'ease' }}"@endif>
    
    @if($link)
        <a href="{{ $link }}" target="{{ $s['linkTarget'] ?? '_self' }}" style="text-decoration: none; color: inherit; display: flex !important; flex-direction: column !important; flex-grow: 1 !important; height: 100% !important; width: 100%;">
    @endif

    @if($colBgLazyUrl)
    <script>if(!window.__lzBgObserverInit){window.__lzBgObserverInit=1;document.addEventListener('DOMContentLoaded',function(){var f=function(el){var u=el.getAttribute('data-bg');if(!u)return;var p=el.style.backgroundImage;el.style.backgroundImage=p?u+', '+p:u;el.removeAttribute('data-bg');el.classList.remove('lz-lazy-bg');};if(!('IntersectionObserver'in window)){document.querySelectorAll('.lz-lazy-bg').forEach(f);return;}var o=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){f(e.target);o.unobserve(e.target);}});},{rootMargin:'200px 0px'});document.querySelectorAll('.lz-lazy-bg').forEach(function(el){o.observe(el);});});}</script>
    @endif
    <div class="lazy-column-inner{{ $colBgLazyUrl ? ' lz-lazy-bg' : '' }}" style="{{ implode('; ', $innerStyles) }}"@if($colBgLazyUrl) data-bg="{{ $colBgLazyUrl }}"@endif>
        @if(!empty($column['elements']))
            @php $__customBuilderDefs = apply_lazy_filters('lazy_builder_elements', []); @endphp
            @foreach($column['elements'] as $el)
                @if($el['type'] === 'row')
                    @if($contentLayout === 'row')
                        <div style="flex-basis: 100%; width: 100%; height: 0; overflow: hidden;"></div>
                    @endif
                    @include('falcon-cms::frontend.builder.container', ['container' => $el])
                    @if($contentLayout === 'row')
                        <div style="flex-basis: 100%; width: 100%; height: 0; overflow: hidden;"></div>
                    @endif
                @else
                    @php
                        $__elType    = $el['type'];
                        $__viewBase  = 'falcon-cms::frontend.builder.elements.';
                        $__viewExact = $__viewBase . $__elType;
                        $__viewDash  = $__viewBase . str_replace('_', '-', $__elType);
                        $__elView    = \Illuminate\Support\Facades\View::exists($__viewExact) ? $__viewExact
                                     : (\Illuminate\Support\Facades\View::exists($__viewDash) ? $__viewDash : null);
                        $__customDef = $__customBuilderDefs[$__elType] ?? null;
                    @endphp
                    @php
                        $__elVisCondition = $el['settings']['vis_condition'] ?? '';
                        $__elShow = true;
                        if ($__elVisCondition === 'logged_in') {
                            $__elShow = auth()->check();
                        } elseif ($__elVisCondition === 'logged_out') {
                            $__elShow = !auth()->check();
                        } elseif ($__elVisCondition === 'schedule') {
                            $__elNow  = time();
                            $__elFrom = !empty($el['settings']['vis_date_from']) ? strtotime($el['settings']['vis_date_from']) : null;
                            $__elTo   = !empty($el['settings']['vis_date_to'])   ? strtotime($el['settings']['vis_date_to'])   : null;
                            $__elShow = (!$__elFrom || $__elNow >= $__elFrom) && (!$__elTo || $__elNow <= $__elTo);
                        }
                    @endphp
                    @if($__elView && $__elShow)
                        @php
                            if (!empty($el['settings']) && function_exists('lazy_resolve_tokens_in_settings')) {
                                $el['settings'] = lazy_resolve_tokens_in_settings($el['settings'], $post ?? null);
                            }
                            $__elAnimType = $el['settings']['anim_type'] ?? '';
                        @endphp
                        @if($__elAnimType)
                            <div class="lz-anim lz-anim-{{ $__elAnimType }}" style="width:100%" data-anim-duration="{{ $el['settings']['anim_duration'] ?? 600 }}" data-anim-delay="{{ $el['settings']['anim_delay'] ?? 0 }}" data-anim-easing="{{ $el['settings']['anim_easing'] ?? 'ease' }}">
                                @include($__elView, ['el' => $el])
                            </div>
                        @else
                            @include($__elView, ['el' => $el])
                        @endif
                    @elseif($__customDef && $__elShow)
                        @php
                            // Resolve dynamic-source fields into final values for both template + generic renderer
                            $el['settings'] = lazy_apply_custom_dynamic($el['settings'] ?? [], $post ?? null);

                            $__tpl        = !empty($__customDef['template'])
                                         && \Illuminate\Support\Facades\View::exists($__customDef['template'])
                                          ? $__customDef['template'] : null;
                            $__elAnimType = $el['settings']['anim_type'] ?? '';
                            // Master wrapper attributes
                            $__ceV   = $el['settings']['visibility'] ?? ['mobile'=>true,'tablet'=>true,'desktop'=>true];
                            $__ceVis = '';
                            if (($__ceV['mobile']  ?? true) === false) $__ceVis .= ' lazy-hide-mobile';
                            if (($__ceV['tablet']  ?? true) === false) $__ceVis .= ' lazy-hide-tablet';
                            if (($__ceV['desktop'] ?? true) === false) $__ceVis .= ' lazy-hide-desktop';
                            $__ceCssClass = $el['settings']['cssClass'] ?? '';
                            $__ceCssId    = $el['settings']['cssId']    ?? '';
                        @endphp
                        @if($__elAnimType)
                            <div class="lz-anim lz-anim-{{ $__elAnimType }}" style="width:100%"
                                 data-anim-duration="{{ $el['settings']['anim_duration'] ?? 600 }}"
                                 data-anim-delay="{{ $el['settings']['anim_delay'] ?? 0 }}"
                                 data-anim-easing="{{ $el['settings']['anim_easing'] ?? 'ease' }}">
                        @endif
                        {{-- Master wrapper: builder handles visibility, class, id here --}}
                        <div class="lazy-builder-element lazy-custom-block w-full{{ $__ceVis ? ' '.$__ceVis : '' }}{{ $__ceCssClass ? ' '.$__ceCssClass : '' }}"
                             @if($__ceCssId) id="{{ $__ceCssId }}" @endif>
                            @if($__tpl)
                                @include($__tpl, ['el' => $el, 's' => $el['settings'] ?? []])
                            @else
                                @include('falcon-cms::frontend.builder.elements.custom', ['el' => $el, 'customDef' => $__customDef])
                            @endif
                        </div>
                        @if($__elAnimType)
                            </div>
                        @endif
                    @endif
                @endif
            @endforeach
        @endif
    </div>

    @if($link)
        </a>
    @endif
</{{ $htmlTag }}>
@endif
