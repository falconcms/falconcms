<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

/**
 * Coupons — what they take off, and what stops them.
 *
 * A coupon is money, and every rule that limits one (expiry, minimum spend, usage caps,
 * which products it covers) is the only thing between the shop and giving stock away. The
 * rules are checked twice, in applyCoupon() when the code is typed and again in
 * revalidateCoupon() on every cart change, because a coupon that was valid when it was
 * applied must stop discounting the moment it stops being valid.
 */
class CouponTest extends TestCase
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
        ]);

        $this->product = $this->makeProduct(['price' => 1000]);
        $this->fillCart(2); // subtotal 2000
    }

    private function fillCart(int $quantity, ?Post $product = null): void
    {
        $product ??= $this->product;

        session()->put('falcon_cart', ['k' => [
            'id' => $product->id, 'name' => 'x', 'quantity' => $quantity,
            'variation_id' => null, 'price' => 1000, 'sale_price' => null,
        ]]);
    }

    private function makeCoupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE10',
            'type' => 'percent',
            'amount' => 10,
            'is_active' => true,
            'usage_count' => 0,
        ], $attributes));
    }

    /** Type a code into the cart form. Returns the message the shopper is shown. */
    private function apply(string $code): string
    {
        $request = Request::create('/coupon', 'POST', ['coupon_code' => $code]);
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        (new ShopFrontendController)->applyCoupon($request);

        return (string) (session()->get('error') ?? session()->get('success') ?? '');
    }

    /** @return array<int, string> the codes currently sticking to the cart */
    private function appliedCodes(): array
    {
        return array_map(
            fn ($coupon) => strtoupper((string) $coupon['code']),
            session()->get('falcon_coupons', [])
        );
    }

    // ---- lookup ----------------------------------------------------------------

    public function test_a_code_is_found_regardless_of_case_or_padding(): void
    {
        $this->makeCoupon(['code' => 'SAVE10']);

        $this->assertNotNull(falcon_find_coupon('save10'));
        $this->assertNotNull(falcon_find_coupon('  SaVe10  '));
        $this->assertNull(falcon_find_coupon('NOPE'));
        $this->assertNull(falcon_find_coupon(''));
        $this->assertNull(falcon_find_coupon(null));
    }

    public function test_an_inactive_coupon_cannot_be_found(): void
    {
        $this->makeCoupon(['is_active' => false]);

        $this->assertNull(falcon_find_coupon('SAVE10'));
    }

    // ---- what each type takes off ----------------------------------------------

    public function test_a_percentage_coupon_discounts_the_whole_subtotal(): void
    {
        $this->makeCoupon(['type' => 'percent', 'amount' => 10]);
        $this->apply('SAVE10');

        $this->assertSame(200.0, round(falcon_cart_discount_total(), 2), '10% of 2000');
    }

    public function test_a_fixed_cart_coupon_takes_its_amount_off_once(): void
    {
        $this->makeCoupon(['code' => 'FLAT', 'type' => 'fixed_cart', 'amount' => 150]);
        $this->apply('FLAT');

        $this->assertSame(150.0, round(falcon_cart_discount_total(), 2));
    }

    /**
     * "৳50 off each item" with no product restriction used to fall through to the
     * fixed_cart branch and take ৳50 off the whole cart once.
     */
    public function test_a_fixed_product_coupon_is_per_unit_even_with_no_restrictions(): void
    {
        $this->makeCoupon(['code' => 'EACH', 'type' => 'fixed_product', 'amount' => 50]);
        $this->apply('EACH');

        $this->assertSame(100.0, round(falcon_cart_discount_total(), 2), '50 x 2 units');
    }

    public function test_a_fixed_cart_coupon_can_never_exceed_the_cart(): void
    {
        $this->fillCart(1); // subtotal 1000
        $this->makeCoupon(['code' => 'HUGE', 'type' => 'fixed_cart', 'amount' => 99999]);
        $this->apply('HUGE');

        $this->assertSame(1000.0, round(falcon_cart_discount_total(), 2), 'capped at the subtotal');
        $this->assertSame(0.0, round(get_falcon_cart_total(), 2), 'never negative');
    }

    public function test_a_free_shipping_coupon_takes_nothing_off_the_cart_but_sticks(): void
    {
        $this->makeCoupon(['code' => 'FREESHIP', 'type' => 'free_shipping', 'amount' => 0]);
        $this->apply('FREESHIP');

        $this->assertSame(['FREESHIP'], $this->appliedCodes(),
            'a zero discount must not get it rejected as "not valid for these products"');
        $this->assertSame(0.0, round(falcon_cart_discount_total(), 2));
        $this->assertTrue(falcon_cart_has_free_shipping_coupon());
    }

    // ---- the rules that stop a coupon ------------------------------------------

    public function test_an_unknown_code_is_refused(): void
    {
        $this->assertStringContainsString('Invalid', $this->apply('NOPE'));
        $this->assertSame([], $this->appliedCodes());
    }

    public function test_an_expired_coupon_is_refused(): void
    {
        $this->makeCoupon(['expiry_date' => now()->subDay()]);

        $this->assertStringContainsString('expired', $this->apply('SAVE10'));
        $this->assertSame([], $this->appliedCodes());
    }

    public function test_a_coupon_expiring_today_still_works(): void
    {
        $this->makeCoupon(['expiry_date' => now()]);
        $this->apply('SAVE10');

        $this->assertSame(['SAVE10'], $this->appliedCodes());
    }

    public function test_a_cart_below_the_minimum_spend_is_refused(): void
    {
        $this->fillCart(1); // 1000
        $this->makeCoupon(['min_spend' => 1500]);

        $this->assertStringContainsString('Minimum spend', $this->apply('SAVE10'));
        $this->assertSame([], $this->appliedCodes());
    }

    /** The rule has to keep holding, not just hold at the moment the code was typed. */
    public function test_a_coupon_falls_off_when_the_cart_drops_below_the_minimum(): void
    {
        $this->makeCoupon(['min_spend' => 1500]);
        $this->apply('SAVE10');
        $this->assertSame(['SAVE10'], $this->appliedCodes());

        $this->fillCart(1); // subtotal falls to 1000
        $this->apply('SAVE10'); // any cart action revalidates

        $this->assertSame([], $this->appliedCodes());
        $this->assertSame(0.0, round(falcon_cart_discount_total(), 2));
    }

    public function test_a_coupon_deactivated_after_it_was_applied_stops_discounting(): void
    {
        $this->makeCoupon();
        $this->apply('SAVE10');
        $this->assertSame(200.0, round(falcon_cart_discount_total(), 2));

        Coupon::where('code', 'SAVE10')->update(['is_active' => false]);
        $this->apply('SAVE10');

        $this->assertSame([], $this->appliedCodes());
        $this->assertSame(0.0, round(falcon_cart_discount_total(), 2));
    }

    public function test_the_same_coupon_cannot_be_applied_twice(): void
    {
        $this->makeCoupon();
        $this->apply('SAVE10');

        $this->assertStringContainsString('already applied', $this->apply('save10'));
        $this->assertSame(['SAVE10'], $this->appliedCodes());
    }

    public function test_coupons_can_be_switched_off_shop_wide(): void
    {
        $this->makeCoupon();
        $this->apply('SAVE10');
        $this->assertSame(['SAVE10'], $this->appliedCodes());

        $this->setCmsOptions(['shop_enable_coupons' => '0']);
        $this->apply('SAVE10');

        $this->assertSame([], $this->appliedCodes(), 'existing coupons are dropped too');
    }

    // ---- usage limits ----------------------------------------------------------

    public function test_a_coupon_at_its_global_limit_is_refused(): void
    {
        $this->makeCoupon(['total_usage_limit' => 5, 'usage_count' => 5]);

        $this->assertStringContainsString('total usage limit', $this->apply('SAVE10'));
        $this->assertSame([], $this->appliedCodes());
    }

    public function test_redeem_refuses_to_go_past_the_global_limit(): void
    {
        $coupon = $this->makeCoupon(['total_usage_limit' => 2, 'usage_count' => 0]);

        $this->assertTrue($coupon->redeem());
        $this->assertTrue($coupon->redeem());
        $this->assertFalse($coupon->redeem(), 'the third redemption is refused');

        $this->assertSame(2, (int) DB::table('shop_coupons')->where('code', 'SAVE10')->value('usage_count'));
    }

    public function test_an_unlimited_coupon_always_redeems(): void
    {
        $coupon = $this->makeCoupon(['total_usage_limit' => null]);

        $this->assertTrue($coupon->redeem());
        $this->assertTrue($coupon->redeem());
        $this->assertSame(2, (int) DB::table('shop_coupons')->where('code', 'SAVE10')->value('usage_count'));
    }

    /**
     * The guest branch of the per-customer limit read a session key that nothing ever
     * wrote, so "one per customer" was enforced for signed-in shoppers only.
     */
    public function test_the_per_customer_limit_applies_to_guests(): void
    {
        $this->makeCoupon(['usage_limit' => 1]);

        $this->apply('SAVE10');
        $this->assertSame(['SAVE10'], $this->appliedCodes(), 'first use is fine');

        // Stand in for a completed guest checkout.
        session()->put('falcon_used_coupons', ['SAVE10' => 1]);
        session()->forget('falcon_coupons');

        $this->assertStringContainsString('Usage limit', $this->apply('SAVE10'));
        $this->assertSame([], $this->appliedCodes());
    }

    // ---- stacking --------------------------------------------------------------

    public function test_two_coupons_stack_sequentially(): void
    {
        $this->makeCoupon(['code' => 'TEN', 'type' => 'percent', 'amount' => 10]);
        $this->makeCoupon(['code' => 'FIVE', 'type' => 'percent', 'amount' => 5]);

        $this->apply('TEN');
        $this->apply('FIVE');

        // 10% of 2000 = 200, then 5% of the remaining 1800 = 90.
        $this->assertSame(290.0, round(falcon_cart_discount_total(), 2));
    }

    public function test_stacking_can_be_switched_off(): void
    {
        $this->setCmsOptions(['shop_coupon_stacking_policy' => '0']);
        $this->makeCoupon(['code' => 'TEN', 'type' => 'percent', 'amount' => 10]);
        $this->makeCoupon(['code' => 'FIVE', 'type' => 'percent', 'amount' => 5]);

        $this->apply('TEN');
        $this->assertStringContainsString('Multiple coupons', $this->apply('FIVE'));

        $this->assertSame(['TEN'], $this->appliedCodes());
    }

    // ---- product and category restrictions -------------------------------------

    public function test_a_coupon_restricted_to_another_product_is_refused(): void
    {
        $other = $this->makeProduct(['price' => 500]);
        $this->makeCoupon(['code' => 'OTHER', 'products' => [$other->id]]);

        $this->assertStringContainsString('not valid', $this->apply('OTHER'));
        $this->assertSame([], $this->appliedCodes());
    }

    public function test_a_restricted_coupon_only_discounts_the_products_it_covers(): void
    {
        $covered = $this->product;
        $uncovered = $this->makeProduct(['price' => 500]);

        session()->put('falcon_cart', [
            'a' => ['id' => $covered->id, 'quantity' => 1, 'price' => 1000, 'sale_price' => null, 'variation_id' => null],
            'b' => ['id' => $uncovered->id, 'quantity' => 1, 'price' => 500, 'sale_price' => null, 'variation_id' => null],
        ]);

        $this->makeCoupon(['code' => 'HALF', 'type' => 'percent', 'amount' => 50, 'products' => [$covered->id]]);
        $this->apply('HALF');

        $this->assertSame(500.0, round(falcon_cart_discount_total(), 2), '50% of 1000, not of 1500');
    }

    public function test_a_category_restricted_coupon_covers_everything_in_that_category(): void
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Shoes', 'slug' => 'shoes', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_category_post')->insert([
            'post_id' => $this->product->id, 'product_category_id' => $categoryId,
        ]);

        $this->makeCoupon(['code' => 'SHOES', 'type' => 'percent', 'amount' => 10, 'categories' => [$categoryId]]);
        $this->apply('SHOES');

        $this->assertSame(200.0, round(falcon_cart_discount_total(), 2));
    }

    public function test_a_coupon_falls_off_when_its_product_leaves_the_cart(): void
    {
        $covered = $this->product;
        $other = $this->makeProduct(['price' => 500]);

        $this->makeCoupon(['code' => 'ONLYTHIS', 'products' => [$covered->id]]);
        $this->apply('ONLYTHIS');
        $this->assertSame(['ONLYTHIS'], $this->appliedCodes());

        $this->fillCart(1, $other);
        $this->apply('ONLYTHIS');

        $this->assertSame([], $this->appliedCodes());
    }

    // ---- interaction with the rest of the total --------------------------------

    public function test_the_discount_comes_off_the_total(): void
    {
        $this->makeCoupon(['type' => 'percent', 'amount' => 10]);
        $this->apply('SAVE10');

        $this->assertSame(2000.0, round(get_falcon_cart_subtotal(), 2));
        $this->assertSame(1800.0, round(get_falcon_cart_total(), 2));
    }

    /** A discount reduces what is paid, so it has to reduce what is taxed. */
    public function test_a_discount_reduces_the_taxable_base(): void
    {
        $this->setCmsOptions([
            'shop_calc_taxes' => '1',
            'shop_tax_price_entry' => 'exclusive',
            'shop_country_state' => 'Bangladesh',
            'shop_tax_rates' => json_encode([['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT']]),
        ]);
        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->makeCoupon(['type' => 'percent', 'amount' => 10]);
        $this->apply('SAVE10');

        // 2000 - 200 discount = 1800 taxable, 10% = 180.
        $this->assertSame(180.0, round(get_falcon_cart_tax(), 2));
        $this->assertSame(1980.0, round(get_falcon_cart_total(), 2), '2000 - 200 + 180');
    }
}
