<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * What price the shopper is shown.
 *
 * Two bugs are pinned here. A sale with a past end date kept being displayed, because
 * only the admin form knew about sale_ends_at. And a variable product showed ৳0.00,
 * because the parent row carries no price of its own — the prices live on the variations.
 */
class PricingTest extends TestCase
{
    use MakesShopFixtures;

    private function setShop(Post $product, array $columns): void
    {
        DB::table('shop_products')->where('post_id', $product->id)->update($columns);
    }

    private function onSaleFilterIncludes(Post $product): bool
    {
        return in_array($product->id, $this->filteredProductIds(['on_sale' => '1']), true);
    }

    // ---- sale expiry -----------------------------------------------------------

    public function test_a_sale_with_no_end_date_runs_forever(): void
    {
        $product = $this->makeProduct(['price' => 1000, 'sale_price' => 700, 'sale_ends_at' => null]);

        $this->assertTrue($this->shopRow($product)->isOnSale());
        $this->assertSame(700.0, (float) $this->shopRow($product)->active_sale_price);
        $this->assertTrue($this->onSaleFilterIncludes($product));
    }

    public function test_a_sale_ending_in_the_future_is_still_running(): void
    {
        $product = $this->makeProduct([
            'price' => 1000, 'sale_price' => 700, 'sale_ends_at' => now()->addDay(),
        ]);

        $this->assertTrue($this->shopRow($product)->isOnSale());
        $this->assertTrue($this->onSaleFilterIncludes($product));
    }

    public function test_an_expired_sale_disappears_from_every_surface_at_once(): void
    {
        $product = $this->makeProduct([
            'price' => 1000, 'sale_price' => 700, 'sale_ends_at' => now()->subMinute(),
        ]);

        $this->assertFalse($this->shopRow($product)->isOnSale());
        $this->assertNull($this->shopRow($product)->active_sale_price);
        $this->assertNull(Post::findOrFail($product->id)->sale_price);
        $this->assertFalse($this->onSaleFilterIncludes($product));
    }

    /**
     * The reason active_sale_price exists at all: expiry is applied on the way out, so
     * the stored value survives. Zeroing the column would wipe the shop owner's number
     * the next time they opened the product and pressed Update.
     */
    public function test_an_expired_sale_keeps_its_value_for_the_admin_form(): void
    {
        $product = $this->makeProduct([
            'price' => 1000, 'sale_price' => 700, 'sale_ends_at' => now()->subMinute(),
        ]);

        $this->assertSame(700.0, (float) $this->shopRow($product)->sale_price);
        $this->assertSame(700.0, (float) DB::table('shop_products')
            ->where('post_id', $product->id)->value('sale_price'));
    }

    public function test_a_sale_price_of_zero_is_not_a_sale(): void
    {
        $product = $this->makeProduct(['price' => 1000, 'sale_price' => 0, 'sale_ends_at' => null]);

        $this->assertFalse($this->shopRow($product)->isOnSale());
        $this->assertFalse($this->onSaleFilterIncludes($product));
    }

    // ---- variable products are variable in both columns ------------------------

    /**
     * The admin form writes `type`; older reads used `product_type`. Whichever one says
     * "variable" wins, otherwise a product saved by the admin looks simple to the
     * frontend — which is exactly how a variable product ended up rendering ৳0.00.
     */
    public function test_either_type_column_saying_variable_is_enough(): void
    {
        $product = $this->makeProduct();

        $this->setShop($product, ['type' => 'variable', 'product_type' => 'simple']);
        $this->assertTrue($this->shopRow($product)->isVariable(), 'type=variable');

        $this->setShop($product, ['type' => 'simple', 'product_type' => 'variable']);
        $this->assertTrue($this->shopRow($product)->isVariable(), 'product_type=variable');

        $this->setShop($product, ['type' => 'variable', 'product_type' => 'variable']);
        $this->assertTrue($this->shopRow($product)->isVariable(), 'both');

        $this->setShop($product, ['type' => 'simple', 'product_type' => 'simple']);
        $this->assertFalse($this->shopRow($product)->isVariable(), 'neither');
    }

    // ---- price range -----------------------------------------------------------

    public function test_a_simple_product_has_no_price_range(): void
    {
        $product = $this->makeProduct(['price' => 900]);

        $this->assertNull($this->shopRow($product)->price_range);
    }

    public function test_several_prices_produce_a_low_to_high_range(): void
    {
        $product = $this->makeVariableProduct([
            ['price' => 1500], ['price' => 2000], ['price' => 2500],
        ]);

        $this->assertSame(
            falcon_price_format(1500).' – '.falcon_price_format(2500),
            $this->shopRow($product)->price_range
        );
    }

    public function test_identical_prices_collapse_to_a_single_price(): void
    {
        $product = $this->makeVariableProduct([['price' => 1800], ['price' => 1800]]);

        $this->assertSame(falcon_price_format(1800), $this->shopRow($product)->price_range);
    }

    public function test_a_variation_on_sale_pulls_the_bottom_of_the_range_down(): void
    {
        $product = $this->makeVariableProduct([
            ['price' => 3000, 'sale_price' => 1200],
            ['price' => 4000],
        ]);

        $this->assertSame(
            falcon_price_format(1200).' – '.falcon_price_format(4000),
            $this->shopRow($product)->price_range
        );
    }

    public function test_no_usable_variation_price_falls_back_to_null(): void
    {
        $noVariations = $this->makeVariableProduct([]);
        $this->assertNull($this->shopRow($noVariations)->price_range,
            'nothing to build a range from');

        $freeOnly = $this->makeVariableProduct([['price' => 0]]);
        $this->assertNull($this->shopRow($freeOnly)->price_range,
            'a range of zero is not worth showing — the template falls back');
    }

    public function test_a_simple_product_ignores_stray_variations(): void
    {
        $product = $this->makeProduct();
        $this->makeVariation($product, ['price' => 1500]);

        $this->assertNull($this->shopRow($product)->price_range);
    }
}
