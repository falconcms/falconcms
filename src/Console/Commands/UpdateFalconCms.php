<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;

class UpdateFalconCms extends Command
{
    protected $signature = 'falcon:update';
    protected $description = 'Update Falcon CMS: run migrations, sync system data, and refresh assets/themes.';

    public function handle()
    {
        $this->info('--- Starting Falcon CMS Update ---');

        // 0. Pull latest package from Packagist
        $this->info('Step 0: Pulling latest FalconCMS package...');
        $this->runComposerUpdate();

        // 1. Run Migrations
        $this->info('Step 1: Running migrations...');
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

        // 4a. Remove published admin views so vendor views are always used directly
        $adminViewsPath = resource_path('views/vendor/falcon-cms/admin');
        if (is_dir($adminViewsPath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($adminViewsPath);
            $this->info('Step 4a: Removed stale published admin views.');
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

        // 7. Auto-create E-commerce pages
        $this->info('Step 7: Auto-creating E-commerce pages...');
        $this->createEcommercePages();

        $this->info('---------------------------------------');
        $this->info('Falcon CMS updated successfully!');
        $this->info('---------------------------------------');
    }
    protected function runComposerUpdate()
    {
        $composer = $this->findComposer();
        if (!$composer) {
            $this->warn('Composer not found — skipping package update.');
            return;
        }

        $cmd = $composer . ' update falconcms/falconcms --no-interaction --no-dev --prefer-dist 2>&1';
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

        if (!is_resource($proc)) {
            $this->warn('Could not start composer process.');
            return;
        }

        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line !== false && trim($line) !== '') $this->line(trim($line));
        }
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit = proc_close($proc);
        if ($exit !== 0) {
            $this->warn("Composer exited with code {$exit} — continuing anyway.");
        } else {
            $this->info('Package updated successfully.');
        }
    }

    protected function findComposer(): ?string
    {
        $phpBin = PHP_BINARY;

        foreach (['composer', 'composer.phar'] as $bin) {
            $path = trim((string) shell_exec("which {$bin} 2>/dev/null"));
            if ($path) return "{$phpBin} {$path}";
        }

        $common = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            base_path('composer.phar'),
        ];
        foreach ($common as $path) {
            if (file_exists($path)) return "{$phpBin} {$path}";
        }

        return null;
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
