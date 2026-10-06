<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_toc] — builder JSON ⇄ shortcode.
 */
final class TocElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        // No body: the list is the page's own headings, found when the page
        // renders. Everything here is a setting about which ones count.
        $a = $base;
        self::attrI($a, 'preset', $s['preset'] ?? null, 'card');
        self::attrKeepEmpty($a, 'title', $s, 'title');
        self::attrI($a, 'min_level', $s['minLevel'] ?? null, 2);
        self::attrI($a, 'max_level', $s['maxLevel'] ?? null, 3);
        self::attrI($a, 'scope', $s['scope'] ?? null);
        self::attrI($a, 'exclude', $s['exclude'] ?? null);
        self::attrI($a, 'collapsible', !empty($s['collapsible']) ? 'yes' : null);
        self::attrI($a, 'open', (($s['openByDefault'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'sticky', !empty($s['sticky']) ? 'yes' : null);
        self::attrI($a, 'sticky_top', $s['stickyTop'] ?? null, 24);
        self::attrI($a, 'max_height', $s['maxHeight'] ?? null, 0);
        self::attrI($a, 'spy', (($s['scrollSpy'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'smooth', (($s['smoothScroll'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'offset', $s['scrollOffset'] ?? null, 80);
        self::attrI($a, 'progress', !empty($s['progress']) ? 'yes' : null);
        self::attrI($a, 'back_to_top', !empty($s['backToTop']) ? 'yes' : null);
        self::attrI($a, 'min_headings', $s['minHeadings'] ?? null, 2);
        self::attrI($a, 'bg', $s['bg'] ?? null);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null);
        self::attrI($a, 'border', $s['borderWidth'] ?? null);
        self::attrI($a, 'radius', $s['radius'] ?? null);
        self::attrI($a, 'pad_y', $s['padY'] ?? null);
        self::attrI($a, 'pad_x', $s['padX'] ?? null);
        self::attrI($a, 'title_color', $s['titleColor'] ?? null);
        self::attrI($a, 'title_size', $s['titleSize'] ?? null);
        self::attrI($a, 'title_weight', $s['titleWeight'] ?? null);
        self::attrI($a, 'link_color', $s['linkColor'] ?? null);
        self::attrI($a, 'active_color', $s['activeColor'] ?? null);
        self::attrI($a, 'active_bg', $s['activeBg'] ?? null);
        self::attrI($a, 'hover_color', $s['hoverColor'] ?? null);
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'item_gap', $s['itemGap'] ?? null);
        self::attrI($a, 'indent', $s['indent'] ?? null);
        self::attrI($a, 'marker', $s['marker'] ?? null);
        self::attrI($a, 'guide', isset($s['guide']) && $s['guide'] !== '' ? (!empty($s['guide']) ? 'yes' : 'no') : null);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        self::attrI($a, 'toc_title_family', $s['toc_title_family'] ?? null);
        self::attrI($a, 'toc_title_weight', $s['toc_title_weight'] ?? null);
        self::attrI($a, 'toc_title_size', $s['toc_title_size'] ?? null);
        self::attrI($a, 'toc_title_line_height', $s['toc_title_line_height'] ?? null);
        self::attrI($a, 'toc_title_letter_spacing', $s['toc_title_letter_spacing'] ?? null);
        self::attrI($a, 'toc_title_transform', $s['toc_title_transform'] ?? null);
        self::attrI($a, 'toc_item_family', $s['toc_item_family'] ?? null);
        self::attrI($a, 'toc_item_weight', $s['toc_item_weight'] ?? null);
        self::attrI($a, 'toc_item_size', $s['toc_item_size'] ?? null);
        self::attrI($a, 'toc_item_line_height', $s['toc_item_line_height'] ?? null);
        self::attrI($a, 'toc_item_letter_spacing', $s['toc_item_letter_spacing'] ?? null);
        self::attrI($a, 'toc_item_transform', $s['toc_item_transform'] ?? null);

        return '[falcon_toc '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'toc', 'settings' => [
            'preset' => $a['preset'] ?? 'card',
            'title' => $a['title'] ?? null,
            'minLevel' => isset($a['min_level']) ? (int) $a['min_level'] : 2,
            'maxLevel' => isset($a['max_level']) ? (int) $a['max_level'] : 3,
            'scope' => $a['scope'] ?? '',
            'exclude' => $a['exclude'] ?? '',
            'collapsible' => ($a['collapsible'] ?? '') === 'yes',
            'openByDefault' => ($a['open'] ?? '') !== 'no',
            'sticky' => ($a['sticky'] ?? '') === 'yes',
            'stickyTop' => isset($a['sticky_top']) ? (int) $a['sticky_top'] : 24,
            'maxHeight' => isset($a['max_height']) ? (int) $a['max_height'] : 0,
            'scrollSpy' => ($a['spy'] ?? '') !== 'no',
            'smoothScroll' => ($a['smooth'] ?? '') !== 'no',
            'scrollOffset' => isset($a['offset']) ? (int) $a['offset'] : 80,
            'progress' => ($a['progress'] ?? '') === 'yes',
            'backToTop' => ($a['back_to_top'] ?? '') === 'yes',
            'minHeadings' => isset($a['min_headings']) ? (int) $a['min_headings'] : 2,
            'bg' => $a['bg'] ?? '',
            'borderColor' => $a['border_color'] ?? '',
            'borderWidth' => self::numOrBlank($a['border'] ?? null),
            'radius' => self::numOrBlank($a['radius'] ?? null),
            'padY' => self::numOrBlank($a['pad_y'] ?? null),
            'padX' => self::numOrBlank($a['pad_x'] ?? null),
            'titleColor' => $a['title_color'] ?? '',
            'titleSize' => self::numOrBlank($a['title_size'] ?? null),
            'titleWeight' => $a['title_weight'] ?? '',
            'linkColor' => $a['link_color'] ?? '',
            'activeColor' => $a['active_color'] ?? '',
            'activeBg' => $a['active_bg'] ?? '',
            'hoverColor' => $a['hover_color'] ?? '',
            'fontSize' => self::numOrBlank($a['font_size'] ?? null),
            'itemGap' => self::numOrBlank($a['item_gap'] ?? null),
            'indent' => self::numOrBlank($a['indent'] ?? null),
            'marker' => $a['marker'] ?? '',
            // Three states, not two: yes, no, and "follow the preset". A plain
            // boolean would turn an untouched element into a permanent "off" the
            // first time it was saved, and the preset could never move it again.
            'guide' => isset($a['guide']) ? ($a['guide'] === 'yes') : '',
            'marginTop' => isset($a['margin_top']) ? self::num($a['margin_top']) : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? self::num($a['margin_bottom']) : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'toc_title_family' => $a['toc_title_family'] ?? null,
            'toc_title_weight' => $a['toc_title_weight'] ?? null,
            'toc_title_size' => $a['toc_title_size'] ?? null,
            'toc_title_line_height' => $a['toc_title_line_height'] ?? null,
            'toc_title_letter_spacing' => $a['toc_title_letter_spacing'] ?? null,
            'toc_title_transform' => $a['toc_title_transform'] ?? null,
            'toc_item_family' => $a['toc_item_family'] ?? null,
            'toc_item_weight' => $a['toc_item_weight'] ?? null,
            'toc_item_size' => $a['toc_item_size'] ?? null,
            'toc_item_line_height' => $a['toc_item_line_height'] ?? null,
            'toc_item_letter_spacing' => $a['toc_item_letter_spacing'] ?? null,
            'toc_item_transform' => $a['toc_item_transform'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
