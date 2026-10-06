<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The site with the shop plugin switched off: nothing of the shop runs, and nothing breaks.
 *
 * Themes (and copies of them published into sites) call route('shop.cart') directly, so the
 * names must still resolve; the URLs behave as if there had never been a shop — an ordinary
 * page with that slug if there is one, the theme's 404 if not.
 *
 * Each test runs in its own process: with the plugin off, the core defines stand-ins for the
 * shop's helpers, and a function, once defined, would outlive this class and shadow the real
 * helpers in every test after it.
 */
#[RunTestsInSeparateProcesses]
class ShopPluginOffTest extends TestCase
{
    use MakesShopFixtures;

    /**
     * Through the env, before the application exists: plugins load while the providers
     * register, which is before defineEnvironment() runs, so setting the config there would
     * be too late.
     */
    protected function setUp(): void
    {
        putenv('FALCON_DISABLED_PLUGINS=falcon-shop');
        $_ENV['FALCON_DISABLED_PLUGINS'] = $_SERVER['FALCON_DISABLED_PLUGINS'] = 'falcon-shop';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('FALCON_DISABLED_PLUGINS');
        unset($_ENV['FALCON_DISABLED_PLUGINS'], $_SERVER['FALCON_DISABLED_PLUGINS']);
    }

    /** @return array<string, string> name => uri */
    private function shopRouteNames(): array
    {
        $names = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'shop.')) {
                $names[$route->getName()] = $route->uri();
            }
        }

        return $names;
    }

    public function test_the_plugin_is_not_loaded(): void
    {
        $this->assertFalse(falcon_plugin_active('falcon-shop'));
    }

    public function test_every_shop_route_name_still_resolves_to_a_url(): void
    {
        $names = $this->shopRouteNames();
        $this->assertCount(30, $names);

        foreach ($names as $name => $uri) {
            $params = [];
            preg_match_all('/\{(\w+)\??\}/', $uri, $m);
            foreach ($m[1] as $p) {
                $params[$p] = 1;
            }
            $this->assertNotSame('', route($name, $params), "{$name} did not resolve");
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'shop.')) {
                $this->assertStringNotContainsString('ShopFrontendController', $route->getActionName(), $route->getName().' still runs shop code');
            }
        }
    }

    /** The header and footer of every page link to the shop by route name; they must still render. */
    public function test_pages_that_link_to_the_shop_still_render(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_a_shop_url_with_no_page_behind_it_is_a_plain_404(): void
    {
        $this->get('/cart')->assertNotFound();
        $this->get('/checkout')->assertNotFound();
        $this->get('/wishlist')->assertNotFound();
        $this->get('/track-order')->assertNotFound();
    }

    public function test_a_page_that_happens_to_be_called_cart_is_just_a_page(): void
    {
        DB::table('posts')->insert([
            'title' => 'Cart', 'slug' => 'cart', 'type' => 'page', 'status' => 'published',
            'lang_code' => 'en', 'content' => '<p>Our basket page</p>', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/cart')->assertOk()->assertSee('Our basket page', false);
    }

    public function test_shop_actions_answer_404_instead_of_running(): void
    {
        $this->withoutMiddleware();

        $this->post('/cart/add', ['product_id' => 1, 'quantity' => 1])->assertNotFound();
        $this->post('/checkout')->assertNotFound();
        $this->post('/payment/stripe/webhook')->assertNotFound();
        $this->post('/wishlist/toggle')->assertNotFound();
    }

    public function test_products_are_not_served_without_the_shop(): void
    {
        DB::table('posts')->insert([
            'title' => 'Hidden product', 'slug' => 'hidden-product', 'type' => 'product', 'status' => 'published',
            'lang_code' => 'en', 'content' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/product/hidden-product')->assertNotFound();
        $this->get('/hidden-product')->assertNotFound();
    }

    /** Search and the sitemap must not list pages that would answer 404. */
    public function test_products_are_left_out_of_search_and_the_sitemap(): void
    {
        foreach ([['Findable product', 'findable-product', 'product'], ['Findable post', 'findable-post', 'post']] as [$title, $slug, $type]) {
            DB::table('posts')->insert([
                'title' => $title, 'slug' => $slug, 'type' => $type, 'status' => 'published',
                'lang_code' => 'en', 'content' => '', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->get('/search?s=Findable')->assertOk()->assertSee('findable-post')->assertDontSee('findable-product');
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/search/live?q=Findable')->assertOk()->assertDontSee('Findable product');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('findable-product');
    }

    public function test_a_page_assigned_as_the_cart_is_just_its_own_content(): void
    {
        $id = DB::table('posts')->insertGetId([
            'title' => 'Basket', 'slug' => 'basket', 'type' => 'page', 'status' => 'published',
            'lang_code' => 'en', 'content' => '<p>Basket page text</p>', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->setCmsOptions(['shop_cart_page_id' => (string) $id]);

        $html = $this->get('/basket')->assertOk()->getContent();
        $this->assertStringContainsString('Basket page text', $html);
        $this->assertStringNotContainsString('cart/update', $html, 'the cart was rendered with the shop off');
    }

    public function test_no_shop_markup_or_script_reaches_a_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('mini-cart-root', $html);
        $this->assertStringNotContainsString('mini-cart.js', $html);
        $this->assertStringNotContainsString('FalconShopConfig', $html);
        $this->assertStringNotContainsString('cart-count-badge', $html);

        // a layout published before the move still includes the old partial: it renders nothing
        $this->assertSame('', trim(view('falcon-cms::themes.falcon-theme.partials.mini-cart')->render()));
    }

    /** The basket cookie is the shop's: no shop, no cookie. */
    public function test_no_basket_cookie_is_written(): void
    {
        session()->put('falcon_cart', ['k' => ['id' => 1, 'quantity' => 1]]);

        $this->get('/')->assertOk()->assertCookieMissing('falcon_cart_v1');
    }

    public function test_no_plugin_asset_is_served(): void
    {
        $this->get('/plugin-assets/falcon-shop/frontend/css/anything.css')->assertNotFound();
    }

    /**
     * A route cache built while the shop was on still names its controllers. They answer 404
     * rather than "Target class does not exist", and no other shop class becomes loadable.
     */
    public function test_a_stale_route_to_a_shop_controller_is_a_404_not_a_500(): void
    {
        Route::get('/zz-stale-cart', ['FalconShop\\Http\\Controllers\\ShopFrontendController', 'cart']);
        Route::post('/zz-stale-wishlist', 'FalconShop\\Http\\Controllers\\WishlistController@toggle');

        $this->get('/zz-stale-cart')->assertNotFound();
        $this->withoutMiddleware()->post('/zz-stale-wishlist')->assertNotFound();

        $this->assertFalse(class_exists('FalconShop\\Templates'), 'only controllers get the stand-in');
        $this->assertFalse(class_exists('FalconCms\\Core\\Http\\Controllers\\NoSuchController'), 'core names are left alone');
    }

    private function actingAsAdmin(): void
    {
        $this->withProLicensed();
        $this->actingAs($this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]));
    }

    /** The back office has no shop either: its screens are 404s, the dashboard still opens. */
    public function test_the_shop_back_office_is_gone_but_the_dashboard_opens(): void
    {
        $this->actingAsAdmin();

        foreach (['/admin/shop/overview', '/admin/shop/orders', '/admin/shop/settings', '/admin/shop/reports',
            '/admin/product-categories', '/admin/product-tags', '/admin/posts?type=product'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/admin/shop/settings')->assertNotFound();

        $this->get('/admin')->assertOk()->assertDontSee('/plugin-assets/falcon-shop/', false);
        $this->get('/admin/posts?type=page')->assertOk();
    }

    public function test_the_sidebar_drops_shop_and_products(): void
    {
        $this->seed(MenuSeeder::class);

        $titles = (new Sidebar)->menuGroups->flatten(1)->pluck('title')->all();

        $this->assertNotContains('Shop', $titles);
        $this->assertNotContains('Products', $titles);
        $this->assertContains('Pages', $titles);
    }

    /** The shop's settings page is a dormant twin while it is off: no Settings link to it. */
    public function test_the_plugins_screen_has_no_settings_link_while_the_shop_is_off(): void
    {
        $this->actingAsAdmin();

        $this->assertNull(app(PluginManager::class)->all()['falcon-shop']['settings_url']);
        $this->get('/admin/plugins')->assertOk()
            ->assertDontSee('href="'.route('admin.shop.settings').'"', false);
    }
}
