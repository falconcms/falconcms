<?php

namespace FalconCms\Core\Services;

/**
 * A WordPress post's SEO, as its SEO plugin stored it, in FalconCMS's own seo_meta shape.
 *
 * Reads Yoast SEO, Rank Math, All in One SEO and SEOPress, whichever the post carries (the
 * first that has a value wins, field by field). Their title templates ("%%title%% %%sep%%
 * %%sitename%%") are filled in; a title that is nothing but the template is left out, so the
 * CMS writes its own default instead of a frozen copy of the old one.
 */
final class WordPressSeoMeta
{
    /** meta keys per field, most used plugin first */
    private const KEYS = [
        'title' => ['_yoast_wpseo_title', 'rank_math_title', '_aioseo_title', '_aioseop_title', '_seopress_titles_title'],
        'description' => ['_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_aioseop_description', '_seopress_titles_desc'],
        'focus_keyword' => ['_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_aioseo_keyphrases', '_seopress_analysis_target_kw'],
        'canonical_url' => ['_yoast_wpseo_canonical', 'rank_math_canonical_url', '_aioseo_canonical_url', '_aioseop_custom_link', '_seopress_robots_canonical'],
        'og_title' => ['_yoast_wpseo_opengraph-title', 'rank_math_facebook_title', '_aioseo_og_title', '_seopress_social_fb_title'],
        'og_description' => ['_yoast_wpseo_opengraph-description', 'rank_math_facebook_description', '_aioseo_og_description', '_seopress_social_fb_desc'],
        'og_image' => ['_yoast_wpseo_opengraph-image', 'rank_math_facebook_image', '_seopress_social_fb_img'],
        'twitter_title' => ['_yoast_wpseo_twitter-title', 'rank_math_twitter_title', '_aioseo_twitter_title', '_seopress_social_twitter_title'],
        'twitter_image' => ['_yoast_wpseo_twitter-image', 'rank_math_twitter_image', '_seopress_social_twitter_img'],
    ];

    /**
     * @param  array<string, string>  $meta  the post's postmeta, key => value
     * @return array<string, string>|null null when no SEO plugin left anything
     */
    public static function fromMeta(array $meta, string $postTitle = '', string $siteTitle = '', string $excerpt = ''): ?array
    {
        $vars = [
            'title' => $postTitle, 'sitename' => $siteTitle, 'sitetitle' => $siteTitle, 'site_title' => $siteTitle, 'post_title' => $postTitle,
            'excerpt' => $excerpt, 'post_excerpt' => $excerpt,
            'sep' => (string) get_cms_option('seo_separator', '-'), 'separator_sa' => (string) get_cms_option('seo_separator', '-'),
        ];

        $seo = [];
        foreach (self::KEYS as $field => $keys) {
            foreach ($keys as $key) {
                $value = self::value($meta[$key] ?? '', $field);
                if ($field === 'title' || $field === 'description' || str_ends_with($field, '_title') || str_ends_with($field, '_description')) {
                    $value = self::fill($value, $vars);
                }
                if ($value !== '') {
                    $seo[$field] = $value;
                    break;
                }
            }
        }

        // noindex / nofollow: each plugin says it its own way.
        $robots = strtolower(implode(' ', array_filter([
            ($meta['_yoast_wpseo_meta-robots-noindex'] ?? '') === '1' ? 'noindex' : '',
            ($meta['_yoast_wpseo_meta-robots-nofollow'] ?? '') === '1' ? 'nofollow' : '',
            self::flatten($meta['rank_math_robots'] ?? ''),
            self::flatten($meta['_aioseo_robots_noindex'] ?? '') === '1' ? 'noindex' : '',
            self::flatten($meta['_aioseo_robots_nofollow'] ?? '') === '1' ? 'nofollow' : '',
            ($meta['_aioseop_noindex'] ?? '') === 'on' ? 'noindex' : '',
            ($meta['_aioseop_nofollow'] ?? '') === 'on' ? 'nofollow' : '',
            ($meta['_seopress_robots_index'] ?? '') === 'yes' ? 'noindex' : '',
            ($meta['_seopress_robots_follow'] ?? '') === 'yes' ? 'nofollow' : '',
        ])));
        if (preg_match('/\bnoindex\b/', $robots)) {
            $seo['noindex'] = '1';
        }
        if (preg_match('/\bnofollow\b/', $robots)) {
            $seo['nofollow'] = '1';
        }

        return $seo === [] ? null : $seo;
    }

    /** A stored value as text: serialized or JSON lists (keyphrases, robots) are read too. */
    private static function value($raw, string $field): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        // AIOSEO keeps keyphrases as JSON: {"focus":{"keyphrase":"…"}}
        if ($field === 'focus_keyword' && str_starts_with($raw, '{')) {
            $json = json_decode($raw, true);

            return trim((string) ($json['focus']['keyphrase'] ?? ''));
        }
        // Rank Math allows several focus keywords, comma-separated: the first is the main one.
        if ($field === 'focus_keyword' && str_contains($raw, ',')) {
            return trim(explode(',', $raw)[0]);
        }

        return html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** "a:2:{i:0;s:5:"index";i:1;s:8:"nofollow";}" or ["noindex"] → "index nofollow" */
    private static function flatten(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^a:\d+:\{/', $raw)) {
            preg_match_all('/s:\d+:"([^"]*)"/', $raw, $m); // read without unserialize(): no objects from an import file

            return implode(' ', $m[1]);
        }
        $json = json_decode($raw, true);

        return is_array($json) ? implode(' ', array_map('strval', $json)) : $raw;
    }

    /**
     * Fill a title template. One made only of variables and separators is the plugin's default
     * pattern, not something the author wrote: it is dropped.
     */
    private static function fill(string $value, array $vars): string
    {
        if ($value === '' || !str_contains($value, '%')) {
            return $value;
        }
        $pattern = '/%%?([a-z_]+)%?%/i';
        $literal = trim(preg_replace([$pattern, '/[\s\-–—|:·•\/]+/u'], '', $value) ?? '');
        if ($literal === '') {
            return '';
        }
        $filled = preg_replace_callback($pattern, fn ($m) => $vars[strtolower($m[1])] ?? '', $value) ?? $value;

        return trim(preg_replace('/\s{2,}/', ' ', $filled) ?? $filled);
    }
}
