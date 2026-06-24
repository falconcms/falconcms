<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Console\Concerns\RemovesFalconData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class UninstallFalconCms extends Command
{
    use RemovesFalconData;

    protected $signature = 'falcon:uninstall
        {--force : Skip the confirmation prompt}
        {--all : Also drop shared Laravel tables (users, sessions, cache, jobs) — full wipe}
        {--keep-files : Keep published views, themes and assets}
        {--no-composer : Do not run "composer remove" automatically}';

    protected $description = 'Fully remove FalconCMS: database tables, app code references, published files and the Composer package.';

    public function handle(): int
    {
        $dropShared = (bool) $this->option('all');

        $this->warn('  This will PERMANENTLY remove FalconCMS — database tables, the trait added to your User model,');
        $this->warn('  published files, and the Composer package itself.');
        $this->line($dropShared
            ? '  --all is set: shared Laravel tables (users, sessions, cache, jobs) will ALSO be dropped.'
            : '  Shared Laravel tables (users, sessions, cache, jobs) are kept. Pass --all to drop them too.');

        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure you want to uninstall FalconCMS?', false)) {
            $this->info('Aborted. Nothing was changed.');
            return self::SUCCESS;
        }

        // 1) Revert the App\Models\User changes FIRST, so the app keeps booting once the package is gone.
        $this->revertUserModel();

        // 2) Drop tables + migration records.
        $this->info('Dropping tables…');
        $dropped = $this->dropFalconTables($dropShared);
        $this->info("Dropped {$dropped} table(s).");
        $migrations = $this->removeFalconMigrationRecords();
        $this->info("Removed {$migrations} migration record(s).");

        // 3) Remove published files.
        if (! $this->option('keep-files')) {
            $this->removePublishedFiles();
        } else {
            $this->line('Kept published files (--keep-files).');
        }

        // 4) Clear caches that don't depend on the (now dropped) tables — before touching Composer.
        foreach (['view:clear', 'route:clear', 'config:clear'] as $cmd) {
            try { $this->callSilently($cmd); } catch (\Throwable $e) {}
        }

        // 5) Remove the Composer package itself.
        if ($this->option('no-composer')) {
            $this->line('Skipped Composer removal (--no-composer). Run it yourself with:');
            $this->line('    composer remove falconcms/falconcms');
        } else {
            $this->removeComposerPackage();
        }

        // 6) Clear the package-discovery / services caches so they don't reference the removed provider.
        //    (composer remove runs with --no-scripts, so it doesn't regenerate these itself.)
        $this->clearBootstrapCaches();

        $this->newLine();
        $this->info('✔ FalconCMS has been fully removed. Your application should boot cleanly.');

        return self::SUCCESS;
    }

    /** Remove the HasCmsPermissions trait + import that the installer added to App\Models\User. */
    protected function revertUserModel(): void
    {
        $path = app_path('Models/User.php');
        if (! file_exists($path)) {
            return;
        }
        $content  = file_get_contents($path);
        $original = $content;

        // Drop the namespace import first ("use FalconCms\Core\Traits\HasCmsPermissions;").
        $content = preg_replace('/\R[ \t]*use\s+FalconCms\\\\Core\\\\Traits\\\\HasCmsPermissions\s*;/', '', $content);
        // Drop a standalone trait-use line ("use HasCmsPermissions;").
        $content = preg_replace('/\R[ \t]*use\s+HasCmsPermissions\s*;/', '', $content);
        // Drop it from a combined trait list ("use A, B, HasCmsPermissions;" / "use HasCmsPermissions, A;").
        $content = preg_replace('/,\s*HasCmsPermissions\b/', '', $content);
        $content = preg_replace('/\buse\s+HasCmsPermissions\s*,\s*/', 'use ', $content);

        if ($content !== null && $content !== $original) {
            file_put_contents($path, $content);
            $this->info('Reverted App\\Models\\User (removed the HasCmsPermissions trait).');
        } else {
            $this->line('App\\Models\\User had no FalconCMS trait to remove.');
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

    /** Delete the compiled package/service caches that still list the (removed) service provider. */
    protected function clearBootstrapCaches(): void
    {
        foreach (['bootstrap/cache/packages.php', 'bootstrap/cache/services.php', 'bootstrap/cache/config.php'] as $rel) {
            $path = base_path($rel);
            if (File::exists($path)) {
                File::delete($path);
                $this->line('  • cleared ' . $rel);
            }
        }
    }

    /** Run "composer remove falconcms/falconcms"; fall back to a printed instruction on failure. */
    protected function removeComposerPackage(): void
    {
        $this->info('Removing the Composer package…');
        $composer = file_exists(base_path('composer.phar'))
            ? '"' . PHP_BINARY . '" composer.phar'
            : 'composer';

        try {
            $process = Process::fromShellCommandline(
                $composer . ' remove falconcms/falconcms --no-interaction --no-scripts',
                base_path()
            );
            $process->setTimeout(600);
            $process->run(fn ($type, $buffer) => $this->output->write($buffer));

            if ($process->isSuccessful()) {
                $this->info('Composer package removed.');
                return;
            }
        } catch (\Throwable $e) {
            // fall through to the manual instruction
        }

        $this->warn('Could not run Composer automatically. Finish the removal manually with:');
        $this->line('    composer remove falconcms/falconcms');
    }
}
