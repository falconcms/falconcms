<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * The Image element's border radius, one value per corner (as on a container).
 *
 * It had a single Border Radius field. Images saved with it must look the same: an empty
 * corner takes that single value.
 */
class ImageBorderRadiusTest extends TestCase
{
    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.image', ['el' => [
            'id' => 'e1', 'type' => 'image', 'settings' => $settings + ['url' => '/a.jpg'],
        ]])->render();
    }

    public function test_each_corner_gets_its_own_radius_and_unit(): void
    {
        $html = $this->render([
            'borderRadiusTopLeft' => 20, 'borderRadiusTopRight' => 0,
            'borderRadiusBottomRight' => 50, 'borderRadiusBottomRightUnit' => '%',
            'borderRadiusBottomLeft' => 1, 'borderRadiusBottomLeftUnit' => 'rem',
        ]);

        $this->assertStringContainsString('border-radius:20px 0px 50% 1rem;', $html);
    }

    public function test_an_image_saved_with_one_radius_keeps_it_on_every_corner(): void
    {
        $this->assertStringContainsString('border-radius:12px 12px 12px 12px;', $this->render(['borderRadius' => 12]));
        // One corner set, the rest from the old value.
        $this->assertStringContainsString('border-radius:0px 12px 12px 12px;', $this->render(['borderRadius' => 12, 'borderRadiusTopLeft' => 0]));
    }

    /**
     * A post card image on falconcms.com had 24 on its top corners and an old single radius of
     * -15: "24px 24px -15px -15px" is invalid CSS, so the browser dropped it and no corner was
     * rounded. A negative value counts as unset.
     */
    public function test_a_negative_radius_is_ignored_instead_of_breaking_the_others(): void
    {
        $html = $this->render(['borderRadius' => -15, 'borderRadiusTopLeft' => 24, 'borderRadiusTopRight' => 24]);
        $this->assertStringContainsString('border-radius:24px 24px 0 0;', $html);
        $this->assertStringNotContainsString('-15px', $html);

        $this->assertStringNotContainsString('border-radius:', $this->render(['borderRadius' => -15]));
        $this->assertStringContainsString('border-radius:0 12px 0 0;', $this->render(['borderRadiusTopLeft' => -3, 'borderRadiusTopRight' => 12]));
    }

    public function test_the_corner_fields_are_text_boxes_without_a_unit_menu(): void
    {
        $panel = file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/falcon-builder/partials/components/elements/image-design.blade.php');
        $block = substr($panel, strpos($panel, 'BORDER RADIUS'), 1600);

        $this->assertStringContainsString("spInput(editingElement.settings, 'borderRadius' + key", $block);
        $this->assertStringNotContainsString('<select', substr($block, 0, strpos($block, 'Border Settings') ?: 1600));
    }

    public function test_no_radius_writes_none(): void
    {
        $this->assertStringNotContainsString('border-radius:', $this->render([]));
        $this->assertStringNotContainsString('border-radius:', $this->render(['borderRadius' => 0]));
    }

    public function test_corners_survive_the_shortcode_round_trip(): void
    {
        $settings = ['url' => '/a.jpg', 'borderRadiusTopLeft' => 8, 'borderRadiusBottomRight' => 25, 'borderRadiusBottomRightUnit' => '%'];
        $json = json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'image', 'settings' => $settings]]]],
        ]]);

        $shortcode = BuilderShortcodeConverter::jsonToShortcodes($json);
        $this->assertStringContainsString('radius_tl="8"', $shortcode);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($shortcode), true)[0]['columns'][0]['elements'][0]['settings'];
        $this->assertSame(8, $back['borderRadiusTopLeft']);
        $this->assertNull($back['borderRadiusTopRight']);
        $this->assertSame(25, $back['borderRadiusBottomRight']);
        $this->assertSame('%', $back['borderRadiusBottomRightUnit']);
    }
}
