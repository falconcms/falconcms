<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Middleware\TrackVisits;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Support\HumanVisits;
use FalconCms\Core\Support\PageTime;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Real visitors only.
 *
 * The server writes a row for every request it serves, and most are not people: on
 * falconcms.com nine rows in ten came from data-centre addresses with a stock Chrome user
 * agent. A row now counts only once the page's own script, in a visible tab, confirms it — and
 * a page served from the page cache, which never wrote a row at all, gets one from that
 * confirmation. These tests pin the server side of that; the script itself is checked in a real
 * browser (it waits for a person, skips WebDriver and prerendering).
 */
class HumanVisitTest extends TestCase
{
    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        // A real site address: with a local APP_URL every host is "the site" (falcon_is_site_host).
        config(['app.url' => 'https://example.com']);
    }

    private function serverHit(string $path = '/a-page', string $ip = '203.0.113.7'): void
    {
        $request = Request::create('https://example.com'.$path, 'GET', [], [], [], ['HTTP_USER_AGENT' => self::CHROME, 'REMOTE_ADDR' => $ip]);
        (new TrackVisits)->handle($request, fn () => new Response('ok', 200));
    }

    private function confirm(string $path = '/a-page', string $ip = '203.0.113.7', array $headers = [], string $ua = self::CHROME): string
    {
        $request = Request::create('/falcon/visit', 'POST', ['p' => $path, 'r' => 'https://www.google.com/'], [], [], array_merge([
            'HTTP_USER_AGENT' => $ua, 'REMOTE_ADDR' => $ip, 'HTTP_ORIGIN' => 'https://example.com',
        ], $headers));

        return HumanVisits::confirm($request);
    }

    public function test_a_request_alone_is_recorded_but_not_counted(): void
    {
        $this->assertTrue(Analytics::hasHumanColumn());
        $this->serverHit();

        $this->assertSame(1, DB::table('cms_analytics')->count(), 'the request is in the log');
        $this->assertSame(0, (int) DB::table('cms_analytics')->value('human'));
        $this->assertSame(0, Analytics::count(), 'but no figure counts it');
    }

    public function test_the_browser_confirmation_makes_it_count(): void
    {
        $this->serverHit('/a-page');

        $this->assertSame('confirmed', $this->confirm('/a-page/'));
        $this->assertSame(1, Analytics::count());
        $this->assertSame(1, DB::table('cms_analytics')->count(), 'the same row, not a second one');
    }

    public function test_a_cached_page_with_no_row_gets_one_from_the_confirmation(): void
    {
        $this->assertSame('created', $this->confirm('/pricing?utm_source=x'));

        $row = Analytics::first();
        $this->assertNotNull($row);
        $this->assertSame('/pricing', parse_url($row->url, PHP_URL_PATH));
        $this->assertSame('utm_source=x', parse_url($row->url, PHP_URL_QUERY));
        $this->assertSame('https://www.google.com/', $row->referrer);
        $this->assertSame('203.0.113.7', $row->ip_address);
    }

    public function test_a_retried_beacon_is_counted_once(): void
    {
        $this->assertSame('created', $this->confirm('/pricing'));
        $this->assertSame('duplicate', $this->confirm('/pricing'));
        $this->assertSame(1, Analytics::count());
    }

    public function test_only_the_matching_page_of_the_same_address_is_confirmed(): void
    {
        $this->serverHit('/a-page', '203.0.113.7');
        $this->serverHit('/other', '203.0.113.7');
        $this->serverHit('/a-page', '198.51.100.9');

        $this->confirm('/a-page', '203.0.113.7');

        $this->assertSame(1, Analytics::count());
        $this->assertSame('/a-page', parse_url(Analytics::first()->url, PHP_URL_PATH));
        $this->assertSame('203.0.113.7', Analytics::first()->ip_address);
    }

    public function test_bots_and_other_sites_cannot_confirm(): void
    {
        $this->serverHit();

        $this->assertSame('bot', $this->confirm('/a-page', '203.0.113.7', [], 'Mozilla/5.0 (compatible; Dataprovider.com)'));
        $this->assertSame('bot', $this->confirm('/a-page', '203.0.113.7', [], 'Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/130.0 Safari/537.36'));
        $this->assertSame('foreign', $this->confirm('/a-page', '203.0.113.7', ['HTTP_ORIGIN' => 'https://evil.example']));
        $this->assertSame('foreign', $this->confirm('/a-page', '203.0.113.7', ['HTTP_ORIGIN' => '']));
        $this->assertSame(0, Analytics::count());
    }

    public function test_the_beacon_route_answers_204(): void
    {
        $this->withHeaders(['Origin' => 'https://example.com', 'User-Agent' => self::CHROME])
            ->post('/falcon/visit', ['p' => '/a-page'])
            ->assertNoContent();
        $this->assertSame(1, Analytics::count());
    }

    public function test_page_time_is_proof_of_a_person_too(): void
    {
        $this->serverHit('/a-page');
        $row = DB::table('cms_analytics')->first();

        // What PageTime::record does for the visitor log, reached through its public entry.
        $ref = new \ReflectionMethod(PageTime::class, 'addToVisitorLog');
        $ref->setAccessible(true);
        $ref->invoke(null, '/a-page', 12, '203.0.113.7');

        $this->assertSame(1, (int) DB::table('cms_analytics')->where('id', $row->id)->value('human'));
        $this->assertSame(12, (int) DB::table('cms_analytics')->where('id', $row->id)->value('duration_seconds'));
    }

    public function test_the_migration_keeps_rows_the_page_time_script_reached_and_hides_the_rest(): void
    {
        // Rows from before the column: one a browser reported time for, one it never did.
        $migration = require __DIR__.'/../../../database/migrations/2026_11_10_000001_add_human_flag_to_cms_analytics.php';
        $migration->down();
        DB::table('cms_analytics')->insert([
            ['ip_address' => '1.1.1.1', 'url' => url('/x'), 'duration_seconds' => 30, 'created_at' => now()],
            ['ip_address' => '2.2.2.2', 'url' => url('/y'), 'duration_seconds' => null, 'created_at' => now()],
        ]);
        $migration->up();
        app()->forgetInstance('falcon.memo.analytics_human_column');

        $this->assertSame(['1.1.1.1'], Analytics::pluck('ip_address')->all());
        $this->assertSame(2, DB::table('cms_analytics')->count(), 'nothing deleted');
    }

    public function test_public_pages_carry_the_script_and_the_dashboard_explains_it(): void
    {
        $html = HumanVisits::script('/falcon/visit');
        $this->assertStringContainsString('navigator', $html);
        $this->assertStringContainsString('n.webdriver', $html);
        $this->assertStringContainsString('d.prerendering', $html);
        $this->assertStringContainsString((string) HumanVisits::DWELL_MS, $html);
    }
}
