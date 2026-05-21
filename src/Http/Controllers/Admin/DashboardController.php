<?php

namespace Acme\CmsDashboard\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Acme\CmsDashboard\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Acme\CmsDashboard\Models\ActivityLog;
use Acme\CmsDashboard\Models\Analytics;

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
        $totalSubmissions = \Acme\CmsDashboard\Models\FormSubmission::count();
        $conversionRate = ($totalVisitors > 0) ? round(($totalSubmissions / $totalVisitors) * 100, 1) : 0;

        // 3. Security Status Check
        $recentBlockedIps = \Acme\CmsDashboard\Models\BlockedIp::where('created_at', '>', now()->subDay())->count();
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
                'count' => \Acme\CmsDashboard\Models\BlockedIp::count(),
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
        $currency = get_cms_option('shop_currency_symbol', '$');
        $ecoStats = [
            'total_orders'    => 0,
            'total_revenue'   => 0,
            'pending_orders'  => 0,
            'total_products'  => 0,
            'orders_today'    => 0,
            'orders_month'    => 0,
            'status_counts'   => [],
            'monthly_revenue' => array_fill(0, 7, 0),
        ];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('shop_orders')) {
                $hasShop = true;
                $ecoStats['total_orders']   = \Acme\CmsDashboard\Models\Order::count();
                $ecoStats['total_revenue']  = \Acme\CmsDashboard\Models\Order::whereIn('status', ['completed', 'processing'])->sum('total');
                $ecoStats['pending_orders'] = \Acme\CmsDashboard\Models\Order::where('status', 'pending')->count();
                $ecoStats['total_products'] = Post::where('type', 'product')->count();
                $ecoStats['orders_today']   = \Acme\CmsDashboard\Models\Order::whereDate('created_at', today())->count();
                $ecoStats['orders_month']   = \Acme\CmsDashboard\Models\Order::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
                $ecoStats['status_counts']  = \Acme\CmsDashboard\Models\Order::selectRaw('status, count(*) as total')
                    ->groupBy('status')->pluck('total', 'status')->toArray();
                $rev = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d    = now()->subMonths($i);
                    $rev[] = (float) \Acme\CmsDashboard\Models\Order::whereIn('status', ['completed', 'processing'])
                        ->whereBetween('created_at', [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()])
                        ->sum('total');
                }
                $ecoStats['monthly_revenue'] = $rev;
            }
        } catch (\Exception $e) {}

        // Ensure Dashboard > Updates submenu exists (self-heals on existing installs)
        $this->ensureUpdateMenu();

        // Refresh update cache silently (only when expired, max once per 6h)
        if (!cache()->has('lazy_cms_update_check')) {
            try { lazy_check_update(); } catch (\Exception $e) {}
        }

        return view('cms-dashboard::admin.dashboard', compact('stats', 'hasShop', 'ecoStats', 'currency'));
    }

    protected function ensureUpdateMenu(): void
    {
        try {
            $dash = \Acme\CmsDashboard\Models\Menu::where('title', 'Dashboard')->whereNull('parent_id')->first();
            if (!$dash) return;

            \Acme\CmsDashboard\Models\Menu::firstOrCreate(
                ['title' => 'Overview', 'parent_id' => $dash->id],
                ['route' => 'admin.dashboard.index', 'order' => 1]
            );
            \Acme\CmsDashboard\Models\Menu::firstOrCreate(
                ['title' => 'Updates', 'parent_id' => $dash->id],
                ['route' => 'admin.update', 'order' => 2]
            );
        } catch (\Exception $e) {}
    }

    public function updateCheck()
    {
        $update = lazy_check_update(force: true);
        return view('cms-dashboard::admin.update', compact('update'));
    }

    public function runUpdate()
    {
        set_time_limit(300);

        $steps   = [];
        $hasError = false;

        // Step 1: composer update
        $composerBin = $this->findComposer();
        if ($composerBin) {
            $cmd = $composerBin . ' update tareqcodex/lazy-cms-rebuild --no-interaction --prefer-dist --no-progress 2>&1';
            exec('cd ' . escapeshellarg(base_path()) . ' && ' . $cmd, $composerOut, $exitCode);
            $steps[] = ['label' => 'composer update', 'output' => implode("\n", $composerOut), 'ok' => $exitCode === 0];
            if ($exitCode !== 0) $hasError = true;
        } else {
            $steps[] = ['label' => 'composer update', 'output' => 'composer not found in PATH. Run manually: composer update tareqcodex/lazy-cms-rebuild', 'ok' => false];
            $hasError = true;
        }

        // Step 2: lazy:update — run as a subprocess so the freshly downloaded code
        // is used. Artisan::call() would re-use the old in-memory ServiceProvider
        // loaded before composer update ran, causing "command does not exist".
        $phpBin     = PHP_BINARY;
        $artisan    = base_path('artisan');
        $lazyCmd    = escapeshellarg($phpBin) . ' ' . escapeshellarg($artisan) . ' lazy:update --no-ansi 2>&1';
        exec($lazyCmd, $lazyOut, $lazyExit);
        $steps[] = ['label' => 'php artisan lazy:update', 'output' => trim(implode("\n", $lazyOut)), 'ok' => $lazyExit === 0];
        if ($lazyExit !== 0) $hasError = true;

        cache()->forget('lazy_cms_update_check');

        return redirect()->route('admin.update')
            ->with('update_steps', $steps)
            ->with('update_had_error', $hasError);
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
        $roles = \Acme\CmsDashboard\Models\Role::orderBy('name')->get();
        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();

        return view('cms-dashboard::admin.settings.index', compact('pages', 'settings', 'roles'));
    }

    public function updateSettings(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        $data = $request->except('_token');
        
        // Handle Checkboxes
        $data['users_can_register'] = $request->has('users_can_register') ? '1' : '0';
        $data['allow_multi_device'] = $request->has('allow_multi_device') ? '1' : '0';
        
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

        lazy_log_activity('settings_updated', "Updated CMS settings");

        return redirect()->back()->with('success', 'Settings updated successfully!');
    }

    public function seoSettings()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        
        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        return view('cms-dashboard::admin.settings.seo', compact('settings'));
    }

    public function updateSeoSettings(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        
        $data = $request->except('_token');
        
        // Handle Sitemap Checkboxes
        $checkboxes = ['sitemap_include_posts', 'sitemap_include_pages', 'sitemap_include_categories', 'sitemap_include_tags', 'noindex', 'nofollow'];
        
        // Dynamic CPT sitemap checkboxes
        try {
            $cpts = \Acme\CmsDashboard\Models\PostType::where('is_builtin', false)->pluck('slug');
            foreach ($cpts as $slug) {
                $checkboxes[] = 'sitemap_include_cpt_' . $slug;
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

        $posts = \Acme\CmsDashboard\Models\Post::where('status', 'published')
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

        return view('cms-dashboard::admin.settings.activity-logs', compact('logs', 'users'));
    }

    public function apiSettings()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        
        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        return view('cms-dashboard::admin.settings.api', compact('settings'));
    }

    public function analytics()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $days = 30;
        $startDate = now()->subDays($days);

        // Daily Visits
        $dailyVisits = Analytics::where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top Pages
        $topPages = Analytics::select('url', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('url')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Browser Distribution
        $browsers = Analytics::select('browser', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('browser')
            ->get();

        // Device Distribution
        $devices = Analytics::select('device_type', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('device_type')
            ->get();

        return view('cms-dashboard::admin.analytics.index', compact('dailyVisits', 'topPages', 'browsers', 'devices'));
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

        return view('cms-dashboard::admin.documentation', compact('content'));
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
            lazy_log_activity('logs_bulk_deleted', "Deleted " . count($ids) . " activity log entries");
            return redirect()->back()->with('success', 'Selected logs deleted successfully!');
        }

        return redirect()->back();
    }
}
