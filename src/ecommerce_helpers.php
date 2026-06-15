<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('get_shop_option')) {
    function get_shop_option($key, $default = null)
    {
        $value = get_cms_option($key, $default);
        
        // Handle JSON decoding for shop options (e.g. lists of countries)
        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return $value;
    }
}

if (!function_exists('update_shop_option')) {
    function update_shop_option($key, $value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        return update_cms_option($key, $value);
    }
}

if (!function_exists('is_lazy_shop_page')) {
    function is_lazy_shop_page($post) {
        if (!$post) return false;
        $id = (int) get_shop_option('shop_shop_page_id');
        if (!$id) return false;
        return ($post->id == $id || (isset($post->origin_id) && $post->origin_id == $id));
    }
}

if (!function_exists('is_falcon_cart_page')) {
    function is_falcon_cart_page($post) {
        if (!$post) return false;
        $id = (int) get_shop_option('shop_cart_page_id');
        if (!$id) return false;
        return ($post->id == $id || (isset($post->origin_id) && $post->origin_id == $id));
    }
}

if (!function_exists('is_lazy_checkout_page')) {
    function is_lazy_checkout_page($post) {
        if (!$post) return false;
        $id = (int) get_shop_option('shop_checkout_page_id');
        if (!$id) return false;
        return ($post->id == $id || (isset($post->origin_id) && $post->origin_id == $id));
    }
}

if (!function_exists('is_lazy_account_page')) {
    function is_lazy_account_page($post) {
        if (!$post) return false;
        $id = (int) get_shop_option('shop_account_page_id');
        if (!$id) return false;
        return ($post->id == $id || (isset($post->origin_id) && $post->origin_id == $id));
    }
}

if (!function_exists('get_lazy_account_url')) {
    function get_lazy_account_url() {
        $id = (int) get_shop_option('shop_account_page_id');
        if ($id) {
            $post = \FalconCms\Core\Models\Post::find($id);
            if ($post) return get_falcon_permalink($post);
        }
        return url('/page/account');
    }
}
