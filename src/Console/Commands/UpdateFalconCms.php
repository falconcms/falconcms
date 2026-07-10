<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Console\Concerns\ReconcilesMigrations;
use Illuminate\Console\Command;

class UpdateFalconCms extends Command
{
    use ReconcilesMigrations;

    protected $signature = 'falcon:update';
    protected $description = 'Update Falcon CMS: run migrations, sync system data, and refresh assets/themes.';

    public function handle()
    {
        $this->info('--- Starting Falcon CMS Update ---');

        // 1. Run Migrations (reconcile already-existing tables first so migrate never fails
        //    with "table already exists" on a partial / pre-existing database).
        $this->info('Step 1: Running migrations...');
        $this->reconcileExistingMigrations();
        $this->call('migrate', ['--force' => true]);

        // 2. Sync System Data (Permissions, Roles, Menus)
        $this->info('Step 2: Syncing system data...');
        $this->call('db:seed', [
            '--class' => 'FalconCms\\Core\\Database\\Seeders\\SystemSyncSeeder',
            '--force' => true
        ]);

        // 3. Publish Assets (Force)
        $this->info('Step 3: Refreshing dashboard assets...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-cms-assets',
            '--force' => true
        ]);

        // 4. Publish Themes (Force) — parent theme only
        $this->info('Step 4: Refreshing themes...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-themes',
            '--force' => true
        ]);

        // 4a. Remove ALL published package view overrides so the vendor views are
        // always used directly. A stale copy under resources/views/vendor/falcon-cms
        // silently shadows the real namespaced package view — e.g. a months-old
        // frontend/builder/column.blade.php kept serving the old layout after every
        // update, regardless of cache/OPcache clears. Deleting the whole namespace
        // directory guarantees no override can linger.
        $publishedViewsPath = resource_path('views/vendor/falcon-cms');
        if (is_dir($publishedViewsPath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($publishedViewsPath);
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
    protected function syncFooterDefaults()
    {
        $newAbout     = 'A clean, fast, and professional CMS. Built for readability and seamless content delivery.';
        $newCopyright = '© ' . date('Y') . ' All rights reserved by Falcon CMS';

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

        $adminId = \App\Models\User::first()->id ?? 1;

        foreach ($pages as $page) {
            \FalconCms\Core\Models\Post::firstOrCreate(
                ['slug' => $page['slug'], 'type' => 'page'],
                [
                    'title' => $page['title'],
                    'status' => 'published',
                    'lang_code' => 'en',
                    'user_id' => $adminId,
                    'editor_type' => 'rich'
                ]
            );
        }
    }
}
