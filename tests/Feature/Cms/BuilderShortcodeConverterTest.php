<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Services\BuilderShortcodeConverter as C;
use FalconCms\Core\Tests\TestCase;

/**
 * Guards the builder JSON <-> shortcode round-trip.
 * These are exactly the failures we hit by hand this session:
 *  - design fields (dividerWidth, padding, button url/style/opacity) getting dropped,
 *  - base64 ("_d=") creeping back into shortcodes.
 */
class BuilderShortcodeConverterTest extends TestCase
{
    /** A small but representative layout: container > column > [title, button]. */
    private function sampleLayout(): array
    {
        return [[
            'id' => 'c1', 'type' => 'container',
            'settings' => ['visibility' => ['mobile' => true, 'tablet' => true, 'desktop' => true]],
            'columns' => [[
                'id' => 'col1', 'basis' => '100%',
                'settings' => ['visibility' => ['mobile' => true, 'tablet' => true, 'desktop' => true]],
                'elements' => [
                    [
                        'id' => 't1', 'type' => 'title',
                        'settings' => [
                            'title' => 'Hello World',
                            'titleColor' => '#222222',
                            'titleHoverColor' => '#ff0000',
                            'fontSize' => 36,
                            'dividerWidth' => 60,
                            'dividerHeight' => 3,
                            'paddingTop' => 20,
                            'separator' => 'default',
                            'visibility' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                        ],
                    ],
                    [
                        'id' => 'b1', 'type' => 'button',
                        'settings' => [
                            'text' => 'Click', 'url' => '#', 'style' => 'primary',
                            'bgColor' => '#0091ea', 'bgColorOpacity' => 1,
                            'visibility' => ['mobile' => true, 'tablet' => true, 'desktop' => true],
                        ],
                    ],
                ],
            ]],
        ]];
    }

    public function test_round_trip_preserves_design_fields(): void
    {
        $layout = $this->sampleLayout();

        $shortcode = C::jsonToShortcodes(json_encode($layout));
        $back = json_decode(C::shortcodesToJson($shortcode), true);

        $title = $back[0]['columns'][0]['elements'][0]['settings'];
        $button = $back[0]['columns'][0]['elements'][1]['settings'];

        // Title design fields survive
        $this->assertSame('#222222', (string) $title['titleColor']);
        $this->assertSame('#ff0000', (string) $title['titleHoverColor']);
        $this->assertSame('60', (string) $title['dividerWidth']);
        $this->assertSame('3', (string) $title['dividerHeight']);
        $this->assertSame('20', (string) $title['paddingTop']);

        // Button design fields survive
        $this->assertSame('#', (string) $button['url']);
        $this->assertSame('primary', (string) $button['style']);
        $this->assertSame('1', (string) $button['bgColorOpacity']);
    }

    public function test_shortcode_is_readable_not_base64(): void
    {
        $shortcode = C::jsonToShortcodes(json_encode($this->sampleLayout()));

        // No base64 "_d=" fidelity blob, and no obvious base64 settings dump.
        $this->assertStringNotContainsString('_d="', $shortcode);
        $this->assertStringNotContainsString('settings_b64', $shortcode);

        // It is the human-readable shortcode format.
        $this->assertStringContainsString('[falcon_section', $shortcode);
        $this->assertStringContainsString('[falcon_title', $shortcode);
    }

    public function test_round_trip_is_idempotent_once_defaults_are_filled(): void
    {
        // The very first serialize is intentionally minimal; parsing fills in defaults
        // (status, paddings, etc.). From the second generation onward it must be stable —
        // i.e. converting back and forth no longer changes the shortcode.
        $layout = $this->sampleLayout();

        $gen2 = C::jsonToShortcodes(C::shortcodesToJson(C::jsonToShortcodes(json_encode($layout))));
        $gen3 = C::jsonToShortcodes(C::shortcodesToJson($gen2));

        $this->assertSame($gen2, $gen3, 'Round-trip should be stable once defaults are populated.');
    }
}
