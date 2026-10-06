<?php

namespace FalconCms\Core\Services\ShortcodeConverter;

/**
 * Small, stateless attribute and value helpers shared by the converter and every element.
 */
trait ConverterHelpers
{
    /**
     * Serialise the Extra tab's Text Animation, shared verbatim by every element that
     * offers it (see FalconCms\Core\Support\TextAnimations::ELEMENTS). The skipped
     * defaults match what textAnimSettings() reads back, so a round-trip adds nothing.
     */
    protected static function attrTextAnim(string &$a, array $s): void
    {
        self::attrI($a, 'text_anim', $s['textAnim'] ?? null);
        self::attrI($a, 'text_anim_trigger', $s['textAnimTrigger'] ?? null, 'always');
        self::attrI($a, 'text_anim_duration', $s['textAnimDuration'] ?? null);
        self::attrI($a, 'text_anim_delay', $s['textAnimDelay'] ?? null);
        self::attrI($a, 'text_anim_iteration', $s['textAnimIteration'] ?? null, 'infinite');
        self::attrI($a, 'text_anim_easing', $s['textAnimEasing'] ?? null);
        self::attrI($a, 'text_anim_color', $s['textAnimColor'] ?? null);
    }

    /** The mirror of attrTextAnim(): merge into an element's parsed settings. */
    protected static function textAnimSettings(array $a): array
    {
        return [
            'textAnim' => $a['text_anim'] ?? null,
            'textAnimTrigger' => $a['text_anim_trigger'] ?? 'always',
            'textAnimDuration' => isset($a['text_anim_duration']) ? (int) $a['text_anim_duration'] : null,
            'textAnimDelay' => isset($a['text_anim_delay']) ? (int) $a['text_anim_delay'] : null,
            'textAnimIteration' => $a['text_anim_iteration'] ?? 'infinite',
            'textAnimEasing' => $a['text_anim_easing'] ?? null,
            'textAnimColor' => $a['text_anim_color'] ?? null,
        ];
    }

    /** Parse numeric-looking string to int/float, else return as-is (keeps units like "px"). */
    protected static function maybeNum($v)
    {
        if (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return strpos($v, '.') !== false ? (float) $v : (int) $v;
        }

        return $v;
    }

    /**
     * Undo what a rich text editor does to a multi-line shortcode body.
     *
     * Bodies that hold real lines — a Markdown table, a code snippet — are readable in
     * the shortcode on purpose, but the classic editor is HTML-oriented: it turns
     * newlines into <br>, wraps blocks in <p>, indents with &nbsp; and escapes stray
     * characters as entities. Left alone, a table opened and saved in that editor came
     * back with its rows shredded.
     *
     * A <br> is only read as a lost newline when the body has no newlines left at all.
     * The editor replaces every one of them, so a mangled body has none — while a code
     * sample about HTML, or a table cell using <br> to break a line, keeps its own and
     * must not be rewritten. Without that test the round trip turned one cell reading
     * "line one<br>line two" into two rows.
     */
    protected static function unmangleBody(string $body, bool $paragraphs = false): string
    {
        if (!str_contains($body, "\n")) {
            $body = preg_replace('/<br\s*\/?>/i', "\n", $body) ?? $body;
        }

        // What a </p><p> boundary meant before the editor rewrote it depends on the
        // element. A table's rows are separated by single newlines, so one newline is
        // the right guess there. A Callout's body is prose, where the boundary was a
        // blank line — the thing that separates one paragraph from the next — and
        // collapsing it would run two paragraphs together on every save.
        $body = preg_replace('/<\/p>\s*<p[^>]*>/i', $paragraphs ? "\n\n" : "\n", $body) ?? $body;
        $body = preg_replace('/<\/?p[^>]*>/i', '', $body) ?? $body;

        // These have no other meaning in a body: the editor writes them, nothing else.
        return str_replace(['&nbsp;', '&#124;', '&vert;'], [' ', '|', '|'], $body);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Append ' key="value"' to a string (used for element inline attrs).
     *
     * The value's own double quotes become &quot;, because an attribute is delimited by
     * double quotes and a raw one inside ends it early: a Callout titled Do not use
     * "force" came back as `Do not use `, with the rest of the shortcode's attributes
     * read as part of the title. attrs() decodes them again on the way back, so the
     * round trip is lossless and every element that writes free text through here — a
     * table caption, a code block's filename, a heading — is fixed by the same change.
     */
    protected static function attrI(string &$str, string $key, $value, $skip = null): void
    {
        if ($value === null || $value === '' || $value === $skip) {
            return;
        }
        $str .= ' '.$key.'="'.str_replace('"', '&quot;', (string) $value).'"';
    }

    /**
     * Append an attribute that must survive being cleared.
     *
     * attrI() drops an empty value, which is right for almost everything — an empty
     * attribute is noise. A title is the exception. The renderer falls back to the
     * variant's name only for a title that was never set, which is what a freshly
     * dropped element has, so clearing one has to be told apart from never having
     * written one. Dropped, every callout an author deliberately untitled grew its
     * title back the next time the page was saved.
     */
    protected static function attrKeepEmpty(string &$str, string $key, array $settings, string $settingKey): void
    {
        if (!array_key_exists($settingKey, $settings) || $settings[$settingKey] === null) {
            return;
        }

        if (trim((string) $settings[$settingKey]) === '') {
            $str .= ' '.$key.'=""';

            return;
        }

        self::attrI($str, $key, $settings[$settingKey]);
    }

    /**
     * A number an author may also leave empty.
     *
     * These fields are three-state: a number, or blank meaning "follow the preset".
     * Casting blindly would turn blank into 0 — a zero-width border rather than the
     * preset's — and leaving them as strings would make '6' come back where 6 went in,
     * which is the kind of difference that shows up as a spurious unsaved-changes
     * prompt rather than as anything visible.
     */
    protected static function numOrBlank($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::num($value);
    }

    /** Append to attr array (used for section/col attrs) */
    protected static function attr(array &$a, string $key, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $a[] = $key.'="'.$value.'"';
    }

    /** Append to attr array, skip if value equals $skip (default value) */
    protected static function attrIf(array &$a, string $key, $value, $skip): void
    {
        if ($value === null || $value === '' || $value === $skip) {
            return;
        }
        $a[] = $key.'="'.$value.'"';
    }

    /** Append _tablet/_mobile variant attrs for a responsive property */
    protected static function respAttr(array &$a, string $attrKey, array $s, string $sKey): void
    {
        foreach (['tablet', 'mobile'] as $dev) {
            $v = $s[$sKey.'_'.$dev] ?? null;
            if ($v !== null && $v !== '') {
                $a[] = $attrKey.'_'.$dev.'="'.$v.'"';
            }
        }
    }

    /**
     * Inject _tablet/_mobile responsive variants from parsed attrs into settings array.
     * defs: array of [settingsKey, attrKey, parser]  parser: null|'int'|'float'|'num'
     */
    protected static function addRespProps(array &$s, array $a, array $defs): void
    {
        foreach (['tablet', 'mobile'] as $dev) {
            foreach ($defs as $def) {
                [$sk, $ak, $parser] = $def;
                $raw = $a[$ak.'_'.$dev] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                if ($parser === 'int') {
                    $s[$sk.'_'.$dev] = (int) $raw;
                } elseif ($parser === 'float') {
                    $s[$sk.'_'.$dev] = (float) $raw;
                } elseif ($parser === 'num') {
                    $s[$sk.'_'.$dev] = self::num($raw);
                } else {
                    $s[$sk.'_'.$dev] = $raw;
                }
            }
        }
    }

    /**
     * Read ' key="value"' pairs back.
     *
     * &quot; is decoded here to undo what attrI() writes. Only that one entity: a value
     * may legitimately contain &amp; or &lt; — a code block's body attributes do — and
     * decoding those as well would change text nobody asked to change.
     */
    protected static function attrs(string $str): array
    {
        $out = [];
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $str, $m, PREG_SET_ORDER);
        foreach ($m as $pair) {
            $out[$pair[1]] = str_replace('&quot;', '"', $pair[2]);
        }

        return $out;
    }

    protected static function visibilityFromAttrs(array $a): array
    {
        return [
            'mobile' => ($a['hide_mobile'] ?? '') !== 'yes',
            'tablet' => ($a['hide_tablet'] ?? '') !== 'yes',
            'desktop' => ($a['hide_desktop'] ?? '') !== 'yes',
        ];
    }

    protected static function num($v)
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? ($v == (int) $v ? (int) $v : (float) $v) : $v;
    }

    protected static function uid(): string
    {
        return substr(md5(uniqid('', true)), 0, 9);
    }
}
