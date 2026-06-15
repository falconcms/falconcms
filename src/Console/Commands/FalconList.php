<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;

class FalconList extends Command
{
    protected $signature = 'lazy';
    protected $description = 'List all available Lazy CMS commands';

    public function handle()
    {
        $this->info('---------------------------------------');
        $this->info('      Lazy CMS available commands      ');
        $this->info('---------------------------------------');

        $commands = [
            ['falcon:install', 'Full setup: Migrations, Assets, Themes, User and seeds.'],
            ['falcon:update', 'Sync update: Refreshes assets, themes, and permissions.'],
            ['falcon:seed', 'Demo data: Seeds default menus and initial demo data.'],
            ['make:lazy-page', 'Scaffold: Creates a new dashboard page, controller, and menu.'],
            ['vendor:publish --tag=lazy-themes', 'Themes only: Publishes frontend themes to resources.'],
            ['vendor:publish --tag=lazy-views', 'Views override: Publishes admin views for manual override.'],
        ];

        $this->table(['Command', 'Description'], $commands);

        $this->info('Usage: php artisan <command>');
        $this->info('---------------------------------------');
    }
}
