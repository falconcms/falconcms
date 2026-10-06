<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_icon_box] — builder JSON ⇄ shortcode.
 */
final class IconBoxElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'icon', $s['icon'] ?? null, 'fas fa-star');
        self::attrI($a, 'layout', $s['layout'] ?? null, 'top');
        self::attrI($a, 'alignment', $s['alignment'] ?? null, 'center');
        self::attrI($a, 'link_url', $s['linkUrl'] ?? null);
        self::attrI($a, 'link_target', $s['linkTarget'] ?? null, '_self');
        self::attrI($a, 'icon_size', $s['iconSize'] ?? null, 40);
        self::attrI($a, 'icon_size_unit', $s['iconSizeUnit'] ?? null, 'px');
        self::attrI($a, 'icon_color', $s['iconColor'] ?? null, '#0091ea');
        self::attrI($a, 'icon_bg_color', $s['iconBgColor'] ?? null);
        self::attrI($a, 'icon_bg_opacity', $s['iconBgColorOpacity'] ?? null, 1);
        self::attrI($a, 'icon_border_radius', $s['iconBorderRadius'] ?? null, 50);
        self::attrI($a, 'icon_spacing', $s['iconSpacing'] ?? null, 16);
        self::attrI($a, 'icon_padding', $s['iconPadding'] ?? null, 0);
        self::attrI($a, 'title_font_family', $s['titleFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'title_tag', $s['titleTag'] ?? null, 'h3');
        self::attrI($a, 'title_font_size', $s['titleFontSize'] ?? null, 20);
        self::attrI($a, 'title_font_size_unit', $s['titleFontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'title_font_weight', $s['titleFontWeight'] ?? null, '600');
        self::attrI($a, 'title_color', $s['titleColor'] ?? null, '#222222');
        self::attrI($a, 'title_spacing', $s['titleSpacing'] ?? null, 8);
        self::attrI($a, 'title_line_height', $s['titleLineHeight'] ?? null, 1.3);
        self::attrI($a, 'title_letter_spacing', $s['titleLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'title_text_transform', $s['titleTextTransform'] ?? null, 'none');
        self::attrI($a, 'desc_font_family', $s['descFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'desc_font_size', $s['descFontSize'] ?? null, 14);
        self::attrI($a, 'desc_font_size_unit', $s['descFontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'desc_font_weight', $s['descFontWeight'] ?? null, '400');
        self::attrI($a, 'desc_color', $s['descColor'] ?? null, '#666666');
        self::attrI($a, 'desc_line_height', $s['descLineHeight'] ?? null, 1.6);
        self::attrI($a, 'desc_letter_spacing', $s['descLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'desc_text_transform', $s['descTextTransform'] ?? null, 'none');
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        $title = htmlspecialchars($s['title'] ?? '', ENT_QUOTES);
        if ($title !== '') {
            $a .= ' title="'.$title.'"';
        }
        $body = $s['description'] ?? '';

        return '[falcon_icon_box '.trim($a).$vis.']'.$body.'[/falcon_icon_box]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'icon_box', 'settings' => [
            'icon' => $a['icon'] ?? 'fas fa-star',
            'title' => htmlspecialchars_decode($a['title'] ?? '', ENT_QUOTES),
            'description' => $inner,
            'linkUrl' => $a['link_url'] ?? '',
            'linkTarget' => $a['link_target'] ?? '_self',
            'layout' => $a['layout'] ?? 'top',
            'alignment' => $a['alignment'] ?? 'center',
            'iconSize' => isset($a['icon_size']) ? (int) $a['icon_size'] : 40,
            'iconSizeUnit' => $a['icon_size_unit'] ?? 'px',
            'iconColor' => $a['icon_color'] ?? '#0091ea',
            'iconBgColor' => $a['icon_bg_color'] ?? '',
            'iconBgColorOpacity' => isset($a['icon_bg_opacity']) ? (float) $a['icon_bg_opacity'] : 1,
            'iconBorderRadius' => isset($a['icon_border_radius']) ? (int) $a['icon_border_radius'] : 50,
            'iconSpacing' => isset($a['icon_spacing']) ? (int) $a['icon_spacing'] : 16,
            'iconPadding' => isset($a['icon_padding']) ? (int) $a['icon_padding'] : 0,
            'titleFontFamily' => $a['title_font_family'] ?? 'inherit',
            'titleTag' => $a['title_tag'] ?? 'h3',
            'titleFontSize' => isset($a['title_font_size']) ? (int) $a['title_font_size'] : 20,
            'titleFontSizeUnit' => $a['title_font_size_unit'] ?? 'px',
            'titleFontWeight' => $a['title_font_weight'] ?? '600',
            'titleColor' => $a['title_color'] ?? '#222222',
            'titleSpacing' => isset($a['title_spacing']) ? (int) $a['title_spacing'] : 8,
            'titleLineHeight' => isset($a['title_line_height']) ? (float) $a['title_line_height'] : 1.3,
            'titleLetterSpacing' => $a['title_letter_spacing'] ?? '0px',
            'titleTextTransform' => $a['title_text_transform'] ?? 'none',
            'descFontFamily' => $a['desc_font_family'] ?? 'inherit',
            'descFontSize' => isset($a['desc_font_size']) ? (int) $a['desc_font_size'] : 14,
            'descFontSizeUnit' => $a['desc_font_size_unit'] ?? 'px',
            'descFontWeight' => $a['desc_font_weight'] ?? '400',
            'descColor' => $a['desc_color'] ?? '#666666',
            'descLineHeight' => isset($a['desc_line_height']) ? (float) $a['desc_line_height'] : 1.6,
            'descLetterSpacing' => $a['desc_letter_spacing'] ?? '0px',
            'descTextTransform' => $a['desc_text_transform'] ?? 'none',
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
