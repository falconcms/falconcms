<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_ticker] — builder JSON ⇄ shortcode.
 */
final class TickerElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        $items = $s['items'] ?? [];
        $n = count($items);
        if ($n > 0) {
            self::attrI($a, 'item_n', $n);
            foreach ($items as $idx => $item) {
                self::attrI($a, 'item_'.$idx, $item['text'] ?? null);
                self::attrI($a, 'item_'.$idx.'_u', $item['url'] ?? null);
            }
        }
        self::attrI($a, 'label', $s['label'] ?? null);
        self::attrI($a, 'speed', $s['speed'] ?? null, 50);
        self::attrI($a, 'dir', $s['direction'] ?? null, 'left');
        self::attrI($a, 'pause', isset($s['pauseOnHover']) ? ($s['pauseOnHover'] ? '1' : '0') : null, '1');
        self::attrI($a, 'sep', $s['separator'] ?? null, '•');
        self::attrI($a, 'bg', $s['bgColor'] ?? null, '#1e3a8a');
        self::attrI($a, 'color', $s['textColor'] ?? null, '#ffffff');
        self::attrI($a, 'lbl_bg', $s['labelBgColor'] ?? null, '#ef4444');
        self::attrI($a, 'lbl_color', $s['labelTextColor'] ?? null, '#ffffff');
        self::attrI($a, 'fsize', $s['fontSize'] ?? null, '14px');
        self::attrI($a, 'fweight', $s['fontWeight'] ?? null, '500');
        self::attrI($a, 'height', $s['height'] ?? null, 44);
        self::attrI($a, 'radius', $s['borderRadius'] ?? null, 0);
        self::attrI($a, 'mt', $s['marginTop'] ?? null, 0);
        self::attrI($a, 'mt_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'mb', $s['marginBottom'] ?? null, 0);
        self::attrI($a, 'mb_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_ticker '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $n = isset($a['item_n']) ? (int) $a['item_n'] : 0;
        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $items[] = ['text' => $a['item_'.$i] ?? '', 'url' => $a['item_'.$i.'_u'] ?? ''];
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'ticker', 'settings' => [
            'items' => $items,
            'label' => $a['label'] ?? '',
            'speed' => isset($a['speed']) ? (int) $a['speed'] : 50,
            'direction' => $a['dir'] ?? 'left',
            'pauseOnHover' => ($a['pause'] ?? '1') !== '0',
            'separator' => $a['sep'] ?? '•',
            'bgColor' => $a['bg'] ?? '#1e3a8a',
            'textColor' => $a['color'] ?? '#ffffff',
            'labelBgColor' => $a['lbl_bg'] ?? '#ef4444',
            'labelTextColor' => $a['lbl_color'] ?? '#ffffff',
            'fontSize' => $a['fsize'] ?? '14px',
            'fontWeight' => $a['fweight'] ?? '500',
            'height' => isset($a['height']) ? (int) $a['height'] : 44,
            'borderRadius' => isset($a['radius']) ? (int) $a['radius'] : 0,
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
