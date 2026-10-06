<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The image element takes what the media library knows about its picture: the size, so the
 * page keeps the space before the image loads and nothing below it jumps, and the alt text
 * when the element itself was given none.
 */
class ImageElementMediaTest extends TestCase
{
    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.image', [
            'el' => ['id' => 'img-test', 'settings' => $settings],
        ])->render();
    }

    private function media(string $path, ?int $w, ?int $h, ?string $alt = null): void
    {
        DB::table('media')->insert([
            'filename' => basename($path), 'path' => $path, 'mime_type' => 'image/webp', 'original_size' => 1000,
            'width' => $w, 'height' => $h, 'alt_text' => $alt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_library_image_carries_its_size(): void
    {
        $this->media('media/2026/10/logo.webp', 1672, 941);

        $html = $this->render(['url' => '/storage/media/2026/10/logo.webp']);

        $this->assertMatchesRegularExpression('/<img src="\/storage\/media\/2026\/10\/logo.webp" alt=""\s*width="1672" height="941"/', $html);
        $this->assertStringContainsString('height:auto', $html, 'CSS still sizes it; the attributes only give the shape');
    }

    public function test_an_empty_alt_is_filled_from_the_library_but_never_overrides_one_set_here(): void
    {
        $this->media('media/2026/10/logo.webp', 100, 50, 'FalconCMS');

        $this->assertStringContainsString('alt="FalconCMS"', $this->render(['url' => '/storage/media/2026/10/logo.webp']));
        $this->assertStringContainsString('alt="Our logo"', $this->render(['url' => '/storage/media/2026/10/logo.webp', 'alt' => 'Our logo']));
    }

    public function test_an_image_the_library_does_not_know_is_left_as_it_was(): void
    {
        $this->media('media/2026/09/icon.svg', null, null);

        $this->assertStringNotContainsString('width="', $this->render(['url' => '/storage/media/2026/09/icon.svg']));
        $this->assertStringNotContainsString('width="', $this->render(['url' => 'https://cdn.example.com/photo.jpg']));
    }
}
