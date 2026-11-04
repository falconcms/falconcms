<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * A text font size left empty follows the Customizer's typography.
 *
 * New elements start with their text font sizes empty: the page then shows the Customizer's
 * body, heading or navigation size, and a size typed into the element wins. Content saved
 * before keeps its look — a size never set (absent) still gets the element's old default.
 */
class EmptyFontSizeTest extends TestCase
{
    private function roundTrip(string $type, array $settings): array
    {
        $json = json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => $type, 'settings' => $settings]]]],
        ]]);

        return json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true)[0]['columns'][0]['elements'][0]['settings'];
    }

    private function render(string $view, array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.'.$view, ['el' => ['id' => 'e1', 'type' => str_replace('-', '_', $view), 'settings' => $settings]])->render();
    }

    /** The post screen saves through shortcodes: an empty size must come back empty, not as 36px. */
    public function test_an_empty_size_survives_the_shortcode_round_trip(): void
    {
        $this->assertSame('', $this->roundTrip('title', ['title' => 'Hi', 'fontSize' => ''])['fontSize']);
        $this->assertSame('', $this->roundTrip('text_block', ['content' => '<p>x</p>', 'fontSize' => ''])['fontSize']);
        $this->assertSame('', $this->roundTrip('button', ['text' => 'Go', 'fontSize' => ''])['fontSize']);
        $this->assertSame('', $this->roundTrip('menu', ['menuId' => 1, 'fontSize' => ''])['fontSize']);
        $back = $this->roundTrip('accordion', ['items' => [], 'titleFontSize' => '', 'contentFontSize' => '']);
        $this->assertSame(['', ''], [$back['titleFontSize'], $back['contentFontSize']]);
        $this->assertSame('', $this->roundTrip('ticker', ['fontSize' => ''])['fontSize']);
    }

    public function test_a_size_that_was_never_set_keeps_the_old_default(): void
    {
        $this->assertSame(15, $this->roundTrip('accordion', ['items' => []])['titleFontSize']);
        $this->assertSame(16, $this->roundTrip('text_block', ['content' => '<p>x</p>'])['fontSize']);
        $this->assertSame('14px', $this->roundTrip('ticker', [])['fontSize']);
        $this->assertSame(24, $this->roundTrip('title', ['title' => 'Hi', 'fontSize' => 24])['fontSize']);
    }

    public function test_an_empty_size_writes_no_font_size_on_the_page(): void
    {
        $this->assertStringNotContainsString('font-size', $this->render('title', ['titleColor' => '#222', 'title' => 'Hi', 'htmlTag' => 'h2', 'fontSize' => '']));
        $this->assertStringNotContainsString('font-size', $this->render('button', ['text' => 'Go', 'linkUrl' => '#', 'fontSize' => '']));
        $this->assertStringNotContainsString('font-size: 16px', $this->render('menu', ['menuId' => 0, 'fontSize' => '', 'mobileCollapseBreakpoint' => 'none']));
    }

    public function test_a_set_or_never_set_size_is_written_as_before(): void
    {
        $this->assertStringContainsString('font-size: 24px', $this->render('title', ['titleColor' => '#222', 'title' => 'Hi', 'fontSize' => 24]));
        $this->assertStringContainsString('font-size: 36px', $this->render('title', ['titleColor' => '#222', 'title' => 'Hi']));
        $this->assertStringContainsString('font-size: 16px', $this->render('button', ['text' => 'Go', 'linkUrl' => '#']));
    }

    public function test_the_customizer_sizes_are_read_for_the_canvas(): void
    {
        update_cms_option('theme_typography_body', json_encode(['size' => '15px']));
        update_cms_option('theme_typography_nav', json_encode(['size' => '16px']));
        update_cms_option('theme_typography_h2', json_encode(['size' => '32px']));
        update_cms_option('theme_typography_h3', '');

        $sizes = falcon_theme_font_sizes();

        $this->assertSame('15px', $sizes['body']);
        $this->assertSame('16px', $sizes['nav']);
        $this->assertSame('32px', $sizes['h2']['max']);
        $this->assertLessThanOrEqual(32, (float) $sizes['h2']['min']);
        $this->assertArrayNotHasKey('h3', $sizes, 'a typography never saved writes no rule');
    }
}
