<?php

/**
 * Dynamic values, tokens and custom-element rendering.
 *
 * Loaded by src/helpers.php.
 */

use Carbon\Carbon;
use FalconCms\Core\Models\PostMeta;
use FalconCms\Core\Support\OffCanvas;
use Illuminate\Support\Str;

if (!function_exists('falcon_dynamic_config')) {
    /**
     * Build the $config for falcon_resolve_dynamic_value() out of an element's settings.
     *
     * An element can carry a text source AND a link source at once, so the two contexts read
     * separate setting keys — otherwise the link's fallback would double as the text's.
     */
    function falcon_dynamic_config(array $s, string $ctx = 'text'): array
    {
        if ($ctx === 'link') {
            return [
                'link_tax_post_type' => $s['dynamic_link_tax_post_type'] ?? '',
                'link_tax_slug' => $s['dynamic_link_tax_slug'] ?? '',
                'link_tax_which' => $s['dynamic_link_tax_which'] ?? 'first',
                'fallback' => $s['dynamic_link_tax_fallback'] ?? '',
                'offcanvas' => $s['dynamic_link_offcanvas'] ?? '',
            ];
        }

        return [
            'date_type' => $s['dynamic_date_type'] ?? 'published',
            'date_format' => $s['dynamic_date_format'] ?? '',
            'before' => $s['dynamic_before'] ?? '',
            'after' => $s['dynamic_after'] ?? '',
            'fallback' => $s['dynamic_fallback'] ?? '',
            'excerpt_length' => (int) ($s['dynamic_excerpt_length'] ?? 150),
            'acpt_slug' => $s['dynamic_acpt_slug'] ?? '',
            'tax_post_type' => $s['dynamic_tax_post_type'] ?? '',
            'tax_slug' => $s['dynamic_tax_slug'] ?? '',
            'tax_separator' => $s['dynamic_tax_separator'] ?? '',
            'tax_limit' => (int) ($s['dynamic_tax_limit'] ?? 0),
        ];
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
            try {
                $shared = view()->getShared();
                $post = $shared['post'] ?? null;
            } catch (Throwable $e) {
            }
        }

        $before = $config['before'] ?? '';
        $after = $config['after'] ?? '';
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
                if (!$post) {
                    break;
                }
                $ex = $post->excerpt ?? '';
                if (!$ex && function_exists('get_falcon_excerpt')) {
                    $ex = get_falcon_excerpt($post);
                }
                $length = max(1, (int) ($config['excerpt_length'] ?? 150));
                if (!$ex) {
                    $raw = strip_tags($post->content ?? '');
                    $rawTrimmed = ltrim($raw);
                    if ($rawTrimmed && $rawTrimmed[0] !== '[' && $rawTrimmed[0] !== '{') {
                        $ex = Str::limit($rawTrimmed, $length);
                    }
                } else {
                    $ex = Str::limit(strip_tags($ex), $length);
                }
                $val = $ex ? strip_tags($ex) : '';
                break;
            case 'post_date':
                $dateType = $config['date_type'] ?? 'published';
                $dateFormat = $config['date_format'] ?? 'M j, Y';
                if (!$dateFormat) {
                    $dateFormat = 'M j, Y';
                }
                $d = $dateType === 'modified'
                    ? ($post->updated_at ?? null)
                    : ($post->published_at ?? $post->created_at ?? null);
                $val = ($post && $d) ? Carbon::parse($d)->format($dateFormat) : '';
                break;
            case 'post_reading_time':
                if (!$post) {
                    break;
                }
                $words = str_word_count(strip_tags($post->content ?? ''));
                $val = max(1, (int) ceil($words / 200)).' min read';
                break;
            case 'post_id':
                $val = $post ? (string) ($post->id ?? '') : '';
                break;
            case 'post_type':
                $val = $post->type ?? '';
                break;
            case 'post_taxonomy':
                // Both halves must match: the post has to BE the chosen post type, and the terms
                // come from the chosen taxonomy. A mismatch yields '' so the fallback shows,
                // which is what makes one template safe to reuse across post types.
                if (!$post) {
                    break;
                }
                $wantType = (string) ($config['tax_post_type'] ?? '');
                $taxSlug = (string) ($config['tax_slug'] ?? '');
                if ($taxSlug === '') {
                    break;
                }
                if ($wantType !== '' && ($post->type ?? '') !== $wantType) {
                    break;
                }
                $terms = falcon_post_terms($post, $taxSlug);
                if ($terms->isEmpty()) {
                    break;
                }
                $limit = (int) ($config['tax_limit'] ?? 0);
                if ($limit > 0) {
                    $terms = $terms->take($limit);
                }
                $sep = $config['tax_separator'] ?? '';
                if ($sep === '') {
                    $sep = ', ';
                }
                $val = $terms->map(fn ($t) => (string) ($t->name ?? ''))->filter()->implode($sep);
                break;

            case 'taxonomy_url':
                if (!$post) {
                    break;
                }
                $wantType = (string) ($config['link_tax_post_type'] ?? '');
                $taxSlug = (string) ($config['link_tax_slug'] ?? '');
                if ($taxSlug === '') {
                    break;
                }
                if ($wantType !== '' && ($post->type ?? '') !== $wantType) {
                    break;
                }
                $terms = falcon_post_terms($post, $taxSlug);
                if ($terms->isEmpty()) {
                    break;
                }
                $term = ($config['link_tax_which'] ?? 'first') === 'last' ? $terms->last() : $terms->first();
                $val = falcon_term_archive_url($term, $taxSlug, (string) ($post->type ?? 'post'));
                break;

            case 'offcanvas_open':
                $val = OffCanvas::anchorFor((string) ($config['offcanvas'] ?? ''));
                break;
            case 'offcanvas_close':
                $val = '#offcanvas-close';
                break;

            case 'post_comment_count':
                if (!$post) {
                    break;
                }
                $val = (string) (isset($post->comments_count) ? $post->comments_count : (method_exists($post, 'comments') ? $post->comments()->count() : 0));
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
                    $av = '/storage/'.ltrim($av, '/');
                }
                $val = $av;
                break;
            case 'featured_image':
            case 'feature_image':
                if (!$post) {
                    break;
                }
                $img = $post->featured_image ?? $post->thumbnail ?? '';
                if ($img && !str_starts_with($img, 'http') && !str_starts_with($img, '/storage')) {
                    $img = '/storage/'.ltrim($img, '/');
                }
                $val = $img;
                break;
            case 'logo':
            case 'site_logo':
                $logo = get_cms_option('theme_site_logo', '');
                if ($logo && !str_starts_with($logo, 'http') && !str_starts_with($logo, '/storage')) {
                    $logo = '/storage/'.ltrim($logo, '/');
                }
                $val = $logo;
                break;
            case 'current_date':
                $dateFormat = $config['date_format'] ?? 'M j, Y';
                if (!$dateFormat) {
                    $dateFormat = 'M j, Y';
                }
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
                    $val = falcon_resolve_dynamic_value('acpt_'.$slug, $post);
                }
                break;
            case 'product_price':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $sale = $sd->sale_price;
                $saleActive = ($sale !== null && $sale !== '' && (empty($sd->sale_ends_at) || Carbon::parse($sd->sale_ends_at)->isFuture()));
                $price = $saleActive ? (float) $sale : (float) ($sd->price ?? 0);
                $val = function_exists('falcon_price_format') ? falcon_price_format($price) : number_format($price, 2);
                break;

            case 'product_regular_price':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $price = (float) ($sd->price ?? 0);
                $val = function_exists('falcon_price_format') ? falcon_price_format($price) : number_format($price, 2);
                break;

            case 'product_sale_price':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $sale = $sd->sale_price;
                $saleActive = ($sale !== null && $sale !== '' && (empty($sd->sale_ends_at) || Carbon::parse($sd->sale_ends_at)->isFuture()));
                if ($saleActive) {
                    $val = function_exists('falcon_price_format') ? falcon_price_format((float) $sale) : number_format((float) $sale, 2);
                }
                break;

            case 'product_sku':
                $val = ($post && $post->shopData) ? (string) ($post->shopData->sku ?? '') : '';
                break;
            case 'product_stock_status':
                $sd = $post ? ($post->shopData ?? null) : null;
                if (!$sd) {
                    break;
                }
                $isOut = ($sd->stock_status ?? 'instock') === 'outofstock'
                      || (($sd->manage_stock ?? false) && (int) ($sd->stock_quantity ?? 0) <= 0);
                $val = $isOut ? 'Out of stock' : 'In stock';
                break;

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

        return $before.$val.$after;
    }
}

if (!function_exists('falcon_apply_custom_dynamic')) {
    /**
     * Replace any `{key}_dynamic` setting with the resolved value into `{key}`,
     * so both custom templates and the generic renderer receive final values.
     */
    function falcon_apply_custom_dynamic(array $settings, $post = null): array
    {
        $config = falcon_dynamic_config($settings);
        foreach ($settings as $k => $v) {
            if (is_string($k) && str_ends_with($k, '_dynamic') && !empty($v)) {
                $base = substr($k, 0, -strlen('_dynamic'));
                $settings[$base] = falcon_resolve_dynamic_value($v, $post, $config);
            }
        }

        return $settings;
    }
}

if (!function_exists('falcon_resolve_tokens')) {
    function falcon_resolve_tokens(string $value, $post = null): string
    {
        if (strpos($value, '{lazy:') === false) {
            return $value;
        }

        return preg_replace_callback('/\{lazy:([^}]+)\}/', function ($m) use ($post) {
            return falcon_resolve_token($m[1], $post);
        }, $value);
    }
}

if (!function_exists('falcon_resolve_token')) {
    function falcon_resolve_token(string $token, $post = null): string
    {
        if ($post === null) {
            try {
                $post = view()->getShared()['post'] ?? null;
            } catch (Throwable $e) {
            }
        }
        switch ($token) {
            case 'post_title':
                return $post->title ?? '';
            case 'post_excerpt':
                if (!$post) {
                    return '';
                }
                $ex = $post->excerpt ?? '';
                if (!$ex && function_exists('get_falcon_excerpt')) {
                    $ex = get_falcon_excerpt($post);
                }
                if (!$ex) {
                    $raw = strip_tags($post->content ?? '');
                    $rawTrimmed = ltrim($raw);
                    if ($rawTrimmed && $rawTrimmed[0] !== '[' && $rawTrimmed[0] !== '{') {
                        $ex = Str::limit($rawTrimmed, 150);
                    }
                }

                return $ex ? strip_tags($ex) : '';
            case 'post_id':
                return (string) ($post->id ?? '');
            case 'post_date':
                $d = $post->created_at ?? ($post->published_at ?? null);

                return $d ? Carbon::parse($d)->format('M j, Y') : '';
            case 'post_type':
                return $post->type ?? '';
            case 'post_permalink':
                if (!$post) {
                    return '';
                }

                return function_exists('get_falcon_permalink') ? get_falcon_permalink($post) : ($post->slug ?? '#');
            case 'post_reading_time':
                if (!$post) {
                    return '';
                }

                return max(1, (int) ceil(str_word_count(strip_tags($post->content ?? '')) / 200)).' min read';
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
                        $meta = PostMeta::where('post_id', $post->id)
                            ->where('meta_key', $slug)->first();
                        if ($meta) {
                            return (string) $meta->meta_value;
                        }
                    } catch (Throwable $e) {
                    }
                }

                return '';
        }
    }
}

if (!function_exists('falcon_resolve_tokens_in_settings')) {
    function falcon_resolve_tokens_in_settings(array $settings, $post = null): array
    {
        foreach ($settings as $k => &$v) {
            if (is_string($v) && strpos($v, '{lazy:') !== false) {
                $v = falcon_resolve_tokens($v, $post);
            } elseif (is_array($v)) {
                $v = falcon_resolve_tokens_in_settings($v, $post);
            }
        }

        return $settings;
    }
}

if (!function_exists('falcon_custom_element_render')) {
    /**
     * Build the convention-based render data for a custom element — the PHP mirror of the
     * builder canvas (getCustomElementRender). Used by the generic frontend renderer so the
     * front-end output matches the canvas preview 1:1 (incl. prefix relations + hover).
     *
     * Returns: ['wrapperStyle' => string, 'wrapperHoverClass' => string,
     *           'hoverCss' => string, 'items' => [ {kind,key,value,style,hoverClass, url?,target?, rows?,subFields?} ]]
     */
    function falcon_custom_element_render(array $el, array $customDef): array
    {
        $s = $el['settings'] ?? [];
        $elId = $el['id'] ?? uniqid('ce');
        $fields = falcon_normalize_custom_fields($customDef); // keyed, ordered
        $contentTypes = ['text', 'textarea', 'wysiwyg', 'image', 'media', 'icon', 'button', 'repeater', 'date', 'number', 'slider', 'select', 'radio', 'checkbox', 'url', 'link'];
        // A field renders as content unless it's a design modifier (align select/radio, or an apply_to relation).
        $isContent = function (string $k, array $f) use ($contentTypes): bool {
            if (!in_array($f['type'], $contentTypes, true)) {
                return false;
            }
            if (in_array($f['type'], ['select', 'radio'], true) && str_ends_with($k, '_align')) {
                return false;
            }
            if (!empty($f['apply_to'])) {
                return false;
            }

            return true;
        };

        $contentKeys = [];
        foreach ($fields as $k => $f) {
            if ($isContent($k, $f)) {
                $contentKeys[] = $k;
            }
        }

        $unit = fn ($v) => (is_numeric($v) ? $v.'px' : $v);

        // typography CSS decls from a prefix
        $typoFor = function (string $tp) use ($s, $unit): array {
            $css = [];
            if (!empty($s[$tp.'_family']) && $s[$tp.'_family'] !== 'inherit') {
                $css[] = 'font-family:'.$s[$tp.'_family'];
            }
            if (!empty($s[$tp.'_size'])) {
                $css[] = 'font-size:'.$unit($s[$tp.'_size']);
            }
            if (!empty($s[$tp.'_weight'])) {
                $css[] = 'font-weight:'.$s[$tp.'_weight'];
            }
            if (!empty($s[$tp.'_line_height'])) {
                $css[] = 'line-height:'.$s[$tp.'_line_height'];
            }
            if (isset($s[$tp.'_letter_spacing']) && $s[$tp.'_letter_spacing'] !== '') {
                $css[] = 'letter-spacing:'.$unit($s[$tp.'_letter_spacing']);
            }
            if (!empty($s[$tp.'_transform']) && $s[$tp.'_transform'] !== 'none') {
                $css[] = 'text-transform:'.$s[$tp.'_transform'];
            }

            return $css;
        };

        // T/R/B/L shorthand from a prefix, or null
        $edgesFor = function (string $prefix) use ($s): ?string {
            $edges = [];
            $has = false;
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $v = $s[$prefix.'_'.$side] ?? '';
                if ($v === '' || $v === null) {
                    $edges[] = '0';
                } else {
                    $edges[] = $v.($s[$prefix.'_'.$side.'_unit'] ?? 'px');
                    $has = true;
                }
            }

            return $has ? implode(' ', $edges) : null;
        };

        // Assemble inline CSS for a base from its prefix-related modifiers
        $styleFor = function (string $base) use ($s, $typoFor, $edgesFor): string {
            $css = [];
            if (!empty($s[$base.'_color'])) {
                $css[] = 'color:'.$s[$base.'_color'];
            }
            if (!empty($s[$base.'_bg'])) {
                $css[] = 'background-color:'.$s[$base.'_bg'];
            }
            if (!empty($s[$base.'_align'])) {
                $css[] = 'text-align:'.$s[$base.'_align'];
            }
            $css = array_merge($css, $typoFor($base.'_typo'));
            if ($p = $edgesFor($base.'_pad')) {
                $css[] = 'padding:'.$p;
            }
            if ($m = $edgesFor($base.'_margin')) {
                $css[] = 'margin:'.$m;
            }

            return implode(';', $css);
        };

        $hoverDecls = function (string $base) use ($s): array {
            $d = [];
            if (!empty($s[$base.'_hover_color'])) {
                $d[] = 'color:'.$s[$base.'_hover_color'].' !important';
            }
            if (!empty($s[$base.'_hover_bg'])) {
                $d[] = 'background-color:'.$s[$base.'_hover_bg'].' !important';
            }

            return $d;
        };

        // Contribution of an apply_to design field to a target → ['style' => string, 'hover' => array]
        $contribFor = function (array $f) use ($s, $typoFor, $edgesFor): array {
            $type = $f['type'];
            $key = $f['key'];
            $as = $f['apply_as'] ?: ($type === 'dimensions' ? 'padding' : ($type === 'color' ? 'color' : ''));
            $style = [];
            $hover = [];
            if ($type === 'color') {
                $v = $s[$key] ?? '';
                if ($v === '' || $v === null) {
                    return ['style' => '', 'hover' => []];
                }
                if ($as === 'bg') {
                    $style[] = 'background-color:'.$v;
                } elseif ($as === 'hover_color') {
                    $hover[] = 'color:'.$v.' !important';
                } elseif ($as === 'hover_bg') {
                    $hover[] = 'background-color:'.$v.' !important';
                } else {
                    $style[] = 'color:'.$v;
                }
            } elseif ($type === 'typography') {
                $style = $typoFor($key);
            } elseif ($type === 'dimensions') {
                $e = $edgesFor($key);
                if ($e) {
                    $style[] = ($as === 'margin' ? 'margin:' : 'padding:').$e;
                }
            }

            return ['style' => implode(';', $style), 'hover' => $hover];
        };

        $modBase = function (string $key, string $type): ?string {
            if ($type === 'color') {
                if (str_ends_with($key, '_hover_color')) {
                    return substr($key, 0, -12);
                }
                if (str_ends_with($key, '_hover_bg')) {
                    return substr($key, 0, -9);
                }
                if (str_ends_with($key, '_color')) {
                    return substr($key, 0, -6);
                }
                if (str_ends_with($key, '_bg')) {
                    return substr($key, 0, -3);
                }
            }
            if ($type === 'typography' && str_ends_with($key, '_typo')) {
                return substr($key, 0, -5);
            }
            if ($type === 'dimensions') {
                if (str_ends_with($key, '_pad')) {
                    return substr($key, 0, -4);
                }
                if (str_ends_with($key, '_margin')) {
                    return substr($key, 0, -7);
                }
            }
            if (in_array($type, ['select', 'radio'], true) && str_ends_with($key, '_align')) {
                return substr($key, 0, -6);
            }

            return null;
        };

        $hoverCss = '';
        $hcSeq = 0;
        $mkHoverClass = function (array $decls) use (&$hoverCss, &$hcSeq, $elId): string {
            if (empty($decls)) {
                return '';
            }
            $cls = 'lzceh-'.$elId.'-'.($hcSeq++);
            $hoverCss .= '.'.$cls.':hover{'.implode(';', $decls).'}';

            return $cls;
        };

        // Explicit multi-target relations: design fields with `apply_to` style one or more content fields.
        $explicit = []; // base => ['style' => [..], 'hover' => [..]]
        foreach ($fields as $k => $f) {
            if (empty($f['apply_to'])) {
                continue;
            }
            $targets = is_array($f['apply_to']) ? $f['apply_to'] : [$f['apply_to']];
            $c = $contribFor($f + ['key' => $k]);
            foreach ($targets as $t) {
                if (!isset($explicit[$t])) {
                    $explicit[$t] = ['style' => [], 'hover' => []];
                }
                if ($c['style'] !== '') {
                    $explicit[$t]['style'][] = $c['style'];
                }
                if (!empty($c['hover'])) {
                    $explicit[$t]['hover'] = array_merge($explicit[$t]['hover'], $c['hover']);
                }
            }
        }

        $items = [];
        foreach ($fields as $k => $f) {
            if (!$isContent($k, $f)) {
                continue;
            }
            $style = $styleFor($k);
            $hoverD = $hoverDecls($k);
            if (isset($explicit[$k])) {
                if (!empty($explicit[$k]['style'])) {
                    $style = trim($style.';'.implode(';', $explicit[$k]['style']), ';');
                }
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
                    'url' => $s[$k.'_url'] ?? '', 'target' => $s[$k.'_target'] ?? '_self'];
            } else {
                $val = ($f['type'] === 'checkbox') ? (is_array($s[$k] ?? null) ? implode(', ', $s[$k]) : '') : ($s[$k] ?? null);
                $items[] = ['kind' => $f['type'], 'key' => $k, 'value' => $val, 'style' => $style, 'hoverClass' => $hoverClass];
            }
        }

        // Orphan prefix modifiers (no matching content field, no apply_to) → wrapper
        $wrapperStyle = '';
        $wrapperHoverClass = '';
        foreach ($fields as $k => $f) {
            if (!empty($f['apply_to'])) {
                continue;
            }
            $base = $modBase($k, $f['type']);
            if ($base && !in_array($base, $contentKeys, true)) {
                $ws = $styleFor($base);
                if ($ws) {
                    $wrapperStyle .= ($wrapperStyle ? ';' : '').$ws;
                }
                $hc = $mkHoverClass($hoverDecls($base));
                if ($hc) {
                    $wrapperHoverClass = $hc;
                }
            }
        }

        return compact('wrapperStyle', 'wrapperHoverClass', 'hoverCss', 'items');
    }
}
