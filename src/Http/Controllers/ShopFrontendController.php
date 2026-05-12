<?php

namespace Acme\CmsDashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Acme\CmsDashboard\Models\Post;
use Acme\CmsDashboard\Models\Product;
use Acme\CmsDashboard\Models\Order;
use Acme\CmsDashboard\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ShopFrontendController extends Controller
{
    protected function resolveThemeView($view)
    {
        $activeTheme = get_cms_option('active_theme', 'lazy-theme');
        $appView = "themes.{$activeTheme}.ecommerce.{$view}";
        if (view()->exists($appView)) return $appView;

        $packageView = "cms-dashboard::themes.{$activeTheme}.ecommerce.{$view}";
        if (view()->exists($packageView)) return $packageView;

        return "cms-dashboard::themes.lazy-theme.ecommerce.{$view}";
    }

    public function cart()
    {
        $this->validateCartItems();
        $this->revalidateCoupon();
        $cart = Session::get('lazy_cart', []);
        return view($this->resolveThemeView('cart'), compact('cart'));
    }

    public function addToCart(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $variationId = $request->input('variation_id');

        $product = Product::with('shopData')->findOrFail($productId);
        $shopData = $product->shopData;

        // Inventory Check
        if ($shopData && $shopData->manage_stock) {
            $cart = Session::get('lazy_cart', []);
            $cartKey = $variationId ? "{$productId}_{$variationId}" : $productId;
            $currentInCart = isset($cart[$cartKey]) ? $cart[$cartKey]['quantity'] : 0;
            
            if (($currentInCart + $quantity) > $shopData->stock_quantity) {
                $errorMsg = $shopData->stock_quantity <= 0 
                    ? 'Sorry, this product is currently out of stock.' 
                    : 'Sorry, only ' . $shopData->stock_quantity . ' items available in stock.';

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg
                    ], 422);
                }
                return redirect()->back()->with('error', $errorMsg);
            }
        }

        $cart = Session::get('lazy_cart', []);

        $cartKey = $variationId ? "{$productId}_{$variationId}" : $productId;

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'id' => $product->id,
                'name' => $product->title,
                'slug' => $product->slug,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'quantity' => $quantity,
                'thumbnail' => $product->featured_image,
                'variation_id' => $variationId,
                'sku' => $product->sku
            ];
        }

        Session::put('lazy_cart', $cart);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart!',
                'cart_count' => get_lazy_cart_count()
            ]);
        }

        return redirect()->to(get_lazy_cart_url())->with('success', 'Product added to cart!');
    }

    public function updateCart(Request $request)
    {
        $cart = Session::get('lazy_cart', []);
        $quantities = $request->input('quantity', []);

        foreach ($quantities as $key => $qty) {
            if (isset($cart[$key])) {
                if ($qty <= 0) {
                    unset($cart[$key]);
                } else {
                    $cart[$key]['quantity'] = (int)$qty;
                }
            }
        }

        Session::put('lazy_cart', $cart);
        $this->validateCartItems();
        $this->revalidateCoupon();

        if ($request->ajax()) {
            $item_subtotals = [];
            foreach ($cart as $key => $item) {
                $price = $item['sale_price'] ?? $item['price'];
                $item_subtotals[$key] = lazy_price_format($price * $item['quantity']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cart updated!',
                'cart_count' => get_lazy_cart_count(),
                'item_subtotals' => $item_subtotals,
                'subtotal' => lazy_price_format(get_lazy_cart_subtotal()),
                'shipping' => lazy_price_format(get_lazy_cart_shipping()),
                'tax' => lazy_price_format(get_lazy_cart_tax()),
                'total' => lazy_price_format(get_lazy_cart_total()),
                'discount_html' => $this->getDiscountHtml()
            ]);
        }

        return redirect()->back()->with('success', 'Cart updated!');
    }

    public function applyCoupon(Request $request)
    {
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

            $coupons = json_decode(get_cms_option('shop_coupons', '[]'), true) ?: [];
            
            if (empty($coupons)) {
                return $this->couponResponse(false, 'No coupons available.', $request);
            }

            $coupon = null;
            foreach ($coupons as $c) {
                if (strtoupper($c['code'] ?? '') === $code) {
                    $coupon = $c;
                    break;
                }
            }

            if (!$coupon) {
                return $this->couponResponse(false, 'Invalid coupon code.', $request);
            }

            // Check if multiple coupons are allowed
            $isMultipleAllowed = (int)get_cms_option('shop_coupon_stacking_policy', '1') === 1;
            $appliedCoupons = Session::get('lazy_coupons', []);
            
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
            $subtotal = round(get_lazy_cart_subtotal(), 2);
            $minSpend = !empty($coupon['min_spend']) ? round((float)$coupon['min_spend'], 2) : 0;
            
            if ($minSpend > 0 && $subtotal < $minSpend) {
                return $this->couponResponse(false, 'Minimum spend for this coupon is ' . lazy_price_format($minSpend), $request);
            }

            // 3. Usage Limit Check
            $usedCoupons = Session::get('lazy_used_coupons', []);
            $usageCount = $usedCoupons[$code] ?? 0;
            if (!empty($coupon['usage_limit']) && $usageCount >= (int)$coupon['usage_limit']) {
                return $this->couponResponse(false, 'Usage limit reached for this coupon.', $request);
            }

            // 4. Product/Category Restrictions
            $cart = Session::get('lazy_cart', []);
            $discount = get_lazy_coupon_discount_amount($coupon, $cart);
            if ($discount <= 0) {
                return $this->couponResponse(false, 'This coupon is not valid for the products in your cart.', $request);
            }

            // Success: Add to coupons array
            $appliedCoupons[] = [
                'code' => $coupon['code'],
                'type' => $coupon['type'] ?? 'percent',
                'amount' => $coupon['amount'] ?? ($coupon['discount'] ?? 0),
                'products' => $coupon['products'] ?? [],
                'categories' => $coupon['categories'] ?? []
            ];
            
            Session::put('lazy_coupons', $appliedCoupons);
            Session::save();
            Session::forget('lazy_coupon'); // Ensure old singular key is gone

            return $this->couponResponse(true, 'Coupon applied successfully!', $request);

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'System Error: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'System Error: ' . $e->getMessage());
        }
    }

    private function couponResponse($success, $message, $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'subtotal' => lazy_price_format(get_lazy_cart_subtotal()),
                'shipping' => lazy_price_format(get_lazy_cart_shipping()),
                'tax' => lazy_price_format(get_lazy_cart_tax()),
                'total' => lazy_price_format(get_lazy_cart_total()),
                'discount_html' => $success ? $this->getDiscountHtml() : ''
            ], $success ? 200 : 422);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }

    private function getDiscountHtml()
    {
        $coupons = Session::get('lazy_coupons', []);
        if (empty($coupons)) return '';
        
        $cart = Session::get('lazy_cart', []);
        $isSequential = (int)get_shop_option('shop_coupon_stacking_policy', '1') == 1;
        $subtotal = get_lazy_cart_subtotal();
        $currentSubtotal = $subtotal;
        
        $html = '';
        foreach ($coupons as $coupon) {
            $discount = get_lazy_coupon_discount_amount($coupon, $cart, $isSequential ? $currentSubtotal : $subtotal);
            $currentSubtotal -= $discount;

            if ($discount > 0) {
                $html .= '
                    <tr class="coupon-row bg-emerald-50/5 border-b border-gray-100">
                        <th class="p-4 bg-gray-50 text-left font-bold text-emerald-700 w-1/3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                Coupon: ' . $coupon['code'] . '
                                <a href="' . route('shop.cart.coupon.remove') . '?code=' . urlencode($coupon['code']) . '" class="text-rose-500 hover:text-rose-700 text-[10px] font-normal">[Remove]</a>
                            </div>
                        </th>
                        <td class="p-4 font-bold text-emerald-700">-' . lazy_price_format($discount) . '</td>
                    </tr>';
            }
        }

        return $html;
    }

    public function removeCoupon(Request $request)
    {
        $code = $request->get('code');
        if ($code) {
            $coupons = Session::get('lazy_coupons', []);
            $newCoupons = [];
            foreach ($coupons as $c) {
                if (strtoupper($c['code']) !== strtoupper($code)) {
                    $newCoupons[] = $c;
                }
            }
            Session::put('lazy_coupons', $newCoupons);
        } else {
            Session::forget('lazy_coupons');
        }
        Session::forget('lazy_coupon');
        
        return redirect()->back()->with('success', 'Coupon removed successfully!');
    }

    public function removeFromCart(Request $request, $key)
    {
        $cart = Session::get('lazy_cart', []);
        if (isset($cart[$key])) {
            unset($cart[$key]);
            Session::put('lazy_cart', $cart);
            $this->revalidateCoupon();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart!',
                'cart_count' => get_lazy_cart_count(),
                'subtotal' => lazy_price_format(get_lazy_cart_subtotal()),
                'shipping' => lazy_price_format(get_lazy_cart_shipping()),
                'tax' => lazy_price_format(get_lazy_cart_tax()),
                'total' => lazy_price_format(get_lazy_cart_total()),
                'discount_html' => $this->getDiscountHtml()
            ]);
        }

        return redirect()->back()->with('success', 'Item removed from cart!');
    }

    /**
     * Revalidates applied coupon when cart is modified
     */
    private function revalidateCoupon()
    {
        $coupons = Session::get('lazy_coupons', []);
        if (empty($coupons)) {
            Session::forget('lazy_coupon');
            return;
        }

        $availableCoupons = json_decode(get_cms_option('shop_coupons', '[]'), true) ?: [];
        $newCoupons = [];

        foreach ($coupons as $applied) {
            $couponData = null;
            foreach ($availableCoupons as $c) {
                if (strtoupper($c['code'] ?? '') === strtoupper($applied['code'])) {
                    $couponData = $c;
                    break;
                }
            }

            if (!$couponData) continue;

            // Check Min Spend
            $subtotal = round(get_lazy_cart_subtotal(), 2);
            $minSpend = !empty($couponData['min_spend']) ? round((float)$couponData['min_spend'], 2) : 0;
            
            if ($minSpend > 0 && $subtotal < $minSpend) continue;
            
            // Check Expiry
            if (!empty($couponData['expiry']) && strtotime($couponData['expiry']) < strtotime(date('Y-m-d'))) {
                continue;
            }

            // Check Product/Category Restrictions
            $cart = Session::get('lazy_cart', []);
            $discount = get_lazy_coupon_discount_amount($couponData, $cart);
            if ($discount <= 0) continue;

            $newCoupons[] = [
                'code' => $couponData['code'],
                'type' => $couponData['type'] ?? 'percent',
                'amount' => $couponData['amount'] ?? ($couponData['discount'] ?? 0),
                'products' => $couponData['products'] ?? [],
                'categories' => $couponData['categories'] ?? []
            ];
        }

        // Wipe if coupons are disabled globally
        if (get_shop_option('shop_enable_coupons', '1') !== '1') {
            Session::forget('lazy_coupons');
            Session::forget('lazy_coupon');
            Session::save();
            return;
        }

        Session::put('lazy_coupons', $newCoupons);
        Session::forget('lazy_coupon');

        // Prune if multiple not allowed anymore
        $isMultipleAllowed = (int)get_cms_option('shop_coupon_stacking_policy', '1') === 1;

        if (!$isMultipleAllowed) {
            $currentCoupons = Session::get('lazy_coupons', []);
            if (count($currentCoupons) > 1) {
                $keptCoupon = array_shift($currentCoupons);
                Session::put('lazy_coupons', [$keptCoupon]);
            }
        }
        Session::save();
    }

    public function checkout()
    {
        $this->validateCartItems();
        $this->revalidateCoupon();
        $cart = Session::get('lazy_cart', []);
        if (empty($cart)) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty!');
        }
        return view($this->resolveThemeView('checkout'), compact('cart'));
    }

    public function placeOrder(Request $request)
    {
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

        if ($request->has('ship_to_different_address')) {
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

        $request->validate($rules, [], $attributes);

        $cart = Session::get('lazy_cart', []);
        if (empty($cart)) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty!');
        }

        $shippingCountry = $request->has('ship_to_different_address') ? $request->shipping_country : $request->billing_country;
        Session::put('lazy_shipping_country', $shippingCountry);

        $subtotal = get_lazy_cart_subtotal();
        $shipping = get_lazy_cart_shipping($shippingCountry);
        $tax = get_lazy_cart_tax();
        $total = get_lazy_cart_total();

        // Coupon Logic for Multiple Coupons
        $coupons = Session::get('lazy_coupons', []);
        $single = Session::get('lazy_coupon');
        if ($single && empty($coupons)) $coupons[] = $single;

        $couponCodes = [];
        $discountTotal = 0;
        foreach ($coupons as $coupon) {
            $couponCodes[] = $coupon['code'];
            $amount = (float) ($coupon['amount'] ?? ($coupon['discount'] ?? 0));
            $discountTotal += ($coupon['type'] ?? 'percent') === 'percent' ? $subtotal * ($amount / 100) : $amount;
        }

        $orderData = [
            'user_id' => auth()->id(),
            'order_number' => 'ORD-' . strtoupper(\Illuminate\Support\Str::random(8)),
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
            'shipping_method' => get_lazy_cart_shipping_details($shippingCountry)['label'],
            'customer_note' => $request->order_comments,
            // Snapshot currency settings for historical accuracy
            'currency' => get_shop_option('shop_currency', 'USD'),
            'currency_symbol' => \Acme\CmsDashboard\Services\EcommerceData::getCurrencySymbol(get_shop_option('shop_currency', 'USD')),
            'currency_position' => get_shop_option('shop_currency_pos', 'left'),
            'thousand_separator' => get_shop_option('shop_thousand_sep', ','),
            'decimal_separator' => get_shop_option('shop_decimal_sep', '.'),
            'decimals' => (int) get_shop_option('shop_num_decimals', 2),
        ];

        if ($request->has('ship_to_different_address')) {
            $orderData['shipping_first_name'] = $request->shipping_first_name;
            $orderData['shipping_last_name'] = $request->shipping_last_name;
            $orderData['shipping_address_line_1'] = $request->shipping_address_1;
            $orderData['shipping_address_line_2'] = $request->shipping_address_2;
            $orderData['shipping_city'] = $request->shipping_city;
            $orderData['shipping_state'] = $request->shipping_state;
            $orderData['shipping_postcode'] = $request->shipping_postcode;
            $orderData['shipping_country'] = $request->shipping_country;
        }

        $order = Order::create($orderData);

        foreach ($cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['sale_price'] ?? $item['price'],
                'subtotal' => ($item['sale_price'] ?? $item['price']) * $item['quantity'],
            ]);

            // Decrement Stock
            $product = Product::with('shopData')->find($item['id']);
            if ($product && $product->shopData && $product->shopData->manage_stock) {
                $product->shopData->decrement('stock_quantity', $item['quantity']);
            }
        }

        Session::forget('lazy_cart');
        Session::forget('lazy_coupon');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'redirect' => route('shop.confirmation', $order->id),
                'order_id' => $order->id
            ]);
        }

        return redirect()->route('shop.confirmation', $order->id)->with('success', 'Order placed successfully!');
    }

    public function confirmation($id)
    {
        $order = Order::with('items')->findOrFail($id);
        return view($this->resolveThemeView('confirmation'), compact('order'));
    }

    public function storeReview(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'parent_id' => 'nullable|exists:shop_reviews,id',
            'rating' => 'required|integer|min:1|max:5',
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
            $query = \Acme\CmsDashboard\Models\Review::where('is_approved', true);
            if ($userId) {
                $isApproved = (clone $query)->where('user_id', $userId)->exists();
            } elseif ($email) {
                $isApproved = (clone $query)->where('email', $email)->exists();
            }
        }

        \Acme\CmsDashboard\Models\Review::create([
            'post_id' => $validated['post_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'is_approved' => $isApproved
        ]);

        $message = $isApproved ? 'Review posted successfully.' : 'Your review is awaiting moderation.';
        
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Ensures all items in cart are still valid and in stock
     */
    private function validateCartItems()
    {
        $cart = Session::get('lazy_cart', []);
        if (empty($cart)) return;

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
            \Illuminate\Support\Facades\Session::put('lazy_cart', $cart);
            \Illuminate\Support\Facades\Session::save();
        }
    }

    public function updateShipping(\Illuminate\Http\Request $request)
    {
        $country = $request->input('country');
        \Illuminate\Support\Facades\Session::put('lazy_shipping_country', $country);
        $shippingDetails = get_lazy_cart_shipping_details($country);
        $shippingCost = $shippingDetails['cost'];
        $total = get_lazy_cart_total();
        
        return response()->json([
            'success' => true,
            'shipping' => $shippingCost > 0 ? $shippingDetails['label'] . ': ' . lazy_price_format($shippingCost) : $shippingDetails['label'],
            'total' => lazy_price_format($total)
        ]);
    }
}
