<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

    private const SHOP = __DIR__.'/../../../plugins/falcon-shop/resources/views/frontend';

    /** Every hook tag a template calls, in the order it calls them. */
    private function tagsIn(string $relative): array
    {
        // Shop templates live in the shop plugin; everything else in the theme.
        $path = is_file(self::THEME.'/'.$relative) ? self::THEME.'/'.$relative : self::SHOP.'/'.$relative;
        $src = file_get_contents($path);
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

    // ── a variable product ───────────────────────────────────────────────────

    public function test_every_hook_on_a_variable_product_fires(): void
    {
        $this->assignShopPages();
        $declared = $this->tagsIn('single-product-variable.blade.php');
        $this->watch($declared);

        $product = $this->makeVariableProduct([
            ['price' => 500, 'attributes_data' => json_encode(['size' => 'M'])],
            ['price' => 700, 'attributes_data' => json_encode(['size' => 'L'])],
        ], ['short_description' => 'Pick a size.']);
        $this->setProductAttributes($product, [['name' => 'Size', 'values' => 'M | L', 'visible' => '1', 'variation' => '1']]);

        $this->get('/product/'.$product->slug)->assertOk();

        $this->assertFired($declared, 'the variable product template');
    }

    /** Simple-product hooks with no variable twin, by design. */
    private const NOT_ON_VARIABLE = [
        // The price is drawn in the browser for whichever variation is picked; there is no
        // server-rendered price markup to filter.
        'falcon_variable_product_price',
        // Stock is per variation: an unavailable one disables the button instead of
        // swapping in a different one.
        'falcon_variable_out_of_stock_button',
    ];

    /**
     * The test above only knows the tags the variable template declares, so a hook dropped
     * from it would pass unnoticed — which is how four of them sat unfired in a template
     * nothing rendered. This pins what it must offer: a twin of every simple-product hook,
     * and a variable-only twin of every hook the two product pages share.
     */
    public function test_the_variable_product_offers_the_same_hooks_as_a_simple_one(): void
    {
        $simple = $this->tagsIn('single-product.blade.php');
        $variable = $this->tagsIn('single-product-variable.blade.php');

        $twins = array_map(fn ($t) => str_replace('falcon_simple_', 'falcon_variable_', $t), $simple);
        $missing = array_values(array_diff($twins, $variable, self::NOT_ON_VARIABLE));
        $this->assertSame([], $missing, 'the variable product lacks: '.implode(', ', $missing));

        $shared = array_filter($simple, fn ($t) => preg_match('/^falcon_(before|after)_(single_product|product_images|product_description)$/', $t));
        $this->assertNotEmpty($shared);
        foreach ($shared as $tag) {
            $twin = str_replace('falcon_', 'falcon_variable_', $tag);
            $this->assertContains($twin, $variable, "the variable product has {$tag} but not {$twin}");
        }
    }

    // ── the mini-cart ────────────────────────────────────────────────────────

    /** Reached only when the mini-cart has nothing in it. */
    private const EMPTY_MINI_CART_ONLY = ['falcon_mini_cart_empty'];

    private function miniCart(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('shop.cart.fragment'))
            ->assertOk();
    }

    public function test_every_hook_on_a_full_mini_cart_fires(): void
    {
        $this->assignShopPages();
        $declared = $this->tagsIn('ecommerce/mini-cart-items.blade.php');
        $this->watch($declared);
        $this->fillCart($this->makeProduct(['price' => 1200]));

        $this->miniCart();

        $this->assertFired(array_diff($declared, self::EMPTY_MINI_CART_ONLY), 'a full mini-cart');
    }

    public function test_an_empty_mini_cart_fires_its_empty_hook(): void
    {
        $this->assignShopPages();
        $this->watch($this->tagsIn('ecommerce/mini-cart-items.blade.php'));

        $this->miniCart();

        $this->assertFired(self::EMPTY_MINI_CART_ONLY, 'an empty mini-cart');
    }

    // ── checkout, the order, the confirmation ────────────────────────────────

    private function fillCart(Post $product): void
    {
        session()->put('falcon_cart', ['k' => [
            'id' => $product->id,
            'name' => $product->title,
            'quantity' => 2,
            'variation_id' => null,
            'price' => (float) DB::table('shop_products')->where('post_id', $product->id)->value('price'),
            'sale_price' => null,
            'slug' => $product->slug,
            'thumbnail' => null,
        ]]);
    }

    /** A shop that can take a cash-on-delivery order to Bangladesh. */
    private function openForOrders(): void
    {
        $this->withProLicensed();
        Mail::fake();
        $this->setCmsOptions([
            'shop_calc_taxes' => '0',
            'shop_currency' => 'USD',
            'shop_country_state' => 'Bangladesh',
            'shop_payment_cod_enable' => '1',
            'shop_shipping_zones' => json_encode([[
                'name' => 'Flat rate', 'countries' => ['Bangladesh'], 'cost' => 100,
                'free_threshold' => 0, 'type' => 'order', 'rules' => [],
            ]]),
        ]);
        $this->assignShopPages();
        session()->put('falcon_shipping_country', 'Bangladesh');
    }

    private function placeOrder(): Order
    {
        $this->post(route('shop.place-order'), [
            'billing_first_name' => 'Alice', 'billing_last_name' => 'Ahmed',
            'billing_email' => 'alice@example.test', 'billing_phone' => '01700000000',
            'billing_address_1' => '12 Road 5', 'billing_city' => 'Dhaka', 'billing_state' => 'Dhaka',
            'billing_postcode' => '1207', 'billing_country' => 'Bangladesh', 'payment_method' => 'cod',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    public function test_every_hook_on_the_checkout_page_fires(): void
    {
        $this->openForOrders();
        $declared = $this->tagsIn('ecommerce/checkout.blade.php');
        $this->watch($declared);
        $this->fillCart($this->makeProduct(['price' => 1000]));

        $this->get('/checkout')->assertOk();

        $this->assertFired($declared, 'the checkout template');
    }

    public function test_adding_to_the_cart_and_placing_an_order_fire_their_hooks(): void
    {
        $this->openForOrders();
        $tags = ['falcon_cart_item_custom_fields', 'falcon_cart_item_data',
            'falcon_checkout_custom_fields', 'falcon_order_item_meta', 'falcon_before_place_order'];
        $this->watch($tags);

        $product = $this->makeProduct(['price' => 1000, 'manage_stock' => 1, 'stock_quantity' => 10]);
        $this->post(route('shop.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->assertNotEmpty(session('falcon_cart'), 'adding to the cart left it empty');
        $this->placeOrder();

        $this->assertFired($tags, 'adding to the cart and placing the order');
    }

    /**
     * The confirmation lists custom checkout fields — and asks for their labels — only when
     * the order has some. A plugin adds one through falcon_checkout_custom_fields, as here.
     */
    public function test_every_hook_on_the_order_confirmation_fires(): void
    {
        $this->openForOrders();
        $declared = $this->tagsIn('ecommerce/confirmation.blade.php');
        $this->watch($declared);
        add_falcon_filter('falcon_checkout_custom_fields', fn ($fields) => $fields + ['gift_note' => 'Leave it at the door']);
        $this->fillCart($this->makeProduct(['price' => 1000, 'manage_stock' => 1, 'stock_quantity' => 10]));

        $order = $this->placeOrder();
        $this->get(route('shop.confirmation', $order->id))
            ->assertOk()
            ->assertSee('Leave it at the door');

        $this->assertFired($declared, 'the order confirmation template');
    }

    // ── the back office ──────────────────────────────────────────────────────

    /** Every hook tag a package view outside the theme calls. */
    private function tagsInView(string $relative): array
    {
        // The shop's back-office views live in the shop plugin; the rest in the package.
        $core = __DIR__.'/../../../resources/views/'.$relative;
        $src = file_get_contents(is_file($core) ? $core : __DIR__.'/../../../plugins/falcon-shop/resources/views/'.$relative);
        $this->assertNotFalse($src, $relative.' is missing');
        preg_match_all("/(?:do_falcon_action|apply_falcon_filters)\(\s*'([a-z0-9_]+)'/", $src, $m);

        return array_values(array_unique($m[1]));
    }

    private function actingAsAdmin(): void
    {
        $roleId = (int) DB::table('roles')->where('slug', 'administrator')->value('id');
        $this->actingAs($this->makeUser(['role_id' => $roleId]));
    }

    public function test_saving_and_deleting_a_product_fire_the_admin_hooks_with_what_they_promise(): void
    {
        $this->actingAsAdmin();
        $seen = [];
        add_falcon_filter('falcon_admin_before_save_product', function ($data, $post, $request) use (&$seen) {
            $seen['before_save'][] = $post;

            return $data;
        });
        add_falcon_action('falcon_admin_after_save_product', function ($post, $shopData, $request, $mode) use (&$seen) {
            $seen['after_save'][] = $mode;
        });
        add_falcon_action('falcon_admin_before_delete_product', function ($post) use (&$seen) {
            $seen['before_delete'] = $post->id;
        });
        add_falcon_action('falcon_admin_after_delete_product', function ($id) use (&$seen) {
            $seen['after_delete'] = $id;
        });

        $form = ['type' => 'product', 'status' => 'published', 'product_type' => 'simple'];
        $this->post(route('admin.posts.store'), $form + ['title' => 'Hooked phone', 'price' => '299'])->assertRedirect();
        $post = Post::where('title', 'Hooked phone')->firstOrFail();
        $this->put(route('admin.posts.update', $post), $form + ['title' => 'Hooked phone', 'price' => '199'])->assertRedirect();
        $this->delete(route('admin.posts.destroy', $post))->assertRedirect();

        // Before saving: no post yet on create, the post on update.
        $this->assertCount(2, $seen['before_save'] ?? []);
        $this->assertNull($seen['before_save'][0]);
        $this->assertSame($post->id, $seen['before_save'][1]->id);
        $this->assertSame(['create', 'update'], $seen['after_save'] ?? null);
        $this->assertSame($post->id, $seen['before_delete'] ?? null);
        $this->assertSame($post->id, $seen['after_delete'] ?? null);
    }

    public function test_every_hook_on_the_admin_order_screens_and_shop_settings_fires(): void
    {
        $this->openForOrders();
        $screens = [
            'admin/shop/orders/show.blade.php' => 'show',
            'admin/shop/orders/invoice.blade.php' => 'invoice',
        ];
        $declared = array_merge(...array_map(fn ($v) => $this->tagsInView($v), array_keys($screens)));
        $declared = array_merge($declared, $this->tagsInView('admin/shop/settings.blade.php'));
        $this->watch($declared);
        $this->fillCart($this->makeProduct(['price' => 1000, 'manage_stock' => 1, 'stock_quantity' => 10]));
        $order = $this->placeOrder();

        $this->actingAsAdmin();
        $this->get(route('admin.shop.orders.show', $order->id))->assertOk();
        $this->get(route('admin.shop.orders.invoice', $order->id))->assertOk();
        $this->get(route('admin.shop.settings'))->assertOk();

        $this->assertFired(array_unique($declared), 'the admin order screens and shop settings');
    }

    // ── a child theme's shop templates ───────────────────────────────────────

    /**
     * A child theme holds only what it overrides. Its cart, checkout and confirmation come
     * from its parent — whichever theme that is, not always falcon-theme.
     */
    public function test_a_child_theme_gets_its_parents_shop_templates(): void
    {
        $themes = resource_path('views/themes');
        $parent = $themes.'/zz-hook-parent';
        $child = $themes.'/zz-hook-child';
        try {
            @mkdir($parent.'/ecommerce', 0777, true);
            @mkdir($child, 0777, true);
            file_put_contents($parent.'/theme.json', json_encode(['name' => 'ZZ Parent']));
            file_put_contents($parent.'/ecommerce/cart.blade.php', '<p>the parent theme cart</p>');
            file_put_contents($child.'/theme.json', json_encode(['name' => 'ZZ Child', 'parent' => 'zz-hook-parent']));

            $this->assignShopPages();
            $this->setCmsOptions(['active_theme' => 'zz-hook-child']);

            $this->get(route('shop.cart'))->assertOk()->assertSee('the parent theme cart');
        } finally {
            foreach ([$parent.'/ecommerce/cart.blade.php', $parent.'/theme.json', $child.'/theme.json'] as $f) {
                @unlink($f);
            }
            foreach ([$parent.'/ecommerce', $parent, $child] as $d) {
                @rmdir($d);
            }
        }
    }
}
