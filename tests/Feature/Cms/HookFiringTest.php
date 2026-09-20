<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The hooks a theme advertises actually fire.
 *
 * Extensibility is a promise: somebody writes a callback against falcon_simple_after_add_to_cart_button
 * and ships it, and a template refactor here can quietly stop calling it. Nothing would fail —
 * their code simply never runs again, on a site they cannot debug from.
 *
 * So the expected tags are read out of the templates at run time rather than typed here: add a
 * hook to a template and this file already knows about it, remove one and it fails. The tags a
 * page only reaches in a particular state (out of stock, an empty cart) are listed as such, with
 * the state that reaches them.
 */
class HookFiringTest extends TestCase
{
    use MakesShopFixtures;

    /** @var array<string, true> */
    private array $fired = [];

    private const THEME = __DIR__.'/../../../resources/views/themes/falcon-theme';

    /** Every hook tag a template calls, in the order it calls them. */
    private function tagsIn(string $relative): array
    {
        $src = file_get_contents(self::THEME.'/'.$relative);
        $this->assertNotFalse($src, $relative.' is missing');

        preg_match_all("/(?:do_falcon_action|apply_falcon_filters)\(\s*'([a-z0-9_]+)'/", $src, $m);

        return array_values(array_unique($m[1]));
    }

    /** Watch every tag: as an action and as a filter, because a tag is only ever one of them. */
    private function watch(array $tags): void
    {
        foreach ($tags as $tag) {
            add_falcon_action($tag, function () use ($tag) {
                $this->fired[$tag] = true;
            }, 1);
            add_falcon_filter($tag, function ($value) use ($tag) {
                $this->fired[$tag] = true;

                return $value;
            }, 1);
        }
    }

    private function assertFired(array $expected, string $where): void
    {
        $missing = array_values(array_diff($expected, array_keys($this->fired)));

        $this->assertSame([], $missing, $where.' did not fire: '.implode(', ', $missing));
    }

    private function assignShopPages(): void
    {
        foreach (['shop' => 'shop_shop_page_id', 'cart' => 'shop_cart_page_id',
            'checkout' => 'shop_checkout_page_id', 'account' => 'shop_account_page_id'] as $slug => $key) {
            $id = DB::table('posts')->where('type', 'page')->where('slug', $slug)->value('id')
                ?: DB::table('posts')->insertGetId([
                    'title' => ucfirst($slug), 'slug' => $slug, 'type' => 'page', 'status' => 'published',
                    'lang_code' => 'en', 'content' => '', 'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => (string) $id]);
        }

        forget_cms_options_cache();
    }

    // ── the layout, on every page there is ───────────────────────────────────

    public function test_the_layout_hooks_fire_on_a_front_end_page(): void
    {
        $this->watch($this->tagsIn('layouts/app.blade.php'));

        $this->get('/')->assertOk();

        $this->assertFired(['falcon_head', 'falcon_footer'], 'the layout');
    }

    // ── a post ───────────────────────────────────────────────────────────────

    public function test_every_hook_on_the_post_template_fires(): void
    {
        $this->watch($this->tagsIn('single.blade.php'));

        Post::create([
            'user_id' => 1, 'title' => 'Hooked', 'slug' => 'hooked-post', 'type' => 'post',
            'status' => 'published', 'lang_code' => 'en', 'content' => '<p>Body.</p>',
        ]);
        $this->get('/post/hooked-post')->assertOk();

        $this->assertFired($this->tagsIn('single.blade.php'), 'the post template');
    }

    // ── a product ────────────────────────────────────────────────────────────

    /**
     * Reached only when the product cannot be bought — the add-to-cart form is not rendered,
     * so neither is anything inside it.
     */
    private const OUT_OF_STOCK_ONLY = ['falcon_simple_out_of_stock_button'];

    /** Reached only when the product can be bought. */
    private const IN_STOCK_ONLY = [
        'falcon_simple_add_to_cart_form_top',
        'falcon_product_fields',
        'falcon_simple_product_fields',
        'falcon_simple_before_add_to_cart_button',
        'falcon_simple_add_to_cart_button',
        'falcon_simple_after_add_to_cart_button',
        'falcon_simple_add_to_cart_form_bottom',
    ];

    /** Reached only when the product has a short description to filter. */
    private const NEEDS_SHORT_DESCRIPTION = ['falcon_simple_short_description'];

    public function test_every_hook_on_an_in_stock_product_fires(): void
    {
        $this->assignShopPages();
        $this->watch($this->tagsIn('single-product.blade.php'));

        $product = $this->makeProduct([
            'stock_status' => 'instock',
            'short_description' => 'Short and to the point.',
        ]);
        $this->get('/product/'.$product->slug)->assertOk();

        $expected = array_diff($this->tagsIn('single-product.blade.php'), self::OUT_OF_STOCK_ONLY);
        $this->assertFired($expected, 'an in-stock product page');
    }

    public function test_every_hook_on_an_out_of_stock_product_fires(): void
    {
        $this->assignShopPages();
        $this->watch($this->tagsIn('single-product.blade.php'));

        $product = $this->makeProduct([
            'stock_status' => 'outofstock',
            'manage_stock' => 1,
            'stock_quantity' => 0,
            'short_description' => 'Short and to the point.',
        ]);
        $this->get('/product/'.$product->slug)->assertOk();

        $expected = array_diff($this->tagsIn('single-product.blade.php'), self::IN_STOCK_ONLY);
        $this->assertFired($expected, 'an out-of-stock product page');
    }

    /**
     * Between the two stock states, every hook the product template declares is reached. If a
     * new one is added that neither state reaches, this says so rather than letting it rot.
     */
    public function test_the_two_stock_states_between_them_reach_every_product_hook(): void
    {
        $this->assignShopPages();
        $declared = $this->tagsIn('single-product.blade.php');
        $this->watch($declared);

        foreach ([
            ['stock_status' => 'instock', 'short_description' => 'Short.'],
            ['stock_status' => 'outofstock', 'manage_stock' => 1, 'stock_quantity' => 0, 'short_description' => 'Short.'],
        ] as $shop) {
            $this->get('/product/'.$this->makeProduct($shop)->slug)->assertOk();
        }

        $this->assertFired($declared, 'the product template, across both stock states,');
    }

    public function test_a_product_hook_is_handed_the_product(): void
    {
        $this->assignShopPages();

        $seen = null;
        add_falcon_action('falcon_simple_after_product_title', function ($post) use (&$seen) {
            $seen = $post;
        });

        $product = $this->makeProduct([], ['title' => 'Handed Over']);
        $this->get('/product/'.$product->slug)->assertOk();

        $this->assertNotNull($seen, 'the hook fired without an argument');
        $this->assertSame($product->id, $seen->id);
    }

    public function test_a_product_filter_changes_what_the_page_shows(): void
    {
        $this->assignShopPages();

        add_falcon_filter('falcon_simple_add_to_cart_button', fn ($html) => '<button>Reserve it</button>');

        $product = $this->makeProduct(['stock_status' => 'instock']);
        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Reserve it', false);
    }

    // ── the cart ─────────────────────────────────────────────────────────────

    /** Reached only once there is a line in the cart to loop over. */
    private const NEEDS_A_FULL_CART = [
        'falcon_before_cart_items', 'falcon_before_cart_item', 'falcon_cart_item_name',
        'falcon_cart_item_meta', 'falcon_after_cart_item',
    ];

    public function test_every_hook_on_the_cart_fires_once_there_is_something_in_it(): void
    {
        $this->assignShopPages();
        $declared = $this->tagsIn('ecommerce/cart.blade.php');
        $this->watch($declared);

        $product = $this->makeProduct(['price' => 1200]);
        session()->put('falcon_cart', ['k' => [
            'id' => $product->id,
            'name' => $product->title,
            'quantity' => 2,
            'variation_id' => null,
            'price' => 1200.0,
            'sale_price' => null,
            'slug' => $product->slug,
            'thumbnail' => null,
        ]]);

        $this->get('/cart')->assertOk();

        $this->assertFired($declared, 'the cart template');
    }

    public function test_the_cart_renders_empty_without_firing_the_item_hooks(): void
    {
        $this->assignShopPages();
        $this->watch($this->tagsIn('ecommerce/cart.blade.php'));

        $this->get('/cart')->assertOk();

        // Not a bug: the loop hooks live inside the "there is something here" branch.
        foreach (self::NEEDS_A_FULL_CART as $tag) {
            $this->assertArrayNotHasKey($tag, $this->fired, $tag.' fired on an empty cart');
        }
    }
}
