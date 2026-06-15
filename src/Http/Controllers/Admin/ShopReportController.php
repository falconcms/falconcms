<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ShopReportController extends Controller
{
    public function index(Request $request)
    {
        $tz     = cms_timezone();
        $period = $request->get('period', 'monthly');
        $toRaw  = $request->filled('to')   ? \Carbon\Carbon::parse($request->to, $tz)->endOfDay()   : cms_now()->endOfDay();
        $fromRaw = $request->filled('from') ? \Carbon\Carbon::parse($request->from, $tz)->startOfDay() : cms_now()->subDays(29)->startOfDay();

        $from = $fromRaw->utc();
        $to   = $toRaw->utc();

        // Revenue by period (GROUP BY day, week, or month)
        $groupFmt = match($period) {
            'daily'  => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            default  => '%Y-%m',
        };

        $revenue = DB::table('shop_orders')
            ->selectRaw("DATE_FORMAT(created_at, ?) as period, SUM(total) as revenue, COUNT(*) as orders", [$groupFmt])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Top-selling products
        $topProducts = DB::table('shop_order_items as oi')
            ->join('shop_orders as o', 'o.id', '=', 'oi.order_id')
            ->selectRaw('oi.product_name, oi.product_id, SUM(oi.quantity) as units_sold, SUM(oi.subtotal) as revenue')
            ->where('o.status', '!=', 'cancelled')
            ->whereBetween('o.created_at', [$from, $to])
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc('units_sold')
            ->limit(20)
            ->get();

        // Customer lifetime value (top spenders)
        $topCustomers = DB::table('shop_orders')
            ->selectRaw('customer_email, CONCAT(first_name, " ", last_name) as customer_name, COUNT(*) as order_count, SUM(total) as lifetime_value')
            ->where('status', '!=', 'cancelled')
            ->groupBy('customer_email', 'first_name', 'last_name')
            ->orderByDesc('lifetime_value')
            ->limit(20)
            ->get();

        // Summary stats for the selected period
        $summary = DB::table('shop_orders')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total),0) as total_revenue, COUNT(DISTINCT customer_email) as unique_customers')
            ->first();

        return view('falcon-cms::admin.shop.reports', compact(
            'revenue', 'topProducts', 'topCustomers', 'summary',
            'period', 'from', 'to', 'fromRaw', 'toRaw'
        ));
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'revenue');
        $tz   = cms_timezone();
        $to   = $request->filled('to')   ? \Carbon\Carbon::parse($request->to, $tz)->endOfDay()->utc()   : cms_now()->endOfDay()->utc();
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->from, $tz)->startOfDay()->utc() : cms_now()->subDays(29)->startOfDay()->utc();

        if ($type === 'products') {
            $rows = DB::table('shop_order_items as oi')
                ->join('shop_orders as o', 'o.id', '=', 'oi.order_id')
                ->selectRaw('oi.product_name, oi.product_id, SUM(oi.quantity) as units_sold, SUM(oi.subtotal) as revenue')
                ->where('o.status', '!=', 'cancelled')
                ->whereBetween('o.created_at', [$from, $to])
                ->groupBy('oi.product_id', 'oi.product_name')
                ->orderByDesc('units_sold')
                ->get();

            $headers = ['Product Name', 'Product ID', 'Units Sold', 'Revenue'];
            $data    = $rows->map(fn($r) => [$r->product_name, $r->product_id, $r->units_sold, number_format($r->revenue, 2)]);
            $filename = 'top-products-' . now()->format('Y-m-d') . '.csv';
        } elseif ($type === 'customers') {
            $rows = DB::table('shop_orders')
                ->selectRaw('customer_email, CONCAT(first_name, " ", last_name) as customer_name, COUNT(*) as order_count, SUM(total) as lifetime_value')
                ->where('status', '!=', 'cancelled')
                ->groupBy('customer_email', 'first_name', 'last_name')
                ->orderByDesc('lifetime_value')
                ->get();

            $headers = ['Email', 'Name', 'Orders', 'Lifetime Value'];
            $data    = $rows->map(fn($r) => [$r->customer_email, $r->customer_name, $r->order_count, number_format($r->lifetime_value, 2)]);
            $filename = 'customer-ltv-' . now()->format('Y-m-d') . '.csv';
        } else {
            $rows = DB::table('shop_orders')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $headers = ['Date', 'Orders', 'Revenue'];
            $data    = $rows->map(fn($r) => [$r->date, $r->orders, number_format($r->revenue, 2)]);
            $filename = 'revenue-' . now()->format('Y-m-d') . '.csv';
        }

        $callback = function () use ($headers, $data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
