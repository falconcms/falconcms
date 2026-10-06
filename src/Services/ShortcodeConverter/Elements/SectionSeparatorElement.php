<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;
use FalconCms\Core\Support\SeparatorShapes;

/**
 * [falcon_section_separator] — builder JSON ⇄ shortcode.
 */
final class SectionSeparatorElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        // Line / pattern
        self::attrI($a, 'style', $s['sepStyle'] ?? null, 'solid');
        self::attrI($a, 'sep_width', $s['sepWidth'] ?? null);
        self::attrI($a, 'sep_width_unit', $s['sepWidthUnit'] ?? null, '%');
        self::attrI($a, 'align', $s['sepAlign'] ?? null, 'center');
        self::attrI($a, 'weight', $s['sepWeight'] ?? null);
        self::attrI($a, 'color', $s['sepColor'] ?? null);
        self::attrI($a, 'pat_height', $s['patHeight'] ?? null);
        self::attrI($a, 'pat_spacing', $s['patSpacing'] ?? null);
        self::attrI($a, 'shape_height', $s['shapeHeight'] ?? null);
        self::attrI($a, 'flip_h', !empty($s['shapeFlipH']) ? 'yes' : null);
        self::attrI($a, 'flip_v', !empty($s['shapeFlipV']) ? 'yes' : null);
        // Middle element (text / icon)
        self::attrI($a, 'content', $s['sepContent'] ?? null, 'none');
        self::attrI($a, 'text', $s['sepText'] ?? null);
        self::attrI($a, 'icon', $s['sepIcon'] ?? null);
        self::attrI($a, 'content_pos', $s['contentPos'] ?? null, 'center');
        self::attrI($a, 'content_gap', $s['contentGap'] ?? null);
        // Text
        self::attrI($a, 'text_color', $s['textColor'] ?? null);
        self::attrI($a, 'txt_family', $s['sep_text_family'] ?? null);
        self::attrI($a, 'txt_weight', $s['sep_text_weight'] ?? null);
        self::attrI($a, 'txt_size', $s['sep_text_size'] ?? null);
        self::attrI($a, 'txt_line_height', $s['sep_text_line_height'] ?? null);
        self::attrI($a, 'txt_letter_spacing', $s['sep_text_letter_spacing'] ?? null);
        self::attrI($a, 'txt_transform', $s['sep_text_transform'] ?? null);
        // Icon
        self::attrI($a, 'icon_size', $s['iconSize'] ?? null);
        self::attrI($a, 'icon_color', $s['iconColor'] ?? null);
        self::attrI($a, 'icon_rotate', $s['iconRotate'] ?? null);
        self::attrI($a, 'icon_view', $s['iconView'] ?? null, 'default');
        self::attrI($a, 'icon_shape', $s['iconShape'] ?? null, 'circle');
        self::attrI($a, 'icon_bg', $s['iconBgColor'] ?? null);
        self::attrI($a, 'icon_border_color', $s['iconBorderColor'] ?? null);
        self::attrI($a, 'icon_border_width', $s['iconBorderWidth'] ?? null);
        self::attrI($a, 'icon_padding', $s['iconPadding'] ?? null);
        // Spacing + CSS
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        // Custom SVG switches; the markup itself goes in the body (see below).
        self::attrI($a, 'svg_name', $s['customSvgName'] ?? null);
        self::attrI($a, 'svg_recolor', (($s['svgRecolor'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'svg_stretch', (($s['svgStretch'] ?? true) === false) ? 'no' : null);

        // Raw SVG cannot live in an attribute — it carries quotes and angle brackets.
        // It goes in the shortcode body instead, exactly like the HTML element's markup.
        $svgBody = SeparatorShapes::sanitizeCustomSvg($s['customSvg'] ?? '');
        if ($svgBody !== '') {
            return '[falcon_section_separator '.trim($a).$vis.']'
                .str_replace(['
', '
', '
'], '', $svgBody)
                .'[/falcon_section_separator]';
        }

        return '[falcon_section_separator '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'section_separator', 'settings' => [
            'sepStyle' => $a['style'] ?? 'solid',
            'sepWidth' => isset($a['sep_width']) ? self::num($a['sep_width']) : 100,
            'sepWidthUnit' => $a['sep_width_unit'] ?? '%',
            'sepAlign' => $a['align'] ?? 'center',
            'sepWeight' => isset($a['weight']) ? self::num($a['weight']) : 1,
            'sepColor' => $a['color'] ?? '#e2e8f0',
            'patHeight' => isset($a['pat_height']) ? (int) $a['pat_height'] : 20,
            'patSpacing' => isset($a['pat_spacing']) ? (int) $a['pat_spacing'] : 20,
            'shapeHeight' => isset($a['shape_height']) ? (int) $a['shape_height'] : 60,
            'shapeFlipH' => ($a['flip_h'] ?? '') === 'yes',
            'shapeFlipV' => ($a['flip_v'] ?? '') === 'yes',
            'customSvg' => SeparatorShapes::sanitizeCustomSvg($inner),
            'customSvgName' => $a['svg_name'] ?? '',
            'svgRecolor' => ($a['svg_recolor'] ?? '') !== 'no',
            'svgStretch' => ($a['svg_stretch'] ?? '') !== 'no',
            'sepContent' => $a['content'] ?? 'none',
            'sepText' => $a['text'] ?? '',
            'sepIcon' => $a['icon'] ?? '',
            'contentPos' => $a['content_pos'] ?? 'center',
            'contentGap' => isset($a['content_gap']) ? (int) $a['content_gap'] : 15,
            'textColor' => $a['text_color'] ?? '#333333',
            'sep_text_family' => $a['txt_family'] ?? 'inherit',
            'sep_text_weight' => $a['txt_weight'] ?? '400',
            'sep_text_size' => $a['txt_size'] ?? '15px',
            'sep_text_line_height' => $a['txt_line_height'] ?? '1.4',
            'sep_text_letter_spacing' => $a['txt_letter_spacing'] ?? 'normal',
            'sep_text_transform' => $a['txt_transform'] ?? 'none',
            'iconSize' => isset($a['icon_size']) ? (int) $a['icon_size'] : 20,
            'iconColor' => $a['icon_color'] ?? '#333333',
            'iconRotate' => isset($a['icon_rotate']) ? (int) $a['icon_rotate'] : 0,
            'iconView' => $a['icon_view'] ?? 'default',
            'iconShape' => $a['icon_shape'] ?? 'circle',
            'iconBgColor' => $a['icon_bg'] ?? '#f1f5f9',
            'iconBorderColor' => $a['icon_border_color'] ?? '#e2e8f0',
            'iconBorderWidth' => isset($a['icon_border_width']) ? (int) $a['icon_border_width'] : 1,
            'iconPadding' => isset($a['icon_padding']) ? (int) $a['icon_padding'] : 10,
            'marginTop' => isset($a['margin_top']) ? self::num($a['margin_top']) : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? self::num($a['margin_bottom']) : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
