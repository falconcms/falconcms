<?php

/**
 * Builder element library, registered on the falcon_builder_elements filter.
 *
 * Loaded last. The add_falcon_filter() calls run while this file loads, so the hook
 * helpers must already be defined, and they register in the order written here.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\PostType;
use Illuminate\Support\Facades\DB;

/**
 * Register Special Text Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['text_block'] = [
        'type' => 'text_block',
        'name' => 'Text Block',
        'icon' => 'fa fa-align-left',
        'template' => 'falcon-cms::frontend.builder.elements.text-block',
        'fields' => [
            // General
            'content' => ['type' => 'wysiwyg', 'label' => 'Content', 'default' => '<p>your content is here...</p>'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16],
            'fontSizeUnit' => ['type' => 'select', 'label' => 'Unit', 'options' => ['px' => 'px', 'em' => 'em', 'rem' => 'rem'], 'default' => 'px'],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Text Align',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'],
                'default' => 'center',
            ],

            // Design - Typography
            'fontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 20],
            'fontSizeUnit' => ['type' => 'text', 'label' => 'Size Unit', 'default' => 'px'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400'],
            'lineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '1.5'],
            'letterSpacing' => ['type' => 'number', 'label' => 'Letter Spacing', 'default' => 0],
            'textTransform' => [
                'type' => 'select',
                'label' => 'Text Transform',
                'options' => ['none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize'],
                'default' => 'none',
            ],

            // Design - Colors
            'color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#333333'],
            'hoverColor' => ['type' => 'color', 'label' => 'Hover Color', 'default' => ''],

            // Design - Spacing
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0],
            'marginLeft' => ['type' => 'number', 'label' => 'Margin Left', 'default' => 0],
            'marginRight' => ['type' => 'number', 'label' => 'Margin Right', 'default' => 0],
            'paddingTop' => ['type' => 'number', 'label' => 'Padding Top', 'default' => 10],
            'paddingRight' => ['type' => 'number', 'label' => 'Padding Right', 'default' => 0],
            'paddingBottom' => ['type' => 'number', 'label' => 'Padding Bottom', 'default' => 10],
            'paddingLeft' => ['type' => 'number', 'label' => 'Padding Left', 'default' => 0],

            // Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
            ],
            'cssClass' => ['type' => 'text', 'default' => ''],
            'cssId' => ['type' => 'text', 'default' => ''],
        ],
    ];

    return $elements;
});

if (!function_exists('falcon_builder_element_defs')) {
    /**
     * The custom builder-element definitions (everything registered on the `falcon_builder_elements`
     * filter), resolved once per request and cached.
     *
     * The front-end renderer needs this for every column, and it used to call
     * apply_falcon_filters('falcon_builder_elements', []) inside the column partial — so on a page
     * with a hundred columns the whole filter chain ran a hundred times, and a plugin that lists
     * rows in its callback (the slider element enumerating every slider, for one) ran that query
     * once per column. The registry is identical all request long, so it is built once here.
     *
     * Pass $fresh = true to rebuild (the admin builder, where elements can be registered after a
     * first read, uses the filter directly and does not go through this cache).
     */
    function falcon_builder_element_defs(bool $fresh = false): array
    {
        static $defs = null;
        if ($defs === null || $fresh) {
            $defs = apply_falcon_filters('falcon_builder_elements', []);
        }

        return is_array($defs) ? $defs : [];
    }
}

/**
 * Register Button Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['button'] = [
        'type' => 'button',
        'name' => 'Button',
        'icon' => 'fas fa-toggle-on',
        'template' => 'falcon-cms::frontend.builder.elements.button',
        'fields' => [
            // General
            'text' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Click Here'],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => '#'],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self',
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center',
            ],

            // Design - Typography
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16, 'tab' => 'design'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '600', 'tab' => 'design'],
            'textTransform' => ['type' => 'select', 'label' => 'Text Transform', 'options' => ['none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize'], 'default' => 'none', 'tab' => 'design'],

            // Design - Colors & Gradients
            'buttonStyle' => ['type' => 'text', 'default' => 'default', 'tab' => 'design'],
            'color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#ffffff', 'tab' => 'design'],
            'bgColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#0091ea', 'tab' => 'design'],
            'hoverColor' => ['type' => 'color', 'label' => 'Text Hover Color', 'default' => '#ffffff', 'tab' => 'design'],
            'hoverBgColor' => ['type' => 'color', 'label' => 'BG Hover Color', 'default' => '#007cc0', 'tab' => 'design'],

            'bgGradientStartColor' => ['type' => 'color', 'default' => '#0091ea', 'tab' => 'design'],
            'bgGradientEndColor' => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientStartPosition' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'bgGradientEndPosition' => ['type' => 'number', 'default' => 100, 'tab' => 'design'],
            'bgGradientType' => ['type' => 'text', 'default' => 'linear', 'tab' => 'design'],
            'bgGradientAngle' => ['type' => 'number', 'default' => 180, 'tab' => 'design'],
            'bgGradientHoverStartColor' => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientHoverEndColor' => ['type' => 'color', 'default' => '#005fa3', 'tab' => 'design'],

            // Design - Spacing & Border
            'paddingTop' => ['type' => 'number', 'label' => 'Padding Top', 'default' => 12, 'tab' => 'design'],
            'paddingBottom' => ['type' => 'number', 'label' => 'Padding Bottom', 'default' => 12, 'tab' => 'design'],
            'paddingLeft' => ['type' => 'number', 'label' => 'Padding Left', 'default' => 30, 'tab' => 'design'],
            'paddingRight' => ['type' => 'number', 'label' => 'Padding Right', 'default' => 30, 'tab' => 'design'],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius', 'default' => 5, 'tab' => 'design'],
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 10, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 10, 'tab' => 'design'],
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design',
            ],
            'borderSizeTop' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeRight' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeBottom' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeLeft' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderColor' => ['type' => 'color', 'default' => '#000000', 'tab' => 'design'],
            'buttonSize' => ['type' => 'text', 'default' => 'medium', 'tab' => 'design'],
            'buttonSpan' => ['type' => 'boolean', 'default' => false, 'tab' => 'design'],
            'icon' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'iconPosition' => ['type' => 'text', 'default' => 'left', 'tab' => 'design'],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ],
    ];

    return $elements;
});

/**
 * Register Menu Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    // This filter is applied many times per render; the menu dropdown only feeds
    // the builder editor, so resolve the list once per request.
    static $menus = null;
    if ($menus === null) {
        $menus = [];
        try {
            $menus = DB::table('navigation_menus')->pluck('name', 'id')->toArray();
        } catch (Exception $e) {
        }
    }

    $elements['menu'] = [
        'type' => 'menu',
        'name' => 'Menu',
        'icon' => 'fa fa-bars',
        'template' => 'falcon-cms::frontend.builder.elements.menu',
        'fields' => [
            // General
            'menuId' => [
                'type' => 'select',
                'label' => 'Select Menu',
                'options' => $menus,
                'default' => count($menus) > 0 ? array_key_first($menus) : '',
                'tab' => 'design',
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'options' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'],
                'default' => 'horizontal',
                'tab' => 'design',
            ],
            'transitionTime' => [
                'type' => 'range',
                'label' => 'Transition Time (s)',
                'default' => 0.3,
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'tab' => 'design',
            ],
            'submenuSpace' => [
                'type' => 'number',
                'label' => 'Space Between Main Menu and Submenu (px)',
                'default' => 10,
                'tab' => 'design',
            ],
            'showArrows' => [
                'type' => 'select',
                'label' => 'Menu Arrows',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
                'tab' => 'design',
            ],

            // Design - Typography
            'fontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit', 'tab' => 'design'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16, 'tab' => 'design'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400', 'tab' => 'design'],
            'lineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '', 'tab' => 'design'],
            'letterSpacing' => ['type' => 'text', 'label' => 'Letter Spacing', 'default' => '', 'tab' => 'design'],
            'textTransform' => ['type' => 'text', 'label' => 'Text Transform', 'default' => 'none', 'tab' => 'design'],

            // Design - Menu Item Styling
            'itemPaddingTop' => ['type' => 'number', 'label' => 'Item Padding Top', 'default' => 0, 'tab' => 'design'],
            'itemPaddingRight' => ['type' => 'number', 'label' => 'Item Padding Right', 'default' => 0, 'tab' => 'design'],
            'itemPaddingBottom' => ['type' => 'number', 'label' => 'Item Padding Bottom', 'default' => 0, 'tab' => 'design'],
            'itemPaddingLeft' => ['type' => 'number', 'label' => 'Item Padding Left', 'default' => 0, 'tab' => 'design'],
            'itemSpacing' => ['type' => 'number', 'label' => 'Item Spacing', 'default' => 0, 'tab' => 'design'],
            'itemBorderRadius' => ['type' => 'number', 'label' => 'Item Border Radius', 'default' => 0, 'tab' => 'design'],
            'itemTransition' => ['type' => 'number', 'label' => 'Item Transition', 'default' => 0.3, 'tab' => 'design'],

            'itemBgColor' => ['type' => 'color', 'label' => 'Item Background Color', 'default' => 'transparent', 'tab' => 'design'],
            'itemBgColorHover' => ['type' => 'color', 'label' => 'Item Background Color Hover', 'default' => 'transparent', 'tab' => 'design'],
            'itemColor' => ['type' => 'color', 'label' => 'Item Text Color', 'default' => '#333333', 'tab' => 'design'],
            'itemColorHover' => ['type' => 'color', 'label' => 'Item Text Color Hover', 'default' => '#0091ea', 'tab' => 'design'],

            // The current page's menu item. The template has always marked it with an
            // .active class, but nothing ever styled it — so the setting people looked for
            // was simply missing rather than broken. Empty means "leave it as the resting
            // colour", which keeps every existing menu looking exactly as it does now.
            'itemColorActive' => ['type' => 'color', 'label' => 'Item Text Color (Active)', 'default' => '', 'tab' => 'design'],
            'itemBgColorActive' => ['type' => 'color', 'label' => 'Item Background Color (Active)', 'default' => '', 'tab' => 'design'],

            'itemBorderSizeTop' => ['type' => 'number', 'label' => 'Item Border Size Top', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeRight' => ['type' => 'number', 'label' => 'Item Border Size Right', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeBottom' => ['type' => 'number', 'label' => 'Item Border Size Bottom', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeLeft' => ['type' => 'number', 'label' => 'Item Border Size Left', 'default' => 0, 'tab' => 'design'],

            'itemBorderSizeTopHover' => ['type' => 'number', 'label' => 'Item Border Size Top Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeRightHover' => ['type' => 'number', 'label' => 'Item Border Size Right Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeBottomHover' => ['type' => 'number', 'label' => 'Item Border Size Bottom Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeLeftHover' => ['type' => 'number', 'label' => 'Item Border Size Left Hover', 'default' => 0, 'tab' => 'design'],

            'itemBorderColor' => ['type' => 'color', 'label' => 'Item Border Color', 'default' => '#eeeeee', 'tab' => 'design'],
            'itemBorderColorHover' => ['type' => 'color', 'label' => 'Item Border Color Hover', 'default' => '#0091ea', 'tab' => 'design'],

            // Design - Sub Menu Styling
            // NOTE: showArrows and submenuSpace are declared once, above, on the design tab.
            // They used to be repeated here as well; PHP keeps the last declaration, so the
            // duplicates quietly overrode the originals and moved both controls to this tab.
            'submenuDirection' => ['type' => 'text', 'label' => 'Expand Direction', 'default' => 'right', 'tab' => 'submenu'],
            'submenuTransition' => ['type' => 'text', 'label' => 'Expand Transition', 'default' => 'fade', 'tab' => 'submenu'],
            'submenuMinWidth' => ['type' => 'text', 'label' => 'Min Width', 'default' => '200px', 'tab' => 'submenu'],
            'submenuMaxWidth' => ['type' => 'text', 'label' => 'Max Width', 'default' => '220px', 'tab' => 'submenu'],

            // Submenu Typography
            'submenuFontFamily' => ['type' => 'text', 'label' => 'Submenu Font Family', 'default' => 'inherit', 'tab' => 'submenu'],
            'submenuFontSize' => ['type' => 'text', 'label' => 'Submenu Font Size', 'default' => '14px', 'tab' => 'submenu'],
            'submenuFontWeight' => ['type' => 'text', 'label' => 'Submenu Font Weight', 'default' => '400', 'tab' => 'submenu'],
            'submenuLineHeight' => ['type' => 'text', 'label' => 'Submenu Line Height', 'default' => '', 'tab' => 'submenu'],
            'submenuLetterSpacing' => ['type' => 'text', 'label' => 'Submenu Letter Spacing', 'default' => '', 'tab' => 'submenu'],
            'submenuTextTransform' => ['type' => 'text', 'label' => 'Submenu Text Transform', 'default' => 'none', 'tab' => 'submenu'],
            'submenuTextAlign' => ['type' => 'text', 'label' => 'Submenu Text Align', 'default' => 'left', 'tab' => 'submenu'],

            // Submenu Item Styling
            'submenuPaddingTop' => ['type' => 'number', 'label' => 'Submenu Padding Top', 'default' => 10, 'tab' => 'submenu'],
            'submenuPaddingRight' => ['type' => 'number', 'label' => 'Submenu Padding Right', 'default' => 20, 'tab' => 'submenu'],
            'submenuPaddingBottom' => ['type' => 'number', 'label' => 'Submenu Padding Bottom', 'default' => 10, 'tab' => 'submenu'],
            'submenuPaddingLeft' => ['type' => 'number', 'label' => 'Submenu Padding Left', 'default' => 20, 'tab' => 'submenu'],

            'submenuBorderRadiusTopLeft' => ['type' => 'number', 'label' => 'Submenu BR TL', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusTopRight' => ['type' => 'number', 'label' => 'Submenu BR TR', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusBottomRight' => ['type' => 'number', 'label' => 'Submenu BR BR', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusBottomLeft' => ['type' => 'number', 'label' => 'Submenu BR BL', 'default' => 4, 'tab' => 'submenu'],

            'submenuBoxShadow' => ['type' => 'text', 'label' => 'Box Shadow', 'default' => 'no', 'tab' => 'submenu'],
            'submenuShadowColor' => ['type' => 'color', 'label' => 'Shadow Color', 'default' => 'rgba(0,0,0,0.12)', 'tab' => 'submenu'],
            'submenuShadowH' => ['type' => 'number', 'label' => 'Shadow H', 'default' => 0, 'tab' => 'submenu'],
            'submenuShadowV' => ['type' => 'number', 'label' => 'Shadow V', 'default' => 15, 'tab' => 'submenu'],
            'submenuShadowBlur' => ['type' => 'number', 'label' => 'Shadow Blur', 'default' => 35, 'tab' => 'submenu'],
            'submenuShadowSpread' => ['type' => 'number', 'label' => 'Shadow Spread', 'default' => 0, 'tab' => 'submenu'],

            'submenuSeparatorColor' => ['type' => 'color', 'label' => 'Separator Color', 'default' => 'rgba(0,0,0,0.05)', 'tab' => 'submenu'],
            'submenuBgColor' => ['type' => 'color', 'label' => 'Submenu BG', 'default' => '#ffffff', 'tab' => 'submenu'],
            'submenuTextColor' => ['type' => 'color', 'label' => 'Submenu Text', 'default' => '#333333', 'tab' => 'submenu'],
            'submenuTextColorHover' => ['type' => 'color', 'label' => 'Submenu Text Hover', 'default' => '#0091ea', 'tab' => 'submenu'],

            // Mobile Menu Styling
            'mobileCollapseBreakpoint' => ['type' => 'text', 'label' => 'Collapse to Mobile Breakpoint', 'default' => 'tablet', 'tab' => 'mobile'],
            'mobileMenuMode' => ['type' => 'text', 'label' => 'Mobile Menu Mode', 'default' => 'collapsed', 'tab' => 'mobile'],
            'mobileMenuExpandMode' => ['type' => 'select', 'label' => 'Mobile Menu Expand Mode', 'default' => 'full-width-static', 'tab' => 'mobile', 'options' => ['full-width-static' => 'Full Width - Static', 'full-width-absolute' => 'Full Width - Absolute', 'sidebar' => 'Sidebar']],
            'mobileMenuSidebarSide' => ['type' => 'select', 'label' => 'Sidebar Side', 'default' => 'left', 'tab' => 'mobile', 'options' => ['left' => 'Left', 'right' => 'Right']],
            'mobileMenuOpeningMode' => ['type' => 'text', 'label' => 'Mobile Menu Opening Mode', 'default' => 'toggle', 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingTop' => ['type' => 'number', 'label' => 'Trigger Padding Top', 'default' => 10, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingRight' => ['type' => 'number', 'label' => 'Trigger Padding Right', 'default' => 15, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingBottom' => ['type' => 'number', 'label' => 'Trigger Padding Bottom', 'default' => 10, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingLeft' => ['type' => 'number', 'label' => 'Trigger Padding Left', 'default' => 15, 'tab' => 'mobile'],
            'mobileMenuTriggerBgColor' => ['type' => 'color', 'label' => 'Trigger Background Color', 'default' => '#ffffff', 'tab' => 'mobile'],
            'mobileMenuTriggerTextColor' => ['type' => 'color', 'label' => 'Trigger Text Color', 'default' => '#333333', 'tab' => 'mobile'],
            'mobileMenuTriggerText' => ['type' => 'text', 'label' => 'Trigger Text', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuTriggerExpandIcon' => ['type' => 'text', 'label' => 'Trigger Expand Icon', 'default' => 'fa-bars', 'tab' => 'mobile'],
            'mobileMenuTriggerCollapseIcon' => ['type' => 'text', 'label' => 'Trigger Collapse Icon', 'default' => 'fa-times', 'tab' => 'mobile'],
            'mobileMenuTriggerFontSize' => ['type' => 'text', 'label' => 'Trigger Font Size', 'default' => '16px', 'tab' => 'mobile'],
            'mobileMenuTriggerHorizontalAlign' => ['type' => 'text', 'label' => 'Trigger Horizontal Align', 'default' => 'flex-start', 'tab' => 'mobile'],

            'mobileMenuItemMinHeight' => ['type' => 'number', 'label' => 'Mobile Menu Item Minimum Height', 'default' => 65, 'tab' => 'mobile'],
            'mobileMenuItemPaddingTop' => ['type' => 'number', 'label' => 'Item Padding Top', 'default' => 12, 'tab' => 'mobile'],
            'mobileMenuItemPaddingBottom' => ['type' => 'number', 'label' => 'Item Padding Bottom', 'default' => 12, 'tab' => 'mobile'],
            'mobileMenuItemPaddingLeft' => ['type' => 'number', 'label' => 'Item Padding Left', 'default' => 20, 'tab' => 'mobile'],
            'mobileMenuItemPaddingRight' => ['type' => 'number', 'label' => 'Item Padding Right', 'default' => 20, 'tab' => 'mobile'],
            'mobileMenuTextAlign' => ['type' => 'text', 'label' => 'Mobile Menu Text Align', 'default' => 'left', 'tab' => 'mobile'],
            'mobileMenuIndentSubmenus' => ['type' => 'text', 'label' => 'Mobile Menu Indent Submenus', 'default' => 'on', 'tab' => 'mobile'],

            'mobileMenuFontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit', 'tab' => 'mobile'],
            'mobileMenuFontSize' => ['type' => 'text', 'label' => 'Font Size', 'default' => '16px', 'tab' => 'mobile'],
            'mobileMenuFontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400', 'tab' => 'mobile'],
            'mobileMenuLineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuLetterSpacing' => ['type' => 'text', 'label' => 'Letter Spacing', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuTextTransform' => ['type' => 'text', 'label' => 'Text Transform', 'default' => 'none', 'tab' => 'mobile'],

            'mobileMenuSeparatorColor' => ['type' => 'color', 'label' => 'Separator Color', 'default' => 'rgba(0,0,0,0.05)', 'tab' => 'mobile'],
            'mobileMenuBgColor' => ['type' => 'color', 'label' => 'Menu Background', 'default' => '#ffffff', 'tab' => 'mobile'],
            'mobileMenuBgColorHover' => ['type' => 'color', 'label' => 'Menu Background Hover', 'default' => '#f8f9fa', 'tab' => 'mobile'],
            'mobileMenuTextColor' => ['type' => 'color', 'label' => 'Menu Text Color', 'default' => '#333333', 'tab' => 'mobile'],
            'mobileMenuTextColorHover' => ['type' => 'color', 'label' => 'Menu Text Hover', 'default' => '#0091ea', 'tab' => 'mobile'],

            // Margins (Simplified per user request)
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0, 'tab' => 'design'],

            // Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design',
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ],
    ];

    return $elements;
});

/**
 * Register Image Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['image'] = [
        'type' => 'image',
        'name' => 'Image',
        'icon' => 'fa fa-image',
        'template' => 'falcon-cms::frontend.builder.elements.image',
        'fields' => [
            // General
            'url' => ['type' => 'media', 'label' => 'Image URL', 'default' => ''],
            'alt' => ['type' => 'text', 'label' => 'Alt Text', 'default' => ''],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => ''],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Link Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self',
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center',
            ],

            // Design - Dimensions
            'width' => ['type' => 'number', 'label' => 'Width', 'default' => '', 'tab' => 'design'],
            'widthUnit' => ['type' => 'text', 'default' => 'px', 'tab' => 'design'],
            'maxWidth' => ['type' => 'number', 'label' => 'Max Width', 'default' => 100, 'tab' => 'design'],
            'maxWidthUnit' => ['type' => 'text', 'default' => '%', 'tab' => 'design'],

            // Design - Spacing & Border
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0, 'tab' => 'design'],
            'marginRight' => ['type' => 'number', 'label' => 'Margin Right', 'default' => 0, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0, 'tab' => 'design'],
            'marginLeft' => ['type' => 'number', 'label' => 'Margin Left', 'default' => 0, 'tab' => 'design'],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius', 'default' => 0, 'tab' => 'design'],
            'borderRadiusUnit' => ['type' => 'text', 'default' => 'px', 'tab' => 'design'],
            'borderSizeTop' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeRight' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeBottom' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeLeft' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderColor' => ['type' => 'color', 'default' => 'transparent', 'tab' => 'design'],
            'hoverType' => ['type' => 'select', 'label' => 'Hover Effect', 'default' => 'none', 'tab' => 'design'],

            // Visibility & Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design',
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ],
    ];

    return $elements;
});

// Supported social platforms: slug => [label, FontAwesome icon]. Used by the Social Icons
// element to generate one URL field per platform (no icon picking) and to render the icons.
if (!function_exists('falcon_social_platforms')) {
    function falcon_social_platforms(): array
    {
        return [
            'behance' => ['label' => 'Behance',     'icon' => 'fa-brands fa-behance',      'color' => '#1769FF'],
            'blogger' => ['label' => 'Blogger',     'icon' => 'fa-brands fa-blogger-b',    'color' => '#FF5722'],
            'bluesky' => ['label' => 'Bluesky',     'icon' => 'fa-brands fa-bluesky',      'color' => '#0085FF'],
            'deviantart' => ['label' => 'Deviantart',  'icon' => 'fa-brands fa-deviantart',   'color' => '#05CC47'],
            'digg' => ['label' => 'Digg',        'icon' => 'fa-brands fa-digg',         'color' => '#1C1C1C'],
            'discord' => ['label' => 'Discord',     'icon' => 'fa-brands fa-discord',      'color' => '#5865F2'],
            'dribbble' => ['label' => 'Dribbble',    'icon' => 'fa-brands fa-dribbble',     'color' => '#EA4C89'],
            'dropbox' => ['label' => 'Dropbox',     'icon' => 'fa-brands fa-dropbox',      'color' => '#0061FF'],
            'email' => ['label' => 'Email',       'icon' => 'fa fa-envelope',            'color' => '#EA4335'],
            'facebook' => ['label' => 'Facebook',    'icon' => 'fa-brands fa-facebook-f',   'color' => '#1877F2'],
            'flickr' => ['label' => 'Flickr',      'icon' => 'fa-brands fa-flickr',       'color' => '#0063DC'],
            'github' => ['label' => 'GitHub',      'icon' => 'fa-brands fa-github',       'color' => '#181717'],
            'instagram' => ['label' => 'Instagram',   'icon' => 'fa-brands fa-instagram',    'color' => '#E4405F'],
            'linkedin' => ['label' => 'LinkedIn',    'icon' => 'fa-brands fa-linkedin-in',  'color' => '#0A66C2'],
            'medium' => ['label' => 'Medium',      'icon' => 'fa-brands fa-medium',       'color' => '#000000'],
            'phone' => ['label' => 'Phone',       'icon' => 'fa fa-phone',               'color' => '#34A853'],
            'pinterest' => ['label' => 'Pinterest',   'icon' => 'fa-brands fa-pinterest-p',  'color' => '#BD081C'],
            'reddit' => ['label' => 'Reddit',      'icon' => 'fa-brands fa-reddit-alien', 'color' => '#FF4500'],
            'snapchat' => ['label' => 'Snapchat',    'icon' => 'fa-brands fa-snapchat',     'color' => '#FFFC00'],
            'soundcloud' => ['label' => 'SoundCloud',  'icon' => 'fa-brands fa-soundcloud',   'color' => '#FF5500'],
            'spotify' => ['label' => 'Spotify',     'icon' => 'fa-brands fa-spotify',      'color' => '#1DB954'],
            'telegram' => ['label' => 'Telegram',    'icon' => 'fa-brands fa-telegram',     'color' => '#26A5E4'],
            'tiktok' => ['label' => 'TikTok',      'icon' => 'fa-brands fa-tiktok',       'color' => '#000000'],
            'tumblr' => ['label' => 'Tumblr',      'icon' => 'fa-brands fa-tumblr',       'color' => '#36465D'],
            'twitch' => ['label' => 'Twitch',      'icon' => 'fa-brands fa-twitch',       'color' => '#9146FF'],
            'vimeo' => ['label' => 'Vimeo',       'icon' => 'fa-brands fa-vimeo-v',      'color' => '#1AB7EA'],
            'website' => ['label' => 'Website',     'icon' => 'fa fa-globe',               'color' => '#2271b1'],
            'whatsapp' => ['label' => 'WhatsApp',    'icon' => 'fa-brands fa-whatsapp',     'color' => '#25D366'],
            'wordpress' => ['label' => 'WordPress',   'icon' => 'fa-brands fa-wordpress',    'color' => '#21759B'],
            'x_twitter' => ['label' => 'X (Twitter)', 'icon' => 'fa-brands fa-x-twitter',    'color' => '#000000'],
            'youtube' => ['label' => 'YouTube',     'icon' => 'fa-brands fa-youtube',      'color' => '#FF0000'],
        ];
    }
}

// Social Icons — one URL field per platform (General tab). Fill a field and that platform's
// icon shows on the front-end. No icon picking; the icon is fixed per platform.
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $fields = [];
    foreach (falcon_social_platforms() as $key => $p) {
        // social_icon / social_color / social_label: tell the live canvas the fixed icon, brand colour and name.
        $fields[$key] = ['type' => 'text', 'label' => $p['label'].' Link', 'tab' => 'general', 'default' => '',
            'social_icon' => $p['icon'], 'social_color' => $p['color'] ?? '#2271b1', 'social_label' => $p['label']];
    }
    // Design + behaviour
    $fields['target'] = ['type' => 'select', 'label' => 'Open Links In', 'tab' => 'design',
        'options' => ['_blank' => 'New Window', '_self' => 'Same Window'], 'default' => '_blank'];
    $fields['shape'] = ['type' => 'select', 'label' => 'Shape', 'tab' => 'design',
        'options' => ['circle' => 'Circle', 'rounded' => 'Rounded', 'square' => 'Square'], 'default' => 'circle'];
    // Boxed Style: Default/Yes = icons sit in a coloured box; No = plain coloured icons (no box).
    $fields['boxedStyle'] = ['type' => 'radio', 'label' => 'Boxed Style', 'tab' => 'design',
        'options' => ['default' => 'Default', 'yes' => 'Yes', 'no' => 'No'], 'default' => 'default'];
    // Color Type: Default = theme colours; Custom = pick your own; Brand = each platform's official colour.
    $fields['colorType'] = ['type' => 'select', 'label' => 'Color Type', 'tab' => 'design',
        'options' => ['default' => 'Default', 'custom' => 'Custom Colors', 'brand' => 'Brand Colors'], 'default' => 'default'];
    $fields['boxSize'] = ['type' => 'number', 'label' => 'Box Size',  'default' => 38, 'min' => 0, 'tab' => 'design',
        'condition' => ['field' => 'boxedStyle', 'value' => 'no', 'operator' => '!=']];
    $fields['iconSize'] = ['type' => 'number', 'label' => 'Icon Size', 'default' => 18, 'min' => 0, 'tab' => 'design'];
    $fields['gap'] = ['type' => 'number', 'label' => 'Gap',       'default' => 10, 'min' => 0, 'tab' => 'design'];
    $fields['align'] = ['type' => 'select', 'label' => 'Alignment', 'tab' => 'design', 'responsive' => true,
        'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'default' => 'center'];
    // Tooltip showing the platform name on hover. Default = Top; None = no tooltip.
    $fields['tooltipPosition'] = ['type' => 'select', 'label' => 'Tooltip Position', 'tab' => 'design',
        'options' => ['default' => 'Default', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right', 'none' => 'None'], 'default' => 'default'];
    // Colour pickers only matter for "Custom Colors".
    $fields['iconColor'] = ['type' => 'color', 'label' => 'Icon Color',       'default' => '#ffffff', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['bgColor'] = ['type' => 'color', 'label' => 'Background',        'default' => '#2271b1', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['iconHoverColor'] = ['type' => 'color', 'label' => 'Icon Hover Color', 'default' => '#ffffff', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['bgHoverColor'] = ['type' => 'color', 'label' => 'Hover Background',  'default' => '#135e96', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['margin'] = ['type' => 'dimensions', 'label' => 'Margin', 'unit' => 'px', 'tab' => 'design'];
    $fields['visibility'] = ['type' => 'object', 'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true], 'tab' => 'design'];

    $elements['social_icons'] = [
        'type' => 'social_icons',
        'name' => 'Social Icons',
        'icon' => 'fa fa-share-alt',
        'template' => 'falcon-cms::frontend.builder.elements.social-icons',
        'fields' => $fields,
    ];

    return $elements;
});

/**
 * Register the Advanced Search element for Falcon Builder.
 * A smart search bar: choose which post type to search, optional live (AJAX)
 * results dropdown, and an optional category dropdown inside the bar.
 */
add_falcon_filter('falcon_builder_elements', function ($elements) {
    // Dynamic post-type options (active types). Multi-select; none selected = all content.
    // Resolved once per request — this filter runs many times per render and the
    // options only feed the builder editor UI.
    static $ptOptions = null;
    if ($ptOptions === null) {
        $ptOptions = [];
        try {
            foreach (PostType::where('is_active', true)->orderBy('name')->get() as $pt) {
                $ptOptions[$pt->slug] = $pt->name;
            }
        } catch (Throwable $e) {
            $ptOptions = ['post' => 'Posts', 'page' => 'Pages'];
        }
    }

    $elements['advanced_search'] = [
        'type' => 'advanced_search',
        'name' => 'Advanced Search',
        'icon' => 'fa fa-magnifying-glass',
        'template' => 'falcon-cms::frontend.builder.elements.advanced-search',
        'fields' => [
            // ── General ──
            'searchPostType' => ['type' => 'multiselect', 'label' => 'Search In (none = all content)', 'tab' => 'general', 'options' => $ptOptions, 'default' => [], 'placeholder' => 'All content (select post types)'],
            'placeholder' => ['type' => 'text', 'label' => 'Placeholder Text', 'tab' => 'general', 'default' => 'Search...'],
            'enableLiveSearch' => ['type' => 'toggle', 'label' => 'Live Search (AJAX results)', 'tab' => 'general', 'default' => true],
            'enableCategoryDropdown' => ['type' => 'toggle', 'label' => 'Show Category Dropdown', 'tab' => 'general', 'default' => false],
            'showButton' => ['type' => 'toggle', 'label' => 'Show Search Button', 'tab' => 'general', 'default' => true],
            'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'tab' => 'general', 'default' => 'Search', 'condition' => ['field' => 'showButton', 'value' => true]],

            // ── Design ──
            'accentColor' => ['type' => 'color', 'label' => 'Accent Color', 'tab' => 'design', 'default' => '#0091ea'],
            'bgColor' => ['type' => 'color', 'label' => 'Background', 'tab' => 'design', 'default' => '#ffffff'],
            'textColor' => ['type' => 'color', 'label' => 'Field Text Color', 'tab' => 'design', 'default' => '#1d2327'],
            'placeholderColor' => ['type' => 'color', 'label' => 'Placeholder Color', 'tab' => 'design', 'default' => '#9ca3af'],
            'dropdownTextColor' => ['type' => 'color', 'label' => 'Dropdown Text Color', 'tab' => 'design', 'default' => '#1d2327'],
            'dropdownBgColor' => ['type' => 'color', 'label' => 'Dropdown Background', 'tab' => 'design', 'default' => '#ffffff'],
            'borderColor' => ['type' => 'color', 'label' => 'Border Color', 'tab' => 'design', 'default' => '#e5e7eb'],
            'height' => ['type' => 'number', 'label' => 'Height (px)', 'tab' => 'design', 'default' => 46, 'min' => 28],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius (px)', 'tab' => 'design', 'default' => 6, 'min' => 0],
            'maxWidth' => ['type' => 'number', 'label' => 'Max Width (px, 0 = full)', 'tab' => 'design', 'default' => 0, 'min' => 0],
            'align' => ['type' => 'select', 'label' => 'Alignment', 'tab' => 'design', 'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'default' => 'flex-start'],

            // ── Extras ──
            'visibility' => ['type' => 'object', 'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true], 'tab' => 'design'],
        ],
    ];

    return $elements;
});
