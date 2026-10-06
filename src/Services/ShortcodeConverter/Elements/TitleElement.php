<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_title] — builder JSON ⇄ shortcode.
 */
final class TitleElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        // Typography
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_size_unit', $s['fontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null);
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null);
        self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
        self::attrI($a, 'text_transform', $s['textTransform'] ?? null, 'none');
        self::attrI($a, 'html_tag', $s['htmlTag'] ?? null, 'h2');
        // Alignment
        self::attrI($a, 'align', $s['textAlign'] ?? null);
        self::attrI($a, 'align_tablet', $s['textAlign_tablet'] ?? null);
        self::attrI($a, 'align_mobile', $s['textAlign_mobile'] ?? null);
        // Color / Gradient
        self::attrI($a, 'color', $s['titleColor'] ?? null);
        self::attrI($a, 'title_hover_color', $s['titleHoverColor'] ?? null);
        self::attrI($a, 'use_gradient', (!empty($s['useGradient']) ? 'yes' : null));
        self::attrI($a, 'gradient_angle', $s['gradientAngle'] ?? null);
        self::attrI($a, 'gradient_start', $s['gradientStartColor'] ?? null);
        self::attrI($a, 'gradient_end', $s['gradientEndColor'] ?? null);
        // Separator
        self::attrI($a, 'separator', $s['separator'] ?? null, 'default');
        self::attrI($a, 'separator_color', $s['separatorColor'] ?? null);
        self::attrI($a, 'divider_width', $s['dividerWidth'] ?? null, 60);
        self::attrI($a, 'divider_height', $s['dividerHeight'] ?? null, 3);
        self::attrI($a, 'separator_spacing', $s['separatorSpacing'] ?? null, 20);
        // Text shadow
        self::attrI($a, 'text_shadow', (!empty($s['textShadow']) ? 'yes' : null));
        self::attrI($a, 'text_shadow_h', $s['textShadowH'] ?? null);
        self::attrI($a, 'text_shadow_v', $s['textShadowV'] ?? null);
        self::attrI($a, 'text_shadow_blur', $s['textShadowBlur'] ?? null);
        self::attrI($a, 'text_shadow_color', $s['textShadowColor'] ?? null);
        // Text stroke
        self::attrI($a, 'text_stroke', (!empty($s['textStroke']) ? 'yes' : null));
        self::attrI($a, 'text_stroke_size', $s['textStrokeSize'] ?? null, 1);
        self::attrI($a, 'text_stroke_color', $s['textStrokeColor'] ?? null);
        // Overflow
        self::attrI($a, 'text_overflow', $s['textOverflow'] ?? null, 'initial');
        // Text animation (Extra tab) — looping motion on the heading itself
        self::attrTextAnim($a, $s);
        // Link
        self::attrI($a, 'use_link', (!empty($s['useLink']) ? 'yes' : null));
        self::attrI($a, 'link_url', $s['linkUrl'] ?? null);
        self::attrI($a, 'link_color', $s['linkColor'] ?? null);
        self::attrI($a, 'link_hover_color', $s['linkHoverColor'] ?? null);
        self::attrI($a, 'link_target', $s['linkTarget'] ?? null, '_self');
        // Spacing
        self::attrI($a, 'padding_top', $s['paddingTop'] ?? null, 20);
        self::attrI($a, 'padding_bottom', $s['paddingBottom'] ?? null, 20);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null, 0);
        self::attrI($a, 'margin_top_tablet', $s['marginTop_tablet'] ?? null);
        self::attrI($a, 'margin_top_mobile', $s['marginTop_mobile'] ?? null);
        self::attrI($a, 'margin_right', $s['marginRight'] ?? null, 0);
        self::attrI($a, 'margin_right_tablet', $s['marginRight_tablet'] ?? null);
        self::attrI($a, 'margin_right_mobile', $s['marginRight_mobile'] ?? null);
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null, 0);
        self::attrI($a, 'margin_bottom_tablet', $s['marginBottom_tablet'] ?? null);
        self::attrI($a, 'margin_bottom_mobile', $s['marginBottom_mobile'] ?? null);
        self::attrI($a, 'margin_left', $s['marginLeft'] ?? null, 0);
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
        // CSS
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        $body = str_replace(["\r\n", "\r", "\n"], '', $s['title'] ?? '');

        return '[falcon_title '.trim($a).$vis.']'.$body.'[/falcon_title]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $ts = [
            'title' => trim($inner),
            // Typography
            'fontSize' => isset($a['font_size']) ? (int) $a['font_size'] : null,
            'fontSizeUnit' => $a['font_size_unit'] ?? 'px',
            'fontWeight' => $a['font_weight'] ?? null,
            'fontFamily' => $a['font_family'] ?? null,
            'lineHeight' => $a['line_height'] ?? null,
            'letterSpacing' => isset($a['letter_spacing']) ? self::num($a['letter_spacing']) : null,
            'textTransform' => $a['text_transform'] ?? null,
            'htmlTag' => $a['html_tag'] ?? null,
            // Alignment
            'textAlign' => $a['align'] ?? null,
            // Color / Gradient
            'titleColor' => $a['color'] ?? null,
            'titleHoverColor' => $a['title_hover_color'] ?? null,
            'useGradient' => ($a['use_gradient'] ?? '') === 'yes',
            'gradientAngle' => isset($a['gradient_angle']) ? (int) $a['gradient_angle'] : null,
            'gradientStartColor' => $a['gradient_start'] ?? null,
            'gradientEndColor' => $a['gradient_end'] ?? null,
            // Separator
            'separator' => $a['separator'] ?? 'default',
            'separatorColor' => $a['separator_color'] ?? null,
            'dividerWidth' => isset($a['divider_width']) ? (int) $a['divider_width'] : null,
            'dividerHeight' => isset($a['divider_height']) ? (int) $a['divider_height'] : null,
            'separatorSpacing' => isset($a['separator_spacing']) ? (int) $a['separator_spacing'] : null,
            // Text shadow
            'textShadow' => ($a['text_shadow'] ?? '') === 'yes',
            'textShadowH' => isset($a['text_shadow_h']) ? self::num($a['text_shadow_h']) : null,
            'textShadowV' => isset($a['text_shadow_v']) ? self::num($a['text_shadow_v']) : null,
            'textShadowBlur' => isset($a['text_shadow_blur']) ? self::num($a['text_shadow_blur']) : null,
            'textShadowColor' => $a['text_shadow_color'] ?? null,
            // Text stroke
            'textStroke' => ($a['text_stroke'] ?? '') === 'yes',
            'textStrokeSize' => isset($a['text_stroke_size']) ? self::num($a['text_stroke_size']) : null,
            'textStrokeColor' => $a['text_stroke_color'] ?? null,
            // Overflow
            'textOverflow' => $a['text_overflow'] ?? null,
            // Link
            'useLink' => ($a['use_link'] ?? '') === 'yes',
            'linkUrl' => $a['link_url'] ?? null,
            'linkColor' => $a['link_color'] ?? null,
            'linkHoverColor' => $a['link_hover_color'] ?? null,
            'linkTarget' => $a['link_target'] ?? '_self',
            // Spacing
            'paddingTop' => isset($a['padding_top']) ? self::num($a['padding_top']) : null,
            'paddingBottom' => isset($a['padding_bottom']) ? self::num($a['padding_bottom']) : null,
            'marginTop' => self::num($a['margin_top'] ?? null),
            'marginRight' => self::num($a['margin_right'] ?? null),
            'marginBottom' => self::num($a['margin_bottom'] ?? null),
            'marginLeft' => self::num($a['margin_left'] ?? null),
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
            // CSS
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ];
        $ts += self::textAnimSettings($a);
        self::addRespProps($ts, $a, [
            ['textAlign',    'align',         null],
            ['marginTop',    'margin_top',    'num'],
            ['marginRight',  'margin_right',  'num'],
            ['marginBottom', 'margin_bottom', 'num'],
            ['marginLeft',   'margin_left',   'num'],
        ]);

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'title', 'settings' => $ts];
    }
}
