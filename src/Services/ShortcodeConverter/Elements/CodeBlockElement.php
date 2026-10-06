<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_code_block] — builder JSON ⇄ shortcode.
 */
final class CodeBlockElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrI($a, 'language', $s['language'] ?? null, 'php');
        self::attrI($a, 'theme', $s['codeTheme'] ?? null, 'falcon-dark');
        self::attrI($a, 'chrome', !empty($s['showChrome']) ? 'yes' : null);
        self::attrI($a, 'dots', (($s['chromeDots'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'filename', $s['filename'] ?? null);
        self::attrI($a, 'lang_tag', (($s['showLangTag'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'line_numbers', (($s['showLineNumbers'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'start_line', $s['startLine'] ?? null, 1);
        self::attrI($a, 'mark_lines', $s['highlightLines'] ?? null);
        self::attrI($a, 'wrap', !empty($s['wrapLines']) ? 'yes' : null);
        self::attrI($a, 'max_height', $s['maxHeight'] ?? null, 0);
        self::attrI($a, 'copy', (($s['showCopy'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'copy_label', $s['copyLabel'] ?? null, 'Copy');
        self::attrI($a, 'copied_label', $s['copiedLabel'] ?? null, 'Copied!');
        self::attrI($a, 'type_mode', $s['typeMode'] ?? null, 'none');
        self::attrI($a, 'type_speed', $s['typeSpeed'] ?? null, 30);
        self::attrI($a, 'type_start', $s['typeStart'] ?? null, 'view');
        self::attrI($a, 'type_caret', (($s['typeCaret'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'font_size', $s['fontSize'] ?? null, 14);
        self::attrI($a, 'line_height', $s['lineHeight'] ?? null, 1.7);
        self::attrI($a, 'font_family', $s['fontFamily'] ?? null);
        self::attrI($a, 'padding', $s['padding'] ?? null, 18);
        self::attrI($a, 'radius', $s['borderRadius'] ?? null, 10);
        self::attrI($a, 'border', $s['borderWidth'] ?? null, 1);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        // The code goes in the shortcode body as it was written.
        //
        // A shortcode is a format people open, diff and edit by hand, so the body
        // is kept readable. Newlines, quotes, square brackets and even a nested
        // [falcon_row] all survive a raw body — the parser only looks for this
        // element's own closing tag. That one string is the single thing that
        // would truncate the snippet, so a snippet containing it falls back to
        // base64 and says so with enc="b64".
        $codeBody = (string) ($s['code'] ?? '');
        if ($codeBody === '') {
            return '[falcon_code_block '.trim($a).$vis.' /]';
        }

        if (str_contains($codeBody, '[/falcon_code_block')) {
            self::attrI($a, 'enc', 'b64');

            return '[falcon_code_block '.trim($a).$vis.']'
                .base64_encode($codeBody)
                .'[/falcon_code_block]';
        }

        // < and & go out as entities. The body is readable either way — only
        // those two characters change — but left raw the classic editor reads
        // "<?php" as a tag and swallows everything after it, taking this
        // element's own closing tag and the section's with it. The snippet, and
        // the rest of the page, simply vanished.
        return '[falcon_code_block '.trim($a).$vis.']'
            .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $codeBody)
            .'[/falcon_code_block]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        // The body holds the code as written. Older content, and any snippet
        // that contains this element's own closing tag, is base64 and says so
        // with enc="b64"; the guard below is what keeps a hand-written body from
        // being decoded by accident. Short lowercase words are valid base64 too,
        // and strict decoding turns "abc" into bytes that are not valid UTF-8 —
        // json_encode then fails on the whole layout, so one hand-typed shortcode
        // would blank the entire page rather than just its own block. Requiring
        // the value to re-encode to exactly what was in the body is the check
        // that actually separates the two.
        // Not trimmed: a snippet's leading indentation and its closing newline
        // are part of the code. The trim below is only for the emptiness test
        // and the base64 comparison, which cannot contain whitespace anyway.
        $raw = self::unmangleBody($inner);
        $body = trim($raw);
        // Undoes the entities the writer put in. A hand-written shortcode holding
        // plain code has none, so this leaves it alone.
        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($body !== '' && ($a['enc'] ?? '') === 'b64') {
            $try = base64_decode($body, true);
            if ($try !== false && base64_encode($try) === $body && mb_check_encoding($try, 'UTF-8')) {
                $decoded = $try;
            }
        } elseif ($body === '') {
            $decoded = '';
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'code_block', 'settings' => [
            'code' => $decoded,
            'language' => $a['language'] ?? 'php',
            'codeTheme' => $a['theme'] ?? 'falcon-dark',
            'showChrome' => ($a['chrome'] ?? '') === 'yes',
            'chromeDots' => ($a['dots'] ?? '') !== 'no',
            'filename' => $a['filename'] ?? '',
            'showLangTag' => ($a['lang_tag'] ?? '') !== 'no',
            'showLineNumbers' => ($a['line_numbers'] ?? '') !== 'no',
            'startLine' => isset($a['start_line']) ? (int) $a['start_line'] : 1,
            'highlightLines' => $a['mark_lines'] ?? '',
            'wrapLines' => ($a['wrap'] ?? '') === 'yes',
            'maxHeight' => isset($a['max_height']) ? (int) $a['max_height'] : 0,
            'showCopy' => ($a['copy'] ?? '') !== 'no',
            'copyLabel' => $a['copy_label'] ?? 'Copy',
            'copiedLabel' => $a['copied_label'] ?? 'Copied!',
            'typeMode' => $a['type_mode'] ?? 'none',
            'typeSpeed' => isset($a['type_speed']) ? (int) $a['type_speed'] : 30,
            'typeStart' => $a['type_start'] ?? 'view',
            'typeCaret' => ($a['type_caret'] ?? '') !== 'no',
            'fontSize' => isset($a['font_size']) ? self::num($a['font_size']) : 14,
            'lineHeight' => isset($a['line_height']) ? self::num($a['line_height']) : 1.7,
            'fontFamily' => $a['font_family'] ?? '',
            'padding' => isset($a['padding']) ? (int) $a['padding'] : 18,
            'borderRadius' => isset($a['radius']) ? (int) $a['radius'] : 10,
            'borderWidth' => isset($a['border']) ? (int) $a['border'] : 1,
            'marginTop' => isset($a['margin_top']) ? self::num($a['margin_top']) : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? self::num($a['margin_bottom']) : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
