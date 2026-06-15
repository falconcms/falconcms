<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PageCacheMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Only cache GET requests and only if not logged in as admin (to avoid caching admin-only views)
        if (!$request->isMethod('get') || auth()->check() || $request->ajax()) {
            return $next($request);
        }

        // Create a unique key based on the URL and query parameters
        $key = 'page_cache_' . md5($request->fullUrl());

        // Check if cache exists
        if (Cache::has($key) && get_cms_option('performance_static_caching', '0') === '1') {
            $cacheData = Cache::get($key);
            return response($cacheData['content'])
                ->header('Content-Type', $cacheData['type'])
                ->header('X-Lazy-Cache', 'HIT');
        }

        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful() && get_cms_option('performance_static_caching', '0') === '1') {
            Cache::put($key, [
                'content' => $response->getContent(),
                'type' => $response->headers->get('Content-Type')
            ], now()->addHours(24));
            
            $response->header('X-Lazy-Cache', 'MISS');
        }

        return $response;
    }
}
