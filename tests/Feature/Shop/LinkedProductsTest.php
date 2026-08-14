<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Upsells, cross-sells, related products, and the schema.org block on the product page.
 *
 * The linked-product ids are a JSON column, which means they are a list of numbers with
 * nothing keeping them honest: the products they point at can be unpublished or deleted
 * long after the link was made. Everything here is about the reader being defensive.
 */
class LinkedProductsTest extends TestCase
{
    use MakesShopFixtures;

    private Post $main;

    private Post $upsell;

    private Post $crossSell;

    private Post $other;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Test category', 'slug' => 'test-category',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->main = $this->inCategory($this->makeProduct(['price' => 1000, 'sku' => 'MAIN'], ['title' => 'Main']));
        $this->upsell = $this->inCategory($this->makeProduct(['price' => 2000, 'sku' => 'UP'], ['title' => 'Upsell']));
        $this->crossSell = $this->inCategory($this->makeProduct(['price' => 300, 'sku' => 'CROSS'], ['title' => 'Cross sell']));
        $this->other = $this->inCategory($this->makeProduct(['price' => 500, 'sku' => 'OTHER'], ['title' => 'Other']));
    }

    private function inCategory(Post $product): Post
    {
        DB::table('product_category_post')->insert([
            'post_id' => $product->id, 'product_category_id' => $this->categoryId,
        ]);

        return $product;
    }

    /**
     * @param  array<int, mixed>  $upsells
     * @param  array<int, mixed>  $crossSells
     */
    private function link(array $upsells, array $crossSells = []): void
    {
        DB::table('shop_products')->where('post_id', $this->main->id)->update([
            'upsell_ids' => json_encode($upsells),
            'cross_sell_ids' => json_encode($crossSells),
        ]);
    }

    private function linked(string $type): array
    {
        return falcon_linked_products(Post::findOrFail($this->main->id), $type)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ---- reading the links -----------------------------------------------------

    public function test_a_product_with_no_links_returns_nothing(): void
    {
        $this->assertSame([], $this->linked('upsell'));
        $this->assertSame([], $this->linked('cross_sell'));
    }

    public function test_links_come_back_in_the_order_the_shop_owner_chose(): void
    {
        $this->link([$this->upsell->id, $this->other->id], [$this->crossSell->id]);

        $this->assertSame([$this->upsell->id, $this->other->id], $this->linked('upsell'));
        $this->assertSame([$this->crossSell->id], $this->linked('cross_sell'));
    }

    public function test_an_unpublished_link_is_dropped(): void
    {
        $this->link([$this->upsell->id, $this->other->id]);

        DB::table('posts')->where('id', $this->other->id)->update(['status' => 'draft']);

        $this->assertSame([$this->upsell->id], $this->linked('upsell'));
    }

    public function test_a_link_to_a_product_that_no_longer_exists_is_dropped(): void
    {
        $this->link([$this->upsell->id, 999999]);

        $this->assertSame([$this->upsell->id], $this->linked('upsell'));
    }

    public function test_junk_in_the_json_column_yields_nothing_rather_than_an_error(): void
    {
        $this->link(['x', -1, 0, null]);
        $this->assertSame([], $this->linked('upsell'));

        DB::table('shop_products')->where('post_id', $this->main->id)->update(['upsell_ids' => null]);
        $this->assertSame([], $this->linked('upsell'));

        DB::table('shop_products')->where('post_id', $this->main->id)->update(['upsell_ids' => 'not json at all']);
        $this->assertSame([], $this->linked('upsell'));
    }

    // ---- related and cart cross-sells ------------------------------------------

    public function test_related_products_share_a_category_and_exclude_the_product_itself(): void
    {
        $related = falcon_related_products(Post::findOrFail($this->main->id));

        $this->assertGreaterThan(0, $related->count());
        $this->assertFalse($related->contains('id', $this->main->id));
    }

    public function test_cart_cross_sells_skip_what_is_already_in_the_cart(): void
    {
        $this->link([], [$this->crossSell->id, $this->upsell->id]);

        session()->put('falcon_cart', [
            'k1' => ['id' => $this->main->id, 'quantity' => 1, 'price' => 1000, 'sale_price' => null, 'variation_id' => null],
            'k2' => ['id' => $this->upsell->id, 'quantity' => 1, 'price' => 2000, 'sale_price' => null, 'variation_id' => null],
        ]);

        $suggestions = falcon_cart_cross_sells();

        $this->assertFalse($suggestions->contains('id', $this->upsell->id), 'already in the cart');
        $this->assertSame([$this->crossSell->id], $suggestions->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_an_empty_cart_has_no_cross_sells(): void
    {
        session()->forget('falcon_cart');

        $this->assertCount(0, falcon_cart_cross_sells());
    }

    // ---- schema.org ------------------------------------------------------------

    public function test_a_simple_product_produces_a_single_offer(): void
    {
        $schema = falcon_product_schema(Post::findOrFail($this->main->id));

        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('Main', $schema['name']);
        $this->assertArrayHasKey('sku', $schema);
        $this->assertSame('Offer', $schema['offers']['@type']);
        $this->assertSame(
            number_format(falcon_display_price(1000, $this->main->id), 2, '.', ''),
            $schema['offers']['price']
        );
        $this->assertSame(get_shop_option('shop_currency', 'USD'), $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
    }

    public function test_a_variable_product_produces_an_aggregate_offer(): void
    {
        $variable = $this->makeVariableProduct([
            ['price' => 1500], ['price' => 2500],
        ], [], ['title' => 'Variable tee']);

        $schema = falcon_product_schema(Post::findOrFail($variable->id));

        $this->assertSame('AggregateOffer', $schema['offers']['@type']);
        $this->assertSame('1500.00', $schema['offers']['lowPrice']);
        $this->assertSame('2500.00', $schema['offers']['highPrice']);
    }

    public function test_an_out_of_stock_product_says_so_in_the_schema(): void
    {
        $this->setCmsOptions(['shop_manage_stock' => '1', 'shop_out_of_stock_threshold' => '0']);
        $soldOut = $this->makeProduct([
            'price' => 100, 'manage_stock' => 1, 'stock_quantity' => 0, 'backorders' => 'no',
        ], ['title' => 'Sold out']);

        $schema = falcon_product_schema(Post::findOrFail($soldOut->id));

        $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['availability']);
    }

    /** Products get a Product block; anything else must not be dressed up as one. */
    public function test_a_page_gets_no_product_schema(): void
    {
        $page = Post::create([
            'title' => 'About', 'slug' => 'about', 'type' => 'page',
            'status' => 'published', 'lang_code' => 'en', 'content' => '',
        ]);

        $this->assertNull(falcon_product_schema($page));
    }
}
