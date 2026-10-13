<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Media;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\AvifImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Media → Generate AVIF copies: an AVIF copy for each library image that has none, in batches.
 * Originals and their addresses are never changed.
 */
class AvifGenerateTest extends TestCase
{
    private function user(string $role): User
    {
        return User::forceCreate(['name' => $role, 'email' => $role.'@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', $role)->value('id')]);
    }

    public function test_it_needs_the_settings_permission(): void
    {
        $this->actingAs($this->user('subscriber'))->postJson(route('admin.media.avif-generate'))->assertForbidden();
    }

    public function test_it_explains_why_it_cannot_run(): void
    {
        $this->setCmsOptions(['perf_avif' => '0']);

        $response = $this->actingAs($this->user('administrator'))->postJson(route('admin.media.avif-generate'))->assertStatus(422);

        $this->assertStringContainsString(AvifImages::supported() ? 'Serve AVIF Images' : 'cannot write AVIF', $response->json('message'));
    }

    /** Runs where PHP's GD can write AVIF (the servers); skipped on a dev box without it. */
    public function test_batches_make_copies_and_leave_the_originals_alone(): void
    {
        if (!AvifImages::supported()) {
            $this->markTestSkipped('GD on this machine cannot write AVIF.');
        }
        Storage::fake('public');
        $this->setCmsOptions(['perf_avif' => '1']);
        $img = imagecreatetruecolor(400, 300);
        imagefilledrectangle($img, 0, 0, 399, 299, imagecolorallocate($img, 200, 80, 40));
        ob_start();
        imagepng($img);
        Storage::disk('public')->put('media/photo.png', ob_get_clean());
        Media::forceCreate(['filename' => 'photo.png', 'path' => 'media/photo.png', 'mime_type' => 'image/png', 'original_size' => 1, 'compressed_size' => 1]);

        $this->actingAs($this->user('administrator'))->postJson(route('admin.media.avif-generate'), ['after_id' => 0])
            ->assertOk()->assertJsonPath('done', true)->assertJsonPath('total', 1);

        Storage::disk('public')->assertExists('media/photo.png');
        $this->assertSame('media/photo.png', Media::first()->path);
    }
}
