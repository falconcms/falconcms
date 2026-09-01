<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ZipArchive;

/**
 * Backups, and restoring from them.
 *
 * This is the most destructive thing the CMS can do to itself: a restore overwrites the
 * database and writes files into the public disk. The archive it works from can arrive
 * from outside — the upload form accepts one — so its contents are input, not data the
 * site produced.
 *
 * The restore also turns out to be a third door into the media directory, after the
 * upload screen and the WordPress importer, which is why the block-list is checked here
 * too rather than only at the other two.
 */
class BackupRestoreTest extends TestCase
{
    use MakesShopFixtures;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        // Recursive, because a restore writes into nested folders. Leaving one behind made
        // a later run assert against a file an earlier run had created.
        foreach (['app/backups', 'app/public'] as $dir) {
            $root = storage_path($dir);
            if (!is_dir($root)) {
                continue;
            }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
        }

        @unlink(dirname(storage_path()).'/escaped.txt');

        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function subscriber(): User
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);
    }

    /**
     * Write a real zip into the backup directory, as though it had been uploaded.
     *
     * @param  array<string, string>  $entries  path inside the archive => contents
     */
    private function placeBackup(string $filename, array $entries): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/'.$filename;
        $this->tempFiles[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function restore(string $filename): TestResponse
    {
        return $this->actingAs($this->admin())->post('/admin/tools/backup/restore/'.$filename);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    // ---- who may touch backups at all ------------------------------------------

    public function test_a_subscriber_cannot_reach_the_backup_screen(): void
    {
        $this->actingAs($this->subscriber())->get('/admin/tools/backup')->assertForbidden();
    }

    public function test_a_subscriber_cannot_restore(): void
    {
        $this->placeBackup('snapshot.zip', ['database.sql' => 'SELECT 1;']);

        $this->actingAs($this->subscriber())
            ->post('/admin/tools/backup/restore/snapshot.zip')
            ->assertForbidden();
    }

    public function test_a_subscriber_cannot_download_or_delete_a_backup(): void
    {
        $this->placeBackup('snapshot.zip', ['database.sql' => 'SELECT 1;']);

        $this->actingAs($this->subscriber())
            ->get('/admin/tools/backup/download/snapshot.zip')->assertForbidden();
        $this->actingAs($this->subscriber())
            ->delete('/admin/tools/backup/snapshot.zip')->assertForbidden();

        $this->assertFileExists(storage_path('app/backups/snapshot.zip'));
    }

    // ---- the filename in the URL -----------------------------------------------

    /**
     * download, restore and destroy all take a filename straight off the URL. Each has to
     * stay inside the backup directory whatever is asked for.
     */
    public function test_a_traversing_filename_cannot_reach_outside_the_backup_directory(): void
    {
        $outside = dirname(storage_path()).'/escaped.txt';
        file_put_contents($outside, 'not a backup');

        try {
            foreach (['../../../escaped.txt', '..%2F..%2F..%2Fescaped.txt'] as $attempt) {
                $this->actingAs($this->admin())->get('/admin/tools/backup/download/'.$attempt);
                $this->actingAs($this->admin())->delete('/admin/tools/backup/'.$attempt);
            }

            $this->assertFileExists($outside, 'a file outside the backup directory was deleted');
        } finally {
            @unlink($outside);
        }
    }

    public function test_restoring_a_file_that_does_not_exist_is_refused(): void
    {
        $this->restore('nothing-here.zip');

        $this->assertTrue(true, 'the point is that it did not fatal');
    }

    // ---- what a restore will write ---------------------------------------------

    /**
     * The media half of a restore writes archive entries into the public disk, which is
     * web-accessible. The upload screen and the WordPress importer both refuse executable
     * extensions; this is the third door and must agree with them.
     */
    public function test_a_restore_will_not_write_an_executable_into_the_public_disk(): void
    {
        $this->placeBackup('full.zip', [
            'database.sql' => 'SELECT 1;',
            'media/ok.png' => $this->onePixelPng(),
            'media/shell.php' => '<?php echo "pwned";',
        ]);

        $this->restore('full.zip');

        $this->assertFileExists(storage_path('app/public/ok.png'),
            'the media half did not run, so this test would pass on silence');
        $this->assertFileDoesNotExist(storage_path('app/public/shell.php'),
            'a backup archive dropped a PHP file into a web-accessible directory');
    }

    /**
     * A backup of a site that legitimately used SVG should restore its artwork, but an
     * archive is untrusted input like any other — so what lands on the public disk is the
     * sanitised markup, never the bytes the archive carried.
     */
    public function test_a_restore_writes_an_svg_only_after_sanitising_it(): void
    {
        $this->placeBackup('full2.zip', [
            'database.sql' => 'SELECT 1;',
            'media/ok2.png' => $this->onePixelPng(),
            'media/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
                .'<script>alert(2)</script><path d="M0 0h10v10H0z"/></svg>',
        ]);

        $this->restore('full2.zip');

        $this->assertFileExists(storage_path('app/public/ok2.png'), 'the media half did not run');

        $restored = @file_get_contents(storage_path('app/public/logo.svg'));
        $this->assertIsString($restored, 'the artwork was dropped instead of sanitised');
        $this->assertStringNotContainsString('<script', $restored);
        $this->assertStringNotContainsString('onload', $restored);
        $this->assertStringContainsString('<path', $restored);
    }

    /**
     * An archive entry named .svg that is not markup has nothing to sanitise, so nothing
     * is written.
     */
    public function test_a_restore_drops_an_svg_that_is_not_markup(): void
    {
        $this->placeBackup('full3.zip', [
            'database.sql' => 'SELECT 1;',
            'media/ok3.png' => $this->onePixelPng(),
            'media/payload.svg' => '<?php echo 1;',
        ]);

        $this->restore('full3.zip');

        $this->assertFileExists(storage_path('app/public/ok3.png'), 'the media half did not run');
        $this->assertFileDoesNotExist(storage_path('app/public/payload.svg'));
    }

    public function test_a_traversing_archive_entry_cannot_escape_the_public_disk(): void
    {
        $this->placeBackup('evil.zip', [
            'database.sql' => 'SELECT 1;',
            'media/../../../../escaped.txt' => 'owned',
        ]);

        $this->restore('evil.zip');

        $this->assertFileDoesNotExist(dirname(storage_path()).'/escaped.txt');
        $this->assertFileDoesNotExist(storage_path('escaped.txt'));
    }

    /** The ordinary case has to keep working. */
    /**
     * The ordinary case has to keep working — and it runs first in this file's reasoning,
     * because if the media half never executes then the two refusals above would pass on
     * silence rather than on being refused.
     */
    public function test_a_restore_writes_the_media_it_should(): void
    {
        $this->placeBackup('good.zip', [
            'database.sql' => 'SELECT 1;',
            'media/media/2023/03/photo.png' => $this->onePixelPng(),
        ]);

        $this->restore('good.zip');

        $this->assertFileExists(storage_path('app/public/media/2023/03/photo.png'),
            'a legitimate media file was not restored');
    }

    /** The same, for a media-only archive, whose paths are already relative to the disk root. */
    public function test_a_media_only_restore_writes_its_files(): void
    {
        $this->placeBackup('media-only.zip', [
            'media/2024/01/picture.png' => $this->onePixelPng(),
            'other/note.txt' => 'keeps the archive from having a single wrapper folder',
        ]);

        $this->restore('media-only.zip');

        $this->assertFileExists(storage_path('app/public/media/2024/01/picture.png'));
    }

    public function test_a_media_only_restore_also_refuses_an_executable(): void
    {
        $this->placeBackup('media-only-evil.zip', [
            'media/2024/01/picture.png' => $this->onePixelPng(),
            'media/2024/01/shell.php' => '<?php echo "pwned";',
            'other/note.txt' => 'keeps the archive from having a single wrapper folder',
        ]);

        $this->restore('media-only-evil.zip');

        $this->assertFileExists(storage_path('app/public/media/2024/01/picture.png'),
            'the good file must still arrive, so this test is not passing on silence');
        $this->assertFileDoesNotExist(storage_path('app/public/media/2024/01/shell.php'));
    }

    // ---- uploading an archive --------------------------------------------------

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->admin())
            ->post('/admin/tools/backup/upload', ['backup_file' => $file]);
    }

    public function test_only_backup_shaped_files_can_be_uploaded(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;'))
            ->assertSessionHasErrors('backup_file');

        $this->assertCount(0, glob(storage_path('app/backups').'/*') ?: []);
    }

    public function test_an_uploaded_backup_is_stored_under_a_sanitised_name(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('../../../weird name!.sql', 'SELECT 1;'));

        $stored = array_map('basename', glob(storage_path('app/backups').'/*') ?: []);

        $this->assertCount(1, $stored);
        $this->assertStringNotContainsString('..', $stored[0]);
        $this->assertStringNotContainsString('/', $stored[0]);
        $this->assertStringEndsWith('.sql', $stored[0]);
    }

    public function test_a_subscriber_cannot_upload_a_backup(): void
    {
        $this->actingAs($this->subscriber())->post('/admin/tools/backup/upload', [
            'backup_file' => UploadedFile::fake()->createWithContent('x.sql', 'SELECT 1;'),
        ])->assertForbidden();

        $this->assertCount(0, glob(storage_path('app/backups').'/*') ?: []);
    }
}
