<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_icon_list] — builder JSON ⇄ shortcode.
 */
final class IconListElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'default_icon', $s['defaultIcon'] ?? null, 'fa fa-check');
        self::attrI($a, 'icon_size', $s['iconSize'] ?? null, 14);
        self::attrI($a, 'icon_color', $s['iconColor'] ?? null, '#0091ea');
        self::attrI($a, 'icon_position', $s['iconPosition'] ?? null, 'left');
        self::attrI($a, 'gap', $s['gap'] ?? null, 10);
        self::attrI($a, 'item_spacing', $s['itemSpacing'] ?? null, 10);
        self::attrI($a, 'text_align', $s['textAlign'] ?? null, 'left');
        self::attrI($a, 'text_color', $s['textColor'] ?? null, '#333333');
        self::attrI($a, 'font_size', $s['fontSize'] ?? null, 15);
        self::attrI($a, 'font_size_unit', $s['fontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null, '400');
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null, 'inherit');
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null, '1.5');
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        $items = '';
        foreach ($s['items'] ?? [] as $item) {
            $ia = !empty($item['id']) ? ' id="'.htmlspecialchars((string) $item['id'], ENT_QUOTES).'"' : '';
            $ia .= ' icon="'.htmlspecialchars($item['icon'] ?? 'fa fa-check', ENT_QUOTES).'"';
            if (!empty($item['iconColor'])) {
                $ia .= ' icon_color="'.htmlspecialchars($item['iconColor'], ENT_QUOTES).'"';
            }
            if (!empty($item['link'])) {
                $ia .= ' link="'.htmlspecialchars($item['link'], ENT_QUOTES).'"';
            }
            if (!empty($item['linkTarget'])) {
                $ia .= ' link_target="'.htmlspecialchars($item['linkTarget'], ENT_QUOTES).'"';
            }
            $items .= "\n".'[falcon_icon_list_item'.$ia.']'.htmlspecialchars($item['text'] ?? '', ENT_QUOTES).'[/falcon_icon_list_item]';
        }

        return '[falcon_icon_list '.trim($a).$vis.']'.$items."\n[/falcon_icon_list]";
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $items = [];
        if (preg_match_all('/\[falcon_icon_list_item([^\]]*)\](.*?)\[\/(?:falcon|lazy)_icon_list_item\]/s', $inner, $im, PREG_SET_ORDER)) {
            foreach ($im as $imatch) {
                $ia = self::attrs($imatch[1]);
                $items[] = [
                    'id' => $ia['id'] ?? self::uid(),
                    'icon' => $ia['icon'] ?? 'fa fa-check',
                    'iconColor' => $ia['icon_color'] ?? '',
                    'text' => htmlspecialchars_decode($imatch[2], ENT_QUOTES),
                    'link' => $ia['link'] ?? '',
                    'linkTarget' => $ia['link_target'] ?? '_self',
                ];
            }
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'icon_list', 'settings' => [
            'items' => $items,
            'defaultIcon' => $a['default_icon'] ?? 'fa fa-check',
            'iconSize' => isset($a['icon_size']) ? (int) $a['icon_size'] : 14,
            'iconColor' => $a['icon_color'] ?? '#0091ea',
            'iconPosition' => $a['icon_position'] ?? 'left',
            'gap' => isset($a['gap']) ? (int) $a['gap'] : 10,
            'itemSpacing' => isset($a['item_spacing']) ? (int) $a['item_spacing'] : 10,
            'textAlign' => $a['text_align'] ?? 'left',
            'textColor' => $a['text_color'] ?? '#333333',
            'fontSize' => isset($a['font_size']) ? (int) $a['font_size'] : 15,
            'fontSizeUnit' => $a['font_size_unit'] ?? 'px',
            'fontWeight' => $a['font_weight'] ?? '400',
            'fontFamily' => $a['font_family'] ?? 'inherit',
            'lineHeight' => $a['line_height'] ?? '1.5',
            'marginTop' => isset($a['margin_top']) ? (int) $a['margin_top'] : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? (int) $a['margin_bottom'] : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
