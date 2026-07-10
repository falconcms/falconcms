<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use FalconCms\Core\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use FalconCms\Core\Models\ActivityLog;
use FalconCms\Core\Models\Analytics;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Get Monthly Stats for Chart (Last 7 Months)
        $labels = [];
        $impressionsData = [];
        $visitorsData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = $date->format('M');
            $labels[] = $monthLabel;

            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $impressions = Analytics::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $visitors = Analytics::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->distinct('ip_address')
                ->count(['ip_address']);

            $impressionsData[] = $impressions;
            $visitorsData[] = $visitors;
        }

        // 2. Conversion Rate Calculation
        $totalVisitors = Analytics::distinct('ip_address')->count(['ip_address']);
        $totalSubmissions = \FalconCms\Core\Models\FormSubmission::count();
        $conversionRate = ($totalVisitors > 0) ? round(($totalSubmissions / $totalVisitors) * 100, 1) : 0;

        // 3. Security Status Check
        $recentBlockedIps = \FalconCms\Core\Models\BlockedIp::where('created_at', '>', now()->subDay())->count();
        $securityStatus = ($recentBlockedIps > 0) ? 'Warning' : 'Healthy';
        $securityMessage = ($recentBlockedIps > 0) 
            ? "Attention: $recentBlockedIps unauthorized attempts blocked in the last 24 hours."
            : "System protection is active. No unauthorized attempts in the last 24 hours.";

        $stats = [
            'total_posts' => [
                'label' => 'Total Posts',
                'count' => Post::where('type', 'post')->count(),
                'change' => '+4.2%'
            ],
            'total_pages' => [
                'label' => 'Total Pages',
                'count' => Post::where('type', 'page')->count(),
                'change' => '+1.5%'
            ],
            'total_users' => [
                'label' => 'Total Users',
                'count' => \App\Models\User::count(),
                'change' => '+2.1%'
            ],
            'blocked_users' => [
                'label' => 'Blocked Accounts',
                'count' => \App\Models\User::where('is_blocked', true)->orWhere(function($q){
                    $q->whereNotNull('blocked_until')->where('blocked_until', '>', now());
                })->count(),
                'change' => 'Security'
            ],
            'blacklisted_ips' => [
                'label' => 'Blacklisted IPs',
                'count' => \FalconCms\Core\Models\BlockedIp::count(),
                'change' => 'Protection'
            ],
            'media_count' => [
                'label' => 'Media Assets',
                'count' => DB::table('media')->count(),
                'change' => '+12.3%'
            ],
            'main_chart' => [
                'labels' => $labels,
                'data1' => $impressionsData,
                'data2' => $visitorsData
            ],
            'traffic_stats' => [
                'labels' => $labels,
                'impressions' => $impressionsData,
                'visitors' => $visitorsData,
                'conversion_rate' => [
                    'value' => $conversionRate . '%',
                    'change' => 'Real-time'
                ],
                'security' => [
                    'status' => $securityStatus,
                    'message' => $securityMessage
                ]
            ]
        ];

        // Ecommerce stats — only when shop tables exist
        $hasShop  = false;
        $currency = \FalconCms\Core\Services\EcommerceData::getCurrencySymbol(get_shop_option('shop_currency', 'USD'));
        $ecoStats = [
            'total_orders'    => 0,
            'total_revenue'   => 0,
            'pending_orders'  => 0,
            'total_products'  => 0,
            'orders_today'    => 0,
            'orders_month'    => 0,
            'status_counts'   => [],
            'monthly_revenue' => array_fill(0, 7, 0),
            'monthly_labels'  => [],
            'top_products'    => collect(),
            'low_stock'       => collect(),
            'revenue_this_month' => 0,
            'revenue_delta'   => null,
            'orders_delta'    => null,
            'low_stock_count' => 0,
            'recent_orders'   => collect(),
            'orders_by_country' => collect(),
        ];
        try {
            // Only expose ecommerce figures (revenue, orders, customer names) to users who can
            // access the shop. Without this gate every dashboard-accessing role would see them.
            if (\Illuminate\Support\Facades\Schema::hasTable('shop_orders') && auth()->user()->hasPermission('access_shop') && falcon_pro('ecommerce')) {
                $hasShop = true;
                // Statuses that represent earned revenue. Net = total minus any amount refunded.
                $revenueStatuses = ['completed', 'processing', 'partially-refunded'];
                $netRevenue = "COALESCE(SUM(total - COALESCE(refunded_amount, 0)), 0)";

                $ecoStats['total_orders']   = \FalconCms\Core\Models\Order::count();
                $ecoStats['total_revenue']  = (float) \FalconCms\Core\Models\Order::whereIn('status', $revenueStatuses)
                    ->selectRaw("{$netRevenue} as net")->value('net');
                $ecoStats['pending_orders'] = \FalconCms\Core\Models\Order::where('status', 'pending')->count();
                $ecoStats['total_products'] = Post::where('type', 'product')->count();
                $ecoStats['orders_today']   = \FalconCms\Core\Models\Order::whereDate('created_at', today())->count();
                $ecoStats['orders_month']   = \FalconCms\Core\Models\Order::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
                $ecoStats['status_counts']  = \FalconCms\Core\Models\Order::selectRaw('status, count(*) as total')
                    ->groupBy('status')->pluck('total', 'status')->toArray();
                // "Partially Refunded" is driven by actual refund data (any order with a partial refund),
                // not just the status label — so it reflects partial refunds on completed/processing orders too.
                $ecoStats['status_counts']['partially-refunded'] = (int) \FalconCms\Core\Models\Order::where('refunded_amount', '>', 0)
                    ->whereColumn('refunded_amount', '<', 'total')->count();
                $rev = [];
                $revLabels = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d    = now()->subMonths($i);
                    $revLabels[] = $d->format('M');
                    $rev[] = (float) \FalconCms\Core\Models\Order::whereIn('status', $revenueStatuses)
                        ->whereBetween('created_at', [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()])
                        ->selectRaw("{$netRevenue} as net")->value('net');
                }
                $ecoStats['monthly_revenue'] = $rev;
                $ecoStats['monthly_labels']  = $revLabels;

                // Best sellers — units sold across paid orders (most useful, unique to this widget)
                $ecoStats['top_products'] = \FalconCms\Core\Models\OrderItem::query()
                    ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.order_id')
                    ->whereIn('shop_orders.status', $revenueStatuses)
                    ->whereNotNull('shop_order_items.product_id')
                    ->selectRaw('shop_order_items.product_id, MAX(shop_order_items.product_name) as product_name, SUM(shop_order_items.quantity) as qty, SUM(shop_order_items.subtotal) as revenue')
                    ->groupBy('shop_order_items.product_id')
                    ->orderByDesc('qty')
                    ->limit(5)
                    ->get();

                // Products that need restocking — managed stock at or below a low threshold
                $ecoStats['low_stock'] = \FalconCms\Core\Models\Product::whereHas('shopData', function ($q) {
                        $q->where('manage_stock', 1)->where('stock_quantity', '<=', 5);
                    })
                    ->with('shopData')
                    ->get()
                    ->sortBy(fn ($p) => (int) ($p->shopData->stock_quantity ?? 0))
                    ->take(5)
                    ->values();

                // Month-over-month deltas + context for the KPI cards
                $lastMonthRef    = now()->subMonthNoOverflow();
                $ordersLastMonth = \FalconCms\Core\Models\Order::whereMonth('created_at', $lastMonthRef->month)
                    ->whereYear('created_at', $lastMonthRef->year)->count();
                $thisRev = (float) ($rev[6] ?? 0);   // current month (last entry in the 7-month series)
                $lastRev = (float) ($rev[5] ?? 0);   // previous month
                $ecoStats['revenue_this_month'] = $thisRev;
                $ecoStats['revenue_delta'] = $lastRev > 0 ? (int) round(($thisRev - $lastRev) / $lastRev * 100) : null;
                $ecoStats['orders_delta']  = $ordersLastMonth > 0
                    ? (int) round(($ecoStats['orders_month'] - $ordersLastMonth) / $ordersLastMonth * 100) : null;
                $ecoStats['low_stock_count'] = \FalconCms\Core\Models\Product::whereHas('shopData', function ($q) {
                        $q->where('manage_stock', 1)->where('stock_quantity', '<=', 5);
                    })->count();

                // Recent orders — fills the space under the revenue chart with actionable activity
                $ecoStats['recent_orders'] = \FalconCms\Core\Models\Order::latest()->limit(6)->get();

                // Orders grouped by country (normalized to ISO-2) for the world map widget
                $countryRows = \FalconCms\Core\Models\Order::query()
                    ->whereNotNull('country')->where('country', '!=', '')
                    ->selectRaw("country, COUNT(*) as orders, COALESCE(SUM(CASE WHEN status IN ('completed','processing','partially-refunded') THEN total - COALESCE(refunded_amount, 0) ELSE 0 END), 0) as revenue")
                    ->groupBy('country')->get();
                $byCountry = [];
                foreach ($countryRows as $row) {
                    $code = \FalconCms\Core\Services\EcommerceData::countryToIso2($row->country);
                    if (!$code) continue;
                    if (!isset($byCountry[$code])) $byCountry[$code] = ['code' => $code, 'orders' => 0, 'revenue' => 0.0];
                    $byCountry[$code]['orders']  += (int) $row->orders;
                    $byCountry[$code]['revenue'] += (float) $row->revenue;
                }
                $ecoStats['orders_by_country'] = collect($byCountry)
                    ->map(fn ($c) => $c + ['name' => \FalconCms\Core\Services\EcommerceData::iso2ToName($c['code'])])
                    ->sortByDesc('orders')->values();
            }
        } catch (\Exception $e) {}

        // Ensure Dashboard > Updates submenu exists (self-heals on existing installs)
        $this->ensureUpdateMenu();

        // Refresh update cache silently (only when expired, max once per 6h)
        if (!cache()->has('falcon_cms_update_check')) {
            try { lazy_check_update(); } catch (\Exception $e) {}
        }

        return view('falcon-cms::admin.dashboard', compact('stats', 'hasShop', 'ecoStats', 'currency'));
    }

    protected function ensureUpdateMenu(): void
    {
        try {
            $dash = \FalconCms\Core\Models\Menu::where('title', 'Dashboard')->whereNull('parent_id')->first();
            if (!$dash) return;

            \FalconCms\Core\Models\Menu::firstOrCreate(
                ['title' => 'Overview', 'parent_id' => $dash->id],
                ['route' => 'admin.dashboard.index', 'order' => 1]
            );
            \FalconCms\Core\Models\Menu::firstOrCreate(
                ['title' => 'Updates', 'parent_id' => $dash->id],
                ['route' => 'admin.update', 'order' => 2]
            );
        } catch (\Exception $e) {}
    }

    public function updateCheck()
    {
        $update = lazy_check_update(force: true);
        return view('falcon-cms::admin.update', compact('update'));
    }

    public function runUpdate()
    {
        set_time_limit(300);

        $steps   = [];
        $hasError = false;

        // Step 1: composer update
        $composerBin = $this->findComposer();
        if ($composerBin) {
            $cmd = $composerBin . ' update falconcms/falconcms --no-interaction --prefer-dist --no-progress 2>&1';
            exec('cd ' . escapeshellarg(base_path()) . ' && ' . $cmd, $composerOut, $exitCode);
            $steps[] = ['label' => 'composer update', 'output' => implode("\n", $composerOut), 'ok' => $exitCode === 0];
            if ($exitCode !== 0) $hasError = true;
        } else {
            $steps[] = ['label' => 'composer update', 'output' => 'composer not found in PATH. Run manually: composer update falconcms/falconcms', 'ok' => false];
            $hasError = true;
        }

        // Step 2: falcon:update — run as a subprocess so the freshly downloaded code
        // is used. Artisan::call() would re-use the old in-memory ServiceProvider
        // loaded before composer update ran, causing "command does not exist".
        $phpBin     = $this->findPhpCli();
        $artisan    = base_path('artisan');
        $falconCmd    = escapeshellarg($phpBin) . ' ' . escapeshellarg($artisan) . ' falcon:update --no-ansi 2>&1';
        exec($falconCmd, $falconOut, $falconExit);
        $steps[] = ['label' => 'php artisan falcon:update', 'output' => trim(implode("\n", $falconOut)), 'ok' => $falconExit === 0];
        if ($falconExit !== 0) $hasError = true;

        // Step 3: reset the php-fpm OPcache from THIS web request. The falcon:update
        // subprocess runs under CLI php, whose opcache_reset() only clears the CLI
        // OPcache — not the shared OPcache the php-fpm workers use to serve frontend
        // pages. Without this, freshly compiled Blade views keep serving stale code
        // (e.g. builder layout fixes not appearing) until an FPM reload/restart.
        if (function_exists('opcache_reset')) {
            $reset = @opcache_reset();
            $steps[] = ['label' => 'opcache reset (php-fpm)', 'output' => $reset ? 'php-fpm OPcache cleared' : 'OPcache not enabled / nothing to clear', 'ok' => true];
        }

        cache()->forget('falcon_cms_update_check');

        return redirect()->route('admin.update')
            ->with('update_steps', $steps)
            ->with('update_had_error', $hasError);
    }

    /**
     * Locate a real CLI php binary.
     *
     * In a web (php-fpm) request PHP_BINARY points at the php-fpm executable,
     * which cannot run `artisan` (it prints its FastCGI usage and exits). We must
     * find the command-line php instead. Absolute paths are checked first because
     * the php-fpm worker often runs with a stripped PATH, so `which php` may fail.
     */
    protected function findPhpCli(): string
    {
        // When this code itself runs under the CLI SAPI, PHP_BINARY is already cli php.
        if (PHP_SAPI === 'cli' && PHP_BINARY) {
            return PHP_BINARY;
        }

        $candidates = [];
        // Derive a sibling cli binary from the fpm path,
        // e.g. /usr/local/sbin/php-fpm -> /usr/local/sbin/php.
        if (PHP_BINARY) {
            $candidates[] = dirname(PHP_BINARY) . '/php';
            $candidates[] = dirname(dirname(PHP_BINARY)) . '/bin/php';
        }
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';

        foreach ($candidates as $p) {
            // Skip anything that is itself an fpm binary.
            if (str_contains(basename($p), 'fpm')) continue;
            if (@is_executable($p)) return $p;
        }

        // PATH lookups (may be empty inside php-fpm, hence checked last).
        $which = shell_exec('which php 2>/dev/null');
        if ($which && trim($which)) return trim($which);
        $where = shell_exec('where php 2>nul');
        if ($where && trim($where)) {
            $lines = preg_split('/\r?\n/', trim($where));
            if (!empty($lines[0])) return trim($lines[0]);
        }

        return 'php';
    }

    protected function findComposer(): ?string
    {
        $candidates = [
            base_path('composer.phar'),
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/usr/local/bin/composer.phar',
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                return str_ends_with($p, '.phar') ? 'php ' . escapeshellarg($p) : escapeshellarg($p);
            }
        }
        // Try PATH
        $which = shell_exec('which composer 2>/dev/null');
        if ($which && trim($which)) return 'composer';
        $where = shell_exec('where composer 2>nul');
        if ($where && trim($where)) return 'composer';
        return null;
    }

    public function settings()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        
        $pages = Post::where('type', 'page')->where('status', 'published')->orderBy('title')->get();
        $roles = \FalconCms\Core\Models\Role::orderBy('name')->get();
        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();

        return view('falcon-cms::admin.settings.index', compact('pages', 'settings', 'roles'));
    }

    public function updateSettings(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        $data = $request->except('_token');
        
        // Handle Checkboxes
        $data['users_can_register']         = $request->has('users_can_register') ? '1' : '0';
        $data['require_email_verification'] = $request->has('require_email_verification') ? '1' : '0';
        // Multi-device Login & Magic Login are Pro features. Only accept changes to them
        // when the site can edit Pro features; otherwise preserve the stored values so a
        // locked (or crafted) request can never enable them.
        if (falcon_pro_editable('advanced_login')) {
            $data['allow_multi_device']  = $request->has('allow_multi_device') ? '1' : '0';
            $data['magic_login_enabled'] = $request->has('magic_login_enabled') ? '1' : '0';
        } else {
            unset($data['allow_multi_device'], $data['magic_login_enabled'], $data['max_devices']);
        }
        
        // Only update these if we are on the page that contains them to avoid overwriting theme options
        if ($request->has('site_title')) {
            $data['enable_documentation'] = $request->has('enable_documentation') ? '1' : '0';
        }

        if ($request->has('enable_rest_api')) {
            $data['enable_rest_api'] = '1';
        } elseif ($request->is('*/settings/api')) {
            $data['enable_rest_api'] = '0';
        }

        // Sanitize Slugs
        if (isset($data['login_url'])) $data['login_url'] = Str::slug($data['login_url']);
        if (isset($data['register_url'])) $data['register_url'] = Str::slug($data['register_url']);

        foreach ($data as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        falcon_log_activity('settings_updated', "Updated CMS settings");

        return redirect()->back()->with('success', 'Settings updated successfully!');
    }

    public function seoSettings()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        
        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        return view('falcon-cms::admin.settings.seo', compact('settings'));
    }

    public function updateSeoSettings(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        
        $data = $request->except('_token');
        
        // Handle Sitemap Checkboxes — all active post types + taxonomies
        $checkboxes = ['sitemap_include_categories', 'sitemap_include_tags', 'noindex', 'nofollow'];

        try {
            $slugs = \FalconCms\Core\Models\PostType::where('is_active', true)->pluck('slug');
            foreach ($slugs as $slug) {
                $checkboxes[] = 'sitemap_include_' . $slug;
            }
        } catch (\Exception $e) {}

        foreach ($checkboxes as $box) {
            $data[$box] = $request->has($box) ? '1' : '0';
        }
        
        foreach ($data as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return redirect()->back()->with('success', 'SEO Settings updated successfully!');
    }

    public function getRelatedPosts(Request $request)
    {
        $search = $request->query('s');
        $excludeId = $request->query('exclude');
        
        if (!$search) return response()->json([]);

        $posts = \FalconCms\Core\Models\Post::where('status', 'published')
            ->where('id', '!=', $excludeId)
            ->where('title', 'like', '%' . $search . '%')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'type']);

        $posts->map(function($post) {
            $prefix = ($post->type === 'post' || $post->type === 'page') ? '' : $post->type . '/';
            $post->url = url('/' . $prefix . $post->slug);
            return $post;
        });

        return response()->json($posts);
    }

    public function activityLogs(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $query = ActivityLog::with('user')->latest();

        if ($request->filled('s')) {
            $search = $request->s;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->paginate(10)->withQueryString();
        $users = User::all();

        return view('falcon-cms::admin.settings.activity-logs', compact('logs', 'users'));
    }

    public function apiSettings()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        $tokens = auth()->user()->apiTokens()->latest()->get();
        return view('falcon-cms::admin.settings.api', compact('settings', 'tokens'));
    }

    public function generateApiToken(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        $request->validate(['token_name' => 'required|string|max:255']);
        $plain = auth()->user()->createApiToken($request->input('token_name'));

        return redirect()->route('admin.settings.api')
            ->with('new_api_token', $plain)
            ->with('success', 'API token created. Copy it now — it will not be shown again.');
    }

    public function revokeApiToken($id)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        auth()->user()->apiTokens()->where('id', $id)->delete();

        return redirect()->route('admin.settings.api')->with('success', 'API token revoked.');
    }

    public function integrationsSettings()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        return view('falcon-cms::admin.settings.integrations', compact('settings'));
    }

    public function updateIntegrationsSettings(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $keys = ['turnstile_site_key', 'turnstile_secret_key'];
        foreach ($keys as $key) {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $request->input($key, ''), 'updated_at' => now()]
            );
        }

        falcon_log_activity('settings_updated', 'Updated integrations settings');

        return redirect()->back()->with('success', 'Integrations settings saved successfully!');
    }

    public static function emailTemplateDefaults(): array
    {
        return [
            'form_notification' => [
                'label'   => 'Form Submission Notification',
                'subject' => 'New Submission: {{form_name}}',
                'intro'   => 'You have received a new submission. Review the details below to follow up promptly.',
                'footer'  => 'This is an automated notification — no reply is needed.',
                'variables' => ['{{form_name}}', '{{submitted_at}}', '{{ip_address}}', '{{site_name}}'],
            ],
            'order_placed_customer' => [
                'label'   => 'Order Placed — Customer Email',
                'subject' => 'Order Confirmation - Order #{{order_number}}',
                'message' => 'We have received your order <strong>#{{order_number}}</strong> and are currently getting it ready. You will receive another notification once your order status updates.',
                'variables' => ['{{order_number}}', '{{customer_name}}', '{{site_name}}'],
            ],
            'order_placed_admin' => [
                'label'   => 'Order Placed — Admin Notification',
                'subject' => '[New Order] #{{order_number}} — {{customer_name}}',
                'message' => 'A new order <strong>#{{order_number}}</strong> has been placed by <strong>{{customer_name}}</strong>.',
                'variables' => ['{{order_number}}', '{{customer_name}}', '{{order_total}}', '{{site_name}}'],
            ],
            'order_status_updated' => [
                'label'   => 'Order Status Updated',
                'subject' => 'Update on your order #{{order_number}} [{{new_status}}]',
                'message_default'    => 'Your order <strong>#{{order_number}}</strong> status has been updated to <strong>{{new_status}}</strong>.',
                'message_completed'  => 'Good news! Your order is completed and fulfilled. Thank you for shopping with us!',
                'message_processing' => 'We are actively preparing your items. We\'ll let you know once it\'s on its way.',
                'variables' => ['{{order_number}}', '{{customer_name}}', '{{new_status}}', '{{site_name}}'],
            ],
        ];
    }

    public function emailTemplates()
    {
        if (!auth()->user()->hasPermission('manage_settings')) abort(403);

        $defaults  = self::emailTemplateDefaults();
        $templates = [];
        foreach ($defaults as $key => $default) {
            $saved = json_decode(get_cms_option('email_template_' . $key, '{}'), true) ?: [];
            $templates[$key] = array_merge($default, $saved);
        }

        return view('falcon-cms::admin.settings.email-templates', compact('templates', 'defaults'));
    }

    public function updateEmailTemplate(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) abort(403);

        $key = $request->input('template_key');
        $defaults = self::emailTemplateDefaults();

        if (!array_key_exists($key, $defaults)) {
            return redirect()->back()->with('error', 'Invalid template.');
        }

        // Collect all fields for this template (exclude template_key)
        $data = $request->except(['_token', 'template_key']);

        DB::table('cms_settings')->updateOrInsert(
            ['key' => 'email_template_' . $key],
            ['value' => json_encode($data), 'updated_at' => now()]
        );

        falcon_log_activity('settings_updated', "Updated email template: {$key}");

        return redirect()->route('admin.settings.email-templates', ['tab' => $key])
            ->with('success', 'Email template saved successfully.');
    }

    public function testEmailTemplate(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) abort(403);

        $key      = $request->input('template_key');
        $toEmail  = auth()->user()->email;
        $defaults = self::emailTemplateDefaults();

        if (!array_key_exists($key, $defaults)) {
            return response()->json(['success' => false, 'message' => 'Invalid template.']);
        }

        $saved    = json_decode(get_cms_option('email_template_' . $key, '{}'), true) ?: [];
        $tpl      = array_merge($defaults[$key], $saved);
        $siteName = get_cms_option('site_name', config('app.name', 'Falcon CMS'));

        try {
            if ($key === 'form_notification') {
                $subject     = str_replace(['{{form_name}}', '{{site_name}}'], ['Test Form', $siteName], $tpl['subject']);
                $introText   = str_replace('{{site_name}}', $siteName, $tpl['intro']);
                $footerText  = $tpl['footer'];
                $form        = (object)['title' => 'Test Form', 'id' => 0, 'settings' => []];
                $rows        = [
                    ['label' => 'Name', 'is_file' => false, 'is_empty' => false, 'display' => 'John Doe'],
                    ['label' => 'Email', 'is_file' => false, 'is_empty' => false, 'display' => $toEmail],
                    ['label' => 'Message', 'is_file' => false, 'is_empty' => false, 'display' => 'This is a test submission.'],
                ];
                $submittedAt = now()->format('d M Y, H:i');
                $ip          = request()->ip();

                \Illuminate\Support\Facades\Mail::send(
                    'falcon-cms::emails.form.notification',
                    compact('form', 'rows', 'submittedAt', 'ip', 'introText', 'footerText'),
                    fn($msg) => $msg->to($toEmail)->subject($subject)
                );
            } elseif (in_array($key, ['order_placed_customer', 'order_placed_admin', 'order_status_updated'])) {
                $subject = str_replace(
                    ['{{order_number}}', '{{customer_name}}', '{{new_status}}', '{{site_name}}'],
                    ['12345', 'John Doe', 'Processing', $siteName],
                    $tpl['subject']
                );
                \Illuminate\Support\Facades\Mail::raw(
                    "This is a test email for the \"{$tpl['label']}\" template.\n\nSubject: {$subject}\n\nTemplate key: {$key}",
                    fn($msg) => $msg->to($toEmail)->subject("[TEST] {$subject}")
                );
            }

            return response()->json(['success' => true, 'message' => "Test email sent to {$toEmail}"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function analytics()
    {
        // Align with the Analytics menu permission (Sidebar::getPermission) so that
        // checking "Analytics" in the role editor is exactly what grants this page.
        if (!auth()->user()->hasPermission('manage_analytics')) {
            abort(403);
        }

        // ── Date range (dynamic) ──────────────────────────────────────────────
        $range = (int) request()->query('range', 30);
        if (!in_array($range, [7, 30, 90, 365], true)) $range = 30;

        // Locked preview: without Pro (analytics), show believable SAMPLE data behind an
        // upgrade overlay — never the site's real figures. Buying Pro makes it live.
        if (! falcon_pro('analytics')) {
            return view('falcon-cms::admin.analytics.index',
                $this->sampleAnalyticsData($range) + ['analyticsLocked' => true]);
        }

        $start     = now()->subDays($range - 1)->startOfDay();
        $prevStart = (clone $start)->subDays($range);
        $prevEnd   = (clone $start)->subSecond();

        // ── KPIs (with % change vs the previous equal period) ─────────────────
        $totalVisits    = Analytics::where('created_at', '>=', $start)->count();
        $uniqueVisitors = Analytics::where('created_at', '>=', $start)->distinct()->count('ip_address');
        $prevVisits     = Analytics::whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $visitsChange   = $prevVisits > 0 ? round((($totalVisits - $prevVisits) / $prevVisits) * 100, 1) : ($totalVisits > 0 ? 100 : 0);
        $today          = Analytics::whereDate('created_at', now()->toDateString())->count();
        $thisMonth      = Analytics::where('created_at', '>=', now()->startOfMonth())->count();

        // ── Daily series (visits + unique), zero-filled across the range ──────
        $daily = Analytics::where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as visits'), DB::raw('COUNT(DISTINCT ip_address) as uniques'))
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $labels = $visitsSeries = $uniqueSeries = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->toDateString();
            $labels[]       = $day->format('M j');
            $visitsSeries[] = (int) ($daily[$key]->visits ?? 0);
            $uniqueSeries[] = (int) ($daily[$key]->uniques ?? 0);
        }

        // ── Distributions (browser / device / os) ────────────────────────────
        // Exclude bot/crawler rows left in legacy data so the charts show humans only
        // (new bot visits are already filtered at tracking time).
        $dist = function (string $col) use ($start) {
            return Analytics::select($col, DB::raw('count(*) as count'))
                ->where('created_at', '>=', $start)
                ->whereNotIn($col, ['bot', 'Bot / Crawler'])
                ->groupBy($col)->orderByDesc('count')->get()
                ->map(fn($r) => ['label' => $r->{$col} ?: 'Unknown', 'count' => (int) $r->count])->values();
        };
        $browsers = $dist('browser');
        $devices  = $dist('device_type');
        $osDist   = $dist('os');

        // ── Top pages & referrers (empty referrer = Direct) ──────────────────
        $topPages = Analytics::select('url', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $start)
            ->groupBy('url')->orderByDesc('count')->limit(8)->get();

        $topReferrers = Analytics::select(DB::raw("COALESCE(NULLIF(referrer, ''), 'Direct') as ref"), DB::raw('count(*) as count'))
            ->where('created_at', '>=', $start)
            ->groupBy('ref')->orderByDesc('count')->limit(8)->get();

        // ── Top countries (geo-resolved; null until geo lookup completes) ─────
        $topCountries = Analytics::select('country', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $start)
            ->whereNotNull('country')->where('country', '!=', '')
            ->groupBy('country')->orderByDesc('count')->limit(8)->get()
            ->map(fn($r) => ['label' => $r->country, 'count' => (int) $r->count])->values();

        // Visitors grouped by ISO-2 country code, for the world map widget
        $visitorsByCountry = Analytics::select('country_code', DB::raw('MAX(country) as country'), DB::raw('count(*) as visitors'))
            ->where('created_at', '>=', $start)
            ->whereNotNull('country_code')->where('country_code', '!=', '')
            ->groupBy('country_code')->orderByDesc('visitors')->get()
            ->map(fn($r) => ['code' => strtoupper($r->country_code), 'name' => $r->country ?: strtoupper($r->country_code), 'visitors' => (int) $r->visitors])
            ->values();

        // ── Engagement: real-time, new vs returning, sessions, bounce ─────────
        $activeNow = Analytics::where('created_at', '>=', now()->subMinutes(5))->distinct()->count('ip_address');

        $returningVisitors = Analytics::where('created_at', '>=', $start)
            ->whereIn('ip_address', function ($q) use ($start) {
                $q->select('ip_address')->from('cms_analytics')->where('created_at', '<', $start);
            })->distinct()->count('ip_address');
        $newVisitors = max(0, $uniqueVisitors - $returningVisitors);

        // Sessions & bounce via a 30-minute inactivity window (gaps-and-islands in PHP).
        // Skipped on very large datasets — the daily rollup table handles that at scale.
        $sessions = $bounceRate = $pagesPerSession = null;
        if ($totalVisits > 0 && $totalVisits <= 100000) {
            $rows = Analytics::where('created_at', '>=', $start)
                ->orderBy('ip_address')->orderBy('created_at')
                ->get(['ip_address', 'created_at']);
            $gap = 1800; // 30 min
            $sessions = 0; $bounces = 0; $curIp = null; $lastTs = null; $pageCount = 0;
            $close = function () use (&$sessions, &$bounces, &$pageCount) {
                if ($pageCount > 0) { $sessions++; if ($pageCount === 1) $bounces++; }
                $pageCount = 0;
            };
            foreach ($rows as $r) {
                $ts = strtotime((string) $r->created_at);
                if ($r->ip_address !== $curIp) { $close(); $curIp = $r->ip_address; $lastTs = null; }
                if ($lastTs !== null && ($ts - $lastTs) > $gap) { $close(); }
                $pageCount++; $lastTs = $ts;
            }
            $close();
            $bounceRate      = $sessions > 0 ? round($bounces / $sessions * 100, 1) : 0;
            $pagesPerSession = $sessions > 0 ? round($totalVisits / $sessions, 1) : 0;
        }

        // ── Channel grouping (Direct / Organic Search / Social / Referral) ────
        $siteHost = strtolower(request()->getHost());
        $channelOf = function (?string $ref) use ($siteHost) {
            if (!$ref) return 'Direct';
            $h = strtolower((string) parse_url($ref, PHP_URL_HOST));
            if ($h === '' || $h === $siteHost) return 'Direct';
            if (preg_match('/(^|\.)(google|bing|yahoo|duckduckgo|yandex|baidu|ecosia|ask)\./', $h)) return 'Organic Search';
            if (preg_match('/(^|\.)(facebook|fb|instagram|twitter|t\.co|x\.com|linkedin|youtube|youtu\.be|pinterest|reddit|tiktok|whatsapp|telegram)/', $h)) return 'Social';
            return 'Referral';
        };
        $channelCounts = [];
        foreach (Analytics::select('referrer', DB::raw('count(*) as count'))->where('created_at', '>=', $start)->groupBy('referrer')->get() as $rr) {
            $ch = $channelOf($rr->referrer);
            $channelCounts[$ch] = ($channelCounts[$ch] ?? 0) + (int) $rr->count;
        }
        arsort($channelCounts);
        $channels = collect($channelCounts)->map(fn($v, $k) => ['label' => $k, 'count' => $v])->values();

        // ── Named traffic sources (Google / Facebook / Instagram / … / Direct / other site) ──
        $sourceOf = function (?string $ref) use ($siteHost) {
            if (!$ref) return ['Direct', null];
            $h = strtolower((string) parse_url($ref, PHP_URL_HOST));
            if ($h === '' || $h === $siteHost) return ['Direct', null];
            $h = preg_replace('/^www\./', '', $h);
            $named = [
                'google' => ['Google', 'google.com'], 'bing' => ['Bing', 'bing.com'],
                'yahoo' => ['Yahoo', 'yahoo.com'], 'duckduckgo' => ['DuckDuckGo', 'duckduckgo.com'],
                'yandex' => ['Yandex', 'yandex.com'], 'baidu' => ['Baidu', 'baidu.com'], 'ecosia' => ['Ecosia', 'ecosia.org'],
                'instagram' => ['Instagram', 'instagram.com'], 'facebook' => ['Facebook', 'facebook.com'],
                'fb.com' => ['Facebook', 'facebook.com'], 'fb.me' => ['Facebook', 'facebook.com'],
                'youtube' => ['YouTube', 'youtube.com'], 'youtu.be' => ['YouTube', 'youtube.com'],
                'twitter' => ['X (Twitter)', 'x.com'], 'x.com' => ['X (Twitter)', 'x.com'], 't.co' => ['X (Twitter)', 'x.com'],
                'linkedin' => ['LinkedIn', 'linkedin.com'], 'lnkd.in' => ['LinkedIn', 'linkedin.com'],
                'pinterest' => ['Pinterest', 'pinterest.com'], 'reddit' => ['Reddit', 'reddit.com'],
                'tiktok' => ['TikTok', 'tiktok.com'], 'whatsapp' => ['WhatsApp', 'whatsapp.com'], 'wa.me' => ['WhatsApp', 'whatsapp.com'],
                'telegram' => ['Telegram', 'telegram.org'], 't.me' => ['Telegram', 'telegram.org'],
            ];
            foreach ($named as $needle => $pair) {
                if (strpos($h, $needle) !== false) return $pair;
            }
            return [$h, $h]; // any other site — show its domain
        };
        $sourceCounts = [];
        foreach (Analytics::select('referrer', DB::raw('count(*) as count'))->where('created_at', '>=', $start)->groupBy('referrer')->get() as $rr) {
            [$label, $domain] = $sourceOf($rr->referrer);
            if (!isset($sourceCounts[$label])) $sourceCounts[$label] = ['label' => $label, 'count' => 0, 'domain' => $domain];
            $sourceCounts[$label]['count'] += (int) $rr->count;
        }
        $trafficSources = collect($sourceCounts)->sortByDesc('count')->take(12)->values();


        // ── Recent visits ─────────────────────────────────────────────────────
        $recent = Analytics::latest()->limit(12)->get();

        return view('falcon-cms::admin.analytics.index', compact(
            'range', 'totalVisits', 'uniqueVisitors', 'visitsChange', 'today', 'thisMonth',
            'labels', 'visitsSeries', 'uniqueSeries',
            'browsers', 'devices', 'osDist', 'topPages', 'topReferrers', 'topCountries', 'recent',
            'activeNow', 'newVisitors', 'returningVisitors', 'sessions', 'bounceRate', 'pagesPerSession', 'channels',
            'visitorsByCountry', 'trafficSources'
        ) + ['analyticsLocked' => false]);
    }

    /**
     * Believable SAMPLE analytics for the locked preview — no real data touched. Buying Pro
     * swaps this for the live queries above. Shapes mirror the real variables exactly.
     */
    private function sampleAnalyticsData(int $range): array
    {
        $labels = $visitsSeries = $uniqueSeries = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('M j');
            $v = (int) max(45, round(300 + 130 * sin($i / 3.2) + ($range - $i) * 1.4 + mt_rand(-45, 70)));
            $visitsSeries[] = $v;
            $uniqueSeries[] = (int) round($v * 0.66);
        }
        $totalVisits    = array_sum($visitsSeries);
        $uniqueVisitors = array_sum($uniqueSeries);
        $pct = fn ($n) => (int) round($uniqueVisitors * $n);

        $arr = fn (array $rows) => collect($rows)->map(fn ($r) => ['label' => $r[0], 'count' => $r[1]])->values();
        $obj = fn (array $rows, string $k) => collect($rows)->map(fn ($r) => (object) [$k => $r[0], 'count' => $r[1]])->values();

        $recent = collect([
            ['/',                'Chrome',  'Desktop', 'Windows', 'US', 'New York',     'United States', 2],
            ['/pricing',         'Safari',  'Mobile',  'iOS',     'GB', 'London',       'United Kingdom', 5],
            ['/blog/getting-started', 'Chrome', 'Mobile', 'Android', 'IN', 'Mumbai',   'India', 9],
            ['/contact',         'Edge',    'Desktop', 'Windows', 'CA', 'Toronto',      'Canada', 14],
            ['/features',        'Firefox', 'Desktop', 'Linux',   'DE', 'Berlin',       'Germany', 21],
            ['/',                'Chrome',  'Tablet',  'Android', 'AU', 'Sydney',       'Australia', 33],
            ['/about',           'Safari',  'Mobile',  'iOS',     'FR', 'Paris',        'France', 48],
            ['/blog',            'Chrome',  'Desktop', 'macOS',   'BR', 'São Paulo',    'Brazil', 60],
        ])->map(fn ($r) => (object) [
            'url' => $r[0], 'browser' => $r[1], 'device_type' => $r[2], 'os' => $r[3],
            'country_code' => $r[4], 'city' => $r[5], 'country' => $r[6],
            'ip_address' => '203.0.113.' . mt_rand(2, 250), 'referrer' => null,
            'created_at' => now()->subMinutes($r[7]),
        ]);

        return [
            'range' => $range,
            'totalVisits' => $totalVisits, 'uniqueVisitors' => $uniqueVisitors,
            'visitsChange' => 14.2, 'today' => end($visitsSeries) ?: 0,
            'thisMonth' => (int) round($totalVisits * (30 / max(1, $range))),
            'labels' => $labels, 'visitsSeries' => $visitsSeries, 'uniqueSeries' => $uniqueSeries,
            'browsers' => $arr([['Chrome', $pct(0.62)], ['Safari', $pct(0.19)], ['Firefox', $pct(0.09)], ['Edge', $pct(0.07)], ['Opera', $pct(0.03)]]),
            'devices'  => $arr([['Desktop', $pct(0.57)], ['Mobile', $pct(0.37)], ['Tablet', $pct(0.06)]]),
            'osDist'   => $arr([['Windows', $pct(0.46)], ['Android', $pct(0.24)], ['iOS', $pct(0.17)], ['macOS', $pct(0.10)], ['Linux', $pct(0.03)]]),
            'topPages' => $obj([['/', $pct(0.28)], ['/pricing', $pct(0.14)], ['/features', $pct(0.11)], ['/blog', $pct(0.09)], ['/about', $pct(0.07)], ['/contact', $pct(0.05)], ['/blog/getting-started', $pct(0.04)], ['/docs', $pct(0.03)]], 'url'),
            'topReferrers' => $obj([['Direct', $pct(0.42)], ['google.com', $pct(0.24)], ['facebook.com', $pct(0.12)], ['x.com', $pct(0.06)], ['linkedin.com', $pct(0.05)], ['github.com', $pct(0.03)]], 'ref'),
            'topCountries' => $arr([['United States', $pct(0.34)], ['United Kingdom', $pct(0.13)], ['India', $pct(0.11)], ['Germany', $pct(0.08)], ['Canada', $pct(0.07)], ['Australia', $pct(0.05)], ['France', $pct(0.04)], ['Brazil', $pct(0.03)]]),
            'visitorsByCountry' => [
                ['code' => 'US', 'name' => 'United States', 'visitors' => $pct(0.34)],
                ['code' => 'GB', 'name' => 'United Kingdom', 'visitors' => $pct(0.13)],
                ['code' => 'IN', 'name' => 'India', 'visitors' => $pct(0.11)],
                ['code' => 'DE', 'name' => 'Germany', 'visitors' => $pct(0.08)],
                ['code' => 'CA', 'name' => 'Canada', 'visitors' => $pct(0.07)],
                ['code' => 'AU', 'name' => 'Australia', 'visitors' => $pct(0.05)],
                ['code' => 'FR', 'name' => 'France', 'visitors' => $pct(0.04)],
                ['code' => 'BR', 'name' => 'Brazil', 'visitors' => $pct(0.03)],
            ],
            'activeNow' => mt_rand(18, 42), 'newVisitors' => $pct(0.61), 'returningVisitors' => $pct(0.39),
            'sessions' => (int) round($totalVisits / 2.3), 'bounceRate' => 42.5, 'pagesPerSession' => 2.3,
            'channels' => $arr([['Direct', $pct(0.42)], ['Organic Search', $pct(0.30)], ['Social', $pct(0.18)], ['Referral', $pct(0.10)]]),
            'trafficSources' => collect([
                ['label' => 'Google', 'count' => $pct(0.24), 'domain' => 'google.com'],
                ['label' => 'Direct', 'count' => $pct(0.42), 'domain' => null],
                ['label' => 'Facebook', 'count' => $pct(0.12), 'domain' => 'facebook.com'],
                ['label' => 'X (Twitter)', 'count' => $pct(0.06), 'domain' => 'x.com'],
                ['label' => 'LinkedIn', 'count' => $pct(0.05), 'domain' => 'linkedin.com'],
            ])->values(),
            'recent' => $recent,
        ];
    }

    /** Live real-time data for the analytics page (polled by JS). */
    public function analyticsRealtime()
    {
        if (!auth()->user()->hasPermission('manage_analytics')) {
            abort(403);
        }

        // Locked preview → sample live figures, never the site's real real-time data.
        if (! falcon_pro('analytics')) {
            return response()->json([
                'active'      => mt_rand(18, 42),
                'minutes'     => collect(range(1, 30))->map(fn () => mt_rand(2, 22))->all(),
                'activePages' => [
                    ['path' => '/', 'count' => mt_rand(6, 16)],
                    ['path' => '/pricing', 'count' => mt_rand(3, 9)],
                    ['path' => '/features', 'count' => mt_rand(2, 7)],
                ],
                'recent' => [
                    ['path' => '/',        'country' => 'United States',  'code' => 'us', 'device' => 'Desktop', 'ago' => 'just now'],
                    ['path' => '/pricing', 'country' => 'United Kingdom', 'code' => 'gb', 'device' => 'Mobile',  'ago' => '1 min ago'],
                    ['path' => '/blog',    'country' => 'India',          'code' => 'in', 'device' => 'Mobile',  'ago' => '3 mins ago'],
                ],
                'time' => now()->format('g:i:s A'),
            ]);
        }

        $since5  = now()->subMinutes(5);
        $since30 = now()->subMinutes(30);
        $host    = request()->getSchemeAndHttpHost();

        $active = Analytics::where('created_at', '>=', $since5)->distinct()->count('ip_address');

        // Per-minute visit counts for the last 30 minutes, zero-filled.
        $perMin = Analytics::where('created_at', '>=', $since30)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as m"), DB::raw('count(*) as c'))
            ->groupBy('m')->pluck('c', 'm');
        $minutes = [];
        for ($i = 29; $i >= 0; $i--) {
            $minutes[] = (int) ($perMin[now()->subMinutes($i)->format('Y-m-d H:i')] ?? 0);
        }

        $activePages = Analytics::where('created_at', '>=', $since5)
            ->select('url', DB::raw('count(*) as count'))
            ->groupBy('url')->orderByDesc('count')->limit(6)->get()
            ->map(fn ($r) => ['path' => falcon_visit_page($r->url), 'count' => (int) $r->count]);

        $recent = Analytics::latest()->limit(8)->get()->map(fn ($v) => [
            'path'    => \Illuminate\Support\Str::limit(falcon_visit_page($v->url), 40),
            'country' => $v->country,
            'code'    => $v->country_code ? strtolower($v->country_code) : null,
            'device'  => $v->device_type,
            'ago'     => $v->created_at ? \Illuminate\Support\Carbon::parse($v->created_at)->diffForHumans(null, true) . ' ago' : '',
        ]);

        return response()->json([
            'active'      => $active,
            'minutes'     => $minutes,
            'activePages' => $activePages,
            'recent'      => $recent,
            'time'        => now()->format('g:i:s A'),
        ]);
    }

    public function documentation()
    {
        if (get_cms_option('enable_documentation', '1') !== '1') {
            abort(403, 'Documentation is disabled by the administrator.');
        }

        $readmePath = __DIR__ . '/../../../../README.md';
        $content = '';
        if (file_exists($readmePath)) {
            $content = file_get_contents($readmePath);
        }

        return view('falcon-cms::admin.documentation', compact('content'));
    }
    
    public function bulkDeleteLogs(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $ids = $request->input('log_ids', []);
        $action = $request->input('bulk_action');

        if ($action === 'delete' && !empty($ids)) {
            ActivityLog::whereIn('id', $ids)->delete();
            falcon_log_activity('logs_bulk_deleted', "Deleted " . count($ids) . " activity log entries");
            return redirect()->back()->with('success', 'Selected logs deleted successfully!');
        }

        return redirect()->back();
    }
}
