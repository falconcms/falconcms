<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_button] — builder JSON ⇄ shortcode.
 */
final class ButtonElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrTextAnim($a, $s);
        // Content
        self::attrI($a, 'text', $s['text'] ?? 'Button');
        self::attrI($a, 'link_url', $s['linkUrl'] ?? null, '#');
        self::attrI($a, 'link_target', $s['linkTarget'] ?? null, '_self');
        // Button style
        self::attrI($a, 'button_style', $s['buttonStyle'] ?? null, 'default');
        self::attrI($a, 'button_size', $s['buttonSize'] ?? null);
        if ($s['buttonSpan'] ?? false) {
            $a .= ' button_span="yes"';
        }
        // Colors + opacities
        self::attrI($a, 'bg_color', $s['bgColor'] ?? null);
        self::attrI($a, 'bg_color_opacity', $s['bgColorOpacity'] ?? null, 1);
        self::attrI($a, 'color', $s['color'] ?? null);
        self::attrI($a, 'color_opacity', $s['colorOpacity'] ?? null, 1);
        self::attrI($a, 'hover_bg_color', $s['hoverBgColor'] ?? null);
        self::attrI($a, 'hover_bg_color_opacity', $s['hoverBgColorOpacity'] ?? null, 1);
        self::attrI($a, 'hover_color', $s['hoverColor'] ?? null);
        self::attrI($a, 'hover_color_opacity', $s['hoverColorOpacity'] ?? null, 1);
        // Gradient + opacities
        self::attrI($a, 'bg_gradient_start_color', $s['bgGradientStartColor'] ?? null);
        self::attrI($a, 'bg_gradient_start_opacity', $s['bgGradientStartOpacity'] ?? null, 1);
        self::attrI($a, 'bg_gradient_end_color', $s['bgGradientEndColor'] ?? null);
        self::attrI($a, 'bg_gradient_end_opacity', $s['bgGradientEndOpacity'] ?? null, 1);
        self::attrI($a, 'bg_gradient_hover_start_color', $s['bgGradientHoverStartColor'] ?? null);
        self::attrI($a, 'bg_gradient_hover_start_opacity', $s['bgGradientHoverStartOpacity'] ?? null, 1);
        self::attrI($a, 'bg_gradient_hover_end_color', $s['bgGradientHoverEndColor'] ?? null);
        self::attrI($a, 'bg_gradient_hover_end_opacity', $s['bgGradientHoverEndOpacity'] ?? null, 1);
        self::attrI($a, 'bg_gradient_type', $s['bgGradientType'] ?? null);
        self::attrI($a, 'bg_gradient_angle', $s['bgGradientAngle'] ?? null);
        self::attrI($a, 'bg_gradient_start_pos', $s['bgGradientStartPosition'] ?? null);
        self::attrI($a, 'bg_gradient_end_pos', $s['bgGradientEndPosition'] ?? null);
        // Typography
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null);
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null);
        self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
        self::attrI($a, 'text_transform', $s['textTransform'] ?? null);
        // Border
        self::attrI($a, 'border_size_top', $s['borderSizeTop'] ?? null);
        self::attrI($a, 'border_size_right', $s['borderSizeRight'] ?? null);
        self::attrI($a, 'border_size_bottom', $s['borderSizeBottom'] ?? null);
        self::attrI($a, 'border_size_left', $s['borderSizeLeft'] ?? null);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null);
        self::attrI($a, 'border_color_opacity', $s['borderColorOpacity'] ?? null, 1);
        self::attrI($a, 'border_radius', $s['borderRadius'] ?? null);
        // Icon
        self::attrI($a, 'icon', $s['icon'] ?? null);
        self::attrI($a, 'icon_position', $s['iconPosition'] ?? null, 'left');
        // Alignment (+ responsive)
        self::attrI($a, 'align', $s['textAlign'] ?? null);
        self::attrI($a, 'align_tablet', $s['textAlign_tablet'] ?? null);
        self::attrI($a, 'align_mobile', $s['textAlign_mobile'] ?? null);
        // Margin (+ responsive)
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_tablet', $s['marginTop_tablet'] ?? null);
        self::attrI($a, 'margin_top_mobile', $s['marginTop_mobile'] ?? null);
        self::attrI($a, 'margin_right', $s['marginRight'] ?? null);
        self::attrI($a, 'margin_right_tablet', $s['marginRight_tablet'] ?? null);
        self::attrI($a, 'margin_right_mobile', $s['marginRight_mobile'] ?? null);
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_tablet', $s['marginBottom_tablet'] ?? null);
        self::attrI($a, 'margin_bottom_mobile', $s['marginBottom_mobile'] ?? null);
        self::attrI($a, 'margin_left', $s['marginLeft'] ?? null);
        self::attrI($a, 'margin_left_tablet', $s['marginLeft_tablet'] ?? null);
        self::attrI($a, 'margin_left_mobile', $s['marginLeft_mobile'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_top_unit_tablet', $s['marginTopUnit_tablet'] ?? null);
        self::attrI($a, 'margin_top_unit_mobile', $s['marginTopUnit_mobile'] ?? null);
        self::attrI($a, 'margin_right_unit', $s['marginRightUnit'] ?? null, 'px');
        self::attrI($a, 'margin_right_unit_tablet', $s['marginRightUnit_tablet'] ?? null);
        self::attrI($a, 'margin_right_unit_mobile', $s['marginRightUnit_mobile'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom_unit_tablet', $s['marginBottomUnit_tablet'] ?? null);
        self::attrI($a, 'margin_bottom_unit_mobile', $s['marginBottomUnit_mobile'] ?? null);
        self::attrI($a, 'margin_left_unit', $s['marginLeftUnit'] ?? null, 'px');
        self::attrI($a, 'margin_left_unit_tablet', $s['marginLeftUnit_tablet'] ?? null);
        self::attrI($a, 'margin_left_unit_mobile', $s['marginLeftUnit_mobile'] ?? null);
        // Padding (+ responsive)
        self::attrI($a, 'padding_top', $s['paddingTop'] ?? null);
        self::attrI($a, 'padding_top_tablet', $s['paddingTop_tablet'] ?? null);
        self::attrI($a, 'padding_top_mobile', $s['paddingTop_mobile'] ?? null);
        self::attrI($a, 'padding_right', $s['paddingRight'] ?? null);
        self::attrI($a, 'padding_right_tablet', $s['paddingRight_tablet'] ?? null);
        self::attrI($a, 'padding_right_mobile', $s['paddingRight_mobile'] ?? null);
        self::attrI($a, 'padding_bottom', $s['paddingBottom'] ?? null);
        self::attrI($a, 'padding_bottom_tablet', $s['paddingBottom_tablet'] ?? null);
        self::attrI($a, 'padding_bottom_mobile', $s['paddingBottom_mobile'] ?? null);
        self::attrI($a, 'padding_left', $s['paddingLeft'] ?? null);
        self::attrI($a, 'padding_left_tablet', $s['paddingLeft_tablet'] ?? null);
        self::attrI($a, 'padding_left_mobile', $s['paddingLeft_mobile'] ?? null);
        self::attrI($a, 'padding_top_unit', $s['paddingTopUnit'] ?? null, 'px');
        self::attrI($a, 'padding_top_unit_tablet', $s['paddingTopUnit_tablet'] ?? null);
        self::attrI($a, 'padding_top_unit_mobile', $s['paddingTopUnit_mobile'] ?? null);
        self::attrI($a, 'padding_right_unit', $s['paddingRightUnit'] ?? null, 'px');
        self::attrI($a, 'padding_right_unit_tablet', $s['paddingRightUnit_tablet'] ?? null);
        self::attrI($a, 'padding_right_unit_mobile', $s['paddingRightUnit_mobile'] ?? null);
        self::attrI($a, 'padding_bottom_unit', $s['paddingBottomUnit'] ?? null, 'px');
        self::attrI($a, 'padding_bottom_unit_tablet', $s['paddingBottomUnit_tablet'] ?? null);
        self::attrI($a, 'padding_bottom_unit_mobile', $s['paddingBottomUnit_mobile'] ?? null);
        self::attrI($a, 'padding_left_unit', $s['paddingLeftUnit'] ?? null, 'px');
        self::attrI($a, 'padding_left_unit_tablet', $s['paddingLeftUnit_tablet'] ?? null);
        self::attrI($a, 'padding_left_unit_mobile', $s['paddingLeftUnit_mobile'] ?? null);
        // CSS
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_button '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $ts = [
            ...self::textAnimSettings($a),
            // Content
            'text' => $a['text'] ?? 'Button',
            'linkUrl' => $a['link_url'] ?? $a['url'] ?? '#',
            'linkTarget' => $a['link_target'] ?? $a['target'] ?? '_self',
            'useLink' => true,
            // Button style
            'buttonStyle' => $a['button_style'] ?? 'default',
            'buttonSize' => $a['button_size'] ?? null,
            'buttonSpan' => ($a['button_span'] ?? '') === 'yes',
            // Colors + opacities
            'bgColor' => $a['bg_color'] ?? null,
            'bgColorOpacity' => isset($a['bg_color_opacity']) ? (float) $a['bg_color_opacity'] : null,
            'color' => $a['color'] ?? null,
            'colorOpacity' => isset($a['color_opacity']) ? (float) $a['color_opacity'] : null,
            'hoverBgColor' => $a['hover_bg_color'] ?? null,
            'hoverBgColorOpacity' => isset($a['hover_bg_color_opacity']) ? (float) $a['hover_bg_color_opacity'] : null,
            'hoverColor' => $a['hover_color'] ?? null,
            'hoverColorOpacity' => isset($a['hover_color_opacity']) ? (float) $a['hover_color_opacity'] : null,
            // Gradient + opacities
            'bgGradientStartColor' => $a['bg_gradient_start_color'] ?? null,
            'bgGradientStartOpacity' => isset($a['bg_gradient_start_opacity']) ? (float) $a['bg_gradient_start_opacity'] : null,
            'bgGradientEndColor' => $a['bg_gradient_end_color'] ?? null,
            'bgGradientEndOpacity' => isset($a['bg_gradient_end_opacity']) ? (float) $a['bg_gradient_end_opacity'] : null,
            'bgGradientHoverStartColor' => $a['bg_gradient_hover_start_color'] ?? null,
            'bgGradientHoverStartOpacity' => isset($a['bg_gradient_hover_start_opacity']) ? (float) $a['bg_gradient_hover_start_opacity'] : null,
            'bgGradientHoverEndColor' => $a['bg_gradient_hover_end_color'] ?? null,
            'bgGradientHoverEndOpacity' => isset($a['bg_gradient_hover_end_opacity']) ? (float) $a['bg_gradient_hover_end_opacity'] : null,
            'bgGradientType' => $a['bg_gradient_type'] ?? null,
            'bgGradientAngle' => isset($a['bg_gradient_angle']) ? (int) $a['bg_gradient_angle'] : null,
            'bgGradientStartPosition' => isset($a['bg_gradient_start_pos']) ? (int) $a['bg_gradient_start_pos'] : null,
            'bgGradientEndPosition' => isset($a['bg_gradient_end_pos']) ? (int) $a['bg_gradient_end_pos'] : null,
            // Typography
            'fontFamily' => $a['font_family'] ?? 'inherit',
            'fontSize' => $a['font_size'] ?? null,
            'fontWeight' => $a['font_weight'] ?? '600',
            'lineHeight' => $a['line_height'] ?? null,
            'letterSpacing' => $a['letter_spacing'] ?? null,
            'textTransform' => $a['text_transform'] ?? null,
            // Border
            'borderSizeTop' => isset($a['border_size_top']) ? (int) $a['border_size_top'] : null,
            'borderSizeRight' => isset($a['border_size_right']) ? (int) $a['border_size_right'] : null,
            'borderSizeBottom' => isset($a['border_size_bottom']) ? (int) $a['border_size_bottom'] : null,
            'borderSizeLeft' => isset($a['border_size_left']) ? (int) $a['border_size_left'] : null,
            'borderColor' => $a['border_color'] ?? null,
            'borderColorOpacity' => isset($a['border_color_opacity']) ? (float) $a['border_color_opacity'] : null,
            'borderRadius' => isset($a['border_radius']) ? (int) $a['border_radius'] : null,
            // Icon
            'icon' => $a['icon'] ?? null,
            'iconPosition' => $a['icon_position'] ?? 'left',
            // Alignment
            'textAlign' => $a['align'] ?? 'center',
            // Margin
            'marginTop' => self::num($a['margin_top'] ?? 10),
            'marginRight' => self::num($a['margin_right'] ?? 0),
            'marginBottom' => self::num($a['margin_bottom'] ?? 10),
            'marginLeft' => self::num($a['margin_left'] ?? 0),
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginTopUnit_tablet' => $a['margin_top_unit_tablet'] ?? null,
            'marginTopUnit_mobile' => $a['margin_top_unit_mobile'] ?? null,
            'marginRightUnit' => $a['margin_right_unit'] ?? 'px',
            'marginRightUnit_tablet' => $a['margin_right_unit_tablet'] ?? null,
            'marginRightUnit_mobile' => $a['margin_right_unit_mobile'] ?? null,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'marginBottomUnit_tablet' => $a['margin_bottom_unit_tablet'] ?? null,
            'marginBottomUnit_mobile' => $a['margin_bottom_unit_mobile'] ?? null,
            'marginLeftUnit' => $a['margin_left_unit'] ?? 'px',
            'marginLeftUnit_tablet' => $a['margin_left_unit_tablet'] ?? null,
            'marginLeftUnit_mobile' => $a['margin_left_unit_mobile'] ?? null,
            // Padding
            'paddingTop' => self::num($a['padding_top'] ?? 12),
            'paddingRight' => self::num($a['padding_right'] ?? 30),
            'paddingBottom' => self::num($a['padding_bottom'] ?? 12),
            'paddingLeft' => self::num($a['padding_left'] ?? 30),
            'paddingTopUnit' => $a['padding_top_unit'] ?? 'px',
            'paddingTopUnit_tablet' => $a['padding_top_unit_tablet'] ?? null,
            'paddingTopUnit_mobile' => $a['padding_top_unit_mobile'] ?? null,
            'paddingRightUnit' => $a['padding_right_unit'] ?? 'px',
            'paddingRightUnit_tablet' => $a['padding_right_unit_tablet'] ?? null,
            'paddingRightUnit_mobile' => $a['padding_right_unit_mobile'] ?? null,
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingBottomUnit_tablet' => $a['padding_bottom_unit_tablet'] ?? null,
            'paddingBottomUnit_mobile' => $a['padding_bottom_unit_mobile'] ?? null,
            'paddingLeftUnit' => $a['padding_left_unit'] ?? 'px',
            'paddingLeftUnit_tablet' => $a['padding_left_unit_tablet'] ?? null,
            'paddingLeftUnit_mobile' => $a['padding_left_unit_mobile'] ?? null,
            // CSS
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ];
        self::addRespProps($ts, $a, [
            ['textAlign',    'align',          null],
            ['marginTop',    'margin_top',     'num'],
            ['marginRight',  'margin_right',   'num'],
            ['marginBottom', 'margin_bottom',  'num'],
            ['marginLeft',   'margin_left',    'num'],
            ['paddingTop',    'padding_top',    'num'],
            ['paddingRight',  'padding_right',  'num'],
            ['paddingBottom', 'padding_bottom', 'num'],
            ['paddingLeft',   'padding_left',   'num'],
        ]);

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'button', 'settings' => $ts];
    }
}
