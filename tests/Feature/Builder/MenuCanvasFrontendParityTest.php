<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The Menu element is drawn twice, and the two drawings have to agree.
 *
 * The builder canvas renders it in Vue; the site renders it in Blade. They are separate
 * files with separate copies of every default, and nothing made them agree — so they drifted,
 * repeatedly, and each time the same report came back: the menu looks different in the editor
 * than it does on the site. The differences found so far were all of one kind — a default
 * written twice and answered differently:
 *
 *   · the font size defaulted to 14 on the canvas and 16 on the site
 *   · a cleared field stores "" rather than null, and `?? default` does not catch it, so the
 *     canvas dropped the property while the site emitted "padding: px 15px" and the browser
 *     threw the whole declaration away
 *   · the canvas asked for line-height "inherit" and inherited the line-height:0 that the
 *     editor puts on every element wrapper, leaving each item ~12px shorter than on the site
 *
 * Nothing here runs JavaScript. It reads the defaults out of both files and insists they are
 * the same number, which is the part that kept going wrong. Add a setting to one side and
 * this test will tell you the other side is missing it.
 */
class MenuCanvasFrontendParityTest extends TestCase
{
    private const FRONTEND = __DIR__.'/../../../resources/views/frontend/builder/elements/menu.blade.php';

    private const CANVAS = __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/menu.blade.php';

    private function read(string $path): string
    {
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Every default the file states, as setting name => list of defaults it uses for it.
     *
     * A list rather than one value because a setting can legitimately have two — item
     * spacing is 25 across a horizontal menu and 10 down a vertical one — and both sides
     * have to offer the same pair.
     *
     * @return array<string, list<string>>
     */
    private function defaults(string $source, string $pattern): array
    {
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

        $found = [];
        foreach ($matches as $match) {
            $found[$match[1]][] = rtrim(rtrim($match[2], '0'), '.') ?: '0';
        }

        foreach ($found as $key => $values) {
            $values = array_values(array_unique($values));
            sort($values);
            $found[$key] = $values;
        }

        return $found;
    }

    /** @return array<string, list<string>> */
    private function frontendDefaults(): array
    {
        // $num($s['itemPaddingTop'] ?? null, 10)
        return $this->defaults($this->read(self::FRONTEND), "/\\\$num\(\\\$s\['(\w+)'\][^,]*,\s*([0-9.]+)\)/");
    }

    /** @return array<string, list<string>> */
    private function canvasDefaults(): array
    {
        // falconNum(el.settings.itemPaddingTop, 10)
        return $this->defaults($this->read(self::CANVAS), '/falconNum\(el\.settings\.(\w+),\s*([0-9.]+)\)/');
    }

    public function test_both_sides_state_defaults_at_all(): void
    {
        $this->assertNotEmpty($this->frontendDefaults(), 'the front end must read its numbers through $num()');
        $this->assertNotEmpty($this->canvasDefaults(), 'the canvas must read its numbers through falconNum()');
    }

    public function test_the_shared_settings_default_to_the_same_number(): void
    {
        $frontend = $this->frontendDefaults();
        $canvas = $this->canvasDefaults();

        $shared = array_intersect_key($frontend, $canvas);
        $this->assertNotEmpty($shared, 'the two renderers should have settings in common');

        foreach ($shared as $setting => $frontendValues) {
            $this->assertSame(
                $frontendValues,
                $canvas[$setting],
                "{$setting} defaults to ".implode('/', $frontendValues).' on the site but '
                .implode('/', $canvas[$setting]).' on the canvas. One menu, one number: change both.'
            );
        }
    }

    public function test_the_item_box_settings_are_covered_on_both_sides(): void
    {
        // The ones that decide the size of the box a reader sees. If a later edit stops
        // routing one of these through the shared helpers, the comparison above would
        // quietly have nothing to compare.
        $expected = ['itemPaddingTop', 'itemPaddingRight', 'itemPaddingBottom', 'itemPaddingLeft', 'itemBorderRadius', 'itemSpacing'];

        foreach ([['the site', $this->frontendDefaults()], ['the canvas', $this->canvasDefaults()]] as [$where, $defaults]) {
            foreach ($expected as $setting) {
                $this->assertArrayHasKey(
                    $setting,
                    $defaults,
                    "{$setting} is no longer read through the blank-aware helper on {$where}, so a "
                    .'cleared field will be handled differently there than on the other side.'
                );
            }
        }
    }

    public function test_a_cleared_field_is_not_read_with_the_null_coalescing_operator_alone(): void
    {
        // "" is not null. `el.settings.itemPaddingTop ?? 10` hands the empty string straight
        // to the browser, which is how the canvas lost its padding while the site kept it.
        $this->assertDoesNotMatchRegularExpression(
            '/el\.settings\.item(Padding\w+|BorderRadius|Spacing)\s*\?\?/',
            $this->read(self::CANVAS),
            'read this through falconNum() so a cleared field falls back like it does on the site'
        );
    }

    public function test_the_canvas_never_inherits_its_line_height(): void
    {
        // The editor puts line-height:0 on every element wrapper on purpose. A menu link
        // asking to inherit gets that 0 and collapses; the site has no such wrapper.
        $this->assertDoesNotMatchRegularExpression(
            '/lineHeight:[^,\n]*[\'"]inherit[\'"]/',
            $this->read(self::CANVAS),
            'the canvas wrapper sets line-height:0, so inheriting it shrinks every menu item'
        );
    }

    public function test_the_main_link_font_size_defaults_to_the_same_number(): void
    {
        $this->assertStringContainsString(
            "\$getTypographyStyle('', '16px')",
            $this->read(self::FRONTEND),
            'the site sizes main menu links from this default'
        );
        $this->assertStringContainsString(
            'falconNum(el.settings.fontSize, 16)',
            $this->read(self::CANVAS),
            'the canvas must use the same default the site does — it was 14 here once, and the '
            .'menu came out visibly smaller in the editor than on the site'
        );
    }
}
