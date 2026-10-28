<?php

namespace FalconShop\Http\Middleware;

use Closure;
use FalconCms\Core\Models\Product;
use FalconCms\Core\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

/**
 * Keeps the guest cart alive for up to a year, independent of the PHP session
 * lifetime. The cart normally lives in Session('falcon_cart'), which is wiped when
 * the (short-lived) session expires or the browser is closed. This middleware
 * mirrors that cart into a long-lived, encrypted cookie:
 *   - before the request: if the session has no cart, rehydrate it from the cookie;
 *   - after the request:  write the current cart back to the cookie, refreshing
 *     the 1-year window (so the cart survives browser/PC restarts).
 *
 * The cookie holds only what identifies each line (product, variation, quantity and the
 * shopper's own custom fields); names, prices and pictures are read again from the shop
 * on the way back. A whole cart in the cookie outgrew the browser's 4 KB cookie limit and
 * the web server's header buffer after a few lines (nginx answered 502).
 */
class PersistCart
{
    /** Persistent cart cookie name. */
    protected string $cookie = 'falcon_cart_v1';

    /** Cookie lifetime in minutes (365 days). */
    protected int $minutes = 525600;

    /**
     * The most JSON the cookie may carry (about 18 plain lines). Encryption makes it about
     * 1.8 times longer, and every response header together must fit nginx's default 4 KB
     * buffer next to the session and XSRF cookies, the security headers and any other
     * plugin's cookie. A bigger cart stays in the session only.
     */
    protected int $maxBytes = 500;

    public function handle(Request $request, Closure $next)
    {
        $isStorefront = !$request->is('admin*') && !$request->is('api*');

        // Rehydrate the session cart from the long-lived cookie when needed.
        if ($isStorefront && empty(Session::get('falcon_cart', []))) {
            $raw = $request->cookie($this->cookie);
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                $cart = is_array($decoded) ? $this->restore($decoded) : [];
                if (!empty($cart)) {
                    Session::put('falcon_cart', $cart);
                    if (function_exists('falcon_refresh_cart_prices')) {
                        falcon_refresh_cart_prices();
                    }
                }
            }
        }

        $response = $next($request);

        // Persist the current cart back to the cookie (refresh the 1-year window).
        if ($isStorefront) {
            $cart = Session::get('falcon_cart', []);
            $value = !empty($cart) ? $this->compact($cart) : null;
            if ($value !== null) {
                Cookie::queue($this->cookie, $value, $this->minutes);
            } elseif ($request->cookie($this->cookie)) {
                // Cart was emptied (or is too big to keep) — drop the persistent cookie too.
                Cookie::queue(Cookie::forget($this->cookie));
            }
        }

        return $response;
    }

    /** The cookie's value: each line's key, product, variation, quantity and custom fields. */
    protected function compact(array $cart): ?string
    {
        $lines = [];
        foreach ($cart as $key => $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $line = ['i' => (int) $item['id'], 'q' => max(1, (int) ($item['quantity'] ?? 1))];
            if (!empty($item['variation_id'])) {
                $line['v'] = (int) $item['variation_id'];
            }
            if (!empty($item['meta'])) {
                $line['m'] = $item['meta'];
            }
            $lines[(string) $key] = $line;
        }
        if ($lines === []) {
            return null;
        }

        $json = json_encode(['v' => 2, 'l' => $lines]);

        return $json !== false && strlen($json) <= $this->maxBytes ? $json : null;
    }

    /**
     * The cart from the cookie: each line built again from the product, the way adding it to
     * the cart builds it. A cookie written before the compact form holds whole lines.
     */
    protected function restore(array $stored): array
    {
        if (($stored['v'] ?? null) !== 2 || !is_array($stored['l'] ?? null)) {
            return array_filter($stored, fn ($item) => is_array($item) && !empty($item['id']) && isset($item['name']));
        }

        $lines = array_filter($stored['l'], fn ($line) => is_array($line) && !empty($line['i']));
        $products = Product::with('shopData')->whereIn('id', array_map(fn ($line) => (int) $line['i'], $lines))->get()->keyBy('id');

        $cart = [];
        foreach ($lines as $key => $line) {
            $product = $products[(int) $line['i']] ?? null;
            if (!$product) {
                continue;
            }
            $variation = null;
            if (!empty($line['v'])) {
                $variation = ProductVariation::find((int) $line['v']);
                if (!$variation || (int) $variation->product_id !== (int) ($product->shopData->id ?? 0)) {
                    continue;
                }
            }

            $name = $product->title;
            if ($variation) {
                $name .= ' - '.collect((array) $variation->attributes_data)->map(fn ($v, $k) => "$k: $v")->implode(', ');
            }

            $item = [
                'id' => $product->id,
                'name' => $name,
                'slug' => $product->slug,
                'price' => $variation ? $variation->price : $product->price,
                'sale_price' => $variation ? $variation->sale_price : $product->sale_price,
                'quantity' => max(1, (int) ($line['q'] ?? 1)),
                'thumbnail' => ($variation && $variation->image) ? $variation->image : $product->featured_image,
                'variation_id' => $variation?->id,
                'sku' => $variation ? $variation->sku : $product->sku,
                'meta' => is_array($line['m'] ?? null) ? $line['m'] : [],
            ];
            $cart[$key] = apply_falcon_filters('falcon_cart_item_data', $item, $product, $variation);
        }

        return $cart;
    }
}
