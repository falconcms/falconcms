<?php

/**
 * Shop: promotions and coupons.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Coupon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

if (!function_exists('falcon_order_discount_lines')) {
    /**
     * Break an order's discount down into named lines the customer can recognise.
     *
     * Orders record a single `discount_total`, which on its own leaves the shopper staring at a
     * gap between the subtotal and the total. Coupon codes live on the order row and promotions
     * in its meta, so both can be named here.
     *
     * Whatever cannot be attributed is emitted as a final "Discount" line, so the figures always
     * reconcile — including for orders placed before promotions existed.
     *
     * @return array<int, array{label:string, note:?string, amount:float}>
     */
    function falcon_order_discount_lines($order): array
    {
        $total = round((float) ($order->discount_total ?? 0), 2);
        if ($total <= 0) {
            return [];
        }

        $lines = [];
        $accounted = 0.0;

        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        foreach ((array) ($meta['promotions'] ?? []) as $promo) {
            $amount = round((float) ($promo['discount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [
                'label' => (string) ($promo['name'] ?? 'Promotion'),
                'note' => $promo['summary'] ?? null,
                'amount' => $amount,
            ];
            $accounted += $amount;
        }

        $remainder = round($total - $accounted, 2);
        if ($remainder <= 0.009) {
            return $lines;
        }

        // Coupons share a single stored figure, so several codes are shown on one line rather
        // than guessing how the money was split between them.
        $codes = array_values(array_filter(array_map('trim', explode(',', (string) ($order->coupon_code ?? '')))));

        $lines[] = [
            'label' => $codes ? 'Coupon'.(count($codes) > 1 ? 's' : '').': '.implode(', ', $codes) : 'Discount',
            'note' => null,
            'amount' => $remainder,
        ];

        return $lines;
    }
}

if (!function_exists('falcon_all_coupons')) {
    /**
     * Every active coupon, in the array shape the cart and checkout already speak.
     *
     * Coupons live in the shop_coupons table (the code column is uniquely indexed, and the
     * redemption counter increments atomically). Falls back to the legacy settings blob if the
     * table is missing, so an install that has not run migrations yet still sells.
     *
     * @return array<int, array<string, mixed>>
     */
    function falcon_all_coupons(): array
    {
        try {
            if (Schema::hasTable('shop_coupons')) {
                return Coupon::where('is_active', true)
                    ->orderBy('code')
                    ->get()
                    ->map(static fn ($c) => $c->toCartArray())
                    ->all();
            }
        } catch (Throwable $e) {
            Log::error('Coupon lookup failed: '.$e->getMessage());
        }

        $legacy = json_decode((string) get_cms_option('shop_coupons', '[]'), true);

        return is_array($legacy) ? $legacy : [];
    }
}
