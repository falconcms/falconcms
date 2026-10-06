<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\FalconCmsServiceProvider;
use FalconCms\Core\Models\Plugin;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\TestCase;
use FalconShop\ShopServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Plugins that ship inside the CMS (the shop): found without being installed, on by default,
 * switched off only by an explicit choice, never uninstalled — and their assets served only
 * while they are on.
 */
class BundledPluginTest extends TestCase
{
    private function manager(): PluginManager
    {
        return new PluginManager;
    }

    public function test_the_shop_is_discovered_as_a_bundled_plugin(): void
    {
        $shop = $this->manager()->manifest('falcon-shop');

        $this->assertNotNull($shop, 'the bundled shop plugin was not found');
        $this->assertTrue($shop['bundled']);
        $this->assertTrue($shop['default_active']);
    }

    public function test_a_bundled_default_active_plugin_is_on_with_no_record_at_all(): void
    {
        $this->assertNull(Plugin::where('slug', 'falcon-shop')->first());

        $this->assertContains('falcon-shop', $this->manager()->activeSlugs());
        $this->assertTrue($this->manager()->all()['falcon-shop']['active']);
        $this->assertTrue(falcon_plugin_active('falcon-shop'), 'the shop was not loaded on boot');
    }

    public function test_switching_it_off_sticks_and_switching_it_on_brings_it_back(): void
    {
        $this->assertTrue($this->manager()->deactivate('falcon-shop')['ok']);
        $this->assertFalse((bool) Plugin::where('slug', 'falcon-shop')->value('is_active'));
        $this->assertNotContains('falcon-shop', $this->manager()->activeSlugs());

        $this->assertTrue($this->manager()->activate('falcon-shop')['ok']);
        $this->assertContains('falcon-shop', $this->manager()->activeSlugs());
    }

    /**
     * A long-running worker (or a test run) boots the application more than once in one
     * process. Registering the shop again must not fail — it once did, on re-aliasing a trait,
     * and the plugin was then switched off as broken.
     */
    public function test_the_shop_can_be_registered_again_in_the_same_process(): void
    {
        (new ShopServiceProvider($this->app))->register();
        (new ShopServiceProvider($this->app))->register();

        $this->assertTrue(trait_exists('FalconCms\\Core\\Http\\Controllers\\Concerns\\SyncsOrderInventory'));
        $this->assertTrue(falcon_plugin_active('falcon-shop'));
    }

    public function test_a_bundled_plugin_cannot_be_uninstalled(): void
    {
        $result = $this->manager()->uninstall('falcon-shop');

        $this->assertFalse($result['ok']);
        $this->assertFileExists(PluginManager::bundledPath().'/falcon-shop/plugin.json');
    }

    public function test_only_a_bundled_plugin_may_be_on_by_default(): void
    {
        $dir = resource_path('views/plugins/zz-default-on');
        try {
            @mkdir($dir, 0777, true);
            file_put_contents($dir.'/plugin.json', json_encode(['name' => 'ZZ', 'slug' => 'zz-default-on', 'default_active' => true]));

            $manager = new PluginManager(resource_path('views/plugins'));
            $this->assertFalse($manager->manifest('zz-default-on')['default_active']);
            $this->assertNotContains('zz-default-on', $manager->activeSlugs());
        } finally {
            @unlink($dir.'/plugin.json');
            @rmdir($dir);
        }
    }

    /**
     * routes/shop-dormant.php must name exactly the routes the plugin registers, with the
     * same methods and URIs — a plugin route without a dormant twin is a route('...') call
     * that would fail on every page of a site with the shop switched off.
     */
    public function test_every_shop_route_has_a_dormant_twin(): void
    {
        $real = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            // every route the plugin serves, storefront and back office alike
            if (str_starts_with($route->getActionName(), 'FalconShop\\')) {
                $methods = $route->methods();
                sort($methods);
                $real[$route->getName()] = [$methods, $route->uri()];
            }
        }
        $this->assertNotEmpty($real, 'the shop plugin registered no routes');

        preg_match_all("/'([a-z][a-z.\-]+)' => \[\[([^\]]+)\], '([^']+)'\]/",
            (string) file_get_contents(__DIR__.'/../../../routes/shop-dormant.php'), $m, PREG_SET_ORDER);
        $dormant = [];
        foreach ($m as [, $name, $methods, $uri]) {
            $list = array_map(fn ($x) => trim($x, " '"), explode(',', $methods));
            sort($list);
            $dormant[$name] = [$list, $uri];
        }

        ksort($real);
        ksort($dormant);
        $this->assertSame($real, $dormant);
    }

    // ── assets ───────────────────────────────────────────────────────────────

    private function withAsset(string $relative, string $contents, callable $test): void
    {
        $file = PluginManager::bundledPath().'/falcon-shop/assets/'.$relative;
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, $contents);
        try {
            $test();
        } finally {
            @unlink($file);
        }
    }

    public function test_an_active_plugins_asset_is_served_without_a_session(): void
    {
        $this->withAsset('frontend/css/zz-probe.css', 'body{--zz:1}', function () {
            $url = falcon_plugin_asset('falcon-shop', 'frontend/css/zz-probe.css');
            $this->assertStringContainsString('/plugin-assets/falcon-shop/frontend/css/zz-probe.css?v=', $url);

            $response = $this->get('/plugin-assets/falcon-shop/frontend/css/zz-probe.css');
            $response->assertOk();
            $this->assertStringStartsWith('text/css', $response->headers->get('Content-Type'));
            $this->assertSame('body{--zz:1}', $response->streamedContent());
            $this->assertEmpty($response->headers->getCookies(), 'an asset request started a session');
        });
    }

    public function test_nothing_is_served_for_a_plugin_that_is_not_loaded(): void
    {
        $this->withAsset('frontend/css/zz-probe.css', 'body{--zz:1}', function () {
            // A fresh manager has loaded nothing: exactly the state of a deactivated plugin.
            $this->app->instance(PluginManager::class, new PluginManager);

            $this->get('/plugin-assets/falcon-shop/frontend/css/zz-probe.css')->assertNotFound();
            $this->assertSame('', falcon_plugin_asset('falcon-shop', 'frontend/css/zz-probe.css'));
        });
    }

    public function test_only_static_files_inside_assets_are_served(): void
    {
        $this->get('/plugin-assets/falcon-shop/../plugin.php')->assertNotFound();
        $this->get('/plugin-assets/falcon-shop/frontend/css/..%2F..%2F..%2Fplugin.json')->assertNotFound();
        $this->get('/plugin-assets/falcon-shop/frontend/css/missing.css')->assertNotFound();

        $this->withAsset('frontend/js/zz-probe.php', '<?php echo 1;', function () {
            $this->get('/plugin-assets/falcon-shop/frontend/js/zz-probe.php')->assertNotFound();
        });
    }

    /**
     * A route cache built with the shop in the other state is dropped. Switching the shop from
     * the admin clears the cache itself; the env switch and an automatic deactivation do not.
     */
    public function test_a_route_cache_built_in_the_other_shop_state_is_removed(): void
    {
        $cache = $this->app->getCachedRoutesPath();
        $this->assertFileDoesNotExist($cache, 'the test app should not have a real route cache');
        file_put_contents($cache, '<?php // stand-in');
        $this->app->instance('routes.cached', true); // Testbench never reports a cache

        try {
            $check = new \ReflectionMethod(FalconCmsServiceProvider::class, 'dropRouteCacheIfShopStateChanged');
            $provider = fn () => new FalconCmsServiceProvider($this->app);

            // The shop is on and shop.cart is its real route: the cache matches and stays.
            $check->invoke($provider());
            $this->assertFileExists($cache);

            // shop.cart a dormant twin while the shop is on: built with the shop off.
            Route::getRoutes()->getByName('shop.cart')->defaults('_falcon_dormant', true);
            $check->invoke($provider());
            $this->assertFileDoesNotExist($cache);
        } finally {
            @unlink($cache);
        }
    }

    /**
     * Servers that answer .css/.js URLs from disk only (no fallback to the CMS) would 404
     * every plugin asset; a copy in public/plugin-assets is found at the same URL.
     */
    public function test_an_active_plugins_static_files_are_published_and_nothing_else(): void
    {
        $public = public_path('plugin-assets/falcon-shop');
        try {
            $this->withAsset('frontend/js/zz-probe.php', '<?php echo 1;', function () use ($public) {
                $this->withAsset('frontend/css/zz-probe.css', 'body{--zz:1}', function () use ($public) {
                    File::ensureDirectoryExists($public.'/frontend/css');
                    File::put($public.'/frontend/css/zz-stale.css', 'old');

                    $this->assertTrue(app(PluginManager::class)->publishAssets('falcon-shop'));

                    $this->assertSame('body{--zz:1}', File::get($public.'/frontend/css/zz-probe.css'));
                    $this->assertFileEquals(PluginManager::bundledPath().'/falcon-shop/assets/frontend/js/mini-cart.js', $public.'/frontend/js/mini-cart.js');
                    $this->assertFileDoesNotExist($public.'/frontend/js/zz-probe.php', 'PHP must never be copied into public/');
                    $this->assertFileDoesNotExist($public.'/frontend/css/zz-stale.css', 'a file the plugin no longer ships must go');
                });
            });
        } finally {
            File::deleteDirectory(public_path('plugin-assets'));
        }
    }

    public function test_switching_the_shop_off_removes_its_published_files_and_on_brings_them_back(): void
    {
        $plugins = app(PluginManager::class);
        $public = public_path('plugin-assets/falcon-shop/frontend/js/mini-cart.js');
        try {
            $this->assertSame(['falcon-shop'], $plugins->syncPublishedAssets());
            $this->assertFileExists($public);

            $plugins->deactivate('falcon-shop');
            $this->assertFileDoesNotExist($public);
            $this->assertSame([], $plugins->syncPublishedAssets(), 'an update must not publish a switched-off plugin');

            $plugins->activate('falcon-shop');
            $this->assertFileExists($public);
        } finally {
            File::deleteDirectory(public_path('plugin-assets'));
        }
    }
}
