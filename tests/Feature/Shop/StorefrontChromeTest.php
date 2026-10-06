<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The shop bits every storefront page carries — the header cart icon and the off-canvas
 * mini-cart — come from the shop plugin through theme hooks, exactly once per page, with
 * the mini-cart's script served from the plugin's assets.
 */
class StorefrontChromeTest extends TestCase
{
    public function test_the_header_cart_icon_and_the_mini_cart_are_on_every_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('cart-count-badge', $html, 'no header cart icon');
        $this->assertSame(1, substr_count($html, 'id="mini-cart-root"'), 'the mini-cart should appear exactly once');
        $this->assertSame(1, preg_match_all('#/plugin-assets/falcon-shop/frontend/js/mini-cart\.js\?v=\d+#', $html));
        $this->assertStringContainsString('window.FalconShopConfig', $html);
        $this->assertStringNotContainsString('window.LazyCart = (function', $html, 'the mini-cart script is still inline');
    }

    public function test_the_mini_cart_script_is_served_from_the_plugin(): void
    {
        $response = $this->get('/plugin-assets/falcon-shop/frontend/js/mini-cart.js');

        $response->assertOk();
        $this->assertStringContainsString('window.LazyCart', $response->streamedContent());
        $this->assertStringContainsString('window.FalconShopConfig.routes', $response->streamedContent());
    }

    /** With the shop on, products are searchable and listed in the sitemap as before. */
    public function test_products_are_in_search_and_the_sitemap(): void
    {
        DB::table('posts')->insert([
            'title' => 'Findable product', 'slug' => 'findable-product', 'type' => 'product', 'status' => 'published',
            'lang_code' => 'en', 'content' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/search?s=Findable')->assertOk()->assertSee('findable-product');
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/search/live?q=Findable')->assertOk()->assertSee('Findable product');
        $this->get('/sitemap.xml')->assertOk()->assertSee('findable-product');
    }

    /** Every storefront asset the templates point at is served from the plugin. */
    public function test_the_storefront_assets_are_served_from_the_plugin(): void
    {
        foreach (['js/mini-cart.js', 'js/wishlist.js', 'js/product-filters.js', 'js/checkout-address-picker.js',
            'css/product-filters.css', 'css/product-variable.css', 'css/wishlist.css'] as $asset) {
            $this->assertNotSame('', falcon_plugin_asset('falcon-shop', 'frontend/'.$asset), "{$asset} is missing");
            $this->get('/plugin-assets/falcon-shop/frontend/'.$asset)->assertOk();
        }
    }

    /** The wishlist button carries its own token: not every theme prints a csrf-token meta tag. */
    public function test_the_wishlist_button_brings_its_own_csrf_token(): void
    {
        $html = view('falcon-shop::frontend.partials.wishlist-button', ['productId' => 1])->render();

        $this->assertStringContainsString('.wishlist = {', $html);
        $this->assertStringContainsString(json_encode(route('shop.wishlist.toggle')), $html);
        $this->assertStringContainsString('csrf:', $html);
        $this->assertStringContainsString('data-no-defer src=', $html);
    }

    /** The pages assigned in the shop settings render the shop through the plugin. */
    public function test_a_page_assigned_as_the_cart_renders_the_cart(): void
    {
        $id = DB::table('posts')->insertGetId([
            'title' => 'Basket', 'slug' => 'basket', 'type' => 'page', 'status' => 'published',
            'lang_code' => 'en', 'content' => '<p>Basket page text</p>', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->setCmsOptions(['shop_cart_page_id' => (string) $id]);

        $this->get('/basket')->assertOk()->assertSee(route('shop.checkout'), false);
    }

    /** A theme that only fires falcon_footer (no falcon_after_footer) still gets one drawer. */
    public function test_a_theme_without_the_after_footer_hook_gets_the_mini_cart_at_the_footer(): void
    {
        ob_start();
        do_falcon_action('falcon_footer');
        do_falcon_action('falcon_footer');
        $html = (string) ob_get_clean();

        $this->assertSame(1, substr_count($html, 'id="mini-cart-root"'));
    }

    /** Layouts published before the move still @include the old partial; it must still work. */
    public function test_a_layout_that_still_includes_the_old_partial_gets_the_mini_cart(): void
    {
        $html = view('falcon-cms::themes.falcon-theme.partials.mini-cart')->render();

        $this->assertSame(1, substr_count($html, 'id="mini-cart-root"'));
    }
}
