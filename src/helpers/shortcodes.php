<?php

/**
 * Shortcode registry and parser.
 *
 * Loaded by src/helpers.php.
 */
if (!function_exists('add_falcon_shortcode')) {
    /**
     * Register a shortcode handler. Themes and plugins call this to expose
     * `[tag attr="value"]` shortcodes that render on the frontend. The callback
     * receives an associative array of the parsed attributes and returns HTML.
     * Written as a pair — `[tag]content[/tag]` — the content is passed as a
     * second argument.
     */
    function add_falcon_shortcode($tag, callable $callback)
    {
        $GLOBALS['__falcon_shortcodes'][$tag] = $callback;
    }
}

if (!function_exists('falcon_parse_shortcode_atts')) {
    /** Parse a shortcode attribute string into an assoc array. Handles &quot; too. */
    function falcon_parse_shortcode_atts($text)
    {
        $atts = [];
        if (!is_string($text) || trim($text) === '') {
            return $atts;
        }
        // key="value with spaces" | key='value' | key=&quot;value&quot; (WYSIWYG editors) |
        // key=value (bare: up to the next space). Editors may also write curly quotes.
        $text = str_replace(['&#8220;', '&#8221;', '“', '”', '&#8216;', '&#8217;', '‘', '’'], ['"', '"', '"', '"', "'", "'", "'", "'"], $text);
        if (preg_match_all('/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|&quot;(.*?)&quot;|([^\s\]"\']+))/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $atts[$pair[1]] = html_entity_decode((string) ($pair[2] ?? '').($pair[3] ?? '').($pair[4] ?? '').($pair[5] ?? ''), ENT_QUOTES, 'UTF-8');
            }
        }

        return $atts;
    }
}

if (!function_exists('falcon_do_shortcodes')) {
    /** Process all registered shortcodes in a content string. */
    function falcon_do_shortcodes($content)
    {
        if (empty($GLOBALS['__falcon_shortcodes']) || !is_string($content) || $content === '') {
            return $content;
        }
        foreach ($GLOBALS['__falcon_shortcodes'] as $tag => $callback) {
            // An enclosing pair first — [tag atts]content[/tag] — whose content goes to the
            // callback as a second argument (a callback that takes one ignores it).
            if (str_contains($content, '[/'.$tag.']')) {
                $pair = '/\['.preg_quote($tag, '/').'(\b[^\]]*)?\](.*?)\[\/'.preg_quote($tag, '/').'\]/s';
                $content = preg_replace_callback($pair, function ($m) use ($callback) {
                    return (string) call_user_func($callback, falcon_parse_shortcode_atts($m[1] ?? ''), $m[2]);
                }, $content) ?? $content;
            }

            $pattern = '/\['.preg_quote($tag, '/').'(\b[^\]]*)?\]/';
            $content = preg_replace_callback($pattern, function ($m) use ($callback) {
                $atts = falcon_parse_shortcode_atts($m[1] ?? '');

                return (string) call_user_func($callback, $atts);
            }, $content);
        }

        return $content;
    }
}

if (!function_exists('do_falcon_shortcode')) {
    function do_falcon_shortcode($content)
    {
        if (empty($content)) {
            return $content;
        }

        // Match [falcon_form slug="..."] — also accept &quot; (entity-encoded quotes from WYSIWYG editors)
        // Do NOT html_entity_decode the entire string: that would undo Blade's {{ }} escaping and open XSS.
        $content = preg_replace_callback(
            '/\[falcon_form\s+slug=(?:&quot;|["\'])([^"\'&\[\]]+)(?:&quot;|["\'])\s*\]/',
            function ($matches) {
                return render_falcon_form($matches[1]);
            },
            $content
        );

        $shortcodes = [
            '[falcon_search]' => falcon_search_form(),
            '[falcon_lang_dropdown]' => falcon_lang_dropdown(),
        ];
        $content = str_replace(array_keys($shortcodes), array_values($shortcodes), $content);

        // Theme/plugin-registered shortcodes (add_falcon_shortcode).
        return falcon_do_shortcodes($content);
    }
}
