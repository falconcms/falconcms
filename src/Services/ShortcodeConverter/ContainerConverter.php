<?php

namespace FalconCms\Core\Services\ShortcodeConverter;

/**
 * A builder container (section) — [falcon_section] — converted both ways.
 *
 * toShortcode() writes a container node and its columns; fromShortcode() reads one back;
 * settings() maps the section's attributes to the builder's settings. Keep the two
 * directions in step: a setting written but not read back is lost on the next save.
 */
final class ContainerConverter
{
    use ConverterHelpers;

    public static function toShortcode(array $container): string
    {
        $s = $container['settings'] ?? [];
        $a = [];

        $a[] = 'id="'.($container['id'] ?? '').'"';
        $a[] = 'type="'.($container['type'] ?? 'container').'"';

        // Status & layout
        self::attr($a, 'status', $s['status'] ?? null);
        self::attr($a, 'content_width', $s['contentWidth'] ?? null);

        // Height (responsive)
        self::attr($a, 'height', $s['height'] ?? null);
        self::respAttr($a, 'height', $s, 'height');
        self::attr($a, 'custom_height', $s['customHeight'] ?? null);
        self::respAttr($a, 'custom_height', $s, 'customHeight');
        self::attr($a, 'min_height', $s['minHeight'] ?? null);
        self::respAttr($a, 'min_height', $s, 'minHeight');

        // Background (responsive)
        self::attr($a, 'bg_type', $s['bgType'] ?? null);
        self::attr($a, 'bg_color', $s['bgColor'] ?? null);
        self::respAttr($a, 'bg_color', $s, 'bgColor');
        self::attrIf($a, 'bg_opacity', $s['bgColorOpacity'] ?? null, 1);
        self::attr($a, 'bg_hover_color', $s['bgHoverColor'] ?? null);
        self::attrIf($a, 'bg_hover_opacity', $s['bgHoverColorOpacity'] ?? null, 1);
        self::respAttr($a, 'bg_opacity', $s, 'bgColorOpacity');

        // Gradient
        self::attr($a, 'gradient_start', $s['bgGradientStartColor'] ?? null);
        self::attr($a, 'gradient_end', $s['bgGradientEndColor'] ?? null);
        self::attrIf($a, 'gradient_type', $s['bgGradientType'] ?? null, 'linear');
        self::attrIf($a, 'gradient_angle', $s['bgGradientAngle'] ?? null, 180);
        self::attrIf($a, 'gradient_start_pos', $s['bgGradientStartPosition'] ?? null, 0);
        self::attrIf($a, 'gradient_end_pos', $s['bgGradientEndPosition'] ?? null, 100);

        // Background image (responsive)
        self::attr($a, 'bg_image', $s['bgImage'] ?? null);
        self::respAttr($a, 'bg_image', $s, 'bgImage');
        self::attr($a, 'bg_image_dynamic', $s['bgImageDynamicSource'] ?? null);
        self::attr($a, 'bg_position', $s['bgImagePosition'] ?? null);
        self::respAttr($a, 'bg_position', $s, 'bgImagePosition');
        self::attrIf($a, 'bg_size', $s['bgImageSize'] ?? null, 'auto');
        self::respAttr($a, 'bg_size', $s, 'bgImageSize');
        self::attrIf($a, 'bg_repeat', $s['bgImageRepeat'] ?? null, 'no-repeat');
        self::respAttr($a, 'bg_repeat', $s, 'bgImageRepeat');
        self::attrIf($a, 'bg_parallax', $s['bgImageParallax'] ?? null, 'none');
        self::attrIf($a, 'bg_blend', $s['bgImageBlendMode'] ?? null, 'normal');
        self::respAttr($a, 'bg_blend', $s, 'bgImageBlendMode');

        // Spacing with units and responsive variants
        foreach (['top', 'bottom', 'left', 'right'] as $side) {
            $cap = ucfirst($side);
            $pk = 'padding'.$cap;
            $pu = $pk.'Unit';
            $mk = 'margin'.$cap;
            $mu = $mk.'Unit';

            if (array_key_exists($pk, $s) && $s[$pk] !== null) {
                $a[] = 'padding_'.$side.'="'.$s[$pk].'"';
            }
            if (!empty($s[$pu]) && $s[$pu] !== 'px') {
                $a[] = 'padding_'.$side.'_unit="'.$s[$pu].'"';
            }
            foreach (['tablet', 'mobile'] as $dev) {
                $pdv = $s[$pk.'_'.$dev] ?? null;
                if ($pdv !== null && $pdv !== '') {
                    $a[] = 'padding_'.$side.'_'.$dev.'="'.$pdv.'"';
                }
                $puv = $s[$pu.'_'.$dev] ?? null;
                if ($puv !== null && $puv !== '' && $puv !== 'px') {
                    $a[] = 'padding_'.$side.'_unit_'.$dev.'="'.$puv.'"';
                }
            }

            if (array_key_exists($mk, $s) && $s[$mk] !== null) {
                $a[] = 'margin_'.$side.'="'.$s[$mk].'"';
            }
            if (!empty($s[$mu]) && $s[$mu] !== 'px') {
                $a[] = 'margin_'.$side.'_unit="'.$s[$mu].'"';
            }
            foreach (['tablet', 'mobile'] as $dev) {
                $mdv = $s[$mk.'_'.$dev] ?? null;
                if ($mdv !== null && $mdv !== '') {
                    $a[] = 'margin_'.$side.'_'.$dev.'="'.$mdv.'"';
                }
                $muv = $s[$mu.'_'.$dev] ?? null;
                if ($muv !== null && $muv !== '' && $muv !== 'px') {
                    $a[] = 'margin_'.$side.'_unit_'.$dev.'="'.$muv.'"';
                }
            }
        }

        // Flex/alignment (responsive)
        self::attrIf($a, 'align_items', $s['alignItems'] ?? null, 'stretch');
        self::respAttr($a, 'align_items', $s, 'alignItems');
        self::attrIf($a, 'justify_content', $s['justifyContent'] ?? null, 'flex-start');
        self::respAttr($a, 'justify_content', $s, 'justifyContent');
        self::attrIf($a, 'flex_wrap', $s['flexWrap'] ?? null, 'wrap');
        self::respAttr($a, 'flex_wrap', $s, 'flexWrap');
        self::attr($a, 'row_align_content', $s['rowAlignContent'] ?? null);
        self::respAttr($a, 'row_align_content', $s, 'rowAlignContent');
        self::attr($a, 'column_gap', $s['columnGap'] ?? null);
        self::respAttr($a, 'column_gap', $s, 'columnGap');

        // HTML / CSS
        self::attrIf($a, 'html_tag', $s['htmlTag'] ?? null, 'div');
        self::attr($a, 'menu_anchor', $s['menuAnchor'] ?? null);
        self::attr($a, 'css_class', $s['cssClass'] ?? null);
        self::attr($a, 'global_id', $s['global_id'] ?? null);
        self::attr($a, 'z_index', $s['zIndex'] ?? null);
        self::respAttr($a, 'z_index', $s, 'zIndex');
        self::attrIf($a, 'overflow', $s['overflow'] ?? null, 'default');
        self::respAttr($a, 'overflow', $s, 'overflow');
        if (!empty($s['sticky'])) {
            $a[] = 'sticky="yes"';
            if (isset($s['stickyDesktop']) && $s['stickyDesktop'] === false) {
                $a[] = 'sticky_desktop="no"';
            }
            if (isset($s['stickyTablet']) && $s['stickyTablet'] === false) {
                $a[] = 'sticky_tablet="no"';
            }
            if (isset($s['stickyMobile']) && $s['stickyMobile'] === false) {
                $a[] = 'sticky_mobile="no"';
            }
            self::attr($a, 'sticky_offset', $s['stickyOffset'] ?? null);
            self::attr($a, 'sticky_z_index', $s['stickyZIndex'] ?? null);
            self::attr($a, 'sticky_bg_color', $s['stickyBgColor'] ?? null);
            if (!empty($s['stickyBgColor']) && isset($s['stickyBgColorOpacity']) && (float) $s['stickyBgColorOpacity'] < 1) {
                $a[] = 'sticky_bg_color_opacity="'.$s['stickyBgColorOpacity'].'"';
            }
        }

        // Visibility (only emit if hidden)
        $v = $s['visibility'] ?? [];
        if (!($v['mobile'] ?? true)) {
            $a[] = 'hide_mobile="yes"';
        }
        if (!($v['tablet'] ?? true)) {
            $a[] = 'hide_tablet="yes"';
        }
        if (!($v['desktop'] ?? true)) {
            $a[] = 'hide_desktop="yes"';
        }

        // Link
        self::attr($a, 'link', $s['linkUrl'] ?? null);
        self::attrIf($a, 'link_target', $s['linkTarget'] ?? null, '_self');
        self::attr($a, 'link_color', $s['linkColor'] ?? null);
        self::attrIf($a, 'link_color_opacity', $s['linkColorOpacity'] ?? null, 1);
        self::attr($a, 'link_hover_color', $s['linkHoverColor'] ?? null);
        self::attrIf($a, 'link_hover_color_opacity', $s['linkHoverColorOpacity'] ?? null, 1);

        // Border
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $side) {
            self::attr($a, 'border_'.strtolower($side), $s['borderSize'.$side] ?? null);
        }
        self::attrIf($a, 'border_color', $s['borderColor'] ?? null, '#000000');
        // Carried for the same reason the link opacities above are: this container's
        // background opacities have always round-tripped, these were simply missed, so a
        // border set to any transparency came back solid on the far side of a shortcode.
        self::attrIf($a, 'border_color_opacity', $s['borderColorOpacity'] ?? null, 1);
        foreach (['TopLeft' => 'tl', 'TopRight' => 'tr', 'BottomRight' => 'br', 'BottomLeft' => 'bl'] as $k => $short) {
            self::attr($a, 'radius_'.$short, $s['borderRadius'.$k] ?? null);
        }

        // Box shadow
        if (!empty($s['boxShadow'])) {
            $a[] = 'box_shadow="yes"';
            self::attr($a, 'shadow_color', $s['boxShadowColor'] ?? null);
            self::attr($a, 'shadow_h', $s['boxShadowPositionHorizontal'] ?? null);
            self::attr($a, 'shadow_v', $s['boxShadowPositionVertical'] ?? null);
            self::attr($a, 'shadow_blur', $s['boxShadowBlurRadius'] ?? null);
            self::attr($a, 'shadow_spread', $s['boxShadowSpreadRadius'] ?? null);
            self::attrIf($a, 'shadow_style', $s['boxShadowStyle'] ?? null, 'outer');
        }

        $colLines = [];
        foreach ($container['columns'] ?? [] as $col) {
            $colLines[] = '  '.ColumnConverter::toShortcode($col);
        }
        $inner = $colLines ? "\n".implode("\n", $colLines)."\n" : '';

        $attrStr = implode(' ', $a);
        $recovered = self::settings(self::attrs($attrStr));

        return Fidelity::appendExtras('[falcon_section '.$attrStr.']'.$inner.'[/falcon_section]', $s, $recovered);
    }

    // =========================================================================
    // Shortcode → JSON
    // =========================================================================

    public static function fromShortcode(string $attrStr, string $inner): ?array
    {
        $a = self::attrs($attrStr);
        $id = $a['id'] ?? self::uid();
        $tp = $a['type'] ?? 'container';

        $settings = self::settings($a);
        $names = Fidelity::perTypeNames('container', ['id' => $id, 'type' => $tp, 'settings' => $settings, 'columns' => []]);
        $settings = Fidelity::mergeExtras($a, $settings, $names);

        return [
            'id' => $id,
            'type' => $tp,
            'settings' => $settings,
            'columns' => ColumnConverter::parseColumns($inner),
        ];
    }

    // =========================================================================
    // Settings builders (shortcode → JSON)
    // =========================================================================

    private static function settings(array $a): array
    {
        $s = [
            'marginTop' => self::num($a['margin_top'] ?? null),
            'marginBottom' => self::num($a['margin_bottom'] ?? null),
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'paddingTop' => self::num($a['padding_top'] ?? 0),
            'paddingBottom' => self::num($a['padding_bottom'] ?? 0),
            'paddingLeft' => self::num($a['padding_left'] ?? 0),
            'paddingRight' => self::num($a['padding_right'] ?? 0),
            'paddingTopUnit' => $a['padding_top_unit'] ?? 'px',
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingLeftUnit' => $a['padding_left_unit'] ?? 'px',
            'paddingRightUnit' => $a['padding_right_unit'] ?? 'px',
            'bgColor' => $a['bg_color'] ?? null,
            'bgColorOpacity' => isset($a['bg_opacity']) ? (float) $a['bg_opacity'] : 1,
            'bgHoverColor' => $a['bg_hover_color'] ?? null,
            'bgHoverColorOpacity' => isset($a['bg_hover_opacity']) ? (float) $a['bg_hover_opacity'] : null,
            'bgType' => $a['bg_type'] ?? 'color',
            'bgGradientStartColor' => $a['gradient_start'] ?? null,
            'bgGradientEndColor' => $a['gradient_end'] ?? null,
            'bgGradientStartPosition' => isset($a['gradient_start_pos']) ? (int) $a['gradient_start_pos'] : 0,
            'bgGradientEndPosition' => isset($a['gradient_end_pos']) ? (int) $a['gradient_end_pos'] : 100,
            'bgGradientType' => $a['gradient_type'] ?? 'linear',
            'bgGradientAngle' => isset($a['gradient_angle']) ? (int) $a['gradient_angle'] : 180,
            'bgImage' => $a['bg_image'] ?? null,
            'bgImageDynamicSource' => $a['bg_image_dynamic'] ?? null,
            'bgImageSkipLazy' => false,
            'bgImagePosition' => $a['bg_position'] ?? 'center center',
            'bgImageRepeat' => $a['bg_repeat'] ?? 'no-repeat',
            'bgImageSize' => $a['bg_size'] ?? 'auto',
            'bgImageFading' => false,
            'bgImageParallax' => $a['bg_parallax'] ?? 'none',
            'bgImageBlendMode' => $a['bg_blend'] ?? 'normal',
            'contentWidth' => $a['content_width'] ?? 'site',
            'height' => $a['height'] ?? 'auto',
            'customHeight' => $a['custom_height'] ?? null,
            'minHeight' => $a['min_height'] ?? null,
            'rowAlignContent' => $a['row_align_content'] ?? null,
            'alignItems' => $a['align_items'] ?? 'stretch',
            'alignContent' => null,
            'justifyContent' => $a['justify_content'] ?? 'flex-start',
            'flexWrap' => $a['flex_wrap'] ?? 'wrap',
            'columnGap' => self::num($a['column_gap'] ?? null),
            'htmlTag' => $a['html_tag'] ?? 'div',
            'menuAnchor' => $a['menu_anchor'] ?? null,
            'visibility' => self::visibilityFromAttrs($a),
            'status' => $a['status'] ?? 'published',
            'cssClass' => $a['css_class'] ?? null,
            'global_id' => $a['global_id'] ?? null,
            'linkColor' => $a['link_color'] ?? null,
            'linkColorOpacity' => self::num($a['link_color_opacity'] ?? null) ?? 1,
            'linkHoverColor' => $a['link_hover_color'] ?? null,
            'linkHoverColorOpacity' => self::num($a['link_hover_color_opacity'] ?? null) ?? 1,
            'linkUrl' => $a['link'] ?? null,
            'linkTarget' => $a['link_target'] ?? '_self',
            'borderSizeTop' => self::num($a['border_top'] ?? null),
            'borderSizeRight' => self::num($a['border_right'] ?? null),
            'borderSizeBottom' => self::num($a['border_bottom'] ?? null),
            'borderSizeLeft' => self::num($a['border_left'] ?? null),
            'borderColor' => $a['border_color'] ?? '#000000',
            'borderColorOpacity' => self::num($a['border_color_opacity'] ?? null) ?? 1,
            'borderRadiusTopLeft' => self::num($a['radius_tl'] ?? null),
            'borderRadiusTopRight' => self::num($a['radius_tr'] ?? null),
            'borderRadiusBottomRight' => self::num($a['radius_br'] ?? null),
            'borderRadiusBottomLeft' => self::num($a['radius_bl'] ?? null),
            'boxShadow' => ($a['box_shadow'] ?? '') === 'yes',
            'boxShadowPositionVertical' => self::num($a['shadow_v'] ?? 0),
            'boxShadowPositionHorizontal' => self::num($a['shadow_h'] ?? 0),
            'boxShadowBlurRadius' => self::num($a['shadow_blur'] ?? 0),
            'boxShadowSpreadRadius' => self::num($a['shadow_spread'] ?? 0),
            'boxShadowColor' => $a['shadow_color'] ?? '#000000',
            'boxShadowStyle' => $a['shadow_style'] ?? 'outer',
            'zIndex' => self::num($a['z_index'] ?? null),
            'overflow' => $a['overflow'] ?? 'default',
            'sticky' => ($a['sticky'] ?? '') === 'yes',
            'stickyDesktop' => ($a['sticky_desktop'] ?? '') !== 'no',
            'stickyTablet' => ($a['sticky_tablet'] ?? '') !== 'no',
            'stickyMobile' => ($a['sticky_mobile'] ?? '') !== 'no',
            'stickyOffset' => self::num($a['sticky_offset'] ?? 0),
            'stickyZIndex' => self::num($a['sticky_z_index'] ?? 99),
            'stickyBgColor' => $a['sticky_bg_color'] ?? '',
            'stickyBgColorOpacity' => isset($a['sticky_bg_color_opacity']) ? (float) $a['sticky_bg_color_opacity'] : 1,
        ];
        self::addRespProps($s, $a, [
            ['bgColor',          'bg_color',            null],
            ['bgColorOpacity',   'bg_opacity',          'float'],
            ['bgImage',          'bg_image',            null],
            ['bgImagePosition',  'bg_position',         null],
            ['bgImageSize',      'bg_size',             null],
            ['bgImageRepeat',    'bg_repeat',           null],
            ['bgImageBlendMode', 'bg_blend',            null],
            ['height',           'height',              null],
            ['customHeight',     'custom_height',       null],
            ['minHeight',        'min_height',          null],
            ['alignItems',       'align_items',         null],
            ['justifyContent',   'justify_content',     null],
            ['flexWrap',         'flex_wrap',           null],
            ['rowAlignContent',  'row_align_content',   null],
            ['columnGap',        'column_gap',          'num'],
            ['paddingTop',       'padding_top',         'num'],
            ['paddingTopUnit',   'padding_top_unit',    null],
            ['paddingBottom',    'padding_bottom',      'num'],
            ['paddingBottomUnit', 'padding_bottom_unit', null],
            ['paddingLeft',      'padding_left',        'num'],
            ['paddingLeftUnit',  'padding_left_unit',   null],
            ['paddingRight',     'padding_right',       'num'],
            ['paddingRightUnit', 'padding_right_unit',  null],
            ['marginTop',        'margin_top',          'num'],
            ['marginTopUnit',    'margin_top_unit',     null],
            ['marginBottom',     'margin_bottom',       'num'],
            ['marginBottomUnit', 'margin_bottom_unit',  null],
            ['zIndex',           'z_index',             'num'],
            ['overflow',         'overflow',            null],
        ]);

        return $s;
    }
}
