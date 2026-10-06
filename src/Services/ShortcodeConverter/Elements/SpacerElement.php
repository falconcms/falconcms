<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_spacer] — builder JSON ⇄ shortcode.
 */
final class SpacerElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'style', $s['style'] ?? 'default');
        self::attrI($a, 'flex_grow', $s['flexGrow'] ?? null);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'separator_width', $s['separatorWidth'] ?? null);
        self::attrI($a, 'separator_width_unit', $s['separatorWidthUnit'] ?? null, '%');
        self::attrI($a, 'alignment', $s['alignment'] ?? null);
        self::attrI($a, 'border_size', $s['borderSize'] ?? null);
        self::attrI($a, 'separator_color', $s['separatorColor'] ?? null);
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_spacer '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'spacer', 'settings' => [
            'style' => $a['style'] ?? 'default',
            'flexGrow' => isset($a['flex_grow']) ? (int) $a['flex_grow'] : 0,
            'marginTop' => isset($a['margin_top']) ? (int) $a['margin_top'] : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? (int) $a['margin_bottom'] : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'separatorWidth' => isset($a['separator_width']) ? (int) $a['separator_width'] : 100,
            'separatorWidthUnit' => $a['separator_width_unit'] ?? '%',
            'alignment' => $a['alignment'] ?? 'center',
            'borderSize' => isset($a['border_size']) ? (int) $a['border_size'] : 1,
            'separatorColor' => $a['separator_color'] ?? '#cccccc',
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
