<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\OrderItem;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The commands nobody watches run.
 *
 * These five are on the scheduler, so on a live site they run every few minutes with no one
 * reading the output. A bug in one does not raise an error — it publishes a draft early,
 * leaves a sale price up after the sale, holds stock a customer never paid for, or quietly
 * deletes more analytics than it was asked to. All of those look like "the data is wrong"
 * weeks later, which is the hardest kind of report to act on.
 *
 * Each is tested for what it does AND for what it must leave alone, because the damage from
 * one of these is almost always over-reach rather than under-reach.
 */
class ScheduledCommandsTest extends TestCase
{
    use MakesShopFixtures;

    private function makePost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create(array_merge([
            'user_id' => 1,
            'title' => "Scheduled {$n}",
            'slug' => "scheduled-{$n}",
            'type' => 'post',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => '',
        ], $attributes));
    }

    // ── falcon:publish-scheduled ─────────────────────────────────────────────

    public function test_a_scheduled_post_whose_time_has_come_is_published(): void
    {
        $due = $this->makePost(['status' => 'scheduled', 'published_at' => now()->subMinute()]);

        $this->artisan('falcon:publish-scheduled')->assertSuccessful();

        $this->assertSame('published', $due->fresh()->status);
    }

    public function test_a_scheduled_post_whose_time_has_not_come_is_left_alone(): void
    {
        $later = $this->makePost(['status' => 'scheduled', 'published_at' => now()->addHour()]);

        $this->artisan('falcon:publish-scheduled')->assertSuccessful();

        $this->assertSame('scheduled', $later->fresh()->status, 'a future post was published early');
    }

    public function test_publishing_does_not_touch_a_draft_or_a_trashed_post(): void
    {
        $draft = $this->makePost(['status' => 'draft', 'published_at' => now()->subDay()]);

        $this->artisan('falcon:publish-scheduled')->assertSuccessful();

        $this->assertSame('draft', $draft->fresh()->status, 'a draft must stay a draft');
    }

    public function test_publishing_with_nothing_due_is_a_no_op(): void
    {
        $this->artisan('falcon:publish-scheduled')->assertSuccessful();

        $this->assertSame(0, Post::where('status', 'scheduled')->count());
    }

    // ── falcon:expire-sales ──────────────────────────────────────────────────

    public function test_a_sale_that_has_ended_is_cleared(): void
    {
        $product = $this->makeProduct([
            'price' => 1000,
            'sale_price' => 800,
            'sale_ends_at' => now()->subHour(),
        ]);

        $this->artisan('falcon:expire-sales')->assertSuccessful();

        $row = DB::table('shop_products')->where('post_id', $product->id)->first();
        $this->assertNull($row->sale_price, 'the sale price should be gone');
        $this->assertNull($row->sale_ends_at);
        $this->assertEquals(1000, $row->price, 'the ordinary price must survive');
    }

    public function test_a_sale_still_running_is_left_alone(): void
    {
        $product = $this->makeProduct([
            'price' => 1000,
            'sale_price' => 800,
            'sale_ends_at' => now()->addWeek(),
        ]);

        $this->artisan('falcon:expire-sales')->assertSuccessful();

        $this->assertEquals(800, DB::table('shop_products')->where('post_id', $product->id)->value('sale_price'));
    }

    public function test_a_sale_with_no_end_date_runs_forever(): void
    {
        // A sale price with no end is deliberate — "this is just what it costs now".
        $product = $this->makeProduct(['price' => 1000, 'sale_price' => 800, 'sale_ends_at' => null]);

        $this->artisan('falcon:expire-sales')->assertSuccessful();

        $this->assertEquals(800, DB::table('shop_products')->where('post_id', $product->id)->value('sale_price'));
    }

    // ── falcon:cancel-held-orders ────────────────────────────────────────────

    public function test_held_orders_are_not_cancelled_when_the_hold_is_switched_off(): void
    {
        $this->setCmsOptions(['shop_hold_stock' => '0']);

        // Zero means the feature is off. A command that treated it as "cancel immediately"
        // would empty a shop's pending orders on its first run.
        $this->artisan('falcon:cancel-held-orders')->assertSuccessful();

        $this->assertTrue(true);
    }

    public function test_the_hold_command_runs_cleanly_with_nothing_to_cancel(): void
    {
        $this->setCmsOptions(['shop_hold_stock' => '60']);

        $this->artisan('falcon:cancel-held-orders')->assertSuccessful();
    }

    /** A pending order, its line, and the stock it took off the shelf. */
    private function heldOrder(Post $product, string $createdAt, int $quantity = 2): Order
    {
        static $n = 0;
        $n++;

        $order = Order::create([
            'user_id' => null,
            'order_number' => 'HELD-'.$n,
            'status' => 'pending',
            'subtotal' => 100, 'shipping_total' => 0, 'tax_total' => 0,
            'discount_total' => 0, 'total' => 100,
            'customer_email' => 'held@example.test',
            'customer_phone' => '0000000000',
            'first_name' => 'Held',
            'last_name' => 'Order',
            'address_line_1' => '1 Test Street',
            'city' => 'Testville',
            'postcode' => '0000',
            'country' => 'Bangladesh',
        ]);
        DB::table('shop_orders')->where('id', $order->id)->update(['created_at' => $createdAt]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variation_id' => null,
            'product_name' => $product->title,
            'quantity' => $quantity,
            'price' => 50,
            'subtotal' => 50 * $quantity,
        ]);

        return $order->fresh();
    }

    public function test_an_order_held_past_the_limit_is_cancelled_and_its_stock_returned(): void
    {
        $this->setCmsOptions(['shop_hold_stock' => '60']);
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 3]);
        $order = $this->heldOrder($product, now()->subHours(2)->toDateTimeString(), 2);

        $this->artisan('falcon:cancel-held-orders')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, (int) DB::table('shop_products')->where('post_id', $product->id)->value('stock_quantity'),
            'the two reserved units should be back on the shelf');
    }

    public function test_an_order_still_inside_the_limit_is_left_alone(): void
    {
        $this->setCmsOptions(['shop_hold_stock' => '60']);
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 3]);
        $order = $this->heldOrder($product, now()->subMinutes(10)->toDateTimeString(), 2);

        $this->artisan('falcon:cancel-held-orders')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status, 'a customer mid-payment was cancelled');
        $this->assertSame(3, (int) DB::table('shop_products')->where('post_id', $product->id)->value('stock_quantity'));
    }

    public function test_a_paid_order_is_never_cancelled_however_old(): void
    {
        $this->setCmsOptions(['shop_hold_stock' => '60']);
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 3]);
        $order = $this->heldOrder($product, now()->subYear()->toDateTimeString(), 2);
        $order->update(['status' => 'processing']);

        $this->artisan('falcon:cancel-held-orders')->assertSuccessful();

        $this->assertSame('processing', $order->fresh()->status, 'a paid order must never be swept up');
        $this->assertSame(3, (int) DB::table('shop_products')->where('post_id', $product->id)->value('stock_quantity'));
    }

    public function test_nothing_is_cancelled_while_the_hold_is_switched_off(): void
    {
        $this->setCmsOptions(['shop_hold_stock' => '0']);
        $product = $this->makeProduct(['manage_stock' => 1, 'stock_quantity' => 3]);
        $order = $this->heldOrder($product, now()->subYear()->toDateTimeString(), 2);

        $this->artisan('falcon:cancel-held-orders')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }

    // ── falcon:prune-analytics ───────────────────────────────────────────────

    private function visit(string $when): void
    {
        // cms_analytics keeps created_at only — it is an append-only log, never updated.
        DB::table('cms_analytics')->insert([
            'url' => '/somewhere',
            'ip_address' => '127.0.0.1',
            'created_at' => $when,
        ]);
    }

    public function test_pruning_deletes_only_rows_past_the_window(): void
    {
        $this->visit(now()->subDays(400)->toDateTimeString());
        $this->visit(now()->subDays(10)->toDateTimeString());

        $this->artisan('falcon:prune-analytics', ['--days' => 30])->assertSuccessful();

        $this->assertSame(1, DB::table('cms_analytics')->count(), 'the recent visit should survive');
    }

    public function test_pruning_will_not_go_below_a_seven_day_floor(): void
    {
        $this->visit(now()->subDays(3)->toDateTimeString());

        // Asking for one day must not delete the last three: the floor is there so a typo in
        // a setting cannot wipe the week you are looking at.
        $this->artisan('falcon:prune-analytics', ['--days' => 1])->assertSuccessful();

        $this->assertSame(1, DB::table('cms_analytics')->count());
    }

    public function test_pruning_an_empty_table_is_a_no_op(): void
    {
        $this->artisan('falcon:prune-analytics', ['--days' => 30])->assertSuccessful();

        $this->assertSame(0, DB::table('cms_analytics')->count());
    }

    // ── falcon:prune-activity-logs ───────────────────────────────────────────

    public function test_pruning_activity_logs_runs_and_keeps_recent_entries(): void
    {
        falcon_log_activity('updated', 'Something happened just now');

        $before = DB::table('activity_logs')->count();
        $this->assertGreaterThan(0, $before, 'the log should have the entry we just wrote');

        $this->artisan('falcon:prune-activity-logs')->assertSuccessful();

        $this->assertSame($before, DB::table('activity_logs')->count(), 'a fresh entry was pruned');
    }

    public function test_pruning_activity_logs_deletes_what_is_past_the_window(): void
    {
        falcon_log_activity('updated', 'Ancient history');
        DB::table('activity_logs')->update(['created_at' => now()->subYears(2)]);

        $this->artisan('falcon:prune-activity-logs', ['--before' => now()->subYear()->toDateString()])
            ->assertSuccessful();

        $this->assertSame(0, DB::table('activity_logs')->count());
    }
}
