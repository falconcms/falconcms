<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Which day a visit is counted on.
 *
 * Visits are stored in UTC, but the admin reads them in the timezone chosen in
 * Settings → General. Getting that wrong is quiet: nothing errors, the totals still
 * look plausible, and the only symptom is that the evening's traffic appears on
 * tomorrow — which is invisible unless you know what the number should have been.
 *
 * Asia/Dhaka (UTC+6, no DST) is used throughout: far enough from UTC that a whole
 * evening falls on the wrong side of a server midnight.
 */
class AnalyticsTimezoneTest extends TestCase
{
    private ?User $admin = null;

    /** One administrator per test — some of them load the page more than once. */
    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** A visit recorded at an exact UTC instant. */
    private function visitAt(string $utc, string $ip = '203.0.113.9'): void
    {
        DB::table('cms_analytics')->insert([
            'ip_address' => $ip,
            'url' => 'https://example.test/',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => Carbon::parse($utc, 'UTC')->format('Y-m-d H:i:s'),
        ]);
    }

    private function analytics()
    {
        $this->withProLicensed();

        return $this->actingAs($this->administrator())->get('/admin/analytics');
    }

    public function test_an_evening_visit_counts_on_the_local_day_not_the_servers(): void
    {
        $this->setCmsOptions(['timezone' => 'Asia/Dhaka']);

        $localToday = Carbon::now('Asia/Dhaka')->startOfDay();

        // 01:00 local is 19:00 UTC on the previous date — the row a server-timezone
        // count drops off today. 23:00 local lands on the same UTC date, so it was
        // never in question. Both are today where the site lives.
        $this->visitAt($localToday->copy()->addHours(1)->utc()->toDateTimeString());
        $this->visitAt($localToday->copy()->addHours(23)->utc()->toDateTimeString());

        // The hour before local midnight belongs to yesterday and must not be counted.
        $this->visitAt($localToday->copy()->subHour()->utc()->toDateTimeString());

        $response = $this->analytics();
        $response->assertOk();

        $this->assertSame(2, $response->viewData('today'));
    }

    public function test_the_same_rows_count_differently_once_the_timezone_changes(): void
    {
        // 19:00 UTC — yesterday evening for a UTC site, already today for Dhaka.
        $instant = Carbon::now('Asia/Dhaka')->startOfDay()->addHours(2)->utc();
        $this->visitAt($instant->toDateTimeString());

        $this->setCmsOptions(['timezone' => 'Asia/Dhaka']);
        $this->assertSame(1, $this->analytics()->viewData('today'), 'Dhaka: the visit happened today.');

        $this->setCmsOptions(['timezone' => 'UTC']);
        $this->assertSame(
            Carbon::now('UTC')->isSameDay($instant) ? 1 : 0,
            $this->analytics()->viewData('today'),
            'UTC: the same row is judged against a different midnight.'
        );
    }

    public function test_the_daily_chart_buckets_by_the_local_date(): void
    {
        $this->setCmsOptions(['timezone' => 'Asia/Dhaka']);

        $localToday = Carbon::now('Asia/Dhaka')->startOfDay();

        // Two visits in the local evening, which fall on the previous UTC date.
        $this->visitAt($localToday->copy()->addHours(1)->utc()->toDateTimeString(), '203.0.113.1');
        $this->visitAt($localToday->copy()->addHours(2)->utc()->toDateTimeString(), '203.0.113.2');

        $response = $this->analytics();
        $response->assertOk();

        // The chart runs oldest → newest, so today is the last column.
        $series = $response->viewData('visitsSeries');
        $this->assertSame(2, end($series), 'Both belong to the local day the chart labels as today.');
    }

    public function test_the_range_opens_on_seven_days(): void
    {
        $this->assertSame(7, $this->analytics()->viewData('range'));
    }
}
