<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_counter] — builder JSON ⇄ shortcode.
 */
final class CounterElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'end', $s['endValue'] ?? null);
        self::attrI($a, 'start', $s['startValue'] ?? null, 0);
        self::attrI($a, 'prefix', $s['prefix'] ?? null);
        self::attrI($a, 'suffix', $s['suffix'] ?? null);
        self::attrI($a, 'label', $s['label'] ?? null);
        self::attrI($a, 'dur', $s['duration'] ?? null, 2000);
        self::attrI($a, 'dec', $s['decimals'] ?? null, 0);
        self::attrI($a, 'sep', $s['separator'] ?? null);
        self::attrI($a, 'align', $s['textAlign'] ?? null, 'center');
        self::attrI($a, 'num_size', $s['numberFontSize'] ?? null, '48px');
        self::attrI($a, 'num_weight', $s['numberFontWeight'] ?? null, '700');
        self::attrI($a, 'num_color', $s['numberColor'] ?? null);
        self::attrI($a, 'num_family', $s['numberFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'num_lh', $s['numberLineHeight'] ?? null, '1.1');
        self::attrI($a, 'num_ls', $s['numberLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'lbl_size', $s['labelFontSize'] ?? null, '14px');
        self::attrI($a, 'lbl_weight', $s['labelFontWeight'] ?? null, '400');
        self::attrI($a, 'lbl_color', $s['labelColor'] ?? null);
        self::attrI($a, 'lbl_family', $s['labelFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'lbl_lh', $s['labelLineHeight'] ?? null, '1.4');
        self::attrI($a, 'lbl_ls', $s['labelLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'lbl_tt', $s['labelTextTransform'] ?? null, 'none');
        self::attrI($a, 'icon', $s['icon'] ?? null);
        self::attrI($a, 'icon_size', $s['iconSize'] ?? null, 40);
        self::attrI($a, 'icon_color', $s['iconColor'] ?? null, '#0091ea');
        self::attrI($a, 'mt', $s['marginTop'] ?? null, 0);
        self::attrI($a, 'mt_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'mb', $s['marginBottom'] ?? null, 0);
        self::attrI($a, 'mb_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_counter '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'counter', 'settings' => [
            'endValue' => isset($a['end']) ? self::num($a['end']) : 100,
            'startValue' => isset($a['start']) ? self::num($a['start']) : 0,
            'prefix' => $a['prefix'] ?? '',
            'suffix' => $a['suffix'] ?? '',
            'label' => $a['label'] ?? '',
            'duration' => isset($a['dur']) ? (int) $a['dur'] : 2000,
            'decimals' => isset($a['dec']) ? (int) $a['dec'] : 0,
            'separator' => $a['sep'] ?? '',
            'textAlign' => $a['align'] ?? 'center',
            'numberFontSize' => $a['num_size'] ?? '48px',
            'numberFontWeight' => $a['num_weight'] ?? '700',
            'numberColor' => $a['num_color'] ?? '#222222',
            'numberFontFamily' => $a['num_family'] ?? 'inherit',
            'numberLineHeight' => $a['num_lh'] ?? '1.1',
            'numberLetterSpacing' => $a['num_ls'] ?? '0px',
            'labelFontSize' => $a['lbl_size'] ?? '14px',
            'labelFontWeight' => $a['lbl_weight'] ?? '400',
            'labelColor' => $a['lbl_color'] ?? '#666666',
            'labelFontFamily' => $a['lbl_family'] ?? 'inherit',
            'labelLineHeight' => $a['lbl_lh'] ?? '1.4',
            'labelLetterSpacing' => $a['lbl_ls'] ?? '0px',
            'labelTextTransform' => $a['lbl_tt'] ?? 'none',
            'icon' => $a['icon'] ?? '',
            'iconSize' => isset($a['icon_size']) ? (int) $a['icon_size'] : 40,
            'iconColor' => $a['icon_color'] ?? '#0091ea',
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
