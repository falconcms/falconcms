<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Support\SiteUrlMigrator;
use Illuminate\Console\Command;

/**
 * Point every link to another site's address at this site: what a restore from another site
 * does by itself, for a database that was copied over before it did (or by other means).
 *
 *   php artisan falcon:replace-url                      (old address read from the data)
 *   php artisan falcon:replace-url https://falconcms.com
 *   php artisan falcon:replace-url https://old.com https://new.com
 *   php artisan falcon:replace-url http://falconcms.test --relative   (→ "/about": right under any address)
 */
class ReplaceSiteUrl extends Command
{
    protected $signature = 'falcon:replace-url
        {from? : The old address, e.g. https://falconcms.com (default: worked out from the data)}
        {to? : The new address (default: this site\'s APP_URL)}
        {--relative : Drop the old address and keep the path ("/about"), so links work under any address}
        {--force : Do not ask for confirmation}';

    protected $description = 'Move links from another site\'s address to this site (menus, content, layouts, settings)';

    public function handle(): int
    {
        $from = SiteUrlMigrator::normalize($this->argument('from') ?: SiteUrlMigrator::guess());
        $relative = (bool) $this->option('relative');
        $to = $relative ? null : SiteUrlMigrator::normalize($this->argument('to') ?: config('app.url'));
        if (!$from) {
            $this->error('No old address given, and none could be worked out from the data.');

            return self::FAILURE;
        }
        if (!$relative && !$to) {
            $this->error('No new address: pass one, set APP_URL, or use --relative.');

            return self::FAILURE;
        }
        if ($from === $to) {
            $this->info("The data already links to {$to}. Nothing to do.");

            return self::SUCCESS;
        }
        $label = $relative ? 'paths ("/…")' : $to;
        if (!$this->option('force') && !$this->confirm("Replace links to {$from} with {$label}?", true)) {
            return self::SUCCESS;
        }

        $stats = SiteUrlMigrator::migrate($from, $to);
        foreach ($stats['tables'] as $table => $n) {
            $this->line("  {$table}: {$n}");
        }
        $this->info("Moved {$stats['values']} values from {$from} to {$label}.");
        if ($stats['skipped']) {
            $this->warn('Skipped (no single-column primary key): '.implode(', ', $stats['skipped']));
        }

        return self::SUCCESS;
    }
}
