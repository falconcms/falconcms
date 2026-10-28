<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Uninstalling a plugin keeps its data unless that was asked for: a plugin removed by mistake
 * (or put back by FalconCMS Pro, which re-copies its plugins) comes back with everything as it
 * was. From the dashboard, its data goes only when "delete its data" is ticked AND its slug is
 * typed; many at once never deletes data.
 */
class PluginUninstallKeepsDataTest extends TestCase
{
    private const SLUG = 'keep-data-probe';

    private const TABLE = 'keep_data_probe_rows';

    private function dir(): string
    {
        return (new PluginManager)->path().DIRECTORY_SEPARATOR.self::SLUG;
    }

    /** A site plugin with one migration that makes one table, activated (so the table exists). */
    private function installAndActivate(bool $seed = true): void
    {
        File::ensureDirectoryExists($this->dir().'/database/migrations');
        File::put($this->dir().'/plugin.json', json_encode(['name' => 'Keep Data Probe', 'slug' => self::SLUG, 'version' => '1.0.0', 'data' => 'its probe rows']));
        File::put($this->dir().'/database/migrations/2026_10_28_000000_create_keep_data_probe_rows.php', '<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create("'.self::TABLE.'", function (Blueprint $t) { $t->id(); $t->string("v"); }); }
    public function down(): void { Schema::dropIfExists("'.self::TABLE.'"); }
};');
        $this->assertTrue((new PluginManager)->activate(self::SLUG)['ok']);
        $this->app->forgetInstance(PluginManager::class); // the app's own copy finds it on the next request
        $this->assertTrue(Schema::hasTable(self::TABLE));
        if ($seed) {
            DB::table(self::TABLE)->insert(['v' => 'kept']);
        }
    }

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);
        File::deleteDirectory($this->dir());
        parent::tearDown();
    }

    public function test_uninstall_keeps_the_data_and_activating_again_brings_it_back(): void
    {
        $this->installAndActivate();

        $result = (new PluginManager)->uninstall(self::SLUG);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('data was kept', $result['message']);
        $this->assertDirectoryDoesNotExist($this->dir(), 'the files go');
        $this->assertSame('kept', DB::table(self::TABLE)->value('v'), 'the data stays');

        // put back (as Pro re-copies its plugins) and switched on: the same rows, nothing lost
        $this->installAndActivateAgain();
        $this->assertSame(['kept'], DB::table(self::TABLE)->pluck('v')->all());
    }

    /** Put back whole (as Pro re-copies its plugins) and switched on again, adding no rows. */
    private function installAndActivateAgain(): void
    {
        $this->installAndActivate(false);
    }

    public function test_uninstall_with_delete_data_removes_its_tables(): void
    {
        $this->installAndActivate();

        $result = (new PluginManager)->uninstall(self::SLUG, true, true);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('data deleted', $result['message']);
        $this->assertFalse(Schema::hasTable(self::TABLE));
    }

    public function test_the_dashboard_deletes_data_only_when_ticked_and_typed(): void
    {
        $this->installAndActivate();
        $admin = $this->admin();

        // ticked, but the slug not typed (or typed wrong): the data stays
        $this->actingAs($admin)->delete(route('admin.plugins.destroy', self::SLUG), ['delete_data' => '1', 'confirm_slug' => 'keep-data'])->assertRedirect();
        $this->assertTrue(Schema::hasTable(self::TABLE), 'a wrong slug never deletes data');

        $this->installAndActivateAgain();
        $this->actingAs($admin)->delete(route('admin.plugins.destroy', self::SLUG), ['delete_data' => '1', 'confirm_slug' => self::SLUG])->assertRedirect();
        $this->assertFalse(Schema::hasTable(self::TABLE), 'ticked and typed: the data goes');
    }

    public function test_bulk_uninstall_never_deletes_data(): void
    {
        $this->installAndActivate();

        $this->actingAs($this->admin())->post(route('admin.plugins.bulk'), ['action' => 'delete', 'slugs' => [self::SLUG], 'delete_data' => '1', 'confirm_slug' => self::SLUG])->assertRedirect();
        $this->assertDirectoryDoesNotExist($this->dir());
        $this->assertTrue(Schema::hasTable(self::TABLE));
    }

    public function test_the_list_shows_the_dialog_with_what_the_plugin_keeps(): void
    {
        $this->installAndActivate();
        (new PluginManager)->deactivate(self::SLUG);

        $html = $this->actingAs($this->admin())->get(route('admin.plugins.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="uninstall-dialog"', $html);
        $this->assertStringContainsString('data-slug="'.self::SLUG.'"', $html);
        $this->assertStringContainsString('data-keeps="its probe rows"', $html);
        $this->assertStringContainsString('Its data is kept', $html);
    }
}
