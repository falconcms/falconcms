<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * A menu per device: the Menu element's menu can be set for tablet and mobile on their own.
 *
 * One left empty uses the larger screen's menu. When they differ the page draws one copy per
 * distinct menu, each shown only on its own devices; with no per-device menu the element is
 * drawn exactly once, as before.
 */
class MenuPerDeviceTest extends TestCase
{
    private function menu(string $name, array $items): int
    {
        $id = DB::table('navigation_menus')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($items as $i => $title) {
            DB::table('navigation_menu_items')->insert(['navigation_menu_id' => $id, 'title' => $title, 'url' => '/'.strtolower($title),
                'order' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function render(array $s): string
    {
        return view('falcon-cms::frontend.builder.elements.menu', ['el' => ['id' => 'mpd', 'settings' => $s + ['mobileCollapseBreakpoint' => 'none']]])->render();
    }

    /** The class list of the copy that lists $title. */
    private function copyClasses(string $html, string $title): ?string
    {
        foreach (explode('<div class="falcon-menu-variant', $html) as $i => $part) {
            if ($i > 0 && str_contains($part, '>'.$title.'<')) {
                return trim(substr($part, 0, strpos($part, '"')));
            }
        }

        return null;
    }

    public function test_one_menu_for_every_device_is_drawn_once_as_before(): void
    {
        $main = $this->menu('Main', ['Home', 'About']);
        $html = $this->render(['menuId' => $main]);
        $this->assertStringNotContainsString('falcon-menu-variant', $html);
        $this->assertSame(1, substr_count($html, 'id="menu-mpd"'));

        // A per-device choice equal to the desktop's is the same thing.
        $this->assertStringNotContainsString('falcon-menu-variant', $this->render(['menuId' => $main, 'menuId_tablet' => (string) $main, 'menuId_mobile' => '']));
    }

    public function test_a_mobile_menu_of_its_own(): void
    {
        $main = $this->menu('Main', ['Home', 'About', 'Pricing']);
        $mobile = $this->menu('Mobile', ['Shop', 'Call']);
        $html = $this->render(['menuId' => $main, 'menuId_mobile' => $mobile]);

        $this->assertSame(2, substr_count($html, 'class="falcon-menu-variant'));
        $this->assertSame('falcon-hide-mobile', $this->copyClasses($html, 'Home'), 'desktop + tablet copy');
        $this->assertSame('falcon-hide-desktop falcon-hide-tablet', $this->copyClasses($html, 'Shop'), 'mobile copy');
        // no id twice: the desktop copy keeps it, the other gets its own
        $this->assertSame(1, substr_count($html, 'id="menu-mpd"'));
        $this->assertSame(1, substr_count($html, 'id="menu-mpd-mobile"'));
    }

    public function test_tablet_carries_down_to_mobile_unless_mobile_has_its_own(): void
    {
        $main = $this->menu('Main', ['Home']);
        $tab = $this->menu('Tab', ['Tablet Item']);
        $mob = $this->menu('Mob', ['Mobile Item']);

        $html = $this->render(['menuId' => $main, 'menuId_tablet' => $tab]);
        $this->assertSame('falcon-hide-tablet falcon-hide-mobile', $this->copyClasses($html, 'Home'));
        $this->assertSame('falcon-hide-desktop', $this->copyClasses($html, 'Tablet Item'), 'mobile follows tablet');

        $html = $this->render(['menuId' => $main, 'menuId_tablet' => $tab, 'menuId_mobile' => $mob]);
        $this->assertSame(3, substr_count($html, 'class="falcon-menu-variant'));
        $this->assertSame('falcon-hide-desktop falcon-hide-mobile', $this->copyClasses($html, 'Tablet Item'));
        $this->assertSame('falcon-hide-desktop falcon-hide-tablet', $this->copyClasses($html, 'Mobile Item'));
    }

    public function test_the_choice_survives_the_shortcode_round_trip(): void
    {
        $json = json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'menu', 'settings' => ['menuId' => 3, 'menuId_tablet' => '5', 'menuId_mobile' => '7']]]]],
        ]]);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true)[0]['columns'][0]['elements'][0]['settings'];
        $this->assertEquals(3, $back['menuId']);
        $this->assertEquals(5, $back['menuId_tablet']);
        $this->assertEquals(7, $back['menuId_mobile']);
    }
}
