<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use App\Models\User;
use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Review;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconShop\Mail\OrderNotificationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * The shop's logic hooks — pricing, cart, coupons, shipping, tax, checkout, payment, the order
 * lifecycle, stock, email, account — each fire where they are documented, with the arguments
 * they are documented with, and what a callback returns really changes what the shop does.
 *
 * These are what a wholesale or multi-vendor plugin is built on. A hook that quietly stops
 * firing breaks somebody's plugin without a single error, so every one of them is pinned here
 * through the real routes rather than by calling the helpers in isolation.
 */
class ShopHooksTest extends TestCase
{
    use MakesShopFixtures;

    private Post $product;

    /** @var array<string, array<int, array<int, mixed>>> tag => list of argument lists */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withProLicensed();
        Mail::fake();

        $this->setCmsOptions([
            'shop_enable_coupons' => '1',
            'shop_coupon_stacking_policy' => '1',
            'shop_calc_taxes' => '0',
            'shop_currency' => 'USD',
            'shop_currency_pos' => 'left',
            'shop_country_state' => 'Bangladesh',
            'shop_payment_cod_enable' => '1',
            'shop_shipping_zones' => json_encode([[
                'name' => 'Flat rate', 'countries' => ['Bangladesh'], 'cost' => 100,
                'free_threshold' => 0, 'type' => 'order', 'rules' => [],
            ]]),
        ]);

        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->product = $this->makeProduct(['price' => 1000, 'manage_stock' => 1, 'stock_quantity' => 10]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** Record every call to an action. */
    private function record(string $tag): void
    {
        add_falcon_action($tag, function (...$args) use ($tag) {
            $this->calls[$tag][] = $args;
        });
    }

    /** @return array<int, array<int, mixed>> */
    private function callsTo(string $tag): array
    {
        return $this->calls[$tag] ?? [];
    }

    private function fillCart(int $quantity, ?Post $product = null): void
    {
        $product ??= $this->product;

        session()->put('falcon_cart', ['k' => [
            'id' => $product->id,
            'name' => $product->title,
            'quantity' => $quantity,
            'variation_id' => null,
            'price' => (float) $product->shopData->price,
            'sale_price' => null,
            'slug' => $product->slug,
            'thumbnail' => null,
        ]]);
    }

    /** @return array<string, mixed> */
    private function checkoutInput(array $overrides = []): array
    {
        return array_merge([
            'billing_first_name' => 'Alice',
            'billing_last_name' => 'Ahmed',
            'billing_email' => 'alice@example.test',
            'billing_phone' => '01700000000',
            'billing_address_1' => '12 Road 5',
            'billing_city' => 'Dhaka',
            'billing_state' => 'Dhaka',
            'billing_postcode' => '1207',
            'billing_country' => 'Bangladesh',
            'payment_method' => 'cod',
        ], $overrides);
    }

    private function placeOrder(array $overrides = []): TestResponse
    {
        return $this->post(route('shop.place-order'), $this->checkoutInput($overrides));
    }

    private function ajax(): static
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-HOOK-'.uniqid(),
            'status' => 'pending',
            'subtotal' => 100, 'total' => 100,
            'customer_email' => 'bob@example.test', 'customer_phone' => '01700000001',
            'first_name' => 'Bob', 'last_name' => 'Bari',
            'address_line_1' => '1 Lane', 'city' => 'Dhaka', 'postcode' => '1000', 'country' => 'Bangladesh',
            'payment_method' => 'cod',
        ], $attributes));
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
        cache()->flush();
    }

    private function stripeWebhook(array $event, string $secret = 'whsec_hooks'): TestResponse
    {
        $payload = json_encode($event);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', route('shop.payment.stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    // ── pricing: falcon_product_prices ──────────────────────────────────────

    public function test_product_prices_filter_reaches_the_storefront_accessors_and_the_range(): void
    {
        $seen = [];
        add_falcon_filter('falcon_product_prices', function (array $prices, int $postId, ?int $variationId, int $qty, string $context) use (&$seen) {
            $seen[] = $context;

            return ['price' => $prices['price'] * 0.5, 'sale_price' => null];
        });

        $shop = $this->shopRow($this->product);
        $this->assertSame(500.0, $shop->current_price);
        $this->assertNull($shop->current_sale_price);
        $this->assertSame(1000.0, (float) $shop->price, 'the catalogue figure the admin edits is untouched');

        $variable = $this->makeVariableProduct([['price' => 200], ['price' => 400]]);
        $this->assertSame(falcon_price_format(100).' – '.falcon_price_format(200), $variable->shopData->price_range);

        $this->assertContains('display', $seen);
    }

    public function test_product_prices_filter_output_is_normalised(): void
    {
        add_falcon_filter('falcon_product_prices', fn () => ['price' => -50, 'sale_price' => 0]);

        $this->assertSame(['price' => 0.0, 'sale_price' => null], falcon_product_prices($this->product));

        add_falcon_filter('falcon_product_prices', fn () => 'not an array', 20);
        // A callback returning garbage further down the chain leaves the catalogue pair.
        $this->assertSame(['price' => 1000.0, 'sale_price' => null], falcon_filter_product_prices(1000, null, $this->product->id));
    }

    public function test_the_product_page_shows_the_filtered_price(): void
    {
        $this->assignShopPages();
        add_falcon_filter('falcon_product_prices', fn (array $p) => ['price' => 777, 'sale_price' => null]);

        $html = $this->get('/product/'.$this->product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('777.00', $html);
    }

    public function test_a_tiered_price_follows_the_cart_quantity_into_the_order(): void
    {
        add_falcon_filter('falcon_product_prices', function (array $prices, int $postId, ?int $variationId, int $qty, string $context) {
            return $context === 'cart' && $qty >= 5 ? ['price' => 800, 'sale_price' => null] : $prices;
        });

        $this->ajax()->post(route('shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])->assertOk();
        $key = array_key_first(session('falcon_cart'));
        $this->assertSame(1000.0, (float) session('falcon_cart')[$key]['price']);

        $this->ajax()->post(route('shop.cart.update'), ['quantity' => [$key => 5]])->assertOk();
        $this->assertSame(800.0, (float) session('falcon_cart')[$key]['price']);
        $this->assertSame(4000.0, (float) get_falcon_cart_subtotal());

        $this->placeOrder();

        $item = DB::table('shop_order_items')->latest('id')->first();
        $this->assertSame(800.0, round((float) $item->price, 2));
        $this->assertSame(4000.0, round((float) $item->subtotal, 2));
    }

    public function test_product_is_in_stock_filter(): void
    {
        $this->assertTrue($this->shopRow($this->product)->isInStock());

        add_falcon_filter('falcon_product_is_in_stock', fn (bool $inStock, $shopData) => $shopData->post_id !== $this->product->id);

        $this->assertFalse($this->shopRow($this->product)->isInStock());
        $this->assertFalse($this->product->fresh()->is_in_stock);
    }

    public function test_product_query_action_narrows_every_listing(): void
    {
        $other = $this->makeProduct();
        $this->assertContains($other->id, $this->filteredProductIds([]));

        add_falcon_action('falcon_product_query', function ($query, array $filters) {
            $query->where('posts.id', $this->product->id);
        });

        $this->assertSame([$this->product->id], $this->filteredProductIds([]));
    }

    // ── cart ────────────────────────────────────────────────────────────────

    public function test_add_to_cart_validation_can_refuse_with_a_message(): void
    {
        $seenQty = null;
        add_falcon_filter('falcon_add_to_cart_validation', function ($ok, $product, $variation, int $qty) use (&$seenQty) {
            $seenQty = $qty;

            return $qty < 10 ? 'Wholesale orders start at 10.' : $ok;
        });

        $this->ajax()->post(route('shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 3])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Wholesale orders start at 10.']);

        $this->assertSame(3, $seenQty);
        $this->assertEmpty(session('falcon_cart', []));

        $this->ajax()->post(route('shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 10])->assertOk();
        $this->assertCount(1, session('falcon_cart'));
    }

    public function test_add_to_cart_validation_is_told_the_line_total(): void
    {
        $seen = [];
        add_falcon_filter('falcon_add_to_cart_validation', function ($ok, $product, $variation, int $qty) use (&$seen) {
            $seen[] = $qty;

            return $ok;
        });

        $this->ajax()->post(route('shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2]);
        $this->ajax()->post(route('shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 3]);

        $this->assertSame([2, 5], $seen);
    }

    public function test_add_to_cart_action_fires_with_the_priced_line(): void
    {
        $this->record('falcon_add_to_cart');

        $this->ajax()->post(route('shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])->assertOk();

        $calls = $this->callsTo('falcon_add_to_cart');
        $this->assertCount(1, $calls);
        [$key, $item, $product, $variation, $qty] = $calls[0];
        $this->assertSame((string) $this->product->id, $key);
        $this->assertSame(2, (int) $item['quantity']);
        $this->assertSame($this->product->id, $product->id);
        $this->assertNull($variation);
        $this->assertSame(2, $qty);
    }

    public function test_update_cart_validation_keeps_the_old_quantity_and_cart_updated_fires(): void
    {
        $this->fillCart(2);
        $this->record('falcon_cart_updated');
        add_falcon_filter('falcon_update_cart_validation', fn ($ok, string $key, array $item, int $qty) => $qty % 5 === 0 ? $ok : 'Sold in packs of 5.');

        $this->ajax()->post(route('shop.cart.update'), ['quantity' => ['k' => 3]])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Sold in packs of 5.', 'reload' => true]);
        $this->assertSame(2, (int) session('falcon_cart')['k']['quantity']);

        $this->ajax()->post(route('shop.cart.update'), ['quantity' => ['k' => 5]])->assertOk();
        $this->assertSame(5, (int) session('falcon_cart')['k']['quantity']);

        $this->assertCount(2, $this->callsTo('falcon_cart_updated'));
        $this->assertSame(5, (int) $this->callsTo('falcon_cart_updated')[1][0]['k']['quantity']);
    }

    public function test_cart_item_removed_fires_with_the_line(): void
    {
        $this->fillCart(2);
        $this->record('falcon_cart_item_removed');

        $this->ajax()->post(route('shop.cart.remove', 'k'))->assertOk();

        $this->assertSame('k', $this->callsTo('falcon_cart_item_removed')[0][0]);
        $this->assertSame($this->product->id, $this->callsTo('falcon_cart_item_removed')[0][1]['id']);
    }

    public function test_cart_subtotal_and_total_filters(): void
    {
        $this->fillCart(2);
        $this->assertSame(2100.0, round((float) get_falcon_cart_total(), 2));

        add_falcon_filter('falcon_cart_subtotal', fn ($subtotal, array $cart) => $subtotal - 500);
        $this->assertSame(1500.0, (float) get_falcon_cart_subtotal());

        $parts = null;
        add_falcon_filter('falcon_cart_total', function ($total, array $p) use (&$parts) {
            $parts = $p;

            return $total + 25; // a handling fee
        });

        $this->assertSame(1625.0, round((float) get_falcon_cart_total(), 2));
        $this->assertSame(1500.0, $parts['subtotal']);
        $this->assertSame(100.0, $parts['shipping']);
        $this->assertArrayHasKey('cart', $parts);

        // What the order is written with follows the filters.
        $this->placeOrder();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(1625.0, round((float) $order->total, 2));
    }

    // ── coupons and promotions ─────────────────────────────────────────────

    private function makeCoupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE10', 'type' => 'percent', 'amount' => 10, 'is_active' => true,
        ], $attributes));
    }

    public function test_coupon_is_valid_can_refuse_and_later_drops_an_applied_coupon(): void
    {
        $this->makeCoupon();
        $this->fillCart(2);
        $this->record('falcon_coupon_applied');

        $this->ajax()->post(route('shop.cart.coupon'), ['coupon_code' => 'save10'])->assertOk();
        $this->assertCount(1, session('falcon_coupons'));
        $this->assertSame('SAVE10', $this->callsTo('falcon_coupon_applied')[0][0]['code']);

        add_falcon_filter('falcon_coupon_is_valid', fn ($ok, array $coupon, array $cart) => 'Not for wholesale accounts.');

        // An applied coupon that stops qualifying falls off on the next cart change.
        $this->ajax()->post(route('shop.cart.update'), ['quantity' => ['k' => 3]])->assertOk();
        $this->assertEmpty(session('falcon_coupons'));

        $this->ajax()->post(route('shop.cart.coupon'), ['coupon_code' => 'SAVE10'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Not for wholesale accounts.']);
    }

    public function test_coupon_discount_amount_filter_is_capped_at_the_base(): void
    {
        $this->fillCart(2);
        $coupon = $this->makeCoupon()->toCartArray();

        $this->assertSame(200.0, round((float) get_falcon_coupon_discount_amount($coupon, session('falcon_cart')), 2));

        add_falcon_filter('falcon_coupon_discount_amount', fn ($discount) => $discount * 2);
        $this->assertSame(400.0, round((float) get_falcon_coupon_discount_amount($coupon, session('falcon_cart')), 2));

        add_falcon_filter('falcon_coupon_discount_amount', fn () => 999999, 20);
        $this->assertSame(2000.0, round((float) get_falcon_coupon_discount_amount($coupon, session('falcon_cart')), 2),
            'never more than the cart it is taken from');
    }

    public function test_coupon_removed_fires_for_each_removed_code(): void
    {
        session()->put('falcon_coupons', [['code' => 'A'], ['code' => 'B']]);
        $this->record('falcon_coupon_removed');

        $this->get(route('shop.cart.coupon.remove', ['code' => 'a']));
        $this->assertSame([['A']], $this->callsTo('falcon_coupon_removed'));

        $this->get(route('shop.cart.coupon.remove'));
        $this->assertSame([['A'], ['B']], $this->callsTo('falcon_coupon_removed'));
    }

    public function test_cart_promotions_filter_shapes_the_discount(): void
    {
        $this->fillCart(2);
        $this->assertSame(0.0, falcon_cart_promotion_total());

        add_falcon_filter('falcon_cart_promotions', fn (array $promos, array $cart) => array_merge($promos, [
            ['id' => 99, 'name' => 'Bulk buyer', 'summary' => '5% off', 'discount' => 100],
            ['name' => 'broken', 'discount' => 'lots'],
            'garbage',
        ]));

        $this->assertSame(100.0, falcon_cart_promotion_total());
        $this->assertSame('Bulk buyer', falcon_evaluate_promotions()[0]['name']);
        $this->assertSame(2000.0, round((float) get_falcon_cart_total(), 2), '2000 + 100 shipping − 100 promotion');
    }

    // ── shipping and tax ───────────────────────────────────────────────────

    public function test_shipping_methods_filter_adds_a_method_the_checkout_accepts(): void
    {
        $this->fillCart(2);
        add_falcon_filter('falcon_shipping_methods', function (array $methods, $country, array $cart) {
            $methods['freight'] = ['id' => 'freight', 'label' => 'Freight', 'cost' => 350];

            return $methods;
        });

        $this->assertArrayHasKey('freight', falcon_shipping_methods('Bangladesh'));

        $this->placeOrder(['shipping_method' => 'freight']);

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(350.0, round((float) $order->shipping_total, 2));
        $this->assertSame('Freight', $order->shipping_method);
    }

    public function test_shipping_methods_filter_cannot_leave_the_checkout_without_a_method(): void
    {
        $this->fillCart(1);
        add_falcon_filter('falcon_shipping_methods', fn () => []);

        $this->assertArrayHasKey('delivery', falcon_shipping_methods('Bangladesh'));
    }

    public function test_tax_rate_and_cart_tax_filters(): void
    {
        $this->setCmsOptions([
            'shop_calc_taxes' => '1',
            'shop_prices_include_tax' => 'no',
            'shop_tax_rates' => json_encode([['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT', 'shipping' => '0']]),
        ]);
        $this->fillCart(2);
        $this->assertSame(200.0, round((float) get_falcon_cart_tax(), 2));

        // A tax-exempt customer.
        add_falcon_filter('falcon_tax_rate', fn ($rate, $country) => null);
        $this->assertSame(0.0, round((float) get_falcon_cart_tax(), 2));
        $this->assertNull(falcon_tax_rate_for('Bangladesh'));

        add_falcon_filter('falcon_cart_tax', fn ($tax, array $cart) => 33.3);
        $this->assertSame(33.3, round((float) get_falcon_cart_tax(), 2));
    }

    // ── checkout ───────────────────────────────────────────────────────────

    public function test_checkout_validation_rules_filter_adds_a_required_field(): void
    {
        $this->fillCart(1);
        add_falcon_filter('falcon_checkout_validation_rules', function (array $rules) {
            $rules['company_vat'] = 'required';

            return $rules;
        });
        add_falcon_filter('falcon_checkout_validation_attributes', fn (array $a) => $a + ['company_vat' => 'VAT number']);

        $this->placeOrder()->assertSessionHasErrors('company_vat');
        $this->assertSame(0, Order::count());

        $this->placeOrder(['company_vat' => 'BD123']);
        $this->assertSame(1, Order::count());
    }

    public function test_checkout_validation_stops_the_order_before_anything_is_written(): void
    {
        $this->fillCart(2);
        add_falcon_filter('falcon_checkout_validation', fn ($ok, $request, array $cart) => 'Minimum wholesale order is 5 items.');

        $this->ajax()->post(route('shop.place-order'), $this->checkoutInput())
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Minimum wholesale order is 5 items.']);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, (int) DB::table('shop_products')->where('post_id', $this->product->id)->value('stock_quantity'));
    }

    public function test_a_payment_method_that_is_not_on_offer_is_refused(): void
    {
        $this->fillCart(1);

        $this->placeOrder(['payment_method' => 'made-up'])->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_payment_gateways_filter_adds_an_offline_gateway(): void
    {
        $this->fillCart(1);
        add_falcon_filter('falcon_payment_gateways', function (array $gateways) {
            $gateways['invoice'] = ['id' => 'invoice', 'title' => 'Pay on invoice (30 days)'];
            $gateways['bad id!'] = ['title' => 'dropped'];

            return $gateways;
        });

        $gateways = falcon_enabled_payment_gateways();
        $this->assertSame('offline', $gateways['invoice']['type']);
        $this->assertArrayNotHasKey('bad id!', $gateways);

        $this->placeOrder(['payment_method' => 'invoice']);

        $this->assertSame('invoice', Order::latest('id')->value('payment_method'));
    }

    public function test_order_number_filter_and_its_uniqueness_fallback(): void
    {
        add_falcon_filter('falcon_order_number', fn () => 'WS-1001');

        $this->fillCart(1);
        $this->placeOrder();
        $this->assertSame('WS-1001', Order::latest('id')->value('order_number'));

        $this->fillCart(1);
        $this->placeOrder(['billing_email' => 'second@example.test']);
        $this->assertStringStartsWith('ORD-', Order::latest('id')->value('order_number'), 'a taken number falls back');
    }

    public function test_order_data_and_order_item_data_filters_reach_the_rows(): void
    {
        $this->fillCart(2);
        add_falcon_filter('falcon_order_data', function (array $data, array $cart, $request) {
            $data['meta'] = array_merge($data['meta'] ?? [], ['po_number' => 'PO-77']);

            return $data;
        });
        add_falcon_filter('falcon_order_item_data', function (array $data, array $item, Order $order) {
            $data['meta'] = ['vendor_id' => 42];
            $data['order_id'] = 999999; // ignored: the item belongs to this order

            return $data;
        });

        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('PO-77', $order->meta['po_number']);
        $item = $order->items()->firstOrFail();
        $this->assertSame(42, $item->meta['vendor_id']);
    }

    public function test_order_created_fires_once_the_items_exist_and_finalized_follows(): void
    {
        $this->fillCart(2);
        $itemsAtCreation = null;
        add_falcon_action('falcon_order_created', function (Order $order, array $cart, $request) use (&$itemsAtCreation) {
            $itemsAtCreation = $order->items()->count();
        });
        $this->record('falcon_order_finalized');

        $this->placeOrder();

        $this->assertSame(1, $itemsAtCreation);
        $this->assertCount(1, $this->callsTo('falcon_order_finalized'));
    }

    public function test_customer_registered_fires_for_an_account_made_at_checkout(): void
    {
        $this->fillCart(1);
        $this->record('falcon_customer_registered');

        $this->placeOrder(['create_account' => '1', 'account_password' => 'secret123']);

        $this->assertCount(1, $this->callsTo('falcon_customer_registered'));
        $this->assertInstanceOf(User::class, $this->callsTo('falcon_customer_registered')[0][0]);
        $this->assertSame(
            (int) User::where('email', 'alice@example.test')->value('id'),
            (int) $this->callsTo('falcon_customer_registered')[0][0]->getKey()
        );
    }

    public function test_product_stock_changed_fires_for_the_checkout_claim(): void
    {
        $this->fillCart(2);
        $this->record('falcon_product_stock_changed');

        $this->placeOrder();

        $this->assertSame([[$this->product->id, null, 8, -2, 'checkout']], $this->callsTo('falcon_product_stock_changed'));
    }

    public function test_product_stock_changed_fires_when_a_cancelled_order_restocks(): void
    {
        $this->fillCart(2);
        $this->placeOrder();
        $order = Order::latest('id')->firstOrFail();
        $this->record('falcon_product_stock_changed');

        $admin = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
        $this->actingAs($admin)->post(route('admin.shop.orders.status', $order->id), ['status' => 'cancelled']);

        $this->assertSame([[$this->product->id, null, 10, 2, 'order_restocked']], $this->callsTo('falcon_product_stock_changed'));
    }

    public function test_admin_email_recipients_filter(): void
    {
        $this->setCmsOptions(['shop_email_admin_recipient' => 'owner@example.test']);
        add_falcon_filter('falcon_order_admin_email_recipients', fn (array $to, Order $order) => array_merge($to, ['warehouse@example.test', 'not-an-email']));

        $this->fillCart(1);
        $this->placeOrder();

        Mail::assertQueued(OrderNotificationMail::class, fn ($mail) => $mail->hasTo('owner@example.test')
            && $mail->hasTo('warehouse@example.test')
            && !$mail->hasTo('not-an-email'));
    }

    public function test_order_email_subject_filter(): void
    {
        $order = $this->makeOrder(['order_number' => 'ORD-SUBJ-1']);
        add_falcon_filter('falcon_order_email_subject', fn (string $subject, Order $o, string $type, string $to) => "{$type}/{$to}: {$o->order_number}");

        $this->assertSame('placed/customer: ORD-SUBJ-1', (new OrderNotificationMail($order))->envelope()->subject);
        $this->assertSame('status_updated/customer: ORD-SUBJ-1', (new OrderNotificationMail($order, 'status_updated'))->envelope()->subject);
    }

    // ── a plugin's own online gateway ──────────────────────────────────────

    public function test_a_custom_online_gateway_redirects_and_is_confirmed_on_return(): void
    {
        add_falcon_filter('falcon_payment_gateways', function (array $g) {
            $g['fakepay'] = ['id' => 'fakepay', 'title' => 'FakePay', 'type' => 'online'];

            return $g;
        });
        add_falcon_filter('falcon_payment_redirect_url', fn ($url, Order $order, string $method) => $method === 'fakepay'
            ? 'https://pay.example.test/session/'.$order->order_number
            : $url);
        add_falcon_filter('falcon_payment_return_verified', fn ($ok, Order $order, string $gateway, $request) => $request->query('ref') === 'good' ? 'TXN-9' : false);

        $this->record('falcon_order_paid');
        $this->record('falcon_order_status_changed');
        $this->record('falcon_order_finalized');

        $this->fillCart(1);
        $response = $this->ajax()->post(route('shop.place-order'), $this->checkoutInput(['payment_method' => 'fakepay']))->assertOk();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('https://pay.example.test/session/'.$order->order_number, $response->json('redirect'));
        $this->assertSame([], $this->callsTo('falcon_order_finalized'), 'not confirmed before it is paid');

        // A return the gateway cannot vouch for leaves the order pending.
        $this->get(route('shop.payment.return', $order->id).'?gateway=fakepay&ref=bad');
        $this->assertNull($order->fresh()->paid_at);

        $this->get(route('shop.payment.return', $order->id).'?gateway=fakepay&ref=good');
        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertSame('TXN-9', $order->transaction_id);
        $this->assertSame('processing', $order->status);

        $this->assertCount(1, $this->callsTo('falcon_order_paid'));
        $this->assertCount(1, $this->callsTo('falcon_order_finalized'));
        $this->assertSame(['pending', 'processing'], array_slice($this->callsTo('falcon_order_status_changed')[0], 1));

        // A second return changes nothing — it was paid once.
        $this->get(route('shop.payment.return', $order->id).'?gateway=fakepay&ref=good');
        $this->assertCount(1, $this->callsTo('falcon_order_paid'));
    }

    // ── order lifecycle ────────────────────────────────────────────────────

    public function test_order_status_changed_fires_from_any_update_and_only_on_a_change(): void
    {
        $order = $this->makeOrder();
        $this->record('falcon_order_status_changed');
        $this->record('falcon_order_status_completed');

        $order->update(['customer_note' => 'no status change']);
        $this->assertSame([], $this->callsTo('falcon_order_status_changed'));

        $order->update(['status' => 'completed']);

        $this->assertCount(1, $this->callsTo('falcon_order_status_changed'));
        [$fired, $old, $new] = $this->callsTo('falcon_order_status_changed')[0];
        $this->assertSame($order->id, $fired->id);
        $this->assertSame(['pending', 'completed'], [$old, $new]);
        $this->assertSame('pending', $this->callsTo('falcon_order_status_completed')[0][1]);
    }

    public function test_order_status_changed_fires_from_the_admin_screen(): void
    {
        $order = $this->makeOrder();
        $this->record('falcon_order_status_changed');

        $admin = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
        $this->actingAs($admin)->post(route('admin.shop.orders.status', $order->id), ['status' => 'on-hold']);

        $this->assertSame(['pending', 'on-hold'], array_slice($this->callsTo('falcon_order_status_changed')[0], 1));
    }

    public function test_stripe_webhook_fires_payment_failed_and_refunded(): void
    {
        $this->setCmsOptions(['shop_payment_stripe_webhook_secret' => 'whsec_hooks']);
        $this->record('falcon_order_payment_failed');
        $this->record('falcon_order_refunded');

        $failing = $this->makeOrder(['payment_method' => 'stripe', 'transaction_id' => 'pi_fail']);
        $this->stripeWebhook(['type' => 'payment_intent.payment_failed', 'data' => ['object' => [
            'id' => 'pi_fail', 'metadata' => ['order_id' => (string) $failing->id],
        ]]])->assertOk();

        $this->assertSame($failing->id, $this->callsTo('falcon_order_payment_failed')[0][0]->id);
        $this->assertSame('stripe', $this->callsTo('falcon_order_payment_failed')[0][1]);

        $paid = $this->makeOrder(['payment_method' => 'stripe', 'transaction_id' => 'pi_paid', 'status' => 'processing', 'paid_at' => now()]);
        $this->stripeWebhook(['type' => 'charge.refunded', 'data' => ['object' => [
            'id' => 'ch_1', 'payment_intent' => 'pi_paid', 'currency' => 'usd', 'amount_refunded' => 4000,
            'metadata' => ['order_id' => (string) $paid->id],
        ]]])->assertOk();

        [$refundedOrder, $amount, $source] = $this->callsTo('falcon_order_refunded')[0];
        $this->assertSame($paid->id, $refundedOrder->id);
        $this->assertSame(40.0, $amount);
        $this->assertSame('stripe_webhook', $source);
    }

    // ── account, reviews, wishlist, admin lists ────────────────────────────

    public function test_account_tabs_filter_and_tab_content_action(): void
    {
        $this->assignShopPages();
        $user = $this->makeUser(['name' => 'Vendor Vic']);
        add_falcon_filter('falcon_account_tabs', function (array $tabs, $u) {
            $tabs[] = ['vendor', 'Vendor dashboard', 'store'];
            $tabs[] = ['Bad Slug!', 'dropped'];

            return $tabs;
        });
        add_falcon_action('falcon_account_tab_content', function (string $tab, $u) {
            echo '<div id="vendor-panel">VENDOR PANEL for '.e($u->getAttribute('name')).'</div>';
        });
        $this->record('falcon_account_orders_query');

        $html = $this->actingAs($user)->get('/account?tab=vendor')->assertOk()->getContent();

        $this->assertStringContainsString('Vendor dashboard', $html);
        $this->assertStringContainsString('VENDOR PANEL for Vendor Vic', $html);
        $this->assertStringNotContainsString('dropped', $html);
        $this->assertCount(1, $this->callsTo('falcon_account_orders_query'));

        // The built-in tabs do not run the plugin action.
        $this->assertStringNotContainsString('VENDOR PANEL', $this->get('/account?tab=orders')->getContent());
    }

    public function test_review_auto_approve_filter_and_review_created(): void
    {
        $this->record('falcon_review_created');
        add_falcon_filter('falcon_review_auto_approve', fn (bool $approve, array $data, $user) => true);

        $this->post(route('shop.review.store'), [
            'post_id' => $this->product->id, 'rating' => 5, 'comment' => 'Great value in bulk.',
            'name' => 'Guest', 'email' => 'guest@example.test',
        ]);

        $review = Review::latest('id')->firstOrFail();
        $this->assertTrue((bool) DB::table('shop_reviews')->where('id', $review->id)->value('is_approved'));
        $this->assertSame($review->id, $this->callsTo('falcon_review_created')[0][0]->id);
    }

    public function test_wishlist_added_and_removed(): void
    {
        $user = $this->makeUser();
        $this->record('falcon_wishlist_item_added');
        $this->record('falcon_wishlist_item_removed');

        $this->actingAs($user);
        $this->ajax()->post(route('shop.wishlist.toggle'), ['product_id' => $this->product->id])->assertOk();
        $this->ajax()->post(route('shop.wishlist.toggle'), ['product_id' => $this->product->id])->assertOk();

        $this->assertSame([[$this->product->id, $user->id]], $this->callsTo('falcon_wishlist_item_added'));
        $this->assertSame([[$this->product->id, $user->id]], $this->callsTo('falcon_wishlist_item_removed'));

        $this->ajax()->post(route('shop.wishlist.toggle'), ['product_id' => $this->product->id]);
        $this->post(route('shop.wishlist.remove'), ['product_id' => $this->product->id]);
        $this->assertCount(2, $this->callsTo('falcon_wishlist_item_removed'));
    }

    public function test_admin_orders_query_action_scopes_the_list(): void
    {
        $mine = $this->makeOrder(['order_number' => 'ORD-MINE']);
        $this->makeOrder(['order_number' => 'ORD-THEIRS']);
        add_falcon_action('falcon_admin_orders_query', fn ($query, $request) => $query->whereKey($mine->id));

        $admin = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
        $html = $this->actingAs($admin)->get('/admin/shop/orders')->assertOk()->getContent();

        $this->assertStringContainsString('ORD-MINE', $html);
        $this->assertStringNotContainsString('ORD-THEIRS', $html);
    }

    // ── no hook, no change ─────────────────────────────────────────────────

    public function test_with_nothing_hooked_the_numbers_are_exactly_what_they_were(): void
    {
        $this->fillCart(2);

        $this->assertSame(2000.0, get_falcon_cart_subtotal());
        $this->assertSame(2100.0, (float) get_falcon_cart_total());
        $this->assertSame(['price' => 1000.0, 'sale_price' => null], falcon_product_prices($this->product));
    }
}
