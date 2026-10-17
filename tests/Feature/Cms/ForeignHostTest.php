<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Middleware\TrackVisits;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests that name a Host other than the site.
 *
 * A web server with no catch-all block hands every unknown Host to the first site it
 * serves. On the demo that was 7,300 analytics rows — research scanners sending
 * "Host: leonsbett.gr", bots hitting the bare IP — listed on Recent Visits as if the
 * site linked to those domains. The same Host feeds route(), so a password reset asked
 * for under "Host: evil.example" mailed a real token on the attacker's domain.
 */
class ForeignHostTest extends TestCase
{
    private const HUMAN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://example.com']);
    }

    private function visit(string $url): int
    {
        $before = Analytics::count();
        $request = Request::create($url, 'GET', [], [], [], ['HTTP_USER_AGENT' => self::HUMAN]);
        (new TrackVisits)->handle($request, fn () => new Response('ok', 200));

        return Analytics::count() - $before;
    }

    /** Make $url the current request, as the router would. */
    private function asRequest(string $url): void
    {
        $request = Request::create($url);
        $this->app->instance('request', $request);
        URL::setRequest($request);
    }

    public function test_the_sites_own_host_is_counted(): void
    {
        $this->assertSame(1, $this->visit('https://example.com/a-page'));
        $this->assertSame(1, $this->visit('https://www.example.com/a-page'), 'www is the same site');
    }

    public function test_a_spoofed_host_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('https://leonsbett.gr/'));
    }

    public function test_the_bare_ip_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('https://188.166.226.95/'));
    }

    public function test_extra_domains_can_be_allowed(): void
    {
        add_falcon_filter('falcon_site_hosts', fn (array $hosts) => [...$hosts, 'example.org']);

        $this->assertSame(1, $this->visit('https://example.org/a-page'));
    }

    public function test_a_local_app_url_compares_nothing(): void
    {
        // The Laravel default, left alone on many installs browsed at site.test.
        config(['app.url' => 'http://localhost']);

        $this->assertSame(1, $this->visit('http://lazy-panda.test/a-page'));
    }

    public function test_a_reset_link_asked_for_under_a_spoofed_host_points_at_the_site(): void
    {
        $this->asRequest('https://evil.example/admin/forgot-password');

        $url = falcon_with_site_root(fn () => route('admin.password.reset', ['token' => 'abc', 'email' => 'a@b.c']));

        $this->assertStringStartsWith('https://example.com/', $url);
        $this->assertSame('https://evil.example', url('/'), 'other URLs are left as they were');
    }

    public function test_a_signed_link_built_under_a_spoofed_host_verifies_on_the_site(): void
    {
        $this->asRequest('https://evil.example/admin/register');

        $signed = falcon_with_site_root(fn () => URL::temporarySignedRoute('admin.verify.email', now()->addHour(), ['id' => 1, 'hash' => 'x']));

        $this->assertStringStartsWith('https://example.com/', $signed);
        $this->assertTrue(URL::hasValidSignature(Request::create($signed)));
    }

    public function test_a_forced_root_set_by_the_site_survives(): void
    {
        URL::forceRootUrl('https://cdn-root.example.com');
        $this->asRequest('https://evil.example/');

        falcon_with_site_root(fn () => route('admin.password.reset', ['token' => 'abc']));

        $this->assertSame('https://cdn-root.example.com', url('/'));
        URL::forceRootUrl(null);
    }
}
