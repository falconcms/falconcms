<?php

namespace FalconCms\Core\Support;

/**
 * Strips everything script-bearing out of SVG markup.
 *
 * SVG is a document, not a picture: it can carry <script>, inline event handlers and
 * javascript: links, and once a file is in the media library it is served from the site's
 * own origin and embedded in pages every visitor loads. That is why SVG is not simply
 * allowed — a site that turns it on under Customizer → Performance → Allowed Upload
 * Formats gets the file rewritten through here before anything is written to disk.
 *
 * Used by every door into the media directory (upload, backup restore) and by the Section
 * Separator element's custom-shape field, so there is one implementation to audit rather
 * than one per caller. The Section Separator's canvas has a JS mirror of these same rules —
 * see sepSanitizeSvg() in resources/views/admin/falcon-builder/partials/scripts.blade.php.
 */
class SvgSanitizer
{
    /** Elements that can execute or fetch — never keep these. */
    private const FORBIDDEN = 'script|foreignObject|iframe|object|embed|handler';

    /**
     * Return the markup with everything executable removed.
     *
     * An empty string means the input held no usable <svg> element and the caller should
     * refuse it rather than write something it could not vet.
     */
    public static function clean(?string $svg): string
    {
        $svg = (string) $svg;
        if ($svg === '' || !preg_match('/<svg\b[\s\S]*<\/svg\s*>/i', $svg, $m)) {
            return '';
        }
        $svg = $m[0];

        // Executable / fetching elements, paired and self-closing.
        $svg = preg_replace('/<\s*('.self::FORBIDDEN.')\b[\s\S]*?<\/\s*\1\s*>/i', '', $svg);
        $svg = preg_replace('/<\s*('.self::FORBIDDEN.')\b[^>]*>/i', '', $svg);

        // Inline event handlers, in every quoting style.
        $svg = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $svg);
        $svg = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $svg);
        $svg = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/i', '', $svg);

        // javascript: in any link-ish attribute.
        $svg = preg_replace('/\s(?:xlink:)?href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', '', $svg);

        return trim((string) $svg);
    }
}
