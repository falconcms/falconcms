<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The cart stores a copy of each price at add-to-cart time, so a cart left open across a
 * price change used to charge the old number — including a sale that had since ended.
 * falcon_refresh_cart_prices() reconciles the session against the database, and
 * placeOrder() runs it before totalling anything.
 */
class CartPriceRefreshTest extends TestCase
{
    use MakesShopFixtures;

    private Post $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = $this->makeProduct(['price' => 1000, 'sale_price' => null]);
    }

    private function putCart(float $price, ?float $sale = null, ?int $variationId = null): void
    {
        session()->put('falcon_cart', ['k' => [
            'id' => $this->product->id,
            'name' => 'Test product',
            'quantity' => 1,
            'variation_id' => $variationId,
            'price' => $price,
            'sale_price' => $sale,
            'slug' => $this->product->slug,
            'thumbnail' => null,
        ]]);
    }

    /** @return array<string, mixed> */
    private function line(): array
    {
        return session()->get('falcon_cart')['k'];
    }

    private function setShop(array $columns): void
    {
        DB::table('shop_products')->where('post_id', $this->product->id)->update($columns);
    }

    public function test_a_price_change_reaches_a_cart_that_was_already_open(): void
    {
        $this->putCart(1000);
        $this->setShop(['price' => 1500]);

        $this->assertSame(1, falcon_refresh_cart_prices(), 'one line was corrected');
        $this->assertSame(1500.0, (float) $this->line()['price']);
        $this->assertSame(1500.0, round(get_falcon_cart_subtotal(), 2));
    }

    public function test_refreshing_an_already_current_cart_writes_nothing(): void
    {
        $this->putCart(1000);

        $this->assertSame(0, falcon_refresh_cart_prices());
    }

    public function test_a_sale_started_after_add_to_cart_is_picked_up(): void
    {
        $this->putCart(1000);
        $this->setShop(['sale_price' => 800, 'sale_ends_at' => null]);

        falcon_refresh_cart_prices();

        $this->assertSame(800.0, (float) $this->line()['sale_price']);
        $this->assertSame(800.0, round(get_falcon_cart_subtotal(), 2));
    }

    public function test_a_sale_that_ended_is_dropped_without_waiting_for_the_cron(): void
    {
        $this->putCart(1000, 800);
        $this->setShop(['sale_price' => 800, 'sale_ends_at' => now()->subDay()]);

        falcon_refresh_cart_prices();

        $this->assertNull($this->line()['sale_price']);
        $this->assertSame(1000.0, round(get_falcon_cart_subtotal(), 2));
    }

    public function test_a_sale_still_running_survives_the_refresh(): void
    {
        $this->putCart(1000);
        $this->setShop(['sale_price' => 800, 'sale_ends_at' => now()->addDay()]);

        falcon_refresh_cart_prices();

        $this->assertSame(800.0, (float) $this->line()['sale_price']);
    }

    /** A zero in the column means "no sale", not "give it away". */
    public function test_a_zero_sale_price_does_not_make_the_item_free(): void
    {
        $this->putCart(1000, 800);
        $this->setShop(['sale_price' => 0, 'sale_ends_at' => null]);

        falcon_refresh_cart_prices();

        $this->assertNull($this->line()['sale_price']);
        $this->assertSame(1000.0, round(get_falcon_cart_subtotal(), 2));
    }

    public function test_a_variation_line_follows_the_variation_price(): void
    {
        $variation = $this->makeVariation($this->product, ['price' => 500]);

        $this->putCart(500, null, $variation->id);
        DB::table('shop_product_variations')->where('id', $variation->id)->update(['price' => 777]);

        falcon_refresh_cart_prices();

        $this->assertSame(777.0, (float) $this->line()['price']);
    }

    public function test_a_product_that_left_the_catalogue_leaves_its_line_alone(): void
    {
        session()->put('falcon_cart', ['k' => [
            'id' => 999999, 'name' => 'Gone', 'quantity' => 1, 'variation_id' => null,
            'price' => 4242, 'sale_price' => null, 'slug' => 'gone', 'thumbnail' => null,
        ]]);

        $this->assertSame(0, falcon_refresh_cart_prices());
        $this->assertSame(4242.0, (float) session()->get('falcon_cart')['k']['price']);
    }

    /**
     * The session is attacker-adjacent input as far as this helper is concerned — it must
     * survive anything that ends up in there rather than fatal on the cart page.
     */
    public function test_a_missing_or_malformed_cart_does_not_crash(): void
    {
        session()->forget('falcon_cart');
        $this->assertSame(0, falcon_refresh_cart_prices(), 'no cart at all');

        session()->put('falcon_cart', 'not-an-array');
        $this->assertSame(0, falcon_refresh_cart_prices(), 'cart is a string');

        session()->put('falcon_cart', ['k' => ['quantity' => 1]]);
        $this->assertSame(0, falcon_refresh_cart_prices(), 'line has no product id');
    }

    /**
     * Guards the wiring, not the helper: the reconcile is worthless if checkout skips it.
     */
    public function test_place_order_refreshes_prices_before_totalling(): void
    {
        $source = file_get_contents(__DIR__.'/../../../src/Http/Controllers/ShopFrontendController.php');
        $start = strpos($source, 'public function placeOrder');

        $this->assertNotFalse($start, 'placeOrder() has been renamed — update this test');
        $this->assertStringContainsString(
            'falcon_refresh_cart_prices()',
            substr($source, $start, 400),
            'placeOrder() must reconcile cart prices against the database before it totals anything'
        );
    }
}
