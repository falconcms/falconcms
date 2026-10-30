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

    /** Stands in for the visitor's CSRF token in a stored page (see withoutVisitorToken()). */
    private const TOKEN_PLACEHOLDER = '__FALCON_CSRF_TOKEN__';

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

        // Check if cache exists. An entry stored before CSRF tokens were swapped out still holds
        // its first visitor's token, so it is rendered afresh instead (and replaced).
        if (Cache::has($key) && get_cms_option('performance_static_caching', '0') === '1') {
            $cacheData = Cache::get($key);

            if (is_array($cacheData) && !empty($cacheData['csrf'])) {
                return response($this->withVisitorToken((string) $cacheData['content']))
                    ->header('Content-Type', $cacheData['type'])
                    ->header('X-Lazy-Cache', 'HIT');
            }
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
            // A stand-in the optimiser served while something it needs is still being prepared
            // (local Google Fonts on their first request): the next request gets the real page,
            // and that is the one to keep.
            if ($response->headers->has(HtmlOptimizeMiddleware::PROVISIONAL_HEADER)) {
                $response->headers->remove(HtmlOptimizeMiddleware::PROVISIONAL_HEADER);

                return $response;
            }

            Cache::put($key, [
                'content' => $this->withoutVisitorToken((string) $response->getContent()),
                'type' => $response->headers->get('Content-Type'),
                'csrf' => true,
            ], now()->addHours(24));

            $response->header('X-Lazy-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * The page as it is stored: this visitor's CSRF token replaced by a placeholder.
     *
     * The token is in every form, the csrf-token meta tag and the shop's scripts (add to cart,
     * wishlist). Stored as-is, the next visitor was served someone else's token and every POST
     * they made — add to cart on a cached product page — failed with 419.
     */
    private function withoutVisitorToken(string $html): string
    {
        $token = $this->visitorToken();

        return $token === null ? $html : str_replace($token, self::TOKEN_PLACEHOLDER, $html);
    }

    /** The stored page with the current visitor's own CSRF token put back in. */
    private function withVisitorToken(string $html): string
    {
        return str_replace(self::TOKEN_PLACEHOLDER, $this->visitorToken() ?? '', $html);
    }

    /** The session's CSRF token, or null when there is no usable one. */
    private function visitorToken(): ?string
    {
        $token = Session::isStarted() ? Session::token() : null;

        // A real token is a long random string; anything short could match ordinary page text.
        return is_string($token) && strlen($token) >= 32 ? $token : null;
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
        // Data flashed by the request before — "Your comment is awaiting moderation", a form's
        // errors and old input — is shown on this page only. Served from the cache the visitor
        // never saw it; stored, every later visitor would have.
        if (Session::isStarted() && (!empty(Session::get('_flash.old')) || !empty(Session::get('_flash.new')))) {
            return true;
        }

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

        // A plugin's own personal pages — a CMS page carrying a login form, say, whose CSRF
        // token must never be served to the next visitor.
        return (bool) apply_falcon_filters('falcon_page_cache_is_personal', false, $request);
    }
}
