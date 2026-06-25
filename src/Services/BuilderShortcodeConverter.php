<?php

namespace FalconCms\Core\Services;

/**
 * Converts Falcon Builder JSON ↔ human-readable shortcodes.
 *
 * Format mirrors Fusion Builder style — every setting is a plain attribute.
 * No base64 encoding. Null / default values are omitted to keep shortcodes short.
 *
 * Roundtrip: shortcode attributes are mapped back to the exact camelCase keys
 * the builder expects, with null defaults for any omitted setting.
 */
class BuilderShortcodeConverter
{
    /** @var array<string,array>|null cached custom element definitions keyed by type */
    private static $customDefs = null;

    /** When true, appendExtras() is a no-op — used to probe the bare per-type attribute names. */
    private static $suppressExtras = false;

    /** Lazily resolve + cache registered custom element definitions (keyed by type). */
    private static function customDefs(): array
    {
        if (self::$customDefs !== null) return self::$customDefs;
        self::$customDefs = [];
        if (function_exists('apply_falcon_filters')) {
            $defs = apply_falcon_filters('falcon_builder_elements', []);
            if (is_array($defs)) {
                foreach ($defs as $key => $def) {
                    $t = $def['type'] ?? $key;
                    self::$customDefs[$t] = $def;
                }
            }
        }
        return self::$customDefs;
    }

    /** Resolve a param's storage key (handles array param_name: suffixed → first; bare targets → heading slug). */
    private static function ceParamKey(array $p): ?string
    {
        $pn = $p['param_name'] ?? null;
        if (is_array($pn) && !empty($pn)) {
            $first = $pn[0];
            $suffixes = ['_hover_color', '_hover_bg', '_color', '_bg', '_typo', '_pad', '_margin'];
            foreach ($suffixes as $suf) {
                if (str_ends_with($first, $suf)) return $first; // suffixed → first entry is the key
            }
            // bare target names → key from heading
            return !empty($p['heading']) ? trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($p['heading'])), '_') : ('cf_' . implode('_', $pn));
        }
        if (!empty($pn)) return $pn;
        if (!empty($p['heading'])) return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($p['heading'])), '_');
        return null;
    }

    /** Flat list of [key,type,value] for a custom element definition. */
    private static function ceFieldList(array $def): array
    {
        $list = [];
        if (!empty($def['params'])) {
            foreach ($def['params'] as $p) {
                $key = self::ceParamKey($p);
                if (!$key) continue;
                $list[] = ['key' => $key, 'type' => $p['type'] ?? 'text', 'value' => $p['value'] ?? null];
            }
        } elseif (!empty($def['fields'])) {
            foreach ($def['fields'] as $k => $f) {
                $list[] = ['key' => $k, 'type' => $f['type'] ?? 'text', 'value' => $f['default'] ?? null];
            }
        }
        return $list;
    }

    /** Base field keys (for prefix-based pruning), or null to allow all. */
    private static function ceAllowedKeys(array $def): ?array
    {
        $list = self::ceFieldList($def);
        if (empty($list)) return null;
        return array_values(array_filter(array_map(fn($f) => $f['key'], $list)));
    }

    /** True if $key equals a base or starts with base."_" (covers _top, _size, _tablet, _unit, _target, _dynamic…). */
    private static function ceKeyAllowed(?array $bases, string $key): bool
    {
        if ($bases === null) return true;
        foreach ($bases as $b) {
            if ($key === $b || str_starts_with($key, $b . '_')) return true;
        }
        return false;
    }

    /** Global setting keys every element shares (Extra tab: visibility cond, animation, css). Always serialized. */
    private const CE_GLOBAL_KEYS = [
        'cssClass', 'cssId',
        'anim_type', 'anim_duration', 'anim_delay', 'anim_easing',
        'vis_condition', 'vis_date_from', 'vis_date_to',
    ];

    /** Parse numeric-looking string to int/float, else return as-is (keeps units like "px"). */
    private static function maybeNum($v)
    {
        if (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return strpos($v, '.') !== false ? (float) $v : (int) $v;
        }
        return $v;
    }

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
        return str_contains($content, '[falcon_section');
    }

    public static function jsonToShortcodes(string $json): string
    {
        $layout = json_decode($json, true);
        if (!is_array($layout) || empty($layout)) return $json;
        return implode("\n\n", array_map([self::class, 'containerToShortcode'], $layout));
    }

    /**
     * Rich editors (TinyMCE) wrap shortcodes in <p>/<br> and HTML-encode brackets. Strip that
     * wrapping around structural builder tags so the layout parses faithfully after a rich-editor edit.
     */
    private static function unwrapEditorMarkup(string $content): string
    {
        // Decode encoded brackets that some editors produce.
        $content = str_replace(['&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;', '&#x5b;', '&#x5d;'], ['[', ']', '[', ']', '[', ']', '[', ']'], $content);
        // Drop <p>/</p>/<br> immediately adjacent to ANY builder shortcode tag (open or close).
        $content = preg_replace('#<p[^>]*>\s*(\[/?(?:falcon_|lazy_|lzr_)[^\]]*\])#i', '$1', $content);
        $content = preg_replace('#(\[/?(?:falcon_|lazy_|lzr_)[^\]]*\])\s*</p>#i', '$1', $content);
        // Stray <br> between shortcode tags.
        $content = preg_replace('#(\])\s*<br\s*/?>\s*(\[)#i', '$1$2', $content);
        // Empty paragraphs left behind.
        $content = preg_replace('#<p[^>]*>\s*(?:&nbsp;)?\s*</p>#i', '', $content);
        return $content;
    }

    public static function shortcodesToJson(string $content): string
    {
        $content = self::unwrapEditorMarkup($content);
        $layout  = [];
        $pattern = '/\[falcon_section([^\]]*)\](.*?)\[\/falcon_section\]/s';
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

    // =========================================================================
    // Lossless fidelity — readable "extra" attributes (no base64)
    // -------------------------------------------------------------------------
    // The per-type serializers only emit a hand-picked subset of each settings
    // object, so a rich-editor round-trip (shortcode → JSON → shortcode) would
    // silently drop the rest (padding, margins, dividers, etc.). To stay lossless
    // *and* keep shortcodes human-readable, we append the dropped fields as plain
    // attributes named after the literal setting key — e.g. dividerWidth="60".
    //
    //   • Serialize: parse our own output back; any setting value the round-trip
    //     fails to recover is appended as a readable attribute.
    //   • Parse: after the per-type parse, any attribute the per-type serializer
    //     does NOT itself emit (and that isn't structural) is merged back in.
    // This is symmetric and collision-free: the per-type attribute names and the
    // extra (literal-key) names never overlap on recoverable fields.
    // =========================================================================

    private const FIDELITY_STRUCTURAL = ['id', 'type', 'width', 'width_tablet', 'width_mobile', 'hide_mobile', 'hide_tablet', 'hide_desktop'];

    private static function isTransientKey($k): bool
    {
        return is_string($k) && ($k === '' || $k[0] === '_' || $k === 'isHovered' || $k === 'isTextHovered');
    }

    /** Attribute names present on the opening tag of a shortcode string. */
    private static function tagAttrNames(string $sc): array
    {
        if (!preg_match('/^\s*\[[a-z0-9_]+([^\]]*)\]/i', $sc, $m)) return [];
        preg_match_all('/([A-Za-z_][\w-]*)\s*=\s*"/', $m[1], $mm);
        return $mm[1] ?? [];
    }

    /** Loose scalar equality (string-normalised), used to detect already-recovered values. */
    private static function looseEq($a, $b): bool
    {
        if (is_bool($a) || is_bool($b)) return (bool)$a === (bool)$b;
        if ($a === null || $b === null) return $a === $b;
        if (is_array($a) || is_array($b)) return $a === $b;
        return (string)$a === (string)$b;
    }

    /** Coerce a readable attribute value back to bool / number / string. */
    private static function coerceAttr(string $v)
    {
        if ($v === 'true') return true;
        if ($v === 'false') return false;
        if ($v === 'null') return null;
        if (is_numeric($v)) {
            // keep large id-like ints as strings only if they overflow; otherwise numeric
            if (preg_match('/^-?\d+$/', $v)) {
                $i = (int)$v;
                return ((string)$i === $v) ? $i : $v;
            }
            return (float)$v;
        }
        return $v;
    }

    /**
     * Append readable "extra" attributes for any scalar setting the per-type round-trip drops.
     * $recovered is what parsing $sc back yields, so only genuinely-lost values are emitted.
     */
    private static function appendExtras(string $sc, array $settings, array $recovered): string
    {
        if (self::$suppressExtras) return $sc;
        $extras = [];
        foreach ($settings as $k => $v) {
            if (self::isTransientKey($k)) continue;
            if ($v === null || $v === '' || is_array($v)) continue; // arrays are content the per-type handles
            if (array_key_exists($k, $recovered) && self::looseEq($recovered[$k], $v)) continue;
            $val = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
            if (strpos($val, '"') !== false) continue; // never break attribute quoting
            $extras[] = $k . '="' . $val . '"';
        }
        if (empty($extras)) return $sc;
        return preg_replace('/^(\s*\[[a-z0-9_]+\s)/i', '${1}' . implode(' ', $extras) . ' ', $sc, 1);
    }

    /**
     * Merge back any attribute the per-type serializer does not itself emit (and that isn't
     * structural). $perTypeNames are the attribute names the per-type serializer produces.
     */
    private static function mergeExtras(array $attrs, array $settings, array $perTypeNames): array
    {
        foreach ($attrs as $k => $v) {
            if (!is_string($k) || $k === '') continue;
            if (in_array($k, self::FIDELITY_STRUCTURAL, true)) continue;
            if (in_array($k, $perTypeNames, true)) continue;
            $settings[$k] = is_string($v) ? self::coerceAttr($v) : $v;
        }
        return $settings;
    }

    /** Strip child-holding keys so re-serialization only yields the node's own opening-tag attrs. */
    private static function stripChildren(array $node): array
    {
        unset($node['columns'], $node['elements']);
        return $node;
    }

    /** The attribute names the per-type serializer alone emits for a node (extras suppressed). */
    private static function perTypeNames(string $kind, array $node): array
    {
        $prev = self::$suppressExtras;
        self::$suppressExtras = true;
        try {
            if ($kind === 'container')   $sc = self::containerToShortcode(self::stripChildren($node));
            elseif ($kind === 'column')  $sc = self::columnToShortcode(self::stripChildren($node));
            else                         $sc = self::elementToShortcode(self::stripChildren($node));
        } finally {
            self::$suppressExtras = $prev;
        }
        return is_string($sc) ? self::tagAttrNames($sc) : [];
    }

    /** What parsing a freshly-serialized element shortcode recovers — used to find dropped fields. */
    private static function elementRecovered(string $sc, string $type, array $settings): array
    {
        if (!preg_match('/^\s*\[([^\s\]\/]+)\s*([^\]]*?)(?:\/\]|\](.*)\[\/\1\])\s*$/is', $sc, $m)) return $settings;
        // Built-in types win (some, e.g. "menu", are ALSO registered as custom defs for the panel UI).
        $el  = self::parseElementInner($type, $m[2], $m[3] ?? '');
        if (!is_array($el) && isset(self::customDefs()[$type])) {
            $el = self::parseCustomElement(self::customDefs()[$type], $m[2], $m[3] ?? '');
        }
        return is_array($el) ? ($el['settings'] ?? []) : $settings;
    }

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
        self::attr($a, 'bg_hover_color', $s['bgHoverColor'] ?? null);
        self::attrIf($a, 'bg_hover_opacity', $s['bgHoverColorOpacity'] ?? null, 1);
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
        self::attr($a, 'global_id',     $s['global_id']  ?? null);
        self::attr($a, 'z_index',       $s['zIndex']     ?? null);
        self::respAttr($a, 'z_index',  $s, 'zIndex');
        self::attrIf($a, 'overflow',   $s['overflow']   ?? null, 'default');
        self::respAttr($a, 'overflow', $s, 'overflow');
        if (!empty($s['sticky'])) {
            $a[] = 'sticky="yes"';
            if (isset($s['stickyDesktop']) && $s['stickyDesktop'] === false) $a[] = 'sticky_desktop="no"';
            if (isset($s['stickyTablet'])  && $s['stickyTablet']  === false) $a[] = 'sticky_tablet="no"';
            if (isset($s['stickyMobile'])  && $s['stickyMobile']  === false) $a[] = 'sticky_mobile="no"';
            self::attr($a, 'sticky_offset',   $s['stickyOffset']   ?? null);
            self::attr($a, 'sticky_z_index',  $s['stickyZIndex']   ?? null);
            self::attr($a, 'sticky_bg_color', $s['stickyBgColor']  ?? null);
            if (!empty($s['stickyBgColor']) && isset($s['stickyBgColorOpacity']) && (float)$s['stickyBgColorOpacity'] < 1) {
                $a[] = 'sticky_bg_color_opacity="' . $s['stickyBgColorOpacity'] . '"';
            }
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

        $attrStr   = implode(' ', $a);
        $recovered = self::containerSettings(self::attrs($attrStr));
        return self::appendExtras('[falcon_section ' . $attrStr . ']' . $inner . '[/falcon_section]', $s, $recovered);
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
        self::attr($a, 'bg_hover_color',   $s['bgHoverColor']   ?? null);
        self::attrIf($a, 'bg_hover_opacity', $s['bgHoverColorOpacity'] ?? null, 1);
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
            self::attr($a, 'sticky_offset',   $s['stickyOffset']   ?? null);
            self::attr($a, 'sticky_z_index',  $s['stickyZIndex']   ?? null);
            self::attr($a, 'sticky_bg_color', $s['stickyBgColor']  ?? null);
            if (!empty($s['stickyBgColor']) && isset($s['stickyBgColorOpacity']) && (float)$s['stickyBgColorOpacity'] < 1) {
                $a[] = 'sticky_bg_color_opacity="' . $s['stickyBgColorOpacity'] . '"';
            }
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
            $sc        = self::elementToShortcode($el);
            $eSettings = $el['settings'] ?? [];
            $recovered = self::elementRecovered($sc, $el['type'] ?? 'text', $eSettings);
            $elems[]   = self::appendExtras($sc, $eSettings, $recovered);
        }
        $inner = $elems ? ' ' . implode(' ', $elems) . ' ' : '';

        $attrStr   = implode(' ', $a);
        $recovered = self::columnSettings(self::attrs($attrStr));
        return self::appendExtras('[falcon_col ' . $attrStr . ']' . $inner . '[/falcon_col]', $s, $recovered);
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
                return '[falcon_heading ' . trim($a) . $vis . ']' . $body . '[/falcon_heading]';
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
                self::attrI($a, 'margin_top_unit',           $s['marginTopUnit']           ?? null, 'px');
                self::attrI($a, 'margin_top_unit_tablet',    $s['marginTopUnit_tablet']    ?? null);
                self::attrI($a, 'margin_top_unit_mobile',    $s['marginTopUnit_mobile']    ?? null);
                self::attrI($a, 'margin_right_unit',         $s['marginRightUnit']         ?? null, 'px');
                self::attrI($a, 'margin_right_unit_tablet',  $s['marginRightUnit_tablet']  ?? null);
                self::attrI($a, 'margin_right_unit_mobile',  $s['marginRightUnit_mobile']  ?? null);
                self::attrI($a, 'margin_bottom_unit',        $s['marginBottomUnit']        ?? null, 'px');
                self::attrI($a, 'margin_bottom_unit_tablet', $s['marginBottomUnit_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_unit_mobile', $s['marginBottomUnit_mobile'] ?? null);
                self::attrI($a, 'margin_left_unit',          $s['marginLeftUnit']          ?? null, 'px');
                self::attrI($a, 'margin_left_unit_tablet',   $s['marginLeftUnit_tablet']   ?? null);
                self::attrI($a, 'margin_left_unit_mobile',   $s['marginLeftUnit_mobile']   ?? null);
                // CSS
                self::attrI($a, 'css_class',         $s['cssClass']           ?? null);
                self::attrI($a, 'css_id',            $s['cssId']              ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['title'] ?? '');
                return '[falcon_title ' . trim($a) . $vis . ']' . $body . '[/falcon_title]';
            }

            case 'text': {
                $a = $base;
                self::attrI($a, 'font_size',   $s['fontSize']   ?? null);
                self::attrI($a, 'font_weight', $s['fontWeight'] ?? null);
                self::attrI($a, 'color',       $s['color']      ?? null);
                self::attrI($a, 'align',       $s['textAlign']  ?? null);
                self::attrI($a, 'css_class',   $s['cssClass']   ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['content'] ?? '');
                return '[falcon_text ' . trim($a) . $vis . ']' . $body . '[/falcon_text]';
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
                self::attrI($a, 'margin_top_unit',           $s['marginTopUnit']           ?? null, 'px');
                self::attrI($a, 'margin_top_unit_tablet',    $s['marginTopUnit_tablet']    ?? null);
                self::attrI($a, 'margin_top_unit_mobile',    $s['marginTopUnit_mobile']    ?? null);
                self::attrI($a, 'margin_right_unit',         $s['marginRightUnit']         ?? null, 'px');
                self::attrI($a, 'margin_right_unit_tablet',  $s['marginRightUnit_tablet']  ?? null);
                self::attrI($a, 'margin_right_unit_mobile',  $s['marginRightUnit_mobile']  ?? null);
                self::attrI($a, 'margin_bottom_unit',        $s['marginBottomUnit']        ?? null, 'px');
                self::attrI($a, 'margin_bottom_unit_tablet', $s['marginBottomUnit_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_unit_mobile', $s['marginBottomUnit_mobile'] ?? null);
                self::attrI($a, 'margin_left_unit',          $s['marginLeftUnit']          ?? null, 'px');
                self::attrI($a, 'margin_left_unit_tablet',   $s['marginLeftUnit_tablet']   ?? null);
                self::attrI($a, 'margin_left_unit_mobile',   $s['marginLeftUnit_mobile']   ?? null);
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
                self::attrI($a, 'padding_top_unit',           $s['paddingTopUnit']           ?? null, 'px');
                self::attrI($a, 'padding_top_unit_tablet',    $s['paddingTopUnit_tablet']    ?? null);
                self::attrI($a, 'padding_top_unit_mobile',    $s['paddingTopUnit_mobile']    ?? null);
                self::attrI($a, 'padding_right_unit',         $s['paddingRightUnit']         ?? null, 'px');
                self::attrI($a, 'padding_right_unit_tablet',  $s['paddingRightUnit_tablet']  ?? null);
                self::attrI($a, 'padding_right_unit_mobile',  $s['paddingRightUnit_mobile']  ?? null);
                self::attrI($a, 'padding_bottom_unit',        $s['paddingBottomUnit']        ?? null, 'px');
                self::attrI($a, 'padding_bottom_unit_tablet', $s['paddingBottomUnit_tablet'] ?? null);
                self::attrI($a, 'padding_bottom_unit_mobile', $s['paddingBottomUnit_mobile'] ?? null);
                self::attrI($a, 'padding_left_unit',          $s['paddingLeftUnit']          ?? null, 'px');
                self::attrI($a, 'padding_left_unit_tablet',   $s['paddingLeftUnit_tablet']   ?? null);
                self::attrI($a, 'padding_left_unit_mobile',   $s['paddingLeftUnit_mobile']   ?? null);
                // CSS
                self::attrI($a, 'css_class', $s['cssClass'] ?? null);
                self::attrI($a, 'css_id',    $s['cssId']    ?? null);
                return '[falcon_button ' . trim($a) . $vis . ' /]';
            }

            case 'image': {
                $a = $base;
                // Source
                self::attrI($a, 'url',       $s['url']       ?? $s['src'] ?? '');
                self::attrI($a, 'alt',       $s['alt']       ?? '');
                // Link
                self::attrI($a, 'link_url',    $s['linkUrl']    ?? null);
                self::attrI($a, 'link_target', $s['linkTarget'] ?? null);
                // Alignment (+ responsive)
                self::attrI($a, 'align',        $s['textAlign']        ?? null);
                self::attrI($a, 'align_tablet', $s['textAlign_tablet'] ?? null);
                self::attrI($a, 'align_mobile', $s['textAlign_mobile'] ?? null);
                // Dimensions
                self::attrI($a, 'width',            $s['width']           ?? null);
                self::attrI($a, 'width_unit',       $s['widthUnit']       ?? null, 'px');
                self::attrI($a, 'max_width',        $s['maxWidth']        ?? null);
                self::attrI($a, 'max_width_unit',   $s['maxWidthUnit']    ?? null, 'px');
                self::attrI($a, 'sticky_width',     $s['stickyWidth']     ?? null);
                self::attrI($a, 'sticky_width_unit', $s['stickyWidthUnit'] ?? null, 'px');
                // Margin (+ responsive)
                self::attrI($a, 'margin_top',            $s['marginTop']            ?? null);
                self::attrI($a, 'margin_top_tablet',     $s['marginTop_tablet']     ?? null);
                self::attrI($a, 'margin_top_mobile',     $s['marginTop_mobile']     ?? null);
                self::attrI($a, 'margin_right',          $s['marginRight']          ?? null);
                self::attrI($a, 'margin_right_tablet',   $s['marginRight_tablet']   ?? null);
                self::attrI($a, 'margin_right_mobile',   $s['marginRight_mobile']   ?? null);
                self::attrI($a, 'margin_bottom',         $s['marginBottom']         ?? null);
                self::attrI($a, 'margin_bottom_tablet',  $s['marginBottom_tablet']  ?? null);
                self::attrI($a, 'margin_bottom_mobile',  $s['marginBottom_mobile']  ?? null);
                self::attrI($a, 'margin_left',           $s['marginLeft']           ?? null);
                self::attrI($a, 'margin_left_tablet',    $s['marginLeft_tablet']    ?? null);
                self::attrI($a, 'margin_left_mobile',    $s['marginLeft_mobile']    ?? null);
                self::attrI($a, 'margin_top_unit',           $s['marginTopUnit']           ?? null, 'px');
                self::attrI($a, 'margin_top_unit_tablet',    $s['marginTopUnit_tablet']    ?? null);
                self::attrI($a, 'margin_top_unit_mobile',    $s['marginTopUnit_mobile']    ?? null);
                self::attrI($a, 'margin_right_unit',         $s['marginRightUnit']         ?? null, 'px');
                self::attrI($a, 'margin_right_unit_tablet',  $s['marginRightUnit_tablet']  ?? null);
                self::attrI($a, 'margin_right_unit_mobile',  $s['marginRightUnit_mobile']  ?? null);
                self::attrI($a, 'margin_bottom_unit',        $s['marginBottomUnit']        ?? null, 'px');
                self::attrI($a, 'margin_bottom_unit_tablet', $s['marginBottomUnit_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_unit_mobile', $s['marginBottomUnit_mobile'] ?? null);
                self::attrI($a, 'margin_left_unit',          $s['marginLeftUnit']          ?? null, 'px');
                self::attrI($a, 'margin_left_unit_tablet',   $s['marginLeftUnit_tablet']   ?? null);
                self::attrI($a, 'margin_left_unit_mobile',   $s['marginLeftUnit_mobile']   ?? null);
                // Border
                self::attrI($a, 'border_radius',      $s['borderRadius']     ?? null);
                self::attrI($a, 'border_radius_unit', $s['borderRadiusUnit'] ?? null, 'px');
                self::attrI($a, 'border_top',         $s['borderSizeTop']    ?? null);
                self::attrI($a, 'border_right',       $s['borderSizeRight']  ?? null);
                self::attrI($a, 'border_bottom',      $s['borderSizeBottom'] ?? null);
                self::attrI($a, 'border_left',        $s['borderSizeLeft']   ?? null);
                self::attrI($a, 'border_color',       $s['borderColor']      ?? null);
                // Hover
                self::attrI($a, 'hover_type',   $s['hoverType']   ?? null);
                self::attrI($a, 'aspect_ratio', $s['aspectRatio'] ?? null);
                self::attrI($a, 'focus_x',      $s['focusX']      ?? null, 50);
                self::attrI($a, 'focus_y',      $s['focusY']      ?? null, 50);
                // CSS
                self::attrI($a, 'css_class', $s['cssClass'] ?? null);
                self::attrI($a, 'css_id',    $s['cssId']    ?? null);
                return '[falcon_image ' . trim($a) . $vis . ' /]';
            }

            case 'spacer': {
                $a = $base;
                self::attrI($a, 'style',               $s['style']              ?? 'default');
                self::attrI($a, 'flex_grow',            $s['flexGrow']           ?? null);
                self::attrI($a, 'margin_top',           $s['marginTop']          ?? null);
                self::attrI($a, 'margin_top_unit',      $s['marginTopUnit']      ?? null, 'px');
                self::attrI($a, 'margin_bottom',        $s['marginBottom']       ?? null);
                self::attrI($a, 'margin_bottom_unit',   $s['marginBottomUnit']   ?? null, 'px');
                self::attrI($a, 'separator_width',      $s['separatorWidth']     ?? null);
                self::attrI($a, 'separator_width_unit', $s['separatorWidthUnit'] ?? null, '%');
                self::attrI($a, 'alignment',            $s['alignment']          ?? null);
                self::attrI($a, 'border_size',          $s['borderSize']         ?? null);
                self::attrI($a, 'separator_color',      $s['separatorColor']     ?? null);
                self::attrI($a, 'css_class',            $s['cssClass']           ?? null);
                self::attrI($a, 'css_id',               $s['cssId']              ?? null);
                return '[falcon_spacer ' . trim($a) . $vis . ' /]';
            }

            case 'html': {
                $a = $base;
                self::attrI($a, 'margin_top',         $s['marginTop']        ?? null);
                self::attrI($a, 'margin_top_unit',    $s['marginTopUnit']    ?? null, 'px');
                self::attrI($a, 'margin_bottom',      $s['marginBottom']     ?? null);
                self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
                self::attrI($a, 'css_class',          $s['cssClass']         ?? null);
                self::attrI($a, 'css_id',             $s['cssId']            ?? null);
                $body = $s['htmlContent'] ?? '';
                return '[falcon_html ' . trim($a) . $vis . ']' . $body . '[/falcon_html]';
            }

            case 'icon_box': {
                $a = $base;
                self::attrI($a, 'icon',              $s['icon']             ?? null, 'fas fa-star');
                self::attrI($a, 'layout',            $s['layout']           ?? null, 'top');
                self::attrI($a, 'alignment',         $s['alignment']        ?? null, 'center');
                self::attrI($a, 'link_url',          $s['linkUrl']          ?? null);
                self::attrI($a, 'link_target',       $s['linkTarget']       ?? null, '_self');
                self::attrI($a, 'icon_size',         $s['iconSize']         ?? null, 40);
                self::attrI($a, 'icon_size_unit',    $s['iconSizeUnit']     ?? null, 'px');
                self::attrI($a, 'icon_color',        $s['iconColor']        ?? null, '#0091ea');
                self::attrI($a, 'icon_bg_color',     $s['iconBgColor']      ?? null);
                self::attrI($a, 'icon_bg_opacity',   $s['iconBgColorOpacity'] ?? null, 1);
                self::attrI($a, 'icon_border_radius',$s['iconBorderRadius'] ?? null, 50);
                self::attrI($a, 'icon_spacing',      $s['iconSpacing']      ?? null, 16);
                self::attrI($a, 'icon_padding',      $s['iconPadding']      ?? null, 0);
                self::attrI($a, 'title_font_family', $s['titleFontFamily']  ?? null, 'inherit');
                self::attrI($a, 'title_tag',         $s['titleTag']         ?? null, 'h3');
                self::attrI($a, 'title_font_size',   $s['titleFontSize']    ?? null, 20);
                self::attrI($a, 'title_font_size_unit', $s['titleFontSizeUnit'] ?? null, 'px');
                self::attrI($a, 'title_font_weight', $s['titleFontWeight']  ?? null, '600');
                self::attrI($a, 'title_color',       $s['titleColor']       ?? null, '#222222');
                self::attrI($a, 'title_spacing',     $s['titleSpacing']     ?? null, 8);
                self::attrI($a, 'title_line_height', $s['titleLineHeight']  ?? null, 1.3);
                self::attrI($a, 'title_letter_spacing', $s['titleLetterSpacing'] ?? null, '0px');
                self::attrI($a, 'title_text_transform', $s['titleTextTransform'] ?? null, 'none');
                self::attrI($a, 'desc_font_family',  $s['descFontFamily']   ?? null, 'inherit');
                self::attrI($a, 'desc_font_size',    $s['descFontSize']     ?? null, 14);
                self::attrI($a, 'desc_font_size_unit', $s['descFontSizeUnit'] ?? null, 'px');
                self::attrI($a, 'desc_font_weight',  $s['descFontWeight']   ?? null, '400');
                self::attrI($a, 'desc_color',        $s['descColor']        ?? null, '#666666');
                self::attrI($a, 'desc_line_height',  $s['descLineHeight']   ?? null, 1.6);
                self::attrI($a, 'desc_letter_spacing', $s['descLetterSpacing'] ?? null, '0px');
                self::attrI($a, 'desc_text_transform', $s['descTextTransform'] ?? null, 'none');
                self::attrI($a, 'margin_top',        $s['marginTop']        ?? null);
                self::attrI($a, 'margin_top_unit',   $s['marginTopUnit']    ?? null, 'px');
                self::attrI($a, 'margin_bottom',     $s['marginBottom']     ?? null);
                self::attrI($a, 'margin_bottom_unit',$s['marginBottomUnit'] ?? null, 'px');
                self::attrI($a, 'css_class',         $s['cssClass']         ?? null);
                self::attrI($a, 'css_id',            $s['cssId']            ?? null);
                $title = htmlspecialchars($s['title'] ?? '', ENT_QUOTES);
                if ($title !== '') $a .= ' title="' . $title . '"';
                $body = $s['description'] ?? '';
                return '[falcon_icon_box ' . trim($a) . $vis . ']' . $body . '[/falcon_icon_box]';
            }

            case 'video': {
                $a = $base;
                self::attrI($a, 'url',          $s['url']         ?? '');
                self::attrI($a, 'video_source',  $s['videoSource'] ?? 'youtube', 'youtube');
                self::attrI($a, 'aspect_ratio',  $s['aspectRatio'] ?? '16-9', '16-9');
                self::attrI($a, 'autoplay',      ($s['autoplay']   ?? false) ? '1' : '0', '0');
                self::attrI($a, 'muted',         ($s['muted']      ?? false) ? '1' : '0', '0');
                self::attrI($a, 'loop',          ($s['loop']       ?? false) ? '1' : '0', '0');
                self::attrI($a, 'controls',      ($s['controls']   ?? true)  ? '1' : '0', '1');
                self::attrI($a, 'margin_top',    $s['marginTop']    ?? 0, 0);
                self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? 'px', 'px');
                self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? 0, 0);
                self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? 'px', 'px');
                self::attrI($a, 'css_class', $s['cssClass'] ?? null);
                self::attrI($a, 'css_id',    $s['cssId']    ?? null);
                return '[falcon_video ' . trim($a) . $vis . ' /]';
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
                self::attrI($a, 'margin_top_unit',           $s['marginTopUnit']           ?? null, 'px');
                self::attrI($a, 'margin_top_unit_tablet',    $s['marginTopUnit_tablet']    ?? null);
                self::attrI($a, 'margin_top_unit_mobile',    $s['marginTopUnit_mobile']    ?? null);
                self::attrI($a, 'margin_right_unit',         $s['marginRightUnit']         ?? null, 'px');
                self::attrI($a, 'margin_right_unit_tablet',  $s['marginRightUnit_tablet']  ?? null);
                self::attrI($a, 'margin_right_unit_mobile',  $s['marginRightUnit_mobile']  ?? null);
                self::attrI($a, 'margin_bottom_unit',        $s['marginBottomUnit']        ?? null, 'px');
                self::attrI($a, 'margin_bottom_unit_tablet', $s['marginBottomUnit_tablet'] ?? null);
                self::attrI($a, 'margin_bottom_unit_mobile', $s['marginBottomUnit_mobile'] ?? null);
                self::attrI($a, 'margin_left_unit',          $s['marginLeftUnit']          ?? null, 'px');
                self::attrI($a, 'margin_left_unit_tablet',   $s['marginLeftUnit_tablet']   ?? null);
                self::attrI($a, 'margin_left_unit_mobile',   $s['marginLeftUnit_mobile']   ?? null);
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
                self::attrI($a, 'padding_top_unit',           $s['paddingTopUnit']           ?? null, 'px');
                self::attrI($a, 'padding_top_unit_tablet',    $s['paddingTopUnit_tablet']    ?? null);
                self::attrI($a, 'padding_top_unit_mobile',    $s['paddingTopUnit_mobile']    ?? null);
                self::attrI($a, 'padding_right_unit',         $s['paddingRightUnit']         ?? null, 'px');
                self::attrI($a, 'padding_right_unit_tablet',  $s['paddingRightUnit_tablet']  ?? null);
                self::attrI($a, 'padding_right_unit_mobile',  $s['paddingRightUnit_mobile']  ?? null);
                self::attrI($a, 'padding_bottom_unit',        $s['paddingBottomUnit']        ?? null, 'px');
                self::attrI($a, 'padding_bottom_unit_tablet', $s['paddingBottomUnit_tablet'] ?? null);
                self::attrI($a, 'padding_bottom_unit_mobile', $s['paddingBottomUnit_mobile'] ?? null);
                self::attrI($a, 'padding_left_unit',          $s['paddingLeftUnit']          ?? null, 'px');
                self::attrI($a, 'padding_left_unit_tablet',   $s['paddingLeftUnit_tablet']   ?? null);
                self::attrI($a, 'padding_left_unit_mobile',   $s['paddingLeftUnit_mobile']   ?? null);
                // CSS
                self::attrI($a, 'css_class', $s['cssClass'] ?? null);
                self::attrI($a, 'css_id',    $s['cssId']    ?? null);
                $body = str_replace(["\r\n", "\r", "\n"], '', $s['content'] ?? '');
                $tag = $type === 'text_block' ? 'text_block' : 'special_text';
                return '[falcon_' . $tag . ' ' . trim($a) . $vis . ']' . $body . '[/falcon_' . $tag . ']';
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
                self::attrI($a, 'icon_position',      $s['menuIconPosition'] ?? null, 'left');
                self::attrI($a, 'icon_gap',           $s['menuIconGap']      ?? null);
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
                return '[falcon_menu ' . trim($a) . $vis . ' /]';
            }

            case 'row': {
                if (!empty($el['columns'])) {
                    $rowCols = [];
                    foreach ($el['columns'] as $nestedCol) {
                        $rowCols[] = self::columnToShortcode($nestedCol);
                    }
                    $rowInner = ' ' . implode(' ', $rowCols) . ' ';
                    return '[falcon_row' . ($base ? ' ' . trim($base) : '') . $vis . ']' . $rowInner . '[/falcon_row]';
                }
                return '[falcon_row ' . trim($base) . $vis . ' /]';
            }

            case 'card': {
                $a = $base;
                self::attrI($a, 'post_card_id',          $s['post_card_id']          ?? null);
                self::attrI($a, 'content_source',        $s['content_source']        ?? null, 'posts');
                self::attrI($a, 'post_type',             $s['post_type']             ?? null, 'post');
                self::attrI($a, 'posts_by',              $s['posts_by']              ?? null, 'all');
                self::attrI($a, 'posts_by_value',        $s['posts_by_value']        ?? null);
                self::attrI($a, 'posts_by_cf_key',       $s['posts_by_cf_key']       ?? null);
                self::attrI($a, 'posts_by_cf_value',     $s['posts_by_cf_value']     ?? null);
                $postStatus = $s['post_status'] ?? ['publish'];
                if ($postStatus !== ['publish']) {
                    self::attrI($a, 'post_status', is_array($postStatus) ? implode(',', $postStatus) : $postStatus);
                }
                if (!empty($s['hide_out_of_stock']))     $a .= ' hide_out_of_stock="yes"';
                self::attrI($a, 'posts_count',           $s['posts_count']           ?? null, 6);
                self::attrI($a, 'posts_offset',          $s['posts_offset']          ?? null, 0);
                self::attrI($a, 'order_by',              $s['order_by']              ?? null, 'created_at');
                self::attrI($a, 'order',                 $s['order']                 ?? null, 'desc');
                self::attrI($a, 'pagination_type',       $s['pagination_type']       ?? null, 'none');
                self::attrI($a, 'nothing_found_message', $s['nothing_found_message'] ?? null, 'No posts found.');
                self::attrI($a, 'layout',                $s['layout']                ?? null, 'grid');
                self::attrI($a, 'card_alignment',        $s['card_alignment']        ?? null, 'left');
                self::attrI($a, 'columns',               $s['columns']               ?? null, 3);
                self::attrI($a, 'columns_tablet',        $s['columns_tablet']        ?? null, 2);
                self::attrI($a, 'columns_mobile',        $s['columns_mobile']        ?? null, 1);
                self::attrI($a, 'column_spacing',        $s['column_spacing']        ?? null, 24);
                self::attrI($a, 'row_spacing',           $s['row_spacing']           ?? null, 24);
                self::attrI($a, 'margin_top',            $s['marginTop']             ?? null, 0);
                self::attrI($a, 'margin_top_unit',       $s['marginTopUnit']         ?? null, 'px');
                self::attrI($a, 'margin_right',          $s['marginRight']           ?? null, 0);
                self::attrI($a, 'margin_right_unit',     $s['marginRightUnit']       ?? null, 'px');
                self::attrI($a, 'margin_bottom',         $s['marginBottom']          ?? null, 0);
                self::attrI($a, 'margin_bottom_unit',    $s['marginBottomUnit']      ?? null, 'px');
                self::attrI($a, 'margin_left',           $s['marginLeft']            ?? null, 0);
                self::attrI($a, 'margin_left_unit',      $s['marginLeftUnit']        ?? null, 'px');
                self::attrI($a, 'css_class',             $s['cssClass']              ?? null);
                self::attrI($a, 'css_id',                $s['cssId']                 ?? null);
                self::attrI($a, 'taxonomy_slug',         $s['taxonomy_slug']         ?? null);
                $taxInclude = is_array($s['taxonomy_include'] ?? '') ? implode(',', $s['taxonomy_include']) : ($s['taxonomy_include'] ?? '');
                self::attrI($a, 'taxonomy_include', $taxInclude ?: null);
                $taxExclude = is_array($s['taxonomy_exclude'] ?? '') ? implode(',', $s['taxonomy_exclude']) : ($s['taxonomy_exclude'] ?? '');
                self::attrI($a, 'taxonomy_exclude', $taxExclude ?: null);
                self::attrI($a, 'carousel_autoplay',       ($s['carousel_autoplay']       ?? false) ? 'yes' : null);
                self::attrI($a, 'carousel_autoplay_speed', $s['carousel_autoplay_speed']  ?? null, 3000);
                self::attrI($a, 'carousel_arrows',         ($s['carousel_arrows']         ?? true)  ? null : 'no');
                self::attrI($a, 'carousel_dots',           ($s['carousel_dots']           ?? true)  ? null : 'no');
                self::attrI($a, 'carousel_loop',           ($s['carousel_loop']           ?? false) ? 'yes' : null);
                self::attrI($a, 'items_per_slide',         $s['items_per_slide']         ?? null, 1);
                self::attrI($a, 'items_per_slide_tablet',  $s['items_per_slide_tablet']  ?? null, 0);
                self::attrI($a, 'items_per_slide_mobile',  $s['items_per_slide_mobile']  ?? null, 0);
                return '[falcon_card ' . trim($a) . $vis . ' /]';
            }

            case 'accordion': {
                $a = $base;
                self::attrI($a, 'default_open',          $s['defaultOpen']        ?? null, 0);
                self::attrI($a, 'allow_multiple',         ($s['allowMultiple'] ?? false) ? 'yes' : null);
                self::attrI($a, 'icon_type',              $s['iconType']           ?? null, 'plus');
                self::attrI($a, 'icon_position',          $s['iconPosition']       ?? null, 'right');
                self::attrI($a, 'title_font_size',        $s['titleFontSize']        ?? null, 15);
                self::attrI($a, 'title_font_weight',      $s['titleFontWeight']      ?? null, '600');
                self::attrI($a, 'title_font_family',      $s['titleFontFamily']      ?? null, 'inherit');
                self::attrI($a, 'title_letter_spacing',   $s['titleLetterSpacing']   ?? null, '0px');
                self::attrI($a, 'title_line_height',      $s['titleLineHeight']      ?? null, 1.4);
                self::attrI($a, 'title_text_transform',   $s['titleTextTransform']   ?? null, 'none');
                self::attrI($a, 'title_color',            $s['titleColor']           ?? null, '#222222');
                self::attrI($a, 'title_bg_color',         $s['titleBgColor']         ?? null, '#f8fafc');
                self::attrI($a, 'title_active_bg_color',  $s['titleActiveBgColor']   ?? null, '#0091ea');
                self::attrI($a, 'title_active_color',     $s['titleActiveColor']     ?? null, '#ffffff');
                self::attrI($a, 'title_padding',          $s['titlePadding']         ?? null, 16);
                self::attrI($a, 'content_font_size',      $s['contentFontSize']      ?? null, 14);
                self::attrI($a, 'content_font_family',    $s['contentFontFamily']    ?? null, 'inherit');
                self::attrI($a, 'content_letter_spacing', $s['contentLetterSpacing'] ?? null, '0px');
                self::attrI($a, 'content_line_height',    $s['contentLineHeight']    ?? null, 1.6);
                self::attrI($a, 'content_color',          $s['contentColor']         ?? null, '#555555');
                self::attrI($a, 'content_bg_color',       $s['contentBgColor']       ?? null, '#ffffff');
                self::attrI($a, 'content_padding',        $s['contentPadding']       ?? null, 16);
                self::attrI($a, 'border_color',           $s['borderColor']        ?? null, '#e2e8f0');
                self::attrI($a, 'border_radius',          $s['borderRadius']       ?? null, 8);
                self::attrI($a, 'item_gap',               $s['itemGap']            ?? null, 8);
                self::attrI($a, 'margin_top',             $s['marginTop']          ?? null);
                self::attrI($a, 'margin_top_unit',        $s['marginTopUnit']      ?? null, 'px');
                self::attrI($a, 'margin_bottom',          $s['marginBottom']       ?? null);
                self::attrI($a, 'margin_bottom_unit',     $s['marginBottomUnit']   ?? null, 'px');
                self::attrI($a, 'css_class',              $s['cssClass']           ?? null);
                self::attrI($a, 'css_id',                 $s['cssId']              ?? null);
                $items = '';
                foreach ($s['items'] ?? [] as $item) {
                    $t  = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
                    $iid = !empty($item['id']) ? ' id="' . htmlspecialchars((string) $item['id'], ENT_QUOTES) . '"' : '';
                    $items .= "\n" . '[falcon_acc_item' . $iid . ' title="' . $t . '"]' . ($item['content'] ?? '') . '[/falcon_acc_item]';
                }
                return '[falcon_accordion ' . trim($a) . $vis . ']' . $items . "\n[/falcon_accordion]";
            }

            case 'tabs': {
                $a = $base;
                self::attrI($a, 'default_active',   $s['defaultActive']  ?? null, 0);
                self::attrI($a, 'style',            $s['style']          ?? null, 'underline');
                self::attrI($a, 'alignment',        $s['alignment']      ?? null, 'left');
                self::attrI($a, 'tab_font_size',        $s['tabFontSize']          ?? null, 14);
                self::attrI($a, 'tab_font_weight',      $s['tabFontWeight']        ?? null, '500');
                self::attrI($a, 'tab_font_family',      $s['tabFontFamily']        ?? null, 'inherit');
                self::attrI($a, 'tab_letter_spacing',   $s['tabLetterSpacing']     ?? null, '0px');
                self::attrI($a, 'tab_color',            $s['tabColor']             ?? null, '#666666');
                self::attrI($a, 'active_color',         $s['activeColor']          ?? null, '#0091ea');
                self::attrI($a, 'content_font_size',    $s['contentFontSize']      ?? null, 14);
                self::attrI($a, 'content_font_family',  $s['contentFontFamily']    ?? null, 'inherit');
                self::attrI($a, 'content_letter_spacing',$s['contentLetterSpacing']?? null, '0px');
                self::attrI($a, 'content_line_height',  $s['contentLineHeight']    ?? null, 1.6);
                self::attrI($a, 'content_color',        $s['contentColor']         ?? null, '#555555');
                self::attrI($a, 'content_bg_color', $s['contentBgColor'] ?? null, '#ffffff');
                self::attrI($a, 'content_padding',  $s['contentPadding'] ?? null, 20);
                self::attrI($a, 'border_color',     $s['borderColor']    ?? null, '#e2e8f0');
                self::attrI($a, 'border_radius',    $s['borderRadius']   ?? null, 4);
                self::attrI($a, 'margin_top',       $s['marginTop']      ?? null);
                self::attrI($a, 'margin_top_unit',  $s['marginTopUnit']  ?? null, 'px');
                self::attrI($a, 'margin_bottom',    $s['marginBottom']   ?? null);
                self::attrI($a, 'margin_bottom_unit',$s['marginBottomUnit']?? null, 'px');
                self::attrI($a, 'css_class',        $s['cssClass']       ?? null);
                self::attrI($a, 'css_id',           $s['cssId']          ?? null);
                $items = '';
                foreach ($s['items'] ?? [] as $item) {
                    $l  = htmlspecialchars($item['label'] ?? '', ENT_QUOTES);
                    $iid = !empty($item['id']) ? ' id="' . htmlspecialchars((string) $item['id'], ENT_QUOTES) . '"' : '';
                    $items .= "\n" . '[falcon_tab_item' . $iid . ' label="' . $l . '"]' . ($item['content'] ?? '') . '[/falcon_tab_item]';
                }
                return '[falcon_tabs ' . trim($a) . $vis . ']' . $items . "\n[/falcon_tabs]";
            }

            case 'icon_list': {
                $a = $base;
                self::attrI($a, 'default_icon',      $s['defaultIcon']     ?? null, 'fa fa-check');
                self::attrI($a, 'icon_size',         $s['iconSize']        ?? null, 14);
                self::attrI($a, 'icon_color',        $s['iconColor']       ?? null, '#0091ea');
                self::attrI($a, 'icon_position',     $s['iconPosition']    ?? null, 'left');
                self::attrI($a, 'gap',               $s['gap']             ?? null, 10);
                self::attrI($a, 'item_spacing',      $s['itemSpacing']     ?? null, 10);
                self::attrI($a, 'text_align',        $s['textAlign']       ?? null, 'left');
                self::attrI($a, 'text_color',        $s['textColor']       ?? null, '#333333');
                self::attrI($a, 'font_size',         $s['fontSize']        ?? null, 15);
                self::attrI($a, 'font_size_unit',    $s['fontSizeUnit']    ?? null, 'px');
                self::attrI($a, 'font_weight',       $s['fontWeight']      ?? null, '400');
                self::attrI($a, 'font_family',       $s['fontFamily']      ?? null, 'inherit');
                self::attrI($a, 'line_height',       $s['lineHeight']      ?? null, '1.5');
                self::attrI($a, 'margin_top',        $s['marginTop']       ?? null);
                self::attrI($a, 'margin_top_unit',   $s['marginTopUnit']   ?? null, 'px');
                self::attrI($a, 'margin_bottom',     $s['marginBottom']    ?? null);
                self::attrI($a, 'margin_bottom_unit',$s['marginBottomUnit']?? null, 'px');
                self::attrI($a, 'css_class',         $s['cssClass']        ?? null);
                self::attrI($a, 'css_id',            $s['cssId']           ?? null);
                $items = '';
                foreach ($s['items'] ?? [] as $item) {
                    $ia  = !empty($item['id']) ? ' id="' . htmlspecialchars((string) $item['id'], ENT_QUOTES) . '"' : '';
                    $ia .= ' icon="' . htmlspecialchars($item['icon'] ?? 'fa fa-check', ENT_QUOTES) . '"';
                    if (!empty($item['iconColor']))  $ia .= ' icon_color="' . htmlspecialchars($item['iconColor'], ENT_QUOTES) . '"';
                    if (!empty($item['link']))       $ia .= ' link="' . htmlspecialchars($item['link'], ENT_QUOTES) . '"';
                    if (!empty($item['linkTarget'])) $ia .= ' link_target="' . htmlspecialchars($item['linkTarget'], ENT_QUOTES) . '"';
                    $items .= "\n" . '[falcon_icon_list_item' . $ia . ']' . htmlspecialchars($item['text'] ?? '', ENT_QUOTES) . '[/falcon_icon_list_item]';
                }
                return '[falcon_icon_list ' . trim($a) . $vis . ']' . $items . "\n[/falcon_icon_list]";
            }

            case 'counter': {
                $a = $base;
                self::attrI($a, 'end',        $s['endValue']            ?? null);
                self::attrI($a, 'start',      $s['startValue']          ?? null, 0);
                self::attrI($a, 'prefix',     $s['prefix']              ?? null);
                self::attrI($a, 'suffix',     $s['suffix']              ?? null);
                self::attrI($a, 'label',      $s['label']               ?? null);
                self::attrI($a, 'dur',        $s['duration']            ?? null, 2000);
                self::attrI($a, 'dec',        $s['decimals']            ?? null, 0);
                self::attrI($a, 'sep',        $s['separator']           ?? null);
                self::attrI($a, 'align',      $s['textAlign']           ?? null, 'center');
                self::attrI($a, 'num_size',   $s['numberFontSize']      ?? null, '48px');
                self::attrI($a, 'num_weight', $s['numberFontWeight']    ?? null, '700');
                self::attrI($a, 'num_color',  $s['numberColor']         ?? null);
                self::attrI($a, 'num_family', $s['numberFontFamily']    ?? null, 'inherit');
                self::attrI($a, 'num_lh',     $s['numberLineHeight']    ?? null, '1.1');
                self::attrI($a, 'num_ls',     $s['numberLetterSpacing'] ?? null, '0px');
                self::attrI($a, 'lbl_size',   $s['labelFontSize']       ?? null, '14px');
                self::attrI($a, 'lbl_weight', $s['labelFontWeight']     ?? null, '400');
                self::attrI($a, 'lbl_color',  $s['labelColor']          ?? null);
                self::attrI($a, 'lbl_family', $s['labelFontFamily']     ?? null, 'inherit');
                self::attrI($a, 'lbl_lh',     $s['labelLineHeight']     ?? null, '1.4');
                self::attrI($a, 'lbl_ls',     $s['labelLetterSpacing']  ?? null, '0px');
                self::attrI($a, 'lbl_tt',     $s['labelTextTransform']  ?? null, 'none');
                self::attrI($a, 'icon',       $s['icon']                ?? null);
                self::attrI($a, 'icon_size',  $s['iconSize']            ?? null, 40);
                self::attrI($a, 'icon_color', $s['iconColor']           ?? null, '#0091ea');
                self::attrI($a, 'mt',         $s['marginTop']           ?? null, 0);
                self::attrI($a, 'mt_unit',    $s['marginTopUnit']       ?? null, 'px');
                self::attrI($a, 'mb',         $s['marginBottom']        ?? null, 0);
                self::attrI($a, 'mb_unit',    $s['marginBottomUnit']    ?? null, 'px');
                self::attrI($a, 'css_class',  $s['cssClass']            ?? null);
                self::attrI($a, 'css_id',     $s['cssId']               ?? null);
                return '[falcon_counter ' . trim($a) . $vis . ' /]';
            }

            case 'star_rating': {
                $a = $base;
                self::attrI($a, 'rating',      $s['rating']              ?? null, 5);
                self::attrI($a, 'max',         $s['maxStars']            ?? null, 5);
                self::attrI($a, 'label',       $s['label']               ?? null);
                self::attrI($a, 'size',        $s['starSize']            ?? null, 24);
                self::attrI($a, 'color',       $s['starColor']           ?? null, '#f59e0b');
                self::attrI($a, 'empty',       $s['emptyColor']          ?? null, '#d1d5db');
                self::attrI($a, 'align',       $s['textAlign']           ?? null, 'center');
                self::attrI($a, 'gap',         $s['gap']                 ?? null, 4);
                self::attrI($a, 'lbl_family',  $s['labelFontFamily']     ?? null, 'inherit');
                self::attrI($a, 'lbl_size',    $s['labelFontSize']       ?? null, '13px');
                self::attrI($a, 'lbl_weight',  $s['labelFontWeight']     ?? null, '400');
                self::attrI($a, 'lbl_lh',      $s['labelLineHeight']     ?? null, '1.4');
                self::attrI($a, 'lbl_ls',      $s['labelLetterSpacing']  ?? null, '0px');
                self::attrI($a, 'lbl_tt',      $s['labelTextTransform']  ?? null, 'none');
                self::attrI($a, 'lbl_color',   $s['labelColor']          ?? null, '#6b7280');
                self::attrI($a, 'mt',          $s['marginTop']           ?? null, 0);
                self::attrI($a, 'mt_unit',     $s['marginTopUnit']       ?? null, 'px');
                self::attrI($a, 'mb',          $s['marginBottom']        ?? null, 0);
                self::attrI($a, 'mb_unit',     $s['marginBottomUnit']    ?? null, 'px');
                self::attrI($a, 'css_class',   $s['cssClass']            ?? null);
                self::attrI($a, 'css_id',      $s['cssId']               ?? null);
                return '[falcon_star_rating ' . trim($a) . $vis . ' /]';
            }

            case 'gallery': {
                $a = $base;
                $imgs = $s['images'] ?? [];
                $imgCount = count($imgs);
                if ($imgCount > 0) {
                    self::attrI($a, 'img_n', $imgCount);
                    foreach ($imgs as $idx => $img) {
                        self::attrI($a, 'img_' . $idx,           $img['url']     ?? null);
                        self::attrI($a, 'img_' . $idx . '_a',    $img['alt']     ?? null);
                        self::attrI($a, 'img_' . $idx . '_c',    $img['caption'] ?? null);
                    }
                }
                self::attrI($a, 'cols',    $s['columns']         ?? null, 3);
                self::attrI($a, 'cols_t',  $s['columnsTablet']   ?? null, 2);
                self::attrI($a, 'cols_m',  $s['columnsMobile']   ?? null, 1);
                self::attrI($a, 'gap',     $s['gap']             ?? null, 8);
                self::attrI($a, 'ratio',   $s['aspectRatio']     ?? null, 'square');
                self::attrI($a, 'radius',  $s['borderRadius']    ?? null, 0);
                $lbVal = isset($s['lightbox']) ? ($s['lightbox'] ? '1' : '0') : null;
                self::attrI($a, 'lightbox', $lbVal, '1');
                self::attrI($a, 'hover',      $s['hoverEffect']          ?? null, 'zoom');
                self::attrI($a, 'cap_align', $s['captionAlign']         ?? null, 'center');
                self::attrI($a, 'cap_family',$s['captionFontFamily']    ?? null, 'inherit');
                self::attrI($a, 'cap_size',  $s['captionFontSize']      ?? null, '13px');
                self::attrI($a, 'cap_weight',$s['captionFontWeight']    ?? null, '400');
                self::attrI($a, 'cap_lh',    $s['captionLineHeight']    ?? null, '1.4');
                self::attrI($a, 'cap_ls',    $s['captionLetterSpacing'] ?? null, '0px');
                self::attrI($a, 'cap_tt',    $s['captionTextTransform'] ?? null, 'none');
                self::attrI($a, 'cap_color', $s['captionColor']         ?? null, '#6b7280');
                self::attrI($a, 'img_bw',    $s['imgBorderWidth']         ?? null, 0);
                self::attrI($a, 'img_bs',    $s['imgBorderStyle']         ?? null, 'solid');
                self::attrI($a, 'img_bc',    $s['imgBorderColor']         ?? null, '#e2e8f0');
                self::attrI($a, 'mt',        $s['marginTop']            ?? null, 0);
                self::attrI($a, 'mt_unit', $s['marginTopUnit']   ?? null, 'px');
                self::attrI($a, 'mb',      $s['marginBottom']    ?? null, 0);
                self::attrI($a, 'mb_unit', $s['marginBottomUnit'] ?? null, 'px');
                self::attrI($a, 'mt_t',      isset($s['marginTop_tablet'])    && $s['marginTop_tablet']    !== '' ? $s['marginTop_tablet']    : null);
                self::attrI($a, 'mt_t_unit', isset($s['marginTop_tablet'])    && $s['marginTop_tablet']    !== '' ? ($s['marginTopUnit_tablet']    ?? 'px') : null);
                self::attrI($a, 'mb_t',      isset($s['marginBottom_tablet']) && $s['marginBottom_tablet'] !== '' ? $s['marginBottom_tablet'] : null);
                self::attrI($a, 'mb_t_unit', isset($s['marginBottom_tablet']) && $s['marginBottom_tablet'] !== '' ? ($s['marginBottomUnit_tablet'] ?? 'px') : null);
                self::attrI($a, 'mt_m',      isset($s['marginTop_mobile'])    && $s['marginTop_mobile']    !== '' ? $s['marginTop_mobile']    : null);
                self::attrI($a, 'mt_m_unit', isset($s['marginTop_mobile'])    && $s['marginTop_mobile']    !== '' ? ($s['marginTopUnit_mobile']    ?? 'px') : null);
                self::attrI($a, 'mb_m',      isset($s['marginBottom_mobile']) && $s['marginBottom_mobile'] !== '' ? $s['marginBottom_mobile'] : null);
                self::attrI($a, 'mb_m_unit', isset($s['marginBottom_mobile']) && $s['marginBottom_mobile'] !== '' ? ($s['marginBottomUnit_mobile'] ?? 'px') : null);
                self::attrI($a, 'css_class', $s['cssClass']      ?? null);
                self::attrI($a, 'css_id',    $s['cssId']         ?? null);
                return '[falcon_gallery ' . trim($a) . $vis . ' /]';
            }

            case 'ticker': {
                $a = $base;
                $items = $s['items'] ?? [];
                $n = count($items);
                if ($n > 0) {
                    self::attrI($a, 'item_n', $n);
                    foreach ($items as $idx => $item) {
                        self::attrI($a, 'item_' . $idx,         $item['text'] ?? null);
                        self::attrI($a, 'item_' . $idx . '_u',  $item['url']  ?? null);
                    }
                }
                self::attrI($a, 'label',     $s['label']           ?? null);
                self::attrI($a, 'speed',     $s['speed']           ?? null, 50);
                self::attrI($a, 'dir',       $s['direction']       ?? null, 'left');
                self::attrI($a, 'pause',     isset($s['pauseOnHover']) ? ($s['pauseOnHover'] ? '1' : '0') : null, '1');
                self::attrI($a, 'sep',       $s['separator']       ?? null, '•');
                self::attrI($a, 'bg',        $s['bgColor']         ?? null, '#1e3a8a');
                self::attrI($a, 'color',     $s['textColor']       ?? null, '#ffffff');
                self::attrI($a, 'lbl_bg',    $s['labelBgColor']    ?? null, '#ef4444');
                self::attrI($a, 'lbl_color', $s['labelTextColor']  ?? null, '#ffffff');
                self::attrI($a, 'fsize',     $s['fontSize']        ?? null, '14px');
                self::attrI($a, 'fweight',   $s['fontWeight']      ?? null, '500');
                self::attrI($a, 'height',    $s['height']          ?? null, 44);
                self::attrI($a, 'radius',    $s['borderRadius']    ?? null, 0);
                self::attrI($a, 'mt',        $s['marginTop']       ?? null, 0);
                self::attrI($a, 'mt_unit',   $s['marginTopUnit']   ?? null, 'px');
                self::attrI($a, 'mb',        $s['marginBottom']    ?? null, 0);
                self::attrI($a, 'mb_unit',   $s['marginBottomUnit'] ?? null, 'px');
                self::attrI($a, 'css_class', $s['cssClass']        ?? null);
                self::attrI($a, 'css_id',    $s['cssId']           ?? null);
                return '[falcon_ticker ' . trim($a) . $vis . ' /]';
            }

            default: {
                $cdefs = self::customDefs();
                $cdef  = $cdefs[$type] ?? null;
                $tag   = ($cdef && !empty($cdef['shortcode'])) ? $cdef['shortcode'] : 'falcon_element';
                $bases = $cdef ? self::ceAllowedKeys($cdef) : null;
                $extraKeys = ($cdef && is_array($cdef['shortcode_keys'] ?? null)) ? $cdef['shortcode_keys'] : [];
                $repKeys = [];
                if ($cdef) {
                    foreach (self::ceFieldList($cdef) as $f) {
                        if (($f['type'] ?? '') === 'repeater') $repKeys[] = $f['key'];
                    }
                }

                $out = '[' . $tag;
                if ($tag === 'falcon_element') $out .= ' type="' . $type . '"';
                $out .= ' ' . trim($base) . $vis;

                foreach ($el['settings'] ?? [] as $k => $v) {
                    if ($k === 'visibility' || (is_string($k) && str_starts_with($k, '_'))) continue;
                    if (in_array($k, $repKeys, true)) continue;                  // repeater → child shortcodes
                    // prune keys that aren't a declared field, a global key, or a user-whitelisted key
                    if ($bases !== null && !self::ceKeyAllowed($bases, $k) && !in_array($k, self::CE_GLOBAL_KEYS, true) && !in_array($k, $extraKeys, true)) continue;
                    if ($v === null || $v === '' || $v === false) continue;
                    if (is_bool($v))  { self::attrI($out, $k, '1'); continue; }
                    if (is_array($v)) { self::attrI($out, $k, implode(',', $v)); continue; } // checkbox → comma list
                    if (!is_scalar($v)) continue;
                    self::attrI($out, $k, (string) $v);
                }

                // Repeater rows → readable child shortcodes
                $children = '';
                foreach ($repKeys as $rk) {
                    $rows = $el['settings'][$rk] ?? null;
                    if (!is_array($rows)) continue;
                    $childTag = 'lzr_' . $rk;
                    foreach ($rows as $row) {
                        if (!is_array($row)) continue;
                        $ra = '';
                        foreach ($row as $sk => $rv) {
                            if (is_string($sk) && str_starts_with($sk, '_')) continue;
                            if ($rv === null || $rv === '' || $rv === false) continue;
                            if (is_bool($rv))  { self::attrI($ra, $sk, '1'); continue; }
                            if (!is_scalar($rv)) continue;
                            self::attrI($ra, $sk, (string) $rv);
                        }
                        $children .= '[' . $childTag . $ra . ' /]';
                    }
                }

                if ($children !== '') return $out . ']' . $children . '[/' . $tag . ']';
                return $out . ' /]';
            }
        }
    }

    // =========================================================================
    // Shortcode → JSON
    // =========================================================================

    private static function parseContainer(string $attrStr, string $inner): ?array
    {
        $a  = self::attrs($attrStr);
        $id = $a['id'] ?? self::uid();
        $tp = $a['type'] ?? 'container';

        $settings = self::containerSettings($a);
        $names    = self::perTypeNames('container', ['id' => $id, 'type' => $tp, 'settings' => $settings, 'columns' => []]);
        $settings = self::mergeExtras($a, $settings, $names);

        return [
            'id'       => $id,
            'type'     => $tp,
            'settings' => $settings,
            'columns'  => self::parseColumnsFromContent($inner),
        ];
    }

    /**
     * Depth-counting column extractor — handles nested [falcon_col] inside [falcon_row] correctly.
     * A simple .*? regex stops at the first [/falcon_col] it finds (the inner nested one),
     * which breaks nested-row structures.
     */
    private static function parseColumnsFromContent(string $content): array
    {
        $cols    = [];
        $pos     = 0;
        $len     = strlen($content);
        $tagLen  = 11; // strlen('[falcon_col')
        $closeLen = 13; // strlen('[/falcon_col]')

        while ($pos < $len) {
            $tagStart = strpos($content, '[falcon_col', $pos);
            if ($tagStart === false) break;

            // Must be [falcon_col] or [falcon_col ...], not [falcon_columns or similar
            $c = $content[$tagStart + $tagLen] ?? '';
            if ($c !== ' ' && $c !== ']') { $pos = $tagStart + $tagLen; continue; }

            $openEnd = strpos($content, ']', $tagStart);
            if ($openEnd === false) break;

            $attrStr = substr($content, $tagStart + $tagLen, $openEnd - $tagStart - $tagLen);
            $depth   = 1;
            $search  = $openEnd + 1;
            $done    = false;

            while ($depth > 0 && $search < $len) {
                $nextOpen  = strpos($content, '[falcon_col', $search);
                $nextClose = strpos($content, '[/falcon_col]', $search);

                if ($nextClose === false) break;

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $nc = $content[$nextOpen + $tagLen] ?? '';
                    if ($nc === ' ' || $nc === ']') $depth++;
                    $search = $nextOpen + $tagLen;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $colInner = substr($content, $openEnd + 1, $nextClose - $openEnd - 1);
                        $col = self::parseColumn($attrStr, $colInner);
                        if ($col) $cols[] = $col;
                        $pos  = $nextClose + $closeLen;
                        $done = true;
                        break;
                    }
                    $search = $nextClose + $closeLen;
                }
            }

            if (!$done) break;
        }

        return $cols;
    }

    private static function parseColumn(string $attrStr, string $inner): ?array
    {
        $a  = self::attrs($attrStr);
        $id = $a['id'] ?? self::uid();

        $settings = self::columnSettings($a);
        $names    = self::perTypeNames('column', ['id' => $id, 'basis' => $a['width'] ?? '100%', 'settings' => $settings, 'elements' => []]);
        $settings = self::mergeExtras($a, $settings, $names);

        $column = [
            'id'       => $id,
            'basis'        => $a['width']        ?? '100%',
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
                $elem = self::parseElement($em[1][0], $em[2][0], $em[3][0] ?? '');
                if ($elem) $found[] = ['pos' => $em[0][1], 'el' => $elem];
            }
        }

        // Parse [falcon_*] built-in elements (excludes section/col; sub-items like acc_item handled inside parent)
        $falconRx = '/\[falcon_(?!section\b|col\b|acc_item\b|tab_item\b|icon_list_item\b)(\w+)([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/falcon_\1\])/';
        if (preg_match_all($falconRx, $inner, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $em) {
                $elem = self::parseElement($em[1][0], $em[2][0], $em[3][0] ?? '');
                if ($elem) $found[] = ['pos' => $em[0][1], 'el' => $elem];
            }
        }

        // Custom element shortcode tags (registered via falcon_builder_elements)
        foreach (self::customDefs() as $type => $def) {
            $tag = $def['shortcode'] ?? $type;
            if (!$tag || str_starts_with($tag, 'lazy_')) continue; // lazy_* already handled above
            $rx = '/\[' . preg_quote($tag, '/') . '([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/' . preg_quote($tag, '/') . '\])/';
            if (preg_match_all($rx, $inner, $cm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($cm as $em) {
                    $elem = self::parseCustomElement($def, $em[1][0], $em[2][0] ?? '');
                    if ($elem) $found[] = ['pos' => $em[0][1], 'el' => $elem];
                }
            }
        }

        usort($found, fn($x, $y) => $x['pos'] <=> $y['pos']);
        $column['elements'] = array_map(fn($f) => $f['el'], $found);

        return $column;
    }

    /** Parse a custom element shortcode (type-aware, mirrors JS parseCustomElement). */
    private static function parseCustomElement(array $def, string $attrStr, string $inner): array
    {
        $a   = self::attrs($attrStr);
        $vis = self::visibilityFromAttrs($a);
        $settings = ['visibility' => $vis];

        $list = self::ceFieldList($def);
        if (!empty($list)) {
            foreach ($list as $f) {
                $k = $f['key'];
                $t = $f['type'];
                if ($t === 'repeater') {
                    $settings[$k] = self::parseRepeaterChildren($inner, $k);
                } elseif ($t === 'checkbox' || $t === 'multiselect' || $t === 'multi_select') {
                    $settings[$k] = isset($a[$k]) ? array_values(array_filter(explode(',', (string) $a[$k]), fn($x) => $x !== '')) : (is_array($f['value']) ? $f['value'] : []);
                } elseif ($t === 'toggle') {
                    $settings[$k] = isset($a[$k]) ? in_array($a[$k], ['1', 'yes', 'true'], true) : (bool) $f['value'];
                } elseif ($t === 'dimensions' || $t === 'typography') {
                    foreach ($a as $ak => $av) {
                        if (str_starts_with($ak, $k . '_')) $settings[$ak] = self::maybeNum($av);
                    }
                } elseif ($t === 'number' || $t === 'slider') {
                    $settings[$k] = isset($a[$k]) ? self::num($a[$k]) : ($f['value'] ?? '');
                } else {
                    $settings[$k] = $a[$k] ?? ($f['value'] ?? '');
                }
                if (isset($a[$k . '_target']))  $settings[$k . '_target']  = $a[$k . '_target'];
                if (isset($a[$k . '_dynamic'])) $settings[$k . '_dynamic'] = $a[$k . '_dynamic'];
                if (isset($a[$k . '_url']))     $settings[$k . '_url']     = $a[$k . '_url'];
            }
            // Restore global keys (Extra tab: css, animation, conditional visibility) + whitelisted keys
            $extraKeys = is_array($def['shortcode_keys'] ?? null) ? $def['shortcode_keys'] : [];
            foreach ($a as $ak => $av) {
                if ($ak === 'id') continue;
                if ((in_array($ak, self::CE_GLOBAL_KEYS, true) || in_array($ak, $extraKeys, true)) && !isset($settings[$ak])) {
                    $settings[$ak] = self::maybeNum($av);
                }
            }
        } else {
            foreach ($a as $k => $v) {
                if (!in_array($k, ['id', 'hide_mobile', 'hide_tablet', 'hide_desktop'], true)) $settings[$k] = $v;
            }
        }

        $el    = ['id' => $a['id'] ?? self::uid(), 'type' => $def['type'] ?? ($def['shortcode'] ?? 'custom'), 'settings' => $settings];
        // Merge back any readable extra attribute the custom serializer didn't itself emit (lossless round-trip).
        $names = self::perTypeNames('element', $el);
        $el['settings'] = self::mergeExtras($a, $settings, $names);
        return $el;
    }

    /** Parse repeater child shortcodes [lzr_<key> sub="..." /] from element inner content. */
    private static function parseRepeaterChildren(string $inner, string $rkey): array
    {
        $rows = [];
        if ($inner === '') return $rows;
        $childTag = 'lzr_' . $rkey;
        $rx = '/\[' . preg_quote($childTag, '/') . '([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/' . preg_quote($childTag, '/') . '\])/';
        if (preg_match_all($rx, $inner, $m, PREG_SET_ORDER)) {
            foreach ($m as $cm) {
                $ra  = self::attrs($cm[1]);
                $row = [];
                foreach ($ra as $k => $v) $row[$k] = self::maybeNum($v);
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private static function parseElement(string $type, string $attrStr, string $inner): ?array
    {
        $el = self::parseElementInner($type, $attrStr, $inner);
        if (is_array($el)) {
            $a        = self::attrs($attrStr);
            $settings = $el['settings'] ?? [];
            $names    = self::perTypeNames('element', $el);
            $el['settings'] = self::mergeExtras($a, $settings, $names);
        }
        return $el;
    }

    private static function parseElementInner(string $type, string $attrStr, string $inner): ?array
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
                    'marginTopUnit'           => $a['margin_top_unit']           ?? 'px',
                    'marginTopUnit_tablet'    => $a['margin_top_unit_tablet']    ?? null,
                    'marginTopUnit_mobile'    => $a['margin_top_unit_mobile']    ?? null,
                    'marginRightUnit'         => $a['margin_right_unit']         ?? 'px',
                    'marginRightUnit_tablet'  => $a['margin_right_unit_tablet']  ?? null,
                    'marginRightUnit_mobile'  => $a['margin_right_unit_mobile']  ?? null,
                    'marginBottomUnit'        => $a['margin_bottom_unit']        ?? 'px',
                    'marginBottomUnit_tablet' => $a['margin_bottom_unit_tablet'] ?? null,
                    'marginBottomUnit_mobile' => $a['margin_bottom_unit_mobile'] ?? null,
                    'marginLeftUnit'          => $a['margin_left_unit']          ?? 'px',
                    'marginLeftUnit_tablet'   => $a['margin_left_unit_tablet']   ?? null,
                    'marginLeftUnit_mobile'   => $a['margin_left_unit_mobile']   ?? null,
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
                    'marginTop'        => self::num($a['margin_top']    ?? 10),
                    'marginRight'      => self::num($a['margin_right']  ?? 0),
                    'marginBottom'     => self::num($a['margin_bottom'] ?? 10),
                    'marginLeft'       => self::num($a['margin_left']   ?? 0),
                    'marginTopUnit'           => $a['margin_top_unit']           ?? 'px',
                    'marginTopUnit_tablet'    => $a['margin_top_unit_tablet']    ?? null,
                    'marginTopUnit_mobile'    => $a['margin_top_unit_mobile']    ?? null,
                    'marginRightUnit'         => $a['margin_right_unit']         ?? 'px',
                    'marginRightUnit_tablet'  => $a['margin_right_unit_tablet']  ?? null,
                    'marginRightUnit_mobile'  => $a['margin_right_unit_mobile']  ?? null,
                    'marginBottomUnit'        => $a['margin_bottom_unit']        ?? 'px',
                    'marginBottomUnit_tablet' => $a['margin_bottom_unit_tablet'] ?? null,
                    'marginBottomUnit_mobile' => $a['margin_bottom_unit_mobile'] ?? null,
                    'marginLeftUnit'          => $a['margin_left_unit']          ?? 'px',
                    'marginLeftUnit_tablet'   => $a['margin_left_unit_tablet']   ?? null,
                    'marginLeftUnit_mobile'   => $a['margin_left_unit_mobile']   ?? null,
                    // Padding
                    'paddingTop'        => self::num($a['padding_top']    ?? 12),
                    'paddingRight'      => self::num($a['padding_right']  ?? 30),
                    'paddingBottom'     => self::num($a['padding_bottom'] ?? 12),
                    'paddingLeft'       => self::num($a['padding_left']   ?? 30),
                    'paddingTopUnit'           => $a['padding_top_unit']           ?? 'px',
                    'paddingTopUnit_tablet'    => $a['padding_top_unit_tablet']    ?? null,
                    'paddingTopUnit_mobile'    => $a['padding_top_unit_mobile']    ?? null,
                    'paddingRightUnit'         => $a['padding_right_unit']         ?? 'px',
                    'paddingRightUnit_tablet'  => $a['padding_right_unit_tablet']  ?? null,
                    'paddingRightUnit_mobile'  => $a['padding_right_unit_mobile']  ?? null,
                    'paddingBottomUnit'        => $a['padding_bottom_unit']        ?? 'px',
                    'paddingBottomUnit_tablet' => $a['padding_bottom_unit_tablet'] ?? null,
                    'paddingBottomUnit_mobile' => $a['padding_bottom_unit_mobile'] ?? null,
                    'paddingLeftUnit'          => $a['padding_left_unit']          ?? 'px',
                    'paddingLeftUnit_tablet'   => $a['padding_left_unit_tablet']   ?? null,
                    'paddingLeftUnit_mobile'   => $a['padding_left_unit_mobile']   ?? null,
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

            case 'image': {
                $ts = [
                    // Source
                    'url'           => $a['url']  ?? $a['src'] ?? '',
                    'alt'           => $a['alt']  ?? '',
                    // Link
                    'linkUrl'       => $a['link_url']    ?? '',
                    'linkTarget'    => $a['link_target'] ?? '_self',
                    // Alignment
                    'textAlign'     => $a['align']        ?? 'center',
                    // Dimensions
                    'width'           => $a['width']             ?? null,
                    'widthUnit'       => $a['width_unit']        ?? 'px',
                    'maxWidth'        => $a['max_width']         ?? null,
                    'maxWidthUnit'    => $a['max_width_unit']    ?? 'px',
                    'stickyWidth'     => $a['sticky_width']      ?? null,
                    'stickyWidthUnit' => $a['sticky_width_unit'] ?? 'px',
                    // Margin
                    'marginTop'        => isset($a['margin_top'])    ? (int)$a['margin_top']    : null,
                    'marginRight'      => isset($a['margin_right'])  ? (int)$a['margin_right']  : null,
                    'marginBottom'     => isset($a['margin_bottom']) ? (int)$a['margin_bottom'] : null,
                    'marginLeft'       => isset($a['margin_left'])   ? (int)$a['margin_left']   : null,
                    'marginTopUnit'           => $a['margin_top_unit']           ?? 'px',
                    'marginTopUnit_tablet'    => $a['margin_top_unit_tablet']    ?? null,
                    'marginTopUnit_mobile'    => $a['margin_top_unit_mobile']    ?? null,
                    'marginRightUnit'         => $a['margin_right_unit']         ?? 'px',
                    'marginRightUnit_tablet'  => $a['margin_right_unit_tablet']  ?? null,
                    'marginRightUnit_mobile'  => $a['margin_right_unit_mobile']  ?? null,
                    'marginBottomUnit'        => $a['margin_bottom_unit']        ?? 'px',
                    'marginBottomUnit_tablet' => $a['margin_bottom_unit_tablet'] ?? null,
                    'marginBottomUnit_mobile' => $a['margin_bottom_unit_mobile'] ?? null,
                    'marginLeftUnit'          => $a['margin_left_unit']          ?? 'px',
                    'marginLeftUnit_tablet'   => $a['margin_left_unit_tablet']   ?? null,
                    'marginLeftUnit_mobile'   => $a['margin_left_unit_mobile']   ?? null,
                    // Border
                    'borderRadius'     => $a['border_radius']      ?? null,
                    'borderRadiusUnit' => $a['border_radius_unit'] ?? 'px',
                    'borderSizeTop'    => isset($a['border_top'])    ? (int)$a['border_top']    : null,
                    'borderSizeRight'  => isset($a['border_right'])  ? (int)$a['border_right']  : null,
                    'borderSizeBottom' => isset($a['border_bottom']) ? (int)$a['border_bottom'] : null,
                    'borderSizeLeft'   => isset($a['border_left'])   ? (int)$a['border_left']   : null,
                    'borderColor'      => $a['border_color'] ?? null,
                    // Hover
                    'hoverType'   => $a['hover_type']   ?? 'none',
                    'aspectRatio' => $a['aspect_ratio'] ?? 'none',
                    'focusX'      => isset($a['focus_x']) ? (int)$a['focus_x'] : 50,
                    'focusY'      => isset($a['focus_y']) ? (int)$a['focus_y'] : 50,
                    // CSS
                    'cssClass'      => $a['css_class'] ?? null,
                    'cssId'         => $a['css_id']    ?? null,
                    'visibility'    => $vis,
                ];
                self::addRespProps($ts, $a, [
                    ['textAlign',    'align',         'str'],
                    ['marginTop',    'margin_top',    'num'],
                    ['marginRight',  'margin_right',  'num'],
                    ['marginBottom', 'margin_bottom', 'num'],
                    ['marginLeft',   'margin_left',   'num'],
                ]);
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'image', 'settings' => $ts];
            }

            case 'spacer':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'spacer', 'settings' => [
                    'style'              => $a['style']              ?? 'default',
                    'flexGrow'           => isset($a['flex_grow'])        ? (int)$a['flex_grow']        : 0,
                    'marginTop'          => isset($a['margin_top'])        ? (int)$a['margin_top']        : 0,
                    'marginTopUnit'      => $a['margin_top_unit']      ?? 'px',
                    'marginBottom'       => isset($a['margin_bottom'])     ? (int)$a['margin_bottom']     : 0,
                    'marginBottomUnit'   => $a['margin_bottom_unit']   ?? 'px',
                    'separatorWidth'     => isset($a['separator_width'])   ? (int)$a['separator_width']   : 100,
                    'separatorWidthUnit' => $a['separator_width_unit'] ?? '%',
                    'alignment'          => $a['alignment']           ?? 'center',
                    'borderSize'         => isset($a['border_size'])       ? (int)$a['border_size']       : 1,
                    'separatorColor'     => $a['separator_color']     ?? '#cccccc',
                    'cssClass'           => $a['css_class']           ?? null,
                    'cssId'              => $a['css_id']              ?? null,
                    'visibility'         => $vis,
                ]];

            case 'html':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'html', 'settings' => [
                    'htmlContent'     => $inner,
                    'marginTop'       => isset($a['margin_top'])    ? (int)$a['margin_top']    : 0,
                    'marginTopUnit'   => $a['margin_top_unit']      ?? 'px',
                    'marginBottom'    => isset($a['margin_bottom']) ? (int)$a['margin_bottom'] : 0,
                    'marginBottomUnit'=> $a['margin_bottom_unit']   ?? 'px',
                    'cssClass'        => $a['css_class']            ?? '',
                    'cssId'           => $a['css_id']               ?? '',
                    'visibility'      => $vis,
                ]];

            case 'icon_box':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'icon_box', 'settings' => [
                    'icon'             => $a['icon']               ?? 'fas fa-star',
                    'title'            => htmlspecialchars_decode($a['title'] ?? '', ENT_QUOTES),
                    'description'      => $inner,
                    'linkUrl'          => $a['link_url']           ?? '',
                    'linkTarget'       => $a['link_target']        ?? '_self',
                    'layout'           => $a['layout']             ?? 'top',
                    'alignment'        => $a['alignment']          ?? 'center',
                    'iconSize'         => isset($a['icon_size'])         ? (int)$a['icon_size']         : 40,
                    'iconSizeUnit'     => $a['icon_size_unit']           ?? 'px',
                    'iconColor'        => $a['icon_color']               ?? '#0091ea',
                    'iconBgColor'      => $a['icon_bg_color']            ?? '',
                    'iconBgColorOpacity' => isset($a['icon_bg_opacity']) ? (float)$a['icon_bg_opacity'] : 1,
                    'iconBorderRadius' => isset($a['icon_border_radius']) ? (int)$a['icon_border_radius'] : 50,
                    'iconSpacing'      => isset($a['icon_spacing'])      ? (int)$a['icon_spacing']      : 16,
                    'iconPadding'      => isset($a['icon_padding'])      ? (int)$a['icon_padding']      : 0,
                    'titleFontFamily'  => $a['title_font_family']        ?? 'inherit',
                    'titleTag'         => $a['title_tag']                ?? 'h3',
                    'titleFontSize'    => isset($a['title_font_size'])   ? (int)$a['title_font_size']   : 20,
                    'titleFontSizeUnit'=> $a['title_font_size_unit']     ?? 'px',
                    'titleFontWeight'  => $a['title_font_weight']        ?? '600',
                    'titleColor'       => $a['title_color']              ?? '#222222',
                    'titleSpacing'     => isset($a['title_spacing'])     ? (int)$a['title_spacing']     : 8,
                    'titleLineHeight'  => isset($a['title_line_height']) ? (float)$a['title_line_height'] : 1.3,
                    'titleLetterSpacing' => $a['title_letter_spacing']   ?? '0px',
                    'titleTextTransform' => $a['title_text_transform']   ?? 'none',
                    'descFontFamily'   => $a['desc_font_family']         ?? 'inherit',
                    'descFontSize'     => isset($a['desc_font_size'])    ? (int)$a['desc_font_size']    : 14,
                    'descFontSizeUnit' => $a['desc_font_size_unit']      ?? 'px',
                    'descFontWeight'   => $a['desc_font_weight']         ?? '400',
                    'descColor'        => $a['desc_color']               ?? '#666666',
                    'descLineHeight'   => isset($a['desc_line_height'])  ? (float)$a['desc_line_height'] : 1.6,
                    'descLetterSpacing' => $a['desc_letter_spacing']     ?? '0px',
                    'descTextTransform' => $a['desc_text_transform']     ?? 'none',
                    'marginTop'        => isset($a['margin_top'])        ? (int)$a['margin_top']        : 0,
                    'marginTopUnit'    => $a['margin_top_unit']          ?? 'px',
                    'marginBottom'     => isset($a['margin_bottom'])     ? (int)$a['margin_bottom']     : 0,
                    'marginBottomUnit' => $a['margin_bottom_unit']       ?? 'px',
                    'cssClass'         => $a['css_class']                ?? '',
                    'cssId'            => $a['css_id']                   ?? '',
                    'visibility'       => $vis,
                ]];

            case 'video':
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'video', 'settings' => [
                    'url'              => $a['url']          ?? '',
                    'videoSource'      => $a['video_source'] ?? 'youtube',
                    'aspectRatio'      => $a['aspect_ratio'] ?? '16-9',
                    'autoplay'         => ($a['autoplay']    ?? '0') === '1',
                    'muted'            => ($a['muted']       ?? '0') === '1',
                    'loop'             => ($a['loop']        ?? '0') === '1',
                    'controls'         => ($a['controls']    ?? '1') !== '0',
                    'marginTop'        => (int)($a['margin_top']    ?? 0),
                    'marginTopUnit'    => $a['margin_top_unit']    ?? 'px',
                    'marginBottom'     => (int)($a['margin_bottom'] ?? 0),
                    'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
                    'cssClass'         => $a['css_class'] ?? '',
                    'cssId'            => $a['css_id']    ?? '',
                    'visibility'       => $vis,
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
                    'marginTop'        => self::num($a['margin_top']    ?? 0),
                    'marginRight'      => self::num($a['margin_right']  ?? 0),
                    'marginBottom'     => self::num($a['margin_bottom'] ?? 0),
                    'marginLeft'       => self::num($a['margin_left']   ?? 0),
                    'marginTopUnit'           => $a['margin_top_unit']           ?? 'px',
                    'marginTopUnit_tablet'    => $a['margin_top_unit_tablet']    ?? null,
                    'marginTopUnit_mobile'    => $a['margin_top_unit_mobile']    ?? null,
                    'marginRightUnit'         => $a['margin_right_unit']         ?? 'px',
                    'marginRightUnit_tablet'  => $a['margin_right_unit_tablet']  ?? null,
                    'marginRightUnit_mobile'  => $a['margin_right_unit_mobile']  ?? null,
                    'marginBottomUnit'        => $a['margin_bottom_unit']        ?? 'px',
                    'marginBottomUnit_tablet' => $a['margin_bottom_unit_tablet'] ?? null,
                    'marginBottomUnit_mobile' => $a['margin_bottom_unit_mobile'] ?? null,
                    'marginLeftUnit'          => $a['margin_left_unit']          ?? 'px',
                    'marginLeftUnit_tablet'   => $a['margin_left_unit_tablet']   ?? null,
                    'marginLeftUnit_mobile'   => $a['margin_left_unit_mobile']   ?? null,
                    // Padding
                    'paddingTop'        => self::num($a['padding_top']    ?? 10),
                    'paddingRight'      => self::num($a['padding_right']  ?? 0),
                    'paddingBottom'     => self::num($a['padding_bottom'] ?? 10),
                    'paddingLeft'       => self::num($a['padding_left']   ?? 0),
                    'paddingTopUnit'           => $a['padding_top_unit']           ?? 'px',
                    'paddingTopUnit_tablet'    => $a['padding_top_unit_tablet']    ?? null,
                    'paddingTopUnit_mobile'    => $a['padding_top_unit_mobile']    ?? null,
                    'paddingRightUnit'         => $a['padding_right_unit']         ?? 'px',
                    'paddingRightUnit_tablet'  => $a['padding_right_unit_tablet']  ?? null,
                    'paddingRightUnit_mobile'  => $a['padding_right_unit_mobile']  ?? null,
                    'paddingBottomUnit'        => $a['padding_bottom_unit']        ?? 'px',
                    'paddingBottomUnit_tablet' => $a['padding_bottom_unit_tablet'] ?? null,
                    'paddingBottomUnit_mobile' => $a['padding_bottom_unit_mobile'] ?? null,
                    'paddingLeftUnit'          => $a['padding_left_unit']          ?? 'px',
                    'paddingLeftUnit_tablet'   => $a['padding_left_unit_tablet']   ?? null,
                    'paddingLeftUnit_mobile'   => $a['padding_left_unit_mobile']   ?? null,
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
                    'menuIconPosition'   => $a['icon_position']        ?? 'left',
                    'menuIconGap'        => isset($a['icon_gap']) ? (int)$a['icon_gap'] : null,
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

            case 'card': {
                $statusRaw = $a['post_status'] ?? 'publish';
                $settings = [
                    'post_card_id'          => $a['post_card_id']          ?? '',
                    'content_source'        => $a['content_source']        ?? 'posts',
                    'post_type'             => $a['post_type']             ?? 'post',
                    'posts_by'              => $a['posts_by']              ?? 'all',
                    'posts_by_value'        => $a['posts_by_value']        ?? '',
                    'posts_by_cf_key'       => $a['posts_by_cf_key']       ?? '',
                    'posts_by_cf_value'     => $a['posts_by_cf_value']     ?? '',
                    'post_status'           => array_values(array_filter(array_map('trim', explode(',', $statusRaw)))),
                    'hide_out_of_stock'     => ($a['hide_out_of_stock']    ?? '') === 'yes',
                    'posts_count'           => (int)($a['posts_count']     ?? 6),
                    'posts_offset'          => (int)($a['posts_offset']    ?? 0),
                    'order_by'              => $a['order_by']              ?? 'created_at',
                    'order'                 => $a['order']                 ?? 'desc',
                    'pagination_type'       => $a['pagination_type']       ?? 'none',
                    'nothing_found_message' => $a['nothing_found_message'] ?? 'No posts found.',
                    'layout'                => $a['layout']                ?? 'grid',
                    'card_alignment'        => $a['card_alignment']        ?? 'left',
                    'columns'               => (int)($a['columns']         ?? 3),
                    'columns_tablet'        => (int)($a['columns_tablet']  ?? 2),
                    'columns_mobile'        => (int)($a['columns_mobile']  ?? 1),
                    'column_spacing'        => (int)($a['column_spacing']  ?? 24),
                    'row_spacing'           => (int)($a['row_spacing']     ?? 24),
                    'marginTop'             => self::num($a['margin_top']        ?? 0),
                    'marginTopUnit'         => $a['margin_top_unit']             ?? 'px',
                    'marginRight'           => self::num($a['margin_right']      ?? 0),
                    'marginRightUnit'       => $a['margin_right_unit']           ?? 'px',
                    'marginBottom'          => self::num($a['margin_bottom']     ?? 0),
                    'marginBottomUnit'      => $a['margin_bottom_unit']          ?? 'px',
                    'marginLeft'            => self::num($a['margin_left']       ?? 0),
                    'marginLeftUnit'        => $a['margin_left_unit']            ?? 'px',
                    'cssClass'              => $a['css_class']                   ?? '',
                    'cssId'                 => $a['css_id']                      ?? '',
                    'taxonomy_slug'         => $a['taxonomy_slug']               ?? '',
                    'taxonomy_include'      => array_values(array_filter(explode(',', trim($a['taxonomy_include'] ?? '')))),
                    'taxonomy_exclude'      => array_values(array_filter(explode(',', trim($a['taxonomy_exclude'] ?? '')))),
                    'carousel_autoplay'       => ($a['carousel_autoplay']       ?? '') === 'yes',
                    'carousel_autoplay_speed' => (int)($a['carousel_autoplay_speed'] ?? 3000),
                    'carousel_arrows'         => ($a['carousel_arrows']         ?? 'yes') !== 'no',
                    'carousel_dots'           => ($a['carousel_dots']           ?? 'yes') !== 'no',
                    'carousel_loop'           => ($a['carousel_loop']           ?? '') === 'yes',
                    'items_per_slide'         => max(1, (int)($a['items_per_slide']        ?? 1)),
                    'items_per_slide_tablet'  => max(0, (int)($a['items_per_slide_tablet'] ?? 0)),
                    'items_per_slide_mobile'  => max(0, (int)($a['items_per_slide_mobile'] ?? 0)),
                    'visibility'            => $vis,
                ];
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'card', 'settings' => $settings];
            }

            case 'accordion': {
                $items = [];
                if (preg_match_all('/\[falcon_acc_item([^\]]*)\](.*?)\[\/(?:falcon|lazy)_acc_item\]/s', $inner, $im, PREG_SET_ORDER)) {
                    foreach ($im as $imatch) {
                        $ia = self::attrs($imatch[1]);
                        $items[] = [
                            'id'      => $ia['id'] ?? self::uid(),
                            'title'   => htmlspecialchars_decode($ia['title'] ?? '', ENT_QUOTES),
                            'content' => trim($imatch[2]),
                        ];
                    }
                }
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'accordion', 'settings' => [
                    'items'              => $items,
                    'defaultOpen'        => isset($a['default_open'])         ? (int)$a['default_open']         : 0,
                    'allowMultiple'      => ($a['allow_multiple']             ?? '') === 'yes',
                    'iconType'           => $a['icon_type']                   ?? 'plus',
                    'iconPosition'       => $a['icon_position']               ?? 'right',
                    'titleFontSize'        => isset($a['title_font_size'])        ? (int)$a['title_font_size']      : 15,
                    'titleFontWeight'      => $a['title_font_weight']           ?? '600',
                    'titleFontFamily'      => $a['title_font_family']           ?? 'inherit',
                    'titleLetterSpacing'   => $a['title_letter_spacing']        ?? '0px',
                    'titleLineHeight'      => isset($a['title_line_height'])    ? (float)$a['title_line_height'] : 1.4,
                    'titleTextTransform'   => $a['title_text_transform']        ?? 'none',
                    'titleColor'           => $a['title_color']                 ?? '#222222',
                    'titleBgColor'         => $a['title_bg_color']              ?? '#f8fafc',
                    'titleActiveBgColor'   => $a['title_active_bg_color']       ?? '#0091ea',
                    'titleActiveColor'     => $a['title_active_color']          ?? '#ffffff',
                    'titlePadding'         => isset($a['title_padding'])        ? (int)$a['title_padding']        : 16,
                    'contentFontSize'      => isset($a['content_font_size'])    ? (int)$a['content_font_size']    : 14,
                    'contentFontFamily'    => $a['content_font_family']         ?? 'inherit',
                    'contentLetterSpacing' => $a['content_letter_spacing']      ?? '0px',
                    'contentLineHeight'    => isset($a['content_line_height'])  ? (float)$a['content_line_height'] : 1.6,
                    'contentColor'         => $a['content_color']               ?? '#555555',
                    'contentBgColor'       => $a['content_bg_color']            ?? '#ffffff',
                    'contentPadding'       => isset($a['content_padding'])      ? (int)$a['content_padding']      : 16,
                    'borderColor'        => $a['border_color']                ?? '#e2e8f0',
                    'borderRadius'       => isset($a['border_radius'])        ? (int)$a['border_radius']        : 8,
                    'itemGap'            => isset($a['item_gap'])             ? (int)$a['item_gap']             : 8,
                    'marginTop'          => isset($a['margin_top'])           ? (int)$a['margin_top']           : 0,
                    'marginTopUnit'      => $a['margin_top_unit']             ?? 'px',
                    'marginBottom'       => isset($a['margin_bottom'])        ? (int)$a['margin_bottom']        : 0,
                    'marginBottomUnit'   => $a['margin_bottom_unit']          ?? 'px',
                    'cssClass'           => $a['css_class']                   ?? '',
                    'cssId'              => $a['css_id']                      ?? '',
                    'visibility'         => $vis,
                ]];
            }

            case 'tabs': {
                $items = [];
                if (preg_match_all('/\[falcon_tab_item([^\]]*)\](.*?)\[\/(?:falcon|lazy)_tab_item\]/s', $inner, $im, PREG_SET_ORDER)) {
                    foreach ($im as $imatch) {
                        $ia = self::attrs($imatch[1]);
                        $items[] = [
                            'id'      => $ia['id'] ?? self::uid(),
                            'label'   => htmlspecialchars_decode($ia['label'] ?? '', ENT_QUOTES),
                            'content' => trim($imatch[2]),
                        ];
                    }
                }
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'tabs', 'settings' => [
                    'items'          => $items,
                    'defaultActive'  => isset($a['default_active'])   ? (int)$a['default_active']   : 0,
                    'style'          => $a['style']                   ?? 'underline',
                    'alignment'      => $a['alignment']               ?? 'left',
                    'tabFontSize'          => isset($a['tab_font_size'])        ? (int)$a['tab_font_size']        : 14,
                    'tabFontWeight'        => $a['tab_font_weight']             ?? '500',
                    'tabFontFamily'        => $a['tab_font_family']             ?? 'inherit',
                    'tabLetterSpacing'     => $a['tab_letter_spacing']          ?? '0px',
                    'tabColor'             => $a['tab_color']                   ?? '#666666',
                    'activeColor'          => $a['active_color']                ?? '#0091ea',
                    'contentFontSize'      => isset($a['content_font_size'])    ? (int)$a['content_font_size']    : 14,
                    'contentFontFamily'    => $a['content_font_family']         ?? 'inherit',
                    'contentLetterSpacing' => $a['content_letter_spacing']      ?? '0px',
                    'contentLineHeight'    => isset($a['content_line_height'])  ? (float)$a['content_line_height'] : 1.6,
                    'contentColor'         => $a['content_color']               ?? '#555555',
                    'contentBgColor'       => $a['content_bg_color']            ?? '#ffffff',
                    'contentPadding'       => isset($a['content_padding'])      ? (int)$a['content_padding']      : 20,
                    'borderColor'    => $a['border_color']            ?? '#e2e8f0',
                    'borderRadius'   => isset($a['border_radius'])    ? (int)$a['border_radius']    : 4,
                    'marginTop'      => isset($a['margin_top'])       ? (int)$a['margin_top']       : 0,
                    'marginTopUnit'  => $a['margin_top_unit']         ?? 'px',
                    'marginBottom'   => isset($a['margin_bottom'])    ? (int)$a['margin_bottom']    : 0,
                    'marginBottomUnit'=> $a['margin_bottom_unit']     ?? 'px',
                    'cssClass'       => $a['css_class']               ?? '',
                    'cssId'          => $a['css_id']                  ?? '',
                    'visibility'     => $vis,
                ]];
            }

            case 'icon_list': {
                $items = [];
                if (preg_match_all('/\[falcon_icon_list_item([^\]]*)\](.*?)\[\/(?:falcon|lazy)_icon_list_item\]/s', $inner, $im, PREG_SET_ORDER)) {
                    foreach ($im as $imatch) {
                        $ia = self::attrs($imatch[1]);
                        $items[] = [
                            'id'         => $ia['id'] ?? self::uid(),
                            'icon'       => $ia['icon']        ?? 'fa fa-check',
                            'iconColor'  => $ia['icon_color']  ?? '',
                            'text'       => htmlspecialchars_decode($imatch[2], ENT_QUOTES),
                            'link'       => $ia['link']        ?? '',
                            'linkTarget' => $ia['link_target'] ?? '_self',
                        ];
                    }
                }
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'icon_list', 'settings' => [
                    'items'          => $items,
                    'defaultIcon'    => $a['default_icon']       ?? 'fa fa-check',
                    'iconSize'       => isset($a['icon_size'])   ? (int)$a['icon_size']   : 14,
                    'iconColor'      => $a['icon_color']         ?? '#0091ea',
                    'iconPosition'   => $a['icon_position']      ?? 'left',
                    'gap'            => isset($a['gap'])         ? (int)$a['gap']         : 10,
                    'itemSpacing'    => isset($a['item_spacing']) ? (int)$a['item_spacing'] : 10,
                    'textAlign'      => $a['text_align']         ?? 'left',
                    'textColor'      => $a['text_color']         ?? '#333333',
                    'fontSize'       => isset($a['font_size'])   ? (int)$a['font_size']   : 15,
                    'fontSizeUnit'   => $a['font_size_unit']     ?? 'px',
                    'fontWeight'     => $a['font_weight']        ?? '400',
                    'fontFamily'     => $a['font_family']        ?? 'inherit',
                    'lineHeight'     => $a['line_height']        ?? '1.5',
                    'marginTop'      => isset($a['margin_top'])  ? (int)$a['margin_top']  : 0,
                    'marginTopUnit'  => $a['margin_top_unit']    ?? 'px',
                    'marginBottom'   => isset($a['margin_bottom']) ? (int)$a['margin_bottom'] : 0,
                    'marginBottomUnit'=> $a['margin_bottom_unit'] ?? 'px',
                    'cssClass'       => $a['css_class']          ?? '',
                    'cssId'          => $a['css_id']             ?? '',
                    'visibility'     => $vis,
                ]];
            }

            case 'counter': {
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'counter', 'settings' => [
                    'endValue'           => isset($a['end'])        ? self::num($a['end'])        : 100,
                    'startValue'         => isset($a['start'])      ? self::num($a['start'])      : 0,
                    'prefix'             => $a['prefix']            ?? '',
                    'suffix'             => $a['suffix']            ?? '',
                    'label'              => $a['label']             ?? '',
                    'duration'           => isset($a['dur'])        ? (int)$a['dur']              : 2000,
                    'decimals'           => isset($a['dec'])        ? (int)$a['dec']              : 0,
                    'separator'          => $a['sep']               ?? '',
                    'textAlign'          => $a['align']             ?? 'center',
                    'numberFontSize'     => $a['num_size']          ?? '48px',
                    'numberFontWeight'   => $a['num_weight']        ?? '700',
                    'numberColor'        => $a['num_color']         ?? '#222222',
                    'numberFontFamily'   => $a['num_family']        ?? 'inherit',
                    'numberLineHeight'   => $a['num_lh']            ?? '1.1',
                    'numberLetterSpacing'=> $a['num_ls']            ?? '0px',
                    'labelFontSize'      => $a['lbl_size']          ?? '14px',
                    'labelFontWeight'    => $a['lbl_weight']        ?? '400',
                    'labelColor'         => $a['lbl_color']         ?? '#666666',
                    'labelFontFamily'    => $a['lbl_family']        ?? 'inherit',
                    'labelLineHeight'    => $a['lbl_lh']            ?? '1.4',
                    'labelLetterSpacing' => $a['lbl_ls']            ?? '0px',
                    'labelTextTransform' => $a['lbl_tt']            ?? 'none',
                    'icon'               => $a['icon']              ?? '',
                    'iconSize'           => isset($a['icon_size'])  ? (int)$a['icon_size']        : 40,
                    'iconColor'          => $a['icon_color']        ?? '#0091ea',
                    'marginTop'          => isset($a['mt'])         ? self::num($a['mt'])         : 0,
                    'marginTopUnit'      => $a['mt_unit']           ?? 'px',
                    'marginBottom'       => isset($a['mb'])         ? self::num($a['mb'])         : 0,
                    'marginBottomUnit'   => $a['mb_unit']           ?? 'px',
                    'cssClass'           => $a['css_class']         ?? '',
                    'cssId'              => $a['css_id']            ?? '',
                    'visibility'         => $vis,
                ]];
            }

            case 'star_rating': {
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'star_rating', 'settings' => [
                    'rating'             => isset($a['rating'])     ? (float)$a['rating']     : 5,
                    'maxStars'           => isset($a['max'])        ? (int)$a['max']          : 5,
                    'label'              => $a['label']             ?? '',
                    'starSize'           => isset($a['size'])       ? (int)$a['size']         : 24,
                    'starColor'          => $a['color']             ?? '#f59e0b',
                    'emptyColor'         => $a['empty']             ?? '#d1d5db',
                    'textAlign'          => $a['align']             ?? 'center',
                    'gap'                => isset($a['gap'])        ? (int)$a['gap']          : 4,
                    'labelFontFamily'    => $a['lbl_family']        ?? 'inherit',
                    'labelFontSize'      => $a['lbl_size']          ?? '13px',
                    'labelFontWeight'    => $a['lbl_weight']        ?? '400',
                    'labelLineHeight'    => $a['lbl_lh']            ?? '1.4',
                    'labelLetterSpacing' => $a['lbl_ls']            ?? '0px',
                    'labelTextTransform' => $a['lbl_tt']            ?? 'none',
                    'labelColor'         => $a['lbl_color']         ?? '#6b7280',
                    'marginTop'          => isset($a['mt'])         ? self::num($a['mt'])     : 0,
                    'marginTopUnit'      => $a['mt_unit']           ?? 'px',
                    'marginBottom'       => isset($a['mb'])         ? self::num($a['mb'])     : 0,
                    'marginBottomUnit'   => $a['mb_unit']           ?? 'px',
                    'cssClass'           => $a['css_class']         ?? '',
                    'cssId'              => $a['css_id']            ?? '',
                    'visibility'         => $vis,
                ]];
            }

            case 'gallery': {
                $n = isset($a['img_n']) ? (int)$a['img_n'] : 0;
                $imgs = [];
                for ($i = 0; $i < $n; $i++) {
                    $imgs[] = [
                        'url'     => $a['img_' . $i]         ?? '',
                        'alt'     => $a['img_' . $i . '_a']  ?? '',
                        'caption' => $a['img_' . $i . '_c']  ?? '',
                    ];
                }
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'gallery', 'settings' => [
                    'images'          => $imgs,
                    'columns'         => isset($a['cols'])    ? (int)$a['cols']    : 3,
                    'columnsTablet'   => isset($a['cols_t'])  ? (int)$a['cols_t']  : 2,
                    'columnsMobile'   => isset($a['cols_m'])  ? (int)$a['cols_m']  : 1,
                    'gap'             => isset($a['gap'])     ? (int)$a['gap']     : 8,
                    'aspectRatio'     => $a['ratio']          ?? 'square',
                    'borderRadius'    => isset($a['radius'])  ? (int)$a['radius']  : 0,
                    'lightbox'           => ($a['lightbox']      ?? '1') !== '0',
                    'hoverEffect'        => $a['hover']          ?? 'zoom',
                    'captionAlign'       => $a['cap_align']      ?? 'center',
                    'captionFontFamily'  => $a['cap_family']     ?? 'inherit',
                    'captionFontSize'    => $a['cap_size']       ?? '13px',
                    'captionFontWeight'  => $a['cap_weight']     ?? '400',
                    'captionLineHeight'  => $a['cap_lh']         ?? '1.4',
                    'captionLetterSpacing'=> $a['cap_ls']        ?? '0px',
                    'captionTextTransform'=> $a['cap_tt']        ?? 'none',
                    'captionColor'       => $a['cap_color']      ?? '#6b7280',
                    'imgBorderWidth'     => isset($a['img_bw'])   ? (int)$a['img_bw']          : 0,
                    'imgBorderStyle'     => $a['img_bs']          ?? 'solid',
                    'imgBorderColor'     => $a['img_bc']          ?? '#e2e8f0',
                    'marginTop'          => isset($a['mt'])      ? self::num($a['mt'])  : 0,
                    'marginTopUnit'      => $a['mt_unit']        ?? 'px',
                    'marginBottom'       => isset($a['mb'])      ? self::num($a['mb'])  : 0,
                    'marginBottomUnit'   => $a['mb_unit']        ?? 'px',
                    'marginTop_tablet'    => isset($a['mt_t'])   ? self::num($a['mt_t'])   : null,
                    'marginTopUnit_tablet'=> $a['mt_t_unit']      ?? null,
                    'marginBottom_tablet' => isset($a['mb_t'])   ? self::num($a['mb_t'])   : null,
                    'marginBottomUnit_tablet' => $a['mb_t_unit'] ?? null,
                    'marginTop_mobile'    => isset($a['mt_m'])   ? self::num($a['mt_m'])   : null,
                    'marginTopUnit_mobile'=> $a['mt_m_unit']      ?? null,
                    'marginBottom_mobile' => isset($a['mb_m'])   ? self::num($a['mb_m'])   : null,
                    'marginBottomUnit_mobile' => $a['mb_m_unit'] ?? null,
                    'cssClass'           => $a['css_class']      ?? '',
                    'cssId'              => $a['css_id']         ?? '',
                    'visibility'         => $vis,
                ]];
            }

            case 'ticker': {
                $n = isset($a['item_n']) ? (int)$a['item_n'] : 0;
                $items = [];
                for ($i = 0; $i < $n; $i++) {
                    $items[] = ['text' => $a['item_' . $i] ?? '', 'url' => $a['item_' . $i . '_u'] ?? ''];
                }
                return ['id' => $a['id'] ?? self::uid(), 'type' => 'ticker', 'settings' => [
                    'items'           => $items,
                    'label'           => $a['label']     ?? '',
                    'speed'           => isset($a['speed'])   ? (int)$a['speed']   : 50,
                    'direction'       => $a['dir']       ?? 'left',
                    'pauseOnHover'    => ($a['pause']    ?? '1') !== '0',
                    'separator'       => $a['sep']       ?? '•',
                    'bgColor'         => $a['bg']        ?? '#1e3a8a',
                    'textColor'       => $a['color']     ?? '#ffffff',
                    'labelBgColor'    => $a['lbl_bg']    ?? '#ef4444',
                    'labelTextColor'  => $a['lbl_color'] ?? '#ffffff',
                    'fontSize'        => $a['fsize']     ?? '14px',
                    'fontWeight'      => $a['fweight']   ?? '500',
                    'height'          => isset($a['height'])  ? (int)$a['height']  : 44,
                    'borderRadius'    => isset($a['radius'])  ? (int)$a['radius']  : 0,
                    'marginTop'       => isset($a['mt'])      ? self::num($a['mt'])  : 0,
                    'marginTopUnit'   => $a['mt_unit']   ?? 'px',
                    'marginBottom'    => isset($a['mb'])      ? self::num($a['mb'])  : 0,
                    'marginBottomUnit'=> $a['mb_unit']   ?? 'px',
                    'cssClass'        => $a['css_class'] ?? '',
                    'cssId'           => $a['css_id']    ?? '',
                    'visibility'      => $vis,
                ]];
            }

            default: {
                $realType = $type === 'element' ? ($a['type'] ?? 'text') : $type;

                // Custom element registered via falcon_builder_elements → type-aware parse
                $cdef = self::customDefs()[$realType] ?? null;
                if ($cdef) {
                    return self::parseCustomElement($cdef, $attrStr, $inner);
                }

                // Legacy: settings stored as base64 JSON blob
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
            'bgHoverColor'        => $a['bg_hover_color'] ?? null,
            'bgHoverColorOpacity' => isset($a['bg_hover_opacity']) ? (float)$a['bg_hover_opacity'] : null,
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
            'global_id'           => $a['global_id']      ?? null,
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
            'stickyBgColor'        => $a['sticky_bg_color']         ?? '',
            'stickyBgColorOpacity' => isset($a['sticky_bg_color_opacity']) ? (float)$a['sticky_bg_color_opacity'] : 1,
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
            'paddingTop'        => self::num($a['padding_top']    ?? 0),
            'paddingBottom'     => self::num($a['padding_bottom'] ?? 0),
            'paddingLeft'       => self::num($a['padding_left']   ?? 0),
            'paddingRight'      => self::num($a['padding_right']  ?? 0),
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
            'bgHoverColor'      => $a['bg_hover_color'] ?? null,
            'bgHoverColorOpacity' => isset($a['bg_hover_opacity']) ? (float)$a['bg_hover_opacity'] : null,
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
            'stickyBgColor'        => $a['sticky_bg_color']         ?? '',
            'stickyBgColorOpacity' => isset($a['sticky_bg_color_opacity']) ? (float)$a['sticky_bg_color_opacity'] : 1,
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
