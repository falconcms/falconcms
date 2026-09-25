<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Analytics over a range the reader chooses.
 *
 * The buttons used to be five fixed lengths, all of them ending today, and every query on the
 * page said `created_at >= $start` with nothing on the other end — which is the same thing as
 * a window only while the window runs to now. A range that ends in the past needs both ends
 * saying, and a single past day needs its empty hours read as zero rather than as "has not
 * happened yet".
 *
 * A year of days replaced by a calendar, because "1 year" is a chart nobody reads and a
 * question nobody asks in those words. The calendar only offers days the site has visits for:
 * a range drawn across nothing produces a page of zeroes that reads as a fault rather than as
 * an answer, and nobody can see from the outside where a site's history starts.
 *
 * Everything the reader can put in the URL is treated as a suggestion — dates that are not
 * dates, the wrong way round, or in the future are corrected rather than refused, because a
 * hand-edited URL or a year-old bookmark should still show a page.
 */
class AnalyticsCustomRangeTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'analytics-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** A visit on a given day, at midday so no timezone shifts it off that day. */
    private function visit(string $day, string $ip = '10.0.0.1', string $url = '/'): void
    {
        // The table keeps created_at only — there is no updated_at to set.
        DB::table('cms_analytics')->insert([
            'ip_address' => $ip,
            'url' => $url,
            'user_agent' => 'Mozilla/5.0',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'created_at' => cms_now()->parse($day.' 12:00:00', cms_now()->getTimezone())->utc(),
        ]);
    }

    private function page(array $query = []): string
    {
        $this->withProLicensed();

        return $this->actingAs($this->administrator())
            ->get('/admin/analytics'.($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->getContent();
    }

    public function test_the_year_button_is_gone_and_a_custom_one_took_its_place(): void
    {
        $this->visit(cms_now()->toDateString());
        $html = $this->page();

        $this->assertStringNotContainsString('>1 year<', $html);
        $this->assertStringContainsString('range-custom-btn', $html);
        $this->assertStringContainsString('Custom', $html);
    }

    public function test_only_days_with_visits_are_offered(): void
    {
        $a = cms_now()->subDays(5)->toDateString();
        $b = cms_now()->subDays(2)->toDateString();
        $this->visit($a);
        $this->visit($b);
        $this->visit($b, '10.0.0.2');

        $html = $this->page();

        // The calendar greys out every day that is not in this map, and shows the count.
        $this->assertMatchesRegularExpression('/data-days=\'[^\']*'.preg_quote($a, '/').'[^\']*\'/', $html);
        $this->assertMatchesRegularExpression('/"'.preg_quote($b, '/').'":2/', $html,
            'the busier day does not carry its count, so the calendar cannot show how busy it was');
        $this->assertStringNotContainsString(cms_now()->subDays(4)->toDateString().'"', $html,
            'a day with no visits at all is being offered');
    }

    public function test_a_custom_range_counts_only_that_range(): void
    {
        $inside = cms_now()->subDays(10)->toDateString();
        $outside = cms_now()->subDays(2)->toDateString();
        $this->visit($inside, '10.0.0.1', '/inside-the-window');
        $this->visit($outside, '10.0.0.2', '/outside-the-window');

        $html = $this->page(['from' => $inside, 'to' => $inside]);

        // The open-ended query would have swept up everything after the start date too.
        $this->assertStringContainsString('/inside-the-window', $html);
        $this->assertStringNotContainsString('/outside-the-window', $html,
            'a page from after the window is being counted, so the range has no end');
    }

    public function test_a_single_past_day_is_drawn_in_hours_with_no_gaps(): void
    {
        $day = cms_now()->subDays(3)->toDateString();
        $this->visit($day);

        $html = $this->page(['from' => $day, 'to' => $day]);

        // Today leaves the hours that have not happened as null so the line stops rather than
        // dropping to the floor. On a past day every hour has happened.
        $this->assertStringContainsString('"hour"', $html, 'a single day should be drawn by the hour');
        $this->assertStringContainsString('11 PM', $html, 'the day is not being drawn in hours');
        $this->assertStringNotContainsString('null', substr($html, (int) strpos($html, 'visitsSeries'), 400));
    }

    public function test_dates_the_wrong_way_round_are_read_the_way_round_that_works(): void
    {
        $early = cms_now()->subDays(6)->toDateString();
        $late = cms_now()->subDays(1)->toDateString();
        $this->visit($early);
        $this->visit($late);

        $html = $this->page(['from' => $late, 'to' => $early]);

        $this->assertStringContainsString('data-from="'.$early.'"', $html);
        $this->assertStringContainsString('data-to="'.$late.'"', $html);
    }

    public function test_a_range_that_runs_into_the_future_stops_at_today(): void
    {
        $this->visit(cms_now()->toDateString());

        $html = $this->page(['from' => cms_now()->subDays(3)->toDateString(), 'to' => cms_now()->addDays(30)->toDateString()]);

        $this->assertStringContainsString('data-to="'.cms_now()->toDateString().'"', $html,
            'the window runs past today, which only adds empty steps to the chart');
    }

    public function test_nonsense_dates_fall_back_to_a_preset_rather_than_failing(): void
    {
        $this->visit(cms_now()->toDateString());

        foreach ([
            ['from' => 'yesterday', 'to' => 'today'],
            ['from' => '2026-02-31', 'to' => '2026-03-01'],   // a date that does not exist
            ['from' => '', 'to' => ''],
            ['from' => '2026-01-01'],                          // only one half
        ] as $query) {
            $html = $this->page($query);
            $this->assertStringContainsString('range-custom-btn', $html,
                'the page fell over on '.json_encode($query));
        }
    }

    public function test_the_presets_still_work_and_still_end_today(): void
    {
        $this->visit(cms_now()->toDateString());
        $this->visit(cms_now()->subDays(3)->toDateString());

        $html = $this->page(['range' => 7]);

        $this->assertStringContainsString('data-from=""', $html, 'a preset should not look like a custom range');
        $this->assertStringContainsString('7 days', $html);
    }

    public function test_the_custom_button_is_dead_until_there_is_something_to_pick(): void
    {
        // No visits at all: a calendar in which every day is greyed out is a worse answer
        // than a button that says why it cannot help.
        $html = $this->page();

        $this->assertStringContainsString('There are no visits recorded yet', $html);
        $this->assertStringNotContainsString('id="range-popover"', $html);
    }

    public function test_the_window_is_capped_rather_than_left_to_the_url(): void
    {
        $this->visit(cms_now()->toDateString());

        $html = $this->page(['from' => '2000-01-01', 'to' => cms_now()->toDateString()]);

        // One point per day: twenty-six years of them is not a chart.
        $this->assertStringNotContainsString('data-from="2000-01-01"', $html);
    }
}
