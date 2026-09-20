<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Console\Commands\UpdateFalconCms;
use FalconCms\Core\Tests\TestCase;
use ReflectionMethod;

/**
 * Handing file ownership back after an update.
 *
 * Composer and the publish steps write as whoever runs the command, which from a shell on a
 * Docker host is usually root. The site keeps serving — reading is all it needs — and the
 * damage only shows at the next update from the dashboard, whose pre-flight check correctly
 * refuses to start because the web process cannot rewrite vendor/falconcms/falconcms.
 */
class UpdateOwnershipTest extends TestCase
{
    private function invoke(string $method, array $args = [])
    {
        $m = new ReflectionMethod(UpdateFalconCms::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs(new UpdateFalconCms, $args);
    }

    public function test_it_does_nothing_at_all_unless_it_is_running_as_root(): void
    {
        // The tests do not run as root, so this is the real guard being exercised: as the web
        // user there is nothing to repair and no permission to try, and it must not throw.
        $before = @fileowner(base_path('composer.json'));

        $this->invoke('restoreWebServerOwnership');

        $this->assertSame($before, @fileowner(base_path('composer.json')));
    }

    public function test_it_works_out_who_the_site_runs_as_from_a_file_it_never_rewrites(): void
    {
        // public/index.php over a guessed name like www-data, which is right on Debian images
        // and wrong on plenty of others.
        $owner = $this->invoke('webServerOwner');

        if ($owner === null) {
            $this->assertTrue(true, 'every probe is root-owned here, which is a valid answer');

            return;
        }

        $this->assertIsInt($owner['uid']);
        $this->assertIsInt($owner['gid']);
        $this->assertNotSame(0, $owner['uid'], 'root is never the answer to "who does the site run as"');
    }

    public function test_walking_a_tree_it_cannot_change_is_not_an_error(): void
    {
        $dir = sys_get_temp_dir().'/falcon-chown-'.uniqid();
        mkdir($dir.'/nested', 0777, true);
        file_put_contents($dir.'/nested/file.txt', 'x');

        $this->invoke('chownRecursive', [$dir, 65534, 65534]);
        $this->invoke('chownRecursive', [$dir.'/does-not-exist', 65534, 65534]);

        $this->assertFileExists($dir.'/nested/file.txt', 'the tree was damaged by a chown it could not make');

        unlink($dir.'/nested/file.txt');
        rmdir($dir.'/nested');
        rmdir($dir);
    }
}
