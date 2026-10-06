<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_gallery] — builder JSON ⇄ shortcode.
 */
final class GalleryElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        $imgs = $s['images'] ?? [];
        $imgCount = count($imgs);
        if ($imgCount > 0) {
            self::attrI($a, 'img_n', $imgCount);
            foreach ($imgs as $idx => $img) {
                self::attrI($a, 'img_'.$idx, $img['url'] ?? null);
                self::attrI($a, 'img_'.$idx.'_a', $img['alt'] ?? null);
                self::attrI($a, 'img_'.$idx.'_c', $img['caption'] ?? null);
            }
        }
        self::attrI($a, 'cols', $s['columns'] ?? null, 3);
        self::attrI($a, 'cols_t', $s['columnsTablet'] ?? null, 2);
        self::attrI($a, 'cols_m', $s['columnsMobile'] ?? null, 1);
        self::attrI($a, 'gap', $s['gap'] ?? null, 8);
        self::attrI($a, 'ratio', $s['aspectRatio'] ?? null, 'square');
        self::attrI($a, 'radius', $s['borderRadius'] ?? null, 0);
        $lbVal = isset($s['lightbox']) ? ($s['lightbox'] ? '1' : '0') : null;
        self::attrI($a, 'lightbox', $lbVal, '1');
        self::attrI($a, 'hover', $s['hoverEffect'] ?? null, 'zoom');
        self::attrI($a, 'cap_align', $s['captionAlign'] ?? null, 'center');
        self::attrI($a, 'cap_family', $s['captionFontFamily'] ?? null, 'inherit');
        self::attrI($a, 'cap_size', $s['captionFontSize'] ?? null, '13px');
        self::attrI($a, 'cap_weight', $s['captionFontWeight'] ?? null, '400');
        self::attrI($a, 'cap_lh', $s['captionLineHeight'] ?? null, '1.4');
        self::attrI($a, 'cap_ls', $s['captionLetterSpacing'] ?? null, '0px');
        self::attrI($a, 'cap_tt', $s['captionTextTransform'] ?? null, 'none');
        self::attrI($a, 'cap_color', $s['captionColor'] ?? null, '#6b7280');
        self::attrI($a, 'img_bw', $s['imgBorderWidth'] ?? null, 0);
        self::attrI($a, 'img_bs', $s['imgBorderStyle'] ?? null, 'solid');
        self::attrI($a, 'img_bc', $s['imgBorderColor'] ?? null, '#e2e8f0');
        self::attrI($a, 'mt', $s['marginTop'] ?? null, 0);
        self::attrI($a, 'mt_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'mb', $s['marginBottom'] ?? null, 0);
        self::attrI($a, 'mb_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'mt_t', isset($s['marginTop_tablet']) && $s['marginTop_tablet'] !== '' ? $s['marginTop_tablet'] : null);
        self::attrI($a, 'mt_t_unit', isset($s['marginTop_tablet']) && $s['marginTop_tablet'] !== '' ? ($s['marginTopUnit_tablet'] ?? 'px') : null);
        self::attrI($a, 'mb_t', isset($s['marginBottom_tablet']) && $s['marginBottom_tablet'] !== '' ? $s['marginBottom_tablet'] : null);
        self::attrI($a, 'mb_t_unit', isset($s['marginBottom_tablet']) && $s['marginBottom_tablet'] !== '' ? ($s['marginBottomUnit_tablet'] ?? 'px') : null);
        self::attrI($a, 'mt_m', isset($s['marginTop_mobile']) && $s['marginTop_mobile'] !== '' ? $s['marginTop_mobile'] : null);
        self::attrI($a, 'mt_m_unit', isset($s['marginTop_mobile']) && $s['marginTop_mobile'] !== '' ? ($s['marginTopUnit_mobile'] ?? 'px') : null);
        self::attrI($a, 'mb_m', isset($s['marginBottom_mobile']) && $s['marginBottom_mobile'] !== '' ? $s['marginBottom_mobile'] : null);
        self::attrI($a, 'mb_m_unit', isset($s['marginBottom_mobile']) && $s['marginBottom_mobile'] !== '' ? ($s['marginBottomUnit_mobile'] ?? 'px') : null);
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_gallery '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $n = isset($a['img_n']) ? (int) $a['img_n'] : 0;
        $imgs = [];
        for ($i = 0; $i < $n; $i++) {
            $imgs[] = [
                'url' => $a['img_'.$i] ?? '',
                'alt' => $a['img_'.$i.'_a'] ?? '',
                'caption' => $a['img_'.$i.'_c'] ?? '',
            ];
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'gallery', 'settings' => [
            'images' => $imgs,
            'columns' => isset($a['cols']) ? (int) $a['cols'] : 3,
            'columnsTablet' => isset($a['cols_t']) ? (int) $a['cols_t'] : 2,
            'columnsMobile' => isset($a['cols_m']) ? (int) $a['cols_m'] : 1,
            'gap' => isset($a['gap']) ? (int) $a['gap'] : 8,
            'aspectRatio' => $a['ratio'] ?? 'square',
            'borderRadius' => isset($a['radius']) ? (int) $a['radius'] : 0,
            'lightbox' => ($a['lightbox'] ?? '1') !== '0',
            'hoverEffect' => $a['hover'] ?? 'zoom',
            'captionAlign' => $a['cap_align'] ?? 'center',
            'captionFontFamily' => $a['cap_family'] ?? 'inherit',
            'captionFontSize' => $a['cap_size'] ?? '13px',
            'captionFontWeight' => $a['cap_weight'] ?? '400',
            'captionLineHeight' => $a['cap_lh'] ?? '1.4',
            'captionLetterSpacing' => $a['cap_ls'] ?? '0px',
            'captionTextTransform' => $a['cap_tt'] ?? 'none',
            'captionColor' => $a['cap_color'] ?? '#6b7280',
            'imgBorderWidth' => isset($a['img_bw']) ? (int) $a['img_bw'] : 0,
            'imgBorderStyle' => $a['img_bs'] ?? 'solid',
            'imgBorderColor' => $a['img_bc'] ?? '#e2e8f0',
            'marginTop' => isset($a['mt']) ? self::num($a['mt']) : 0,
            'marginTopUnit' => $a['mt_unit'] ?? 'px',
            'marginBottom' => isset($a['mb']) ? self::num($a['mb']) : 0,
            'marginBottomUnit' => $a['mb_unit'] ?? 'px',
            'marginTop_tablet' => isset($a['mt_t']) ? self::num($a['mt_t']) : null,
            'marginTopUnit_tablet' => $a['mt_t_unit'] ?? null,
            'marginBottom_tablet' => isset($a['mb_t']) ? self::num($a['mb_t']) : null,
            'marginBottomUnit_tablet' => $a['mb_t_unit'] ?? null,
            'marginTop_mobile' => isset($a['mt_m']) ? self::num($a['mt_m']) : null,
            'marginTopUnit_mobile' => $a['mt_m_unit'] ?? null,
            'marginBottom_mobile' => isset($a['mb_m']) ? self::num($a['mb_m']) : null,
            'marginBottomUnit_mobile' => $a['mb_m_unit'] ?? null,
            'cssClass' => $a['css_class'] ?? '',
            'cssId' => $a['css_id'] ?? '',
            'visibility' => $vis,
        ]];
    }
}
