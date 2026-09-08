<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * Which item does the theme's header mark as the current page?
 *
 * The theme header and the Layout builder's Menu element each answered this for
 * themselves, and disagreed: the builder's copy read an anchor-only item ("#pricing") as
 * the site root, so on the home page every custom link in the menu was highlighted at
 * once. Both now go through falcon_same_page(), so a menu highlights the same way
 * whichever header is drawing it — see {@see SamePageTest} for the comparison itself.
 */
class MenuIsActiveTest extends TestCase
{
    private function onPage(string $url): void
    {
        $this->get($url);
    }

    public function test_the_current_page_is_active(): void
    {
        $this->onPage('/docs');

        $this->assertTrue(falcon_menu_is_active('/docs'));
        $this->assertTrue(falcon_menu_is_active('/docs/'), 'a trailing slash is the same page');
        $this->assertTrue(falcon_menu_is_active(url('/docs')), 'so is the fully qualified form');
    }

    public function test_another_page_is_not_active(): void
    {
        $this->onPage('/docs');

        $this->assertFalse(falcon_menu_is_active('/about'));
        $this->assertFalse(falcon_menu_is_active('/docs/install'));
    }

    public function test_anchor_only_items_are_never_active(): void
    {
        // The reported bug: on the home page, custom links saved as anchors all lit up.
        $this->onPage('/');

        foreach (['#pricing', '#features', '#contact', '#', '?tab=2'] as $item) {
            $this->assertFalse(
                falcon_menu_is_active($item),
                "{$item} is a place on this page, not a page — it must not be marked current"
            );
        }
    }

    public function test_the_home_item_is_active_on_the_home_page(): void
    {
        $this->onPage('/');

        $this->assertTrue(falcon_menu_is_active('/'));
        $this->assertTrue(falcon_menu_is_active(url('/')));
        $this->assertTrue(falcon_menu_is_active(rtrim(url('/'), '/')), 'a bare host is the home page');
    }

    public function test_a_link_to_another_site_is_never_active(): void
    {
        $this->onPage('/docs');

        $this->assertFalse(falcon_menu_is_active('https://elsewhere.test/docs'));
    }

    public function test_an_empty_url_is_not_active(): void
    {
        $this->onPage('/docs');

        $this->assertFalse(falcon_menu_is_active(''));
        $this->assertFalse(falcon_menu_is_active(null));
    }
}
