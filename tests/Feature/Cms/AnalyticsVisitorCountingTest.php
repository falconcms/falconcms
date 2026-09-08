<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What counts as a visitor on the analytics page.
 *
 * Every card here is fed from one row per page view, so anything that answers "how
 * many people" has to count distinct visitors rather than rows. Several did not:
 * Visitors by Country, Top Countries and Active Pages counted rows, and the Live
 * Visitors list showed the latest eight rows — so one person reading eight pages
 * appeared as eight visitors from eight countries. The figures were not slightly
 * off, they were multiplied by however much each person read, which is exactly the
 * number a site owner uses to judge whether anything is working.
 *
 * Visits are a different question and stay row-based: Top Pages, Recent Visits and
 * the traffic series are all "how much was read", and are meant to be.
 */
class AnalyticsVisitorCountingTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** One page view. */
    private function pageView(string $ip, string $path, string $country = 'Bangladesh', string $code = 'BD', ?string $at = null): void
    {
        DB::table('cms_analytics')->insert([
            'ip_address' => $ip,
            'url' => 'https://example.test'.$path,
            'user_agent' => 'Mozilla/5.0',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'country' => $country,
            'country_code' => $code,
            'created_at' => ($at ? Carbon::parse($at, 'UTC') : Carbon::now('UTC')->subMinute())->format('Y-m-d H:i:s'),
        ]);
    }

    private function analytics()
    {
        $this->withProLicensed();

        return $this->actingAs($this->administrator())->get('/admin/analytics');
    }

    /** One person, six pages. Every "people" figure must say one. */
    public function test_one_person_reading_many_pages_is_one_visitor(): void
    {
        foreach (['/', '/about', '/pricing', '/blog', '/contact', '/features'] as $path) {
            $this->pageView('203.0.113.9', $path);
        }

        $response = $this->analytics();
        $response->assertOk();

        $this->assertSame(1, $response->viewData('uniqueVisitors'), 'unique visitors');
        $this->assertSame(1, $response->viewData('totalVisits'), 'the headline figure is people');
        $this->assertSame(1, $response->viewData('today'), 'visitors today');
        $this->assertSame(1, $response->viewData('thisMonth'), 'visitors this month');
        $this->assertSame(6, $response->viewData('pageViews'), 'page views are still counted, and labelled as such');

        // Every breakdown answers "how many people", so all of them say one.
        $this->assertSame(1, (int) collect($response->viewData('browsers'))->firstWhere('label', 'Chrome')['count'], 'browsers');
        $this->assertSame(1, (int) collect($response->viewData('devices'))->firstWhere('label', 'desktop')['count'], 'devices');
        $this->assertSame(1, (int) collect($response->viewData('osDist'))->firstWhere('label', 'Windows')['count'], 'operating systems');
        $this->assertSame(1, (int) collect($response->viewData('channels'))->firstWhere('label', 'Direct')['count'], 'traffic channels');
        $this->assertSame(1, (int) collect($response->viewData('trafficSources'))->firstWhere('label', 'Direct')['count'], 'traffic sources');
        $this->assertSame(1, (int) $response->viewData('topPages')->first()->count, 'top pages counts people per page');

        $byCountry = collect($response->viewData('visitorsByCountry'));
        $this->assertSame(1, (int) $byCountry->firstWhere('code', 'BD')['visitors'], 'visitors by country');

        $topCountries = collect($response->viewData('topCountries'));
        $this->assertSame(1, (int) $topCountries->firstWhere('label', 'Bangladesh')['count'], 'top countries');
    }

    /** Distinct people are still counted separately. */
    public function test_separate_people_are_counted_separately(): void
    {
        $this->pageView('203.0.113.9', '/');
        $this->pageView('203.0.113.9', '/about');
        $this->pageView('198.51.100.4', '/', 'India', 'IN');
        $this->pageView('198.51.100.7', '/', 'India', 'IN');

        $response = $this->analytics();

        $this->assertSame(3, $response->viewData('uniqueVisitors'));

        $byCountry = collect($response->viewData('visitorsByCountry'))->keyBy('code');
        $this->assertSame(1, (int) $byCountry['BD']['visitors']);
        $this->assertSame(2, (int) $byCountry['IN']['visitors']);
    }

    /**
     * Active Pages lists the pages the people who are here now have read.
     *
     * It used to list only the single page each of them was on at that instant. That was
     * the fix for a real fault — one reader had been counted on all six pages at once, so
     * the table said six where the counter said one — but it answered a narrower question
     * than the card is for. Both halves matter, so both are asserted here: every page the
     * visitor opened is listed, and no row ever counts one person more than once.
     */
    public function test_active_pages_lists_every_page_the_visitor_has_read(): void
    {
        foreach (['/about', '/account', '/blog'] as $path) {
            $this->pageView('203.0.113.9', $path);
        }
        // Read twice — the same person on the same page is still one visitor there.
        $this->pageView('203.0.113.9', '/about');
        $this->pageView('203.0.113.9', '/pricing', 'Bangladesh', 'BD', Carbon::now('UTC')->toDateTimeString());

        $this->withProLicensed();
        $json = $this->actingAs($this->administrator())->getJson('/admin/analytics/realtime')->assertOk()->json();

        $this->assertSame(1, $json['active'], 'it is one person however many pages they read');

        $pages = collect($json['activePages']);
        $this->assertEqualsCanonicalizing(
            ['/about', '/account', '/blog', '/pricing'],
            $pages->pluck('path')->all(),
            'every page they opened should be listed'
        );
        $this->assertTrue(
            $pages->every(fn ($p) => (int) $p['count'] === 1),
            'each row counts people, so one reader is 1 on each page — never 2 for reading it twice'
        );
    }

    /**
     * The per-minute sparkline beside the real-time counter. It counted rows, so one
     * person opening four pages inside a minute drew a bar four times too tall — the
     * last place on the page that still turned one reader into several.
     */
    public function test_the_per_minute_graph_counts_people(): void
    {
        $minute = Carbon::now('UTC')->subMinutes(3)->startOfMinute();

        foreach (['/a', '/b', '/c', '/d'] as $path) {
            $this->pageView('203.0.113.9', $path, 'Bangladesh', 'BD', $minute->toDateTimeString());
        }
        $this->pageView('198.51.100.4', '/a', 'India', 'IN', $minute->toDateTimeString());

        $this->withProLicensed();
        $json = $this->actingAs($this->administrator())->getJson('/admin/analytics/realtime')->assertOk()->json();

        $this->assertSame(
            2,
            max($json['minutes']),
            'that minute held two people, not the five pages they opened between them'
        );
    }

    /** The real-time feed lists people, not page views. */
    public function test_the_live_feed_shows_each_visitor_once(): void
    {
        foreach (['/', '/about', '/pricing', '/blog'] as $path) {
            $this->pageView('203.0.113.9', $path);
        }
        $this->pageView('198.51.100.4', '/', 'India', 'IN');

        $this->withProLicensed();
        $json = $this->actingAs($this->administrator())
            ->getJson('/admin/analytics/realtime')
            ->assertOk()
            ->json();

        $this->assertSame(2, $json['active'], 'two people are active, not five page views');
        $this->assertCount(2, $json['recent'], 'the live list is one row per visitor');

        // Active Pages lists what those two people have read. The home page is the only
        // one both of them opened, so it is the only row counting two.
        $pages = collect($json['activePages'])->keyBy('path');
        $this->assertCount(4, $pages, 'four distinct pages were read between them');
        $this->assertSame(
            1,
            $pages->where('count', 2)->count(),
            'the home page is the only one both of them opened, so it is the only row of two'
        );
        $this->assertSame(1, (int) $pages['/about']['count'], 'only one of them read this');
        $this->assertTrue(
            $pages->every(fn ($p) => (int) $p['count'] <= $json['active']),
            'no page can hold more people than are active'
        );
    }
}
