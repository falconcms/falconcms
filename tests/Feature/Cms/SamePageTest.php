<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * Do two URLs point at the same page?
 *
 * Two features hang on this answer. A menu marks its current item with it, and
 * Previous/Next finds where the page sits in a menu with it — so a wrong answer either
 * highlights the wrong item or, worse, silently gives a page no neighbours at all with
 * nothing on the page to say why.
 *
 * The reason it needs care is that the two sides are never spelled the same way. What an
 * editor pastes into a menu is whatever was on the clipboard — absolute one day, relative
 * the next, with or without a trailing slash — while the page being rendered always knows
 * its own, fully qualified. Comparing them as strings almost never said yes.
 */
class SamePageTest extends TestCase
{
    /** The same page, however either side happens to be spelled. */
    public function test_the_same_page_spelled_differently(): void
    {
        foreach ([
            ['/docs', 'https://example.test/docs'],
            ['/docs/', 'https://example.test/docs'],
            ['/docs', 'https://example.test/docs/'],
            ['https://example.test/docs', '/docs'],
            ['/docs#install', '/docs'],
            ['/docs?page=2', '/docs'],
        ] as [$a, $b]) {
            $this->assertTrue(falcon_same_page($a, $b), "{$a} and {$b} are the same page");
        }
    }

    /** The site root, which is the one path that is only a slash. */
    public function test_the_home_page(): void
    {
        $this->assertTrue(falcon_same_page('/', 'https://example.test'));
        $this->assertTrue(falcon_same_page('/', 'https://example.test/'));
        $this->assertFalse(falcon_same_page('/', 'https://example.test/docs'));
    }

    /** Different pages, including the one that merely starts the same way. */
    public function test_different_pages(): void
    {
        $this->assertFalse(falcon_same_page('/docs/install', '/docs'));
        $this->assertFalse(falcon_same_page('/docs', '/documentation'));
    }

    /**
     * A link to another host is a link off the site, so it is never the page you are on
     * however its path reads. Two sites both having a /docs is not a coincidence worth
     * being wrong about.
     */
    public function test_another_host_is_never_the_current_page(): void
    {
        $this->assertFalse(falcon_same_page('https://elsewhere.test/docs', 'https://example.test/docs'));

        // A relative link is on this site, which is what the other side is saying too.
        $this->assertTrue(falcon_same_page('/docs', 'https://example.test/docs'));
    }

    /**
     * A bare # is a menu item with no link — usually a parent that only opens a submenu.
     * Treating it as the current page would highlight it on every page of the site.
     */
    public function test_a_bare_anchor_is_not_a_page(): void
    {
        $this->assertFalse(falcon_same_page('#', 'https://example.test/docs'));
        $this->assertFalse(falcon_same_page('', 'https://example.test/docs'));
        $this->assertFalse(falcon_same_page(null, 'https://example.test/docs'));
        $this->assertFalse(falcon_same_page('/docs', ''));
    }

    /**
     * An anchor or a query with no path is a place on the page you are already reading.
     * These used to read as the site root, so on the home page every custom menu item
     * saved as "#pricing" lit up at once — the whole menu looked current.
     */
    public function test_an_item_with_only_an_anchor_or_query_is_not_a_page(): void
    {
        foreach (['#pricing', '#top', '?tab=2', '?s=laravel'] as $item) {
            $this->assertFalse(
                falcon_same_page($item, 'https://example.test'),
                "{$item} must not match the home page"
            );
            $this->assertFalse(
                falcon_same_page($item, 'https://example.test/docs'),
                "{$item} must not match an inner page"
            );
        }
    }

    /** A bare host has no path written down, but it is the home page all the same. */
    public function test_a_bare_host_is_the_home_page(): void
    {
        $this->assertTrue(falcon_same_page('https://example.test', 'https://example.test/'));
        $this->assertFalse(falcon_same_page('https://example.test', 'https://example.test/docs'));
    }

    /** A path typed without its leading slash is the same page as one with it. */
    public function test_a_path_without_a_leading_slash(): void
    {
        $this->assertTrue(falcon_same_page('docs', 'https://example.test/docs'));
        $this->assertTrue(falcon_same_page('docs/install', '/docs/install'));
        $this->assertFalse(falcon_same_page('docs', '/documentation'));
    }
}
