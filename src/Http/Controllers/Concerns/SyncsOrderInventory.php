<?php

namespace FalconCms\Core\Http\Controllers\Concerns;

use FalconCms\Core\Models\Order;

/**
 * Moves stock in and out of inventory when an order changes status.
 *
 * Shared because a refund can now arrive from two directions — the admin order screen and
 * the Stripe webhook (a refund issued from the Stripe dashboard) — and both have to restock
 * identically. Keeping one implementation is what stops the two paths from drifting.
 */
trait SyncsOrderInventory
{
    /** Statuses that hold stock out of inventory. */
    protected array $stockActiveStatuses = ['pending', 'processing', 'completed', 'on-hold', 'partially-refunded'];

    /** Statuses that return stock to inventory. */
    protected array $stockInactiveStatuses = ['cancelled', 'refunded', 'failed'];

    protected function syncOrderInventory(Order $order, ?string $oldStatus, ?string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $wasActive   = in_array($oldStatus, $this->stockActiveStatuses, true);
        $isInactive  = in_array($newStatus, $this->stockInactiveStatuses, true);
        $wasInactive = in_array($oldStatus, $this->stockInactiveStatuses, true);
        $isActive    = in_array($newStatus, $this->stockActiveStatuses, true);

        if (!(($wasActive && $isInactive) || ($wasInactive && $isActive))) {
            return;
        }

        $restock = $wasActive && $isInactive;

        $order->loadMissing(['items.product.shopData', 'items.variation']);

        foreach ($order->items as $item) {
            $target = $item->variation ?: ($item->product->shopData ?? null);
            if (!$target || !$target->manage_stock) {
                continue;
            }
            $restock
                ? $target->increment('stock_quantity', $item->quantity)
                : $target->decrement('stock_quantity', $item->quantity);
        }
    }
}
