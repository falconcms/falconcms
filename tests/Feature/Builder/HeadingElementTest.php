<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * The Heading element's settings panel.
 *
 * The element has been in the builder since the beginning and its panel was empty: both
 * tabs opened onto a placeholder whose comment said "we can add these later". The
 * renderer read a tag, an alignment, a colour and a full set of typography the whole
 * time — none of it reachable except by editing the shortcode by hand.
 *
 * The tag is the part that matters beyond convenience. It is what makes a heading a
 * heading rather than large text, so it decides the page's outline for a screen reader
 * and for search, and it is what the Table of Contents element lists. With no way to
 * choose it, every heading on a page was an H2 and a contents list could only ever be
 * flat.
 */
class HeadingElementTest extends TestCase
{
    private function sidebar(): string
    {
        return (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/sidebar.blade.php'
        );
    }

    /** Both tabs must reach a real panel, not the placeholder they used to. */
    public function test_the_heading_has_a_content_and_a_design_panel(): void
    {
        $sidebar = $this->sidebar();

        $this->assertStringContainsString('components.elements.heading-content', $sidebar,
            'the Heading element has no Content panel');
        $this->assertStringContainsString('components.elements.heading-design', $sidebar,
            'the Heading element has no Design panel');

        // The placeholder must not still be catching the type before those branches do.
        $this->assertDoesNotMatchRegularExpression(
            "/editingElement\\?\\.type === 'heading' \\|\\| editingElement\\?\\.type === 'text'/",
            $sidebar,
            'the empty placeholder branch still swallows the Heading element'
        );
    }

    /**
     * The panel must write the key the rest of the system already reads.
     *
     * The two heading-ish elements disagree and always have: this one stores its tag as
     * `tag`, the Title element as `htmlTag`, and the renderer and the shortcode have
     * been written that way for as long as they have existed. A panel writing the other
     * name would look like it worked and change nothing on the page.
     */
    public function test_the_panel_writes_the_tag_the_renderer_reads(): void
    {
        $panel = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/heading-content.blade.php'
        );

        $this->assertStringContainsString('editingElement.settings.tag = tag', $panel);
        $this->assertStringNotContainsString('settings.htmlTag', $panel,
            'the panel writes the Title element\'s key, which this element never reads');
    }

    /** Every level has to be offered, or the outline cannot be expressed. */
    public function test_every_heading_level_is_offered(): void
    {
        $panel = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/heading-content.blade.php'
        );

        $this->assertStringContainsString("['h1','h2','h3','h4','h5','h6']", $panel);
    }

    /** The chosen tag has to reach the page. */
    public function test_the_chosen_tag_is_rendered(): void
    {
        $this->assertStringContainsString('<h3', $this->render(['title' => 'Requirements', 'tag' => 'h3']));
        $this->assertStringContainsString('<h2', $this->render(['title' => 'Install', 'tag' => 'h2']));

        // An unset tag is an H2, and a tag the renderer does not allow falls back to one
        // rather than writing an element name a browser would not recognise.
        $this->assertStringContainsString('<h2', $this->render(['title' => 'Install']));
        $this->assertStringContainsString('<h2', $this->render(['title' => 'Install', 'tag' => 'marquee']));
    }

    /** And survive the shortcode round trip, or it is lost on the next save. */
    public function test_the_tag_survives_the_round_trip(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'heading', 'settings' => [
                'title' => 'Requirements', 'tag' => 'h3', 'textAlign' => 'center', 'color' => '#112233',
            ]]]]],
        ]]));

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $settings = $back[0]['columns'][0]['elements'][0]['settings'] ?? [];

        $this->assertSame('Requirements', $settings['title'] ?? null);
        $this->assertSame('h3', $settings['tag'] ?? null);
        $this->assertSame('center', $settings['textAlign'] ?? null);
        $this->assertSame('#112233', $settings['color'] ?? null);
    }

    /**
     * The element spans its column in the canvas.
     *
     * Without that it is laid out as an inline-ish box sized to its own text, so
     * alignment has nothing to align within — centring a heading did nothing, and the
     * canvas disagreed with a page where the heading is a block.
     */
    public function test_the_heading_is_full_width_in_the_canvas(): void
    {
        foreach ([
            'components/column/col.blade.php' => 'el',
            'components/nested/row.blade.php' => 'nestedEl',
        ] as $file => $var) {
            $source = (string) file_get_contents(
                __DIR__.'/../../../resources/views/admin/falcon-builder/partials/'.$file
            );

            $this->assertStringContainsString($var.".type === 'heading'", $source,
                "the Heading element is not laid out full width in {$file}");
        }
    }

    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.heading', ['el' => [
            'id' => 'e1', 'type' => 'heading', 'settings' => $settings,
        ]])->render();
    }
}
