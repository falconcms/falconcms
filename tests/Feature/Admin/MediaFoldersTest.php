<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Models\Media;
use FalconCms\Core\Models\MediaFolder;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Folders in the media library group files without moving them: a file keeps its address
 * whichever folder it is in, a folder can be renamed any time, and deleting one never deletes
 * a file.
 */
class MediaFoldersTest extends TestCase
{
    use MakesShopFixtures;

    private function admin()
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function file(string $name, ?int $folder = null): Media
    {
        return Media::forceCreate([
            'filename' => $name, 'path' => 'media/2026/10/'.$name, 'mime_type' => 'image/png',
            'original_size' => 10, 'compressed_size' => 10, 'folder_id' => $folder,
        ]);
    }

    public function test_folders_are_made_renamed_and_nested(): void
    {
        $this->actingAs($this->admin());

        $products = $this->postJson('/admin/media/folders', ['name' => '  Products  '])->assertOk()->json('folder');
        $this->assertSame('Products', $products['name']);
        $shoes = $this->postJson('/admin/media/folders', ['name' => 'Shoes', 'parent_id' => $products['id']])->assertOk()->json('folder');

        // The same name twice in one place is refused, in any case; elsewhere it is fine.
        $this->postJson('/admin/media/folders', ['name' => 'products'])->assertStatus(422);
        $this->postJson('/admin/media/folders', ['name' => 'Shoes'])->assertOk();

        $this->putJson('/admin/media/folders/'.$products['id'], ['name' => 'Shop products'])->assertOk();
        $this->assertSame('Shop products', MediaFolder::find($products['id'])->name);

        // A folder cannot go inside itself or one of its own subfolders.
        $this->putJson('/admin/media/folders/'.$products['id'], ['parent_id' => $shoes['id']])->assertStatus(422);
        $this->putJson('/admin/media/folders/'.$products['id'], ['parent_id' => $products['id']])->assertStatus(422);

        $tree = $this->getJson('/admin/media/folders')->assertOk()->json('folders');
        $this->assertSame(['Shoes', 'Shop products', 'Shoes'], array_column($tree, 'name'));
        $this->assertSame([0, 0, 1], array_column($tree, 'depth'));
    }

    public function test_a_subfolder_becomes_a_main_folder_and_back(): void
    {
        $this->actingAs($this->admin());
        $top = MediaFolder::create(['name' => 'Products']);
        $sub = MediaFolder::create(['name' => 'Summer', 'parent_id' => $top->id]);
        $other = MediaFolder::create(['name' => 'Campaigns']);

        // To the top level: a main folder.
        $this->putJson('/admin/media/folders/'.$sub->id, ['parent_id' => null])->assertOk();
        $this->assertNull($sub->fresh()->parent_id);

        // And into another folder; its name stays.
        $this->putJson('/admin/media/folders/'.$sub->id, ['parent_id' => $other->id])->assertOk();
        $this->assertSame($other->id, $sub->fresh()->parent_id);
        $this->assertSame('Summer', $sub->fresh()->name);

        // Not where a folder of that name already is.
        MediaFolder::create(['name' => 'Summer']);
        $this->putJson('/admin/media/folders/'.$sub->id, ['parent_id' => null])->assertStatus(422);
    }

    public function test_files_move_between_folders_and_keep_their_address(): void
    {
        $this->actingAs($this->admin());
        $folder = MediaFolder::create(['name' => 'Banners']);
        $a = $this->file('a.png');
        $b = $this->file('b.png');

        $this->postJson('/admin/media/move', ['ids' => [$a->id, $b->id], 'folder_id' => $folder->id])->assertOk()->assertJsonPath('moved', 2);
        $this->assertSame($folder->id, $a->fresh()->folder_id);
        $this->assertSame('media/2026/10/a.png', $a->fresh()->path);

        // The library lists a folder's files, Uncategorized, or everything.
        $this->getJson('/admin/media?folder='.$folder->id)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/admin/media?folder=none')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/admin/media/move', ['ids' => [$b->id], 'folder_id' => null])->assertOk();
        $this->getJson('/admin/media?folder=none')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/admin/media')->assertOk()->assertJsonCount(2, 'data');

        $this->postJson('/admin/media/move', ['ids' => [$a->id], 'folder_id' => 99999])->assertStatus(422);
    }

    public function test_deleting_a_folder_keeps_its_files_and_subfolders(): void
    {
        $this->actingAs($this->admin());
        $top = MediaFolder::create(['name' => 'Top']);
        $mid = MediaFolder::create(['name' => 'Mid', 'parent_id' => $top->id]);
        $low = MediaFolder::create(['name' => 'Low', 'parent_id' => $mid->id]);
        $file = $this->file('kept.png', $mid->id);

        $this->deleteJson('/admin/media/folders/'.$mid->id)->assertOk();

        $this->assertNull(MediaFolder::find($mid->id));
        $this->assertNotNull($file->fresh(), 'the file is not deleted');
        $this->assertNull($file->fresh()->folder_id, 'it is Uncategorized');
        $this->assertSame($top->id, $low->fresh()->parent_id, 'the subfolder moves up');
    }

    public function test_an_upload_goes_into_the_folder_it_was_made_from(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $folder = MediaFolder::create(['name' => 'Uploads here']);

        $this->post('/admin/media', ['file' => UploadedFile::fake()->create('notes.pdf', 4, 'application/pdf'), 'folder_id' => $folder->id], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertSame($folder->id, Media::query()->latest('id')->value('folder_id'));

        $this->post('/admin/media', ['file' => UploadedFile::fake()->create('more.pdf', 4, 'application/pdf'), 'folder_id' => 99999], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNull(Media::query()->latest('id')->value('folder_id'), 'a folder that does not exist means none');
    }

    /**
     * Right after an update the new code can run before its migration (or a site updated with
     * composer alone never ran it). Uploads and the media window must keep working then.
     */
    public function test_the_library_works_before_the_folders_migration_has_run(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        // The migration's own rollback takes the site back to before it.
        $migration = require __DIR__.'/../../../database/migrations/2026_10_29_000001_create_cms_media_folders_table.php';
        $migration->down();
        $this->assertFalse(Schema::hasTable('cms_media_folders'));
        $this->assertFalse(Schema::hasColumn('media', 'folder_id'));
        MediaFolder::flushReady();

        try {
            $this->post('/admin/media', ['file' => UploadedFile::fake()->create('early.pdf', 4, 'application/pdf'), 'folder_id' => 5], ['Accept' => 'application/json'])
                ->assertOk();
            $this->getJson('/admin/media?folder=5')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson('/admin/media/folders')->assertOk()->assertJsonPath('folders', [])->assertJsonPath('uncategorized', 1);
            $this->get('/admin/media/upload')->assertOk()->assertSee('Upload to');

            // And running it again brings folders back, without touching the files.
            $migration->up();
            MediaFolder::flushReady();
            $this->assertTrue(MediaFolder::ready());
            $this->getJson('/admin/media/folders')->assertOk()->assertJsonPath('uncategorized', 1);
        } finally {
            MediaFolder::flushReady();
        }
    }

    public function test_the_upload_page_offers_the_folders(): void
    {
        $this->actingAs($this->admin());
        $folder = MediaFolder::create(['name' => 'Team photos']);

        // (The library page itself groups by month with MySQL's DATE_FORMAT, which the SQLite
        // test database lacks; its folder panel is checked in the browser.)
        $this->get('/admin/media/upload?folder='.$folder->id)->assertOk()->assertSee('Upload to')->assertSee('Team photos');
    }
}
