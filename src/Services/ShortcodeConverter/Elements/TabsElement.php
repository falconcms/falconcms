<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_tabs] — builder JSON ⇄ shortcode.
 */
final class TabsElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'default_active', $s['defaultActive'] ?? null, 0);
        self::attrI($a, 'style', $s['style'] ?? null, 'underline');
        self::attrI($a, 'alignment', $s['alignment'] ?? null, 'left');
        self::attrI($a, 'tab_font_size', $s['tabFontSize'] ?? null, 14);
        self::attrI($a, 'tab_font_weight', $s['tabFontWeight'] ?? null, '500');
        self::attrI($a, 'tab_font_family', $s['tabFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'tab_letter_spacing', $s['tabLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'tab_color', $s['tabColor'] ?? null, '#666666');
        self::attrI($a, 'active_color', $s['activeColor'] ?? null, '#0091ea');
        self::attrI($a, 'content_font_size', $s['contentFontSize'] ?? null, 14);
        self::attrI($a, 'content_font_family', $s['contentFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'content_letter_spacing', $s['contentLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'content_line_height', $s['contentLineHeight'] ?? null, 1.6);
        self::attrI($a, 'content_color', $s['contentColor'] ?? null, '#555555');
        self::attrI($a, 'content_bg_color', $s['contentBgColor'] ?? null, '#ffffff');
        self::attrI($a, 'content_padding', $s['contentPadding'] ?? null, 20);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null, '#e2e8f0');
        self::attrI($a, 'border_radius', $s['borderRadius'] ?? null, 4);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        $items = '';
        foreach ($s['items'] ?? [] as $item) {
            $l = htmlspecialchars($item['label'] ?? '', ENT_QUOTES);
            $iid = !empty($item['id']) ? ' id="'.htmlspecialchars((string) $item['id'], ENT_QUOTES).'"' : '';
            $items .= "\n".'[falcon_tab_item'.$iid.' label="'.$l.'"]'.($item['content'] ?? '').'[/falcon_tab_item]';
        }

        return '[falcon_tabs '.trim($a).$vis.']'.$items."\n[/falcon_tabs]";
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $items = [];
        if (preg_match_all('/\[falcon_tab_item([^\]]*)\](.*?)\[\/(?:falcon|lazy)_tab_item\]/s', $inner, $im, PREG_SET_ORDER)) {
            foreach ($im as $imatch) {
                $ia = self::attrs($imatch[1]);
                $items[] = [
                    'id' => $ia['id'] ?? self::uid(),
                    'label' => htmlspecialchars_decode($ia['label'] ?? '', ENT_QUOTES),
                    'content' => trim($imatch[2]),
                ];
            }
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'tabs', 'settings' => [
            'items' => $items,
            'defaultActive' => isset($a['default_active']) ? (int) $a['default_active'] : 0,
            'style' => $a['style'] ?? 'underline',
            'alignment' => $a['alignment'] ?? 'left',
            'tabFontSize' => isset($a['tab_font_size']) ? (int) $a['tab_font_size'] : 14,
            'tabFontWeight' => $a['tab_font_weight'] ?? '500',
            'tabFontFamily' => $a['tab_font_family'] ?? 'inherit',
            'tabLetterSpacing' => $a['tab_letter_spacing'] ?? '0px',
            'tabColor' => $a['tab_color'] ?? '#666666',
            'activeColor' => $a['active_color'] ?? '#0091ea',
            'contentFontSize' => isset($a['content_font_size']) ? (int) $a['content_font_size'] : 14,
            'contentFontFamily' => $a['content_font_family'] ?? 'inherit',
            'contentLetterSpacing' => $a['content_letter_spacing'] ?? '0px',
            'contentLineHeight' => isset($a['content_line_height']) ? (float) $a['content_line_height'] : 1.6,
            'contentColor' => $a['content_color'] ?? '#555555',
            'contentBgColor' => $a['content_bg_color'] ?? '#ffffff',
            'contentPadding' => isset($a['content_padding']) ? (int) $a['content_padding'] : 20,
            'borderColor' => $a['border_color'] ?? '#e2e8f0',
            'borderRadius' => isset($a['border_radius']) ? (int) $a['border_radius'] : 4,
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
