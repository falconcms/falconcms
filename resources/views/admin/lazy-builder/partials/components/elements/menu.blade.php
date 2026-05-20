<div v-if="el.type === 'menu'"
     class="element-menu-wrapper w-full relative group/menu-preview"
     :class="[el.settings.cssClass || '', 'menu-container-' + el.id]"
     :id="el.settings.cssId || undefined"
     :style="[
         { 
            marginTop: getUnitVal(el.settings.marginTop ?? 0, 'px'),
            marginBottom: getUnitVal(el.settings.marginBottom ?? 0, 'px'),
            minHeight: getUnitVal(el.settings.minHeight, 'px'),
            width: '100%',
            display: 'flex',
            flexDirection: 'column',
            alignItems: (device === 'mobile' || device === 'tablet') ? (el.settings.mobileMenuTriggerHorizontalAlign || 'center') : 'stretch'
         },
         getCanvasVisibilityStyle(el.settings)
     ]">
    
    <!-- Font Loader for Builder -->
    <link v-if="el.settings.mobileMenuFontFamily && el.settings.mobileMenuFontFamily !== 'inherit'" 
          rel="stylesheet" 
          :href="'https://fonts.googleapis.com/css2?family=' + el.settings.mobileMenuFontFamily.replace(/ /g, '+') + ':wght@100;200;300;400;500;600;700;800;900&display=swap'">
    <link v-if="el.settings.submenuFontFamily && el.settings.submenuFontFamily !== 'inherit'" 
          rel="stylesheet" 
          :href="'https://fonts.googleapis.com/css2?family=' + el.settings.submenuFontFamily.replace(/ /g, '+') + ':wght@100;200;300;400;500;600;700;800;900&display=swap'">
    <link v-if="el.settings.fontFamily && el.settings.fontFamily !== 'inherit'" 
          rel="stylesheet" 
          :href="'https://fonts.googleapis.com/css2?family=' + el.settings.fontFamily.replace(/ /g, '+') + ':wght@100;200;300;400;500;600;700;800;900&display=swap'">

    <!-- CSS hover for desktop submenu visibility (supplements JS events for builder canvas compatibility) -->
    <component :is="'style'" v-if="device === 'desktop'"
        :key="'menu-style-' + el.id + '-' + (el.settings.justification || 'flex-start') + '-' + (el.settings.submenuDirection || 'left') + '-' + (el.settings.submenuSpace || 10) + '-' + (el.settings.subSubMenuDirection || 'right') + '-' + (el.settings.subSubMenuOffset ?? 5) + '-' + (el.settings.submenuTextColorHover || '#0091ea')"
        :innerHTML="
            '.menu-container-' + el.id + ' .lazy-menu-nav { justify-content: ' + (el.settings.justification || 'flex-start') + '!important; }' +
            '.menu-container-' + el.id + ' .lazy-menu-list { justify-content: ' + (el.settings.justification || 'flex-start') + '!important; }' +
            '.menu-container-' + el.id + ' .lazy-menu-list > .admin-menu-item:hover > .admin-submenu {' +
            'opacity:1!important;visibility:visible!important;' +
            'transform:' + (el.settings.submenuDirection === 'center' ? 'translateX(-50%) ' : '') + 'translateY(' + (el.settings.submenuSpace || 10) + 'px)!important;}' +
            '.menu-container-' + el.id + ' .admin-submenu > .admin-menu-item:hover > .admin-submenu {' +
            'opacity:1!important;visibility:visible!important;' +
            'transform:translateX(' + (el.settings.subSubMenuDirection === 'left' ? '-' : '') + (el.settings.subSubMenuOffset !== undefined ? el.settings.subSubMenuOffset : 5) + 'px)!important;}' +
            '.menu-container-' + el.id + ' .admin-submenu .admin-menu-item:hover>.admin-menu-link{color:' + (el.settings.submenuTextColorHover || '#0091ea') + '!important;}'
        ">
    </component>

    <!-- Full Width Absolute: overflow visible so dropdown can extend beyond column bounds -->
    <component :is="'style'"
        v-if="el.settings.mobileMenuExpandMode === 'full-width-absolute' && el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)"
        :innerHTML="
            '.column-outer:has(.menu-container-' + el.id + ') { overflow: visible !important; }' +
            '.column-inner:has(.menu-container-' + el.id + ') { overflow: visible !important; }' +
            '.group\\/el:has(.menu-container-' + el.id + ') { overflow: visible !important; }' +
            '.menu-container-' + el.id + ' { overflow: visible !important; }' +
            '.menu-container-' + el.id + ' .lazy-menu-nav { overflow: visible !important; }'
        ">
    </component>

    <nav class="lazy-menu-nav w-full border border-dashed border-transparent hover:border-slate-200 transition-all rounded relative"
         :class="{'p-1': (el.settings.mobileCollapseBreakpoint === 'none' || ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) < ({mobile:1,tablet:2,desktop:3}[device]||1)), 'p-0': (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1))}"
         @vue:mounted="(v) => { const n=v.el; if(!n) return; const r=n.closest('.container-row'); if(!r) return; const W=n.ownerDocument&&n.ownerDocument.defaultView; const _c=()=>{ if(el.settings.mobileMenuExpandMode!=='full-width-absolute') return; let l=0,e=n; while(e&&e!==r){l+=e.offsetLeft;e=e.offsetParent;} const nl=-l,nr=-(r.offsetWidth-l-n.offsetWidth); if(el._fwaLeft!==nl||el._fwaRight!==nr){el._fwaLeft=nl;el._fwaRight=nr;} }; _c(); if(W&&W.ResizeObserver&&!n._fwaObs){n._fwaObs=new W.ResizeObserver(_c);n._fwaObs.observe(r);} }"
         @vue:before-unmount="(v) => { if(v.el&&v.el._fwaObs){v.el._fwaObs.disconnect();delete v.el._fwaObs;} }"
         @vue:updated="(v) => { const n=v.el; if(!n||el.settings.mobileMenuExpandMode!=='full-width-absolute') return; const r=n.closest('.container-row'); if(!r) return; if(!n._fwaObs){ const W=n.ownerDocument&&n.ownerDocument.defaultView; if(W&&W.ResizeObserver){ const _c=()=>{ if(el.settings.mobileMenuExpandMode!=='full-width-absolute') return; let l=0,e=n;while(e&&e!==r){l+=e.offsetLeft;e=e.offsetParent;} const nl=-l,nr=-(r.offsetWidth-l-n.offsetWidth); if(el._fwaLeft!==nl||el._fwaRight!==nr){el._fwaLeft=nl;el._fwaRight=nr;} }; n._fwaObs=new W.ResizeObserver(_c); n._fwaObs.observe(r); } } let l=0,e=n; while(e&&e!==r){l+=e.offsetLeft;e=e.offsetParent;} const nl=-l,nr=-(r.offsetWidth-l-n.offsetWidth); if(el._fwaLeft!==nl||el._fwaRight!==nr){el._fwaLeft=nl;el._fwaRight=nr;} }"
         @mouseenter="(ev) => { if(el.settings.mobileMenuExpandMode==='full-width-absolute'){const n=ev.currentTarget;const r=n.closest('.container-row');if(r){let l=0,e=n;while(e&&e!==r){l+=e.offsetLeft;e=e.offsetParent;}const nl=-l,nr=-(r.offsetWidth-l-n.offsetWidth);if(el._fwaLeft!==nl||el._fwaRight!==nr){el._fwaLeft=nl;el._fwaRight=nr;}}} }"
         :style="(el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) ? {
             display: 'flex',
             flexDirection: 'column',
             alignItems: el.settings.mobileMenuTriggerHorizontalAlign || 'flex-start',
             minHeight: el.settings.mobileMenuExpandMode === 'full-width-absolute' ? '54px' : '0px',
             position: 'relative'
         } : {
             display: 'flex',
             alignItems: el.settings.alignItems || 'center',
             justifyContent: el.settings.justification || 'flex-start',
             minHeight: getUnitVal(el.settings.minHeight || 60, 'px'),
             width: '100%'
         }">
        
        <!-- Mobile Trigger (Visible when device matches breakpoint and at least one icon is set) -->
        <div v-if="(el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) && (el.settings.mobileMenuTriggerExpandIcon || el.settings.mobileMenuTriggerCollapseIcon)"
             class="mobile-menu-trigger-preview flex items-center cursor-pointer transition-all"
             @click="el._mobileMenuOpen = !(el._mobileMenuOpen !== false && (el.settings.mobileMenuMode === 'expanded' || el._mobileMenuOpen === true)); if (el.settings.mobileMenuExpandMode === 'full-width-absolute') { const _n=$event.currentTarget.closest('.lazy-menu-nav'); const _r=_n&&_n.closest('.container-row'); if(_n&&_r){let l=0,e=_n;while(e&&e!==_r){l+=e.offsetLeft;e=e.offsetParent;}el._fwaLeft=-l;el._fwaRight=-((_r.offsetWidth-l)-_n.offsetWidth);} }"
             :style="{
                 paddingTop: getUnitVal(el.settings.mobileMenuTriggerPaddingTop ?? 10, 'px'),
                 paddingRight: getUnitVal(el.settings.mobileMenuTriggerPaddingRight ?? 15, 'px'),
                 paddingBottom: getUnitVal(el.settings.mobileMenuTriggerPaddingBottom ?? 10, 'px'),
                 paddingLeft: getUnitVal(el.settings.mobileMenuTriggerPaddingLeft ?? 15, 'px'),
                 backgroundColor: el.settings.mobileMenuTriggerBgColor || '#ffffff',
                 color: el.settings.mobileMenuTriggerTextColor || '#333333',
                 fontSize: getUnitVal(el.settings.mobileMenuTriggerFontSize || 16, 'px'),
                 justifyContent: el.settings.mobileMenuTriggerHorizontalAlign || 'flex-start',
                 gap: '10px',
                 width: 'max-content',
                 borderRadius: '4px',
                 marginBottom: getUnitVal(el.settings.mobileMenuTriggerSpacing || 0, 'px')
             }">
             <span v-if="el.settings.mobileMenuTriggerText" class="font-bold">@{{ el.settings.mobileMenuTriggerText }}</span>
             <i class="fa" :class="(el._mobileMenuOpen !== false && (el.settings.mobileMenuMode === 'expanded' || el._mobileMenuOpen === true)) ? (el.settings.mobileMenuTriggerCollapseIcon || 'fa-times') : (el.settings.mobileMenuTriggerExpandIcon || 'fa-bars')"></i>
        </div>

        <!-- Menu List -->
        <ul class="lazy-menu-list m-0 p-0" 
            v-if="(el.settings.mobileCollapseBreakpoint === 'none' || ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) < ({mobile:1,tablet:2,desktop:3}[device]||1)) || (el._mobileMenuOpen !== false && (el.settings.mobileMenuMode === 'expanded' || el._mobileMenuOpen === true))"
            :class="[
                {'flex': (el.settings.mobileCollapseBreakpoint === 'none' || ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) < ({mobile:1,tablet:2,desktop:3}[device]||1)), 'block': (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1))},
                el.settings.mobileMenuExpandMode === 'sidebar' ? 'sidebar-preview' : ''
            ]"
            :style="{
                display: (el.settings.mobileCollapseBreakpoint === 'none' || ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) < ({mobile:1,tablet:2,desktop:3}[device]||1)) ? 'flex' : 'block',
                flexDirection: (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) ? 'column' : (el.settings.layout === 'vertical' ? 'column' : 'row'),
                justifyContent: el.settings.justification || 'flex-start',
                alignItems: el.settings.alignItems || 'center',
                gap: ((el.settings.mobileCollapseBreakpoint === 'none' || ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) < ({mobile:1,tablet:2,desktop:3}[device]||1)) && el.settings.layout !== 'vertical') ? (['space-between', 'space-around', 'space-evenly'].includes(el.settings.justification) ? '0' : getUnitVal(el.settings.itemSpacing ?? 25, 'px')) : getUnitVal(el.settings.itemSpacing ?? 10, 'px'),
                listStyle: 'none',
                width: '100%',
                '--submenu-space': (el.settings.submenuSpace || 10) + 'px',
                backgroundColor: (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) ? (el.settings.mobileMenuBgColor || '#ffffff') : 'transparent',
                marginTop: (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1) && el.settings.mobileMenuExpandMode !== 'sidebar' && el.settings.mobileMenuExpandMode !== 'full-width-absolute') ? '5px' : '0',
                boxShadow: (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) ? '0 10px 15px -3px rgba(0,0,0,0.1)' : 'none',
                ...( (el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) && el.settings.mobileMenuExpandMode === 'full-width-absolute' ? {
                    position: 'absolute',
                    top: 'calc(100% + 4px)',
                    left: (el._fwaLeft ?? 0) + 'px',
                    right: (el._fwaRight ?? 0) + 'px',
                    width: 'auto',
                    zIndex: '9999',
                } : {} ),
                ...((el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) && el.settings.mobileMenuExpandMode === 'sidebar' ? {
                    borderLeft: el.settings.mobileMenuSidebarSide === 'right' ? '4px solid #0091ea' : 'none',
                    borderRight: el.settings.mobileMenuSidebarSide === 'left' ? '4px solid #0091ea' : 'none',
                } : {})
            }">
            
            <li v-if="(el.settings.mobileCollapseBreakpoint !== 'none' && ({mobile:1,tablet:2,desktop:3}[el.settings.mobileCollapseBreakpoint||'tablet']||2) >= ({mobile:1,tablet:2,desktop:3}[device]||1)) && el.settings.mobileMenuExpandMode === 'sidebar'"
                class="sidebar-header-preview border-b border-slate-100 p-4 flex items-center justify-between"
                :style="{ backgroundColor: el.settings.mobileMenuBgColor || '#ffffff' }">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">@{{ el.settings.mobileMenuTriggerText || 'Menu' }}</span>
                <i class="fa fa-times text-slate-300"></i>
            </li>
            <template v-if="el.settings.menuId && lazyMenuData[el.settings.menuId]">
                
                <!-- Main Level -->
                <li v-for="(item, idx) in lazyMenuData[el.settings.menuId]"
                    class="admin-menu-item relative group/item"
                    :key="idx"
                    :style="device === 'desktop' ? { display: 'flex', alignItems: 'stretch' } : {}"
                    @mouseenter="el._hoveredIdx = idx; el._hoveredSubIdx = null; el._hoveredGSubIdx = null; el._activeMenuLink = null; el._activeSubMenuLink = null"
                    @mouseleave="el._hoveredIdx = null; el._hoveredSubIdx = null; el._hoveredGSubIdx = null; el._activeMenuLink = null; el._activeSubMenuLink = null">
                    
                    <a href="javascript:void(0)"
                       class="admin-menu-link flex items-center justify-between gap-2 transition-all"
                       @mouseenter="el._activeMenuLink = idx"
                       @mouseleave="el._activeMenuLink = null"
                       :style='(device === "mobile" || device === "tablet") ? ("color: " + (el._activeMenuLink === idx ? (el.settings.mobileMenuTextColorHover || "#0091ea") : (el.settings.mobileMenuTextColor || "#333")) + " !important; " +
                              "background-color: " + (el._activeMenuLink === idx ? (el.settings.mobileMenuBgColorHover || "#f8f9fa") : "transparent") + " !important; " +
                              "padding-top: " + getUnitVal(el.settings.mobileMenuItemPaddingTop ?? 12, "px") + " !important; " +
                              "padding-right: " + getUnitVal(el.settings.mobileMenuItemPaddingRight ?? 20, "px") + " !important; " +
                              "padding-bottom: " + getUnitVal(el.settings.mobileMenuItemPaddingBottom ?? 12, "px") + " !important; " +
                              "padding-left: " + getUnitVal(el.settings.mobileMenuItemPaddingLeft ?? 20, "px") + " !important; " +
                              "min-height: " + getUnitVal(el.settings.mobileMenuItemMinHeight || 50, "px") + " !important; " +
                              "font-size: " + getUnitVal(el.settings.mobileMenuFontSize || 16, "px") + " !important; " +
                              "font-weight: " + (el.settings.mobileMenuFontWeight || "400") + " !important; " +
                              "font-family: " + (el.settings.mobileMenuFontFamily || "inherit") + " !important; " +
                              "text-transform: " + (el.settings.mobileMenuTextTransform || "none") + " !important; " +
                              "letter-spacing: " + getUnitVal(el.settings.mobileMenuLetterSpacing || 0, "px") + " !important; " +
                              "line-height: " + (el.settings.mobileMenuLineHeight || "inherit") + " !important; " +
                              "text-align: " + (el.settings.mobileMenuTextAlign || "left") + " !important; " +
                              "display: flex !important; align-items: center !important; justify-content: " + ((el.settings.mobileMenuTextAlign === "center") ? "center" : "space-between") + " !important; " +
                              "flex-direction: " + ((el.settings.mobileMenuTextAlign === "right") ? "row-reverse" : "row") + " !important; " +
                              "border-bottom: " + (el.settings.mobileSeparatorEnabled === "no" ? "none" : ("1px solid " + (el.settings.mobileMenuSeparatorColor || "rgba(0,0,0,0.05)"))) + " !important; text-decoration: none !important;") : {
                               color: el._hoveredIdx === idx ? (el.settings.itemColorHover || "#0091ea") : (el.settings.itemColor || "#333"),
                               backgroundColor: el._hoveredIdx === idx ? (el.settings.itemBgColorHover || "transparent") : (el.settings.itemBgColor || "transparent"),
                               paddingTop: getUnitVal(el.settings.itemPaddingTop ?? 10, "px"),
                               paddingRight: getUnitVal(el.settings.itemPaddingRight ?? 15, "px"),
                               paddingBottom: getUnitVal(el.settings.itemPaddingBottom ?? 10, "px"),
                               paddingLeft: getUnitVal(el.settings.itemPaddingLeft ?? 15, "px"),
                               borderRadius: getUnitVal(el.settings.itemBorderRadius || 0, "px"),
                               borderStyle: "solid",
                               borderTopWidth: getUnitVal(el._hoveredIdx === idx ? (el.settings.itemBorderSizeTopHover ?? el.settings.itemBorderSizeTop ?? 0) : (el.settings.itemBorderSizeTop ?? 0), "px"),
                               borderRightWidth: getUnitVal(el._hoveredIdx === idx ? (el.settings.itemBorderSizeRightHover ?? el.settings.itemBorderSizeRight ?? 0) : (el.settings.itemBorderSizeRight ?? 0), "px"),
                               borderBottomWidth: getUnitVal(el._hoveredIdx === idx ? (el.settings.itemBorderSizeBottomHover ?? el.settings.itemBorderSizeBottom ?? 0) : (el.settings.itemBorderSizeBottom ?? 0), "px"),
                               borderLeftWidth: getUnitVal(el._hoveredIdx === idx ? (el.settings.itemBorderSizeLeftHover ?? el.settings.itemBorderSizeLeft ?? 0) : (el.settings.itemBorderSizeLeft ?? 0), "px"),
                               borderColor: el._hoveredIdx === idx ? (el.settings.itemBorderColorHover || el.settings.itemBorderColor || "transparent") : (el.settings.itemBorderColor || "transparent"),
                               fontFamily: el.settings.fontFamily !== "inherit" ? el.settings.fontFamily : "inherit",
                               fontSize: getUnitVal(el.settings.fontSize || 14, "px"),
                               fontWeight: el.settings.fontWeight || "400",
                               lineHeight: el.settings.lineHeight || "inherit",
                               letterSpacing: el.settings.letterSpacing || "normal",
                               textTransform: el.settings.textTransform || "none",
                               textDecoration: "none",
                               display: "flex",
                               height: "100%",
                               alignItems: el.settings.alignItems || "center",
                               justifyContent: "space-between",
                               transition: "all " + (el.settings.itemTransition || 0.3) + "s ease-in-out"
                           }'>
                        <span :style='(device === "mobile" || device === "tablet") ? (
                            "font-size: " + (el.settings.mobileMenuFontSize ? (isNaN(el.settings.mobileMenuFontSize) ? el.settings.mobileMenuFontSize : el.settings.mobileMenuFontSize + "px") : "16px") + " !important; " +
                            "letter-spacing: " + (el.settings.mobileMenuLetterSpacing ? (isNaN(el.settings.mobileMenuLetterSpacing) ? el.settings.mobileMenuLetterSpacing : el.settings.mobileMenuLetterSpacing + "px") : "0px") + " !important; " +
                            "line-height: " + (el.settings.mobileMenuLineHeight || "inherit") + " !important; " +
                            "text-transform: " + (el.settings.mobileMenuTextTransform || "none") + " !important; " +
                            "font-family: " + (el.settings.mobileMenuFontFamily || "inherit") + " !important; " +
                            "font-weight: " + (el.settings.mobileMenuFontWeight || "400") + " !important; " +
                            "color: inherit !important;"
                        ) : ""'>@{{ item.title }}</span>
                        <i v-if="item.children && item.children.length > 0 && (device === 'desktop' || el.settings.mobileMenuMode !== 'expanded') && ((device === 'mobile' || device === 'tablet') || (el.settings.arrowScopeObj && el.settings.arrowScopeObj.main) || (!(el.settings.arrowScopeObj && el.settings.arrowScopeObj.main) && !(el.settings.arrowScopeObj && el.settings.arrowScopeObj.submenu) && el.settings.showArrows !== 'no'))"
                           class="fa fa-chevron-down admin-menu-arrow text-[10px]"
                           :style="{
                               transform: el._hoveredIdx === idx ? 'rotate(180deg)' : 'rotate(0deg)',
                               transition: 'all 0.3s',
                               opacity: 0.6,
                               fontSize: '10px'
                           }"></i>
                    </a>

                    <!-- Desktop hover bridge: spans the submenuSpace gap so mouseleave doesn't fire mid-air -->
                    <div v-if="device === 'desktop' && item.children && item.children.length > 0"
                         :style="{ position: 'absolute', top: '100%', left: '0', right: '0', height: ((el.settings.submenuSpace || 10) + 4) + 'px', zIndex: '998' }">
                    </div>

                    <!-- Submenu (Level 2) -->
                    <ul v-if="item.children && item.children.length > 0"
                        class="admin-submenu"
                        :class="{'absolute top-full left-0 z-[1000] transition-all duration-300': device === 'desktop', 'static block': device !== 'desktop'}"
                        :style="[
                            (device === 'mobile' || device === 'tablet') ? {
                                backgroundColor: el.settings.mobileMenuBgColor || 'transparent',
                                display: (el.settings.mobileMenuMode == 'expanded' || el._hoveredIdx === idx) ? 'block !important' : 'none',
                                opacity: (el.settings.mobileMenuMode == 'expanded' || el._hoveredIdx === idx) ? '1 !important' : '1',
                                visibility: (el.settings.mobileMenuMode == 'expanded' || el._hoveredIdx === idx) ? 'visible !important' : 'visible',
                                position: 'static',
                                width: '100%',
                                boxShadow: 'none',
                                transform: 'none',
                                transition: 'none',
                                border: 'none',
                                padding: '0'
                            } : {
                                backgroundColor: el.settings.submenuBgColor || '#fff',
                                minWidth: el.settings.submenuMinWidth || '200px',
                                maxWidth: el.settings.submenuMaxWidth || '300px',
                                boxShadow: el.settings.submenuBoxShadow === 'yes' ? ( (el.settings.submenuShadowH ?? 0) + 'px ' + (el.settings.submenuShadowV ?? 15) + 'px ' + (el.settings.submenuShadowBlur ?? 35) + 'px ' + (el.settings.submenuShadowSpread ?? 0) + 'px ' + (el.settings.submenuShadowColor || 'rgba(0,0,0,0.12)') ) : 'none',
                                borderRadius: (el.settings.submenuBorderRadiusTopLeft ?? 4) + 'px ' + (el.settings.submenuBorderRadiusTopRight ?? 4) + 'px ' + (el.settings.submenuBorderRadiusBottomRight ?? 4) + 'px ' + (el.settings.submenuBorderRadiusBottomLeft ?? 4) + 'px',
                                left: el.settings.submenuDirection === 'left' ? 'auto' : (el.settings.submenuDirection === 'center' ? '50%' : '0'),
                                right: el.settings.submenuDirection === 'left' ? '0' : 'auto',
                                opacity: el._hoveredIdx === idx ? 1 : 0,
                                visibility: el._hoveredIdx === idx ? 'visible' : 'hidden',
                                transform: el._hoveredIdx === idx
                                    ? (el.settings.submenuDirection === 'center' ? 'translateX(-50%) ' : '') + 'translateY(' + (el.settings.submenuSpace || 10) + 'px)'
                                    : (el.settings.submenuDirection === 'center' ? 'translateX(-50%) ' : '') + (el.settings.submenuTransition === 'slide-down' ? 'translateY(' + ((el.settings.submenuSpace || 10) - 12) + 'px)' : (el.settings.submenuTransition === 'slide-up' ? 'translateY(' + ((el.settings.submenuSpace || 10) + 12) + 'px)' : 'translateY(' + ((el.settings.submenuSpace || 10) - 5) + 'px)')),
                                padding: '0',
                                margin: '0',
                                listStyle: 'none'
                            }
                        ]">
                        <li v-for="(child, cidx) in item.children" 
                            class="admin-menu-item relative group/subitem"
                            :key="cidx"
                            @mouseenter="el._hoveredSubIdx = cidx; el._hoveredGSubIdx = null; el._activeSubMenuLink = null"
                            @mouseleave="el._hoveredSubIdx = null; el._hoveredGSubIdx = null; el._activeSubMenuLink = null">
                            
                            <a href="javascript:void(0)"
                               class="admin-menu-link flex items-center justify-between transition-all"
                               @mouseenter="el._activeSubMenuLink = cidx"
                               @mouseleave="el._activeSubMenuLink = null"
                                :style='(device === "mobile" || device === "tablet") ? ("color: " + (el._activeSubMenuLink === cidx ? (el.settings.mobileMenuTextColorHover || "#0091ea") : (el.settings.mobileMenuTextColor || "#333")) + " !important; " +
                                       "background-color: " + (el._activeSubMenuLink === cidx ? (el.settings.mobileMenuBgColorHover || "#f8f9fa") : "transparent") + " !important; " +
                                       "padding-top: " + getUnitVal(el.settings.mobileMenuItemPaddingTop ?? 12, "px") + " !important; " +
                                       "padding-right: " + getUnitVal(el.settings.mobileMenuItemPaddingRight ?? 20, "px") + " !important; " +
                                       "padding-bottom: " + getUnitVal(el.settings.mobileMenuItemPaddingBottom ?? 12, "px") + " !important; " +
                                       "padding-left: " + (el.settings.mobileMenuIndentSubmenus !== "off" ? "35px" : getUnitVal(el.settings.mobileMenuItemPaddingLeft ?? 20, "px")) + " !important; " +
                                       "min-height: " + getUnitVal(el.settings.mobileMenuItemMinHeight || 50, "px") + " !important; " +
                                       "font-size: " + getUnitVal(el.settings.mobileMenuFontSize || 15, "px") + " !important; " +
                                       "font-weight: " + (el.settings.mobileMenuFontWeight || "400") + " !important; " +
                                       "font-family: " + (el.settings.mobileMenuFontFamily || "inherit") + " !important; " +
                                       "letter-spacing: " + getUnitVal(el.settings.mobileMenuLetterSpacing || 0, "px") + " !important; " +
                                       "line-height: " + (el.settings.mobileMenuLineHeight || "inherit") + " !important; " +
                                       "text-transform: " + (el.settings.mobileMenuTextTransform || "none") + " !important; " +
                                       "text-align: " + (el.settings.mobileMenuTextAlign || "left") + " !important; " +
                                       "display: flex !important; align-items: center !important; justify-content: " + ((el.settings.mobileMenuTextAlign === "center") ? "center" : "space-between") + " !important; " +
                                       "flex-direction: " + ((el.settings.mobileMenuTextAlign === "right") ? "row-reverse" : "row") + " !important; " +
                                       "text-decoration: none !important; border-bottom: " + (el.settings.mobileSeparatorEnabled === "no" ? "none" : ("1px solid " + (el.settings.mobileMenuSeparatorColor || "rgba(0,0,0,0.05)"))) + " !important;") : {
                                    color: (el._hoveredSubIdx === cidx ? (el.settings.submenuTextColorHover || "#0091ea") : (el.settings.submenuTextColor || "#333")),
                                    paddingTop: (el.settings.submenuPaddingTop !== undefined ? el.settings.submenuPaddingTop : 10) + "px",
                                    paddingRight: (el.settings.submenuPaddingRight !== undefined ? el.settings.submenuPaddingRight : 20) + "px",
                                    paddingBottom: (el.settings.submenuPaddingBottom !== undefined ? el.settings.submenuPaddingBottom : 10) + "px",
                                    paddingLeft: (el.settings.submenuPaddingLeft !== undefined ? el.settings.submenuPaddingLeft : 20) + "px",
                                    fontFamily: el.settings.submenuFontFamily !== "inherit" ? el.settings.submenuFontFamily : "inherit",
                                    fontSize: getUnitVal(el.settings.submenuFontSize || 14, "px"),
                                    fontWeight: el.settings.submenuFontWeight || "400",
                                    letterSpacing: el.settings.submenuLetterSpacing || "normal",
                                    lineHeight: el.settings.submenuLineHeight || "inherit",
                                    textTransform: el.settings.submenuTextTransform || "none",
                                    textAlign: el.settings.submenuTextAlign || "left",
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "space-between",
                                    flexDirection: "row",
                                    gap: "8px",
                                    width: "100%",
                                    boxSizing: "border-box",
                                    textDecoration: "none",
                                    borderBottom: (cidx !== item.children.length - 1) ? "1px solid " + (el.settings.submenuSeparatorColor || "rgba(0,0,0,0.05)") : "none"
                                }'>
                                 <span :style='(device === "mobile" || device === "tablet") ? (
                                     "font-size: " + (el.settings.mobileMenuFontSize ? (isNaN(el.settings.mobileMenuFontSize) ? el.settings.mobileMenuFontSize : el.settings.mobileMenuFontSize + "px") : "15px") + " !important; " +
                                     "letter-spacing: " + (el.settings.mobileMenuLetterSpacing ? (isNaN(el.settings.mobileMenuLetterSpacing) ? el.settings.mobileMenuLetterSpacing : el.settings.mobileMenuLetterSpacing + "px") : "0px") + " !important; " +
                                     "line-height: " + (el.settings.mobileMenuLineHeight || "inherit") + " !important; " +
                                     "text-transform: " + (el.settings.mobileMenuTextTransform || "none") + " !important; " +
                                     "font-family: " + (el.settings.mobileMenuFontFamily || "inherit") + " !important; " +
                                     "font-weight: " + (el.settings.mobileMenuFontWeight || "400") + " !important; " +
                                     "color: inherit !important;"
                                 ) : ""'>@{{ child.title }}</span>
                                <i v-if="child.children && child.children.length > 0 && (device === 'desktop' || el.settings.mobileMenuMode !== 'expanded') && ((device === 'mobile' || device === 'tablet') || (el.settings.arrowScopeObj && el.settings.arrowScopeObj.submenu) || (!(el.settings.arrowScopeObj && el.settings.arrowScopeObj.main) && !(el.settings.arrowScopeObj && el.settings.arrowScopeObj.submenu) && el.settings.showArrows !== 'no'))"
                                   :class="'fa admin-menu-arrow text-[10px] ' + (el.settings.subSubMenuDirection === 'left' ? 'fa-chevron-left' : 'fa-chevron-right')"
                                   :style="{
                                       transform: (device === 'mobile' || device === 'tablet') ? (el._hoveredSubIdx === cidx ? 'rotate(180deg)' : 'rotate(0deg)') : (el._hoveredSubIdx === cidx ? 'rotate(0deg)' : (el.settings.subSubMenuDirection === 'left' ? 'rotate(90deg)' : 'rotate(-90deg)')),
                                       transition: 'all 0.3s',
                                       opacity: 0.6,
                                       fontSize: '10px'
                                   }"></i>
                            </a>

                            <!-- Desktop hover bridge: spans the subSubMenuOffset gap horizontally -->
                            <div v-if="device === 'desktop' && child.children && child.children.length > 0"
                                 :style="{
                                     position: 'absolute', top: '0', height: '100%',
                                     width: ((el.settings.subSubMenuOffset || 5) + 4) + 'px', zIndex: '998',
                                     ...(el.settings.subSubMenuDirection === 'left' ? { right: '100%' } : { left: '100%' })
                                 }">
                            </div>

                            <!-- Level 3 Submenu -->
                            <ul v-if="child.children && child.children.length > 0"
                                class="admin-submenu"
                                :class="{'absolute top-0 left-full z-[1001] transition-all duration-300': device === 'desktop', 'static block': device !== 'desktop'}"
                                :style="[
                                    (device === 'mobile' || device === 'tablet') ? {
                                        backgroundColor: el.settings.mobileMenuBgColor || 'transparent',
                                        paddingTop: '0',
                                        paddingRight: '0',
                                        paddingBottom: '0',
                                        paddingLeft: '0',
                                        display: (el.settings.mobileMenuMode == 'expanded' || el._hoveredSubIdx === cidx) ? 'block !important' : 'none',
                                        opacity: (el.settings.mobileMenuMode == 'expanded' || el._hoveredSubIdx === cidx) ? '1 !important' : '1',
                                        visibility: (el.settings.mobileMenuMode == 'expanded' || el._hoveredSubIdx === cidx) ? 'visible !important' : 'visible',
                                        position: 'static',
                                        width: '100%',
                                        boxShadow: 'none',
                                        border: 'none',
                                        transform: 'none',
                                        transition: 'none'
                                    } : {
                                        backgroundColor: el.settings.submenuBgColor || '#fff',
                                        minWidth: el.settings.submenuMinWidth || '200px',
                                        maxWidth: el.settings.submenuMaxWidth || '300px',
                                        boxShadow: el.settings.submenuBoxShadow === 'yes' ? ( (el.settings.submenuShadowH ?? 0) + 'px ' + (el.settings.submenuShadowV ?? 15) + 'px ' + (el.settings.submenuShadowBlur ?? 35) + 'px ' + (el.settings.submenuShadowSpread ?? 0) + 'px ' + (el.settings.submenuShadowColor || 'rgba(0,0,0,0.12)') ) : 'none',
                                        borderRadius: (el.settings.submenuBorderRadiusTopLeft ?? 4) + 'px ' + (el.settings.submenuBorderRadiusTopRight ?? 4) + 'px ' + (el.settings.submenuBorderRadiusBottomRight ?? 4) + 'px ' + (el.settings.submenuBorderRadiusBottomLeft ?? 4) + 'px',
                                        opacity: el._hoveredSubIdx === cidx ? 1 : 0,
                                        visibility: el._hoveredSubIdx === cidx ? 'visible' : 'hidden',
                                        transform: el._hoveredSubIdx === cidx ? 'translateX(' + (el.settings.subSubMenuDirection === 'left' ? '-' : '') + (el.settings.subSubMenuOffset !== undefined ? el.settings.subSubMenuOffset : 5) + 'px)' : 'translateX(' + (el.settings.subSubMenuDirection === 'left' ? '' : '-') + '10px)',
                                        top: '0',
                                        left: el.settings.subSubMenuDirection === 'left' ? 'auto' : '100%',
                                        right: el.settings.subSubMenuDirection === 'left' ? '100%' : 'auto',
                                        padding: '0',
                                        margin: '0',
                                        listStyle: 'none'
                                    }
                                ]">
                                <li v-for="(gchild, gidx) in child.children" class="admin-menu-item" :key="gidx"
                                    @mouseenter="el._hoveredGSubIdx = gidx"
                                    @mouseleave="el._hoveredGSubIdx = null">
                                    <a href="javascript:void(0)" 
                                       class="admin-menu-link flex items-center justify-between transition-all"
                                :style='(device === "mobile" || device === "tablet") ? ("color: " + (el._hoveredGSubIdx === gidx ? (el.settings.mobileMenuTextColorHover || "#0091ea") : (el.settings.mobileMenuTextColor || "#333")) + " !important; " +
                                       "background-color: " + (el._hoveredGSubIdx === gidx ? (el.settings.mobileMenuBgColorHover || "#f8f9fa") : "transparent") + " !important; " +
                                       "padding-top: " + getUnitVal(el.settings.mobileMenuItemPaddingTop ?? 12, "px") + " !important; " +
                                       "padding-right: " + getUnitVal(el.settings.mobileMenuItemPaddingRight ?? 20, "px") + " !important; " +
                                       "padding-bottom: " + getUnitVal(el.settings.mobileMenuItemPaddingBottom ?? 12, "px") + " !important; " +
                                       "padding-left: " + (el.settings.mobileMenuIndentSubmenus !== "off" ? "55px" : getUnitVal(el.settings.mobileMenuItemPaddingLeft ?? 20, "px")) + " !important; " +
                                       "min-height: " + getUnitVal(el.settings.mobileMenuItemMinHeight || 50, "px") + " !important; " +
                                       "font-size: " + getUnitVal(el.settings.mobileMenuFontSize || 15, "px") + " !important; " +
                                       "font-weight: " + (el.settings.mobileMenuFontWeight || "400") + " !important; " +
                                       "font-family: " + (el.settings.mobileMenuFontFamily || "inherit") + " !important; " +
                                       "letter-spacing: " + getUnitVal(el.settings.mobileMenuLetterSpacing || 0, "px") + " !important; " +
                                       "line-height: " + (el.settings.mobileMenuLineHeight || "inherit") + " !important; " +
                                       "text-transform: " + (el.settings.mobileMenuTextTransform || "none") + " !important; " +
                                       "text-align: " + (el.settings.mobileMenuTextAlign || "left") + " !important; " +
                                       "display: flex !important; align-items: center !important; justify-content: " + ((el.settings.mobileMenuTextAlign === "center") ? "center" : "space-between") + " !important; " +
                                       "flex-direction: " + ((el.settings.mobileMenuTextAlign === "right") ? "row-reverse" : "row") + " !important; " +
                                       "text-decoration: none !important; border-bottom: " + (el.settings.mobileSeparatorEnabled === "no" ? "none" : ("1px solid " + (el.settings.mobileMenuSeparatorColor || "rgba(0,0,0,0.05)"))) + " !important;") : {
                                             color: el._hoveredGSubIdx === gidx ? (el.settings.submenuTextColorHover || "#0091ea") : (el.settings.submenuTextColor || "#333"),
                                             paddingTop: (el.settings.submenuPaddingTop !== undefined ? el.settings.submenuPaddingTop : 10) + "px",
                                             paddingRight: (el.settings.submenuPaddingRight !== undefined ? el.settings.submenuPaddingRight : 20) + "px",
                                             paddingBottom: (el.settings.submenuPaddingBottom !== undefined ? el.settings.submenuPaddingBottom : 10) + "px",
                                             paddingLeft: (el.settings.submenuPaddingLeft !== undefined ? el.settings.submenuPaddingLeft : 20) + "px",
                                             fontFamily: el.settings.submenuFontFamily !== "inherit" ? el.settings.submenuFontFamily : "inherit",
                                             fontSize: getUnitVal(el.settings.submenuFontSize || 14, "px"),
                                             fontWeight: el.settings.submenuFontWeight || "400",
                                             letterSpacing: el.settings.submenuLetterSpacing || "normal",
                                             lineHeight: el.settings.submenuLineHeight || "inherit",
                                             textTransform: el.settings.submenuTextTransform || "none",
                                             textAlign: el.settings.submenuTextAlign || "left",
                                             display: "flex",
                                             alignItems: "center",
                                             justifyContent: "space-between",
                                             flexDirection: "row",
                                             gap: "8px",
                                             width: "100%",
                                             boxSizing: "border-box",
                                             textDecoration: "none",
                                             borderBottom: (gidx !== child.children.length - 1) ? "1px solid " + (el.settings.submenuSeparatorColor || "rgba(0,0,0,0.05)") : "none"
                                         }'>
                                         <span :style='(device === "mobile" || device === "tablet") ? (
                                             "font-size: " + (el.settings.mobileMenuFontSize ? (isNaN(el.settings.mobileMenuFontSize) ? el.settings.mobileMenuFontSize : el.settings.mobileMenuFontSize + "px") : "15px") + " !important; " +
                                             "letter-spacing: " + (el.settings.mobileMenuLetterSpacing ? (isNaN(el.settings.mobileMenuLetterSpacing) ? el.settings.mobileMenuLetterSpacing : el.settings.mobileMenuLetterSpacing + "px") : "0px") + " !important; " +
                                             "line-height: " + (el.settings.mobileMenuLineHeight || "inherit") + " !important; " +
                                             "text-transform: " + (el.settings.mobileMenuTextTransform || "none") + " !important; " +
                                             "font-family: " + (el.settings.mobileMenuFontFamily || "inherit") + " !important; " +
                                             "font-weight: " + (el.settings.mobileMenuFontWeight || "400") + " !important; " +
                                             "color: inherit !important;"
                                         ) : ""'>@{{ gchild.title }}</span>
                                         <i v-if="gchild.children && gchild.children.length > 0 && (device === 'desktop' || el.settings.mobileMenuMode !== 'expanded')"
                                            class="fa fa-chevron-down admin-menu-arrow text-[10px]"
                                            :style="{
                                                transform: ((device === 'mobile' || device === 'tablet') && el._hoveredGSubIdx === gidx) ? 'rotate(180deg)' : 'rotate(0deg)',
                                                transition: 'all 0.3s',
                                                opacity: 0.6,
                                                fontSize: '10px'
                                            }"></i>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </template>
            <template v-else>
                <div class="py-2 px-4 bg-slate-50 border border-dashed border-slate-200 text-slate-400 text-[11px] font-bold uppercase tracking-widest rounded flex items-center gap-2">
                    <i class="fa fa-info-circle text-[#0091ea]"></i>
                    @{{ el.settings.menuId ? 'Selected menu is empty' : 'No menu selected' }}
                </div>
            </template>
        </ul>
    </nav>
</div>
