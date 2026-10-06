<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_prev_next] — builder JSON ⇄ shortcode.
 */
final class PrevNextElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        // No body: the two links are worked out from the menu or the post type
        // when the page renders. Everything here says how to work them out.
        $a = $base;
        self::attrI($a, 'preset', $s['preset'] ?? null, 'cards');
        self::attrI($a, 'source', $s['source'] ?? null, 'menu');
        self::attrI($a, 'menu_id', $s['menuId'] ?? null);
        self::attrI($a, 'order_by', $s['orderBy'] ?? null, 'menu_order');
        self::attrI($a, 'order_dir', $s['orderDir'] ?? null, 'asc');
        self::attrKeepEmpty($a, 'prev_label', $s, 'prevLabel');
        self::attrKeepEmpty($a, 'next_label', $s, 'nextLabel');
        self::attrI($a, 'titles', (($s['showTitles'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'arrows', (($s['showArrows'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'prev_url', $s['prevUrl'] ?? null);
        self::attrI($a, 'prev_title', $s['prevTitle'] ?? null);
        self::attrI($a, 'next_url', $s['nextUrl'] ?? null);
        self::attrI($a, 'next_title', $s['nextTitle'] ?? null);
        self::attrI($a, 'bg', $s['bg'] ?? null);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null);
        self::attrI($a, 'hover_border', $s['hoverBorder'] ?? null);
        self::attrI($a, 'label_color', $s['labelColor'] ?? null);
        self::attrI($a, 'label_size', $s['labelSize'] ?? null);
        self::attrI($a, 'title_color', $s['titleColor'] ?? null);
        self::attrI($a, 'title_size', $s['titleSize'] ?? null);
        self::attrI($a, 'title_weight', $s['titleWeight'] ?? null);
        self::attrI($a, 'arrow_color', $s['arrowColor'] ?? null);
        self::attrI($a, 'border', $s['borderWidth'] ?? null);
        self::attrI($a, 'radius', $s['radius'] ?? null);
        self::attrI($a, 'pad_y', $s['padY'] ?? null);
        self::attrI($a, 'pad_x', $s['padX'] ?? null);
        self::attrI($a, 'gap', $s['gap'] ?? null);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        foreach (['pn_label', 'pn_title'] as $pnPrefix) {
            foreach (['family', 'weight', 'size', 'line_height', 'letter_spacing', 'transform'] as $pnKey) {
                self::attrI($a, $pnPrefix.'_'.$pnKey, $s[$pnPrefix.'_'.$pnKey] ?? null);
            }
        }

        return '[falcon_prev_next '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $pnSettings = [
            'preset' => $a['preset'] ?? 'cards',
            'source' => $a['source'] ?? 'menu',
            'menuId' => $a['menu_id'] ?? '',
            'orderBy' => $a['order_by'] ?? 'menu_order',
            'orderDir' => $a['order_dir'] ?? 'asc',
            // Written whenever the element had one, so a cleared label stays
            // cleared rather than growing "Previous" back on the next save.
            'prevLabel' => $a['prev_label'] ?? null,
            'nextLabel' => $a['next_label'] ?? null,
            'showTitles' => ($a['titles'] ?? '') !== 'no',
            'showArrows' => ($a['arrows'] ?? '') !== 'no',
            'prevUrl' => $a['prev_url'] ?? '',
            'prevTitle' => $a['prev_title'] ?? '',
            'nextUrl' => $a['next_url'] ?? '',
            'nextTitle' => $a['next_title'] ?? '',
            'bg' => $a['bg'] ?? '',
            'borderColor' => $a['border_color'] ?? '',
            'hoverBorder' => $a['hover_border'] ?? '',
            'labelColor' => $a['label_color'] ?? '',
            'labelSize' => self::numOrBlank($a['label_size'] ?? null),
            'titleColor' => $a['title_color'] ?? '',
            'titleSize' => self::numOrBlank($a['title_size'] ?? null),
            'titleWeight' => $a['title_weight'] ?? '',
            'arrowColor' => $a['arrow_color'] ?? '',
            'borderWidth' => self::numOrBlank($a['border'] ?? null),
            'radius' => self::numOrBlank($a['radius'] ?? null),
            'padY' => self::numOrBlank($a['pad_y'] ?? null),
            'padX' => self::numOrBlank($a['pad_x'] ?? null),
            'gap' => self::numOrBlank($a['gap'] ?? null),
            'marginTop' => isset($a['margin_top']) ? self::num($a['margin_top']) : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? self::num($a['margin_bottom']) : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ];

        foreach (['pn_label', 'pn_title'] as $pnPrefix) {
            foreach (['family', 'weight', 'size', 'line_height', 'letter_spacing', 'transform'] as $pnKey) {
                $pnSettings[$pnPrefix.'_'.$pnKey] = $a[$pnPrefix.'_'.$pnKey] ?? null;
            }
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'prev_next', 'settings' => $pnSettings];
    }
}
