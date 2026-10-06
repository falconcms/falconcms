<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_content_box] — builder JSON ⇄ shortcode.
 */
final class ContentBoxElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'box_layout', $s['boxLayout'] ?? null, 'classic-title');
        self::attrI($a, 'columns', $s['columns'] ?? null, 1);
        self::attrI($a, 'alignment', $s['alignment'] ?? null, 'left');
        self::attrI($a, 'content_alignment', $s['contentAlignment'] ?? null, 'left');
        self::attrI($a, 'link_type', $s['linkType'] ?? null, 'text');
        self::attrI($a, 'link_area', $s['linkArea'] ?? null, 'link');
        self::attrI($a, 'link_target', $s['linkTarget'] ?? null, '_self');
        self::attrI($a, 'link_arrow', (($s['linkArrow'] ?? true) ? '1' : '0'), '1');
        self::attrI($a, 'column_gap', $s['columnGap'] ?? null, 30);
        self::attrI($a, 'row_gap', $s['rowGap'] ?? null, 30);
        self::attrI($a, 'icon_size', $s['iconSize'] ?? null, 32);
        self::attrI($a, 'icon_color', $s['iconColor'] ?? null, '#2271b1');
        self::attrI($a, 'icon_bg_color', $s['iconBgColor'] ?? null);
        self::attrI($a, 'icon_border_radius', $s['iconBorderRadius'] ?? null, 50);
        self::attrI($a, 'icon_padding', $s['iconPadding'] ?? null, 0);
        self::attrI($a, 'icon_spacing', $s['iconSpacing'] ?? null, 16);
        self::attrI($a, 'title_tag', $s['titleTag'] ?? null, 'h3');
        self::attrI($a, 'title_font_family', $s['titleFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'title_font_size', $s['titleFontSize'] ?? null, 20);
        self::attrI($a, 'title_font_size_unit', $s['titleFontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'title_font_weight', $s['titleFontWeight'] ?? null, '600');
        self::attrI($a, 'title_color', $s['titleColor'] ?? null, '#222222');
        self::attrI($a, 'title_line_height', $s['titleLineHeight'] ?? null, 1.3);
        self::attrI($a, 'title_letter_spacing', $s['titleLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'title_text_transform', $s['titleTextTransform'] ?? null, 'none');
        self::attrI($a, 'title_spacing', $s['titleSpacing'] ?? null, 10);
        self::attrI($a, 'content_font_family', $s['contentFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'content_font_size', $s['contentFontSize'] ?? null, 15);
        self::attrI($a, 'content_font_size_unit', $s['contentFontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'content_font_weight', $s['contentFontWeight'] ?? null, '400');
        self::attrI($a, 'content_color', $s['contentColor'] ?? null, '#666666');
        self::attrI($a, 'content_line_height', $s['contentLineHeight'] ?? null, 1.6);
        self::attrI($a, 'content_letter_spacing', $s['contentLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'link_font_family', $s['linkFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'link_font_size', $s['linkFontSize'] ?? null, 14);
        self::attrI($a, 'link_font_size_unit', $s['linkFontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'link_font_weight', $s['linkFontWeight'] ?? null, '600');
        self::attrI($a, 'link_text_transform', $s['linkTextTransform'] ?? null, 'none');
        self::attrI($a, 'link_color', $s['linkColor'] ?? null);
        self::attrI($a, 'link_hover_color', $s['linkHoverColor'] ?? null);
        self::attrI($a, 'link_spacing', $s['linkSpacing'] ?? null, 14);
        self::attrI($a, 'link_btn_text_color', $s['linkButtonTextColor'] ?? null, '#ffffff');
        self::attrI($a, 'link_btn_pad_x', $s['linkButtonPaddingX'] ?? null, 20);
        self::attrI($a, 'link_btn_pad_y', $s['linkButtonPaddingY'] ?? null, 10);
        self::attrI($a, 'link_btn_radius', $s['linkButtonRadius'] ?? null, 4);
        self::attrI($a, 'box_bg_color', $s['boxBgColor'] ?? null);
        self::attrI($a, 'box_padding', $s['boxPadding'] ?? null);
        self::attrI($a, 'box_border_radius', $s['boxBorderRadius'] ?? null, 0);
        self::attrI($a, 'box_border_width', $s['boxBorderWidth'] ?? null, 0);
        self::attrI($a, 'box_border_color', $s['boxBorderColor'] ?? null, '#e5e7eb');
        self::attrI($a, 'box_shadow', $s['boxShadow'] ?? null, 'none');
        self::attrI($a, 'tl_line_color', $s['timelineLineColor'] ?? null, '#e5e7eb');
        self::attrI($a, 'tl_line_width', $s['timelineLineWidth'] ?? null, 2);
        self::attrI($a, 'tl_line_style', $s['timelineLineStyle'] ?? null, 'solid');
        self::attrI($a, 'tl_dot_size', $s['timelineDotSize'] ?? null, 16);
        self::attrI($a, 'tl_dot_color', $s['timelineDotColor'] ?? null);
        self::attrI($a, 'tl_dot_border_width', $s['timelineDotBorderWidth'] ?? null, 0);
        self::attrI($a, 'tl_dot_border_color', $s['timelineDotBorderColor'] ?? null, '#ffffff');
        self::attrI($a, 'tl_gap', $s['timelineGap'] ?? null, 22);
        self::attrI($a, 'tl_icon_color', $s['timelineIconColor'] ?? null, '#ffffff');
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        // Items become child shortcodes; each body carries that box's rich content.
        $items = '';
        foreach ($s['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $ia = '';
            self::attrI($ia, 'id', $item['id'] ?? null);
            self::attrI($ia, 'icon', $item['icon'] ?? null);
            self::attrI($ia, 'icon_color', $item['iconColor'] ?? null);
            self::attrI($ia, 'image', $item['image'] ?? null);
            self::attrI($ia, 'bg_color', $item['bgColor'] ?? null);
            self::attrI($ia, 'link_text', $item['linkText'] ?? null);
            self::attrI($ia, 'link_url', $item['linkUrl'] ?? null);
            $t = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
            $items .= "\n".'[falcon_content_box_item '.trim($ia).' title="'.$t.'"]'.($item['content'] ?? '').'[/falcon_content_box_item]';
        }

        return '[falcon_content_box '.trim($a).$vis.']'.$items."\n[/falcon_content_box]";
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $items = [];
        if (preg_match_all('/\[falcon_content_box_item([^\]]*)\](.*?)\[\/falcon_content_box_item\]/s', $inner, $im, PREG_SET_ORDER)) {
            foreach ($im as $imatch) {
                $ia = self::attrs($imatch[1]);
                $items[] = [
                    'id' => $ia['id'] ?? self::uid(),
                    'icon' => $ia['icon'] ?? '',
                    'iconColor' => $ia['icon_color'] ?? '',
                    'image' => $ia['image'] ?? '',
                    'bgColor' => $ia['bg_color'] ?? '',
                    'title' => htmlspecialchars_decode($ia['title'] ?? '', ENT_QUOTES),
                    'content' => trim($imatch[2]),
                    'linkText' => $ia['link_text'] ?? '',
                    'linkUrl' => $ia['link_url'] ?? '',
                ];
            }
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'content_box', 'settings' => [
            'items' => $items,
            'boxLayout' => $a['box_layout'] ?? 'classic-title',
            'columns' => isset($a['columns']) ? (int) $a['columns'] : 1,
            'alignment' => $a['alignment'] ?? 'left',
            'contentAlignment' => $a['content_alignment'] ?? 'left',
            'linkType' => $a['link_type'] ?? 'text',
            'linkArea' => $a['link_area'] ?? 'link',
            'linkTarget' => $a['link_target'] ?? '_self',
            'linkArrow' => ($a['link_arrow'] ?? '1') !== '0',
            'columnGap' => isset($a['column_gap']) ? (int) $a['column_gap'] : 30,
            'rowGap' => isset($a['row_gap']) ? (int) $a['row_gap'] : 30,
            'iconSize' => isset($a['icon_size']) ? (int) $a['icon_size'] : 32,
            'iconColor' => $a['icon_color'] ?? '#2271b1',
            'iconBgColor' => $a['icon_bg_color'] ?? '',
            'iconBorderRadius' => isset($a['icon_border_radius']) ? (int) $a['icon_border_radius'] : 50,
            'iconPadding' => isset($a['icon_padding']) ? (int) $a['icon_padding'] : 0,
            'iconSpacing' => isset($a['icon_spacing']) ? (int) $a['icon_spacing'] : 16,
            'titleTag' => $a['title_tag'] ?? 'h3',
            'titleFontFamily' => $a['title_font_family'] ?? 'inherit',
            'titleFontSize' => $a['title_font_size'] ?? 20,
            'titleFontSizeUnit' => $a['title_font_size_unit'] ?? 'px',
            'titleFontWeight' => $a['title_font_weight'] ?? '600',
            'titleColor' => $a['title_color'] ?? '#222222',
            'titleLineHeight' => isset($a['title_line_height']) ? (float) $a['title_line_height'] : 1.3,
            'titleLetterSpacing' => $a['title_letter_spacing'] ?? '0px',
            'titleTextTransform' => $a['title_text_transform'] ?? 'none',
            'titleSpacing' => isset($a['title_spacing']) ? (int) $a['title_spacing'] : 10,
            'contentFontFamily' => $a['content_font_family'] ?? 'inherit',
            'contentFontSize' => $a['content_font_size'] ?? 15,
            'contentFontSizeUnit' => $a['content_font_size_unit'] ?? 'px',
            'contentFontWeight' => $a['content_font_weight'] ?? '400',
            'contentColor' => $a['content_color'] ?? '#666666',
            'contentLineHeight' => isset($a['content_line_height']) ? (float) $a['content_line_height'] : 1.6,
            'contentLetterSpacing' => $a['content_letter_spacing'] ?? '0px',
            'linkFontFamily' => $a['link_font_family'] ?? 'inherit',
            'linkFontSize' => $a['link_font_size'] ?? 14,
            'linkFontSizeUnit' => $a['link_font_size_unit'] ?? 'px',
            'linkFontWeight' => $a['link_font_weight'] ?? '600',
            'linkTextTransform' => $a['link_text_transform'] ?? 'none',
            'linkColor' => $a['link_color'] ?? '',
            'linkHoverColor' => $a['link_hover_color'] ?? '',
            'linkSpacing' => isset($a['link_spacing']) ? (int) $a['link_spacing'] : 14,
            'linkButtonTextColor' => $a['link_btn_text_color'] ?? '#ffffff',
            'linkButtonPaddingX' => isset($a['link_btn_pad_x']) ? (int) $a['link_btn_pad_x'] : 20,
            'linkButtonPaddingY' => isset($a['link_btn_pad_y']) ? (int) $a['link_btn_pad_y'] : 10,
            'linkButtonRadius' => isset($a['link_btn_radius']) ? (int) $a['link_btn_radius'] : 4,
            'boxBgColor' => $a['box_bg_color'] ?? '',
            'boxPadding' => $a['box_padding'] ?? '',
            'boxBorderRadius' => isset($a['box_border_radius']) ? (int) $a['box_border_radius'] : 0,
            'boxBorderWidth' => isset($a['box_border_width']) ? (int) $a['box_border_width'] : 0,
            'boxBorderColor' => $a['box_border_color'] ?? '#e5e7eb',
            'boxShadow' => $a['box_shadow'] ?? 'none',
            'timelineLineColor' => $a['tl_line_color'] ?? '#e5e7eb',
            'timelineLineWidth' => isset($a['tl_line_width']) ? (int) $a['tl_line_width'] : 2,
            'timelineLineStyle' => $a['tl_line_style'] ?? 'solid',
            'timelineDotSize' => isset($a['tl_dot_size']) ? (int) $a['tl_dot_size'] : 16,
            'timelineDotColor' => $a['tl_dot_color'] ?? '',
            'timelineDotBorderWidth' => isset($a['tl_dot_border_width']) ? (int) $a['tl_dot_border_width'] : 0,
            'timelineDotBorderColor' => $a['tl_dot_border_color'] ?? '#ffffff',
            'timelineGap' => isset($a['tl_gap']) ? (int) $a['tl_gap'] : 22,
            'timelineIconColor' => $a['tl_icon_color'] ?? '#ffffff',
            'marginTop' => $a['margin_top'] ?? 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => $a['margin_bottom'] ?? 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
