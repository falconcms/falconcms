<?php

namespace FalconCms\Core\Console\Commands;

use App\Models\User;
use FalconCms\Core\Console\Concerns\ReconcilesMigrations;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\PluginManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class UpdateFalconCms extends Command
{
    use ReconcilesMigrations;

    protected $signature = 'falcon:update';

    protected $description = 'Update Falcon CMS: run migrations, sync system data, and refresh assets/themes.';

    public function handle()
    {
        $this->info('--- Starting Falcon CMS Update ---');

        // 0. Move a legacy root-level plugins/ directory under resources/views,
        //    next to themes. Runs before migrations so a plugin that ships new
        //    migrations in this release still gets them in this same run.
        $this->info('Step 0: Checking plugins location...');
        $this->relocatePlugins();

        // 1. Run Migrations (reconcile already-existing tables first so migrate never fails
        //    with "table already exists" on a partial / pre-existing database).
        $this->info('Step 1: Running migrations...');
        $this->reconcileExistingMigrations();
        $this->call('migrate', ['--force' => true]);

        // 2. Sync System Data (Permissions, Roles, Menus)
        $this->info('Step 2: Syncing system data...');
        $this->call('db:seed', [
            '--class' => 'FalconCms\\Core\\Database\\Seeders\\SystemSyncSeeder',
            '--force' => true,
        ]);

        // 3. Publish Assets (Force)
        $this->info('Step 3: Refreshing dashboard assets...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-cms-assets',
            '--force' => true,
        ]);

        // 4. Publish Themes (Force) — parent theme only
        $this->info('Step 4: Refreshing themes...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-themes',
            '--force' => true,
        ]);

        // 4a. Remove ALL published package view overrides so the vendor views are
        // always used directly. A stale copy under resources/views/vendor/falcon-cms
        // silently shadows the real namespaced package view — e.g. a months-old
        // frontend/builder/column.blade.php kept serving the old layout after every
        // update, regardless of cache/OPcache clears. Deleting the whole namespace
        // directory guarantees no override can linger.
        $publishedViewsPath = resource_path('views/vendor/falcon-cms');
        if (is_dir($publishedViewsPath)) {
            File::deleteDirectory($publishedViewsPath);
            $this->info('Step 4a: Removed stale published view overrides (vendor/falcon-cms).');
        }

        // 4b. Publish child theme skeleton if it does not exist yet (never --force)
        $this->info('Step 4b: Publishing child theme (skipped if already exists)...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-theme-child',
        ]);

        // 5. Sync footer defaults (update stale default values from old installs)
        $this->info('Step 5: Syncing footer defaults...');
        $this->syncFooterDefaults();

        // 6. Clear Cache
        $this->info('Step 6: Clearing cache...');
        $this->call('optimize:clear');
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->info('Step 6b: OPcache cleared.');
        }

        // 7. Auto-create E-commerce pages
        $this->info('Step 7: Auto-creating E-commerce pages...');
        $this->createEcommercePages();

        $this->info('---------------------------------------');
        $this->info('Falcon CMS updated successfully!');
        $this->info('---------------------------------------');
    }

    /**
     * Relocate plugins from the pre-2.3 root-level plugins/ directory into
     * resources/views/plugins.
     *
     * Safe to run on every update: it is a no-op once there is nothing left at
     * the old location. Nothing at the destination is ever overwritten — a slug
     * that already exists there is left where it is and reported, so no
     * customer's plugin can be silently replaced.
     */
    protected function relocatePlugins(): void
    {
        $legacy = base_path('plugins');
        $target = resource_path('views'.DIRECTORY_SEPARATOR.'plugins');

        if (!is_dir($legacy) || realpath($legacy) === realpath($target)) {
            $this->line('  Plugins already live in resources/views/plugins — nothing to move.');

            return;
        }

        // A junction/symlink is a dev setup pointing at a checkout elsewhere;
        // moving it would drag someone's source tree around. Leave it alone.
        if (is_link(rtrim($legacy, DIRECTORY_SEPARATOR))) {
            $this->warn('  plugins/ is a symlink — left in place. Move it by hand if you want it under resources/views.');

            return;
        }

        $candidates = array_filter(
            glob($legacy.'/*', GLOB_ONLYDIR) ?: [],
            fn ($dir) => is_file($dir.'/plugin.json')
        );

        if (!$candidates) {
            $this->line('  No plugins found in the old plugins/ directory — nothing to move.');

            return;
        }

        File::ensureDirectoryExists($target);

        $moved = [];
        $skipped = [];

        foreach ($candidates as $dir) {
            $slug = basename($dir);
            $dest = $target.DIRECTORY_SEPARATOR.$slug;

            if (file_exists($dest)) {
                $skipped[] = $slug;

                continue;
            }

            try {
                File::moveDirectory($dir, $dest);
                $moved[] = $slug;
            } catch (\Throwable $e) {
                $skipped[] = $slug;
                Log::warning("Falcon update: could not relocate plugin '{$slug}': ".$e->getMessage());
            }
        }

        // Only remove the old directory once it is genuinely empty — never delete
        // anything the customer may have left in there.
        if (!$skipped && File::isEmptyDirectory($legacy)) {
            @rmdir($legacy);
        }

        if ($moved) {
            $this->info('  Moved to resources/views/plugins: '.implode(', ', $moved));
            $this->registerRelocatedMigrations($target);
        }

        if ($skipped) {
            $this->warn('  Left in plugins/ (already present at the new location, or not movable): '.implode(', ', $skipped));
        }
    }

    /**
     * Point the migrator at the active plugins' new migration directories.
     *
     * The service provider registered those paths at boot, before the move, so
     * without this the plugin migrations in this release would be skipped until
     * the next command ran.
     */
    protected function registerRelocatedMigrations(string $target): void
    {
        try {
            $migrator = $this->laravel['migrator'];

            foreach ((new PluginManager($target))->all() as $data) {
                if (empty($data['active'])) {
                    continue;
                }
                $migrations = $data['dir'].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
                if (is_dir($migrations)) {
                    $migrator->path($migrations);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falcon update: could not re-register relocated plugin migrations: '.$e->getMessage());
        }
    }

    protected function syncFooterDefaults()
    {
        $newAbout = 'A clean, fast, and professional CMS. Built for readability and seamless content delivery.';
        $newCopyright = '© '.date('Y').' All rights reserved by Falcon CMS';

        \DB::table('cms_settings')
            ->where('key', 'footer_about')
            ->where('value', 'LIKE', '%Astra-inspired%')
            ->update(['value' => $newAbout]);

        \DB::table('cms_settings')
            ->where('key', 'theme_footer_copyright')
            ->where('value', 'LIKE', '%Your Site%')
            ->update(['value' => $newCopyright]);

        if (!\DB::table('cms_settings')->where('key', 'footer_about')->exists()) {
            \DB::table('cms_settings')->insert(['key' => 'footer_about', 'value' => $newAbout]);
        }

        // Remove any stored footer logo override so the template default (embedded logo) is used.
        // Users can set a custom footer logo via Customizer at any time after this.
        \DB::table('cms_settings')->where('key', 'theme_footer_logo')->delete();
        \DB::table('cms_settings')->where('key', 'theme_site_logo')->delete();
        forget_cms_options_cache();
    }

    protected function createEcommercePages()
    {
        $pages = [
            ['title' => 'Shop', 'slug' => 'product'],
            ['title' => 'Cart', 'slug' => 'cart'],
            ['title' => 'Checkout', 'slug' => 'checkout'],
            ['title' => 'Account', 'slug' => 'account'],
        ];

        $adminId = User::first()->id ?? 1;

        foreach ($pages as $page) {
            try {
                // Match on the FULL unique key (slug + type + lang_code) and drop any
                // language global scope, so an existing shop page is found instead of
                // re-inserted — otherwise firstOrCreate hits the unique constraint and
                // the whole update reports "completed with errors".
                Post::withoutGlobalScopes()->firstOrCreate(
                    ['slug' => $page['slug'], 'type' => 'page', 'lang_code' => 'en'],
                    [
                        'title' => $page['title'],
                        'status' => 'published',
                        'user_id' => $adminId,
                        'editor_type' => 'rich',
                    ]
                );
            } catch (\Throwable $e) {
                // Page already exists (or a benign race) — never fail the update for this.
                Log::warning('createEcommercePages ['.$page['slug'].']: '.$e->getMessage());
            }
        }
    }
}
