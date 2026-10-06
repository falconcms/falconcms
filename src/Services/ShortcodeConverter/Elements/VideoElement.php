<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_video] — builder JSON ⇄ shortcode.
 */
final class VideoElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'url', $s['url'] ?? '');
        self::attrI($a, 'video_source', $s['videoSource'] ?? 'youtube', 'youtube');
        self::attrI($a, 'aspect_ratio', $s['aspectRatio'] ?? '16-9', '16-9');
        self::attrI($a, 'autoplay', ($s['autoplay'] ?? false) ? '1' : '0', '0');
        self::attrI($a, 'muted', ($s['muted'] ?? false) ? '1' : '0', '0');
        self::attrI($a, 'loop', ($s['loop'] ?? false) ? '1' : '0', '0');
        self::attrI($a, 'controls', ($s['controls'] ?? true) ? '1' : '0', '1');
        self::attrI($a, 'margin_top', $s['marginTop'] ?? 0, 0);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? 'px', 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? 0, 0);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? 'px', 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_video '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'video', 'settings' => [
            'url' => $a['url'] ?? '',
            'videoSource' => $a['video_source'] ?? 'youtube',
            'aspectRatio' => $a['aspect_ratio'] ?? '16-9',
            'autoplay' => ($a['autoplay'] ?? '0') === '1',
            'muted' => ($a['muted'] ?? '0') === '1',
            'loop' => ($a['loop'] ?? '0') === '1',
            'controls' => ($a['controls'] ?? '1') !== '0',
            'marginTop' => (int) ($a['margin_top'] ?? 0),
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => (int) ($a['margin_bottom'] ?? 0),
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
