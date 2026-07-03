<?php

use Illuminate\Support\Facades\DB;

if (!defined('LAZY_CMS_VERSION')) {
    $__versionFile = __DIR__ . '/../version.json';
    $__versionData = file_exists($__versionFile) ? json_decode(file_get_contents($__versionFile), true) : [];
    define('LAZY_CMS_VERSION', $__versionData['version'] ?? '1.2.0');
    unset($__versionFile, $__versionData);
}

if (!function_exists('falcon_elem_resp_css')) {
    /**
     * Generate responsive @media CSS for a builder element.
     *
     * @param array  $s        Element settings (the raw array from $el['settings'])
     * @param int    $bpSm     "Small" breakpoint (mobile max-width)
     * @param int    $bpMed    "Medium" breakpoint (tablet max-width)
     * @param array  $props    Property definitions, each:
     *   [ 'prop' => 'fontSize', 'sel' => '.my-class',
     *     'unitProp' => 'fontSizeUnit',  // optional – key for the unit setting
     *     'css'      => 'font-size',     // optional – defaults to camelCase→kebab
     *   ]
     * @return string Raw CSS (no <style> tags).  Empty string when nothing changed.
     */
    function falcon_elem_resp_css(array $s, int $bpSm, int $bpMed, array $props): string
    {
        $bpSm1 = $bpSm + 1;
        $css   = '';

        foreach ([
            ['tablet', "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)"],
            ['mobile', "@media(max-width:{$bpSm}px)"],
        ] as [$dev, $mq]) {
            $bySel = [];

            foreach ($props as $p) {
                $prop     = $p['prop'];
                $sel      = $p['sel'];
                $unitProp = $p['unitProp'] ?? null;
                $cssProp  = $p['css']      ?? strtolower(preg_replace('/([A-Z])/', '-$1', $prop));

                // Resolve responsive value (mobile cascades through tablet)
                $val = null;
                if ($dev === 'mobile') {
                    if (isset($s[$prop . '_mobile']) && (string)$s[$prop . '_mobile'] !== '') {
                        $val = (string)$s[$prop . '_mobile'];
                    } elseif (isset($s[$prop . '_tablet']) && (string)$s[$prop . '_tablet'] !== '') {
                        $val = (string)$s[$prop . '_tablet'];
                    }
                } else {
                    if (isset($s[$prop . '_tablet']) && (string)$s[$prop . '_tablet'] !== '') {
                        $val = (string)$s[$prop . '_tablet'];
                    }
                }
                if ($val === null) continue;

                // Append unit when value is numeric and a unit property is declared
                if ($unitProp !== null && !preg_match('/[a-zA-Z%]/', $val)) {
                    $unit = $dev === 'mobile'
                        ? ($s[$unitProp . '_mobile'] ?? $s[$unitProp . '_tablet'] ?? $s[$unitProp] ?? 'px')
                        : ($s[$unitProp . '_tablet'] ?? $s[$unitProp] ?? 'px');
                    // Units must be safe CSS unit tokens only
                    $unit = preg_replace('/[^a-zA-Z%]/', '', (string)($unit ?: 'px')) ?: 'px';
                    $val .= $unit;
                }

                // Strip characters that could break out of a CSS/style-tag context
                $val = preg_replace('/[<>"\']/', '', $val);
                $bySel[$sel][] = "{$cssProp}:{$val}!important";
            }

            // Build @media block
            $block = '';
            foreach ($bySel as $sel => $rules) {
                $block .= "{$sel}{" . implode(';', $rules) . "}";
            }
            if ($block !== '') {
                $css .= "{$mq}{{$block}}";
            }
        }

        return $css;
    }
}

if (!function_exists('falcon_max_upload_bytes')) {
    /**
     * The real maximum upload size (in bytes) the server allows — the smaller of
     * PHP's `upload_max_filesize` and `post_max_size`. Returns 0 when unlimited.
     */
    function falcon_max_upload_bytes(): int {
        $toBytes = function ($val): int {
            $val = trim((string) $val);
            if ($val === '') return 0;
            $last = strtolower($val[strlen($val) - 1]);
            $num  = (int) $val;
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
    function falcon_max_upload_human(): string {
        $bytes = falcon_max_upload_bytes();
        if ($bytes <= 0)                    return 'unlimited';
        if ($bytes >= 1024 * 1024 * 1024)   return rtrim(rtrim(number_format($bytes / 1024 / 1024 / 1024, 1), '0'), '.') . ' GB';
        if ($bytes >= 1024 * 1024)          return round($bytes / 1024 / 1024) . ' MB';
        return round($bytes / 1024) . ' KB';
    }
}

if (!function_exists('falcon_export_response')) {
    /** Stream a typed payload as a pretty .json download (used by all import/export tools). */
    function falcon_export_response(string $type, array $data, string $filename) {
        $payload = [
            '_type'       => $type,
            'version'     => 1,
            'exported_at' => now()->toIso8601String(),
            'data'        => $data,
        ];
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '-', $filename) ?: 'export';
        return response()->json(
            $payload,
            200,
            ['Content-Disposition' => 'attachment; filename="' . $name . '.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}

if (!function_exists('falcon_read_import')) {
    /** Validate + decode an uploaded .json export of the given type. Returns the `data`, or null. */
    function falcon_read_import(\Illuminate\Http\Request $request, string $field, string $expectedType): ?array {
        if (!$request->hasFile($field)) return null;
        $file = $request->file($field);
        if (strtolower($file->getClientOriginalExtension()) !== 'json') return null;

        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (!is_array($decoded) || ($decoded['_type'] ?? null) !== $expectedType || !array_key_exists('data', $decoded)) {
            return null;
        }
        return is_array($decoded['data']) ? $decoded['data'] : null;
    }
}

if (!function_exists('falcon_sanitize_html')) {
    /**
     * Strip dangerous HTML from user-supplied rich-text content.
     * Removes <script> blocks, on* event handlers, and javascript: URLs.
     * Safe structural HTML (<a>, <img>, <iframe>, etc.) is preserved.
     */
    function falcon_sanitize_html(string $html): string {
        if ($html === '') return '';

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

if (!function_exists('lazy_sanitize_builder_json')) {
    /**
     * Recursively walks every node of a decoded builder layout array and applies
     * falcon_sanitize_html() to every string value. Numbers, booleans, and null are
     * passed through unchanged. Call this on the decoded `layout` array before
     * json_encode-ing it to the database.
     */
    function lazy_sanitize_builder_json(mixed $node): mixed {
        if (is_array($node)) {
            return array_map('lazy_sanitize_builder_json', $node);
        }
        if (is_string($node)) {
            return falcon_sanitize_html($node);
        }
        return $node;
    }
}

if (!function_exists('falcon_cms_installed_version')) {
    /**
     * The version actually installed by Composer (falls back to LAZY_CMS_VERSION).
     * Reading it live keeps the "update available" check accurate after every
     * `composer update`, even if the LAZY_CMS_VERSION constant isn't hand-bumped.
     */
    function falcon_cms_installed_version(): string
    {
        // version.json ships inside the package and is bumped on every release, so it is the most
        // reliable source of the installed *code* version. Composer's reported version can be a
        // pinned alias (path repositories) or otherwise out of step, so it is only a fallback.
        if (defined('LAZY_CMS_VERSION') && preg_match('/^v?\d+\.\d+\.\d+/', LAZY_CMS_VERSION)) {
            return ltrim(LAZY_CMS_VERSION, 'v');
        }

        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $v = \Composer\InstalledVersions::getPrettyVersion('falconcms/falconcms');
                if ($v) {
                    $clean = ltrim($v, 'v');
                    if (preg_match('/^\d+\.\d+\.\d+$/', $clean)) {
                        return $clean;
                    }
                }
            } catch (\Throwable $e) {}
        }

        return LAZY_CMS_VERSION;
    }
}

if (!function_exists('lazy_check_update')) {
    function lazy_check_update(bool $force = false): array
    {
        $cacheKey = 'falcon_cms_update_check';
        if (!$force && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $current = falcon_cms_installed_version();
        $result  = ['current' => $current, 'latest' => null, 'has_update' => false, 'url' => null, 'checked_at' => now()->toDateTimeString()];

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'LazyCMS/' . $current])
                ->get('https://repo.packagist.org/p2/falconcms/falconcms.json');

            if ($res->successful()) {
                $versions = $res->json('packages.falconcms/falconcms') ?? [];
                foreach ($versions as $v) {
                    $ver = ltrim($v['version'] ?? '', 'v');
                    if (preg_match('/^\d+\.\d+\.\d+$/', $ver)) {
                        $result['latest'] = $ver;
                        $result['url']    = 'https://packagist.org/packages/falconcms/falconcms';
                        break;
                    }
                }
            }
        } catch (\Exception $e) {}

        if (!$result['latest']) {
            try {
                // Try GitHub Releases first, fall back to Tags (tags exist even without formal releases)
                $gh = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/' . $current])
                    ->get('https://api.github.com/repos/falconcms/falconcms/releases/latest');
                if ($gh->successful() && $gh->json('tag_name')) {
                    $tag = ltrim($gh->json('tag_name'), 'v');
                    if ($tag) {
                        $result['latest'] = $tag;
                        $result['url']    = $gh->json('html_url');
                    }
                }
            } catch (\Exception $e) {}
        }

        if (!$result['latest']) {
            try {
                // Fall back to tags list when no formal GitHub Release exists
                $gh = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/' . $current])
                    ->get('https://api.github.com/repos/falconcms/falconcms/tags');
                if ($gh->successful()) {
                    foreach ($gh->json() ?? [] as $t) {
                        $tag = ltrim($t['name'] ?? '', 'v');
                        if (preg_match('/^\d+\.\d+\.\d+$/', $tag)) {
                            $result['latest'] = $tag;
                            $result['url']    = 'https://github.com/falconcms/falconcms/releases/tag/v' . $tag;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        if ($result['latest']) {
            $result['has_update'] = version_compare($result['latest'], $result['current'], '>');
        }

        cache()->put($cacheKey, $result, now()->addHours(6));
        return $result;
    }
}

if (!function_exists('_falcon_cms_options_store')) {
    function &_falcon_cms_options_store(): array
    {
        static $store = ['loaded' => false, 'data' => []];
        return $store;
    }
}

if (!function_exists('get_cms_option')) {
    function get_cms_option($key, $default = null)
    {
        try {
            $store = &_falcon_cms_options_store();

            // Bulk-load all settings on first call — 1 DB query per request
            if (!$store['loaded']) {
                $rows = DB::table('cms_settings')->get(['key', 'value']);
                foreach ($rows as $row) {
                    $store['data'][$row->key] = $row->value;
                }
                $store['loaded'] = true;
            }

            $localeKey = $key . '_' . app()->getLocale();

            if (array_key_exists($localeKey, $store['data'])) {
                return $store['data'][$localeKey] ?? $default;
            }
            if (array_key_exists($key, $store['data'])) {
                return $store['data'][$key] ?? $default;
            }
            return $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('update_cms_option')) {
    function update_cms_option($key, $value)
    {
        try {
            \Illuminate\Support\Facades\DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
            // Keep in-memory cache consistent within the same request
            $store = &_falcon_cms_options_store();
            $store['data'][$key] = $value;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('cms_timezone')) {
    /**
     * The CMS display/input timezone chosen in Settings → General.
     * Storage stays UTC; this is only used to render/interpret dates for the admin.
     */
    function cms_timezone(): string
    {
        try {
            $tz = get_cms_option('timezone');
            if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
                return $tz;
            }
        } catch (\Throwable $e) {
        }
        return config('app.timezone') ?: 'UTC';
    }
}

if (!function_exists('cms_now')) {
    /** Current time in the CMS timezone. Use for display and for building day-boundary queries. */
    function cms_now(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::now(cms_timezone());
    }
}

if (!function_exists('cms_date')) {
    /**
     * Format a datetime in the CMS timezone.
     * Accepts Carbon, DateTime, or a date string. Returns '—' for null/empty.
     */
    function cms_date($dt, string $format = 'M j, Y H:i'): string
    {
        if (!$dt) return '—';
        $c = $dt instanceof \Carbon\Carbon ? $dt : \Illuminate\Support\Carbon::parse($dt);
        return $c->timezone(cms_timezone())->format($format);
    }
}

if (!function_exists('lazy_timezone_list')) {
    /**
     * All PHP timezones grouped by region, each labelled with its CURRENT UTC offset
     * (e.g. "(UTC+06:00) Asia/Dhaka"). Offsets are computed live, so DST/changes stay correct.
     * @return array<string, array<string,string>>  region => [identifier => label]
     */
    function lazy_timezone_list(): array
    {
        $groups = [];
        foreach (timezone_identifiers_list() as $tz) {
            try {
                $offset = (new \DateTime('now', new \DateTimeZone($tz)))->getOffset();
            } catch (\Throwable $e) {
                continue;
            }
            $sign  = $offset < 0 ? '-' : '+';
            $abs   = abs($offset);
            $label = sprintf('(UTC%s%02d:%02d) %s', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60), $tz);
            $region = strpos($tz, '/') !== false ? explode('/', $tz)[0] : 'Other';
            $groups[$region][$tz] = $label;
        }
        return $groups;
    }
}

if (!function_exists('falcon_normalize_publish')) {
    /**
     * Normalise a save payload's publish fields:
     *  - interpret the incoming naive `published_at` in the CMS timezone and convert to UTC for storage,
     *  - then set `status` (scheduled vs published) from that UTC time on the server.
     * Keeps the DB in UTC while letting the admin work in their chosen timezone.
     */
    function falcon_normalize_publish(array $data): array
    {
        if (!empty($data['published_at'])) {
            try {
                $data['published_at'] = \Illuminate\Support\Carbon::parse($data['published_at'], cms_timezone())
                    ->utc()->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
            }
        }
        $data['status'] = \FalconCms\Core\Models\Post::resolveStatusForSchedule(
            $data['status'] ?? null,
            $data['published_at'] ?? null
        );
        return $data;
    }
}

if (!function_exists('get_custom_field')) {
    function get_custom_field($post, $fieldName, $default = null)
    {
        try {
            $postId = is_object($post) ? $post->id : $post;
            $value = DB::table('post_custom_field_values')
                ->join('custom_fields', 'post_custom_field_values.field_id', '=', 'custom_fields.id')
                ->where('post_custom_field_values.post_id', $postId)
                ->where('custom_fields.name', $fieldName)
                ->value('post_custom_field_values.value');
            return $value !== null ? $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('get_acpt_field')) {
    /**
     * Resolve a single ACPT / custom-field value for a post by the field's slug.
     * The "Field Slug" entered in the builder maps to the `custom_fields.name` column.
     * Returns the raw stored value, or $default when the field or its value is absent.
     *
     * Used by falcon_resolve_dynamic_value() for the `acpt_custom` dynamic source.
     */
    function get_acpt_field($post, string $slug, $default = '')
    {
        try {
            $postId = is_object($post) ? ($post->id ?? null) : $post;
            if (!$postId || $slug === '') return $default;

            $value = DB::table('post_custom_field_values')
                ->join('custom_fields', 'post_custom_field_values.field_id', '=', 'custom_fields.id')
                ->where('post_custom_field_values.post_id', $postId)
                ->where('custom_fields.name', $slug)
                ->value('post_custom_field_values.value');

            return $value !== null ? $value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('get_post_custom_fields')) {
    /**
     * Resolve ALL custom fields that apply to a post (by its type's field groups) as a
     * keyed array [field_name => value]. Data-driven: it reads the field DEFINITIONS and
     * VALUES from the database at call time, so adding/removing a field in a field group
     * is reflected automatically everywhere — including the REST API — with no code change.
     *
     * JSON-encoded values (repeaters, galleries, checkboxes, etc.) are decoded to arrays.
     * Fields with no value yet are returned as null so consumers always see the schema.
     */
    function get_post_custom_fields($post): array
    {
        try {
            $post = is_object($post) ? $post : \FalconCms\Core\Models\Post::find($post);
            if (!$post) return [];
            $type = $post->type;

            $groupIds = DB::table('custom_field_groups')
                ->where('is_active', 1)->get()
                ->filter(function ($g) use ($type) {
                    $rules = json_decode($g->rules ?? '', true);
                    $pt = is_array($rules) ? ($rules['post_type'] ?? null) : null;
                    return is_array($pt) ? in_array($type, $pt, true) : $pt === $type;
                })->pluck('id');

            if ($groupIds->isEmpty()) return [];

            $fields = DB::table('custom_fields')->whereIn('field_group_id', $groupIds)->orderBy('order')->get();
            $values = DB::table('post_custom_field_values')->where('post_id', $post->id)->pluck('value', 'field_id');

            $out = [];
            foreach ($fields as $f) {
                $raw = $values[$f->id] ?? null;
                if (is_string($raw) && strlen($raw) && in_array($raw[0], ['[', '{'], true)) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE) $raw = $decoded;
                }
                $out[$f->name] = $raw;
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_lazy_content')) {
    function get_lazy_content($content)
    {
        if (empty($content)) return '';
        // Check if it's builder shortcode format
        if (is_string($content) && \FalconCms\Core\Services\BuilderShortcodeConverter::isBuilderShortcode($content)) {
            $content = \FalconCms\Core\Services\BuilderShortcodeConverter::shortcodesToJson($content);
        }

        try {
            $layout = is_string($content) ? json_decode($content, true) : $content;
            
            if (!is_array($layout)) {
                // Classic / non-builder HTML: strip <script>, on* handlers and
                // javascript: URLs before output (builder layouts are already
                // sanitised per-element by the builder renderer).
                return do_lazy_shortcode(falcon_sanitize_html((string) $content));
            }

            $data = ['layout' => $layout];
            // Expose current post context so dynamic sources (feature image, author, etc.) —
            // including dynamic backgrounds on containers/columns — resolve to the viewed post.
            $cp = view()->getShared()['current_post'] ?? null;
            if ($cp && function_exists('_lazy_layout_post_context')) {
                $data += _lazy_layout_post_context($cp);
            }

            $rendered = view('falcon-cms::frontend.builder.render', $data)->render();
            return do_lazy_shortcode($rendered);
        } catch (\Exception $e) {
            \Log::error('Falcon Builder Error: ' . $e->getMessage());
            return do_lazy_shortcode(falcon_sanitize_html((string) $content));
        }
    }
}

if (!function_exists('_lazy_hex_to_rgba')) {
    function _lazy_hex_to_rgba(string $hex, float $opacity = 1): string
    {
        if (empty($hex) || $hex === 'transparent') return 'transparent';
        if (strpos($hex, 'rgba') !== false) return $hex;
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        [$r, $g, $b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
        return $opacity >= 1 ? "rgb({$r},{$g},{$b})" : "rgba({$r},{$g},{$b},{$opacity})";
    }
}

if (!function_exists('_lazy_parse_builder_layout')) {
    function _lazy_parse_builder_layout(string $raw): ?array
    {
        try {
            if (\FalconCms\Core\Services\BuilderShortcodeConverter::isBuilderShortcode($raw)) {
                $raw = \FalconCms\Core\Services\BuilderShortcodeConverter::shortcodesToJson($raw);
            }
            $layout = json_decode($raw, true);
            return is_array($layout) ? $layout : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('_lazy_render_layout')) {
    function _lazy_render_layout(array $layout): string
    {
        $data = ['layout' => $layout];

        // When a single post/page/product is being viewed, expose its data so the
        // dynamic Post elements (Content, Post Meta, Product Meta) placed inside a
        // Layout Builder section resolve to the current post — the same variables
        // the post-card renderer provides.
        $cp = view()->getShared()['current_post'] ?? null;
        if ($cp) {
            $data += _lazy_layout_post_context($cp);
        }

        $rendered = view('falcon-cms::frontend.builder.render', $data)->render();
        return do_lazy_shortcode($rendered);
    }
}

if (!function_exists('falcon_html_to_text')) {
    /**
     * Convert rendered HTML to clean plain text. Unlike strip_tags(), this first
     * removes <script>/<style>/<noscript> blocks *including their contents*, so
     * builder-injected CSS/JS never leaks out as visible text.
     */
    function falcon_html_to_text($html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}

if (!function_exists('_lazy_layout_post_context')) {
    /** Post-context variables consumed by the Post elements (mirrors the card renderer). */
    function _lazy_layout_post_context($post): array
    {
        if (!$post) return [];

        $img = $post->featured_image ?? null;
        if ($img && !str_starts_with((string) $img, 'http')) $img = asset('storage/' . $img);

        // Full, rendered content for the Content element (builder JSON → HTML, or classic).
        $fullContent = function_exists('get_lazy_content') ? get_lazy_content($post->content ?? '') : (string) ($post->content ?? '');
        $plain = trim(strip_tags($fullContent));
        $excerpt = $post->excerpt ?? (mb_strlen($plain) > 160 ? mb_substr($plain, 0, 160) . '…' : $plain);

        return [
            'post'              => $post,
            'postTitle'         => $post->title ?? '',
            'postContent'       => $fullContent,
            'postExcerpt'       => $excerpt,
            'postPublishedAt'   => $post->published_at ?? null,
            'postCreatedAt'     => $post->created_at ?? null,
            'postAuthor'        => optional($post->user)->name ?? '',
            'postFeaturedImage' => $img,
            'postPermalink'     => function_exists('get_falcon_permalink') ? get_falcon_permalink($post) : '#',
            'postCategories'    => $post->categories ?? collect(),
        ];
    }
}

if (!function_exists('_lazy_build_sticky_wrapper')) {
    /**
     * Build a sticky wrapper element around $content.
     * $settings is the settings array of the first sticky container/column.
     * $wrapperClass is the CSS class on the wrapper (e.g. falcon-builder-header).
     * $tag is the HTML tag (header|footer|div).
     */
    function _lazy_build_sticky_wrapper(string $content, array $settings, string $wrapperClass, string $tag): string
    {
        $offset    = (int)($settings['stickyOffset']  ?? 0);
        $zIndex    = (int)($settings['stickyZIndex']  ?? 100);
        $desktop   = ($settings['stickyDesktop'] ?? true) !== false;
        $tablet    = ($settings['stickyTablet']  ?? true) !== false;
        $mobile    = ($settings['stickyMobile']  ?? true) !== false;
        $bgColor   = $settings['stickyBgColor']        ?? '';
        $bgOpacity = (float)($settings['stickyBgColorOpacity'] ?? 1);

        $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
        $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
        $bpSm1 = $bpSm + 1;

        $sOn  = "position:sticky;top:{$offset}px;z-index:{$zIndex};";
        $sOff = "position:static;top:auto;z-index:auto;";

        $baseStyle    = ($tag === 'header') ? 'width:100%;' : '';
        $wrapperStyle = $baseStyle . ($desktop ? $sOn : '');

        $mediaCss = '';
        if ($tablet !== $desktop) {
            $rule = $tablet ? $sOn : $sOff;
            $mediaCss .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){.{$wrapperClass}{{$rule}}}";
        }
        if ($mobile !== $tablet) {
            $rule = $mobile ? $sOn : $sOff;
            $mediaCss .= "@media(max-width:{$bpSm}px){.{$wrapperClass}{{$rule}}}";
        }

        // Suppress per-container/column sticky — the wrapper handles positioning
        $css = ".{$wrapperClass} .lazy-container,.{$wrapperClass} .lazy-column{position:static!important;top:auto!important;}";
        $css .= $mediaCss;

        if (!empty($bgColor)) {
            $rgba = _lazy_hex_to_rgba($bgColor, $bgOpacity);
            $css .= ".{$wrapperClass}{transition:background-color 0.3s ease;}";
            $css .= ".lazy-sticky-active.{$wrapperClass}{background-color:{$rgba}!important;}";
        }

        // lazy-sticky-col → IntersectionObserver detects stuck state
        return "<{$tag} class=\"{$wrapperClass} lazy-sticky-col\" style=\"{$wrapperStyle}\">"
             . "<style>{$css}</style>"
             . $content
             . "</{$tag}>";
    }
}

if (!function_exists('_lazy_builder_render_wrapper')) {
    /**
     * Render header/footer builder content with correct sticky handling.
     *
     * Only the containers from the FIRST sticky container onwards are placed
     * inside the sticky wrapper. Containers before it render in a plain div so
     * they scroll away normally (e.g. a top-bar above a sticky nav).
     */
    function _lazy_builder_render_wrapper(string $raw, string $tag, string $wrapperClass): string
    {
        $layout = _lazy_parse_builder_layout($raw);

        if (!is_array($layout) || empty($layout)) {
            $content = get_lazy_content($raw);
            $style   = $tag === 'header' ? ' style="width:100%;"' : '';
            return "<{$tag} class=\"{$wrapperClass}\"{$style}>{$content}</{$tag}>";
        }

        // Find the index of the first sticky container (check container + column settings)
        $stickyIndex    = null;
        $stickySettings = null;
        foreach ($layout as $i => $container) {
            $cs = $container['settings'] ?? [];
            if (!empty($cs['sticky'])) {
                $stickyIndex    = $i;
                $stickySettings = $cs;
                break;
            }
            foreach ($container['columns'] ?? [] as $col) {
                $cls = $col['settings'] ?? [];
                if (!empty($cls['sticky'])) {
                    $stickyIndex    = $i;
                    $stickySettings = $cls;
                    break 2;
                }
            }
        }

        if ($stickySettings === null) {
            // Nothing sticky — simple wrapper
            $style = $tag === 'header' ? ' style="width:100%;"' : '';
            return "<{$tag} class=\"{$wrapperClass}\"{$style}>"
                 . _lazy_render_layout($layout)
                 . "</{$tag}>";
        }

        // Render containers BEFORE the first sticky one in a plain above-wrapper
        $html = '';
        if ($stickyIndex > 0) {
            $html .= '<div class="' . $wrapperClass . '-above" style="width:100%;">'
                   . _lazy_render_layout(array_slice($layout, 0, $stickyIndex))
                   . '</div>';
        }

        // Render sticky containers inside the sticky wrapper
        $stickyContent = _lazy_render_layout(array_slice($layout, $stickyIndex));
        $html .= _lazy_build_sticky_wrapper($stickyContent, $stickySettings, $wrapperClass, $tag);

        return $html;
    }
}

if (!function_exists('falcon_layout_context')) {
    /**
     * Request-scoped description of what the frontend is currently rendering,
     * used to decide which custom Layout (by its conditions) applies. The
     * frontend controllers set this before returning their view; the header/
     * footer resolvers read it while the view renders.
     *
     * Shape: ['kind' => 'home|single|archive|search', 'post_type' => ?, 'post_id' => ?, 'taxonomy' => ?]
     */
    function falcon_layout_context(?array $set = null): array
    {
        static $ctx = ['kind' => null];
        if ($set !== null) {
            $ctx = $set;
        }
        return $ctx;
    }
}

if (!function_exists('falcon_get_custom_layouts')) {
    /** All user-created custom layouts (name, conditions, per-slot assignments). */
    function falcon_get_custom_layouts(): array
    {
        $raw = get_cms_option('falcon_layouts', null);
        $layouts = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($layouts) ? array_values(array_filter($layouts, 'is_array')) : [];
    }
}

if (!function_exists('falcon_condition_target_matches')) {
    /** Does a single condition target match the current render context? */
    function falcon_condition_target_matches(string $target, array $ctx): bool
    {
        $kind = $ctx['kind'] ?? null;
        if ($target === 'entire_site')   return true;
        if ($target === 'home')          return $kind === 'home';
        if ($target === 'search')        return $kind === 'search';
        if ($target === '404')           return $kind === '404';
        if ($target === 'all_archives')  return $kind === 'archive';
        if ($target === 'author_archive')return $kind === 'archive' && ($ctx['archive_type'] ?? null) === 'author';
        if (str_starts_with($target, 'all:')) {
            // All singular items of a post type (the front page counts if it is one).
            return in_array($kind, ['single', 'home'], true) && ($ctx['post_type'] ?? null) === substr($target, 4);
        }
        if (str_starts_with($target, 'singular:')) { // legacy alias of all:
            return in_array($kind, ['single', 'home'], true) && ($ctx['post_type'] ?? null) === substr($target, 9);
        }
        if (str_starts_with($target, 'archive:')) {
            return $kind === 'archive' && ($ctx['post_type'] ?? null) === substr($target, 8);
        }
        if (str_starts_with($target, 'tax:')) {
            return $kind === 'archive' && ($ctx['taxonomy'] ?? null) === substr($target, 4);
        }
        if (str_starts_with($target, 'taxonomy:')) { // legacy alias of tax:
            return $kind === 'archive' && ($ctx['taxonomy'] ?? null) === substr($target, 9);
        }
        if (str_starts_with($target, 'term:')) {
            [$tax, $id] = array_pad(explode(':', substr($target, 5), 2), 2, null);
            return $kind === 'archive'
                && ($ctx['taxonomy'] ?? null) === $tax
                && (int) ($ctx['term_id'] ?? 0) === (int) $id;
        }
        if (str_starts_with($target, 'author:')) {
            return $kind === 'archive'
                && ($ctx['archive_type'] ?? null) === 'author'
                && (int) ($ctx['author_id'] ?? 0) === (int) substr($target, 7);
        }
        if (str_starts_with($target, 'post:')) {
            return (int) ($ctx['post_id'] ?? 0) === (int) substr($target, 5);
        }
        return false;
    }
}

if (!function_exists('falcon_normalize_conditions')) {
    /** Normalise stored conditions to a list of ['mode'=>'include|exclude','target'=>string]. */
    function falcon_normalize_conditions($conditions): array
    {
        $out = [];
        foreach ((array) $conditions as $c) {
            if (is_string($c)) {                       // legacy flat target => include
                $out[] = ['mode' => 'include', 'target' => $c];
            } elseif (is_array($c) && !empty($c['target'])) {
                $out[] = ['mode' => ($c['mode'] ?? 'include') === 'exclude' ? 'exclude' : 'include', 'target' => (string) $c['target']];
            }
        }
        return $out;
    }
}

if (!function_exists('falcon_layout_matches')) {
    /**
     * Does a layout apply to the given render context? A layout shows where at
     * least one INCLUDE condition matches and no EXCLUDE condition matches.
     * With no include conditions it applies nowhere.
     */
    function falcon_layout_matches($conditions, array $ctx): bool
    {
        $anyInclude = false;
        $includeMatched = false;
        foreach (falcon_normalize_conditions($conditions) as $c) {
            $matches = falcon_condition_target_matches($c['target'], $ctx);
            if ($c['mode'] === 'exclude') {
                if ($matches) return false;             // an exclusion always wins
            } else {
                $anyInclude = true;
                if ($matches) $includeMatched = true;
            }
        }
        return $anyInclude && $includeMatched;
    }
}

if (!function_exists('falcon_layout_assigned_section')) {
    /**
     * Resolve the section that fills a layout slot for the current request.
     *
     * Model ("Global = Pages, Custom = rest"):
     *   • On a PAGE (single 'page' / front page): the Global Layout's explicit
     *     assignment wins, then a matching custom layout, then (header/footer only)
     *     the first published section as a legacy fallback, else the theme default.
     *   • Everywhere else (posts, CPTs, archives, search): only a custom layout
     *     whose conditions match applies; otherwise the theme default (null).
     *
     * Returns a published Post or null (null ⇒ theme renders its own default).
     */
    function falcon_layout_assigned_section(string $slot, string $type)
    {
        $ctx = falcon_layout_context();
        $isPage = in_array($ctx['kind'] ?? null, ['single', 'home'], true)
            && ($ctx['post_type'] ?? null) === 'page';

        // Normalise a stored assignment (legacy int, or ['id','active']) into
        // ['id','active']; the 'active' flag is this layout's own on/off switch.
        $entryOf = function ($v) {
            if (is_array($v) && !empty($v['id'])) {
                return ['id' => (int) $v['id'], 'active' => !array_key_exists('active', $v) || (bool) $v['active']];
            }
            if (is_numeric($v) && (int) $v > 0) return ['id' => (int) $v, 'active' => true];
            return null;
        };
        $resolve = function ($entry) use ($type) {
            if (!$entry || !$entry['active']) return null;
            return \FalconCms\Core\Models\Post::where('id', $entry['id'])->where('type', $type)->first();
        };

        // 1) PAGE context → the Global Layout's assignment wins first (if active).
        if ($isPage) {
            $raw = get_cms_option('falcon_layout_global', null);
            $global = is_string($raw) ? json_decode($raw, true) : $raw;
            $global = is_array($global) ? $global : [];
            if (array_key_exists($slot, $global)) {
                if ($section = $resolve($entryOf($global[$slot]))) return $section;
                // assigned but this layout turned it off → fall through to custom.
            }
        }

        // 2) Custom layouts whose conditions match the current context.
        foreach (falcon_get_custom_layouts() as $layout) {
            $conditions = is_array($layout['conditions'] ?? null) ? $layout['conditions'] : [];
            $entry = $entryOf($layout['assignments'][$slot] ?? null);
            if ($entry && falcon_layout_matches($conditions, $ctx)) {
                if ($section = $resolve($entry)) return $section;
            }
        }

        // 3) Legacy fallback (PAGE + chrome only): keep the built header/footer
        //    showing on pages even when nothing has been explicitly assigned.
        if ($isPage && in_array($slot, ['header', 'footer'], true)) {
            return \FalconCms\Core\Models\Post::where('type', $type)
                    ->where('status', 'published')
                    ->where('lang_code', app()->getLocale())
                    ->first()
                ?: \FalconCms\Core\Models\Post::where('type', $type)
                    ->where('status', 'published')
                    ->first();
        }

        // 4) Theme default.
        return null;
    }
}

if (!function_exists('get_falcon_header')) {
    function get_falcon_header()
    {
        $header = falcon_layout_assigned_section('header', 'falcon_header');
        if ($header) {
            return _lazy_builder_render_wrapper($header->content ?? '', 'header', 'falcon-builder-header');
        }
        return null;
    }
}

if (!function_exists('get_falcon_footer')) {
    function get_falcon_footer()
    {
        $footer = falcon_layout_assigned_section('footer', 'falcon_footer');
        if ($footer) {
            return _lazy_builder_render_wrapper($footer->content ?? '', 'footer', 'falcon-builder-footer');
        }
        return null;
    }
}

if (!function_exists('get_falcon_page_title_bar')) {
    function get_falcon_page_title_bar()
    {
        $ptb = falcon_layout_assigned_section('page_title_bar', 'falcon_ptb');
        if ($ptb) {
            return _lazy_builder_render_wrapper($ptb->content ?? '', 'div', 'falcon-builder-ptb');
        }
        return null;
    }
}

if (!function_exists('get_falcon_content')) {
    function get_falcon_content()
    {
        $content = falcon_layout_assigned_section('content', 'falcon_content');
        if ($content) {
            return _lazy_builder_render_wrapper($content->content ?? '', 'div', 'falcon-builder-content');
        }
        return null;
    }
}

if (!function_exists('falcon_theme_view')) {
    /**
     * Resolve a theme view name for the active theme, mirroring the frontend
     * controller's resolution: app-level theme → package theme → falcon-theme
     * fallback. Usable anywhere (e.g. the 404 renderer).
     */
    function falcon_theme_view(string $view, ?string $fallback = null): string
    {
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        foreach (["themes.{$activeTheme}.{$view}", "falcon-cms::themes.{$activeTheme}.{$view}", "falcon-cms::themes.falcon-theme.{$view}"] as $candidate) {
            if (view()->exists($candidate)) return $candidate;
        }
        if ($fallback && $fallback !== $view) return falcon_theme_view($fallback);
        return "falcon-cms::themes.falcon-theme.{$view}";
    }
}

if (!function_exists('getUnitVal')) {
    function getUnitVal($val, $unit = 'px') {
        if ($val === null || $val === '') return null;
        if (is_numeric($val)) return $val . $unit;
        return $val;
    }
}

if (!function_exists('falcon_visit_page')) {
    /**
     * Human-friendly page label for an analytics visit URL.
     * Strips the scheme + host (works for raw-IP visits too) and shows just the path;
     * for the homepage (root) it shows the site domain instead of a bare "/".
     */
    function falcon_visit_page($url) {
        $path = preg_replace('#^https?://[^/]+#i', '', (string) $url);
        if ($path === '' || $path === '/') {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);
            if (empty($host) && function_exists('request')) {
                try { $host = request()->getHost(); } catch (\Throwable $e) { $host = ''; }
            }
            return $host ?: '/';
        }
        return $path;
    }
}

if (!function_exists('the_lazy_content')) {
    function the_lazy_content($content) { echo get_lazy_content($content); }
}

if (!function_exists('get_falcon_posts')) {
    function get_falcon_posts($args = []) {
        $defaults = [
            'post_type'        => 'post',
            'limit'            => 10,
            'offset'           => 0,
            'order'            => 'desc',
            'orderby'          => 'created_at',
            'status'           => 'published',
            'category'         => null,
            'category_exclude' => null,
            'tag'              => null,
            'tag_exclude'      => null,
            'has_categories'   => false,
            'has_tags'         => false,
            'author'           => null,
            'search'           => null,
            'post_id'          => null,
            'meta_key'         => null,
            'meta_value'       => null,
            'taxonomy_slug'    => null,
            'taxonomy_include' => null,
            'taxonomy_exclude' => null,
            'paginate'         => false,
            'page_name'        => 'page',
            'lang'             => null,
        ];
        $args = array_merge($defaults, $args);

        if ($args['post_type'] === 'any') {
            $query = \FalconCms\Core\Models\Post::query();
        } else {
            $query = \FalconCms\Core\Models\Post::where('type', $args['post_type']);
        }

        $lang = $args['lang'] ?: app()->getLocale();
        $query->where('lang_code', $lang);

        if ($args['status']) {
            if (is_array($args['status'])) {
                $query->whereIn('status', $args['status']);
            } else {
                $query->where('status', $args['status']);
            }
        }
        if ($args['category']) {
            $catSlugs = is_array($args['category']) ? $args['category'] : array_filter(explode(',', $args['category']));
            $query->whereHas('categories', function($q) use ($catSlugs) {
                $q->whereIn('slug', $catSlugs);
            });
        } elseif ($args['has_categories']) {
            if ($args['post_type'] === 'post') {
                $query->has('categories');
            } else {
                $query->has('taxonomyTerms');
            }
        }
        if ($args['category_exclude']) {
            $catExSlugs = is_array($args['category_exclude']) ? $args['category_exclude'] : array_filter(explode(',', $args['category_exclude']));
            if (!empty($catExSlugs)) {
                $query->whereDoesntHave('categories', function($q) use ($catExSlugs) {
                    $q->whereIn('slug', $catExSlugs);
                });
            }
        }
        if ($args['tag']) {
            $tagSlugs = is_array($args['tag']) ? $args['tag'] : array_filter(explode(',', $args['tag']));
            $query->whereHas('tags', function($q) use ($tagSlugs) {
                $q->whereIn('slug', $tagSlugs);
            });
        } elseif ($args['has_tags']) {
            if ($args['post_type'] === 'post') {
                $query->has('tags');
            } else {
                $query->has('taxonomyTerms');
            }
        }
        if ($args['tag_exclude']) {
            $tagExSlugs = is_array($args['tag_exclude']) ? $args['tag_exclude'] : array_filter(explode(',', $args['tag_exclude']));
            if (!empty($tagExSlugs)) {
                $query->whereDoesntHave('tags', function($q) use ($tagExSlugs) {
                    $q->whereIn('slug', $tagExSlugs);
                });
            }
        }
        if ($args['author']) {
            $query->where('user_id', $args['author']);
        }
        if ($args['search']) {
            $query->where('title', 'like', '%' . $args['search'] . '%');
        }
        if (!empty($args['post_id'])) {
            $ids = is_array($args['post_id']) ? $args['post_id'] : explode(',', $args['post_id']);
            $query->whereIn('id', array_filter(array_map('intval', $ids)));
        }
        if (!empty($args['taxonomy_slug'])) {
            $taxSlug = $args['taxonomy_slug'];
            if (!empty($args['taxonomy_include'])) {
                $include = is_array($args['taxonomy_include']) ? $args['taxonomy_include'] : explode(',', $args['taxonomy_include']);
                $query->whereHas('taxonomyTerms', function($q) use ($taxSlug, $include) {
                    $q->where('taxonomy_slug', $taxSlug)->whereIn('slug', array_filter($include));
                });
            } else {
                $query->whereHas('taxonomyTerms', function($q) use ($taxSlug) {
                    $q->where('taxonomy_slug', $taxSlug);
                });
            }
            if (!empty($args['taxonomy_exclude'])) {
                $exclude = is_array($args['taxonomy_exclude']) ? $args['taxonomy_exclude'] : explode(',', $args['taxonomy_exclude']);
                $query->whereDoesntHave('taxonomyTerms', function($q) use ($taxSlug, $exclude) {
                    $q->where('taxonomy_slug', $taxSlug)->whereIn('slug', array_filter($exclude));
                });
            }
        }

        if ($args['orderby'] === 'rand') {
            $query->inRandomOrder();
        } else {
            $safeOrderby = in_array($args['orderby'], ['created_at','updated_at','title','views','menu_order','id'])
                ? $args['orderby'] : 'created_at';
            $query->orderBy($safeOrderby, $args['order']);
        }

        if ($args['paginate']) {
            return $query->paginate($args['limit'], ['*'], $args['page_name'] ?? 'page');
        }
        return $query->limit($args['limit'])->offset((int)$args['offset'])->get();
    }
}

if (!function_exists('the_lazy_pagination')) {
    function the_lazy_pagination($items, $view = null) {
        if (!($items instanceof \Illuminate\Pagination\LengthAwarePaginator)) return '';
        return $items->links($view);
    }
}

if (!function_exists('the_lazy_loop')) {
    function the_lazy_loop($args = [], $view = 'falcon-cms::frontend.loop')
    {
        $posts = get_falcon_posts($args);
        echo view($view, ['posts' => $posts])->render();
    }
}

if (!function_exists('get_falcon_excerpt')) {
    function get_falcon_excerpt($post, $limit = 120)
    {
        $content = $post->content ?? '';
        $isBuilder = ($post->editor_type ?? '') === 'builder'
            || (is_string($content) && (str_starts_with(ltrim($content), '[') || str_starts_with(ltrim($content), '{')));

        if (!$isBuilder) {
            return \Illuminate\Support\Str::limit(strip_tags($content), $limit);
        }

        try {
            $layout = is_string($content) ? json_decode($content, true) : $content;
            if (!is_array($layout)) return '';

            $textTypes = ['title', 'heading', 'text', 'text_block', 'special_text'];
            $text = '';

            $extractFromElements = function (array $elements) use (&$text, &$limit, $textTypes, &$extractFromElements) {
                foreach ($elements as $el) {
                    $type = $el['type'] ?? '';
                    $s    = $el['settings'] ?? [];
                    if (in_array($type, ['title', 'heading'])) {
                        $text .= trim($s['title'] ?? '') . ' ';
                    } elseif (in_array($type, ['text', 'text_block', 'special_text'])) {
                        $text .= trim(strip_tags($s['content'] ?? '')) . ' ';
                    } elseif ($type === 'nested-row' && !empty($el['columns'])) {
                        foreach ($el['columns'] as $ncol) {
                            $extractFromElements($ncol['elements'] ?? []);
                            if (strlen($text) > $limit) return;
                        }
                    }
                    if (strlen($text) > $limit) return;
                }
            };

            foreach ($layout as $container) {
                foreach ($container['columns'] ?? [] as $column) {
                    $extractFromElements($column['elements'] ?? []);
                    if (strlen($text) > $limit) break 2;
                }
            }

            return \Illuminate\Support\Str::limit(trim($text) ?: '', $limit);
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('get_lazy_post')) {
    function get_lazy_post($slugOrId) {
        if (is_numeric($slugOrId)) return \FalconCms\Core\Models\Post::find($slugOrId);
        return \FalconCms\Core\Models\Post::where('slug', $slugOrId)->where('lang_code', app()->getLocale())->first();
    }
}

if (!function_exists('get_lazy_category_taxonomy')) {
    /**
     * Returns ['type' => 'native'|'product'|'acpt', 'taxonomy_slug' => string|null]
     * for the category taxonomy of a given post type.
     */
    function get_lazy_category_taxonomy($postType) {
        if (!$postType || $postType === 'post') {
            return ['type' => 'native', 'taxonomy_slug' => null];
        }
        if ($postType === 'product') {
            return ['type' => 'product', 'taxonomy_slug' => null];
        }
        // ACPT: find an active hierarchical (category) taxonomy for this CPT
        $row = \Illuminate\Support\Facades\DB::table('custom_taxonomies')
            ->where('hierarchical', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn($t) => in_array($postType, json_decode($t->post_types ?? '[]', true)));
        if (!$row) return ['type' => 'none', 'taxonomy_slug' => null];
        return ['type' => 'acpt', 'taxonomy_slug' => $row->slug];
    }
}

if (!function_exists('get_lazy_categories')) {
    function get_lazy_categories($taxonomy = 'category', $postType = null) {
        if ($taxonomy === 'category') {
            $info = get_lazy_category_taxonomy($postType);
            if ($info['type'] === 'native') {
                return \FalconCms\Core\Models\Category::withCount(['posts' => fn($r) => $r->where('status', 'published')])
                    ->orderBy('name')->get();
            }
            if ($info['type'] === 'product') {
                return \FalconCms\Core\Models\ProductCategory::withCount(['posts as posts_count' => fn($r) => $r->where('status', 'published')])
                    ->orderBy('name')->get();
            }
            if ($info['type'] === 'acpt') {
                return \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $info['taxonomy_slug'])
                    ->withCount(['posts as posts_count' => fn($q) => $q->where('status', 'published')])
                    ->orderBy('name')->get();
            }
            return collect();
        }
        return \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $taxonomy)
            ->withCount(['posts' => fn($q) => $q->where('status', 'published')])->get();
    }
}

if (!function_exists('get_lazy_menu')) {
    function get_lazy_menu($slugOrLocation) {
        $query = \FalconCms\Core\Models\NavigationMenu::query();
        
        if ($slugOrLocation === 'header') {
            $query->where('is_header', true);
        } elseif ($slugOrLocation === 'footer') {
            $query->where('is_footer', true);
        } else {
            $query->where('slug', $slugOrLocation);
        }

        $currentLocale = app()->getLocale();
        
        // Try to find menu with exact slug-locale if it's a slug
        if (!in_array($slugOrLocation, ['header', 'footer'])) {
            $langSlug = $slugOrLocation . '-' . $currentLocale;
            $menu = (clone $query)->where('slug', $langSlug)->first();
            if ($menu) return this_process_items($menu);
        }

        // Try to find by location AND lang_code
        $menu = (clone $query)->where('lang_code', $currentLocale)->first();
        
        if (!$menu) {
            // Fallback to location only without lang_code
            $menu = (clone $query)->whereNull('lang_code')->first();
        }

        if (!$menu) return collect();

        return this_process_items($menu);
    }
}

// Internal helper for menu processing (moved logic out of the main function for reuse)
if (!function_exists('this_process_items')) {
    function this_process_items($menu) {
        // Fetch active CPTs and Taxonomies to filter items
        $activePostTypes = \FalconCms\Core\Models\PostType::where('is_active', true)->pluck('slug')->toArray();
        $activeTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->pluck('slug')->toArray();

        // Built-in types are always active
        $activePostTypes[] = 'post';
        $activePostTypes[] = 'page';
        $activePostTypes[] = 'category'; // Default category
        $activePostTypes[] = 'custom';   // Custom links

        $items = $menu->items->filter(function($item) use ($activePostTypes, $activeTaxonomies) {
            // If it's a post/page/cpt item
            if (!in_array($item->type, ['category', 'custom'])) {
                return in_array($item->type, $activePostTypes);
            }
            // If it's a category/taxonomy item
            if ($item->type === 'category' && $item->object_id) {
                $term = \FalconCms\Core\Models\TaxonomyTerm::find($item->object_id);
                if ($term) return in_array($term->taxonomy_slug, $activeTaxonomies);
                $standardCat = \FalconCms\Core\Models\Category::find($item->object_id);
                return (bool) $standardCat;
            }
            return true;
        });

        $cleanItems = function($items) use (&$cleanItems) {
            return $items->map(function($item) use ($cleanItems) {
                $currentLocale = app()->getLocale();
                
                // If it's a post/page/cpt item, find translation
                if (!in_array($item->type, ['category', 'custom']) && $item->object_id) {
                    $post = \FalconCms\Core\Models\Post::find($item->object_id);
                    if ($post) {
                        // Find translation in current locale
                        if ($post->lang_code !== $currentLocale) {
                            $translation = $post->getTranslation($currentLocale);
                            if ($translation) {
                                $post = $translation;
                            }
                        }
                        $item->url = get_falcon_permalink($post);
                    }
                }

                // Recursively clean children
                if ($item->children && $item->children->count() > 0) {
                    $item->setRelation('children', $cleanItems($item->children));
                }

                return $item;
            });
        };

        return $cleanItems($items);
    }
}

if (!function_exists('is_lazy_homepage')) {
    function is_lazy_homepage($post) {
        if (!$post) return false;
        $homeId = (int) get_cms_option('home_page_id');
        if (!$homeId) return false;
        return ($post->id == $homeId || ($post->origin_id && $post->origin_id == $homeId));
    }
}

if (!function_exists('get_falcon_permalink')) {
    function get_falcon_permalink($post) {
        if (!$post) return '#';
        
        $type = is_array($post) ? ($post['type'] ?? 'product') : ($post->type ?? 'post');
        $slug = is_array($post) ? ($post['slug'] ?? '') : ($post->slug ?? '');
        $postLang = is_array($post) ? ($post['lang_code'] ?? 'en') : ($post->lang_code ?? 'en');
        
        // Homepage logic
        if (!is_array($post) && is_lazy_homepage($post)) {
            $homePageId = get_cms_option('home_page_id');
            // ... (rest of homepage logic)
        }

        // Find actual default language from DB
        $defaultLang = 'en';
        try {
            $dbDefault = \Illuminate\Support\Facades\DB::table('cms_languages')->where('is_default', true)->value('code');
            if ($dbDefault) $defaultLang = $dbDefault;
        } catch (\Exception $e) {}

        // Language prefix logic: If it's not the default language, we MUST add the prefix
        $langPrefix = ($postLang === $defaultLang) ? '' : '/' . $postLang;

        // Homepage check again for safety
        if (!is_array($post) && is_lazy_homepage($post)) {
            if ($postLang === $defaultLang) return url('/');
            return url($postLang);
        }

        if ($type === 'page') {
            return url($langPrefix . '/' . $slug);
        }
        return url($langPrefix . '/' . $type . '/' . $slug);
    }
}

if (!function_exists('clear_page_cache')) {
    function clear_page_cache() {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_geoip')) {
    /**
     * Resolve an IP address to geo/network details via ip-api.com, cached for 30 days.
     * Uses the Laravel HTTP client (with a timeout) rather than file_get_contents, which is
     * often disabled or blocked on production hosts. Returns null values when unresolved.
     *
     * @return array{country: ?string, country_code: ?string, city: ?string, region: ?string, isp: ?string}
     */
    function falcon_geoip(?string $ip): array
    {
        $empty = ['country' => null, 'country_code' => null, 'city' => null, 'region' => null, 'isp' => null];
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)
            || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
            return $empty;
        }
        return \Illuminate\Support\Facades\Cache::remember('falcon_geoip_' . md5($ip), now()->addDays(30), function () use ($ip, $empty) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(3)
                    ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,countryCode,city,regionName,isp']);
                if ($resp->ok() && $resp->json('status') === 'success') {
                    return [
                        'country'      => $resp->json('country'),
                        'country_code' => $resp->json('countryCode'),
                        'city'         => $resp->json('city'),
                        'region'       => $resp->json('regionName'),
                        'isp'          => $resp->json('isp'),
                    ];
                }
            } catch (\Throwable $e) {}
            return $empty;
        });
    }
}

if (!function_exists('falcon_log_activity')) {
    function falcon_log_activity($action, $description, $model = null, $properties = []) {
        try {
            $ip = request()->ip();
            $country = null;
            $countryCode = null;

            // Simple IP to Country Cache/Lookup
            if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
                try {
                    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,countryCode");
                    if ($response) {
                        $data = json_decode($response, true);
                        if ($data && $data['status'] === 'success') {
                            $country = $data['country'];
                            $countryCode = $data['countryCode'];
                        }
                    }
                } catch (\Exception $e) {}
            }

            return \FalconCms\Core\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->id : null,
                'description' => $description,
                'properties' => $properties,
                'ip_address' => $ip,
                'country' => $country,
                'country_code' => $countryCode,
                'user_agent' => request()->userAgent()
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('render_lazy_widgets')) {
    function render_lazy_widgets($area) {
        $currentLocale = app()->getLocale();
        $query = \FalconCms\Core\Models\Widget::forArea($area);
        
        // 1. Filter by lang_code
        $widgets = $query->where(function($q) use ($currentLocale) {
            $q->where('lang_code', $currentLocale)->orWhereNull('lang_code');
        })->get();

        $output = '';
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        foreach ($widgets as $widget) {
            // Resolution order mirrors FrontendController::resolveThemeView():
            // 1. Published theme widget (non-namespaced): resources/views/themes/{theme}/widgets/{type}
            // 2. Package theme widget (namespaced):       falcon-cms::themes.{theme}.widgets.{type}
            // 3. Package default widget (namespaced):     falcon-cms::frontend.widgets.{type}
            $publishedThemeWidget = "themes.{$activeTheme}.widgets.{$widget->type}";
            $packageThemeWidget   = "falcon-cms::themes.{$activeTheme}.widgets.{$widget->type}";
            $falconThemeWidget      = "falcon-cms::themes.falcon-theme.widgets.{$widget->type}";
            $defaultWidget        = "falcon-cms::frontend.widgets.{$widget->type}";

            if (view()->exists($publishedThemeWidget)) {
                $output .= view($publishedThemeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($packageThemeWidget)) {
                $output .= view($packageThemeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($falconThemeWidget)) {
                $output .= view($falconThemeWidget, ['widget' => $widget])->render();
            } elseif (view()->exists($defaultWidget)) {
                $output .= view($defaultWidget, ['widget' => $widget])->render();
            } else {
                // Fallback for custom HTML or simple text
                if ($widget->type === 'custom_html') {
                    $content = $widget->settings['content'] ?? '';
                    // Process Shortcodes if any system exists (placeholder for now)
                    $content = do_lazy_shortcode($content);

                    $output .= '<div class="widget mb-12">';
                    if ($widget->title) $output .= '<h4 class="widget-title">' . e($widget->title) . '</h4>';
                    $output .= $content;
                    $output .= '</div>';
                }
            }
        }
        return $output;
    }
}

// --- Hook System Helpers ---

if (!function_exists('add_falcon_action')) {
    function add_falcon_action($tag, $callback, $priority = 10) {
        \FalconCms\Core\Core\HookManager::getInstance()->addAction($tag, $callback, $priority);
    }
}

if (!function_exists('do_falcon_action')) {
    function do_falcon_action($tag, ...$args) {
        \FalconCms\Core\Core\HookManager::getInstance()->doAction($tag, ...$args);
    }
}

if (!function_exists('add_falcon_filter')) {
    function add_falcon_filter($tag, $callback, $priority = 10) {
        \FalconCms\Core\Core\HookManager::getInstance()->addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('apply_falcon_filters')) {
    function apply_falcon_filters($tag, $value, ...$args) {
        return \FalconCms\Core\Core\HookManager::getInstance()->applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('has_falcon_action')) {
    function has_falcon_action($tag) {
        return \FalconCms\Core\Core\HookManager::getInstance()->hasAction($tag);
    }
}

if (!function_exists('has_falcon_filter')) {
    function has_falcon_filter($tag) {
        return \FalconCms\Core\Core\HookManager::getInstance()->hasFilter($tag);
    }
}

if (!function_exists('lazy_render_product_field')) {
    function lazy_render_product_field(array $field): string
    {
        $type        = $field['type']          ?? 'text';
        $name        = $field['name']          ?? '';
        $label       = $field['label']         ?? '';
        $placeholder = $field['placeholder']   ?? '';
        $required    = !empty($field['required']);
        $value       = $field['value']         ?? '';
        $class       = $field['class']         ?? '';
        $rows        = (int)($field['rows']    ?? 3);
        $min         = $field['min']           ?? null;
        $max         = $field['max']           ?? null;
        $options     = $field['options']       ?? [];
        $hint        = $field['hint']          ?? '';

        // wrapper: HTML tag ('div', 'li', 'p', 'span', false = no wrapper)
        $wrapperTag   = $field['wrapper']       ?? 'div';
        $wrapperClass = $field['wrapper_class'] ?? 'mb-4';
        // extra attributes on the wrapper element (e.g. 'data-foo="bar"')
        $wrapperAttrs = $field['wrapper_attrs'] ?? '';

        $baseInput = 'w-full border border-gray-300 rounded-sm px-3 py-2 text-sm focus:outline-none focus:border-gray-500 ' . $class;
        $req       = $required ? ' required' : '';
        $reqStar   = $required ? '<span class="text-red-500 ml-0.5">*</span>' : '';

        $inner = '';

        switch ($type) {
            case 'textarea':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">' . e($label) . $reqStar . '</label>';
                }
                $inner .= '<textarea name="' . e($name) . '" rows="' . $rows . '" placeholder="' . e($placeholder) . '" class="' . e($baseInput) . '"' . $req . '>' . e($value) . '</textarea>';
                break;

            case 'select':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">' . e($label) . $reqStar . '</label>';
                }
                $inner .= '<select name="' . e($name) . '" class="' . e($baseInput) . '"' . $req . '>';
                if ($placeholder) {
                    $inner .= '<option value="">' . e($placeholder) . '</option>';
                }
                foreach ($options as $optVal => $optLabel) {
                    $selected = ($value == $optVal) ? ' selected' : '';
                    $inner .= '<option value="' . e($optVal) . '"' . $selected . '>' . e($optLabel) . '</option>';
                }
                $inner .= '</select>';
                break;

            case 'radio':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">' . e($label) . $reqStar . '</label>';
                }
                $inner .= '<div class="flex flex-wrap gap-3 mt-1">';
                foreach ($options as $optVal => $optLabel) {
                    $checked = ($value == $optVal) ? ' checked' : '';
                    $inner .= '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">';
                    $inner .= '<input type="radio" name="' . e($name) . '" value="' . e($optVal) . '"' . $checked . $req . ' class="accent-primary">';
                    $inner .= e($optLabel) . '</label>';
                }
                $inner .= '</div>';
                break;

            case 'checkbox':
                $checked = !empty($field['checked']) ? ' checked' : '';
                $inner .= '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">';
                $inner .= '<input type="checkbox" name="' . e($name) . '" value="' . e($value ?: '1') . '"' . $checked . ' class="accent-primary">';
                $inner .= e($label) . $reqStar . '</label>';
                break;

            case 'number':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">' . e($label) . $reqStar . '</label>';
                }
                $minAttr = $min !== null ? ' min="' . e($min) . '"' : '';
                $maxAttr = $max !== null ? ' max="' . e($max) . '"' : '';
                $inner .= '<input type="number" name="' . e($name) . '" value="' . e($value) . '" placeholder="' . e($placeholder) . '" class="' . e($baseInput) . '"' . $minAttr . $maxAttr . $req . '>';
                break;

            case 'hidden':
                return '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';

            case 'raw':
                // 'content' key — raw HTML, trusted developer input
                $inner = $field['content'] ?? '';
                break;

            default: // text, email, tel, url, date, etc.
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">' . e($label) . $reqStar . '</label>';
                }
                $minAttr = $min !== null ? ' minlength="' . e($min) . '"' : '';
                $maxAttr = $max !== null ? ' maxlength="' . e($max) . '"' : '';
                $inner .= '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="' . e($placeholder) . '" class="' . e($baseInput) . '"' . $minAttr . $maxAttr . $req . '>';
                break;
        }

        if ($hint) {
            $inner .= '<p class="text-xs text-gray-400 mt-1">' . e($hint) . '</p>';
        }

        // No wrapper
        if (!$wrapperTag) {
            return $inner;
        }

        $tag = preg_replace('/[^a-z0-9]/', '', strtolower($wrapperTag));
        return '<' . $tag . ($wrapperClass ? ' class="' . e($wrapperClass) . '"' : '') . ($wrapperAttrs ? ' ' . $wrapperAttrs : '') . '>'
            . $inner
            . '</' . $tag . '>';
    }
}

if (!function_exists('falcon_render_product_fields')) {
    function falcon_render_product_fields(array $fields): void
    {
        foreach ($fields as $field) {
            echo lazy_render_product_field($field);
        }
    }
}

if (!function_exists('falcon_render_item_custom_fields')) {
    /**
     * Render custom fields for a cart session item (array) or an OrderItem model.
     * Context labels can be overridden via the falcon_custom_field_labels filter.
     *
     * @param  array|object  $item      Cart array item OR OrderItem model
     * @param  string        $context   'cart' | 'checkout' | 'confirmation' | 'admin'
     * @param  string        $wrapClass CSS class on the wrapper div
     * @return string
     */
    function falcon_render_item_custom_fields($item, string $context = 'cart', string $wrapClass = 'mt-1.5 space-y-0.5'): string
    {
        // Support both cart session array and OrderItem model
        if (is_array($item)) {
            $customFields = $item['meta']['custom_fields'] ?? [];
        } else {
            $meta = is_array($item->meta) ? $item->meta : (json_decode($item->meta ?? '{}', true) ?? []);
            $customFields = $meta['custom_fields'] ?? [];
        }

        $customFields = apply_falcon_filters('lazy_item_custom_fields_display', $customFields, $item, $context);

        if (empty($customFields)) return '';

        // Allow label overrides via filter
        $labels = apply_falcon_filters('falcon_custom_field_labels', [], $context);

        $html = '<div class="' . e($wrapClass) . '">';
        foreach ($customFields as $key => $value) {
            if ((string)$value === '') continue;
            $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
            $html .= '<div class="text-[11px] text-gray-500 leading-snug">'
                   . '<span class="font-semibold text-gray-700">' . e($label) . ':</span> '
                   . e($value)
                   . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('falcon_normalize_custom_fields')) {
    /**
     * Normalize a custom builder element definition (registered via falcon_builder_elements)
     * into a flat, keyed fields array the builder + frontend can consume consistently.
     *
     * Handles both the legacy `fields` map and the Avada-style indexed `params` array,
     * auto-generates param_name from heading, normalizes type aliases, and preserves
     * extended keys (condition, options, unit, step, fields/params for repeaters, etc.).
     *
     * @return array<string,array> keyed by field key
     */
    function falcon_normalize_custom_fields(array $custEl): array
    {
        $typeMap = [
            'textfield'        => 'text',
            'colorpickeralpha' => 'color',
            'colorpicker'      => 'color',
            'textarea_html'    => 'wysiwyg',
        ];

        // suffix → apply_as + base stripping (shared with the array param_name sugar)
        $suffixAs = ['_hover_color' => 'hover_color', '_hover_bg' => 'hover_bg', '_color' => 'color', '_bg' => 'bg', '_typo' => '', '_pad' => 'padding', '_margin' => 'margin'];
        $stripBase = function ($k) use ($suffixAs) {
            foreach ($suffixAs as $suf => $as) {
                if (str_ends_with($k, $suf)) return [substr($k, 0, -strlen($suf)), $as];
            }
            return [$k, ''];
        };

        $slug = fn($t) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($t)), '_');
        $contentTypes = ['text','textfield','textarea','wysiwyg','image','media','icon','button','repeater'];

        // Pre-scan content-field keys so array param_name sugar can avoid colliding with them
        $contentKeys = [];
        foreach (($custEl['params'] ?? []) as $p) {
            if (!in_array($p['type'] ?? 'text', $contentTypes, true)) continue;
            $pn = $p['param_name'] ?? null;
            $ck = is_array($pn) ? ($pn[0] ?? null) : $pn;
            if (!$ck && !empty($p['heading'])) $ck = $slug($p['heading']);
            if ($ck) $contentKeys[] = $ck;
        }

        $autoKey = function ($p) use ($slug) {
            $pn = $p['param_name'] ?? null;
            if (is_array($pn) && !empty($pn)) return $pn[0];
            if (!empty($pn)) return $pn;
            if (!empty($p['heading'])) return $slug($p['heading']);
            return null;
        };

        // Start from legacy fields map (already keyed)
        $fields = $custEl['fields'] ?? [];

        foreach (($custEl['params'] ?? []) as $p) {
            $key = $autoKey($p);
            if (!$key) continue;
            $rawType = $p['type'] ?? 'text';

            // Array param_name → sugar for relating one field to many targets
            $applyTo = $p['apply_to'] ?? null;
            $applyAs = $p['apply_as'] ?? null;
            if (is_array($p['param_name'] ?? null) && !empty($p['param_name'])) {
                $entries = $p['param_name'];
                [$b0, $as0] = $stripBase($entries[0]);
                if ($b0 !== $entries[0]) {
                    // Suffixed entries (e.g. title_color) → first is the storage key, strip suffix for targets
                    if ($applyAs === null) $applyAs = $as0;
                    if ($applyTo === null) $applyTo = array_map(fn($k) => $stripBase($k)[0], $entries);
                } else {
                    // Bare target names (e.g. ['title','subtitle']) → synthesise a non-colliding storage key
                    $base = !empty($p['heading']) ? $slug($p['heading']) : ('cf_' . substr(md5(implode(',', $entries)), 0, 6));
                    while (in_array($base, $contentKeys, true)) $base .= '_x';
                    $key = $base;
                    if ($applyTo === null) $applyTo = $entries;
                    if ($applyAs === null) {
                        $nt = $typeMap[$rawType] ?? $rawType;
                        $applyAs = $nt === 'dimensions' ? 'padding' : ($nt === 'color' ? 'color' : '');
                    }
                }
            }

            $fields[$key] = [
                'type'        => $typeMap[$rawType] ?? $rawType,
                'raw_type'    => $rawType,
                'label'       => $p['heading'] ?? $key,
                'default'     => $p['value'] ?? '',
                'tab'         => $p['tab'] ?? 'general',
                'placeholder' => $p['placeholder'] ?? '',
                'description' => $p['description'] ?? '',
                'options'     => $p['options'] ?? [],
                'rows'        => $p['rows'] ?? null,
                'min'         => $p['min'] ?? null,
                'max'         => $p['max'] ?? null,
                'step'        => $p['step'] ?? null,
                'unit'        => $p['unit'] ?? '',
                'condition'   => $p['condition'] ?? null,
                'dynamic'     => $p['dynamic'] ?? false,
                'apply_to'    => $applyTo,
                'apply_as'    => $applyAs,
                // repeater sub-fields (either key works)
                'fields'      => $p['fields'] ?? [],
                'params'      => $p['params'] ?? [],
            ];
        }

        return $fields;
    }
}

if (!function_exists('falcon_resolve_dynamic_value')) {
    /**
     * Resolve a dynamic-source key to a real value using the current post context.
     * $config may include: date_type, date_format, before, after, fallback,
     *                      excerpt_length, acpt_slug
     */
    function falcon_resolve_dynamic_value(string $source, $post = null, array $config = [])
    {
        if ($post === null) {
            try { $shared = view()->getShared(); $post = $shared['post'] ?? null; } catch (\Throwable $e) {}
        }

        $before   = $config['before']   ?? '';
        $after    = $config['after']    ?? '';
        $fallback = $config['fallback'] ?? '';

        $val = '';
        switch ($source) {
            case 'site_name':
            case 'site_title':
                $val = function_exists('get_cms_option') ? (string) get_cms_option('site_title', get_cms_option('site_name', config('app.name', ''))) : config('app.name', '');
                break;
            case 'site_tagline':
                $val = function_exists('get_cms_option') ? (string) get_cms_option('site_description', '') : '';
                break;
            case 'site_url':
                $val = function_exists('url') ? url('/') : config('app.url', '');
                break;
            case 'post_title':
                $val = $post->title ?? '';
                break;
            case 'post_url':
                $val = ($post && function_exists('get_falcon_permalink')) ? get_falcon_permalink($post) : ($post->slug ?? '');
                break;
            case 'post_excerpt':
                if (!$post) break;
                $ex = $post->excerpt ?? '';
                if (!$ex && function_exists('get_falcon_excerpt')) $ex = get_falcon_excerpt($post);
                $length = max(1, (int)($config['excerpt_length'] ?? 150));
                if (!$ex) {
                    $raw = strip_tags($post->content ?? '');
                    $rawTrimmed = ltrim($raw);
                    if ($rawTrimmed && $rawTrimmed[0] !== '[' && $rawTrimmed[0] !== '{') {
                        $ex = \Illuminate\Support\Str::limit($rawTrimmed, $length);
                    }
                } else {
                    $ex = \Illuminate\Support\Str::limit(strip_tags($ex), $length);
                }
                $val = $ex ? strip_tags($ex) : '';
                break;
            case 'post_date':
                $dateType   = $config['date_type']   ?? 'published';
                $dateFormat = $config['date_format']  ?? 'M j, Y';
                if (!$dateFormat) $dateFormat = 'M j, Y';
                $d = $dateType === 'modified'
                    ? ($post->updated_at ?? null)
                    : ($post->published_at ?? $post->created_at ?? null);
                $val = ($post && $d) ? \Carbon\Carbon::parse($d)->format($dateFormat) : '';
                break;
            case 'post_reading_time':
                if (!$post) break;
                $words = str_word_count(strip_tags($post->content ?? ''));
                $val = max(1, (int)ceil($words / 200)) . ' min read';
                break;
            case 'post_id':
                $val = $post ? (string)($post->id ?? '') : '';
                break;
            case 'post_type':
                $val = $post->type ?? '';
                break;
            case 'post_comment_count':
                if (!$post) break;
                $val = (string)(isset($post->comments_count) ? $post->comments_count : (method_exists($post, 'comments') ? $post->comments()->count() : 0));
                break;
            case 'post_author':
            case 'author_name':
                $val = $post->user->name ?? ($post->author->name ?? '');
                break;
            case 'author_bio':
                $val = $post->user->bio ?? ($post->user->description ?? '');
                break;
            case 'author_url':
                $val = '';
                break;
            case 'author_avatar':
                $av = $post->user->avatar ?? ($post->user->profile_photo_url ?? '');
                if ($av && !str_starts_with($av, 'http') && !str_starts_with($av, '/storage')) {
                    $av = '/storage/' . ltrim($av, '/');
                }
                $val = $av;
                break;
            case 'featured_image':
            case 'feature_image':
                if (!$post) break;
                $img = $post->featured_image ?? $post->thumbnail ?? '';
                if ($img && !str_starts_with($img, 'http') && !str_starts_with($img, '/storage')) {
                    $img = '/storage/' . ltrim($img, '/');
                }
                $val = $img;
                break;
            case 'logo':
            case 'site_logo':
                $logo = get_cms_option('theme_site_logo', '');
                if ($logo && !str_starts_with($logo, 'http') && !str_starts_with($logo, '/storage')) {
                    $logo = '/storage/' . ltrim($logo, '/');
                }
                $val = $logo;
                break;
            case 'current_date':
                $dateFormat = $config['date_format'] ?? 'M j, Y';
                if (!$dateFormat) $dateFormat = 'M j, Y';
                $val = now()->format($dateFormat);
                break;
            case 'current_year':
                $val = now()->format('Y');
                break;
            case 'user_name':
                $val = auth()->check() ? (auth()->user()->name ?? '') : '';
                break;
            case 'acpt_custom':
                $slug = $config['acpt_slug'] ?? '';
                if ($slug) {
                    $val = falcon_resolve_dynamic_value('acpt_' . $slug, $post);
                }
                break;
            case 'product_price': {
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) break;
                $sale = $sd->sale_price;
                $saleActive = ($sale !== null && $sale !== '' && (empty($sd->sale_ends_at) || \Carbon\Carbon::parse($sd->sale_ends_at)->isFuture()));
                $price = $saleActive ? (float) $sale : (float) ($sd->price ?? 0);
                $val = function_exists('falcon_price_format') ? falcon_price_format($price) : number_format($price, 2);
                break;
            }
            case 'product_regular_price': {
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) break;
                $price = (float) ($sd->price ?? 0);
                $val = function_exists('falcon_price_format') ? falcon_price_format($price) : number_format($price, 2);
                break;
            }
            case 'product_sale_price': {
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) break;
                $sale = $sd->sale_price;
                $saleActive = ($sale !== null && $sale !== '' && (empty($sd->sale_ends_at) || \Carbon\Carbon::parse($sd->sale_ends_at)->isFuture()));
                if ($saleActive) {
                    $val = function_exists('falcon_price_format') ? falcon_price_format((float) $sale) : number_format((float) $sale, 2);
                }
                break;
            }
            case 'product_sku':
                $val = ($post && $post->shopData) ? (string) ($post->shopData->sku ?? '') : '';
                break;
            case 'product_stock_status': {
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) break;
                $isOut = ($sd->stock_status ?? 'instock') === 'outofstock'
                      || (($sd->manage_stock ?? false) && (int) ($sd->stock_quantity ?? 0) <= 0);
                $val = $isOut ? 'Out of stock' : 'In stock';
                break;
            }
            case 'product_stock_quantity':
                $val = ($post && $post->shopData && $post->shopData->stock_quantity !== null)
                    ? (string) (int) $post->shopData->stock_quantity : '';
                break;
            default:
                if (str_starts_with($source, 'acpt_') && $post) {
                    $acptSlug = substr($source, 5);
                    if (function_exists('get_acpt_field')) {
                        $acptVal = get_acpt_field($post->id ?? null, $acptSlug);
                        $val = is_string($acptVal) ? $acptVal : '';
                    }
                }
                break;
        }

        if ($val === '' || $val === null) {
            return $fallback;
        }
        return $before . $val . $after;
    }
}

if (!function_exists('lazy_apply_custom_dynamic')) {
    /**
     * Replace any `{key}_dynamic` setting with the resolved value into `{key}`,
     * so both custom templates and the generic renderer receive final values.
     */
    function lazy_apply_custom_dynamic(array $settings, $post = null): array
    {
        $config = [
            'date_type'      => $settings['dynamic_date_type']      ?? 'published',
            'date_format'    => $settings['dynamic_date_format']     ?? '',
            'before'         => $settings['dynamic_before']          ?? '',
            'after'          => $settings['dynamic_after']           ?? '',
            'fallback'       => $settings['dynamic_fallback']        ?? '',
            'excerpt_length' => (int)($settings['dynamic_excerpt_length'] ?? 150),
            'acpt_slug'      => $settings['dynamic_acpt_slug']       ?? '',
        ];
        foreach ($settings as $k => $v) {
            if (is_string($k) && str_ends_with($k, '_dynamic') && !empty($v)) {
                $base = substr($k, 0, -strlen('_dynamic'));
                $settings[$base] = falcon_resolve_dynamic_value($v, $post, $config);
            }
        }
        return $settings;
    }
}

if (!function_exists('lazy_resolve_tokens')) {
    function lazy_resolve_tokens(string $value, $post = null): string {
        if (strpos($value, '{lazy:') === false) return $value;
        return preg_replace_callback('/\{lazy:([^}]+)\}/', function ($m) use ($post) {
            return lazy_resolve_token($m[1], $post);
        }, $value);
    }
}

if (!function_exists('lazy_resolve_token')) {
    function lazy_resolve_token(string $token, $post = null): string {
        if ($post === null) {
            try { $post = view()->getShared()['post'] ?? null; } catch (\Throwable $e) {}
        }
        switch ($token) {
            case 'post_title':
                return $post->title ?? '';
            case 'post_excerpt':
                if (!$post) return '';
                $ex = $post->excerpt ?? '';
                if (!$ex && function_exists('get_falcon_excerpt')) $ex = get_falcon_excerpt($post);
                if (!$ex) {
                    $raw = strip_tags($post->content ?? '');
                    $rawTrimmed = ltrim($raw);
                    if ($rawTrimmed && $rawTrimmed[0] !== '[' && $rawTrimmed[0] !== '{') {
                        $ex = \Illuminate\Support\Str::limit($rawTrimmed, 150);
                    }
                }
                return $ex ? strip_tags($ex) : '';
            case 'post_id':
                return (string)($post->id ?? '');
            case 'post_date':
                $d = $post->created_at ?? ($post->published_at ?? null);
                return $d ? \Carbon\Carbon::parse($d)->format('M j, Y') : '';
            case 'post_type':
                return $post->type ?? '';
            case 'post_permalink':
                if (!$post) return '';
                return function_exists('get_falcon_permalink') ? get_falcon_permalink($post) : ($post->slug ?? '#');
            case 'post_reading_time':
                if (!$post) return '';
                return max(1, (int) ceil(str_word_count(strip_tags($post->content ?? '')) / 200)) . ' min read';
            case 'site_title':
                return function_exists('get_cms_option') ? (string) get_cms_option('site_title', config('app.name', '')) : config('app.name', '');
            case 'site_tagline':
                return function_exists('get_cms_option') ? (string) get_cms_option('site_description', '') : '';
            case 'current_date':
                return now()->format('M j, Y');
            case 'current_year':
                return now()->format('Y');
            case 'author_name':
                return $post?->user?->name ?? ($post?->author?->name ?? '');
            case 'user_name':
                return auth()->check() ? auth()->user()->name : '';
            default:
                if (str_starts_with($token, 'acpt_') && $post) {
                    $slug = substr($token, 5);
                    try {
                        $meta = \FalconCms\Core\Models\PostMeta::where('post_id', $post->id)
                            ->where('meta_key', $slug)->first();
                        if ($meta) return (string) $meta->meta_value;
                    } catch (\Throwable $e) {}
                }
                return '';
        }
    }
}

if (!function_exists('falcon_resolve_tokens_in_settings')) {
    function falcon_resolve_tokens_in_settings(array $settings, $post = null): array {
        foreach ($settings as $k => &$v) {
            if (is_string($v) && strpos($v, '{lazy:') !== false) {
                $v = lazy_resolve_tokens($v, $post);
            } elseif (is_array($v)) {
                $v = falcon_resolve_tokens_in_settings($v, $post);
            }
        }
        return $settings;
    }
}

if (!function_exists('lazy_custom_element_render')) {
    /**
     * Build the convention-based render data for a custom element — the PHP mirror of the
     * builder canvas (getCustomElementRender). Used by the generic frontend renderer so the
     * front-end output matches the canvas preview 1:1 (incl. prefix relations + hover).
     *
     * Returns: ['wrapperStyle' => string, 'wrapperHoverClass' => string,
     *           'hoverCss' => string, 'items' => [ {kind,key,value,style,hoverClass, url?,target?, rows?,subFields?} ]]
     */
    function lazy_custom_element_render(array $el, array $customDef): array
    {
        $s    = $el['settings'] ?? [];
        $elId = $el['id'] ?? uniqid('ce');
        $fields = falcon_normalize_custom_fields($customDef); // keyed, ordered
        $contentTypes = ['text','textarea','wysiwyg','image','media','icon','button','repeater','date','number','slider','select','radio','checkbox','url','link'];
        // A field renders as content unless it's a design modifier (align select/radio, or an apply_to relation).
        $isContent = function (string $k, array $f) use ($contentTypes): bool {
            if (!in_array($f['type'], $contentTypes, true)) return false;
            if (in_array($f['type'], ['select','radio'], true) && str_ends_with($k, '_align')) return false;
            if (!empty($f['apply_to'])) return false;
            return true;
        };

        $contentKeys = [];
        foreach ($fields as $k => $f) {
            if ($isContent($k, $f)) $contentKeys[] = $k;
        }

        $unit = fn($v) => (is_numeric($v) ? $v . 'px' : $v);

        // typography CSS decls from a prefix
        $typoFor = function (string $tp) use ($s, $unit): array {
            $css = [];
            if (!empty($s[$tp . '_family']) && $s[$tp . '_family'] !== 'inherit') $css[] = 'font-family:' . $s[$tp . '_family'];
            if (!empty($s[$tp . '_size']))   $css[] = 'font-size:' . $unit($s[$tp . '_size']);
            if (!empty($s[$tp . '_weight'])) $css[] = 'font-weight:' . $s[$tp . '_weight'];
            if (!empty($s[$tp . '_line_height'])) $css[] = 'line-height:' . $s[$tp . '_line_height'];
            if (isset($s[$tp . '_letter_spacing']) && $s[$tp . '_letter_spacing'] !== '') $css[] = 'letter-spacing:' . $unit($s[$tp . '_letter_spacing']);
            if (!empty($s[$tp . '_transform']) && $s[$tp . '_transform'] !== 'none') $css[] = 'text-transform:' . $s[$tp . '_transform'];
            return $css;
        };

        // T/R/B/L shorthand from a prefix, or null
        $edgesFor = function (string $prefix) use ($s): ?string {
            $edges = []; $has = false;
            foreach (['top','right','bottom','left'] as $side) {
                $v = $s[$prefix . '_' . $side] ?? '';
                if ($v === '' || $v === null) { $edges[] = '0'; }
                else { $edges[] = $v . ($s[$prefix . '_' . $side . '_unit'] ?? 'px'); $has = true; }
            }
            return $has ? implode(' ', $edges) : null;
        };

        // Assemble inline CSS for a base from its prefix-related modifiers
        $styleFor = function (string $base) use ($s, $typoFor, $edgesFor): string {
            $css = [];
            if (!empty($s[$base . '_color'])) $css[] = 'color:' . $s[$base . '_color'];
            if (!empty($s[$base . '_bg']))    $css[] = 'background-color:' . $s[$base . '_bg'];
            if (!empty($s[$base . '_align'])) $css[] = 'text-align:' . $s[$base . '_align'];
            $css = array_merge($css, $typoFor($base . '_typo'));
            if ($p = $edgesFor($base . '_pad'))    $css[] = 'padding:' . $p;
            if ($m = $edgesFor($base . '_margin')) $css[] = 'margin:' . $m;
            return implode(';', $css);
        };

        $hoverDecls = function (string $base) use ($s): array {
            $d = [];
            if (!empty($s[$base . '_hover_color'])) $d[] = 'color:' . $s[$base . '_hover_color'] . ' !important';
            if (!empty($s[$base . '_hover_bg']))    $d[] = 'background-color:' . $s[$base . '_hover_bg'] . ' !important';
            return $d;
        };

        // Contribution of an apply_to design field to a target → ['style' => string, 'hover' => array]
        $contribFor = function (array $f) use ($s, $typoFor, $edgesFor): array {
            $type = $f['type']; $key = $f['key'];
            $as = $f['apply_as'] ?: ($type === 'dimensions' ? 'padding' : ($type === 'color' ? 'color' : ''));
            $style = []; $hover = [];
            if ($type === 'color') {
                $v = $s[$key] ?? ''; if ($v === '' || $v === null) return ['style' => '', 'hover' => []];
                if ($as === 'bg')              $style[] = 'background-color:' . $v;
                elseif ($as === 'hover_color') $hover[] = 'color:' . $v . ' !important';
                elseif ($as === 'hover_bg')    $hover[] = 'background-color:' . $v . ' !important';
                else                           $style[] = 'color:' . $v;
            } elseif ($type === 'typography') {
                $style = $typoFor($key);
            } elseif ($type === 'dimensions') {
                $e = $edgesFor($key);
                if ($e) $style[] = ($as === 'margin' ? 'margin:' : 'padding:') . $e;
            }
            return ['style' => implode(';', $style), 'hover' => $hover];
        };

        $modBase = function (string $key, string $type): ?string {
            if ($type === 'color') {
                if (str_ends_with($key, '_hover_color')) return substr($key, 0, -12);
                if (str_ends_with($key, '_hover_bg'))    return substr($key, 0, -9);
                if (str_ends_with($key, '_color'))        return substr($key, 0, -6);
                if (str_ends_with($key, '_bg'))           return substr($key, 0, -3);
            }
            if ($type === 'typography' && str_ends_with($key, '_typo')) return substr($key, 0, -5);
            if ($type === 'dimensions') {
                if (str_ends_with($key, '_pad'))    return substr($key, 0, -4);
                if (str_ends_with($key, '_margin')) return substr($key, 0, -7);
            }
            if (in_array($type, ['select','radio'], true) && str_ends_with($key, '_align')) return substr($key, 0, -6);
            return null;
        };

        $hoverCss = ''; $hcSeq = 0;
        $mkHoverClass = function (array $decls) use (&$hoverCss, &$hcSeq, $elId): string {
            if (empty($decls)) return '';
            $cls = 'lzceh-' . $elId . '-' . ($hcSeq++);
            $hoverCss .= '.' . $cls . ':hover{' . implode(';', $decls) . '}';
            return $cls;
        };

        // Explicit multi-target relations: design fields with `apply_to` style one or more content fields.
        $explicit = []; // base => ['style' => [..], 'hover' => [..]]
        foreach ($fields as $k => $f) {
            if (empty($f['apply_to'])) continue;
            $targets = is_array($f['apply_to']) ? $f['apply_to'] : [$f['apply_to']];
            $c = $contribFor($f + ['key' => $k]);
            foreach ($targets as $t) {
                if (!isset($explicit[$t])) $explicit[$t] = ['style' => [], 'hover' => []];
                if ($c['style'] !== '') $explicit[$t]['style'][] = $c['style'];
                if (!empty($c['hover']))  $explicit[$t]['hover'] = array_merge($explicit[$t]['hover'], $c['hover']);
            }
        }

        $items = [];
        foreach ($fields as $k => $f) {
            if (!$isContent($k, $f)) continue;
            $style = $styleFor($k);
            $hoverD = $hoverDecls($k);
            if (isset($explicit[$k])) {
                if (!empty($explicit[$k]['style'])) $style = trim($style . ';' . implode(';', $explicit[$k]['style']), ';');
                $hoverD = array_merge($hoverD, $explicit[$k]['hover']);
            }
            $hoverClass = $mkHoverClass($hoverD);
            if ($f['type'] === 'repeater') {
                $subDefs = !empty($f['fields']) ? $f['fields'] : ($f['params'] ?? []);
                $subFields = [];
                foreach ($subDefs as $sp) {
                    $sk = $sp['param_name'] ?? trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($sp['heading'] ?? '')), '_');
                    $subFields[] = ['key' => $sk, 'type' => $sp['type'] ?? 'text'];
                }
                $items[] = ['kind' => 'repeater', 'key' => $k, 'style' => $style, 'hoverClass' => $hoverClass,
                            'rows' => (is_array($s[$k] ?? null) ? $s[$k] : []), 'subFields' => $subFields];
            } elseif ($f['type'] === 'button') {
                $items[] = ['kind' => 'button', 'key' => $k, 'value' => $s[$k] ?? '', 'style' => $style, 'hoverClass' => $hoverClass,
                            'url' => $s[$k . '_url'] ?? '', 'target' => $s[$k . '_target'] ?? '_self'];
            } else {
                $val = ($f['type'] === 'checkbox') ? (is_array($s[$k] ?? null) ? implode(', ', $s[$k]) : '') : ($s[$k] ?? null);
                $items[] = ['kind' => $f['type'], 'key' => $k, 'value' => $val, 'style' => $style, 'hoverClass' => $hoverClass];
            }
        }

        // Orphan prefix modifiers (no matching content field, no apply_to) → wrapper
        $wrapperStyle = ''; $wrapperHoverClass = '';
        foreach ($fields as $k => $f) {
            if (!empty($f['apply_to'])) continue;
            $base = $modBase($k, $f['type']);
            if ($base && !in_array($base, $contentKeys, true)) {
                $ws = $styleFor($base);
                if ($ws) $wrapperStyle .= ($wrapperStyle ? ';' : '') . $ws;
                $hc = $mkHoverClass($hoverDecls($base));
                if ($hc) $wrapperHoverClass = $hc;
            }
        }

        return compact('wrapperStyle', 'wrapperHoverClass', 'hoverCss', 'items');
    }
}

if (!function_exists('lazy_revision_diff')) {
    /**
     * Produce an HTML line-level diff between two content versions (for the revisions compare page).
     * Builder JSON is converted to readable shortcodes first. Uses an LCS line diff — no external deps.
     */
    function lazy_revision_diff(string $old, string $new): string
    {
        $prep = function ($s) {
            $s = (string) $s;
            if (\FalconCms\Core\Services\BuilderShortcodeConverter::isBuilderJson($s)) {
                $s = \FalconCms\Core\Services\BuilderShortcodeConverter::jsonToShortcodes($s);
            }
            $s = preg_replace('/>\s*</', ">\n<", $s);        // break HTML onto separate lines
            $lines = preg_split('/\r\n|\r|\n/', $s);
            return array_values(array_filter($lines, fn($l) => trim($l) !== '' || $l === ''));
        };

        $a = $prep($old);
        $b = $prep($new);
        $n = count($a);
        $m = count($b);

        // Safety cap — very large contents skip the O(n*m) diff
        if ($n + $m > 4000) {
            return '<div class="diff-note">Content too large to diff line-by-line. Use Restore to roll back.</div>';
        }

        // LCS dynamic programming table
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = ($a[$i] === $b[$j])
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0; $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j])              { $rows[] = [' ', $a[$i]]; $i++; $j++; }
            elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) { $rows[] = ['-', $a[$i]]; $i++; }
            else                                { $rows[] = ['+', $b[$j]]; $j++; }
        }
        while ($i < $n) { $rows[] = ['-', $a[$i]]; $i++; }
        while ($j < $m) { $rows[] = ['+', $b[$j]]; $j++; }

        $changed = false;
        $html = '';
        foreach ($rows as [$op, $line]) {
            $esc = e($line);
            if ($op === '+')      { $html .= '<div class="diff-line diff-add"><span class="diff-sign">+</span>' . $esc . '</div>'; $changed = true; }
            elseif ($op === '-')  { $html .= '<div class="diff-line diff-del"><span class="diff-sign">-</span>' . $esc . '</div>'; $changed = true; }
            else                  { $html .= '<div class="diff-line diff-eq"><span class="diff-sign"> </span>' . $esc . '</div>'; }
        }

        if (!$changed) return '<div class="diff-note">No differences between these two versions.</div>';
        return $html;
    }
}

if (!function_exists('remove_falcon_action')) {
    function remove_falcon_action($tag, $callback, $priority = 10) {
        return \FalconCms\Core\Core\HookManager::getInstance()->removeAction($tag, $callback, $priority);
    }
}

if (!function_exists('remove_falcon_filter')) {
    function remove_falcon_filter($tag, $callback, $priority = 10) {
        return \FalconCms\Core\Core\HookManager::getInstance()->removeFilter($tag, $callback, $priority);
    }
}

if (!function_exists('lazy_lang_switcher')) {
    function lazy_lang_switcher($showFlags = true) {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) return '';
            $languages = \FalconCms\Core\Models\Language::where('status', true)->get();
            if ($languages->count() <= 1) return '';
            
            $currentLocale = app()->getLocale();
            $output = '<div class="lazy-lang-switcher flex items-center space-x-3">';
            
            // Check if we are on a single post/page to find equivalents
            $currentPost = null;
            if (request()->route('typeOrSlug')) {
                $viewData = view()->getShared();
                if (isset($viewData['post'])) {
                    $currentPost = $viewData['post'];
                }
            }

            foreach ($languages as $lang) {
                $isActive = ($currentLocale == $lang->code);
                $url = url($lang->code); 
                
                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    }
                }

                $output .= '<a href="' . $url . '" class="flex items-center text-[13px] ' . ($isActive ? 'font-bold text-blue-600' : 'text-gray-600 hover:text-black') . '">';
                if ($showFlags) $output .= '<span class="mr-1">' . $lang->flag . '</span> ';
                $output .= strtoupper($lang->code);
                $output .= '</a>';
            }
            $output .= '</div>';
            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('falcon_lang_dropdown')) {
    function falcon_lang_dropdown() {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) return '';
            $activeLangs = \FalconCms\Core\Models\Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) return '';
            
            $currentLang = $activeLangs->where('code', app()->getLocale())->first() ?? $activeLangs->first();
            
            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) return true;
                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) return '';

            $displayMode = get_cms_option('lang_switcher_display', 'both');
            
            $output = '<div class="relative group inline-block language-switcher-dropdown">';
            $output .= '<button class="flex items-center gap-1.5 text-slate-700 hover:text-primary transition-colors text-[13px] font-bold cursor-pointer" onclick="this.nextElementSibling.classList.toggle(\'hidden\')">';
            
            $currentLangCode = strtolower($currentLang->code);
            $countryMap = [
                'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                'br' => 'br', 'in' => 'in'
            ];
            $currentFlagCode = $countryMap[$currentLangCode] ?? $currentLangCode;

            if (in_array($displayMode, ['both', 'flag_only'])) {
                $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                $output .= '<img src="' . url('/assets/flags/' . $currentFlagCode . '.png') . '" class="w-full h-full object-cover" alt="' . $currentLang->name . '">';
                $output .= '</span>';
            }
            
            if (in_array($displayMode, ['both', 'text_only'])) {
                $output .= '<span class="uppercase">' . $currentLang->name . '</span>';
            } elseif ($displayMode === 'code_only') {
                $output .= '<span class="uppercase">' . $currentLang->code . '</span>';
            }
            
            $output .= '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
            $output .= '</button>';
            $output .= '<div class="absolute top-full right-0 mt-2 w-32 bg-white border border-slate-100 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 rounded-md overflow-hidden">';
            $output .= '<ul class="py-1 m-0 list-none">';
            
            foreach($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);
                
                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_falcon_permalink($currentPost);
                    }
                }

                $output .= '<li>';
                $output .= '<a href="' . $url . '" class="flex items-center justify-between gap-2 px-4 py-2 text-[13px] font-medium text-slate-600 hover:text-primary hover:bg-slate-50 transition-all ' . ($isActive ? 'bg-slate-50 text-primary font-bold' : '') . '">';
                $output .= '<div class="flex items-center gap-2">';
                
                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in'
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                    $output .= '<img src="' . url('/assets/flags/' . $flagCode . '.png') . '" class="w-full h-full object-cover" alt="' . $lang->name . '">';
                    $output .= '</span>';
                }
                
                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span>' . $lang->name . '</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="uppercase">' . $lang->code . '</span>';
                }
                
                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a></li>';
            }
            
            $output .= '</ul></div></div>';
            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('lazy_mobile_lang_switcher')) {
    function lazy_mobile_lang_switcher() {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) return '';
            $activeLangs = \FalconCms\Core\Models\Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) return '';
            
            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) return true;
                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) return '';

            $displayMode = get_cms_option('lang_switcher_display', 'both');
            $output = '<div class="grid grid-cols-2 gap-2">';
            foreach($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);
                
                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_falcon_permalink($currentPost);
                    }
                }

                $output .= '<a href="' . $url . '" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border ' . ($isActive ? 'border-primary bg-primary/5 text-primary' : 'border-slate-100 text-slate-600') . ' transition-all">';
                $output .= '<div class="flex items-center gap-2">';
                
                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in'
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="w-6 h-4 overflow-hidden rounded-sm flex items-center justify-center shrink-0 border border-slate-100 shadow-sm">';
                    $output .= '<img src="' . url('/assets/flags/' . $flagCode . '.png') . '" class="w-full h-full object-cover" alt="' . $lang->name . '">';
                    $output .= '</span>';
                }
                
                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span class="text-[13px] font-semibold">' . $lang->name . '</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="text-[13px] font-semibold uppercase">' . $lang->code . '</span>';
                }
                
                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a>';
            }
            $output .= '</div>';
            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('the_falcon_lang_dropdown')) {
    function the_falcon_lang_dropdown() { echo falcon_lang_dropdown(); }
}

if (!function_exists('lazy_search_form')) {
    function lazy_search_form($placeholder = 'Search...') {
        $url = route('frontend.search');
        $output = '<form action="' . $url . '" method="GET" class="relative lazy-search-form">';
        $output .= '<input type="text" name="s" placeholder="' . e($placeholder) . '" class="w-full bg-slate-50 border border-slate-200 rounded-full px-5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">';
        $output .= '<button type="submit" class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-primary text-white rounded-full text-xs font-bold hover:bg-primary/90 transition-colors uppercase">Search</button>';
        $output .= '</form>';
        return $output;
    }
}

if (!function_exists('the_lazy_search_form')) {
    function the_lazy_search_form($placeholder = 'Search...') { echo lazy_search_form($placeholder); }
}

if (!function_exists('render_lazy_form')) {
    function render_lazy_form($slug) {
        try {
            $form = \FalconCms\Core\Models\Form::where('slug', $slug)->where('status', true)->first();
            if (!$form || empty($form->fields)) return '';
            return view('falcon-cms::frontend.form-renderer', ['form' => $form])->render();
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('do_lazy_shortcode')) {
    function do_lazy_shortcode($content) {
        if (empty($content)) return $content;

        // Match [falcon_form slug="..."] — also accept &quot; (entity-encoded quotes from WYSIWYG editors)
        // Do NOT html_entity_decode the entire string: that would undo Blade's {{ }} escaping and open XSS.
        $content = preg_replace_callback(
            '/\[falcon_form\s+slug=(?:&quot;|["\'])([^"\'&\[\]]+)(?:&quot;|["\'])\s*\]/',
            function ($matches) { return render_lazy_form($matches[1]); },
            $content
        );

        $shortcodes = [
            '[falcon_search]'        => lazy_search_form(),
            '[falcon_lang_dropdown]' => falcon_lang_dropdown(),
        ];

        return str_replace(array_keys($shortcodes), array_values($shortcodes), $content);
    }
}

if (!function_exists('falcon_translate')) {
    function falcon_translate($text, $targetLang = 'en', $sourceLang = 'auto') {
        if (empty($text)) return $text;
        
        // Map common CMS codes to Google Translate codes
        $map = [
            'jp' => 'ja', 'gb' => 'en', 'in' => 'hi', 'cn' => 'zh-CN', 'kr' => 'ko',
            'ua' => 'uk', 'br' => 'pt', 'sa' => 'ar', 'bd' => 'bn', 'zh' => 'zh-CN',
            'ja' => 'ja', 'ko' => 'ko', 'pt' => 'pt', 'hi' => 'hi'
        ];
        
        $targetLang = $map[strtolower($targetLang)] ?? $targetLang;
        $sourceLang = $map[strtolower($sourceLang)] ?? $sourceLang;

        try {
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=" . $sourceLang . "&tl=" . $targetLang . "&dt=t&q=" . urlencode($text);
            
            $options = [
                "http" => [
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                $translated = '';
                if (isset($data[0])) {
                    foreach ($data[0] as $line) {
                        $translated .= $line[0];
                    }
                    return $translated;
                }
            }
        } catch (\Exception $e) {}
        return $text; 
    }
}

if (!function_exists('get_lazy_shop_url')) {
    function get_lazy_shop_url() {
        $pageId = get_shop_option('shop_shop_page_id');
        if ($pageId) {
            $page = \FalconCms\Core\Models\Post::find($pageId);
            if ($page) return get_falcon_permalink($page);
        }
        return url('/product');
    }
}

if (!function_exists('get_falcon_cart_url')) {
    function get_falcon_cart_url() {
        $pageId = get_shop_option('shop_cart_page_id');
        if ($pageId) {
            $page = \FalconCms\Core\Models\Post::find($pageId);
            if ($page) return get_falcon_permalink($page);
        }
        return route('shop.cart');
    }
}

if (!function_exists('get_lazy_checkout_url')) {
    function get_lazy_checkout_url() {
        $pageId = get_shop_option('shop_checkout_page_id');
        if ($pageId) {
            $page = \FalconCms\Core\Models\Post::find($pageId);
            if ($page) return get_falcon_permalink($page);
        }
        return route('shop.checkout');
    }
}

if (!function_exists('falcon_price_format')) {
    function falcon_price_format($price, $order = null) {
        if ($order && is_object($order) && isset($order->currency_symbol)) {
            $symbol = $order->currency_symbol;
            $position = $order->currency_position ?? 'left';
            $decimals = (int) ($order->decimals ?? 2);
            $thousandSep = $order->thousand_separator ?? ',';
            $decimalSep = $order->decimal_separator ?? '.';
        } else {
            $currencyCode = get_shop_option('shop_currency', 'USD');
            $symbol = \FalconCms\Core\Services\EcommerceData::getCurrencySymbol($currencyCode);
            
            $position = get_shop_option('shop_currency_pos', 'left');
            $decimals = (int) get_shop_option('shop_num_decimals', 2);
            $thousandSep = get_shop_option('shop_thousand_sep', ',');
            $decimalSep = get_shop_option('shop_decimal_sep', '.');
        }
        
        $formatted = number_format((float)$price, $decimals, $decimalSep, $thousandSep);
        
        switch ($position) {
            case 'left':
                return $symbol . $formatted;
            case 'right':
                return $formatted . $symbol;
            case 'left_space':
                return $symbol . ' ' . $formatted;
            case 'right_space':
                return $formatted . ' ' . $symbol;
            default:
                return $symbol . $formatted;
        }
    }
}

if (!function_exists('get_falcon_cart_count')) {
    function get_falcon_cart_count() {
        $cart = session()->get('falcon_cart', []);
        $total = 0;
        foreach($cart as $item) {
            $total += $item['quantity'] ?? 0;
        }
        return $total;
    }
}

if (!function_exists('get_falcon_cart_subtotal')) {
    function get_falcon_cart_subtotal() {
        $cart = session()->get('falcon_cart', []);
        $subtotal = 0;
        foreach($cart as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal += $price * $item['quantity'];
        }
        return $subtotal;
    }
}

if (!function_exists('get_falcon_cart_shipping')) {
    /**
     * Calculate shipping cost based on subtotal, quantity, and location.
     * @param string|null $country Customer country code
     * @return float
     */
    function get_falcon_cart_shipping($country = null) {
        $details = get_falcon_cart_shipping_details($country);
        return $details['cost'];
    }
}

if (!function_exists('get_falcon_cart_shipping_details')) {
    function get_falcon_cart_shipping_details($country = null) {
        $subtotal = get_falcon_cart_subtotal();
        $cart = session()->get('falcon_cart', []);
        $itemCount = 0;
        foreach ($cart as $item) {
            $itemCount += ($item['quantity'] ?? 0);
        }

        // 1. Check Global Free Shipping Threshold
        $globalFreeThreshold = (float) get_shop_option('shop_free_shipping_threshold', 0);
        if ($globalFreeThreshold > 0 && $subtotal >= $globalFreeThreshold) {
            return ['cost' => 0, 'label' => 'Free shipping'];
        }

        // 2. Advanced Shipping Zones
        $zones = get_shop_option('shop_shipping_zones', []);
        
        // Find matching zone if country is provided
        $matchedZone = null;
        if ($country) {
            $normalizedCountry = str_replace('—', '-', $country);
            foreach ($zones as $zone) {
                $zoneCountries = (array)($zone['countries'] ?? []);
                $normalizedZoneCountries = array_map(fn($c) => str_replace('—', '-', $c), $zoneCountries);

                if (in_array($normalizedCountry, $normalizedZoneCountries)) {
                    $matchedZone = $zone;
                    break;
                }

                if (strpos($normalizedCountry, ' - ') !== false) {
                    $parts = explode(' - ', $normalizedCountry);
                    $parentCountry = trim($parts[0]);
                    if (in_array($parentCountry, $normalizedZoneCountries)) {
                        $matchedZone = $zone;
                        break;
                    }
                }
            }
        }

        if ($matchedZone) {
            $zoneName = $matchedZone['name'] ?? 'Shipping';
            
            // Check zone-specific free shipping
            $zoneFreeThreshold = (float) ($matchedZone['free_threshold'] ?? 0);
            if ($zoneFreeThreshold > 0 && $subtotal >= $zoneFreeThreshold) {
                return ['cost' => 0, 'label' => 'Free shipping (' . $zoneName . ')'];
            }

            $baseCost = (float) ($matchedZone['cost'] ?? 0);
            $type = $matchedZone['type'] ?? 'order';

            if ($type === 'item' && !empty($matchedZone['rules'])) {
                $ruleCost = 0;
                $matchedRule = false;
                foreach ($matchedZone['rules'] as $rule) {
                    $min = (int) ($rule['min'] ?? 0);
                    $max = ($rule['max'] === '' || $rule['max'] === null) ? PHP_INT_MAX : (int) $rule['max'];
                    
                    if ($itemCount >= $min && $itemCount <= $max) {
                        $ruleCost = (float) ($rule['cost'] ?? 0);
                        $matchedRule = true;
                        break;
                    }
                }
                return [
                    'cost' => $matchedRule ? $ruleCost : $baseCost,
                    'label' => $zoneName
                ];
            }

            return ['cost' => $baseCost, 'label' => $zoneName];
        }

        // 3. Fallback to Global Flat Rate
        return [
            'cost' => (float) get_shop_option('shop_flat_rate_cost', 0),
            'label' => 'Flat rate'
        ];
    }
}

if (!function_exists('get_falcon_cart_tax')) {
    function get_falcon_cart_tax() {
        if (get_cms_option('shop_enable_tax', 0) != 1) return 0;
        $subtotal = get_falcon_cart_subtotal();
        $taxRate = (float) get_cms_option('shop_tax_rate', 0);
        return $subtotal * ($taxRate / 100);
    }
}

if (!function_exists('get_falcon_cart_total')) {
    function get_falcon_cart_total() {
        $cart = session()->get('falcon_cart', []);
        $subtotal = get_falcon_cart_subtotal();
        $shipping = get_falcon_cart_shipping(session()->get('falcon_shipping_country'));
        $tax = get_falcon_cart_tax();
        
        $coupons = session()->get('falcon_coupons', []);
        $totalDiscount = 0;
        $currentCart = $cart; // For sequential calculation if needed
        $isSequential = (int)get_shop_option('shop_coupon_stacking_policy', '1') == 1;
        $subtotal = get_falcon_cart_subtotal();
        $currentSubtotal = $subtotal;

        foreach ($coupons as $coupon) {
            $discount = get_falcon_coupon_discount_amount($coupon, $cart, $isSequential ? $currentSubtotal : $subtotal);
            $totalDiscount += $discount;
            $currentSubtotal -= $discount;
        }
        
        return max(0, $subtotal + $shipping + $tax - $totalDiscount);
    }
}

if (!function_exists('falcon_is_variable_product')) {
    /**
     * Whether a product is a variable product (flag may live in shopData->type or ->product_type).
     */
    function falcon_is_variable_product($product): bool {
        $sd = $product->shopData ?? null;
        if (!$sd) return false;
        return ($sd->type ?? null) === 'variable' || ($sd->product_type ?? null) === 'variable';
    }
}

if (!function_exists('falcon_shipping_carriers')) {
    /**
     * Shipping carriers for order tracking, grouped (Local / International).
     * Each value is a tracking-URL template with a {tracking} placeholder ('' = use universal fallback).
     */
    function lazy_shipping_carriers(): array {
        return [
            'Local (Bangladesh)' => [
                'Pathao'            => 'https://merchant.pathao.com/tracking?consignment_id={tracking}',
                'Steadfast'         => 'https://steadfast.com.bd/track/{tracking}',
                'RedX'              => 'https://redx.com.bd/track-parcel/?trackingId={tracking}',
                'Paperfly'          => '',
                'eCourier'          => 'https://ecourier.com.bd/track?tracking_id={tracking}',
                'Sundarban Courier' => '',
                'SA Paribahan'      => '',
                'Pickaboo'          => '',
                'Delivery Tiger'    => '',
            ],
            'International' => [
                'DHL'        => 'https://www.dhl.com/track?tracking-id={tracking}',
                'FedEx'      => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
                'UPS'        => 'https://www.ups.com/track?tracknum={tracking}',
                'USPS'       => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking}',
                'Aramex'     => 'https://www.aramex.com/track/results?ShipmentNumber={tracking}',
                'DPD'        => 'https://www.dpd.com/tracking/{tracking}',
                'TNT'        => 'https://www.tnt.com/express/en_us/site/tracking.html?searchType=con&cons={tracking}',
                'China Post' => 'https://www.17track.net/en/track?nums={tracking}',
                'India Post' => 'https://www.17track.net/en/track?nums={tracking}',
                'Other'      => 'https://www.17track.net/en/track?nums={tracking}',
            ],
        ];
    }
}

if (!function_exists('falcon_wishlist_product_ids')) {
    /**
     * Product IDs in the current user's wishlist (cached per request). Empty for guests.
     */
    function falcon_wishlist_product_ids(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        if (!auth()->check()) return $cache = [];
        try {
            $cache = \FalconCms\Core\Models\Wishlist::where('user_id', auth()->id())->pluck('product_id')->map(fn($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }
}

if (!function_exists('lazy_in_wishlist')) {
    function lazy_in_wishlist($productId): bool {
        return in_array((int) $productId, falcon_wishlist_product_ids(), true);
    }
}

// ── Checkout Field System ──────────────────────────────────────────────────────

if (!function_exists('falcon_standard_checkout_field_names')) {
    /**
     * Returns the field names that map to dedicated order columns.
     * Any field NOT in this list is treated as a custom field and saved to order meta.
     */
    function falcon_standard_checkout_field_names(): array
    {
        return [
            'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
            'billing_address_1',  'billing_address_2',  'billing_city', 'billing_state',
            'billing_postcode',   'billing_country',
            'shipping_first_name', 'shipping_last_name',
            'shipping_address_1',  'shipping_address_2',  'shipping_city', 'shipping_state',
            'shipping_postcode',   'shipping_country',
        ];
    }
}

if (!function_exists('falcon_get_checkout_fields')) {
    /**
     * Returns the sorted field array for 'billing' or 'shipping'.
     * Applies the falcon_billing_fields / lazy_shipping_fields filter so developers
     * can add, remove, or reorder fields from functions.php.
     *
     * Field keys:
     *   name        string   Input name attribute (required)
     *   type        string   text|email|tel|number|password|date|select|country|textarea|checkbox|hidden
     *   label       string|null  Label text; null = no label rendered
     *   required    bool     Adds required rule in placeOrder validation
     *   width       string   'half' = one column, 'full' = spans both columns (default full)
     *   priority    int      Sort order (lower = earlier, default 10)
     *   default     mixed    Default value if old() is empty
     *   placeholder string   Input placeholder
     *   options     array    key=>label pairs for select type
     *   rows        int      Rows for textarea type
     *   rules       string   Custom Laravel validation rule (overrides 'required' default)
     *   class       string   Extra CSS classes on the field wrapper div
     */
    function falcon_get_checkout_fields(string $section): array
    {
        static $defaults = null;

        if ($defaults === null) {
            $user = auth()->user();
            $defaults = [
                'billing' => [
                    ['name' => 'billing_first_name', 'type' => 'text',    'label' => 'First name',       'required' => true,  'width' => 'half', 'priority' => 10,  'default' => $user->first_name ?? ''],
                    ['name' => 'billing_last_name',  'type' => 'text',    'label' => 'Last name',         'required' => true,  'width' => 'half', 'priority' => 20,  'default' => $user->last_name  ?? ''],
                    ['name' => 'billing_country',    'type' => 'country', 'label' => 'Country / Region',  'required' => true,  'width' => 'full', 'priority' => 30,  'default' => session('falcon_shipping_country', '')],
                    ['name' => 'billing_address_1',  'type' => 'text',    'label' => 'Street address',    'required' => true,  'width' => 'full', 'priority' => 40,  'placeholder' => 'House number and street name'],
                    ['name' => 'billing_address_2',  'type' => 'text',    'label' => null,                'required' => false, 'width' => 'full', 'priority' => 50,  'placeholder' => 'Apartment, suite, unit, etc. (optional)'],
                    ['name' => 'billing_city',       'type' => 'text',    'label' => 'Town / City',       'required' => true,  'width' => 'half', 'priority' => 60],
                    ['name' => 'billing_state',      'type' => 'text',    'label' => 'State / Province',  'required' => true,  'width' => 'half', 'priority' => 70],
                    ['name' => 'billing_postcode',   'type' => 'text',    'label' => 'ZIP Code',          'required' => true,  'width' => 'half', 'priority' => 80],
                    ['name' => 'billing_phone',      'type' => 'tel',     'label' => 'Phone',             'required' => true,  'width' => 'half', 'priority' => 90],
                    ['name' => 'billing_email',      'type' => 'email',   'label' => 'Email address',     'required' => true,  'width' => 'full', 'priority' => 100, 'default' => $user->email ?? ''],
                ],
                'shipping' => [
                    ['name' => 'shipping_first_name', 'type' => 'text',    'label' => 'First name',       'required' => true,  'width' => 'half', 'priority' => 10],
                    ['name' => 'shipping_last_name',  'type' => 'text',    'label' => 'Last name',         'required' => true,  'width' => 'half', 'priority' => 20],
                    ['name' => 'shipping_country',    'type' => 'country', 'label' => 'Country / Region',  'required' => true,  'width' => 'full', 'priority' => 30,  'default' => session('falcon_shipping_country', '')],
                    ['name' => 'shipping_address_1',  'type' => 'text',    'label' => 'Street address',    'required' => true,  'width' => 'full', 'priority' => 40,  'placeholder' => 'House number and street name'],
                    ['name' => 'shipping_address_2',  'type' => 'text',    'label' => null,                'required' => false, 'width' => 'full', 'priority' => 50,  'placeholder' => 'Apartment, suite, unit, etc. (optional)'],
                    ['name' => 'shipping_city',       'type' => 'text',    'label' => 'Town / City',       'required' => true,  'width' => 'half', 'priority' => 60],
                    ['name' => 'shipping_state',      'type' => 'text',    'label' => 'State / Province',  'required' => true,  'width' => 'half', 'priority' => 70],
                    ['name' => 'shipping_postcode',   'type' => 'text',    'label' => 'ZIP Code',          'required' => true,  'width' => 'half', 'priority' => 80],
                ],
            ];
        }

        $fields = $defaults[$section] ?? [];
        $fields = apply_falcon_filters("lazy_{$section}_fields", $fields);
        usort($fields, fn($a, $b) => ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10));

        return $fields;
    }
}

if (!function_exists('falcon_render_checkout_field')) {
    /**
     * Renders a single checkout field inside the 2-column grid.
     * Full-width fields receive md:col-span-2; half-width get one column.
     */
    function falcon_render_checkout_field(array $field): void
    {
        $name    = $field['name']         ?? '';
        $type    = $field['type']         ?? 'text';
        $label   = $field['label']        ?? null;
        $req     = !empty($field['required']);
        $width   = $field['width']        ?? 'full';
        $default = $field['default']      ?? '';
        $ph      = $field['placeholder']  ?? '';
        $opts    = $field['options']       ?? [];
        $class   = $field['class']        ?? '';

        $value = old($name, $default);
        $span  = $width === 'half' ? '' : 'md:col-span-2';
        $inp   = 'w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none';

        if ($type === 'hidden') {
            echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
            return;
        }

        echo '<div class="space-y-1.5 ' . $span . ($class ? ' ' . e($class) : '') . '">';

        if ($label !== null && $label !== '') {
            echo '<label class="text-[14px] font-bold text-heading">'
               . e($label)
               . ($req ? ' <span class="text-red-600">*</span>' : '')
               . '</label>';
        }

        if ($type === 'country') {
            $countries = \FalconCms\Core\Services\EcommerceData::getCountriesWithStates();
            echo '<select name="' . e($name) . '" class="' . $inp . ' bg-white cursor-pointer">';
            foreach ($countries as $code => $cname) {
                echo '<option value="' . e($code) . '"' . ($value == $code ? ' selected' : '') . '>' . e($cname) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'select') {
            echo '<select name="' . e($name) . '" class="' . $inp . ' bg-white">';
            if ($ph) {
                echo '<option value="">' . e($ph) . '</option>';
            }
            foreach ($opts as $k => $v) {
                echo '<option value="' . e($k) . '"' . ($value == $k ? ' selected' : '') . '>' . e($v) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            $rows = (int) ($field['rows'] ?? 3);
            echo '<textarea name="' . e($name) . '" rows="' . $rows . '" placeholder="' . e($ph) . '" class="' . $inp . ' resize-none">' . e($value) . '</textarea>';
        } elseif ($type === 'checkbox') {
            echo '<label class="flex items-center gap-2 cursor-pointer text-[14px] text-body">'
               . '<input type="checkbox" name="' . e($name) . '" value="1" class="w-4 h-4 border-[#ddd] rounded text-primary focus:ring-0"' . ($value ? ' checked' : '') . '>'
               . ($label !== null ? e($label) : '')
               . '</label>';
        } else {
            echo '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="' . e($ph) . '" class="' . $inp . '">';
        }

        echo '</div>';
    }
}

if (!function_exists('falcon_render_checkout_fields')) {
    /**
     * Renders all checkout fields inside a responsive 2-column grid.
     * Call with the output of falcon_get_checkout_fields().
     */
    function falcon_render_checkout_fields(array $fields): void
    {
        if (empty($fields)) return;
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
        foreach ($fields as $field) {
            falcon_render_checkout_field($field);
        }
        echo '</div>';
    }
}

if (!function_exists('falcon_wishlist_count')) {
    function falcon_wishlist_count(): int {
        return count(falcon_wishlist_product_ids());
    }
}

if (!function_exists('falcon_enabled_payment_gateways')) {
    /**
     * Enabled storefront payment gateways (single source of truth for checkout + order processing).
     * Returns: [ id => ['id','title','desc','type' => offline|online] ]
     */
    function falcon_enabled_payment_gateways(): array {
        $gateways = [];

        if (get_shop_option('shop_payment_cod_enable') === '1') {
            $gateways['cod'] = [
                'id'    => 'cod',
                'title' => get_shop_option('shop_payment_cod_title', 'Cash on Delivery'),
                'desc'  => get_shop_option('shop_payment_cod_desc', 'Pay with cash upon delivery.'),
                'type'  => 'offline',
            ];
        }
        if (get_shop_option('shop_payment_bank_enable') === '1') {
            $gateways['bank'] = [
                'id'    => 'bank',
                'title' => get_shop_option('shop_payment_bank_title', 'Direct Bank Transfer'),
                'desc'  => get_shop_option('shop_payment_bank_details', 'Make your payment directly into our bank account.'),
                'type'  => 'offline',
            ];
        }
        if (get_shop_option('shop_payment_stripe_enable') === '1' && get_shop_option('shop_payment_stripe_secret')) {
            $gateways['stripe'] = [
                'id'    => 'stripe',
                'title' => get_shop_option('shop_payment_stripe_title', 'Credit / Debit Card'),
                'desc'  => get_shop_option('shop_payment_stripe_desc', 'Pay securely with your card via Stripe.'),
                'type'  => 'online',
            ];
        }
        if (get_shop_option('shop_payment_paypal_enable') === '1' && get_shop_option('shop_payment_paypal_email')) {
            $gateways['paypal'] = [
                'id'    => 'paypal',
                'title' => get_shop_option('shop_payment_paypal_title', 'PayPal'),
                'desc'  => get_shop_option('shop_payment_paypal_desc', 'Pay via PayPal; you can pay with your card if you don’t have an account.'),
                'type'  => 'online',
            ];
        }
        if (get_shop_option('shop_payment_sslcommerz_enable') === '1'
            && get_shop_option('shop_payment_sslcommerz_store_id')
            && get_shop_option('shop_payment_sslcommerz_store_pass')) {
            $gateways['sslcommerz'] = [
                'id'    => 'sslcommerz',
                'title' => get_shop_option('shop_payment_sslcommerz_title', 'SSLCommerz'),
                'desc'  => get_shop_option('shop_payment_sslcommerz_desc', 'Pay with cards, mobile banking (bKash, Nagad) and net banking via SSLCommerz.'),
                'type'  => 'online',
            ];
        }

        return $gateways;
    }
}

if (!function_exists('get_falcon_coupon_discount_amount')) {
    function get_falcon_coupon_discount_amount($coupon, $cart, $calcBaseSubtotal = null) {
        $amount = (float) ($coupon['amount'] ?? ($coupon['discount'] ?? 0));
        $couponType = $coupon['type'] ?? 'percent';
        $products = (array) ($coupon['products'] ?? []);
        $categories = (array) ($coupon['categories'] ?? []);
        
        // If NO restrictions, apply to the whole provided subtotal
        if (empty($products) && empty($categories)) {
            $base = $calcBaseSubtotal ?? get_falcon_cart_subtotal();
            if ($couponType === 'percent') {
                return $base * ($amount / 100);
            }
            return min($amount, $base);
        }

        // Fetch origin IDs for restricted products and categories for robust matching
        $restrictedProductOriginIds = [];
        if (!empty($products)) {
            $restrictedProductOriginIds = \Illuminate\Support\Facades\DB::table('posts')
                ->whereIn('id', $products)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        $restrictedCategoryOriginIds = [];
        if (!empty($categories)) {
            $restrictedCategoryOriginIds = \Illuminate\Support\Facades\DB::table('taxonomy_terms')
                ->whereIn('id', $categories)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        // Calculate discount
        $totalDiscount = 0;
        $eligibleSubtotal = 0;

        foreach ($cart as $item) {
            $productId = $item['id'] ?? 0;
            if (!$productId) continue;

            // Check Product Eligibility
            $matchProduct = false;
            if (!empty($restrictedProductOriginIds)) {
                $itemIdentity = \Illuminate\Support\Facades\DB::table('posts')
                    ->where('id', $productId)
                    ->selectRaw('COALESCE(origin_id, id) as identity')
                    ->value('identity');
                $matchProduct = in_array($itemIdentity, $restrictedProductOriginIds);
            }
            
            // Check Category Eligibility
            $matchCategory = false;
            if (!empty($restrictedCategoryOriginIds)) {
                $itemCategoryIdentities = \Illuminate\Support\Facades\DB::table('product_category_post')
                    ->join('product_categories', 'product_category_post.product_category_id', '=', 'product_categories.id')
                    ->where('product_category_post.post_id', $productId)
                    ->selectRaw('COALESCE(product_categories.origin_id, product_categories.id) as identity')
                    ->pluck('identity')
                    ->toArray();
                $matchCategory = !empty(array_intersect($itemCategoryIdentities, $restrictedCategoryOriginIds));
            }

            $isEligible = false;
            if (empty($restrictedProductOriginIds) && empty($restrictedCategoryOriginIds)) {
                $isEligible = true;
            } else {
                $isEligible = $matchProduct || $matchCategory;
            }

            if ($isEligible) {
                $qty = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['sale_price'] ?? $item['price']);
                
                if ($couponType === 'percent') {
                    $eligibleSubtotal += $price * $qty;
                } elseif ($couponType === 'fixed_product') {
                    $totalDiscount += $amount * $qty;
                } else { // fixed_cart
                    $eligibleSubtotal += $price * $qty;
                }
            }
        }

        if ($couponType === 'percent') {
            return $eligibleSubtotal * ($amount / 100);
        } elseif ($couponType === 'fixed_product') {
            return $totalDiscount;
        } else { // fixed_cart
            return min($amount, $eligibleSubtotal);
        }
    }
}

if (!function_exists('get_falcon_image_url')) {
    function get_falcon_image_url($path, $default = 'https://via.placeholder.com/300?text=No+Image') {
        if (empty($path)) return $default;
        if (str_starts_with($path, 'http')) return $path;
        
        // Check common paths
        if (file_exists(public_path($path))) return asset($path);
        if (file_exists(public_path('storage/' . $path))) return asset('storage/' . $path);

        return asset('storage/' . $path); // Fallback to storage
    }

}

if (!function_exists('lazy_is_special_menu_item')) {
    /** True when a menu item is one of the Lazy Special Menu widgets. */
    function lazy_is_special_menu_item($type): bool
    {
        return in_array($type, ['special_cart', 'special_search', 'special_wishlist'], true);
    }
}

if (!function_exists('lazy_render_special_menu_item')) {
    /**
     * Render a Lazy Special Menu widget (Cart / Search / Wishlist) inside a navigation menu.
     * Returns a full <li>…</li> string. Reuses existing helpers + the global mini-cart drawer.
     *
     * @param object $item   navigation_menu_items row (stdClass)
     * @param string $style  inline link style inherited from the menu element
     */
    function lazy_render_special_menu_item($item, string $style = '', bool $isMobile = false, $elId = ''): string
    {
        $type  = $item->type ?? '';
        $label = $item->title ?? '';

        $icons = [
            'special_cart'     => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
            'special_search'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
            'special_wishlist' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
        ];
        $icon = $icons[$type] ?? '';

        // Count-badge appearance (NO display property here — Tailwind classes control show/hide,
        // matching the header badge so LazyCart's `hidden`-class toggle keeps working).
        $badgeStyle = 'min-width:18px;height:18px;padding:0 5px;margin-left:6px;font-size:11px;font-weight:700;line-height:1;color:#fff;background:#0091ea;border-radius:9999px;';
        $badgeCls   = 'inline-flex items-center justify-center';
        $iconWrap   = 'display:inline-flex;align-items:center;gap:2px;';

        if ($type === 'special_cart') {
            $count   = function_exists('get_falcon_cart_count') ? (int) get_falcon_cart_count() : 0;
            $cartUrl = \Illuminate\Support\Facades\Route::has('shop.cart') ? route('shop.cart') : url('/cart');
            $badge   = '<span class="cart-count-badge ' . $badgeCls . ($count > 0 ? '' : ' hidden') . '" style="' . $badgeStyle . '">' . $count . '</span>';
            return '<li class="falcon-menu-item lazy-special-item lazy-special-cart">'
                 . '<a href="' . e($cartUrl) . '" class="falcon-menu-link lazy-special-link" style="' . $style . $iconWrap . '" '
                 . 'onclick="if(window.LazyCart){LazyCart.open();return false;}" aria-label="' . e($label ?: 'Cart') . '">'
                 . $icon . $badge
                 . '</a></li>';
        }

        if ($type === 'special_wishlist') {
            $count   = function_exists('falcon_wishlist_count') ? (int) falcon_wishlist_count() : 0;
            $wishUrl = \Illuminate\Support\Facades\Route::has('shop.wishlist') ? route('shop.wishlist') : url('/wishlist');
            $badge   = '<span class="wishlist-count-badge ' . $badgeCls . ($count > 0 ? '' : ' hidden') . '" style="' . $badgeStyle . '">' . $count . '</span>';
            return '<li class="falcon-menu-item lazy-special-item lazy-special-wishlist">'
                 . '<a href="' . e($wishUrl) . '" class="falcon-menu-link lazy-special-link" style="' . $style . $iconWrap . '" aria-label="' . e($label ?: 'Wishlist') . '">'
                 . $icon . $badge
                 . '</a></li>';
        }

        // special_search — icon toggles a simple search box that drops below the menu.
        $searchUrl = \Illuminate\Support\Facades\Route::has('frontend.search') ? route('frontend.search') : url('/search');
        $toggle = "var p=this.parentNode.querySelector('.lazy-search-panel');"
                . "var open=p.style.display!=='block';p.style.display=open?'block':'none';"
                . "if(open){var i=p.querySelector('input');if(i){i.focus();}}return false;";
        $panelStyle = 'display:none;position:absolute;top:100%;right:0;margin-top:8px;z-index:9999;'
                    . 'background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px;min-width:240px;';
        return '<li class="falcon-menu-item lazy-special-item lazy-special-search" style="position:relative;">'
             . '<a href="#" class="falcon-menu-link lazy-special-link" style="' . $style . $iconWrap . '" '
             . 'onclick="' . e($toggle) . '" aria-label="' . e($label ?: 'Search') . '">'
             . $icon
             . '</a>'
             . '<div class="lazy-search-panel" style="' . $panelStyle . '">'
             . '<form action="' . e($searchUrl) . '" method="GET" style="display:flex;align-items:center;gap:6px;">'
             . '<input type="text" name="s" placeholder="Search…" autocomplete="off" '
             . 'style="flex:1;height:36px;padding:0 10px;border:1px solid #e5e7eb;border-radius:4px;font-size:14px;outline:none;color:#111;">'
             . '<button type="submit" style="height:36px;padding:0 12px;background:#0091ea;color:#fff;border:none;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer;">Go</button>'
             . '</form>'
             . '</div></li>';
    }
}

/**
 * Register Special Text Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function($elements) {
    $elements['text_block'] = [
        'type' => 'text_block',
        'name' => 'Text Block',
        'icon' => 'fa fa-align-left',
        'template' => 'falcon-cms::frontend.builder.elements.text-block',
        'fields' => [
            // General
            'content' => ['type' => 'wysiwyg', 'label' => 'Content', 'default' => '<p>your content is here...</p>'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16],
            'fontSizeUnit' => ['type' => 'select', 'label' => 'Unit', 'options' => ['px' => 'px', 'em' => 'em', 'rem' => 'rem'], 'default' => 'px'],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Text Align',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'],
                'default' => 'center'
            ],
            
            // Design - Typography
            'fontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 20],
            'fontSizeUnit' => ['type' => 'text', 'label' => 'Size Unit', 'default' => 'px'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400'],
            'lineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '1.5'],
            'letterSpacing' => ['type' => 'number', 'label' => 'Letter Spacing', 'default' => 0],
            'textTransform' => [
                'type' => 'select',
                'label' => 'Text Transform',
                'options' => ['none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize'],
                'default' => 'none'
            ],
            
            // Design - Colors
            'color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#333333'],
            'hoverColor' => ['type' => 'color', 'label' => 'Hover Color', 'default' => ''],
            
            // Design - Spacing
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0],
            'marginLeft' => ['type' => 'number', 'label' => 'Margin Left', 'default' => 0],
            'marginRight' => ['type' => 'number', 'label' => 'Margin Right', 'default' => 0],
            'paddingTop' => ['type' => 'number', 'label' => 'Padding Top', 'default' => 10],
            'paddingRight' => ['type' => 'number', 'label' => 'Padding Right', 'default' => 0],
            'paddingBottom' => ['type' => 'number', 'label' => 'Padding Bottom', 'default' => 10],
            'paddingLeft' => ['type' => 'number', 'label' => 'Padding Left', 'default' => 0],

            // Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true]
            ],
            'cssClass' => ['type' => 'text', 'default' => ''],
            'cssId' => ['type' => 'text', 'default' => ''],
        ]
    ];
    return $elements;
});

if (!function_exists('get_lazy_builder_fonts')) {
    function get_lazy_builder_fonts($layout, &$fonts = []) {
        if (empty($layout) || !is_array($layout)) return $fonts;
        
        $fontKeys = ['fontFamily', 'numberFontFamily', 'labelFontFamily', 'titleFontFamily', 'captionFontFamily', 'descFontFamily', 'contentFontFamily', 'tabFontFamily'];
        foreach ($layout as $item) {
            foreach ($fontKeys as $fk) {
                if (!empty($item['settings'][$fk]) && $item['settings'][$fk] !== 'inherit') {
                    $family = trim(trim(explode(',', $item['settings'][$fk])[0]), "'\"");
                    if ($family) $fonts[] = $family;
                }
            }
            
            // Check nested columns/elements
            if (isset($item['columns'])) {
                get_lazy_builder_fonts($item['columns'], $fonts);
            }
            if (isset($item['elements'])) {
                get_lazy_builder_fonts($item['elements'], $fonts);
            }
        }
        return array_unique($fonts);
    }
}

/**
 * Register Button Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function($elements) {
    $elements['button'] = [
        'type' => 'button',
        'name' => 'Button',
        'icon' => 'fas fa-toggle-on',
        'template' => 'falcon-cms::frontend.builder.elements.button',
        'fields' => [
            // General
            'text' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Click Here'],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => '#'],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self'
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center'
            ],
            
            // Design - Typography
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16, 'tab' => 'design'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '600', 'tab' => 'design'],
            'textTransform' => ['type' => 'select', 'label' => 'Text Transform', 'options' => ['none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize'], 'default' => 'none', 'tab' => 'design'],
            
            // Design - Colors & Gradients
            'buttonStyle' => ['type' => 'text', 'default' => 'default', 'tab' => 'design'],
            'color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#ffffff', 'tab' => 'design'],
            'bgColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#0091ea', 'tab' => 'design'],
            'hoverColor' => ['type' => 'color', 'label' => 'Text Hover Color', 'default' => '#ffffff', 'tab' => 'design'],
            'hoverBgColor' => ['type' => 'color', 'label' => 'BG Hover Color', 'default' => '#007cc0', 'tab' => 'design'],
            
            'bgGradientStartColor' => ['type' => 'color', 'default' => '#0091ea', 'tab' => 'design'],
            'bgGradientEndColor'   => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientStartPosition' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'bgGradientEndPosition'   => ['type' => 'number', 'default' => 100, 'tab' => 'design'],
            'bgGradientType'          => ['type' => 'text', 'default' => 'linear', 'tab' => 'design'],
            'bgGradientAngle'         => ['type' => 'number', 'default' => 180, 'tab' => 'design'],
            'bgGradientHoverStartColor' => ['type' => 'color', 'default' => '#007cc0', 'tab' => 'design'],
            'bgGradientHoverEndColor'   => ['type' => 'color', 'default' => '#005fa3', 'tab' => 'design'],
            
            // Design - Spacing & Border
            'paddingTop' => ['type' => 'number', 'label' => 'Padding Top', 'default' => 12, 'tab' => 'design'],
            'paddingBottom' => ['type' => 'number', 'label' => 'Padding Bottom', 'default' => 12, 'tab' => 'design'],
            'paddingLeft' => ['type' => 'number', 'label' => 'Padding Left', 'default' => 30, 'tab' => 'design'],
            'paddingRight' => ['type' => 'number', 'label' => 'Padding Right', 'default' => 30, 'tab' => 'design'],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius', 'default' => 5, 'tab' => 'design'],
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 10, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 10, 'tab' => 'design'],
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design'
            ],
            'borderSizeTop' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeRight' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeBottom' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeLeft' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderColor' => ['type' => 'color', 'default' => '#000000', 'tab' => 'design'],
            'buttonSize' => ['type' => 'text', 'default' => 'medium', 'tab' => 'design'],
            'buttonSpan' => ['type' => 'boolean', 'default' => false, 'tab' => 'design'],
            'icon' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'iconPosition' => ['type' => 'text', 'default' => 'left', 'tab' => 'design'],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ]
    ];
    return $elements;
});

/**
 * Register Menu Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function($elements) {
    $menus = [];
    try {
        $menus = \Illuminate\Support\Facades\DB::table('navigation_menus')->pluck('name', 'id')->toArray();
    } catch (\Exception $e) {}

    $elements['menu'] = [
        'type' => 'menu',
        'name' => 'Menu',
        'icon' => 'fa fa-bars',
        'template' => 'falcon-cms::frontend.builder.elements.menu',
        'fields' => [
            // General
            'menuId' => [
                'type' => 'select',
                'label' => 'Select Menu',
                'options' => $menus,
                'default' => count($menus) > 0 ? array_key_first($menus) : '',
                'tab' => 'design'
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'options' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'],
                'default' => 'horizontal',
                'tab' => 'design'
            ],
            'transitionTime' => [
                'type' => 'range',
                'label' => 'Transition Time (s)',
                'default' => 0.3,
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'tab' => 'design'
            ],
            'submenuSpace' => [
                'type' => 'number',
                'label' => 'Space Between Main Menu and Submenu (px)',
                'default' => 10,
                'tab' => 'design'
            ],
            'showArrows' => [
                'type' => 'select',
                'label' => 'Menu Arrows',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
                'tab' => 'design'
            ],
            
            // Design - Typography
            'fontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit', 'tab' => 'design'],
            'fontSize' => ['type' => 'number', 'label' => 'Font Size', 'default' => 16, 'tab' => 'design'],
            'fontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400', 'tab' => 'design'],
            'lineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '', 'tab' => 'design'],
            'letterSpacing' => ['type' => 'text', 'label' => 'Letter Spacing', 'default' => '', 'tab' => 'design'],
            'textTransform' => ['type' => 'text', 'label' => 'Text Transform', 'default' => 'none', 'tab' => 'design'],

            // Design - Menu Item Styling
            'itemPaddingTop' => ['type' => 'number', 'label' => 'Item Padding Top', 'default' => 0, 'tab' => 'design'],
            'itemPaddingRight' => ['type' => 'number', 'label' => 'Item Padding Right', 'default' => 0, 'tab' => 'design'],
            'itemPaddingBottom' => ['type' => 'number', 'label' => 'Item Padding Bottom', 'default' => 0, 'tab' => 'design'],
            'itemPaddingLeft' => ['type' => 'number', 'label' => 'Item Padding Left', 'default' => 0, 'tab' => 'design'],
            'itemSpacing' => ['type' => 'number', 'label' => 'Item Spacing', 'default' => 0, 'tab' => 'design'],
            'itemBorderRadius' => ['type' => 'number', 'label' => 'Item Border Radius', 'default' => 0, 'tab' => 'design'],
            'itemTransition' => ['type' => 'number', 'label' => 'Item Transition', 'default' => 0.3, 'tab' => 'design'],
            
            'itemBgColor' => ['type' => 'color', 'label' => 'Item Background Color', 'default' => 'transparent', 'tab' => 'design'],
            'itemBgColorHover' => ['type' => 'color', 'label' => 'Item Background Color Hover', 'default' => 'transparent', 'tab' => 'design'],
            'itemColor' => ['type' => 'color', 'label' => 'Item Text Color', 'default' => '#333333', 'tab' => 'design'],
            'itemColorHover' => ['type' => 'color', 'label' => 'Item Text Color Hover', 'default' => '#0091ea', 'tab' => 'design'],
            
            'itemBorderSizeTop' => ['type' => 'number', 'label' => 'Item Border Size Top', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeRight' => ['type' => 'number', 'label' => 'Item Border Size Right', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeBottom' => ['type' => 'number', 'label' => 'Item Border Size Bottom', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeLeft' => ['type' => 'number', 'label' => 'Item Border Size Left', 'default' => 0, 'tab' => 'design'],
            
            'itemBorderSizeTopHover' => ['type' => 'number', 'label' => 'Item Border Size Top Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeRightHover' => ['type' => 'number', 'label' => 'Item Border Size Right Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeBottomHover' => ['type' => 'number', 'label' => 'Item Border Size Bottom Hover', 'default' => 0, 'tab' => 'design'],
            'itemBorderSizeLeftHover' => ['type' => 'number', 'label' => 'Item Border Size Left Hover', 'default' => 0, 'tab' => 'design'],
            
            'itemBorderColor' => ['type' => 'color', 'label' => 'Item Border Color', 'default' => '#eeeeee', 'tab' => 'design'],
            'itemBorderColorHover' => ['type' => 'color', 'label' => 'Item Border Color Hover', 'default' => '#0091ea', 'tab' => 'design'],
            
            // Design - Sub Menu Styling
            'showArrows' => ['type' => 'text', 'label' => 'Show Arrows', 'default' => 'yes', 'tab' => 'submenu'],
            'submenuDirection' => ['type' => 'text', 'label' => 'Expand Direction', 'default' => 'right', 'tab' => 'submenu'],
            'submenuTransition' => ['type' => 'text', 'label' => 'Expand Transition', 'default' => 'fade', 'tab' => 'submenu'],
            'submenuMinWidth' => ['type' => 'text', 'label' => 'Min Width', 'default' => '200px', 'tab' => 'submenu'],
            'submenuMaxWidth' => ['type' => 'text', 'label' => 'Max Width', 'default' => '220px', 'tab' => 'submenu'],
            'submenuSpace' => ['type' => 'number', 'label' => 'Submenu Space', 'default' => 10, 'tab' => 'submenu'],
            
            // Submenu Typography
            'submenuFontFamily' => ['type' => 'text', 'label' => 'Submenu Font Family', 'default' => 'inherit', 'tab' => 'submenu'],
            'submenuFontSize' => ['type' => 'text', 'label' => 'Submenu Font Size', 'default' => '14px', 'tab' => 'submenu'],
            'submenuFontWeight' => ['type' => 'text', 'label' => 'Submenu Font Weight', 'default' => '400', 'tab' => 'submenu'],
            'submenuLineHeight' => ['type' => 'text', 'label' => 'Submenu Line Height', 'default' => '', 'tab' => 'submenu'],
            'submenuLetterSpacing' => ['type' => 'text', 'label' => 'Submenu Letter Spacing', 'default' => '', 'tab' => 'submenu'],
            'submenuTextTransform' => ['type' => 'text', 'label' => 'Submenu Text Transform', 'default' => 'none', 'tab' => 'submenu'],
            'submenuTextAlign' => ['type' => 'text', 'label' => 'Submenu Text Align', 'default' => 'left', 'tab' => 'submenu'],
            
            // Submenu Item Styling
            'submenuPaddingTop' => ['type' => 'number', 'label' => 'Submenu Padding Top', 'default' => 10, 'tab' => 'submenu'],
            'submenuPaddingRight' => ['type' => 'number', 'label' => 'Submenu Padding Right', 'default' => 20, 'tab' => 'submenu'],
            'submenuPaddingBottom' => ['type' => 'number', 'label' => 'Submenu Padding Bottom', 'default' => 10, 'tab' => 'submenu'],
            'submenuPaddingLeft' => ['type' => 'number', 'label' => 'Submenu Padding Left', 'default' => 20, 'tab' => 'submenu'],
            
            'submenuBorderRadiusTopLeft' => ['type' => 'number', 'label' => 'Submenu BR TL', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusTopRight' => ['type' => 'number', 'label' => 'Submenu BR TR', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusBottomRight' => ['type' => 'number', 'label' => 'Submenu BR BR', 'default' => 4, 'tab' => 'submenu'],
            'submenuBorderRadiusBottomLeft' => ['type' => 'number', 'label' => 'Submenu BR BL', 'default' => 4, 'tab' => 'submenu'],
            
            'submenuBoxShadow' => ['type' => 'text', 'label' => 'Box Shadow', 'default' => 'no', 'tab' => 'submenu'],
            'submenuShadowColor' => ['type' => 'color', 'label' => 'Shadow Color', 'default' => 'rgba(0,0,0,0.12)', 'tab' => 'submenu'],
            'submenuShadowH' => ['type' => 'number', 'label' => 'Shadow H', 'default' => 0, 'tab' => 'submenu'],
            'submenuShadowV' => ['type' => 'number', 'label' => 'Shadow V', 'default' => 15, 'tab' => 'submenu'],
            'submenuShadowBlur' => ['type' => 'number', 'label' => 'Shadow Blur', 'default' => 35, 'tab' => 'submenu'],
            'submenuShadowSpread' => ['type' => 'number', 'label' => 'Shadow Spread', 'default' => 0, 'tab' => 'submenu'],
            
            'submenuSeparatorColor' => ['type' => 'color', 'label' => 'Separator Color', 'default' => 'rgba(0,0,0,0.05)', 'tab' => 'submenu'],
            'submenuBgColor' => ['type' => 'color', 'label' => 'Submenu BG', 'default' => '#ffffff', 'tab' => 'submenu'],
            'submenuTextColor' => ['type' => 'color', 'label' => 'Submenu Text', 'default' => '#333333', 'tab' => 'submenu'],
            'submenuTextColorHover' => ['type' => 'color', 'label' => 'Submenu Text Hover', 'default' => '#0091ea', 'tab' => 'submenu'],

            // Mobile Menu Styling
            'mobileCollapseBreakpoint' => ['type' => 'text', 'label' => 'Collapse to Mobile Breakpoint', 'default' => 'tablet', 'tab' => 'mobile'],
            'mobileMenuMode' => ['type' => 'text', 'label' => 'Mobile Menu Mode', 'default' => 'collapsed', 'tab' => 'mobile'],
            'mobileMenuExpandMode' => ['type' => 'select', 'label' => 'Mobile Menu Expand Mode', 'default' => 'full-width-static', 'tab' => 'mobile', 'options' => ['full-width-static' => 'Full Width - Static', 'full-width-absolute' => 'Full Width - Absolute', 'sidebar' => 'Sidebar']],
            'mobileMenuSidebarSide' => ['type' => 'select', 'label' => 'Sidebar Side', 'default' => 'left', 'tab' => 'mobile', 'options' => ['left' => 'Left', 'right' => 'Right']],
            'mobileMenuOpeningMode' => ['type' => 'text', 'label' => 'Mobile Menu Opening Mode', 'default' => 'toggle', 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingTop' => ['type' => 'number', 'label' => 'Trigger Padding Top', 'default' => 10, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingRight' => ['type' => 'number', 'label' => 'Trigger Padding Right', 'default' => 15, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingBottom' => ['type' => 'number', 'label' => 'Trigger Padding Bottom', 'default' => 10, 'tab' => 'mobile'],
            'mobileMenuTriggerPaddingLeft' => ['type' => 'number', 'label' => 'Trigger Padding Left', 'default' => 15, 'tab' => 'mobile'],
            'mobileMenuTriggerBgColor' => ['type' => 'color', 'label' => 'Trigger Background Color', 'default' => '#ffffff', 'tab' => 'mobile'],
            'mobileMenuTriggerTextColor' => ['type' => 'color', 'label' => 'Trigger Text Color', 'default' => '#333333', 'tab' => 'mobile'],
            'mobileMenuTriggerText' => ['type' => 'text', 'label' => 'Trigger Text', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuTriggerExpandIcon' => ['type' => 'text', 'label' => 'Trigger Expand Icon', 'default' => 'fa-bars', 'tab' => 'mobile'],
            'mobileMenuTriggerCollapseIcon' => ['type' => 'text', 'label' => 'Trigger Collapse Icon', 'default' => 'fa-times', 'tab' => 'mobile'],
            'mobileMenuTriggerFontSize' => ['type' => 'text', 'label' => 'Trigger Font Size', 'default' => '16px', 'tab' => 'mobile'],
            'mobileMenuTriggerHorizontalAlign' => ['type' => 'text', 'label' => 'Trigger Horizontal Align', 'default' => 'flex-start', 'tab' => 'mobile'],

            'mobileMenuItemMinHeight' => ['type' => 'number', 'label' => 'Mobile Menu Item Minimum Height', 'default' => 65, 'tab' => 'mobile'],
            'mobileMenuItemPaddingTop' => ['type' => 'number', 'label' => 'Item Padding Top', 'default' => 12, 'tab' => 'mobile'],
            'mobileMenuItemPaddingBottom' => ['type' => 'number', 'label' => 'Item Padding Bottom', 'default' => 12, 'tab' => 'mobile'],
            'mobileMenuItemPaddingLeft' => ['type' => 'number', 'label' => 'Item Padding Left', 'default' => 20, 'tab' => 'mobile'],
            'mobileMenuItemPaddingRight' => ['type' => 'number', 'label' => 'Item Padding Right', 'default' => 20, 'tab' => 'mobile'],
            'mobileMenuTextAlign' => ['type' => 'text', 'label' => 'Mobile Menu Text Align', 'default' => 'left', 'tab' => 'mobile'],
            'mobileMenuIndentSubmenus' => ['type' => 'text', 'label' => 'Mobile Menu Indent Submenus', 'default' => 'on', 'tab' => 'mobile'],
            
            'mobileMenuFontFamily' => ['type' => 'text', 'label' => 'Font Family', 'default' => 'inherit', 'tab' => 'mobile'],
            'mobileMenuFontSize' => ['type' => 'text', 'label' => 'Font Size', 'default' => '16px', 'tab' => 'mobile'],
            'mobileMenuFontWeight' => ['type' => 'text', 'label' => 'Font Weight', 'default' => '400', 'tab' => 'mobile'],
            'mobileMenuLineHeight' => ['type' => 'text', 'label' => 'Line Height', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuLetterSpacing' => ['type' => 'text', 'label' => 'Letter Spacing', 'default' => '', 'tab' => 'mobile'],
            'mobileMenuTextTransform' => ['type' => 'text', 'label' => 'Text Transform', 'default' => 'none', 'tab' => 'mobile'],

            'mobileMenuSeparatorColor' => ['type' => 'color', 'label' => 'Separator Color', 'default' => 'rgba(0,0,0,0.05)', 'tab' => 'mobile'],
            'mobileMenuBgColor' => ['type' => 'color', 'label' => 'Menu Background', 'default' => '#ffffff', 'tab' => 'mobile'],
            'mobileMenuBgColorHover' => ['type' => 'color', 'label' => 'Menu Background Hover', 'default' => '#f8f9fa', 'tab' => 'mobile'],
            'mobileMenuTextColor' => ['type' => 'color', 'label' => 'Menu Text Color', 'default' => '#333333', 'tab' => 'mobile'],
            'mobileMenuTextColorHover' => ['type' => 'color', 'label' => 'Menu Text Hover', 'default' => '#0091ea', 'tab' => 'mobile'],

            // Margins (Simplified per user request)
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0, 'tab' => 'design'],
            
            // Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design'
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ]
    ];
    return $elements;
});

/**
 * Register Image Element for Falcon Builder
 */
add_falcon_filter('falcon_builder_elements', function($elements) {
    $elements['image'] = [
        'type' => 'image',
        'name' => 'Image',
        'icon' => 'fa fa-image',
        'template' => 'falcon-cms::frontend.builder.elements.image',
        'fields' => [
            // General
            'url' => ['type' => 'media', 'label' => 'Image URL', 'default' => ''],
            'alt' => ['type' => 'text', 'label' => 'Alt Text', 'default' => ''],
            'linkUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => ''],
            'linkTarget' => [
                'type' => 'select',
                'label' => 'Link Target',
                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                'default' => '_self'
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Alignment',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'default' => 'center'
            ],
            
            // Design - Dimensions
            'width' => ['type' => 'number', 'label' => 'Width', 'default' => '', 'tab' => 'design'],
            'widthUnit' => ['type' => 'text', 'default' => 'px', 'tab' => 'design'],
            'maxWidth' => ['type' => 'number', 'label' => 'Max Width', 'default' => 100, 'tab' => 'design'],
            'maxWidthUnit' => ['type' => 'text', 'default' => '%', 'tab' => 'design'],
            
            // Design - Spacing & Border
            'marginTop' => ['type' => 'number', 'label' => 'Margin Top', 'default' => 0, 'tab' => 'design'],
            'marginRight' => ['type' => 'number', 'label' => 'Margin Right', 'default' => 0, 'tab' => 'design'],
            'marginBottom' => ['type' => 'number', 'label' => 'Margin Bottom', 'default' => 0, 'tab' => 'design'],
            'marginLeft' => ['type' => 'number', 'label' => 'Margin Left', 'default' => 0, 'tab' => 'design'],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius', 'default' => 0, 'tab' => 'design'],
            'borderRadiusUnit' => ['type' => 'text', 'default' => 'px', 'tab' => 'design'],
            'borderSizeTop' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeRight' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeBottom' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderSizeLeft' => ['type' => 'number', 'default' => 0, 'tab' => 'design'],
            'borderColor' => ['type' => 'color', 'default' => 'transparent', 'tab' => 'design'],
            'hoverType' => ['type' => 'select', 'label' => 'Hover Effect', 'default' => 'none', 'tab' => 'design'],

            // Visibility & Extras
            'visibility' => [
                'type' => 'object',
                'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                'tab' => 'design'
            ],
            'cssClass' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
            'cssId' => ['type' => 'text', 'default' => '', 'tab' => 'design'],
        ]
    ];
    return $elements;
});

// Supported social platforms: slug => [label, FontAwesome icon]. Used by the Social Icons
// element to generate one URL field per platform (no icon picking) and to render the icons.
if (!function_exists('falcon_social_platforms')) {
    function falcon_social_platforms(): array {
        return [
            'behance'    => ['label' => 'Behance',     'icon' => 'fa-brands fa-behance',      'color' => '#1769FF'],
            'blogger'    => ['label' => 'Blogger',     'icon' => 'fa-brands fa-blogger-b',    'color' => '#FF5722'],
            'bluesky'    => ['label' => 'Bluesky',     'icon' => 'fa-brands fa-bluesky',      'color' => '#0085FF'],
            'deviantart' => ['label' => 'Deviantart',  'icon' => 'fa-brands fa-deviantart',   'color' => '#05CC47'],
            'digg'       => ['label' => 'Digg',        'icon' => 'fa-brands fa-digg',         'color' => '#1C1C1C'],
            'discord'    => ['label' => 'Discord',     'icon' => 'fa-brands fa-discord',      'color' => '#5865F2'],
            'dribbble'   => ['label' => 'Dribbble',    'icon' => 'fa-brands fa-dribbble',     'color' => '#EA4C89'],
            'dropbox'    => ['label' => 'Dropbox',     'icon' => 'fa-brands fa-dropbox',      'color' => '#0061FF'],
            'email'      => ['label' => 'Email',       'icon' => 'fa fa-envelope',            'color' => '#EA4335'],
            'facebook'   => ['label' => 'Facebook',    'icon' => 'fa-brands fa-facebook-f',   'color' => '#1877F2'],
            'flickr'     => ['label' => 'Flickr',      'icon' => 'fa-brands fa-flickr',       'color' => '#0063DC'],
            'github'     => ['label' => 'GitHub',      'icon' => 'fa-brands fa-github',       'color' => '#181717'],
            'instagram'  => ['label' => 'Instagram',   'icon' => 'fa-brands fa-instagram',    'color' => '#E4405F'],
            'linkedin'   => ['label' => 'LinkedIn',    'icon' => 'fa-brands fa-linkedin-in',  'color' => '#0A66C2'],
            'medium'     => ['label' => 'Medium',      'icon' => 'fa-brands fa-medium',       'color' => '#000000'],
            'phone'      => ['label' => 'Phone',       'icon' => 'fa fa-phone',               'color' => '#34A853'],
            'pinterest'  => ['label' => 'Pinterest',   'icon' => 'fa-brands fa-pinterest-p',  'color' => '#BD081C'],
            'reddit'     => ['label' => 'Reddit',      'icon' => 'fa-brands fa-reddit-alien', 'color' => '#FF4500'],
            'snapchat'   => ['label' => 'Snapchat',    'icon' => 'fa-brands fa-snapchat',     'color' => '#FFFC00'],
            'soundcloud' => ['label' => 'SoundCloud',  'icon' => 'fa-brands fa-soundcloud',   'color' => '#FF5500'],
            'spotify'    => ['label' => 'Spotify',     'icon' => 'fa-brands fa-spotify',      'color' => '#1DB954'],
            'telegram'   => ['label' => 'Telegram',    'icon' => 'fa-brands fa-telegram',     'color' => '#26A5E4'],
            'tiktok'     => ['label' => 'TikTok',      'icon' => 'fa-brands fa-tiktok',       'color' => '#000000'],
            'tumblr'     => ['label' => 'Tumblr',      'icon' => 'fa-brands fa-tumblr',       'color' => '#36465D'],
            'twitch'     => ['label' => 'Twitch',      'icon' => 'fa-brands fa-twitch',       'color' => '#9146FF'],
            'vimeo'      => ['label' => 'Vimeo',       'icon' => 'fa-brands fa-vimeo-v',      'color' => '#1AB7EA'],
            'website'    => ['label' => 'Website',     'icon' => 'fa fa-globe',               'color' => '#2271b1'],
            'whatsapp'   => ['label' => 'WhatsApp',    'icon' => 'fa-brands fa-whatsapp',     'color' => '#25D366'],
            'wordpress'  => ['label' => 'WordPress',   'icon' => 'fa-brands fa-wordpress',    'color' => '#21759B'],
            'x_twitter'  => ['label' => 'X (Twitter)', 'icon' => 'fa-brands fa-x-twitter',    'color' => '#000000'],
            'youtube'    => ['label' => 'YouTube',     'icon' => 'fa-brands fa-youtube',      'color' => '#FF0000'],
        ];
    }
}

// Returns a readable foreground (#111111 / #ffffff) for a given background hex — used so
// brand-coloured boxes keep their icon legible (e.g. white icon on Snapchat yellow → dark).
if (!function_exists('lazy_contrast_color')) {
    function lazy_contrast_color($hex): string {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) < 6) return '#ffffff';
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $lum > 0.65 ? '#111111' : '#ffffff';
    }
}

// Social Icons — one URL field per platform (General tab). Fill a field and that platform's
// icon shows on the front-end. No icon picking; the icon is fixed per platform.
add_falcon_filter('falcon_builder_elements', function($elements) {
    $fields = [];
    foreach (falcon_social_platforms() as $key => $p) {
        // social_icon / social_color / social_label: tell the live canvas the fixed icon, brand colour and name.
        $fields[$key] = ['type' => 'text', 'label' => $p['label'] . ' Link', 'tab' => 'general', 'default' => '',
                         'social_icon' => $p['icon'], 'social_color' => $p['color'] ?? '#2271b1', 'social_label' => $p['label']];
    }
    // Design + behaviour
    $fields['target']         = ['type' => 'select', 'label' => 'Open Links In', 'tab' => 'design',
                                 'options' => ['_blank' => 'New Window', '_self' => 'Same Window'], 'default' => '_blank'];
    $fields['shape']          = ['type' => 'select', 'label' => 'Shape', 'tab' => 'design',
                                 'options' => ['circle' => 'Circle', 'rounded' => 'Rounded', 'square' => 'Square'], 'default' => 'circle'];
    // Boxed Style: Default/Yes = icons sit in a coloured box; No = plain coloured icons (no box).
    $fields['boxedStyle']     = ['type' => 'radio', 'label' => 'Boxed Style', 'tab' => 'design',
                                 'options' => ['default' => 'Default', 'yes' => 'Yes', 'no' => 'No'], 'default' => 'default'];
    // Color Type: Default = theme colours; Custom = pick your own; Brand = each platform's official colour.
    $fields['colorType']      = ['type' => 'select', 'label' => 'Color Type', 'tab' => 'design',
                                 'options' => ['default' => 'Default', 'custom' => 'Custom Colors', 'brand' => 'Brand Colors'], 'default' => 'default'];
    $fields['boxSize']        = ['type' => 'number', 'label' => 'Box Size',  'default' => 38, 'min' => 0, 'tab' => 'design',
                                 'condition' => ['field' => 'boxedStyle', 'value' => 'no', 'operator' => '!=']];
    $fields['iconSize']       = ['type' => 'number', 'label' => 'Icon Size', 'default' => 18, 'min' => 0, 'tab' => 'design'];
    $fields['gap']            = ['type' => 'number', 'label' => 'Gap',       'default' => 10, 'min' => 0, 'tab' => 'design'];
    $fields['align']          = ['type' => 'select', 'label' => 'Alignment', 'tab' => 'design', 'responsive' => true,
                                 'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'default' => 'center'];
    // Tooltip showing the platform name on hover. Default = Top; None = no tooltip.
    $fields['tooltipPosition'] = ['type' => 'select', 'label' => 'Tooltip Position', 'tab' => 'design',
                                  'options' => ['default' => 'Default', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right', 'none' => 'None'], 'default' => 'default'];
    // Colour pickers only matter for "Custom Colors".
    $fields['iconColor']      = ['type' => 'color', 'label' => 'Icon Color',       'default' => '#ffffff', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['bgColor']        = ['type' => 'color', 'label' => 'Background',        'default' => '#2271b1', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['iconHoverColor'] = ['type' => 'color', 'label' => 'Icon Hover Color', 'default' => '#ffffff', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['bgHoverColor']   = ['type' => 'color', 'label' => 'Hover Background',  'default' => '#135e96', 'tab' => 'design', 'condition' => ['field' => 'colorType', 'value' => 'custom']];
    $fields['margin']         = ['type' => 'dimensions', 'label' => 'Margin', 'unit' => 'px', 'tab' => 'design'];
    $fields['visibility']     = ['type' => 'object', 'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true], 'tab' => 'design'];

    $elements['social_icons'] = [
        'type' => 'social_icons',
        'name' => 'Social Icons',
        'icon' => 'fa fa-share-alt',
        'template' => 'falcon-cms::frontend.builder.elements.social-icons',
        'fields' => $fields,
    ];
    return $elements;
});

/**
 * Register the Advanced Search element for Falcon Builder.
 * A smart search bar: choose which post type to search, optional live (AJAX)
 * results dropdown, and an optional category dropdown inside the bar.
 */
add_falcon_filter('falcon_builder_elements', function($elements) {
    // Dynamic post-type options (active types). Multi-select; none selected = all content.
    $ptOptions = [];
    try {
        foreach (\FalconCms\Core\Models\PostType::where('is_active', true)->orderBy('name')->get() as $pt) {
            $ptOptions[$pt->slug] = $pt->name;
        }
    } catch (\Throwable $e) {
        $ptOptions = ['post' => 'Posts', 'page' => 'Pages'];
    }

    $elements['advanced_search'] = [
        'type' => 'advanced_search',
        'name' => 'Advanced Search',
        'icon' => 'fa fa-magnifying-glass',
        'template' => 'falcon-cms::frontend.builder.elements.advanced-search',
        'fields' => [
            // ── General ──
            'searchPostType'         => ['type' => 'multiselect', 'label' => 'Search In (none = all content)', 'tab' => 'general', 'options' => $ptOptions, 'default' => [], 'placeholder' => 'All content (select post types)'],
            'placeholder'            => ['type' => 'text', 'label' => 'Placeholder Text', 'tab' => 'general', 'default' => 'Search...'],
            'enableLiveSearch'       => ['type' => 'toggle', 'label' => 'Live Search (AJAX results)', 'tab' => 'general', 'default' => true],
            'enableCategoryDropdown' => ['type' => 'toggle', 'label' => 'Show Category Dropdown', 'tab' => 'general', 'default' => false],
            'showButton'             => ['type' => 'toggle', 'label' => 'Show Search Button', 'tab' => 'general', 'default' => true],
            'buttonText'             => ['type' => 'text', 'label' => 'Button Text', 'tab' => 'general', 'default' => 'Search', 'condition' => ['field' => 'showButton', 'value' => true]],

            // ── Design ──
            'accentColor'      => ['type' => 'color', 'label' => 'Accent Color', 'tab' => 'design', 'default' => '#0091ea'],
            'bgColor'          => ['type' => 'color', 'label' => 'Background', 'tab' => 'design', 'default' => '#ffffff'],
            'textColor'         => ['type' => 'color', 'label' => 'Field Text Color', 'tab' => 'design', 'default' => '#1d2327'],
            'placeholderColor'  => ['type' => 'color', 'label' => 'Placeholder Color', 'tab' => 'design', 'default' => '#9ca3af'],
            'dropdownTextColor' => ['type' => 'color', 'label' => 'Dropdown Text Color', 'tab' => 'design', 'default' => '#1d2327'],
            'dropdownBgColor'   => ['type' => 'color', 'label' => 'Dropdown Background', 'tab' => 'design', 'default' => '#ffffff'],
            'borderColor'       => ['type' => 'color', 'label' => 'Border Color', 'tab' => 'design', 'default' => '#e5e7eb'],
            'height'       => ['type' => 'number', 'label' => 'Height (px)', 'tab' => 'design', 'default' => 46, 'min' => 28],
            'borderRadius' => ['type' => 'number', 'label' => 'Border Radius (px)', 'tab' => 'design', 'default' => 6, 'min' => 0],
            'maxWidth'     => ['type' => 'number', 'label' => 'Max Width (px, 0 = full)', 'tab' => 'design', 'default' => 0, 'min' => 0],
            'align'        => ['type' => 'select', 'label' => 'Alignment', 'tab' => 'design', 'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'default' => 'flex-start'],

            // ── Extras ──
            'visibility' => ['type' => 'object', 'default' => ['mobile' => true, 'tablet' => true, 'desktop' => true], 'tab' => 'design'],
        ],
    ];
    return $elements;
});