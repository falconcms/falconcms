<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Http\Middleware\TrackVisits;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What reaches the analytics table.
 *
 * Every figure on the analytics page is a count of rows written here, so a row that
 * should not exist is not a cosmetic problem — it inflates visits, uniques, top pages
 * and the country map all at once, and there is nothing on the page that would reveal
 * where the extra traffic came from.
 *
 * The middleware is exercised directly rather than through a route: it is registered on
 * the whole `web` group and its decisions depend only on the request, the response status
 * and who is signed in, none of which need a rendered page.
 */
class VisitTrackingTest extends TestCase
{
    private const HUMAN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    private function someone(): User
    {
        return User::forceCreate([
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'password' => 'secret-password',
        ]);
    }

    /** Run one request through the middleware and return how many rows it left behind. */
    private function visit(string $path = '/a-page', string $userAgent = self::HUMAN, int $status = 200): int
    {
        $before = Analytics::count();

        $request = Request::create($path, 'GET', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);

        (new TrackVisits)->handle($request, fn () => new Response('ok', $status));

        return Analytics::count() - $before;
    }

    public function test_an_anonymous_visitor_is_counted(): void
    {
        $this->assertSame(1, $this->visit());
    }

    public function test_a_signed_in_visitor_is_not_counted(): void
    {
        $this->actingAs($this->someone());

        $this->assertSame(0, $this->visit(), 'A signed-in visitor must not appear in analytics.');
    }

    public function test_signing_out_starts_counting_again(): void
    {
        $this->actingAs($this->someone());
        $this->assertSame(0, $this->visit());

        auth()->logout();

        $this->assertSame(1, $this->visit(), 'Only the session, not the visitor, decides this.');
    }

    // ---- the rules that were already there stay in force ------------------------

    public function test_a_crawler_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('/a-page', 'Mozilla/5.0 (compatible; Googlebot/2.1)'));
    }

    public function test_a_request_with_no_user_agent_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('/a-page', ''));
    }

    public function test_the_admin_panel_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('/admin/posts'));
    }

    public function test_the_api_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('/api/posts'));
    }

    public function test_a_page_that_did_not_render_is_not_counted(): void
    {
        $this->assertSame(0, $this->visit('/missing', self::HUMAN, 404));
    }
}
