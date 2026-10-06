<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Http\Controllers\DormantPluginController;
use FalconCms\Core\Http\Controllers\PluginAssetController;
use FalconCms\Core\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Discovers, loads and manages drop-in plugins — the functional counterpart to
 * the theme system.
 *
 * A plugin is a folder in resources/views/plugins containing a plugin.json
 * manifest and (optionally) a plugin.php bootstrap, a PSR-4 src/, a
 * ServiceProvider, routes/, database/migrations/ and resources/views/. Only
 * plugins marked active in the `plugins` table are loaded.
 *
 * Loading is fatal-safe: a plugin that throws while loading is auto-deactivated
 * and logged rather than white-screening the whole CMS.
 */
class PluginManager
{
    protected string $path;

    /** Discovered manifests keyed by slug (null until discover() runs). */
    protected ?array $manifests = null;

    /** Manifests that were successfully loaded this request (for the boot phase). */
    protected array $loaded = [];

    /** Guard so active plugins are loaded at most once per request. */
    protected bool $bootedActive = false;

    /** The Composer class loader, resolved lazily for runtime PSR-4 registration. */
    protected $composer = null;

    /** Guard so the switched-off plugins' controller stand-in is registered once. */
    protected bool $dormantLoaderRegistered = false;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: static::defaultPath();
    }

    /**
     * Where plugins live: resources/views/plugins, alongside resources/views/themes.
     *
     * Installs created before plugins moved under resources/views keep a
     * root-level plugins/ directory. That one stays in use until its plugins have
     * actually been relocated (falcon:update does this) — deliberately keyed on
     * "does it hold plugins", not "does it exist", so an empty new directory can
     * never hide plugins that are still sitting in the old one.
     */
    public static function defaultPath(): string
    {
        $path = resource_path('views'.DIRECTORY_SEPARATOR.'plugins');
        $legacy = base_path('plugins');

        if ($legacy !== $path && !static::holdsPlugins($path) && static::holdsPlugins($legacy)) {
            return $legacy;
        }

        return $path;
    }

    /** True when a directory contains at least one folder with a plugin.json. */
    protected static function holdsPlugins(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $candidate) {
            if (is_file($candidate.'/plugin.json')) {
                return true;
            }
        }

        return false;
    }

    /** Absolute path to the plugins directory, or a specific plugin folder. */
    public function path(?string $slug = null): string
    {
        return $slug ? $this->path.DIRECTORY_SEPARATOR.$slug : $this->path;
    }

    /**
     * Plugins that ship inside the CMS package itself (e.g. the shop). They update with the
     * CMS, can be deactivated but never uninstalled, and may be active by default.
     */
    public static function bundledPath(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'plugins';
    }

    // ── Discovery ────────────────────────────────────────────────────────────

    /**
     * All plugins present on disk with a valid manifest, keyed by slug: the site's own
     * plugins, then the bundled ones. A bundled plugin wins over a site folder with the
     * same slug, so a stale copy dropped into the site can never shadow the real one.
     */
    public function discover(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }
        $this->manifests = [];

        foreach ([[$this->path, false], [static::bundledPath(), true]] as [$root, $bundled]) {
            if (!is_dir($root)) {
                continue;
            }
            foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $manifestFile = $dir.'/plugin.json';
                if (!is_file($manifestFile)) {
                    continue;
                }
                $data = json_decode((string) file_get_contents($manifestFile), true);
                if (!is_array($data)) {
                    continue;
                }
                // Slug comes from the manifest, falling back to the folder name; keep
                // it to a safe charset (it feeds routes, view namespaces, DB rows).
                $slug = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($data['slug'] ?? basename($dir)));
                if ($slug === '') {
                    continue;
                }
                $data['slug'] = $slug;
                $data['dir'] = $dir;
                $data['bundled'] = $bundled;
                // Only a bundled plugin may be active by default; a site plugin is opt-in.
                $data['default_active'] = $bundled && !empty($data['default_active']);
                $this->manifests[$slug] = $data;
            }
        }

        return $this->manifests;
    }

    /**
     * Whether a plugin counts as active, given the DB rows (slug => is_active).
     *
     * A row decides. With no row yet, a bundled default-active plugin is active — so a site
     * that updates the CMS keeps its shop even before (or without) running migrations, and
     * turning it off is what writes the row.
     */
    protected function isActive(string $slug, array $rows): bool
    {
        // The config switch beats everything (see falcon-options.disabled_plugins).
        if (in_array($slug, (array) config('falcon-options.disabled_plugins', []), true)) {
            return false;
        }
        if (array_key_exists($slug, $rows)) {
            return (bool) $rows[$slug];
        }

        return (bool) ($this->manifest($slug)['default_active'] ?? false);
    }

    /** A single discovered manifest, or null. */
    public function manifest(string $slug): ?array
    {
        return $this->discover()[$slug] ?? null;
    }

    /**
     * Every discovered plugin merged with its DB state — for listings/UI.
     * Each item: manifest data + installed, active, and update_available (the
     * on-disk manifest version is newer than the version last activated).
     */
    public function all(): array
    {
        $records = $this->records();
        $out = [];
        foreach ($this->discover() as $slug => $manifest) {
            $rec = $records[$slug] ?? null;

            // A bundled plugin updates with the CMS itself, so it never offers its own update.
            $updateAvailable = false;
            if (empty($manifest['bundled']) && $rec && !empty($manifest['version']) && !empty($rec->version)) {
                $updateAvailable = version_compare($manifest['version'], $rec->version, '>');
            }

            $active = $this->isActive($slug, array_map(fn ($r) => (bool) $r->is_active, $records));
            $out[$slug] = $manifest + [
                'installed' => $rec !== null || !empty($manifest['bundled']),
                'active' => $active,
                'installed_version' => $rec->version ?? null,
                'update_available' => $updateAvailable,
                'settings_url' => $active ? $this->settingsUrl($manifest) : null,
            ];
        }

        return $out;
    }

    /**
     * Where the Plugins screen's "Settings" link goes: the route named by the manifest's
     * "settings_route". Only a registered, live route counts. A dormant twin is not a page,
     * and neither is the route of a plugin that is not loaded on this request.
     */
    protected function settingsUrl(array $manifest): ?string
    {
        $name = (string) ($manifest['settings_route'] ?? '');
        if ($name === '') {
            return null;
        }

        try {
            $route = app('router')->getRoutes()->getByName($name);
            if ($route === null || !empty($route->defaults['_falcon_dormant'])) {
                return null;
            }

            return route($name);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** DB records keyed by slug (empty when the table is missing). */
    protected function records(): array
    {
        try {
            return Plugin::all()->keyBy('slug')->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Slugs of active plugins: DB state, plus bundled default-active ones with no row yet. */
    public function activeSlugs(): array
    {
        try {
            $rows = Plugin::pluck('is_active', 'slug')->map(fn ($v) => (bool) $v)->all();
        } catch (Throwable $e) {
            return [];
        }

        return array_values(array_filter(array_keys($this->discover()), fn ($slug) => $this->isActive($slug, $rows)));
    }

    // ── Loading ──────────────────────────────────────────────────────────────

    /**
     * Load every active plugin into the application. Called from the CMS service
     * provider's register() so plugin providers register and plugin.php hooks
     * (menus, settings, actions) are in place before the app boots.
     */
    public function loadActive($app): void
    {
        if ($this->bootedActive) {
            return;
        }

        // Use the query builder, not Eloquent: this runs during register(), where
        // Eloquent's connection resolver isn't wired up yet (DatabaseServiceProvider
        // sets it in boot()). DB::table() works at that point — the same reason the
        // theme loader reads active_theme this way.
        //
        // Returning WITHOUT setting the guard when the DB isn't ready lets a later
        // call (boot()) retry, instead of permanently loading nothing.
        try {
            $rows = DB::table('plugins')->pluck('is_active', 'slug')->map(fn ($v) => (bool) $v)->all();
            $this->bootedActive = true;
        } catch (Throwable $e) {
            // No plugins table yet (a fresh install before migrating, or a test app that
            // migrates after it boots). Nobody has switched anything off, so the bundled
            // default-active plugins still load; site plugins wait for the retry in boot().
            $rows = [];
        }

        $active = array_filter(
            $this->orderByDependencies(array_keys($this->discover())),
            fn ($slug) => $this->isActive($slug, $rows)
        );

        foreach ($active as $slug) {
            $manifest = $this->manifest($slug);
            if ($manifest && !isset($this->loaded[$slug])) {
                $this->loadPlugin($app, $manifest);
            }
        }

        $this->registerDormantControllers();
    }

    /**
     * Make the controllers of plugins that are switched off answer 404 instead of failing.
     *
     * A route cache built while a plugin was on still routes its URLs to its controllers. With
     * the plugin off, its namespace is never registered, so each of those requests would be a
     * 500 until the cache was cleared. This autoloader runs only after Composer has failed to
     * find a class, and only for a controller (…\Http\Controllers\…) in the namespace of a
     * plugin that is not loaded. It never loads any of the plugin's own code.
     */
    protected function registerDormantControllers(): void
    {
        if ($this->dormantLoaderRegistered) {
            return;
        }
        $this->dormantLoaderRegistered = true;

        spl_autoload_register(function (string $class): void {
            if (!str_contains($class, '\\Http\\Controllers\\')) {
                return;
            }
            foreach ($this->discover() as $slug => $manifest) {
                $namespace = rtrim((string) ($manifest['namespace'] ?? ''), '\\');
                if ($namespace !== '' && !isset($this->loaded[$slug]) && str_starts_with($class, $namespace.'\\')) {
                    class_alias(DormantPluginController::class, $class);

                    return;
                }
            }
        });
    }

    /** Manifests loaded this request — used by the boot phase (views/routes/migrations). */
    public function loaded(): array
    {
        return $this->loaded;
    }

    /**
     * Load one plugin: register its PSR-4 namespace, its ServiceProvider, and
     * require its bootstrap file. Fatal-safe — a throwing plugin is deactivated,
     * not allowed to take down the CMS.
     */
    protected function loadPlugin($app, array $manifest, bool $safe = true): void
    {
        $slug = $manifest['slug'];
        $dir = $manifest['dir'];

        try {
            if (!empty($manifest['namespace'])) {
                $src = is_dir($dir.'/src') ? $dir.'/src' : $dir;
                $this->registerPsr4($manifest['namespace'], $src);
            }

            if (!empty($manifest['provider']) && class_exists($manifest['provider'])) {
                $app->register($manifest['provider']);
            }

            $bootstrap = $dir.'/'.($manifest['bootstrap'] ?? 'plugin.php');
            if (is_file($bootstrap)) {
                require_once $bootstrap;
            }

            $this->loaded[$slug] = $manifest;
        } catch (Throwable $e) {
            if (!$safe) {
                // During activation we want the failure to abort the activate, so
                // a broken plugin never gets marked active in the first place.
                throw $e;
            }
            // On normal boot, keep the CMS up by auto-deactivating the offender.
            $this->handleFatal($slug, $e);
        }
    }

    /**
     * Copy a plugin's static files to public/plugin-assets/{slug}, under the same URLs
     * PluginAssetController answers.
     *
     * Many servers serve every .css/.js URL straight from disk and 404 when there is no
     * file, so the request never reaches the CMS. With the files in public/, those servers
     * find them, and on a server that passes the request to the CMS the controller still
     * answers. Only the static types the controller serves are copied, never PHP or anything
     * else, and nothing outside assets/: a symlink pointing out of the folder is skipped.
     * The copy replaces any earlier one, so files the plugin no longer ships are gone too.
     */
    public function publishAssets(string $slug): bool
    {
        $manifest = $this->manifest($slug);
        $root = $manifest ? realpath($manifest['dir'].DIRECTORY_SEPARATOR.'assets') : false;
        if (!$root || !is_dir($root)) {
            return false;
        }

        try {
            $target = public_path('plugin-assets/'.$slug);
            File::deleteDirectory($target);

            foreach (File::allFiles($root) as $file) {
                $real = $file->getRealPath();
                $extension = strtolower($file->getExtension());
                if (!$real || !str_starts_with($real, $root.DIRECTORY_SEPARATOR) || !isset(PluginAssetController::TYPES[$extension])) {
                    continue;
                }
                $to = $target.'/'.str_replace('\\', '/', $file->getRelativePathname());
                File::ensureDirectoryExists(dirname($to));
                File::copy($real, $to);
            }

            return true;
        } catch (Throwable $e) {
            // The controller still serves them on servers that pass the request through.
            Log::warning("Could not publish the assets of plugin '{$slug}': ".$e->getMessage());

            return false;
        }
    }

    /**
     * Bring public/plugin-assets in line with which plugins are on: every active plugin's
     * files published fresh, a switched-off plugin's removed. Run by falcon:update and
     * falcon:install, so an update that brings new plugin files also brings their copies.
     *
     * @return list<string> the slugs whose assets were published
     */
    public function syncPublishedAssets(): array
    {
        try {
            $rows = Plugin::pluck('is_active', 'slug')->map(fn ($v) => (bool) $v)->all();
        } catch (Throwable $e) {
            $rows = []; // no plugins table yet: the bundled default-on plugins count as on
        }

        $published = [];
        foreach (array_keys($this->discover()) as $slug) {
            if ($this->isActive($slug, $rows)) {
                if ($this->publishAssets($slug)) {
                    $published[] = $slug;
                }
            } else {
                $this->unpublishAssets($slug);
            }
        }

        return $published;
    }

    /** Remove a plugin's published files: a switched-off plugin leaves nothing in public/. */
    public function unpublishAssets(string $slug): void
    {
        try {
            File::deleteDirectory(public_path('plugin-assets/'.$slug));
        } catch (Throwable $e) {
            Log::warning("Could not remove the published assets of plugin '{$slug}': ".$e->getMessage());
        }
    }

    /** Register a PSR-4 namespace on the live Composer loader. */
    protected function registerPsr4(string $namespace, string $path): void
    {
        if ($this->composer === null) {
            $this->composer = require base_path('vendor/autoload.php');
        }
        $namespace = rtrim($namespace, '\\').'\\';
        $this->composer->addPsr4($namespace, $path);
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Activate a plugin: verify requirements, load it, run its migrations, call
     * its optional lifecycle activate() hook, then mark it active.
     *
     * @return array{ok:bool,message:string}
     */
    public function activate(string $slug): array
    {
        $manifest = $this->manifest($slug);
        if (!$manifest) {
            return $this->result(false, "Plugin '{$slug}' not found.");
        }

        if ($error = $this->checkRequirements($manifest)) {
            return $this->result(false, $error);
        }

        try {
            $this->loadPlugin(app(), $manifest, false);
            $this->runMigrations($manifest);
            $this->callLifecycle($manifest, 'activate');

            Plugin::updateOrCreate(
                ['slug' => $slug],
                ['version' => $manifest['version'] ?? null, 'is_active' => true, 'activated_at' => now()]
            );
        } catch (Throwable $e) {
            Log::error("Plugin activation failed [{$slug}]: ".$e->getMessage());

            return $this->result(false, 'Activation failed: '.$e->getMessage());
        }

        $this->publishAssets($slug);
        $this->refreshCaches();

        return $this->result(true, ($manifest['name'] ?? $slug).' activated.');
    }

    /**
     * Deactivate a plugin: call its optional lifecycle deactivate() hook and
     * mark it inactive. Data/tables are left intact (use uninstall() to remove).
     *
     * @return array{ok:bool,message:string}
     */
    public function deactivate(string $slug): array
    {
        $manifest = $this->manifest($slug);
        if ($manifest) {
            // Depending plugins would break — block deactivation while any active
            // plugin still declares this one as a dependency.
            if ($blocker = $this->activeDependent($slug)) {
                return $this->result(false, "Cannot deactivate — '{$blocker}' depends on it.");
            }
            try {
                $this->callLifecycle($manifest, 'deactivate');
            } catch (Throwable $e) {
                Log::error("Plugin deactivate hook failed [{$slug}]: ".$e->getMessage());
            }
        }

        try {
            // updateOrCreate, not update: a bundled default-active plugin may have no row yet,
            // and without one it would still count as active.
            Plugin::updateOrCreate(['slug' => $slug], ['is_active' => false]);
        } catch (Throwable $e) {
            return $this->result(false, 'Deactivation failed: '.$e->getMessage());
        }

        $this->unpublishAssets($slug);
        $this->refreshCaches();

        return $this->result(true, ($manifest['name'] ?? $slug).' deactivated.');
    }

    /**
     * Reconcile an active plugin whose on-disk files were replaced with a newer
     * version: run any new migrations, call its optional lifecycle upgrade(), and
     * record the new version. (Files are updated by re-uploading / dropping in.)
     *
     * @return array{ok:bool,message:string}
     */
    public function update(string $slug): array
    {
        $manifest = $this->manifest($slug);
        if (!$manifest) {
            return $this->result(false, "Plugin '{$slug}' not found.");
        }
        $record = $this->records()[$slug] ?? null;
        if (!$record) {
            return $this->result(false, 'Plugin is not installed.');
        }

        try {
            $this->loadPlugin(app(), $manifest, false);
            $this->runMigrations($manifest);

            // Optional lifecycle upgrade($previousVersion) hook.
            $class = $manifest['lifecycle'] ?? null;
            if ($class && class_exists($class)) {
                $instance = app()->make($class);
                if (method_exists($instance, 'upgrade')) {
                    $instance->upgrade($record->version);
                }
            }

            $record->update(['version' => $manifest['version'] ?? $record->version]);
        } catch (Throwable $e) {
            Log::error("Plugin update failed [{$slug}]: ".$e->getMessage());

            return $this->result(false, 'Update failed: '.$e->getMessage());
        }

        $this->publishAssets($slug);

        return $this->result(true, ($manifest['name'] ?? $slug).' updated to '.($manifest['version'] ?? '?').'.');
    }

    /**
     * Uninstall a plugin: deactivate it, run its optional lifecycle uninstall()
     * (where it can drop its own data), roll back its convention migrations,
     * remove its DB record and — when $deleteFiles — delete its folder.
     *
     * @return array{ok:bool,message:string}
     */
    public function uninstall(string $slug, bool $deleteFiles = true): array
    {
        $manifest = $this->manifest($slug);

        // A bundled plugin ships with the CMS: it can be switched off, never removed —
        // its files belong to the package, and its data must survive.
        if (!empty($manifest['bundled'])) {
            return $this->result(false, ($manifest['name'] ?? $slug).' is part of FalconCMS and cannot be uninstalled. Deactivate it instead.');
        }

        if ($manifest && ($blocker = $this->activeDependent($slug))) {
            return $this->result(false, "Cannot uninstall — '{$blocker}' depends on it.");
        }

        // Ensure it's inactive first (also fires deactivate hook).
        if (in_array($slug, $this->activeSlugs(), true)) {
            $this->deactivate($slug);
        }

        try {
            if ($manifest) {
                $this->callLifecycle($manifest, 'uninstall');
                $this->rollbackMigrations($manifest);
            }

            Plugin::where('slug', $slug)->delete();

            if ($deleteFiles && $manifest && is_dir($manifest['dir'])) {
                File::deleteDirectory($manifest['dir']);
                $this->manifests = null; // force re-discovery
            }
        } catch (Throwable $e) {
            Log::error("Plugin uninstall failed [{$slug}]: ".$e->getMessage());

            return $this->result(false, 'Uninstall failed: '.$e->getMessage());
        }

        return $this->result(true, ($manifest['name'] ?? $slug).' uninstalled.');
    }

    /**
     * Extract an uploaded plugin ZIP into the plugins directory. Rejects unsafe
     * (zip-slip) paths and archives without a valid plugin.json.
     *
     * @return array{ok:bool,message:string,slug?:string}
     */
    public function installFromZip(string $zipPath, ?string $originalName = null): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return $this->result(false, 'PHP zip extension is not available.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return $this->result(false, 'Could not open ZIP file.');
        }

        // Reject path-traversal / absolute entries before extracting anything.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
                $zip->close();

                return $this->result(false, 'Invalid ZIP: contains unsafe file paths.');
            }
        }

        $temp = storage_path('app/tmp_plugin_'.time());
        File::makeDirectory($temp, 0755, true);
        $zip->extractTo($temp);
        $zip->close();

        // The plugin may sit at the archive root or inside a single wrapper folder.
        $source = $temp;
        if (!is_file($temp.'/plugin.json')) {
            $dirs = File::directories($temp);
            if (count($dirs) === 1 && is_file($dirs[0].'/plugin.json')) {
                $source = $dirs[0];
            }
        }

        $manifestFile = $source.'/plugin.json';
        if (!is_file($manifestFile)) {
            File::deleteDirectory($temp);

            return $this->result(false, 'Invalid plugin: plugin.json not found.');
        }
        $data = json_decode((string) file_get_contents($manifestFile), true);
        if (!is_array($data)) {
            File::deleteDirectory($temp);

            return $this->result(false, 'Invalid plugin: plugin.json is malformed.');
        }

        $slug = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($data['slug'] ?? ($originalName ? pathinfo($originalName, PATHINFO_FILENAME) : basename($source))));
        if ($slug === '') {
            File::deleteDirectory($temp);

            return $this->result(false, 'Invalid plugin: missing slug.');
        }

        $target = $this->path($slug);
        if (is_dir($target)) {
            File::deleteDirectory($temp);

            return $this->result(false, "Plugin '{$slug}' already exists. Uninstall it first to reinstall.");
        }

        File::ensureDirectoryExists($this->path());
        File::moveDirectory($source, $target);
        File::deleteDirectory($temp);
        $this->manifests = null; // force re-discovery

        return $this->result(true, ($data['name'] ?? $slug).' installed. Activate it to enable.') + ['slug' => $slug];
    }

    /** Roll back a plugin's own migrations (best-effort). */
    protected function rollbackMigrations(array $manifest): void
    {
        $migrations = $manifest['dir'].'/database/migrations';
        if (!is_dir($migrations)) {
            return;
        }
        $relative = ltrim(str_replace(base_path(), '', $migrations), '/\\');
        try {
            Artisan::call('migrate:rollback', ['--path' => $relative, '--force' => true]);
        } catch (Throwable $e) {
            Log::warning("Plugin migration rollback failed [{$manifest['slug']}]: ".$e->getMessage());
        }
    }

    /**
     * Verify a plugin's declared requirements (PHP, CMS version, active
     * dependencies). Returns an error string, or null when all are satisfied.
     */
    protected function checkRequirements(array $manifest): ?string
    {
        if (!empty($manifest['requires_php']) && !$this->versionSatisfied(PHP_VERSION, $manifest['requires_php'])) {
            return "Requires PHP {$manifest['requires_php']} (running ".PHP_VERSION.').';
        }

        if (!empty($manifest['requires_cms']) && function_exists('falcon_cms_installed_version')) {
            if (!$this->versionSatisfied((string) falcon_cms_installed_version(), $manifest['requires_cms'])) {
                return "Requires FalconCMS {$manifest['requires_cms']}.";
            }
        }

        foreach ((array) ($manifest['dependencies'] ?? []) as $dep) {
            $dep = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $dep);
            if ($dep === '') {
                continue;
            }
            if (!$this->manifest($dep)) {
                return "Missing dependency: {$dep}.";
            }
            if (!in_array($dep, $this->activeSlugs(), true)) {
                return "Dependency '{$dep}' must be activated first.";
            }
        }

        return null;
    }

    /** Compare a version against a simple ">=x", "<=x", "x" or bare constraint. */
    protected function versionSatisfied(string $current, string $constraint): bool
    {
        $constraint = trim($constraint);
        if (preg_match('/^(>=|<=|>|<|=)?\s*(.+)$/', $constraint, $m)) {
            $op = $m[1] ?: '>=';
            $ver = ltrim(trim($m[2]), 'v^~');

            return version_compare($current, $ver, $op);
        }

        return true;
    }

    /** Run a plugin's own migrations (plugins/<slug>/database/migrations). */
    protected function runMigrations(array $manifest): void
    {
        $migrations = $manifest['dir'].'/database/migrations';
        if (!is_dir($migrations)) {
            return;
        }
        // --path is interpreted relative to the app base path.
        $relative = ltrim(str_replace(base_path(), '', $migrations), '/\\');
        Artisan::call('migrate', ['--path' => $relative, '--force' => true]);
    }

    /**
     * Call an optional lifecycle method (activate/deactivate/uninstall) on the
     * class named in the manifest's "lifecycle" key, if it defines one.
     */
    protected function callLifecycle(array $manifest, string $method): void
    {
        $class = $manifest['lifecycle'] ?? null;
        if ($class && class_exists($class)) {
            $instance = app()->make($class);
            if (method_exists($instance, $method)) {
                $instance->{$method}();
            }
        }
    }

    // ── Dependency ordering ──────────────────────────────────────────────────

    /** Order slugs so a plugin's dependencies load before it (best-effort). */
    protected function orderByDependencies(array $slugs): array
    {
        $ordered = [];
        $visiting = [];

        $visit = function (string $slug) use (&$visit, &$ordered, &$visiting) {
            if (isset($ordered[$slug]) || isset($visiting[$slug])) {
                return; // already placed, or a cycle — stop
            }
            $visiting[$slug] = true;
            foreach ((array) ($this->manifest($slug)['dependencies'] ?? []) as $dep) {
                $dep = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $dep);
                if ($dep !== '' && $this->manifest($dep)) {
                    $visit($dep);
                }
            }
            unset($visiting[$slug]);
            $ordered[$slug] = true;
        };

        foreach ($slugs as $slug) {
            $visit($slug);
        }

        return array_keys($ordered);
    }

    /** First active plugin that depends on $slug, or null. */
    protected function activeDependent(string $slug): ?string
    {
        foreach ($this->activeSlugs() as $active) {
            $deps = (array) ($this->manifest($active)['dependencies'] ?? []);
            if (in_array($slug, array_map(fn ($d) => preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $d), $deps), true)) {
                return $active;
            }
        }

        return null;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * After a plugin is switched on or off: a cached route table would still hold (or lack)
     * its routes, and cached pages would still carry (or miss) its markup — the shop's cart
     * icon, say. Both are rebuilt on the next request.
     */
    protected function refreshCaches(): void
    {
        try {
            if (app()->routesAreCached()) {
                Artisan::call('route:clear');
            }
        } catch (Throwable $e) {
            Log::warning('Could not clear the route cache after a plugin change: '.$e->getMessage());
        }
        if (function_exists('clear_page_cache')) {
            try {
                clear_page_cache();
            } catch (Throwable $e) {
                Log::warning('Could not clear the page cache after a plugin change: '.$e->getMessage());
            }
        }
    }

    /** Deactivate a plugin that threw during load, so the CMS stays up. */
    protected function handleFatal(string $slug, Throwable $e): void
    {
        Log::error("Plugin '{$slug}' failed to load and was deactivated: ".$e->getMessage());
        try {
            // updateOrCreate, not update: a bundled default-active plugin may have no row yet,
            // and without one it would still count as active.
            Plugin::updateOrCreate(['slug' => $slug], ['is_active' => false]);
        } catch (Throwable $ignored) {
            // Table may not exist yet — nothing more we can do safely.
        }
    }

    protected function result(bool $ok, string $message): array
    {
        return ['ok' => $ok, 'message' => $message];
    }
}
