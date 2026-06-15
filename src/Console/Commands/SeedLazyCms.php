<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;

class SeedLazyCms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'falcon:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Lazy CMS default menus and initial data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding Lazy CMS data...');

        try {
            $this->info('Step 1: Syncing Roles, Permissions and Menus...');
            $this->call('db:seed', [
                '--class' => 'FalconCms\\Core\\Database\\Seeders\\SystemSyncSeeder',
                '--force' => true
            ]);

            $this->info('Step 2: Syncing Languages...');
            $this->call('db:seed', [
                '--class' => 'FalconCms\\Core\\Database\\Seeders\\LanguageSeeder',
                '--force' => true
            ]);

            $this->info('Step 3: Creating default pages & sample content...');
            $this->call('db:seed', [
                '--class' => 'FalconCms\\Core\\Database\\Seeders\\DefaultContentSeeder',
                '--force' => true
            ]);

            $this->info('Lazy CMS seeding completed successfully!');
        } catch (\Exception $e) {
            $this->error('Seeding failed: ' . $e->getMessage());
        }
    }
}
