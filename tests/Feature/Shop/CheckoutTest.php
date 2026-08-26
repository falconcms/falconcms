<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Checkout, end to end, through the real route.
 *
 * Everything underneath this — subtotal, tax, coupons, shipping, the stock claim — has its
 * own tests. This is the one that proves those parts are wired together: that the figure
 * the shopper was shown is the figure written to shop_orders, that the stock really comes
 * off the shelf, that the coupon is really spent, and that a cart which cannot be
 * fulfilled leaves nothing behind.
 *
 * This is the shape of gap that hides bugs — every piece verified alone, the seam between
 * them never exercised.
 */
class CheckoutTest extends TestCase
{
    use MakesShopFixtures;

    private Post $product;

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
            'shop_country_state' => 'Bangladesh',
            'shop_payment_cod_enable' => '1',
            'shop_shipping_zones' => json_encode([[
                'name' => 'Flat rate', 'countries' => ['Bangladesh'], 'cost' => 100,
                'free_threshold' => 0, 'type' => 'order', 'rules' => [],
            ]]),
        ]);

        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->product = $this->makeProduct(['price' => 1000, 'manage_stock' => 1, 'stock_quantity' => 10]);
        $this->fillCart(2);
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

    // ---- the order that comes out ----------------------------------------------

    public function test_an_order_is_written_with_the_totals_the_shopper_was_shown(): void
    {
        // What the cart page would have said, captured before checkout consumes the session.
        $expected = [
            'subtotal' => round(get_falcon_cart_subtotal(), 2),
            'shipping' => round(get_falcon_cart_shipping('Bangladesh'), 2),
            'tax' => round(get_falcon_cart_tax(), 2),
            'total' => round(get_falcon_cart_total(), 2),
        ];

        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame($expected['subtotal'], round((float) $order->subtotal, 2));
        $this->assertSame($expected['shipping'], round((float) $order->shipping_total, 2));
        $this->assertSame($expected['tax'], round((float) $order->tax_total, 2));
        $this->assertSame($expected['total'], round((float) $order->total, 2));

        $this->assertSame(2100.0, round((float) $order->total, 2), '2000 goods + 100 shipping');
    }

    public function test_the_order_carries_the_customer_details(): void
    {
        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame('alice@example.test', $order->customer_email);
        $this->assertSame('Alice', $order->first_name);
        $this->assertSame('12 Road 5', $order->address_line_1);
        $this->assertSame('Bangladesh', $order->country);
        $this->assertSame('cod', $order->payment_method);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_every_cart_line_becomes_an_order_item(): void
    {
        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();
        $items = DB::table('shop_order_items')->where('order_id', $order->id)->get();

        $this->assertCount(1, $items);
        $this->assertSame($this->product->id, (int) $items[0]->product_id);
        $this->assertSame(2, (int) $items[0]->quantity);
        $this->assertSame(1000.0, round((float) $items[0]->price, 2));
        $this->assertSame(2000.0, round((float) $items[0]->subtotal, 2));
    }

    public function test_the_order_items_add_up_to_the_order_subtotal(): void
    {
        $second = $this->makeProduct(['price' => 250, 'manage_stock' => 0]);
        session()->put('falcon_cart', [
            'a' => ['id' => $this->product->id, 'name' => 'A', 'quantity' => 2, 'variation_id' => null, 'price' => 1000, 'sale_price' => null],
            'b' => ['id' => $second->id, 'name' => 'B', 'quantity' => 3, 'variation_id' => null, 'price' => 250, 'sale_price' => null],
        ]);

        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();
        $sum = (float) DB::table('shop_order_items')->where('order_id', $order->id)->sum('subtotal');

        $this->assertSame(2750.0, round($sum, 2));
        $this->assertSame(round((float) $order->subtotal, 2), round($sum, 2),
            'the books must agree with themselves');
    }

    // ---- side effects ----------------------------------------------------------

    public function test_stock_comes_off_the_shelf(): void
    {
        $this->placeOrder();

        $this->assertSame(8, (int) DB::table('shop_products')
            ->where('post_id', $this->product->id)->value('stock_quantity'));
    }

    public function test_the_cart_and_its_coupons_are_cleared(): void
    {
        $this->placeOrder();

        $this->assertEmpty(session()->get('falcon_cart', []));
        $this->assertEmpty(session()->get('falcon_coupons', []));
    }

    public function test_a_digital_product_gets_its_download_tokens(): void
    {
        $downloadable = $this->makeProduct([
            'price' => 500, 'manage_stock' => 0, 'is_downloadable' => 1,
        ]);
        DB::table('shop_product_downloads')->insert([
            'product_id' => $downloadable->shopData->id,
            'name' => 'Manual.pdf',
            'file_path' => 'downloads/manual.pdf',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->fillCart(1, $downloadable);
        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(1, DB::table('shop_order_downloads')->where('order_id', $order->id)->count());
    }

    public function test_a_physical_product_gets_no_download_tokens(): void
    {
        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(0, DB::table('shop_order_downloads')->where('order_id', $order->id)->count());
    }

    // ---- coupons through the whole flow ----------------------------------------

    public function test_a_coupon_reaches_the_order_and_is_spent(): void
    {
        Coupon::create([
            'code' => 'SAVE10', 'type' => 'percent', 'amount' => 10,
            'is_active' => true, 'usage_count' => 0,
        ]);
        session()->put('falcon_coupons', [[
            'code' => 'SAVE10', 'type' => 'percent', 'amount' => 10,
            'products' => [], 'categories' => [],
        ]]);

        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(200.0, round((float) $order->discount_total, 2), '10% of 2000');
        $this->assertSame(1900.0, round((float) $order->total, 2), '2000 + 100 - 200');
        $this->assertSame('SAVE10', $order->coupon_code);

        $this->assertSame(1, (int) DB::table('shop_coupons')->where('code', 'SAVE10')->value('usage_count'),
            'the coupon must be spent, not merely applied');
    }

    /**
     * The discount written to the order has to come from the same helper the cart used.
     * An inline sum here once ignored the stacking policy and each coupon's restrictions,
     * so the books recorded a discount the customer never received.
     */
    public function test_stacked_coupons_are_recorded_the_way_they_were_charged(): void
    {
        foreach ([['TEN', 10], ['FIVE', 5]] as [$code, $amount]) {
            Coupon::create([
                'code' => $code, 'type' => 'percent', 'amount' => $amount,
                'is_active' => true, 'usage_count' => 0,
            ]);
        }
        session()->put('falcon_coupons', [
            ['code' => 'TEN', 'type' => 'percent', 'amount' => 10, 'products' => [], 'categories' => []],
            ['code' => 'FIVE', 'type' => 'percent', 'amount' => 5, 'products' => [], 'categories' => []],
        ]);

        $expectedDiscount = round(falcon_cart_discount_total(), 2);
        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(290.0, $expectedDiscount, '200, then 5% of the remaining 1800');
        $this->assertSame($expectedDiscount, round((float) $order->discount_total, 2));
        $this->assertStringContainsString('TEN', (string) $order->coupon_code);
        $this->assertStringContainsString('FIVE', (string) $order->coupon_code);
    }

    // ---- tax through the whole flow --------------------------------------------

    public function test_tax_reaches_the_order(): void
    {
        $this->setCmsOptions([
            'shop_calc_taxes' => '1',
            'shop_tax_price_entry' => 'exclusive',
            'shop_tax_display_shop' => 'exclusive',
            'shop_tax_calculation_basis' => 'shipping',
            'shop_tax_rates' => json_encode([['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT']]),
        ]);

        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(200.0, round((float) $order->tax_total, 2), '10% of 2000');
        $this->assertSame(2300.0, round((float) $order->total, 2), '2000 + 100 + 200');
    }

    // ---- what must not produce an order ----------------------------------------

    public function test_an_empty_cart_produces_no_order(): void
    {
        session()->forget('falcon_cart');

        $this->placeOrder();

        $this->assertSame(0, Order::count());
    }

    public function test_missing_billing_details_produce_no_order(): void
    {
        $this->post(route('shop.place-order'), ['payment_method' => 'cod']);

        $this->assertSame(0, Order::count());
    }

    public function test_a_malformed_email_produces_no_order(): void
    {
        $this->placeOrder(['billing_email' => 'not-an-email']);

        $this->assertSame(0, Order::count());
    }

    /**
     * The cart says three, the shelf has one. Nothing may be half-done: no order, and the
     * stock exactly as it was.
     */
    public function test_a_cart_that_cannot_be_fulfilled_leaves_nothing_behind(): void
    {
        DB::table('shop_products')->where('post_id', $this->product->id)->update(['stock_quantity' => 1]);
        $this->fillCart(3);

        $this->placeOrder();

        $this->assertSame(0, Order::count(), 'no order for stock that does not exist');
        $this->assertSame(1, (int) DB::table('shop_products')
            ->where('post_id', $this->product->id)->value('stock_quantity'));
        $this->assertSame(0, DB::table('shop_order_items')->count());
    }

    /** A double-tapped Place Order button must not buy the same basket twice. */
    public function test_the_same_order_submitted_twice_is_only_taken_once(): void
    {
        $this->placeOrder();
        $this->assertSame(1, Order::count());

        $this->fillCart(2);
        $this->placeOrder();

        $this->assertSame(1, Order::count(), 'the duplicate guard let a second order through');
    }

    public function test_a_second_genuinely_different_order_is_accepted(): void
    {
        $this->placeOrder();

        $this->fillCart(3);
        $this->placeOrder();

        $this->assertSame(2, Order::count());
    }

    // ---- nothing to pay --------------------------------------------------------

    /**
     * A 100% coupon leaves nothing to collect. Card processors reject a zero-value charge,
     * so this has to be settled here rather than sent to a gateway that would strand the
     * customer and leave the order pending forever.
     */
    public function test_an_order_with_nothing_to_pay_is_settled_immediately(): void
    {
        $this->setCmsOptions(['shop_shipping_zones' => json_encode([[
            'name' => 'Free', 'countries' => ['Bangladesh'], 'cost' => 0,
            'free_threshold' => 0, 'type' => 'order', 'rules' => [],
        ]])]);

        Coupon::create([
            'code' => 'ALLOFF', 'type' => 'percent', 'amount' => 100,
            'is_active' => true, 'usage_count' => 0,
        ]);
        session()->put('falcon_coupons', [[
            'code' => 'ALLOFF', 'type' => 'percent', 'amount' => 100,
            'products' => [], 'categories' => [],
        ]]);

        $this->placeOrder();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(0.0, round((float) $order->total, 2));
        $this->assertNotSame('pending', $order->status, 'a settled order must not sit on pending');
    }
}
