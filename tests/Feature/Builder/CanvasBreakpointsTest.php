<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * One pair of numbers for the canvas and the page.
 *
 * Small Screen and Medium Screen are what the front end builds its media queries from, so
 * the builder previews mobile at Small Screen and tablet at Medium Screen. Both sides then
 * read the same two settings and cannot disagree about where a screen stops being a phone.
 *
 * The builder reads them once, when its page renders. Someone changing them does it in the
 * Customizer — another screen, usually another tab — and comes back to a builder still
 * sizing its canvas to the old value, with no sign that anything is stale. This endpoint is
 * what it re-reads on the way back in.
 */
class CanvasBreakpointsTest extends TestCase
{
    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'canvas-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    public function test_it_reports_the_screen_sizes_the_front_end_uses(): void
    {
        update_cms_option('theme_small_screen_breakpoint', '389');
        update_cms_option('theme_medium_screen_breakpoint', '900');
        forget_cms_options_cache();

        $this->actingAs($this->administrator())
            ->getJson('/admin/falcon-builder-sections/breakpoints')
            ->assertOk()
            ->assertExactJson(['small' => 389, 'medium' => 900]);
    }

    public function test_it_follows_a_change_rather_than_repeating_the_first_answer(): void
    {
        // The whole point: an open builder asks again and gets the new value.
        $admin = $this->administrator();

        update_cms_option('theme_small_screen_breakpoint', '800');
        forget_cms_options_cache();
        $this->actingAs($admin)->getJson('/admin/falcon-builder-sections/breakpoints')
            ->assertJsonPath('small', 800);

        update_cms_option('theme_small_screen_breakpoint', '420');
        forget_cms_options_cache();
        $this->actingAs($admin)->getJson('/admin/falcon-builder-sections/breakpoints')
            ->assertJsonPath('small', 420);
    }

    public function test_the_numbers_come_back_as_numbers(): void
    {
        // They are arithmetic on the other side — a canvas width, and the desktop floor one
        // pixel above Medium Screen. A string would concatenate instead of adding.
        update_cms_option('theme_medium_screen_breakpoint', '1100');
        forget_cms_options_cache();

        $medium = $this->actingAs($this->administrator())
            ->getJson('/admin/falcon-builder-sections/breakpoints')
            ->json('medium');

        $this->assertIsInt($medium);
    }

    public function test_it_falls_back_to_the_defaults_when_nothing_is_saved(): void
    {
        DB::table('cms_settings')->whereIn('key', [
            'theme_small_screen_breakpoint', 'theme_medium_screen_breakpoint',
        ])->delete();
        forget_cms_options_cache();

        $this->actingAs($this->administrator())
            ->getJson('/admin/falcon-builder-sections/breakpoints')
            ->assertExactJson(['small' => 800, 'medium' => 1100]);
    }

    public function test_it_is_behind_the_same_permission_as_the_rest_of_the_builder(): void
    {
        $subscriber = User::forceCreate([
            'name' => 'Sub', 'email' => 'canvas-sub@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);

        $this->actingAs($subscriber)
            ->getJson('/admin/falcon-builder-sections/breakpoints')
            ->assertForbidden();
    }
}
