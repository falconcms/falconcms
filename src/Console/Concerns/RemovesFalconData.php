<?php

namespace FalconCms\Core\Console\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared table-removal helpers for the uninstall commands.
 *
 * @method void line(string $string, ?string $style = null, int|string|null $verbosity = null)
 */
trait RemovesFalconData
{
    /** CMS-owned tables — always removed (child/pivot tables first so FK order is safe). */
    protected function cmsTables(): array
    {
        return [
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
    }

    /** Shared Laravel tables — only removed with --all. */
    protected function sharedTables(): array
    {
        return [
            'users', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'password_reset_tokens',
        ];
    }

    /** Drop the package's tables. Returns the number dropped. */
    protected function dropFalconTables(bool $all): int
    {
        $tables = $all ? array_merge($this->cmsTables(), $this->sharedTables()) : $this->cmsTables();

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

        return $dropped;
    }

    /** Delete this package's rows from the migrations table so a reinstall re-runs cleanly. */
    protected function removeFalconMigrationRecords(): int
    {
        if (! Schema::hasTable('migrations')) {
            return 0;
        }
        $dir = __DIR__ . '/../../../database/migrations';
        if (! is_dir($dir)) {
            return 0;
        }
        $names = array_map(fn ($p) => basename($p, '.php'), glob($dir . '/*.php') ?: []);

        return $names ? DB::table('migrations')->whereIn('migration', $names)->delete() : 0;
    }
}
