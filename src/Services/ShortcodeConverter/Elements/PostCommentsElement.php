<?php

namespace FalconCms\Core\Services\ShortcodeConverter\Elements;

use FalconCms\Core\Services\ShortcodeConverter\Element;

/**
 * [falcon_post_comments] — builder JSON ⇄ shortcode.
 */
final class PostCommentsElement extends Element
{
    public static function toShortcode(array $el, $type, $s, string $base, string $vis): string
    {
        // No body: the comments are the current post's, found when the page renders.
        // The colours, sizes and responsive margins travel as Fidelity extras.
        $a = $base;
        self::attrKeepEmpty($a, 'title', $s, 'title');
        self::attrI($a, 'count', (($s['showCount'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'order', $s['order'] ?? null, 'oldest');
        self::attrI($a, 'replies', (($s['allowReplies'] ?? true) === false) ? 'no' : null);
        self::attrI($a, 'form', (($s['showForm'] ?? true) === false) ? 'no' : null);
        self::attrKeepEmpty($a, 'form_title', $s, 'formTitle');
        self::attrI($a, 'button_text', $s['buttonText'] ?? null, 'Post Comment');
        self::attrKeepEmpty($a, 'empty_text', $s, 'emptyText');
        self::attrI($a, 'margin_top', $s['marginTop'] ?? null);
        self::attrI($a, 'margin_top_unit', $s['marginTopUnit'] ?? null, 'px');
        self::attrI($a, 'margin_bottom', $s['marginBottom'] ?? null);
        self::attrI($a, 'margin_bottom_unit', $s['marginBottomUnit'] ?? null, 'px');
        self::attrI($a, 'css_class', $s['cssClass'] ?? null);
        self::attrI($a, 'css_id', $s['cssId'] ?? null);

        return '[falcon_post_comments '.trim($a).$vis.' /]';
    }

    public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array
    {
        return ['id' => $a['id'] ?? self::uid(), 'type' => 'post_comments', 'settings' => [
            // Written whenever the element had one, so a cleared heading stays cleared.
            'title' => $a['title'] ?? null,
            'showCount' => ($a['count'] ?? '') !== 'no',
            'order' => ($a['order'] ?? '') === 'newest' ? 'newest' : 'oldest',
            'allowReplies' => ($a['replies'] ?? '') !== 'no',
            'showForm' => ($a['form'] ?? '') !== 'no',
            'formTitle' => $a['form_title'] ?? null,
            'buttonText' => $a['button_text'] ?? 'Post Comment',
            'emptyText' => $a['empty_text'] ?? null,
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
