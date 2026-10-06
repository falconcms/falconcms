<?php

namespace FalconCms\Core\Services\ShortcodeConverter;

/**
 * Elements that are not core elements: the ones registered through the
 * falcon_builder_elements filter (themes, plugins, Pro), and any unknown type.
 *
 * Their fields come from the element definition rather than from code, so one generic
 * converter handles them all. toShortcode() and fromShortcode() are the two directions;
 * customDefs() is the definition registry, read once per request.
 */
final class CustomElementConverter
{
    use ConverterHelpers;

    /** @var array<string,array>|null cached custom element definitions keyed by type */
    private static $customDefs = null;

    /** Global setting keys every element shares (Extra tab: visibility cond, animation, css). Always serialized. */
    private const CE_GLOBAL_KEYS = [
        'cssClass', 'cssId',
        'anim_type', 'anim_duration', 'anim_delay', 'anim_easing',
        'vis_condition', 'vis_date_from', 'vis_date_to',
    ];

    /** Lazily resolve + cache registered custom element definitions (keyed by type). */
    public static function customDefs(): array
    {
        if (self::$customDefs !== null) {
            return self::$customDefs;
        }
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
                if (str_ends_with($first, $suf)) {
                    return $first;
                } // suffixed → first entry is the key
            }

            // bare target names → key from heading
            return !empty($p['heading']) ? trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($p['heading'])), '_') : ('cf_'.implode('_', $pn));
        }
        if (!empty($pn)) {
            return $pn;
        }
        if (!empty($p['heading'])) {
            return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($p['heading'])), '_');
        }

        return null;
    }

    /** Flat list of [key,type,value] for a custom element definition. */
    private static function ceFieldList(array $def): array
    {
        $list = [];
        if (!empty($def['params'])) {
            foreach ($def['params'] as $p) {
                $key = self::ceParamKey($p);
                if (!$key) {
                    continue;
                }
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
        if (empty($list)) {
            return null;
        }

        return array_values(array_filter(array_map(fn ($f) => $f['key'], $list)));
    }

    /** True if $key equals a base or starts with base."_" (covers _top, _size, _tablet, _unit, _target, _dynamic…). */
    private static function ceKeyAllowed(?array $bases, string $key): bool
    {
        if ($bases === null) {
            return true;
        }
        foreach ($bases as $b) {
            if ($key === $b || str_starts_with($key, $b.'_')) {
                return true;
            }
        }

        return false;
    }

    /** Parse a custom element shortcode (type-aware, mirrors JS parseCustomElement). */
    public static function parseCustomElement(array $def, string $attrStr, string $inner): array
    {
        $a = self::attrs($attrStr);
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
                    $settings[$k] = isset($a[$k]) ? array_values(array_filter(explode(',', (string) $a[$k]), fn ($x) => $x !== '')) : (is_array($f['value']) ? $f['value'] : []);
                } elseif ($t === 'toggle') {
                    $settings[$k] = isset($a[$k]) ? in_array($a[$k], ['1', 'yes', 'true'], true) : (bool) $f['value'];
                } elseif ($t === 'dimensions' || $t === 'typography') {
                    foreach ($a as $ak => $av) {
                        if (str_starts_with($ak, $k.'_')) {
                            $settings[$ak] = self::maybeNum($av);
                        }
                    }
                } elseif ($t === 'number' || $t === 'slider') {
                    $settings[$k] = isset($a[$k]) ? self::num($a[$k]) : ($f['value'] ?? '');
                } else {
                    $settings[$k] = $a[$k] ?? ($f['value'] ?? '');
                }
                if (isset($a[$k.'_target'])) {
                    $settings[$k.'_target'] = $a[$k.'_target'];
                }
                if (isset($a[$k.'_dynamic'])) {
                    $settings[$k.'_dynamic'] = $a[$k.'_dynamic'];
                }
                if (isset($a[$k.'_url'])) {
                    $settings[$k.'_url'] = $a[$k.'_url'];
                }
            }
            // Restore global keys (Extra tab: css, animation, conditional visibility) + whitelisted keys
            $extraKeys = is_array($def['shortcode_keys'] ?? null) ? $def['shortcode_keys'] : [];
            foreach ($a as $ak => $av) {
                if ($ak === 'id') {
                    continue;
                }
                if ((in_array($ak, self::CE_GLOBAL_KEYS, true) || in_array($ak, $extraKeys, true)) && !isset($settings[$ak])) {
                    $settings[$ak] = self::maybeNum($av);
                }
            }
        } else {
            foreach ($a as $k => $v) {
                if (!in_array($k, ['id', 'hide_mobile', 'hide_tablet', 'hide_desktop'], true)) {
                    $settings[$k] = $v;
                }
            }
        }

        $el = ['id' => $a['id'] ?? self::uid(), 'type' => $def['type'] ?? ($def['shortcode'] ?? 'custom'), 'settings' => $settings];
        // Merge back any readable extra attribute the custom serializer didn't itself emit (lossless round-trip).
        $names = Fidelity::perTypeNames('element', $el);
        $el['settings'] = Fidelity::mergeExtras($a, $settings, $names);

        return $el;
    }

    /** Parse repeater child shortcodes [lzr_<key> sub="..." /] from element inner content. */
    private static function parseRepeaterChildren(string $inner, string $rkey): array
    {
        $rows = [];
        if ($inner === '') {
            return $rows;
        }
        $childTag = 'lzr_'.$rkey;
        $rx = '/\['.preg_quote($childTag, '/').'([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/'.preg_quote($childTag, '/').'\])/';
        if (preg_match_all($rx, $inner, $m, PREG_SET_ORDER)) {
            foreach ($m as $cm) {
                $ra = self::attrs($cm[1]);
                $row = [];
                foreach ($ra as $k => $v) {
                    $row[$k] = self::maybeNum($v);
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public static function toShortcode(array $el, $type, string $base, $v, string $vis): string
    {
        $cdefs = self::customDefs();
        $cdef = $cdefs[$type] ?? null;
        $tag = ($cdef && !empty($cdef['shortcode'])) ? $cdef['shortcode'] : 'falcon_element';
        $bases = $cdef ? self::ceAllowedKeys($cdef) : null;
        $extraKeys = ($cdef && is_array($cdef['shortcode_keys'] ?? null)) ? $cdef['shortcode_keys'] : [];
        $repKeys = [];
        if ($cdef) {
            foreach (self::ceFieldList($cdef) as $f) {
                if (($f['type'] ?? '') === 'repeater') {
                    $repKeys[] = $f['key'];
                }
            }
        }

        $out = '['.$tag;
        if ($tag === 'falcon_element') {
            $out .= ' type="'.$type.'"';
        }
        $out .= ' '.trim($base).$vis;

        foreach ($el['settings'] ?? [] as $k => $v) {
            if ($k === 'visibility' || (is_string($k) && str_starts_with($k, '_'))) {
                continue;
            }
            if (in_array($k, $repKeys, true)) {
                continue;
            }                  // repeater → child shortcodes
            // prune keys that aren't a declared field, a global key, or a user-whitelisted key
            if ($bases !== null && !self::ceKeyAllowed($bases, $k) && !in_array($k, self::CE_GLOBAL_KEYS, true) && !in_array($k, $extraKeys, true)) {
                continue;
            }
            if ($v === null || $v === '' || $v === false) {
                continue;
            }
            if (is_bool($v)) {
                self::attrI($out, $k, '1');

                continue;
            }
            // checkbox → comma list. Repeater rows are arrays OF arrays and can't be
            // flattened that way; imploding one used to fatal the whole converter.
            if (is_array($v)) {
                foreach ($v as $vv) {
                    if (is_array($vv) || is_object($vv)) {
                        continue 2;
                    }
                }
                self::attrI($out, $k, implode(',', $v));

                continue;
            }
            if (!is_scalar($v)) {
                continue;
            }
            self::attrI($out, $k, (string) $v);
        }

        // Repeater rows → readable child shortcodes
        $children = '';
        foreach ($repKeys as $rk) {
            $rows = $el['settings'][$rk] ?? null;
            if (!is_array($rows)) {
                continue;
            }
            $childTag = 'lzr_'.$rk;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $ra = '';
                foreach ($row as $sk => $rv) {
                    if (is_string($sk) && str_starts_with($sk, '_')) {
                        continue;
                    }
                    if ($rv === null || $rv === '' || $rv === false) {
                        continue;
                    }
                    if (is_bool($rv)) {
                        self::attrI($ra, $sk, '1');

                        continue;
                    }
                    if (!is_scalar($rv)) {
                        continue;
                    }
                    self::attrI($ra, $sk, (string) $rv);
                }
                $children .= '['.$childTag.$ra.' /]';
            }
        }

        if ($children !== '') {
            return $out.']'.$children.'[/'.$tag.']';
        }

        return $out.' /]';
    }

    public static function fromShortcode(string $type, string $attrStr, string $inner, array $a, array $vis): ?array
    {
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
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        $settings['visibility'] = $vis;

        return ['id' => $a['id'] ?? self::uid(), 'type' => $realType, 'settings' => $settings];
    }
}
