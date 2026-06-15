<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

/**
 * Keeps the guest cart alive for up to a year, independent of the PHP session
 * lifetime. The cart normally lives in Session('lazy_cart'), which is wiped when
 * the (short-lived) session expires or the browser is closed. This middleware
 * mirrors that cart into a long-lived, encrypted cookie:
 *   - before the request: if the session has no cart, rehydrate it from the cookie;
 *   - after the request:  write the current cart back to the cookie, refreshing
 *     the 1-year window (so the cart survives browser/PC restarts).
 */
class PersistCart
{
    /** Persistent cart cookie name. */
    protected string $cookie = 'lazy_cart_v1';

    /** Cookie lifetime in minutes (365 days). */
    protected int $minutes = 525600;

    public function handle(Request $request, Closure $next)
    {
        $isStorefront = !$request->is('admin*') && !$request->is('api*');

        // Rehydrate the session cart from the long-lived cookie when needed.
        if ($isStorefront && empty(Session::get('lazy_cart', []))) {
            $raw = $request->cookie($this->cookie);
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    Session::put('lazy_cart', $decoded);
                }
            }
        }

        $response = $next($request);

        // Persist the current cart back to the cookie (refresh the 1-year window).
        if ($isStorefront) {
            $cart = Session::get('lazy_cart', []);
            if (!empty($cart)) {
                Cookie::queue($this->cookie, json_encode($cart), $this->minutes);
            } elseif ($request->cookie($this->cookie)) {
                // Cart was emptied — drop the persistent cookie too.
                Cookie::queue(Cookie::forget($this->cookie));
            }
        }

        return $response;
    }
}
