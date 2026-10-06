<?php

namespace FalconCms\Core\Services\ShortcodeConverter;

/**
 * One core builder element, converted both ways.
 *
 * Both directions live in the same class on purpose: every setting toShortcode() writes,
 * fromShortcode() must read back, or it is lost the next time the page is opened. Keep the
 * two side by side and change them together. The builder canvas has a JavaScript twin of
 * each in public/assets/js/falcon-builder-converter.js, which must produce the same output.
 *
 * A new element is a new subclass in Elements/ plus one line in
 * BuilderShortcodeConverter::ELEMENTS.
 */
abstract class Element
{
    use ConverterHelpers;

    /**
     * Builder JSON → shortcode.
     *
     * @param  array  $el  the whole element node (id, type, settings, …)
     * @param  mixed  $type  the element type as it appears in the JSON
     * @param  mixed  $s  the node's settings (normally an array)
     * @param  string  $base  the leading attributes, e.g. id="abc123" (or '')
     * @param  string  $vis  the visibility attributes with a leading space (or '')
     */
    abstract public static function toShortcode(array $el, $type, $s, string $base, string $vis): string;

    /**
     * Shortcode → builder JSON.
     *
     * @param  string  $type  the element type from the tag name ([falcon_<type>])
     * @param  array  $a  the parsed attributes
     * @param  array  $vis  the visibility settings parsed from hide_* attributes
     * @param  string  $inner  the shortcode body
     * @param  string  $attrStr  the raw attribute string
     */
    abstract public static function fromShortcode(string $type, array $a, array $vis, string $inner, string $attrStr): ?array;
}
