<?php

/**
 * The old lazy_* helper names, kept working.
 *
 * Every helper in here was renamed to a falcon_* name so the public API reads as one CMS
 * rather than two. Themes and plugins already in the wild call the old names, and a rename
 * that breaks them on update is not a rename anybody wants — so each old name stays, as a
 * one-line forward to its replacement.
 *
 * Nothing in FalconCMS calls these any more. They exist for code written before the rename,
 * and they are the only place the word "lazy" still appears in the helper API.
 *
 * The one that is not a straight swap is get_lazy_content(). get_falcon_content() was already
 * taken by the layout-section helper, which takes no arguments, so the content renderer became
 * get_falcon_post_content() instead.
 */

if (!function_exists('do_lazy_shortcode')) {
    /** @deprecated Use do_falcon_shortcode(). */
    function do_lazy_shortcode(...$args)
    {
        return do_falcon_shortcode(...$args);
    }
}

if (!function_exists('get_lazy_account_url')) {
    /** @deprecated Use get_falcon_account_url(). */
    function get_lazy_account_url(...$args)
    {
        return get_falcon_account_url(...$args);
    }
}

if (!function_exists('get_lazy_builder_fonts')) {
    /** @deprecated Use get_falcon_builder_fonts(). */
    function get_lazy_builder_fonts(...$args)
    {
        return get_falcon_builder_fonts(...$args);
    }
}

if (!function_exists('get_lazy_categories')) {
    /** @deprecated Use get_falcon_categories(). */
    function get_lazy_categories(...$args)
    {
        return get_falcon_categories(...$args);
    }
}

if (!function_exists('get_lazy_category_taxonomy')) {
    /** @deprecated Use get_falcon_category_taxonomy(). */
    function get_lazy_category_taxonomy(...$args)
    {
        return get_falcon_category_taxonomy(...$args);
    }
}

if (!function_exists('get_lazy_checkout_url')) {
    /** @deprecated Use get_falcon_checkout_url(). */
    function get_lazy_checkout_url(...$args)
    {
        return get_falcon_checkout_url(...$args);
    }
}

if (!function_exists('get_lazy_content')) {
    /** @deprecated Use get_falcon_post_content(). */
    function get_lazy_content(...$args)
    {
        return get_falcon_post_content(...$args);
    }
}

if (!function_exists('get_lazy_menu')) {
    /** @deprecated Use get_falcon_menu(). */
    function get_lazy_menu(...$args)
    {
        return get_falcon_menu(...$args);
    }
}

if (!function_exists('get_lazy_post')) {
    /** @deprecated Use get_falcon_post(). */
    function get_lazy_post(...$args)
    {
        return get_falcon_post(...$args);
    }
}

if (!function_exists('get_lazy_shop_url')) {
    /** @deprecated Use get_falcon_shop_url(). */
    function get_lazy_shop_url(...$args)
    {
        return get_falcon_shop_url(...$args);
    }
}

if (!function_exists('is_lazy_account_page')) {
    /** @deprecated Use is_falcon_account_page(). */
    function is_lazy_account_page(...$args)
    {
        return is_falcon_account_page(...$args);
    }
}

if (!function_exists('is_lazy_checkout_page')) {
    /** @deprecated Use is_falcon_checkout_page(). */
    function is_lazy_checkout_page(...$args)
    {
        return is_falcon_checkout_page(...$args);
    }
}

if (!function_exists('is_lazy_homepage')) {
    /** @deprecated Use is_falcon_homepage(). */
    function is_lazy_homepage(...$args)
    {
        return is_falcon_homepage(...$args);
    }
}

if (!function_exists('is_lazy_shop_page')) {
    /** @deprecated Use is_falcon_shop_page(). */
    function is_lazy_shop_page(...$args)
    {
        return is_falcon_shop_page(...$args);
    }
}

if (!function_exists('lazy_apply_custom_dynamic')) {
    /** @deprecated Use falcon_apply_custom_dynamic(). */
    function lazy_apply_custom_dynamic(...$args)
    {
        return falcon_apply_custom_dynamic(...$args);
    }
}

if (!function_exists('lazy_check_update')) {
    /** @deprecated Use falcon_check_update(). */
    function lazy_check_update(...$args)
    {
        return falcon_check_update(...$args);
    }
}

if (!function_exists('lazy_contrast_color')) {
    /** @deprecated Use falcon_contrast_color(). */
    function lazy_contrast_color(...$args)
    {
        return falcon_contrast_color(...$args);
    }
}

if (!function_exists('lazy_custom_element_render')) {
    /** @deprecated Use falcon_custom_element_render(). */
    function lazy_custom_element_render(...$args)
    {
        return falcon_custom_element_render(...$args);
    }
}

if (!function_exists('lazy_in_wishlist')) {
    /** @deprecated Use falcon_in_wishlist(). */
    function lazy_in_wishlist(...$args)
    {
        return falcon_in_wishlist(...$args);
    }
}

if (!function_exists('lazy_is_special_menu_item')) {
    /** @deprecated Use falcon_is_special_menu_item(). */
    function lazy_is_special_menu_item(...$args)
    {
        return falcon_is_special_menu_item(...$args);
    }
}

if (!function_exists('lazy_lang_switcher')) {
    /** @deprecated Use falcon_lang_switcher(). */
    function lazy_lang_switcher(...$args)
    {
        return falcon_lang_switcher(...$args);
    }
}

if (!function_exists('lazy_mobile_lang_switcher')) {
    /** @deprecated Use falcon_mobile_lang_switcher(). */
    function lazy_mobile_lang_switcher(...$args)
    {
        return falcon_mobile_lang_switcher(...$args);
    }
}

if (!function_exists('lazy_render_product_field')) {
    /** @deprecated Use falcon_render_product_field(). */
    function lazy_render_product_field(...$args)
    {
        return falcon_render_product_field(...$args);
    }
}

if (!function_exists('lazy_render_special_menu_item')) {
    /** @deprecated Use falcon_render_special_menu_item(). */
    function lazy_render_special_menu_item(...$args)
    {
        return falcon_render_special_menu_item(...$args);
    }
}

if (!function_exists('lazy_resolve_token')) {
    /** @deprecated Use falcon_resolve_token(). */
    function lazy_resolve_token(...$args)
    {
        return falcon_resolve_token(...$args);
    }
}

if (!function_exists('lazy_resolve_tokens')) {
    /** @deprecated Use falcon_resolve_tokens(). */
    function lazy_resolve_tokens(...$args)
    {
        return falcon_resolve_tokens(...$args);
    }
}

if (!function_exists('lazy_revision_diff')) {
    /** @deprecated Use falcon_revision_diff(). */
    function lazy_revision_diff(...$args)
    {
        return falcon_revision_diff(...$args);
    }
}

if (!function_exists('lazy_sanitize_builder_json')) {
    /** @deprecated Use falcon_sanitize_builder_json(). */
    function lazy_sanitize_builder_json(...$args)
    {
        return falcon_sanitize_builder_json(...$args);
    }
}

if (!function_exists('lazy_search_form')) {
    /** @deprecated Use falcon_search_form(). */
    function lazy_search_form(...$args)
    {
        return falcon_search_form(...$args);
    }
}

if (!function_exists('lazy_shipping_carriers')) {
    /** @deprecated Use falcon_shipping_carriers(). */
    function lazy_shipping_carriers(...$args)
    {
        return falcon_shipping_carriers(...$args);
    }
}

if (!function_exists('lazy_timezone_list')) {
    /** @deprecated Use falcon_timezone_list(). */
    function lazy_timezone_list(...$args)
    {
        return falcon_timezone_list(...$args);
    }
}

if (!function_exists('render_lazy_form')) {
    /** @deprecated Use render_falcon_form(). */
    function render_lazy_form(...$args)
    {
        return render_falcon_form(...$args);
    }
}

if (!function_exists('render_lazy_widgets')) {
    /** @deprecated Use render_falcon_widgets(). */
    function render_lazy_widgets(...$args)
    {
        return render_falcon_widgets(...$args);
    }
}

if (!function_exists('the_lazy_content')) {
    /** @deprecated Use the_falcon_post_content(). */
    function the_lazy_content(...$args)
    {
        return the_falcon_post_content(...$args);
    }
}

if (!function_exists('the_lazy_loop')) {
    /** @deprecated Use the_falcon_loop(). */
    function the_lazy_loop(...$args)
    {
        return the_falcon_loop(...$args);
    }
}

if (!function_exists('the_lazy_pagination')) {
    /** @deprecated Use the_falcon_pagination(). */
    function the_lazy_pagination(...$args)
    {
        return the_falcon_pagination(...$args);
    }
}

if (!function_exists('the_lazy_search_form')) {
    /** @deprecated Use the_falcon_search_form(). */
    function the_lazy_search_form(...$args)
    {
        return the_falcon_search_form(...$args);
    }
}
