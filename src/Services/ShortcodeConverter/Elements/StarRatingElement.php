<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_star_rating] — builder JSON ⇄ shortcode.
 */
final class StarRatingElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'rating', $s['rating'] ?? null, 5);
        self::attrI($a, 'max', $s['maxStars'] ?? null, 5);
        self::attrI($a, 'label', $s['label'] ?? null);
        self::attrI($a, 'size', $s['starSize'] ?? null, 24);
        self::attrI($a, 'color', $s['starColor'] ?? null, '#f59e0b');
        self::attrI($a, 'empty', $s['emptyColor'] ?? null, '#d1d5db');
        self::attrI($a, 'align', $s['textAlign'] ?? null, 'center');
        self::attrI($a, 'gap', $s['gap'] ?? null, 4);
        self::attrI($a, 'lbl_family', $s['labelFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'lbl_size', $s['labelFontSize'] ?? null, '13px');
        self::attrI($a, 'lbl_weight', $s['labelFontWeight'] ?? null, '400');
        self::attrI($a, 'lbl_lh', $s['labelLineHeight'] ?? null, '1.4');
        self::attrI($a, 'lbl_ls', $s['labelLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'lbl_tt', $s['labelTextTransform'] ?? null, 'none');
        self::attrI($a, 'lbl_color', $s['labelColor'] ?? null, '#6b7280');
        self::attrI($a, 'mt', $s['marginTop'] ?? null, 0);
        self::attrI($a, 'mt_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'mb', $s['marginBottom'] ?? null, 0);
        self::attrI($a, 'mb_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_star_rating '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'star_rating', 'settings' => [
            'rating' => isset($a['rating']) ? (float) $a['rating'] : 5,
            'maxStars' => isset($a['max']) ? (int) $a['max'] : 5,
            'label' => $a['label'] ?? '',
            'starSize' => isset($a['size']) ? (int) $a['size'] : 24,
            'starColor' => $a['color'] ?? '#f59e0b',
            'emptyColor' => $a['empty'] ?? '#d1d5db',
            'textAlign' => $a['align'] ?? 'center',
            'gap' => isset($a['gap']) ? (int) $a['gap'] : 4,
            'labelFontFamily' => $a['lbl_family'] ?? 'inherit',
            'labelFontSize' => $a['lbl_size'] ?? '13px',
            'labelFontWeight' => $a['lbl_weight'] ?? '400',
            'labelLineHeight' => $a['lbl_lh'] ?? '1.4',
            'labelLetterSpacing' => $a['lbl_ls'] ?? '0px',
            'labelTextTransform' => $a['lbl_tt'] ?? 'none',
            'labelColor' => $a['lbl_color'] ?? '#6b7280',
            'marginTop' => isset($a['mt']) ? self::num($a['mt']) : 0,
            'marginTopUnit' => $a['mt_unit'] ?? 'px',
            'marginBottom' => isset($a['mb']) ? self::num($a['mb']) : 0,
            'marginBottomUnit' => $a['mb_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
