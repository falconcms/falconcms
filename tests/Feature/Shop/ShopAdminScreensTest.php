<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Facades\DB;

/**
 * The shop's back office, now served by the shop plugin: every screen opens, carries its
 * scripts from the plugin's assets/admin, and the sidebar links to it.
 */
class ShopAdminScreensTest extends TestCase
{
    use MakesShopFixtures;

    private function actingAsAdmin(): void
    {
        $this->withProLicensed();
        $this->actingAs($this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]));
    }

    /** @return array<string, array{0: string, 1: list<string>}> screen => [url, admin assets it loads] */
    private function screens(): array
    {
        return [
            'overview' => ['/admin/shop/overview', []],
            'orders' => ['/admin/shop/orders', ['js/orders.js']],
            'promotions' => ['/admin/shop/promotions', ['js/promotions.js']],
            'new promotion' => ['/admin/shop/promotions/create', ['js/promotion-form.js']],
            'reviews' => ['/admin/shop/reviews', ['js/reviews.js']],
            // reports: MySQL date functions, not available in the SQLite test database
            'settings' => ['/admin/shop/settings', ['js/settings.js', 'css/settings.css']],
            'product categories' => ['/admin/product-categories', ['js/product-categories.js']],
            'product tags' => ['/admin/product-tags', ['js/product-tags.js']],
        ];
    }

    public function test_every_shop_screen_opens_with_its_assets(): void
    {
        $this->actingAsAdmin();

        foreach ($this->screens() as $screen => [$url, $assets]) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($assets as $asset) {
                $this->assertStringContainsString('/plugin-assets/falcon-shop/admin/'.$asset.'?v=', $html, "{$screen} does not load {$asset}");
                $this->get('/plugin-assets/falcon-shop/admin/'.$asset)->assertOk();
            }
        }
    }

    public function test_an_order_and_its_invoice_open(): void
    {
        $this->actingAsAdmin();
        $orderId = DB::table('shop_orders')->insertGetId([
            'order_number' => 'ORD-ADMIN-1', 'first_name' => 'Alice', 'last_name' => 'Ahmed', 'customer_email' => 'alice@example.test',
            'customer_phone' => '01700000000', 'address_line_1' => '12 Road 5', 'city' => 'Dhaka', 'postcode' => '1205',
            'country' => 'Bangladesh',
            'subtotal' => 100, 'total' => 100, 'status' => 'pending', 'payment_method' => 'cod',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // a line whose product was deleted since: product_id is null (nullOnDelete)
        DB::table('shop_order_items')->insert([
            'order_id' => $orderId, 'product_id' => null, 'product_name' => 'Retired Lamp', 'quantity' => 1,
            'price' => 100, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get("/admin/shop/orders/{$orderId}")->assertOk()->assertSee('ORD-ADMIN-1')->assertSee('Retired Lamp');
        $this->get("/admin/shop/orders/{$orderId}/invoice")->assertOk()
            ->assertSee('/plugin-assets/falcon-shop/admin/css/invoice.css?v=', false);
    }

    public function test_the_sidebar_links_to_the_shop_and_products(): void
    {
        $this->seed(MenuSeeder::class);

        $titles = (new Sidebar)->menuGroups->flatten(1)
            ->flatMap(fn ($menu) => [$menu->title, ...$menu->children->pluck('title')->map(fn ($t) => $menu->title.' > '.$t)])
            ->all();

        foreach (['Shop', 'Shop > Orders', 'Shop > Reports', 'Products', 'Products > All Products', 'Products > Categories'] as $title) {
            $this->assertContains($title, $titles);
        }
    }

    public function test_the_plugins_screen_links_to_the_shop_settings(): void
    {
        $this->actingAsAdmin();

        $this->assertSame(route('admin.shop.settings'), app(PluginManager::class)->all()['falcon-shop']['settings_url']);
        $this->get('/admin/plugins')->assertOk()
            ->assertSee('href="'.route('admin.shop.settings').'"', false)
            ->assertDontSee('Part of FalconCMS');
    }
}
