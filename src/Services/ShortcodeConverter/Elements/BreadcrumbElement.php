<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_breadcrumb] — builder JSON ⇄ shortcode.
 */
final class BreadcrumbElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'show_home', (($s['showHome'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'home_label', $s['homeLabel'] ?? null, 'Home');
        self::attrI($a, 'separator', $s['separator'] ?? null, '/');
        self::attrI($a, 'show_current', (($s['showCurrent'] ?? true) === false) ? 'no' : null);
        // Typography
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null, 'inherit');
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_size_unit', $s['fontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null, '400');
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null);
        self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
        self::attrI($a, 'text_transform', $s['textTransform'] ?? null, 'none');
        self::attrI($a, 'align', $s['textAlign'] ?? null, 'left');
        // Colors
        self::attrI($a, 'color', $s['color'] ?? null);
        self::attrI($a, 'link_color', $s['linkColor'] ?? null);
        self::attrI($a, 'link_hover_color', $s['linkHoverColor'] ?? null);
        self::attrI($a, 'separator_color', $s['separatorColor'] ?? null);
        self::attrI($a, 'current_color', $s['currentColor'] ?? null);
        // Spacing
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_right', $s['marginRight'] ?? null);
        self::attrI($a, 'margin_right_unit', $s['marginRightUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'margin_left', $s['marginLeft'] ?? null);
        self::attrI($a, 'margin_left_unit', $s['marginLeftUnit'] ?? null, 'px');
        self::attrI($a, 'padding_top', $s['paddingTop'] ?? null);
        self::attrI($a, 'padding_top_unit', $s['paddingTopUnit'] ?? null, 'px');
        self::attrI($a, 'padding_right', $s['paddingRight'] ?? null);
        self::attrI($a, 'padding_right_unit', $s['paddingRightUnit'] ?? null, 'px');
        self::attrI($a, 'padding_bottom', $s['paddingBottom'] ?? null);
        self::attrI($a, 'padding_bottom_unit', $s['paddingBottomUnit'] ?? null, 'px');
        self::attrI($a, 'padding_left', $s['paddingLeft'] ?? null);
        self::attrI($a, 'padding_left_unit', $s['paddingLeftUnit'] ?? null, 'px');
        // CSS
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_breadcrumb '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'breadcrumb', 'settings' => [
            'showHome' => isset($a['show_home']) ? !in_array($a['show_home'], ['no', 'false', '0'], true) : true,
            'homeLabel' => $a['home_label'] ?? 'Home',
            'separator' => $a['separator'] ?? '/',
            'showCurrent' => isset($a['show_current']) ? !in_array($a['show_current'], ['no', 'false', '0'], true) : true,
            'fontFamily' => $a['font_family'] ?? 'inherit',
            'fontSize' => $a['font_size'] ?? 14,
            'fontSizeUnit' => $a['font_size_unit'] ?? 'px',
            'fontWeight' => $a['font_weight'] ?? '400',
            'lineHeight' => $a['line_height'] ?? '',
            'letterSpacing' => $a['letter_spacing'] ?? '',
            'textTransform' => $a['text_transform'] ?? 'none',
            'textAlign' => $a['align'] ?? 'left',
            'color' => $a['color'] ?? '#6b7280',
            'linkColor' => $a['link_color'] ?? '#6b7280',
            'linkHoverColor' => $a['link_hover_color'] ?? '#2271b1',
            'separatorColor' => $a['separator_color'] ?? '#9ca3af',
            'currentColor' => $a['current_color'] ?? '#111827',
            'marginTop' => isset($a['margin_top']) ? (int) $a['margin_top'] : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginRight' => isset($a['margin_right']) ? (int) $a['margin_right'] : 0,
            'marginRightUnit' => $a['margin_right_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? (int) $a['margin_bottom'] : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'marginLeft' => isset($a['margin_left']) ? (int) $a['margin_left'] : 0,
            'marginLeftUnit' => $a['margin_left_unit'] ?? 'px',
            'paddingTop' => isset($a['padding_top']) ? (int) $a['padding_top'] : 0,
            'paddingTopUnit' => $a['padding_top_unit'] ?? 'px',
            'paddingRight' => isset($a['padding_right']) ? (int) $a['padding_right'] : 0,
            'paddingRightUnit' => $a['padding_right_unit'] ?? 'px',
            'paddingBottom' => isset($a['padding_bottom']) ? (int) $a['padding_bottom'] : 0,
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingLeft' => isset($a['padding_left']) ? (int) $a['padding_left'] : 0,
            'paddingLeftUnit' => $a['padding_left_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
