<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_menu] — builder JSON ⇄ shortcode.
 */
final class MenuElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        // General
        self::attrI($a, 'menu_id', $s['menuId'] ?? null);
        self::attrI($a, 'layout', $s['layout'] ?? null, 'horizontal');
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'item_transition', $s['itemTransition'] ?? null);
        self::attrI($a, 'item_transition_ms', $s['itemTransitionMs'] ?? null);
        self::attrI($a, 'submenu_space', $s['submenuSpace'] ?? null, 10);
        $aso = $s['arrowScopeObj'] ?? [];
        if (!($aso['main'] ?? true)) {
            $a .= ' arrow_main="no"';
        }
        if ($aso['active'] ?? false) {
            $a .= ' arrow_active="yes"';
        }
        if ($aso['submenu'] ?? false) {
            $a .= ' arrow_submenu="yes"';
        }
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        // Design
        self::attrI($a, 'min_height', $s['minHeight'] ?? null);
        self::attrI($a, 'align_items', $s['alignItems'] ?? null, 'flex-start');
        self::attrI($a, 'justification', $s['justification'] ?? null, 'flex-start');
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null, 'inherit');
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null);
        self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
        self::attrI($a, 'text_transform', $s['textTransform'] ?? null);
        self::attrI($a, 'item_padding_top', $s['itemPaddingTop'] ?? null);
        self::attrI($a, 'item_padding_right', $s['itemPaddingRight'] ?? null);
        self::attrI($a, 'item_padding_bottom', $s['itemPaddingBottom'] ?? null);
        self::attrI($a, 'item_padding_left', $s['itemPaddingLeft'] ?? null);
        self::attrI($a, 'item_spacing', $s['itemSpacing'] ?? null);
        self::attrI($a, 'item_border_radius', $s['itemBorderRadius'] ?? null);
        self::attrI($a, 'item_bg_color', $s['itemBgColor'] ?? null);
        self::attrI($a, 'item_bg_color_hover', $s['itemBgColorHover'] ?? null);
        self::attrI($a, 'item_color', $s['itemColor'] ?? null);
        self::attrI($a, 'item_color_hover', $s['itemColorHover'] ?? null);
        self::attrI($a, 'item_border_top', $s['itemBorderSizeTop'] ?? null);
        self::attrI($a, 'item_border_right', $s['itemBorderSizeRight'] ?? null);
        self::attrI($a, 'item_border_bottom', $s['itemBorderSizeBottom'] ?? null);
        self::attrI($a, 'item_border_left', $s['itemBorderSizeLeft'] ?? null);
        self::attrI($a, 'item_border_color', $s['itemBorderColor'] ?? null);
        self::attrI($a, 'item_border_top_h', $s['itemBorderSizeTopHover'] ?? null);
        self::attrI($a, 'item_border_right_h', $s['itemBorderSizeRightHover'] ?? null);
        self::attrI($a, 'item_border_bottom_h', $s['itemBorderSizeBottomHover'] ?? null);
        self::attrI($a, 'item_border_left_h', $s['itemBorderSizeLeftHover'] ?? null);
        self::attrI($a, 'item_border_color_h', $s['itemBorderColorHover'] ?? null);
        self::attrI($a, 'icon_position', $s['menuIconPosition'] ?? null, 'left');
        self::attrI($a, 'icon_gap', $s['menuIconGap'] ?? null);
        // Submenu
        self::attrI($a, 'show_arrows', $s['showArrows'] ?? null, 'yes');
        self::attrI($a, 'submenu_direction', $s['submenuDirection'] ?? null, 'right');
        self::attrI($a, 'submenu_transition', $s['submenuTransition'] ?? null, 'fade');
        self::attrI($a, 'submenu_min_width', $s['submenuMinWidth'] ?? null);
        self::attrI($a, 'submenu_max_width', $s['submenuMaxWidth'] ?? null);
        self::attrI($a, 'sub_sub_direction', $s['subSubMenuDirection'] ?? null, 'right');
        self::attrI($a, 'sub_sub_offset', $s['subSubMenuOffset'] ?? null, 5);
        self::attrI($a, 'submenu_font_family', $s['submenuFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'submenu_font_size', $s['submenuFontSize'] ?? null);
        self::attrI($a, 'submenu_line_height', $s['submenuLineHeight'] ?? null);
        self::attrI($a, 'submenu_letter_sp', $s['submenuLetterSpacing'] ?? null);
        self::attrI($a, 'submenu_text_transform', $s['submenuTextTransform'] ?? null);
        self::attrI($a, 'submenu_text_align', $s['submenuTextAlign'] ?? null);
        self::attrI($a, 'submenu_pt', $s['submenuPaddingTop'] ?? null);
        self::attrI($a, 'submenu_pr', $s['submenuPaddingRight'] ?? null);
        self::attrI($a, 'submenu_pb', $s['submenuPaddingBottom'] ?? null);
        self::attrI($a, 'submenu_pl', $s['submenuPaddingLeft'] ?? null);
        self::attrI($a, 'submenu_radius_tl', $s['submenuBorderRadiusTopLeft'] ?? null);
        self::attrI($a, 'submenu_radius_tr', $s['submenuBorderRadiusTopRight'] ?? null);
        self::attrI($a, 'submenu_radius_br', $s['submenuBorderRadiusBottomRight'] ?? null);
        self::attrI($a, 'submenu_radius_bl', $s['submenuBorderRadiusBottomLeft'] ?? null);
        self::attrI($a, 'submenu_shadow', $s['submenuBoxShadow'] ?? null, 'no');
        self::attrI($a, 'submenu_shadow_color', $s['submenuShadowColor'] ?? null);
        self::attrI($a, 'submenu_shadow_h', $s['submenuShadowH'] ?? null);
        self::attrI($a, 'submenu_shadow_v', $s['submenuShadowV'] ?? null);
        self::attrI($a, 'submenu_shadow_blur', $s['submenuShadowBlur'] ?? null);
        self::attrI($a, 'submenu_shadow_spread', $s['submenuShadowSpread'] ?? null);
        self::attrI($a, 'submenu_thumb_w', $s['submenuThumbWidth'] ?? null);
        self::attrI($a, 'submenu_thumb_h', $s['submenuThumbHeight'] ?? null);
        self::attrI($a, 'submenu_sep_color', $s['submenuSeparatorColor'] ?? null);
        self::attrI($a, 'submenu_bg_color', $s['submenuBgColor'] ?? null);
        self::attrI($a, 'submenu_text_color', $s['submenuTextColor'] ?? null);
        self::attrI($a, 'submenu_text_color_h', $s['submenuTextColorHover'] ?? null);
        // Mobile
        self::attrI($a, 'mobile_breakpoint', $s['mobileCollapseBreakpoint'] ?? null, 'tablet');
        self::attrI($a, 'mobile_mode', $s['mobileMenuMode'] ?? null, 'collapsed');
        self::attrI($a, 'mobile_expand_mode', $s['mobileMenuExpandMode'] ?? null, 'full-width-static');
        self::attrI($a, 'mobile_sidebar_side', $s['mobileMenuSidebarSide'] ?? null, 'left');
        self::attrI($a, 'mobile_trigger_pt', $s['mobileMenuTriggerPaddingTop'] ?? null, 10);
        self::attrI($a, 'mobile_trigger_pr', $s['mobileMenuTriggerPaddingRight'] ?? null, 15);
        self::attrI($a, 'mobile_trigger_pb', $s['mobileMenuTriggerPaddingBottom'] ?? null, 10);
        self::attrI($a, 'mobile_trigger_pl', $s['mobileMenuTriggerPaddingLeft'] ?? null, 15);
        self::attrI($a, 'mobile_trigger_bg', $s['mobileMenuTriggerBgColor'] ?? null);
        self::attrI($a, 'mobile_trigger_color', $s['mobileMenuTriggerTextColor'] ?? null);
        self::attrI($a, 'mobile_trigger_text', $s['mobileMenuTriggerText'] ?? null);
        self::attrI($a, 'mobile_expand_icon', $s['mobileMenuTriggerExpandIcon'] ?? null);
        self::attrI($a, 'mobile_collapse_icon', $s['mobileMenuTriggerCollapseIcon'] ?? null);
        self::attrI($a, 'mobile_trigger_fs', $s['mobileMenuTriggerFontSize'] ?? null);
        self::attrI($a, 'mobile_trigger_align', $s['mobileMenuTriggerHorizontalAlign'] ?? null, 'flex-start');
        self::attrI($a, 'mobile_item_min_h', $s['mobileMenuItemMinHeight'] ?? null);
        self::attrI($a, 'mobile_item_pt', $s['mobileMenuItemPaddingTop'] ?? null);
        self::attrI($a, 'mobile_item_pr', $s['mobileMenuItemPaddingRight'] ?? null);
        self::attrI($a, 'mobile_item_pb', $s['mobileMenuItemPaddingBottom'] ?? null);
        self::attrI($a, 'mobile_item_pl', $s['mobileMenuItemPaddingLeft'] ?? null);
        self::attrI($a, 'mobile_text_align', $s['mobileMenuTextAlign'] ?? null, 'left');
        self::attrI($a, 'mobile_indent', $s['mobileMenuIndentSubmenus'] ?? null, 'on');
        self::attrI($a, 'mobile_font_family', $s['mobileMenuFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'mobile_font_size', $s['mobileMenuFontSize'] ?? null);
        self::attrI($a, 'mobile_font_weight', $s['mobileMenuFontWeight'] ?? null);
        self::attrI($a, 'mobile_line_height', $s['mobileMenuLineHeight'] ?? null);
        self::attrI($a, 'mobile_letter_sp', $s['mobileMenuLetterSpacing'] ?? null);
        self::attrI($a, 'mobile_text_transform', $s['mobileMenuTextTransform'] ?? null, 'none');
        self::attrI($a, 'mobile_separator', $s['mobileSeparatorEnabled'] ?? null, 'yes');
        self::attrI($a, 'mobile_sep_color', $s['mobileMenuSeparatorColor'] ?? null);
        self::attrI($a, 'mobile_bg_color', $s['mobileMenuBgColor'] ?? null);
        self::attrI($a, 'mobile_bg_color_h', $s['mobileMenuBgColorHover'] ?? null);
        self::attrI($a, 'mobile_text_color', $s['mobileMenuTextColor'] ?? null);
        self::attrI($a, 'mobile_text_color_h', $s['mobileMenuTextColorHover'] ?? null);

        return '[falcon_menu '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $aso = [
            'main' => ($a['arrow_main'] ?? '') !== 'no',
            'active' => ($a['arrow_active'] ?? '') === 'yes',
            'submenu' => ($a['arrow_submenu'] ?? '') === 'yes',
        ];

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'menu', 'settings' => [
            'menuId' => $a['menu_id'] ?? null,
            'layout' => $a['layout'] ?? 'horizontal',
            'marginTop' => self::num($a['margin_top'] ?? null),
            'marginBottom' => self::num($a['margin_bottom'] ?? null),
            'itemTransition' => isset($a['item_transition']) ? (float) $a['item_transition'] : null,
            'itemTransitionMs' => isset($a['item_transition_ms']) ? (int) $a['item_transition_ms'] : null,
            'submenuSpace' => isset($a['submenu_space']) ? (int) $a['submenu_space'] : 10,
            'arrowScopeObj' => $aso,
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'minHeight' => isset($a['min_height']) ? (int) $a['min_height'] : null,
            'alignItems' => $a['align_items'] ?? 'flex-start',
            'justification' => $a['justification'] ?? 'flex-start',
            'fontFamily' => $a['font_family'] ?? 'inherit',
            'fontSize' => $a['font_size'] ?? null,
            'fontWeight' => $a['font_weight'] ?? null,
            'lineHeight' => $a['line_height'] ?? null,
            'letterSpacing' => $a['letter_spacing'] ?? null,
            'textTransform' => $a['text_transform'] ?? null,
            'itemPaddingTop' => self::num($a['item_padding_top'] ?? null),
            'itemPaddingRight' => self::num($a['item_padding_right'] ?? null),
            'itemPaddingBottom' => self::num($a['item_padding_bottom'] ?? null),
            'itemPaddingLeft' => self::num($a['item_padding_left'] ?? null),
            'itemSpacing' => self::num($a['item_spacing'] ?? null),
            'itemBorderRadius' => self::num($a['item_border_radius'] ?? null),
            'itemBgColor' => $a['item_bg_color'] ?? null,
            'itemBgColorHover' => $a['item_bg_color_hover'] ?? null,
            'itemColor' => $a['item_color'] ?? null,
            'itemColorHover' => $a['item_color_hover'] ?? null,
            'itemBorderSizeTop' => self::num($a['item_border_top'] ?? null),
            'itemBorderSizeRight' => self::num($a['item_border_right'] ?? null),
            'itemBorderSizeBottom' => self::num($a['item_border_bottom'] ?? null),
            'itemBorderSizeLeft' => self::num($a['item_border_left'] ?? null),
            'itemBorderColor' => $a['item_border_color'] ?? null,
            'itemBorderSizeTopHover' => self::num($a['item_border_top_h'] ?? null),
            'itemBorderSizeRightHover' => self::num($a['item_border_right_h'] ?? null),
            'itemBorderSizeBottomHover' => self::num($a['item_border_bottom_h'] ?? null),
            'itemBorderSizeLeftHover' => self::num($a['item_border_left_h'] ?? null),
            'itemBorderColorHover' => $a['item_border_color_h'] ?? null,
            'menuIconPosition' => $a['icon_position'] ?? 'left',
            'menuIconGap' => isset($a['icon_gap']) ? (int) $a['icon_gap'] : null,
            'showArrows' => $a['show_arrows'] ?? 'yes',
            'submenuDirection' => $a['submenu_direction'] ?? 'right',
            'submenuTransition' => $a['submenu_transition'] ?? 'fade',
            'submenuMinWidth' => $a['submenu_min_width'] ?? null,
            'submenuMaxWidth' => $a['submenu_max_width'] ?? null,
            'subSubMenuDirection' => $a['sub_sub_direction'] ?? 'right',
            'subSubMenuOffset' => isset($a['sub_sub_offset']) ? (int) $a['sub_sub_offset'] : 5,
            'submenuFontFamily' => $a['submenu_font_family'] ?? 'inherit',
            'submenuFontSize' => $a['submenu_font_size'] ?? null,
            'submenuLineHeight' => $a['submenu_line_height'] ?? null,
            'submenuLetterSpacing' => $a['submenu_letter_sp'] ?? null,
            'submenuTextTransform' => $a['submenu_text_transform'] ?? null,
            'submenuTextAlign' => $a['submenu_text_align'] ?? null,
            'submenuPaddingTop' => self::num($a['submenu_pt'] ?? null),
            'submenuPaddingRight' => self::num($a['submenu_pr'] ?? null),
            'submenuPaddingBottom' => self::num($a['submenu_pb'] ?? null),
            'submenuPaddingLeft' => self::num($a['submenu_pl'] ?? null),
            'submenuBorderRadiusTopLeft' => self::num($a['submenu_radius_tl'] ?? null),
            'submenuBorderRadiusTopRight' => self::num($a['submenu_radius_tr'] ?? null),
            'submenuBorderRadiusBottomRight' => self::num($a['submenu_radius_br'] ?? null),
            'submenuBorderRadiusBottomLeft' => self::num($a['submenu_radius_bl'] ?? null),
            'submenuBoxShadow' => $a['submenu_shadow'] ?? 'no',
            'submenuShadowColor' => $a['submenu_shadow_color'] ?? null,
            'submenuShadowH' => self::num($a['submenu_shadow_h'] ?? null),
            'submenuShadowV' => self::num($a['submenu_shadow_v'] ?? null),
            'submenuShadowBlur' => self::num($a['submenu_shadow_blur'] ?? null),
            'submenuShadowSpread' => self::num($a['submenu_shadow_spread'] ?? null),
            'submenuThumbWidth' => $a['submenu_thumb_w'] ?? null,
            'submenuThumbHeight' => $a['submenu_thumb_h'] ?? null,
            'submenuSeparatorColor' => $a['submenu_sep_color'] ?? null,
            'submenuBgColor' => $a['submenu_bg_color'] ?? null,
            'submenuTextColor' => $a['submenu_text_color'] ?? null,
            'submenuTextColorHover' => $a['submenu_text_color_h'] ?? null,
            'mobileCollapseBreakpoint' => $a['mobile_breakpoint'] ?? 'tablet',
            'mobileMenuMode' => $a['mobile_mode'] ?? 'collapsed',
            'mobileMenuExpandMode' => $a['mobile_expand_mode'] ?? 'full-width-static',
            'mobileMenuSidebarSide' => $a['mobile_sidebar_side'] ?? 'left',
            'mobileMenuTriggerPaddingTop' => self::num($a['mobile_trigger_pt'] ?? 10),
            'mobileMenuTriggerPaddingRight' => self::num($a['mobile_trigger_pr'] ?? 15),
            'mobileMenuTriggerPaddingBottom' => self::num($a['mobile_trigger_pb'] ?? 10),
            'mobileMenuTriggerPaddingLeft' => self::num($a['mobile_trigger_pl'] ?? 15),
            'mobileMenuTriggerBgColor' => $a['mobile_trigger_bg'] ?? null,
            'mobileMenuTriggerTextColor' => $a['mobile_trigger_color'] ?? null,
            'mobileMenuTriggerText' => $a['mobile_trigger_text'] ?? null,
            'mobileMenuTriggerExpandIcon' => $a['mobile_expand_icon'] ?? null,
            'mobileMenuTriggerCollapseIcon' => $a['mobile_collapse_icon'] ?? null,
            'mobileMenuTriggerFontSize' => $a['mobile_trigger_fs'] ?? null,
            'mobileMenuTriggerHorizontalAlign' => $a['mobile_trigger_align'] ?? 'flex-start',
            'mobileMenuItemMinHeight' => isset($a['mobile_item_min_h']) ? (int) $a['mobile_item_min_h'] : null,
            'mobileMenuItemPaddingTop' => self::num($a['mobile_item_pt'] ?? null),
            'mobileMenuItemPaddingRight' => self::num($a['mobile_item_pr'] ?? null),
            'mobileMenuItemPaddingBottom' => self::num($a['mobile_item_pb'] ?? null),
            'mobileMenuItemPaddingLeft' => self::num($a['mobile_item_pl'] ?? null),
            'mobileMenuTextAlign' => $a['mobile_text_align'] ?? 'left',
            'mobileMenuIndentSubmenus' => $a['mobile_indent'] ?? 'on',
            'mobileMenuFontFamily' => $a['mobile_font_family'] ?? 'inherit',
            'mobileMenuFontSize' => $a['mobile_font_size'] ?? null,
            'mobileMenuFontWeight' => $a['mobile_font_weight'] ?? null,
            'mobileMenuLineHeight' => $a['mobile_line_height'] ?? null,
            'mobileMenuLetterSpacing' => $a['mobile_letter_sp'] ?? null,
            'mobileMenuTextTransform' => $a['mobile_text_transform'] ?? 'none',
            'mobileSeparatorEnabled' => $a['mobile_separator'] ?? 'yes',
            'mobileMenuSeparatorColor' => $a['mobile_sep_color'] ?? null,
            'mobileMenuBgColor' => $a['mobile_bg_color'] ?? null,
            'mobileMenuBgColorHover' => $a['mobile_bg_color_h'] ?? null,
            'mobileMenuTextColor' => $a['mobile_text_color'] ?? null,
            'mobileMenuTextColorHover' => $a['mobile_text_color_h'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
