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
        $rowObj = ['id' => $a['id'] ?? self::uid(), 'type' => 'row', 'settings' => ['visibility' => $vis]];
        if (!empty(trim($inner))) {
            $nestedCols = ColumnConverter::parseColumns($inner);
            if (!empty($nestedCols)) {
                $rowObj['columns'] = $nestedCols;
            }
        }

        return $rowObj;
    }
}
