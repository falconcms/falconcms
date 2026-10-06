<?php

namespace FalconCms\Core\Services;

use FalconCms\Core\Services\ShortcodeConverter\ContainerConverter;
use FalconCms\Core\Services\ShortcodeConverter\ConverterHelpers;
use FalconCms\Core\Services\ShortcodeConverter\CustomElementConverter;
use FalconCms\Core\Services\ShortcodeConverter\Element;
use FalconCms\Core\Services\ShortcodeConverter\Elements;
use FalconCms\Core\Services\ShortcodeConverter\Fidelity;

/**
 * Converts Falcon Builder JSON ↔ human-readable shortcodes.
 *
 * Format mirrors Fusion Builder style — every setting is a plain attribute.
 * No base64 encoding. Null / default values are omitted to keep shortcodes short.
 *
 * Roundtrip: shortcode attributes are mapped back to the exact camelCase keys
 * the builder expects, with null defaults for any omitted setting.
 *
 * This class is the entry point and the element dispatch. The work is done in
 * ShortcodeConverter/, one class per part, each holding both directions:
 *   ContainerConverter     [falcon_section]
 *   ColumnConverter        [falcon_col], and the columns of a row
 *   Elements/*Element      one core element each (see ELEMENTS)
 *   CustomElementConverter elements registered through the falcon_builder_elements filter
 *   Fidelity               readable extra attributes that keep the round trip lossless
 */
class BuilderShortcodeConverter
{
    use ConverterHelpers;

    /**
     * The core builder elements, by type. Each class converts its element both ways:
     * toShortcode() (builder JSON → shortcode) and fromShortcode() (shortcode → builder JSON).
     *
     * Adding an element: create a class in ShortcodeConverter/Elements that extends Element,
     * add it here, and mirror it in public/assets/js/falcon-builder-converter.js. Anything
     * not listed falls through to the custom-element path (falcon_builder_elements filter).
     *
     * @var array<string, class-string<Element>>
     */
    public const ELEMENTS = [
        'heading' => Elements\HeadingElement::class,
        'title' => Elements\TitleElement::class,
        'text' => Elements\TextElement::class,
        'button' => Elements\ButtonElement::class,
        'image' => Elements\ImageElement::class,
        'spacer' => Elements\SpacerElement::class,
        'table' => Elements\TableElement::class,
        'prev_next' => Elements\PrevNextElement::class,
        'callout' => Elements\CalloutElement::class,
        'toc' => Elements\TocElement::class,
        'code_block' => Elements\CodeBlockElement::class,
        'section_separator' => Elements\SectionSeparatorElement::class,
        'breadcrumb' => Elements\BreadcrumbElement::class,
        'html' => Elements\HtmlElement::class,
        'icon_box' => Elements\IconBoxElement::class,
        'content_box' => Elements\ContentBoxElement::class,
        'video' => Elements\VideoElement::class,
        'text_block' => Elements\SpecialTextElement::class,
        'special_text' => Elements\SpecialTextElement::class,
        'menu' => Elements\MenuElement::class,
        'row' => Elements\RowElement::class,
        'card' => Elements\CardElement::class,
        'accordion' => Elements\AccordionElement::class,
        'tabs' => Elements\TabsElement::class,
        'icon_list' => Elements\IconListElement::class,
        'counter' => Elements\CounterElement::class,
        'star_rating' => Elements\StarRatingElement::class,
        'gallery' => Elements\GalleryElement::class,
        'ticker' => Elements\TickerElement::class,
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    public static function isBuilderJson(string $content): bool
    {
        $t = trim($content);
        if (empty($t) || ($t[0] !== '[' && $t[0] !== '{')) {
            return false;
        }
        $d = json_decode($t, true);

        return is_array($d) && !empty($d) && isset($d[0]['id']);
    }

    public static function isBuilderShortcode(string $content): bool
    {
        return str_contains($content, '[falcon_section');
    }

    public static function jsonToShortcodes(string $json): string
    {
        $layout = json_decode($json, true);
        if (!is_array($layout) || empty($layout)) {
            return $json;
        }

        return implode("\n\n", array_map([ContainerConverter::class, 'toShortcode'], $layout));
    }

    /**
     * Rich editors (TinyMCE) wrap shortcodes in <p>/<br> and HTML-encode brackets. Strip that
     * wrapping around structural builder tags so the layout parses faithfully after a rich-editor edit.
     */
    private static function unwrapEditorMarkup(string $content): string
    {
        // Decode encoded brackets that some editors produce.
        $content = str_replace(['&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;', '&#x5b;', '&#x5d;'], ['[', ']', '[', ']', '[', ']', '[', ']'], $content);
        // Drop <p>/</p>/<br> immediately adjacent to ANY builder shortcode tag (open or close).
        $content = preg_replace('#<p[^>]*>\s*(\[/?(?:falcon_|lazy_|lzr_)[^\]]*\])#i', '$1', $content);
        $content = preg_replace('#(\[/?(?:falcon_|lazy_|lzr_)[^\]]*\])\s*</p>#i', '$1', $content);
        // Stray <br> between shortcode tags.
        $content = preg_replace('#(\])\s*<br\s*/?>\s*(\[)#i', '$1$2', $content);
        // Empty paragraphs left behind.
        $content = preg_replace('#<p[^>]*>\s*(?:&nbsp;)?\s*</p>#i', '', $content);

        return $content;
    }

    public static function shortcodesToJson(string $content): string
    {
        $content = self::unwrapEditorMarkup($content);
        $layout = [];
        $pattern = '/\[falcon_section([^\]]*)\](.*?)\[\/falcon_section\]/s';
        if (!preg_match_all($pattern, $content, $m, PREG_SET_ORDER)) {
            return $content;
        }
        foreach ($m as $match) {
            $c = ContainerConverter::fromShortcode($match[1], $match[2]);
            if ($c) {
                $layout[] = $c;
            }
        }

        return json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** What parsing a freshly-serialized element shortcode recovers — used to find dropped fields. */
    public static function elementRecovered(string $sc, string $type, array $settings): array
    {
        if (!preg_match('/^\s*\[([^\s\]\/]+)\s*([^\]]*?)(?:\/\]|\](.*)\[\/\1\])\s*$/is', $sc, $m)) {
            return $settings;
        }
        // Built-in types win (some, e.g. "menu", are ALSO registered as custom defs for the panel UI).
        $el = self::parseElementInner($type, $m[2], $m[3] ?? '');
        if (!is_array($el) && isset(CustomElementConverter::customDefs()[$type])) {
            $el = CustomElementConverter::parseCustomElement(CustomElementConverter::customDefs()[$type], $m[2], $m[3] ?? '');
        }

        return is_array($el) ? ($el['settings'] ?? []) : $settings;
    }

    /**
     * The element class for a type from builder JSON, or null for a custom/unknown element.
     *
     * A type that is not a string only comes from malformed JSON. It is still matched the way
     * the switch this replaced matched it — loosely, in ELEMENTS order — so even bad data
     * converts exactly as before.
     *
     * @return class-string<Element>|null
     */
    private static function elementClass($type): ?string
    {
        if (is_string($type)) {
            return self::ELEMENTS[$type] ?? null;
        }
        foreach (self::ELEMENTS as $key => $class) {
            if ($type == $key) {
                return $class;
            }
        }

        return null;
    }

    public static function elementToShortcode(array $el): string
    {
        $type = $el['type'] ?? 'text';
        $id = $el['id'] ?? '';
        $s = $el['settings'] ?? [];
        $base = $id ? 'id="'.$id.'"' : '';

        // Visibility (common to all elements)
        $visAttrs = [];
        $v = $s['visibility'] ?? [];
        if (!($v['mobile'] ?? true)) {
            $visAttrs[] = 'hide_mobile="yes"';
        }
        if (!($v['tablet'] ?? true)) {
            $visAttrs[] = 'hide_tablet="yes"';
        }
        if (!($v['desktop'] ?? true)) {
            $visAttrs[] = 'hide_desktop="yes"';
        }
        $vis = $visAttrs ? ' '.implode(' ', $visAttrs) : '';

        $element = self::elementClass($type);
        if ($element !== null) {
            return $element::toShortcode($el, $type, $s, $base, $vis);
        }

        // Not a core element: one registered through the falcon_builder_elements filter, or unknown.
        return CustomElementConverter::toShortcode($el, $type, $base, $v, $vis);
    }

    public static function parseElement(string $type, string $attrStr, string $inner): ?array
    {
        $el = self::parseElementInner($type, $attrStr, $inner);
        if (is_array($el)) {
            $a = self::attrs($attrStr);
            $settings = $el['settings'] ?? [];
            $names = Fidelity::perTypeNames('element', $el);
            $el['settings'] = Fidelity::mergeExtras($a, $settings, $names);
        }

        return $el;
    }

    private static function parseElementInner(string $type, string $attrStr, string $inner): ?array
    {
        $a = self::attrs($attrStr);
        $vis = self::visibilityFromAttrs($a);

        $element = self::ELEMENTS[$type] ?? null;
        if ($element !== null) {
            return $element::fromShortcode($type, $a, $vis, $inner, $attrStr);
        }

        // Not a core element: one registered through the falcon_builder_elements filter, or unknown.
        return CustomElementConverter::fromShortcode($type, $attrStr, $inner, $a, $vis);
    }
}
