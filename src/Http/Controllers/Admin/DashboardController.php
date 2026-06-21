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
        ];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('shop_orders')) {
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
        $data['users_can_register']  = $request->has('users_can_register') ? '1' : '0';
        $data['allow_multi_device']  = $request->has('allow_multi_device') ? '1' : '0';
        $data['magic_login_enabled'] = $request->has('magic_login_enabled') ? '1' : '0';
        
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
        $dist = function (string $col) use ($start) {
            return Analytics::select($col, DB::raw('count(*) as count'))
                ->where('created_at', '>=', $start)
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

        // ── Recent visits ─────────────────────────────────────────────────────
        $recent = Analytics::latest()->limit(12)->get();

        return view('falcon-cms::admin.analytics.index', compact(
            'range', 'totalVisits', 'uniqueVisitors', 'visitsChange', 'today', 'thisMonth',
            'labels', 'visitsSeries', 'uniqueSeries',
            'browsers', 'devices', 'osDist', 'topPages', 'topReferrers', 'recent'
        ));
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
