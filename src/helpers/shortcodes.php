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
        // key="value" | key='value' | key=value | key=&quot;value&quot;
        if (preg_match_all('/(\w+)\s*=\s*(?:&quot;|["\'])?([^"\'\]\s&]+)(?:&quot;|["\'])?/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $atts[$pair[1]] = $pair[2];
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
