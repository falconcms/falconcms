<?php

namespace FalconCms\Core;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class FalconCmsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return ($user && method_exists($user, 'hasRole') && $user->hasRole('super-admin')) ? true : null;
        });

        // Share activeTheme globally with all views
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        view()->share('activeTheme', $activeTheme);

        // Load active drop-in plugins. Done in boot() (not register()) so the DB —
        // which tells us which plugins are active — is ready in both web and console.
        // Each plugin's provider is registered and its plugin.php bootstrap required
        // here; the view/migration/route wiring below then picks up what loaded.
        // Fatal-safe: a throwing plugin is auto-deactivated, never crashes the CMS.
        try {
            $this->app->make(\FalconCms\Core\Support\PluginManager::class)->loadActive($this->app);
        } catch (\Throwable $e) {
            // Never let plugin loading stop the CMS from booting.
        }

        // Register Middlewares
        $this->app['router']->prependMiddlewareToGroup('web', \FalconCms\Core\Http\Middleware\RedirectMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', \FalconCms\Core\Http\Middleware\TrackVisits::class);
        $this->app['router']->pushMiddlewareToGroup('web', \FalconCms\Core\Http\Middleware\LocalizationMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', \FalconCms\Core\Http\Middleware\BuilderShortcodeMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', \FalconCms\Core\Http\Middleware\PersistCart::class);
        $this->app['router']->aliasMiddleware('api.token', \FalconCms\Core\Http\Middleware\AuthenticateApiToken::class);

        $this->app->booted(function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

            // Active plugins' own routes are registered BEFORE the CMS web routes
            // so a plugin route can be reached — otherwise the frontend catch-all
            // (a greedy /{slug} at the end of web.php) would shadow every plugin URL.
            foreach ($this->loadedPlugins() as $manifest) {
                $routes = $manifest['dir'] . '/routes/web.php';
                if (is_file($routes)) {
                    $this->loadRoutesFrom($routes);
                }
            }

            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'falcon-cms');

        // Active plugins' views ("<slug>::view") and migrations, by convention.
        foreach ($this->loadedPlugins() as $slug => $manifest) {
            $views = $manifest['dir'] . '/resources/views';
            if (is_dir($views)) {
                $this->loadViewsFrom($views, $slug);
            }
            $migrations = $manifest['dir'] . '/database/migrations';
            if (is_dir($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }
        }

        // Register View Composers for Magic Keys
        $viewMap = [
            'admin.users.edit' => 'users-edit',
            'admin.settings.index' => 'general-settings',
            
            'falcon-cms::admin.users.edit' => 'users-edit',
            'falcon-cms::admin.settings.index'         => 'general-settings',
        ];

        view()->composer('*', function ($view) use ($viewMap) {
            $viewName = $view->getName();
            $magicKey = $viewMap[$viewName] ?? null;

            if ($magicKey) {
                $dynamicFields = config("falcon-options.hooks.{$magicKey}.fields", []);
                $settings = \Illuminate\Support\Facades\DB::table('cms_settings')->pluck('value', 'key')->toArray();
                $view->with(compact('dynamicFields', 'settings'));
            }
        });

        Blade::componentNamespace('FalconCms\\Core\\View\\Components', 'falcon-cms');
        Blade::component('falcon-cms::components.frontend.breadcrumbs', 'falcon-breadcrumbs');

        // Render theme/plugin-registered settings fields into the native settings
        // screens. The extension view echoes inside the native <form>, so custom
        // fields save through each screen's existing controller (no route changes).
        // Screen id => the hook tag fired at the bottom of that screen's form.
        // Each entry: screen id => [hook tag fired in that screen's form, option
        // prefix its controller stores keys under, Alpine tab variable if the
        // screen has its own client-side tab UI]. '' prefix = cms_settings as-is;
        // 'shop_' = the Shop screen namespaces every posted key. A non-null tab
        // var (Shop's 'tab') groups injected fields into their tab's panel.
        if (function_exists('add_falcon_action')) {
            $settingsScreens = [
                'general'      => ['falcon_settings_form_bottom', '', null],
                'seo'          => ['falcon_seo_settings_form_bottom', '', null],
                'api'          => ['falcon_api_settings_form_bottom', '', null],
                'integrations' => ['falcon_integrations_settings_form_bottom', '', null],
                'shop'         => ['falcon_shop_settings_form_bottom', 'shop_', 'tab'],
            ];
            foreach ($settingsScreens as $screen => [$hook, $prefix, $tabVar]) {
                add_falcon_action($hook, function () use ($screen, $prefix, $tabVar) {
                    echo view('falcon-cms::admin.settings.extension', [
                        'screen'       => $screen,
                        'optionPrefix' => $prefix,
                        'alpineTabVar' => $tabVar,
                    ])->render();
                });
            }
        }

        // Register commands always (not just in console) so Artisan::call() works from web requests
        $this->commands([
            \FalconCms\Core\Console\Commands\FalconList::class,
            \FalconCms\Core\Console\Commands\MakeDashboardPage::class,
            \FalconCms\Core\Console\Commands\InstallFalconCms::class,
            \FalconCms\Core\Console\Commands\UninstallFalconCms::class,
            \FalconCms\Core\Console\Commands\UninstallFalconDatabase::class,
            \FalconCms\Core\Console\Commands\SeedFalconCms::class,
            \FalconCms\Core\Console\Commands\UpdateFalconCms::class,
            \FalconCms\Core\Console\Commands\PublishScheduledPosts::class,
            \FalconCms\Core\Console\Commands\ExpireSalePrices::class,
            \FalconCms\Core\Console\Commands\PruneAnalytics::class,
            \FalconCms\Core\Console\Commands\PluginList::class,
            \FalconCms\Core\Console\Commands\PluginActivate::class,
            \FalconCms\Core\Console\Commands\PluginDeactivate::class,
            \FalconCms\Core\Console\Commands\MakePlugin::class,
        ]);

        // Register scheduled tasks from within the package
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command('falcon:publish-scheduled')->everyMinute()->withoutOverlapping();
            $schedule->command('falcon:expire-sales')->daily();
            $schedule->command('falcon:prune-analytics')->dailyAt('03:30')->withoutOverlapping();
        });

        // Cron-independent fallback: many hosts (and local dev) never run `schedule:run`,
        // so scheduled posts/pages/products/CPTs would never go live. After EVERY web response is
        // sent (terminating = zero user-facing latency), flip any scheduled item whose time has
        // arrived to published. No throttle, so the very first visit at/after the scheduled time
        // publishes it — as close to "exactly on time" as traffic allows. Type-agnostic (the base
        // Post model has no global scope) so it covers post, page, product and every CPT.
        if (!$this->app->runningInConsole()) {
            $this->app->terminating(function () {
                try {
                    \FalconCms\Core\Models\Post::where('status', 'scheduled')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())
                        ->update(['status' => 'published']);
                } catch (\Throwable $e) {
                    // Never let scheduling maintenance affect the request.
                }

                // Cron-independent analytics retention: once a day (cache-locked), trim a capped
                // batch of expired rows after the response is sent — keeps the table lean even on
                // hosts that never run `schedule:run`. The scheduled command does the full sweep.
                try {
                    if (\Illuminate\Support\Facades\Cache::add('falcon_analytics_prune_lock', 1, now()->addDay())) {
                        $days = max(7, (int) get_cms_option('analytics_retention_days', 365));
                        \Illuminate\Support\Facades\DB::table('cms_analytics')
                            ->where('created_at', '<', now()->subDays($days))
                            ->limit(5000)->delete();
                    }
                } catch (\Throwable $e) {
                    // Best-effort; never let maintenance affect the request.
                }
            });
        }

        // Render 404s through the active theme's 404 template so the Layout Builder
        // (header/footer/page-title-bar/content hooks + "404 Page" conditions) applies.
        $this->callAfterResolving(\Illuminate\Contracts\Debug\ExceptionHandler::class, function ($handler) {
            if (!method_exists($handler, 'renderable')) return;
            $handler->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
                if (!$request || $request->is('admin', 'admin/*') || $request->expectsJson()) {
                    return null; // keep admin/API/default handling
                }
                try {
                    return response()->view(falcon_theme_view('404'), ['exception' => $e], 404);
                } catch (\Throwable $ex) {
                    return null; // fall back to Laravel's default 404
                }
            });
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/falcon-cms'),
            ], 'falcon-cms-views');

            $this->publishes([
                __DIR__ . '/../resources/views/themes' => resource_path('views/themes'),
            ], 'falcon-cms-themes');

            $this->publishes([
                __DIR__ . '/../public/assets' => public_path('vendor/falcon-cms'),
            ], 'falcon-cms-assets');

            // 1. Parent theme only — safe to publish with --force on every update
            $this->publishes([
                __DIR__ . '/../resources/views/themes/falcon-theme' => resource_path('views/themes/falcon-theme'),
            ], 'falcon-themes');

            // Child theme — published WITHOUT --force so user customizations are never overwritten
            $this->publishes([
                __DIR__ . '/../resources/views/themes/falcon-theme-child' => resource_path('views/themes/falcon-theme-child'),
            ], 'falcon-theme-child');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/falcon-cms'),
            ], 'lazy-views');
        }
    }

    public function register(): void
    {
        require_once __DIR__ . '/helpers.php';
        require_once __DIR__ . '/ecommerce_helpers.php';
        require_once __DIR__ . '/admin-menu.php';
        require_once __DIR__ . '/settings-fields.php';
        $this->mergeConfigFrom(__DIR__ . '/../config/falcon-options.php', 'falcon-options');

        // Runtime admin sidebar menu registry (WordPress-style add_menu_page). Shared
        // for the request so all falcon_add_menu_page() calls land in one place.
        $this->app->singleton(\FalconCms\Core\Support\AdminMenu::class);

        // Registry for extending the native Settings screens (add_settings_field /
        // add_settings_tab). Bound before theme functions.php loads so registrations
        // made there land in one shared instance.
        $this->app->singleton(\FalconCms\Core\Support\SettingsExtension::class);

        // Drop-in plugin manager. Shared for the request so discovery/loading and
        // the boot-phase wiring (views, migrations, routes) use one instance.
        $this->app->singleton(\FalconCms\Core\Support\PluginManager::class);

        // Pro license gateway. Core binds a Null gateway where every paid feature is
        // inactive; when the falconcms/pro package is installed and licensed, its provider
        // rebinds this to a live gateway. Core asks it (via falcon_pro()) before exposing
        // any paid feature — it never checks for the Pro package directly.
        $this->app->singleton(
            \FalconCms\Core\Pro\LicenseGateway::class,
            \FalconCms\Core\Pro\NullLicenseGateway::class
        );

        // 1. Get Active Theme
        // We use a simple way to get it since DB might not be ready in early register
        $activeTheme = 'falcon-theme';
        try {
            // Check if we are running in web context and can access DB
            if (!$this->app->runningInConsole()) {
                $setting = \Illuminate\Support\Facades\DB::table('cms_settings')->where('key', 'active_theme')->first();
                if ($setting) $activeTheme = $setting->value;
            }
        } catch (\Exception $e) {}

        // 2. Resolve active theme path
        $themePath = resource_path("views/themes/{$activeTheme}");
        if (!file_exists($themePath)) {
            $themePath = __DIR__ . "/../resources/views/themes/{$activeTheme}";
        }

        // 2a. Detect child theme — read theme.json for parent reference
        $parentTheme     = null;
        $parentThemePath = null;
        $themeJsonFile   = $themePath . '/theme.json';
        if (file_exists($themeJsonFile)) {
            $themeJson   = json_decode(file_get_contents($themeJsonFile), true) ?: [];
            $parentTheme = $themeJson['parent'] ?? null;
        }
        if ($parentTheme) {
            $parentThemePath = resource_path("views/themes/{$parentTheme}");
            if (!file_exists($parentThemePath)) {
                $parentThemePath = __DIR__ . "/../resources/views/themes/{$parentTheme}";
            }
            if (!file_exists($parentThemePath)) {
                $parentThemePath = null;
            }
        }

        // 3. Load functions.php — parent first, then child (child can override parent hooks)
        if ($parentThemePath) {
            $parentFunctionsFile = $parentThemePath . '/functions.php';
            if (file_exists($parentFunctionsFile)) {
                require_once $parentFunctionsFile;
            }
        }
        $functionsFile = $themePath . '/functions.php';
        if (!file_exists($functionsFile) && !$parentTheme) {
            $functionsFile = __DIR__ . "/../resources/views/themes/{$activeTheme}/functions.php";
        }
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }

        // 3b. Load active plugins right after the theme's functions.php so plugins
        // get the SAME timing as a theme: their bootstrap runs during register(),
        // letting them hook register-time filters too (e.g. cms_theme_options).
        // loadActive() reads the active list with the query builder (Eloquent isn't
        // wired up yet here) and, if the DB/table isn't ready, returns without
        // marking itself done so boot() retries. Safe in web and console alike.
        try {
            $this->app->make(\FalconCms\Core\Support\PluginManager::class)->loadActive($this->app);
        } catch (\Throwable $e) {
            // Never let plugin loading break the CMS from coming up.
        }

        // 4. Load options.php — parent first, then child merged on top
        $themeOptions = [];
        if ($parentThemePath) {
            $parentOptionsFile = $parentThemePath . '/options.php';
            if (!file_exists($parentOptionsFile)) {
                $parentOptionsFile = __DIR__ . "/../resources/views/themes/{$parentTheme}/options.php";
            }
            if (file_exists($parentOptionsFile)) {
                require $parentOptionsFile;
            }
        }
        $parentThemeOptions = $themeOptions;
        $themeOptions = [];

        $optionsFile = $themePath . '/options.php';
        if (!file_exists($optionsFile) && !$parentTheme) {
            $optionsFile = __DIR__ . "/../resources/views/themes/{$activeTheme}/options.php";
        }
        if (file_exists($optionsFile)) {
            require $optionsFile;
        }
        if (!empty($parentThemeOptions)) {
            $themeOptions = array_replace_recursive($parentThemeOptions, $themeOptions);
        }

        // 5. Merge and Filter Options
        $baseOptions = config('falcon-options', []);
        if (!empty($themeOptions)) {
            $baseOptions = array_replace_recursive($baseOptions, $themeOptions);
        }
        $finalOptions = apply_falcon_filters('cms_theme_options', $baseOptions);
        if (isset($finalOptions['hooks'])) {
            foreach ($finalOptions['hooks'] as $key => $hookData) {
                $filterTag = 'lazy_' . str_replace('-', '_', $key) . '_fields';
                $finalOptions['hooks'][$key]['fields'] = apply_falcon_filters($filterTag, $finalOptions['hooks'][$key]['fields'] ?? []);
            }
        }
        config(['falcon-options' => $finalOptions]);

        // 6. Set View Paths Priority: child theme first → parent theme second → Laravel default
        $paths = config('view.paths', []);
        if ($parentThemePath) {
            array_unshift($paths, $parentThemePath); // parent inserted first
            array_unshift($paths, $themePath);        // child pushed to front (checked first)
        } else {
            array_unshift($paths, $themePath);
        }
        config(['view.paths' => array_unique($paths)]);
    }

    /** Manifests of plugins successfully loaded this request (safe if none). */
    protected function loadedPlugins(): array
    {
        try {
            return $this->app->make(\FalconCms\Core\Support\PluginManager::class)->loaded();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
