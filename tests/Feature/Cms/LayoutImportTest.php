<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\WordPressImporter;
use FalconCms\Core\Support\ExportManager;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Moving layouts from one site to another: Tools → Export here, Tools → Import there.
 *
 * The import recreates the header/footer/content sections the layouts point at, then remaps
 * every assignment onto the new ids. Recreating them is where it used to break with a raw SQL
 * error, and the reason is worth stating because the same shape has bitten this codebase
 * before: `posts` is unique on (slug, type, lang_code), and the lookup that decided whether a
 * section already existed asked only for slug and type, through the soft-delete scope.
 *
 * So a site that had ever deleted a header called "Header" carried a trashed row holding that
 * key. The lookup could not see it, the import inserted the same key, and the database
 * refused — the import died on a site whose only crime was having used the bin.
 */
class LayoutImportTest extends TestCase
{
    /** The export a site produces for its layouts, as XML. */
    private function exportedLayouts(): string
    {
        return ExportManager::generate(['layout']);
    }

    private function seedLayoutOnThisSite(): void
    {
        $header = Post::create([
            'title' => 'Header', 'slug' => 'header', 'type' => 'falcon_header',
            'status' => 'published', 'content' => '[{"id":"c1"}]', 'editor_type' => 'builder', 'lang_code' => 'en',
        ]);

        update_cms_option('falcon_layout_global', json_encode(['header' => ['id' => $header->id, 'active' => true]]));
        update_cms_option('falcon_layouts', json_encode([[
            'id' => 'lay_one', 'name' => 'Shop Layout', 'conditions' => [],
            'assignments' => ['header' => ['id' => $header->id, 'active' => true]],
        ]]));
    }

    /** Wipe what the export was made from, so the import has to recreate it. */
    private function clearLayoutsFromThisSite(): void
    {
        update_cms_option('falcon_layout_global', json_encode([]));
        update_cms_option('falcon_layouts', json_encode([]));
    }

    public function test_layouts_survive_an_export_and_import(): void
    {
        $this->seedLayoutOnThisSite();
        $xml = $this->exportedLayouts();

        $originalId = Post::where('type', 'falcon_header')->where('slug', 'header')->value('id');
        Post::where('type', 'falcon_header')->forceDelete();
        $this->clearLayoutsFromThisSite();

        (new WordPressImporter)->importFromXml($xml, ['lang' => 'en']);

        $header = Post::where('type', 'falcon_header')->where('slug', 'header')->first();
        $this->assertNotNull($header, 'the header section must be recreated');
        $this->assertNotSame($originalId, $header->id, 'it is a new row, so the assignments must be remapped');

        $global = json_decode((string) get_cms_option('falcon_layout_global'), true);
        $this->assertSame($header->id, $global['header']['id'] ?? null, 'the Global Layout must point at the new id');

        $layouts = json_decode((string) get_cms_option('falcon_layouts'), true);
        $this->assertSame('Shop Layout', $layouts[0]['name'] ?? null);
        $this->assertSame($header->id, $layouts[0]['assignments']['header']['id'] ?? null);
    }

    public function test_a_trashed_section_of_the_same_name_does_not_break_the_import(): void
    {
        // The reported failure: the target site had deleted a header once.
        $this->seedLayoutOnThisSite();
        $xml = $this->exportedLayouts();
        $this->clearLayoutsFromThisSite();

        $trashed = Post::where('type', 'falcon_header')->where('slug', 'header')->first();
        $trashed->delete();
        $this->assertSame(1, (int) DB::table('posts')->where('slug', 'header')->whereNotNull('deleted_at')->count());

        (new WordPressImporter)->importFromXml($xml, ['lang' => 'en']);

        $header = Post::where('type', 'falcon_header')->where('slug', 'header')->first();
        $this->assertNotNull($header, 'the trashed section should be restored and reused, not collided with');
        $this->assertTrue(
            DB::table('posts')->where('id', $header->id)->whereNull('deleted_at')->exists(),
            'the restored row must no longer be in the bin'
        );

        $global = json_decode((string) get_cms_option('falcon_layout_global'), true);
        $this->assertSame($header->id, $global['header']['id'] ?? null);

        $this->assertSame(
            1,
            (int) DB::table('posts')->where('slug', 'header')->where('type', 'falcon_header')->count(),
            'restoring must not leave a second row behind'
        );
    }

    public function test_a_section_of_the_same_name_in_another_language_is_left_alone(): void
    {
        // The unique key includes lang_code, and so must the lookup: a Bengali header called
        // "header" is a different section, not the one being imported.
        $this->seedLayoutOnThisSite();
        $xml = $this->exportedLayouts();
        Post::where('type', 'falcon_header')->forceDelete();
        $this->clearLayoutsFromThisSite();

        $other = Post::create([
            'title' => 'হেডার', 'slug' => 'header', 'type' => 'falcon_header',
            'status' => 'published', 'content' => 'bn', 'editor_type' => 'builder', 'lang_code' => 'bn',
        ]);

        (new WordPressImporter)->importFromXml($xml, ['lang' => 'en']);

        $this->assertSame('হেডার', $other->fresh()->title, 'the other language must not be overwritten');
        $this->assertNotNull(
            Post::where('type', 'falcon_header')->where('slug', 'header')->where('lang_code', 'en')->first(),
            'the English section must still be created alongside it'
        );
    }
}
