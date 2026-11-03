<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * "Stop sticking" on a sticky column or container.
 *
 * A sticky box travels to the end of its own parent, and a top-level container's parent
 * is the whole page, so a sticky container rode along to the footer. It can now let go
 * after the reader has scrolled N px with it stuck, or when a chosen element reaches its
 * bottom edge. The browser does the work; these tests hold the settings on their way
 * there: the markup carries them, the script comes with them, and a save keeps them.
 */
class StickyEndTest extends TestCase
{
    private function column(array $settings): string
    {
        return view('falcon-cms::frontend.builder.column', [
            'column' => ['id' => 'col-1', 'basis' => '50%', 'settings' => $settings, 'elements' => []],
            'container' => ['settings' => [], 'columns' => [['basis' => '50%']]],
        ])->render();
    }

    private function container(array $settings): string
    {
        return view('falcon-cms::frontend.builder.container', ['container' => [
            'id' => 's1', 'settings' => $settings,
            'columns' => [['id' => 'c1', 'basis' => '100%', 'settings' => [], 'elements' => []]],
        ]])->render();
    }

    public function test_a_sticky_column_without_an_end_renders_as_before(): void
    {
        $html = $this->column(['sticky' => true, 'stickyOffset' => 20]);

        $this->assertStringContainsString('lazy-sticky-col', $html);
        $this->assertStringNotContainsString('data-sticky-end', $html);
        $this->assertStringNotContainsString('__falconStickyEnd', $html, 'the script came without anything to run on');
    }

    public function test_a_column_stops_after_a_distance(): void
    {
        $html = $this->column(['sticky' => true, 'stickyOffset' => 20, 'stickyEnd' => 'distance', 'stickyEndDistance' => 450]);

        $this->assertStringContainsString('data-sticky-end="distance" data-sticky-end-value="450" data-sticky-top="20"', $html);
        $this->assertStringContainsString('window.__falconStickyEnd', $html);
    }

    public function test_a_blank_distance_is_the_placeholder_not_zero(): void
    {
        $html = $this->column(['sticky' => true, 'stickyEnd' => 'distance', 'stickyEndDistance' => '']);

        $this->assertStringContainsString('data-sticky-end-value="600"', $html,
            'a blank distance let go the moment the column stuck');
    }

    public function test_a_container_stops_at_an_element(): void
    {
        $html = $this->container(['sticky' => true, 'stickyOffset' => 0, 'stickyEnd' => 'element', 'stickyEndAt' => '#cta']);

        $this->assertStringContainsString('data-sticky-end="element" data-sticky-end-value="#cta"', $html);
        $this->assertStringContainsString('window.__falconStickyEnd', $html);
        // The container's own rule is !important, so the script's top has to be as well.
        $this->assertStringContainsString("el.style.setProperty('top', Math.round(want) + 'px', 'important')", $html);
    }

    public function test_an_empty_element_or_no_sticky_writes_nothing(): void
    {
        $this->assertStringNotContainsString('data-sticky-end', $this->container(['sticky' => true, 'stickyEnd' => 'element', 'stickyEndAt' => '  ']));
        $this->assertStringNotContainsString('data-sticky-end', $this->column(['sticky' => false, 'stickyEnd' => 'distance', 'stickyEndDistance' => 300]));
    }

    public function test_the_end_survives_the_shortcode_round_trip(): void
    {
        $json = (string) json_encode([[
            'id' => 's1', 'settings' => ['sticky' => true, 'stickyEnd' => 'element', 'stickyEndAt' => '#cta'],
            'columns' => [['id' => 'c1', 'basis' => '100%',
                'settings' => ['sticky' => true, 'stickyEnd' => 'distance', 'stickyEndDistance' => 300],
                'elements' => []]],
        ]]);
        $sc = BuilderShortcodeConverter::jsonToShortcodes($json);
        $this->assertStringContainsString('sticky_end="element"', $sc);
        $this->assertStringContainsString('sticky_end_at="#cta"', $sc);
        $this->assertStringContainsString('sticky_end="distance"', $sc);
        $this->assertStringContainsString('sticky_end_distance="300"', $sc);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $this->assertSame('element', $back[0]['settings']['stickyEnd']);
        $this->assertSame('#cta', $back[0]['settings']['stickyEndAt']);
        $this->assertSame('distance', $back[0]['columns'][0]['settings']['stickyEnd']);
        $this->assertSame(300, $back[0]['columns'][0]['settings']['stickyEndDistance']);
    }

    public function test_quotes_and_brackets_cannot_break_the_shortcode(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'id' => 's1', 'settings' => ['sticky' => true, 'stickyEnd' => 'element', 'stickyEndAt' => '[data-x="y"] .cta'],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'settings' => [], 'elements' => []]],
        ]]));
        $this->assertStringContainsString('sticky_end_at="data-x=y .cta"', $sc);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $this->assertSame('100%', $back[0]['columns'][0]['basis'], 'the shortcode after the selector was cut apart');
    }
}
