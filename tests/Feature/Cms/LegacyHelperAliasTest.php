<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * The lazy_* helper names still work after the rename.
 *
 * Every helper was renamed to a falcon_* name. Themes and plugins written before that call the
 * old names, and they are loaded by the site rather than shipped with it — so the old names
 * have to keep resolving, and keep returning what they always returned. This pins both: that
 * each alias exists, and that it is really forwarding rather than quietly doing nothing.
 */
class LegacyHelperAliasTest extends TestCase
{
    /** Old name => the falcon_* helper it forwards to. */
    private const ALIASES = [
        'do_lazy_shortcode' => 'do_falcon_shortcode',
        'get_lazy_account_url' => 'get_falcon_account_url',
        'get_lazy_builder_fonts' => 'get_falcon_builder_fonts',
        'get_lazy_categories' => 'get_falcon_categories',
        'get_lazy_category_taxonomy' => 'get_falcon_category_taxonomy',
        'get_lazy_checkout_url' => 'get_falcon_checkout_url',
        'get_lazy_content' => 'get_falcon_post_content',
        'get_lazy_menu' => 'get_falcon_menu',
        'get_lazy_post' => 'get_falcon_post',
        'get_lazy_shop_url' => 'get_falcon_shop_url',
        'is_lazy_account_page' => 'is_falcon_account_page',
        'is_lazy_checkout_page' => 'is_falcon_checkout_page',
        'is_lazy_homepage' => 'is_falcon_homepage',
        'is_lazy_shop_page' => 'is_falcon_shop_page',
        'lazy_apply_custom_dynamic' => 'falcon_apply_custom_dynamic',
        'lazy_check_update' => 'falcon_check_update',
        'lazy_contrast_color' => 'falcon_contrast_color',
        'lazy_custom_element_render' => 'falcon_custom_element_render',
        'lazy_in_wishlist' => 'falcon_in_wishlist',
        'lazy_is_special_menu_item' => 'falcon_is_special_menu_item',
        'lazy_lang_switcher' => 'falcon_lang_switcher',
        'lazy_mobile_lang_switcher' => 'falcon_mobile_lang_switcher',
        'lazy_render_product_field' => 'falcon_render_product_field',
        'lazy_render_special_menu_item' => 'falcon_render_special_menu_item',
        'lazy_resolve_token' => 'falcon_resolve_token',
        'lazy_resolve_tokens' => 'falcon_resolve_tokens',
        'lazy_revision_diff' => 'falcon_revision_diff',
        'lazy_sanitize_builder_json' => 'falcon_sanitize_builder_json',
        'lazy_search_form' => 'falcon_search_form',
        'lazy_shipping_carriers' => 'falcon_shipping_carriers',
        'lazy_timezone_list' => 'falcon_timezone_list',
        'render_lazy_form' => 'render_falcon_form',
        'render_lazy_widgets' => 'render_falcon_widgets',
        'the_lazy_content' => 'the_falcon_post_content',
        'the_lazy_loop' => 'the_falcon_loop',
        'the_lazy_pagination' => 'the_falcon_pagination',
        'the_lazy_search_form' => 'the_falcon_search_form',
    ];

    public function test_every_old_name_still_exists_and_its_replacement_does_too(): void
    {
        foreach (self::ALIASES as $old => $new) {
            $this->assertTrue(function_exists($old), "the old helper {$old}() has gone");
            $this->assertTrue(function_exists($new), "the replacement {$new}() does not exist");
        }
    }

    public function test_no_falcon_helper_is_still_called_lazy(): void
    {
        $source = file_get_contents(__DIR__.'/../../../src/helpers.php')
            .file_get_contents(__DIR__.'/../../../src/ecommerce_helpers.php');

        preg_match_all('/function\s+([a-z_]*lazy[a-z_]*)\s*\(/', $source, $m);

        $this->assertSame([], $m[1], 'a helper is still named with "lazy": '.implode(', ', $m[1]));
    }

    public function test_the_aliases_forward_rather_than_return_nothing(): void
    {
        // A handful with no side effects and a value that is easy to compare.
        $this->assertSame(falcon_contrast_color('#ffffff'), lazy_contrast_color('#ffffff'));
        $this->assertSame(falcon_contrast_color('#000000'), lazy_contrast_color('#000000'));
        $this->assertSame(is_falcon_homepage(null), is_lazy_homepage(null));
        $this->assertSame(get_falcon_shop_url(), get_lazy_shop_url());
        $this->assertSame(falcon_timezone_list(), lazy_timezone_list());
        $this->assertSame(
            get_falcon_post_content('<p>Hello</p>'),
            get_lazy_content('<p>Hello</p>')
        );
    }

    public function test_arguments_reach_the_replacement(): void
    {
        // Two different arguments have to give two different answers, or the alias could be
        // dropping what it was handed.
        $this->assertNotSame(
            get_lazy_content('<p>one</p>'),
            get_lazy_content('<p>two</p>')
        );
    }
}
