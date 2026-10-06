<?php

/**
 * Stand-ins for the shop plugin's theme-facing helpers, for while the plugin is off.
 *
 * A theme may show a cart total in its header or list related products; with the shop plugin
 * switched off those helpers do not exist, and calling one would end the page. These answer
 * with what an empty shop has: nothing, and zero.
 *
 * NOT in the src/helpers.php list on purpose: the service provider loads this file after the
 * plugins have loaded, so while the shop plugin is on its real helpers are already defined and
 * every function_exists() guard below skips its stand-in.
 */
if (!function_exists('get_falcon_cart_url')) {
    function get_falcon_cart_url()
    {
        return route('shop.cart');
    }
}

if (!function_exists('get_falcon_cart_subtotal')) {
    function get_falcon_cart_subtotal()
    {
        return 0.0;
    }
}

if (!function_exists('get_falcon_cart_total')) {
    function get_falcon_cart_total()
    {
        return 0.0;
    }
}

if (!function_exists('get_falcon_cart_shipping')) {
    function get_falcon_cart_shipping($country = null)
    {
        return 0.0;
    }
}

if (!function_exists('get_falcon_cart_tax')) {
    function get_falcon_cart_tax()
    {
        return 0.0;
    }
}

if (!function_exists('get_falcon_cart_shipping_details')) {
    function get_falcon_cart_shipping_details($country = null)
    {
        return ['cost' => 0.0, 'label' => '', 'method' => null, 'pending' => false];
    }
}

if (!function_exists('get_falcon_coupon_discount_amount')) {
    function get_falcon_coupon_discount_amount($coupon, $cart, $calcBaseSubtotal = null)
    {
        return 0.0;
    }
}

if (!function_exists('falcon_related_products')) {
    function falcon_related_products($product, int $limit = 4)
    {
        return collect();
    }
}

if (!function_exists('falcon_linked_products')) {
    function falcon_linked_products($product, string $kind = 'upsell', int $limit = 4)
    {
        return collect();
    }
}

if (!function_exists('falcon_cart_cross_sells')) {
    function falcon_cart_cross_sells(int $limit = 4)
    {
        return collect();
    }
}
