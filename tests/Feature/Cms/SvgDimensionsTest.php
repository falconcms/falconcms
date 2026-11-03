<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * An SVG in the media library has no pixel size for the upload to read, so the Image element
 * printed it without width and height and the page shifted as it loaded (falconcms.com: every
 * illustration on the home, Laravel CMS and page builder pages).
 */
class SvgDimensionsTest extends TestCase
{
    private function svg(string $name, string $root): string
    {
        $path = 'media/test/'.$name;
        File::ensureDirectoryExists(storage_path('app/public/media/test'));
        File::put(storage_path('app/public/'.$path), $root.'<rect width="10" height="10"/></svg>');
        DB::table('media')->insert(['filename' => $name, 'path' => $path, 'mime_type' => 'image/svg+xml', 'original_size' => 100, 'created_at' => now(), 'updated_at' => now()]);

        return $path;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/media/test'));
        parent::tearDown();
    }

    public function test_an_svgs_size_comes_from_its_attributes_or_its_view_box(): void
    {
        $sized = $this->svg('sized.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="640px" height="360">');
        $boxed = $this->svg('boxed.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800">');
        $relative = $this->svg('relative.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 300 150">');

        $this->assertSame([640, 360], [falcon_media_info('/storage/'.$sized)['width'], falcon_media_info('/storage/'.$sized)['height']]);
        $this->assertSame([1200, 800], [falcon_media_info('/storage/'.$boxed)['width'], falcon_media_info('/storage/'.$boxed)['height']]);
        $this->assertSame([300, 150], [falcon_media_info('/storage/'.$relative)['width'], falcon_media_info('/storage/'.$relative)['height']]);
    }

    public function test_the_image_element_prints_the_svgs_size(): void
    {
        $path = $this->svg('hero.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800">');

        $html = view('falcon-cms::frontend.builder.elements.image', ['el' => [
            'id' => 'e1', 'type' => 'image', 'settings' => ['url' => '/storage/'.$path, 'alt' => 'Dashboard'],
        ]])->render();

        $this->assertStringContainsString('width="1200" height="800"', $html);
    }
}
