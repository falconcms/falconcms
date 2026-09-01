<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\ActivityLog;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The activity log switch, and what automatic removal deletes.
 *
 * Two things here are worth guarding. Switching logging off has to stop rows being
 * written, not merely hide the screen — a setting that looks off while the table
 * keeps growing is worse than no setting. And a retention window decides what gets
 * permanently deleted, so the boundary it draws has to be the one the admin picked,
 * in the timezone they picked it in.
 */
class ActivityLogSettingsTest extends TestCase
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

    private function logAt(string $utc): void
    {
        DB::table('activity_logs')->insert([
            'action' => 'test',
            'description' => 'entry',
            'created_at' => Carbon::parse($utc, 'UTC')->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::parse($utc, 'UTC')->format('Y-m-d H:i:s'),
        ]);
    }

    // ---- the switch --------------------------------------------------------------

    public function test_logging_is_on_by_default(): void
    {
        $this->assertTrue(falcon_activity_log_enabled());

        falcon_log_activity('test', 'something happened');

        $this->assertSame(1, ActivityLog::count());
    }

    public function test_switching_it_off_writes_no_rows(): void
    {
        $this->setCmsOptions(['activity_log_enabled' => '0']);

        falcon_log_activity('test', 'something happened');

        $this->assertSame(0, ActivityLog::count(), 'Off must mean nothing is recorded, not merely nothing shown.');
    }

    public function test_the_log_screen_is_closed_while_it_is_off(): void
    {
        $this->setCmsOptions(['activity_log_enabled' => '0']);

        $this->actingAs($this->administrator())
            ->get('/admin/settings/activity-logs')
            ->assertRedirect(route('admin.settings.index'));
    }

    public function test_the_log_screen_opens_while_it_is_on(): void
    {
        $this->actingAs($this->administrator())
            ->get('/admin/settings/activity-logs')
            ->assertOk();
    }

    public function test_existing_entries_survive_being_switched_off(): void
    {
        falcon_log_activity('test', 'recorded while on');
        $this->setCmsOptions(['activity_log_enabled' => '0']);

        $this->assertSame(1, ActivityLog::count(), 'Switching off stops new entries; it does not erase old ones.');
    }

    // ---- the settings screen -------------------------------------------------------

    public function test_the_tab_is_listed_while_logging_is_on(): void
    {
        $this->actingAs($this->administrator())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Activity Logs')
            ->assertSee('Is Activity Log on?');
    }

    public function test_the_tab_is_not_listed_while_logging_is_off(): void
    {
        $this->setCmsOptions(['activity_log_enabled' => '0']);

        $this->actingAs($this->administrator())
            ->get('/admin/settings')
            ->assertOk()
            ->assertDontSee(route('admin.settings.activity-logs'))
            ->assertSee('Is Activity Log on?');
    }

    public function test_a_save_from_another_settings_tab_leaves_the_switch_alone(): void
    {
        // The SEO screen posts to the same action without these checkboxes. Reading
        // them as "unticked" there would switch logging off behind the admin's back.
        $this->actingAs($this->administrator())
            ->post('/admin/settings', ['site_title' => 'Still On'])
            ->assertRedirect();

        $this->assertTrue(falcon_activity_log_enabled());
    }

    public function test_the_general_form_saves_a_custom_window(): void
    {
        $this->setCmsOptions(['timezone' => 'Asia/Dhaka']);

        $this->actingAs($this->administrator())->post('/admin/settings', [
            'activity_log_form' => '1',
            'activity_log_enabled' => '1',
            'activity_log_autoprune' => '1',
            'activity_log_retention' => 'custom',
            'activity_log_prune_before' => '2026-09-01T22:00',
        ])->assertRedirect();

        // Stored as the wall-clock time that was typed, read back as 22:00 in Dhaka.
        $this->assertSame('2026-09-01 22:00:00', get_cms_option('activity_log_prune_before'));
        $this->assertSame('2026-09-01 16:00:00', falcon_activity_log_cutoff()->format('Y-m-d H:i:s'));
    }

    // ---- what automatic removal deletes -------------------------------------------

    public function test_nothing_is_deleted_while_automatic_removal_is_off(): void
    {
        $this->setCmsOptions(['activity_log_autoprune' => '0']);

        $this->assertNull(falcon_activity_log_cutoff());
    }

    public function test_a_preset_window_cuts_at_that_many_hours_ago(): void
    {
        $this->setCmsOptions([
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '48',
        ]);

        $cutoff = falcon_activity_log_cutoff();

        $this->assertNotNull($cutoff);
        $this->assertEqualsWithDelta(48 * 3600, Carbon::now('UTC')->diffInSeconds($cutoff, true), 5);
    }

    public function test_an_unrecognised_window_keeps_more_history_not_less(): void
    {
        $this->setCmsOptions([
            'activity_log_autoprune' => '1',
            'activity_log_retention' => 'nonsense',
        ]);

        $this->assertEqualsWithDelta(
            72 * 3600,
            Carbon::now('UTC')->diffInSeconds(falcon_activity_log_cutoff(), true),
            5,
            'A bad stored value must not shorten the window.'
        );
    }

    public function test_a_custom_moment_is_read_in_the_site_timezone(): void
    {
        $this->setCmsOptions([
            'timezone' => 'Asia/Dhaka',
            'activity_log_autoprune' => '1',
            'activity_log_retention' => 'custom',
            'activity_log_prune_before' => '2026-09-01 10:00:00',
        ]);

        // 10:00 in Dhaka is 04:00 UTC — not 10:00 UTC.
        $this->assertSame('2026-09-01 04:00:00', falcon_activity_log_cutoff()->format('Y-m-d H:i:s'));
    }

    public function test_the_same_custom_moment_moves_with_the_timezone(): void
    {
        $this->setCmsOptions([
            'activity_log_autoprune' => '1',
            'activity_log_retention' => 'custom',
            'activity_log_prune_before' => '2026-09-01 10:00:00',
            'timezone' => 'UTC',
        ]);

        $this->assertSame('2026-09-01 10:00:00', falcon_activity_log_cutoff()->format('Y-m-d H:i:s'));
    }

    public function test_a_blank_custom_moment_deletes_nothing(): void
    {
        $this->setCmsOptions([
            'activity_log_autoprune' => '1',
            'activity_log_retention' => 'custom',
            'activity_log_prune_before' => '',
        ]);

        $this->assertNull(falcon_activity_log_cutoff(), 'An empty cutoff must not be read as the beginning of time.');
    }

    // ---- the command ---------------------------------------------------------------

    public function test_the_command_deletes_only_what_is_older_than_the_cutoff(): void
    {
        $this->setCmsOptions([
            'timezone' => 'Asia/Dhaka',
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '24',
        ]);

        $this->logAt(Carbon::now('UTC')->subHours(30)->toDateTimeString());  // gone
        $this->logAt(Carbon::now('UTC')->subHours(25)->toDateTimeString());  // gone
        $this->logAt(Carbon::now('UTC')->subHours(23)->toDateTimeString());  // kept
        $this->logAt(Carbon::now('UTC')->subHour()->toDateTimeString());     // kept

        $this->artisan('falcon:prune-activity-logs')->assertSuccessful();

        $this->assertSame(2, ActivityLog::count());
    }

    public function test_saving_the_window_applies_it_straight_away(): void
    {
        $this->logAt(Carbon::now('UTC')->subHours(30)->toDateTimeString());
        $this->logAt(Carbon::now('UTC')->subHour()->toDateTimeString());

        $this->actingAs($this->administrator())->post('/admin/settings', [
            'activity_log_form' => '1',
            'activity_log_enabled' => '1',
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '24',
        ])->assertRedirect();

        // Counted by action: saving settings records an entry of its own, which is
        // inside the window and correctly stays.
        $this->assertSame(1, ActivityLog::where('action', 'test')->count(),
            'Saving a window must not wait for the next sweep.');
    }

    public function test_saving_says_what_it_removed(): void
    {
        $this->logAt(Carbon::now('UTC')->subHours(30)->toDateTimeString());

        $this->actingAs($this->administrator())->post('/admin/settings', [
            'activity_log_form' => '1',
            'activity_log_enabled' => '1',
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '24',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Removed 1 activity log entry'));
    }

    public function test_saving_says_so_even_when_nothing_was_old_enough(): void
    {
        // The case that reads as "it is broken": a window is set, nothing happens,
        // because nothing was old enough yet. Saying so is the whole point.
        $this->logAt(Carbon::now('UTC')->subHour()->toDateTimeString());

        $this->actingAs($this->administrator())->post('/admin/settings', [
            'activity_log_form' => '1',
            'activity_log_enabled' => '1',
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '24',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'No activity log entries were older than'));

        $this->assertSame(1, ActivityLog::where('action', 'test')->count());
    }

    public function test_the_hourly_fallback_does_not_burn_its_lock_on_nothing(): void
    {
        // The lock used to be claimed before checking whether there was anything to
        // do. A visit arriving while removal was off spent the hour on nothing, and
        // switching it on a minute later removed nothing until that hour was up —
        // which is indistinguishable from the feature not working.
        $this->setCmsOptions(['activity_log_autoprune' => '0']);
        $this->logAt(Carbon::now('UTC')->subHours(30)->toDateTimeString());

        falcon_prune_activity_logs_throttled();

        $this->assertFalse(
            Cache::has('falcon_activity_log_prune_lock'),
            'Nothing to prune must not consume the window for something that is.'
        );

        // Switched on a moment later — the very next visit has to act on it.
        $this->setCmsOptions([
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '24',
        ]);

        $this->assertSame(1, falcon_prune_activity_logs_throttled());
        $this->assertSame(0, ActivityLog::where('action', 'test')->count());
    }

    public function test_the_hourly_fallback_runs_only_once_an_hour(): void
    {
        $this->setCmsOptions([
            'activity_log_autoprune' => '1',
            'activity_log_retention' => '24',
        ]);

        $this->logAt(Carbon::now('UTC')->subHours(30)->toDateTimeString());
        $this->assertSame(1, falcon_prune_activity_logs_throttled());

        $this->logAt(Carbon::now('UTC')->subHours(30)->toDateTimeString());
        $this->assertSame(0, falcon_prune_activity_logs_throttled(), 'The lock holds for the hour.');
    }

    public function test_the_command_deletes_nothing_while_automatic_removal_is_off(): void
    {
        $this->logAt(Carbon::now('UTC')->subYear()->toDateTimeString());

        $this->artisan('falcon:prune-activity-logs')->assertSuccessful();

        $this->assertSame(1, ActivityLog::count(), 'Old is not the same as unwanted.');
    }
}
