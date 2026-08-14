<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Tax.
 *
 * Two settings decide everything and they are easy to confuse: how prices were *entered*
 * (shop_tax_price_entry) and how they should be *displayed* (shop_tax_display_shop). When
 * those two disagree the catalogue price has to be converted, and when they agree it must
 * be left alone — getting that backwards is how the same product came to show two
 * different prices on two different pages.
 *
 * The other thing pinned here is that the catalogue deliberately uses the shop's own
 * country rather than the visitor's. Catalogue pages are cached on the URL alone, so a
 * rate taken from one visitor's session would be baked into the page served to everybody
 * else. The real rate is applied at checkout, where the tax line spells it out.
 */
class TaxTest extends TestCase
{
    use MakesShopFixtures;

    /** @param array<int, array<string, mixed>> $rates */
    private function configureTax(array $overrides = [], ?array $rates = null): void
    {
        $rates ??= [['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT', 'shipping' => '0']];

        $this->setCmsOptions(array_merge([
            'shop_calc_taxes' => '1',
            'shop_tax_price_entry' => 'exclusive',
            'shop_tax_display_shop' => 'exclusive',
            'shop_tax_calculation_basis' => 'shipping',
            'shop_country_state' => 'Bangladesh',
            'shop_tax_rates' => json_encode($rates),
        ], $overrides));

        // Destination-based tax needs a destination. Unless a test says otherwise the
        // shopper is shipping to the shop's own country; the "no address yet" case is
        // pinned separately below.
        if (!session()->has('falcon_shipping_country')) {
            session()->put('falcon_shipping_country', 'Bangladesh');
        }
    }

    private function cartOf(Post $product, float $price, int $quantity = 1): void
    {
        session()->put('falcon_cart', ['k' => [
            'id' => $product->id, 'name' => 'x', 'quantity' => $quantity,
            'variation_id' => null, 'price' => $price, 'sale_price' => null,
        ]]);
    }

    // ---- the master switch -----------------------------------------------------

    public function test_tax_is_off_until_the_setting_is_exactly_one(): void
    {
        $this->configureTax(['shop_calc_taxes' => '0']);
        $this->assertFalse(falcon_tax_enabled());

        $this->setCmsOptions(['shop_calc_taxes' => 'yes']);
        $this->assertFalse(falcon_tax_enabled(), 'anything other than "1" leaves tax off');

        $this->setCmsOptions(['shop_calc_taxes' => '1']);
        $this->assertTrue(falcon_tax_enabled());
    }

    public function test_with_tax_off_nothing_is_charged_and_nothing_is_converted(): void
    {
        $this->configureTax(['shop_calc_taxes' => '0']);
        $product = $this->makeProduct(['price' => 1000]);
        $this->cartOf($product, 1000);

        $this->assertSame(0.0, (float) get_falcon_cart_tax());
        $this->assertSame(1000.0, falcon_display_price(1000, $product->id));
        $this->assertNull(falcon_tax_rate_for('Bangladesh'));
    }

    // ---- picking a rate --------------------------------------------------------

    public function test_an_exact_country_match_wins(): void
    {
        $this->configureTax([], [
            ['country' => '*', 'rate' => 5, 'name' => 'Global'],
            ['country' => 'Bangladesh', 'rate' => 15, 'name' => 'VAT'],
        ]);

        $rate = falcon_tax_rate_for('Bangladesh');

        $this->assertSame(15.0, $rate['rate']);
        $this->assertSame('VAT', $rate['name']);
    }

    public function test_a_country_with_a_state_falls_back_to_the_country_row(): void
    {
        $this->configureTax([], [['country' => 'Bangladesh', 'rate' => 15, 'name' => 'VAT']]);

        $this->assertSame(15.0, falcon_tax_rate_for('Bangladesh - Dhaka')['rate']);
    }

    public function test_a_state_specific_row_beats_the_country_row(): void
    {
        $this->configureTax([], [
            ['country' => 'Bangladesh', 'rate' => 15, 'name' => 'VAT'],
            ['country' => 'Bangladesh - Dhaka', 'rate' => 20, 'name' => 'Dhaka VAT'],
        ]);

        $this->assertSame(20.0, falcon_tax_rate_for('Bangladesh - Dhaka')['rate']);
    }

    public function test_the_wildcard_row_catches_everything_else(): void
    {
        $this->configureTax([], [
            ['country' => 'Bangladesh', 'rate' => 15, 'name' => 'VAT'],
            ['country' => '*', 'rate' => 5, 'name' => 'Global'],
        ]);

        $this->assertSame(5.0, falcon_tax_rate_for('Narnia')['rate']);
    }

    public function test_no_matching_row_means_no_tax(): void
    {
        $this->configureTax([], [['country' => 'Bangladesh', 'rate' => 15, 'name' => 'VAT']]);

        $this->assertNull(falcon_tax_rate_for('Narnia'));
    }

    /** Shop owners paste country names with en/em dashes; the matcher normalises them. */
    public function test_country_matching_ignores_case_padding_and_dash_style(): void
    {
        $this->configureTax([], [['country' => 'Bangladesh – Dhaka', 'rate' => 12, 'name' => 'VAT']]);

        $this->assertSame(12.0, falcon_tax_rate_for('  BANGLADESH - dhaka  ')['rate']);
    }

    public function test_malformed_rate_rows_are_skipped(): void
    {
        $this->configureTax([], ['garbage', ['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT']]);

        $this->assertSame(10.0, falcon_tax_rate_for('Bangladesh')['rate']);
    }

    // ---- catalogue display -----------------------------------------------------

    public function test_entered_and_displayed_the_same_way_leaves_the_price_untouched(): void
    {
        foreach (['exclusive', 'inclusive'] as $mode) {
            $this->configureTax([
                'shop_tax_price_entry' => $mode,
                'shop_tax_display_shop' => $mode,
            ]);
            $product = $this->makeProduct(['price' => 1000]);

            $this->assertSame(1000.0, falcon_display_price(1000, $product->id), "both {$mode}");
        }
    }

    public function test_prices_entered_without_tax_get_it_added_when_shown_with_tax(): void
    {
        $this->configureTax([
            'shop_tax_price_entry' => 'exclusive',
            'shop_tax_display_shop' => 'inclusive',
        ]);
        $product = $this->makeProduct(['price' => 1000]);

        $this->assertSame(1100.0, round(falcon_display_price(1000, $product->id), 2));
    }

    public function test_prices_entered_with_tax_get_it_stripped_when_shown_without(): void
    {
        $this->configureTax([
            'shop_tax_price_entry' => 'inclusive',
            'shop_tax_display_shop' => 'exclusive',
        ]);
        $product = $this->makeProduct(['price' => 1100]);

        $this->assertSame(1000.0, round(falcon_display_price(1100, $product->id), 2));
    }

    public function test_a_non_taxable_product_is_never_converted(): void
    {
        $this->configureTax([
            'shop_tax_price_entry' => 'exclusive',
            'shop_tax_display_shop' => 'inclusive',
        ]);
        $exempt = $this->makeProduct(['price' => 1000, 'tax_status' => 'none']);

        $this->assertSame(1000.0, falcon_display_price(1000, $exempt->id));
    }

    /**
     * The catalogue is cached per URL, so it must not vary by who is looking. A shopper
     * shipping somewhere with a different rate still sees the shop's base figure.
     */
    public function test_the_catalogue_price_uses_the_shops_country_not_the_visitors(): void
    {
        $this->configureTax([
            'shop_country_state' => 'Bangladesh',
            'shop_tax_price_entry' => 'exclusive',
            'shop_tax_display_shop' => 'inclusive',
        ], [
            ['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT'],
            ['country' => 'Narnia', 'rate' => 90, 'name' => 'Narnia tax'],
        ]);
        $product = $this->makeProduct(['price' => 1000]);

        session()->put('falcon_shipping_country', 'Narnia');

        $this->assertSame(1100.0, round(falcon_display_price(1000, $product->id), 2),
            'the visitor shipping to Narnia must not change the shared catalogue price');
    }

    public function test_a_free_product_is_left_alone(): void
    {
        $this->configureTax(['shop_tax_display_shop' => 'inclusive']);

        $this->assertSame(0.0, falcon_display_price(0));
    }

    // ---- what the cart charges -------------------------------------------------

    public function test_exclusive_pricing_adds_tax_on_top(): void
    {
        $this->configureTax(['shop_tax_price_entry' => 'exclusive']);
        $product = $this->makeProduct(['price' => 1000]);
        $this->cartOf($product, 1000, 2);

        $this->assertSame(200.0, round(get_falcon_cart_tax(), 2), '10% of 2000');
        $this->assertSame(2200.0, round(get_falcon_cart_total(), 2));
    }

    /** Inclusive tax is already inside the price; adding it to the total would double-charge. */
    public function test_inclusive_pricing_reports_tax_without_adding_it_to_the_total(): void
    {
        $this->configureTax(['shop_tax_price_entry' => 'inclusive']);
        $product = $this->makeProduct(['price' => 1100]);
        $this->cartOf($product, 1100);

        $this->assertSame(100.0, round(get_falcon_cart_tax(), 2), '1100 - 1100/1.1');
        $this->assertSame(1100.0, round(get_falcon_cart_total(), 2), 'the customer pays the marked price');
    }

    public function test_a_non_taxable_line_is_excluded_from_the_taxable_base(): void
    {
        $this->configureTax();
        $taxable = $this->makeProduct(['price' => 1000]);
        $exempt = $this->makeProduct(['price' => 500, 'tax_status' => 'none']);

        session()->put('falcon_cart', [
            'a' => ['id' => $taxable->id, 'quantity' => 1, 'price' => 1000, 'sale_price' => null, 'variation_id' => null],
            'b' => ['id' => $exempt->id, 'quantity' => 1, 'price' => 500, 'sale_price' => null, 'variation_id' => null],
        ]);

        $this->assertSame(1500.0, round(get_falcon_cart_subtotal(), 2));
        $this->assertSame(1000.0, round(falcon_cart_taxable_subtotal(), 2), 'only the taxable line');
        $this->assertSame(100.0, round(get_falcon_cart_tax(), 2), '10% of 1000, not of 1500');
    }

    public function test_the_taxable_base_follows_the_sale_price(): void
    {
        $this->configureTax();
        $product = $this->makeProduct(['price' => 1000, 'sale_price' => 600]);

        session()->put('falcon_cart', ['k' => [
            'id' => $product->id, 'quantity' => 1, 'price' => 1000, 'sale_price' => 600, 'variation_id' => null,
        ]]);

        $this->assertSame(600.0, round(falcon_cart_taxable_subtotal(), 2));
        $this->assertSame(60.0, round(get_falcon_cart_tax(), 2));
    }

    public function test_shipping_is_taxed_only_when_the_rate_says_so(): void
    {
        $this->configureTax([
            'shop_shipping_zones' => json_encode([[
                'name' => 'Zone', 'countries' => ['Bangladesh'], 'cost' => 100,
                'free_threshold' => 0, 'type' => 'order', 'rules' => [],
            ]]),
        ], [['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT', 'shipping' => '0']]);

        $product = $this->makeProduct(['price' => 1000]);
        $this->cartOf($product, 1000);
        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->assertSame(100.0, round(get_falcon_cart_tax(), 2), 'goods only');

        $this->setCmsOptions(['shop_tax_rates' => json_encode([
            ['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT', 'shipping' => '1'],
        ])]);

        $this->assertSame(110.0, round(get_falcon_cart_tax(), 2), 'goods plus the 100 shipping');
    }

    /**
     * Destination-based tax cannot be worked out before there is a destination. The cart
     * shows no tax line rather than guessing — the shop can opt into a default location
     * (shop_default_customer_location) if it would rather show one.
     */
    public function test_no_destination_yet_means_no_tax_line(): void
    {
        session()->forget('falcon_shipping_country');
        $this->configureTax(['shop_default_customer_location' => 'none']);
        session()->forget('falcon_shipping_country');
        session()->forget('falcon_default_country_resolved');

        $product = $this->makeProduct(['price' => 1000]);
        $this->cartOf($product, 1000);

        $this->assertNull(falcon_tax_country());
        $this->assertSame(0.0, round(get_falcon_cart_tax(), 2));
        $this->assertSame(1000.0, round(get_falcon_cart_total(), 2));
    }

    public function test_the_shop_can_default_the_customer_to_its_own_country(): void
    {
        session()->forget('falcon_shipping_country');
        $this->configureTax(['shop_default_customer_location' => 'base']);
        session()->forget('falcon_shipping_country');
        session()->forget('falcon_default_country_resolved');

        $product = $this->makeProduct(['price' => 1000]);
        $this->cartOf($product, 1000);

        $this->assertSame('Bangladesh', falcon_tax_country());
        $this->assertSame(100.0, round(get_falcon_cart_tax(), 2));
    }

    public function test_the_tax_label_comes_from_the_matched_rate(): void
    {
        $this->configureTax([], [['country' => 'Bangladesh', 'rate' => 10, 'name' => 'VAT 10%']]);
        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->assertSame('VAT 10%', falcon_cart_tax_label());
    }

    public function test_an_unnamed_rate_still_gets_a_label(): void
    {
        $this->configureTax([], [['country' => 'Bangladesh', 'rate' => 10, 'name' => '']]);
        session()->put('falcon_shipping_country', 'Bangladesh');

        $this->assertSame('Tax', falcon_cart_tax_label());
    }

    // ---- which address the rate is taken from ----------------------------------

    public function test_the_calculation_basis_chooses_the_country(): void
    {
        $rates = [
            ['country' => 'Bangladesh', 'rate' => 10, 'name' => 'Base'],
            ['country' => 'Narnia', 'rate' => 20, 'name' => 'Shipping'],
            ['country' => 'Atlantis', 'rate' => 30, 'name' => 'Billing'],
        ];

        session()->put('falcon_shipping_country', 'Narnia');
        session()->put('falcon_billing_country', 'Atlantis');

        $this->configureTax(['shop_tax_calculation_basis' => 'base'], $rates);
        $this->assertSame('Bangladesh', falcon_tax_country());

        $this->configureTax(['shop_tax_calculation_basis' => 'shipping'], $rates);
        $this->assertSame('Narnia', falcon_tax_country());

        $this->configureTax(['shop_tax_calculation_basis' => 'billing'], $rates);
        $this->assertSame('Atlantis', falcon_tax_country());
    }

    /** A customer who has not typed a billing country yet must still be charged something sane. */
    public function test_billing_basis_falls_back_to_shipping_when_billing_is_unknown(): void
    {
        $this->configureTax(['shop_tax_calculation_basis' => 'billing'], [
            ['country' => 'Narnia', 'rate' => 20, 'name' => 'Shipping'],
        ]);
        session()->put('falcon_shipping_country', 'Narnia');
        session()->forget('falcon_billing_country');

        $this->assertSame('Narnia', falcon_tax_country());
    }

    /**
     * The memo behind falcon_product_tax_status() must not outlive a change to the row —
     * it is bound to the application instance precisely so a worker cannot serve a stale
     * status after the shop owner edits a product.
     */
    public function test_a_changed_tax_status_is_not_served_from_a_stale_memo(): void
    {
        $this->configureTax();
        $product = $this->makeProduct(['price' => 1000, 'tax_status' => 'taxable']);

        $this->assertSame('taxable', falcon_product_tax_status($product->id));

        DB::table('shop_products')
            ->where('post_id', $product->id)->update(['tax_status' => 'none']);

        // Same request: the memo is doing its job and the old answer stands.
        $this->assertSame('taxable', falcon_product_tax_status($product->id));

        // A fresh application instance — what the next request gets — sees the new value.
        falcon_request_memo('product_tax_statuses')->exchangeArray([]);
        $this->assertSame('none', falcon_product_tax_status($product->id));
    }
}
