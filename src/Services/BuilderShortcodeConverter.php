<?php

namespace Acme\CmsDashboard\Services;

/**
 * Converts Lazy Builder JSON ↔ human-readable shortcodes.
 *
 * Format mirrors Fusion Builder style — every setting is a plain attribute.
 * No base64 encoding. Null / default values are omitted to keep shortcodes short.
 *
 * Roundtrip: shortcode attributes are mapped back to the exact camelCase keys
 * the builder expects, with null defaults for any omitted setting.
 */
class BuilderShortcodeConverter
{
    // =========================================================================
    // Public API
    // =========================================================================

    public static function isBuilderJson(string $content): bool
    {
        $t = trim($content);
        if (empty($t) || ($t[0] !== '[' && $t[0] !== '{')) return false;
        $d = json_decode($t, true);
        return is_array($d) && !empty($d) && isset($d[0]['id']);
    }

    public static function isBuilderShortcode(string $content): bool
    {
        return str_contains($content, '[lazy_section');
    }

    public static function jsonToShortcodes(string $json): string
    {
        $layout = json_decode($json, true);
        if (!is_array($layout) || empty($layout)) return $json;
        return implode("\n\n", array_map([self::class, 'containerToShortcode'], $layout));
    }

    public static function shortcodesToJson(string $content): string
    {
        $layout  = [];
        $pattern = '/\[lazy_section([^\]]*)\](.*?)\[\/lazy_section\]/s';
        if (!preg_match_all($pattern, $content, $m, PREG_SET_ORDER)) return $content;
        foreach ($m as $match) {
            $c = self::parseContainer($match[1], $match[2]);
            if ($c) $layout[] = $c;
        }
        return json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // =========================================================================
    // JSON → Shortcode
    // =========================================================================

    private static function containerToShortcode(array $container): string
    {
        $s  = $container['settings'] ?? [];
        $a  = [];

        $a[] = 'id="'   . ($container['id']   ?? '') . '"';
        $a[] = 'type="' . ($container['type'] ?? 'container') . '"';

        // Status & layout
        self::attr($a, 'status',        $s['status']       ?? null);
        self::attr($a, 'content_width', $s['contentWidth'] ?? null);

        // Height (responsive)
        self::attr($a, 'height',        $s['height']       ?? null);
        self::respAttr($a, 'height', $s, 'height');
        self::attr($a, 'custom_height', $s['customHeight'] ?? null);
        self::respAttr($a, 'custom_height', $s, 'customHeight');
        self::attr($a, 'min_height',    $s['minHeight']    ?? null);
        self::respAttr($a, 'min_height', $s, 'minHeight');

        // Background (responsive)
        self::attr($a, 'bg_type',    $s['bgType']        ?? null);
        self::attr($a, 'bg_color',   $s['bgColor']       ?? null);
        self::respAttr($a, 'bg_color', $s, 'bgColor');
        self::attrIf($a, 'bg_opacity', $s['bgColorOpacity'] ?? null, 1);
        self::respAttr($a, 'bg_opacity', $s, 'bgColorOpacity');

        // Gradient
        self::attr($a, 'gradient_start',    $s['bgGradientStartColor']    ?? null);
        self::attr($a, 'gradient_end',      $s['bgGradientEndColor']      ?? null);
        self::attrIf($a, 'gradient_type',   $s['bgGradientType']          ?? null, 'linear');
        self::attrIf($a, 'gradient_angle',  $s['bgGradientAngle']         ?? null, 180);
        self::attrIf($a, 'gradient_start_pos', $s['bgGradientStartPosition'] ?? null, 0);
        self::attrIf($a, 'gradient_end_pos',   $s['bgGradientEndPosition']   ?? null, 100);

        // Background image (responsive)
        self::attr($a, 'bg_image',    $s['bgImage']         ?? null);
        self::respAttr($a, 'bg_image', $s, 'bgImage');
        self::attr($a, 'bg_position', $s['bgImagePosition'] ?? null);
        self::respAttr($a, 'bg_position', $s, 'bgImagePosition');
        self::attrIf($a, 'bg_size',      $s['bgImageSize']     ?? null, 'auto');
        self::respAttr($a, 'bg_size', $s, 'bgImageSize');
        self::attrIf($a, 'bg_repeat',    $s['bgImageRepeat']   ?? null, 'no-repeat');
        self::respAttr($a, 'bg_repeat', $s, 'bgImageRepeat');
        self::attrIf($a, 'bg_parallax',  $s['bgImageParallax'] ?? null, 'none');
        self::attrIf($a, 'bg_blend',     $s['bgImageBlendMode'] ?? null, 'normal');
        self::respAttr($a, 'bg_blend', $s, 'bgImageBlendMode');

        // Spacing with units and responsive variants
        foreach (['top', 'bottom', 'left', 'right'] as $side) {
            $cap = ucfirst($side);
            $pk = 'padding' . $cap; $pu = $pk . 'Unit';
            $mk = 'margin'  . $cap; $mu = $mk . 'Unit';

            if (array_key_exists($pk, $s) && $s[$pk] !== null) $a[] = 'padding_' . $side . '="' . $s[$pk] . '"';
            if (!empty($s[$pu]) && $s[$pu] !== 'px') $a[] = 'padding_' . $side . '_unit="' . $s[$pu] . '"';
            foreach (['tablet', 'mobile'] as $dev) {
                $pdv = $s[$pk . '_' . $dev] ?? null;
                if ($pdv !== null && $pdv !== '') $a[] = 'padding_' . $side . '_' . $dev . '="' . $pdv . '"';
                $puv = $s[$pu . '_' . $dev] ?? null;
                if ($puv !== null && $puv !== '' && $puv !== 'px') $a[] = 'padding_' . $side . '_unit_' . $dev . '="' . $puv . '"';
            }

            if (array_key_exists($mk, $s) && $s[$mk] !== null) $a[] = 'margin_' . $side . '="' . $s[$mk] . '"';
            if (!empty($s[$mu]) && $s[$mu] !== 'px') $a[] = 'margin_' . $side . '_unit="' . $s[$mu] . '"';
            foreach (['tablet', 'mobile'] as $dev) {
                $mdv = $s[$mk . '_' . $dev] ?? null;
                if ($mdv !== null && $mdv !== '') $a[] = 'margin_' . $side . '_' . $dev . '="' . $mdv . '"';
                $muv = $s[$mu . '_' . $dev] ?? null;
                if ($muv !== null && $muv !== '' && $muv !== 'px') $a[] = 'margin_' . $side . '_unit_' . $dev . '="' . $muv . '"';
            }
        }

        // Flex/alignment (responsive)
        self::attrIf($a, 'align_items',     $s['alignItems']     ?? null, 'stretch');
        self::respAttr($a, 'align_items', $s, 'alignItems');
        self::attrIf($a, 'justify_content', $s['justifyContent'] ?? null, 'flex-start');
        self::respAttr($a, 'justify_content', $s, 'justifyContent');
        self::attrIf($a, 'flex_wrap',       $s['flexWrap']       ?? null, 'wrap');
        self::respAttr($a, 'flex_wrap', $s, 'flexWrap');
        self::attr($a, 'row_align_content', $s['rowAlignContent'] ?? null);
        self::respAttr($a, 'row_align_content', $s, 'rowAlignContent');
        self::attr($a, 'column_gap', $s['columnGap'] ?? null);
        self::respAttr($a, 'column_gap', $s, 'columnGap');

        // HTML / CSS
        self::attrIf($a, 'html_tag',    $s['htmlTag']    ?? null, 'div');
        self::attr($a, 'menu_anchor',   $s['menuAnchor'] ?? null);
        self::attr($a, 'css_class',     $s['cssClass']   ?? null);
        self::attr($a, 'z_index',       $s['zIndex']     ?? null);
        self::respAttr($a, 'z_index',  $s, 'zIndex');
        self::attrIf($a, 'overflow',   $s['overflow']   ?? null, 'default');
        self::respAttr($a, 'overflow', $s, 'overflow');
        if (!empty($s['sticky'])) {
            $a[] = 'sticky="yes"';
            if (isset($s['stickyDesktop']) && $s['stickyDesktop'] === false) $a[] = 'sticky_desktop="no"';
            if (isset($s['stickyTablet'])  && $s['stickyTablet']  === false) $a[] = 'sticky_tablet="no"';
            if (isset($s['stickyMobile'])  && $s['stickyMobile']  === false) $a[] = 'sticky_mobile="no"';
            self::attr($a, 'sticky_offset',  $s['stickyOffset']  ?? null);
            self::attr($a, 'sticky_z_index', $s['stickyZIndex']  ?? null);
        }

        // Visibility (only emit if hidden)
        $v = $s['visibility'] ?? [];
        if (!($v['mobile']  ?? true)) $a[] = 'hide_mobile="yes"';
        if (!($v['tablet']  ?? true)) $a[] = 'hide_tablet="yes"';
        if (!($v['desktop'] ?? true)) $a[] = 'hide_desktop="yes"';

        // Link
        self::attr($a, 'link',        $s['linkUrl']    ?? null);
        self::attrIf($a, 'link_target', $s['linkTarget'] ?? null, '_self');
        self::attr($a, 'link_color',  $s['linkColor']  ?? null);

        // Border
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $side) {
            self::attr($a, 'border_' . strtolower($side), $s['borderSize' . $side] ?? null);
        }
        self::attrIf($a, 'border_color', $s['borderColor'] ?? null, '#000000');
        foreach (['TopLeft' => 'tl', 'TopRight' => 'tr', 'BottomRight' => 'br', 'BottomLeft' => 'bl'] as $k => $short) {
            self::attr($a, 'radius_' . $short, $s['borderRadius' . $k] ?? null);
        }

        // Box shadow
        if (!empty($s['boxShadow'])) {
            $a[] = 'box_shadow="yes"';
            self::attr($a, 'shadow_color',  $s['boxShadowColor']              ?? null);
            self::attr($a, 'shadow_h',      $s['boxShadowPositionHorizontal'] ?? null);
            self::attr($a, 'shadow_v',      $s['boxShadowPositionVertical']   ?? null);
            self::attr($a, 'shadow_blur',   $s['boxShadowBlurRadius']         ?? null);
            self::attr($a, 'shadow_spread', $s['boxShadowSpreadRadius']       ?? null);
            self::attrIf($a, 'shadow_style', $s['boxShadowStyle'] ?? null, 'outer');
        }

        $colLines = [];
        foreach ($container['columns'] ?? [] as $col) {
            $colLines[] = '  ' . self::columnToShortcode($col);
        }
        $inner = $colLines ? "\n" . implode("\n", $colLines) . "\n" : '';

        return '[lazy_section ' . implode(' ', $a) . ']' . $inner . '[/lazy_section]';
    }

    private static function columnToShortcode(array $column): string
    {
        $s = $column['settings'] ?? [];
        $a = [];

        $a[] = 'id="'    . ($column['id']    ?? '') . '"';
        $a[] = 'width="' . ($column['basis'] ?? '100%') . '"';
        if (!empty($column['basis_tablet'])) $a[] = 'width_tablet="' . $column['basis_tablet'] . '"';
        if (!empty($column['basis_mobile'])) $a[] = 'width_mobile="' . $column['basis_mobile'] . '"';

        // Spacing with units and responsive variants
        foreach (['top', 'bottom', 'left', 'right'] as $side) {
            $cap = ucfirst($side);
            $pk = 'padding' . $cap; $pu = $pk . 'Unit';
            $mk = 'margin'  . $cap; $mu = $mk . 'Unit';

            if (array_key_exists($pk, $s) && $s[$pk] !== null) $a[] = 'padding_' . $side . '="' . $s[$pk] . '"';
            if (!empty($s[$pu]) && $s[$pu] !== 'px') $a[] = 'padding_' . $side . '_unit="' . $s[$pu] . '"';
            foreach (['tablet', 'mobile'] as $dev) {
                $pdv = $s[$pk . '_' . $dev] ?? null;
                if ($pdv !== null && $pdv !== '') $a[] = 'padding_' . $side . '_' . $dev . '="' . $pdv . '"';
                $puv = $s[$pu . '_' . $dev] ?? null;
                if ($puv !== null && $puv !== '' && $puv !== 'px') $a[] = 'padding_' . $side . '_unit_' . $dev . '="' . $puv . '"';
            }

            if (array_key_exists($mk, $s) && $s[$mk] !== null) $a[] = 'margin_' . $side . '="' . $s[$mk] . '"';
            if (!empty($s[$mu]) && $s[$mu] !== 'px') $a[] = 'margin_' . $side . '_unit="' . $s[$mu] . '"';
            foreach (['tablet', 'mobile'] as $dev) {
                $mdv = $s[$mk . '_' . $dev] ?? null;
                if ($mdv !== null && $mdv !== '') $a[] = 'margin_' . $side . '_' . $dev . '="' . $mdv . '"';
                $muv = $s[$mu . '_' . $dev] ?? null;
                if ($muv !== null && $muv !== '' && $muv !== 'px') $a[] = 'margin_' . $side . '_unit_' . $dev . '="' . $muv . '"';
            }
        }

        // Layout (responsive)
        self::attrIf($a, 'alignment',      $s['alignment']      ?? null, 'default');
        self::respAttr($a, 'alignment', $s, 'alignment');
        self::attr($a, 'content_layout',   $s['contentLayout']  ?? null);
        self::attr($a, 'align_h',          $s['contentAlignH']  ?? null);
        self::respAttr($a, 'align_h', $s, 'contentAlignH');
        self::attr($a, 'align_v',          $s['contentAlignV']  ?? null);
        self::respAttr($a, 'align_v', $s, 'contentAlignV');
        self::attr($a, 'gap_width',        $s['gapWidth']       ?? null);
        self::attr($a, 'gap_height',       $s['gapHeight']      ?? null);
        self::attrIf($a, 'html_tag',       $s['htmlTag']        ?? null, 'div');
        self::attr($a, 'css_class',        $s['cssClass']       ?? null);
        self::attr($a, 'css_id',           $s['cssId']          ?? null);

        // Colors (responsive)
        self::attrIf($a, 'bg_color',       $s['bgColor']        ?? null, 'transparent');
        self::respAttr($a, 'bg_color', $s, 'bgColor');
        self::attr($a, 'text_color',        $s['textColor']      ?? null);
        self::attrIf($a, 'bg_opacity',      $s['bgColorOpacity'] ?? null, 1);
        self::respAttr($a, 'bg_opacity', $s, 'bgColorOpacity');
        self::attrIf($a, 'bg_type',        $s['bgType']         ?? null, 'color');
        self::attrIf($a, 'hover_type',     $s['hoverType']      ?? null, 'none');

        // Gradient (column)
        self::attr($a, 'gradient_start',         $s['bgGradientStartColor']    ?? null);
        self::attr($a, 'gradient_end',           $s['bgGradientEndColor']      ?? null);
        self::attrIf($a, 'gradient_angle',       $s['bgGradientAngle']         ?? null, 180);
        self::attrIf($a, 'gradient_start_opacity', $s['bgGradientStartOpacity'] ?? null, 1);
        self::attrIf($a, 'gradient_end_opacity',   $s['bgGradientEndOpacity']   ?? null, 1);
        self::attrIf($a, 'gradient_start_pos',   $s['bgGradientStartPosition'] ?? null, 0);
        self::attrIf($a, 'gradient_end_pos',     $s['bgGradientEndPosition']   ?? null, 100);
        self::attrIf($a, 'gradient_type',        $s['bgGradientType']          ?? null, 'linear');

        // Background image (responsive)
        self::attr($a, 'bg_image',         $s['bgImage']         ?? null);
        self::respAttr($a, 'bg_image', $s, 'bgImage');
        self::attr($a, 'bg_position',      $s['bgImagePosition'] ?? null);
        self::respAttr($a, 'bg_position', $s, 'bgImagePosition');
        self::attrIf($a, 'bg_size',        $s['bgImageSize']     ?? null, 'auto');
        self::respAttr($a, 'bg_size', $s, 'bgImageSize');
        self::attrIf($a, 'bg_repeat',      $s['bgImageRepeat']   ?? null, 'no-repeat');
        self::respAttr($a, 'bg_repeat', $s, 'bgImageRepeat');
        self::attrIf($a, 'bg_blend',       $s['bgImageBlendMode'] ?? null, 'normal');
        self::respAttr($a, 'bg_blend', $s, 'bgImageBlendMode');
        self::attrIf($a, 'bg_parallax',    $s['bgImageParallax'] ?? null, 'none');
        if (!empty($s['bgImageSkipLazy'])) $a[] = 'bg_skip_lazy="yes"';
        foreach (['tablet', 'mobile'] as $_dev) {
            if (!empty($s['bgImageSkipLazy_' . $_dev])) $a[] = 'bg_skip_lazy_' . $_dev . '="yes"';
        }

        // Layout extras (non-responsive)
        self::attr($a, 'flex_grow',         $s['flexGrow']           ?? null);
        self::attr($a, 'flex_shrink',       $s['flexShrink']         ?? null);
        self::attr($a, 'max_height',        $s['maxHeight']          ?? null);
        self::attr($a, 'col_spacing_left',  $s['columnSpacingLeft']  ?? null);
        self::attr($a, 'col_spacing_right', $s['columnSpacingRight'] ?? null);

        // Link
        self::attr($a, 'link',             $s['linkUrl']    ?? null);
        self::attrIf($a, 'link_target',    $s['linkTarget'] ?? null, '_self');

        // Extra
        self::attr($a, 'z_index',    $s['zIndex']   ?? null);
        self::attrIf($a, 'overflow', $s['overflow'] ?? null, 'default');
        if (!empty($s['sticky'])) {
            $a[] = 'sticky="yes"';
            if (isset($s['stickyDesktop']) && $s['stickyDesktop'] === false) $a[] = 'sticky_desktop="no"';
            if (isset($s['stickyTablet'])  && $s['stickyTablet']  === false) $a[] = 'sticky_tablet="no"';
            if (isset($s['stickyMobile'])  && $s['stickyMobile']  === false) $a[] = 'sticky_mobile="no"';
            self::attr($a, 'sticky_offset',  $s['stickyOffset']  ?? null);
            self::attr($a, 'sticky_z_index', $s['stickyZIndex']  ?? null);
        }

        // Visibility
        $v = $s['visibility'] ?? [];
        if (!($v['mobile']  ?? true)) $a[] = 'hide_mobile="yes"';
        if (!($v['tablet']  ?? true)) $a[] = 'hide_tablet="yes"';
        if (!($v['desktop'] ?? true)) $a[] = 'hide_desktop="yes"';

        // Border
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $side) {
            self::attr($a, 'border_' . strtolower($side), $s['borderSize' . $side] ?? null);
        }
        self::attrIf($a, 'border_color', $s['borderColor'] ?? null, '#000000');
        foreach (['TopLeft' => 'tl', 'TopRight' => 'tr', 'BottomRight' => 'br', 'BottomLeft' => 'bl'] as $k => $short) {
            self::attr($a, 'radius_' . $short, $s['borderRadius' . $k] ?? null);
            self::attrIf($a, 'radius_' . $short . '_unit', $s['borderRadius' . $k . 'Unit'] ?? null, 'px');
        }

        // Box shadow
        if (!empty($s['boxShadow'])) {
            $a[] = 'box_shadow="yes"';
            self::attr($a, 'shadow_h',      $s['boxShadowPositionHorizontal'] ?? null);
            self::attr($a, 'shadow_v',      $s['boxShadowPositionVertical']   ?? null);
            self::attr($a, 'shadow_blur',   $s['boxShadowBlurRadius']         ?? null);
            self::attr($a, 'shadow_spread', $s['boxShadowSpreadRadius']       ?? null);
            self::attrIf($a, 'shadow_color', $s['boxShadowColor']  ?? null, '#000000');
            self::attrIf($a, 'shadow_style', $s['boxShadowStyle']  ?? null, 'outer');
        }

        // Responsive variants: border sizes, color, radius, shadow fields, zIndex, overflow
        foreach (['Top', 'Right', 'Bottom', 'Left'] as $bSide) {
            self::respAttr($a, 'border_' . strtolower($bSide), $s, 'borderSize' . $bSide);
        }
        self::respAttr($a, 'border_color', $s, 'borderColor');
        foreach (['TopLeft' => 'tl', 'TopRight' => 'tr', 'BottomRight' => 'br', 'BottomLeft' => 'bl'] as $k => $short) {
            self::respAttr($a, 'radius_' . $short,              $s, 'borderRadius' . $k);
            self::respAttr($a, 'radius_' . $short . '_unit',    $s, 'borderRadius' . $k . 'Unit');
        }
        foreach (['tablet', 'mobile'] as $bsDev) {
            if (isset($s['boxShadow_' . $bsDev])) {
                $a[] = 'box_shadow_' . $bsDev . '="' . ($s['boxShadow_' . $bsDev] ? 'yes' : 'no') . '"';
            }
        }
        self::respAttr($a, 'shadow_h',      $s, 'boxShadowPositionHorizontal');
        self::respAttr($a, 'shadow_v',      $s, 'boxShadowPositionVertical');
        self::respAttr($a, 'shadow_blur',   $s, 'boxShadowBlurRadius');
        self::respAttr($a, 'shadow_spread', $s, 'boxShadowSpreadRadius');
        self::respAttr($a, 'shadow_color',  $s, 'boxShadowColor');
        self::respAttr($a, 'shadow_style',  $s, 'boxShadowStyle');
        self::respAttr($a, 'z_index',       $s, 'zIndex');
        self::respAttr($a, 'overflow',      $s, 'overflow');

        $elems = [];
        foreach ($column['elements'] ?? [] as $el) {
            $elems[] = self::elementToShortcode($el);
        }
        $inner = $elems ? ' ' . implode(' ', $elems) . ' ' : '';

        return '[lazy_col ' . implode(' ', $a) . ']' . $inner . '[/lazy_col]';
    }

    private static function elementToShortcode(array $el): string
    {
        $type = $el['type']     ?? 'text';
        $id   = $el['id']       ?? '';
        $s    = $el['settings'] ?? [];
        $base = $id ? 'id="' . $id . '"' : '';

        // Visibility (common to all elements)
        $visAttrs = [];
        $v = $s['visibility'] ?? [];
        if (!($v['mobile']  ?? true)) $visAttrs[] = 'hide_mobile="yes"';
        if (!($v['tablet']  ?? true)) $visAttrs[] = 'hide_tablet="yes"';
        if (!($v['desktop'] ?? true)) $visAttrs[] = 'hide_desktop="yes"';
        $vis = $visAttrs ? ' ' . implode(' ', $visAttrs) : '';

        switch ($type) {
            case 'heading': {
                $a = $base;
                self::attrI($a, 'tag',        $s['tag']        ?? null, 'h2');
                self::attrI($a, 'font_size',   $s['fontSize']   ?? null);
                self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
                self::attrI($a, 'align',       $s['textAlign']  ?? null);
                self::attrI($a, 'color',       $s['color']      ?? null);
                self::attrI($a, 'css_class',   $s['cssClass']   ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['title'] ?? '');
                return '[lazy_heading ' . trim($a) . $vis . ']' . $body . '[/lazy_heading]';
            }

            case 'title': {
                $a = $base;
                // Typography
                self::attrI($a, 'font_size',         $s['fontSize']      ?? null);
                self::attrI($a, 'font_size_unit',    $s['fontSizeUnit']  ?? null, 'px');
                self::attrI($a, 'font_weight',       $s['fontWeight']    ?? null);
                self::attrI($a, 'font_family',       $s['fontFamily']    ?? null);
                self::attrI($a, 'line_height',       $s['lineHeight']    ?? null);
                self::attrI($a, 'letter_spacing',    $s['letterSpacing'] ?? null);
                self::attrI($a, 'text_transform',    $s['textTransform'] ?? null, 'none');
                self::attrI($a, 'html_tag',          $s['htmlTag']       ?? null, 'h2');
                // Alignment
                self::attrI($a, 'align',             $s['textAlign']          ?? null);
                self::attrI($a, 'align_tablet',      $s['textAlign_tablet']   ?? null);
                self::attrI($a, 'align_mobile',      $s['textAlign_mobile']   ?? null);
                // Color / Gradient
                self::attrI($a, 'color',             $s['titleColor']         ?? null);
                self::attrI($a, 'title_hover_color', $s['titleHoverColor']    ?? null);
                self::attrI($a, 'use_gradient',      (!empty($s['useGradient']) ? 'yes' : null));
                self::attrI($a, 'gradient_angle',    $s['gradientAngle']      ?? null);
                self::attrI($a, 'gradient_start',    $s['gradientStartColor'] ?? null);
                self::attrI($a, 'gradient_end',      $s['gradientEndColor']   ?? null);
                // Separator
                self::attrI($a, 'separator',         $s['separator']          ?? null, 'default');
                self::attrI($a, 'separator_color',   $s['separatorColor']     ?? null);
                self::attrI($a, 'divider_width',     $s['dividerWidth']       ?? null, 60);
                self::attrI($a, 'divider_height',    $s['dividerHeight']      ?? null, 3);
                self::attrI($a, 'separator_spacing', $s['separatorSpacing']   ?? null, 20);
                // Text shadow
                self::attrI($a, 'text_shadow',       (!empty($s['textShadow']) ? 'yes' : null));
                self::attrI($a, 'text_shadow_h',     $s['textShadowH']        ?? null);
                self::attrI($a, 'text_shadow_v',     $s['textShadowV']        ?? null);
                self::attrI($a, 'text_shadow_blur',  $s['textShadowBlur']     ?? null);
                self::attrI($a, 'text_shadow_color', $s['textShadowColor']    ?? null);
                // Text stroke
                self::attrI($a, 'text_stroke',       (!empty($s['textStroke']) ? 'yes' : null));
                self::attrI($a, 'text_stroke_size',  $s['textStrokeSize']     ?? null, 1);
                self::attrI($a, 'text_stroke_color', $s['textStrokeColor']    ?? null);
                // Overflow
                self::attrI($a, 'text_overflow',     $s['textOverflow']       ?? null, 'initial');
                // Link
                self::attrI($a, 'use_link',          (!empty($s['useLink']) ? 'yes' : null));
                self::attrI($a, 'link_url',          $s['linkUrl']            ?? null);
                self::attrI($a, 'link_color',        $s['linkColor']          ?? null);
                self::attrI($a, 'link_hover_color',  $s['linkHoverColor']     ?? null);
                self::attrI($a, 'link_target',       $s['linkTarget']         ?? null, '_self');
                // Spacing
                self::attrI($a, 'padding_top',       $s['paddingTop']         ?? null, 20);
                self::attrI($a, 'padding_bottom',    $s['paddingBottom']      ?? null, 20);
                self::attrI($a, 'margin_top',           $s['marginTop']           ?? null, 0);
                self::attrI($a, 'margin_top_tablet',    $s['marginTop_tablet']    ?? null);
                self::attrI($a, 'margin_top_mobile',    $s['marginTop_mobile']    ?? null);
                self::attrI($a, 'margin_right',         $s['marginRight']         ?? null, 0);
                self::attrI($a, 'margin_right_tablet',  $s['marginRight_tablet']  ?? null);
                self::attrI($a, 'margin_right_mobile',  $s['marginRight_mobile']  ?? null);
                self::attrI($a, 'margin_bottom',        $s['marginBottom']        ?? null, 0);
                self::attrI($a, 'margin_bottom_tablet', $s['marginBottom_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_mobile', $s['marginBottom_mobile'] ?? null);
                self::attrI($a, 'margin_left',          $s['marginLeft']          ?? null, 0);
                self::attrI($a, 'margin_left_tablet',   $s['marginLeft_tablet']   ?? null);
                self::attrI($a, 'margin_left_mobile',   $s['marginLeft_mobile']   ?? null);
                // CSS
                self::attrI($a, 'css_class',         $s['cssClass']           ?? null);
                self::attrI($a, 'css_id',            $s['cssId']              ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['title'] ?? '');
                return '[lazy_title ' . trim($a) . $vis . ']' . $body . '[/lazy_title]';
            }

            case 'text': {
                $a = $base;
                self::attrI($a, 'font_size',   $s['fontSize']   ?? null);
                self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
                self::attrI($a, 'color',       $s['color']      ?? null);
                self::attrI($a, 'align',       $s['textAlign']  ?? null);
                self::attrI($a, 'css_class',   $s['cssClass']   ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['content'] ?? '');
                return '[lazy_text ' . trim($a) . $vis . ']' . $body . '[/lazy_text]';
            }

            case 'button': {
                $a = $base;
                // Content
                self::attrI($a, 'text',        $s['text']       ?? 'Button');
                self::attrI($a, 'link_url',    $s['linkUrl']    ?? null, '#');
                self::attrI($a, 'link_target', $s['linkTarget'] ?? null, '_self');
                // Button style
                self::attrI($a, 'button_style', $s['buttonStyle'] ?? null, 'default');
                self::attrI($a, 'button_size',  $s['buttonSize']  ?? null);
                if ($s['buttonSpan'] ?? false) $a .= ' button_span="yes"';
                // Colors + opacities
                self::attrI($a, 'bg_color',            $s['bgColor']           ?? null);
                self::attrI($a, 'bg_color_opacity',    $s['bgColorOpacity']    ?? null, 1);
                self::attrI($a, 'color',               $s['color']             ?? null);
                self::attrI($a, 'color_opacity',       $s['colorOpacity']      ?? null, 1);
                self::attrI($a, 'hover_bg_color',      $s['hoverBgColor']      ?? null);
                self::attrI($a, 'hover_bg_color_opacity', $s['hoverBgColorOpacity'] ?? null, 1);
                self::attrI($a, 'hover_color',         $s['hoverColor']        ?? null);
                self::attrI($a, 'hover_color_opacity', $s['hoverColorOpacity'] ?? null, 1);
                // Gradient + opacities
                self::attrI($a, 'bg_gradient_start_color',              $s['bgGradientStartColor']              ?? null);
                self::attrI($a, 'bg_gradient_start_opacity',            $s['bgGradientStartOpacity']            ?? null, 1);
                self::attrI($a, 'bg_gradient_end_color',                $s['bgGradientEndColor']                ?? null);
                self::attrI($a, 'bg_gradient_end_opacity',              $s['bgGradientEndOpacity']              ?? null, 1);
                self::attrI($a, 'bg_gradient_hover_start_color',        $s['bgGradientHoverStartColor']         ?? null);
                self::attrI($a, 'bg_gradient_hover_start_opacity',      $s['bgGradientHoverStartOpacity']       ?? null, 1);
                self::attrI($a, 'bg_gradient_hover_end_color',          $s['bgGradientHoverEndColor']           ?? null);
                self::attrI($a, 'bg_gradient_hover_end_opacity',        $s['bgGradientHoverEndOpacity']         ?? null, 1);
                self::attrI($a, 'bg_gradient_type',                $s['bgGradientType']              ?? null);
                self::attrI($a, 'bg_gradient_angle',               $s['bgGradientAngle']             ?? null);
                self::attrI($a, 'bg_gradient_start_pos',           $s['bgGradientStartPosition']     ?? null);
                self::attrI($a, 'bg_gradient_end_pos',             $s['bgGradientEndPosition']       ?? null);
                // Typography
                self::attrI($a, 'font_family',    $s['fontFamily']    ?? null);
                self::attrI($a, 'font_size',      $s['fontSize']      ?? null);
                self::attrI($a, 'font_weight',    $s['fontWeight']    ?? null);
                self::attrI($a, 'line_height',    $s['lineHeight']    ?? null);
                self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
                self::attrI($a, 'text_transform', $s['textTransform'] ?? null);
                // Border
                self::attrI($a, 'border_size_top',    $s['borderSizeTop']    ?? null);
                self::attrI($a, 'border_size_right',  $s['borderSizeRight']  ?? null);
                self::attrI($a, 'border_size_bottom', $s['borderSizeBottom'] ?? null);
                self::attrI($a, 'border_size_left',   $s['borderSizeLeft']   ?? null);
                self::attrI($a, 'border_color',         $s['borderColor']       ?? null);
                self::attrI($a, 'border_color_opacity', $s['borderColorOpacity'] ?? null, 1);
                self::attrI($a, 'border_radius',       $s['borderRadius']     ?? null);
                // Icon
                self::attrI($a, 'icon',          $s['icon']         ?? null);
                self::attrI($a, 'icon_position', $s['iconPosition'] ?? null, 'left');
                // Alignment (+ responsive)
                self::attrI($a, 'align',        $s['textAlign']        ?? null);
                self::attrI($a, 'align_tablet', $s['textAlign_tablet'] ?? null);
                self::attrI($a, 'align_mobile', $s['textAlign_mobile'] ?? null);
                // Margin (+ responsive)
                self::attrI($a, 'margin_top',            $s['marginTop']           ?? null);
                self::attrI($a, 'margin_top_tablet',     $s['marginTop_tablet']    ?? null);
                self::attrI($a, 'margin_top_mobile',     $s['marginTop_mobile']    ?? null);
                self::attrI($a, 'margin_right',          $s['marginRight']         ?? null);
                self::attrI($a, 'margin_right_tablet',   $s['marginRight_tablet']  ?? null);
                self::attrI($a, 'margin_right_mobile',   $s['marginRight_mobile']  ?? null);
                self::attrI($a, 'margin_bottom',         $s['marginBottom']        ?? null);
                self::attrI($a, 'margin_bottom_tablet',  $s['marginBottom_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_mobile',  $s['marginBottom_mobile'] ?? null);
                self::attrI($a, 'margin_left',           $s['marginLeft']          ?? null);
                self::attrI($a, 'margin_left_tablet',    $s['marginLeft_tablet']   ?? null);
                self::attrI($a, 'margin_left_mobile',    $s['marginLeft_mobile']   ?? null);
                // Padding (+ responsive)
                self::attrI($a, 'padding_top',            $s['paddingTop']           ?? null);
                self::attrI($a, 'padding_top_tablet',     $s['paddingTop_tablet']    ?? null);
                self::attrI($a, 'padding_top_mobile',     $s['paddingTop_mobile']    ?? null);
                self::attrI($a, 'padding_right',          $s['paddingRight']         ?? null);
                self::attrI($a, 'padding_right_tablet',   $s['paddingRight_tablet']  ?? null);
                self::attrI($a, 'padding_right_mobile',   $s['paddingRight_mobile']  ?? null);
                self::attrI($a, 'padding_bottom',         $s['paddingBottom']        ?? null);
                self::attrI($a, 'padding_bottom_tablet',  $s['paddingBottom_tablet'] ?? null);
                self::attrI($a, 'padding_bottom_mobile',  $s['paddingBottom_mobile'] ?? null);
                self::attrI($a, 'padding_left',           $s['paddingLeft']          ?? null);
                self::attrI($a, 'padding_left_tablet',    $s['paddingLeft_tablet']   ?? null);
                self::attrI($a, 'padding_left_mobile',    $s['paddingLeft_mobile']   ?? null);
                // CSS
                self::attrI($a, 'css_class', $s['cssClass'] ?? null);
                self::attrI($a, 'css_id',    $s['cssId']    ?? null);
                return '[lazy_button ' . trim($a) . $vis . ' /]';
            }

            case 'image': {
                $a = $base;
                self::attrI($a, 'src',       $s['src']       ?? '');
                self::attrI($a, 'alt',       $s['alt']       ?? '');
                self::attrI($a, 'width',     $s['width']     ?? null);
                self::attrI($a, 'align',     $s['alignment'] ?? null);
                self::attrI($a, 'css_class', $s['cssClass']  ?? null);
                return '[lazy_image ' . trim($a) . $vis . ' /]';
            }

            case 'spacer': {
                $a = $base;
                self::attrI($a, 'height', $s['height'] ?? 20);
                return '[lazy_spacer ' . trim($a) . $vis . ' /]';
            }

            case 'video': {
                $a = $base;
                self::attrI($a, 'src',    $s['src']    ?? '');
                self::attrI($a, 'type',   $s['type']   ?? null);
                self::attrI($a, 'width',  $s['width']  ?? null);
                self::attrI($a, 'height', $s['height'] ?? null);
                return '[lazy_video ' . trim($a) . $vis . ' /]';
            }

            case 'text_block':
            case 'special_text': {
                $a = $base;
                // Typography
                self::attrI($a, 'font_family',    $s['fontFamily']    ?? null);
                self::attrI($a, 'font_size',      $s['fontSize']      ?? null);
                self::attrI($a, 'font_size_unit', $s['fontSizeUnit']  ?? null, 'px');
                self::attrI($a, 'font_weight',    $s['fontWeight']    ?? null);
                self::attrI($a, 'line_height',    $s['lineHeight']    ?? null);
                self::attrI($a, 'letter_spacing', $s['letterSpacing'] ?? null);
                self::attrI($a, 'text_transform', $s['textTransform'] ?? null);
                // Colors
                self::attrI($a, 'color',          $s['color']         ?? null);
                self::attrI($a, 'hover_color',    $s['hoverColor']    ?? null);
                // Alignment (+ responsive)
                self::attrI($a, 'align',          $s['textAlign']          ?? null);
                self::attrI($a, 'align_tablet',   $s['textAlign_tablet']   ?? null);
                self::attrI($a, 'align_mobile',   $s['textAlign_mobile']   ?? null);
                // Margin (+ responsive)
                self::attrI($a, 'margin_top',           $s['marginTop']           ?? null);
                self::attrI($a, 'margin_top_tablet',    $s['marginTop_tablet']    ?? null);
                self::attrI($a, 'margin_top_mobile',    $s['marginTop_mobile']    ?? null);
                self::attrI($a, 'margin_right',         $s['marginRight']         ?? null);
                self::attrI($a, 'margin_right_tablet',  $s['marginRight_tablet']  ?? null);
                self::attrI($a, 'margin_right_mobile',  $s['marginRight_mobile']  ?? null);
                self::attrI($a, 'margin_bottom',        $s['marginBottom']        ?? null);
                self::attrI($a, 'margin_bottom_tablet', $s['marginBottom_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_mobile', $s['marginBottom_mobile'] ?? null);
                self::attrI($a, 'margin_left',          $s['marginLeft']          ?? null);
                self::attrI($a, 'margin_left_tablet',   $s['marginLeft_tablet']   ?? null);
                self::attrI($a, 'margin_left_mobile',   $s['marginLeft_mobile']   ?? null);
                // Padding (+ responsive)
                self::attrI($a, 'padding_top',           $s['paddingTop']           ?? null);
                self::attrI($a, 'padding_top_tablet',    $s['paddingTop_tablet']    ?? null);
                self::attrI($a, 'padding_top_mobile',    $s['paddingTop_mobile']    ?? null);
                self::attrI($a, 'padding_right',         $s['paddingRight']         ?? null);
                self::attrI($a, 'padding_right_tablet',  $s['paddingRight_tablet']  ?? null);
                self::attrI($a, 'padding_right_mobile',  $s['paddingRight_mobile']  ?? null);
                self::attrI($a, 'padding_bottom',        $s['paddingBottom']        ?? null);
                self::attrI($a, 'padding_bottom_tablet', $s['paddingBottom_tablet'] ?? null);
                self::attrI($a, 'padding_bottom_mobile', $s['paddingBottom_mobile'] ?? null);
                self::attrI($a, 'padding_left',          $s['paddingLeft']          ?? null);
                self::attrI($a, 'padding_left_tablet',   $s['paddingLeft_tablet']   ?? null);
                self::attrI($a, 'padding_left_mobile',   $s['paddingLeft_mobile']   ?? null);
                // CSS
                self::attrI($a, 'css_class', $s['cssClass'] ?? null);
                self::attrI($a, 'css_id',    $s['cssId']    ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['content'] ?? '');
                $tag = $type === 'text_block' ? 'text_block' : 'special_text';
                return '[lazy_' . $tag . ' ' . trim($a) . $vis . ']' . $body . '[/lazy_' . $tag . ']';
            }

            case 'menu': {
                $a = $base;
                // General
                self::attrI($a, 'menu_id',           $s['menuId']         ?? null);
                self::attrI($a, 'layout',             $s['layout']         ?? null, 'horizontal');
                self::attrI($a, 'margin_top',         $s['marginTop']      ?? null);
                self::attrI($a, 'margin_bottom',      $s['marginBottom']   ?? null);
                self::attrI($a, 'item_transition',    $s['itemTransition'] ?? null);
                self::attrI($a, 'item_transition_ms', $s['itemTransitionMs'] ?? null);
                self::attrI($a, 'submenu_space',      $s['submenuSpace']   ?? null, 10);
                $aso = $s['arrowScopeObj'] ?? [];
                if (!($aso['main']    ?? true))  $a .= ' arrow_main="no"';
                if  ($aso['active']   ?? false)  $a .= ' arrow_active="yes"';
                if  ($aso['submenu']  ?? false)  $a .= ' arrow_submenu="yes"';
                self::attrI($a, 'css_class',          $s['cssClass']       ?? null);
                self::attrI($a, 'css_id',             $s['cssId']          ?? null);
                // Design
                self::attrI($a, 'min_height',         $s['minHeight']      ?? null);
                self::attrI($a, 'align_items',        $s['alignItems']     ?? null, 'flex-start');
                self::attrI($a, 'justification',      $s['justification']  ?? null, 'flex-start');
                self::attrI($a, 'font_family',        $s['fontFamily']     ?? null, 'inherit');
                self::attrI($a, 'font_size',          $s['fontSize']       ?? null);
                self::attrI($a, 'font_weight',        $s['fontWeight']     ?? null);
                self::attrI($a, 'line_height',        $s['lineHeight']     ?? null);
                self::attrI($a, 'letter_spacing',     $s['letterSpacing']  ?? null);
                self::attrI($a, 'text_transform',     $s['textTransform']  ?? null);
                self::attrI($a, 'item_padding_top',   $s['itemPaddingTop']    ?? null);
                self::attrI($a, 'item_padding_right', $s['itemPaddingRight']  ?? null);
                self::attrI($a, 'item_padding_bottom',$s['itemPaddingBottom'] ?? null);
                self::attrI($a, 'item_padding_left',  $s['itemPaddingLeft']   ?? null);
                self::attrI($a, 'item_spacing',       $s['itemSpacing']       ?? null);
                self::attrI($a, 'item_border_radius', $s['itemBorderRadius']  ?? null);
                self::attrI($a, 'item_bg_color',      $s['itemBgColor']       ?? null);
                self::attrI($a, 'item_bg_color_hover',$s['itemBgColorHover']  ?? null);
                self::attrI($a, 'item_color',         $s['itemColor']         ?? null);
                self::attrI($a, 'item_color_hover',   $s['itemColorHover']    ?? null);
                self::attrI($a, 'item_border_top',    $s['itemBorderSizeTop']    ?? null);
                self::attrI($a, 'item_border_right',  $s['itemBorderSizeRight']  ?? null);
                self::attrI($a, 'item_border_bottom', $s['itemBorderSizeBottom'] ?? null);
                self::attrI($a, 'item_border_left',   $s['itemBorderSizeLeft']   ?? null);
                self::attrI($a, 'item_border_color',  $s['itemBorderColor']      ?? null);
                self::attrI($a, 'item_border_top_h',  $s['itemBorderSizeTopHover']    ?? null);
                self::attrI($a, 'item_border_right_h',$s['itemBorderSizeRightHover']  ?? null);
                self::attrI($a, 'item_border_bottom_h',$s['itemBorderSizeBottomHover'] ?? null);
                self::attrI($a, 'item_border_left_h', $s['itemBorderSizeLeftHover']   ?? null);
                self::attrI($a, 'item_border_color_h',$s['itemBorderColorHover']      ?? null);
                // Submenu
                self::attrI($a, 'show_arrows',          $s['showArrows']         ?? null, 'yes');
                self::attrI($a, 'submenu_direction',    $s['submenuDirection']   ?? null, 'right');
                self::attrI($a, 'submenu_transition',   $s['submenuTransition']  ?? null, 'fade');
                self::attrI($a, 'submenu_min_width',    $s['submenuMinWidth']    ?? null);
                self::attrI($a, 'submenu_max_width',    $s['submenuMaxWidth']    ?? null);
                self::attrI($a, 'sub_sub_direction',    $s['subSubMenuDirection'] ?? null, 'right');
                self::attrI($a, 'sub_sub_offset',       $s['subSubMenuOffset']   ?? null, 5);
                self::attrI($a, 'submenu_font_family',  $s['submenuFontFamily']  ?? null, 'inherit');
                self::attrI($a, 'submenu_font_size',    $s['submenuFontSize']    ?? null);
                self::attrI($a, 'submenu_line_height',  $s['submenuLineHeight']  ?? null);
                self::attrI($a, 'submenu_letter_sp',    $s['submenuLetterSpacing'] ?? null);
                self::attrI($a, 'submenu_text_transform',$s['submenuTextTransform'] ?? null);
                self::attrI($a, 'submenu_text_align',   $s['submenuTextAlign']   ?? null);
                self::attrI($a, 'submenu_pt',           $s['submenuPaddingTop']    ?? null);
                self::attrI($a, 'submenu_pr',           $s['submenuPaddingRight']  ?? null);
                self::attrI($a, 'submenu_pb',           $s['submenuPaddingBottom'] ?? null);
                self::attrI($a, 'submenu_pl',           $s['submenuPaddingLeft']   ?? null);
                self::attrI($a, 'submenu_radius_tl',    $s['submenuBorderRadiusTopLeft']     ?? null);
                self::attrI($a, 'submenu_radius_tr',    $s['submenuBorderRadiusTopRight']    ?? null);
                self::attrI($a, 'submenu_radius_br',    $s['submenuBorderRadiusBottomRight'] ?? null);
                self::attrI($a, 'submenu_radius_bl',    $s['submenuBorderRadiusBottomLeft']  ?? null);
                self::attrI($a, 'submenu_shadow',       $s['submenuBoxShadow']    ?? null, 'no');
                self::attrI($a, 'submenu_shadow_color', $s['submenuShadowColor']  ?? null);
                self::attrI($a, 'submenu_shadow_h',     $s['submenuShadowH']      ?? null);
                self::attrI($a, 'submenu_shadow_v',     $s['submenuShadowV']      ?? null);
                self::attrI($a, 'submenu_shadow_blur',  $s['submenuShadowBlur']   ?? null);
                self::attrI($a, 'submenu_shadow_spread',$s['submenuShadowSpread'] ?? null);
                self::attrI($a, 'submenu_thumb_w',      $s['submenuThumbWidth']   ?? null);
                self::attrI($a, 'submenu_thumb_h',      $s['submenuThumbHeight']  ?? null);
                self::attrI($a, 'submenu_sep_color',    $s['submenuSeparatorColor'] ?? null);
                self::attrI($a, 'submenu_bg_color',     $s['submenuBgColor']      ?? null);
                self::attrI($a, 'submenu_text_color',   $s['submenuTextColor']    ?? null);
                self::attrI($a, 'submenu_text_color_h', $s['submenuTextColorHover'] ?? null);
                // Mobile
                self::attrI($a, 'mobile_breakpoint',   $s['mobileCollapseBreakpoint'] ?? null, 'tablet');
                self::attrI($a, 'mobile_mode',          $s['mobileMenuMode']       ?? null, 'collapsed');
                self::attrI($a, 'mobile_expand_mode',   $s['mobileMenuExpandMode'] ?? null, 'full-width-static');
                self::attrI($a, 'mobile_sidebar_side',  $s['mobileMenuSidebarSide'] ?? null, 'left');
                self::attrI($a, 'mobile_trigger_pt',    $s['mobileMenuTriggerPaddingTop']    ?? null, 10);
                self::attrI($a, 'mobile_trigger_pr',    $s['mobileMenuTriggerPaddingRight']  ?? null, 15);
                self::attrI($a, 'mobile_trigger_pb',    $s['mobileMenuTriggerPaddingBottom'] ?? null, 10);
                self::attrI($a, 'mobile_trigger_pl',    $s['mobileMenuTriggerPaddingLeft']   ?? null, 15);
                self::attrI($a, 'mobile_trigger_bg',    $s['mobileMenuTriggerBgColor']     ?? null);
                self::attrI($a, 'mobile_trigger_color', $s['mobileMenuTriggerTextColor']   ?? null);
                self::attrI($a, 'mobile_trigger_text',  $s['mobileMenuTriggerText']        ?? null);
                self::attrI($a, 'mobile_expand_icon',   $s['mobileMenuTriggerExpandIcon']  ?? null);
                self::attrI($a, 'mobile_collapse_icon', $s['mobileMenuTriggerCollapseIcon'] ?? null);
                self::attrI($a, 'mobile_trigger_fs',    $s['mobileMenuTriggerFontSize']    ?? null);
                self::attrI($a, 'mobile_trigger_align', $s['mobileMenuTriggerHorizontalAlign'] ?? null, 'flex-start');
                self::attrI($a, 'mobile_item_min_h',    $s['mobileMenuItemMinHeight']      ?? null);
                self::attrI($a, 'mobile_item_pt',       $s['mobileMenuItemPaddingTop']     ?? null);
                self::attrI($a, 'mobile_item_pr',       $s['mobileMenuItemPaddingRight']   ?? null);
                self::attrI($a, 'mobile_item_pb',       $s['mobileMenuItemPaddingBottom']  ?? null);
                self::attrI($a, 'mobile_item_pl',       $s['mobileMenuItemPaddingLeft']    ?? null);
                self::attrI($a, 'mobile_text_align',    $s['mobileMenuTextAlign']          ?? null, 'left');
                self::attrI($a, 'mobile_indent',        $s['mobileMenuIndentSubmenus']     ?? null, 'on');
                self::attrI($a, 'mobile_font_family',   $s['mobileMenuFontFamily']         ?? null, 'inherit');
                self::attrI($a, 'mobile_font_size',     $s['mobileMenuFontSize']           ?? null);
                self::attrI($a, 'mobile_font_weight',   $s['mobileMenuFontWeight']         ?? null);
                self::attrI($a, 'mobile_line_height',   $s['mobileMenuLineHeight']         ?? null);
                self::attrI($a, 'mobile_letter_sp',     $s['mobileMenuLetterSpacing']      ?? null);
                self::attrI($a, 'mobile_text_transform',$s['mobileMenuTextTransform']      ?? null, 'none');
                self::attrI($a, 'mobile_separator',     $s['mobileSeparatorEnabled']       ?? null, 'yes');
                self::attrI($a, 'mobile_sep_color',     $s['mobileMenuSeparatorColor']     ?? null);
                self::attrI($a, 'mobile_bg_color',      $s['mobileMenuBgColor']            ?? null);
                self::attrI($a, 'mobile_bg_color_h',    $s['mobileMenuBgColorHover']       ?? null);
                self::attrI($a, 'mobile_text_color',    $s['mobileMenuTextColor']          ?? null);
                self::attrI($a, 'mobile_text_color_h',  $s['mobileMenuTextColorHover']     ?? null);
                return '[lazy_menu ' . trim($a) . $vis . ' /]';
            }

            case 'row': {
                if (!empty($el['columns'])) {
                    $rowCols = [];
                    foreach ($el['columns'] as $nestedCol) {
                        $rowCols[] = self::columnToShortcode($nestedCol);
                    }
                    $rowInner = ' ' . implode(' ', $rowCols) . ' ';
                    return '[lazy_row' . ($base ? ' ' . trim($base) : '') . $vis . ']' . $rowInner . '[/lazy_row]';
                }
                return '[lazy_row ' . trim($base) . $vis . ' /]';
            }

            default: {
                $out = '[lazy_element type="' . $type . '" ' . trim($base) . $vis;
                $elSettings = $el['settings'] ?? [];
                if (!empty($elSettings)) {
                    $out .= ' settings_b64="' . base64_encode(json_encode($elSettings)) . '"';
                }
                return $out . ' /]';
            }
        }
    }

    // =========================================================================
    // Shortcode → JSON
    // =========================================================================

    private static function parseContainer(string $attrStr, string $inner): ?array
    {
        $a = self::attrs($attrStr);

        $container = [
            'id'       => $a['id']   ?? self::uid(),
            'type'     => $a['type'] ?? 'container',
            'settings' => self::containerSettings($a),
            'columns'  => self::parseColumnsFromContent($inner),
        ];

        return $container;
    }

    /**
     * Depth-counting column extractor — handles nested [lazy_col] inside [lazy_row] correctly.
     * A simple .*? regex stops at the first [/lazy_col] it finds (the inner nested one),
     * which breaks nested-row structures.
     */
    private static function parseColumnsFromContent(string $content): array
    {
        $cols = [];
        $pos  = 0;
        $len  = strlen($content);

        while ($pos < $len) {
            $tagStart = strpos($content, '[lazy_col', $pos);
            if ($tagStart === false) break;

            // Must be [lazy_col] or [lazy_col ...], not [lazy_columns or similar
            $c = $content[$tagStart + 9] ?? '';
            if ($c !== ' ' && $c !== ']') { $pos = $tagStart + 9; continue; }

            $openEnd = strpos($content, ']', $tagStart);
            if ($openEnd === false) break;

            $attrStr = substr($content, $tagStart + 9, $openEnd - $tagStart - 9);
            $depth   = 1;
            $search  = $openEnd + 1;
            $done    = false;

            while ($depth > 0 && $search < $len) {
                $nextOpen  = strpos($content, '[lazy_col', $search);
                $nextClose = strpos($content, '[/lazy_col]', $search);

                if ($nextClose === false) break;

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $nc = $content[$nextOpen + 9] ?? '';
                    if ($nc === ' ' || $nc === ']') $depth++;
                    $search = $nextOpen + 9;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $colInner = substr($content, $openEnd + 1, $nextClose - $openEnd - 1);
                        $col = self::parseColumn($attrStr, $colInner);
                        if ($col) $cols[] = $col;
                        $pos  = $nextClose + 11; // strlen('[/lazy_col]')
                        $done = true;
                        break;
                    }
                    $search = $nextClose + 11;
                }
            }

            if (!$done) break;
        }

        return $cols;
    }

    private static function parseColumn(string $attrStr, string $inner): ?array
    {
        $a = self::attrs($attrStr);

        $column = [
            'id'       => $a['id']    ?? self::uid(),
            'basis'        => $a['width']        ?? '100%',
            'basis_tablet' => $a['width_tablet'] ?? null,
            'basis_mobile' => $a['width_mobile'] ?? null,
            'settings' => self::columnSettings($a),
            'elements' => [],
        ];

        $elemRx = '/\[lazy_(?!section\b|col\b)(\w+)([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/lazy_\1\])/';
        if (preg_match_all($elemRx, $inner, $m, PREG_SET_ORDER)) {
            foreach ($m as $em) {
                $elem = self::parseElement($em[1], $em[2], $em[3] ?? '');
                if ($elem) $column['elements'][] = $elem;
            }
        }

        return $column;
    }

    private static function parseElement(string $type, string $attrStr, string $inner): ?array
    {
        $a   = self::attrs($attrStr);
        $vis = self::visibilityFromAttrs($a);

        switch ($type) {
            case 'heading':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'heading', 'settings' => array_merge([
                    'title'      => trim($inner),
                    'tag'        => $a['tag']        ?? 'h2',
                    'fontSize'   => $a['font_size']   ?? null,
                    'fontWeight' => $a['font_weight'] ?? null,
                    'textAlign'  => $a['align']       ?? null,
                    'color'      => $a['color']       ?? null,
                    'cssClass'   => $a['css_class']   ?? null,
                    'visibility' => $vis,
                ], [])];

            case 'title': {
                $ts = [
                    'title'            => trim($inner),
                    // Typography
                    'fontSize'         => isset($a['font_size']) ? (int)$a['font_size'] : null,
                    'fontSizeUnit'     => $a['font_size_unit']    ?? 'px',
                    'fontWeight'       => $a['font_weight']       ?? null,
                    'fontFamily'       => $a['font_family']       ?? null,
                    'lineHeight'       => $a['line_height']       ?? null,
                    'letterSpacing'    => isset($a['letter_spacing']) ? self::num($a['letter_spacing']) : null,
                    'textTransform'    => $a['text_transform']    ?? null,
                    'htmlTag'          => $a['html_tag']          ?? null,
                    // Alignment
                    'textAlign'        => $a['align']             ?? null,
                    // Color / Gradient
                    'titleColor'       => $a['color']             ?? null,
                    'titleHoverColor'  => $a['title_hover_color'] ?? null,
                    'useGradient'      => ($a['use_gradient']     ?? '') === 'yes',
                    'gradientAngle'    => isset($a['gradient_angle']) ? (int)$a['gradient_angle'] : null,
                    'gradientStartColor' => $a['gradient_start']  ?? null,
                    'gradientEndColor'   => $a['gradient_end']    ?? null,
                    // Separator
                    'separator'        => $a['separator']         ?? 'default',
                    'separatorColor'   => $a['separator_color']   ?? null,
                    'dividerWidth'     => isset($a['divider_width'])      ? (int)$a['divider_width']      : null,
                    'dividerHeight'    => isset($a['divider_height'])     ? (int)$a['divider_height']     : null,
                    'separatorSpacing' => isset($a['separator_spacing'])  ? (int)$a['separator_spacing']  : null,
                    // Text shadow
                    'textShadow'       => ($a['text_shadow']      ?? '') === 'yes',
                    'textShadowH'      => isset($a['text_shadow_h'])    ? self::num($a['text_shadow_h'])    : null,
                    'textShadowV'      => isset($a['text_shadow_v'])    ? self::num($a['text_shadow_v'])    : null,
                    'textShadowBlur'   => isset($a['text_shadow_blur']) ? self::num($a['text_shadow_blur']) : null,
                    'textShadowColor'  => $a['text_shadow_color'] ?? null,
                    // Text stroke
                    'textStroke'       => ($a['text_stroke']      ?? '') === 'yes',
                    'textStrokeSize'   => isset($a['text_stroke_size'])  ? self::num($a['text_stroke_size'])  : null,
                    'textStrokeColor'  => $a['text_stroke_color'] ?? null,
                    // Overflow
                    'textOverflow'     => $a['text_overflow']     ?? null,
                    // Link
                    'useLink'          => ($a['use_link']         ?? '') === 'yes',
                    'linkUrl'          => $a['link_url']          ?? null,
                    'linkColor'        => $a['link_color']        ?? null,
                    'linkHoverColor'   => $a['link_hover_color']  ?? null,
                    'linkTarget'       => $a['link_target']       ?? '_self',
                    // Spacing
                    'paddingTop'       => isset($a['padding_top'])    ? self::num($a['padding_top'])    : null,
                    'paddingBottom'    => isset($a['padding_bottom']) ? self::num($a['padding_bottom']) : null,
                    'marginTop'        => self::num($a['margin_top']    ?? null),
                    'marginRight'      => self::num($a['margin_right']  ?? null),
                    'marginBottom'     => self::num($a['margin_bottom'] ?? null),
                    'marginLeft'       => self::num($a['margin_left']   ?? null),
                    // CSS
                    'cssClass'         => $a['css_class']         ?? null,
                    'cssId'            => $a['css_id']            ?? null,
                    'visibility'       => $vis,
                ];
                self::addRespProps($ts, $a, [
                    ['textAlign',    'align',         null],
                    ['marginTop',    'margin_top',    'num'],
                    ['marginRight',  'margin_right',  'num'],
                    ['marginBottom', 'margin_bottom', 'num'],
                    ['marginLeft',   'margin_left',   'num'],
                ]);
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'title', 'settings' => $ts];
            }

            case 'text':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'text', 'settings' => [
                    'content'    => trim($inner),
                    'fontSize'   => $a['font_size']   ?? null,
                    'fontWeight' => $a['font_weight'] ?? null,
                    'color'      => $a['color']       ?? null,
                    'textAlign'  => $a['align']       ?? null,
                    'cssClass'   => $a['css_class']   ?? null,
                    'visibility' => $vis,
                ]];

            case 'button': {
                $ts = [
                    // Content
                    'text'        => $a['text']        ?? 'Button',
                    'linkUrl'     => $a['link_url']    ?? $a['url']    ?? '#',
                    'linkTarget'  => $a['link_target'] ?? $a['target'] ?? '_self',
                    'useLink'     => true,
                    // Button style
                    'buttonStyle' => $a['button_style'] ?? 'default',
                    'buttonSize'  => $a['button_size']  ?? null,
                    'buttonSpan'  => ($a['button_span'] ?? '') === 'yes',
                    // Colors + opacities
                    'bgColor'              => $a['bg_color']             ?? null,
                    'bgColorOpacity'       => isset($a['bg_color_opacity'])       ? (float)$a['bg_color_opacity']       : null,
                    'color'                => $a['color']                ?? null,
                    'colorOpacity'         => isset($a['color_opacity'])         ? (float)$a['color_opacity']         : null,
                    'hoverBgColor'         => $a['hover_bg_color']       ?? null,
                    'hoverBgColorOpacity'  => isset($a['hover_bg_color_opacity']) ? (float)$a['hover_bg_color_opacity'] : null,
                    'hoverColor'           => $a['hover_color']          ?? null,
                    'hoverColorOpacity'    => isset($a['hover_color_opacity'])    ? (float)$a['hover_color_opacity']    : null,
                    // Gradient + opacities
                    'bgGradientStartColor'        => $a['bg_gradient_start_color']              ?? null,
                    'bgGradientStartOpacity'      => isset($a['bg_gradient_start_opacity'])      ? (float)$a['bg_gradient_start_opacity']      : null,
                    'bgGradientEndColor'          => $a['bg_gradient_end_color']                ?? null,
                    'bgGradientEndOpacity'        => isset($a['bg_gradient_end_opacity'])        ? (float)$a['bg_gradient_end_opacity']        : null,
                    'bgGradientHoverStartColor'   => $a['bg_gradient_hover_start_color']         ?? null,
                    'bgGradientHoverStartOpacity' => isset($a['bg_gradient_hover_start_opacity']) ? (float)$a['bg_gradient_hover_start_opacity'] : null,
                    'bgGradientHoverEndColor'     => $a['bg_gradient_hover_end_color']           ?? null,
                    'bgGradientHoverEndOpacity'   => isset($a['bg_gradient_hover_end_opacity'])   ? (float)$a['bg_gradient_hover_end_opacity']   : null,
                    'bgGradientType'            => $a['bg_gradient_type']               ?? null,
                    'bgGradientAngle'           => isset($a['bg_gradient_angle'])    ? (int)$a['bg_gradient_angle']    : null,
                    'bgGradientStartPosition'   => isset($a['bg_gradient_start_pos']) ? (int)$a['bg_gradient_start_pos'] : null,
                    'bgGradientEndPosition'     => isset($a['bg_gradient_end_pos'])   ? (int)$a['bg_gradient_end_pos']   : null,
                    // Typography
                    'fontFamily'    => $a['font_family']    ?? 'inherit',
                    'fontSize'      => $a['font_size']      ?? null,
                    'fontWeight'    => $a['font_weight']    ?? '600',
                    'lineHeight'    => $a['line_height']    ?? null,
                    'letterSpacing' => $a['letter_spacing'] ?? null,
                    'textTransform' => $a['text_transform'] ?? null,
                    // Border
                    'borderSizeTop'    => isset($a['border_size_top'])    ? (int)$a['border_size_top']    : null,
                    'borderSizeRight'  => isset($a['border_size_right'])  ? (int)$a['border_size_right']  : null,
                    'borderSizeBottom' => isset($a['border_size_bottom']) ? (int)$a['border_size_bottom'] : null,
                    'borderSizeLeft'   => isset($a['border_size_left'])   ? (int)$a['border_size_left']   : null,
                    'borderColor'        => $a['border_color']         ?? null,
                    'borderColorOpacity' => isset($a['border_color_opacity']) ? (float)$a['border_color_opacity'] : null,
                    'borderRadius'       => isset($a['border_radius']) ? (int)$a['border_radius'] : null,
                    // Icon
                    'icon'         => $a['icon']          ?? null,
                    'iconPosition' => $a['icon_position'] ?? 'left',
                    // Alignment
                    'textAlign'    => $a['align']         ?? 'center',
                    // Margin
                    'marginTop'    => self::num($a['margin_top']    ?? 10),
                    'marginRight'  => self::num($a['margin_right']  ?? 0),
                    'marginBottom' => self::num($a['margin_bottom'] ?? 10),
                    'marginLeft'   => self::num($a['margin_left']   ?? 0),
                    // Padding
                    'paddingTop'    => self::num($a['padding_top']    ?? 12),
                    'paddingRight'  => self::num($a['padding_right']  ?? 30),
                    'paddingBottom' => self::num($a['padding_bottom'] ?? 12),
                    'paddingLeft'   => self::num($a['padding_left']   ?? 30),
                    // CSS
                    'cssClass'   => $a['css_class'] ?? null,
                    'cssId'      => $a['css_id']    ?? null,
                    'visibility' => $vis,
                ];
                self::addRespProps($ts, $a, [
                    ['textAlign',    'align',          null],
                    ['marginTop',    'margin_top',     'num'],
                    ['marginRight',  'margin_right',   'num'],
                    ['marginBottom', 'margin_bottom',  'num'],
                    ['marginLeft',   'margin_left',    'num'],
                    ['paddingTop',    'padding_top',    'num'],
                    ['paddingRight',  'padding_right',  'num'],
                    ['paddingBottom', 'padding_bottom', 'num'],
                    ['paddingLeft',   'padding_left',   'num'],
                ]);
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'button', 'settings' => $ts];
            }

            case 'image':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'image', 'settings' => [
                    'src'        => $a['src']       ?? '',
                    'alt'        => $a['alt']       ?? '',
                    'width'      => $a['width']     ?? null,
                    'alignment'  => $a['align']     ?? null,
                    'cssClass'   => $a['css_class'] ?? null,
                    'visibility' => $vis,
                ]];

            case 'spacer':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'spacer', 'settings' => [
                    'height'     => isset($a['height']) ? (int)$a['height'] : 20,
                    'visibility' => $vis,
                ]];

            case 'video':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'video', 'settings' => [
                    'src'        => $a['src']    ?? '',
                    'type'       => $a['type']   ?? null,
                    'width'      => $a['width']  ?? null,
                    'height'     => $a['height'] ?? null,
                    'visibility' => $vis,
                ]];

            case 'text_block':
            case 'special_text': {
                $ts = [
                    'content'       => trim($inner),
                    // Typography
                    'fontFamily'    => $a['font_family']    ?? 'inherit',
                    'fontSize'      => $a['font_size']      ?? 16,
                    'fontSizeUnit'  => $a['font_size_unit'] ?? 'px',
                    'fontWeight'    => $a['font_weight']    ?? '400',
                    'lineHeight'    => $a['line_height']    ?? '1.5',
                    'letterSpacing' => $a['letter_spacing'] ?? 0,
                    'textTransform' => $a['text_transform'] ?? 'none',
                    // Colors
                    'color'         => $a['color']       ?? '#333333',
                    'hoverColor'    => $a['hover_color'] ?? '',
                    // Alignment
                    'textAlign'     => $a['align']       ?? 'center',
                    // Margin
                    'marginTop'     => self::num($a['margin_top']    ?? 0),
                    'marginRight'   => self::num($a['margin_right']  ?? 0),
                    'marginBottom'  => self::num($a['margin_bottom'] ?? 0),
                    'marginLeft'    => self::num($a['margin_left']   ?? 0),
                    // Padding
                    'paddingTop'    => self::num($a['padding_top']    ?? 10),
                    'paddingRight'  => self::num($a['padding_right']  ?? 0),
                    'paddingBottom' => self::num($a['padding_bottom'] ?? 10),
                    'paddingLeft'   => self::num($a['padding_left']   ?? 0),
                    // CSS
                    'cssClass'      => $a['css_class'] ?? null,
                    'cssId'         => $a['css_id']    ?? null,
                    'visibility'    => $vis,
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

            case 'menu': {
                $aso = [
                    'main'    => ($a['arrow_main']    ?? '') !== 'no',
                    'active'  => ($a['arrow_active']  ?? '') === 'yes',
                    'submenu' => ($a['arrow_submenu'] ?? '') === 'yes',
                ];
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'menu', 'settings' => [
                    'menuId'             => $a['menu_id']           ?? null,
                    'layout'             => $a['layout']             ?? 'horizontal',
                    'marginTop'          => self::num($a['margin_top']    ?? null),
                    'marginBottom'       => self::num($a['margin_bottom'] ?? null),
                    'itemTransition'     => isset($a['item_transition'])    ? (float)$a['item_transition']    : null,
                    'itemTransitionMs'   => isset($a['item_transition_ms']) ? (int)$a['item_transition_ms']   : null,
                    'submenuSpace'       => isset($a['submenu_space'])      ? (int)$a['submenu_space']        : 10,
                    'arrowScopeObj'      => $aso,
                    'cssClass'           => $a['css_class']          ?? null,
                    'cssId'              => $a['css_id']             ?? null,
                    'minHeight'          => isset($a['min_height'])  ? (int)$a['min_height']  : null,
                    'alignItems'         => $a['align_items']        ?? 'flex-start',
                    'justification'      => $a['justification']      ?? 'flex-start',
                    'fontFamily'         => $a['font_family']        ?? 'inherit',
                    'fontSize'           => $a['font_size']          ?? null,
                    'fontWeight'         => $a['font_weight']        ?? null,
                    'lineHeight'         => $a['line_height']        ?? null,
                    'letterSpacing'      => $a['letter_spacing']     ?? null,
                    'textTransform'      => $a['text_transform']     ?? null,
                    'itemPaddingTop'     => self::num($a['item_padding_top']    ?? null),
                    'itemPaddingRight'   => self::num($a['item_padding_right']  ?? null),
                    'itemPaddingBottom'  => self::num($a['item_padding_bottom'] ?? null),
                    'itemPaddingLeft'    => self::num($a['item_padding_left']   ?? null),
                    'itemSpacing'        => self::num($a['item_spacing']        ?? null),
                    'itemBorderRadius'   => self::num($a['item_border_radius']  ?? null),
                    'itemBgColor'        => $a['item_bg_color']       ?? null,
                    'itemBgColorHover'   => $a['item_bg_color_hover'] ?? null,
                    'itemColor'          => $a['item_color']          ?? null,
                    'itemColorHover'     => $a['item_color_hover']    ?? null,
                    'itemBorderSizeTop'          => self::num($a['item_border_top']      ?? null),
                    'itemBorderSizeRight'         => self::num($a['item_border_right']    ?? null),
                    'itemBorderSizeBottom'        => self::num($a['item_border_bottom']   ?? null),
                    'itemBorderSizeLeft'          => self::num($a['item_border_left']     ?? null),
                    'itemBorderColor'             => $a['item_border_color']     ?? null,
                    'itemBorderSizeTopHover'      => self::num($a['item_border_top_h']    ?? null),
                    'itemBorderSizeRightHover'    => self::num($a['item_border_right_h']  ?? null),
                    'itemBorderSizeBottomHover'   => self::num($a['item_border_bottom_h'] ?? null),
                    'itemBorderSizeLeftHover'     => self::num($a['item_border_left_h']   ?? null),
                    'itemBorderColorHover'        => $a['item_border_color_h']   ?? null,
                    'showArrows'         => $a['show_arrows']          ?? 'yes',
                    'submenuDirection'   => $a['submenu_direction']    ?? 'right',
                    'submenuTransition'  => $a['submenu_transition']   ?? 'fade',
                    'submenuMinWidth'    => $a['submenu_min_width']    ?? null,
                    'submenuMaxWidth'    => $a['submenu_max_width']    ?? null,
                    'subSubMenuDirection'=> $a['sub_sub_direction']    ?? 'right',
                    'subSubMenuOffset'   => isset($a['sub_sub_offset']) ? (int)$a['sub_sub_offset'] : 5,
                    'submenuFontFamily'  => $a['submenu_font_family']  ?? 'inherit',
                    'submenuFontSize'    => $a['submenu_font_size']    ?? null,
                    'submenuLineHeight'  => $a['submenu_line_height']  ?? null,
                    'submenuLetterSpacing'=> $a['submenu_letter_sp']  ?? null,
                    'submenuTextTransform'=> $a['submenu_text_transform'] ?? null,
                    'submenuTextAlign'   => $a['submenu_text_align']   ?? null,
                    'submenuPaddingTop'  => self::num($a['submenu_pt'] ?? null),
                    'submenuPaddingRight'=> self::num($a['submenu_pr'] ?? null),
                    'submenuPaddingBottom'=> self::num($a['submenu_pb'] ?? null),
                    'submenuPaddingLeft' => self::num($a['submenu_pl'] ?? null),
                    'submenuBorderRadiusTopLeft'     => self::num($a['submenu_radius_tl'] ?? null),
                    'submenuBorderRadiusTopRight'    => self::num($a['submenu_radius_tr'] ?? null),
                    'submenuBorderRadiusBottomRight' => self::num($a['submenu_radius_br'] ?? null),
                    'submenuBorderRadiusBottomLeft'  => self::num($a['submenu_radius_bl'] ?? null),
                    'submenuBoxShadow'   => $a['submenu_shadow']       ?? 'no',
                    'submenuShadowColor' => $a['submenu_shadow_color'] ?? null,
                    'submenuShadowH'     => self::num($a['submenu_shadow_h']      ?? null),
                    'submenuShadowV'     => self::num($a['submenu_shadow_v']      ?? null),
                    'submenuShadowBlur'  => self::num($a['submenu_shadow_blur']   ?? null),
                    'submenuShadowSpread'=> self::num($a['submenu_shadow_spread'] ?? null),
                    'submenuThumbWidth'  => $a['submenu_thumb_w']    ?? null,
                    'submenuThumbHeight' => $a['submenu_thumb_h']    ?? null,
                    'submenuSeparatorColor'=> $a['submenu_sep_color'] ?? null,
                    'submenuBgColor'     => $a['submenu_bg_color']    ?? null,
                    'submenuTextColor'   => $a['submenu_text_color']  ?? null,
                    'submenuTextColorHover'=> $a['submenu_text_color_h'] ?? null,
                    'mobileCollapseBreakpoint'        => $a['mobile_breakpoint']    ?? 'tablet',
                    'mobileMenuMode'                  => $a['mobile_mode']          ?? 'collapsed',
                    'mobileMenuExpandMode'            => $a['mobile_expand_mode']   ?? 'full-width-static',
                    'mobileMenuSidebarSide'           => $a['mobile_sidebar_side']  ?? 'left',
                    'mobileMenuTriggerPaddingTop'     => self::num($a['mobile_trigger_pt'] ?? 10),
                    'mobileMenuTriggerPaddingRight'   => self::num($a['mobile_trigger_pr'] ?? 15),
                    'mobileMenuTriggerPaddingBottom'  => self::num($a['mobile_trigger_pb'] ?? 10),
                    'mobileMenuTriggerPaddingLeft'    => self::num($a['mobile_trigger_pl'] ?? 15),
                    'mobileMenuTriggerBgColor'        => $a['mobile_trigger_bg']    ?? null,
                    'mobileMenuTriggerTextColor'      => $a['mobile_trigger_color'] ?? null,
                    'mobileMenuTriggerText'           => $a['mobile_trigger_text']  ?? null,
                    'mobileMenuTriggerExpandIcon'     => $a['mobile_expand_icon']   ?? null,
                    'mobileMenuTriggerCollapseIcon'   => $a['mobile_collapse_icon'] ?? null,
                    'mobileMenuTriggerFontSize'       => $a['mobile_trigger_fs']    ?? null,
                    'mobileMenuTriggerHorizontalAlign'=> $a['mobile_trigger_align'] ?? 'flex-start',
                    'mobileMenuItemMinHeight'         => isset($a['mobile_item_min_h']) ? (int)$a['mobile_item_min_h'] : null,
                    'mobileMenuItemPaddingTop'        => self::num($a['mobile_item_pt'] ?? null),
                    'mobileMenuItemPaddingRight'      => self::num($a['mobile_item_pr'] ?? null),
                    'mobileMenuItemPaddingBottom'     => self::num($a['mobile_item_pb'] ?? null),
                    'mobileMenuItemPaddingLeft'       => self::num($a['mobile_item_pl'] ?? null),
                    'mobileMenuTextAlign'             => $a['mobile_text_align']    ?? 'left',
                    'mobileMenuIndentSubmenus'        => $a['mobile_indent']        ?? 'on',
                    'mobileMenuFontFamily'            => $a['mobile_font_family']   ?? 'inherit',
                    'mobileMenuFontSize'              => $a['mobile_font_size']     ?? null,
                    'mobileMenuFontWeight'            => $a['mobile_font_weight']   ?? null,
                    'mobileMenuLineHeight'            => $a['mobile_line_height']   ?? null,
                    'mobileMenuLetterSpacing'         => $a['mobile_letter_sp']     ?? null,
                    'mobileMenuTextTransform'         => $a['mobile_text_transform'] ?? 'none',
                    'mobileSeparatorEnabled'          => $a['mobile_separator']     ?? 'yes',
                    'mobileMenuSeparatorColor'        => $a['mobile_sep_color']     ?? null,
                    'mobileMenuBgColor'               => $a['mobile_bg_color']      ?? null,
                    'mobileMenuBgColorHover'          => $a['mobile_bg_color_h']    ?? null,
                    'mobileMenuTextColor'             => $a['mobile_text_color']    ?? null,
                    'mobileMenuTextColorHover'        => $a['mobile_text_color_h']  ?? null,
                    'visibility'                      => $vis,
                ]];
            }

            case 'row': {
                $rowObj = ['id' => $a['id'] ?? self::uid(), 'type' => 'row', 'settings' => ['visibility' => $vis]];
                if (!empty(trim($inner))) {
                    $nestedCols = self::parseColumnsFromContent($inner);
                    if (!empty($nestedCols)) $rowObj['columns'] = $nestedCols;
                }
                return $rowObj;
            }

            default: {
                $realType = $type === 'element' ? ($a['type'] ?? 'text') : $type;
                $settings = [];
                if (!empty($a['settings_b64'])) {
                    $decoded = json_decode(base64_decode($a['settings_b64']), true);
                    if (is_array($decoded)) $settings = $decoded;
                }
                $settings['visibility'] = $vis;
                return ['id' => $a['id'] ?? self::uid(), 'type' => $realType, 'settings' => $settings];
            }
        }
    }

    // =========================================================================
    // Settings builders (shortcode → JSON)
    // =========================================================================

    private static function containerSettings(array $a): array
    {
        $s = [
            'marginTop'         => self::num($a['margin_top']    ?? null),
            'marginBottom'      => self::num($a['margin_bottom'] ?? null),
            'marginTopUnit'     => $a['margin_top_unit']    ?? 'px',
            'marginBottomUnit'  => $a['margin_bottom_unit'] ?? 'px',
            'paddingTop'        => self::num($a['padding_top']    ?? 0),
            'paddingBottom'     => self::num($a['padding_bottom'] ?? 0),
            'paddingLeft'       => self::num($a['padding_left']   ?? 0),
            'paddingRight'      => self::num($a['padding_right']  ?? 0),
            'paddingTopUnit'    => $a['padding_top_unit']    ?? 'px',
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingLeftUnit'   => $a['padding_left_unit']   ?? 'px',
            'paddingRightUnit'  => $a['padding_right_unit']  ?? 'px',
            'bgColor'             => $a['bg_color']       ?? null,
            'bgColorOpacity'      => isset($a['bg_opacity']) ? (float)$a['bg_opacity'] : 1,
            'bgType'              => $a['bg_type']        ?? 'color',
            'bgGradientStartColor'=> $a['gradient_start'] ?? null,
            'bgGradientEndColor'  => $a['gradient_end']   ?? null,
            'bgGradientStartPosition' => isset($a['gradient_start_pos']) ? (int)$a['gradient_start_pos'] : 0,
            'bgGradientEndPosition'   => isset($a['gradient_end_pos'])   ? (int)$a['gradient_end_pos']   : 100,
            'bgGradientType'      => $a['gradient_type']  ?? 'linear',
            'bgGradientAngle'     => isset($a['gradient_angle']) ? (int)$a['gradient_angle'] : 180,
            'bgImage'             => $a['bg_image']       ?? null,
            'bgImageSkipLazy'     => false,
            'bgImagePosition'     => $a['bg_position']    ?? 'center center',
            'bgImageRepeat'       => $a['bg_repeat']      ?? 'no-repeat',
            'bgImageSize'         => $a['bg_size']        ?? 'auto',
            'bgImageFading'       => false,
            'bgImageParallax'     => $a['bg_parallax']    ?? 'none',
            'bgImageBlendMode'    => $a['bg_blend']       ?? 'normal',
            'contentWidth'        => $a['content_width']  ?? 'site',
            'height'              => $a['height']         ?? 'auto',
            'customHeight'        => $a['custom_height']  ?? null,
            'minHeight'           => $a['min_height']     ?? null,
            'rowAlignContent'     => $a['row_align_content'] ?? null,
            'alignItems'          => $a['align_items']    ?? 'stretch',
            'alignContent'        => null,
            'justifyContent'      => $a['justify_content'] ?? 'flex-start',
            'flexWrap'            => $a['flex_wrap']      ?? 'wrap',
            'columnGap'           => self::num($a['column_gap'] ?? null),
            'htmlTag'             => $a['html_tag']       ?? 'div',
            'menuAnchor'          => $a['menu_anchor']    ?? null,
            'visibility'          => self::visibilityFromAttrs($a),
            'status'              => $a['status']         ?? 'published',
            'cssClass'            => $a['css_class']      ?? null,
            'linkColor'           => $a['link_color']     ?? null,
            'linkUrl'             => $a['link']           ?? null,
            'linkTarget'          => $a['link_target']    ?? '_self',
            'borderSizeTop'       => self::num($a['border_top']    ?? null),
            'borderSizeRight'     => self::num($a['border_right']  ?? null),
            'borderSizeBottom'    => self::num($a['border_bottom'] ?? null),
            'borderSizeLeft'      => self::num($a['border_left']   ?? null),
            'borderColor'         => $a['border_color']   ?? '#000000',
            'borderRadiusTopLeft'     => self::num($a['radius_tl'] ?? null),
            'borderRadiusTopRight'    => self::num($a['radius_tr'] ?? null),
            'borderRadiusBottomRight' => self::num($a['radius_br'] ?? null),
            'borderRadiusBottomLeft'  => self::num($a['radius_bl'] ?? null),
            'boxShadow'               => ($a['box_shadow'] ?? '') === 'yes',
            'boxShadowPositionVertical'   => self::num($a['shadow_v']      ?? 0),
            'boxShadowPositionHorizontal' => self::num($a['shadow_h']      ?? 0),
            'boxShadowBlurRadius'         => self::num($a['shadow_blur']   ?? 0),
            'boxShadowSpreadRadius'       => self::num($a['shadow_spread'] ?? 0),
            'boxShadowColor'              => $a['shadow_color'] ?? '#000000',
            'boxShadowStyle'              => $a['shadow_style'] ?? 'outer',
            'zIndex'                      => self::num($a['z_index']  ?? null),
            'overflow'                    => $a['overflow'] ?? 'default',
            'sticky'        => ($a['sticky']         ?? '') === 'yes',
            'stickyDesktop' => ($a['sticky_desktop'] ?? '') !== 'no',
            'stickyTablet'  => ($a['sticky_tablet']  ?? '') !== 'no',
            'stickyMobile'  => ($a['sticky_mobile']  ?? '') !== 'no',
            'stickyOffset'  => self::num($a['sticky_offset']  ?? 0),
            'stickyZIndex'  => self::num($a['sticky_z_index'] ?? 99),
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
            ['paddingBottomUnit','padding_bottom_unit', null],
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

    private static function columnSettings(array $a): array
    {
        $s = [
            'paddingTop'        => self::num($a['padding_top']    ?? 10),
            'paddingBottom'     => self::num($a['padding_bottom'] ?? 10),
            'paddingLeft'       => self::num($a['padding_left']   ?? 10),
            'paddingRight'      => self::num($a['padding_right']  ?? 10),
            'paddingTopUnit'    => $a['padding_top_unit']    ?? 'px',
            'paddingBottomUnit' => $a['padding_bottom_unit'] ?? 'px',
            'paddingLeftUnit'   => $a['padding_left_unit']   ?? 'px',
            'paddingRightUnit'  => $a['padding_right_unit']  ?? 'px',
            'marginTop'         => self::num($a['margin_top']     ?? 0),
            'marginBottom'      => self::num($a['margin_bottom']  ?? 0),
            'marginLeft'        => self::num($a['margin_left']    ?? 0),
            'marginRight'       => self::num($a['margin_right']   ?? 0),
            'marginTopUnit'     => $a['margin_top_unit']    ?? 'px',
            'marginBottomUnit'  => $a['margin_bottom_unit'] ?? 'px',
            'marginLeftUnit'    => $a['margin_left_unit']   ?? 'px',
            'marginRightUnit'   => $a['margin_right_unit']  ?? 'px',
            'alignment'         => $a['alignment']      ?? 'default',
            'contentLayout'     => $a['content_layout'] ?? null,
            'contentAlignH'     => $a['align_h']        ?? 'flex-start',
            'contentAlignV'     => $a['align_v']        ?? 'flex-start',
            'gapWidth'          => self::num($a['gap_width']  ?? null),
            'gapHeight'         => self::num($a['gap_height'] ?? null),
            'htmlTag'           => $a['html_tag']    ?? 'div',
            'linkUrl'           => $a['link']        ?? null,
            'linkTarget'        => $a['link_target'] ?? '_self',
            'visibility'        => self::visibilityFromAttrs($a),
            'cssClass'          => $a['css_class']   ?? null,
            'cssId'             => $a['css_id']      ?? null,
            'textColor'         => $a['text_color']  ?? null,
            'bgColor'           => $a['bg_color']    ?? 'transparent',
            'bgColorOpacity'    => isset($a['bg_opacity']) ? (float)$a['bg_opacity'] : 1,
            'bgType'            => $a['bg_type']     ?? 'color',
            'hoverType'         => $a['hover_type']  ?? 'none',
            'bgGradientStartColor'    => $a['gradient_start']           ?? null,
            'bgGradientEndColor'      => $a['gradient_end']             ?? null,
            'bgGradientStartOpacity'  => isset($a['gradient_start_opacity']) ? (float)$a['gradient_start_opacity'] : 1,
            'bgGradientEndOpacity'    => isset($a['gradient_end_opacity'])   ? (float)$a['gradient_end_opacity']   : 1,
            'bgGradientStartPosition' => isset($a['gradient_start_pos'])     ? (int)$a['gradient_start_pos']       : 0,
            'bgGradientEndPosition'   => isset($a['gradient_end_pos'])       ? (int)$a['gradient_end_pos']         : 100,
            'bgGradientType'    => $a['gradient_type']  ?? 'linear',
            'bgGradientAngle'   => isset($a['gradient_angle']) ? (int)$a['gradient_angle'] : 180,
            'bgImage'           => $a['bg_image']    ?? null,
            'bgImageSkipLazy'   => ($a['bg_skip_lazy'] ?? '') === 'yes',
            'bgImagePosition'   => $a['bg_position'] ?? 'center center',
            'bgImageRepeat'     => $a['bg_repeat']   ?? 'no-repeat',
            'bgImageSize'       => $a['bg_size']     ?? 'auto',
            'bgImageFading'     => false,
            'bgImageParallax'   => $a['bg_parallax'] ?? 'none',
            'bgImageBlendMode'  => $a['bg_blend']    ?? 'normal',
            'fontSize'          => null,
            'fontWeight'        => null,
            'lineHeight'        => null,
            'letterSpacing'     => null,
            'textAlign'         => null,
            'borderSizeTop'     => self::num($a['border_top']    ?? null),
            'borderSizeRight'   => self::num($a['border_right']  ?? null),
            'borderSizeBottom'  => self::num($a['border_bottom'] ?? null),
            'borderSizeLeft'    => self::num($a['border_left']   ?? null),
            'borderColor'       => $a['border_color'] ?? '#000000',
            'borderRadiusTopLeft'     => self::num($a['radius_tl'] ?? null),
            'borderRadiusTopRight'    => self::num($a['radius_tr'] ?? null),
            'borderRadiusBottomRight' => self::num($a['radius_br'] ?? null),
            'borderRadiusBottomLeft'  => self::num($a['radius_bl'] ?? null),
            'borderRadiusTopLeftUnit'     => $a['radius_tl_unit'] ?? 'px',
            'borderRadiusTopRightUnit'    => $a['radius_tr_unit'] ?? 'px',
            'borderRadiusBottomRightUnit' => $a['radius_br_unit'] ?? 'px',
            'borderRadiusBottomLeftUnit'  => $a['radius_bl_unit'] ?? 'px',
            'boxShadow'               => ($a['box_shadow'] ?? '') === 'yes',
            'boxShadowPositionVertical'   => self::num($a['shadow_v']      ?? 0),
            'boxShadowPositionHorizontal' => self::num($a['shadow_h']      ?? 0),
            'boxShadowBlurRadius'         => self::num($a['shadow_blur']   ?? 0),
            'boxShadowSpreadRadius'       => self::num($a['shadow_spread'] ?? 0),
            'boxShadowColor'              => $a['shadow_color'] ?? '#000000',
            'boxShadowStyle'              => $a['shadow_style'] ?? 'outer',
            'flexGrow'           => self::num($a['flex_grow']        ?? null),
            'flexShrink'         => self::num($a['flex_shrink']      ?? null),
            'maxHeight'          => $a['max_height']                 ?? null,
            'columnSpacingLeft'  => self::num($a['col_spacing_left'] ?? null),
            'columnSpacingRight' => self::num($a['col_spacing_right'] ?? null),
            'zIndex'        => self::num($a['z_index']  ?? null),
            'overflow'      => $a['overflow'] ?? 'default',
            'sticky'        => ($a['sticky']         ?? '') === 'yes',
            'stickyDesktop' => ($a['sticky_desktop'] ?? '') !== 'no',
            'stickyTablet'  => ($a['sticky_tablet']  ?? '') !== 'no',
            'stickyMobile'  => ($a['sticky_mobile']  ?? '') !== 'no',
            'stickyOffset'  => self::num($a['sticky_offset']  ?? 0),
            'stickyZIndex'  => self::num($a['sticky_z_index'] ?? 99),
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
            ['paddingBottomUnit','padding_bottom_unit', null],
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
            ['borderRadiusBottomRightUnit','radius_br_unit',         null],
            ['borderRadiusBottomLeftUnit', 'radius_bl_unit',         null],
            ['boxShadowPositionHorizontal','shadow_h',               'num'],
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
            if (isset($a['box_shadow_' . $dev]) && $a['box_shadow_' . $dev] !== '') {
                $s['boxShadow_' . $dev] = $a['box_shadow_' . $dev] === 'yes';
            }
        }
        // Responsive bgImageSkipLazy
        foreach (['tablet', 'mobile'] as $dev) {
            if (isset($a['bg_skip_lazy_' . $dev]) && $a['bg_skip_lazy_' . $dev] !== '') {
                $s['bgImageSkipLazy_' . $dev] = $a['bg_skip_lazy_' . $dev] === 'yes';
            }
        }
        return $s;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Append ' key="value"' to a string (used for element inline attrs) */
    private static function attrI(string &$str, string $key, $value, $skip = null): void
    {
        if ($value === null || $value === '' || $value === $skip) return;
        $str .= ' ' . $key . '="' . $value . '"';
    }

    /** Append to attr array (used for section/col attrs) */
    private static function attr(array &$a, string $key, $value): void
    {
        if ($value === null || $value === '') return;
        $a[] = $key . '="' . $value . '"';
    }

    /** Append to attr array, skip if value equals $skip (default value) */
    private static function attrIf(array &$a, string $key, $value, $skip): void
    {
        if ($value === null || $value === '' || $value === $skip) return;
        $a[] = $key . '="' . $value . '"';
    }

    /** Append _tablet/_mobile variant attrs for a responsive property */
    private static function respAttr(array &$a, string $attrKey, array $s, string $sKey): void
    {
        foreach (['tablet', 'mobile'] as $dev) {
            $v = $s[$sKey . '_' . $dev] ?? null;
            if ($v !== null && $v !== '') $a[] = $attrKey . '_' . $dev . '="' . $v . '"';
        }
    }

    /**
     * Inject _tablet/_mobile responsive variants from parsed attrs into settings array.
     * defs: array of [settingsKey, attrKey, parser]  parser: null|'int'|'float'|'num'
     */
    private static function addRespProps(array &$s, array $a, array $defs): void
    {
        foreach (['tablet', 'mobile'] as $dev) {
            foreach ($defs as $def) {
                [$sk, $ak, $parser] = $def;
                $raw = $a[$ak . '_' . $dev] ?? null;
                if ($raw === null || $raw === '') continue;
                if ($parser === 'int')   { $s[$sk . '_' . $dev] = (int)$raw; }
                elseif ($parser === 'float') { $s[$sk . '_' . $dev] = (float)$raw; }
                elseif ($parser === 'num')   { $s[$sk . '_' . $dev] = self::num($raw); }
                else { $s[$sk . '_' . $dev] = $raw; }
            }
        }
    }

    private static function attrs(string $str): array
    {
        $out = [];
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $str, $m, PREG_SET_ORDER);
        foreach ($m as $pair) $out[$pair[1]] = $pair[2];
        return $out;
    }

    private static function visibilityFromAttrs(array $a): array
    {
        return [
            'mobile'  => ($a['hide_mobile']  ?? '') !== 'yes',
            'tablet'  => ($a['hide_tablet']  ?? '') !== 'yes',
            'desktop' => ($a['hide_desktop'] ?? '') !== 'yes',
        ];
    }

    private static function num($v)
    {
        if ($v === null || $v === '') return null;
        return is_numeric($v) ? ($v == (int)$v ? (int)$v : (float)$v) : $v;
    }

    private static function uid(): string
    {
        return substr(md5(uniqid('', true)), 0, 9);
    }
}
