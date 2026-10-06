<?php

/**
 * Builder layouts: header, footer, title bar and content sections, and their display conditions.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\BuilderShortcodeConverter;

if (!function_exists('_falcon_parse_builder_layout')) {
    function _falcon_parse_builder_layout(string $raw): ?array
    {
        try {
            if (BuilderShortcodeConverter::isBuilderShortcode($raw)) {
                $raw = BuilderShortcodeConverter::shortcodesToJson($raw);
            }
            $layout = json_decode($raw, true);

            return is_array($layout) ? $layout : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('_falcon_render_layout')) {
    function _falcon_render_layout(array $layout): string
    {
        $data = ['layout' => $layout];

        // When a single post/page/product is being viewed, expose its data so the
        // dynamic Post elements (Content, Post Meta, Product Meta) placed inside a
        // Layout Builder section resolve to the current post — the same variables
        // the post-card renderer provides.
        $cp = view()->getShared()['current_post'] ?? null;
        if ($cp) {
            $data += _falcon_layout_post_context($cp);
        }

        $rendered = view('falcon-cms::frontend.builder.render', $data)->render();

        return do_falcon_shortcode($rendered);
    }
}

if (!function_exists('_falcon_layout_post_context')) {
    /** Post-context variables consumed by the Post elements (mirrors the card renderer). */
    function _falcon_layout_post_context($post): array
    {
        if (!$post) {
            return [];
        }

        $img = $post->featured_image ?? null;
        if ($img && !str_starts_with((string) $img, 'http')) {
            $img = asset('storage/'.$img);
        }

        // Full, rendered content for the Content element (builder JSON → HTML, or classic).
        $fullContent = function_exists('get_falcon_post_content') ? get_falcon_post_content($post->content ?? '') : (string) ($post->content ?? '');
        $plain = trim(strip_tags($fullContent));
        $excerpt = $post->excerpt ?? (mb_strlen($plain) > 160 ? mb_substr($plain, 0, 160).'…' : $plain);

        return [
            'post' => $post,
            'postTitle' => $post->title ?? '',
            'postContent' => $fullContent,
            'postExcerpt' => $excerpt,
            'postPublishedAt' => $post->published_at ?? null,
            'postCreatedAt' => $post->created_at ?? null,
            'postAuthor' => optional($post->user)->name ?? '',
            'postFeaturedImage' => $img,
            'postPermalink' => function_exists('get_falcon_permalink') ? get_falcon_permalink($post) : '#',
            'postCategories' => $post->categories ?? collect(),
        ];
    }
}

if (!function_exists('_falcon_build_sticky_wrapper')) {
    /**
     * Build a sticky wrapper element around $content.
     * $settings is the settings array of the first sticky container/column.
     * $wrapperClass is the CSS class on the wrapper (e.g. falcon-builder-header).
     * $tag is the HTML tag (header|footer|div).
     */
    function _falcon_build_sticky_wrapper(string $content, array $settings, string $wrapperClass, string $tag): string
    {
        $offset = (int) ($settings['stickyOffset'] ?? 0);
        $zIndex = (int) ($settings['stickyZIndex'] ?? 100);
        $desktop = ($settings['stickyDesktop'] ?? true) !== false;
        $tablet = ($settings['stickyTablet'] ?? true) !== false;
        $mobile = ($settings['stickyMobile'] ?? true) !== false;
        $bgColor = $settings['stickyBgColor'] ?? '';
        $bgOpacity = (float) ($settings['stickyBgColorOpacity'] ?? 1);

        $bpSm = (int) get_cms_option('theme_small_screen_breakpoint', '800');
        $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
        $bpSm1 = $bpSm + 1;

        $sOn = "position:sticky;top:{$offset}px;z-index:{$zIndex};";
        $sOff = 'position:static;top:auto;z-index:auto;';

        $baseStyle = ($tag === 'header') ? 'width:100%;' : '';
        $wrapperStyle = $baseStyle.($desktop ? $sOn : '');

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
            $rgba = _falcon_hex_to_rgba($bgColor, $bgOpacity);
            $css .= ".{$wrapperClass}{transition:background-color 0.3s ease;}";
            $css .= ".lazy-sticky-active.{$wrapperClass}{background-color:{$rgba}!important;}";
        }

        // lazy-sticky-col → IntersectionObserver detects stuck state
        return "<{$tag} class=\"{$wrapperClass} lazy-sticky-col\" style=\"{$wrapperStyle}\">"
             ."<style>{$css}</style>"
             .$content
             ."</{$tag}>";
    }
}

if (!function_exists('_falcon_builder_render_wrapper')) {
    /**
     * Render header/footer builder content with correct sticky handling.
     *
     * Only the containers from the FIRST sticky container onwards are placed
     * inside the sticky wrapper. Containers before it render in a plain div so
     * they scroll away normally (e.g. a top-bar above a sticky nav).
     */
    function _falcon_builder_render_wrapper(string $raw, string $tag, string $wrapperClass): string
    {
        $layout = _falcon_parse_builder_layout($raw);

        if (!is_array($layout) || empty($layout)) {
            $content = get_falcon_post_content($raw);
            $style = $tag === 'header' ? ' style="width:100%;"' : '';

            return "<{$tag} class=\"{$wrapperClass}\"{$style}>{$content}</{$tag}>";
        }

        // Find the index of the first sticky container (check container + column settings)
        $stickyIndex = null;
        $stickySettings = null;
        foreach ($layout as $i => $container) {
            $cs = $container['settings'] ?? [];
            if (!empty($cs['sticky'])) {
                $stickyIndex = $i;
                $stickySettings = $cs;
                break;
            }
            foreach ($container['columns'] ?? [] as $col) {
                $cls = $col['settings'] ?? [];
                if (!empty($cls['sticky'])) {
                    $stickyIndex = $i;
                    $stickySettings = $cls;
                    break 2;
                }
            }
        }

        if ($stickySettings === null) {
            // Nothing sticky — simple wrapper
            $style = $tag === 'header' ? ' style="width:100%;"' : '';

            return "<{$tag} class=\"{$wrapperClass}\"{$style}>"
                 ._falcon_render_layout($layout)
                 ."</{$tag}>";
        }

        // Render containers BEFORE the first sticky one in a plain above-wrapper
        $html = '';
        if ($stickyIndex > 0) {
            $html .= '<div class="'.$wrapperClass.'-above" style="width:100%;">'
                   ._falcon_render_layout(array_slice($layout, 0, $stickyIndex))
                   .'</div>';
        }

        // Render sticky containers inside the sticky wrapper
        $stickyContent = _falcon_render_layout(array_slice($layout, $stickyIndex));
        $html .= _falcon_build_sticky_wrapper($stickyContent, $stickySettings, $wrapperClass, $tag);

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
        if ($target === 'entire_site') {
            return true;
        }
        if ($target === 'home') {
            return $kind === 'home';
        }
        if ($target === 'search') {
            return $kind === 'search';
        }
        if ($target === '404') {
            return $kind === '404';
        }
        if ($target === 'all_archives') {
            return $kind === 'archive';
        }
        if ($target === 'author_archive') {
            return $kind === 'archive' && ($ctx['archive_type'] ?? null) === 'author';
        }
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
                if ($matches) {
                    return false;
                }             // an exclusion always wins
            } else {
                $anyInclude = true;
                if ($matches) {
                    $includeMatched = true;
                }
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
            if (is_numeric($v) && (int) $v > 0) {
                return ['id' => (int) $v, 'active' => true];
            }

            return null;
        };
        $resolve = function ($entry) use ($type) {
            if (!$entry || !$entry['active']) {
                return null;
            }

            return Post::where('id', $entry['id'])->where('type', $type)->first();
        };

        // Resolution cascade — a slot renders the FIRST active assignment found:
        //   custom layout (for its targeted content) → Global Layout → theme default.
        // A slot that's toggled OFF or has no section selected resolves to null at that level and
        // falls through to the next, so nothing "resurrects" a disabled section — it just yields.

        // The Global Layout's assignment for this slot, decoded once (used as the fallback below).
        $raw = get_cms_option('falcon_layout_global', null);
        $global = is_string($raw) ? json_decode($raw, true) : $raw;
        $global = is_array($global) ? $global : [];
        $globalSection = function () use ($global, $slot, $entryOf, $resolve) {
            return $resolve($entryOf($global[$slot] ?? null)); // active → section; off/invalid → null
        };

        // 1) PAGE context → the Global Layout owns pages; use it directly (no custom fallthrough).
        if ($isPage) {
            return $globalSection(); // active → section; off/unselected → null → theme default
        }

        // 2) Custom layouts whose conditions match this content — highest priority when active.
        foreach (falcon_get_custom_layouts() as $layout) {
            $conditions = is_array($layout['conditions'] ?? null) ? $layout['conditions'] : [];
            $entry = $entryOf($layout['assignments'][$slot] ?? null);
            if ($entry && falcon_layout_matches($conditions, $ctx)) {
                if ($section = $resolve($entry)) {
                    return $section;
                }
            }
        }

        // 3) No active custom assignment → fall back to the Global Layout's header/footer/etc.
        if ($section = $globalSection()) {
            return $section;
        }

        // 4) Global also off/unselected → theme default.
        return null;
    }
}

if (!function_exists('falcon_layout_slot_off')) {
    /**
     * Whether a Layout slot is EXPLICITLY turned off for the current context — i.e. a section was
     * selected for it but its toggle is inactive. This is the "render nothing" state. It is NOT
     * true when the slot has no section selected at all (that case falls back to the theme default).
     *
     * Three states, distinguished with falcon_layout_assigned_section():
     *   - assigned + active   → assigned_section() returns the section  (slot_off = false)
     *   - assigned + inactive → assigned_section() returns null         (slot_off = TRUE  → nothing)
     *   - not selected        → assigned_section() returns null         (slot_off = false → theme default)
     */
    function falcon_layout_slot_off(string $slot, string $type): bool
    {
        $ctx = falcon_layout_context();
        $isPage = in_array($ctx['kind'] ?? null, ['single', 'home'], true)
            && ($ctx['post_type'] ?? null) === 'page';

        // Mirror falcon_layout_assigned_section(): a valid entry has a section id; missing/0 → null.
        $entryOf = function ($v) {
            if (is_array($v) && !empty($v['id'])) {
                return ['id' => (int) $v['id'], 'active' => !array_key_exists('active', $v) || (bool) $v['active']];
            }
            if (is_numeric($v) && (int) $v > 0) {
                return ['id' => (int) $v, 'active' => true];
            }

            return null;
        };

        // PAGE → the Global Layout's assignment is authoritative.
        if ($isPage) {
            $raw = get_cms_option('falcon_layout_global', null);
            $global = is_string($raw) ? json_decode($raw, true) : $raw;
            $global = is_array($global) ? $global : [];
            if (array_key_exists($slot, $global)) {
                $e = $entryOf($global[$slot]);
                if ($e !== null) {
                    return !$e['active'];
                } // selected → off iff inactive
            }

            return false; // no section selected → not "off" (→ theme default)
        }

        // Custom layouts whose conditions match: first matching layout that HAS this slot selected wins.
        foreach (falcon_get_custom_layouts() as $layout) {
            $conditions = is_array($layout['conditions'] ?? null) ? $layout['conditions'] : [];
            if (!falcon_layout_matches($conditions, $ctx)) {
                continue;
            }
            $e = $entryOf($layout['assignments'][$slot] ?? null);
            if ($e !== null) {
                return !$e['active'];
            } // selected → off iff inactive
        }

        return false; // not selected in any matching layout → theme default
    }
}

if (!function_exists('falcon_layout_is_active')) {
    /**
     * True once the Layout Builder is actually in use — i.e. a Global Layout assignment or any
     * custom layout exists. When active, the site's header/title-bar/footer are controlled by
     * the Layout Builder, so a slot that is disabled or unassigned renders NOTHING (the theme's
     * built-in default chrome is only a fallback for sites that never touched the Layout Builder).
     */
    function falcon_layout_is_active(): bool
    {
        static $active = null;
        if ($active !== null) {
            return $active;
        }

        $global = get_cms_option('falcon_layout_global', null);
        $global = is_string($global) ? json_decode($global, true) : $global;
        if (is_array($global)) {
            foreach ($global as $v) {
                if ((is_array($v) && !empty($v['id'])) || (is_numeric($v) && (int) $v > 0)) {
                    return $active = true;
                }
            }
        }

        $custom = get_cms_option('falcon_layouts', null);
        $custom = is_string($custom) ? json_decode($custom, true) : $custom;
        if (is_array($custom) && !empty($custom)) {
            return $active = true;
        }

        return $active = false;
    }
}

if (!function_exists('_falcon_builder_section_or_null')) {
    /**
     * A layout section's HTML, or null when it would come out as an empty shell.
     *
     * An assigned section with nothing built in it rendered as `<footer class="…"></footer>` —
     * forty-seven characters of nothing, and truthy, so the theme's own footer was skipped and
     * the page ended with no footer at all. Nothing explained it: the section WAS active and
     * assigned, it simply had no content, and the two states looked identical from the outside.
     *
     * Returning null lets the slot fall through to the theme default, which is what an empty
     * section means. A section with anything in it at all is rendered untouched.
     */
    function _falcon_builder_section_or_null(?string $raw, string $tag, string $wrapperClass): ?string
    {
        $html = _falcon_builder_render_wrapper((string) $raw, $tag, $wrapperClass);

        $t = preg_quote($tag, '#');
        $inner = preg_replace('#^<'.$t.'(?:\s[^>]*)?>(.*)</'.$t.'>$#is', '$1', trim($html));

        return trim((string) $inner) === '' ? null : $html;
    }
}

if (!function_exists('get_falcon_header')) {
    function get_falcon_header()
    {
        $header = falcon_layout_assigned_section('header', 'falcon_header');
        if ($header) {
            return _falcon_builder_section_or_null($header->content ?? '', 'header', 'falcon-builder-header');
        }

        return null;
    }
}

if (!function_exists('get_falcon_footer')) {
    function get_falcon_footer()
    {
        $footer = falcon_layout_assigned_section('footer', 'falcon_footer');
        if ($footer) {
            return _falcon_builder_section_or_null($footer->content ?? '', 'footer', 'falcon-builder-footer');
        }

        return null;
    }
}

if (!function_exists('get_falcon_page_title_bar')) {
    function get_falcon_page_title_bar()
    {
        $ptb = falcon_layout_assigned_section('page_title_bar', 'falcon_ptb');
        if ($ptb) {
            return _falcon_builder_section_or_null($ptb->content ?? '', 'div', 'falcon-builder-ptb');
        }

        return null;
    }
}

if (!function_exists('get_falcon_content')) {
    function get_falcon_content()
    {
        $content = falcon_layout_assigned_section('content', 'falcon_content');
        if ($content) {
            return _falcon_builder_section_or_null($content->content ?? '', 'div', 'falcon-builder-content');
        }

        return null;
    }
}
