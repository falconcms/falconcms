<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Media;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the CMS will accept onto its own disk.
 *
 * An upload screen is the shortest path from "someone can log in" to "someone can run
 * code on the server", so the rules here are worth stating explicitly rather than
 * inferring from the implementation. The library is also shared: a file one editor
 * uploads is embedded in pages every visitor loads, which is why SVG is refused
 * alongside the obvious executables — it is a document format that can carry script.
 */
class MediaUploadTest extends TestCase
{
    use MakesShopFixtures;

    private function admin(): User
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->admin())->post('/admin/media', ['file' => $file]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ---- what must be refused --------------------------------------------------

    #[DataProvider('dangerousExtensions')]
    public function test_an_executable_extension_is_refused(string $extension): void
    {
        $response = $this->upload(
            UploadedFile::fake()->createWithContent("payload.{$extension}", '<?php echo 1;')
        );

        $this->assertSame(422, $response->getStatusCode(), ".{$extension} was accepted");
        $this->assertSame(0, Media::count(), ".{$extension} reached the library");
    }

    public static function dangerousExtensions(): array
    {
        return array_map(fn ($e) => [$e], [
            'php', 'php5', 'phtml', 'phar', 'asp', 'aspx', 'jsp',
            'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'htaccess',
        ]);
    }

    /**
     * SVG is off by default. It is a document, not a picture — it can carry script, and it
     * is served from the site's own origin once it is in the library — so a site has to
     * turn it on under Allowed Upload Formats before anything will accept it.
     */
    public function test_an_svg_is_refused_unless_the_site_allows_it(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $response = $this->upload(UploadedFile::fake()->createWithContent('logo.svg', $svg));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Media::count());
    }

    /**
     * With SVG allowed, the file is kept — but what lands on disk is the sanitised markup,
     * not the bytes that were uploaded. Allowing the format is a decision about file types,
     * not permission for one of them to carry script.
     */
    public function test_an_allowed_svg_is_stored_sanitised(): void
    {
        $this->setCmsOptions(['performance_allowed_formats' => json_encode(['png', 'svg'])]);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
            .'<script>alert(2)</script>'
            .'<a href="javascript:alert(3)"><path d="M0 0h10v10H0z"/></a>'
            .'</svg>';

        $response = $this->upload(UploadedFile::fake()->createWithContent('logo.svg', $svg));

        $this->assertSame(200, $response->getStatusCode(), 'an allowed SVG was still refused');
        $this->assertSame(1, Media::count());

        $stored = Storage::disk('public')->get(Media::first()->path);

        $this->assertStringNotContainsString('<script', $stored, 'a script survived into the library');
        $this->assertStringNotContainsString('onload', $stored, 'an event handler survived into the library');
        $this->assertStringNotContainsString('javascript:', $stored, 'a javascript: link survived into the library');
        $this->assertStringContainsString('<path', $stored, 'the drawing itself was thrown away');
    }

    /**
     * Something that is not markup at all must not be kept just because it is named .svg.
     */
    public function test_an_allowed_svg_that_is_not_markup_is_refused(): void
    {
        $this->setCmsOptions(['performance_allowed_formats' => json_encode(['svg'])]);

        $response = $this->upload(
            UploadedFile::fake()->createWithContent('payload.svg', '<?php echo 1;')
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Media::count());
    }

    public function test_the_extension_check_is_not_case_sensitive(): void
    {
        $response = $this->upload(
            UploadedFile::fake()->createWithContent('payload.PhP', '<?php echo 1;')
        );

        $this->assertSame(422, $response->getStatusCode(), 'a capitalised extension slipped through');
        $this->assertSame(0, Media::count());
    }

    /**
     * "shell.php.jpg" must never come to rest with an extension the server would execute.
     * The name is slugged and the image may be converted, so what is asserted is the only
     * thing that actually matters: the extension the file is finally stored under.
     */
    public function test_a_double_extension_lands_under_a_harmless_one(): void
    {
        $this->upload(UploadedFile::fake()->image('shell.php.jpg'));

        $media = Media::first();
        $this->assertNotNull($media);

        $extension = strtolower(pathinfo($media->filename, PATHINFO_EXTENSION));

        $this->assertContains($extension, ['jpg', 'jpeg', 'webp'],
            "stored as .{$extension}");
        $this->assertStringEndsWith($extension, $media->path);
    }

    // ---- what must still work --------------------------------------------------

    /**
     * The other half: a fix that refused everything would satisfy every test above and
     * make the CMS useless.
     */
    public function test_an_ordinary_image_is_accepted_and_recorded(): void
    {
        $response = $this->upload(UploadedFile::fake()->image('holiday.jpg', 400, 300));

        $this->assertSame(200, $response->getStatusCode());

        $media = Media::first();
        $this->assertNotNull($media, 'a plain JPEG was rejected');
        $this->assertSame('holiday', $media->title);
        $this->assertNotNull($media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_a_pdf_is_accepted(): void
    {
        $this->upload(UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'));

        $this->assertSame(1, Media::count(), 'a PDF was rejected');
    }

    public function test_the_allowed_format_list_narrows_what_is_accepted(): void
    {
        $this->setCmsOptions(['performance_allowed_formats' => json_encode(['png'])]);

        $this->assertSame(422, $this->upload(UploadedFile::fake()->image('a.jpg'))->getStatusCode());
        $this->assertSame(0, Media::count());

        $this->upload(UploadedFile::fake()->image('b.png'));
        $this->assertSame(1, Media::count(), 'the format on the allowed list was rejected');
    }

    /** An upload is attributed, so the library can say who put a file there. */
    public function test_an_upload_records_who_made_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/media', ['file' => UploadedFile::fake()->image('x.jpg')]);

        $this->assertSame($admin->id, (int) Media::first()->user_id);
    }
}
