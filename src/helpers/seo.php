<?php

/**
 * Search-engine helpers the core asks, whoever answers them.
 *
 * Loaded by src/helpers.php.
 */
if (!function_exists('falcon_site_hidden_from_search')) {
    /**
     * Whether the whole site is kept out of search engines (a demo, a staging copy, a site
     * still being built). The Falcon SEO plugin answers from its "Hide this site from search
     * engines" setting; without it nothing is hidden.
     */
    function falcon_site_hidden_from_search(): bool
    {
        return (bool) apply_falcon_filters('falcon_site_hidden_from_search', false);
    }
}
