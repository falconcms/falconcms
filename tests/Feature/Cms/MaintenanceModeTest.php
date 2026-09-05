<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Http\Middleware\MaintenanceModeMiddleware;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hiding the site while it is being worked on.
 *
 * The switch itself always worked; what it let through did not. The bypass was "any
 * logged-in user", which on a site with accounts is not the same thing as "the people
 * doing the work" — a customer who registered last year walked straight through a page
 * meant to hide a half-finished site.
 *
 * The permission that first looked like the right test was not: on a real install the
 * subscriber role held eighteen permissions including manage_settings, so checking for
 * it let through exactly the people it was meant to stop. The question is about the
 * role, which is what isAdmin() answers and what the admin area itself asks.
 */
class MaintenanceModeTest extends TestCase
{
    private function setMode(string $value): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => 'maintenance_mode'], ['value' => $value]);
        forget_cms_options_cache();
    }

    /** Run one request through the middleware, as whoever is currently signed in. */
    private function pass(): Response
    {
        return (new MaintenanceModeMiddleware)->handle(
            Request::create('/', 'GET'),
            fn () => response('<html><body>the site</body></html>')
        );
    }

    /** Off is off: nothing is intercepted and nothing is added to the page. */
    public function test_it_does_nothing_while_switched_off(): void
    {
        $this->setMode('0');

        $response = $this->pass();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Maintenance mode is on', (string) $response->getContent());
    }

    /** A visitor gets the maintenance page, and a 503 so crawlers come back later. */
    public function test_a_visitor_is_shown_the_maintenance_page(): void
    {
        $this->setMode('1');

        $response = $this->pass();

        $this->assertSame(503, $response->getStatusCode(),
            'the maintenance page must say the site is unavailable, not that it is missing or fine');
        $this->assertStringNotContainsString('the site', (string) $response->getContent());
    }

    /**
     * An ordinary signed-in user is a visitor.
     *
     * This is the bug. Anyone with an account — a customer, a commenter, anyone who
     * registered in the minute before the switch was thrown — used to walk through.
     */
    public function test_a_signed_in_reader_is_still_shown_the_maintenance_page(): void
    {
        $this->setMode('1');
        $this->actingAs($this->reader());

        $this->assertSame(503, $this->pass()->getStatusCode(),
            'a reader with an account walked through the maintenance page');
    }

    /** An administrator gets the site, because they are the one fixing it. */
    public function test_an_administrator_sees_the_site(): void
    {
        $this->setMode('1');
        $this->actingAs($this->administrator());

        $response = $this->pass();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('the site', (string) $response->getContent());
    }

    /**
     * And is told, because otherwise they cannot see that it is on.
     *
     * The person who threw the switch is the one person the switch does nothing to.
     * Without a marker, "is maintenance mode working?" is only answerable by opening a
     * private window — and the answer people reach first is that it is broken.
     */
    public function test_an_administrator_is_told_the_site_is_dark(): void
    {
        $this->setMode('1');
        $this->actingAs($this->administrator());

        $this->assertStringContainsString('Maintenance mode is on', (string) $this->pass()->getContent());
    }

    /**
     * The marker only goes into a page that has somewhere to put it. A redirect or a
     * JSON response from the same route group must come back untouched.
     */
    public function test_the_marker_is_only_added_to_a_page(): void
    {
        $this->setMode('1');
        $this->actingAs($this->administrator());

        $json = (new MaintenanceModeMiddleware)->handle(
            Request::create('/', 'GET'),
            fn () => response()->json(['ok' => true])
        );
        $this->assertSame('{"ok":true}', (string) $json->getContent());

        $redirect = (new MaintenanceModeMiddleware)->handle(
            Request::create('/', 'GET'),
            fn () => redirect('/elsewhere')
        );
        $this->assertSame(302, $redirect->getStatusCode());
        $this->assertStringNotContainsString('Maintenance mode is on', (string) $redirect->getContent());
    }

    /** The person doing the work. */
    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin',
            'email' => 'maintenance-admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** A reader with an account, and nothing else. */
    private function reader(): User
    {
        $roleId = DB::table('roles')->where('slug', 'subscriber')->value('id')
            ?: DB::table('roles')->insertGetId(['name' => 'Subscriber', 'slug' => 'subscriber']);

        return User::create([
            'name' => 'Reader',
            'email' => 'reader@example.test',
            'password' => bcrypt('secret-password'),
            'role_id' => $roleId,
        ]);
    }
}
