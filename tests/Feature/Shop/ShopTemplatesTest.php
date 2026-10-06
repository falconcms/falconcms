<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Support\SiteHealth;
use FalconCms\Core\Tests\TestCase;
use FalconShop\Templates;
use Illuminate\Support\Facades\File;

/**
 * A theme restyles the shop by holding its own copy of a shop template. `shop:template` makes
 * that copy; the version line in it is how a copy left behind by an update is spotted.
 */
class ShopTemplatesTest extends TestCase
{
    private string $themeDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themeDir = resource_path('views/themes/test-shop-theme');
        File::ensureDirectoryExists($this->themeDir);
        File::put($this->themeDir.'/theme.json', json_encode(['name' => 'Test Shop Theme']));
        $this->setCmsOptions(['active_theme' => 'test-shop-theme']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themeDir);
        parent::tearDown();
    }

    private function shopCheck(): array
    {
        foreach (SiteHealth::checks() as $check) {
            if ($check['id'] === 'shop_templates') {
                return $check;
            }
        }
        $this->fail('Site Health has no shop templates check');
    }

    public function test_every_overridable_template_declares_its_version(): void
    {
        $names = Templates::overridable();

        $this->assertContains('ecommerce.cart', $names);
        $this->assertContains('single-product', $names);
        $this->assertNotContains('partials.product-card', $names);
        $this->assertCount(10, $names);
        foreach ($names as $name) {
            $this->assertNotNull(Templates::versionOf(Templates::defaultFile($name)), "{$name} has no @version line");
        }
    }

    public function test_the_version_line_prints_nothing(): void
    {
        $html = view('falcon-shop::frontend.ecommerce.mini-cart-items', ['cart' => []])->render();

        $this->assertStringNotContainsString('@version', $html);
    }

    public function test_the_command_lists_the_templates(): void
    {
        $this->artisan('shop:template')
            ->expectsOutputToContain('ecommerce/cart')
            ->assertSuccessful();
    }

    public function test_copying_a_template_makes_the_theme_render_its_copy(): void
    {
        $this->assertSame('falcon-shop::frontend.ecommerce.cart', Templates::view('ecommerce.cart'));

        $this->artisan('shop:template', ['template' => 'cart'])->assertSuccessful();

        $copy = $this->themeDir.'/ecommerce/cart.blade.php';
        $this->assertFileEquals(Templates::defaultFile('ecommerce.cart'), $copy);
        $this->assertSame('themes.test-shop-theme.ecommerce.cart', Templates::view('ecommerce.cart'));
        $this->assertSame('good', $this->shopCheck()['status']);

        // a second copy would overwrite the theme's changes: refused without --force
        File::put($copy, 'my cart');
        $this->artisan('shop:template', ['template' => 'ecommerce/cart'])->assertFailed();
        $this->assertSame('my cart', File::get($copy));
        $this->artisan('shop:template', ['template' => 'ecommerce/cart', '--force' => true])->assertSuccessful();
        $this->assertFileEquals(Templates::defaultFile('ecommerce.cart'), $copy);
    }

    public function test_an_unknown_template_or_theme_is_refused(): void
    {
        $this->artisan('shop:template', ['template' => 'nope'])->assertFailed();
        $this->artisan('shop:template', ['template' => 'cart', '--theme' => 'not-a-theme'])->assertFailed();
        $this->artisan('shop:template', ['template' => 'cart', '--theme' => '../etc'])->assertFailed();
    }

    public function test_an_old_or_unversioned_copy_is_reported_as_outdated(): void
    {
        File::ensureDirectoryExists($this->themeDir.'/ecommerce');
        File::put($this->themeDir.'/ecommerce/checkout.blade.php', 'copied before versions existed');
        File::put($this->themeDir.'/single-product.blade.php', "{{-- @version 0.9.0 --}}\nold copy");

        $outdated = array_column(array_filter(Templates::status(), fn ($r) => $r['outdated']), 'name');
        $this->assertEqualsCanonicalizing(['ecommerce.checkout', 'single-product'], $outdated);

        $check = $this->shopCheck();
        $this->assertSame('recommended', $check['status']);
        $this->assertCount(2, $check['details']['files']);

        $this->artisan('shop:template')->expectsOutputToContain('OUTDATED')->assertSuccessful();
    }
}
