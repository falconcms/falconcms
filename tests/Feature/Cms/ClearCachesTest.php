<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Clearing the saved copies from Customizer → Performance.
 *
 * A Customizer save writes the row and then drops the settings cache. When that drop fails —
 * usually a file under storage/framework/cache left behind by a command run as root, which
 * the web server user can no longer delete — the row is right and every page on the site
 * keeps reading the old value. The setting saves, reports success, and does nothing, and
 * there is no sign of it anywhere a person would look.
 *
 * Hence the part of this worth testing is not that the button clears caches. It is that the
 * button refuses to say it did when it did not: a green "Cleared!" over a cache that is still
 * there is worse than having no button, because it rules out the real cause.
 */
class ClearCachesTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'cache-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function clearCaches(): TestResponse
    {
        return $this->actingAs($this->administrator())
            ->postJson('/admin/customizer/action/clearCaches');
    }

    public function test_it_drops_the_settings_cache(): void
    {
        Cache::put('falcon:cms_options', ['theme_site_width' => 'stale'], 600);

        $this->clearCaches()->assertOk()->assertJsonPath('success', true);

        $this->assertFalse(Cache::has('falcon:cms_options'),
            'the settings cache survived, so saved settings keep reading their old values');
    }

    public function test_a_saved_setting_is_readable_immediately_afterwards(): void
    {
        // The end someone actually cares about: change a value, clear, read the new one.
        update_cms_option('theme_site_width', '1240px');
        forget_cms_options_cache();
        $this->assertSame('1240px', get_cms_option('theme_site_width'));

        DB::table('cms_settings')->updateOrInsert(['key' => 'theme_site_width'], ['value' => '980px']);
        $this->clearCaches()->assertOk();

        $this->assertSame('980px', get_cms_option('theme_site_width'));
    }

    public function test_it_names_what_it_cleared(): void
    {
        // The message is the only thing shown, so it has to say more than "done".
        $message = $this->clearCaches()->json('message');

        $this->assertStringContainsString('saved settings', $message);
        $this->assertStringContainsString('compiled templates', $message);
    }

    public function test_it_reports_failure_rather_than_claiming_success(): void
    {
        // The whole point. A cache entry that cannot be dropped must not come back green.
        Cache::shouldReceive('forget')->andReturn(false);
        Cache::shouldReceive('has')->andReturn(true);

        $response = $this->clearCaches();

        $response->assertOk()->assertJsonPath('success', false);
        $this->assertStringContainsString('Could not clear', $response->json('message'));
        $this->assertStringContainsString('saved settings', $response->json('message'));
    }

    public function test_a_failure_says_where_to_look(): void
    {
        // Someone reading this has a setting that will not stick and no idea why. The reply
        // has to name the folder and the cause, or it is just a red box.
        Cache::shouldReceive('forget')->andReturn(false);
        Cache::shouldReceive('has')->andReturn(true);

        $message = $this->clearCaches()->json('message');

        $this->assertStringContainsString('storage/framework/cache', $message);
        $this->assertStringContainsString('chown', $message);
    }

    public function test_it_is_behind_the_settings_permission(): void
    {
        $subscriber = User::forceCreate([
            'name' => 'Sub', 'email' => 'cache-sub@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);

        $this->actingAs($subscriber)
            ->postJson('/admin/customizer/action/clearCaches')
            ->assertForbidden();
    }

    public function test_the_button_is_offered_in_performance_and_stores_nothing(): void
    {
        // action_button is in the Customizer's list of types that carry no value. If it ever
        // stopped being one, saving the Performance section would write a settings row named
        // after a button — and, because the save writes every field of every section, would
        // do it on every save from anywhere.
        $html = $this->actingAs($this->administrator())
            ->get('/admin/customizer')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Clear Caches Now', $html);

        $this->clearCaches()->assertOk();
        $this->assertDatabaseMissing('cms_settings', ['key' => 'clear_caches']);
    }
}
