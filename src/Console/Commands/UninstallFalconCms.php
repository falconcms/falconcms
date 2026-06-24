<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class UninstallFalconCms extends Command
{
    protected $signature = 'falcon:uninstall
        {--force : Skip the confirmation prompt}
        {--all : Also drop shared Laravel tables (users, sessions, cache, jobs) — full wipe}
        {--keep-files : Keep published views, themes and assets}';

    protected $description = 'Uninstall FalconCMS: drop its database tables, remove migration records and published files.';

    /** CMS-owned tables — always removed (child/pivot tables first so FK order is safe). */
    protected array $cmsTables = [
        'shop_order_downloads', 'shop_order_items', 'shop_order_status_history', 'shop_orders',
        'shop_product_downloads', 'shop_product_variations', 'shop_products', 'shop_reviews',
        'product_category_post', 'product_tag_post', 'product_categories', 'product_tags',
        'post_custom_field_values', 'post_taxonomy_term', 'post_translations', 'post_tag',
        'category_post', 'taxonomy_terms', 'custom_taxonomies', 'custom_field_groups', 'custom_fields',
        'posts', 'post_types', 'categories', 'tags',
        'navigation_menu_items', 'navigation_menus', 'menus', 'widgets', 'media', 'comments',
        'cms_analytics', 'cms_form_submissions', 'cms_forms', 'cms_languages', 'cms_redirects',
        'cms_revisions', 'cms_settings',
        'role_permission', 'role_user', 'permissions', 'roles',
        'activity_logs', 'api_tokens', 'magic_login_tokens', 'blocked_ips',
    ];

    /** Shared Laravel tables — only removed with --all. */
    protected array $sharedTables = [
        'users', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        $dropShared = (bool) $this->option('all');
        $tables = $dropShared ? array_merge($this->cmsTables, $this->sharedTables) : $this->cmsTables;

        $this->warn('  This will PERMANENTLY DROP FalconCMS database tables and delete all of its data.');
        if ($dropShared) {
            $this->warn('  --all is set: shared Laravel tables (users, sessions, cache, jobs) will ALSO be dropped.');
        } else {
            $this->line('  Shared Laravel tables (users, sessions, cache, jobs) are kept. Pass --all to drop them too.');
        }

        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure you want to uninstall FalconCMS?', false)) {
            $this->info('Aborted. Nothing was changed.');
            return self::SUCCESS;
        }

        // 1) Drop tables (foreign-key checks off so drop order never matters).
        $this->info('Dropping tables…');
        Schema::disableForeignKeyConstraints();
        $dropped = 0;
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
                $dropped++;
                $this->line("  • dropped {$table}");
            }
        }
        Schema::enableForeignKeyConstraints();
        $this->info("Dropped {$dropped} table(s).");

        // 2) Remove this package's migration records so a future reinstall re-runs cleanly.
        $this->removeMigrationRecords();

        // 3) Remove published files.
        if (! $this->option('keep-files')) {
            $this->removePublishedFiles();
        } else {
            $this->line('Kept published files (--keep-files).');
        }

        // 4) Clear caches that do not depend on the (now dropped) tables.
        foreach (['view:clear', 'route:clear', 'config:clear'] as $cmd) {
            try { $this->callSilently($cmd); } catch (\Throwable $e) {}
        }

        $this->newLine();
        $this->info('✔ FalconCMS database and files removed.');
        $this->warn('Final step — remove the package code itself with Composer:');
        $this->line('    composer remove falconcms/falconcms');
        $this->line('    (then remove the FalconCms trait/provider references from your app if you added any)');

        return self::SUCCESS;
    }

    /** Delete this package's rows from the migrations table (so reinstall re-runs them). */
    protected function removeMigrationRecords(): void
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return;
            }
            $dir = __DIR__ . '/../../../database/migrations';
            if (! is_dir($dir)) {
                return;
            }
            $names = array_map(fn ($p) => basename($p, '.php'), glob($dir . '/*.php') ?: []);
            if ($names) {
                $count = DB::table('migrations')->whereIn('migration', $names)->delete();
                $this->info("Removed {$count} migration record(s).");
            }
        } catch (\Throwable $e) {
            $this->warn('Could not clean migration records: ' . $e->getMessage());
        }
    }

    /** Delete the directories the package publishes (views, assets, themes). */
    protected function removePublishedFiles(): void
    {
        $paths = [
            resource_path('views/vendor/falcon-cms'),
            public_path('vendor/falcon-cms'),
            resource_path('views/themes/falcon-theme'),
            resource_path('views/themes/falcon-theme-child'),
        ];
        $removed = 0;
        foreach ($paths as $path) {
            if (File::exists($path)) {
                File::deleteDirectory($path);
                $removed++;
                $this->line('  • removed ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));
            }
        }
        $this->info("Removed {$removed} published path(s).");
    }
}
