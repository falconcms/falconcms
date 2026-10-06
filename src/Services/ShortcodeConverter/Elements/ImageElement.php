<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_image] — builder JSON ⇄ shortcode.
 */
final class ImageElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        // Source
        self::attrI($a, 'url', $s['url'] ?? $s['src'] ?? '');
        self::attrI($a, 'alt', $s['alt'] ?? '');
        // Link
        self::attrI($a, 'link_url', $s['linkUrl'] ?? null);
        self::attrI($a, 'link_target', $s['linkTarget'] ?? null);
        // Alignment (+ responsive)
        self::attrI($a, 'align', $s['textAlign'] ?? null);
        self::attrI($a, 'align_tablet', $s['textAlign_tablet'] ?? null);
        self::attrI($a, 'align_mobile', $s['textAlign_mobile'] ?? null);
        // Dimensions
        self::attrI($a, 'width', $s['width'] ?? null);
        self::attrI($a, 'width_unit', $s['widthUnit'] ?? null, 'px');
        self::attrI($a, 'max_width', $s['maxWidth'] ?? null);
        self::attrI($a, 'max_width_unit', $s['maxWidthUnit'] ?? null, 'px');
        self::attrI($a, 'sticky_width', $s['stickyWidth'] ?? null);
        self::attrI($a, 'sticky_width_unit', $s['stickyWidthUnit'] ?? null, 'px');
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
        // Border
        self::attrI($a, 'border_radius', $s['borderRadius'] ?? null);
        self::attrI($a, 'border_radius_unit', $s['borderRadiusUnit'] ?? null, 'px');
        self::attrI($a, 'border_top', $s['borderSizeTop'] ?? null);
        self::attrI($a, 'border_right', $s['borderSizeRight'] ?? null);
        self::attrI($a, 'border_bottom', $s['borderSizeBottom'] ?? null);
        self::attrI($a, 'border_left', $s['borderSizeLeft'] ?? null);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null);
        // Hover
        self::attrI($a, 'lightbox', !empty($s['lightbox']) ? 'yes' : null);
        self::attrI($a, 'hover_type', $s['hoverType'] ?? null);
        self::attrI($a, 'aspect_ratio', $s['aspectRatio'] ?? null);
        self::attrI($a, 'focus_x', $s['focusX'] ?? null, 50);
        self::attrI($a, 'focus_y', $s['focusY'] ?? null, 50);
        // CSS
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_image '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $ts = [
            // Source
            'url' => $a['url'] ?? $a['src'] ?? '',
            'alt' => $a['alt'] ?? '',
            // Link
            'linkUrl' => $a['link_url'] ?? '',
            'linkTarget' => $a['link_target'] ?? '_self',
            // Alignment
            'textAlign' => $a['align'] ?? 'center',
            // Dimensions
            'width' => $a['width'] ?? null,
            'widthUnit' => $a['width_unit'] ?? 'px',
            'maxWidth' => $a['max_width'] ?? null,
            'maxWidthUnit' => $a['max_width_unit'] ?? 'px',
            'stickyWidth' => $a['sticky_width'] ?? null,
            'stickyWidthUnit' => $a['sticky_width_unit'] ?? 'px',
            // Margin
            'marginTop' => isset($a['margin_top']) ? (int) $a['margin_top'] : null,
            'marginRight' => isset($a['margin_right']) ? (int) $a['margin_right'] : null,
            'marginBottom' => isset($a['margin_bottom']) ? (int) $a['margin_bottom'] : null,
            'marginLeft' => isset($a['margin_left']) ? (int) $a['margin_left'] : null,
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
            // Border
            'borderRadius' => $a['border_radius'] ?? null,
            'borderRadiusUnit' => $a['border_radius_unit'] ?? 'px',
            'borderSizeTop' => isset($a['border_top']) ? (int) $a['border_top'] : null,
            'borderSizeRight' => isset($a['border_right']) ? (int) $a['border_right'] : null,
            'borderSizeBottom' => isset($a['border_bottom']) ? (int) $a['border_bottom'] : null,
            'borderSizeLeft' => isset($a['border_left']) ? (int) $a['border_left'] : null,
            'borderColor' => $a['border_color'] ?? null,
            'lightbox' => ($a['lightbox'] ?? '') === 'yes',
            // Hover
            'hoverType' => $a['hover_type'] ?? 'none',
            'aspectRatio' => $a['aspect_ratio'] ?? 'none',
            'focusX' => isset($a['focus_x']) ? (int) $a['focus_x'] : 50,
            'focusY' => isset($a['focus_y']) ? (int) $a['focus_y'] : 50,
            // CSS
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ];
        self::addRespProps($ts, $a, [
            ['textAlign',    'align',         'str'],
            ['marginTop',    'margin_top',    'num'],
            ['marginRight',  'margin_right',  'num'],
            ['marginBottom', 'margin_bottom', 'num'],
            ['marginLeft',   'margin_left',   'num'],
        ]);

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'image', 'settings' => $ts];
    }
}
