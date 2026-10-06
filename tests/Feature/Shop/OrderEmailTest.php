<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Order;
use FalconCms\Core\Tests\TestCase;
use FalconShop\Mail\OrderNotificationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * The order email moved into the shop plugin with the rest of the shop. A site may still hold
 * mail queued under the old class name, or a restyled copy of the email at its old view path.
 */
class OrderEmailTest extends TestCase
{
    private function order(): Order
    {
        $id = DB::table('shop_orders')->insertGetId([
            'order_number' => 'ORD-MAIL-1', 'first_name' => 'Alice', 'last_name' => 'Ahmed', 'customer_email' => 'alice@example.test',
            'address_line_1' => '12 Road 5', 'city' => 'Dhaka', 'postcode' => '1205', 'country' => 'Bangladesh',
            'subtotal' => 100, 'total' => 100, 'status' => 'pending', 'payment_method' => 'cod',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }

    public function test_the_order_email_renders_from_the_plugin(): void
    {
        $mail = new OrderNotificationMail($this->order(), 'placed');

        $this->assertSame('falcon-shop::emails.order_notification', $mail->content()->view);
        $this->assertStringContainsString('ORD-MAIL-1', $mail->render());
        $this->assertStringContainsString('ORD-MAIL-1', $mail->envelope()->subject);
    }

    public function test_the_old_class_name_still_builds_the_email(): void
    {
        $mail = app()->make('FalconCms\\Core\\Mail\\OrderNotificationMail', ['order' => $this->order(), 'notificationType' => 'status_updated']);

        $this->assertInstanceOf(OrderNotificationMail::class, $mail);
        $this->assertStringContainsString('ORD-MAIL-1', $mail->render());
    }

    public function test_a_published_copy_at_the_old_path_still_wins(): void
    {
        $dir = storage_path('framework/testing/published-views');
        File::ensureDirectoryExists($dir.'/emails/shop');
        File::put($dir.'/emails/shop/order_notification.blade.php', 'restyled {{ $order->order_number }}');
        View::prependNamespace('falcon-cms', $dir);

        try {
            $mail = new OrderNotificationMail($this->order(), 'placed');
            $this->assertSame('restyled ORD-MAIL-1', trim($mail->render()));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
