<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * The Navigator name an author gives a Container, Column, Nested Row or Nested Column.
 *
 * The builder saves through shortcodes, so a name that is not written into one is gone on the
 * next load. Empty means the default name, and characters that would end the shortcode are
 * dropped from the free text rather than allowed to break the layout.
 */
class NavigatorNameTest extends TestCase
{
    private function roundTrip(array $layout): array
    {
        return json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout))), true);
    }

    private function layout(array $containerSettings, array $colSettings, array $rowSettings, array $ncolSettings): array
    {
        return [[
            'id' => 'r1', 'settings' => $containerSettings,
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => $colSettings,
                'elements' => [['id' => 'n1', 'type' => 'row', 'settings' => $rowSettings + ['visibility' => ['mobile' => true, 'tablet' => true, 'desktop' => true]],
                    'columns' => [['id' => 'nc1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => $ncolSettings, 'elements' => []]]]]]],
        ]];
    }

    public function test_every_level_keeps_its_name(): void
    {
        $back = $this->roundTrip($this->layout(
            ['adminLabel' => 'Hero'], ['adminLabel' => 'Left side'], ['adminLabel' => 'Badges'], ['adminLabel' => 'Badge one']
        ))[0];

        $this->assertSame('Hero', $back['settings']['adminLabel']);
        $col = $back['columns'][0];
        $this->assertSame('Left side', $col['settings']['adminLabel']);
        $this->assertSame('Badges', $col['elements'][0]['settings']['adminLabel']);
        $this->assertSame('Badge one', $col['elements'][0]['columns'][0]['settings']['adminLabel']);
    }

    public function test_no_name_means_the_default_and_writes_nothing(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes(json_encode($this->layout([], ['adminLabel' => '   '], [], [])));
        $this->assertStringNotContainsString('admin_label', $sc);

        $back = $this->roundTrip($this->layout([], [], [], []))[0];
        $this->assertNull($back['settings']['adminLabel']);
        $this->assertNull($back['columns'][0]['settings']['adminLabel']);
    }

    public function test_a_name_cannot_break_the_shortcode(): void
    {
        $back = $this->roundTrip($this->layout(['adminLabel' => 'Hero "big" [top]'], [], [], []))[0];

        $this->assertSame('Hero big top', $back['settings']['adminLabel']);
        $this->assertCount(1, $back['columns']);
    }
}
