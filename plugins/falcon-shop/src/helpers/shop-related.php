<?php

/**
 * Shop plugin helpers (shop-related): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-related.php.
 */

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\ProductData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

if (!function_exists('falcon_linked_products')) {
    /**
     * Products the shop owner hand-picked for this one.
     *
     * @param  string  $kind  'upsell' (shown on the product page) or 'cross_sell' (shown in the cart)
     * @return Collection<int, Post>
     */
    function falcon_linked_products($product, string $kind = 'upsell', int $limit = 4)
    {
        $shopData = is_object($product) ? ($product->shopData ?? null) : null;
        if (!$shopData) {
            return collect();
        }

        $ids = $kind === 'cross_sell' ? $shopData->cross_sell_ids : $shopData->upsell_ids;
        $ids = array_values(array_filter(array_map('intval', (array) $ids), fn ($id) => $id > 0));

        if (empty($ids)) {
            return collect();
        }

        try {
            $found = Post::whereIn('posts.id', array_slice($ids, 0, max(1, $limit) * 3))
                ->where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->with(['shopData.variations', 'productCategories'])
                ->get();

            // Keep the order the shop owner chose rather than whatever the database returns.
            return $found->sortBy(fn ($p) => array_search($p->id, $ids, true))->take($limit)->values();
        } catch (Throwable $e) {
            Log::error('Linked product lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_related_products')) {
    /**
     * Products related to this one: same category first, topped up with recent products.
     *
     * The templates used to run their own query for this and simply took the four newest products
     * in the shop — so "Related products" under a phone could be a pair of socks. Sharing one
     * helper also means the two single-product templates cannot drift apart again.
     *
     * @return Collection<int, Post>
     */
    function falcon_related_products($product, int $limit = 4)
    {
        $limit = max(1, $limit);

        try {
            $categoryIds = $product->productCategories?->pluck('id')->all() ?? [];

            $base = fn () => Post::where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->where('posts.id', '!=', $product->id)
                ->with(['shopData.variations', 'productCategories']);

            $related = collect();
            if (!empty($categoryIds)) {
                $related = $base()
                    ->whereHas('productCategories', fn ($q) => $q->whereIn('product_categories.id', $categoryIds))
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
            }

            // A product that is alone in its category would otherwise show an empty row.
            if ($related->count() < $limit) {
                $filler = $base()
                    ->whereNotIn('posts.id', $related->pluck('id')->all())
                    ->latest('posts.id')
                    ->limit($limit - $related->count())
                    ->get();

                $related = $related->concat($filler);
            }

            return $related->take($limit)->values();
        } catch (Throwable $e) {
            Log::error('Related product lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_cart_cross_sells')) {
    /**
     * Cross-sells for everything currently in the cart, minus what is already in it.
     *
     * @return Collection<int, Post>
     */
    function falcon_cart_cross_sells(int $limit = 4)
    {
        $cart = session()->get('falcon_cart', []);
        if (empty($cart) || !is_array($cart)) {
            return collect();
        }

        $inCart = [];
        $ids = [];
        foreach ($cart as $item) {
            $productId = (int) ($item['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $inCart[] = $productId;

            $shopData = ProductData::where('post_id', $productId)->first(['cross_sell_ids']);
            foreach ((array) ($shopData->cross_sell_ids ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0 && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        // Suggesting something the shopper has already added is noise.
        $ids = array_values(array_diff($ids, $inCart));
        if (empty($ids)) {
            return collect();
        }

        try {
            return Post::whereIn('posts.id', $ids)
                ->where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->with(['shopData.variations', 'productCategories'])
                ->limit($limit)
                ->get();
        } catch (Throwable $e) {
            Log::error('Cross-sell lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}
