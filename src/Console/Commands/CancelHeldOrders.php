<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use FalconCms\Core\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CancelHeldOrders extends Command
{
    protected $signature = 'falcon:cancel-held-orders';
    protected $description = 'Cancel pending orders that exceeded the hold_stock time limit (Shop → Settings → Products).';

    public function handle(): void
    {
        $minutes = (int) get_shop_option('shop_hold_stock', '0');

        // 0 or empty = disabled
        if ($minutes <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subMinutes($minutes);

        $orders = Order::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->with(['items.product.shopData', 'items.variation'])
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($orders as $order) {
            try {
                $order->update(['status' => 'cancelled']);
                $this->restoreStock($order);
                falcon_log_activity('order_auto_cancelled', "Auto-cancelled held order #{$order->order_number} after {$minutes} minutes.");
            } catch (\Throwable $e) {
                Log::error("falcon:cancel-held-orders failed for order #{$order->order_number}: " . $e->getMessage());
            }
        }

        $this->info("Cancelled {$orders->count()} held order(s).");
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->variation) {
                if ($item->variation->manage_stock) {
                    $item->variation->increment('stock_quantity', $item->quantity);
                }
            } elseif ($item->product && $item->product->shopData) {
                $shopData = $item->product->shopData;
                if ($shopData->manage_stock) {
                    $shopData->increment('stock_quantity', $item->quantity);
                }
            }
        }
    }
}
