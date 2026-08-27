<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The canvas viewport itself, independent of any one element.
 *
 * "Desktop" preview only means what the front end's desktop media query means when the
 * canvas is actually as wide as that breakpoint. At 100% of the panel it usually isn't —
 * the sidebar and the design panel both eat into the same screen a real visitor's browser
 * never has to share — so desktop-only layouts (a menu with enough items, a row of columns
 * that never stacks) were given less room than they will ever actually have on the real
 * site, and content that fits perfectly for a real visitor spilled out past its own column
 * in the editor. Nothing was wrong with the column or the element; the ruler was too short.
 */
class CanvasLayoutTest extends TestCase
{
    private function stylesSource(): string
    {
        return file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/styles.blade.php'
        );
    }

    /**
     * The desktop canvas must be floored at the real desktop breakpoint, not left at
     * width:100% of whatever the panel happens to have free.
     */
    public function test_the_desktop_canvas_is_floored_at_the_real_desktop_breakpoint(): void
    {
        $styles = $this->stylesSource();

        $this->assertStringContainsString('.canvas-container.desktop', $styles,
            'there is no dedicated rule for the desktop canvas width');

        $start = strpos($styles, '.canvas-container.desktop');
        $block = substr($styles, $start, 200);

        $this->assertStringContainsString('min-width', $block,
            'the desktop canvas has no minimum width — it can still be narrower than a real desktop visitor ever sees');
        $this->assertStringContainsString("get_cms_option('theme_medium_screen_breakpoint'", $block,
            'the desktop floor is not tied to the theme\'s own desktop breakpoint, so it can drift out of sync with it');
    }

    /**
     * A floor that makes the canvas wider than the panel would need a permanent horizontal
     * scrollbar to be reachable that way — and a first cut did exactly that, via
     * `overflow-x: auto` here. It surfaced two things at once: the rare, real case (a
     * desktop-floored canvas genuinely wider than the panel) and an unrelated few-px
     * rounding slop that had always been there, silently clipped, and had never been a
     * problem. The fix for "the canvas overflows its column" was `canvasScale`/`zoom` in
     * scripts.blade.php, not a scrollbar — a correctly computed zoom leaves nothing to
     * scroll to. So this stays clipped: the alternative is a permanent scrollbar under every
     * single edit, for a few pixels of slop nobody was ever able to see anyway.
     */
    public function test_the_canvas_area_does_not_grow_a_permanent_scrollbar(): void
    {
        $styles = $this->stylesSource();

        $start = strpos($styles, '.builder-canvas-area {');
        $this->assertNotFalse($start, 'the canvas scroll container rule has moved');

        $block = substr($styles, $start, 900);

        $this->assertStringContainsString('overflow-x: hidden', $block,
            'the canvas area scrolls horizontally again — the desktop floor needs zoom-to-fit to prevent overflow, not a permanent scrollbar');
    }

    /**
     * The desktop floor above only avoids a scrollbar if something actually shrinks the
     * oversized canvas back down to fit the panel. `zoom` (not `transform: scale`) is the
     * right tool: the browser still lays the subtree out at its full, unscaled width
     * internally — so children size themselves as they would for a real desktop — while the
     * space it occupies in the document shrinks, which `transform: scale` never affects.
     */
    public function test_the_canvas_zooms_to_fit_when_the_panel_is_narrower_than_the_floor(): void
    {
        $scripts = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $this->assertStringContainsString('const canvasScale = ref(1)', $scripts,
            'the desktop zoom-to-fit state is missing');
        $this->assertStringContainsString('const updateCanvasScale', $scripts,
            'nothing computes the zoom-to-fit scale');
        $this->assertStringContainsString('zoom: canvasScale.value', $scripts,
            'the computed scale is never applied to the canvas — canvasStyle does not read canvasScale');
    }
}
