<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Http\Controllers\Admin\RoleController;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * A menu a plugin or theme registers in code, and who is allowed to see it.
 *
 * `falcon_add_menu_page()` puts an entry in the sidebar without touching the menus table, and
 * Users → Roles built its checkboxes from that table alone — so a package could add a menu
 * that no administrator could grant to anybody. The capability existed only in the package's
 * own source, and the one screen for handing out capabilities never mentioned it.
 *
 * Registered menus are listed there now. A package that does not want that says so: `public`
 * for something everyone should reach, `show_in_roles => false` for a capability granted
 * elsewhere.
 */
class RegisteredMenuPermissionTest extends TestCase
{
    private function registry(): AdminMenu
    {
        // A fresh registry per test: it fires the registration hook once and caches, so a
        // shared one would carry the previous test's menus.
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);

        return $registry;
    }

    /** @return array<string,array> the Roles screen's own checkbox tree */
    private function rolesScreenTree(): array
    {
        $method = new ReflectionMethod(RoleController::class, 'getDynamicPermissions');
        $method->setAccessible(true);

        return $method->invoke(app(RoleController::class))['dynamicPermissions'];
    }

    /** Every permission slug the Roles screen offers, at any depth. */
    private function offeredSlugs(): array
    {
        $slugs = [];
        foreach ($this->rolesScreenTree() as $items) {
            foreach ($items as $item) {
                $slugs[] = $item['slug'];
                foreach ($item['children'] ?? [] as $child) {
                    $slugs[] = $child['slug'];
                }
            }
        }

        return $slugs;
    }

    public function test_a_registered_menu_can_be_granted_from_the_roles_screen(): void
    {
        $registry = $this->registry();
        $registry->addMenuPage([
            'slug' => 'reports',
            'menu_title' => 'Reports',
            'capability' => 'manage_reports',
            'group' => 'Extend',
        ]);
        $registry->addSubmenuPage('reports', ['menu_title' => 'Monthly', 'capability' => 'manage_reports_monthly']);

        $tree = $this->rolesScreenTree();

        $this->assertArrayHasKey('Extend', $tree, 'the package\'s group is missing from Roles');
        $this->assertSame('Reports', $tree['Extend'][0]['title']);
        $this->assertSame('manage_reports', $tree['Extend'][0]['slug']);
        $this->assertSame(
            [['title' => 'Monthly', 'slug' => 'manage_reports_monthly']],
            $tree['Extend'][0]['children']
        );
    }

    public function test_a_menu_registered_without_a_capability_gets_one_of_its_own(): void
    {
        // It used to fall back to access_dashboard, which meant every package menu belonged to
        // whoever could see the dashboard and could not be granted or withheld on its own —
        // and would have been a meaningless checkbox.
        $registry = $this->registry();
        $registry->addMenuPage(['slug' => 'field-reports', 'menu_title' => 'Field Reports']);
        $registry->addSubmenuPage('field-reports', ['menu_title' => 'By Region']);

        $item = $this->rolesScreenTree()['Extend'][0];

        $this->assertSame('access_field_reports', $item['slug']);
        $this->assertSame('access_field_reports_by_region', $item['children'][0]['slug']);
    }

    public function test_a_public_menu_is_not_listed_because_there_is_nothing_to_grant(): void
    {
        $registry = $this->registry();
        $registry->addMenuPage(['slug' => 'handbook', 'menu_title' => 'Handbook', 'public' => true]);

        $this->assertNotContains('access_handbook', $this->offeredSlugs());
    }

    public function test_a_menu_can_keep_its_capability_and_stay_out_of_the_list(): void
    {
        $registry = $this->registry();
        $registry->addMenuPage([
            'slug' => 'billing',
            'menu_title' => 'Billing',
            'capability' => 'manage_settings',
            'show_in_roles' => false,
        ]);

        $this->assertNotContains('manage_settings', array_column($this->rolesScreenTree()['Extend'] ?? [], 'slug'));
    }

    public function test_the_core_menus_are_still_listed_alongside_them(): void
    {
        // The registered ones are merged in, not swapped for what was already there.
        $this->registry()->addMenuPage(['slug' => 'reports', 'menu_title' => 'Reports', 'group' => 'Extend']);

        $slugs = $this->offeredSlugs();

        $this->assertContains('access_reports', $slugs);
        $this->assertGreaterThan(1, count($slugs), 'the database-driven menus have gone missing');
    }

    // ── who sees it in the sidebar ───────────────────────────────────────────────

    private function subscriber(): User
    {
        return User::forceCreate([
            'name' => 'Sub', 'email' => 'sub@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);
    }

    public function test_a_public_menu_is_visible_to_a_user_who_holds_nothing(): void
    {
        $sidebar = new Sidebar;
        $this->actingAs($this->subscriber());

        $this->assertTrue($sidebar->canSee((object) ['title' => 'Handbook', 'public' => true, 'permission' => 'access_handbook']));
        $this->assertFalse($sidebar->canSee((object) ['title' => 'Billing', 'public' => false, 'permission' => 'access_billing']));
    }

    public function test_a_database_menu_is_unaffected_by_the_public_flag(): void
    {
        // Menu rows have no such column, so reading it must not throw or turn everything public.
        $sidebar = new Sidebar;
        $this->actingAs($this->subscriber());

        $menu = new Menu(['title' => 'Settings', 'permission' => 'manage_settings']);

        $this->assertFalse($sidebar->canSee($menu));
    }
}
