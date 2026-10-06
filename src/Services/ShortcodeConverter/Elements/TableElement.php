<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;
use FalconCms\Core\Support\TableStyles;

/**
 * [falcon_table] — builder JSON ⇄ shortcode.
 */
final class TableElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'preset', $s['preset'] ?? null, 'docs');
        self::attrI($a, 'header_row', (($s['headerRow'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'header_col', !empty($s['headerCol']) ? 'yes' : null);
        self::attrI($a, 'caption', $s['caption'] ?? null);
        self::attrI($a, 'sortable', !empty($s['sortable']) ? 'yes' : null);
        self::attrI($a, 'sticky', !empty($s['stickyHeader']) ? 'yes' : null);
        self::attrI($a, 'max_height', $s['maxHeight'] ?? null, 0);
        self::attrI($a, 'responsive', $s['responsive'] ?? null, 'scroll');
        self::attrI($a, 'header_bg', $s['headerBg'] ?? null);
        self::attrI($a, 'header_color', $s['headerColor'] ?? null);
        self::attrI($a, 'text_color', $s['textColor'] ?? null);
        self::attrI($a, 'body_bg', $s['bodyBg'] ?? null);
        self::attrI($a, 'border_color', $s['borderColor'] ?? null);
        self::attrI($a, 'borders', $s['borders'] ?? null);
        self::attrI($a, 'stripe', isset($s['stripe']) ? (!empty($s['stripe']) ? 'yes' : 'no') : null);
        self::attrI($a, 'stripe_bg', $s['stripeBg'] ?? null);
        self::attrI($a, 'hover', isset($s['hover']) ? (!empty($s['hover']) ? 'yes' : 'no') : null);
        self::attrI($a, 'hover_bg', $s['hoverBg'] ?? null);
        self::attrI($a, 'font_size', $s['fontSize'] ?? null);
        self::attrI($a, 'pad_y', $s['cellPaddingY'] ?? null);
        self::attrI($a, 'pad_x', $s['cellPaddingX'] ?? null);
        self::attrI($a, 'radius', $s['radius'] ?? null);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        self::attrI($a, 'hl_rows', $s['highlightRows'] ?? null);
        self::attrI($a, 'hl_cols', $s['highlightCols'] ?? null);
        self::attrI($a, 'hl_bg', $s['highlightBg'] ?? null);
        self::attrI($a, 'hl_color', $s['highlightColor'] ?? null);
        self::attrI($a, 'icon_yes', $s['iconYesColor'] ?? null);
        self::attrI($a, 'icon_no', $s['iconNoColor'] ?? null);
        self::attrI($a, 'btn_bg', $s['btnBg'] ?? null);
        self::attrI($a, 'btn_color', $s['btnColor'] ?? null);
        self::attrI($a, 'tbl_head_family', $s['tbl_head_family'] ?? null);
        self::attrI($a, 'tbl_head_weight', $s['tbl_head_weight'] ?? null);
        self::attrI($a, 'tbl_head_size', $s['tbl_head_size'] ?? null);
        self::attrI($a, 'tbl_head_line_height', $s['tbl_head_line_height'] ?? null);
        self::attrI($a, 'tbl_head_letter_spacing', $s['tbl_head_letter_spacing'] ?? null);
        self::attrI($a, 'tbl_head_transform', $s['tbl_head_transform'] ?? null);
        self::attrI($a, 'tbl_body_family', $s['tbl_body_family'] ?? null);
        self::attrI($a, 'tbl_body_weight', $s['tbl_body_weight'] ?? null);
        self::attrI($a, 'tbl_body_size', $s['tbl_body_size'] ?? null);
        self::attrI($a, 'tbl_body_line_height', $s['tbl_body_line_height'] ?? null);
        self::attrI($a, 'tbl_body_letter_spacing', $s['tbl_body_letter_spacing'] ?? null);
        self::attrI($a, 'tbl_body_transform', $s['tbl_body_transform'] ?? null);

        // The grid goes in the body as a Markdown table, which is a format
        // people can read, diff and edit — and hand-write, since the parser
        // accepts one either way. Alignments and widths travel as attributes
        // rather than being inferred from the alignment line, so nothing is lost
        // when there is no header row for that line to sit under.
        $tblRows = TableStyles::rectangular($s['rows'] ?? []);
        $tblCols = array_values($s['cols'] ?? []);

        if ($tblRows === []) {
            return '[falcon_table '.trim($a).$vis.' /]';
        }

        $aligns = [];
        $widths = [];
        foreach ($tblRows[0] as $c => $_) {
            $aligns[] = $tblCols[$c]['align'] ?? 'left';
            $widths[] = (string) ($tblCols[$c]['width'] ?? '');
        }
        self::attrI($a, 'col_align', implode(',', $aligns));
        if (trim(implode('', $widths)) !== '') {
            self::attrI($a, 'col_width', implode(',', $widths));
        }

        $markdown = TableStyles::toMarkdown($tblRows, $tblCols, ($s['headerRow'] ?? true) !== false);

        // A cell holding this element's own closing tag is the one thing that
        // would truncate the body, so such a table falls back to base64 JSON.
        if (str_contains($markdown, '[/falcon_table')) {
            self::attrI($a, 'enc', 'b64');
            $grid = json_encode(['rows' => $tblRows, 'cols' => $tblCols],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return '[falcon_table '.trim($a).$vis.']'.base64_encode((string) $grid).'[/falcon_table]';
        }

        return '[falcon_table '.trim($a).$vis.']'."\n".$markdown."\n".'[/falcon_table]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        // Same guard as the Code Block: a body that is not genuinely our own
        // base64 must not be decoded, or a hand-written shortcode turns into
        // bytes that are not valid UTF-8 and json_encode fails on the whole
        // layout — blanking the page rather than just this element. A body that
        // is not ours is read as a Markdown table instead, so a shortcode typed
        // by hand still produces a table.
        $tblRows = [];
        $tblCols = [];
        $tblBody = trim(self::unmangleBody($inner));

        if ($tblBody !== '' && ($a['enc'] ?? '') === 'b64') {
            // Only decoded when the writer said so: short lowercase words are
            // valid base64 too, and decoding a hand-written body would produce
            // bytes that are not valid UTF-8, failing json_encode for the whole
            // layout and blanking the page rather than just this element.
            $tblTry = base64_decode($tblBody, true);
            if ($tblTry !== false && base64_encode($tblTry) === $tblBody && mb_check_encoding($tblTry, 'UTF-8')
                && is_array($tblDecoded = json_decode($tblTry, true))) {
                $tblRows = is_array($tblDecoded['rows'] ?? null) ? $tblDecoded['rows'] : [];
                $tblCols = is_array($tblDecoded['cols'] ?? null) ? $tblDecoded['cols'] : [];
            }
        } elseif ($tblBody !== '') {
            $tblMd = TableStyles::parseMarkdown($tblBody);
            if ($tblMd !== null) {
                $tblRows = $tblMd['rows'];
                // The alignment line is what a hand-written table carries; the
                // attributes below win when this element wrote the shortcode.
                $tblCols = array_map(static fn ($al) => ['align' => $al, 'width' => ''], $tblMd['align']);
            }
        }

        $attrAlign = array_values(array_filter(
            array_map('trim', explode(',', (string) ($a['col_align'] ?? ''))),
            static fn ($v) => $v !== ''
        ));

        // Last resort: the body lost its line breaks altogether — pasted into a
        // single-line field, or run through something that collapses whitespace.
        // The row boundaries are gone from the text, but col_align still says how
        // many columns there are, which is enough to put the cells back.
        if (count($tblRows) === 1 && $attrAlign !== [] && count($tblRows[0]) > count($attrAlign)) {
            $width = count($attrAlign);
            $rebuiltRows = [];
            $current = [];
            $afterRule = false;

            foreach ($tblRows[0] as $cellText) {
                $text = trim((string) $cellText);

                // The alignment line, if the body still carries one.
                if (preg_match('/^:?-{3,}:?$/', $text)) {
                    $afterRule = true;

                    continue;
                }

                // Where two rows met there is now one empty cell, from the "| |"
                // that joined them. It is only a boundary when the row in hand is
                // already full — an empty cell anywhere else is real content and
                // has to be kept, which is why this counts rather than filtering.
                //
                // The one exception is the boundary that followed the alignment
                // line: its cells were skipped, so the row in hand is empty and
                // the boundary would otherwise be read as a first, blank cell —
                // which shifted every following row by one.
                if ($text === '' && $afterRule && $current === []) {
                    $afterRule = false;

                    continue;
                }
                $afterRule = false;

                if ($text === '' && count($current) >= $width) {
                    $rebuiltRows[] = $current;
                    $current = [];

                    continue;
                }

                if (count($current) >= $width) {
                    $rebuiltRows[] = $current;
                    $current = [];
                }

                $current[] = $cellText;
            }

            if ($current !== []) {
                $rebuiltRows[] = array_pad($current, $width, '');
            }

            if ($rebuiltRows !== []) {
                $tblRows = $rebuiltRows;
            }
        }

        if ($tblRows !== []) {
            $width = count($tblRows[0]);
            $attrWidth = ($a['col_width'] ?? null) !== null ? array_map('trim', explode(',', (string) $a['col_width'])) : null;

            $rebuilt = [];
            for ($c = 0; $c < $width; $c++) {
                $align = $attrAlign[$c] ?? ($tblCols[$c]['align'] ?? 'left');
                $rebuilt[] = [
                    'align' => in_array($align, TableStyles::ALIGNMENTS, true) ? $align : 'left',
                    'width' => $attrWidth[$c] ?? ($tblCols[$c]['width'] ?? ''),
                ];
            }
            $tblCols = $rebuilt;
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'table', 'settings' => [
            'rows' => $tblRows,
            'cols' => $tblCols,
            'preset' => $a['preset'] ?? 'docs',
            'headerRow' => ($a['header_row'] ?? '') !== 'no',
            'headerCol' => ($a['header_col'] ?? '') === 'yes',
            'caption' => $a['caption'] ?? '',
            'sortable' => ($a['sortable'] ?? '') === 'yes',
            'stickyHeader' => ($a['sticky'] ?? '') === 'yes',
            'maxHeight' => isset($a['max_height']) ? (int) $a['max_height'] : 0,
            'responsive' => $a['responsive'] ?? 'scroll',
            'headerBg' => $a['header_bg'] ?? null,
            'headerColor' => $a['header_color'] ?? null,
            'textColor' => $a['text_color'] ?? null,
            'bodyBg' => $a['body_bg'] ?? null,
            'borderColor' => $a['border_color'] ?? null,
            'borders' => $a['borders'] ?? null,
            'stripe' => isset($a['stripe']) ? ($a['stripe'] === 'yes') : null,
            'stripeBg' => $a['stripe_bg'] ?? null,
            'hover' => isset($a['hover']) ? ($a['hover'] === 'yes') : null,
            'hoverBg' => $a['hover_bg'] ?? null,
            'fontSize' => isset($a['font_size']) ? self::num($a['font_size']) : null,
            'cellPaddingY' => isset($a['pad_y']) ? (int) $a['pad_y'] : null,
            'cellPaddingX' => isset($a['pad_x']) ? (int) $a['pad_x'] : null,
            'radius' => isset($a['radius']) ? (int) $a['radius'] : null,
            'marginTop' => isset($a['margin_top']) ? self::num($a['margin_top']) : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? self::num($a['margin_bottom']) : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'highlightRows' => $a['hl_rows'] ?? null,
            'highlightCols' => $a['hl_cols'] ?? null,
            'highlightBg' => $a['hl_bg'] ?? null,
            'highlightColor' => $a['hl_color'] ?? null,
            'iconYesColor' => $a['icon_yes'] ?? null,
            'iconNoColor' => $a['icon_no'] ?? null,
            'btnBg' => $a['btn_bg'] ?? null,
            'btnColor' => $a['btn_color'] ?? null,
            'tbl_head_family' => $a['tbl_head_family'] ?? null,
            'tbl_head_weight' => $a['tbl_head_weight'] ?? null,
            'tbl_head_size' => $a['tbl_head_size'] ?? null,
            'tbl_head_line_height' => $a['tbl_head_line_height'] ?? null,
            'tbl_head_letter_spacing' => $a['tbl_head_letter_spacing'] ?? null,
            'tbl_head_transform' => $a['tbl_head_transform'] ?? null,
            'tbl_body_family' => $a['tbl_body_family'] ?? null,
            'tbl_body_weight' => $a['tbl_body_weight'] ?? null,
            'tbl_body_size' => $a['tbl_body_size'] ?? null,
            'tbl_body_line_height' => $a['tbl_body_line_height'] ?? null,
            'tbl_body_letter_spacing' => $a['tbl_body_letter_spacing'] ?? null,
            'tbl_body_transform' => $a['tbl_body_transform'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
