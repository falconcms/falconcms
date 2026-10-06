<?php

/**
 * Uploads, import/export and sanitising.
 *
 * Loaded by src/helpers.php.
 */

use Illuminate\Http\Request;

if (!function_exists('falcon_max_upload_bytes')) {
    /**
     * The real maximum upload size (in bytes) the server allows — the smaller of
     * PHP's `upload_max_filesize` and `post_max_size`. Returns 0 when unlimited.
     */
    function falcon_max_upload_bytes(): int
    {
        $toBytes = function ($val): int {
            $val = trim((string) $val);
            if ($val === '') {
                return 0;
            }
            $last = strtolower($val[strlen($val) - 1]);
            $num = (int) $val;

            return match ($last) {
                'g' => $num * 1024 * 1024 * 1024,
                'm' => $num * 1024 * 1024,
                'k' => $num * 1024,
                default => $num,
            };
        };
        $limits = array_filter([
            $toBytes(ini_get('upload_max_filesize') ?: '0'),
            $toBytes(ini_get('post_max_size') ?: '0'), // 0 = unlimited → ignored
        ], fn ($v) => $v > 0);

        return $limits ? min($limits) : 0;
    }
}

if (!function_exists('falcon_max_upload_human')) {
    /** Human-readable version of falcon_max_upload_bytes() (e.g. "40 MB", "1 GB"). */
    function falcon_max_upload_human(): string
    {
        $bytes = falcon_max_upload_bytes();
        if ($bytes <= 0) {
            return 'unlimited';
        }
        if ($bytes >= 1024 * 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024 / 1024 / 1024, 1), '0'), '.').' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024).' MB';
        }

        return round($bytes / 1024).' KB';
    }
}

if (!function_exists('falcon_export_response')) {
    /** Stream a typed payload as a pretty .json download (used by all import/export tools). */
    function falcon_export_response(string $type, array $data, string $filename)
    {
        $payload = [
            '_type' => $type,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'data' => $data,
        ];
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '-', $filename) ?: 'export';

        return response()->json(
            $payload,
            200,
            ['Content-Disposition' => 'attachment; filename="'.$name.'.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}

if (!function_exists('falcon_read_import')) {
    /** Validate + decode an uploaded .json export of the given type. Returns the `data`, or null. */
    function falcon_read_import(Request $request, string $field, string $expectedType): ?array
    {
        if (!$request->hasFile($field)) {
            return null;
        }
        $file = $request->file($field);
        if (strtolower($file->getClientOriginalExtension()) !== 'json') {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (!is_array($decoded) || ($decoded['_type'] ?? null) !== $expectedType || !array_key_exists('data', $decoded)) {
            return null;
        }

        return is_array($decoded['data']) ? $decoded['data'] : null;
    }
}

if (!function_exists('falcon_safe_upload_extension')) {
    /**
     * Decide the extension an uploaded file may keep, for uploads that reach the public disk —
     * public form attachments especially. The front end's file inputs are only advisory
     * (a visitor can post straight past them), so the real gate is here.
     *
     * An allowlist, not a blocklist: anything not plainly a document, image, audio, video or
     * archive is refused, so a server-executable upload (.php, .phtml, .phar, .cgi, .pl, .sh,
     * .htaccess, a double extension like "x.php.jpg") can never land in a web-served folder.
     * Returns the lower-cased extension to store the file under, or null to reject it.
     *
     * Override the list with config('falcon-options.upload_extensions').
     */
    function falcon_safe_upload_extension($file): ?string
    {
        $default = [
            // documents
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
            'txt', 'csv', 'rtf',
            // images
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg', 'heic',
            // audio / video
            'mp3', 'wav', 'ogg', 'm4a', 'mp4', 'webm', 'mov', 'avi', 'mkv',
            // archives
            'zip', 'rar', '7z',
        ];
        $allowed = array_map('strtolower', (array) config('falcon-options.upload_extensions', $default));

        $name = is_object($file) && method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : (string) $file;

        // The LAST extension is what the web server runs the file as, so that is the one
        // checked — "invoice.php.jpg" is judged on "jpg", "shell.jpg.php" on "php".
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return null;
        }

        // Normalise the handful of extensions whose spelling varies.
        return match ($ext) {
            'jpeg' => 'jpg',
            default => $ext,
        };
    }
}

if (!function_exists('falcon_sanitize_html')) {
    /**
     * Strip dangerous HTML from user-supplied rich-text content.
     * Removes <script> blocks, on* event handlers, and javascript: URLs.
     * Safe structural HTML (<a>, <img>, <iframe>, etc.) is preserved.
     */
    function falcon_sanitize_html(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove <script> blocks entirely (opening tag + content + closing tag)
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);

        // Strip on* event handler attributes from every HTML tag.
        // [\s\/]+ allows both whitespace and / as attribute separator (e.g. <img/onerror=...>).
        $html = preg_replace_callback('/<[a-z][^>]*>/i', static function (array $m): string {
            return preg_replace('/[\s\/]+on[a-z][a-z0-9]*\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i', '', $m[0]);
        }, $html);

        // Replace javascript: protocol in href/src/action/formaction with safe #
        $html = preg_replace(
            '/(\b(?:href|src|action|formaction)\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/i',
            '$1#$2',
            $html
        );

        return $html;
    }
}

if (!function_exists('falcon_sanitize_builder_json')) {
    /**
     * Recursively walks every node of a decoded builder layout array and applies
     * falcon_sanitize_html() to every string value. Numbers, booleans, and null are
     * passed through unchanged. Call this on the decoded `layout` array before
     * json_encode-ing it to the database.
     */
    function falcon_sanitize_builder_json(mixed $node): mixed
    {
        if (is_array($node)) {
            return array_map('falcon_sanitize_builder_json', $node);
        }
        if (is_string($node)) {
            return falcon_sanitize_html($node);
        }

        return $node;
    }
}

if (!function_exists('falcon_safe_url')) {
    /**
     * Return $url only when it uses a safe scheme (http/https) or is a
     * relative/root-relative/protocol-relative path; blocks javascript:, data:,
     * vbscript: and similar. Use wherever a stored field value is placed into an
     * href/src to prevent stored XSS.
     */
    function falcon_safe_url($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        // Relative, root-relative, or protocol-relative URLs are fine.
        if ($url[0] === '/' || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return $url;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false || $scheme === '') {
            return $url; // e.g. "media/foo.jpg" — a relative path, no scheme
        }
        $scheme = strtolower($scheme);

        return ($scheme === 'http' || $scheme === 'https') ? $url : '';
    }
}

if (!function_exists('falcon_blocked_upload_extensions')) {
    /**
     * File extensions this CMS will never keep, whichever door they arrive through.
     *
     * Two kinds of thing are on the list. Most are executable or server-side scripts: a
     * file the web server would run rather than serve. The rest — svgz, xml, xsl, xslt —
     * are documents that can carry script, which matters because the media library is
     * shared and its files are embedded in pages every visitor loads, from the site's own
     * origin.
     *
     * SVG used to be on this list. It is now handled by falcon_sanitized_upload_extensions()
     * instead: a site decides whether to accept it under Customizer → Performance → Allowed
     * Upload Formats (it is off by default), and what is written to disk is the sanitised
     * markup rather than the file as uploaded. svgz stays here — it is gzipped, so the
     * sanitiser cannot read it.
     *
     * It lives here, once, because there is more than one way into the library: the upload
     * screen and the WordPress media importer both write to it. The importer used to keep
     * its own list, which had drifted to allow SVG.
     *
     * @return array<int, string>
     */
    function falcon_blocked_upload_extensions(): array
    {
        return apply_falcon_filters('falcon_blocked_upload_extensions', [
            // Executed by the server rather than served to the browser.
            'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
            'asp', 'aspx', 'jsp', 'js', 'cgi', 'pl', 'py', 'rb',
            'sh', 'bash', 'exe', 'bat', 'cmd', 'htaccess', 'htpasswd',
            // Documents that can carry script.
            'svgz', 'xml', 'xsl', 'xslt',
        ]);
    }
}

if (!function_exists('falcon_sanitized_upload_extensions')) {
    /**
     * Extensions that may be kept, but only after their contents have been rewritten.
     *
     * These are not blocked and not simply trusted either. A file with one of these
     * extensions is passed through FalconCms\Core\Support\SvgSanitizer before anything
     * reaches disk, and is refused outright if nothing usable survives — so an SVG in the
     * library cannot carry <script>, an event handler or a javascript: link, however it
     * arrived. Turning the format on at all is still a site decision, made under
     * Customizer → Performance → Allowed Upload Formats.
     *
     * @return array<int, string>
     */
    function falcon_sanitized_upload_extensions(): array
    {
        return apply_falcon_filters('falcon_sanitized_upload_extensions', ['svg']);
    }
}
