<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Media;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ZipArchive;

/**
 * The WordPress media importer — the other door into the media library.
 *
 * It writes files straight to disk from a ZIP, which means it is a second implementation
 * of the same question the upload screen answers: what is this CMS willing to keep? Two
 * implementations of one rule is how rules drift, so these tests state the rule once and
 * hold both doors to it.
 */
class MediaImportTest extends TestCase
{
    use MakesShopFixtures;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        foreach (glob(storage_path('app/public/media/*/*/*')) ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /**
     * A real zip on disk — the importer opens the uploaded file with ZipArchive, so a
     * faked upload with no archive inside would not exercise anything.
     *
     * @param  array<string, string>  $entries  path inside the archive => contents
     */
    private function zipUpload(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'wpmedia').'.zip';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return new UploadedFile($path, 'media.zip', 'application/zip', null, true);
    }

    /** @param array<string, string> $entries */
    private function import(array $entries): TestResponse
    {
        return $this->actingAs($this->admin())
            ->post('/admin/tools/wp-import/media', ['wp_media_file' => $this->zipUpload($entries)]);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    // ---- the rule both doors must agree on -------------------------------------

    /**
     * The upload screen refuses SVG outright: it is a document that can carry script, and
     * once it is in the library it is served from the site's own origin and embedded in
     * pages every visitor loads. The importer wrote it to disk and recorded it.
     */
    public function test_an_svg_is_refused_by_the_importer_too(): void
    {
        $this->import([
            'wp-content/uploads/2023/03/payload.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        ]);

        $this->assertSame(0, Media::where('filename', 'like', '%.svg')->count(),
            'an SVG entered the library through the importer');
    }

    public function test_an_executable_entry_is_refused_by_the_importer(): void
    {
        $this->import([
            'wp-content/uploads/2023/03/shell.php' => '<?php echo 1;',
            'wp-content/uploads/2023/03/shell.phtml' => '<?php echo 1;',
        ]);

        $this->assertSame(0, Media::count(), 'an executable entered the library');
    }

    // ---- what must still work --------------------------------------------------

    public function test_an_ordinary_image_is_imported(): void
    {
        $this->import(['wp-content/uploads/2023/03/photo.png' => $this->onePixelPng()]);

        $media = Media::first();

        $this->assertNotNull($media, 'a plain PNG was rejected');
        $this->assertSame('media/2023/03/photo.png', $media->path);
    }

    public function test_the_year_and_month_come_from_the_archive_path(): void
    {
        $this->import(['uploads/2019/7/old.png' => $this->onePixelPng()]);

        $this->assertSame('media/2019/07/old.png', Media::first()?->path,
            'a single-digit month must be padded');
    }

    public function test_a_mixed_archive_imports_what_it_may_and_skips_the_rest(): void
    {
        $this->import([
            'wp-content/uploads/2023/03/good.png' => $this->onePixelPng(),
            'wp-content/uploads/2023/03/bad.php' => '<?php echo 1;',
            'wp-content/uploads/2023/03/also-bad.svg' => '<svg/>',
        ]);

        $this->assertSame(1, Media::count(), 'the whole archive should not be lost over one bad entry');
        $this->assertStringEndsWith('good.png', Media::first()->path);
    }

    // ---- the archive itself ----------------------------------------------------

    /** Entry names are attacker-supplied; nothing may be written outside the media tree. */
    public function test_a_traversing_entry_cannot_escape_the_media_directory(): void
    {
        $this->import(['../../../../escaped.png' => $this->onePixelPng()]);

        $this->assertFileDoesNotExist(storage_path('app/escaped.png'));
        $this->assertFileDoesNotExist(dirname(storage_path()).'/escaped.png');

        foreach (Media::all() as $media) {
            $this->assertStringStartsWith('media/', $media->path);
        }
    }

    public function test_only_a_zip_is_accepted(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/tools/wp-import/media', [
            'wp_media_file' => UploadedFile::fake()->image('not-an-archive.png'),
        ]);

        $response->assertSessionHasErrors('wp_media_file');
        $this->assertSame(0, Media::count());
    }

    // ---- the rule stays in one place -------------------------------------------

    /**
     * The guarantee behind this whole file: both doors read the same list, so neither can
     * drift again. A future contributor who reintroduces a private copy in either
     * controller trips this.
     */
    public function test_both_doors_read_the_same_block_list(): void
    {
        $blocked = falcon_blocked_upload_extensions();

        $this->assertContains('php', $blocked);
        $this->assertContains('svg', $blocked);

        foreach ([
            'src/Http/Controllers/Admin/MediaController.php',
            'src/Http/Controllers/Admin/WordPressImportController.php',
        ] as $file) {
            $source = file_get_contents(__DIR__.'/../../../'.$file);

            $this->assertStringContainsString('falcon_blocked_upload_extensions()', $source,
                "{$file} no longer consults the shared list");
            $this->assertStringNotContainsString("'phtml'", $source,
                "{$file} has grown its own copy of the block list again");
        }
    }

    /**
     * Extending the list is a supported thing for a site to do, and both doors have to
     * honour the extension because they both go through the same filter.
     */
    public function test_a_site_can_add_to_the_block_list(): void
    {
        add_falcon_filter('falcon_blocked_upload_extensions', fn (array $list) => [...$list, 'png']);

        $this->import(['wp-content/uploads/2023/03/photo.png' => $this->onePixelPng()]);

        $this->assertSame(0, Media::count(), 'the added extension was imported anyway');
    }
}
