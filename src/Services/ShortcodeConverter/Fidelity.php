<?php

namespace FalconCms\Core\Services\ShortcodeConverter;

use FalconCms\Core\Services\BuilderShortcodeConverter;

/**
 * Lossless round trips with readable shortcodes.
 *
 * Each converter writes only a hand-picked set of attributes. Whatever a node's settings hold
 * beyond that is appended as plain extra attributes named after the setting key
 * (appendExtras), and merged back in when the shortcode is read (mergeExtras), so an edit in
 * a rich editor never drops a field. perTypeNames() finds out which attributes a converter
 * writes by itself, by running it with the extras switched off.
 */
final class Fidelity
{
    use ConverterHelpers;

    /** When true, appendExtras() is a no-op — used to probe the bare per-type attribute names. */
    private static $suppressExtras = false;

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
        if (!preg_match('/^\s*\[[a-z0-9_]+([^\]]*)\]/i', $sc, $m)) {
            return [];
        }
        preg_match_all('/([A-Za-z_][\w-]*)\s*=\s*"/', $m[1], $mm);

        return $mm[1] ?? [];
    }

    /** Loose scalar equality (string-normalised), used to detect already-recovered values. */
    private static function looseEq($a, $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if (is_array($a) || is_array($b)) {
            return $a === $b;
        }

        return (string) $a === (string) $b;
    }

    /** Coerce a readable attribute value back to bool / number / string. */
    private static function coerceAttr(string $v)
    {
        if ($v === 'true') {
            return true;
        }
        if ($v === 'false') {
            return false;
        }
        if ($v === 'null') {
            return null;
        }
        if (is_numeric($v)) {
            // keep large id-like ints as strings only if they overflow; otherwise numeric
            if (preg_match('/^-?\d+$/', $v)) {
                $i = (int) $v;

                return ((string) $i === $v) ? $i : $v;
            }

            return (float) $v;
        }

        return $v;
    }

    /**
     * Append readable "extra" attributes for any scalar setting the per-type round-trip drops.
     * $recovered is what parsing $sc back yields, so only genuinely-lost values are emitted.
     */
    public static function appendExtras(string $sc, array $settings, array $recovered): string
    {
        if (self::$suppressExtras) {
            return $sc;
        }
        $extras = [];
        foreach ($settings as $k => $v) {
            if (self::isTransientKey($k)) {
                continue;
            }
            if ($v === null || $v === '' || is_array($v)) {
                continue;
            } // arrays are content the per-type handles
            if (array_key_exists($k, $recovered) && self::looseEq($recovered[$k], $v)) {
                continue;
            }
            $val = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
            if (strpos($val, '"') !== false) {
                continue;
            } // never break attribute quoting
            $extras[] = $k.'="'.$val.'"';
        }
        if (empty($extras)) {
            return $sc;
        }

        return preg_replace('/^(\s*\[[a-z0-9_]+\s)/i', '${1}'.implode(' ', $extras).' ', $sc, 1);
    }

    /**
     * Merge back any attribute the per-type serializer does not itself emit (and that isn't
     * structural). $perTypeNames are the attribute names the per-type serializer produces.
     */
    public static function mergeExtras(array $attrs, array $settings, array $perTypeNames): array
    {
        foreach ($attrs as $k => $v) {
            if (!is_string($k) || $k === '') {
                continue;
            }
            if (in_array($k, self::FIDELITY_STRUCTURAL, true)) {
                continue;
            }
            if (in_array($k, $perTypeNames, true)) {
                continue;
            }
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
    public static function perTypeNames(string $kind, array $node): array
    {
        $prev = self::$suppressExtras;
        self::$suppressExtras = true;
        try {
            if ($kind === 'container') {
                $sc = ContainerConverter::toShortcode(self::stripChildren($node));
            } elseif ($kind === 'column') {
                $sc = ColumnConverter::toShortcode(self::stripChildren($node));
            } else {
                $sc = BuilderShortcodeConverter::elementToShortcode(self::stripChildren($node));
            }
        } finally {
            self::$suppressExtras = $prev;
        }

        return is_string($sc) ? self::tagAttrNames($sc) : [];
    }
}
