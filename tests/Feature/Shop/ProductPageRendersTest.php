<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * A product page renders, whether or not the product can be bought.
 *
 * Nothing in the suite had ever fetched one, which is how this got out: the add-to-cart form
 * is only rendered when the product is in stock, but the page's inline script bound a submit
 * handler to it unconditionally. On an out-of-stock product that threw on the first line, and
 * because it is one script block the throw took everything after it down with it — the
 * description tabs stopped switching and the review form stopped submitting. Exactly the page
 * where a shopper who cannot buy yet goes to read.
 *
 * The assertions are about the shape of the script rather than its behaviour: a browser is
 * the only thing that can prove the tabs still work, but an unguarded bind is a string, and a
 * string is something a test can hold.
 */
class ProductPageRendersTest extends TestCase
{
    use MakesShopFixtures;

    /** The shop needs its pages assigned before a product has an address to serve on. */
    protected function setUp(): void
    {
        parent::setUp();

        // The installer creates these four, so a Testbench database may already have them.
        foreach (['shop' => 'shop_shop_page_id', 'cart' => 'shop_cart_page_id',
            'checkout' => 'shop_checkout_page_id', 'account' => 'shop_account_page_id'] as $slug => $key) {
            $id = DB::table('posts')->where('type', 'page')->where('slug', $slug)->value('id')
                ?: DB::table('posts')->insertGetId([
                    'title' => ucfirst($slug),
                    'slug' => $slug,
                    'type' => 'page',
                    'status' => 'published',
                    'lang_code' => 'en',
                    'content' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => (string) $id]);
        }

        forget_cms_options_cache();
        cache()->flush();
    }

    private function productHtml(array $shop = [], array $post = []): string
    {
        $product = $this->makeProduct($shop, $post);
        $response = $this->get('/product/'.$product->slug);
        $response->assertOk();

        return $response->getContent();
    }

    public function test_an_in_stock_product_page_renders_with_its_form(): void
    {
        $html = $this->productHtml(['stock_status' => 'instock']);

        $this->assertStringContainsString('id="add-to-cart-form"', $html);
    }

    public function test_an_out_of_stock_product_page_renders_without_one(): void
    {
        $html = $this->productHtml([
            'stock_status' => 'outofstock',
            'manage_stock' => 1,
            'stock_quantity' => 0,
        ]);

        $this->assertStringNotContainsString('id="add-to-cart-form"', $html);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function stockStates(): array
    {
        return [
            'in stock' => [['stock_status' => 'instock']],
            'out of stock' => [['stock_status' => 'outofstock', 'manage_stock' => 1, 'stock_quantity' => 0]],
        ];
    }

    /**
     * @param  array<string, mixed>  $shop
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stockStates')]
    public function test_nothing_binds_to_an_element_that_may_not_exist(array $shop): void
    {
        $html = $this->productHtml($shop);

        // The exact shape of the bug: reach for the element and bind in one expression, with
        // nothing in between to notice it is not there.
        foreach (['add-to-cart-form', 'review-form'] as $id) {
            $this->assertStringNotContainsString(
                "getElementById('".$id."').addEventListener",
                $html,
                $id.' is bound without checking whether it was rendered'
            );
        }
    }

    public function test_the_tabs_and_review_script_are_still_on_an_out_of_stock_page(): void
    {
        $html = $this->productHtml([
            'stock_status' => 'outofstock',
            'manage_stock' => 1,
            'stock_quantity' => 0,
        ]);

        // These come after the add-to-cart binding in the same script block, so they are what
        // an unguarded throw used to take out.
        $this->assertStringContainsString('function switchTab', $html);
        $this->assertStringContainsString('review-form', $html);
    }
}
