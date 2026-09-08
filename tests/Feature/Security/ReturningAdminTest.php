<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Http\Middleware\AdminMiddleware;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * What an admin sees when their session has gone.
 *
 * /admin answers a stranger with 404 so that guessing the obvious path cannot reveal the
 * login URL — see {@see AdminAccessTest}. Taken alone that also meets the site's own
 * administrator: sessions last hours, work in a builder tab lasts longer, and nothing on
 * the site links back to a login page they may never have written down. A bare 404 at
 * that moment reads as a broken site, not as "sign in again".
 *
 * So a browser that has signed in here before is sent to the login page instead. It
 * already knows the address, so it learns nothing; a browser that has not still gets the
 * 404. The marker is an ordinary Laravel cookie, which means it is encrypted with APP_KEY
 * and cannot be produced by anyone who does not already have the key.
 */
class ReturningAdminTest extends TestCase
{
    use MakesShopFixtures;

    private function administrator()
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            // Verified, or the sign-in stops at the verify-email notice and never reaches
            // the point this test is about.
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_browser_that_has_never_signed_in_still_gets_a_404(): void
    {
        $response = $this->get('/admin');

        $response->assertNotFound();
        $this->assertNull($response->headers->get('Location'), 'a stranger must not be pointed anywhere');
    }

    public function test_signing_in_marks_the_browser(): void
    {
        $admin = $this->administrator();

        $response = $this->post(route('admin.login'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        // Guard the premise: if the sign-in did not happen, the assertion below would
        // pass or fail for reasons that have nothing to do with the marker.
        $this->assertTrue(auth()->check(), 'the sign-in itself must succeed');
        $response->assertRedirect();

        $this->assertNotNull(
            $this->getCookie(AdminMiddleware::RETURNING_COOKIE),
            'a completed sign-in should leave the marker behind'
        );
    }

    public function test_a_returning_browser_without_a_session_is_sent_to_the_login_page(): void
    {
        $response = $this->withCookie(AdminMiddleware::RETURNING_COOKIE, '1')->get('/admin');

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('error');
    }

    public function test_the_marker_does_not_let_anyone_in(): void
    {
        // It only changes what an unauthenticated visitor is shown. It is not a session.
        $response = $this->withCookie(AdminMiddleware::RETURNING_COOKIE, '1')->get('/admin/settings');

        $response->assertRedirect(route('admin.login'));
        $this->assertFalse(auth()->check(), 'the marker must never stand in for signing in');
    }

    public function test_an_administrator_with_a_session_is_unaffected(): void
    {
        $this->actingAs($this->administrator())->get('/admin')->assertOk();
    }

    /** Read a cookie the response queued, whatever Laravel wrapped it in. */
    private function getCookie(string $name)
    {
        foreach (app('cookie')->getQueuedCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
