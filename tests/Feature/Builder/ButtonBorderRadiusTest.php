<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * The Button's border radius, one value per corner (as on a container and the Image).
 *
 * It had a single slider. A button saved with it keeps that value on every corner, and one
 * saved with none keeps the 5px default it always rendered with.
 */
class ButtonBorderRadiusTest extends TestCase
{
    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.button', ['el' => [
            'id' => 'e1', 'type' => 'button', 'settings' => $settings + ['text' => 'Go', 'linkUrl' => '#'],
        ]])->render();
    }

    public function test_each_corner_gets_its_own_radius(): void
    {
        $html = $this->render(['borderRadiusTopLeft' => 20, 'borderRadiusTopRight' => 0, 'borderRadiusBottomRight' => 50, 'borderRadiusBottomRightUnit' => '%', 'borderRadiusBottomLeft' => 0]);

        $this->assertStringContainsString('border-radius: 20px 0px 50% 0px', $html);
    }

    public function test_older_buttons_look_the_same(): void
    {
        $this->assertStringContainsString('border-radius: 5px 5px 5px 5px', $this->render([]));
        $this->assertStringContainsString('border-radius: 12px 12px 12px 12px', $this->render(['borderRadius' => 12]));
        $this->assertStringNotContainsString('border-radius:', $this->render(['borderRadius' => 0]));
    }

    public function test_corners_survive_the_shortcode_round_trip(): void
    {
        $json = json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'button', 'settings' => ['text' => 'Go', 'borderRadiusTopLeft' => 8, 'borderRadiusBottomLeft' => 2, 'borderRadiusBottomLeftUnit' => 'rem']]]]],
        ]]);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true)[0]['columns'][0]['elements'][0]['settings'];

        $this->assertSame(8, $back['borderRadiusTopLeft']);
        $this->assertNull($back['borderRadiusTopRight']);
        $this->assertSame(2, $back['borderRadiusBottomLeft']);
        $this->assertSame('rem', $back['borderRadiusBottomLeftUnit']);
    }
}
