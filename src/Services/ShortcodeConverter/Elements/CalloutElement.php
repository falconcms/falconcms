<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_callout] — builder JSON ⇄ shortcode.
 */
final class CalloutElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        $a = $base;
        self::attrTextAnim($a, $s);
        self::attrI($a, 'variant', $s['variant'] ?? null, 'note');
        self::attrI($a, 'preset', $s['preset'] ?? null, 'bar');
        self::attrKeepEmpty($a, 'title', $s, 'title');
        self::attrI($a, 'icon', $s['icon'] ?? null);
        self::attrI($a, 'icon_style', $s['iconStyle'] ?? null);
        self::attrI($a, 'accent', $s['accent'] ?? null);
        self::attrI($a, 'bg', $s['bgColor'] ?? null);
        self::attrI($a, 'title_color', $s['titleColor'] ?? null);
        self::attrI($a, 'body_color', $s['bodyColor'] ?? null);
        self::attrI($a, 'fill', $s['fill'] ?? null);
        self::attrI($a, 'radius', $s['radius'] ?? null);
        self::attrI($a, 'pad_y', $s['padY'] ?? null);
        self::attrI($a, 'pad_x', $s['padX'] ?? null);
        self::attrI($a, 'gap', $s['gap'] ?? null);
        self::attrI($a, 'bar', $s['barWidth'] ?? null);
        self::attrI($a, 'border', $s['borderWidth'] ?? null);
        self::attrI($a, 'icon_size', $s['iconSize'] ?? null);
        self::attrI($a, 'title_size', $s['titleSize'] ?? null);
        self::attrI($a, 'title_weight', $s['titleWeight'] ?? null);
        self::attrI($a, 'body_size', $s['bodySize'] ?? null);
        self::attrI($a, 'collapsible', !empty($s['collapsible']) ? 'yes' : null);
        self::attrI($a, 'open', (($s['openByDefault'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);
        self::attrI($a, 'cal_title_family', $s['cal_title_family'] ?? null);
        self::attrI($a, 'cal_title_weight', $s['cal_title_weight'] ?? null);
        self::attrI($a, 'cal_title_size', $s['cal_title_size'] ?? null);
        self::attrI($a, 'cal_title_line_height', $s['cal_title_line_height'] ?? null);
        self::attrI($a, 'cal_title_letter_spacing', $s['cal_title_letter_spacing'] ?? null);
        self::attrI($a, 'cal_title_transform', $s['cal_title_transform'] ?? null);
        self::attrI($a, 'cal_body_family', $s['cal_body_family'] ?? null);
        self::attrI($a, 'cal_body_weight', $s['cal_body_weight'] ?? null);
        self::attrI($a, 'cal_body_size', $s['cal_body_size'] ?? null);
        self::attrI($a, 'cal_body_line_height', $s['cal_body_line_height'] ?? null);
        self::attrI($a, 'cal_body_letter_spacing', $s['cal_body_letter_spacing'] ?? null);
        self::attrI($a, 'cal_body_transform', $s['cal_body_transform'] ?? null);

        // The text goes in the body, as written. Same reasoning as the Code
        // Block: a shortcode is a format people open and edit by hand, and the
        // parser only ever looks for this element's own closing tag, so
        // newlines, brackets and quotes all survive a raw body. A body holding
        // that one string falls back to base64 and says so with enc="b64".
        $calBody = (string) ($s['body'] ?? '');
        if (trim($calBody) === '') {
            return '[falcon_callout '.trim($a).$vis.' /]';
        }

        if (str_contains($calBody, '[/falcon_callout')) {
            self::attrI($a, 'enc', 'b64');

            return '[falcon_callout '.trim($a).$vis.']'
                .base64_encode($calBody)
                .'[/falcon_callout]';
        }

        // < and & go out as entities, exactly as the Code Block sends them. The
        // body stays readable — only those characters change — but left raw the
        // classic editor reads a "<" as the start of a tag and swallows
        // everything after it, taking this element's closing tag with it.
        return '[falcon_callout '.trim($a).$vis.']'
            .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $calBody)
            .'[/falcon_callout]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        // The body holds the text as written, with < and & as entities. Older
        // content and any text containing this element's own closing tag is
        // base64 and says so with enc="b64" — and only then is it decoded. A
        // short lowercase word is valid base64 too, so decoding a hand-written
        // body would produce bytes that are not valid UTF-8, and json_encode
        // would then fail on the whole layout: one hand-typed shortcode would
        // blank the entire page rather than just its own box. Requiring the
        // value to re-encode to exactly what was in the body is the check that
        // separates the two.
        $calRaw = self::unmangleBody($inner, true);
        $calTrim = trim($calRaw);
        $calBody = html_entity_decode($calRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($calTrim !== '' && ($a['enc'] ?? '') === 'b64') {
            $calTry = base64_decode($calTrim, true);
            if ($calTry !== false && base64_encode($calTry) === $calTrim && mb_check_encoding($calTry, 'UTF-8')) {
                $calBody = $calTry;
            }
        } elseif ($calTrim === '') {
            $calBody = '';
        }

        return ['id' => $a['id'] ?? self::uid(), 'type' => 'callout', 'settings' => [
            ...self::textAnimSettings($a),
            'body' => trim($calBody),
            'variant' => $a['variant'] ?? 'note',
            'preset' => $a['preset'] ?? 'bar',
            // Written whenever the element had one, so a cleared title stays
            // cleared: the renderer only falls back to the variant's name for a
            // title that was never set, and an absent attribute is exactly that.
            'title' => $a['title'] ?? null,
            'icon' => $a['icon'] ?? '',
            'iconStyle' => $a['icon_style'] ?? '',
            'accent' => $a['accent'] ?? '',
            'bgColor' => $a['bg'] ?? '',
            'titleColor' => $a['title_color'] ?? '',
            'bodyColor' => $a['body_color'] ?? '',
            'fill' => $a['fill'] ?? '',
            'radius' => self::numOrBlank($a['radius'] ?? null),
            'padY' => self::numOrBlank($a['pad_y'] ?? null),
            'padX' => self::numOrBlank($a['pad_x'] ?? null),
            'gap' => self::numOrBlank($a['gap'] ?? null),
            'barWidth' => self::numOrBlank($a['bar'] ?? null),
            'borderWidth' => self::numOrBlank($a['border'] ?? null),
            'iconSize' => self::numOrBlank($a['icon_size'] ?? null),
            'titleSize' => self::numOrBlank($a['title_size'] ?? null),
            'titleWeight' => $a['title_weight'] ?? '',
            'bodySize' => self::numOrBlank($a['body_size'] ?? null),
            'collapsible' => ($a['collapsible'] ?? '') === 'yes',
            'openByDefault' => ($a['open'] ?? '') !== 'no',
            'marginTop' => isset($a['margin_top']) ? self::num($a['margin_top']) : 0,
            'marginTopUnit' => $a['margin_top_unit'] ?? 'px',
            'marginBottom' => isset($a['margin_bottom']) ? self::num($a['margin_bottom']) : 0,
            'marginBottomUnit' => $a['margin_bottom_unit'] ?? 'px',
            'cssClass' => $a['css_class'] ?? null,
            'cssId' => $a['css_id'] ?? null,
            'cal_title_family' => $a['cal_title_family'] ?? null,
            'cal_title_weight' => $a['cal_title_weight'] ?? null,
            'cal_title_size' => $a['cal_title_size'] ?? null,
            'cal_title_line_height' => $a['cal_title_line_height'] ?? null,
            'cal_title_letter_spacing' => $a['cal_title_letter_spacing'] ?? null,
            'cal_title_transform' => $a['cal_title_transform'] ?? null,
            'cal_body_family' => $a['cal_body_family'] ?? null,
            'cal_body_weight' => $a['cal_body_weight'] ?? null,
            'cal_body_size' => $a['cal_body_size'] ?? null,
            'cal_body_line_height' => $a['cal_body_line_height'] ?? null,
            'cal_body_letter_spacing' => $a['cal_body_letter_spacing'] ?? null,
            'cal_body_transform' => $a['cal_body_transform'] ?? null,
            'visibility' => $vis,
        ]];
    }
}
