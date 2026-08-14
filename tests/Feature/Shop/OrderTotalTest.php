<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use ReflectionClass;

/**
 * The number the customer is actually charged.
 *
 * Subtotal, shipping, discount and tax each have their own tests; this is about how they
 * compose, and about the three places that number appears having to agree: the cart page,
 * the row written to shop_orders, and the amount handed to the payment gateway. A
 * disagreement between the last two is the worst bug this shop can have — the customer is
 * charged one figure and the shop's books record another.
 */
class OrderTotalTest extends TestCase
{
    use MakesShopFixtures;

    private Post $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setCmsOptions([
            'shop_enable_coupons' => '1',
            'shop_coupon_stacking_policy' => '1',
            'shop_calc_taxes' => '0',
            'shop_currency' => 'USD',
            'shop_country_state' => 'Bangladesh',
            'shop_shipping_zones' => json_encode([[
                'name' => 'Flat rate', 'countries' => ['Bangladesh'], 'cost' => 100,
                'free_threshold' => 0, 'type' => 'order', 'rules' => [],
            ]]),
        ]);

        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->product = $this->makeProduct(['price' => 1000]);
        $this->cart(2); // subtotal 2000
    }

    private function cart(int $quantity): void
    {
        session()->put('falcon_cart', ['k' => [
            'id' => $this->product->id, 'name' => 'x', 'quantity' => $quantity,
            'variation_id' => null, 'price' => 1000, 'sale_price' => null,
        ]]);
    }

    private function enableTax(float $rate = 10, string $entry = 'exclusive', string $taxShipping = '0'): void
    {
        $this->setCmsOptions([
            'shop_calc_taxes' => '1',
            'shop_tax_price_entry' => $entry,
            'shop_tax_display_shop' => $entry,
            'shop_tax_calculation_basis' => 'shipping',
            'shop_tax_rates' => json_encode([
                ['country' => 'Bangladesh', 'rate' => $rate, 'name' => 'VAT', 'shipping' => $taxShipping],
            ]),
        ]);
    }

    private function applyCoupon(array $attributes): void
    {
        Coupon::create(array_merge([
            'code' => 'CODE', 'type' => 'percent', 'amount' => 10,
            'is_active' => true, 'usage_count' => 0,
        ], $attributes));

        $request = Request::create('/coupon', 'POST', [
            'coupon_code' => $attributes['code'] ?? 'CODE',
        ]);
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        (new ShopFrontendController)->applyCoupon($request);
    }

    // ---- composition -----------------------------------------------------------

    public function test_the_plainest_total_is_subtotal_plus_shipping(): void
    {
        $this->assertSame(2000.0, round(get_falcon_cart_subtotal(), 2));
        $this->assertSame(100.0, round(get_falcon_cart_shipping('Bangladesh'), 2));
        $this->assertSame(2100.0, round(get_falcon_cart_total(), 2));
    }

    public function test_discount_comes_off_before_tax_is_added(): void
    {
        $this->enableTax(10);
        $this->applyCoupon(['code' => 'TEN', 'type' => 'percent', 'amount' => 10]);

        // 2000 goods − 200 discount = 1800 taxable → 180 tax. Shipping 100 is untaxed.
        $this->assertSame(180.0, round(get_falcon_cart_tax(), 2));
        $this->assertSame(2080.0, round(get_falcon_cart_total(), 2), '2000 + 100 − 200 + 180');
    }

    public function test_taxed_shipping_is_included_in_the_tax_but_counted_once_in_the_total(): void
    {
        $this->enableTax(10, 'exclusive', taxShipping: '1');

        // (2000 goods + 100 shipping) × 10% = 210.
        $this->assertSame(210.0, round(get_falcon_cart_tax(), 2));
        $this->assertSame(2310.0, round(get_falcon_cart_total(), 2), '2000 + 100 + 210');
    }

    public function test_inclusive_tax_is_not_added_to_the_total_a_second_time(): void
    {
        $this->enableTax(10, 'inclusive');

        $this->assertSame(2100.0, round(get_falcon_cart_total(), 2),
            'the customer pays the marked prices plus shipping, nothing more');
        $this->assertGreaterThan(0, get_falcon_cart_tax(), 'but the tax is still reported');
    }

    public function test_a_free_shipping_coupon_zeroes_the_shipping_line(): void
    {
        $this->assertSame(100.0, round(get_falcon_cart_shipping('Bangladesh'), 2));

        $this->applyCoupon(['code' => 'FREESHIP', 'type' => 'free_shipping', 'amount' => 0]);

        $this->assertSame(0.0, round(get_falcon_cart_shipping('Bangladesh'), 2));
        $this->assertSame(2000.0, round(get_falcon_cart_total(), 2), 'goods only');
        $this->assertSame(0.0, round(falcon_cart_discount_total(), 2),
            'it must not ALSO come off the cart as a discount');
    }

    public function test_a_total_can_never_go_negative(): void
    {
        $this->applyCoupon(['code' => 'HUGE', 'type' => 'fixed_cart', 'amount' => 99999]);

        $this->assertGreaterThanOrEqual(0.0, get_falcon_cart_total());
    }

    public function test_an_empty_cart_totals_nothing(): void
    {
        session()->forget('falcon_cart');

        $this->assertSame(0.0, round(get_falcon_cart_subtotal(), 2));
        $this->assertSame(0.0, round(get_falcon_cart_tax(), 2));
        $this->assertSame(0.0, round(get_falcon_cart_total(), 2));
    }

    public function test_the_subtotal_uses_the_sale_price_when_there_is_one(): void
    {
        session()->put('falcon_cart', ['k' => [
            'id' => $this->product->id, 'name' => 'x', 'quantity' => 2,
            'variation_id' => null, 'price' => 1000, 'sale_price' => 700,
        ]]);

        $this->assertSame(1400.0, round(get_falcon_cart_subtotal(), 2));
    }

    // ---- what the cart page shows ----------------------------------------------

    /**
     * The cart's AJAX payload is what the shopper reads after every quantity change. It has
     * to be built from the same helpers as the order, not from a parallel calculation.
     */
    public function test_the_cart_payload_matches_the_helpers_it_is_built_from(): void
    {
        $this->enableTax(10);

        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('cartTotalsPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($controller);

        $this->assertTrue($payload['success']);
        $this->assertSame(2, $payload['cart_count']);
        $this->assertSame(falcon_price_format(get_falcon_cart_subtotal()), $payload['subtotal']);
        $this->assertSame(falcon_price_format(get_falcon_cart_total()), $payload['total']);
        $this->assertSame(falcon_price_format(get_falcon_cart_tax()), $payload['tax']);
        $this->assertSame('VAT', $payload['tax_label']);
        $this->assertTrue($payload['tax_visible']);
        $this->assertFalse($payload['tax_included']);
    }

    public function test_the_tax_row_is_hidden_when_there_is_no_tax(): void
    {
        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('cartTotalsPayload');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller)['tax_visible']);
    }

    // ---- what the gateway is asked to charge -----------------------------------

    /**
     * Stripe takes the smallest currency unit. Sending 2100 instead of 210000 undercharges
     * by a hundredfold; sending 210000 for a zero-decimal currency overcharges by the same.
     */
    public function test_the_stripe_amount_is_the_order_total_in_minor_units(): void
    {
        $order = new Order(['total' => 2100.00]);

        $this->setCmsOptions(['shop_currency' => 'USD']);
        $this->assertSame(210000, $this->stripeAmountFor($order));

        $this->setCmsOptions(['shop_currency' => 'BDT']);
        $this->assertSame(210000, $this->stripeAmountFor($order));
    }

    public function test_zero_decimal_currencies_are_sent_whole(): void
    {
        $order = new Order(['total' => 2100.00]);

        $this->setCmsOptions(['shop_currency' => 'JPY']);
        $this->assertSame(2100, $this->stripeAmountFor($order));

        $this->setCmsOptions(['shop_currency' => 'krw']);
        $this->assertSame(2100, $this->stripeAmountFor($order), 'case must not matter');
    }

    public function test_fractional_totals_are_rounded_not_truncated(): void
    {
        $this->setCmsOptions(['shop_currency' => 'USD']);

        $this->assertSame(1999, $this->stripeAmountFor(new Order(['total' => 19.985])));
        $this->assertSame(1000, $this->stripeAmountFor(new Order(['total' => 9.999])));
    }

    private function stripeAmountFor(Order $order): int
    {
        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('stripeAmount');
        $method->setAccessible(true);

        return $method->invoke($controller, $order);
    }
}
