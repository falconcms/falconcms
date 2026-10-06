<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_text_block], [falcon_special_text] — builder JSON ⇄ shortcode.
 */
final class SpecialTextElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrTextAnim($a, $s);
        // Typography
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null);
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'font_size_unit', $s['fontSizeUnit'] ?? null, 'px');
        self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null);
        self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
        self::attrI($a, 'text_transform', $s['textTransform'] ?? null);
        // Colors
        self::attrI($a, 'color', $s['color'] ?? null);
        self::attrI($a, 'hover_color', $s['hoverColor'] ?? null);
        // Alignment (+ responsive)
        self::attrI($a, 'align', $s['textAlign'] ?? null);
        self::attrI($a, 'align_tablet', $s['textAlign_tablet'] ?? null);
        self::attrI($a, 'align_mobile', $s['textAlign_mobile'] ?? null);
        // Margin (+ responsive)
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_tablet', $s['marginTop_tablet'] ?? null);
        self::attrI($a, 'margin_top_mobile', $s['marginTop_mobile'] ?? null);
        self::attrI($a, 'margin_right', $s['marginRight'] ?? null);
        self::attrI($a, 'margin_right_tablet', $s['marginRight_tablet'] ?? null);
        self::attrI($a, 'margin_right_mobile', $s['marginRight_mobile'] ?? null);
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_tablet', $s['marginBottom_tablet'] ?? null);
        self::attrI($a, 'margin_bottom_mobile', $s['marginBottom_mobile'] ?? null);
        self::attrI($a, 'margin_left', $s['marginLeft'] ?? null);
        self::attrI($a, 'margin_left_tablet', $s['marginLeft_tablet'] ?? null);
        self::attrI($a, 'margin_left_mobile', $s['marginLeft_mobile'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_top_unit_tablet', $s['marginTopUnit_tablet'] ?? null);
        self::attrI($a, 'margin_top_unit_mobile', $s['marginTopUnit_mobile'] ?? null);
        self::attrI($a, 'margin_right_unit', $s['marginRightUnit'] ?? null, 'px');
        self::attrI($a, 'margin_right_unit_tablet', $s['marginRightUnit_tablet'] ?? null);
        self::attrI($a, 'margin_right_unit_mobile', $s['marginRightUnit_mobile'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom_unit_tablet', $s['marginBottomUnit_tablet'] ?? null);
        self::attrI($a, 'margin_bottom_unit_mobile', $s['marginBottomUnit_mobile'] ?? null);
        self::attrI($a, 'margin_left_unit', $s['marginLeftUnit'] ?? null, 'px');
        self::attrI($a, 'margin_left_unit_tablet', $s['marginLeftUnit_tablet'] ?? null);
        self::attrI($a, 'margin_left_unit_mobile', $s['marginLeftUnit_mobile'] ?? null);
        // Padding (+ responsive)
        self::attrI($a, 'padding_top', $s['paddingTop'] ?? null);
        self::attrI($a, 'padding_top_tablet', $s['paddingTop_tablet'] ?? null);
        self::attrI($a, 'padding_top_mobile', $s['paddingTop_mobile'] ?? null);
        self::attrI($a, 'padding_right', $s['paddingRight'] ?? null);
        self::attrI($a, 'padding_right_tablet', $s['paddingRight_tablet'] ?? null);
        self::attrI($a, 'padding_right_mobile', $s['paddingRight_mobile'] ?? null);
        self::attrI($a, 'padding_bottom', $s['paddingBottom'] ?? null);
        self::attrI($a, 'padding_bottom_tablet', $s['paddingBottom_tablet'] ?? null);
        self::attrI($a, 'padding_bottom_mobile', $s['paddingBottom_mobile'] ?? null);
        self::attrI($a, 'padding_left', $s['paddingLeft'] ?? null);
        self::attrI($a, 'padding_left_tablet', $s['paddingLeft_tablet'] ?? null);
        self::attrI($a, 'padding_left_mobile', $s['paddingLeft_mobile'] ?? null);
        self::attrI($a, 'padding_top_unit', $s['paddingTopUnit'] ?? null, 'px');
        self::attrI($a, 'padding_top_unit_tablet', $s['paddingTopUnit_tablet'] ?? null);
        self::attrI($a, 'padding_top_unit_mobile', $s['paddingTopUnit_mobile'] ?? null);
        self::attrI($a, 'padding_right_unit', $s['paddingRightUnit'] ?? null, 'px');
        self::attrI($a, 'padding_right_unit_tablet', $s['paddingRightUnit_tablet'] ?? null);
        self::attrI($a, 'padding_right_unit_mobile', $s['paddingRightUnit_mobile'] ?? null);
        self::attrI($a, 'padding_bottom_unit', $s['paddingBottomUnit'] ?? null, 'px');
        self::attrI($a, 'padding_bottom_unit_tablet', $s['paddingBottomUnit_tablet'] ?? null);
        self::attrI($a, 'padding_bottom_unit_mobile', $s['paddingBottomUnit_mobile'] ?? null);
        self::attrI($a, 'padding_left_unit', $s['paddingLeftUnit'] ?? null, 'px');
        self::attrI($a, 'padding_left_unit_tablet', $s['paddingLeftUnit_tablet'] ?? null);
        self::attrI($a, 'padding_left_unit_mobile', $s['paddingLeftUnit_mobile'] ?? null);
        // CSS
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        $body = str_replace(["\r\n", "\r", "\n"], '', $s['content'] ?? '');
        $tag = $type === 'text_block' ? 'text_block' : 'special_text';

        return '[falcon_'.$tag.' '.trim($a).$vis.']'.$body.'[/falcon_'.$tag.']';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $ts = [
            ...self::textAnimSettings($a),
            'content' => trim($inner),
            // Typography
            'fontFamily' => $a['font_family'] ?? 'inherit',
            'fontSize' => $a['font_size'] ?? 16,
            'fontSizeUnit' => $a['font_size_unit'] ?? 'px',
            'fontWeight' => $a['font_weight'] ?? '400',
            'lineHeight' => $a['line_height'] ?? '1.5',
            'letterSpacing' => $a['letter_spacing'] ?? 0,
            'textTransform' => $a['text_transform'] ?? 'none',
            // Colors
            'color' => $a['color'] ?? '#333333',
            'hoverColor' => $a['hover_color'] ?? '',
            // Alignment
            'textAlign' => $a['align'] ?? 'center',
            // Margin
            'marginTop' => self::num($a['margin_top'] ?? 0),
            'marginRight' => self::num($a['margin_right'] ?? 0),
            'marginBottom' => self::num($a['margin_bottom'] ?? 0),
            'marginLeft' => self::num($a['margin_left'] ?? 0),
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginTopUnit_tablet' => $a['margin_top_unit_tablet'] ?? null,
            'marginTopUnit_mobile' => $a['margin_top_unit_mobile'] ?? null,
            'marginRightUnit' => $a['margin_right_unit'] ?? 'px',
            'marginRightUnit_tablet' => $a['margin_right_unit_tablet'] ?? null,
            'marginRightUnit_mobile' => $a['margin_right_unit_mobile'] ?? null,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'marginBottomUnit_tablet' => $a['margin_bottom_unit_tablet'] ?? null,
            'marginBottomUnit_mobile' => $a['margin_bottom_unit_mobile'] ?? null,
            'marginLeftUnit' => $a['margin_left_unit'] ?? 'px',
            'marginLeftUnit_tablet' => $a['margin_left_unit_tablet'] ?? null,
            'marginLeftUnit_mobile' => $a['margin_left_unit_mobile'] ?? null,
            // Padding
            'paddingTop' => self::num($a['padding_top'] ?? 10),
            'paddingRight' => self::num($a['padding_right'] ?? 0),
            'paddingBottom' => self::num($a['padding_bottom'] ?? 10),
            'paddingLeft' => self::num($a['padding_left'] ?? 0),
            'paddingTopUnit' => $a['padding_top_unit'] ?? 'px',
            'paddingTopUnit_tablet' => $a['padding_top_unit_tablet'] ?? null,
            'paddingTopUnit_mobile' => $a['padding_top_unit_mobile'] ?? null,
            'paddingRightUnit' => $a['padding_right_unit'] ?? 'px',
            'paddingRightUnit_tablet' => $a['padding_right_unit_tablet'] ?? null,
            'paddingRightUnit_mobile' => $a['padding_right_unit_mobile'] ?? null,
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingBottomUnit_tablet' => $a['padding_bottom_unit_tablet'] ?? null,
            'paddingBottomUnit_mobile' => $a['padding_bottom_unit_mobile'] ?? null,
            'paddingLeftUnit' => $a['padding_left_unit'] ?? 'px',
            'paddingLeftUnit_tablet' => $a['padding_left_unit_tablet'] ?? null,
            'paddingLeftUnit_mobile' => $a['padding_left_unit_mobile'] ?? null,
            // CSS
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ];
        self::addRespProps($ts, $a, [
            ['textAlign',     'align',          null],
            ['marginTop',     'margin_top',     'num'],
            ['marginRight',   'margin_right',   'num'],
            ['marginBottom',  'margin_bottom',  'num'],
            ['marginLeft',    'margin_left',    'num'],
            ['paddingTop',    'padding_top',    'num'],
            ['paddingRight',  'padding_right',  'num'],
            ['paddingBottom', 'padding_bottom', 'num'],
            ['paddingLeft',   'padding_left',   'num'],
        ]);

        return ['id' => $a['id'] ?? self::uid(), 'type' => $type, 'settings' => $ts];
    }
}
