<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The hooks a plugin uses to hide prices (falcon_show_prices / falcon_hidden_price_html), to
 * price-filter and price-sort listings by its own prices, and to narrow every product listing
 * (related products included).
 */
class ShopPriceVisibilityHooksTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withProLicensed();
        foreach (['shop' => 'shop_shop_page_id', 'cart' => 'shop_cart_page_id',
            'checkout' => 'shop_checkout_page_id', 'account' => 'shop_account_page_id'] as $slug => $key) {
            $id = DB::table('posts')->where('type', 'page')->where('slug', $slug)->value('id')
                ?: DB::table('posts')->insertGetId([
                    'title' => ucfirst($slug), 'slug' => $slug, 'type' => 'page', 'status' => 'published',
                    'lang_code' => 'en', 'content' => '', 'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => (string) $id]);
        }
        $this->setCmsOptions(['shop_currency' => 'USD', 'shop_currency_pos' => 'left']);
        cache()->flush();
    }

    private function hidePrices(): void
    {
        add_falcon_filter('falcon_show_prices', fn ($show, $product) => false);
        add_falcon_filter('falcon_hidden_price_html', fn ($html, $product, $context) => $context === 'cart'
            ? '<a class="login-to-buy" href="/account">Log in to buy</a>'
            : '<span class="login-to-see">Log in to see prices</span>');
    }

    public function test_prices_show_by_default(): void
    {
        $this->assertTrue(falcon_can_see_prices());
        $this->assertSame('', falcon_hidden_price_html());
    }

    public function test_a_hidden_price_leaves_the_product_page(): void
    {
        $product = $this->makeProduct(['price' => 1234]);
        $this->hidePrices();

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('1,234.00', $html);
        $this->assertStringContainsString('Log in to see prices', $html);
        $this->assertStringContainsString('Log in to buy', $html);
        $this->assertStringNotContainsString('id="add-to-cart-form"', $html);
        $this->assertStringNotContainsString('"price":"1234', $html, 'not in the structured data either');
    }

    public function test_a_hidden_price_leaves_the_variable_product_page_source(): void
    {
        $product = $this->makeVariableProduct([['price' => 4321]]);
        $this->hidePrices();

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('4321', $html);
        $this->assertStringNotContainsString('4,321', $html);
        $this->assertStringContainsString('Log in to buy', $html);
    }

    public function test_a_hidden_price_leaves_the_shop_cards(): void
    {
        $product = $this->makeProduct(['price' => 2468]);
        $this->hidePrices();

        $html = $this->get('/product')->assertOk()->getContent();

        $this->assertStringContainsString($product->title, $html);
        $this->assertStringNotContainsString('2,468.00', $html);
        $this->assertStringContainsString('Log in to see prices', $html);
        $this->assertStringNotContainsString('addToCart('.$product->id.')', $html);
    }

    public function test_a_hidden_price_cannot_be_bought(): void
    {
        $product = $this->makeProduct();
        $this->hidePrices();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('shop.cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(403)
            ->assertJson(['message' => 'Log in to buy']);

        $this->assertEmpty(session('falcon_cart', []));
    }

    public function test_the_price_filter_and_sort_can_be_taken_over(): void
    {
        $cheap = $this->makeProduct(['price' => 100]);
        $dear = $this->makeProduct(['price' => 900]);

        // A plugin whose prices run the other way round.
        add_falcon_filter('falcon_product_price_filter', function ($handled, $query, $min, $max) use ($dear) {
            $query->where('posts.id', $dear->id);

            return true;
        });
        $this->assertSame([$dear->id], $this->filteredProductIds(['min_price' => 50, 'max_price' => 150]));

        $seen = null;
        add_falcon_filter('falcon_product_price_sort', function ($handled, $query, string $direction) use (&$seen, $dear) {
            $seen = $direction;
            $query->orderByRaw('CASE WHEN posts.id = ? THEN 0 ELSE 1 END', [$dear->id]);

            return true;
        });
        $query = Post::query()->where('posts.type', 'product')->whereIn('posts.id', [$cheap->id, $dear->id]);
        falcon_apply_product_sorting($query, 'price');
        $this->assertSame([$dear->id, $cheap->id], $query->pluck('posts.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('ASC', $seen);
    }

    public function test_the_filter_price_band_can_be_replaced(): void
    {
        $product = $this->makeProduct(['price' => 500]);
        add_falcon_filter('falcon_product_filter_options', fn (array $options, array $ids) => ['min_price' => 1.0, 'max_price' => count($ids) * 10.0]);

        $options = falcon_product_filter_options(fn () => Post::query()->where('posts.type', 'product')->whereKey($product->id));

        $this->assertSame(1.0, $options['min_price']);
        $this->assertSame(10.0, $options['max_price']);
        $this->assertArrayHasKey('categories', $options);
    }

    public function test_related_and_linked_products_go_through_the_product_query(): void
    {
        $product = $this->makeProduct();
        $hidden = $this->makeProduct();
        $shown = $this->makeProduct();
        DB::table('shop_products')->where('post_id', $product->id)->update(['upsell_ids' => json_encode([$hidden->id, $shown->id])]);

        add_falcon_action('falcon_product_query', fn ($query) => $query->where('posts.id', '!=', $hidden->id));

        $related = falcon_related_products($product->fresh(['shopData', 'productCategories']), 8)->pluck('id')->all();
        $this->assertNotContains($hidden->id, $related);

        $upsells = falcon_linked_products($product->fresh(['shopData']), 'upsell', 8)->pluck('id')->all();
        $this->assertSame([$shown->id], $upsells);
    }

    public function test_saving_variations_alone_tells_plugins_with_mode_variations(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        $product = $this->makeVariableProduct([['price' => 100]]);
        $modes = [];
        add_falcon_action('falcon_admin_after_save_product', function ($post, $shopData, $request, $mode) use (&$modes) {
            $modes[] = $mode;
        });

        $this->postJson(route('admin.posts.variations.ajax-save', $product->id), [
            'attributes_data' => [['name' => 'size', 'values' => 'M', 'variation' => true]],
            'variations' => [['attributes_data' => ['size' => 'M'], 'price' => 120]],
        ])->assertOk();

        $this->assertSame(['variations'], $modes);
    }
}
