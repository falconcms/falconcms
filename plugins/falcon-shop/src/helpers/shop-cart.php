<?php

/**
 * Shop plugin helpers (shop-cart): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-cart.php.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('get_falcon_cart_subtotal')) {
    function get_falcon_cart_subtotal()
    {
        $cart = session()->get('falcon_cart', []);
        $subtotal = 0;
        foreach ($cart as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal += $price * $item['quantity'];
        }

        return $subtotal;
    }
}

if (!function_exists('get_falcon_cart_shipping')) {
    /**
     * Calculate shipping cost based on subtotal, quantity, and location.
     *
     * @param  string|null  $country  Customer country code
     * @return float
     */
    function get_falcon_cart_shipping($country = null)
    {
        $details = get_falcon_cart_shipping_details($country);

        return $details['cost'];
    }
}

if (!function_exists('falcon_refresh_cart_prices')) {
    /**
     * Re-read every cart line's price from the catalogue.
     *
     * The cart stores the price that was current when the item went in, and nothing ever looked
     * at it again — so a sale that ended, or a price the shop owner corrected, never reached a
     * cart that already existed. Coupons were already re-checked on every cart load; prices are
     * now held to the same rule.
     *
     * Deliberately conservative in two places:
     *   - a product that has vanished from the catalogue is left untouched rather than silently
     *     repriced or removed, so a lookup failure can never zero out someone's basket;
     *   - a sale price of zero or less is stored as null, because the subtotal treats a present
     *     sale price as authoritative and a literal 0 would hand the item over for free.
     *
     * @return int how many lines actually changed
     */
    function falcon_refresh_cart_prices(): int
    {
        $cart = session()->get('falcon_cart', []);
        if (empty($cart) || !is_array($cart)) {
            return 0;
        }

        $productIds = [];
        $variationIds = [];
        foreach ($cart as $item) {
            if (!empty($item['id'])) {
                $productIds[] = (int) $item['id'];
            }
            if (!empty($item['variation_id'])) {
                $variationIds[] = (int) $item['variation_id'];
            }
        }

        try {
            $products = empty($productIds) ? collect() : DB::table('shop_products')
                ->whereIn('post_id', array_unique($productIds))
                ->get(['post_id', 'price', 'sale_price', 'sale_ends_at'])
                ->keyBy('post_id');

            $variations = empty($variationIds) ? collect() : DB::table('shop_product_variations')
                ->whereIn('id', array_unique($variationIds))
                ->get(['id', 'price', 'sale_price'])
                ->keyBy('id');
        } catch (Throwable $e) {
            // A pricing lookup that fails must not empty or corrupt the basket.
            Log::error('Cart price refresh failed: '.$e->getMessage());

            return 0;
        }

        $changed = 0;
        foreach ($cart as $key => $item) {
            $source = null;
            if (!empty($item['variation_id'])) {
                $source = $variations[(int) $item['variation_id']] ?? null;
            }
            $parent = $products[(int) ($item['id'] ?? 0)] ?? null;
            $source = $source ?? $parent;

            if (!$source) {
                continue;   // no longer in the catalogue — leave the line exactly as it was
            }

            $price = round((float) $source->price, 2);
            $sale = $source->sale_price !== null ? round((float) $source->sale_price, 2) : null;

            // The scheduled falcon:expire-sale-prices command clears these, but it may not have
            // run yet — an expired sale must not survive in a cart either way.
            $endsAt = $parent->sale_ends_at ?? null;
            if ($sale !== null && $endsAt && strtotime((string) $endsAt) < time()) {
                $sale = null;
            }
            if ($sale !== null && $sale <= 0) {
                $sale = null;
            }

            $oldPrice = round((float) ($item['price'] ?? 0), 2);
            $oldSale = isset($item['sale_price']) && $item['sale_price'] !== null && $item['sale_price'] !== ''
                ? round((float) $item['sale_price'], 2)
                : null;

            if ($oldPrice !== $price || $oldSale !== $sale) {
                $cart[$key]['price'] = $price;
                $cart[$key]['sale_price'] = $sale;
                $changed++;
            }
        }

        if ($changed > 0) {
            session()->put('falcon_cart', $cart);
        }

        return $changed;
    }
}

if (!function_exists('falcon_cart_weight')) {
    /**
     * Total shipping weight of the cart, in the shop's configured weight unit.
     *
     * A variation uses its own weight when one was entered and otherwise inherits the parent
     * product's. Items with no weight at all count as zero rather than blocking the order — a
     * shop that has not filled its weights in yet must still be able to sell.
     *
     * Both lookups are single queries, so the cost does not grow with the size of the cart.
     */
    function falcon_cart_weight(?array $cart = null): float
    {
        $cart = $cart ?? session()->get('falcon_cart', []);
        if (empty($cart)) {
            return 0.0;
        }

        $productIds = [];
        $variationIds = [];
        foreach ($cart as $item) {
            if (!empty($item['id'])) {
                $productIds[] = (int) $item['id'];
            }
            if (!empty($item['variation_id'])) {
                $variationIds[] = (int) $item['variation_id'];
            }
        }

        try {
            $productWeights = empty($productIds) ? collect() : DB::table('shop_products')
                ->whereIn('post_id', array_unique($productIds))
                ->pluck('weight', 'post_id');

            $variationWeights = empty($variationIds) ? collect() : DB::table('shop_product_variations')
                ->whereIn('id', array_unique($variationIds))
                ->pluck('weight', 'id');
        } catch (Throwable $e) {
            Log::error('Cart weight lookup failed: '.$e->getMessage());

            return 0.0;
        }

        $total = 0.0;
        foreach ($cart as $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($quantity === 0) {
                continue;
            }

            $weight = null;
            if (!empty($item['variation_id'])) {
                $weight = $variationWeights[(int) $item['variation_id']] ?? null;
            }
            if ($weight === null || $weight === '' || (float) $weight <= 0) {
                $weight = $productWeights[(int) ($item['id'] ?? 0)] ?? null;
            }

            $total += max(0.0, (float) $weight) * $quantity;
        }

        return round($total, 4);
    }
}

if (!function_exists('falcon_cart_discount_total')) {
    /**
     * Total coupon discount for the cart. Shared by the total and the tax base so a discount
     * can never be counted differently in the two places.
     */
    function falcon_cart_discount_total(): float
    {
        $coupons = session()->get('falcon_coupons', []);
        if (empty($coupons)) {
            return 0.0;
        }

        $cart = session()->get('falcon_cart', []);
        $subtotal = get_falcon_cart_subtotal();
        $currentSubtotal = $subtotal;
        $isSequential = (int) get_shop_option('shop_coupon_stacking_policy', '1') === 1;
        $discountTotal = 0.0;

        foreach ($coupons as $coupon) {
            $discount = get_falcon_coupon_discount_amount($coupon, $cart, $isSequential ? $currentSubtotal : $subtotal);
            $discountTotal += $discount;
            $currentSubtotal -= $discount;
        }

        return $discountTotal;
    }
}

if (!function_exists('get_falcon_cart_total')) {
    function get_falcon_cart_total()
    {
        $cart = session()->get('falcon_cart', []);
        $subtotal = get_falcon_cart_subtotal();
        // Resolver rather than the raw session key, so the store's default-location setting
        // feeds the total the same way it feeds the line the customer is shown.
        $shipping = get_falcon_cart_shipping(falcon_customer_shipping_country());
        $tax = get_falcon_cart_tax();

        // Coupons the customer typed in, plus whatever the automatic promotions earned them.
        $totalDiscount = falcon_cart_discount_total() + falcon_cart_promotion_total($cart);

        // Inclusive pricing means the tax is already inside the item prices — adding $tax here
        // would charge it a second time. It is still reported separately for the tax line.
        $total = $subtotal + $shipping - $totalDiscount;
        if (!falcon_prices_include_tax()) {
            $total += $tax;
        }

        return max(0, $total);
    }
}
