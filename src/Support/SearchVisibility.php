<?php

namespace FalconCms\Core\Support;

/**
 * "Hide this site from search engines": for a demo, a staging copy or a site still being
 * built. Every response then says noindex (a robots meta tag on pages, an X-Robots-Tag header
 * on everything else), the sitemap is withdrawn and IndexNow stays quiet.
 *
 * robots.txt deliberately keeps allowing crawling: a page Google may not fetch is a page
 * whose noindex it never sees, so a Disallow would keep already-indexed URLs in the results.
 */
final class SearchVisibility
{
    public const OPTION = 'seo_hide_site';

    public static function hidden(): bool
    {
        return (string) get_cms_option(self::OPTION, '0') === '1';
    }
}
