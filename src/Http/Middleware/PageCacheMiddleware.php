<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class PageCacheMiddleware
{
    /**
     * Session keys that make a page personal.
     *
     * The header carries a basket count and the coupon line is part of every total, so a
     * page rendered while any of these is set is a page about one visitor. Storing it under
     * a key made only of the URL would hand it to the next person who asks for the address.
     */
    private const PERSONAL_SESSION_KEYS = [
        'falcon_cart',
        'falcon_coupons',
        'falcon_coupon',
        'falcon_shipping_method',
        'falcon_shipping_country',
        'falcon_billing_country',
        'last_order_id',
    ];

    /**
     * Routes whose answer belongs to one visitor even when the session is empty.
     *
     * Matched on the route name, so a site is free to move the addresses. An empty basket is
     * as personal as a full one: cache "your cart is empty" once and the next shopper is told
     * their basket is empty too.
     */
    private const PERSONAL_ROUTES = [
        'shop.cart',
        'shop.checkout',
        'shop.confirmation',
        'shop.track',
        'shop.wishlist',
        'shop.download',
        'shop.magic',
        'shop.account',
        'shop.payment',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Only cache GET requests and only if not logged in as admin (to avoid caching admin-only views)
        if (!$request->isMethod('get') || auth()->check() || $request->ajax()) {
            return $next($request);
        }

        if ($this->isPersonal($request)) {
            return $next($request);
        }

        // Create a unique key based on the URL and query parameters
        $key = 'page_cache_'.md5($request->fullUrl());

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
            // Asked again after the request has run: a page that put something in the session
            // on its way through — the first add-to-cart, a coupon, a resolved country — was
            // shared when it arrived and is personal by the time it leaves.
            if ($this->isPersonal($request)) {
                return $response;
            }

            Cache::put($key, [
                'content' => $response->getContent(),
                'type' => $response->headers->get('Content-Type'),
            ], now()->addHours(24));

            $response->header('X-Lazy-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Whether this page is about the visitor rather than about the site.
     *
     * Both halves are needed. The route list alone would still cache the home page of a
     * shopper carrying three items, because the header shows the count; the session check
     * alone would still cache an empty cart page and show it to someone holding a full one.
     */
    private function isPersonal(Request $request): bool
    {
        foreach (self::PERSONAL_SESSION_KEYS as $sessionKey) {
            if (!empty(Session::get($sessionKey))) {
                return true;
            }
        }

        $name = (string) ($request->route()?->getName() ?? '');

        foreach (self::PERSONAL_ROUTES as $route) {
            if ($name === $route || str_starts_with($name, $route.'.')) {
                return true;
            }
        }

        return false;
    }
}
