<?php

/**
 * Shop plugin helpers (shop-promotions): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-promotions.php.
 */

use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

if (!function_exists('falcon_promotion_matches_item')) {
    /**
     * Does a cart line fall inside a promotion's product / category list?
     *
     * An empty list means "anything", which is what makes a shop-wide rule expressible.
     * Category membership is matched through origin ids so translated duplicates of the same
     * product resolve to the same identity, exactly as the coupon restrictions do.
     */
    function falcon_promotion_matches_item(array $item, string $scope, array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $postId = (int) ($item['id'] ?? 0);
        if ($postId <= 0) {
            return false;
        }

        if ($scope === 'category') {
            static $catCache = [];
            if (!array_key_exists($postId, $catCache)) {
                $catCache[$postId] = DB::table('product_category_post')
                    ->join('product_categories', 'product_category_post.product_category_id', '=', 'product_categories.id')
                    ->where('product_category_post.post_id', $postId)
                    ->selectRaw('COALESCE(product_categories.origin_id, product_categories.id) as identity')
                    ->pluck('identity')->map(fn ($v) => (int) $v)->all();
            }

            return !empty(array_intersect($catCache[$postId], array_map('intval', $ids)));
        }

        static $idCache = [];
        if (!array_key_exists($postId, $idCache)) {
            $idCache[$postId] = (int) (DB::table('posts')
                ->where('id', $postId)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->value('identity') ?: $postId);
        }

        return in_array($idCache[$postId], array_map('intval', $ids), true);
    }
}

if (!function_exists('falcon_promotion_applications')) {
    /**
     * How many times a cart satisfies a promotion's condition.
     *
     * Shared by the discount calculation and the "you qualify" prompt so the two can never
     * disagree about whether an offer is earned.
     *
     * @param  array  $units  cart key => ['item'=>…, 'price'=>float, 'qty'=>int]
     * @param  array  $claimed  cart key => units an earlier promotion already gave away
     */
    function falcon_promotion_applications($promo, array $units, float $subtotal, array $claimed = []): int
    {
        $triggerQty = max(0.0, (float) $promo->trigger_qty);
        $rewardQty = max(1, (int) $promo->reward_qty);

        if ($promo->trigger_type === 'cart_total') {
            $applications = ($triggerQty > 0 && $subtotal >= $triggerQty) ? 1 : 0;
        } elseif ($triggerQty <= 0) {
            $applications = 0;
        } else {
            // Units an earlier promotion already gave away cannot also count towards qualifying
            // for this one — otherwise two "buy 1 get 1" rules on the same two items would make
            // both free, and the shop would be paid nothing.
            $matchedQty = 0;
            foreach ($units as $key => $u) {
                if (falcon_promotion_matches_item($u['item'], $promo->trigger_type, (array) ($promo->trigger_ids ?? []))) {
                    $matchedQty += max(0, $u['qty'] - ($claimed[$key] ?? 0));
                }
            }

            if ($promo->reward_scope === 'same') {
                // "Buy 2 get 1 free" needs three units per round — two paid, one free —
                // otherwise a basket of 2 would hand back both of them.
                $perRound = (int) ceil($triggerQty) + $rewardQty;
                $applications = intdiv($matchedQty, max(1, $perRound));
            } else {
                $applications = (int) floor($matchedQty / $triggerQty);
            }
        }

        if ($promo->max_applications !== null && $promo->max_applications > 0) {
            $applications = min($applications, (int) $promo->max_applications);
        }

        return max(0, $applications);
    }
}

if (!function_exists('falcon_promotion_reward_target')) {
    /**
     * Which pool of items a promotion rewards: [scope, ids].
     *
     * 'same' means the reward comes from whatever triggered the rule. A cart_total trigger has
     * no product list of its own to inherit, so it falls back to "anything".
     *
     * @return array{0: string, 1: array}
     */
    function falcon_promotion_reward_target($promo): array
    {
        if ($promo->reward_scope !== 'same') {
            return [$promo->reward_scope, (array) ($promo->reward_ids ?? [])];
        }

        if ($promo->trigger_type === 'cart_total') {
            return ['product', []];
        }

        return [$promo->trigger_type, (array) ($promo->trigger_ids ?? [])];
    }
}

if (!function_exists('falcon_pending_promotion_offers')) {
    /**
     * Offers the customer has already earned but is not receiving, because the reward item is
     * not in their basket.
     *
     * "Buy 3 phones, get a case free" only discounts a case that is actually in the cart — the
     * shopper has no way of knowing that on their own, so the cart shows a prompt with the
     * qualifying products and a one-click add.
     *
     * @return array<int, array{name:string, summary:string, missing:int, products:array}>
     */
    function falcon_pending_promotion_offers(?array $cart = null): array
    {
        $cart = $cart ?? session()->get('falcon_cart', []);
        if (empty($cart)) {
            return [];
        }

        $subtotal = 0.0;
        $units = [];
        foreach ($cart as $key => $item) {
            $price = (float) ($item['sale_price'] ?? $item['price']);
            $qty = (int) ($item['quantity'] ?? 0);
            $subtotal += $price * $qty;
            if ($qty > 0) {
                $units[$key] = ['item' => $item, 'price' => $price, 'qty' => $qty];
            }
        }

        $offers = [];

        foreach (falcon_active_promotions() as $promo) {
            // 'same'-scope rules reward the very items that triggered them, so there is never
            // anything for the customer to add.
            if ($promo->reward_scope === 'same') {
                continue;
            }

            $applications = falcon_promotion_applications($promo, $units, $subtotal);
            if ($applications < 1) {
                continue;
            }

            [$scope, $ids] = falcon_promotion_reward_target($promo);

            $inCart = 0;
            foreach ($units as $u) {
                if (falcon_promotion_matches_item($u['item'], $scope, $ids)) {
                    $inCart += $u['qty'];
                }
            }

            $wanted = $applications * max(1, (int) $promo->reward_qty);
            $missing = $wanted - $inCart;
            if ($missing < 1) {
                continue;   // already receiving it
            }

            $offers[] = [
                'name' => (string) $promo->name,
                'summary' => str_replace('{missing}', (string) $missing, $promo->customer_message),
                // Tells the view whether the shop supplied its own wording, so the default
                // "add N more item(s)" tail is only appended to the generated text.
                'custom' => trim((string) ($promo->cart_message ?? '')) !== '',
                'missing' => $missing,
                'products' => falcon_promotion_reward_products($scope, $ids),
            ];
        }

        return $offers;
    }
}

if (!function_exists('falcon_promotion_reward_products')) {
    /**
     * Buyable products that would satisfy a reward pool, for the cart prompt.
     * Capped because a category-wide reward could otherwise list the whole catalogue.
     */
    function falcon_promotion_reward_products(string $scope, array $ids, int $limit = 4): array
    {
        try {
            $query = DB::table('posts')
                ->join('shop_products', 'shop_products.post_id', '=', 'posts.id')
                ->where('posts.type', 'product')
                ->where('posts.status', 'published')
                ->whereNull('posts.deleted_at');

            if (!empty($ids)) {
                if ($scope === 'category') {
                    $query->join('product_category_post', 'product_category_post.post_id', '=', 'posts.id')
                        ->whereIn('product_category_post.product_category_id', array_map('intval', $ids));
                } else {
                    $query->whereIn('posts.id', array_map('intval', $ids));
                }
            }

            return $query->distinct()
                ->orderBy('shop_products.price')
                ->limit($limit)
                ->get(['posts.id', 'posts.title', 'posts.slug', 'shop_products.price', 'shop_products.sale_price'])
                ->map(static fn ($r) => [
                    'id' => (int) $r->id,
                    'title' => $r->title,
                    'slug' => $r->slug,
                    'price' => (float) ($r->sale_price ?: $r->price),
                ])->all();
        } catch (Throwable $e) {
            Log::error('Promotion reward product lookup failed: '.$e->getMessage());

            return [];
        }
    }
}

if (!function_exists('falcon_active_promotions')) {
    /** Promotions that are live right now, cheapest-priority first. */
    function falcon_active_promotions()
    {
        try {
            if (!Schema::hasTable('shop_promotions')) {
                return collect();
            }

            return Promotion::usable()->get();
        } catch (Throwable $e) {
            Log::error('Promotion lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_evaluate_promotions')) {
    /**
     * Work out which promotions the cart currently earns, and what each is worth.
     *
     * Prices are read from the cart lines and every figure is recalculated here on each call —
     * nothing is cached in the session, so a customer cannot hold on to a reward after the
     * qualifying item leaves their basket.
     *
     * Each unit of stock can only be rewarded once: `$claimed` tracks how many units of every
     * line an earlier (higher-priority) rule already discounted, so two overlapping promotions
     * cannot both give away the same phone.
     *
     * @return array<int, array{id:int, name:string, summary:string, discount:float, applications:int}>
     */
    function falcon_evaluate_promotions(?array $cart = null): array
    {
        $cart = $cart ?? session()->get('falcon_cart', []);
        if (empty($cart)) {
            return [];
        }

        $subtotal = 0.0;
        $units = [];   // flattened: one entry per unit, so "cheapest first" is a plain sort
        foreach ($cart as $key => $item) {
            $price = (float) ($item['sale_price'] ?? $item['price']);
            $qty = (int) ($item['quantity'] ?? 0);
            $subtotal += $price * $qty;
            if ($qty > 0) {
                $units[$key] = ['item' => $item, 'price' => $price, 'qty' => $qty];
            }
        }

        $claimed = [];   // cart key => units already given away by an earlier promotion
        $results = [];

        foreach (falcon_active_promotions() as $promo) {
            $rewardQty = max(1, (int) $promo->reward_qty);
            $applications = falcon_promotion_applications($promo, $units, $subtotal, $claimed);
            if ($applications < 1) {
                continue;
            }

            [$scope, $ids] = falcon_promotion_reward_target($promo);

            $pool = [];
            foreach ($units as $key => $u) {
                if (!falcon_promotion_matches_item($u['item'], $scope, $ids)) {
                    continue;
                }
                $available = $u['qty'] - ($claimed[$key] ?? 0);
                for ($i = 0; $i < $available; $i++) {
                    $pool[] = ['key' => $key, 'price' => $u['price']];
                }
            }
            if (empty($pool)) {
                continue;
            }

            // Cheapest first — the customary reading of "get one free" and the safest for the shop.
            usort($pool, static fn ($a, $b) => $a['price'] <=> $b['price']);

            $wanted = $applications * $rewardQty;
            $taken = array_slice($pool, 0, $wanted);
            if (empty($taken)) {
                continue;
            }

            $discount = 0.0;
            foreach ($taken as $unit) {
                $discount += match ($promo->reward_type) {
                    'percent_off' => $unit['price'] * (min(100, max(0, $promo->reward_value)) / 100),
                    'fixed_off' => min($unit['price'], max(0, $promo->reward_value)),
                    default => $unit['price'],   // free_item
                };
                $claimed[$unit['key']] = ($claimed[$unit['key']] ?? 0) + 1;
            }

            if ($discount <= 0) {
                continue;
            }

            $results[] = [
                'id' => (int) $promo->id,
                'name' => (string) $promo->name,
                // The shop's own wording when it wrote some, otherwise the generated summary.
                // {missing} has no meaning once the reward is already applied, so it is dropped.
                'summary' => trim(str_replace('{missing}', '', $promo->customer_message)),
                'discount' => round($discount, 2),
                'applications' => $applications,
                'units' => count($taken),
            ];
        }

        return $results;
    }
}

if (!function_exists('falcon_cart_promotion_total')) {
    /** Total money the active promotions take off this cart. */
    function falcon_cart_promotion_total(?array $cart = null): float
    {
        $total = 0.0;
        foreach (falcon_evaluate_promotions($cart) as $applied) {
            $total += $applied['discount'];
        }

        return round($total, 2);
    }
}

if (!function_exists('falcon_find_coupon')) {
    /**
     * One coupon by code, case-insensitively, in the cart's array shape. Null when unknown.
     */
    function falcon_find_coupon(?string $code): ?array
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        try {
            if (Schema::hasTable('shop_coupons')) {
                return Coupon::findByCode($code)?->toCartArray();
            }
        } catch (Throwable $e) {
            Log::error('Coupon lookup failed: '.$e->getMessage());
        }

        foreach (falcon_all_coupons() as $coupon) {
            if (strtoupper((string) ($coupon['code'] ?? '')) === $code) {
                return $coupon;
            }
        }

        return null;
    }
}

if (!function_exists('get_falcon_coupon_discount_amount')) {
    function get_falcon_coupon_discount_amount($coupon, $cart, $calcBaseSubtotal = null)
    {
        $amount = (float) ($coupon['amount'] ?? ($coupon['discount'] ?? 0));
        $couponType = $coupon['type'] ?? 'percent';
        $products = (array) ($coupon['products'] ?? []);
        $categories = (array) ($coupon['categories'] ?? []);

        // Free Shipping takes nothing off the cart — its value lands on the shipping line,
        // which falcon_shipping_methods() zeroes out while such a coupon is applied.
        if ($couponType === 'free_shipping') {
            return 0.0;
        }

        // If NO restrictions, apply to the whole provided subtotal
        if (empty($products) && empty($categories)) {
            $base = $calcBaseSubtotal ?? get_falcon_cart_subtotal();
            if ($couponType === 'percent') {
                return $base * ($amount / 100);
            }
            // fixed_product is a per-item amount. Without this it fell through to the
            // fixed_cart branch below and discounted the amount once for the whole cart,
            // so an unrestricted "৳50 off each item" coupon only ever took off ৳50.
            if ($couponType === 'fixed_product') {
                $units = 0;
                foreach ($cart as $item) {
                    $units += (int) ($item['quantity'] ?? 1);
                }

                return min($amount * $units, $base);
            }

            return min($amount, $base);
        }

        // Fetch origin IDs for restricted products and categories for robust matching
        $restrictedProductOriginIds = [];
        if (!empty($products)) {
            $restrictedProductOriginIds = DB::table('posts')
                ->whereIn('id', $products)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        $restrictedCategoryOriginIds = [];
        if (!empty($categories)) {
            $restrictedCategoryOriginIds = DB::table('taxonomy_terms')
                ->whereIn('id', $categories)
                ->selectRaw('COALESCE(origin_id, id) as identity')
                ->pluck('identity')
                ->toArray();
        }

        // Calculate discount
        $totalDiscount = 0;
        $eligibleSubtotal = 0;

        foreach ($cart as $item) {
            $productId = $item['id'] ?? 0;
            if (!$productId) {
                continue;
            }

            // Check Product Eligibility
            $matchProduct = false;
            if (!empty($restrictedProductOriginIds)) {
                $itemIdentity = DB::table('posts')
                    ->where('id', $productId)
                    ->selectRaw('COALESCE(origin_id, id) as identity')
                    ->value('identity');
                $matchProduct = in_array($itemIdentity, $restrictedProductOriginIds);
            }

            // Check Category Eligibility
            $matchCategory = false;
            if (!empty($restrictedCategoryOriginIds)) {
                $itemCategoryIdentities = DB::table('product_category_post')
                    ->join('product_categories', 'product_category_post.product_category_id', '=', 'product_categories.id')
                    ->where('product_category_post.post_id', $productId)
                    ->selectRaw('COALESCE(product_categories.origin_id, product_categories.id) as identity')
                    ->pluck('identity')
                    ->toArray();
                $matchCategory = !empty(array_intersect($itemCategoryIdentities, $restrictedCategoryOriginIds));
            }

            $isEligible = false;
            if (empty($restrictedProductOriginIds) && empty($restrictedCategoryOriginIds)) {
                $isEligible = true;
            } else {
                $isEligible = $matchProduct || $matchCategory;
            }

            if ($isEligible) {
                $qty = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['sale_price'] ?? $item['price']);

                if ($couponType === 'percent') {
                    $eligibleSubtotal += $price * $qty;
                } elseif ($couponType === 'fixed_product') {
                    $totalDiscount += $amount * $qty;
                } else { // fixed_cart
                    $eligibleSubtotal += $price * $qty;
                }
            }
        }

        if ($couponType === 'percent') {
            return $eligibleSubtotal * ($amount / 100);
        } elseif ($couponType === 'fixed_product') {
            return $totalDiscount;
        } else { // fixed_cart
            return min($amount, $eligibleSubtotal);
        }
    }
}
