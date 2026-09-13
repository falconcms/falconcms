<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Saving a variable product from the editor.
 *
 * A variable product has no price of its own — each variation is priced — but
 * shop_products.price is NOT NULL, so the insert died and the editor answered with a 500
 * after the post row had already been written: a product with no shop data, priced at zero
 * on the storefront. The parent now takes the cheapest variation's price, which is also the
 * figure the archive sorts and filters on.
 */
class VariableProductSaveTest extends TestCase
{
    use MakesShopFixtures;

    private function actingAsAdmin(): void
    {
        $roleId = (int) DB::table('roles')->where('slug', 'administrator')->value('id');
        $this->actingAs($this->makeUser(['role_id' => $roleId]));
    }

    /** @param  array<int, array<string, mixed>>  $variations */
    private function publishVariable(array $variations, string $title = 'Variable phone')
    {
        return $this->post(route('admin.posts.store'), [
            'title' => $title,
            'type' => 'product',
            'status' => 'published',
            'product_type' => 'variable',
            'attributes_data' => [
                ['name' => 'Storage', 'values' => '128GB | 256GB | 512GB', 'visible' => '1', 'variation' => '1', 'filterable' => '1'],
            ],
            'variations' => $variations,
        ]);
    }

    public function test_a_variable_product_saves_and_takes_the_cheapest_variation_price(): void
    {
        $this->actingAsAdmin();

        $response = $this->publishVariable([
            ['attributes' => ['Storage' => '256GB'], 'price' => '999', 'stock_quantity' => '8'],
            ['attributes' => ['Storage' => '128GB'], 'price' => '899', 'stock_quantity' => '12'],
            ['attributes' => ['Storage' => '512GB'], 'price' => '1199', 'stock_quantity' => '4'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $post = Post::where('type', 'product')->where('title', 'Variable phone')->firstOrFail();
        $shopData = $post->shopData;

        $this->assertNotNull($shopData, 'the product was saved without its shop data');
        $this->assertSame('variable', $shopData->type);
        $this->assertEquals(899.0, (float) $shopData->price);
        $this->assertNull($shopData->sale_price);
        $this->assertCount(3, $shopData->variations);
    }

    public function test_the_parent_sale_price_only_follows_a_discount_on_the_cheapest_variation(): void
    {
        $this->actingAsAdmin();

        // The discount belongs to the dearest variation, so it is not a discount on the
        // "from" price and must not be advertised as one.
        $this->publishVariable([
            ['attributes' => ['Storage' => '128GB'], 'price' => '899'],
            ['attributes' => ['Storage' => '512GB'], 'price' => '1199', 'sale_price' => '1099'],
        ], 'Dearer variation on sale');

        $shopData = Post::where('title', 'Dearer variation on sale')->firstOrFail()->shopData;
        $this->assertEquals(899.0, (float) $shopData->price);
        $this->assertNull($shopData->sale_price);

        // A real discount on the cheapest one does come through.
        $this->publishVariable([
            ['attributes' => ['Storage' => '128GB'], 'price' => '899', 'sale_price' => '799'],
            ['attributes' => ['Storage' => '512GB'], 'price' => '1199'],
        ], 'Cheapest variation on sale');

        $shopData = Post::where('title', 'Cheapest variation on sale')->firstOrFail()->shopData;
        $this->assertEquals(899.0, (float) $shopData->price);
        $this->assertEquals(799.0, (float) $shopData->sale_price);
    }

    public function test_a_variable_product_with_no_variations_still_saves(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.posts.store'), [
            'title' => 'Empty variable',
            'type' => 'product',
            'status' => 'published',
            'product_type' => 'variable',
        ]);

        $response->assertRedirect();
        $shopData = Post::where('title', 'Empty variable')->firstOrFail()->shopData;
        $this->assertNotNull($shopData);
        $this->assertEquals(0.0, (float) $shopData->price);
    }

    public function test_a_simple_product_keeps_the_price_that_was_typed(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.posts.store'), [
            'title' => 'Simple phone',
            'type' => 'product',
            'status' => 'published',
            'product_type' => 'simple',
            'price' => '299',
            'sale_price' => '269',
        ])->assertRedirect();

        $shopData = Post::where('title', 'Simple phone')->firstOrFail()->shopData;
        $this->assertEquals(299.0, (float) $shopData->price);
        $this->assertEquals(269.0, (float) $shopData->sale_price);
    }
}
