<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Restoring another site's backup through Tools → Backup moves its links to this site. */
class BackupRestoreSiteUrlTest extends TestCase
{
    use MakesShopFixtures;

    public function test_a_restore_moves_the_old_sites_links(): void
    {
        (new MenuSeeder)->run();
        $user = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $menu = DB::table('navigation_menus')->insertGetId(['name' => 'Main', 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('navigation_menu_items')->insertGetId(['navigation_menu_id' => $menu, 'title' => 'Pricing',
            'url' => 'https://old-site.example/pricing', 'type' => 'custom', 'order' => 1, 'created_at' => now(), 'updated_at' => now()]);

        // A dump from the other site: its address in the header, and a statement to replay.
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = 'restore-url-'.uniqid().'.sql';
        file_put_contents("$dir/$file", "-- Falcon CMS Backup\n-- Database: other\n-- Site URL: https://old-site.example\n-- Date: now\n\n"
            ."UPDATE navigation_menu_items SET title = 'Pricing' WHERE id = {$item};\n");

        try {
            $this->actingAs($user)->from('/admin/tools/backup')->post("/admin/tools/backup/restore/{$file}")
                ->assertRedirect('/admin/tools/backup')
                ->assertSessionHas('success', fn ($m) => str_contains($m, 'Links moved from https://old-site.example to http://localhost (1 values)'));
        } finally {
            @unlink("$dir/$file");
        }

        $this->assertSame('http://localhost/pricing', DB::table('navigation_menu_items')->where('id', $item)->value('url'));

        // The page says so, so nobody sets about changing links by hand.
        $this->actingAs($user)->get('/admin/tools/backup')->assertOk()
            ->assertSee("changed to this site's address automatically", false);
    }
}
