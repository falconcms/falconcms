<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;

/**
 * Opening an image full size.
 *
 * The lightbox is the Gallery's, not a second one: it was already written, already
 * worked, and two of them on one site would drift into two behaviours. The Image element
 * marks its image with the same data attributes the Gallery uses and includes the same
 * partial.
 *
 * The part worth pinning down is what happens to the link. A lightbox and a link both
 * want the click, so the panel puts the URL field away — and the page ignores whatever
 * was saved there, which is the half that is easy to forget and impossible to see: an
 * image that quietly kept navigating would look like the lightbox was broken.
 */
class ImageLightboxTest extends TestCase
{
    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.image', ['el' => [
            'id' => 'e1', 'type' => 'image', 'settings' => $settings,
        ]])->render();
    }

    /** Off by default: an image is a picture until someone says otherwise. */
    public function test_it_is_off_unless_asked_for(): void
    {
        $html = $this->render(['url' => '/a.jpg']);

        $this->assertStringNotContainsString('data-lz-gallery', $html);
        $this->assertStringNotContainsString('lz-lightbox', $html);
    }

    /** On, the image becomes a trigger and the lightbox comes with it. */
    public function test_it_marks_the_image_and_ships_the_lightbox(): void
    {
        $html = $this->render(['url' => '/a.jpg', 'alt' => 'A photo', 'lightbox' => true]);

        // The id is generated per render, so the test reads it rather than assuming it —
        // what matters is that the trigger and the shell agree on it, since a mismatch is
        // a lightbox that never opens and says nothing about why.
        $this->assertSame(1, preg_match('/data-lz-gallery="([^"]+)"/', $html, $m));
        $this->assertStringContainsString('id="lz-lb-'.$m[1].'"', $html,
            'the trigger and the lightbox do not share an id');
        $this->assertStringContainsString('data-lz-gallery-url="/a.jpg"', $html);

        // The alt text becomes the caption — it is the description the author already
        // wrote, so asking for a second one would be asking twice.
        $this->assertStringContainsString('data-lz-gallery-cap="A photo"', $html);
    }

    /**
     * A single image has nowhere to go, so it gets no arrows. The Gallery keeps them.
     */
    public function test_a_single_image_gets_no_arrows(): void
    {
        $html = $this->render(['url' => '/a.jpg', 'lightbox' => true]);

        // The shared script defines lzGalleryNav whether or not anything calls it, so the
        // check is on the buttons rather than on the name.
        $this->assertStringNotContainsString('onclick="lzGalleryNav(', $html);
        $this->assertStringContainsString('onclick="lzGalleryClose(', $html);

        // A gallery of more than one keeps them.
        $gallery = view('falcon-cms::components.frontend.lightbox', ['id' => 'g1', 'nav' => true])->render();
        $this->assertStringContainsString('onclick="lzGalleryNav(', $gallery);
    }

    /**
     * The link is ignored while the lightbox is on.
     *
     * The panel hides the field, but an image that already had a URL saved keeps it — so
     * the page has to make the same decision, or that image goes on navigating and the
     * lightbox never opens.
     */
    public function test_a_saved_link_is_ignored_while_the_lightbox_is_on(): void
    {
        $html = $this->render([
            'url' => '/a.jpg', 'lightbox' => true, 'linkUrl' => 'https://example.test/elsewhere',
        ]);

        $this->assertStringNotContainsString('example.test/elsewhere', $html);
        $this->assertStringNotContainsString('<a href', $html);
    }

    /** And works again the moment the lightbox is switched off. */
    public function test_the_link_works_again_with_the_lightbox_off(): void
    {
        $html = $this->render([
            'url' => '/a.jpg', 'lightbox' => false, 'linkUrl' => 'https://example.test/elsewhere',
        ]);

        $this->assertStringContainsString('href="https://example.test/elsewhere"', $html);
        $this->assertStringNotContainsString('data-lz-gallery', $html);
    }

    /**
     * The URL survives being switched off and on, so turning the lightbox off gives the
     * author their link back rather than an empty field.
     */
    public function test_the_setting_and_the_link_both_survive_the_round_trip(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'image', 'settings' => [
                'url' => '/a.jpg', 'lightbox' => true, 'linkUrl' => 'https://example.test/kept',
            ]]]]],
        ]]));

        $this->assertStringContainsString('lightbox="yes"', $sc);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $settings = $back[0]['columns'][0]['elements'][0]['settings'] ?? [];

        $this->assertTrue($settings['lightbox'] ?? null);
        $this->assertSame('https://example.test/kept', $settings['linkUrl'] ?? null);
    }

    /** Off is not written into the shortcode at all — an absent attribute means no. */
    public function test_off_leaves_nothing_in_the_shortcode(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'image', 'settings' => ['url' => '/a.jpg']]]]],
        ]]));

        $this->assertStringNotContainsString('lightbox', $sc);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $this->assertFalse($back[0]['columns'][0]['elements'][0]['settings']['lightbox'] ?? null);
    }

    /** The panel puts the link fields away rather than leaving two things fighting. */
    public function test_the_panel_hides_the_link_fields(): void
    {
        $sidebar = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/sidebar.blade.php'
        );

        $this->assertStringContainsString('editingElement.settings.lightbox = true', $sidebar,
            'the Image element has no lightbox switch');
        $this->assertStringContainsString('<div v-if="!editingElement.settings.lightbox">', $sidebar,
            'the Link URL field is shown even while the lightbox is on');

        // Link Target hangs off the same condition, and the brackets matter: without them
        // && binds tighter than || and a dynamic link source brings the field back.
        $this->assertStringContainsString(
            'v-if="!editingElement.settings.lightbox && (editingElement.settings.linkUrl || editingElement.settings.link_dynamic_source)"',
            $sidebar
        );
    }

    /**
     * One lightbox on the site, not two.
     *
     * The Gallery had it first. A copy in the Image element would be a second thing to
     * fix every time one of them was wrong.
     */
    public function test_the_gallery_and_the_image_share_one_lightbox(): void
    {
        $root = __DIR__.'/../../../resources/views/';

        foreach (['frontend/builder/elements/image.blade.php', 'frontend/builder/elements/gallery.blade.php'] as $file) {
            $this->assertStringContainsString('components.frontend.lightbox', (string) file_get_contents($root.$file),
                "{$file} no longer uses the shared lightbox");
        }

        // And its script is guarded by a flag the browser checks, not by Blade's
        // render-once directive — which the theme's double render defeats, and which is
        // how the Code Block element once shipped without any script at all.
        $partial = (string) file_get_contents($root.'components/frontend/lightbox.blade.php');
        $this->assertStringContainsString('if(window.__falconLightbox)return;', $partial);
        $this->assertStringNotContainsString('@once', $partial);
    }
}
