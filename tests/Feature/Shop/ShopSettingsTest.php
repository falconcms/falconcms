<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use App\Models\User;
use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Saving the shop settings.
 *
 * This is where every number the rest of the shop trusts comes from — tax rates, shipping
 * zones, coupons, the switches that turn tax and coupons on at all. A value that lands
 * here wrong does not throw; it quietly changes what customers are charged, which is why
 * it is worth pinning even though the screen is admin-only.
 *
 * Two things get particular attention. Unchecked checkboxes, because a browser posts
 * nothing for them and "nothing" has to be read as off rather than as no change — the
 * tax switch was once saved twice on one form and the second write undid the first. And
 * coupons, because they are the one part of this screen that is sanitised rather than
 * stored as-is: an amount over 100% or an unknown discount type would price wrongly in
 * the cart rather than being rejected there.
 */
class ShopSettingsTest extends TestCase
{
    use MakesShopFixtures;

    private function admin(): User
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** @param array<string, mixed> $input */
    private function save(array $input): TestResponse
    {
        $this->withProLicensed();

        return $this->actingAs($this->admin())->post('/admin/shop/settings', $input);
    }

    private function option(string $key): mixed
    {
        forget_cms_options_cache();

        return DB::table('cms_settings')->where('key', $key)->value('value');
    }

    // ---- switches --------------------------------------------------------------

    /**
     * A browser posts nothing at all for an unchecked box, so the save has to write 0 from
     * its absence. Reading "not present" as "leave it alone" is how a switch becomes
     * impossible to turn off.
     */
    public function test_an_unchecked_switch_saves_as_off(): void
    {
        $this->save(['enable_coupons' => '1']);
        $this->assertSame('1', $this->option('shop_enable_coupons'));

        $this->save([]); // the box was unticked, so the browser sends nothing
        $this->assertSame('0', $this->option('shop_enable_coupons'), 'the switch could not be turned off');
    }

    public function test_every_switch_on_the_screen_behaves_the_same_way(): void
    {
        $switches = [
            'enable_coupons' => 'shop_enable_coupons',
            'multi_coupon_policy' => 'shop_coupon_stacking_policy',
            'enable_guest_checkout' => 'shop_enable_guest_checkout',
            'force_login_checkout' => 'shop_force_login_checkout',
        ];

        $this->save(array_fill_keys(array_keys($switches), '1'));
        foreach ($switches as $option) {
            $this->assertSame('1', $this->option($option), $option);
        }

        $this->save([]);
        foreach ($switches as $option) {
            $this->assertSame('0', $this->option($option), $option.' stayed on');
        }
    }

    /**
     * A global value has to win over any per-locale copy, otherwise turning something off
     * on the main screen leaves a translated override still switched on underneath.
     */
    public function test_saving_clears_the_per_locale_overrides(): void
    {
        DB::table('cms_settings')->insert([
            'key' => 'shop_enable_coupons_bn', 'value' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->save([]);

        $this->assertNull($this->option('shop_enable_coupons_bn'), 'a locale override survived');
    }

    // ---- ordinary settings -----------------------------------------------------

    public function test_a_plain_setting_is_stored_under_its_shop_prefix(): void
    {
        $this->save(['currency' => 'BDT', 'country_state' => 'Bangladesh']);

        $this->assertSame('BDT', $this->option('shop_currency'));
        $this->assertSame('Bangladesh', $this->option('shop_country_state'));
    }

    public function test_the_settings_the_shop_reads_survive_a_round_trip(): void
    {
        $rates = [['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT', 'shipping' => '0']];

        $this->save([
            'calc_taxes' => '1',
            'tax_rates' => $rates,
            'tax_price_entry' => 'exclusive',
        ]);

        $this->assertTrue(falcon_tax_enabled());
        $this->assertSame(10.0, falcon_tax_rate_for('Bangladesh')['rate'],
            'the rate the cart reads must be the rate that was saved');
    }

    /** The licence state is not a shop setting and must not be reachable from this form. */
    public function test_a_protected_option_cannot_be_written_through_this_screen(): void
    {
        $this->save(['falcon_license_state' => 'forged']);

        $this->assertNotSame('forged', $this->option('shop_falcon_license_state'));
        $this->assertNotSame('forged', $this->option('falcon_license_state'));
    }

    // ---- coupons ---------------------------------------------------------------

    /** @param array<int, array<string, mixed>> $coupons */
    private function saveCoupons(array $coupons): TestResponse
    {
        return $this->save(['coupons_submitted' => '1', 'coupons' => $coupons]);
    }

    public function test_a_coupon_is_created_from_the_form(): void
    {
        $this->saveCoupons([
            ['code' => 'save10', 'type' => 'percent', 'amount' => '10', 'min_spend' => '500'],
        ]);

        $coupon = Coupon::first();

        $this->assertNotNull($coupon);
        $this->assertSame('SAVE10', $coupon->code, 'codes are stored upper-case');
        $this->assertSame(10.0, (float) $coupon->amount);
        $this->assertSame(500.0, (float) $coupon->min_spend);
    }

    public function test_a_percentage_over_one_hundred_is_capped(): void
    {
        $this->saveCoupons([['code' => 'TOOMUCH', 'type' => 'percent', 'amount' => '250']]);

        $this->assertSame(100.0, (float) Coupon::first()->amount,
            'a discount larger than the cart would have been stored');
    }

    public function test_a_negative_amount_becomes_zero(): void
    {
        $this->saveCoupons([['code' => 'NEG', 'type' => 'fixed_cart', 'amount' => '-50']]);

        $this->assertSame(0.0, (float) Coupon::first()->amount,
            'a negative discount would add money to an order');
    }

    /** An unknown type would be priced as a percentage by the cart. */
    public function test_an_unknown_discount_type_falls_back_to_a_safe_one(): void
    {
        $this->saveCoupons([['code' => 'WEIRD', 'type' => 'made_up', 'amount' => '20']]);

        $this->assertSame('fixed_cart', Coupon::first()->type);
    }

    public function test_a_free_shipping_coupon_carries_no_money_value(): void
    {
        $this->saveCoupons([['code' => 'FREESHIP', 'type' => 'free_shipping', 'amount' => '999']]);

        $coupon = Coupon::first();

        $this->assertSame('free_shipping', $coupon->type);
        $this->assertSame(0.0, (float) $coupon->amount);
    }

    public function test_blank_rows_and_duplicate_codes_are_dropped(): void
    {
        $this->saveCoupons([
            ['code' => '', 'type' => 'percent', 'amount' => '10'],
            ['code' => 'DUPE', 'type' => 'percent', 'amount' => '10'],
            ['code' => 'dupe', 'type' => 'percent', 'amount' => '90'],
            'not-an-array',
        ]);

        $this->assertSame(1, Coupon::count());
        $this->assertSame(10.0, (float) Coupon::first()->amount, 'the first row wins, not the last');
    }

    /**
     * Editing a coupon must not reset how many times it has been redeemed — that number is
     * what enforces its usage cap.
     */
    public function test_editing_a_coupon_keeps_its_redemption_count(): void
    {
        $this->saveCoupons([['code' => 'KEEP', 'type' => 'percent', 'amount' => '10']]);
        Coupon::where('code', 'KEEP')->update(['usage_count' => 7]);

        $this->saveCoupons([['code' => 'KEEP', 'type' => 'percent', 'amount' => '15']]);

        $coupon = Coupon::where('code', 'KEEP')->first();

        $this->assertSame(15.0, (float) $coupon->amount, 'the edit did not take');
        $this->assertSame(7, (int) $coupon->usage_count, 'the redemption count was reset');
    }

    public function test_a_coupon_left_off_the_form_is_deleted(): void
    {
        $this->saveCoupons([
            ['code' => 'ONE', 'type' => 'percent', 'amount' => '10'],
            ['code' => 'TWO', 'type' => 'percent', 'amount' => '20'],
        ]);
        $this->assertSame(2, Coupon::count());

        $this->saveCoupons([['code' => 'ONE', 'type' => 'percent', 'amount' => '10']]);

        $this->assertSame(['ONE'], Coupon::pluck('code')->all());
    }

    /**
     * Deleting the last coupon posts no coupon inputs at all. That has to mean "none left"
     * rather than "no change", which is what the hidden marker is for.
     */
    public function test_deleting_the_last_coupon_works(): void
    {
        $this->saveCoupons([['code' => 'ONLY', 'type' => 'percent', 'amount' => '10']]);
        $this->assertSame(1, Coupon::count());

        $this->save(['coupons_submitted' => '1']);

        $this->assertSame(0, Coupon::count(), 'the last coupon could not be removed');
    }

    /** A save from a different tab carries no coupon fields and must leave them alone. */
    public function test_a_save_from_another_tab_does_not_touch_the_coupons(): void
    {
        $this->saveCoupons([['code' => 'SAFE', 'type' => 'percent', 'amount' => '10']]);

        $this->save(['currency' => 'BDT']); // no coupons_submitted marker

        $this->assertSame(1, Coupon::count(), 'saving another tab wiped the coupons');
    }

    /** Coupons live in their own table; no blob of them may be written back to settings. */
    public function test_coupons_are_not_also_written_into_the_settings_table(): void
    {
        $this->saveCoupons([['code' => 'NOBLOB', 'type' => 'percent', 'amount' => '10']]);

        $this->assertNull($this->option('shop_coupons'));
        $this->assertNull($this->option('shop_coupons_submitted'));
    }
}
