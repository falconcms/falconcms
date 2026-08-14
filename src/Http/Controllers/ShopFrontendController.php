<?php

namespace FalconCms\Core\Http\Controllers;

use App\Models\User;
use FalconCms\Core\Http\Controllers\Concerns\SyncsOrderInventory;
use FalconCms\Core\Mail\MagicLoginMail;
use FalconCms\Core\Mail\OrderNotificationMail;
use FalconCms\Core\Models\Coupon;
use FalconCms\Core\Models\CustomerAddress;
use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\OrderDownload;
use FalconCms\Core\Models\OrderItem;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Product;
use FalconCms\Core\Models\ProductVariation;
use FalconCms\Core\Models\Promotion;
use FalconCms\Core\Models\Review;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Services\EcommerceData;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ShopFrontendController extends Controller
{
    use SyncsOrderInventory;

    protected function resolveThemeView($view)
    {
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        $appView = "themes.{$activeTheme}.ecommerce.{$view}";
        if (view()->exists($appView)) {
            return $appView;
        }

        $packageView = "falcon-cms::themes.{$activeTheme}.ecommerce.{$view}";
        if (view()->exists($packageView)) {
            return $packageView;
        }

        return "falcon-cms::themes.falcon-theme.ecommerce.{$view}";
    }

    public function cart()
    {
        $this->validateCartItems();
        // Prices first: the coupon rules below (minimum spend, and the totals themselves)
        // have to be judged against what the catalogue charges today, not what it charged
        // when the item went into the basket.
        falcon_refresh_cart_prices();
        $this->revalidateCoupon();
        $cart = Session::get('falcon_cart', []);

        return view($this->resolveThemeView('cart'), compact('cart'));
    }

    /**
     * Off-canvas mini-cart fragment (AJAX). Returns the rendered item list,
     * live subtotal and item count so the drawer can refresh dynamically.
     */
    public function miniCart(Request $request)
    {
        falcon_refresh_cart_prices();
        // Internal AJAX fragment endpoint — send a direct browser visit to the cart page
        // instead of exposing the raw JSON payload.
        if (!$request->ajax()) {
            return redirect()->route('shop.cart');
        }

        $this->validateCartItems();
        $cart = Session::get('falcon_cart', []);

        $html = view($this->resolveThemeView('mini-cart-items'), compact('cart'))->render();

        return response()->json([
            'success' => true,
            'count' => get_falcon_cart_count(),
            'subtotal' => falcon_price_format(get_falcon_cart_subtotal()),
            'html' => $html,
        ]);
    }

    public function addToCart(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $variationId = $request->input('variation_id');

        $product = Product::with('shopData')->findOrFail($productId);
        $shopData = $product->shopData;

        $variation = null;
        if ($variationId) {
            $variation = ProductVariation::find($variationId);
        }

        // Inventory Check
        $stockSource = ($variation && $variation->manage_stock) ? $variation : $shopData;
        // Backorders are configured on the product, so a variation-tracked item follows its
        // parent's policy — there is no per-variation backorder setting to consult.
        $allowsBackorder = $shopData && method_exists($shopData, 'allowsBackorders') && $shopData->allowsBackorders();

        if ($stockSource && $stockSource->manage_stock && !$allowsBackorder) {
            $cart = Session::get('falcon_cart', []);
            $cartKey = $variationId ? "{$productId}_{$variationId}" : $productId;
            $currentInCart = isset($cart[$cartKey]) ? $cart[$cartKey]['quantity'] : 0;

            if (($currentInCart + $quantity) > $stockSource->stock_quantity) {
                $errorMsg = $stockSource->stock_quantity <= 0
                    ? 'Sorry, this product is currently out of stock.'
                    : 'Sorry, only '.$stockSource->stock_quantity.' items available in stock.';

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg,
                    ], 422);
                }

                return redirect()->back()->with('error', $errorMsg);
            }
        }

        $cart = Session::get('falcon_cart', []);

        $cartKey = $variationId ? "{$productId}_{$variationId}" : $productId;

        // Collect custom fields prefixed with lazy_custom_
        $customFields = [];
        foreach ($request->all() as $k => $v) {
            if (str_starts_with($k, 'lazy_custom_')) {
                $customFields[substr($k, 12)] = $v;
            }
        }
        $customFields = apply_falcon_filters('falcon_cart_item_custom_fields', $customFields, $product, $variation);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
            // Merge any new custom fields into existing meta
            if (!empty($customFields)) {
                $existing = $cart[$cartKey]['meta'] ?? [];
                $existing['custom_fields'] = array_merge($existing['custom_fields'] ?? [], $customFields);
                $cart[$cartKey]['meta'] = $existing;
            }
        } else {
            // Determine name and attributes for variation
            $itemName = $product->title;
            if ($variation) {
                $attrString = collect($variation->attributes_data)->map(fn ($v, $k) => "$k: $v")->implode(', ');
                $itemName .= ' - '.$attrString;
            }

            $cart[$cartKey] = [
                'id' => $product->id,
                'name' => $itemName,
                'slug' => $product->slug,
                'price' => $variation ? $variation->price : $product->price,
                'sale_price' => $variation ? $variation->sale_price : $product->sale_price,
                'quantity' => $quantity,
                'thumbnail' => ($variation && $variation->image) ? $variation->image : $product->featured_image,
                'variation_id' => $variationId,
                'sku' => $variation ? $variation->sku : $product->sku,
                'meta' => !empty($customFields) ? ['custom_fields' => $customFields] : [],
            ];

            $cart[$cartKey] = apply_falcon_filters('falcon_cart_item_data', $cart[$cartKey], $product, $variation);
        }

        Session::put('falcon_cart', $cart);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart!',
                'cart_count' => get_falcon_cart_count(),
            ]);
        }

        return redirect()->to(get_falcon_cart_url())->with('success', 'Product added to cart!');
    }

    public function updateCart(Request $request)
    {
        $request->validate([
            'quantity' => ['required', 'array', 'max:100'],
            'quantity.*' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $cart = Session::get('falcon_cart', []);
        $quantities = $request->input('quantity', []);

        foreach ($quantities as $key => $qty) {
            // Only process keys that genuinely exist in the session cart
            if (!isset($cart[$key])) {
                continue;
            }
            $cart[$key]['quantity'] = (int) $qty;
        }

        Session::put('falcon_cart', $cart);
        $this->validateCartItems();
        // Prices first: the coupon rules below (minimum spend, and the totals themselves)
        // have to be judged against what the catalogue charges today, not what it charged
        // when the item went into the basket.
        falcon_refresh_cart_prices();
        $this->revalidateCoupon();

        if ($request->ajax()) {
            $item_subtotals = [];
            foreach ($cart as $key => $item) {
                $price = $item['sale_price'] ?? $item['price'];
                $item_subtotals[$key] = falcon_price_format($price * $item['quantity']);
            }

            return response()->json($this->cartTotalsPayload([
                'message' => 'Cart updated!',
                'item_subtotals' => $item_subtotals,
            ]));
        }

        return redirect()->back()->with('success', 'Cart updated!');
    }

    public function applyCoupon(Request $request)
    {
        // Prices first: the coupon rules below (minimum spend, and the totals themselves)
        // have to be judged against what the catalogue charges today, not what it charged
        // when the item went into the basket.
        falcon_refresh_cart_prices();
        $this->revalidateCoupon(); // Prune first based on current settings

        // Check if coupons are enabled in settings
        if (get_shop_option('shop_enable_coupons', '1') !== '1') {
            return $this->couponResponse(false, 'Coupons are currently disabled.', $request);
        }

        try {
            $code = strtoupper($request->input('coupon_code'));
            if (empty($code)) {
                return $this->couponResponse(false, 'Please enter a coupon code.', $request);
            }

            $coupon = falcon_find_coupon($code);

            if (!$coupon) {
                return $this->couponResponse(false, 'Invalid coupon code.', $request);
            }

            // Check if multiple coupons are allowed
            $isMultipleAllowed = (int) get_shop_option('shop_coupon_stacking_policy', '1') === 1;
            $appliedCoupons = Session::get('falcon_coupons', []);

            if (!$isMultipleAllowed && count($appliedCoupons) > 0) {
                return $this->couponResponse(false, 'Multiple coupons are not allowed for this order.', $request);
            }

            foreach ($appliedCoupons as $applied) {
                if (strtoupper($applied['code']) === $code) {
                    return $this->couponResponse(false, 'This coupon is already applied.', $request);
                }
            }

            // 1. Expiry Check
            if (!empty($coupon['expiry']) && strtotime($coupon['expiry']) < strtotime(date('Y-m-d'))) {
                return $this->couponResponse(false, 'This coupon has expired.', $request);
            }

            // 2. Min Spend Check
            $subtotal = round(get_falcon_cart_subtotal(), 2);
            $minSpend = !empty($coupon['min_spend']) ? round((float) $coupon['min_spend'], 2) : 0;

            if ($minSpend > 0 && $subtotal < $minSpend) {
                return $this->couponResponse(false, 'Minimum spend for this coupon is '.falcon_price_format($minSpend), $request);
            }

            // 3a. Total Usage Limit Check (global across all customers)
            $totalLimit = (int) ($coupon['total_usage_limit'] ?? 0);
            $usedCount = (int) ($coupon['used_count'] ?? 0);
            if ($totalLimit > 0 && $usedCount >= $totalLimit) {
                return $this->couponResponse(false, 'This coupon has reached its total usage limit.', $request);
            }

            // 3b. Per-User Usage Limit Check
            if (!empty($coupon['usage_limit'])) {
                $perUserLimit = (int) $coupon['usage_limit'];
                if (auth()->check()) {
                    $userUsageCount = Order::where('user_id', auth()->id())
                        ->where(function ($q) use ($code) {
                            $q->where('coupon_code', $code)
                                ->orWhere('coupon_code', 'like', $code.',%')
                                ->orWhere('coupon_code', 'like', '%, '.$code.',%')
                                ->orWhere('coupon_code', 'like', '%, '.$code);
                        })->count();
                    if ($userUsageCount >= $perUserLimit) {
                        return $this->couponResponse(false, 'You have already used this coupon the maximum number of times.', $request);
                    }
                } else {
                    $usedCoupons = Session::get('lazy_used_coupons', []);
                    if (($usedCoupons[$code] ?? 0) >= $perUserLimit) {
                        return $this->couponResponse(false, 'Usage limit reached for this coupon.', $request);
                    }
                }
            }

            // 4. Product/Category Restrictions
            $cart = Session::get('falcon_cart', []);
            $discount = get_falcon_coupon_discount_amount($coupon, $cart);
            // A Free Shipping coupon legitimately takes nothing off the cart — its value lands on
            // the shipping line — so it must not be rejected for having a zero discount.
            if ($discount <= 0 && ($coupon['type'] ?? '') !== 'free_shipping') {
                return $this->couponResponse(false, 'This coupon is not valid for the products in your cart.', $request);
            }

            // Success: Add to coupons array
            $appliedCoupons[] = [
                'code' => $coupon['code'],
                'type' => $coupon['type'] ?? 'percent',
                'amount' => $coupon['amount'] ?? ($coupon['discount'] ?? 0),
                'products' => $coupon['products'] ?? [],
                'categories' => $coupon['categories'] ?? [],
            ];

            Session::put('falcon_coupons', $appliedCoupons);
            Session::save();
            Session::forget('falcon_coupon'); // Ensure old singular key is gone

            return $this->couponResponse(true, 'Coupon applied successfully!', $request);

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'System Error: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'System Error: '.$e->getMessage());
        }
    }

    private function couponResponse($success, $message, $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($this->cartTotalsPayload([
                'success' => $success,
                'message' => $message,
                // A rejected coupon leaves the applied list untouched, so the rows stay as they are.
                'discount_html' => $success ? $this->getDiscountHtml() : '',
            ]), $success ? 200 : 422);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }

    private function getDiscountHtml()
    {
        $coupons = Session::get('falcon_coupons', []);
        if (empty($coupons)) {
            return '';
        }

        $cart = Session::get('falcon_cart', []);
        $isSequential = (int) get_shop_option('shop_coupon_stacking_policy', '1') == 1;
        $subtotal = get_falcon_cart_subtotal();
        $currentSubtotal = $subtotal;

        $html = '';
        foreach ($coupons as $coupon) {
            $discount = get_falcon_coupon_discount_amount($coupon, $cart, $isSequential ? $currentSubtotal : $subtotal);
            $currentSubtotal -= $discount;

            if ($discount > 0) {
                $html .= '
                    <tr class="coupon-row bg-emerald-50/5 border-b border-gray-100">
                        <th class="p-4 bg-gray-50 text-left font-bold text-emerald-700 w-1/3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                Coupon: '.$coupon['code'].'
                                <a href="'.route('shop.cart.coupon.remove').'?code='.urlencode($coupon['code']).'" class="text-rose-500 hover:text-rose-700 text-[10px] font-normal">[Remove]</a>
                            </div>
                        </th>
                        <td class="p-4 font-bold text-emerald-700">-'.falcon_price_format($discount).'</td>
                    </tr>';
            }
        }

        return $html;
    }

    /**
     * Take the stock this cart needs, atomically, before the order exists.
     *
     * Each claim is a single conditional UPDATE — `SET qty = qty - n WHERE id = ? AND qty >= n`.
     * Two customers racing for the last unit cannot both succeed: whichever UPDATE lands second
     * matches no rows and is turned away. Reading the quantity and then writing it back, which is
     * what the old post-order decrement did, let both of them through.
     *
     * A product that accepts backorders drops the `qty >= n` guard, so its stock is allowed to go
     * negative — that is exactly what backordering means.
     *
     * @return array{0: bool, 1: string, 2: array} [ok, error message, claims to release on failure]
     */
    private function claimCartStock(array $cart): array
    {
        $claimed = [];

        foreach ($cart as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $product = Product::with('shopData')->find($item['id'] ?? 0);
            $shopData = $product?->shopData;

            // Which row actually tracks stock for this line.
            $table = $id = null;
            if (!empty($item['variation_id'])) {
                $variation = ProductVariation::find($item['variation_id']);
                if ($variation && $variation->manage_stock) {
                    [$table, $id] = ['shop_product_variations', $variation->id];
                }
            } elseif ($shopData && $shopData->manage_stock) {
                [$table, $id] = ['shop_products', $shopData->id];
            }

            if (!$table) {
                continue;   // stock is not managed for this line — nothing to reserve
            }

            $allowsBackorder = $shopData && method_exists($shopData, 'allowsBackorders') && $shopData->allowsBackorders();

            $query = DB::table($table)->where('id', $id);
            if (!$allowsBackorder) {
                $query->where('stock_quantity', '>=', $qty);
            }

            // $qty is an int, so the raw expression carries no user input.
            $affected = $query->update([
                'stock_quantity' => DB::raw('stock_quantity - '.$qty),
                'updated_at' => now(),
            ]);

            if ($affected !== 1) {
                $this->releaseClaimedStock($claimed);
                $name = $item['name'] ?? 'An item';

                return [false, 'Sorry, "'.$name.'" is no longer available in the quantity you asked for. Someone else may have just bought it — please adjust your cart and try again.', []];
            }

            $claimed[] = [
                'table' => $table,
                'id' => $id,
                'qty' => $qty,
                'title' => $product->title ?? ($item['name'] ?? 'Product'),
            ];
        }

        return [true, '', $claimed];
    }

    /** Hand back stock claimed earlier in a checkout that could not be completed. */
    private function releaseClaimedStock(array $claims): void
    {
        foreach ($claims as $claim) {
            DB::table($claim['table'])
                ->where('id', $claim['id'])
                ->update([
                    'stock_quantity' => DB::raw('stock_quantity + '.(int) $claim['qty']),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * The "you qualify — add this to claim it" prompts.
     *
     * Rendered from the same partial the cart page includes on load, so the AJAX version and the
     * server-rendered one can never drift apart.
     */
    private function getPromotionOfferHtml(): string
    {
        try {
            return view('falcon-cms::frontend.promotion-offers', [
                'offers' => falcon_pending_promotion_offers(),
            ])->render();
        } catch (\Throwable $e) {
            Log::error('Promotion offer render failed: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Rows for the automatic promotions this cart currently earns.
     *
     * Rendered server-side like the coupon rows so the storefront never has to know the rules —
     * it only paints what the engine says the customer is entitled to right now.
     */
    private function getPromotionHtml(): string
    {
        $html = '';

        foreach (falcon_evaluate_promotions() as $promo) {
            $html .= '
                    <tr class="promotion-row bg-amber-50/40 border-b border-gray-100">
                        <th class="p-4 bg-gray-50 text-left font-bold text-amber-700 w-1/3">
                            <div class="flex items-center gap-2">
                                <span>&#127873;</span>
                                <span>'.e($promo['name']).'</span>
                            </div>
                            <div class="text-[11px] font-normal text-amber-700/70 mt-0.5">'.e($promo['summary']).'</div>
                        </th>
                        <td class="p-4 font-bold text-amber-700">-'.falcon_price_format($promo['discount']).'</td>
                    </tr>';
        }

        return $html;
    }

    public function removeCoupon(Request $request)
    {
        $code = $request->get('code');
        if ($code) {
            $coupons = Session::get('falcon_coupons', []);
            $newCoupons = [];
            foreach ($coupons as $c) {
                if (strtoupper($c['code']) !== strtoupper($code)) {
                    $newCoupons[] = $c;
                }
            }
            Session::put('falcon_coupons', $newCoupons);
        } else {
            Session::forget('falcon_coupons');
        }
        Session::forget('falcon_coupon');

        return redirect()->back()->with('success', 'Coupon removed successfully!');
    }

    public function removeFromCart(Request $request, $key)
    {
        // Reject keys that don't look like our session keys (alphanumeric + dash/underscore, max 128 chars)
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,128}$/', $key)) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Invalid request.'], 422);
            }

            return redirect()->route('shop.cart');
        }

        $cart = Session::get('falcon_cart', []);
        if (isset($cart[$key])) {
            unset($cart[$key]);
            Session::put('falcon_cart', $cart);
            // Prices first: the coupon rules below (minimum spend, and the totals themselves)
            // have to be judged against what the catalogue charges today, not what it charged
            // when the item went into the basket.
            falcon_refresh_cart_prices();
            $this->revalidateCoupon();
        }

        if ($request->ajax()) {
            return response()->json($this->cartTotalsPayload([
                'message' => 'Item removed from cart!',
            ]));
        }

        return redirect()->back()->with('success', 'Item removed from cart!');
    }

    /**
     * Revalidates applied coupon when cart is modified
     */
    private function revalidateCoupon()
    {
        $coupons = Session::get('falcon_coupons', []);
        if (empty($coupons)) {
            Session::forget('falcon_coupon');

            return;
        }

        $newCoupons = [];

        foreach ($coupons as $applied) {
            // Re-read from the source of truth each time: a coupon deleted, deactivated or
            // edited after it was applied must stop discounting the cart immediately.
            $couponData = falcon_find_coupon($applied['code'] ?? null);

            if (!$couponData) {
                continue;
            }

            // Check Min Spend
            $subtotal = round(get_falcon_cart_subtotal(), 2);
            $minSpend = !empty($couponData['min_spend']) ? round((float) $couponData['min_spend'], 2) : 0;

            if ($minSpend > 0 && $subtotal < $minSpend) {
                continue;
            }

            // Check Expiry
            if (!empty($couponData['expiry']) && strtotime($couponData['expiry']) < strtotime(date('Y-m-d'))) {
                continue;
            }

            // Check Product/Category Restrictions
            $cart = Session::get('falcon_cart', []);
            $discount = get_falcon_coupon_discount_amount($couponData, $cart);
            // Free Shipping coupons discount nothing off the cart by design — dropping them here
            // would silently un-apply them on the next page load.
            if ($discount <= 0 && ($couponData['type'] ?? '') !== 'free_shipping') {
                continue;
            }

            $newCoupons[] = [
                'code' => $couponData['code'],
                'type' => $couponData['type'] ?? 'percent',
                'amount' => $couponData['amount'] ?? ($couponData['discount'] ?? 0),
                'products' => $couponData['products'] ?? [],
                'categories' => $couponData['categories'] ?? [],
            ];
        }

        // Wipe if coupons are disabled globally
        if (get_shop_option('shop_enable_coupons', '1') !== '1') {
            Session::forget('falcon_coupons');
            Session::forget('falcon_coupon');
            Session::save();

            return;
        }

        Session::put('falcon_coupons', $newCoupons);
        Session::forget('falcon_coupon');

        // Prune if multiple not allowed anymore
        $isMultipleAllowed = (int) get_shop_option('shop_coupon_stacking_policy', '1') === 1;

        if (!$isMultipleAllowed) {
            $currentCoupons = Session::get('falcon_coupons', []);
            if (count($currentCoupons) > 1) {
                $keptCoupon = array_shift($currentCoupons);
                Session::put('falcon_coupons', [$keptCoupon]);
            }
        }
        Session::save();
    }

    public function checkout()
    {
        $this->validateCartItems();
        // Prices first: the coupon rules below (minimum spend, and the totals themselves)
        // have to be judged against what the catalogue charges today, not what it charged
        // when the item went into the basket.
        falcon_refresh_cart_prices();
        $this->revalidateCoupon();
        $cart = Session::get('falcon_cart', []);
        if (empty($cart)) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty!');
        }

        return view($this->resolveThemeView('checkout'), compact('cart'));
    }

    public function placeOrder(Request $request)
    {
        // The final word on price. Even if the cart page was rendered from a stale
        // session, the order is written from what the catalogue charges right now.
        falcon_refresh_cart_prices();

        $rules = [
            'billing_first_name' => 'required',
            'billing_last_name' => 'required',
            'billing_email' => 'required|email',
            'billing_phone' => 'required',
            'billing_address_1' => 'required',
            'billing_city' => 'required',
            'billing_state' => 'required',
            'billing_postcode' => 'required',
            'billing_country' => 'required',
            'payment_method' => 'required',
        ];

        // Under "Mandatory shipping to the billing address" the shipping fields are not offered,
        // and a request that carries them anyway is ignored outright — goods must go to the
        // address the payment was authorised against, whatever the form says.
        $shipToDifferent = falcon_allows_separate_shipping_address() && $request->has('ship_to_different_address');

        if ($shipToDifferent) {
            $rules['shipping_first_name'] = 'required';
            $rules['shipping_last_name'] = 'required';
            $rules['shipping_address_1'] = 'required';
            $rules['shipping_city'] = 'required';
            $rules['shipping_state'] = 'required';
            $rules['shipping_postcode'] = 'required';
            $rules['shipping_country'] = 'required';
        }

        $attributes = [
            'billing_first_name' => 'Billing First Name',
            'billing_last_name' => 'Billing Last Name',
            'billing_email' => 'Billing Email',
            'billing_phone' => 'Billing Phone',
            'billing_address_1' => 'Billing Street Address',
            'billing_city' => 'Billing City',
            'billing_state' => 'Billing State',
            'billing_postcode' => 'Billing ZIP Code',
            'billing_country' => 'Billing Country',
            'payment_method' => 'Payment Method',
            'shipping_first_name' => 'Shipping First Name',
            'shipping_last_name' => 'Shipping Last Name',
            'shipping_address_1' => 'Shipping Street Address',
            'shipping_city' => 'Shipping City',
            'shipping_state' => 'Shipping State',
            'shipping_postcode' => 'Shipping ZIP Code',
            'shipping_country' => 'Shipping Country',
        ];

        // Collect extra fields registered via falcon_billing_fields / lazy_shipping_fields hooks
        $allHookFields = array_merge(falcon_get_checkout_fields('billing'), falcon_get_checkout_fields('shipping'));
        $standardNames = falcon_standard_checkout_field_names();

        foreach ($allHookFields as $hf) {
            $hfName = $hf['name'] ?? '';
            if (!$hfName || in_array($hfName, $standardNames)) {
                continue;
            }
            if (!empty($hf['required'])) {
                $rules[$hfName] = $hf['rules'] ?? 'required';
                $attributes[$hfName] = $hf['label'] ?? $hfName;
            }
        }

        $request->validate($rules, [], $attributes);

        // Handle guest checkout based on shop settings
        if (!auth()->check()) {
            $guestCheckoutEnabled = get_shop_option('shop_enable_guest_checkout', '1') === '1';
            $forceLogin = get_shop_option('shop_force_login_checkout', '0') === '1';
            $submittingPassword = $request->filled('account_password');

            // Block only pure guest orders (no password) when force login is required
            if ($forceLogin && !$submittingPassword) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Please log in or register to place an order.']);
                }

                return redirect()->back()->with('error', 'Please log in or register to place an order.');
            }

            // Create account when: guest checkout disabled (mandatory), user opted in, or force login requires it
            if (!$guestCheckoutEnabled || $request->boolean('create_account') || $forceLogin) {
                $request->validate([
                    'account_password' => 'required|min:6',
                ], [
                    'account_password.required' => 'Please enter a password to create your account.',
                    'account_password.min' => 'Password must be at least 6 characters.',
                ]);

                $existingUser = User::where('email', $request->billing_email)->first();
                if ($existingUser) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'An account with this email already exists. Please log in.']);
                    }

                    return redirect()->back()->with('error', 'An account with this email already exists. Please log in.');
                }

                $customerRole = Role::firstOrCreate(
                    ['slug' => 'customer'],
                    ['name' => 'Customer', 'description' => 'Customer who registered via store checkout or account.']
                );
                $newUser = User::create([
                    'name' => trim($request->billing_first_name.' '.$request->billing_last_name),
                    'email' => $request->billing_email,
                    'password' => Hash::make($request->account_password),
                    'role_id' => $customerRole->id,
                ]);
                auth()->login($newUser);
            }
        }

        $cart = Session::get('falcon_cart', []);
        if (empty($cart)) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty!');
        }

        // Duplicate order guard: same email + same cart total, pending/processing, within 60 s
        $dupExists = Order::where('customer_email', $request->billing_email)
            ->whereIn('status', ['pending', 'processing'])
            ->where('total', round(get_falcon_cart_total(), 2))
            ->where('created_at', '>=', now()->subSeconds(60))
            ->exists();

        if ($dupExists) {
            $msg = 'It looks like this order was already submitted. Please check your email before trying again.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }

            return redirect()->back()->with('error', $msg);
        }

        $shippingCountry = $shipToDifferent ? $request->shipping_country : $request->billing_country;
        Session::put('falcon_shipping_country', $shippingCountry);

        // Re-validate the shipping choice at the moment of purchase: the session may have been
        // set when a different method was on offer, and the form field is customer-supplied.
        // An unavailable id is dropped, which makes the resolver fall back to delivery.
        $postedMethod = $request->input('shipping_method');
        if (is_string($postedMethod) && array_key_exists($postedMethod, falcon_shipping_methods($shippingCountry))) {
            Session::put('falcon_shipping_method', $postedMethod);
        } elseif (!array_key_exists((string) Session::get('falcon_shipping_method'), falcon_shipping_methods($shippingCountry))) {
            Session::forget('falcon_shipping_method');
        }

        // Stored before the totals are worked out: with "Calculate tax based on → billing
        // address" the tax engine needs the billing country, which only exists on this request.
        Session::put('falcon_billing_country', $request->billing_country);

        $subtotal = get_falcon_cart_subtotal();
        $shipping = get_falcon_cart_shipping($shippingCountry);
        $tax = get_falcon_cart_tax();
        $total = get_falcon_cart_total();

        // Coupon Logic for Multiple Coupons
        $coupons = Session::get('falcon_coupons', []);
        $single = Session::get('falcon_coupon');
        if ($single && empty($coupons)) {
            $coupons[] = $single;
        }

        $couponCodes = [];
        foreach ($coupons as $coupon) {
            $couponCodes[] = $coupon['code'];
        }

        // The same helper the cart total uses. The previous inline sum here ignored both the
        // stacking policy and each coupon's product/category restrictions, so the discount_total
        // written to the order could disagree with the total the customer was charged.
        //
        // Promotions are re-evaluated here, at the moment of purchase, rather than trusted from
        // anything the browser sent — a rule may have expired, hit its usage cap or stopped
        // matching since the cart page was rendered.
        $appliedPromotions = falcon_evaluate_promotions();
        $promotionTotal = 0.0;
        foreach ($appliedPromotions as $applied) {
            $promotionTotal += $applied['discount'];
        }

        $discountTotal = falcon_cart_discount_total() + $promotionTotal;

        $orderData = [
            'user_id' => auth()->id(),
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'tax_total' => $tax,
            'discount_total' => $discountTotal,
            'coupon_code' => implode(', ', $couponCodes),
            'total' => $total,
            'first_name' => $request->billing_first_name,
            'last_name' => $request->billing_last_name,
            'customer_email' => $request->billing_email,
            'customer_phone' => $request->billing_phone,
            'address_line_1' => $request->billing_address_1,
            'address_line_2' => $request->billing_address_2,
            'city' => $request->billing_city,
            'state' => $request->billing_state,
            'postcode' => $request->billing_postcode,
            'country' => $request->billing_country,
            'payment_method' => $request->payment_method,
            'shipping_method' => get_falcon_cart_shipping_details($shippingCountry)['label'],
            'customer_note' => $request->order_comments,
            // Snapshot currency settings for historical accuracy
            'currency' => get_shop_option('shop_currency', 'USD'),
            'currency_symbol' => EcommerceData::getCurrencySymbol(get_shop_option('shop_currency', 'USD')),
            'currency_position' => get_shop_option('shop_currency_pos', 'left'),
            'thousand_separator' => get_shop_option('shop_thousand_sep', ','),
            'decimal_separator' => get_shop_option('shop_decimal_sep', '.'),
            'decimals' => (int) get_shop_option('shop_num_decimals', 2),
        ];

        if ($shipToDifferent) {
            $orderData['shipping_first_name'] = $request->shipping_first_name;
            $orderData['shipping_last_name'] = $request->shipping_last_name;
            $orderData['shipping_address_line_1'] = $request->shipping_address_1;
            $orderData['shipping_address_line_2'] = $request->shipping_address_2;
            $orderData['shipping_city'] = $request->shipping_city;
            $orderData['shipping_state'] = $request->shipping_state;
            $orderData['shipping_postcode'] = $request->shipping_postcode;
            $orderData['shipping_country'] = $request->shipping_country;
        }

        // Save custom checkout field values to order meta
        $customCheckout = [];
        foreach ($allHookFields as $hf) {
            $hfName = $hf['name'] ?? '';
            if (!$hfName || in_array($hfName, $standardNames)) {
                continue;
            }
            $val = $request->input($hfName);
            if ($val !== null && $val !== '') {
                $customCheckout[$hfName] = $val;
            }
        }
        $customCheckout = apply_falcon_filters('falcon_checkout_custom_fields', $customCheckout, $request);
        if (!empty($customCheckout)) {
            $orderData['meta'] = ['checkout_fields' => $customCheckout];
        }

        // Record which promotions this order earned and what each was worth, so the invoice and
        // the admin order screen can explain the discount long after the rule itself changes.
        if (!empty($appliedPromotions)) {
            $orderData['meta'] = array_merge($orderData['meta'] ?? [], [
                'promotions' => array_map(static fn (array $p) => [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'summary' => $p['summary'],
                    'discount' => $p['discount'],
                ], $appliedPromotions),
            ]);
        }

        // Reserve stock *before* the order exists: if the last unit has gone, the customer gets a
        // clear message instead of a confirmed order the shop cannot fulfil.
        [$stockOk, $stockError, $claimedStock] = $this->claimCartStock($cart);
        if (!$stockOk) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $stockError], 409);
            }

            return redirect()->back()->with('error', $stockError);
        }

        $order = Order::create($orderData);

        // Remember the address for next time. After the order, deliberately: a failure here must
        // never cost the customer their purchase.
        if (auth()->check() && $request->boolean('save_address')) {
            try {
                $this->rememberCustomerAddress($request);
            } catch (\Throwable $e) {
                Log::warning('Could not save the customer address for order #'.$order->order_number.': '.$e->getMessage());
            }
        }

        // Claim a use of each promotion. redeem() is a conditional UPDATE, so a usage cap holds
        // even when two checkouts complete at the same instant.
        foreach ($appliedPromotions as $applied) {
            $promo = Promotion::find($applied['id']);
            if ($promo && !$promo->redeem()) {
                Log::warning("Promotion #{$applied['id']} hit its usage limit while placing order #{$order->order_number}.");
            }
        }

        // Store order ID in session so the confirmation page can verify ownership for guests
        $request->session()->put('last_order_id', $order->id);

        // Redeem each applied coupon. Coupon::redeem() is a single conditional UPDATE, so the
        // global usage cap holds even when two checkouts complete at the same instant — the
        // previous read-modify-write on the settings blob could hand out the last use twice.
        foreach ($couponCodes as $couponCode) {
            $couponModel = Coupon::findByCode($couponCode);
            if ($couponModel && !$couponModel->redeem()) {
                Log::warning("Coupon {$couponCode} hit its usage limit while placing order #{$order->order_number}.");
            }
        }

        do_falcon_action('falcon_before_place_order', $order, $cart, $request);

        foreach ($cart as $item) {
            $itemMeta = $item['meta'] ?? [];
            $itemMeta = apply_falcon_filters('falcon_order_item_meta', $itemMeta, $item, $order);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'variation_id' => $item['variation_id'] ?? null,
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['sale_price'] ?? $item['price'],
                'subtotal' => ($item['sale_price'] ?? $item['price']) * $item['quantity'],
                'meta' => !empty($itemMeta) ? $itemMeta : null,
            ]);

            // Stock was already taken by claimCartStock(), atomically, before the order existed.
        }

        // Low/no-stock alerts, now that the quantities have settled.
        foreach ($claimedStock as $claim) {
            $remaining = DB::table($claim['table'])->where('id', $claim['id'])->value('stock_quantity');
            $this->maybeNotifyStock($claim['title'], (int) $remaining);
        }

        // Nothing to collect — a fully-discounted cart, a free product, or a 100% coupon.
        // Card processors reject a zero-value charge, so sending this to a gateway would strand
        // the customer on a payment page and leave the order stuck on "pending" forever.
        if ((float) $order->total <= 0) {
            $this->markOrderPaid($order, null, 'processing');

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order placed successfully!',
                    'redirect' => route('shop.confirmation', $order->id),
                    'order_id' => $order->id,
                ]);
            }

            return redirect()->route('shop.confirmation', $order->id)
                ->with('success', 'Order placed successfully! Nothing was due, so no payment was needed.');
        }

        // Online gateways: send the customer to the gateway to pay before finalizing.
        $gateways = falcon_enabled_payment_gateways();
        $gateway = $gateways[$order->payment_method] ?? null;

        if ($gateway && $gateway['type'] === 'online') {
            // Stripe → inline card (Stripe Elements). Create a PaymentIntent; the card is confirmed on-page.
            if ($order->payment_method === 'stripe') {
                $intent = $this->createStripePaymentIntent($order);
                if ($intent) {
                    $order->update(['transaction_id' => $intent['id']]);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'stripe_payment' => true,
                            'client_secret' => $intent['client_secret'],
                            'return_url' => route('shop.payment.return', $order->id).'?gateway=stripe&payment_intent='.$intent['id'],
                            'order_id' => $order->id,
                        ]);
                    }

                    return redirect()->route('shop.confirmation', $order->id)->with('error', 'JavaScript is required to pay by card.');
                }
            } else {
                // Redirect-based gateways (e.g. PayPal).
                try {
                    $payUrl = $this->initiatePayment($order, $order->payment_method);
                    if ($payUrl) {
                        if ($request->ajax() || $request->wantsJson()) {
                            return response()->json(['success' => true, 'redirect' => $payUrl, 'order_id' => $order->id]);
                        }

                        return redirect()->away($payUrl);
                    }
                } catch (\Exception $e) {
                    Log::error("Payment init failed for order #{$order->order_number}: ".$e->getMessage());
                }
            }
            // Gateway init failed → leave the order pending and inform the customer.
            $msg = 'We could not start the online payment. Your order was saved as pending — please contact us or try again.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'redirect' => route('shop.confirmation', $order->id), 'order_id' => $order->id, 'message' => $msg]);
            }

            return redirect()->route('shop.confirmation', $order->id)->with('error', $msg);
        }

        // Offline gateways (COD / Bank Transfer) → finalize immediately.
        $this->finalizeOrder($order);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'redirect' => route('shop.confirmation', $order->id),
                'order_id' => $order->id,
            ]);
        }

        return redirect()->route('shop.confirmation', $order->id)->with('success', 'Order placed successfully!');
    }

    /**
     * Send notification emails and clear the cart/coupons for a completed checkout.
     */
    private function finalizeOrder(Order $order): void
    {
        try {
            Mail::to($order->customer_email)->send(new OrderNotificationMail($order, 'placed'));
        } catch (\Exception $e) {
            Log::error("Order #{$order->order_number} email failed: ".$e->getMessage());
        }

        $adminRecipient = get_shop_option('shop_email_admin_recipient');
        if (!empty($adminRecipient)) {
            try {
                Mail::to($adminRecipient)->send(new OrderNotificationMail($order, 'placed', 'New Order Received'));
            } catch (\Exception $e) {
                Log::error("Order #{$order->order_number} admin email failed: ".$e->getMessage());
            }
        }

        $this->generateDownloadTokens($order);

        Session::forget('falcon_cart');
        Session::forget('falcon_coupon');
        Session::forget('falcon_coupons');
        // The next order starts from the shop's defaults rather than inheriting this one's choice.
        Session::forget('falcon_shipping_method');
    }

    private function generateDownloadTokens(Order $order): void
    {
        try {
            $items = $order->items()->with(['product.shopData.downloads'])->get();
            foreach ($items as $item) {
                $shopData = $item->product?->shopData;
                if (!$shopData || !$shopData->is_downloadable) {
                    continue;
                }
                $files = $shopData->downloads;
                if ($files->isEmpty()) {
                    continue;
                }

                $expiryDays = $shopData->download_expiry_days;
                $expiresAt = $expiryDays ? now()->addDays($expiryDays) : null;

                foreach ($files as $file) {
                    OrderDownload::create([
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'product_download_id' => $file->id,
                        'token' => Str::random(48),
                        'expires_at' => $expiresAt,
                        'download_limit' => $file->download_limit,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Download token generation failed for order #'.$order->id.': '.$e->getMessage());
        }
    }

    /**
     * Start an online payment and return the URL to redirect the customer to.
     * Returns null if the gateway is unsupported / misconfigured.
     */
    /**
     * Smallest-unit amount for the order total in the shop currency (handles zero-decimal currencies).
     */
    private function stripeAmount(Order $order): int
    {
        $currency = strtolower(get_shop_option('shop_currency', 'usd'));
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        return in_array($currency, $zeroDecimal, true) ? (int) round($order->total) : (int) round($order->total * 100);
    }

    /**
     * Create a Stripe PaymentIntent for inline (Stripe Elements) card payment.
     * Returns ['id','client_secret'] or null on failure.
     */
    private function createStripePaymentIntent(Order $order): ?array
    {
        $secret = get_shop_option('shop_payment_stripe_secret');
        if (!$secret) {
            return null;
        }

        $resp = falcon_gateway_http(fn ($h) => $h->asForm()->withToken($secret)->post('https://api.stripe.com/v1/payment_intents', [
            'amount' => $this->stripeAmount($order),
            'currency' => strtolower(get_shop_option('shop_currency', 'usd')),
            'description' => 'Order '.$order->order_number,
            'receipt_email' => $order->customer_email,
            'payment_method_types[0]' => 'card',
            'metadata[order_id]' => (string) $order->id,
            'metadata[order_number]' => $order->order_number,
        ]));

        if ($resp && $resp->successful() && !empty($resp->json('client_secret'))) {
            return ['id' => $resp->json('id'), 'client_secret' => $resp->json('client_secret')];
        }
        Log::error('Stripe PaymentIntent error: '.($resp ? $resp->body() : 'connection failed'));

        return null;
    }

    private function initiatePayment(Order $order, string $method): ?string
    {
        if ($method === 'paypal') {
            // PayPal Standard (email based) — redirect with a hosted button form via query string.
            $email = get_shop_option('shop_payment_paypal_email');
            if (!$email) {
                return null;
            }
            $sandbox = get_shop_option('shop_payment_paypal_sandbox') === '1';
            $base = $sandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
            $params = http_build_query([
                'cmd' => '_xclick',
                'business' => $email,
                'item_name' => 'Order '.$order->order_number,
                'amount' => number_format((float) $order->total, 2, '.', ''),
                'currency_code' => strtoupper(get_shop_option('shop_currency', 'USD')),
                'custom' => $order->id,
                'return' => route('shop.payment.return', $order->id).'?gateway=paypal',
                'cancel_return' => route('shop.payment.cancel', $order->id).'?gateway=paypal',
                'notify_url' => route('shop.payment.return', $order->id).'?gateway=paypal&ipn=1',
                'no_shipping' => 1,
            ]);

            return $base.'?'.$params;
        }

        if ($method === 'sslcommerz') {
            $storeId = get_shop_option('shop_payment_sslcommerz_store_id');
            $storePass = get_shop_option('shop_payment_sslcommerz_store_pass');
            if (!$storeId || !$storePass) {
                return null;
            }
            $sandbox = get_shop_option('shop_payment_sslcommerz_sandbox') === '1';
            $apiUrl = $sandbox
                ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
                : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

            $resp = falcon_gateway_http(fn ($h) => $h->asForm()->post($apiUrl, [
                'store_id' => $storeId,
                'store_passwd' => $storePass,
                'total_amount' => number_format((float) $order->total, 2, '.', ''),
                'currency' => strtoupper(get_shop_option('shop_currency', 'BDT')),
                'tran_id' => $order->order_number,
                'success_url' => route('shop.payment.return', $order->id).'?gateway=sslcommerz',
                'fail_url' => route('shop.payment.cancel', $order->id).'?gateway=sslcommerz',
                'cancel_url' => route('shop.payment.cancel', $order->id).'?gateway=sslcommerz',
                'ipn_url' => route('shop.payment.return', $order->id).'?gateway=sslcommerz&ipn=1',
                'shipping_method' => 'NO',
                'product_name' => 'Order '.$order->order_number,
                'product_category' => 'General',
                'product_profile' => 'general',
                'cus_name' => trim($order->first_name.' '.$order->last_name),
                'cus_email' => $order->customer_email,
                'cus_phone' => $order->customer_phone,
                'cus_add1' => $order->address_line_1,
                'cus_city' => $order->city,
                'cus_country' => $order->country,
            ]));

            if ($resp && $resp->successful() && $resp->json('status') === 'SUCCESS' && !empty($resp->json('GatewayPageURL'))) {
                $order->update(['transaction_id' => $resp->json('sessionkey')]);

                return $resp->json('GatewayPageURL');
            }
            Log::error('SSLCommerz session error: '.($resp ? $resp->body() : 'connection failed'));

            return null;
        }

        return null;
    }

    /**
     * Customer returns from an online gateway. Verify and finalize the order.
     */
    public function paymentReturn(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Verify the returning user owns this order
        if (auth()->check()) {
            if ((int) $order->user_id !== auth()->id() && $order->customer_email !== auth()->user()->email) {
                abort(403);
            }
        } else {
            if ((int) session('last_order_id') !== (int) $id) {
                abort(403);
            }
        }

        $gateway = $request->get('gateway');

        // Already finalized
        if ($order->paid_at) {
            return redirect()->route('shop.confirmation', $order->id);
        }

        $paid = false;

        if ($gateway === 'stripe') {
            $secret = get_shop_option('shop_payment_stripe_secret');
            $paymentIntent = $request->get('payment_intent');
            $sessionId = $request->get('session_id');
            if ($secret && $paymentIntent) {
                // Inline (Stripe Elements) — verify the PaymentIntent.
                $resp = falcon_gateway_http(fn ($h) => $h->withToken($secret)->get('https://api.stripe.com/v1/payment_intents/'.$paymentIntent));
                if ($resp && $resp->successful() && $resp->json('status') === 'succeeded') {
                    $paid = true;
                    $order->update(['transaction_id' => $paymentIntent]);
                }
            } elseif ($secret && $sessionId) {
                // Hosted Stripe Checkout (fallback) — verify the session.
                $resp = falcon_gateway_http(fn ($h) => $h->withToken($secret)->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId));
                if ($resp && $resp->successful() && $resp->json('payment_status') === 'paid') {
                    $paid = true;
                    $order->update(['transaction_id' => $resp->json('payment_intent') ?: $sessionId]);
                }
            }
        } elseif ($gateway === 'sslcommerz') {
            $storeId = get_shop_option('shop_payment_sslcommerz_store_id');
            $storePass = get_shop_option('shop_payment_sslcommerz_store_pass');
            $valId = $request->input('val_id');
            $postedStatus = $request->input('status');
            if ($storeId && $storePass && $valId && in_array($postedStatus, ['VALID', 'VALIDATED'], true)) {
                $sandbox = get_shop_option('shop_payment_sslcommerz_sandbox') === '1';
                $valApi = $sandbox
                    ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
                    : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
                $resp = falcon_gateway_http(fn ($h) => $h->get($valApi, [
                    'val_id' => $valId,
                    'store_id' => $storeId,
                    'store_passwd' => $storePass,
                    'format' => 'json',
                ]));
                if ($resp && $resp->successful()) {
                    $st = $resp->json('status');
                    $amt = (float) $resp->json('amount');
                    // Confirm the gateway validated it AND the amount matches the order total.
                    if (in_array($st, ['VALID', 'VALIDATED'], true) && $amt >= (float) $order->total - 0.5) {
                        $paid = true;
                        $order->update(['transaction_id' => $resp->json('bank_tran_id') ?: $valId]);
                    }
                }
            }
        } elseif ($gateway === 'paypal') {
            // PayPal Standard cannot be verified server-side without IPN; mark as awaiting confirmation.
            return redirect()->route('shop.confirmation', $order->id)
                ->with('success', 'Thank you! Your PayPal payment is being confirmed and your order will update shortly.');
        }

        if ($paid) {
            // markOrderPaid() claims the order atomically — the Stripe webhook fires for the same
            // payment at almost the same moment, and finalizing twice would double-send the
            // customer's order email and mint a second set of download tokens.
            $this->markOrderPaid($order);

            return redirect()->route('shop.confirmation', $order->id)->with('success', 'Payment successful! Your order is confirmed.');
        }

        return redirect()->route('shop.confirmation', $order->id)
            ->with('error', 'We could not confirm your payment yet. If you were charged, please contact us.');
    }

    /**
     * Flip an unpaid order to paid and run the post-payment side effects, exactly once.
     *
     * The conditional UPDATE is the lock: whichever of the browser return and the Stripe
     * webhook reaches it first matches `paid_at IS NULL` and gets 1 affected row; the loser
     * gets 0 and skips finalisation. Doing this with a read-then-write would let both pass.
     *
     * @return bool true only for the caller that actually claimed the order.
     */
    private function markOrderPaid(Order $order, ?string $transactionId = null, string $status = 'processing'): bool
    {
        $updates = ['status' => $status, 'paid_at' => now(), 'updated_at' => now()];
        if ($transactionId) {
            $updates['transaction_id'] = $transactionId;
        }

        if (Order::whereKey($order->getKey())->whereNull('paid_at')->update($updates) !== 1) {
            return false;
        }

        $order->refresh();
        $this->finalizeOrder($order);

        return true;
    }

    /**
     * Stripe webhook receiver.
     *
     * Without this, a payment is only ever confirmed by the customer's browser coming back to
     * the return URL — so closing the tab straight after paying leaves a charged customer with
     * an order stuck on "pending". Stripe retries webhook deliveries, so this is the reliable
     * side of the confirmation. It also picks up refunds issued from the Stripe dashboard.
     *
     * The endpoint is unauthenticated by necessity; the signature check below is what
     * establishes that a request really came from Stripe.
     */
    public function stripeWebhook(Request $request)
    {
        $secret = get_shop_option('shop_payment_stripe_webhook_secret');

        // No signing secret configured → refuse to process anything. Answering 404 (rather than
        // 200) surfaces the misconfiguration in the Stripe dashboard instead of hiding it.
        if (empty($secret)) {
            abort(404);
        }

        $payload = $request->getContent();

        if (!$this->stripeSignatureIsValid($payload, (string) $request->header('Stripe-Signature'), (string) $secret)) {
            Log::warning('Stripe webhook rejected: signature verification failed.');

            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type']) || !isset($event['data']['object'])) {
            return response('Malformed payload', 400);
        }

        $object = (array) $event['data']['object'];

        try {
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->stripeHandlePaid($object);
                    break;
                case 'payment_intent.payment_failed':
                case 'payment_intent.canceled':
                    $this->stripeHandleFailed($object);
                    break;
                case 'charge.refunded':
                    $this->stripeHandleRefunded($object);
                    break;
                    // Anything else is acknowledged and ignored — Stripe sends far more event
                    // types than a shop needs, and 2xx stops it retrying them forever.
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler failed ('.$event['type'].'): '.$e->getMessage());

            // 500 asks Stripe to retry with backoff rather than dropping the event.
            return response('Handler error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Verify Stripe's `Stripe-Signature` header against the raw request body.
     *
     * Follows Stripe's scheme: signed payload is "<timestamp>.<raw body>", HMAC-SHA256 with the
     * endpoint signing secret, compared in constant time. The timestamp tolerance is what stops
     * a captured-and-replayed webhook from being accepted later.
     */
    private function stripeSignatureIsValid(string $payload, string $header, string $secret, int $toleranceSeconds = 300): bool
    {
        if ($header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || !ctype_digit($timestamp) || empty($signatures)) {
            return false;
        }

        // Replay protection — reject anything outside the tolerance window in either direction.
        if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the order a Stripe object belongs to.
     *
     * Prefers the metadata order id we set when creating the PaymentIntent, and falls back to
     * matching the stored transaction reference.
     */
    private function stripeResolveOrder(array $object): ?Order
    {
        $orderId = $object['metadata']['order_id'] ?? null;
        if (is_scalar($orderId) && ctype_digit((string) $orderId)) {
            $order = Order::find((int) $orderId);
            if ($order) {
                return $order;
            }
        }

        // charge.* objects carry the intent under `payment_intent`; payment_intent.* are the intent.
        $reference = $object['payment_intent'] ?? ($object['id'] ?? null);

        return is_string($reference) && $reference !== ''
            ? Order::where('transaction_id', $reference)->first()
            : null;
    }

    private function stripeHandlePaid(array $intent): void
    {
        $order = $this->stripeResolveOrder($intent);
        if (!$order || $order->paid_at) {
            return;
        }

        // Defence in depth: only settle the order if the money Stripe actually received covers
        // it, in the shop's own currency. Guards against an intent being pointed at the wrong
        // order by a mismatched or stale metadata value.
        $received = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
        $currency = strtolower((string) ($intent['currency'] ?? ''));
        $expected = $this->stripeAmount($order);

        if ($currency !== strtolower(get_shop_option('shop_currency', 'usd')) || $received < $expected) {
            Log::warning("Stripe webhook: intent for order #{$order->order_number} did not match "
                ."(received {$received} {$currency}, expected {$expected}).");

            return;
        }

        if ($this->markOrderPaid($order, (string) ($intent['id'] ?? '') ?: null)) {
            Log::info("Stripe webhook: order #{$order->order_number} confirmed as paid.");
        }
    }

    private function stripeHandleFailed(array $intent): void
    {
        $order = $this->stripeResolveOrder($intent);
        // Never touch an order that is already paid — a later failed intent (e.g. a retried card)
        // must not undo a successful payment.
        if (!$order || $order->paid_at || $order->status !== 'pending') {
            return;
        }

        $oldStatus = $order->status;
        $order->update(['status' => 'failed']);
        $this->syncOrderInventory($order, $oldStatus, 'failed');
    }

    /**
     * Mirror a refund made in the Stripe dashboard back into the order.
     *
     * `amount_refunded` is cumulative, so comparing against what we already recorded makes this
     * idempotent: repeat deliveries of the same event change nothing, and a refund issued from
     * our own admin screen (which records the amount first) is not double-counted.
     */
    private function stripeHandleRefunded(array $charge): void
    {
        $order = $this->stripeResolveOrder($charge);
        if (!$order) {
            return;
        }

        $currency = strtolower((string) ($charge['currency'] ?? ''));
        if ($currency !== strtolower(get_shop_option('shop_currency', 'usd'))) {
            return;
        }

        $refunded = $this->stripeToMajorUnits((int) ($charge['amount_refunded'] ?? 0));
        $recorded = (float) ($order->refunded_amount ?? 0);

        // Only move forward, and never past the order total.
        $refunded = min($refunded, (float) $order->total);
        if ($refunded <= $recorded + 0.001) {
            return;
        }

        $log = $order->refund_log ?? [];
        $log[] = [
            'amount' => round($refunded - $recorded, 2),
            'at' => now()->utc()->toIso8601String(),
            'by' => 'Stripe (webhook)',
            'gateway' => 'stripe',
            'ref' => (string) ($charge['id'] ?? ''),
        ];

        $oldStatus = $order->status;
        $fully = $refunded >= (float) $order->total - 0.001;
        $newStatus = $fully ? 'refunded' : 'partially-refunded';

        $order->update([
            'refunded_amount' => round($refunded, 2),
            'refund_log' => $log,
            'status' => $newStatus,
        ]);

        // Only a full refund returns stock to inventory, matching the admin refund screen.
        $this->syncOrderInventory($order, $oldStatus, $newStatus);

        try {
            Mail::to($order->customer_email)->send(
                new OrderNotificationMail($order, 'status_updated', null, 'customer', round($refunded - $recorded, 2))
            );
        } catch (\Exception $e) {
            Log::error("Order #{$order->order_number} refund email failed: ".$e->getMessage());
        }
    }

    /** Inverse of stripeAmount(): smallest currency unit back to the shop's major unit. */
    private function stripeToMajorUnits(int $amount): float
    {
        $currency = strtolower(get_shop_option('shop_currency', 'usd'));
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        return in_array($currency, $zeroDecimal, true) ? (float) $amount : $amount / 100;
    }

    /**
     * Returns a safe redirect URL, rejecting anything pointing to an external host.
     * Allows relative paths and absolute URLs on the same host as the app only.
     */
    private function safeRedirectUrl(string $url): string
    {
        // Protocol-relative (//evil.com) and external hosts are rejected
        if (str_starts_with($url, '//')) {
            return url('/');
        }
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return url('/');
        }

        return $url;
    }

    public function accountLogout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $raw = $request->input('redirect_to') ?: url('/');
        $redirect = $this->safeRedirectUrl($raw);

        return redirect($redirect);
    }

    /**
     * Handle login form submitted from the customer account page.
     * On success redirects back to the same page (now authenticated).
     */
    public function accountLogin(Request $request)
    {
        $request->validate([
            'account_email' => 'required|email',
            'account_password' => 'required',
        ], [
            'account_email.required' => 'Please enter your email address.',
            'account_email.email' => 'Please enter a valid email address.',
            'account_password.required' => 'Please enter your password.',
        ]);

        $credentials = [
            'email' => $request->input('account_email'),
            'password' => $request->input('account_password'),
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $raw = $request->input('redirect_to') ?: url('/');
            $redirect = $this->safeRedirectUrl($raw);

            return redirect($redirect);
        }

        return back()
            ->withErrors(['account_email' => 'Invalid email or password. Please try again.'])
            ->onlyInput('account_email');
    }

    public function updateProfile(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->back();
        }

        $user = auth()->user();
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user->update([
            'name' => $request->name,
        ]);

        return redirect()->back()->with('profile_success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->back();
        }

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ], [
            'password.min' => 'New password must be at least 8 characters.',
            'password.confirmed' => 'New password confirmation does not match.',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->back()->with('password_success', 'Password updated successfully.');
    }

    /**
     * Create or update one of the signed-in customer's saved addresses.
     *
     * Every lookup is scoped to auth()->id(): the id in the request is a claim, not a permission,
     * so an address belonging to someone else simply is not found.
     */
    public function saveAddress(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->back();
        }

        $validated = $request->validate([
            'address_id' => 'nullable|integer',
            'label' => 'nullable|string|max:60',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'address_1' => 'required|string|max:191',
            'address_2' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:191',
        ]);

        $userId = auth()->id();
        $data = collect($validated)->except('address_id')->all();
        $data['user_id'] = $userId;

        if (!empty($validated['address_id'])) {
            $address = CustomerAddress::where('user_id', $userId)
                ->find($validated['address_id']);

            if (!$address) {
                return redirect()->back()->withErrors(['address' => 'That address could not be found.']);
            }
            $address->update($data);
            $message = 'Address updated.';
        } else {
            // A cap keeps one account from filling the table; nobody legitimately needs 50.
            if (CustomerAddress::where('user_id', $userId)->count() >= 20) {
                return redirect()->back()->withErrors(['address' => 'You can save up to 20 addresses. Delete one to add another.']);
            }

            $address = CustomerAddress::create($data);
            $message = 'Address saved.';

            // The first address a customer saves is the one they mean.
            if (CustomerAddress::where('user_id', $userId)->count() === 1) {
                $address->update(['is_default_billing' => true, 'is_default_shipping' => true]);
            }
        }

        return redirect()->back()->with('address_success', $message);
    }

    /**
     * Store the billing address just used at checkout, unless the customer already has it.
     *
     * Matching is on the parts that identify a place, normalised for case and spacing — otherwise
     * every order would add a near-duplicate and the address book would become useless.
     */
    protected function rememberCustomerAddress(Request $request): void
    {
        if (!Schema::hasTable('shop_customer_addresses')) {
            return;
        }

        $data = [
            'first_name' => trim((string) $request->input('billing_first_name', '')),
            'last_name' => trim((string) $request->input('billing_last_name', '')),
            'country' => trim((string) $request->input('billing_country', '')),
            'address_1' => trim((string) $request->input('billing_address_1', '')),
            'address_2' => trim((string) $request->input('billing_address_2', '')),
            'city' => trim((string) $request->input('billing_city', '')),
            'state' => trim((string) $request->input('billing_state', '')),
            'postcode' => trim((string) $request->input('billing_postcode', '')),
            'phone' => trim((string) $request->input('billing_phone', '')),
            'email' => trim((string) $request->input('billing_email', '')),
        ];

        if ($data['first_name'] === '' || $data['address_1'] === '') {
            return;
        }

        $userId = auth()->id();
        $fingerprint = static fn (array $a): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim(implode('|', [
            $a['first_name'] ?? '', $a['last_name'] ?? '', $a['address_1'] ?? '', $a['address_2'] ?? '',
            $a['city'] ?? '', $a['state'] ?? '', $a['postcode'] ?? '', $a['country'] ?? '',
        ]))));

        $mine = $fingerprint($data);
        $existing = CustomerAddress::where('user_id', $userId)->get();
        foreach ($existing as $address) {
            if ($fingerprint($address->toArray()) === $mine) {
                return;
            }
        }

        // Same cap as the account page, so checkout cannot be used to get around it.
        if ($existing->count() >= 20) {
            return;
        }

        $data['user_id'] = $userId;
        $data['is_default_billing'] = $existing->isEmpty();
        $data['is_default_shipping'] = $existing->isEmpty();

        CustomerAddress::create($data);
    }

    public function deleteAddress(Request $request, $id)
    {
        if (!auth()->check()) {
            return redirect()->back();
        }

        $address = CustomerAddress::where('user_id', auth()->id())->find($id);
        if (!$address) {
            return redirect()->back()->withErrors(['address' => 'That address could not be found.']);
        }

        $wasBilling = $address->is_default_billing;
        $wasShipping = $address->is_default_shipping;
        $address->delete();

        // Never leave the customer with no default while they still have addresses.
        $next = CustomerAddress::where('user_id', auth()->id())->oldest('id')->first();
        if ($next) {
            $next->update(array_filter([
                'is_default_billing' => $wasBilling ?: null,
                'is_default_shipping' => $wasShipping ?: null,
            ]));
        }

        return redirect()->back()->with('address_success', 'Address deleted.');
    }

    public function setDefaultAddress(Request $request, $id)
    {
        if (!auth()->check()) {
            return redirect()->back();
        }

        $type = $request->input('type') === 'shipping' ? 'shipping' : 'billing';
        $column = 'is_default_'.$type;

        $userId = auth()->id();
        $address = CustomerAddress::where('user_id', $userId)->find($id);
        if (!$address) {
            return redirect()->back()->withErrors(['address' => 'That address could not be found.']);
        }

        // Exactly one default per type, so clear the rest in the same breath.
        DB::transaction(function () use ($userId, $column, $address) {
            CustomerAddress::where('user_id', $userId)->update([$column => false]);
            $address->update([$column => true]);
        });

        return redirect()->back()->with('address_success', 'Default address updated.');
    }

    public function checkMagicEmail(Request $request)
    {
        $email = strtolower(trim($request->input('email', '')));
        $exists = false;

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $exists = User::where('email', $email)->exists();
        }

        return response()->json(['exists' => $exists]);
    }

    public function requestMagicLink(Request $request)
    {
        if (!get_cms_option('magic_login_enabled')) {
            return redirect()->back()->withErrors(['magic_email' => 'Magic login is not enabled.']);
        }

        $request->validate(['magic_email' => 'required|email'], [
            'magic_email.required' => 'Please enter your email address.',
            'magic_email.email' => 'Please enter a valid email address.',
        ]);

        $email = strtolower(trim($request->magic_email));
        $user = User::where('email', $email)->first();

        // Always show the same success message — never confirm whether an email exists
        if ($user) {
            DB::table('magic_login_tokens')
                ->where('email', $email)
                ->where('used_at', null)
                ->delete();

            $rawToken = Str::random(48);
            $hash = hash('sha256', $rawToken);

            DB::table('magic_login_tokens')->insert([
                'email' => $email,
                'token' => $hash,
                'expires_at' => now()->addMinutes(10),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $magicUrl = route('shop.magic.verify', ['token' => $rawToken]);

            Mail::to($email)->send(
                new MagicLoginMail($magicUrl, $user->name)
            );
        }

        return redirect()->back()->with('magic_sent', true);
    }

    public function verifyMagicLink(Request $request, string $token)
    {
        $accountPageId = get_shop_option('shop_account_page_id');
        $accountPage = $accountPageId ? Post::find($accountPageId) : null;
        $accountUrl = $accountPage ? url('/'.$accountPage->slug) : url('/');

        $hash = hash('sha256', $token);

        $row = DB::table('magic_login_tokens')
            ->where('token', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return redirect($accountUrl)
                ->withErrors(['account_email' => 'This magic link is invalid or has expired. Please request a new one.']);
        }

        DB::table('magic_login_tokens')
            ->where('token', $hash)
            ->update(['used_at' => now()]);

        $user = User::where('email', $row->email)->first();

        if (!$user) {
            return redirect($accountUrl)
                ->withErrors(['account_email' => 'No account found for this magic link.']);
        }

        auth()->login($user, false);

        return redirect($accountUrl)->with('magic_login_success', 'You have been signed in successfully.');
    }

    /**
     * Serve a digital download file via a secure one-time token.
     */
    public function downloadFile(Request $request, string $token)
    {
        $dl = OrderDownload::with('productDownload')
            ->where('token', $token)
            ->first();

        if (!$dl) {
            abort(404, 'Download link not found.');
        }
        if ($dl->isExpired()) {
            abort(410, 'This download link has expired.');
        }
        if ($dl->isExhausted()) {
            abort(410, 'Download limit reached for this file.');
        }

        $file = $dl->productDownload;
        if (!$file) {
            abort(404, 'File not found.');
        }

        // Files from media library live on the public disk; legacy uploads used local disk.
        if (str_starts_with($file->file_path, 'downloads/')) {
            $path = storage_path('app/'.$file->file_path);
        } else {
            $path = storage_path('app/public/'.$file->file_path);
        }

        if (!file_exists($path)) {
            abort(404, 'File not found on server.');
        }

        $dl->increment('download_count');

        $filename = $file->name ?: basename($file->file_path);

        return response()->download($path, $filename, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
        ]);
    }

    /**
     * Public order tracking — look up an order by number + email.
     */
    public function trackOrder(Request $request)
    {
        $order = null;
        $notFound = false;

        if ($request->isMethod('post') || ($request->filled('order_number') && $request->filled('email'))) {
            $request->validate([
                'order_number' => 'required|string',
                'email' => 'required|email',
            ], [], ['order_number' => 'Order Number', 'email' => 'Email']);

            $order = Order::with(['items.product.shopData', 'statusHistory'])
                ->where('order_number', trim($request->order_number))
                ->where('customer_email', trim($request->email))
                ->first();

            $notFound = !$order;
        }

        return view($this->resolveThemeView('track-order'), compact('order', 'notFound'));
    }

    /**
     * Customer cancelled / abandoned the online payment.
     */
    public function paymentCancel(Request $request, $id)
    {
        return redirect()->route('shop.checkout')->with('error', 'Payment was cancelled. Your order is saved as pending — you can try again.');
    }

    public function confirmation($id)
    {
        $order = Order::with('items')->findOrFail($id);

        if (auth()->check()) {
            // Logged-in user must own the order (by user_id or matching email for orders placed while logged out)
            if ((int) $order->user_id !== auth()->id() && $order->customer_email !== auth()->user()->email) {
                abort(403);
            }
        } else {
            // Guest: only allow if this is the order they just placed in this session
            if ((int) session('last_order_id') !== (int) $id) {
                abort(403);
            }
        }

        return view($this->resolveThemeView('confirmation'), compact('order'));
    }

    /**
     * Email the store admin when a product crosses the low/out-of-stock threshold
     * after a sale. Controlled by the Inventory settings (Shop → Settings → Products).
     * Fully guarded: a mail failure must never break checkout.
     */
    private function maybeNotifyStock(string $name, int $qty): void
    {
        try {
            $admin = get_shop_option('shop_email_admin_recipient') ?: get_shop_option('shop_email_from_address');
            if (!$admin) {
                return;
            }
            $out = (int) get_shop_option('shop_out_of_stock_threshold', '0');
            $low = (int) get_shop_option('shop_low_stock_threshold', '2');
            if ($qty <= $out && get_shop_option('shop_notification_no_stock', '1') === '1') {
                Mail::raw("Product \"{$name}\" is now OUT OF STOCK (remaining: {$qty}).", function ($m) use ($admin, $name) {
                    $m->to($admin)->subject('Out of stock: '.$name);
                });
            } elseif ($qty <= $low && get_shop_option('shop_notification_low_stock', '1') === '1') {
                Mail::raw("Product \"{$name}\" is running LOW on stock (remaining: {$qty}).", function ($m) use ($admin, $name) {
                    $m->to($admin)->subject('Low stock: '.$name);
                });
            }
        } catch (\Throwable $e) {
            // Notifications are best-effort; ignore failures.
        }
    }

    public function storeReview(Request $request)
    {
        // Respect the "Enable reviews" shop setting (Shop → Settings → Products).
        if (get_shop_option('shop_enable_reviews', '1') !== '1') {
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => 'Reviews are currently disabled.'], 403)
                : back()->with('error', 'Reviews are currently disabled.');
        }

        // Rating is only required when star ratings are enabled.
        $ratingOn = get_shop_option('shop_enable_review_rating', '1') === '1';

        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'parent_id' => 'nullable|exists:shop_reviews,id',
            'rating' => ($ratingOn ? 'required' : 'nullable').'|integer|min:1|max:5',
            'comment' => 'required|string|min:3',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $userId = auth()->id();
        $email = auth()->check() ? auth()->user()->email : ($validated['email'] ?? null);
        $name = auth()->check() ? auth()->user()->name : ($validated['name'] ?? 'Guest');

        // Check if this user/email already has at least one approved review (auto-approve logic)
        $isApproved = false;

        // Auto-approve if user is an admin
        if (auth()->check() && (auth()->user()->role && in_array(auth()->user()->role->slug, ['admin', 'super-admin']))) {
            $isApproved = true;
        } else {
            $query = Review::where('is_approved', true);
            if ($userId) {
                $isApproved = (clone $query)->where('user_id', $userId)->exists();
            } elseif ($email) {
                $isApproved = (clone $query)->where('email', $email)->exists();
            }
        }

        Review::create([
            'post_id' => $validated['post_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'rating' => $ratingOn ? ($validated['rating'] ?? 0) : 0,
            'comment' => $validated['comment'],
            'is_approved' => $isApproved,
        ]);

        $message = $isApproved ? 'Review posted successfully.' : 'Your review is awaiting moderation.';

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Ensures all items in cart are still valid and in stock
     */
    private function validateCartItems()
    {
        $cart = Session::get('falcon_cart', []);
        if (empty($cart)) {
            return;
        }

        $productIds = array_column($cart, 'id');
        // Fetch all products in cart with their shopData
        $products = Product::with('shopData')->whereIn('id', $productIds)->get()->keyBy('id');

        $updated = false;
        foreach ($cart as $key => $item) {
            $productId = $item['id'];

            // 1. Check if product exists and is published
            if (!isset($products[$productId])) {
                unset($cart[$key]);
                $updated = true;

                continue;
            }

            $product = $products[$productId];
            $shopData = $product->shopData;

            // 2. Check Stock
            if ($shopData) {
                if ($shopData->stock_status === 'outofstock' || ($shopData->manage_stock && $shopData->stock_quantity <= 0)) {
                    unset($cart[$key]);
                    $updated = true;

                    continue;
                }

                // Adjust quantity if it exceeds available stock
                if ($shopData->manage_stock && $item['quantity'] > $shopData->stock_quantity) {
                    $cart[$key]['quantity'] = $shopData->stock_quantity;
                    $updated = true;
                }
            }
        }

        if ($updated) {
            Session::put('falcon_cart', $cart);
            Session::save();
        }
    }

    /**
     * Every figure the cart/checkout totals table can show, already formatted.
     *
     * One builder for all four cart endpoints (quantity change, item removal, coupon, shipping
     * update) so no endpoint can quietly leave a row behind — the tax line used to be missing
     * from the shipping response alone, which is why it only refreshed on a full page load.
     *
     * @param  array<string, mixed>  $extra  Endpoint-specific keys merged on top.
     */
    private function cartTotalsPayload(array $extra = []): array
    {
        $country = falcon_customer_shipping_country();
        $shipping = get_falcon_cart_shipping_details($country);
        $tax = falcon_tax_enabled() ? (float) get_falcon_cart_tax() : 0.0;

        // Shipping renders as its own label + amount ("Flat rate: ৳25.00"), or just the label
        // when it is free or not yet known.
        $shippingHtml = !empty($shipping['pending'])
            ? '<span class="text-gray-500">Enter your address to see shipping options.</span>'
            : ($shipping['cost'] > 0
                ? e($shipping['label']).': <span class="font-bold text-heading">'.falcon_price_format($shipping['cost']).'</span>'
                : '<span class="font-bold text-heading">'.e($shipping['label']).'</span>');

        return array_merge([
            'success' => true,
            'cart_count' => get_falcon_cart_count(),
            'subtotal' => falcon_price_format(get_falcon_cart_subtotal()),
            'shipping' => $shippingHtml,
            'shipping_method' => $shipping['method'] ?? 'delivery',
            'shipping_pending' => (bool) ($shipping['pending'] ?? false),
            'tax' => falcon_price_format($tax),
            'tax_label' => falcon_cart_tax_label(),
            'tax_included' => falcon_prices_include_tax(),
            // The row is hidden rather than removed, so the storefront only has to toggle it.
            'tax_visible' => falcon_tax_enabled() && $tax > 0,
            'total' => falcon_price_format(get_falcon_cart_total()),
            'discount_html' => $this->getDiscountHtml(),
            'promotion_html' => $this->getPromotionHtml(),
            // Qualifying for an offer can start or stop from a quantity change alone, so the
            // prompt travels with every cart update instead of waiting for a page reload.
            'offer_html' => $this->getPromotionOfferHtml(),
        ], $extra);
    }

    public function updateShipping(Request $request)
    {
        // Only overwrite the stored country when one was actually supplied — a method-only
        // update (the pickup/delivery radio) must not blank out the customer's chosen country
        // and silently drop them back to the flat rate.
        $country = $request->input('country');
        if (is_string($country) && $country !== '') {
            Session::put('falcon_shipping_country', $country);
        } else {
            $country = Session::get('falcon_shipping_country');
        }

        // Only the method *id* is accepted, and only if it is genuinely on offer for this cart.
        // Costs are never taken from the request — falcon_selected_shipping_method() recalculates
        // them server-side, so a forged "shipping is free" payload changes nothing.
        $requestedMethod = $request->input('shipping_method');
        if (is_string($requestedMethod) && array_key_exists($requestedMethod, falcon_shipping_methods($country))) {
            Session::put('falcon_shipping_method', $requestedMethod);
        }

        // Costs travel back already formatted so the storefront never has to price anything itself.
        $methods = [];
        foreach (falcon_shipping_methods($country) as $id => $method) {
            $methods[] = [
                'id' => $id,
                'label' => $method['label'],
                'cost' => $method['cost'] > 0 ? falcon_price_format($method['cost']) : 'Free',
            ];
        }

        // Full totals payload: changing country moves the tax rate as well as the shipping cost,
        // so subtotal, tax, discounts and total all have to travel back with it.
        return response()->json($this->cartTotalsPayload([
            'method' => get_falcon_cart_shipping_details($country)['method'],
            'methods' => $methods,
        ]));
    }
}
