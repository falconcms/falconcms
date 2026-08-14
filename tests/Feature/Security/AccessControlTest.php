<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Http\Middleware\AuthenticateApiToken;
use FalconCms\Core\Models\ApiToken;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ways into an account, and the ways out of the site.
 *
 * Everything here is a credential of some kind — a bearer token, a link mailed to an
 * inbox, a redirect target off a query string. The tests are written from the attacker's
 * side: reuse the link, block the account and try anyway, point the redirect somewhere
 * else. A pass means the door is shut, not that the happy path works.
 */
class AccessControlTest extends TestCase
{
    use MakesShopFixtures;

    // ---- open redirect ---------------------------------------------------------

    /**
     * A "come back here afterwards" URL is attacker-controlled. If it can point at another
     * host, the site becomes a credible-looking springboard into a phishing page.
     */
    public function test_a_redirect_target_cannot_leave_the_site(): void
    {
        config(['app.url' => 'https://shop.test']);

        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('safeRedirectUrl');
        $method->setAccessible(true);
        $safe = fn (string $url) => $method->invoke($controller, $url);

        $home = url('/');

        foreach ([
            '//evil.test',
            '//evil.test/path',
            'https://evil.test',
            'http://evil.test/checkout',
            // The host is what the browser obeys, not the userinfo before the @.
            'https://shop.test@evil.test',
            // A browser reads these backslashes as slashes, so they are protocol-relative
            // in practice — while parse_url() sees no host and calls them relative paths.
            '/\evil.test',
            '\/evil.test',
            '\\\\evil.test',
            "\n//evil.test",
            "\t//evil.test",
            '',
            '   ',
        ] as $hostile) {
            $this->assertSame($home, $safe($hostile), 'escaped via: '.json_encode($hostile));
        }
    }

    /** userinfo before the @ is decoration; this one genuinely lands on our host. */
    public function test_a_username_that_looks_like_another_host_is_not_an_escape(): void
    {
        config(['app.url' => 'https://shop.test']);

        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('safeRedirectUrl');
        $method->setAccessible(true);

        $this->assertSame('https://evil.test@shop.test',
            $method->invoke($controller, 'https://evil.test@shop.test'));
    }

    public function test_a_redirect_target_on_the_site_is_kept(): void
    {
        config(['app.url' => 'https://shop.test']);

        $controller = new ShopFrontendController;
        $method = (new ReflectionClass($controller))->getMethod('safeRedirectUrl');
        $method->setAccessible(true);

        foreach (['/cart', '/checkout?step=2', 'https://shop.test/account'] as $ours) {
            $this->assertSame($ours, $method->invoke($controller, $ours));
        }
    }

    // ---- API tokens ------------------------------------------------------------

    private function callApiWith(?string $bearer): Response
    {
        $request = Request::create('/api/v1/posts', 'GET');
        if ($bearer !== null) {
            $request->headers->set('Authorization', 'Bearer '.$bearer);
        }
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        return (new AuthenticateApiToken)->handle(
            $request,
            fn () => response()->json(['success' => true, 'user' => auth()->id()])
        );
    }

    private function issueToken(User $user): string
    {
        $plain = Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'Test token',
            'token' => hash('sha256', $plain),
        ]);

        return $plain;
    }

    public function test_a_request_with_no_token_is_rejected(): void
    {
        $this->assertSame(401, $this->callApiWith(null)->getStatusCode());
    }

    public function test_a_token_that_does_not_exist_is_rejected(): void
    {
        $this->assertSame(401, $this->callApiWith('nonsense')->getStatusCode());
    }

    /** The column holds a sha256 hash; presenting the hash must not authenticate. */
    public function test_the_stored_hash_is_not_itself_a_usable_token(): void
    {
        $user = $this->makeUser();
        $plain = $this->issueToken($user);

        $this->assertSame(401, $this->callApiWith(hash('sha256', $plain))->getStatusCode());
    }

    public function test_a_valid_token_authenticates_as_its_owner(): void
    {
        $user = $this->makeUser();
        $token = $this->issueToken($user);

        $response = $this->callApiWith($token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($user->id, json_decode($response->getContent(), true)['user']);
    }

    public function test_using_a_token_records_when_it_was_last_used(): void
    {
        $user = $this->makeUser();
        $token = $this->issueToken($user);

        $this->assertNull(ApiToken::where('user_id', $user->id)->value('last_used_at'));

        $this->callApiWith($token);

        $this->assertNotNull(ApiToken::where('user_id', $user->id)->value('last_used_at'));
    }

    /** Blocking an account has to mean blocking it everywhere, the API included. */
    public function test_a_blocked_users_token_stops_working(): void
    {
        $user = $this->makeUser();
        $token = $this->issueToken($user);
        $this->assertSame(200, $this->callApiWith($token)->getStatusCode());

        $user->forceFill(['is_blocked' => true])->save();

        $this->assertSame(403, $this->callApiWith($token)->getStatusCode());
    }

    public function test_a_temporarily_blocked_user_is_refused_until_the_block_lapses(): void
    {
        $user = $this->makeUser();
        $token = $this->issueToken($user);

        $user->forceFill(['blocked_until' => now()->addHour()])->save();
        $this->assertSame(403, $this->callApiWith($token)->getStatusCode());

        $user->forceFill(['blocked_until' => now()->subHour()])->save();
        $this->assertSame(200, $this->callApiWith($token)->getStatusCode());
    }

    public function test_deleting_the_user_takes_their_tokens_with_them(): void
    {
        $user = $this->makeUser();
        $token = $this->issueToken($user);

        $user->delete();

        $this->assertSame(401, $this->callApiWith($token)->getStatusCode());
    }

    // ---- magic-link login ------------------------------------------------------

    private function issueMagicLink(string $email, ?\DateTimeInterface $expiresAt = null): string
    {
        $plain = Str::random(48);

        DB::table('magic_login_tokens')->insert([
            'email' => $email,
            'token' => hash('sha256', $plain),
            'expires_at' => $expiresAt ?? now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plain;
    }

    private function verifyMagicLink(string $token): void
    {
        $request = Request::create('/magic-login/'.$token, 'GET');
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        (new ShopFrontendController)->verifyMagicLink($request, $token);
    }

    public function test_a_valid_magic_link_signs_the_customer_in(): void
    {
        $user = $this->makeUser();
        $token = $this->issueMagicLink($user->email);

        $this->verifyMagicLink($token);

        $this->assertTrue(auth()->check());
        $this->assertSame($user->id, auth()->id());
    }

    public function test_a_magic_link_works_once(): void
    {
        $user = $this->makeUser();
        $token = $this->issueMagicLink($user->email);

        $this->verifyMagicLink($token);
        auth()->logout();

        $this->verifyMagicLink($token);

        $this->assertFalse(auth()->check(), 'the same link signed someone in twice');
    }

    public function test_an_expired_magic_link_is_refused(): void
    {
        $user = $this->makeUser();
        $token = $this->issueMagicLink($user->email, now()->subMinute());

        $this->verifyMagicLink($token);

        $this->assertFalse(auth()->check());
    }

    public function test_a_forged_magic_link_is_refused(): void
    {
        $this->makeUser();

        $this->verifyMagicLink(Str::random(48));

        $this->assertFalse(auth()->check());
    }

    /** The table stores a hash, so reading the database must not yield a usable link. */
    public function test_the_stored_hash_is_not_itself_a_usable_link(): void
    {
        $user = $this->makeUser();
        $plain = $this->issueMagicLink($user->email);

        $this->verifyMagicLink(hash('sha256', $plain));

        $this->assertFalse(auth()->check());
    }

    public function test_a_blocked_customer_cannot_sign_in_by_email(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['is_blocked' => true])->save();
        $token = $this->issueMagicLink($user->email);

        $this->verifyMagicLink($token);

        $this->assertFalse(auth()->check());
    }

    public function test_a_link_for_an_address_with_no_account_signs_nobody_in(): void
    {
        $token = $this->issueMagicLink('ghost@example.test');

        $this->verifyMagicLink($token);

        $this->assertFalse(auth()->check());
    }

    /**
     * Requesting a link never says whether the address is registered — the response is the
     * same either way, so the form cannot be used to harvest accounts.
     */
    public function test_requesting_a_link_gives_nothing_away(): void
    {
        $this->setCmsOptions(['magic_login_enabled' => '1']);
        $user = $this->makeUser();

        $known = $this->requestMagicLinkFor($user->email);
        $unknown = $this->requestMagicLinkFor('nobody@example.test');

        $this->assertSame($known, $unknown, 'the two responses must be indistinguishable');

        $this->assertSame(1, DB::table('magic_login_tokens')->count(),
            'only the real address gets a token');
    }

    private function requestMagicLinkFor(string $email): array
    {
        Mail::fake();

        $request = Request::create('/magic-login', 'POST', ['magic_email' => $email]);
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        $response = (new ShopFrontendController)->requestMagicLink($request);

        return [
            'status' => $response->getStatusCode(),
            'session' => $response->getSession()?->get('magic_sent'),
        ];
    }
}
