<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Discovers, loads and manages drop-in plugins — the functional counterpart to
 * the theme system.
 *
 * A plugin is a folder in the app's plugins/ directory containing a plugin.json
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

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: base_path('plugins');
    }

    /** Absolute path to the plugins directory, or a specific plugin folder. */
    public function path(?string $slug = null): string
    {
        return $slug ? $this->path . DIRECTORY_SEPARATOR . $slug : $this->path;
    }

    // ── Discovery ────────────────────────────────────────────────────────────

    /** All plugins present on disk with a valid manifest, keyed by slug. */
    public function discover(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }
        $this->manifests = [];

        if (! is_dir($this->path)) {
            return $this->manifests;
        }

        foreach (glob($this->path . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $manifestFile = $dir . '/plugin.json';
            if (! is_file($manifestFile)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($manifestFile), true);
            if (! is_array($data)) {
                continue;
            }
            // Slug comes from the manifest, falling back to the folder name; keep
            // it to a safe charset (it feeds routes, view namespaces, DB rows).
            $slug = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($data['slug'] ?? basename($dir)));
            if ($slug === '') {
                continue;
            }
            $data['slug'] = $slug;
            $data['dir']  = $dir;
            $this->manifests[$slug] = $data;
        }

        return $this->manifests;
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

            $updateAvailable = false;
            if ($rec && ! empty($manifest['version']) && ! empty($rec->version)) {
                $updateAvailable = version_compare($manifest['version'], $rec->version, '>');
            }

            $out[$slug] = $manifest + [
                'installed'        => $rec !== null,
                'active'           => $rec ? (bool) $rec->is_active : false,
                'installed_version' => $rec->version ?? null,
                'update_available' => $updateAvailable,
            ];
        }
        return $out;
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

    /** Slugs of plugins marked active in the DB (safe before the table exists). */
    public function activeSlugs(): array
    {
        try {
            return Plugin::where('is_active', true)->pluck('slug')->all();
        } catch (Throwable $e) {
            return [];
        }
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
            $activeSlugs = DB::table('plugins')->where('is_active', true)->pluck('slug')->all();
        } catch (Throwable $e) {
            return;
        }

        $this->bootedActive = true;

        $active = array_intersect($this->orderByDependencies(array_keys($this->discover())), $activeSlugs);

        foreach ($active as $slug) {
            $manifest = $this->manifest($slug);
            if ($manifest) {
                $this->loadPlugin($app, $manifest);
            }
        }
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
        $dir  = $manifest['dir'];

        try {
            if (! empty($manifest['namespace'])) {
                $src = is_dir($dir . '/src') ? $dir . '/src' : $dir;
                $this->registerPsr4($manifest['namespace'], $src);
            }

            if (! empty($manifest['provider']) && class_exists($manifest['provider'])) {
                $app->register($manifest['provider']);
            }

            $bootstrap = $dir . '/' . ($manifest['bootstrap'] ?? 'plugin.php');
            if (is_file($bootstrap)) {
                require_once $bootstrap;
            }

            $this->loaded[$slug] = $manifest;
        } catch (Throwable $e) {
            if (! $safe) {
                // During activation we want the failure to abort the activate, so
                // a broken plugin never gets marked active in the first place.
                throw $e;
            }
            // On normal boot, keep the CMS up by auto-deactivating the offender.
            $this->handleFatal($slug, $e);
        }
    }

    /** Register a PSR-4 namespace on the live Composer loader. */
    protected function registerPsr4(string $namespace, string $path): void
    {
        if ($this->composer === null) {
            $this->composer = require base_path('vendor/autoload.php');
        }
        $namespace = rtrim($namespace, '\\') . '\\';
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
        if (! $manifest) {
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
            Log::error("Plugin activation failed [{$slug}]: " . $e->getMessage());
            return $this->result(false, 'Activation failed: ' . $e->getMessage());
        }

        return $this->result(true, ($manifest['name'] ?? $slug) . ' activated.');
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
                Log::error("Plugin deactivate hook failed [{$slug}]: " . $e->getMessage());
            }
        }

        try {
            Plugin::where('slug', $slug)->update(['is_active' => false]);
        } catch (Throwable $e) {
            return $this->result(false, 'Deactivation failed: ' . $e->getMessage());
        }

        return $this->result(true, ($manifest['name'] ?? $slug) . ' deactivated.');
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
        if (! $manifest) {
            return $this->result(false, "Plugin '{$slug}' not found.");
        }
        $record = $this->records()[$slug] ?? null;
        if (! $record) {
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
            Log::error("Plugin update failed [{$slug}]: " . $e->getMessage());
            return $this->result(false, 'Update failed: ' . $e->getMessage());
        }

        return $this->result(true, ($manifest['name'] ?? $slug) . ' updated to ' . ($manifest['version'] ?? '?') . '.');
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
                \Illuminate\Support\Facades\File::deleteDirectory($manifest['dir']);
                $this->manifests = null; // force re-discovery
            }
        } catch (Throwable $e) {
            Log::error("Plugin uninstall failed [{$slug}]: " . $e->getMessage());
            return $this->result(false, 'Uninstall failed: ' . $e->getMessage());
        }

        return $this->result(true, ($manifest['name'] ?? $slug) . ' uninstalled.');
    }

    /**
     * Extract an uploaded plugin ZIP into the plugins directory. Rejects unsafe
     * (zip-slip) paths and archives without a valid plugin.json.
     *
     * @return array{ok:bool,message:string,slug?:string}
     */
    public function installFromZip(string $zipPath, ?string $originalName = null): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->result(false, 'PHP zip extension is not available.');
        }

        $zip = new \ZipArchive();
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

        $temp = storage_path('app/tmp_plugin_' . time());
        \Illuminate\Support\Facades\File::makeDirectory($temp, 0755, true);
        $zip->extractTo($temp);
        $zip->close();

        // The plugin may sit at the archive root or inside a single wrapper folder.
        $source = $temp;
        if (! is_file($temp . '/plugin.json')) {
            $dirs = \Illuminate\Support\Facades\File::directories($temp);
            if (count($dirs) === 1 && is_file($dirs[0] . '/plugin.json')) {
                $source = $dirs[0];
            }
        }

        $manifestFile = $source . '/plugin.json';
        if (! is_file($manifestFile)) {
            \Illuminate\Support\Facades\File::deleteDirectory($temp);
            return $this->result(false, 'Invalid plugin: plugin.json not found.');
        }
        $data = json_decode((string) file_get_contents($manifestFile), true);
        if (! is_array($data)) {
            \Illuminate\Support\Facades\File::deleteDirectory($temp);
            return $this->result(false, 'Invalid plugin: plugin.json is malformed.');
        }

        $slug = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($data['slug'] ?? ($originalName ? pathinfo($originalName, PATHINFO_FILENAME) : basename($source))));
        if ($slug === '') {
            \Illuminate\Support\Facades\File::deleteDirectory($temp);
            return $this->result(false, 'Invalid plugin: missing slug.');
        }

        $target = $this->path($slug);
        if (is_dir($target)) {
            \Illuminate\Support\Facades\File::deleteDirectory($temp);
            return $this->result(false, "Plugin '{$slug}' already exists. Uninstall it first to reinstall.");
        }

        \Illuminate\Support\Facades\File::ensureDirectoryExists($this->path());
        \Illuminate\Support\Facades\File::moveDirectory($source, $target);
        \Illuminate\Support\Facades\File::deleteDirectory($temp);
        $this->manifests = null; // force re-discovery

        return $this->result(true, ($data['name'] ?? $slug) . ' installed. Activate it to enable.') + ['slug' => $slug];
    }

    /** Roll back a plugin's own migrations (best-effort). */
    protected function rollbackMigrations(array $manifest): void
    {
        $migrations = $manifest['dir'] . '/database/migrations';
        if (! is_dir($migrations)) {
            return;
        }
        $relative = ltrim(str_replace(base_path(), '', $migrations), '/\\');
        try {
            Artisan::call('migrate:rollback', ['--path' => $relative, '--force' => true]);
        } catch (Throwable $e) {
            Log::warning("Plugin migration rollback failed [{$manifest['slug']}]: " . $e->getMessage());
        }
    }

    /**
     * Verify a plugin's declared requirements (PHP, CMS version, active
     * dependencies). Returns an error string, or null when all are satisfied.
     */
    protected function checkRequirements(array $manifest): ?string
    {
        if (! empty($manifest['requires_php']) && ! $this->versionSatisfied(PHP_VERSION, $manifest['requires_php'])) {
            return "Requires PHP {$manifest['requires_php']} (running " . PHP_VERSION . ').';
        }

        if (! empty($manifest['requires_cms']) && function_exists('falcon_cms_installed_version')) {
            if (! $this->versionSatisfied((string) falcon_cms_installed_version(), $manifest['requires_cms'])) {
                return "Requires FalconCMS {$manifest['requires_cms']}.";
            }
        }

        foreach ((array) ($manifest['dependencies'] ?? []) as $dep) {
            $dep = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $dep);
            if ($dep === '') {
                continue;
            }
            if (! $this->manifest($dep)) {
                return "Missing dependency: {$dep}.";
            }
            if (! in_array($dep, $this->activeSlugs(), true)) {
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
            $op  = $m[1] ?: '>=';
            $ver = ltrim(trim($m[2]), 'v^~');
            return version_compare($current, $ver, $op);
        }
        return true;
    }

    /** Run a plugin's own migrations (plugins/<slug>/database/migrations). */
    protected function runMigrations(array $manifest): void
    {
        $migrations = $manifest['dir'] . '/database/migrations';
        if (! is_dir($migrations)) {
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

    /** Deactivate a plugin that threw during load, so the CMS stays up. */
    protected function handleFatal(string $slug, Throwable $e): void
    {
        Log::error("Plugin '{$slug}' failed to load and was deactivated: " . $e->getMessage());
        try {
            Plugin::where('slug', $slug)->update(['is_active' => false]);
        } catch (Throwable $ignored) {
            // Table may not exist yet — nothing more we can do safely.
        }
    }

    protected function result(bool $ok, string $message): array
    {
        return ['ok' => $ok, 'message' => $message];
    }
}
