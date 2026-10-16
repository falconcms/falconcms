<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Console\Commands\UpdateFalconCms;
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

    public function test_a_failed_rebuild_is_cleared_again(): void
    {
        $this->assertSame(['config:cache', 'route:cache', 'route:clear'], $this->recache(true, true, ['route:cache']));
    }
}
