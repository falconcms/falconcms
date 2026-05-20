<?php

namespace Acme\CmsDashboard\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Acme\CmsDashboard\Models\Order;
use Illuminate\Http\Request;

class ShopController extends Controller
{
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
        return view('cms-dashboard::admin.shop.orders.index', compact('orders'));
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
                    $this->handleInventoryStatusChange($o, $oldStatus, $status);
                    try {
                        \Illuminate\Support\Facades\Mail::to($o->customer_email)->send(new \Acme\CmsDashboard\Mail\OrderNotificationMail($o, 'status_updated'));
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Order #{$o->order_number} bulk status email failed: " . $e->getMessage());
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

        return view('cms-dashboard::admin.shop.orders.show', compact('order'));
    }

    public function orderInvoice($id)
    {
        $order = Order::with('items.product')->findOrFail($id);
        return view('cms-dashboard::admin.shop.orders.invoice', compact('order'));
    }

    public function orderUpdateStatus(Request $request, $id)
    {
        $order = Order::with(['items.product.shopData', 'items.variation'])->findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->status;

        if ($oldStatus !== $newStatus) {
            $order->update(['status' => $newStatus]);
            $this->handleInventoryStatusChange($order, $oldStatus, $newStatus);

            if ($oldStatus !== $newStatus) {
                try {
                    \Illuminate\Support\Facades\Mail::to($order->customer_email)->send(new \Acme\CmsDashboard\Mail\OrderNotificationMail($order, 'status_updated'));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Order #{$order->order_number} status email failed: " . $e->getMessage());
                }
            }
        }

        return redirect()->back()->with('success', 'Order status updated successfully.');
    }

    private function handleInventoryStatusChange(Order $order, $oldStatus, $newStatus)
    {
        // Statuses that represent active stock holding
        $activeStatuses = ['pending', 'processing', 'completed', 'on-hold'];
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
        $countries = \Acme\CmsDashboard\Services\EcommerceData::getCountriesWithStates(false);
        $allowedCountries = \Acme\CmsDashboard\Services\EcommerceData::getCountriesWithStates(true);
        $currencies = \Acme\CmsDashboard\Services\EcommerceData::getCurrencies();
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

        $categories = \Illuminate\Support\Facades\DB::table('taxonomy_terms')
            ->where('taxonomy_slug', 'product_cat')
            ->get(['id', 'name']);

        return view('cms-dashboard::admin.shop.settings', compact('countries', 'allowedCountries', 'currencies', 'pages', 'products', 'categories'));
    }

    public function saveSettings(Request $request)
    {
        // 1. Explicitly handle toggles (so they save 0 when unchecked)
        $toggles = [
            'enable_coupons'        => 'shop_enable_coupons',
            'multi_coupon_policy'   => 'shop_coupon_stacking_policy',
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
