<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The builder library, global sections and mega menus moved to falcon_* keys — with the work
 * that was stored under the old ones intact.
 *
 * These three rows are not preferences that can be re-set in a minute. They hold saved
 * sections, saved mega menus and a whole builder library, and a rename that dropped them would
 * look to the site owner exactly like the CMS deleting their work.
 */
class RenamedSettingKeysTest extends TestCase
{
    private function setOption(string $key, string $value): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        forget_cms_options_cache();
    }

    private function read(string $constant)
    {
        $method = new \ReflectionMethod(BuilderLibraryController::class, 'option');
        $method->setAccessible(true);

        return $method->invoke(null, $constant);
    }

    public function test_the_keys_no_longer_carry_the_old_brand(): void
    {
        $this->assertSame('falcon_builder_library', BuilderLibraryController::OPTION_KEY);
        $this->assertSame('falcon_global_sections', BuilderLibraryController::GLOBAL_SECTIONS_KEY);
        $this->assertSame('falcon_mega_menus', BuilderLibraryController::MEGA_MENUS_KEY);
    }

    public function test_a_value_still_under_the_old_key_is_found(): void
    {
        // The window between composer pulling the package down and migrations running.
        $this->setOption('lazy_global_sections', '["still here"]');

        $this->assertSame('["still here"]', $this->read(BuilderLibraryController::GLOBAL_SECTIONS_KEY));
    }

    public function test_the_new_key_wins_when_both_exist(): void
    {
        $this->setOption('lazy_mega_menus', '["old"]');
        $this->setOption('falcon_mega_menus', '["new"]');

        $this->assertSame('["new"]', $this->read(BuilderLibraryController::MEGA_MENUS_KEY));
    }

    public function test_the_migration_moves_the_rows_across(): void
    {
        DB::table('cms_settings')->whereIn('key', [
            'lazy_builder_library', 'falcon_builder_library',
        ])->delete();
        $this->setOption('lazy_builder_library', '{"containers":[]}');

        $this->runRenameMigration();

        $this->assertDatabaseHas('cms_settings', [
            'key' => 'falcon_builder_library',
            'value' => '{"containers":[]}',
        ]);
        $this->assertDatabaseMissing('cms_settings', ['key' => 'lazy_builder_library']);
    }

    public function test_the_migration_does_not_overwrite_a_value_already_under_the_new_key(): void
    {
        DB::table('cms_settings')->whereIn('key', [
            'lazy_mega_menus', 'falcon_mega_menus',
        ])->delete();
        $this->setOption('lazy_mega_menus', '["old"]');
        $this->setOption('falcon_mega_menus', '["new"]');

        $this->runRenameMigration();

        $this->assertSame(
            '["new"]',
            DB::table('cms_settings')->where('key', 'falcon_mega_menus')->value('value')
        );
    }

    private function runRenameMigration(): void
    {
        $migration = require __DIR__.'/../../../database/migrations/2026_09_16_000001_rename_lazy_settings_keys.php';
        $migration->up();
        forget_cms_options_cache();
    }
}
