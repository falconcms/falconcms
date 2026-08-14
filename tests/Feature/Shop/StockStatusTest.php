<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * "Is this in stock?" has to give the same answer everywhere it is asked.
 *
 * There are three callers — the badge on the card ($post->is_in_stock), the archive's
 * "In stock only" filter, and the add-to-cart check — and they used to disagree: a
 * variable product whose every variation was sold out still advertised itself as in
 * stock, because the parent row's own stock_status said so. The rule now lives in one
 * place (ProductData::isInStock) and every case below asserts the badge and the filter
 * agree, not just that each is individually right.
 */
class StockStatusTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setCmsOptions([
            'shop_manage_stock' => '1',
            'shop_out_of_stock_threshold' => '0',
        ]);
    }

    /** Assert the badge and the archive filter agree, and that both say $expected. */
    private function assertStockIs(bool $expected, Post $product, string $because): void
    {
        $badge = (bool) Post::findOrFail($product->id)->is_in_stock;
        $inFilter = in_array($product->id, $this->filteredProductIds(['in_stock' => '1']), true);

        $this->assertSame($expected, $badge, "badge disagrees: {$because}");
        $this->assertSame($expected, $inFilter, "archive filter disagrees: {$because}");
    }

    private function setShop(Post $product, array $columns): void
    {
        DB::table('shop_products')->where('post_id', $product->id)->update($columns);
    }

    private function clearVariations(Post $product): void
    {
        DB::table('shop_product_variations')
            ->where('product_id', $product->shopData->id)
            ->delete();
    }

    // ---- simple products -------------------------------------------------------

    public function test_a_simple_product_that_does_not_track_stock_is_always_available(): void
    {
        $product = $this->makeProduct(['manage_stock' => 0, 'stock_status' => 'instock']);

        $this->assertStockIs(true, $product, 'stock is not tracked at all');
    }

    public function test_a_simple_product_follows_its_quantity(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 5]);
        $this->assertStockIs(true, $product, 'five on the shelf');

        $this->setShop($product, ['stock_quantity' => 0]);
        $this->assertStockIs(false, $product, 'none on the shelf');
    }

    public function test_backorders_keep_an_empty_product_orderable(): void
    {
        $product = $this->makeProduct([
            'manage_stock' => 1, 'stock_quantity' => 0, 'backorders' => 'yes',
        ]);

        $this->assertStockIs(true, $product, 'empty but backorders are allowed');
    }

    public function test_an_explicit_outofstock_status_beats_a_healthy_quantity(): void
    {
        $product = $this->makeProduct([
            'manage_stock' => 1, 'stock_quantity' => 5, 'stock_status' => 'outofstock',
        ]);

        $this->assertStockIs(false, $product, 'the shop owner marked it out of stock by hand');
    }

    // ---- variable products -----------------------------------------------------

    public function test_a_variable_product_with_no_variations_falls_back_to_the_parent(): void
    {
        $product = $this->makeVariableProduct([], ['stock_status' => 'instock', 'manage_stock' => 0]);

        $this->assertStockIs(true, $product, 'nothing to look at but the parent row');
    }

    public function test_one_variation_in_stock_is_enough(): void
    {
        $product = $this->makeVariableProduct([
            ['manage_stock' => 1, 'stock_quantity' => 3, 'attributes_data' => json_encode(['size' => 'S'])],
            ['manage_stock' => 1, 'stock_quantity' => 0, 'attributes_data' => json_encode(['size' => 'M'])],
        ]);

        $this->assertStockIs(true, $product, 'one size is still on the shelf');
    }

    /** The bug this whole test class exists for. */
    public function test_a_variable_product_whose_every_variation_is_empty_is_out_of_stock(): void
    {
        $product = $this->makeVariableProduct([
            ['manage_stock' => 1, 'stock_quantity' => 0, 'attributes_data' => json_encode(['size' => 'S'])],
            ['manage_stock' => 1, 'stock_quantity' => 0, 'attributes_data' => json_encode(['size' => 'M'])],
        ], ['stock_status' => 'instock', 'manage_stock' => 0]);

        $this->assertStockIs(false, $product, 'every variation is sold out');
    }

    public function test_backorders_rescue_a_variable_product_with_no_stock_anywhere(): void
    {
        $product = $this->makeVariableProduct([
            ['manage_stock' => 1, 'stock_quantity' => 0],
        ], ['stock_status' => 'instock', 'backorders' => 'notify']);

        $this->assertStockIs(true, $product, 'sold out but backorders are on');
    }

    public function test_a_variation_marked_outofstock_does_not_count_even_with_quantity(): void
    {
        $product = $this->makeVariableProduct([
            ['manage_stock' => 1, 'stock_quantity' => 0, 'attributes_data' => json_encode(['size' => 'S'])],
            ['manage_stock' => 1, 'stock_quantity' => 4, 'stock_status' => 'outofstock', 'attributes_data' => json_encode(['size' => 'M'])],
        ], ['stock_status' => 'instock']);

        $this->assertStockIs(false, $product, 'the only stocked variation is switched off');
    }

    public function test_a_variation_that_does_not_track_stock_borrows_the_parent_quantity(): void
    {
        $product = $this->makeVariableProduct([
            ['manage_stock' => 0],
        ], ['manage_stock' => 1, 'stock_quantity' => 7]);

        $this->assertStockIs(true, $product, 'the variation defers to the parent, which has seven');

        $this->setShop($product, ['stock_quantity' => 0]);
        $this->assertStockIs(false, $product, 'the parent it defers to is empty too');
    }

    // ---- shop-wide settings ----------------------------------------------------

    public function test_turning_stock_management_off_makes_everything_available(): void
    {
        $product = $this->makeVariableProduct([
            ['manage_stock' => 1, 'stock_quantity' => 0],
        ]);

        $this->assertStockIs(false, $product, 'sold out while stock management is on');

        $this->setCmsOptions(['shop_manage_stock' => '0']);

        $this->assertStockIs(true, $product, 'the shop does not track stock at all');
    }

    public function test_the_out_of_stock_threshold_is_inclusive(): void
    {
        $this->setCmsOptions(['shop_out_of_stock_threshold' => '2']);

        $product = $this->makeVariableProduct([
            ['manage_stock' => 1, 'stock_quantity' => 2],
        ]);
        $this->assertStockIs(false, $product, 'two left with a threshold of two counts as gone');

        $this->clearVariations($product);
        $this->makeVariation($product, ['manage_stock' => 1, 'stock_quantity' => 3]);
        $this->assertStockIs(true, $product, 'three left clears a threshold of two');
    }
}
