<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Order;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function overview(Request $request)
    {
        // Resolve the date range — preset or custom from/to.
        $tz     = cms_timezone();
        $preset = $request->get('range', '30d');
        $to     = $request->filled('to')   ? \Carbon\Carbon::parse($request->to, $tz)->endOfDay()->utc()   : cms_now()->endOfDay()->utc();
        $from   = null;

        if ($request->filled('from')) {
            $from   = \Carbon\Carbon::parse($request->from, $tz)->startOfDay()->utc();
            $preset = 'custom';
        } else {
            switch ($preset) {
                case 'today': $from = cms_now()->startOfDay()->utc(); break;
                case '7d':    $from = cms_now()->subDays(6)->startOfDay()->utc(); break;
                case 'month': $from = cms_now()->startOfMonth()->utc(); break;
                case 'year':  $from = cms_now()->startOfYear()->utc(); break;
                case 'all':   $from = null; break;
                case '30d':
                default:      $from = cms_now()->subDays(29)->startOfDay()->utc(); $preset = '30d'; break;
            }
        }

        $scoped = function () use ($from, $to) {
            $q = Order::query()->where('created_at', '<=', $to);
            if ($from) $q->where('created_at', '>=', $from);
            return $q;
        };

        $revenueStatuses = ['completed', 'processing', 'partially-refunded'];
        $netExpr = "COALESCE(SUM(total - COALESCE(refunded_amount, 0)), 0)";

        $stats = [
            'total_orders'   => (clone $scoped())->count(),
            'net_revenue'    => (float) $scoped()->whereIn('status', $revenueStatuses)->selectRaw("{$netExpr} as n")->value('n'),
            'gross_revenue'  => (float) $scoped()->whereIn('status', $revenueStatuses)->sum('total'),
            'total_refunded' => (float) $scoped()->where('refunded_amount', '>', 0)->sum('refunded_amount'),
            'paid_orders'    => (clone $scoped())->whereIn('status', $revenueStatuses)->count(),
            'pending'        => (clone $scoped())->where('status', 'pending')->count(),
        ];
        $stats['avg_order'] = $stats['paid_orders'] ? $stats['net_revenue'] / $stats['paid_orders'] : 0;

        $statusCounts = (clone $scoped())->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $statusCounts['partially-refunded'] = (clone $scoped())->where('refunded_amount', '>', 0)->whereColumn('refunded_amount', '<', 'total')->count();

        // Orders list — search filters the list only (stat cards stay for the whole range).
        $ordersQuery = $scoped();
        if ($request->filled('s')) {
            $s = $request->s;
            $ordersQuery->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhere('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('customer_email', 'like', "%{$s}%");
            });
        }
        $orders   = $ordersQuery->latest()->paginate(15)->withQueryString();
        $currency = \FalconCms\Core\Services\EcommerceData::getCurrencySymbol(get_shop_option('shop_currency', 'USD'));

        return view('falcon-cms::admin.shop.overview', compact('orders', 'stats', 'statusCounts', 'from', 'to', 'preset', 'currency'));
    }

    public function orders(Request $request)
    {
        $query = Order::with('items.product');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('s')) {
            $query->where('order_number', 'like', '%' . $request->s . '%')
                  ->orWhere('first_name', 'like', '%' . $request->s . '%')
                  ->orWhere('last_name', 'like', '%' . $request->s . '%');
        }

        $orders = $query->latest()->paginate(10)->withQueryString();
        return view('falcon-cms::admin.shop.orders.index', compact('orders'));
    }

    public function ordersBulk(Request $request)
    {
        $ids = $request->input('ids', []);
        $action = $request->input('action');

        if (empty($ids)) {
            return back()->with('error', 'Please select at least one order.');
        }

        if (strpos($action, 'status_') === 0) {
            $status = str_replace('status_', '', $action);
            $orders = Order::whereIn('id', $ids)->get();
            foreach ($orders as $o) {
                if ($o->status !== $status) {
                    $oldStatus = $o->status;
                    $o->update(['status' => $status]);
                    $o->logStatus($status);
                    $this->handleInventoryStatusChange($o, $oldStatus, $status);
                    if ($status === 'delivered') {
                        try {
                            \Illuminate\Support\Facades\Mail::to($o->customer_email)->send(new \FalconCms\Core\Mail\OrderNotificationMail($o, 'status_updated'));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Order #{$o->order_number} delivery email failed: " . $e->getMessage());
                        }
                    }
                }
            }
            return back()->with('success', 'Orders status updated successfully.');
        }

        if ($action === 'delete') {
            Order::whereIn('id', $ids)->delete();
            return back()->with('success', 'Selected orders deleted successfully.');
        }

        return back()->with('error', 'Invalid action selected.');
    }

    public function orderShow($id)
    {
        $order = Order::with('items.product')->findOrFail($id);
        
        if (!$order->is_read) {
            $order->update(['is_read' => true]);
        }

        return view('falcon-cms::admin.shop.orders.show', compact('order'));
    }

    public function orderInvoice($id)
    {
        $order = Order::with('items.product')->findOrFail($id);
        return view('falcon-cms::admin.shop.orders.invoice', compact('order'));
    }

    public function orderUpdateStatus(Request $request, $id)
    {
        $order = Order::with(['items.product.shopData', 'items.variation'])->findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Save shipment tracking details (independent of status change).
        if ($request->has('tracking_number') || $request->has('tracking_carrier') || $request->has('tracking_url')) {
            $order->update([
                'tracking_number'  => $request->input('tracking_number') ?: null,
                'tracking_carrier' => $request->input('tracking_carrier') ?: null,
                'tracking_url'     => $request->input('tracking_url') ?: null,
            ]);
        }

        $refundMsg = '';
        $emailRefundAmount = null;
        if ($oldStatus !== $newStatus) {
            // Auto-refund through Stripe when an admin moves a paid Stripe order to "Refunded".
            if ($newStatus === 'refunded' && $order->payment_method === 'stripe' && $order->paid_at) {
                $remainingBefore = max(0, (float) $order->total - (float) ($order->refunded_amount ?? 0));
                [$ok, $msg] = $this->refundStripeOrder($order);
                if (!$ok) {
                    return redirect()->back()->with('error', 'Stripe refund failed: ' . $msg . ' — order status was not changed.');
                }
                $refundMsg = ' ' . $msg;
                $emailRefundAmount = $remainingBefore; // amount refunded in this action
            }

            $order->update(['status' => $newStatus]);
            $order->logStatus($newStatus, $request->input('timeline_note') ?: null);
            $this->handleInventoryStatusChange($order, $oldStatus, $newStatus);

            // Only email customer when order is delivered
            if ($newStatus === 'delivered') {
                try {
                    \Illuminate\Support\Facades\Mail::to($order->customer_email)->send(new \FalconCms\Core\Mail\OrderNotificationMail($order, 'status_updated', null, 'customer', $emailRefundAmount));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Order #{$order->order_number} delivery email failed: " . $e->getMessage());
                }
            }
        }

        return redirect()->back()->with('success', 'Order status updated successfully.' . $refundMsg);
    }

    /**
     * Issue a real refund on Stripe for the order's PaymentIntent.
     * $amount = null → refund the full remaining amount; otherwise a partial amount.
     * Returns [bool success, string message].
     */
    private function refundStripeOrder(Order $order, ?float $amount = null): array
    {
        $secret = get_shop_option('shop_payment_stripe_secret');
        if (!$secret) return [false, 'Stripe secret key is not configured.'];
        if (!$order->transaction_id) return [false, 'No Stripe transaction reference found on this order.'];

        $alreadyRefunded = (float) ($order->refunded_amount ?? 0);
        $remaining       = max(0, (float) $order->total - $alreadyRefunded);
        if ($remaining <= 0) return [false, 'This order is already fully refunded.'];

        // Default: refund everything that is left.
        $refundAmount = $amount === null ? $remaining : (float) $amount;
        if ($refundAmount <= 0) return [false, 'Refund amount must be greater than zero.'];
        if ($refundAmount > $remaining + 0.001) {
            return [false, 'Refund amount exceeds the remaining refundable balance (' . lazy_price_format($remaining, $order) . ').'];
        }

        // Stripe expects the smallest currency unit.
        $currency    = strtolower(get_shop_option('shop_currency', 'usd'));
        $zeroDecimal = ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'];
        $stripeUnits = in_array($currency, $zeroDecimal, true) ? (int) round($refundAmount) : (int) round($refundAmount * 100);

        try {
            $resp = \Illuminate\Support\Facades\Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/refunds', [
                'payment_intent'     => $order->transaction_id,
                'amount'             => $stripeUnits,
                'metadata[order_id]' => (string) $order->id,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Stripe refund exception for order #{$order->order_number}: " . $e->getMessage());
            return [false, 'Could not reach Stripe. Please try again.'];
        }

        if ($resp->successful()) {
            $log = $order->refund_log ?? [];
            $log[] = [
                'amount'  => round($refundAmount, 2),
                'at'      => now()->utc()->toIso8601String(),
                'by'      => optional(auth()->user())->name ?? 'Admin',
                'gateway' => 'stripe',
                'ref'     => $resp->json('id'),
            ];
            $order->update([
                'refunded_amount' => round($alreadyRefunded + $refundAmount, 2),
                'refund_log'      => $log,
            ]);
            return [true, 'Refunded ' . lazy_price_format($refundAmount, $order) . ' via Stripe.'];
        }

        // Treat an already-refunded charge as fully refunded so the status can still be set.
        if ($resp->json('error.code') === 'charge_already_refunded') {
            $order->update(['refunded_amount' => (float) $order->total]);
            return [true, 'This payment was already refunded in Stripe.'];
        }

        \Illuminate\Support\Facades\Log::error("Stripe refund error for order #{$order->order_number}: " . $resp->body());
        return [false, $resp->json('error.message') ?: 'Stripe rejected the refund.'];
    }

    /**
     * Process a (full or partial) refund request from the order page.
     */
    public function orderRefund(Request $request, $id)
    {
        $order = Order::with(['items.product.shopData', 'items.variation'])->findOrFail($id);

        if ($order->payment_method !== 'stripe' || !$order->paid_at) {
            return redirect()->back()->with('error', 'Automatic refunds are only available for paid Stripe orders.');
        }

        $request->validate(['refund_amount' => 'required|numeric|min:0.01'], [], ['refund_amount' => 'Refund Amount']);

        [$ok, $msg] = $this->refundStripeOrder($order, (float) $request->refund_amount);
        if (!$ok) {
            return redirect()->back()->with('error', 'Refund failed: ' . $msg);
        }

        // Reflect the refund in the order status automatically.
        $refunded   = (float) $order->fresh()->refunded_amount;
        $fully      = $refunded >= (float) $order->total - 0.001;
        $newStatus  = $fully ? 'refunded' : 'partially-refunded';
        $oldStatus  = $order->status;
        if ($oldStatus !== $newStatus) {
            $order->update(['status' => $newStatus]);
            // Only a full refund returns stock to inventory.
            if ($fully) $this->handleInventoryStatusChange($order, $oldStatus, 'refunded');
        }

        // Always notify the customer about the refund (even on repeat partial refunds), with the amount.
        try {
            \Illuminate\Support\Facades\Mail::to($order->customer_email)->send(new \FalconCms\Core\Mail\OrderNotificationMail($order, 'status_updated', null, 'customer', (float) $request->refund_amount));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Order #{$order->order_number} refund email failed: " . $e->getMessage());
        }

        return redirect()->back()->with('success', $msg);
    }

    private function handleInventoryStatusChange(Order $order, $oldStatus, $newStatus)
    {
        // Statuses that represent active stock holding
        $activeStatuses = ['pending', 'processing', 'completed', 'on-hold', 'partially-refunded'];
        // Statuses where stock is returned to inventory
        $inactiveStatuses = ['cancelled', 'refunded', 'failed'];

        $wasActive = in_array($oldStatus, $activeStatuses);
        $isInactive = in_array($newStatus, $inactiveStatuses);

        $wasInactive = in_array($oldStatus, $inactiveStatuses);
        $isActive = in_array($newStatus, $activeStatuses);

        $order->loadMissing(['items.product.shopData', 'items.variation']);

        if ($wasActive && $isInactive) {
            // Restock (Restore Inventory)
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
        } elseif ($wasInactive && $isActive) {
            // Deduct Stock
            foreach ($order->items as $item) {
                if ($item->variation) {
                    if ($item->variation->manage_stock) {
                        $item->variation->decrement('stock_quantity', $item->quantity);
                    }
                } elseif ($item->product && $item->product->shopData) {
                    $shopData = $item->product->shopData;
                    if ($shopData->manage_stock) {
                        $shopData->decrement('stock_quantity', $item->quantity);
                    }
                }
            }
        }
    }

    public function settings()
    {
        $countries = \FalconCms\Core\Services\EcommerceData::getCountriesWithStates(false);
        $allowedCountries = \FalconCms\Core\Services\EcommerceData::getCountriesWithStates(true);
        $currencies = \FalconCms\Core\Services\EcommerceData::getCurrencies();
        $frontPageId    = get_cms_option('page_on_front');
        $shopPageId     = get_shop_option('shop_shop_page_id');
        $cartPageId     = get_shop_option('shop_cart_page_id');
        $checkoutPageId = get_shop_option('shop_checkout_page_id');
        $accountPageId  = get_shop_option('shop_account_page_id');

        $pages = \Illuminate\Support\Facades\DB::table('posts')
            ->where('type', 'page')
            ->get(['id', 'title', 'status']);

        foreach ($pages as $page) {
            $labels = [];
            if ($page->id == $frontPageId) $labels[] = 'Front Page';
            if ($page->id == $shopPageId) $labels[] = 'Shop Page';
            if ($page->id == $cartPageId) $labels[] = 'Cart Page';
            if ($page->id == $checkoutPageId) $labels[] = 'Checkout Page';
            if ($page->id == $accountPageId) $labels[] = 'Account Page';
            if ($page->status == 'draft') $labels[] = 'Draft';
            
            if (!empty($labels)) {
                $page->title .= ' — ' . implode(', ', $labels);
            }
        }

        $products = \Illuminate\Support\Facades\DB::table('posts')
            ->where('type', 'product')
            ->where('status', 'published')
            ->get(['id', 'title']);

        $categories = \Illuminate\Support\Facades\DB::table('product_categories')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('falcon-cms::admin.shop.settings', compact('countries', 'allowedCountries', 'currencies', 'pages', 'products', 'categories'));
    }

    public function saveSettings(Request $request)
    {
        // 1. Explicitly handle toggles (so they save 0 when unchecked)
        $toggles = [
            'enable_coupons'          => 'shop_enable_coupons',
            'multi_coupon_policy'     => 'shop_coupon_stacking_policy',
            'enable_guest_checkout'   => 'shop_enable_guest_checkout',
            'force_login_checkout'    => 'shop_force_login_checkout',
        ];

        foreach ($toggles as $reqKey => $optKey) {
            $val = $request->has($reqKey) ? '1' : '0';
            \Illuminate\Support\Facades\DB::table('cms_settings')->updateOrInsert(
                ['key' => $optKey],
                ['value' => $val, 'updated_at' => now()]
            );
            
            // Delete locale keys
            \Illuminate\Support\Facades\DB::table('cms_settings')
                ->where('key', 'like', $optKey . '_%')
                ->delete();
        }

        // 2. Save everything else
        $skip = array_merge(['_token', 'active_tab'], array_keys($toggles));
        foreach ($request->except($skip) as $key => $value) {
            $optKey = 'shop_' . $key;
            update_shop_option($optKey, $value);
            
            // Ensure global settings take precedence by deleting localized overrides
            \Illuminate\Support\Facades\DB::table('cms_settings')
                ->where('key', 'like', $optKey . '_%')
                ->delete();
        }

        if ($request->has('active_tab')) {
            session(['active_shop_tab' => $request->active_tab]);
            session()->save();
        }

        return redirect()->back()->with('success', 'Shop settings saved successfully!');
    }
}
