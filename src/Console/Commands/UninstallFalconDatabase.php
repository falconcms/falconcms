<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Console\Concerns\RemovesFalconData;
use Illuminate\Console\Command;

class UninstallFalconDatabase extends Command
{
    use RemovesFalconData;

    protected $signature = 'falcon:uninstall-db
        {--force : Skip the confirmation prompt}
        {--all : Also drop shared Laravel tables (users, sessions, cache, jobs)}';

    protected $description = 'Remove only FalconCMS database tables (keeps the package code, files and User model trait).';

    public function handle(): int
    {
        $dropShared = (bool) $this->option('all');

        $this->warn('  This will PERMANENTLY DROP FalconCMS database tables and delete all of its data.');
        $this->line($dropShared
            ? '  --all is set: shared Laravel tables (users, sessions, cache, jobs) will ALSO be dropped.'
            : '  Shared Laravel tables (users, sessions, cache, jobs) are kept. Pass --all to drop them too.');
        $this->line('  The package code, published files and User model trait are left untouched.');

        if (! $this->option('force') && ! $this->confirm('Drop the FalconCMS database tables now?', false)) {
            $this->info('Aborted. Nothing was changed.');
            return self::SUCCESS;
        }

        $this->info('Dropping tables…');
        $dropped = $this->dropFalconTables($dropShared);
        $this->info("Dropped {$dropped} table(s).");

        $migrations = $this->removeFalconMigrationRecords();
        $this->info("Removed {$migrations} migration record(s).");

        // Clear caches that don't depend on the dropped tables.
        foreach (['view:clear', 'route:clear', 'config:clear'] as $cmd) {
            try { $this->callSilently($cmd); } catch (\Throwable $e) {}
        }

        $this->newLine();
        $this->info('✔ FalconCMS database tables removed. The package code is still installed.');
        $this->line('  Run "php artisan falcon:install" (or "migrate") to set the database up again.');

        return self::SUCCESS;
    }
}
