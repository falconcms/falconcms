<?php

namespace FalconShop\Http;

use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\Post;
use FalconShop\Templates;

/**
 * Renders the pages assigned as Shop, Cart, Checkout and Account (Shop settings) — moved from
 * the CMS's FrontendController, which now asks plugins through falcon_frontend_page_response.
 * Any other page gets null back, and renders as an ordinary page.
 */
class AssignedShopPages
{
    /** This request is drawing one of the visitor's own pages. */
    public static function markPersonal(): void
    {
        falcon_request_memo('shop_personal_page')['served'] = true;
    }

    /**
     * `falcon_page_cache_is_personal`: asked again once the page has run, so a cart, checkout,
     * account or wishlist page is known by what it drew, not by its address.
     */
    public static function personal($personal, $request = null): bool
    {
        return (bool) $personal || falcon_request_memo('shop_personal_page')->offsetExists('served');
    }

    public static function respond($response, Post $post)
    {
        if ($response !== null) {
            return $response;
        }

        // Check if this page is assigned as a special Shop Page
        $shopPageId = get_shop_option('shop_shop_page_id');
        $cartPageId = get_shop_option('shop_cart_page_id');
        $checkoutPageId = get_shop_option('shop_checkout_page_id');
        $accountPageId = get_shop_option('shop_account_page_id');
        $wishlistPageId = (int) get_shop_option('shop_wishlist_page_id');

        // Cart, checkout, account and wishlist belong to one visitor (a basket, a sign-in form and
        // its CSRF token): never stored by the page cache, at whatever address the owner gave them.
        if (in_array((int) $post->id, array_map('intval', array_filter([$cartPageId, $checkoutPageId, $accountPageId, $wishlistPageId])), true)) {
            self::markPersonal();
        }

        // Cart / checkout / account / wishlist are the transactional storefront — a Pro feature that
        // locks the moment the freemium grace window ends (strict = license OR grace, so
        // grandfathering is ignored, mirroring EnsurePro:ecommerce,strict on the shop routes).
        if (!falcon_pro_editable('ecommerce')
            && in_array($post->id, array_filter([$cartPageId, $checkoutPageId, $accountPageId, $wishlistPageId]))) {
            return response()->view('falcon-cms::pro-required', [
                'message' => 'This feature is available in the Pro version.',
            ], 200);
        }

        // The Shop listing page stays reachable whenever ecommerce is available
        // (grandfather-inclusive), so products can still be browsed after grace ends.
        if ($post->id == $shopPageId && !falcon_pro('ecommerce')) {
            return response()->view('falcon-cms::pro-required', [
                'message' => 'This feature is available in the Pro version.',
            ], 200);
        }

        // The page assigned as the Wishlist shows the visitor's wishlist (it signs a guest in first).
        if ($wishlistPageId && (int) $post->id === $wishlistPageId) {
            return app(Controllers\WishlistController::class)->index();
        }

        if ($post->id == $shopPageId) {
            $postsQuery = Post::where('posts.type', 'product')
                ->where('posts.lang_code', app()->getLocale())
                ->where('posts.status', 'published')
                // Eager-load what the product card needs so the category shows and
                // there's no N+1 (and it works under strict lazy-loading in prod).
                ->with(['taxonomyTerms', 'productCategories', 'shopData.variations']);

            // Sidebar options come from the *unfiltered* set, so deselecting a filter is always
            // possible — a panel that removes its own options as you use it is a dead end.
            $filterOptions = falcon_product_filter_options(fn () => Post::where('posts.type', 'product')
                ->where('posts.lang_code', app()->getLocale())
                ->where('posts.status', 'published'));

            falcon_apply_product_filters($postsQuery);
            falcon_apply_product_sorting($postsQuery);

            $posts = $postsQuery->paginate(12)->withQueryString();
            $title = $post->title;
            $type = 'Shop';
            falcon_layout_context(['kind' => 'archive', 'post_type' => 'product']);

            return view(Templates::view('archive-product'), compact('posts', 'title', 'type', 'post', 'filterOptions'));
        }

        if ($post->id == $cartPageId) {
            $cart = session()->get('falcon_cart', []);

            return view(Templates::view('ecommerce.cart'), compact('cart', 'post'));
        }

        if ($post->id == $checkoutPageId) {
            $cart = session()->get('falcon_cart', []);

            return view(Templates::view('ecommerce.checkout'), compact('cart', 'post'));
        }

        if ($post->id == $accountPageId) {
            if (!auth()->check()) {
                return view(Templates::view('ecommerce.account'), [
                    'orders' => null,
                    'post' => $post,
                ]);
            }
            $ordersQuery = Order::with(['items.product'])->where('user_id', auth()->id());
            if (request()->filled('s')) {
                $s = request('s');
                $ordersQuery->where(function ($q) use ($s) {
                    $q->where('order_number', 'like', "%{$s}%")
                        ->orWhere('status', 'like', "%{$s}%");
                });
            }
            // Narrow or extend the customer's order list (hide a vendor's sub-orders, say).
            do_falcon_action('falcon_account_orders_query', $ordersQuery, auth()->user());

            $orders = $ordersQuery->latest()->paginate(8)->withQueryString();

            return view(Templates::view('ecommerce.account'), compact('orders', 'post'));
        }

        return null;
    }
}
