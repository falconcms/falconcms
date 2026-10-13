<?php

/**
 * Falcon SEO — bootstrap file.
 *
 * Loaded only while the plugin is active. The plugin holds the CMS's search and speed tools:
 * page analysis, SEO Audit, Bulk SEO, keyword research, link suggestions, structured data,
 * hreflang, IndexNow, AI crawler rules, the 404 log, search visibility, AVIF copies,
 * speculative loading, Web Vitals and Cloudflare purge.
 *
 * Layout of this plugin:
 *   resources/views/admin   screens and the parts it adds to core screens ("falcon-seo::...")
 *   routes/web.php, admin.php
 *   src/                    classes (namespace FalconSeo\), hooks in SeoServiceProvider
 *   src/helpers/            global helpers (falcon_hreflang_links)
 *
 * Its data (the 404 log, keyword volumes, Web Vitals) and the basic SEO the CMS always does
 * (titles, descriptions, canonical, sitemap, robots.txt, redirects) stay in the core, so
 * switching this plugin off never loses a row and never leaves a page without its meta tags.
 */
foreach (glob(__DIR__.'/src/helpers/*.php') ?: [] as $falconSeoHelpers) {
    require_once $falconSeoHelpers;
}
unset($falconSeoHelpers);
