<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * Smooth scrolling for every link to a section of the page — a builder button, a text link,
 * an image link — not only the landing-page menu's.
 *
 * What it does in the browser (smooth, lands under the sticky header, the reader can take
 * over, the address reads /page/#section) was checked in a real browser; these pin down the
 * parts that break silently: that the script reaches the page, and that the menu scrolls
 * through the same function rather than drifting back to its own.
 */
class AnchorScrollTest extends TestCase
{
    public function test_the_footer_hook_prints_it_once_on_the_frontend(): void
    {
        ob_start();
        do_falcon_action('falcon_footer');
        $html = (string) ob_get_clean();

        $this->assertSame(1, substr_count($html, 'window.__falconAnchorScroll = true'));
    }

    public function test_it_scrolls_frame_by_frame_and_writes_the_slash_before_the_fragment(): void
    {
        $js = view('falcon-cms::components.frontend.anchor-scroll')->render();

        $this->assertStringContainsString('window.falconScrollTo = function', $js);
        $this->assertStringContainsString('requestAnimationFrame(step)', $js);
        // /pricing/#plans, the spelling falcon_anchor_url() gives links on the server.
        $this->assertStringContainsString("path += '/'", $js);
        $this->assertStringContainsString('pushState(null, \'\', url)', $js);
        // Clicks another script already took (off-canvas, table of contents, menu) are left alone.
        $this->assertStringContainsString('e.defaultPrevented', $js);
    }

    public function test_the_menu_scrolls_with_the_same_function(): void
    {
        $js = view('falcon-cms::components.frontend.menu-scrollspy')->render();

        $this->assertStringContainsString('window.falconScrollTo(target, { smooth: smooth })', $js);
        $this->assertStringContainsString('window.falconSectionUrl(item.target.id)', $js);
    }
}
