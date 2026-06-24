<?php

namespace FalconCms\Core\Console\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks migrations whose created table(s) already exist as "run", so `migrate` never fails
 * with "table already exists" after a partial uninstall, a manual DB reset, or a reinstall on
 * top of an app that already has the default Laravel tables. Works for both the app's own and
 * the package's migration files — without needing any of them to be hand-edited.
 *
 * @method void line(string $string, ?string $style = null, int|string|null $verbosity = null)
 */
trait ReconcilesMigrations
{
    protected function reconcileExistingMigrations(): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        $ran   = DB::table('migrations')->pluck('migration')->all();
        $batch = (int) DB::table('migrations')->max('batch');
        $batch = $batch > 0 ? $batch : 1;

        $paths = [database_path('migrations')];
        try {
            $paths = array_merge($paths, app('migrator')->paths());
        } catch (\Throwable $e) {
            // migrator not resolvable — fall back to the app path only
        }
        $paths = array_values(array_unique($paths));

        $reconciled = 0;
        foreach ($paths as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                if (in_array($name, $ran, true)) {
                    continue;
                }
                $tables = $this->tablesCreatedBy($file);
                // Only skip a migration when EVERY table it creates already exists, so migrations
                // that still need to run (their tables were dropped) are left untouched.
                if ($tables && collect($tables)->every(fn ($t) => Schema::hasTable($t))) {
                    DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                    $ran[] = $name;
                    $reconciled++;
                }
            }
        }

        if ($reconciled > 0) {
            $this->line("  Reconciled {$reconciled} migration(s) whose tables already exist (they will be skipped).");
        }
    }

    /** Table names a migration file creates via Schema::create('table', ...). */
    protected function tablesCreatedBy(string $file): array
    {
        $code = @file_get_contents($file);
        if (! $code) {
            return [];
        }
        preg_match_all("/Schema::create\\(\\s*'([^']+)'/", $code, $m);

        return array_values(array_unique($m[1] ?? []));
    }
}
