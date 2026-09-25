<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The Icon Box's icon: its border, and what it does under the pointer.
 *
 * The icon is drawn twice — in Vue on the builder canvas and in Blade on the page — so the
 * description of it lives in one place, falcon_icon_box_icon_style(), and the canvas mirrors
 * that function declaration for declaration. The parity test at the bottom is what stops the
 * two from drifting the way the Menu element's two drawings repeatedly did.
 *
 * Two things here are easy to get wrong. The normal state is written as an inline style, and
 * an inline style outranks any rule, so the hover rule only works because it is !important —
 * which is also why the Read More hover already is. And the wrapper is only drawn as a box
 * when there is something to draw; before a border existed that test was "has a background",
 * so a border on its own would have had no box to sit on.
 */
class IconBoxIconStateTest extends TestCase
{
    private function style(array $settings): array
    {
        return falcon_icon_box_icon_style($settings, '#box');
    }

    public function test_an_icon_with_nothing_set_on_hover_gets_no_rule_at_all(): void
    {
        // Every icon box already out there must render exactly as it did.
        $out = $this->style(['iconSize' => 40, 'iconColor' => '#2271b1']);

        $this->assertSame('', $out['css']);
        $this->assertStringContainsString('font-size:40px', $out['icon']);
        $this->assertStringContainsString('color:#2271b1', $out['icon']);
    }

    public function test_without_a_background_or_a_border_the_icon_is_not_a_box(): void
    {
        $out = $this->style(['iconSize' => 40, 'iconColor' => '#2271b1']);

        $this->assertStringNotContainsString('width:', $out['wrap']);
        $this->assertStringNotContainsString('border:', $out['wrap']);
        $this->assertStringNotContainsString('background-color:', $out['wrap']);
    }

    public function test_a_border_on_its_own_draws_a_box(): void
    {
        // The old test was "has a background", so this drew nothing whatsoever.
        $out = $this->style(['iconSize' => 24, 'iconBorderWidth' => 2, 'iconBorderColor' => '#333333']);

        $this->assertStringContainsString('border:2px solid #333333', $out['wrap']);
        $this->assertStringContainsString('width:48px', $out['wrap']);
        $this->assertStringContainsString('background-color:transparent', $out['wrap']);
    }

    public function test_the_background_opacity_reaches_the_page(): void
    {
        // It was read into a variable and then never used, so the slider moved nothing.
        $out = $this->style(['iconSize' => 24, 'iconBgColor' => '#f6ece0', 'iconBgColorOpacity' => 0.5]);

        $this->assertStringContainsString('rgba(246,236,224,0.5)', $out['wrap']);
    }

    public function test_one_hover_setting_changes_one_thing(): void
    {
        $out = $this->style([
            'iconSize' => 24, 'iconColor' => '#F39F2A',
            'iconColorHover' => '#ff0000',
        ]);

        $this->assertStringContainsString('#box .lazy-icon-box__icon:hover i{', $out['css']);
        $this->assertStringContainsString('color:#ff0000 !important', $out['css']);
        // The size was not touched, so hover still states the normal one rather than dropping it.
        $this->assertStringContainsString('font-size:24px !important', $out['css']);
    }

    public function test_the_hover_rule_is_important_because_the_normal_state_is_inline(): void
    {
        $out = $this->style(['iconSize' => 24, 'iconColor' => '#F39F2A', 'iconColorHover' => '#fff']);

        foreach (array_filter(explode('}', $out['css'])) as $chunk) {
            if (!str_contains($chunk, ':hover')) {
                continue;
            }
            [, $body] = explode('{', $chunk, 2);
            foreach (array_filter(array_map('trim', explode(';', $body))) as $decl) {
                $this->assertStringContainsString('!important', $decl,
                    "an inline style will beat this: {$decl}");
            }
        }
    }

    public function test_a_border_that_appears_only_on_hover_works(): void
    {
        $out = $this->style([
            'iconSize' => 24, 'iconColor' => '#F39F2A',
            'iconBorderWidthHover' => 3, 'iconBorderColorHover' => '#000000',
        ]);

        $this->assertStringNotContainsString('border:', $out['wrap'], 'the border leaked into the resting state');
        $this->assertStringContainsString('border:3px solid #000000 !important', $out['css']);
    }

    public function test_a_hover_opacity_alone_still_has_a_colour_to_apply_it_to(): void
    {
        // Setting only the opacity used to leave the hover state with no background at all,
        // so the icon's box vanished as the pointer arrived.
        $out = $this->style([
            'iconSize' => 24, 'iconBgColor' => '#f6ece0', 'iconBgColorOpacity' => 1,
            'iconBgColorHoverOpacity' => 0.4,
        ]);

        $this->assertStringContainsString('rgba(246,236,224,0.4) !important', $out['css']);
    }

    public function test_a_cleared_field_is_not_a_hover_value(): void
    {
        // The panel stores "" for a field someone emptied, and "" is not a colour.
        $out = $this->style([
            'iconSize' => 24, 'iconColor' => '#F39F2A',
            'iconColorHover' => '', 'iconBorderWidthHover' => '', 'iconPaddingHover' => '',
        ]);

        $this->assertSame('', $out['css']);
    }

    public function test_hover_changes_are_given_something_to_animate(): void
    {
        $out = $this->style(['iconSize' => 24, 'iconColorHover' => '#fff']);

        $this->assertStringContainsString('transition:all .2s ease', $out['css']);
    }

    public function test_the_scope_keeps_one_icon_box_out_of_another(): void
    {
        $out = falcon_icon_box_icon_style(['iconColorHover' => '#fff'], '#lzib-42');

        $this->assertStringStartsWith('#lzib-42 ', $out['css']);
        $this->assertSame(4, substr_count($out['css'], '#lzib-42 '));
    }

    /**
     * The canvas has its own copy of all of this, in JavaScript. Nothing here runs it — this
     * reads both files and insists that every setting one of them knows about, the other one
     * knows about too. Add a setting to one side and this will say so.
     */
    public function test_the_canvas_knows_about_every_setting_the_page_does(): void
    {
        $php = (string) file_get_contents(__DIR__.'/../../../src/helpers.php');
        $canvas = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');

        $block = substr($php, strpos($php, 'function falcon_icon_box_icon_style'));
        $block = substr($block, 0, strpos($block, "\n}"));

        preg_match_all("/'(icon[A-Za-z]+)'/", $block, $m);
        $keys = array_unique($m[1]);

        $this->assertNotEmpty($keys);
        foreach ($keys as $key) {
            $this->assertStringContainsString("'{$key}'", $canvas,
                "the page reads {$key} and the builder canvas has never heard of it");
        }
    }

    public function test_both_renderers_decide_the_same_way_when_the_icon_is_a_box(): void
    {
        // The test is spelled out in both files; if one of them changes its mind the icon
        // gains or loses its box on the way from the editor to the page.
        $canvas = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');

        $this->assertStringContainsString("const boxed = v.bg !== '' || borderWidth > 0;", $canvas);

        $php = (string) file_get_contents(__DIR__.'/../../../src/helpers.php');
        $this->assertStringContainsString("\$boxed = \$v['bg'] !== '' || \$borderWidth > 0;", $php);
    }
}
