<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * The image shown where a post or product has none. It used to be assets/images/placeholder.jpg,
 * which no release ever shipped (every imageless product card was a 404), and an image on
 * via.placeholder.com, which no longer answers. It is an inline SVG now: nothing to publish,
 * nothing to request.
 */
class PlaceholderImageTest extends TestCase
{
    public function test_it_is_an_inline_svg(): void
    {
        $src = falcon_placeholder_image();

        $this->assertStringStartsWith('data:image/svg+xml;charset=utf-8,', $src);
        $this->assertStringContainsString('<svg', rawurldecode($src));
        // it lands inside HTML attributes and single-quoted JavaScript strings
        $this->assertDoesNotMatchRegularExpression('/[\'"<>\s]/', $src);
    }

    public function test_a_site_can_use_its_own_image(): void
    {
        add_falcon_filter('falcon_placeholder_image', fn () => '/images/no-photo.png');

        $this->assertSame('/images/no-photo.png', falcon_placeholder_image());
        $this->assertSame('/images/no-photo.png', get_falcon_image_url(''));
    }

    public function test_an_empty_image_path_gets_the_placeholder_unless_told_otherwise(): void
    {
        $this->assertSame(falcon_placeholder_image(), get_falcon_image_url(null));
        $this->assertSame('/fallback.png', get_falcon_image_url('', '/fallback.png'));
    }

    public function test_no_template_still_points_at_the_missing_jpg(): void
    {
        $root = __DIR__.'/../../..';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/resources/views', \FilesystemIterator::SKIP_DOTS));
        $plugin = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/plugins', \FilesystemIterator::SKIP_DOTS));

        foreach ([$it, $plugin] as $files) {
            foreach ($files as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    $this->assertStringNotContainsString('placeholder.jpg', (string) file_get_contents($file->getPathname()), $file->getPathname());
                }
            }
        }
    }
}
