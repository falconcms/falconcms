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

    /**
     * Off-canvas mini-cart fragment (AJAX). Returns the rendered item list,
     * live subtotal and item count so the drawer can refresh dynamically.
     */
    public function miniCart()
    {
        $this->validateCartItems();
        $cart = Session::get('lazy_cart', []);

        $html = view($this->resolveThemeView('mini-cart-items'), compact('cart'))->render();

        return response()->json([
            'success'  => true,
            'count'    => get_lazy_cart_count(),
            'subtotal' => lazy_price_format(get_lazy_cart_subtotal()),
            'html'     => $html,
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
            $variation = \Acme\CmsDashboard\Models\ProductVariation::find($variationId);
        }

        // Inventory Check
        $stockSource = ($variation && $variation->manage_stock) ? $variation : $shopData;
        if ($stockSource && $stockSource->manage_stock) {
            $cart = Session::get('lazy_cart', []);
            $cartKey = $variationId ? "{$productId}_{$variationId}" : $productId;
            $currentInCart = isset($cart[$cartKey]) ? $cart[$cartKey]['quantity'] : 0;
            
            if (($currentInCart + $quantity) > $stockSource->stock_quantity) {
                $errorMsg = $stockSource->stock_quantity <= 0 
                    ? 'Sorry, this product is currently out of stock.' 
                    : 'Sorry, only ' . $stockSource->stock_quantity . ' items available in stock.';

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
            // Determine name and attributes for variation
            $itemName = $product->title;
            if ($variation) {
                $attrString = collect($variation->attributes_data)->map(fn($v, $k) => "$k: $v")->implode(', ');
                $itemName .= " - " . $attrString;
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
                'sku' => $variation ? $variation->sku : $product->sku
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

        // Account creation & Guest checkout validation
        if (!auth()->check()) {
            $guestEnabled = get_shop_option('shop_checkout_guest_enable', '1') === '1';
            $createAcc = $request->input('create_account') == '1';

            if (!$guestEnabled && !$createAcc) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Guest checkout is disabled. Please login or create an account.'
                    ]);
                }
                return redirect()->back()->with('error', 'Guest checkout is disabled. Please login or create an account.');
            }

            if ($createAcc) {
                $request->validate([
                    'account_password' => 'required|min:6'
                ], [
                    'account_password.required' => 'Please enter a password to create your account.'
                ]);

                $existingUser = \App\Models\User::where('email', $request->billing_email)->first();
                if ($existingUser) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'An account with this email already exists. Please login.'
                        ]);
                    }
                    return redirect()->back()->with('error', 'An account with this email already exists. Please login.');
                }

                $newUser = \App\Models\User::create([
                    'name' => trim($request->billing_first_name . ' ' . $request->billing_last_name),
                    'email' => $request->billing_email,
                    'password' => \Illuminate\Support\Facades\Hash::make($request->account_password),
                ]);

                $customerRole = \Acme\CmsDashboard\Models\Role::firstOrCreate(
                    ['slug' => 'customer'],
                    ['name' => 'Customer', 'description' => 'Customer who registered via store checkout or account.']
                );
                if ($customerRole) {
                    $newUser->update(['role_id' => $customerRole->id]);
                }

                auth()->login($newUser);
            }
        }

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
                'variation_id' => $item['variation_id'] ?? null,
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['sale_price'] ?? $item['price'],
                'subtotal' => ($item['sale_price'] ?? $item['price']) * $item['quantity'],
            ]);

            // Decrement Stock
            if (!empty($item['variation_id'])) {
                $variation = \Acme\CmsDashboard\Models\ProductVariation::find($item['variation_id']);
                if ($variation && $variation->manage_stock) {
                    $variation->decrement('stock_quantity', $item['quantity']);
                }
            } else {
                $product = Product::with('shopData')->find($item['id']);
                if ($product && $product->shopData && $product->shopData->manage_stock) {
                    $product->shopData->decrement('stock_quantity', $item['quantity']);
                    $this->maybeNotifyStock($product->title ?? 'Product', (int) $product->shopData->fresh()->stock_quantity);
                }
            }
        }

        // Online gateways: send the customer to the gateway to pay before finalizing.
        $gateways = lazy_enabled_payment_gateways();
        $gateway  = $gateways[$order->payment_method] ?? null;

        if ($gateway && $gateway['type'] === 'online') {
            // Stripe → inline card (Stripe Elements). Create a PaymentIntent; the card is confirmed on-page.
            if ($order->payment_method === 'stripe') {
                $intent = $this->createStripePaymentIntent($order);
                if ($intent) {
                    $order->update(['transaction_id' => $intent['id']]);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success'        => true,
                            'stripe_payment' => true,
                            'client_secret'  => $intent['client_secret'],
                            'return_url'     => route('shop.payment.return', $order->id) . '?gateway=stripe&payment_intent=' . $intent['id'],
                            'order_id'       => $order->id,
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
                    \Illuminate\Support\Facades\Log::error("Payment init failed for order #{$order->order_number}: " . $e->getMessage());
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
                'order_id' => $order->id
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
            \Illuminate\Support\Facades\Mail::to($order->customer_email)->send(new \Acme\CmsDashboard\Mail\OrderNotificationMail($order, 'placed'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Order #{$order->order_number} email failed: " . $e->getMessage());
        }

        $adminRecipient = get_shop_option('shop_email_admin_recipient');
        if (!empty($adminRecipient)) {
            try {
                \Illuminate\Support\Facades\Mail::to($adminRecipient)->send(new \Acme\CmsDashboard\Mail\OrderNotificationMail($order, 'placed', 'New Order Received'));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Order #{$order->order_number} admin email failed: " . $e->getMessage());
            }
        }

        Session::forget('lazy_cart');
        Session::forget('lazy_coupon');
        Session::forget('lazy_coupons');
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
        $zeroDecimal = ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'];
        return in_array($currency, $zeroDecimal, true) ? (int) round($order->total) : (int) round($order->total * 100);
    }

    /**
     * Create a Stripe PaymentIntent for inline (Stripe Elements) card payment.
     * Returns ['id','client_secret'] or null on failure.
     */
    private function createStripePaymentIntent(Order $order): ?array
    {
        $secret = get_shop_option('shop_payment_stripe_secret');
        if (!$secret) return null;

        $resp = \Illuminate\Support\Facades\Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/payment_intents', [
            'amount'                 => $this->stripeAmount($order),
            'currency'               => strtolower(get_shop_option('shop_currency', 'usd')),
            'description'            => 'Order ' . $order->order_number,
            'receipt_email'          => $order->customer_email,
            'payment_method_types[0]'=> 'card',
            'metadata[order_id]'     => (string) $order->id,
            'metadata[order_number]' => $order->order_number,
        ]);

        if ($resp->successful() && !empty($resp->json('client_secret'))) {
            return ['id' => $resp->json('id'), 'client_secret' => $resp->json('client_secret')];
        }
        \Illuminate\Support\Facades\Log::error('Stripe PaymentIntent error: ' . $resp->body());
        return null;
    }

    private function initiatePayment(Order $order, string $method): ?string
    {
        if ($method === 'paypal') {
            // PayPal Standard (email based) — redirect with a hosted button form via query string.
            $email   = get_shop_option('shop_payment_paypal_email');
            if (!$email) return null;
            $sandbox = get_shop_option('shop_payment_paypal_sandbox') === '1';
            $base    = $sandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
            $params  = http_build_query([
                'cmd'           => '_xclick',
                'business'      => $email,
                'item_name'     => 'Order ' . $order->order_number,
                'amount'        => number_format((float) $order->total, 2, '.', ''),
                'currency_code' => strtoupper(get_shop_option('shop_currency', 'USD')),
                'custom'        => $order->id,
                'return'        => route('shop.payment.return', $order->id) . '?gateway=paypal',
                'cancel_return' => route('shop.payment.cancel', $order->id) . '?gateway=paypal',
                'notify_url'    => route('shop.payment.return', $order->id) . '?gateway=paypal&ipn=1',
                'no_shipping'   => 1,
            ]);
            return $base . '?' . $params;
        }

        if ($method === 'sslcommerz') {
            $storeId   = get_shop_option('shop_payment_sslcommerz_store_id');
            $storePass = get_shop_option('shop_payment_sslcommerz_store_pass');
            if (!$storeId || !$storePass) return null;
            $sandbox = get_shop_option('shop_payment_sslcommerz_sandbox') === '1';
            $apiUrl  = $sandbox
                ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
                : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

            $resp = \Illuminate\Support\Facades\Http::asForm()->post($apiUrl, [
                'store_id'         => $storeId,
                'store_passwd'     => $storePass,
                'total_amount'     => number_format((float) $order->total, 2, '.', ''),
                'currency'         => strtoupper(get_shop_option('shop_currency', 'BDT')),
                'tran_id'          => $order->order_number,
                'success_url'      => route('shop.payment.return', $order->id) . '?gateway=sslcommerz',
                'fail_url'         => route('shop.payment.cancel', $order->id) . '?gateway=sslcommerz',
                'cancel_url'       => route('shop.payment.cancel', $order->id) . '?gateway=sslcommerz',
                'ipn_url'          => route('shop.payment.return', $order->id) . '?gateway=sslcommerz&ipn=1',
                'shipping_method'  => 'NO',
                'product_name'     => 'Order ' . $order->order_number,
                'product_category' => 'General',
                'product_profile'  => 'general',
                'cus_name'         => trim($order->first_name . ' ' . $order->last_name),
                'cus_email'        => $order->customer_email,
                'cus_phone'        => $order->customer_phone,
                'cus_add1'         => $order->address_line_1,
                'cus_city'         => $order->city,
                'cus_country'      => $order->country,
            ]);

            if ($resp->successful() && $resp->json('status') === 'SUCCESS' && !empty($resp->json('GatewayPageURL'))) {
                $order->update(['transaction_id' => $resp->json('sessionkey')]);
                return $resp->json('GatewayPageURL');
            }
            \Illuminate\Support\Facades\Log::error('SSLCommerz session error: ' . $resp->body());
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
        $gateway = $request->get('gateway');

        // Already finalized
        if ($order->paid_at) {
            return redirect()->route('shop.confirmation', $order->id);
        }

        $paid = false;

        if ($gateway === 'stripe') {
            $secret       = get_shop_option('shop_payment_stripe_secret');
            $paymentIntent = $request->get('payment_intent');
            $sessionId     = $request->get('session_id');
            if ($secret && $paymentIntent) {
                // Inline (Stripe Elements) — verify the PaymentIntent.
                $resp = \Illuminate\Support\Facades\Http::withToken($secret)->get('https://api.stripe.com/v1/payment_intents/' . $paymentIntent);
                if ($resp->successful() && $resp->json('status') === 'succeeded') {
                    $paid = true;
                    $order->update(['transaction_id' => $paymentIntent]);
                }
            } elseif ($secret && $sessionId) {
                // Hosted Stripe Checkout (fallback) — verify the session.
                $resp = \Illuminate\Support\Facades\Http::withToken($secret)->get('https://api.stripe.com/v1/checkout/sessions/' . $sessionId);
                if ($resp->successful() && $resp->json('payment_status') === 'paid') {
                    $paid = true;
                    $order->update(['transaction_id' => $resp->json('payment_intent') ?: $sessionId]);
                }
            }
        } elseif ($gateway === 'sslcommerz') {
            $storeId   = get_shop_option('shop_payment_sslcommerz_store_id');
            $storePass = get_shop_option('shop_payment_sslcommerz_store_pass');
            $valId     = $request->input('val_id');
            $postedStatus = $request->input('status');
            if ($storeId && $storePass && $valId && in_array($postedStatus, ['VALID', 'VALIDATED'], true)) {
                $sandbox = get_shop_option('shop_payment_sslcommerz_sandbox') === '1';
                $valApi  = $sandbox
                    ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
                    : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
                $resp = \Illuminate\Support\Facades\Http::get($valApi, [
                    'val_id'       => $valId,
                    'store_id'     => $storeId,
                    'store_passwd' => $storePass,
                    'format'       => 'json',
                ]);
                if ($resp->successful()) {
                    $st  = $resp->json('status');
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
            $order->update(['status' => 'processing', 'paid_at' => now()]);
            $this->finalizeOrder($order);
            return redirect()->route('shop.confirmation', $order->id)->with('success', 'Payment successful! Your order is confirmed.');
        }

        return redirect()->route('shop.confirmation', $order->id)
            ->with('error', 'We could not confirm your payment yet. If you were charged, please contact us.');
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
                'email'        => 'required|email',
            ], [], ['order_number' => 'Order Number', 'email' => 'Email']);

            $order = Order::with('items')
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
            if (!$admin) return;
            $out = (int) get_shop_option('shop_out_of_stock_threshold', '0');
            $low = (int) get_shop_option('shop_low_stock_threshold', '2');
            if ($qty <= $out && get_shop_option('shop_notification_no_stock', '1') === '1') {
                \Illuminate\Support\Facades\Mail::raw("Product \"{$name}\" is now OUT OF STOCK (remaining: {$qty}).", function ($m) use ($admin, $name) {
                    $m->to($admin)->subject('Out of stock: ' . $name);
                });
            } elseif ($qty <= $low && get_shop_option('shop_notification_low_stock', '1') === '1') {
                \Illuminate\Support\Facades\Mail::raw("Product \"{$name}\" is running LOW on stock (remaining: {$qty}).", function ($m) use ($admin, $name) {
                    $m->to($admin)->subject('Low stock: ' . $name);
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
            'rating' => ($ratingOn ? 'required' : 'nullable') . '|integer|min:1|max:5',
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
            'rating' => $ratingOn ? ($validated['rating'] ?? 0) : 0,
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
