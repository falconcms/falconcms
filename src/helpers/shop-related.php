<?php

/**
 * Shop: linked, related and cross-sell products, and the wishlist.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Wishlist;

if (!function_exists('falcon_wishlist_product_ids')) {
    /**
     * Product IDs in the current user's wishlist (cached per request). Empty for guests.
     */
    function falcon_wishlist_product_ids(): array
    {
        // Container-scoped, never static: this list belongs to one signed-in visitor, and a
        // static would hand it to whoever the worker serves next. See falcon_request_memo().
        $memo = falcon_request_memo('wishlist_product_ids');

        if ($memo->offsetExists('ids')) {
            return $memo['ids'];
        }

        if (!auth()->check()) {
            return $memo['ids'] = [];
        }

        try {
            $ids = Wishlist::where('user_id', auth()->id())->pluck('product_id')->map(fn ($v) => (int) $v)->all();
        } catch (Throwable $e) {
            $ids = [];
        }

        return $memo['ids'] = $ids;
    }
}

if (!function_exists('falcon_in_wishlist')) {
    function falcon_in_wishlist($productId): bool
    {
        return in_array((int) $productId, falcon_wishlist_product_ids(), true);
    }
}

if (!function_exists('falcon_wishlist_count')) {
    function falcon_wishlist_count(): int
    {
        return count(falcon_wishlist_product_ids());
    }
}
