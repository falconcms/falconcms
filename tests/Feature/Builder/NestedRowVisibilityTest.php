<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * A nested row's Device Visibility hides it on the page, survives the shortcode round trip,
 * and its buttons show the right state in the panel.
 *
 * Nested rows were created without a visibility setting, and the buttons only lit up when
 * one existed, so a row shown everywhere looked switched off on every device. The canvas
 * also never dimmed a nested row hidden on the device being edited.
 */
class NestedRowVisibilityTest extends TestCase
{
    private function container(array $rowSettings): array
    {
        return [
            'id' => 's1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'r1', 'type' => 'row', 'settings' => $rowSettings,
                'columns' => [['id' => 'n1', 'basis' => '100%', 'settings' => [], 'elements' => []]],
            ]]]],
        ];
    }

    public function test_a_nested_row_hidden_on_a_device_is_hidden_on_the_page(): void
    {
        $html = view('falcon-cms::frontend.builder.container', ['container' => $this->container(['visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => true]])])->render();
        $this->assertMatchesRegularExpression('/class="lazy-container [^"]*falcon-hide-mobile/', $html);
        $this->assertMatchesRegularExpression('/\.falcon-hide-mobile\s*\{\s*display:\s*none !important/', $html);

        $html = view('falcon-cms::frontend.builder.container', ['container' => $this->container(['visibility' => ['mobile' => false, 'tablet' => false, 'desktop' => false]])])->render();
        $this->assertStringContainsString('falcon-hide-all', $html);

        // Shown everywhere (or never set): no hiding class at all.
        $html = view('falcon-cms::frontend.builder.container', ['container' => $this->container([])])->render();
        $this->assertDoesNotMatchRegularExpression('/class="lazy-container [^"]*falcon-hide-/', $html);
    }

    public function test_the_choice_survives_the_shortcode_round_trip(): void
    {
        $json = json_encode([$this->container(['visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => false]])]);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true);
        $row = $back[0]['columns'][0]['elements'][0];
        $this->assertSame('row', $row['type']);
        $this->assertFalse($row['settings']['visibility']['mobile']);
        $this->assertTrue($row['settings']['visibility']['tablet']);
        $this->assertFalse($row['settings']['visibility']['desktop']);
    }

    /** The panel buttons count a missing setting as "shown", and the canvas dims a hidden nested row. */
    public function test_the_builder_reads_a_missing_setting_as_shown(): void
    {
        $views = dirname(__DIR__, 3).'/resources/views/admin/falcon-builder/partials';
        foreach (glob($views.'/{*,components/*/*}.blade.php', GLOB_BRACE) as $file) {
            $src = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/settings\.visibility && [\w.\[\]{}$ ]+\.settings\.visibility\.(mobile|tablet|desktop) !== false/', $src, basename($file));
            // `x = !x` leaves a missing value "shown" on the first click: nothing seemed to happen.
            $this->assertDoesNotMatchRegularExpression('/visibility\.(mobile|tablet|desktop) = ![\w.\[\]{}$ ]+\.visibility\.\1\b/', $src, basename($file));
        }
        $this->assertStringContainsString('getCanvasVisibilityStyle(el.settings)', (string) file_get_contents($views.'/components/nested/row.blade.php'));
    }
}
