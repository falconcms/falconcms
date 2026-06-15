@if(!function_exists('renderLazyMenuItemsResponsive'))
@php
function renderLazyMenuItemsResponsive($items, $grouped, $mainStyle, $subStyle, $isMobile, $elId, $settings, $megaMenuLayouts = []) {
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

        // Lazy Special Menu widgets (Cart / Search / Wishlist) — dynamic, render & skip the normal item.
        if (function_exists('lazy_is_special_menu_item') && lazy_is_special_menu_item($item->type ?? '')) {
            echo lazy_render_special_menu_item($item, $style, $isMobile, $elId);
            continue;
        }

        $isActive = false;
        if ($item->url) {
            $isActive = (rtrim($currentUrl, '/') == rtrim($item->url, '/'));
        }

        // Mega menu: only for desktop top-level items
        $hasMegaMenu = !$isMobile && !$isSubmenu
            && !empty($item->mega_menu_id)
            && isset($megaMenuLayouts[$item->mega_menu_id]);

        $liClass = (($hasChildren || $hasMegaMenu) ? 'has-children' : '') . ($isActive ? ' active' : '');
        $liExtra = $hasMegaMenu ? ' data-mega-trigger="' . e($item->id) . '"' : '';
        echo '<li class="lazy-menu-item ' . $liClass . '"' . $liExtra . '>';

        $targetAttr = (!empty($item->target) && $item->target === '_blank') ? ' target="_blank" rel="noopener noreferrer"' : '';
        echo '<a href="' . ($item->url ?? '#') . '"' . $targetAttr . ' class="lazy-menu-link" style="' . $style . '">';
        // Optional icon + "show only icon" support (set via menu builder → Options).
        // Icon position (left/right) and gap come from the Menu element settings.
        $itemIcon = $item->icon ?? '';
        $iconOnly = !empty($item->show_only_icon) && $itemIcon !== '';
        $iconPos  = ($s['menuIconPosition'] ?? 'left') === 'right' ? 'row-reverse' : 'row';
        $iconGap  = (isset($s['menuIconGap']) && $s['menuIconGap'] !== '') ? (int) $s['menuIconGap'] : 6;
        if ($iconOnly) {
            echo '<i class="' . e($itemIcon) . ' lazy-menu-icon" title="' . e($item->title) . '"></i>';
        } elseif ($itemIcon !== '') {
            echo '<span style="display:inline-flex;align-items:center;gap:' . $iconGap . 'px;flex-direction:' . $iconPos . ';">';
            echo '<i class="' . e($itemIcon) . ' lazy-menu-icon"></i>';
            echo '<span>' . $item->title . '</span>';
            echo '</span>';
        } else {
            echo '<span>' . $item->title . '</span>';
        }

        if ($hasMegaMenu) {
            // Always show a down-chevron for mega menu triggers
            echo '<i class="fa fa-chevron-down lazy-menu-arrow"></i>';
        } elseif($hasChildren) {
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

        // Desktop + mega menu: skip submenu (panel rendered separately)
        // Mobile or no mega menu: always render standard submenu
        if ($hasChildren && (!$hasMegaMenu || $isMobile)) {
            echo '<ul class="lazy-submenu ' . ($isMobile ? 'mobile-submenu' : '') . '">';
            renderLazyMenuItemsResponsive($children, $grouped, $mainStyle, $subStyle, $isMobile, $elId, $settings, $megaMenuLayouts);
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
    $submenuTransition = $s['submenuTransition'] ?? 'fade';
    $megaInitTranslate = match($submenuTransition) {
        'slide-down' => 'translateY(-10px)',
        'slide-up'   => 'translateY(10px)',
        default      => 'translateY(0px)',
    };
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

    // Mega Menu Layouts
    $megaMenuLayouts = [];
    try {
        $__allMM = json_decode(get_cms_option('lazy_mega_menus', '[]'), true) ?: [];
        foreach ($__allMM as $__mm) {
            if (!empty($__mm['id'])) $megaMenuLayouts[$__mm['id']] = $__mm;
        }
    } catch (\Exception $e) {}

    // Styles for Desktop
    $wrapperStyle = 'width: 100%; position: relative;';
    if (isset($s['marginTop'])    && $s['marginTop']    !== '') $wrapperStyle .= ' margin-top: '    . $s['marginTop']    . ($s['marginTopUnit']    ?? 'px') . ';';
    if (isset($s['marginBottom']) && $s['marginBottom'] !== '') $wrapperStyle .= ' margin-bottom: ' . $s['marginBottom'] . ($s['marginBottomUnit'] ?? 'px') . ';';
    
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

    $menuBpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $menuBpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $menuRespCss = falcon_elem_resp_css($s, $menuBpSm, $menuBpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".menu-{$elId}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".menu-{$elId}"],
    ]);
@endphp
@if($menuRespCss){!! '<style>' . $menuRespCss . '</style>' !!}@endif

<div id="{{ $customId }}" class="element-menu menu-{{ $elId }} {{ $visibilityClasses }} {{ $customClass }}" style="{{ $wrapperStyle }}">
    @if($menuId && count($menuItems) > 0)
        <!-- Desktop Nav -->
        @php
            $isDistributing = $layout === 'horizontal' && in_array($s['justification'] ?? 'flex-start', ['space-between', 'space-around', 'space-evenly']);
        @endphp
        <nav class="lazy-desktop-nav" style="display: {{ $isMobileView ? 'none' : 'flex' }}; width: 100%; align-items: {{ $s['alignItems'] ?? 'center' }}; justify-content: {{ $s['justification'] ?? 'flex-start' }}; min-height: {{ ($s['minHeight'] ?? '') !== '' ? $s['minHeight'].'px' : '60px' }};">
            <ul class="lazy-menu-list" style="display: flex; width: 100%; flex-direction: {{ $layout === 'horizontal' ? 'row' : 'column' }}; align-items: {{ $s['alignItems'] ?? 'center' }}; justify-content: {{ $s['justification'] ?? 'flex-start' }}; gap: {{ $layout === 'horizontal' ? (in_array($s['justification'] ?? '', ['space-between', 'space-around', 'space-evenly']) ? '0' : ($s['itemSpacing'] ?? 25) . 'px') : ($s['itemSpacing'] ?? 10) . 'px' }};">
                @php renderLazyMenuItemsResponsive($menuItems, $grouped, $mainLinkStyle, $subLinkStyle, false, $elId, $s, $megaMenuLayouts); @endphp
            </ul>

            {{-- Mega Panels --}}
            @foreach($menuItems as $__topItem)
                @php $__hasMM = !empty($__topItem->mega_menu_id) && isset($megaMenuLayouts[$__topItem->mega_menu_id]); @endphp
                @if($__hasMM)
                @php
                    $__mmCfg         = $megaMenuLayouts[$__topItem->mega_menu_id]['config']['settings'] ?? [];
                    $__mmWidthType   = $__mmCfg['width_type']   ?? 'site_width';
                    $__mmCustomWidth = (int)($__mmCfg['custom_width'] ?? 1200);
                    $__mmSiteWidth   = (int)get_cms_option('theme_site_width', '1240');
                    // JS reads these data-attributes to set the outer panel width
                    $__mmPxValue = $__mmWidthType === 'custom' ? $__mmCustomWidth : $__mmSiteWidth;
                @endphp
                {{-- JS uses data-width-type / data-width-px to set exact panel width & position --}}
                <div class="lazy-mega-panel"
                     data-mega-panel="{{ $__topItem->id }}"
                     data-width-type="{{ $__mmWidthType }}"
                     data-width-px="{{ $__mmPxValue }}"
                     style="position:absolute;top:100%;left:0;z-index:9999;pointer-events:none;opacity:0;visibility:hidden;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.12);border-top:1px solid #e5e7eb;box-sizing:border-box;">
                    <div style="width:100%;box-sizing:border-box;">
                        @include('themes.lazy-theme.partials.mega-menu', ['megaMenuData' => $megaMenuLayouts[$__topItem->mega_menu_id]])
                    </div>
                </div>
                @endif
            @endforeach
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
                    @php renderLazyMenuItemsResponsive($menuItems, $grouped, $mobileLinkStyle, $mobileLinkStyle, true, $elId, $s, $megaMenuLayouts); @endphp
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
    .menu-{{ $elId }} .lazy-desktop-nav { width: 100%; min-height: {{ ($s['minHeight'] ?? '') !== '' ? $s['minHeight'].'px' : '60px' }}; position:relative; overflow:visible; }
    .menu-{{ $elId }} .lazy-menu-list {
        list-style: none; padding: 0; margin: 0; display: flex; width: 100%;
        flex-direction: {{ $layout === 'horizontal' ? 'row' : 'column' }};
        align-items: {{ $s['alignItems'] ?? 'center' }};
        justify-content: {{ $s['justification'] ?? 'flex-start' }};
        gap: {{ $layout === 'horizontal' ? (in_array($s['justification'] ?? '', ['space-between', 'space-around', 'space-evenly']) ? '0' : ($s['itemSpacing'] ?? 25) . 'px') : ($s['itemSpacing'] ?? 10) . 'px' }};
    }
    .menu-{{ $elId }} .lazy-menu-item { position: relative; }
    .menu-{{ $elId }} .lazy-mega-panel {
        top: calc(100% + {{ $submenuSpace }}px) !important;
        transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
        transform: {{ $megaInitTranslate }};
    }
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

        // slide-down: starts 10px above resting pos → slides down; slide-up: starts 10px below → slides up; fade: no movement
        $yInit = match($submenuTransition) {
            'slide-up'   => $submenuSpace + 10,
            'slide-down' => 0,                   // slides down by submenuSpace
            default      => $submenuSpace,        // fade: no position change, only opacity
        };
        $subInitTransform = $subDir === 'center'
            ? 'translateX(-50%) translateY(' . $yInit . 'px)'
            : 'translateY(' . $yInit . 'px)';

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

        // Mega Menu hover
        (function() {
            var menuEl = document.getElementById('{{ $customId }}');
            if (!menuEl) return;
            var megaTriggers = menuEl.querySelectorAll('[data-mega-trigger]');
            if (!megaTriggers.length) return;

            var allPanels = [];
            var hideTimer = null;

            function getHeaderEl() {
                var el = menuEl.parentElement;
                while (el && el !== document.body) {
                    var pos = getComputedStyle(el).position;
                    if (pos === 'sticky' || pos === 'fixed') return el;
                    el = el.parentElement;
                }
                return document.body;
            }

            // Move each panel inside its trigger <li> so it positions relative to the trigger,
            // exactly like a regular submenu. CSS top: calc(100% + submenuSpacepx) handles the gap.
            megaTriggers.forEach(function(trigger) {
                var itemId = trigger.getAttribute('data-mega-trigger');
                var panel  = menuEl.querySelector('.lazy-mega-panel[data-mega-panel="' + itemId + '"]');
                if (!panel) return;
                trigger.appendChild(panel);
                allPanels.push(panel);
            });

            // Only left + width need JS; top gap + transition are handled by CSS.
            // Direction only applies to regular submenus, not mega panels.
            function positionPanel(panel, triggerEl) {
                var hdrRect  = getHeaderEl().getBoundingClientRect();
                var trigRect = triggerEl.getBoundingClientRect();
                var widthType = panel.dataset.widthType || 'site_width';
                var widthPx   = parseInt(panel.dataset.widthPx) || 1200;
                var vw        = document.documentElement.clientWidth; // excludes scrollbar — matches CSS centering

                var panelW, panelLeft;
                if (widthType === 'full_width') {
                    // Span the full header
                    panelW    = hdrRect.width;
                    panelLeft = hdrRect.left - trigRect.left;
                } else {
                    panelW = Math.min(widthPx, vw);
                    // site_width: align panel with the site template container (centered in viewport)
                    // custom:     center the custom-width panel within that same container
                    // Both cases reduce to: center the panel in the viewport.
                    panelLeft = (vw - panelW) / 2 - trigRect.left;
                }

                panel.style.left  = panelLeft + 'px';
                panel.style.width = panelW + 'px';
            }

            function showPanel(panel, triggerEl) {
                clearTimeout(hideTimer);
                positionPanel(panel, triggerEl);
                allPanels.forEach(function(p) {
                    if (p === panel) {
                        p.style.opacity       = '1';
                        p.style.visibility    = 'visible';
                        p.style.pointerEvents = 'auto';
                        p.style.transform     = 'translateY(0)';
                    } else {
                        p.style.opacity       = '0';
                        p.style.visibility    = 'hidden';
                        p.style.pointerEvents = 'none';
                        p.style.transform     = ''; // fall back to CSS initial (hidden transform)
                    }
                });
            }

            function hideAll() {
                allPanels.forEach(function(p) {
                    p.style.opacity       = '0';
                    p.style.visibility    = 'hidden';
                    p.style.pointerEvents = 'none';
                    p.style.transform     = ''; // fall back to CSS initial (hidden transform)
                });
            }

            function scheduleHide() { hideTimer = setTimeout(hideAll, 150); }

            megaTriggers.forEach(function(trigger) {
                var panel = trigger.querySelector('.lazy-mega-panel');
                if (!panel) return;

                trigger.addEventListener('mouseenter', function() { showPanel(panel, this); });
                trigger.addEventListener('mouseleave', scheduleHide);
                panel.addEventListener('mouseenter',  function() { clearTimeout(hideTimer); });
                panel.addEventListener('mouseleave',  scheduleHide);
            });
        })();
    })();
</script>
