<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * How often one person counts.
 *
 * Two opposite mistakes are possible and the page has made both. Counting rows turned
 * one reader into as many visitors as they had pages open. Counting distinct addresses
 * across the whole range fixed that but went too far the other way: someone who came
 * back on ten different days was still a single address, so a month looked no busier
 * than a day and returning readers were invisible.
 *
 * The rule is one visitor per day: within a day, however many times they come back, they
 * are one; the next day they count again, and from that second day on they are counted as
 * returning. Over Today the two rules agree, which is why the fault only ever showed on
 * the longer ranges.
 */
class AnalyticsVisitorWindowTest extends TestCase
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

    private function pageView(string $ip, string $path, Carbon $at): void
    {
        DB::table('cms_analytics')->insert([
            'ip_address' => $ip,
            'url' => 'https://example.test'.$path,
            'user_agent' => 'Mozilla/5.0',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'country' => 'Bangladesh',
            'country_code' => 'BD',
            'created_at' => $at->copy()->utc()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Midnight of a local day, in the site's own timezone.
     *
     * Days here are the site's (Settings → General), not UTC's, so a test that builds its
     * times in UTC can put 20:00 on the following local day and count one visitor as two —
     * which is exactly the sort of off-by-a-timezone this rule has to get right.
     */
    private function localDay(int $daysAgo = 0): Carbon
    {
        return cms_now()->subDays($daysAgo)->startOfDay();
    }

    private function analytics(int $range)
    {
        $this->withProLicensed();

        return $this->actingAs($this->administrator())->get('/admin/analytics?range='.$range);
    }

    public function test_coming_back_all_day_is_one_visitor(): void
    {
        foreach ([2, 6, 9, 14, 20] as $hour) {
            $this->pageView('203.0.113.9', '/', $this->localDay()->addHours($hour));
        }

        $response = $this->analytics(1);

        $this->assertSame(1, $response->viewData('uniqueVisitors'), 'five visits in a day is one person');
        $this->assertSame(5, $response->viewData('pageViews'), 'the reading itself is still five');
    }

    public function test_coming_back_the_next_day_counts_again(): void
    {
        $this->pageView('203.0.113.9', '/', $this->localDay(2)->addHours(9));
        $this->pageView('203.0.113.9', '/', $this->localDay(1)->addHours(9));
        $this->pageView('203.0.113.9', '/', $this->localDay()->addHours(9));

        $this->assertSame(1, $this->analytics(1)->viewData('uniqueVisitors'), 'today alone is one');
        $this->assertSame(
            3,
            $this->analytics(7)->viewData('uniqueVisitors'),
            'three days of visits from one person is three, not one'
        );
    }

    public function test_the_second_day_onwards_is_a_returning_visitor(): void
    {
        // One person on three days, and a second person here for the first time today.
        $this->pageView('203.0.113.9', '/', $this->localDay(2)->addHours(9));
        $this->pageView('203.0.113.9', '/', $this->localDay(1)->addHours(9));
        $this->pageView('203.0.113.9', '/', $this->localDay()->addHours(9));
        $this->pageView('198.51.100.4', '/', $this->localDay()->addHours(10));

        $response = $this->analytics(7);

        $this->assertSame(4, $response->viewData('uniqueVisitors'));
        $this->assertSame(2, $response->viewData('returningVisitors'), 'their second and third days');
        $this->assertSame(2, $response->viewData('newVisitors'), 'their first day, and the other person');
        $this->assertSame(
            $response->viewData('uniqueVisitors'),
            $response->viewData('newVisitors') + $response->viewData('returningVisitors'),
            'new and returning must add up to the headline'
        );
    }

    public function test_two_people_on_one_day_are_two(): void
    {
        $this->pageView('203.0.113.9', '/', $this->localDay()->addHours(9));
        $this->pageView('198.51.100.4', '/', $this->localDay()->addHours(9));

        $this->assertSame(2, $this->analytics(1)->viewData('uniqueVisitors'));
    }
}
