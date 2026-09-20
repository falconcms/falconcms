<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Tests\TestCase;

/**
 * A category in a menu points at where that category lives now.
 *
 * The address was written into the item when somebody added it and served forever after. A
 * page item never had this problem — its URL is rebuilt from the post on every render — but a
 * term item kept whatever was stored. So renaming a category's slug, or changing the archive
 * base, left the menu pointing at the old address: the link still arrived, because a rename
 * leaves a redirect behind, but it arrived the long way round and never matched the page it
 * was on, so the item never showed as the current one.
 */
class MenuTermUrlTest extends TestCase
{
    private function menuWithCategory(Category $category): NavigationMenu
    {
        $menu = NavigationMenu::create([
            'name' => 'Term URL Menu',
            'slug' => 'term-url-menu',
            'is_header' => true,
        ]);

        NavigationMenuItem::create([
            'navigation_menu_id' => $menu->id,
            'title' => $category->name,
            // Deliberately stale: this is what was right the day it was added.
            'url' => '/category/'.$category->slug,
            'type' => 'category',
            'object_id' => $category->id,
            'target' => '_self',
            'order' => 0,
        ]);

        forget_nav_menu_cache();
        cache()->flush();

        return $menu;
    }

    private function firstUrl(string $location = 'header'): string
    {
        $items = get_falcon_menu($location);

        return $items[0]->url ?? '';
    }

    public function test_a_term_item_follows_a_renamed_slug(): void
    {
        $category = Category::create(['name' => 'Menu Term', 'slug' => 'menu-term', 'lang_code' => 'en']);
        $this->menuWithCategory($category);

        $category->update(['slug' => 'menu-term-renamed']);
        forget_nav_menu_cache();
        cache()->flush();

        $this->assertSame('/category/menu-term-renamed', $this->firstUrl());
    }

    public function test_the_item_is_current_on_the_page_it_points_at(): void
    {
        $category = Category::create(['name' => 'Current', 'slug' => 'current', 'lang_code' => 'en']);
        $this->menuWithCategory($category);

        $url = $this->firstUrl();
        $this->get('/category/current');

        $this->assertTrue(
            falcon_menu_is_active($url),
            'the menu item did not match the archive it points at'
        );
    }

    public function test_a_custom_link_is_left_exactly_as_it_was_typed(): void
    {
        $menu = NavigationMenu::create([
            'name' => 'Custom Menu', 'slug' => 'custom-menu', 'is_header' => true,
        ]);
        NavigationMenuItem::create([
            'navigation_menu_id' => $menu->id,
            'title' => 'Elsewhere',
            'url' => 'https://example.test/somewhere?a=1',
            'type' => 'custom',
            'target' => '_blank',
            'order' => 0,
        ]);
        forget_nav_menu_cache();
        cache()->flush();

        // Only an internal item is rewritten; a link somebody typed is theirs.
        $this->assertSame('https://example.test/somewhere?a=1', $this->firstUrl());
    }

    public function test_an_item_whose_term_has_gone_keeps_its_stored_address(): void
    {
        $category = Category::create(['name' => 'Doomed', 'slug' => 'doomed', 'lang_code' => 'en']);
        $this->menuWithCategory($category);
        $id = $category->id;
        $category->delete();
        forget_nav_menu_cache();
        cache()->flush();

        // Nothing to resolve against, so a stored address beats a guess. The item is filtered
        // out of the tree entirely in that case, which is the existing behaviour.
        $items = get_falcon_menu('header');
        $this->assertTrue(
            count($items) === 0 || $items[0]->url === '/category/doomed',
            'a menu item whose term was deleted should be dropped or left alone, not rewritten'
        );
        $this->assertNull(Category::find($id));
    }
}
