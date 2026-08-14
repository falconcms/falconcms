<?php

namespace FalconCms\Core\Tests\Concerns;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\ProductData;
use FalconCms\Core\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

/**
 * Fixture builders for the shop tests.
 *
 * Products are written through the tables rather than through the admin controller on
 * purpose: these tests are about what the models and helpers do with a given row, so the
 * row is the input. Anything that goes through the controller belongs in a feature test
 * that posts to the route.
 */
trait MakesShopFixtures
{
    /**
     * A published product plus its shop row.
     *
     * @param  array<string, mixed>  $shop  columns for shop_products
     * @param  array<string, mixed>  $post  columns for posts
     */
    protected function makeProduct(array $shop = [], array $post = []): Post
    {
        static $n = 0;
        $n++;

        $postId = DB::table('posts')->insertGetId(array_merge([
            'title' => "Test product {$n}",
            'slug' => "test-product-{$n}",
            'type' => 'product',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ], $post));

        DB::table('shop_products')->insert(array_merge([
            'post_id' => $postId,
            // Both spellings, because the admin writes `type` and older readers use
            // `product_type`. A fixture that sets only one hides exactly the bug that
            // made a variable product show up as "simple" in the admin.
            'type' => 'simple',
            'product_type' => 'simple',
            'price' => 1000,
            'sale_price' => null,
            'manage_stock' => 0,
            'stock_status' => 'instock',
            'created_at' => now(),
            'updated_at' => now(),
        ], $shop));

        return Post::with('shopData')->findOrFail($postId);
    }

    /**
     * A variable product: `type` and `product_type` both set to "variable".
     *
     * @param  array<int, array<string, mixed>>  $variations
     * @param  array<string, mixed>  $shop
     */
    protected function makeVariableProduct(array $variations, array $shop = [], array $post = []): Post
    {
        $product = $this->makeProduct(array_merge([
            'type' => 'variable',
            'product_type' => 'variable',
        ], $shop), $post);

        foreach ($variations as $variation) {
            $this->makeVariation($product, $variation);
        }

        return $product->fresh(['shopData']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeVariation(Post $product, array $attributes = []): ProductVariation
    {
        $shopId = $product->shopData->id;

        $id = DB::table('shop_product_variations')->insertGetId(array_merge([
            'product_id' => $shopId,
            'attributes_data' => json_encode(['size' => 'M']),
            'price' => 1000,
            'sale_price' => null,
            'manage_stock' => 0,
            'stock_status' => 'instock',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return ProductVariation::findOrFail($id);
    }

    /**
     * Store an attribute definition on the product the way the admin form does.
     *
     * @param  array<int, array<string, mixed>>  $attributes
     */
    protected function setProductAttributes(Post $product, array $attributes): Post
    {
        DB::table('shop_products')
            ->where('post_id', $product->id)
            ->update(['attributes_data' => json_encode($attributes)]);

        return $product->fresh(['shopData']);
    }

    protected function shopRow(Post $product): ProductData
    {
        return ProductData::where('post_id', $product->id)->firstOrFail();
    }

    /**
     * The ids the product archive would show for a given query string.
     *
     * Filtering reads the current request, so the request is swapped rather than the
     * arguments being passed in — this is the same code path a real /product?… hit takes.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, int>
     */
    protected function filteredProductIds(array $query): array
    {
        $request = Request::create('/product', 'GET', $query);
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        $builder = Post::query()
            ->where('posts.type', 'product')
            ->where('posts.status', 'published')
            ->where('posts.lang_code', 'en');

        falcon_apply_product_filters($builder);

        return $builder->pluck('posts.id')->map(fn ($id) => (int) $id)->all();
    }

    protected function makeUser(array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::forceCreate(array_merge([
            'name' => "Customer {$n}",
            'email' => "customer{$n}@example.test",
            'password' => 'secret-password',
        ], $attributes));
    }
}
