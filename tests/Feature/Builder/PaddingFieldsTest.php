<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * Padding fields are one text box per side, like Font Size: "20", "20px", "2rem", "5%", "10vh".
 *
 * Four-sided paddings keep their old storage (a number and its unit key), so content saved
 * before renders as it did. Paddings that were one or two boxes (Accordion, Tabs, Code Block,
 * Content Box, Table, Callout, Prev/Next, TOC) now have four; a side left empty falls back to
 * the old box and then to the old default.
 */
class PaddingFieldsTest extends TestCase
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

    public function test_an_empty_side_falls_back_to_the_old_box_then_the_default(): void
    {
        $this->assertSame('16px 16px 16px 16px', falcon_padding_sides([], 'titlePadding', 16, 'titlePadding'));
        $this->assertSame('24px 24px 24px 24px', falcon_padding_sides(['titlePadding' => 24], 'titlePadding', 16, 'titlePadding'));
        $this->assertSame('1rem 24px 5% 24px', falcon_padding_sides(
            ['titlePadding' => 24, 'titlePaddingTop' => '1rem', 'titlePaddingBottom' => '5%'], 'titlePadding', 16, 'titlePadding'));

        $xy = ['Top' => 'cellPaddingY', 'Right' => 'cellPaddingX', 'Bottom' => 'cellPaddingY', 'Left' => 'cellPaddingX'];
        $this->assertSame('8px 12px 8px 2vw', falcon_padding_sides(
            ['cellPaddingY' => 8, 'cellPaddingX' => 12, 'cellPaddingLeft' => '2vw'], 'cellPadding', 0, $xy));
        $this->assertSame('calc(1rem + 2px)', falcon_padding_side_list(['padTop' => 'calc(1rem + 2px)'], 'pad')['Top']);
    }

    public function test_split_paddings_survive_the_shortcode_round_trip(): void
    {
        $back = $this->roundTrip('accordion', ['items' => [], 'titlePaddingTop' => '1.5rem', 'titlePaddingLeft' => 12, 'contentPaddingBottom' => '5%']);
        $this->assertSame(['1.5rem', '', '', 12, '5%'], [$back['titlePaddingTop'], $back['titlePaddingRight'], $back['titlePaddingBottom'], $back['titlePaddingLeft'], $back['contentPaddingBottom']]);
        // Content saved before has only the old box; it must still come back.
        $this->assertSame(20, $this->roundTrip('accordion', ['items' => [], 'titlePadding' => 20])['titlePadding']);

        $this->assertSame('2vh', $this->roundTrip('tabs', ['items' => [], 'contentPaddingTop' => '2vh'])['contentPaddingTop']);
        $this->assertSame('1em', $this->roundTrip('code_block', ['code' => 'x', 'paddingRight' => '1em'])['paddingRight']);
        $this->assertSame('3rem', $this->roundTrip('table', ['rows' => [], 'cellPaddingLeft' => '3rem'])['cellPaddingLeft']);
        $back = $this->roundTrip('content_box', ['items' => [], 'boxPaddingTop' => 40, 'linkButtonPaddingRight' => '2rem']);
        $this->assertSame([40, '2rem'], [$back['boxPaddingTop'], $back['linkButtonPaddingRight']]);
        foreach (['callout', 'prev_next', 'toc'] as $type) {
            $this->assertSame('7px', $this->roundTrip($type, ['padTop' => '7px'])['padTop'], $type);
        }
    }

    public function test_a_four_sided_padding_keeps_a_fraction_or_any_css_value(): void
    {
        // Breadcrumb used to read its padding back with (int): 2.5 became 2.
        $back = $this->roundTrip('breadcrumb', ['paddingTop' => 2.5, 'paddingTopUnit' => 'rem', 'paddingLeft' => 'calc(1rem + 4px)']);
        $this->assertSame([2.5, 'rem', 'calc(1rem + 4px)'], [$back['paddingTop'], $back['paddingTopUnit'], $back['paddingLeft']]);
        $this->assertSame('1rem', $this->roundTrip('menu', ['menuId' => 1, 'itemPaddingTop' => '1rem'])['itemPaddingTop']);
    }

    public function test_the_page_writes_what_was_typed(): void
    {
        $html = $this->render('accordion', ['items' => [['title' => 'Q', 'content' => 'A']], 'titlePaddingTop' => '1.5rem', 'titlePadding' => 10]);
        $this->assertStringContainsString('padding:1.5rem 10px 10px 10px', $html);

        $html = $this->render('text-block', ['content' => '<p>x</p>', 'paddingTop' => 'calc(1rem + 2px)', 'paddingTopUnit' => 'px']);
        $this->assertStringContainsString('padding-top: calc(1rem + 2px)', $html);
        $this->assertStringNotContainsString('calc(1rem + 2px)px', $html);

        // Old content renders as before.
        $html = $this->render('code-block', ['code' => 'x', 'padding' => 18]);
        $this->assertStringContainsString('padding: 18px 18px 18px 18px', $html);
    }

    /** Margins work the same way: what is typed is what renders, and survives the round trip. */
    public function test_a_margin_takes_any_css_size(): void
    {
        // Elements that read their margin back with (int) turned 1.5rem into 1 and calc() into 0.
        foreach (['accordion', 'tabs', 'icon_box', 'icon_list', 'spacer', 'html', 'video'] as $type) {
            $back = $this->roundTrip($type, ['marginTop' => 1.5, 'marginTopUnit' => 'rem', 'marginBottom' => 'calc(1rem + 2px)']);
            $this->assertSame([1.5, 'rem', 'calc(1rem + 2px)'], [$back['marginTop'], $back['marginTopUnit'], $back['marginBottom']], $type);
        }
        $back = $this->roundTrip('image', ['marginLeft' => 2.5, 'marginLeftUnit' => 'vw']);
        $this->assertSame([2.5, 'vw'], [$back['marginLeft'], $back['marginLeftUnit']]);

        $html = $this->render('title', ['titleColor' => '#222', 'title' => 'Hi', 'marginTop' => 'calc(1rem + 2px)', 'marginTopUnit' => 'px', 'marginBottom' => 2, 'marginBottomUnit' => 'rem']);
        $this->assertStringContainsString('margin-top: calc(1rem + 2px)', $html);
        $this->assertStringContainsString('margin-bottom: 2rem', $html);
        $this->assertStringNotContainsString('calc(1rem + 2px)px', $html);
        // A margin box without a unit key (Code Block, Table) keeps "1rem" whole.
        $this->assertStringContainsString('1rem', $this->render('code-block', ['code' => 'x', 'marginTop' => '1rem']));
    }

    /** No margin box in the panels is a number box or has a unit dropdown any more. */
    public function test_no_panel_has_a_number_box_or_unit_dropdown_for_a_margin(): void
    {
        $dir = dirname(__DIR__, 3).'/resources/views/admin/falcon-builder/partials';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            preg_match_all('/<input[^>]*type="number"[^>]*margin(Top|Right|Bottom|Left)/s', $src, $boxes);
            preg_match_all('/<select[^>]*margin[^>]*Unit/s', $src, $units);
            $this->assertSame([], array_merge($boxes[0], $units[0]), $file->getFilename());
        }
    }

    /** No padding box in the panels is a number box with a unit dropdown any more. */
    public function test_no_panel_has_a_number_box_for_a_padding(): void
    {
        $dir = dirname(__DIR__, 3).'/resources/views/admin/falcon-builder/partials';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            // Icon padding (the room around an icon in its shape) stays one value on purpose.
            preg_match_all('/<input[^>]*type="number"[^>]*v-model[^=]*="[^"]*(?<![Ii]con)Padding[^"]*"/', $src, $m);
            preg_match_all('/<input[^>]*type="number"[^>]*v-model[^=]*="[^"]*\bpadding\w*"/', $src, $m2);
            $this->assertSame([], array_merge($m[0], $m2[0]), $file->getFilename());
        }
    }
}
