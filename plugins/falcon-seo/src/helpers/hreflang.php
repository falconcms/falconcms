<?php

/**
 * hreflang alternates, printed in the page head by the plugin (SeoServiceProvider).
 */

use FalconCms\Core\Models\Post;
use Illuminate\Support\Facades\DB;

if (!function_exists('falcon_hreflang_links')) {
    /**
     * The hreflang alternates for the page being shown: language code => URL, plus
     * 'x-default' for the default language's version. Empty when the site has one language,
     * or for a page whose alternates cannot be known (an archive, a search).
     *
     * Every version of a post is read as one group (the original and everything with its
     * origin_id), so each page lists the same set and the links always point both ways, the
     * mistake Google sees most. Only published versions in active languages count; a version
     * marked noindex is left out, since a link to it would point Google at a page it may not
     * keep.
     *
     * @return array<string, string>
     */
    function falcon_hreflang_links($post = null): array
    {
        try {
            $languages = DB::table('cms_languages')->where('status', true)->pluck('code')->all();
        } catch (Throwable $e) {
            return [];
        }
        if (count($languages) < 2) {
            return [];
        }
        $default = falcon_default_language();
        $links = [];

        if ($post && !empty($post->id)) {
            $origin = $post->origin_id ?: $post->id;
            $versions = Post::query()->published()
                ->where(fn ($q) => $q->where('id', $origin)->orWhere('origin_id', $origin))
                ->whereIn('lang_code', $languages)
                ->get();
            foreach ($versions as $version) {
                $seo = is_array($version->seo_meta) ? $version->seo_meta : [];
                if (($seo['noindex'] ?? '0') == '1' || isset($links[$version->lang_code])) {
                    continue;
                }
                $links[$version->lang_code] = get_falcon_permalink($version);
            }
        } elseif (in_array(trim(request()->path(), '/'), array_merge([''], $languages), true)) {
            // The home page exists in every language: / for the default, /{code} for the rest.
            foreach ($languages as $code) {
                $links[$code] = $code === $default ? url('/') : url($code);
            }
        }

        // One version alone has no alternates to announce.
        if (count($links) < 2) {
            return [];
        }
        if (isset($links[$default])) {
            $links['x-default'] = $links[$default];
        }

        return $links;
    }
}
