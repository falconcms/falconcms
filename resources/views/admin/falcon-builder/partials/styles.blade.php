<style>
    :root {
        --builder-bg: #ffffff;
        --sidebar-width: 300px;
        --topbar-height: 50px;
        --primary: #2271b1;
        --dark-bg: #1b1b1b;
        --border-color: #e1e1e1;
    }

    /* Basic Reset to prevent complete breakage without Tailwind */
    * { box-sizing: border-box; }
    
    .dragging-no-transition, .dragging-no-transition * {
        transition: none !important;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        background-color: var(--builder-bg);
        overflow: hidden;
        margin: 0;
        padding: 0;
        display: block;
        width: 100%;
        height: 100vh;
    }



    h1, h2, h3, .font-premium {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    [v-cloak] { display: none !important; }

    /* Builder Layout (Standard CSS as fallback) */
    .builder-wrapper {
        display: grid;
        grid-template-areas: 
            "topbar topbar"
            "sidebar canvas";
        grid-template-columns: var(--sidebar-width) 1fr;
        grid-template-rows: var(--topbar-height) 1fr;
        height: 100vh;
        width: 100%;
    }

    /* PREVIEW MODE - Grid column fix */
    .builder-wrapper.is-preview {
        grid-template-columns: 0 1fr !important;
    }

    .is-preview .container-row,
    .is-preview .container-row:hover,
    .is-preview .column-outer,
    .is-preview .column-outer:hover {
        outline: 2px solid transparent !important;
    }
    .is-preview .nested-row-outer-wrapper {
        outline: 2px solid transparent !important;
        border-color: transparent !important;
        box-shadow: none !important;
    }
    .is-preview .container-handles,
    .is-preview .container-right-panel,
    .is-preview .column-left-panel,
    .is-preview .add-element-placeholder,
    .is-preview .column-add-btn,
    .is-preview .element-edit-panel,
    .is-preview .empty-column-placeholder,
    .is-preview .nested-column-toolbar,
    .is-preview .handle-orange,
    .is-preview .handle-blue,
    .is-preview .handle-purple {
        display: none !important;
    }
    .is-preview .canvas-container {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .builder-topbar { 
        grid-area: topbar; 
        background: var(--dark-bg); 
        color: white;
        z-index: 50; 
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 15px;
        height: var(--topbar-height);
        width: 100%;
    }
    
    .builder-sidebar {
        grid-area: sidebar;
        background: #fff;
        border-right: 1px solid var(--border-color);
        z-index: 40;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .builder-canvas-area {
        grid-area: canvas;
        overflow-y: auto;
        background-color: var(--builder-bg);
        display: flex;
        flex-direction: column;
        padding: 0;
        overflow-x: hidden;
    }

    /* Canvas Styles */
    .canvas-container {
        width: 100%;
        margin: 0 auto !important;
        background: #fff;
        min-height: calc(100vh - var(--topbar-height));
        box-shadow: none;
        border: none;
        transition: all 0.3s ease;
        position: relative;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .canvas-container.mobile, .canvas-container.tablet {
        box-shadow: 0 10px 45px rgba(0,0,0,0.15) !important;
        outline: 1px solid #cbd5e1;
    }


    /* Hide inline <style> tags rendered by Vue's <component is="style"> inside canvas.
       They apply CSS regardless of display, but if they become visible (e.g. Tailwind CDN
       overrides the UA stylesheet's display:none for <style>) they inflate element height. */
    .canvas-container style {
        display: none !important;
        height: 0 !important;
        overflow: hidden !important;
    }

    /* Fix huge icons if Tailwind fails */
    svg {
        max-width: 100%;
        height: auto;
    }
    .topbar-icon svg { width: 20px; height: 20px; }
    .builder-sidebar svg { width: 24px; height: 24px; }
    i.fa { font-size: 14px; }

    /* Topbar Icons */
    .topbar-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        transition: color 0.2s;
        cursor: pointer;
    }
    .topbar-icon:hover { color: white; }
    .topbar-icon.active { color: white; background: rgba(255,255,255,0.1); }

    .container-row {
        position: relative;
        transition: all 0.2s;
        display: block;
        width: 100%;
        z-index: 10;
        outline: 2px solid #bcdff1;
        outline-offset: -2px;
    }
    .container-row:hover, .container-active {
        outline: 2px solid var(--primary) !important;
        z-index: 11;
    }

    /* Column Styles */
    .column-outer {
        position: relative;
        transition: all 0.2s;
        z-index: 5;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        outline: 2px solid transparent;
        outline-offset: -2px;
    }
    .column-outer:hover {
        outline: 2px solid #bcdff1 !important;
        z-index: 6;
    }
    .column-active {
        outline: 2px solid var(--primary) !important;
        z-index: 7;
    }

    /* Padding/Margin Handles */
    .container-handles > div {
        position: absolute;
        z-index: 100;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .container-active .container-handles > div {
        opacity: 1;
    }

    .handle-blue, .handle-purple, .handle-blue-h, .handle-purple-h, .handle-left, .handle-right {
        width: 18px;
        height: 18px;
        background: #2271b1;
        border-radius: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
        pointer-events: auto;
        z-index: 120;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    .handle-blue, .handle-purple { cursor: ns-resize !important; }
    .handle-blue-h, .handle-purple-h, .handle-left, .handle-right { cursor: ew-resize !important; }
    .handle-purple, .handle-purple-h { background: #9c27b0; }

    .handle-top { top: -10px; left: 50%; transform: translateX(-50%); position: absolute; }
    .handle-bottom { bottom: -10px; left: 50%; transform: translateX(-50%); position: absolute; }
    .handle-left { left: -10px; top: 50%; transform: translateY(-50%); position: absolute; }
    .handle-right { right: -10px; top: 50%; transform: translateY(-50%); position: absolute; }

    /* Right/Left Panels Toolbar */
    .container-right-panel {
        position: absolute;
        right: 0;
        top: 0;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .column-left-panel {
        position: absolute;
        left: 0;
        top: 0;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .column-left-panel:hover {
        z-index: 1100;
    }
    .container-right-panel:hover {
        z-index: 1100;
    }
    .container-row:hover .container-right-panel,
    .container-active .container-right-panel,
    .column-outer:hover .column-left-panel,
    .column-active .column-left-panel {
        opacity: 1;
    }
    .panel-inner {
        background: var(--primary);
        border-radius: 4px;
        display: flex;
        padding: 2px;
        gap: 1px;
        align-items: center;
    }
    .panel-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #e1f5fe;
        cursor: pointer;
        border-radius: 2px;
        transition: all 0.2s;
        position: relative;
    }
    .column-label {
        color: white;
        font-size: 10px;
        font-weight: 800;
        padding: 0 6px;
        letter-spacing: 0.5px;
    }
    .panel-btn:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    .panel-btn i { font-size: 14px; }

    /* Tooltips */
    .falcon-tooltip {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 10px;
        background: #1b1b1b;
        color: white;
        text-transform: uppercase;
        font-size: 10px;
        line-height: normal;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 4px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
        z-index: 200;
    }
    .falcon-tooltip::after {
        content: '';
        position: absolute;
        bottom: 100%;
        right: 12px;
        border: 5px solid transparent;
        border-bottom-color: #1b1b1b;
    }
    .panel-btn:hover .falcon-tooltip {
        opacity: 1;
    }

    /* Navigator Item */
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        cursor: pointer;
        border-bottom: 1px solid #f8fafc;
    }
    .nav-item.active {
        background: #eff6ff;
        border-left: 4px solid var(--primary);
    }

    /* Save Button */
    .btn-save {
        background-color: #4A5259;
        color: white;
        border: none;
        padding: 8px 24px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-save:hover {
        background-color: #3d4449;
    }
    .btn-save:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .dragging-no-transition, .dragging-no-transition * {
        transition: none !important;
    }
    .handle-orange, .handle-orange-h {
        width: 18px;
        height: 18px;
        background: #ff9800;
        border-radius: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
        pointer-events: auto;
        z-index: 120;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    .handle-orange { cursor: ns-resize !important; }
    .handle-orange-h { cursor: ew-resize !important; }

    .panel-btn-orange {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
        border-radius: 2px;
        transition: all 0.2s;
        position: relative;
    }
    .panel-btn-orange:hover {
        background: rgba(255,255,255,0.2);
    }
    .panel-btn-orange i { font-size: 14px; }

    .falcon-tooltip-v2 {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-bottom: 10px;
        background: #1b1b1b;
        color: white;
        text-transform: uppercase;
        font-size: 10px;
        line-height: normal;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 4px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
        z-index: 500;
    }
    .falcon-tooltip-v2::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: #1b1b1b;
    }
    .panel-btn-orange:hover .falcon-tooltip-v2,
    .handle-orange:hover .falcon-tooltip-v2 {
        opacity: 1 !important;
        visibility: visible !important;
    }
    .nested-column-active {
        @apply ring-2 ring-[#ff9800] ring-offset-2;
    }

    /* WordPress Style Components for Media Modal */
    .wp-btn-primary {
        background: #2271b1;
        color: #fff;
        border: 1px solid #2271b1;
        border-radius: 3px;
        padding: 0 10px;
        min-height: 30px;
        font-size: 13px;
        line-height: 2.15384615;
        cursor: pointer;
        transition: all 0.1s;
        display: inline-flex;
        align-items: center;
        box-sizing: border-box;
    }
    .wp-btn-primary:hover {
        background: #135e96;
        border-color: #135e96;
    }
    .wp-btn-primary:disabled {
        background: #a7aaad !important;
        border-color: #a7aaad !important;
        color: #dcdcde !important;
        cursor: default !important;
        opacity: 0.7;
    }

    .wp-btn-secondary {
        color: #2271b1;
        border-color: #2271b1;
        background: #f6f7f7;
        vertical-align: top;
        display: inline-block;
        text-decoration: none;
        font-size: 13px;
        line-height: 2.15384615;
        min-height: 30px;
        margin: 0;
        padding: 0 10px;
        cursor: pointer;
        border-width: 1px;
        border-style: solid;
        -webkit-appearance: none;
        border-radius: 3px;
        white-space: nowrap;
        box-sizing: border-box;
    }
    .wp-btn-secondary:hover {
        background: #f0f0f1;
        border-color: #0a4b78;
        color: #0a4b78;
    }

    .wp-input {
        box-shadow: 0 0 0 transparent;
        border-radius: 4px;
        border: 1px solid #8c8f94;
        background-color: #fff;
        color: #2c3338;
        font-family: inherit;
        font-size: 14px;
        padding: 0 8px;
        line-height: 2;
        min-height: 30px;
    }
    .wp-input:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: 2px solid transparent;
    }

    /* Modal Animation */
    @keyframes wp-modal-fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes wp-modal-zoom-in {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    #wp-media-modal:not(.hidden) {
        display: block !important;
        animation: wp-modal-fade-in 0.15s ease-out;
    }

    #wp-media-modal:not(.hidden) > div:last-child {
        animation: wp-modal-zoom-in 0.2s ease-out;
    }
    /* FINAL PREVIEW OVERRIDES - DO NOT MOVE */
    .is-preview.builder-wrapper,
    .builder-wrapper.is-preview {
        grid-template-columns: 0 1fr !important;
        grid-template-areas: 
            "topbar topbar"
            "sidebar canvas" !important;
        width: 100% !important;
    }
    .is-preview .builder-sidebar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
        opacity: 0 !important;
        pointer-events: none !important;
        position: absolute !important;
        left: -9999px !important;
    }
    .is-preview .builder-canvas-area {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        /* Keep the canvas in its named grid area (row 2 / 1fr column).
           Using grid-area:auto here auto-placed it into the 0-width first
           column once the sidebar was hidden, blanking the whole preview. */
        grid-area: canvas !important;
    }
    .is-preview .canvas-container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    /* Hover Effects */
    .lazy-column, .lazy-container, .column-outer, .container-row {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .hover-effect-zoom:hover { transform: scale(1.03) !important; z-index: 50 !important; }
    .hover-effect-lift:hover { transform: translateY(-10px) !important; z-index: 50 !important; box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important; }
    .hover-effect-glow:hover { box-shadow: 0 0 25px rgba(0, 145, 234, 0.5) !important; z-index: 50 !important; }
    .hover-effect-fade:hover { opacity: 0.7 !important; }

    .falcon-hide-all { display: none !important; }

    /* Builder-Specific Canvas Device Overrides (Simulating Media Queries) */
    .canvas-container.mobile .lazy-column,
    .canvas-container.mobile .column-outer {
        flex-basis: 100%;
        max-width: 100%;
        width: 100%;
    }

    /* Editing: dim hidden elements + yellow border instead of removing */
    .builder-wrapper:not(.is-preview) .canvas-container.mobile .falcon-hide-mobile,
    .builder-wrapper:not(.is-preview) .canvas-container.tablet .falcon-hide-tablet,
    .builder-wrapper:not(.is-preview) .canvas-container.desktop .falcon-hide-desktop {
        opacity: 0.35 !important;
        outline: 2px dashed #fbbf24 !important;
        outline-offset: -2px !important;
    }

    /* Preview: hide completely to simulate frontend */
    .builder-wrapper.is-preview .canvas-container.mobile .falcon-hide-mobile,
    .builder-wrapper.is-preview .canvas-container.tablet .falcon-hide-tablet,
    .builder-wrapper.is-preview .canvas-container.desktop .falcon-hide-desktop {
        display: none !important;
    }
    /* Alpha/Transparency Checkerboard */
    .checkerboard {
        background-color: #fff;
        background-image: 
            linear-gradient(45deg, #eee 25%, transparent 25%, transparent 75%, #eee 75%, #eee 100%), 
            linear-gradient(45deg, #eee 25%, transparent 25%, transparent 75%, #eee 75%, #eee 100%);
        background-size: 10px 10px;
        background-position: 0 0, 5px 5px;
    }

    /* Modern Slider Style */
    .alpha-slider {
        -webkit-appearance: none;
        width: 100%;
        height: 8px;
        border-radius: 4px;
        outline: none;
        background: linear-gradient(to right, transparent, var(--primary));
    }
    .alpha-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        background: white;
        border: 2px solid var(--primary);
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    /* WordPress Style Pickr (Iris Lookalike) */
    .pcr-app {
        width: 260px !important;
        padding: 12px !important;
        background: #ffffff !important;
        border-radius: 4px !important;
        border: 1px solid #ccc !important;
        box-shadow: 0 1px 15px rgba(0,0,0,0.15) !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .pcr-app .pcr-selection {
        display: flex !important;
        flex-direction: row !important;
        height: 150px !important;
        margin-bottom: 10px !important;
    }
    .pcr-app .pcr-selection .pcr-color-preview {
        display: none !important;
    }
    .pcr-app .pcr-selection .pcr-color-palette {
        width: 200px !important;
        height: 150px !important;
        margin-right: 12px !important;
    }
    .pcr-app .pcr-selection .pcr-picker {
        width: 14px !important;
        height: 14px !important;
    }
    /* Move sliders to the side */
    .pcr-app .pcr-selection .pcr-sliders {
        flex: 1 !important;
        display: flex !important;
        flex-direction: row !important; /* This is tricky as Pickr assumes horizontal */
        justify-content: space-between !important;
        gap: 8px !important;
    }
    .pcr-app .pcr-selection .pcr-hue,
    .pcr-app .pcr-selection .pcr-opacity {
        width: 15px !important;
        height: 100% !important;
        margin: 0 !important;
    }
    /* Interaction & Inputs at bottom */
    .pcr-app .pcr-interaction {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        padding-top: 10px !important;
        border-top: 1px solid #eee !important;
    }
    .pcr-app .pcr-interaction input {
        flex: 1 !important;
        min-width: 80px !important;
        font-family: monospace !important;
        font-size: 11px !important;
        height: 28px !important;
        border: 1px solid #ddd !important;
        border-radius: 3px !important;
    }
    .pcr-app .pcr-interaction .pcr-save,
    .pcr-app .pcr-interaction .pcr-cancel,
    .pcr-app .pcr-interaction .pcr-clear {
        height: 28px !important;
        font-size: 11px !important;
        padding: 0 10px !important;
        border-radius: 3px !important;
    }
    .pcr-app .pcr-interaction .pcr-save { background: #2271b1 !important; }
    .pcr-app .pcr-interaction .pcr-clear { background: #f6f7f7 !important; color: #2271b1 !important; border: 1px solid #2271b1 !important; }

    /* Swatches at the very bottom */
    .pcr-app .pcr-swatches {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 4px !important;
        margin-top: 10px !important;
    }
    .pcr-app .pcr-swatches button {
        width: 22px !important;
        height: 22px !important;
        border-radius: 3px !important;
    }

    /* ===== FalconCMS color-picker skin (.pcr-fnew) — shared across CMS + builder ===== */
@include('falcon-cms::admin.falcon-builder.partials.pcr-fnew')

    .toast-enter-active, .toast-leave-active {
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .toast-enter-from {
        opacity: 0;
        transform: translateX(30px);
    }
    .toast-leave-to {
        opacity: 0;
        transform: translateY(-20px);
    }
    .modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
    .modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

    /* Auto Responsive Columns for Mobile Devices */
    @media (max-width: {{ get_cms_option('theme_small_screen_breakpoint', '800') }}px) {
        .lazy-column, .column-outer {
            flex-basis: 100% !important;
            max-width: 100% !important;
            width: 100% !important;
        }
    }
    /* Compact TinyMCE Fix */
    .tox-tinymce { border-radius: 4px !important; border-color: #e2e8f0 !important; }
    .tox .tox-toolbar__group { padding: 0 2px !important; border: none !important; }
    /* Compact TinyMCE Fix */
    .tox-tinymce { border-radius: 4px !important; border-color: #e2e8f0 !important; }
    .tox .tox-toolbar__group { padding: 0 2px !important; border: none !important; }
    .tox .tox-tbtn { width: 32px !important; height: 32px !important; margin: 0 !important; }
    .tox .tox-tbtn svg { transform: scale(0.85); }
    .tox .tox-tbtn--select { width: auto !important; padding: 0 6px !important; }
    .tox .tox-toolbar { background-color: #f8fafc !important; border-bottom: 1px solid #f1f5f9 !important; }
    .tox .tox-toolbar:last-child { border-bottom: none !important; }
    /* Image Hover Effects */
    .element-image-wrapper img, .element-image img {
        transition: all 0.3s ease-in-out;
    }
    .hover-zoom-in:hover img { transform: scale(1.05); }
    .hover-zoom-out:hover img { transform: scale(0.95); }
    .hover-lift:hover img { transform: translateY(-5px); }
    .hover-shadow:hover img { box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .hover-opacity:hover img { opacity: 0.75; }

    /* Admin Menu Preview Styles */
    .admin-menu-item { position: relative; }
    .admin-submenu {
        position: absolute;
        top: 100%;
        left: 0;
        min-width: 180px;
        background: #fff;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border-radius: 4px;
        padding: 10px 0;
        margin: 0;
        list-style: none;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateY(10px);
        transition: all 0.2s ease-in-out !important;
        z-index: 1000 !important;
        text-align: left;
        border: 1px solid #eee;
    }
    .admin-menu-item:hover > .admin-submenu {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(var(--submenu-space, 10px));
    }
    .admin-submenu .admin-menu-link {
        padding: 8px 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: 100%;
        box-sizing: border-box;
    }
    .admin-menu-arrow { font-size: 9px; margin-left: 4px; transition: transform 0.2s; }
    .admin-menu-item:hover > .admin-menu-link .admin-menu-arrow { transform: rotate(180deg); }

    /* Post Meta canvas — link hover uses CSS vars set on wrapper */
    .post-meta-canvas-wrap .pm-canvas-link { color: var(--pm-tc, #6b7280); text-decoration: none; cursor: pointer; transition: color 0.15s; }
    .post-meta-canvas-wrap .pm-g:hover .pm-canvas-link,
    .post-meta-canvas-wrap .pm-g:hover i { color: var(--pm-lc, #374151); transition: color 0.15s; }

    /* Builder Tab Button Styles */
    .builder-tab-btn {
        transition: flex-grow 0.4s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        white-space: nowrap;
    }
    .builder-tab-btn span {
        animation: builderFadeIn 0.4s ease forwards;
    }
    @keyframes builderFadeIn {
        from { opacity: 0; transform: translateX(-5px); }
        to { opacity: 1; transform: translateX(0); }
    }

    /* ===== Navigator Tree — FB-thread style ===== */
    .nav-branch {
        margin-left: 14px;
        border-left: 2px solid #94a3b8; /* slate-400 — clearly visible spine */
        position: relative;
    }
    .nav-branch-2 {
        border-left-color: #cbd5e1; /* slate-300 — lighter for deeper levels */
    }
    /* Each child row: horizontal connector branching from the spine */
    .nav-leaf {
        position: relative;
        padding-left: 14px !important;
        padding-right: 16px !important;
    }
    .nav-leaf::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        width: 10px;
        height: 2px;
        background: #94a3b8;
        transform: translateY(-50%);
        border-radius: 1px;
        pointer-events: none;
    }
    /* Element rows (nav-branch-2 direct children) have icon+gap that push text far — tighten */
    .nav-branch-2 > .nav-leaf,
    .nav-branch-2 > div > .nav-leaf {
        gap: 6px !important;
    }
    .nav-branch-2 > .nav-leaf > i:first-child,
    .nav-branch-2 > div > .nav-leaf > i:first-child {
        width: 10px !important;
        min-width: 10px !important;
    }
    .nav-branch-2 > .nav-leaf::before,
    .nav-branch-2 > * > .nav-leaf::before {
        background: #cbd5e1;
    }
    /* Last child: clip the spine below the midpoint so it doesn't dangle */
    .nav-branch > *:last-child,
    .nav-branch > * > *:last-child {
        position: relative;
    }
    .nav-branch > .nav-leaf:last-child::after,
    .nav-branch > * > .nav-leaf:last-child::after {
        content: '';
        position: absolute;
        left: -2px;
        top: 50%;
        bottom: 0;
        width: 2px;
        background: white;
        pointer-events: none;
    }

    /* ===== Builder Panel Visibility Enhancements ===== */

    /* 1. Main field labels — 14px, near-black */
    .builder-sidebar label {
        font-size: 14px !important;
        color: #111 !important;
    }
    /* Sub-labels (uppercase grid labels: Top / Right / Bottom / Left etc.) — bigger + black */
    .builder-sidebar label.uppercase {
        font-size: 11px !important;
        color: #222 !important;
        letter-spacing: 0.02em !important;
        text-transform: capitalize !important;
    }
    /* Info/description text under sections */
    .builder-sidebar div.text-slate-500,
    .builder-sidebar p.text-slate-500 {
        color: #333 !important;
        font-size: 12px !important;
    }

    /* 2. Inputs / Selects / Textareas — darker borders */
    .builder-sidebar input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
    .builder-sidebar select,
    .builder-sidebar textarea {
        border-color: #9a9da0 !important;
        color: #111 !important;
    }
    .builder-sidebar input:focus:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
    .builder-sidebar select:focus,
    .builder-sidebar textarea:focus {
        border-color: #0091ea !important;
        box-shadow: 0 0 0 1px #0091ea !important;
    }
    /* Compound field wrappers (input + unit select together) */
    .builder-sidebar .border.border-slate-200.rounded-md,
    .builder-sidebar .border.border-slate-200.overflow-hidden {
        border-color: #9a9da0 !important;
    }
    .builder-sidebar .border.border-slate-200.rounded-md:focus-within,
    .builder-sidebar .border.border-slate-200.overflow-hidden:focus-within {
        border-color: #0091ea !important;
    }

    /* 3. Left sidebar tab bar — bigger font */
    .builder-sidebar span.tracking-widest {
        font-size: 12.5px !important;
    }
    .builder-sidebar > div:first-child .fa-cog {
        font-size: 17px !important;
    }

    /* Toggle / segment button groups — all panels */
    .builder-sidebar .flex.bg-slate-100 > button,
    .builder-sidebar .flex.bg-slate-50 > button {
        font-size: 12.5px !important;
        padding-top: 8px !important;
        padding-bottom: 8px !important;
        font-weight: 600 !important;
        text-transform: capitalize !important;
    }
    /* Active = brand blue */
    .builder-sidebar .flex.bg-slate-100 > button.text-white,
    .builder-sidebar .flex.bg-slate-100 > button.bg-slate-200,
    .builder-sidebar .flex.bg-slate-100 > button.bg-white,
    .builder-sidebar .flex.bg-slate-50 > button.text-white,
    .builder-sidebar .flex.bg-slate-50 > button.bg-white {
        background-color: #2271b1 !important;
        color: #fff !important;
    }
    /* Inactive — neutralize any blue-tinted states to muted gray */
    .builder-sidebar .flex.bg-slate-50 > button:not(.text-white):not(.bg-white) {
        background-color: transparent !important;
        color: #94a3b8 !important;
    }

    /* Device / Element Visibility buttons */
    .builder-sidebar .grid.grid-cols-3.gap-2 > button {
        padding-top: 7px !important;
        padding-bottom: 7px !important;
        font-size: 12px !important;
    }

    /* 4. Icons next to field labels — darker (was near-invisible slate-300/400) */
    .builder-sidebar .flex.justify-between .text-slate-300,
    .builder-sidebar .flex.items-center.mb-2 .text-slate-300,
    .builder-sidebar .flex.items-center.mb-4 .text-slate-300 {
        color: #555 !important;
    }
    .builder-sidebar .flex.justify-between .text-slate-300:hover,
    .builder-sidebar .flex.items-center.mb-2 .text-slate-300:hover,
    .builder-sidebar .flex.items-center.mb-4 .text-slate-300:hover {
        color: rgb(239, 68, 68) !important;
    }
    .builder-sidebar .flex.justify-between i.fa-caret-down,
    .builder-sidebar .flex.items-center.mb-2 i.fa-caret-down {
        color: #555 !important;
    }

    /* ── Ticker: label badge animations ── */
    .lztick-la-blink-dot { animation: lztick-label-pulse 1.8s ease-in-out infinite; }
    .lztick-la-blink-dot::before {
        content: ''; width: 8px; height: 8px; border-radius: 50%;
        background: currentColor; margin-right: 6px; flex-shrink: 0;
        animation: lztick-dot-blink 1s step-end infinite;
    }
    .lztick-la-pulse   { animation: lztick-label-pulse  1.8s ease-in-out infinite; }
    .lztick-la-flash   { animation: lztick-label-flash  1.2s step-end  infinite; }
    .lztick-la-shake   { animation: lztick-label-shake  2.5s ease-in-out infinite; }
    .lztick-la-bounce  { animation: lztick-label-bounce 1.5s ease-in-out infinite; }
    .lztick-la-none    { animation: none; }

    @keyframes lztick-dot-blink  { 0%,100%{opacity:1} 50%{opacity:0} }
    @keyframes lztick-label-pulse { 0%,100%{filter:brightness(1) saturate(1)} 50%{filter:brightness(1.35) saturate(1.6)} }
    @keyframes lztick-label-flash { 0%,49%,100%{opacity:1} 50%,98%{opacity:0.15} }
    @keyframes lztick-label-shake {
        0%,100%{transform:translateX(0)} 10%{transform:translateX(-4px)} 20%{transform:translateX(4px)}
        30%{transform:translateX(-3px)} 40%{transform:translateX(3px)}
        50%{transform:translateX(-2px)} 60%{transform:translateX(2px)} 70%,90%{transform:translateX(0)}
    }
    @keyframes lztick-label-bounce {
        0%,100%{transform:scaleX(1)} 20%{transform:scaleX(1.1)} 40%{transform:scaleX(1)}
        60%{transform:scaleX(1.05)} 80%{transform:scaleX(1)}
    }

    /* ── Ticker: dancing-dots separator ── */
    .lztick-sep-dance { display:inline-flex; align-items:center; gap:3px; margin:0 10px; vertical-align:middle; }
    .lztick-sep-dance > span { display:inline-block; width:4px; height:4px; border-radius:50%; background:currentColor; opacity:.7; }
    .lztick-sep-dance > span:nth-child(1) { animation: lztick-ddot .9s ease-in-out infinite 0s; }
    .lztick-sep-dance > span:nth-child(2) { animation: lztick-ddot .9s ease-in-out infinite .15s; }
    .lztick-sep-dance > span:nth-child(3) { animation: lztick-ddot .9s ease-in-out infinite .3s; }
    @keyframes lztick-ddot { 0%,100%{transform:translateY(0);opacity:.5} 50%{transform:translateY(-4px);opacity:1} }

    /* ── Ticker: text effects on scrolling content ── */
    .lztick-te-glow { text-shadow: 0 0 10px rgba(255,255,255,0.6); }
    .lztick-te-highlight span:not(.lztick-sep):not(.lztick-sep-dance):not(.lztick-sep-dance > span) {
        background: rgba(255,255,255,0.12); padding: 1px 8px; border-radius: 3px;
    }

    /* ── Ticker: live scrolling in the builder canvas (seamless loop of 2 copies) ── */
    @keyframes lztick-canvas-left  { from { transform: translateX(0); }    to { transform: translateX(-50%); } }
    @keyframes lztick-canvas-right { from { transform: translateX(-50%); } to { transform: translateX(0); } }
</style>
