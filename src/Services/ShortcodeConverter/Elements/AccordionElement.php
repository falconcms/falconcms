<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_accordion] — builder JSON ⇄ shortcode.
 */
final class AccordionElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'default_open', $s['defaultOpen'] ?? null, 0);
        self::attrI($a, 'allow_multiple', ($s['allowMultiple'] ?? false) ? 'yes' : null);
        self::attrI($a, 'icon_type', $s['iconType'] ?? null, 'plus');
        self::attrI($a, 'icon_position', $s['iconPosition'] ?? null, 'right');
        self::attrI($a, 'title_font_size', $s['titleFontSize'] ?? null, 15);
        self::attrI($a, 'title_font_weight', $s['titleFontWeight'] ?? null, '600');
        self::attrI($a, 'title_font_family', $s['titleFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'title_letter_spacing', $s['titleLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'title_line_height', $s['titleLineHeight'] ?? null, 1.4);
        self::attrI($a, 'title_text_transform', $s['titleTextTransform'] ?? null, 'none');
        self::attrI($a, 'title_color', $s['titleColor'] ?? null, '#222222');
        self::attrI($a, 'title_bg_color', $s['titleBgColor'] ?? null, '#f8fafc');
        self::attrI($a, 'title_active_bg_color', $s['titleActiveBgColor'] ?? null, '#0091ea');
        self::attrI($a, 'title_active_color', $s['titleActiveColor'] ?? null, '#ffffff');
        self::attrI($a, 'title_padding', $s['titlePadding'] ?? null, 16);
        self::attrI($a, 'content_font_size', $s['contentFontSize'] ?? null, 14);
        self::attrI($a, 'content_font_family', $s['contentFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'content_letter_spacing', $s['contentLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'content_line_height', $s['contentLineHeight'] ?? null, 1.6);
        self::attrI($a, 'content_color', $s['contentColor'] ?? null, '#555555');
        self::attrI($a, 'content_bg_color', $s['contentBgColor'] ?? null, '#ffffff');
        self::attrI($a, 'content_padding', $s['contentPadding'] ?? null, 16);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null, '#e2e8f0');
        self::attrI($a, 'border_radius', $s['borderRadius'] ?? null, 8);
        self::attrI($a, 'item_gap', $s['itemGap'] ?? null, 8);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        $items = '';
        foreach ($s['items'] ?? [] as $item) {
            $t = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
            $iid = !empty($item['id']) ? ' id="'.htmlspecialchars((string) $item['id'], ENT_QUOTES).'"' : '';
            $items .= "\n".'[falcon_acc_item'.$iid.' title="'.$t.'"]'.($item['content'] ?? '').'[/falcon_acc_item]';
        }

        return '[falcon_accordion '.trim($a).$vis.']'.$items."\n[/falcon_accordion]";
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $items = [];
        if (preg_match_all('/\[falcon_acc_item([^\]]*)\](.*?)\[\/(?:falcon|lazy)_acc_item\]/s', $inner, $im, PREG_SET_ORDER)) {
            foreach ($im as $imatch) {
                $ia = self::attrs($imatch[1]);
                $items[] = [
                    'id' => $ia['id'] ?? self::uid(),
                    'title' => htmlspecialchars_decode($ia['title'] ?? '', ENT_QUOTES),
                    'content' => trim($imatch[2]),
                ];
            }
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'accordion', 'settings' => [
            'items' => $items,
            'defaultOpen' => isset($a['default_open']) ? (int) $a['default_open'] : 0,
            'allowMultiple' => ($a['allow_multiple'] ?? '') === 'yes',
            'iconType' => $a['icon_type'] ?? 'plus',
            'iconPosition' => $a['icon_position'] ?? 'right',
            'titleFontSize' => isset($a['title_font_size']) ? (int) $a['title_font_size'] : 15,
            'titleFontWeight' => $a['title_font_weight'] ?? '600',
            'titleFontFamily' => $a['title_font_family'] ?? 'inherit',
            'titleLetterSpacing' => $a['title_letter_spacing'] ?? '0px',
            'titleLineHeight' => isset($a['title_line_height']) ? (float) $a['title_line_height'] : 1.4,
            'titleTextTransform' => $a['title_text_transform'] ?? 'none',
            'titleColor' => $a['title_color'] ?? '#222222',
            'titleBgColor' => $a['title_bg_color'] ?? '#f8fafc',
            'titleActiveBgColor' => $a['title_active_bg_color'] ?? '#0091ea',
            'titleActiveColor' => $a['title_active_color'] ?? '#ffffff',
            'titlePadding' => isset($a['title_padding']) ? (int) $a['title_padding'] : 16,
            'contentFontSize' => isset($a['content_font_size']) ? (int) $a['content_font_size'] : 14,
            'contentFontFamily' => $a['content_font_family'] ?? 'inherit',
            'contentLetterSpacing' => $a['content_letter_spacing'] ?? '0px',
            'contentLineHeight' => isset($a['content_line_height']) ? (float) $a['content_line_height'] : 1.6,
            'contentColor' => $a['content_color'] ?? '#555555',
            'contentBgColor' => $a['content_bg_color'] ?? '#ffffff',
            'contentPadding' => isset($a['content_padding']) ? (int) $a['content_padding'] : 16,
            'borderColor' => $a['border_color'] ?? '#e2e8f0',
            'borderRadius' => isset($a['border_radius']) ? (int) $a['border_radius'] : 8,
            'itemGap' => isset($a['item_gap']) ? (int) $a['item_gap'] : 8,
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
