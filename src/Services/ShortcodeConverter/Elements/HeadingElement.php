<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_heading] — builder JSON ⇄ shortcode.
 */
final class HeadingElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'tag', $s['tag'] ?? null, 'h2');
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
        self::attrI($a, 'align', $s['textAlign'] ?? null);
        self::attrI($a, 'color', $s['color'] ?? null);
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        $body = str_replace(["\r\n", "\r", "\n"], '', $s['title'] ?? '');

        return '[falcon_heading '.trim($a).$vis.']'.$body.'[/falcon_heading]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'heading', 'settings' => array_merge([
            'title' => trim($inner),
            'tag' => $a['tag'] ?? 'h2',
            'fontSize' => $a['font_size'] ?? null,
            'fontWeight' => $a['font_weight'] ?? null,
            'textAlign' => $a['align'] ?? null,
            'color' => $a['color'] ?? null,
            'cssClass' => $a['css_class'] ?? null,
            'visibility' => $vis,
        ], [])];
    }
}
