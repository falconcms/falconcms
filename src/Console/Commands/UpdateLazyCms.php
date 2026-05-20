<?php

namespace Acme\CmsDashboard\Console\Commands;

use Illuminate\Console\Command;

class UpdateLazyCms extends Command
{
    protected $signature = 'lazy:update';
    protected $description = 'Update Lazy CMS: run migrations, sync system data, and refresh assets/themes.';

    public function handle()
    {
        $this->info('--- Starting Lazy CMS Update ---');

        // 1. Run Migrations
        $this->info('Step 1: Running migrations...');
        $this->call('migrate', ['--force' => true]);

        // 2. Sync System Data (Permissions, Roles, Menus)
        $this->info('Step 2: Syncing system data...');
        $this->call('db:seed', [
            '--class' => 'Acme\\CmsDashboard\\Database\\Seeders\\SystemSyncSeeder',
            '--force' => true
        ]);

        // 3. Publish Assets (Force)
        $this->info('Step 3: Refreshing dashboard assets...');
        $this->call('vendor:publish', [
            '--tag' => 'cms-dashboard-assets',
            '--force' => true
        ]);

        // 4. Publish Themes (Force)
        $this->info('Step 4: Refreshing themes...');
        $this->call('vendor:publish', [
            '--tag' => 'lazy-themes',
            '--force' => true
        ]);

        // 5. Clear Cache
        $this->info('Step 5: Clearing cache...');
        $this->call('optimize:clear');

        // 6. Auto-create E-commerce pages
        $this->info('Step 6: Auto-creating E-commerce pages...');
        $this->createEcommercePages();

        $this->info('---------------------------------------');
        $this->info('Lazy CMS updated successfully!');
        $this->info('---------------------------------------');
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
            \Acme\CmsDashboard\Models\Post::firstOrCreate(
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
