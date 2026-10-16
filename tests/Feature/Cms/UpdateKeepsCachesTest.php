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

    public function test_a_failed_rebuild_is_cleared_again(): void
    {
        $this->assertSame(['config:cache', 'route:cache', 'route:clear'], $this->recache(true, true, ['route:cache']));
    }
}
