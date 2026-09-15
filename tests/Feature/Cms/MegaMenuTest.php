<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Http\Controllers\Admin\CustomizerController;
use FalconCms\Core\Http\Controllers\Admin\MenuManagementController;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * The theme header's own mega menu.
 *
 * A mega menu was previously something you designed in the Layout builder and assigned to a
 * menu item, which only the builder's Menu element ever rendered — the theme's own header
 * showed an ordinary dropdown no matter what. The header can now build one itself out of the
 * sub-items already in the menu.
 *
 * The switch that turns it on lives in the Customizer, and OFF is the default, so most of what
 * follows is about the promise that off changes nothing: the header keeps its dropdowns and the
 * builder route is untouched.
 */
class MegaMenuTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /**
     * A header menu with one top-level item, three sub-items, and grandchildren under two of
     * them — the shape a mega menu is built from.
     */
    private function headerMenu(array $megaAttributes = []): NavigationMenu
    {
        $menu = NavigationMenu::create(['name' => 'Header', 'slug' => 'header-'.uniqid(), 'is_header' => true]);

        $parent = NavigationMenuItem::create($megaAttributes + [
            'navigation_menu_id' => $menu->id, 'title' => 'Catalogue', 'url' => '/catalogue',
            'type' => 'custom', 'order' => 0,
        ]);

        foreach ([['Women', ['Dresses', 'Coats']], ['Men', ['Shirts']], ['Sale', []]] as $i => [$label, $kids]) {
            $child = NavigationMenuItem::create([
                'navigation_menu_id' => $menu->id, 'parent_id' => $parent->id,
                'title' => $label, 'url' => '/'.strtolower($label), 'type' => 'custom', 'order' => $i,
            ]);
            foreach ($kids as $j => $kid) {
                NavigationMenuItem::create([
                    'navigation_menu_id' => $menu->id, 'parent_id' => $child->id,
                    'title' => $kid, 'url' => '/'.strtolower($kid), 'type' => 'custom', 'order' => $j,
                ]);
            }
        }

        forget_nav_menu_cache();

        return $menu;
    }

    /** The theme header, rendered. */
    private function header(): string
    {
        forget_cms_options_cache();
        forget_nav_menu_cache();

        // The namespaced name the layout itself uses, so this renders the package's own copy
        // rather than whatever a host site may have published over it.
        return view('falcon-cms::themes.falcon-theme.partials.header')->render();
    }

    private function layoutSource(): string
    {
        return (string) file_get_contents(
            __DIR__.'/../../../resources/views/themes/falcon-theme/layouts/app.blade.php'
        );
    }

    // ── off by default ───────────────────────────────────────────────────────────

    public function test_the_switch_is_off_until_someone_turns_it_on(): void
    {
        $this->assertSame('0', get_cms_option('theme_mega_menu_enabled', '0'));
    }

    public function test_with_the_switch_off_the_header_renders_an_ordinary_dropdown(): void
    {
        // Even for an item that has been marked for a mega menu: the Customizer switch is what
        // decides, so turning it back off returns every header to what it was.
        $this->headerMenu(['mega_enabled' => true, 'mega_columns' => 4, 'mega_width' => 'full']);
        $this->setCmsOptions(['theme_mega_menu_enabled' => '0']);

        $html = $this->header();

        $this->assertStringNotContainsString('falcon-mega-panel', $html);
        $this->assertStringContainsString('Women', $html, 'the sub-items are still there, as a dropdown');
    }

    public function test_the_mega_css_is_only_written_while_the_switch_is_on(): void
    {
        // Guarded in the layout rather than always emitted, so a site that does not use mega
        // menus does not carry the rules for them on every page.
        $source = $this->layoutSource();

        $this->assertStringContainsString('@if($megaMenuOn)', $source);
        $this->assertMatchesRegularExpression(
            '/@if\(\$megaMenuOn\).*?@media \(min-width: 1024px\).*?falcon-mega-panel/s',
            $source,
            'the mega rules are not inside both the switch and the desktop breakpoint'
        );
    }

    // ── on ───────────────────────────────────────────────────────────────────────

    public function test_an_enabled_item_renders_a_panel_with_the_columns_it_was_given(): void
    {
        $this->headerMenu(['mega_enabled' => true, 'mega_columns' => 4, 'mega_width' => 'site']);
        $this->setCmsOptions(['theme_mega_menu_enabled' => '1']);

        $html = $this->header();

        $this->assertStringContainsString('falcon-mega-panel', $html);
        $this->assertStringContainsString('falcon-mega-site', $html);
        $this->assertStringContainsString('--falcon-mega-cols: 4', $html);

        // A sub-item with children of its own heads a column and lists them; one without is a
        // link standing on its own.
        $this->assertStringContainsString('falcon-mega-heading', $html);
        $this->assertStringContainsString('Dresses', $html);
        $this->assertStringContainsString('Sale', $html);
    }

    public function test_a_custom_width_reaches_the_panel(): void
    {
        $this->headerMenu([
            'mega_enabled' => true, 'mega_width' => 'custom', 'mega_custom_width' => '640px',
        ]);
        $this->setCmsOptions(['theme_mega_menu_enabled' => '1']);

        $html = $this->header();

        $this->assertStringContainsString('falcon-mega-custom', $html);
        $this->assertStringContainsString('--falcon-mega-width: 640px', $html);
    }

    public function test_an_item_with_no_sub_items_never_gets_a_panel(): void
    {
        // There would be nothing to put in the columns, so the switch and the checkbox together
        // still leave it an ordinary link.
        $menu = NavigationMenu::create(['name' => 'Header', 'slug' => 'header-'.uniqid(), 'is_header' => true]);
        NavigationMenuItem::create([
            'navigation_menu_id' => $menu->id, 'title' => 'Contact', 'url' => '/contact',
            'type' => 'custom', 'order' => 0, 'mega_enabled' => true,
        ]);
        forget_nav_menu_cache();
        $this->setCmsOptions(['theme_mega_menu_enabled' => '1']);

        $html = $this->header();

        $this->assertStringNotContainsString('falcon-mega-panel', $html);
        $this->assertStringContainsString('Contact', $html);
    }

    public function test_an_item_that_was_not_enabled_keeps_its_dropdown(): void
    {
        $this->headerMenu(['mega_enabled' => false]);
        $this->setCmsOptions(['theme_mega_menu_enabled' => '1']);

        $this->assertStringNotContainsString('falcon-mega-panel', $this->header());
    }

    public function test_the_third_level_is_reachable_on_a_phone(): void
    {
        // The mobile drawer used to stop at two levels. A mega menu is built out of exactly the
        // third, so without this its links would exist on desktop and nowhere else.
        $this->headerMenu(['mega_enabled' => true]);
        $this->setCmsOptions(['theme_mega_menu_enabled' => '1']);

        $html = $this->header();
        $mobile = substr($html, strpos($html, 'id="mobile-menu"'));

        $this->assertStringContainsString('Dresses', $mobile);
        $this->assertStringNotContainsString('falcon-mega-panel', $mobile, 'the panel itself stays on desktop');
    }

    // ── item border ──────────────────────────────────────────────────────────────

    public function test_a_line_under_each_item_is_drawn_under_every_link(): void
    {
        // It was exempting the last link in a column, which read as tidier typography and was
        // a bug: a sub-item with no children of its own is a column holding ONE link, so in the
        // commonest menu — a top-level item with a flat list of sub-items — every link was the
        // last one and the setting drew nothing whatsoever.
        $source = $this->layoutSource();

        $this->assertMatchesRegularExpression(
            "/megaItemBorder === 'bottom'\).*?falcon-mega-link \{\s*border-bottom:/s",
            $source,
            'the "line under each item" setting no longer writes a border-bottom'
        );
        $this->assertStringNotContainsString(
            'li:last-child > .falcon-mega-link { border-bottom: 0; }',
            $source,
            'the last link in a column is exempt again, so a one-link column shows no line'
        );
    }

    public function test_a_sub_item_without_children_is_still_a_link_the_border_can_reach(): void
    {
        // The shape the bug above hid in: three sub-items, no third level, so each column is a
        // single link. They have to be real .falcon-mega-link elements for any item styling to
        // apply to them at all.
        $menu = NavigationMenu::create(['name' => 'Header', 'slug' => 'header-'.uniqid(), 'is_header' => true]);
        $parent = NavigationMenuItem::create([
            'navigation_menu_id' => $menu->id, 'title' => 'Catalogue', 'url' => '/catalogue',
            'type' => 'custom', 'order' => 0, 'mega_enabled' => true, 'mega_columns' => 3,
        ]);
        foreach (['One', 'Two', 'Three'] as $i => $t) {
            NavigationMenuItem::create([
                'navigation_menu_id' => $menu->id, 'parent_id' => $parent->id,
                'title' => $t, 'url' => '/'.strtolower($t), 'type' => 'custom', 'order' => $i,
            ]);
        }
        forget_nav_menu_cache();
        $this->setCmsOptions(['theme_mega_menu_enabled' => '1']);

        $html = $this->header();

        $this->assertSame(3, substr_count($html, 'falcon-mega-link'));
        $this->assertStringNotContainsString('falcon-mega-heading', $html,
            'nothing here has children of its own, so no column should claim a heading');
    }

    // ── saving ───────────────────────────────────────────────────────────────────

    public function test_the_saved_columns_and_width_are_clamped_to_what_can_be_rendered(): void
    {
        // The column count drives a CSS grid and the width picks a class; a hand-edited payload
        // must not be able to produce a panel nobody can read.
        $menu = NavigationMenu::create(['name' => 'Header', 'slug' => 'header-'.uniqid(), 'is_header' => true]);

        $request = Request::create('/admin/menus/'.$menu->id, 'PUT', [
            'name' => 'Header',
            'menu_items' => json_encode([[
                'title' => 'Catalogue', 'url' => '/catalogue', 'type' => 'custom',
                'mega_enabled' => true, 'mega_columns' => 99, 'mega_width' => 'nonsense',
                'mega_custom_width' => '640px',
                'children' => [['title' => 'Women', 'url' => '/women', 'type' => 'custom']],
            ]]),
        ]);
        $request->setUserResolver(fn () => $this->administrator());

        app(MenuManagementController::class)->update($request, $menu->id);

        $saved = NavigationMenuItem::where('navigation_menu_id', $menu->id)->whereNull('parent_id')->first();

        $this->assertSame(6, (int) $saved->mega_columns);
        $this->assertSame('site', $saved->mega_width);
        $this->assertSame('640px', $saved->mega_custom_width);
        $this->assertTrue((bool) $saved->mega_enabled);
    }

    // ── the Customizer controls ──────────────────────────────────────────────────

    public function test_the_customizer_offers_the_switch_and_its_options(): void
    {
        $sections = new ReflectionMethod(CustomizerController::class, 'sections');
        $sections->setAccessible(true);
        $fields = $sections->invoke(app(CustomizerController::class))['menu']['fields'];

        $this->assertArrayHasKey('theme_mega_menu_enabled', $fields);
        $this->assertSame('toggle', $fields['theme_mega_menu_enabled']['type']);
        $this->assertSame('0', $fields['theme_mega_menu_enabled']['default'], 'the switch must start off');

        // Everything else in the group is greyed out until the switch is on, so the section
        // cannot be read as a set of controls that do nothing.
        foreach ([
            'theme_mega_menu_typo' => 'typography',
            'theme_mega_menu_bg' => 'color',
            'theme_mega_menu_heading_color' => 'color',
            'theme_mega_menu_link_color' => 'color',
            'theme_mega_menu_link_hover_color' => 'color',
            'theme_mega_menu_item_border' => 'select',
            'theme_mega_menu_item_border_color' => 'color',
        ] as $key => $type) {
            $this->assertArrayHasKey($key, $fields, "{$key} is missing from the Menu section");
            $this->assertSame($type, $fields[$key]['type'], "{$key} is the wrong kind of control");
            $this->assertSame('theme_mega_menu_enabled', $fields[$key]['depends'] ?? null,
                "{$key} does not follow the Mega Menu switch");
        }
    }
}
