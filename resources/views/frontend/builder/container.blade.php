@php
    $s = $container['settings'] ?? [];
    $heightMode = $s['height'] ?? 'auto';
    
    $containerStyles = ['width: 100%'];
    if (!empty($cardStretch ?? false)) $containerStyles[] = 'flex-grow: 1';

    $hexToRgba = function($hex, $opacity) {
        if (empty($hex) || $hex === 'transparent') return 'transparent';
        if (strpos($hex, 'rgba') !== false) return $hex;
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) {
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

    $bgType = $s['bgType'] ?? 'color';

    // Gradient and/or image as layered background-image (gradient on top, image below)
    // These have no responsive controls, so they remain inline.
    $bgImages = [];
    if ($bgType === 'gradient' && !empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor'])) {
        $gType    = $s['bgGradientType'] ?? 'linear';
        $angle    = $s['bgGradientAngle'] ?? 180;
        $startPos = $s['bgGradientStartPosition'] ?? 0;
        $endPos   = $s['bgGradientEndPosition']   ?? 100;
        $start    = $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? $s['bgColorOpacity'] ?? 1);
        $end      = $hexToRgba($s['bgGradientEndColor'],   $s['bgGradientEndOpacity']   ?? $s['bgColorOpacity'] ?? 1);
        if ($gType === 'linear') {
            $bgImages[] = "linear-gradient({$angle}deg, {$start} {$startPos}%, {$end} {$endPos}%)";
        } else {
            $bgImages[] = "radial-gradient(circle at center, {$start} {$startPos}%, {$end} {$endPos}%)";
        }
    }
    $ctnBgLazyUrl = null;
    if ($bgType !== 'color' && !empty($s['bgImage'])) {
        // background-attachment has no responsive control — keep inline
        $containerStyles[] = "background-attachment: " . (($s['bgImageParallax'] ?? 'none') === 'fixed' ? 'fixed' : 'scroll');
        if (!empty($s['bgImageSkipLazy'])) {
            // Skip lazy loading — apply immediately via inline CSS
            $bgImages[] = "url('{$s['bgImage']}')";
        } else {
            // Lazy load — JS IntersectionObserver will apply when element enters viewport
            $ctnBgLazyUrl = "url('{$s['bgImage']}')";
        }
    }
    if (!empty($bgImages)) {
        $containerStyles[] = "background-image: " . implode(', ', $bgImages);
    }
    
    // Padding moved to <style> block for responsive support (like margin)

    // Borders
    if (isset($s['borderSizeTop'])) $containerStyles[] = "border-top: {$s['borderSizeTop']}px solid " . ($s['borderColor'] ?? '#000');
    if (isset($s['borderSizeRight'])) $containerStyles[] = "border-right: {$s['borderSizeRight']}px solid " . ($s['borderColor'] ?? '#000');
    if (isset($s['borderSizeBottom'])) $containerStyles[] = "border-bottom: {$s['borderSizeBottom']}px solid " . ($s['borderColor'] ?? '#000');
    if (isset($s['borderSizeLeft'])) $containerStyles[] = "border-left: {$s['borderSizeLeft']}px solid " . ($s['borderColor'] ?? '#000');
    
    // Border Radius
    if (isset($s['borderRadiusTopLeft'])) $containerStyles[] = "border-top-left-radius: {$s['borderRadiusTopLeft']}" . ($s['borderRadiusTopLeftUnit'] ?? 'px');
    if (isset($s['borderRadiusTopRight'])) $containerStyles[] = "border-top-right-radius: {$s['borderRadiusTopRight']}" . ($s['borderRadiusTopRightUnit'] ?? 'px');
    if (isset($s['borderRadiusBottomRight'])) $containerStyles[] = "border-bottom-right-radius: {$s['borderRadiusBottomRight']}" . ($s['borderRadiusBottomRightUnit'] ?? 'px');
    if (isset($s['borderRadiusBottomLeft'])) $containerStyles[] = "border-bottom-left-radius: {$s['borderRadiusBottomLeft']}" . ($s['borderRadiusBottomLeftUnit'] ?? 'px');

    // Flex/Alignment Inner
    $isNestedRow = ($container['type'] ?? 'container') === 'row';

    $hasContent = false;
    foreach ($container['columns'] as $col) {
        if (!empty($col['elements'])) { $hasContent = true; break; }
    }

    // These go into the <style> block (not inline) so media queries can override them
    $justifyContent  = $s['justifyContent'] ?? 'flex-start';
    $alignItems      = $s['alignItems'] ?? 'stretch';
    $flexWrap        = $s['flexWrap'] ?? 'wrap';
    if ($isNestedRow) {
        $alignContentVal = $s['rowAlignContent'] ?? 'flex-start';
    } elseif ($heightMode === 'auto') {
        $alignContentVal = 'flex-start';
    } else {
        $alignContentVal = $s['rowAlignContent'] ?? 'stretch';
    }
    if ($heightMode !== 'auto') {
        $heightVal  = $heightMode === 'full' ? '100vh' : ($s['customHeight'] ?? 'auto');
        $minHeightVal = $heightVal;
        $heightCss  = 'auto';
    } else {
        $minHeightVal = !empty($s['minHeight']) ? $s['minHeight'] : ($hasContent ? '8px' : '100px');
        $heightCss    = 'auto';
    }

    // Only non-responsive properties stay inline.
    // Padding is forced to 0 so builder containers are exactly site-width wide,
    // matching the admin preview and the user's Site Width setting.
    // Builder sections handle their own padding via their settings panel.
    $innerStyles = [
        'display: flex',
        'flex-grow: 1',
        'width: 100%',
        'padding-left: 0',
        'padding-right: 0',
    ];

    $contentWidth = $s['contentWidth'] ?? 'site';
    $innerClass = ($contentWidth === 'site' && !$isNestedRow) ? 'container-custom mx-auto' : 'w-full';
    
    $htmlTag = in_array($s['htmlTag'] ?? 'div', ['div','section','article','aside','main','header','footer','nav']) ? ($s['htmlTag'] ?? 'div') : 'div';
    $status = $s['status'] ?? 'published';

    // Device Visibility
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';
    if (!($v['mobile'] ?? true) && !($v['tablet'] ?? true) && !($v['desktop'] ?? true)) {
        $visibilityClasses = ' falcon-hide-all';
    }
    // Hover Logic
    $hoverClass = (!empty($s['hoverType']) && $s['hoverType'] !== 'none') ? 'hover-effect-' . $s['hoverType'] : '';

    $link = !empty($s['linkUrl']) ? $s['linkUrl'] : null;
    $linkTarget = $s['linkTarget'] ?? '_self';

    // ── Responsive CSS ──────────────────────────────────────────────────────
    $cid = 'lc-' . ($container['id'] ?? str_replace('.', '', uniqid('', true)));
    $bpSmall  = (int) get_cms_option('theme_small_screen_breakpoint', '800');
    $bpMedium = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');

    // Mirror JS getResponsiveVal: mobile → tablet → desktop fallback cascade
    $getResVal = function(string $prop, string $device) use ($s): ?string {
        if ($device === 'mobile') {
            if (isset($s[$prop . '_mobile']) && $s[$prop . '_mobile'] !== '') return (string) $s[$prop . '_mobile'];
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string) $s[$prop . '_tablet'];
        } elseif ($device === 'tablet') {
            if (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '') return (string) $s[$prop . '_tablet'];
        }
        return (isset($s[$prop]) && $s[$prop] !== '') ? (string) $s[$prop] : null;
    };

    // True if there is an explicit override for the given device (considers cascade)
    $hasOvr = function(string $prop, string $device) use ($s): bool {
        if ($device === 'tablet') return isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '';
        // mobile: explicit _mobile OR _tablet (tablet value cascades to mobile)
        return (isset($s[$prop . '_mobile']) && $s[$prop . '_mobile'] !== '')
            || (isset($s[$prop . '_tablet']) && $s[$prop . '_tablet'] !== '');
    };

    // Translate height mode to CSS rules
    $heightRules = function(string $device) use ($s, $getResVal): array {
        $mode = $getResVal('height', $device) ?? 'auto';
        if ($mode !== 'auto') {
            $val = ($mode === 'full') ? '100vh' : ($getResVal('customHeight', $device) ?? 'auto');
            return ["min-height:{$val}", "height:{$val}"];
        }
        return ['min-height:auto', 'height:auto'];
    };

    // Build per-device rule arrays (all overrides use !important to beat desktop base)
    $buildRules = function(string $dev) use ($s, $hasOvr, $getResVal, $heightRules, $isNestedRow, $hexToRgba, $bgType): array {
        $inner = []; $outer = [];
        if ($hasOvr('alignItems', $dev))
            $inner[] = 'align-items:' . $getResVal('alignItems', $dev) . '!important';
        if ($hasOvr('justifyContent', $dev))
            $inner[] = 'justify-content:' . $getResVal('justifyContent', $dev) . '!important';
        if ($hasOvr('rowAlignContent', $dev))
            $inner[] = 'align-content:' . $getResVal('rowAlignContent', $dev) . '!important';
        if ($hasOvr('flexWrap', $dev))
            $inner[] = 'flex-wrap:' . $getResVal('flexWrap', $dev) . '!important';
        if ($hasOvr('height', $dev) || $hasOvr('customHeight', $dev))
            foreach ($heightRules($dev) as $r) $inner[] = $r . '!important';
        if ($isNestedRow && $hasOvr('minHeight', $dev))
            $inner[] = 'min-height:' . $getResVal('minHeight', $dev) . '!important';
        if ($hasOvr('marginTop', $dev) && ($mv = $getResVal('marginTop', $dev)) !== null)
            $outer[] = 'margin-top:' . $mv . ($getResVal('marginTopUnit', $dev) ?? 'px') . '!important';
        if ($hasOvr('marginBottom', $dev) && ($mv = $getResVal('marginBottom', $dev)) !== null)
            $outer[] = 'margin-bottom:' . $mv . ($getResVal('marginBottomUnit', $dev) ?? 'px') . '!important';
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $_side) {
            if ($hasOvr('padding' . $_side, $dev) && ($_pv = $getResVal('padding' . $_side, $dev)) !== null) {
                $_unit = $getResVal('padding' . $_side . 'Unit', $dev) ?? 'px';
                $outer[] = 'padding-' . strtolower($_side) . ':' . $_pv . $_unit . '!important';
            }
        }
        if ($hasOvr('columnGap', $dev) && ($_cgv = $getResVal('columnGap', $dev)) !== null)
            $outer[] = '--lc-col-gap:' . $_cgv . '!important';
        // Background tab responsive properties
        if ($bgType === 'color' && ($hasOvr('bgColor', $dev) || $hasOvr('bgColorOpacity', $dev))) {
            $_col = $getResVal('bgColor', $dev);
            $_opa = $getResVal('bgColorOpacity', $dev) ?? 1;
            if (!empty($_col)) $outer[] = 'background-color:' . $hexToRgba($_col, $_opa) . '!important';
        }
        if ($bgType !== 'color' && $hasOvr('bgImageSize', $dev) && ($_bsv = $getResVal('bgImageSize', $dev)) !== null)
            $outer[] = 'background-size:' . $_bsv . '!important';
        if ($bgType !== 'color' && $hasOvr('bgImagePosition', $dev) && ($_bpv = $getResVal('bgImagePosition', $dev)) !== null)
            $outer[] = 'background-position:' . $_bpv . '!important';
        if ($bgType !== 'color' && $hasOvr('bgImageRepeat', $dev) && ($_brv = $getResVal('bgImageRepeat', $dev)) !== null)
            $outer[] = 'background-repeat:' . $_brv . '!important';
        if ($bgType !== 'color' && $hasOvr('bgImageBlendMode', $dev) && ($_bmv = $getResVal('bgImageBlendMode', $dev)) !== null && $_bmv !== 'normal')
            $outer[] = 'background-blend-mode:' . $_bmv . '!important';
        if ($bgType !== 'color' && $hasOvr('bgImage', $dev)) {
            $_rImg = $getResVal('bgImage', $dev);
            $_bgParts = [];
            if (!empty($s['bgGradientStartColor']) && !empty($s['bgGradientEndColor'])) {
                $_gType = $s['bgGradientType'] ?? 'linear';
                $_gAng  = $s['bgGradientAngle'] ?? 180;
                $_gS    = $hexToRgba($s['bgGradientStartColor'], $s['bgGradientStartOpacity'] ?? $s['bgColorOpacity'] ?? 1);
                $_gE    = $hexToRgba($s['bgGradientEndColor'],   $s['bgGradientEndOpacity']   ?? $s['bgColorOpacity'] ?? 1);
                $_gSP   = $s['bgGradientStartPosition'] ?? 0;
                $_gEP   = $s['bgGradientEndPosition']   ?? 100;
                $_bgParts[] = $_gType === 'linear'
                    ? "linear-gradient({$_gAng}deg, {$_gS} {$_gSP}%, {$_gE} {$_gEP}%)"
                    : "radial-gradient(circle at center, {$_gS} {$_gSP}%, {$_gE} {$_gEP}%)";
            }
            if (!empty($_rImg)) $_bgParts[] = "url('{$_rImg}')";
            $outer[] = 'background-image:' . (!empty($_bgParts) ? implode(', ', $_bgParts) : 'none') . '!important';
        }
        if ($hasOvr('zIndex', $dev) && ($_zv = $getResVal('zIndex', $dev)) !== null)
            $outer[] = 'z-index:' . $_zv . '!important';
        if ($hasOvr('overflow', $dev) && ($_ov = $getResVal('overflow', $dev)) !== null && $_ov !== 'default')
            $outer[] = 'overflow:' . $_ov . '!important';
        return [$inner, $outer];
    };
    [$ti, $to] = $buildRules('tablet');
    [$mi, $mo] = $buildRules('mobile');

    // Desktop base values for responsive properties
    $dMarginTop    = (isset($s['marginTop'])    && $s['marginTop']    !== '') ? $s['marginTop']    . ($s['marginTopUnit']    ?? 'px') : '0px';
    $dMarginBottom = (isset($s['marginBottom']) && $s['marginBottom'] !== '') ? $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px') : '0px';
    $dPaddingCss = '';
    foreach (['Top' => 'top', 'Right' => 'right', 'Bottom' => 'bottom', 'Left' => 'left'] as $_ps => $_pc) {
        if (isset($s['padding' . $_ps]) && $s['padding' . $_ps] !== '')
            $dPaddingCss .= "padding-{$_pc}:{$s['padding' . $_ps]}" . ($s['padding' . $_ps . 'Unit'] ?? 'px') . ';';
    }
    $dColGap = (isset($s['columnGap']) && $s['columnGap'] !== '') ? $s['columnGap'] : '0';

    // Desktop base: background tab properties (moved out of inline for responsive override support)
    $dBgCss = '';
    if ($bgType === 'color' && !empty($s['bgColor']))
        $dBgCss .= 'background-color:' . $hexToRgba($s['bgColor'], $s['bgColorOpacity'] ?? 1) . ';';
    if ($bgType !== 'color' && !empty($s['bgImage'])) {
        $dBgCss .= 'background-size:'     . ($s['bgImageSize']     ?? 'cover') . ';';
        $dBgCss .= 'background-position:' . ($s['bgImagePosition'] ?? 'center center') . ';';
        $dBgCss .= 'background-repeat:'   . ($s['bgImageRepeat']   ?? 'no-repeat') . ';';
        if (!empty($s['bgImageBlendMode']) && $s['bgImageBlendMode'] !== 'normal')
            $dBgCss .= 'background-blend-mode:' . $s['bgImageBlendMode'] . ';';
    }

    $bpSmall1 = $bpSmall + 1;

    // Extra: z-index and overflow (base)
    $dExtraCss = '';
    if (!empty($s['zIndex'])) $dExtraCss .= "z-index:{$s['zIndex']};";
    if (!empty($s['overflow']) && $s['overflow'] !== 'default') $dExtraCss .= "overflow:{$s['overflow']};";

    // Build full CSS string in PHP to avoid Blade parsing @media as directives
    $css  = ".{$cid}{margin-top:{$dMarginTop};margin-bottom:{$dMarginBottom};{$dPaddingCss}--lc-col-gap:{$dColGap};{$dBgCss}{$dExtraCss}}";

    // Sticky
    if (!empty($s['sticky'])) {
        $stickyOffset  = isset($s['stickyOffset'])  ? (int) $s['stickyOffset']  : 0;
        $stickyZIndex  = isset($s['stickyZIndex'])  ? (int) $s['stickyZIndex']  : 99;
        $stickyDesktop = $s['stickyDesktop'] ?? true;
        $stickyTablet  = $s['stickyTablet']  ?? true;
        $stickyMobile  = $s['stickyMobile']  ?? true;
        $sOn  = "position:sticky!important;top:{$stickyOffset}px!important;z-index:{$stickyZIndex}!important;";
        $sOff = "position:static!important;top:auto!important;z-index:auto!important;";
        if ($stickyDesktop) $css .= ".{$cid}{{$sOn}}";
        if ($stickyTablet !== $stickyDesktop) {
            $rule = $stickyTablet ? $sOn : $sOff;
            $css .= "@media(min-width:{$bpSmall1}px) and (max-width:{$bpMedium}px){.{$cid}{{$rule}}}";
        }
        if ($stickyMobile !== $stickyTablet) {
            $rule = $stickyMobile ? $sOn : $sOff;
            $css .= "@media(max-width:{$bpSmall}px){.{$cid}{{$rule}}}";
        }
        $stickyBgColor   = $s['stickyBgColor']        ?? '';
        $stickyBgOpacity = (float)($s['stickyBgColorOpacity'] ?? 1);
        if ($stickyBgColor !== '') {
            $stickyBgRgba = $hexToRgba($stickyBgColor, $stickyBgOpacity);
            $css .= ".{$cid}{transition:background-color 0.3s ease;}";
            $css .= ".lazy-sticky-active.{$cid}{background-color:{$stickyBgRgba}!important;}";
        }
    }
    $css .= ".{$cid} .lazy-container-inner{";
    $css .= "flex-wrap:{$flexWrap}!important;";
    $css .= "align-items:{$alignItems}!important;";
    $css .= "justify-content:{$justifyContent}!important;";
    $css .= "align-content:{$alignContentVal}!important;";
    $css .= "min-height:{$minHeightVal}!important;";
    $css .= "height:{$heightCss}!important;";
    $css .= "}";
    if ($ti || $to) {
        $css .= "@media(min-width:{$bpSmall1}px) and (max-width:{$bpMedium}px){";
        if ($ti) $css .= ".{$cid} .lazy-container-inner{" . implode(';', $ti) . "}";
        if ($to) $css .= ".{$cid}{" . implode(';', $to) . "}";
        $css .= "}";
    }
    if ($mi || $mo) {
        $css .= "@media(max-width:{$bpSmall}px){";
        if ($mi) $css .= ".{$cid} .lazy-container-inner{" . implode(';', $mi) . "}";
        if ($mo) $css .= ".{$cid}{" . implode(';', $mo) . "}";
        $css .= "}";
    }
@endphp

{!! '<style>' . $css . '</style>' !!}

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
    $visCondition = $s['vis_condition'] ?? '';
    $showByCondition = true;
    if ($visCondition === 'logged_in') {
        $showByCondition = auth()->check();
    } elseif ($visCondition === 'logged_out') {
        $showByCondition = !auth()->check();
    } elseif ($visCondition === 'schedule') {
        $now = time();
        $from = !empty($s['vis_date_from']) ? strtotime($s['vis_date_from']) : null;
        $to   = !empty($s['vis_date_to'])   ? strtotime($s['vis_date_to'])   : null;
        $showByCondition = (!$from || $now >= $from) && (!$to || $now <= $to);
    }
@endphp
@if($status === 'published' && $showByCondition)
    @php $ctnAnimType = $s['anim_type'] ?? ''; @endphp
    <{{ $htmlTag }} id="{{ $s['menuAnchor'] ?? '' }}" class="lazy-container {{ $cid }} {{ $hoverClass }} {{ $s['cssClass'] ?? '' }} {{ $visibilityClasses }} {{ !empty($s['sticky']) ? 'lazy-sticky-col' : '' }}{{ $ctnAnimType ? ' lz-anim lz-anim-' . $ctnAnimType : '' }}{{ $ctnBgLazyUrl ? ' lz-lazy-bg' : '' }}" style="{{ implode('; ', $containerStyles) }}"@if($ctnAnimType) data-anim-duration="{{ $s['anim_duration'] ?? 600 }}" data-anim-delay="{{ $s['anim_delay'] ?? 0 }}" data-anim-easing="{{ $s['anim_easing'] ?? 'ease' }}"@endif @if($ctnBgLazyUrl) data-bg="{{ $ctnBgLazyUrl }}"@endif>
        @if($link)
            <a href="{{ $link }}" target="{{ $linkTarget }}" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; flex-grow: 1; width: 100%;">
        @endif
        <div class="lazy-container-inner {{ $innerClass }} flex flex-wrap" style="{{ implode('; ', $innerStyles) }}">
            @if(!empty($container['columns']))
                @foreach($container['columns'] as $column)
                    @include('falcon-cms::frontend.builder.column', ['column' => $column, 'container' => $container])
                @endforeach
            @endif
        </div>
        @if($link)
            </a>
        @endif
    </{{ $htmlTag }}>
@endif
