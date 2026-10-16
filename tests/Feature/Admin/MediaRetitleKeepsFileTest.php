<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Models\Media;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retitling a media item changes its label, not its file.
 *
 * It used to rename the file after the title, moving it to a new URL while every page that
 * embedded the old one kept pointing there: on falconcms.com, retitling "dashboard-1.svg"
 * to "FalconCMS Dashboard" broke the hero image on four pages.
 */
class MediaRetitleKeepsFileTest extends TestCase
{
    use MakesShopFixtures;

    public function test_a_new_title_leaves_the_file_where_pages_point(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/2026/09/dashboard-1.svg', '<svg/>');
        $media = Media::forceCreate([
            'filename' => 'dashboard-1.svg', 'path' => 'media/2026/09/dashboard-1.svg',
            'mime_type' => 'image/svg+xml', 'title' => 'dashboard-1', 'original_size' => 6, 'compressed_size' => 6,
        ]);
        $admin = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $this->actingAs($admin)
            ->putJson('/admin/media/'.$media->id, ['title' => 'FalconCMS Dashboard', 'alt_text' => 'FalconCMS dashboard interface'])
            ->assertOk()
            ->assertJsonPath('data.path', 'media/2026/09/dashboard-1.svg');

        $media->refresh();
        $this->assertSame('FalconCMS Dashboard', $media->title);
        $this->assertSame('FalconCMS dashboard interface', $media->alt_text);
        $this->assertSame('dashboard-1.svg', $media->filename);
        Storage::disk('public')->assertExists('media/2026/09/dashboard-1.svg');
        Storage::disk('public')->assertMissing('media/2026/09/falconcms-dashboard.svg');
    }
}
