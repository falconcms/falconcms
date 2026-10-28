<?php

namespace FalconAi\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans the HTML an AI writes before it goes into a post.
 *
 * Only article markup survives: headings, paragraphs, lists, emphasis, quotes, tables, code and
 * links. Every other element is unwrapped (its text kept) or, for script-like ones, dropped with
 * its content; every attribute is removed except a link's href, and that only when it is http,
 * https, mailto or a relative address. A model asked to write an article can still be steered by
 * the material it is given, so its output is treated as untrusted.
 */
final class HtmlSanitizer
{
    /** Allowed elements. */
    private const TAGS = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'u', 'a', 'blockquote',
        'br', 'hr', 'code', 'pre', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

    /** Dropped with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select',
        'svg', 'math', 'noscript', 'template', 'link', 'meta', 'base', 'title', 'head'];

    public static function clean(string $html): string
    {
        $html = trim(self::stripFences($html));
        if ($html === '') {
            return '';
        }
        // Plain text (no tags at all) becomes paragraphs.
        if (!preg_match('/<[a-z][^>]*>/i', $html)) {
            $paragraphs = preg_split('/\R{2,}/', $html) ?: [];

            return implode("\n", array_map(fn ($p) => '<p>'.nl2br(e(trim($p)), false).'</p>', array_filter($paragraphs, fn ($p) => trim($p) !== '')));
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="falcon-ai-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('falcon-ai-root');
        if (!$root) {
            return '';
        }
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if ($tag === 'h1') {
                $child = self::rename($child, 'h2');
                $tag = 'h2';
            }
            // Unknown element, or a link to somewhere unsafe: keep what it says, lose the element.
            if (!in_array($tag, self::TAGS, true) || ($tag === 'a' && !self::safeHref(trim($child->getAttribute('href'))))) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }
        if ($tag === 'a' && self::safeHref($href)) {
            $element->setAttribute('href', $href);
            if (preg_match('#^https?://#i', $href)) {
                $element->setAttribute('rel', 'noopener');
            }
        }
    }

    private static function safeHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }
        // Entities and control characters are decoded and stripped first: "java&#09;script:" is javascript:.
        $plain = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', (string) $plain, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true);
        }

        // No scheme: a relative address, a fragment or a path.
        return !str_starts_with((string) $plain, '//');
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $new = $element->ownerDocument->createElement($tag);
        while ($element->firstChild) {
            $new->appendChild($element->firstChild);
        }
        $element->parentNode->replaceChild($new, $element);

        return $new;
    }

    /** A model sometimes wraps HTML in a ```html fence. */
    private static function stripFences(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^```[a-z]*\s*\n(.*)\n```$/is', $text, $m)) {
            return $m[1];
        }

        return $text;
    }
}
