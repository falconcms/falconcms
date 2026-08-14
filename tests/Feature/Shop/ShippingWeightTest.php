<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;

/**
 * Weight-based shipping: the cart's total weight picks a band, and the band sets the cost.
 *
 * The dangerous failure here is silent. A rule the shop owner typed wrong used to match
 * nothing and fall through to a cost of zero, so every order shipped free — no error
 * anywhere, just money quietly leaving. Malformed rules are now skipped so the zone's
 * base cost applies instead.
 */
class ShippingWeightTest extends TestCase
{
    use MakesShopFixtures;

    private Post $light;   // 0.5 kg

    private Post $heavy;   // 2.0 kg

    protected function setUp(): void
    {
        parent::setUp();

        $this->light = $this->makeProduct(['weight' => 0.5]);
        $this->heavy = $this->makeProduct(['weight' => 2.0]);

        $this->setCmsOptions(['shop_free_shipping_threshold' => '0']);
    }

    /** @return array<string, array<string, mixed>> */
    private function cart(int $lightQty, int $heavyQty = 0): array
    {
        return array_filter([
            'a' => $lightQty ? ['id' => $this->light->id, 'quantity' => $lightQty, 'variation_id' => null, 'price' => 10, 'sale_price' => null] : null,
            'b' => $heavyQty ? ['id' => $this->heavy->id, 'quantity' => $heavyQty, 'variation_id' => null, 'price' => 10, 'sale_price' => null] : null,
        ]);
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function useZone(string $type, array $rules, float $baseCost = 999): void
    {
        $this->setCmsOptions(['shop_shipping_zones' => json_encode([[
            'name' => 'Test Zone',
            'countries' => ['Bangladesh'],
            'cost' => $baseCost,
            'free_threshold' => 0,
            'type' => $type,
            'rules' => $rules,
        ]])]);
    }

    /** @param array<string, array<string, mixed>> $cart */
    private function costFor(array $cart): float
    {
        session()->put('falcon_cart', $cart);

        return (float) falcon_delivery_shipping_details('Bangladesh')['cost'];
    }

    // ---- weighing the cart -----------------------------------------------------

    public function test_cart_weight_adds_up_quantity_times_unit_weight(): void
    {
        $this->assertSame(0.0, falcon_cart_weight([]));
        $this->assertSame(0.5, falcon_cart_weight($this->cart(1)));
        $this->assertSame(1.5, falcon_cart_weight($this->cart(3)));
        $this->assertSame(5.5, falcon_cart_weight($this->cart(3, 2)));
    }

    public function test_cart_weight_ignores_what_it_cannot_weigh(): void
    {
        $this->assertSame(0.0, falcon_cart_weight([
            ['id' => 999999, 'quantity' => 5, 'price' => 1],
        ]), 'a product that is no longer in the catalogue');

        $this->assertSame(0.0, falcon_cart_weight([
            ['id' => $this->light->id, 'quantity' => -4, 'price' => 1],
        ]), 'a negative quantity must not subtract weight');
    }

    // ---- bands -----------------------------------------------------------------

    public function test_the_matching_weight_band_sets_the_cost(): void
    {
        $this->useZone('weight', [
            ['min' => 0, 'max' => 1, 'cost' => 50],
            ['min' => 1, 'max' => 5, 'cost' => 120],
            ['min' => 5, 'max' => '', 'cost' => 300],
        ]);

        $this->assertSame(50.0, $this->costFor($this->cart(1)), '0.5 kg');
        $this->assertSame(120.0, $this->costFor($this->cart(3)), '1.5 kg');
        $this->assertSame(300.0, $this->costFor($this->cart(3, 2)), '5.5 kg, open-ended band');
        $this->assertSame(50.0, $this->costFor($this->cart(2)), 'exactly 1.0 kg takes the first match');
    }

    /** Casting the weight to int would round 0.5 down to 0 and pick the wrong band. */
    public function test_fractional_weights_are_not_truncated(): void
    {
        $this->useZone('weight', [
            ['min' => 0, 'max' => 0.5, 'cost' => 20],
            ['min' => 0.5, 'max' => '', 'cost' => 400],
        ]);

        $this->assertSame(20.0, $this->costFor($this->cart(1)));
    }

    public function test_a_cart_outside_every_band_falls_back_to_the_zone_cost(): void
    {
        $this->useZone('weight', [['min' => 100, 'max' => 200, 'cost' => 10]], baseCost: 777);

        $this->assertSame(777.0, $this->costFor($this->cart(1)));
    }

    /** The free-shipping bug: a broken rule must not read as "cost nothing". */
    public function test_malformed_rules_are_skipped_rather_than_shipping_free(): void
    {
        $this->useZone('weight', [
            'garbage',
            ['min' => 'abc', 'max' => null, 'cost' => 'xyz'],
            ['min' => 0, 'max' => 9, 'cost' => 33],
        ], baseCost: 60);

        $this->assertSame(33.0, $this->costFor($this->cart(1)));
    }

    public function test_every_rule_being_malformed_leaves_the_base_cost(): void
    {
        $this->useZone('weight', ['garbage', ['nonsense' => true]], baseCost: 60);

        $this->assertSame(60.0, $this->costFor($this->cart(1)));
    }

    // ---- the other two modes still work ----------------------------------------

    public function test_item_count_mode_is_unaffected(): void
    {
        $this->useZone('item', [
            ['min' => 1, 'max' => 2, 'cost' => 10],
            ['min' => 3, 'max' => '', 'cost' => 25],
        ]);

        $this->assertSame(10.0, $this->costFor($this->cart(2)));
        $this->assertSame(25.0, $this->costFor($this->cart(5)));
    }

    public function test_flat_rate_mode_ignores_weight_entirely(): void
    {
        $this->useZone('order', [], baseCost: 88);

        $this->assertSame(88.0, $this->costFor($this->cart(3, 2)));
    }
}
