<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use App\Models\User;
use FalconCms\Core\Core\HookManager;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * The places in the shop a plugin builds on (Falcon Shop Boost does): a line above the cart, a
 * button on product cards, a tab of the shop's settings with switches that save, and the
 * shipping zone lookup. With nothing hooked, the shop is exactly as it was.
 */
class ShopExtensionHooksTest extends TestCase
{
    use MakesShopFixtures;

    /** @var array{actions: array<string, mixed>, filters: array<string, mixed>} */
    private array $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = HookManager::snapshot();
    }

    protected function tearDown(): void
    {
        HookManager::restore($this->hooks);
        parent::tearDown();
    }

    private function fillCart(): void
    {
        $product = $this->makeProduct(['price' => 30]);
        session()->put('falcon_cart', ['k' => [
            'id' => $product->id, 'name' => $product->title, 'quantity' => 2, 'variation_id' => null,
            'price' => 30.0, 'sale_price' => null, 'slug' => $product->slug, 'thumbnail' => null,
        ]]);
    }

    public function test_the_cart_line_is_empty_until_a_plugin_fills_it(): void
    {
        $this->fillCart();
        $this->assertSame('', falcon_cart_shipping_bar('cart'));
        $fragment = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('shop.cart.fragment'))->assertOk();
        $this->assertSame('', $fragment->json('shipping_bar'));

        add_falcon_filter('falcon_cart_shipping_bar', fn ($html, $context) => '<p class="test-line">line for '.$context.'</p>', 10);

        $fragment = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('shop.cart.fragment'))->assertOk();
        $this->assertSame('<p class="test-line">line for mini-cart</p>', $fragment->json('shipping_bar'));
        $this->assertStringContainsString('line for cart', falcon_cart_shipping_bar('cart'));
    }

    public function test_a_product_card_carries_a_plugins_button(): void
    {
        $product = $this->makeProduct([], ['title' => 'Card Lamp']);
        add_falcon_action('falcon_product_card_actions', function ($p) {
            echo '<button class="test-card-action" data-id="'.$p->id.'"></button>';
        });

        $html = view('falcon-shop::frontend.partials.product-card', ['product' => $product->fresh()])->render();

        $this->assertStringContainsString('class="test-card-action" data-id="'.$product->id.'"', $html);
    }

    private function admin(): User
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    public function test_a_plugin_adds_a_settings_tab_and_its_switches_save(): void
    {
        $this->withProLicensed();
        add_falcon_filter('falcon_shop_settings_tabs', fn ($tabs) => $tabs + ['boost' => 'Boost Tab', 'bad id' => 'Nope', 'general' => 'Taken']);
        add_falcon_filter('falcon_shop_settings_toggles', fn ($t) => $t + ['test_switch' => 'shop_test_switch', 'evil' => 'site_title']);
        add_falcon_action('falcon_shop_settings_form_bottom', function () {
            echo '<div x-show="tab === \'boost\'"><input type="checkbox" name="test_switch" value="1"></div>';
        });

        $html = $this->actingAs($this->admin())->get('/admin/shop/settings?tab=boost')->assertOk()->getContent();
        $this->assertStringContainsString('Boost Tab', $html);
        $this->assertStringNotContainsString('Nope', $html, 'a tab id that is not a plain slug is refused');
        $this->assertStringNotContainsString('>Taken<', $html, 'a plugin cannot rename a built-in tab');

        $this->post('/admin/shop/settings', ['test_switch' => '1'])->assertRedirect();
        forget_cms_options_cache();
        $this->assertSame('1', get_cms_option('shop_test_switch'));

        $this->post('/admin/shop/settings', [])->assertRedirect();
        forget_cms_options_cache();
        $this->assertSame('0', get_cms_option('shop_test_switch'), 'an unticked switch saves as off');
        $this->assertNotSame('0', (string) get_cms_option('site_title'), 'only shop_ keys may be switches');
    }

    public function test_the_shipping_zone_lookup_matches_a_state_through_its_country(): void
    {
        $this->setCmsOptions(['shop_shipping_zones' => json_encode([
            ['name' => 'Home', 'countries' => ['Bangladesh'], 'cost' => 5],
            ['name' => 'Texas', 'countries' => ['United States - Texas'], 'cost' => 9],
        ])]);

        $this->assertSame('Home', falcon_shipping_zone_for_country('Bangladesh')['name']);
        $this->assertSame('Home', falcon_shipping_zone_for_country('Bangladesh - Dhaka')['name']);
        $this->assertSame('Texas', falcon_shipping_zone_for_country('United States — Texas')['name']);
        $this->assertNull(falcon_shipping_zone_for_country('India'));
        $this->assertNull(falcon_shipping_zone_for_country(null));
    }

    /** A plugin's Settings link can open one tab of a shared screen. */
    public function test_a_plugin_settings_link_carries_its_params(): void
    {
        $url = new ReflectionMethod(PluginManager::class, 'settingsUrl');
        $pm = app(PluginManager::class);

        $this->assertSame(route('admin.shop.settings', ['tab' => 'experience']),
            $url->invoke($pm, ['settings_route' => 'admin.shop.settings', 'settings_params' => ['tab' => 'experience']]));
        $this->assertSame(route('admin.shop.settings'), $url->invoke($pm, ['settings_route' => 'admin.shop.settings']));
        $this->assertNull($url->invoke($pm, ['settings_route' => 'no.such.route']));
    }
}
