<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\ColumnConverter;
use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_row] — builder JSON ⇄ shortcode.
 */
final class RowElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        // The Navigator name is the only setting a nested row has besides visibility.
        $label = self::adminLabel($s['adminLabel'] ?? null);
        if ($label !== null) {
            $base = trim($base).' admin_label="'.$label.'"';
        }
        if (!empty($el['columns'])) {
            $rowCols = [];
            foreach ($el['columns'] as $nestedCol) {
                $rowCols[] = ColumnConverter::toShortcode($nestedCol);
            }
            $rowInner = ' '.implode(' ', $rowCols).' ';

            return '[falcon_row'.($base ? ' '.trim($base) : '').$vis.']'.$rowInner.'[/falcon_row]';
        }

        return '[falcon_row '.trim($base).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        $rowObj = ['id' => $a['id'] ?? self::uid(), 'type' => 'row', 'settings' => array_filter(['visibility' => $vis, 'adminLabel' => $a['admin_label'] ?? null], fn ($v) => $v !== null)];
        if (!empty(trim($inner))) {
            $nestedCols = ColumnConverter::parseColumns($inner);
            if (!empty($nestedCols)) {
                $rowObj['columns'] = $nestedCols;
            }
        }

        return $rowObj;
    }
}
