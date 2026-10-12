<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Support\SearchVisibility;
use Illuminate\Http\Request;

/**
 * While the site is hidden from search engines, every response carries X-Robots-Tag: noindex.
 * The header reaches what a robots meta tag cannot: 404s, shop endpoints, feeds, files served
 * through Laravel. Runs in the web group, so plugin routes are covered too.
 */
class SearchVisibilityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Crawlers only GET. Reading the option after a POST would also refill a settings cache
        // that request may just have cleared on purpose (Customizer → Clear caches).
        if (!$request->isMethodCacheable() || $request->is('robots.txt')) {
            return $response;
        }

        if (SearchVisibility::hidden()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
