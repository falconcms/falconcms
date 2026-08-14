<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

/**
 * Reserving stock at checkout.
 *
 * Two people can reach "Place order" on the last unit at the same instant. Read the
 * quantity, decide it is enough, then write the new one, and both of them get it — the
 * shop is now oversold and someone is getting a refund and an apology. claimCartStock()
 * avoids that by never reading first: it is a single conditional UPDATE whose WHERE
 * clause carries the check, so exactly one of the two can affect a row.
 *
 * The other half is the rollback. A cart with three lines can claim two and fail on the
 * third, and the two already taken have to go back on the shelf before the customer is
 * told no.
 */
class StockClaimTest extends TestCase
{
    use MakesShopFixtures;

    /**
     * @param  array<int, array<string, mixed>>  $cart
     * @return array{0: bool, 1: string, 2: array<int, array<string, mixed>>}
     */
    private function claim(array $cart): array
    {
        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('claimCartStock');
        $method->setAccessible(true);

        return $method->invoke($controller, $cart);
    }

    /** @param array<int, array<string, mixed>> $claims */
    private function release(array $claims): void
    {
        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('releaseClaimedStock');
        $method->setAccessible(true);

        $method->invoke($controller, $claims);
    }

    /** @return array<string, mixed> */
    private function line(Post $product, int $quantity, ?int $variationId = null): array
    {
        return [
            'id' => $product->id, 'name' => $product->title, 'quantity' => $quantity,
            'variation_id' => $variationId, 'price' => 100, 'sale_price' => null,
        ];
    }

    private function stockOf(Post $product): int
    {
        return (int) DB::table('shop_products')->where('post_id', $product->id)->value('stock_quantity');
    }

    // ---- the basic claim -------------------------------------------------------

    public function test_a_claim_takes_the_quantity_off_the_shelf(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 10]);

        [$ok, $message, $claims] = $this->claim([$this->line($product, 3)]);

        $this->assertTrue($ok, $message);
        $this->assertSame(7, $this->stockOf($product));
        $this->assertCount(1, $claims);
    }

    public function test_claiming_exactly_what_is_left_succeeds(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 3]);

        [$ok] = $this->claim([$this->line($product, 3)]);

        $this->assertTrue($ok);
        $this->assertSame(0, $this->stockOf($product));
    }

    public function test_claiming_more_than_is_left_fails_and_changes_nothing(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 2]);

        [$ok, $message] = $this->claim([$this->line($product, 3)]);

        $this->assertFalse($ok);
        $this->assertStringContainsString('no longer available', $message);
        $this->assertSame(2, $this->stockOf($product), 'the shelf is untouched');
    }

    public function test_a_product_that_does_not_track_stock_is_never_reserved(): void
    {
        $product = $this->makeProduct(['manage_stock' => 0, 'stock_quantity' => 0]);

        [$ok, , $claims] = $this->claim([$this->line($product, 99)]);

        $this->assertTrue($ok);
        $this->assertSame([], $claims, 'nothing to reserve');
    }

    public function test_backorders_let_the_shelf_go_negative(): void
    {
        $product = $this->makeProduct([
            'manage_stock' => 1, 'stock_quantity' => 1, 'backorders' => 'yes',
        ]);

        [$ok] = $this->claim([$this->line($product, 5)]);

        $this->assertTrue($ok);
        $this->assertSame(-4, $this->stockOf($product), 'four are owed to the customer');
    }

    // ---- the race --------------------------------------------------------------

    /**
     * The whole reason this code exists. Both checkouts see one unit; only one may have it.
     */
    public function test_only_one_of_two_checkouts_can_take_the_last_unit(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 1]);

        [$firstOk] = $this->claim([$this->line($product, 1)]);
        [$secondOk, $message] = $this->claim([$this->line($product, 1)]);

        $this->assertTrue($firstOk);
        $this->assertFalse($secondOk, 'the shop was oversold');
        $this->assertStringContainsString('no longer available', $message);
        $this->assertSame(0, $this->stockOf($product));
    }

    /**
     * Ten simultaneous attempts at three units: exactly three win, and the shelf lands on
     * zero rather than somewhere negative.
     */
    public function test_a_run_on_limited_stock_never_oversells(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 3]);

        $won = 0;
        for ($i = 0; $i < 10; $i++) {
            [$ok] = $this->claim([$this->line($product, 1)]);
            $won += $ok ? 1 : 0;
        }

        $this->assertSame(3, $won);
        $this->assertSame(0, $this->stockOf($product));
    }

    // ---- rollback --------------------------------------------------------------

    /**
     * A partly-claimed cart must not leave stock stranded: the customer is not getting the
     * order, so nothing of theirs may stay off the shelf.
     */
    public function test_a_failed_line_puts_back_everything_claimed_before_it(): void
    {
        $plenty = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 10]);
        $scarce = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 1]);

        [$ok, , $claims] = $this->claim([
            $this->line($plenty, 2),
            $this->line($scarce, 5),
        ]);

        $this->assertFalse($ok);
        $this->assertSame([], $claims);
        $this->assertSame(10, $this->stockOf($plenty), 'the first line was returned to the shelf');
        $this->assertSame(1, $this->stockOf($scarce));
    }

    public function test_releasing_a_claim_restores_the_exact_quantity(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 10]);

        [$ok, , $claims] = $this->claim([$this->line($product, 4)]);
        $this->assertTrue($ok);
        $this->assertSame(6, $this->stockOf($product));

        $this->release($claims);

        $this->assertSame(10, $this->stockOf($product));
    }

    // ---- variations ------------------------------------------------------------

    public function test_a_variation_that_tracks_its_own_stock_is_reserved_from_the_variation(): void
    {
        $product = $this->makeVariableProduct([], ['manage_stock' => 1, 'stock_quantity' => 10]);
        $variation = $this->makeVariation($product, ['manage_stock' => 1, 'stock_quantity' => 4]);

        [$ok] = $this->claim([$this->line($product, 3, $variation->id)]);

        $this->assertTrue($ok);
        $this->assertSame(1, (int) DB::table('shop_product_variations')
            ->where('id', $variation->id)->value('stock_quantity'));
        $this->assertSame(10, $this->stockOf($product), 'the parent shelf is untouched');
    }

    public function test_a_variation_beyond_its_stock_is_refused(): void
    {
        $product = $this->makeVariableProduct([], ['manage_stock' => 0]);
        $variation = $this->makeVariation($product, ['manage_stock' => 1, 'stock_quantity' => 2]);

        [$ok] = $this->claim([$this->line($product, 3, $variation->id)]);

        $this->assertFalse($ok);
        $this->assertSame(2, (int) DB::table('shop_product_variations')
            ->where('id', $variation->id)->value('stock_quantity'));
    }

    public function test_a_variation_that_defers_to_the_parent_draws_from_the_parent(): void
    {
        $product = $this->makeVariableProduct([], ['manage_stock' => 1, 'stock_quantity' => 5]);
        $variation = $this->makeVariation($product, ['manage_stock' => 0]);

        [$ok] = $this->claim([$this->line($product, 2, $variation->id)]);

        $this->assertTrue($ok);
        $this->assertSame(3, $this->stockOf($product));
    }

    // ---- odd input -------------------------------------------------------------

    public function test_a_zero_or_negative_quantity_claims_nothing(): void
    {
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 5]);

        [$ok, , $claims] = $this->claim([
            $this->line($product, 0),
            $this->line($product, -3),
        ]);

        $this->assertTrue($ok);
        $this->assertSame([], $claims);
        $this->assertSame(5, $this->stockOf($product), 'a negative quantity must not add stock');
    }

    public function test_a_line_for_a_product_that_no_longer_exists_is_skipped(): void
    {
        [$ok, , $claims] = $this->claim([
            ['id' => 999999, 'name' => 'Gone', 'quantity' => 1, 'variation_id' => null, 'price' => 1],
        ]);

        $this->assertTrue($ok);
        $this->assertSame([], $claims);
    }

    public function test_an_empty_cart_claims_nothing(): void
    {
        [$ok, , $claims] = $this->claim([]);

        $this->assertTrue($ok);
        $this->assertSame([], $claims);
    }
}
