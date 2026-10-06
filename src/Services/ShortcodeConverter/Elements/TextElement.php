<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_text] — builder JSON ⇄ shortcode.
 */
final class TextElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
        self::attrI($a, 'color', $s['color'] ?? null);
        self::attrI($a, 'align', $s['textAlign'] ?? null);
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        $body = str_replace(["\r\n", "\r", "\n"], '', $s['content'] ?? '');

        return '[falcon_text '.trim($a).$vis.']'.$body.'[/falcon_text]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'text', 'settings' => [
            'content' => trim($inner),
            'fontSize' => $a['font_size'] ?? null,
            'fontWeight' => $a['font_weight'] ?? null,
            'color' => $a['color'] ?? null,
            'textAlign' => $a['align'] ?? null,
            'cssClass' => $a['css_class'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
