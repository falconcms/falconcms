@if(!function_exists('renderLazyMenuItemsResponsive'))
@php
function renderLazyMenuItemsResponsive($items, $grouped, $mainStyle, $subStyle, $isMobile, $elId, $settings) {
    $currentUrl = request()->url();
    
    // Resilient Settings Access
    $s = is_array($settings) ? $settings : (is_object($settings) ? (array)$settings : []);
    $arrowScope  = $s['arrowScopeObj'] ?? null;
    $showArrows  = $s['showArrows'] ?? 'yes';
    $arrowScopeArr = $arrowScope ? (is_array($arrowScope) ? $arrowScope : (is_object($arrowScope) ? (array)$arrowScope : [])) : [];

    foreach($items as $item) {
        $children = $grouped->get($item->id, collect([]));
        $hasChildren = count($children) > 0;
        $isSubmenu = !empty($item->parent_id);
        $style = ($isMobile || $isSubmenu) ? $subStyle : $mainStyle;

        $isActive = false;
        if ($item->url) {
            $isActive = (rtrim($currentUrl, '/') == rtrim($item->url, '/'));
        }

        echo '<li class="lazy-menu-item ' . ($hasChildren ? 'has-children' : '') . ($isActive ? ' active' : '') . '">';
        echo '<a href="' . ($item->url ?? '#') . '" class="lazy-menu-link" style="' . $style . '">';
        echo '<span>' . $item->title . '</span>';

        if($hasChildren) {
            $showArrow = false;
            if ($isMobile) {
                $showArrow = ($s['mobileMenuMode'] ?? 'collapsed') !== 'expanded';
            } else {
                // arrowScopeObj explicitly enables arrows per scope
                if (!$isSubmenu && !empty($arrowScopeArr['main']))    $showArrow = true;
                if ($isSubmenu  && !empty($arrowScopeArr['submenu'])) $showArrow = true;
                if ($isActive   && !empty($arrowScopeArr['active']))  $showArrow = true;

                // If neither main nor submenu scope is set, showArrows controls both levels
                if (!$showArrow && empty($arrowScopeArr['main']) && empty($arrowScopeArr['submenu'])) {
                    $showArrow = ($showArrows !== 'no');
                }
            }

            if($showArrow) {
                $subSubDir = $s['subSubMenuDirection'] ?? 'right';
                $arrowIcon = $isMobile ? 'fa-chevron-down' : ($isSubmenu ? ($subSubDir === 'left' ? 'fa-chevron-left' : 'fa-chevron-right') : 'fa-chevron-down');
                echo '<i class="fa ' . $arrowIcon . ' lazy-menu-arrow"></i>';
            }
        }
        echo '</a>';
        
        if($hasChildren) {
            echo '<ul class="lazy-submenu ' . ($isMobile ? 'mobile-submenu' : '') . '">';
            renderLazyMenuItemsResponsive($children, $grouped, $mainStyle, $subStyle, $isMobile, $elId, $settings);
            echo '</ul>';
        }
        echo '</li>';
    }
}
@endphp
@endif

@php
    $s = $el['settings'] ?? [];
    $menuId = $s['menuId'] ?? null;
    $layout = $s['layout'] ?? 'horizontal';
    $transitionTime = $s['itemTransition'] ?? 0.3;
    $submenuSpace = $s['submenuSpace'] ?? 10;
    $elId = $el['id'];
    
    // Visibility
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' lazy-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' lazy-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' lazy-hide-desktop';

    // CSS Class & ID
    $customClass = $s['cssClass'] ?? '';
    $customId = $s['cssId'] ?? 'menu-'.$elId;

    // Font Loading
    $fontsToLoad = [];
    foreach(['', 'submenu', 'mobileMenu'] as $p) {
        $ffKey = $p ? $p . 'FontFamily' : 'fontFamily';
        $ff = $s[$ffKey] ?? 'inherit';
        if($ff !== 'inherit' && $ff !== '') {
            // Extract only the first part before comma (e.g. "Roboto, sans-serif" -> "Roboto")
            $family = trim(explode(',', $ff)[0]);
            if(!in_array($family, $fontsToLoad)) {
                $fontsToLoad[] = $family;
            }
        }
    }
@endphp

@if(count($fontsToLoad) > 0)
    @foreach($fontsToLoad as $font)
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', trim($font, "'\"")) }}:wght@100;200;300;400;500;600;700;800;900&display=swap">
    @endforeach
@endif

@php
    // Breakpoint Logic
    $bp = $s['mobileCollapseBreakpoint'] ?? 'tablet';
    $isMobileView = ($bp === 'desktop' || $bp == '1024'); 
    
    $breakpointVal = 1024;
    if ($bp === 'mobile' || $bp === '640')  $breakpointVal = 767;
    if ($bp === 'tablet' || $bp === '768')  $breakpointVal = 1023;
    if ($bp === 'desktop' || $bp === '1024') $breakpointVal = 99999;
    if ($bp === 'none')    $breakpointVal = 0;

    // Data Fetching
    $menuItems = [];
    $grouped = collect([]);
    if ($menuId) {
        $allItems = \Illuminate\Support\Facades\DB::table('navigation_menu_items')
            ->where('navigation_menu_id', $menuId)
            ->orderBy('order')
            ->get();
        $grouped = $allItems->groupBy('parent_id');
        $menuItems = $grouped->get(null, collect([]));
    }

    // Styles for Desktop
    $wrapperStyle = 'width: 100%; position: relative;';
    if (isset($s['marginTop'])    && $s['marginTop']    !== '') $wrapperStyle .= ' margin-top: '    . $s['marginTop']    . 'px;';
    if (isset($s['marginBottom']) && $s['marginBottom'] !== '') $wrapperStyle .= ' margin-bottom: ' . $s['marginBottom'] . 'px;';
    
    // Link Styles (General)
    $getTypographyStyle = function($prefix, $defaultSize = '16px') use ($s) {
        $style = '';
        
        $ffKey = $prefix ? $prefix . 'FontFamily' : 'fontFamily';
        $fsKey = $prefix ? $prefix . 'FontSize'   : 'fontSize';
        $fwKey = $prefix ? $prefix . 'FontWeight' : 'fontWeight';
        $lhKey = $prefix ? $prefix . 'LineHeight' : 'lineHeight';
        $lsKey = $prefix ? $prefix . 'LetterSpacing' : 'letterSpacing';
        $ttKey = $prefix ? $prefix . 'TextTransform' : 'textTransform';

        $ff = $s[$ffKey] ?? 'inherit';
        if ($ff !== 'inherit' && $ff !== '') {
            $style .= "font-family: $ff !important;";
        }
        
        $fontSize = $s[$fsKey] ?? $defaultSize;
        $style .= "font-size: " . $fontSize . (is_numeric($fontSize) ? 'px' : '') . " !important;";
        
        $fontWeight = $s[$fwKey] ?? '400';
        $style .= "font-weight: " . $fontWeight . " !important;";
        
        if (isset($s[$lhKey]) && $s[$lhKey] !== '')    $style .= "line-height: " . $s[$lhKey] . " !important;";
        if (isset($s[$lsKey]) && $s[$lsKey] !== '')    $style .= "letter-spacing: " . (is_numeric($s[$lsKey]) ? $s[$lsKey].'px' : $s[$lsKey]) . " !important;";
        if (isset($s[$ttKey]) && $s[$ttKey] !== '' && $s[$ttKey] !== 'none') $style .= "text-transform: " . $s[$ttKey] . " !important;";
        
        return $style;
    };

    $mainLinkStyle = $getTypographyStyle('', '16px');
    $mainLinkStyle .= ' color: ' . ($s['itemColor'] ?? '#333') . ';';
    $mainLinkStyle .= ' background-color: ' . ($s['itemBgColor'] ?? 'transparent') . ';';
    $mainLinkStyle .= ' padding: ' . ($s['itemPaddingTop'] ?? 10) . 'px ' . ($s['itemPaddingRight'] ?? 15) . 'px ' . ($s['itemPaddingBottom'] ?? 10) . 'px ' . ($s['itemPaddingLeft'] ?? 15) . 'px;';
    $mainLinkStyle .= ' border-radius: ' . ($s['itemBorderRadius'] ?? 0) . 'px;';
    
    // Normal Borders
    $bt = $s['itemBorderSizeTop'] ?? 0; $br = $s['itemBorderSizeRight'] ?? 0; $bb = $s['itemBorderSizeBottom'] ?? 0; $bl = $s['itemBorderSizeLeft'] ?? 0;
    if($bt || $br || $bb || $bl) {
        $mainLinkStyle .= " border-style: solid; border-width: {$bt}px {$br}px {$bb}px {$bl}px; border-color: " . ($s['itemBorderColor'] ?? '#eee') . ";";
    }
    $mainLinkStyle .= ' transition: all ' . $transitionTime . 's ease-in-out; text-decoration: none; display: flex; align-items: center; justify-content: space-between; gap: 8px;';

    $subLinkStyle = $getTypographyStyle('submenu', '14px');
    $subLinkStyle .= ' color: ' . ($s['submenuTextColor'] ?? '#333') . ';';
    $subLinkStyle .= ' background-color: ' . ($s['submenuBgColor'] ?? 'transparent') . ';';
    $subLinkStyle .= ' padding: ' . ($s['submenuPaddingTop'] ?? 10) . 'px ' . ($s['submenuPaddingRight'] ?? 20) . 'px ' . ($s['submenuPaddingBottom'] ?? 10) . 'px ' . ($s['submenuPaddingLeft'] ?? 20) . 'px;';
    $subLinkStyle .= ' text-decoration: none; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; gap: 10px;';

    // Mobile Specifics
    $mobileLinkStyle = $getTypographyStyle('mobileMenu', '16px');
    $mobileLinkStyle .= ' color: ' . ($s['mobileMenuTextColor'] ?? '#333') . ';';
    $mobileLinkStyle .= ' min-height: ' . ($s['mobileMenuItemMinHeight'] ?? 60) . 'px;';
    
    $mPtop = $s['mobileMenuItemPaddingTop'] ?? 12;
    $mPright = $s['mobileMenuItemPaddingRight'] ?? 20;
    $mPbottom = $s['mobileMenuItemPaddingBottom'] ?? 12;
    $mPleft = $s['mobileMenuItemPaddingLeft'] ?? 20;
    $mobileLinkStyle .= " padding: {$mPtop}px {$mPright}px {$mPbottom}px {$mPleft}px;";
    
    $mAlign = $s['mobileMenuTextAlign'] ?? 'left';
    $mJustify = ($mAlign === 'center') ? 'center' : 'space-between';
    $mFlexDir = ($mAlign === 'right') ? 'row-reverse' : 'row';
    
    $mobileLinkStyle .= " text-align: {$mAlign} !important; justify-content: {$mJustify} !important; flex-direction: {$mFlexDir} !important; text-decoration: none !important; display: flex !important; align-items: center !important; transition: 0.2s !important; gap: 10px !important;";

    $triggerIconExpand   = $s['mobileMenuTriggerExpandIcon'] ?? 'fa-bars';
    $triggerIconCollapse = $s['mobileMenuTriggerCollapseIcon'] ?? 'fa-times';
    $triggerFontSize     = $s['mobileMenuTriggerFontSize'] ?? '24px';
    $triggerAlign        = $s['mobileMenuTriggerHorizontalAlign'] ?? 'flex-start';
@endphp

<div id="{{ $customId }}" class="element-menu menu-{{ $elId }} {{ $visibilityClasses }} {{ $customClass }}" style="{{ $wrapperStyle }}">
    @if($menuId && count($menuItems) > 0)
        <!-- Desktop Nav -->
        @php
            $isDistributing = $layout === 'horizontal' && in_array($s['justification'] ?? 'flex-start', ['space-between', 'space-around', 'space-evenly']);
        @endphp
        <nav class="lazy-desktop-nav" style="display: {{ $isMobileView ? 'none' : 'flex' }}; width: 100%; align-items: {{ $s['alignItems'] ?? 'center' }}; justify-content: {{ $s['justification'] ?? 'flex-start' }}; min-height: {{ ($s['minHeight'] ?? '') !== '' ? $s['minHeight'].'px' : '60px' }};">
            <ul class="lazy-menu-list" style="display: flex; width: 100%; flex-direction: {{ $layout === 'horizontal' ? 'row' : 'column' }}; align-items: {{ $s['alignItems'] ?? 'center' }}; justify-content: {{ $s['justification'] ?? 'flex-start' }}; gap: {{ $layout === 'horizontal' ? (in_array($s['justification'] ?? '', ['space-between', 'space-around', 'space-evenly']) ? '0' : ($s['itemSpacing'] ?? 25) . 'px') : ($s['itemSpacing'] ?? 10) . 'px' }};">
                @php renderLazyMenuItemsResponsive($menuItems, $grouped, $mainLinkStyle, $subLinkStyle, false, $elId, $s); @endphp
            </ul>
        </nav>

        <!-- Mobile Nav -->
        <div class="lazy-mobile-wrapper" style="display: {{ $isMobileView ? 'flex' : 'none' }}; flex-direction: column; align-items: {{ $triggerAlign }};">
            @if($bp !== 'none' && (!empty($s['mobileMenuTriggerExpandIcon']) || !empty($s['mobileMenuTriggerCollapseIcon'])))
                @php
                    $pTop = $s['mobileMenuTriggerPaddingTop'] ?? 10;
                    $pRight = $s['mobileMenuTriggerPaddingRight'] ?? 15;
                    $pBottom = $s['mobileMenuTriggerPaddingBottom'] ?? 10;
                    $pLeft = $s['mobileMenuTriggerPaddingLeft'] ?? 15;

                    $pStyle = "padding: " .
                              (is_numeric($pTop) ? $pTop.'px' : $pTop) . " " .
                              (is_numeric($pRight) ? $pRight.'px' : $pRight) . " " .
                              (is_numeric($pBottom) ? $pBottom.'px' : $pBottom) . " " .
                              (is_numeric($pLeft) ? $pLeft.'px' : $pLeft) . ";";
                    $modeIsExpanded = ($s['mobileMenuMode'] ?? 'collapsed') === 'expanded';
                @endphp
                <button class="lazy-mobile-trigger" id="trigger-{{ $elId }}" style="font-size: {{ $triggerFontSize }}; color: {{ $s['mobileMenuTriggerTextColor'] ?? '#333' }}; background: {{ $s['mobileMenuTriggerBgColor'] ?? 'transparent' }}; {{ $pStyle }} margin-bottom: {{ intval($s['mobileMenuTriggerSpacing'] ?? 0) }}px; width: max-content; border: none; cursor: pointer; display: flex; align-items: center; gap: 10px; border-radius: 4px;">
                    <span class="icon-expand" style="{{ $modeIsExpanded ? 'display:none;' : '' }}"><i class="fa {{ $triggerIconExpand }}"></i></span>
                    <span class="icon-collapse" style="{{ $modeIsExpanded ? '' : 'display:none;' }}"><i class="fa {{ $triggerIconCollapse }}"></i></span>
                    @if(!empty($s['mobileMenuTriggerText']))
                        <span class="ml-2 text-sm font-bold">{{ $s['mobileMenuTriggerText'] }}</span>
                    @endif
                </button>
            @endif
            
            <nav class="lazy-mobile-nav {{ $s['mobileMenuExpandMode'] ?? 'full-width-static' }} side-{{ $s['mobileMenuSidebarSide'] ?? 'left' }} {{ ($bp === 'none' || ($s['mobileMenuMode'] ?? 'collapsed') === 'expanded') ? 'active' : '' }}" id="nav-{{ $elId }}">
                @if(($s['mobileMenuExpandMode'] ?? '') === 'sidebar')
                    <div class="lazy-sidebar-header" style="background: {{ $s['mobileMenuBgColor'] ?? '#fff' }}; border-bottom: 1px solid {{ $s['mobileMenuSeparatorColor'] ?? '#eee' }}; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between;">
                        <span class="font-bold uppercase text-xs tracking-widest">{{ $s['mobileMenuTriggerText'] ?: 'Menu' }}</span>
                        <button class="lazy-sidebar-close" style="background: none; border: none; cursor: pointer; font-size: 20px; color: {{ $s['mobileMenuTextColor'] ?? '#333' }};">&times;</button>
                    </div>
                @endif
                <ul class="lazy-mobile-list">
                    @php renderLazyMenuItemsResponsive($menuItems, $grouped, $mobileLinkStyle, $mobileLinkStyle, true, $elId, $s); @endphp
                </ul>
            </nav>
            @if(($s['mobileMenuExpandMode'] ?? '') === 'sidebar')
                <div class="lazy-mobile-overlay" id="overlay-{{ $elId }}"></div>
            @endif
        </div>
    @else
        <div class="lazy-menu-empty">
            <i class="fa fa-info-circle mr-1"></i> {{ $menuId ? 'Selected menu has no items' : 'No menu selected' }}
        </div>
    @endif
</div>

<style>
    /* DESKTOP STYLES */
    .menu-{{ $elId }} .lazy-desktop-nav { width: 100%; min-height: {{ ($s['minHeight'] ?? '') !== '' ? $s['minHeight'].'px' : '60px' }}; }
    .menu-{{ $elId }} .lazy-menu-list {
        list-style: none; padding: 0; margin: 0; display: flex; width: 100%;
        flex-direction: {{ $layout === 'horizontal' ? 'row' : 'column' }};
        align-items: {{ $s['alignItems'] ?? 'center' }};
        justify-content: {{ $s['justification'] ?? 'flex-start' }};
        gap: {{ $layout === 'horizontal' ? (in_array($s['justification'] ?? '', ['space-between', 'space-around', 'space-evenly']) ? '0' : ($s['itemSpacing'] ?? 25) . 'px') : ($s['itemSpacing'] ?? 10) . 'px' }};
    }
    .menu-{{ $elId }} .lazy-menu-item { position: relative; }
    @php
        $bth = intval($s['itemBorderSizeTopHover']    ?? 0);
        $brh = intval($s['itemBorderSizeRightHover']  ?? 0);
        $bbh = intval($s['itemBorderSizeBottomHover'] ?? 0);
        $blh = intval($s['itemBorderSizeLeftHover']   ?? 0);
        $bch = $s['itemBorderColorHover'] ?? '#0091ea';
        $hoverBorderStyle = ($bth || $brh || $bbh || $blh)
            ? "border-style: solid !important; border-width: {$bth}px {$brh}px {$bbh}px {$blh}px !important; border-color: {$bch} !important;"
            : '';
    @endphp
    .menu-{{ $elId }} .lazy-menu-link:hover { color: {{ $s['itemColorHover'] ?? '#0091ea' }} !important; background-color: {{ $s['itemBgColorHover'] ?? 'transparent' }} !important; {{ $hoverBorderStyle }} }
    
    /* Arrow Styling & Visibility */
    .menu-{{ $elId }} .lazy-menu-arrow {
        transition: transform 0.3s ease, opacity 0.3s ease, color 0.3s ease;
        width: {{ $s['arrowWidth'] ?? '10' }}{{ is_numeric($s['arrowWidth'] ?? '10') ? 'px' : '' }};
        height: {{ $s['arrowHeight'] ?? '10' }}{{ is_numeric($s['arrowHeight'] ?? '10') ? 'px' : '' }};
        font-size: 10px !important;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.7;
    }

    /* Active/Hover Arrow State */
    .menu-{{ $elId }} .lazy-menu-item:hover > .lazy-menu-link .lazy-menu-arrow,
    .menu-{{ $elId }} .lazy-menu-item.active > .lazy-menu-link .lazy-menu-arrow {
        opacity: 1;
        transform: rotate(180deg);
        @if($s['arrowScopeObj']['active'] ?? false)
            color: {{ $s['itemColorHover'] ?? '#0091ea' }};
        @endif
    }

    /* Submenu Arrow Direction */
    .menu-{{ $elId }} .lazy-submenu .fa-chevron-right.lazy-menu-arrow { transform: rotate(-90deg); }
    .menu-{{ $elId }} .lazy-submenu .fa-chevron-left.lazy-menu-arrow { transform: rotate(90deg); }
    .menu-{{ $elId }} .lazy-submenu .lazy-menu-item:hover > .lazy-menu-link .lazy-menu-arrow { transform: rotate(0deg); }

    @php
        $subDir    = $s['submenuDirection'] ?? 'right';
        $subLeft   = $subDir === 'left' ? 'auto' : ($subDir === 'center' ? '50%' : '0');
        $subRight  = $subDir === 'left' ? '0' : 'auto';
        $subHoverTransform = $subDir === 'center'
            ? 'translateX(-50%) translateY(' . $submenuSpace . 'px)'
            : 'translateY(' . $submenuSpace . 'px)';
        $subInitTransform = $subDir === 'center' ? 'translateX(-50%)' : 'none';

        $subSubDir    = $s['subSubMenuDirection'] ?? 'right';
        $subSubOffset = intval($s['subSubMenuOffset'] ?? 5);
        $subSubLeft   = $subSubDir === 'left' ? 'auto' : '100%';
        $subSubRight  = $subSubDir === 'left' ? '100%' : 'auto';
        $subSubHiddenTransform = $subSubDir === 'left' ? 'translateX(' . $subSubOffset . 'px)' : 'translateX(-' . $subSubOffset . 'px)';
        $subSubShownTransform  = $subSubDir === 'left' ? 'translateX(-' . $subSubOffset . 'px)' : 'translateX(' . $subSubOffset . 'px)';

        $subSepColor    = !empty($s['submenuSeparatorColor'])    ? $s['submenuSeparatorColor']    : '#e5e7eb';
        $mobileSepColor = !empty($s['mobileMenuSeparatorColor']) ? $s['mobileMenuSeparatorColor'] : 'rgba(0,0,0,0.08)';
    @endphp

    .menu-{{ $elId }} .lazy-submenu {
        position: absolute; top: 100%;
        left: {{ $subLeft }}; right: {{ $subRight }};
        min-width: {{ $s['submenuMinWidth'] ?? '200px' }}; max-width: {{ $s['submenuMaxWidth'] ?? '300px' }};
        background: {{ $s['submenuBgColor'] ?? '#fff' }}; opacity: 0; visibility: hidden; z-index: 1000; list-style: none; padding: 0; margin: 0; transition: 0.3s;
        box-shadow: {{ ($s['submenuBoxShadow'] ?? 'no') === 'yes' ? '0 10px 30px rgba(0,0,0,0.1)' : 'none' }};
        border-radius: {{ $s['submenuBorderRadiusTopLeft'] ?? 4 }}px;
        transform: {{ $subInitTransform }};
    }
    .menu-{{ $elId }} .lazy-submenu .lazy-submenu {
        top: 0px !important; left: {{ $subSubLeft }} !important; right: {{ $subSubRight }} !important;
        transform: {{ $subSubHiddenTransform }} !important; transition: none !important;
    }
    .menu-{{ $elId }} .lazy-submenu .lazy-menu-link { border-bottom: 1px solid {{ $subSepColor }} !important; }
    /* Remove bottom border only from the last item of the FIRST-LEVEL submenu (L2), not nested (L3+) */
    .menu-{{ $elId }} .lazy-desktop-nav .lazy-menu-list > .lazy-menu-item > .lazy-submenu > .lazy-menu-item:last-child > .lazy-menu-link { border-bottom: none !important; }
    .menu-{{ $elId }} .lazy-submenu .lazy-menu-link:hover { color: {{ $s['submenuTextColorHover'] ?? '#0091ea' }} !important; background: rgba(0,0,0,0.02); }
    .menu-{{ $elId }} .lazy-menu-item:hover > .lazy-submenu { opacity: 1; visibility: visible; transform: {{ $subHoverTransform }}; }
    .menu-{{ $elId }} .lazy-submenu .lazy-menu-item:hover > .lazy-submenu {
        opacity: 1 !important; visibility: visible !important;
        transform: {{ $subSubShownTransform }} !important;
    }
    
    /* MOBILE STYLES */
    .menu-{{ $elId }} .lazy-mobile-wrapper { display: none; width: 100%; position: relative; }
    .menu-{{ $elId }} .lazy-mobile-trigger { border: none; cursor: pointer; display: flex; align-items: center; border-radius: 4px; box-sizing: border-box; -webkit-appearance: none; appearance: none; line-height: 1; }
    .menu-{{ $elId }} .lazy-mobile-nav { 
        display: none; background: {{ $s['mobileMenuBgColor'] ?? '#fff' }}; width: 100%; 
        border-top: 1px solid {{ $mobileSepColor }};
    }
    .menu-{{ $elId }} .lazy-mobile-nav.active { display: block; }
    
    /* SIDEBAR MODE */
    .menu-{{ $elId }} .lazy-mobile-nav.sidebar {
        position: fixed; top: 0; bottom: 0; width: 280px; max-width: 85%; z-index: 10001; 
        display: block !important; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 0 50px rgba(0,0,0,0.15); overflow-y: auto; height: 100vh;
    }
    .menu-{{ $elId }} .lazy-mobile-nav.sidebar.side-left { left: 0; transform: translateX(-100%); }
    .menu-{{ $elId }} .lazy-mobile-nav.sidebar.side-right { right: 0; transform: translateX(100%); }
    .menu-{{ $elId }} .lazy-mobile-nav.sidebar.active { transform: translateX(0); }
    
    .menu-{{ $elId }} .lazy-mobile-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); 
        z-index: 10000; opacity: 0; visibility: hidden; transition: 0.3s;
    }
    .menu-{{ $elId }} .lazy-mobile-overlay.active { opacity: 1; visibility: visible; }
    
    .menu-{{ $elId }} .lazy-mobile-list { list-style: none; padding: 0; margin: 0; }
    @if(($s['mobileSeparatorEnabled'] ?? 'yes') !== 'no')
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-menu-link { border-bottom: 1px solid {{ $mobileSepColor }} !important; }
    .menu-{{ $elId }} .lazy-mobile-nav .mobile-submenu .lazy-menu-link { border-bottom: 1px solid {{ $mobileSepColor }} !important; }
    .menu-{{ $elId }} .lazy-mobile-nav .mobile-submenu .mobile-submenu .lazy-menu-link { border-bottom: 1px solid {{ $mobileSepColor }} !important; }
    /* Only remove border from the very last top-level item — submenu items always keep their dividers */
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-mobile-list > .lazy-menu-item:last-child > .lazy-menu-link { border-bottom: none !important; }
    @else
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-menu-link { border-bottom: none !important; }
    @endif
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-menu-link:hover { color: {{ $s['mobileMenuTextColorHover'] ?? '#0091ea' }} !important; background: {{ $s['mobileMenuBgColorHover'] ?? '#f8f9fa' }} !important; }
    
    .menu-{{ $elId }} .mobile-submenu {
        display: none;
        list-style: none;
        padding: 0;
        margin: 0;
        background: transparent;
        position: static !important;
        width: 100% !important;
        box-shadow: none !important;
        transform: none !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-submenu {
        position: static !important;
        transform: none !important;
        left: auto !important; right: auto !important; top: auto !important;
        opacity: 1 !important; visibility: visible !important;
        transition: none !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-menu-item:hover > .lazy-submenu { transform: none !important; }
    @if(($s['mobileMenuIndentSubmenus'] ?? 'on') === 'on')
        .menu-{{ $elId }} .mobile-submenu .lazy-menu-link { padding-left: 35px !important; }
        .menu-{{ $elId }} .mobile-submenu .mobile-submenu .lazy-menu-link { padding-left: 55px !important; }
        .menu-{{ $elId }} .mobile-submenu .mobile-submenu .mobile-submenu .lazy-menu-link { padding-left: 75px !important; }
    @endif
    
    .menu-{{ $elId }} .lazy-menu-item.active > .mobile-submenu,
    .menu-{{ $elId }} .lazy-menu-item:hover > .mobile-submenu { display: block !important; }
    
    .menu-{{ $elId }} .lazy-mobile-nav .lazy-menu-arrow {
        transition: 0.3s !important;
    }
    .menu-{{ $elId }} .lazy-menu-item.active > .lazy-menu-link .lazy-menu-arrow {
        transform: rotate(180deg) !important;
    }
    .menu-{{ $elId }} .lazy-menu-link span {
        font-size: inherit !important;
        letter-spacing: inherit !important;
        line-height: inherit !important;
        text-transform: inherit !important;
        font-family: inherit !important;
        font-weight: inherit !important;
    }
    .menu-{{ $elId }} .lazy-menu-item.active > .mobile-submenu,
    .menu-{{ $elId }} .lazy-menu-item:hover > .mobile-submenu { display: block !important; }
    
    /* MOBILE MENU MODE: EXPANDED */
    @if(($s['mobileMenuMode'] ?? 'collapsed') === 'expanded')
        .menu-{{ $elId }} .mobile-submenu { 
            display: block !important; 
            position: static !important; 
            width: 100% !important; 
            opacity: 1 !important; 
            visibility: visible !important; 
            transform: none !important; 
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
    @endif
    
    .menu-{{ $elId }} .lazy-menu-item.active > .lazy-menu-link .lazy-menu-arrow { transform: rotate(180deg); }

    /* RESPONSIVE BREAKPOINT */
    @media (max-width: {{ ($bp === 'desktop' || $bp === '1024') ? '9999' : (($bp === 'tablet' || $bp === '768') ? '1023' : (($bp === 'mobile' || $bp === '640') ? '767' : '0')) }}px) {
        .menu-{{ $elId }} .lazy-desktop-nav { display: none !important; }
        .menu-{{ $elId }} .lazy-mobile-wrapper { display: flex !important; flex-wrap: wrap; }
        @if(($s['mobileMenuExpandMode'] ?? 'full-width-static') === 'full-width-absolute')
            .menu-{{ $elId }} .lazy-mobile-nav { position: absolute; top: 100%; z-index: 1000; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        @endif
    }
    
    .lazy-menu-empty { padding: 30px; border: 2px dashed #eee; border-radius: 8px; text-align: center; color: #aaa; width: 100%; }
</style>

<script>
    (function() {
        const elId = "{{ $elId }}";
        const trigger = document.getElementById('trigger-' + elId);
        const nav = document.getElementById('nav-' + elId);
        const overlay = document.getElementById('overlay-' + elId);
        if (!nav) return;

        const isSidebar = {{ json_encode(($s['mobileMenuExpandMode'] ?? '') === 'sidebar') }};
        const isFwa = {{ json_encode(($s['mobileMenuExpandMode'] ?? '') === 'full-width-absolute') }};
        const closeBtn = nav.querySelector('.lazy-sidebar-close');

        const positionFwaNav = () => {
            if (!isFwa) return;
            const wrapper = nav.parentElement;
            const row = nav.closest('.lazy-container') || nav.closest('.container-row');
            if (!row || !wrapper) return;
            const nr = wrapper.getBoundingClientRect();
            const rr = row.getBoundingClientRect();
            nav.style.left  = (rr.left - nr.left) + 'px';
            nav.style.right = (nr.right - rr.right) + 'px';
            nav.style.width = 'auto';
        };

        const toggleMenu = (active) => {
            const isActive = active !== undefined ? active : nav.classList.toggle('active');
            if (active !== undefined) nav.classList.toggle('active', isActive);
            if (isActive) positionFwaNav();

            if (overlay) overlay.classList.toggle('active', isActive);
            if (trigger) {
                trigger.querySelector('.icon-expand').style.display = isActive ? 'none' : 'block';
                trigger.querySelector('.icon-collapse').style.display = isActive ? 'block' : 'none';
            }

            if (isSidebar) {
                document.body.style.overflow = isActive ? 'hidden' : '';
            }
        };

        // Position FWA nav on load if already active (expanded mode or pre-opened)
        if (nav.classList.contains('active')) positionFwaNav();

        if (trigger) {
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleMenu();
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => toggleMenu(false));
        }

        if (overlay) {
            overlay.addEventListener('click', () => toggleMenu(false));
        }

        // Accordion for Mobile
        @if(($s['mobileMenuMode'] ?? 'collapsed') !== 'expanded')
            const itemsWithChildren = nav.querySelectorAll('.has-children > .lazy-menu-link');
            itemsWithChildren.forEach(link => {
                link.addEventListener('click', function(e) {
                    const parent = this.parentElement;
                    const isAlreadyActive = parent.classList.contains('active');

                    if (!isAlreadyActive) {
                        e.preventDefault();
                        parent.classList.add('active');

                        // Close siblings
                        @if(($s['mobileMenuOpeningMode'] ?? 'toggle') === 'accordion')
                            const siblings = parent.parentElement.children;
                            for(let s of siblings) {
                                if (s !== parent) s.classList.remove('active');
                            }
                        @endif
                    } else {
                        const href = this.getAttribute('href');
                        if (href === '#' || href === '') {
                            e.preventDefault();
                            parent.classList.remove('active');
                        }
                    }
                });
            });
        @endif
    })();
</script>
