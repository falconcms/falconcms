<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_card] — builder JSON ⇄ shortcode.
 */
final class CardElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'post_card_id', $s['post_card_id'] ?? null);
        self::attrI($a, 'content_source', $s['content_source'] ?? null, 'posts');
        self::attrI($a, 'post_type', $s['post_type'] ?? null, 'post');
        self::attrI($a, 'posts_by', $s['posts_by'] ?? null, 'all');
        self::attrI($a, 'posts_by_value', $s['posts_by_value'] ?? null);
        self::attrI($a, 'posts_by_cf_key', $s['posts_by_cf_key'] ?? null);
        self::attrI($a, 'posts_by_cf_value', $s['posts_by_cf_value'] ?? null);
        $postStatus = $s['post_status'] ?? ['publish'];
        if ($postStatus !== ['publish']) {
            self::attrI($a, 'post_status', is_array($postStatus) ? implode(',', $postStatus) : $postStatus);
        }
        if (!empty($s['hide_out_of_stock'])) {
            $a .= ' hide_out_of_stock="yes"';
        }
        self::attrI($a, 'posts_count', $s['posts_count'] ?? null, 6);
        self::attrI($a, 'posts_offset', $s['posts_offset'] ?? null, 0);
        self::attrI($a, 'order_by', $s['order_by'] ?? null, 'created_at');
        self::attrI($a, 'order', $s['order'] ?? null, 'desc');
        self::attrI($a, 'pagination_type', $s['pagination_type'] ?? null, 'none');
        self::attrI($a, 'nothing_found_message', $s['nothing_found_message'] ?? null, 'No posts found.');
        self::attrI($a, 'layout', $s['layout'] ?? null, 'grid');
        self::attrI($a, 'card_alignment', $s['card_alignment'] ?? null, 'left');
        self::attrI($a, 'columns', $s['columns'] ?? null, 3);
        self::attrI($a, 'columns_tablet', $s['columns_tablet'] ?? null, 2);
        self::attrI($a, 'columns_mobile', $s['columns_mobile'] ?? null, 1);
        self::attrI($a, 'column_spacing', $s['column_spacing'] ?? null, 24);
        self::attrI($a, 'row_spacing', $s['row_spacing'] ?? null, 24);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null, 0);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_right', $s['marginRight'] ?? null, 0);
        self::attrI($a, 'margin_right_unit', $s['marginRightUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null, 0);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'margin_left', $s['marginLeft'] ?? null, 0);
        self::attrI($a, 'margin_left_unit', $s['marginLeftUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        self::attrI($a, 'taxonomy_slug', $s['taxonomy_slug'] ?? null);
        $taxInclude = is_array($s['taxonomy_include'] ?? '') ? implode(',', $s['taxonomy_include']) : ($s['taxonomy_include'] ?? '');
        self::attrI($a, 'taxonomy_include', $taxInclude ?: null);
        $taxExclude = is_array($s['taxonomy_exclude'] ?? '') ? implode(',', $s['taxonomy_exclude']) : ($s['taxonomy_exclude'] ?? '');
        self::attrI($a, 'taxonomy_exclude', $taxExclude ?: null);
        self::attrI($a, 'carousel_autoplay', ($s['carousel_autoplay'] ?? false) ? 'yes' : null);
        self::attrI($a, 'carousel_autoplay_speed', $s['carousel_autoplay_speed'] ?? null, 3000);
        self::attrI($a, 'carousel_arrows', ($s['carousel_arrows'] ?? true) ? null : 'no');
        self::attrI($a, 'carousel_dots', ($s['carousel_dots'] ?? true) ? null : 'no');
        self::attrI($a, 'carousel_loop', ($s['carousel_loop'] ?? false) ? 'yes' : null);
        self::attrI($a, 'items_per_slide', $s['items_per_slide'] ?? null, 1);
        self::attrI($a, 'items_per_slide_tablet', $s['items_per_slide_tablet'] ?? null, 0);
        self::attrI($a, 'items_per_slide_mobile', $s['items_per_slide_mobile'] ?? null, 0);

        return '[falcon_card '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $statusRaw = $a['post_status'] ?? 'publish';
        $settings = [
            'post_card_id' => $a['post_card_id'] ?? '',
            'content_source' => $a['content_source'] ?? 'posts',
            'post_type' => $a['post_type'] ?? 'post',
            'posts_by' => $a['posts_by'] ?? 'all',
            'posts_by_value' => $a['posts_by_value'] ?? '',
            'posts_by_cf_key' => $a['posts_by_cf_key'] ?? '',
            'posts_by_cf_value' => $a['posts_by_cf_value'] ?? '',
            'post_status' => array_values(array_filter(array_map('trim', explode(',', $statusRaw)))),
            'hide_out_of_stock' => ($a['hide_out_of_stock'] ?? '') === 'yes',
            'posts_count' => (int) ($a['posts_count'] ?? 6),
            'posts_offset' => (int) ($a['posts_offset'] ?? 0),
            'order_by' => $a['order_by'] ?? 'created_at',
            'order' => $a['order'] ?? 'desc',
            'pagination_type' => $a['pagination_type'] ?? 'none',
            'nothing_found_message' => $a['nothing_found_message'] ?? 'No posts found.',
            'layout' => $a['layout'] ?? 'grid',
            'card_alignment' => $a['card_alignment'] ?? 'left',
            'columns' => (int) ($a['columns'] ?? 3),
            'columns_tablet' => (int) ($a['columns_tablet'] ?? 2),
            'columns_mobile' => (int) ($a['columns_mobile'] ?? 1),
            'column_spacing' => (int) ($a['column_spacing'] ?? 24),
            'row_spacing' => (int) ($a['row_spacing'] ?? 24),
            'marginTop' => self::num($a['margin_top'] ?? 0),
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginRight' => self::num($a['margin_right'] ?? 0),
            'marginRightUnit' => $a['margin_right_unit'] ?? 'px',
            'marginBottom' => self::num($a['margin_bottom'] ?? 0),
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'marginLeft' => self::num($a['margin_left'] ?? 0),
            'marginLeftUnit' => $a['margin_left_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'taxonomy_slug' => $a['taxonomy_slug'] ?? '',
            'taxonomy_include' => array_values(array_filter(explode(',', trim($a['taxonomy_include'] ?? '')))),
            'taxonomy_exclude' => array_values(array_filter(explode(',', trim($a['taxonomy_exclude'] ?? '')))),
            'carousel_autoplay' => ($a['carousel_autoplay'] ?? '') === 'yes',
            'carousel_autoplay_speed' => (int) ($a['carousel_autoplay_speed'] ?? 3000),
            'carousel_arrows' => ($a['carousel_arrows'] ?? 'yes') !== 'no',
            'carousel_dots' => ($a['carousel_dots'] ?? 'yes') !== 'no',
            'carousel_loop' => ($a['carousel_loop'] ?? '') === 'yes',
            'items_per_slide' => max(1, (int) ($a['items_per_slide'] ?? 1)),
            'items_per_slide_tablet' => max(0, (int) ($a['items_per_slide_tablet'] ?? 0)),
            'items_per_slide_mobile' => max(0, (int) ($a['items_per_slide_mobile'] ?? 0)),
            'visibility' => $vis,
        ];

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'card', 'settings' => $settings];
    }
}
