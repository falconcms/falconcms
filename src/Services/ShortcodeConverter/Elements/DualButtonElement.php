<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;
use FalconCms\Core\Support\DualButton;

/**
 * [falcon_dual_button] — builder JSON ⇄ shortcode.
 *
 * Both directions walk DualButton::FIELDS, so a setting added there is written and read
 * back without touching this class. An attribute is the setting's name in snake_case
 * (primaryHoverBg → primary_hover_bg).
 *
 * Three states are kept apart on purpose: a setting that was never set is left out and
 * comes back as its default; one equal to its default is left out too; one the author
 * cleared is written empty (primary_bg="") and comes back empty — a cleared label hides
 * its button and a cleared background is transparent, and neither may grow back on save.
 */
final class DualButtonElement extends Element
{
    private const SIDES = ['Top' => 'top', 'Right' => 'right', 'Bottom' => 'bottom', 'Left' => 'left'];

    private const DEVICES = ['', '_tablet', '_mobile'];

    private const TYPOGRAPHY = ['family', 'weight', 'size', 'line_height', 'letter_spacing', 'transform'];

    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $s = is_array($s) ? $s : [];
        $a = $base;

        foreach (DualButton::FIELDS as $key => [$kind, $default]) {
            if (!array_key_exists($key, $s) || $s[$key] === null) {
                continue;
            }
            $value = $s[$key];
            if ($kind === 'bool') {
                if ((bool) $value !== $default) {
                    $a .= ' '.self::snake($key).'="'.($value ? 'yes' : 'no').'"';
                }

                continue;
            }
            if ((string) $value === (string) $default) {
                continue;
            }
            self::attrKeepEmpty($a, self::snake($key), $s, $key);
        }

        // Alignment (+ responsive)
        self::attrI($a, 'align_tablet', $s['align_tablet'] ?? null);
        self::attrI($a, 'align_mobile', $s['align_mobile'] ?? null);

        // Each button's dynamic source and its options ({side}Dynamic), one attribute per
        // key: primary_dyn_dynamic_source="post_title" primary_dyn_dynamic_before="By ".
        // Written whole, because the options differ per source and a plugin may add its own.
        foreach (DualButton::SIDES as $side) {
            foreach (DualButton::dynamic($s, $side) as $key => $value) {
                if (is_string($key) && preg_match('/^\w+$/', $key) && (is_scalar($value) && $value !== '' && $value !== false)) {
                    self::attrI($a, $side.'_dyn_'.$key, $value === true ? '1' : $value);
                }
            }
        }

        // Border width, corners, icon gap per device
        foreach (DualButton::RESPONSIVE as $key) {
            foreach (['_tablet', '_mobile'] as $dev) {
                self::attrI($a, self::snake($key).$dev, $s[$key.$dev] ?? null);
            }
        }

        // Padding and margin (+ responsive), each side with its unit
        foreach (self::boxes() as [$box, $side, $suffix, $default]) {
            foreach (self::DEVICES as $dev) {
                $key = $box.$side.$dev;
                if (array_key_exists($key, $s) && $s[$key] !== null && !($dev === '' && (string) $s[$key] === (string) $default)) {
                    self::attrKeepEmpty($a, $box.'_'.$suffix.$dev, $s, $key);
                }
                self::attrI($a, $box.'_'.$suffix.'_unit'.$dev, $s[$box.$side.'Unit'.$dev] ?? null, $dev === '' ? 'px' : null);
            }
        }

        // Typography (the shared control, prefix "db")
        foreach (self::TYPOGRAPHY as $key) {
            self::attrI($a, 'db_'.$key, $s['db_'.$key] ?? null);
        }

        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_dual_button '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $ts = [];

        foreach (DualButton::FIELDS as $key => [$kind, $default]) {
            $raw = $a[self::snake($key)] ?? null;
            if ($raw === null) {
                $ts[$key] = $default;
            } elseif ($kind === 'bool') {
                $ts[$key] = $raw === 'yes';
            } elseif ($kind === 'num') {
                $ts[$key] = self::numOrBlank($raw);
            } else {
                $ts[$key] = $raw;
            }
        }

        foreach (DualButton::SIDES as $side) {
            $prefix = $side.'_dyn_';
            $dyn = [];
            foreach ($a as $attr => $value) {
                if (is_string($attr) && str_starts_with($attr, $prefix) && strlen($attr) > strlen($prefix)) {
                    $dyn[substr($attr, strlen($prefix))] = $value;
                }
            }
            if ($dyn) {
                $ts[$side.'Dynamic'] = $dyn;
            }
        }

        foreach (['tablet', 'mobile'] as $dev) {
            if (($a['align_'.$dev] ?? '') !== '') {
                $ts['align_'.$dev] = $a['align_'.$dev];
            }
            foreach (DualButton::RESPONSIVE as $key) {
                $raw = $a[self::snake($key).'_'.$dev] ?? '';
                if ($raw !== '') {
                    $ts[$key.'_'.$dev] = self::numOrBlank($raw);
                }
            }
        }

        foreach (self::boxes() as [$box, $side, $suffix, $default]) {
            foreach (self::DEVICES as $dev) {
                $raw = $a[$box.'_'.$suffix.$dev] ?? null;
                if ($raw !== null) {
                    $ts[$box.$side.$dev] = self::numOrBlank($raw);
                } elseif ($dev === '') {
                    $ts[$box.$side] = $default;
                }
                $unit = $a[$box.'_'.$suffix.'_unit'.$dev] ?? null;
                if ($unit !== null && $unit !== '') {
                    $ts[$box.$side.'Unit'.$dev] = $unit;
                } elseif ($dev === '') {
                    $ts[$box.$side.'Unit'] = 'px';
                }
            }
        }

        foreach (self::TYPOGRAPHY as $key) {
            $ts['db_'.$key] = $a['db_'.$key] ?? null;
        }

        $ts['cssClass'] = $a['css_class'] ?? null;
        $ts['cssId'] = $a['css_id'] ?? null;
        $ts['visibility'] = $vis;

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'dual_button', 'settings' => $ts];
    }

    /**
     * The spacing boxes: [setting prefix, side, attribute suffix, default px].
     *
     * @return list<array{string, string, string, int}>
     */
    private static function boxes(): array
    {
        $out = [];
        foreach (DualButton::PADDING as $side => [, $default]) {
            $out[] = ['padding', $side, self::SIDES[$side], $default];
        }
        foreach (DualButton::MARGIN as $side => $default) {
            $out[] = ['margin', $side, self::SIDES[$side], $default];
        }

        return $out;
    }

    /** primaryHoverBg → primary_hover_bg */
    private static function snake(string $key): string
    {
        return strtolower(preg_replace('/([A-Z])/', '_$1', $key));
    }
}
