<?php

namespace FalconCms\Core\Services\ShortcodeConverter;

use FalconCms\Core\Services\BuilderShortcodeConverter;

/**
 * A builder column — [falcon_col] — converted both ways.
 *
 * toShortcode() writes a column and its elements; fromShortcode() reads one back, and
 * parseColumns() reads every column in a container's or a row's body. settings() maps the
 * column's attributes to the builder's settings. Keep the two directions in step: a
 * setting written but not read back is lost on the next save.
 */
final class ColumnConverter
{
    use ConverterHelpers;

    /** Used by ContainerConverter for its columns and by RowElement for nested ones. */
    public static function toShortcode(array $column): string
    {
        $s = $column['settings'] ?? [];
        $a = [];

        $a[] = 'id="'.($column['id'] ?? '').'"';
        $a[] = 'width="'.($column['basis'] ?? '100%').'"';
        if (!empty($column['basis_tablet'])) {
            $a[] = 'width_tablet="'.$column['basis_tablet'].'"';
        }
        if (!empty($column['basis_mobile'])) {
            $a[] = 'width_mobile="'.$column['basis_mobile'].'"';
        }

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

        // Layout (responsive)
        self::attrIf($a, 'alignment', $s['alignment'] ?? null, 'default');
        self::respAttr($a, 'alignment', $s, 'alignment');
        self::attr($a, 'content_layout', $s['contentLayout'] ?? null);
        self::attr($a, 'align_h', $s['contentAlignH'] ?? null);
        self::respAttr($a, 'align_h', $s, 'contentAlignH');
        self::attr($a, 'align_v', $s['contentAlignV'] ?? null);
        self::respAttr($a, 'align_v', $s, 'contentAlignV');
        self::attr($a, 'gap_width', $s['gapWidth'] ?? null);
        self::attr($a, 'gap_height', $s['gapHeight'] ?? null);
        self::attrIf($a, 'html_tag', $s['htmlTag'] ?? null, 'div');
        self::attr($a, 'css_class', $s['cssClass'] ?? null);
        self::attr($a, 'css_id', $s['cssId'] ?? null);

        // Colors (responsive)
        self::attrIf($a, 'bg_color', $s['bgColor'] ?? null, 'transparent');
        self::respAttr($a, 'bg_color', $s, 'bgColor');
        self::attr($a, 'bg_hover_color', $s['bgHoverColor'] ?? null);
        self::attrIf($a, 'bg_hover_opacity', $s['bgHoverColorOpacity'] ?? null, 1);
        self::attr($a, 'text_color', $s['textColor'] ?? null);
        self::attrIf($a, 'bg_opacity', $s['bgColorOpacity'] ?? null, 1);
        self::respAttr($a, 'bg_opacity', $s, 'bgColorOpacity');
        self::attrIf($a, 'bg_type', $s['bgType'] ?? null, 'color');
        self::attrIf($a, 'hover_type', $s['hoverType'] ?? null, 'none');

        // Gradient (column)
        self::attr($a, 'gradient_start', $s['bgGradientStartColor'] ?? null);
        self::attr($a, 'gradient_end', $s['bgGradientEndColor'] ?? null);
        self::attrIf($a, 'gradient_angle', $s['bgGradientAngle'] ?? null, 180);
        self::attrIf($a, 'gradient_start_opacity', $s['bgGradientStartOpacity'] ?? null, 1);
        self::attrIf($a, 'gradient_end_opacity', $s['bgGradientEndOpacity'] ?? null, 1);
        self::attrIf($a, 'gradient_start_pos', $s['bgGradientStartPosition'] ?? null, 0);
        self::attrIf($a, 'gradient_end_pos', $s['bgGradientEndPosition'] ?? null, 100);
        self::attrIf($a, 'gradient_type', $s['bgGradientType'] ?? null, 'linear');

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
        self::attrIf($a, 'bg_blend', $s['bgImageBlendMode'] ?? null, 'normal');
        self::respAttr($a, 'bg_blend', $s, 'bgImageBlendMode');
        self::attrIf($a, 'bg_parallax', $s['bgImageParallax'] ?? null, 'none');
        if (!empty($s['bgImageSkipLazy'])) {
            $a[] = 'bg_skip_lazy="yes"';
        }
        foreach (['tablet', 'mobile'] as $_dev) {
            if (!empty($s['bgImageSkipLazy_'.$_dev])) {
                $a[] = 'bg_skip_lazy_'.$_dev.'="yes"';
            }
        }

        // Layout extras (non-responsive)
        self::attr($a, 'flex_grow', $s['flexGrow'] ?? null);
        self::attr($a, 'flex_shrink', $s['flexShrink'] ?? null);
        self::attr($a, 'max_height', $s['maxHeight'] ?? null);
        self::attr($a, 'col_spacing_left', $s['columnSpacingLeft'] ?? null);
        self::attr($a, 'col_spacing_right', $s['columnSpacingRight'] ?? null);

        // Link
        self::attr($a, 'link', $s['linkUrl'] ?? null);
        self::attrIf($a, 'link_target', $s['linkTarget'] ?? null, '_self');

        // Extra
        self::attr($a, 'z_index', $s['zIndex'] ?? null);
        self::attrIf($a, 'overflow', $s['overflow'] ?? null, 'default');
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

        // Visibility
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

        // Border
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $side) {
            self::attr($a, 'border_'.strtolower($side), $s['borderSize'.$side] ?? null);
        }
        self::attrIf($a, 'border_color', $s['borderColor'] ?? null, '#000000');
        foreach (['TopLeft' => 'tl', 'TopRight' => 'tr', 'BottomRight' => 'br', 'BottomLeft' => 'bl'] as $k => $short) {
            self::attr($a, 'radius_'.$short, $s['borderRadius'.$k] ?? null);
            self::attrIf($a, 'radius_'.$short.'_unit', $s['borderRadius'.$k.'Unit'] ?? null, 'px');
        }

        // Box shadow
        if (!empty($s['boxShadow'])) {
            $a[] = 'box_shadow="yes"';
            self::attr($a, 'shadow_h', $s['boxShadowPositionHorizontal'] ?? null);
            self::attr($a, 'shadow_v', $s['boxShadowPositionVertical'] ?? null);
            self::attr($a, 'shadow_blur', $s['boxShadowBlurRadius'] ?? null);
            self::attr($a, 'shadow_spread', $s['boxShadowSpreadRadius'] ?? null);
            self::attrIf($a, 'shadow_color', $s['boxShadowColor'] ?? null, '#000000');
            self::attrIf($a, 'shadow_style', $s['boxShadowStyle'] ?? null, 'outer');
        }

        // Responsive variants: border sizes, color, radius, shadow fields, zIndex, overflow
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $bSide) {
            self::respAttr($a, 'border_'.strtolower($bSide), $s, 'borderSize'.$bSide);
        }
        self::respAttr($a, 'border_color', $s, 'borderColor');
        foreach (['TopLeft' => 'tl', 'TopRight' => 'tr', 'BottomRight' => 'br', 'BottomLeft' => 'bl'] as $k => $short) {
            self::respAttr($a, 'radius_'.$short, $s, 'borderRadius'.$k);
            self::respAttr($a, 'radius_'.$short.'_unit', $s, 'borderRadius'.$k.'Unit');
        }
        foreach (['tablet', 'mobile'] as $bsDev) {
            if (isset($s['boxShadow_'.$bsDev])) {
                $a[] = 'box_shadow_'.$bsDev.'="'.($s['boxShadow_'.$bsDev] ? 'yes' : 'no').'"';
            }
        }
        self::respAttr($a, 'shadow_h', $s, 'boxShadowPositionHorizontal');
        self::respAttr($a, 'shadow_v', $s, 'boxShadowPositionVertical');
        self::respAttr($a, 'shadow_blur', $s, 'boxShadowBlurRadius');
        self::respAttr($a, 'shadow_spread', $s, 'boxShadowSpreadRadius');
        self::respAttr($a, 'shadow_color', $s, 'boxShadowColor');
        self::respAttr($a, 'shadow_style', $s, 'boxShadowStyle');
        self::respAttr($a, 'z_index', $s, 'zIndex');
        self::respAttr($a, 'overflow', $s, 'overflow');

        $elems = [];
        foreach ($column['elements'] ?? [] as $el) {
            $sc = BuilderShortcodeConverter::elementToShortcode($el);
            $eSettings = $el['settings'] ?? [];
            $recovered = BuilderShortcodeConverter::elementRecovered($sc, $el['type'] ?? 'text', $eSettings);
            $elems[] = Fidelity::appendExtras($sc, $eSettings, $recovered);
        }
        $inner = $elems ? ' '.implode(' ', $elems).' ' : '';

        $attrStr = implode(' ', $a);
        $recovered = self::settings(self::attrs($attrStr));

        return Fidelity::appendExtras('[falcon_col '.$attrStr.']'.$inner.'[/falcon_col]', $s, $recovered);
    }

    /**
     * Depth-counting column extractor — handles nested [falcon_col] inside [falcon_row] correctly.
     * A simple .*? regex stops at the first [/falcon_col] it finds (the inner nested one),
     * which breaks nested-row structures.
     */
    public static function parseColumns(string $content): array
    {
        $cols = [];
        $pos = 0;
        $len = strlen($content);
        $tagLen = 11; // strlen('[falcon_col')
        $closeLen = 13; // strlen('[/falcon_col]')

        while ($pos < $len) {
            $tagStart = strpos($content, '[falcon_col', $pos);
            if ($tagStart === false) {
                break;
            }

            // Must be [falcon_col] or [falcon_col ...], not [falcon_columns or similar
            $c = $content[$tagStart + $tagLen] ?? '';
            if ($c !== ' ' && $c !== ']') {
                $pos = $tagStart + $tagLen;

                continue;
            }

            $openEnd = strpos($content, ']', $tagStart);
            if ($openEnd === false) {
                break;
            }

            $attrStr = substr($content, $tagStart + $tagLen, $openEnd - $tagStart - $tagLen);
            $depth = 1;
            $search = $openEnd + 1;
            $done = false;

            while ($depth > 0 && $search < $len) {
                $nextOpen = strpos($content, '[falcon_col', $search);
                $nextClose = strpos($content, '[/falcon_col]', $search);

                if ($nextClose === false) {
                    break;
                }

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $nc = $content[$nextOpen + $tagLen] ?? '';
                    if ($nc === ' ' || $nc === ']') {
                        $depth++;
                    }
                    $search = $nextOpen + $tagLen;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $colInner = substr($content, $openEnd + 1, $nextClose - $openEnd - 1);
                        $col = self::fromShortcode($attrStr, $colInner);
                        if ($col) {
                            $cols[] = $col;
                        }
                        $pos = $nextClose + $closeLen;
                        $done = true;
                        break;
                    }
                    $search = $nextClose + $closeLen;
                }
            }

            if (!$done) {
                break;
            }
        }

        return $cols;
    }

    private static function fromShortcode(string $attrStr, string $inner): ?array
    {
        $a = self::attrs($attrStr);
        $id = $a['id'] ?? self::uid();

        $settings = self::settings($a);
        $names = Fidelity::perTypeNames('column', ['id' => $id, 'basis' => $a['width'] ?? '100%', 'settings' => $settings, 'elements' => []]);
        $settings = Fidelity::mergeExtras($a, $settings, $names);

        $column = [
            'id' => $id,
            'basis' => $a['width'] ?? '100%',
            'basis_tablet' => $a['width_tablet'] ?? null,
            'basis_mobile' => $a['width_mobile'] ?? null,
            'settings' => $settings,
            'elements' => [],
        ];

        $found = [];

        // Parse legacy [lazy_*] built-in elements
        $elemRx = '/\[lazy_(?!section\b|col\b)(\w+)([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/lazy_\1\])/';
        if (preg_match_all($elemRx, $inner, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $em) {
                $elem = BuilderShortcodeConverter::parseElement($em[1][0], $em[2][0], $em[3][0] ?? '');
                if ($elem) {
                    $found[] = ['pos' => $em[0][1], 'el' => $elem];
                }
            }
        }

        // Parse [falcon_*] built-in elements (excludes section/col; sub-items like acc_item handled inside parent)
        $falconRx = '/\[falcon_(?!section\b|col\b|acc_item\b|tab_item\b|icon_list_item\b)(\w+)([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/falcon_\1\])/';
        if (preg_match_all($falconRx, $inner, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $em) {
                $elem = BuilderShortcodeConverter::parseElement($em[1][0], $em[2][0], $em[3][0] ?? '');
                if ($elem) {
                    $found[] = ['pos' => $em[0][1], 'el' => $elem];
                }
            }
        }

        // Custom element shortcode tags (registered via falcon_builder_elements)
        foreach (CustomElementConverter::customDefs() as $type => $def) {
            $tag = $def['shortcode'] ?? $type;
            if (!$tag || str_starts_with($tag, 'lazy_')) {
                continue;
            } // lazy_* already handled above
            $rx = '/\['.preg_quote($tag, '/').'([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/'.preg_quote($tag, '/').'\])/';
            if (preg_match_all($rx, $inner, $cm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($cm as $em) {
                    $elem = CustomElementConverter::parseCustomElement($def, $em[1][0], $em[2][0] ?? '');
                    if ($elem) {
                        $found[] = ['pos' => $em[0][1], 'el' => $elem];
                    }
                }
            }
        }

        usort($found, fn ($x, $y) => $x['pos'] <=> $y['pos']);
        $column['elements'] = array_map(fn ($f) => $f['el'], $found);

        return $column;
    }

    private static function settings(array $a): array
    {
        $s = [
            'paddingTop' => self::num($a['padding_top'] ?? 0),
            'paddingBottom' => self::num($a['padding_bottom'] ?? 0),
            'paddingLeft' => self::num($a['padding_left'] ?? 0),
            'paddingRight' => self::num($a['padding_right'] ?? 0),
            'paddingTopUnit' => $a['padding_top_unit'] ?? 'px',
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingLeftUnit' => $a['padding_left_unit'] ?? 'px',
            'paddingRightUnit' => $a['padding_right_unit'] ?? 'px',
            'marginTop' => self::num($a['margin_top'] ?? 0),
            'marginBottom' => self::num($a['margin_bottom'] ?? 0),
            'marginLeft' => self::num($a['margin_left'] ?? 0),
            'marginRight' => self::num($a['margin_right'] ?? 0),
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'marginLeftUnit' => $a['margin_left_unit'] ?? 'px',
            'marginRightUnit' => $a['margin_right_unit'] ?? 'px',
            'alignment' => $a['alignment'] ?? 'default',
            'contentLayout' => $a['content_layout'] ?? null,
            'contentAlignH' => $a['align_h'] ?? 'flex-start',
            'contentAlignV' => $a['align_v'] ?? 'flex-start',
            'gapWidth' => self::num($a['gap_width'] ?? null),
            'gapHeight' => self::num($a['gap_height'] ?? null),
            'htmlTag' => $a['html_tag'] ?? 'div',
            'linkUrl' => $a['link'] ?? null,
            'linkTarget' => $a['link_target'] ?? '_self',
            'visibility' => self::visibilityFromAttrs($a),
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'textColor' => $a['text_color'] ?? null,
            'bgColor' => $a['bg_color'] ?? 'transparent',
            'bgColorOpacity' => isset($a['bg_opacity']) ? (float) $a['bg_opacity'] : 1,
            'bgHoverColor' => $a['bg_hover_color'] ?? null,
            'bgHoverColorOpacity' => isset($a['bg_hover_opacity']) ? (float) $a['bg_hover_opacity'] : null,
            'bgType' => $a['bg_type'] ?? 'color',
            'hoverType' => $a['hover_type'] ?? 'none',
            'bgGradientStartColor' => $a['gradient_start'] ?? null,
            'bgGradientEndColor' => $a['gradient_end'] ?? null,
            'bgGradientStartOpacity' => isset($a['gradient_start_opacity']) ? (float) $a['gradient_start_opacity'] : 1,
            'bgGradientEndOpacity' => isset($a['gradient_end_opacity']) ? (float) $a['gradient_end_opacity'] : 1,
            'bgGradientStartPosition' => isset($a['gradient_start_pos']) ? (int) $a['gradient_start_pos'] : 0,
            'bgGradientEndPosition' => isset($a['gradient_end_pos']) ? (int) $a['gradient_end_pos'] : 100,
            'bgGradientType' => $a['gradient_type'] ?? 'linear',
            'bgGradientAngle' => isset($a['gradient_angle']) ? (int) $a['gradient_angle'] : 180,
            'bgImage' => $a['bg_image'] ?? null,
            'bgImageDynamicSource' => $a['bg_image_dynamic'] ?? null,
            'bgImageSkipLazy' => ($a['bg_skip_lazy'] ?? '') === 'yes',
            'bgImagePosition' => $a['bg_position'] ?? 'center center',
            'bgImageRepeat' => $a['bg_repeat'] ?? 'no-repeat',
            'bgImageSize' => $a['bg_size'] ?? 'auto',
            'bgImageFading' => false,
            'bgImageParallax' => $a['bg_parallax'] ?? 'none',
            'bgImageBlendMode' => $a['bg_blend'] ?? 'normal',
            'fontSize' => null,
            'fontWeight' => null,
            'lineHeight' => null,
            'letterSpacing' => null,
            'textAlign' => null,
            'borderSizeTop' => self::num($a['border_top'] ?? null),
            'borderSizeRight' => self::num($a['border_right'] ?? null),
            'borderSizeBottom' => self::num($a['border_bottom'] ?? null),
            'borderSizeLeft' => self::num($a['border_left'] ?? null),
            'borderColor' => $a['border_color'] ?? '#000000',
            'borderRadiusTopLeft' => self::num($a['radius_tl'] ?? null),
            'borderRadiusTopRight' => self::num($a['radius_tr'] ?? null),
            'borderRadiusBottomRight' => self::num($a['radius_br'] ?? null),
            'borderRadiusBottomLeft' => self::num($a['radius_bl'] ?? null),
            'borderRadiusTopLeftUnit' => $a['radius_tl_unit'] ?? 'px',
            'borderRadiusTopRightUnit' => $a['radius_tr_unit'] ?? 'px',
            'borderRadiusBottomRightUnit' => $a['radius_br_unit'] ?? 'px',
            'borderRadiusBottomLeftUnit' => $a['radius_bl_unit'] ?? 'px',
            'boxShadow' => ($a['box_shadow'] ?? '') === 'yes',
            'boxShadowPositionVertical' => self::num($a['shadow_v'] ?? 0),
            'boxShadowPositionHorizontal' => self::num($a['shadow_h'] ?? 0),
            'boxShadowBlurRadius' => self::num($a['shadow_blur'] ?? 0),
            'boxShadowSpreadRadius' => self::num($a['shadow_spread'] ?? 0),
            'boxShadowColor' => $a['shadow_color'] ?? '#000000',
            'boxShadowStyle' => $a['shadow_style'] ?? 'outer',
            'flexGrow' => self::num($a['flex_grow'] ?? null),
            'flexShrink' => self::num($a['flex_shrink'] ?? null),
            'maxHeight' => $a['max_height'] ?? null,
            'columnSpacingLeft' => self::num($a['col_spacing_left'] ?? null),
            'columnSpacingRight' => self::num($a['col_spacing_right'] ?? null),
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
            ['alignment',        'alignment',           null],
            ['contentAlignH',    'align_h',             null],
            ['contentAlignV',    'align_v',             null],
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
            ['marginLeft',       'margin_left',         'num'],
            ['marginLeftUnit',   'margin_left_unit',    null],
            ['marginRight',      'margin_right',        'num'],
            ['marginRightUnit',  'margin_right_unit',   null],
            ['borderSizeTop',    'border_top',          'num'],
            ['borderSizeRight',  'border_right',        'num'],
            ['borderSizeBottom', 'border_bottom',       'num'],
            ['borderSizeLeft',   'border_left',         'num'],
            ['borderColor',                'border_color',           null],
            ['borderRadiusTopLeft',        'radius_tl',              'num'],
            ['borderRadiusTopRight',       'radius_tr',              'num'],
            ['borderRadiusBottomRight',    'radius_br',              'num'],
            ['borderRadiusBottomLeft',     'radius_bl',              'num'],
            ['borderRadiusTopLeftUnit',    'radius_tl_unit',         null],
            ['borderRadiusTopRightUnit',   'radius_tr_unit',         null],
            ['borderRadiusBottomRightUnit', 'radius_br_unit',         null],
            ['borderRadiusBottomLeftUnit', 'radius_bl_unit',         null],
            ['boxShadowPositionHorizontal', 'shadow_h',               'num'],
            ['boxShadowPositionVertical',  'shadow_v',               'num'],
            ['boxShadowBlurRadius',        'shadow_blur',            'num'],
            ['boxShadowSpreadRadius',      'shadow_spread',          'num'],
            ['boxShadowColor',             'shadow_color',           null],
            ['boxShadowStyle',             'shadow_style',           null],
            ['zIndex',                     'z_index',                'num'],
            ['overflow',                   'overflow',               null],
        ]);
        // Responsive boxShadow toggle (boolean, stored as 'yes'/'no' in shortcode)
        foreach (['tablet', 'mobile'] as $dev) {
            if (isset($a['box_shadow_'.$dev]) && $a['box_shadow_'.$dev] !== '') {
                $s['boxShadow_'.$dev] = $a['box_shadow_'.$dev] === 'yes';
            }
        }
        // Responsive bgImageSkipLazy
        foreach (['tablet', 'mobile'] as $dev) {
            if (isset($a['bg_skip_lazy_'.$dev]) && $a['bg_skip_lazy_'.$dev] !== '') {
                $s['bgImageSkipLazy_'.$dev] = $a['bg_skip_lazy_'.$dev] === 'yes';
            }
        }

        return $s;
    }
}
