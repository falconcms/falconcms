<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Console\Commands\UpdateFalconCms;
use FalconCms\Core\Models\Plugin;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * falcon:update clears every cache, and puts back the config and route caches a site had.
 *
 * It used to stop at optimize:clear, so each update left a cached production site booting
 * uncached — and Site Health flagging it — until someone ran `php artisan optimize` by hand.
 */
class UpdateKeepsCachesTest extends TestCase
{
    /** Runs recache() and returns the commands it called, with $failing ones exiting 1. */
    private function recache(bool $config, bool $routes, array $failing = []): array
    {
        $cmd = new class extends UpdateFalconCms
        {
            public array $called = [];

            public array $failing = [];

            public function callSilently($command, array $arguments = [])
            {
                $this->called[] = $command;

                return in_array($command, $this->failing, true) ? 1 : 0;
            }
        };
        $cmd->failing = $failing;
        $cmd->setLaravel($this->app);
        $cmd->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $m = new ReflectionMethod(UpdateFalconCms::class, 'recache');
        $m->invoke($cmd, $config, $routes);

        return $cmd->called;
    }

    public function test_what_was_cached_is_rebuilt(): void
    {
        $this->assertSame(['config:cache', 'route:cache'], $this->recache(true, true));
        $this->assertSame(['route:cache'], $this->recache(false, true));
    }

    public function test_a_site_that_never_cached_stays_uncached(): void
    {
        $this->assertSame([], $this->recache(false, false));
    }

    public function test_the_dashboard_can_say_the_config_was_cached_before_composer_ran(): void
    {
        // Composer deletes bootstrap/cache/config.php before falcon:update starts, so the
        // dashboard reports what it saw; the option must exist or the update would fail.
        $this->assertTrue((new UpdateFalconCms)->getDefinition()->hasOption('config-cached'));

        $source = (string) file_get_contents(dirname(__DIR__, 3).'/src/Http/Controllers/Admin/DashboardController.php');
        $this->assertSame(2, substr_count($source, "falcon:update --no-ansi'.\$recacheFlag"), 'both the core and the Pro update pass it');
    }

    public function test_an_active_plugin_whose_files_moved_ahead_is_finished(): void
    {
        // The demo after the Pro 1.3.1 update: Falcon Wholesale's files were 1.0.1, the site
        // still recorded 1.0.0, and the reconcile that should have run had failed unseen.
        $dir = resource_path('views/plugins/zz-reconcile');
        try {
            @mkdir($dir, 0777, true);
            file_put_contents($dir.'/plugin.json', json_encode(['name' => 'ZZ Reconcile', 'slug' => 'zz-reconcile', 'version' => '1.0.1']));
            Plugin::forceCreate(['slug' => 'zz-reconcile', 'version' => '1.0.0', 'is_active' => true]);
            Plugin::forceCreate(['slug' => 'zz-off', 'version' => '0.1.0', 'is_active' => false]);

            $cmd = new UpdateFalconCms;
            $cmd->setLaravel($this->app);
            $out = new BufferedOutput;
            $cmd->setOutput(new OutputStyle(new ArrayInput([]), $out));
            (new ReflectionMethod(UpdateFalconCms::class, 'reconcilePluginUpdates'))->invoke($cmd);

            $this->assertSame('1.0.1', Plugin::where('slug', 'zz-reconcile')->value('version'));
            $this->assertStringContainsString('ZZ Reconcile updated to 1.0.1', $out->fetch());
        } finally {
            @unlink($dir.'/plugin.json');
            @rmdir($dir);
        }
    }

    public function test_a_failed_rebuild_is_cleared_again(): void
    {
        $this->assertSame(['config:cache', 'route:cache', 'route:clear'], $this->recache(true, true, ['route:cache']));
    }
}
