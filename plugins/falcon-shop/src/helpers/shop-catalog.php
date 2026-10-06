<?php

/**
 * Shop plugin helpers (shop-catalog): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-catalog.php.
 */

use FalconCms\Core\Models\Post;

if (!function_exists('get_falcon_cart_url')) {
    function get_falcon_cart_url()
    {
        $pageId = get_shop_option('shop_cart_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            if ($page) {
                return get_falcon_permalink($page);
            }
        }

        return route('shop.cart');
    }
}
