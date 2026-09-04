<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * Changing the login or registration URL in Settings → General.
 *
 * The two slugs are read in routes/web.php while the routes are being registered —
 * and on a site with cached routes that file never runs. Every production install
 * caches its routes, so changing either slug did nothing at all there: the new URL
 * returned 404 and the old one carried on working, with nothing to say a cache was
 * in the way. The setting looked saved, because it was; only the routing never heard
 * about it.
 */
class LoginSlugTest extends TestCase
{
    /**
     * The routes have to be built from the options, not from constants — otherwise no
     * amount of cache clearing would help.
     */
    public function test_the_routes_are_built_from_the_settings(): void
    {
        $routes = file_get_contents(__DIR__.'/../../../routes/web.php');

        $this->assertMatchesRegularExpression(
            "/\\\$login_slug\s*=\s*get_cms_option\(\s*'login_url'/",
            $routes,
            'the login route no longer follows the setting'
        );
        $this->assertMatchesRegularExpression(
            "/\\\$register_slug\s*=\s*get_cms_option\(\s*'register_url'/",
            $routes,
            'the registration route no longer follows the setting'
        );
    }

    /**
     * Saving either slug must refresh the route cache, or the change is invisible on
     * every cached site — which is all of them.
     */
    public function test_saving_a_slug_refreshes_the_route_cache(): void
    {
        $controller = file_get_contents(
            __DIR__.'/../../../src/Http/Controllers/Admin/DashboardController.php'
        );

        $this->assertStringContainsString('falcon_refresh_route_cache()', $controller,
            'the settings save does not refresh the route cache');

        $guard = strpos($controller, "isset(\$data['login_url']) || isset(\$data['register_url'])");
        $this->assertNotFalse($guard, 'the refresh is not tied to the two settings that need it');

        $call = strpos($controller, 'falcon_refresh_route_cache()');
        $this->assertGreaterThan($guard, $call, 'the cache is refreshed regardless of what was saved');
    }

    /** The helper exists and is safe to call on a site that never cached its routes. */
    public function test_the_refresh_helper_is_a_no_op_when_routes_are_not_cached(): void
    {
        $this->assertTrue(function_exists('falcon_refresh_route_cache'));
        $this->assertFalse(app()->routesAreCached(), 'the test app should not be running cached routes');

        // Nothing to rebuild, so this must return quietly rather than shelling out.
        falcon_refresh_route_cache();

        $this->assertTrue(true);
    }

    /** A slug is stored as a slug, so a typed value can never produce an unreachable URL. */
    public function test_the_slug_is_sanitised_on_save(): void
    {
        $controller = file_get_contents(
            __DIR__.'/../../../src/Http/Controllers/Admin/DashboardController.php'
        );

        $this->assertStringContainsString("\$data['login_url'] = Str::slug(\$data['login_url'])", $controller);
        $this->assertStringContainsString("\$data['register_url'] = Str::slug(\$data['register_url'])", $controller);
    }
}
