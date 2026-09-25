<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An image sized per screen.
 *
 * Width and Max Width used to be one value for every screen, which is fine until a logo set
 * to 180px sits in a column that is a quarter of a desktop and the whole width of a phone.
 * They now take a value per device, cascading mobile -> tablet -> desktop like every other
 * responsive setting in the builder.
 *
 * Two things here are easy to get wrong and invisible when you do. The desktop size is
 * written inline, and an inline style outranks any media query, so the per-device rules only
 * work because they are !important and land on a class the element actually carries — and
 * the image is one of four different shapes depending on whether it has a link, a ratio or a
 * lightbox. And a size and its unit have to be read for the same device: a mobile width of
 * 100 beside a desktop unit of px is 100px, which is not what anyone typing 100% meant.
 */
class ImageResponsiveWidthTest extends TestCase
{
    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.image', ['el' => [
            'id' => 'e1', 'type' => 'image', 'settings' => $settings,
        ]])->render();
    }

    /** The class the media queries target, as generated for this one render. */
    private function elemClass(string $html): string
    {
        $this->assertMatchesRegularExpression('/image-el-[a-z0-9-]+/', $html,
            'nothing in the markup carries the class the responsive rules are written against');
        preg_match('/image-el-[a-z0-9-]+/', $html, $m);

        return $m[0];
    }

    public function test_an_image_without_per_device_sizes_is_untouched(): void
    {
        // Existing images must render exactly as they did: one inline width, no media query.
        $html = $this->render(['url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px']);

        $this->assertStringContainsString('width:180px', $html);
        $this->assertStringNotContainsString('@media', $html);
    }

    public function test_a_mobile_width_becomes_a_rule_in_the_mobile_band(): void
    {
        $html = $this->render([
            'url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px',
            'width_mobile' => 100, 'widthUnit_mobile' => '%',
        ]);
        $cls = $this->elemClass($html);

        $this->assertStringContainsString('@media(max-width:800px){.'.$cls.'{width:100%!important}}', $html);
        $this->assertStringContainsString('width:180px', $html, 'the desktop size was lost');
    }

    public function test_a_tablet_width_becomes_a_rule_in_the_tablet_band(): void
    {
        $html = $this->render([
            'url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px',
            'width_tablet' => 120, 'widthUnit_tablet' => 'px',
        ]);
        $cls = $this->elemClass($html);

        $this->assertStringContainsString(
            '@media(min-width:801px) and (max-width:1100px){.'.$cls.'{width:120px!important}}', $html);
    }

    public function test_mobile_falls_back_to_the_tablet_value(): void
    {
        // Setting tablet alone means "smaller than desktop from here down", which is what the
        // cascade everywhere else in the builder means.
        $html = $this->render([
            'url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px',
            'width_tablet' => 120, 'widthUnit_tablet' => 'px',
        ]);
        $cls = $this->elemClass($html);

        $this->assertStringContainsString('@media(max-width:800px){.'.$cls.'{width:120px!important}}', $html);
    }

    public function test_the_unit_is_read_for_the_same_device_as_the_value(): void
    {
        // The trap: 100 on mobile with px on desktop is not 100px.
        $html = $this->render([
            'url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px',
            'width_mobile' => 100, 'widthUnit_mobile' => '%',
        ]);

        $this->assertStringContainsString('width:100%!important', $html);
        $this->assertStringNotContainsString('width:100px!important', $html);
    }

    public function test_max_width_is_per_device_too(): void
    {
        $html = $this->render([
            'url' => '/a.jpg', 'maxWidth' => 60, 'maxWidthUnit' => '%',
            'maxWidth_mobile' => 100, 'maxWidthUnit_mobile' => '%',
        ]);
        $cls = $this->elemClass($html);

        $this->assertStringContainsString('@media(max-width:800px){.'.$cls.'{max-width:100%!important}}', $html);
    }

    #[DataProvider('shapes')]
    public function test_every_shape_of_image_carries_the_class(array $extra, string $note): void
    {
        $html = $this->render($extra + [
            'url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px',
            'width_mobile' => 100, 'widthUnit_mobile' => '%',
        ]);
        $cls = $this->elemClass($html);

        // The class must sit on the same tag as the inline width, or the rule targets one
        // element and the size it is overriding lives on another.
        $this->assertMatchesRegularExpression(
            '/<(?:img|a|div)[^>]*'.preg_quote($cls, '/').'[^>]*style="[^"]*width:180px/',
            $html, "the class and the inline width parted company on: {$note}");
    }

    public static function shapes(): array
    {
        return [
            'plain image' => [[], 'plain image'],
            'linked image' => [['linkUrl' => '/somewhere'], 'linked image'],
            'aspect ratio' => [['aspectRatio' => '16/9'], 'aspect ratio'],
            'lightbox' => [['lightbox' => true], 'lightbox'],
        ];
    }

    public function test_the_bands_follow_the_customizer_screen_sizes(): void
    {
        // The same two settings the builder canvas sizes itself from. Change them and the
        // front end has to move with them, or the preview and the page stop agreeing.
        update_cms_option('theme_small_screen_breakpoint', '389');
        update_cms_option('theme_medium_screen_breakpoint', '900');
        forget_cms_options_cache();

        $html = $this->render([
            'url' => '/a.jpg', 'width_mobile' => 100, 'widthUnit_mobile' => '%',
            'width_tablet' => 120, 'widthUnit_tablet' => 'px',
        ]);
        $cls = $this->elemClass($html);

        $this->assertStringContainsString('@media(max-width:389px){.'.$cls.'{width:100%!important}}', $html);
        $this->assertStringContainsString(
            '@media(min-width:390px) and (max-width:900px){.'.$cls.'{width:120px!important}}', $html);
    }

    public function test_a_sticky_width_still_wins_while_the_column_is_stuck(): void
    {
        // Sticky is a state, not a screen, so it has to outrank the per-device sizes.
        $html = $this->render([
            'url' => '/a.jpg', 'width' => 180, 'widthUnit' => 'px',
            'width_mobile' => 100, 'widthUnit_mobile' => '%',
            'stickyWidth' => 60, 'stickyWidthUnit' => 'px',
        ]);

        $this->assertStringContainsString('.lazy-sticky-active .image-wrap-', $html);
        $this->assertStringContainsString('width:60px!important', $html);
    }
}
