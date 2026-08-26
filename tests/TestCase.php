<?php

namespace FalconCms\Core\Tests;

use App\Models\User;
use FalconCms\Core\Core\HookManager;
use FalconCms\Core\FalconCmsServiceProvider;
use FalconCms\Core\Pro\LicenseGateway;
use FalconCms\Core\Tests\Doubles\LicensedGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base class for the package's own tests.
 *
 * Testbench boots a throwaway Laravel application around the package, so these tests
 * exercise the real service provider, the real migrations and the real helper API —
 * no host site required, and nothing they do can touch a real database.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** @var array{actions: array<string, mixed>, filters: array<string, mixed>}|null */
    private static ?array $hookBaseline = null;

    protected function setUp(): void
    {
        // Before the app boots, because booting is what registers the hooks. The registry is
        // a process-wide singleton, so without this each test would inherit the previous
        // test's filters and a second copy of every hook the provider registers.
        //
        // A snapshot rather than a reset: helpers.php registers the builder element library
        // while it is being loaded, which happens once per process. Resetting would throw
        // that away for every test after the first. The baseline is captured before the
        // very first boot, so it holds exactly those file-load registrations and nothing
        // else, and each boot then adds one clean copy of the provider's own hooks.
        if (self::$hookBaseline === null) {
            self::$hookBaseline = HookManager::snapshot();
        } else {
            HookManager::restore(self::$hookBaseline);
        }

        parent::setUp();

        // get_cms_option() keeps a per-request static store on top of the cache. Tests run
        // in one process, so without this the first test's settings would leak into the rest.
        if (function_exists('forget_cms_options_cache')) {
            forget_cms_options_cache();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [FalconCmsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // The options store is cached; an array driver keeps each test isolated.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('queue.default', 'sync');

        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * Open the Pro-gated storefront routes.
     *
     * cart/checkout sit behind EnsurePro, which asks the LicenseGateway contract. Whether
     * that gate is right is the Pro package's business and is tested there; a test about
     * checkout needs it open so it can reach checkout.
     */
    protected function withProLicensed(): void
    {
        $this->app->instance(
            LicenseGateway::class,
            new LicensedGateway
        );
    }

    /**
     * Give a setting a value the way the admin screens do, and drop the cached copy so
     * the very next get_cms_option() sees it.
     *
     * @param  array<string, string|null>  $values
     */
    protected function setCmsOptions(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }

        forget_cms_options_cache();
    }
}
