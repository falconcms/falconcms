<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * What an admin sees at /admin when their session has gone: a 404, the same as anyone else.
 *
 * /admin answers a stranger with 404 so that guessing the obvious path cannot reveal the
 * address set in Settings → Login URL — see {@see AdminAccessTest}. v2.6.13 carved out an
 * exception for the site's own administrator: a browser carrying a year-long
 * `falcon_admin_seen` cookie, dropped on any successful sign-in, was redirected to the login
 * page instead of meeting a bare 404.
 *
 * The exception was removed again, because the cookie gave away exactly what the 404 was
 * hiding: it was never cleared on logout, so a shared or handed-on machine kept pointing at
 * the login URL for a year, to whoever used it next. A 404 that is only sometimes a 404 is
 * not a 404.
 *
 * That cookie is a year long, so browsers are still carrying it. The test below that hands it
 * over deliberately is the one that matters: the marker must buy nothing.
 */
class ReturningAdminTest extends TestCase
{
    use MakesShopFixtures;

    /** The marker v2.6.13 dropped. The constant is gone; the cookies in the wild are not. */
    private const OLD_MARKER = 'falcon_admin_seen';

    private function administrator()
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            // Verified, or the sign-in stops at the verify-email notice and never reaches
            // the point this test is about.
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_browser_that_has_never_signed_in_gets_a_404(): void
    {
        $response = $this->get('/admin');

        $response->assertNotFound();
        $this->assertNull($response->headers->get('Location'), 'a stranger must not be pointed anywhere');
    }

    public function test_a_browser_carrying_the_old_marker_gets_the_same_404(): void
    {
        // The whole point of the reversal. A machine that was signed in on once, a year ago,
        // must not still be handing the login URL to whoever is sitting at it now.
        $response = $this->withCookie(self::OLD_MARKER, '1')->get('/admin');

        $response->assertNotFound();
        $this->assertNull($response->headers->get('Location'),
            'the old marker still buys a redirect, which is the thing that was removed');
    }

    public function test_signing_in_leaves_no_marker_behind(): void
    {
        $admin = $this->administrator();

        $response = $this->post(route('admin.login'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        // Guard the premise: if the sign-in did not happen, the assertion below would pass
        // for a reason that has nothing to do with the marker.
        $this->assertTrue(auth()->check(), 'the sign-in itself must succeed');
        $response->assertRedirect();

        foreach (app('cookie')->getQueuedCookies() as $cookie) {
            $this->assertNotSame(self::OLD_MARKER, $cookie->getName(),
                'sign-in is dropping the marker again, and nothing clears it on the way out');
        }
    }

    public function test_the_marker_does_not_stand_in_for_a_session(): void
    {
        $response = $this->withCookie(self::OLD_MARKER, '1')->get('/admin/settings');

        $this->assertFalse(auth()->check(), 'the marker must never stand in for signing in');
        $this->assertNotSame(200, $response->getStatusCode(), 'the marker let an unauthenticated visitor through');
    }

    public function test_the_login_url_itself_still_answers(): void
    {
        // The 404 hides where the door is; it does not brick the door.
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_an_administrator_with_a_session_is_unaffected(): void
    {
        $this->actingAs($this->administrator())->get('/admin')->assertOk();
    }
}
