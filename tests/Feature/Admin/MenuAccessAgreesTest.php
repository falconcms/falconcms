<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\SystemSyncSeeder;
use FalconCms\Core\Http\Middleware\AdminMiddleware;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ReflectionMethod;

/**
 * The sidebar, the Roles screen and the page guard must answer the same question the same way.
 *
 * Every bug in this area has had one shape: a menu is drawn for somebody the page behind it
 * then refuses, or a permission is offered that nothing checks. It has happened with the
 * subscriber's Users menu, with an options page, and with menus a plugin registers — whose
 * pages the middleware could not match at all, so granting the permission changed nothing and
 * opening the menu answered 403.
 *
 * So rather than another test per case, this walks every menu there is and asserts the two
 * sides agree about it.
 */
class MenuAccessAgreesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Unguarded, because that is how seeding actually runs: Laravel's db:seed command wraps
        // the seeder in Model::unguarded(), and the menus table has columns outside $fillable —
        // `params`, which carries the ?type=product that tells Products → All Products apart
        // from Posts. Calling run() directly drops them and invents failures that no site has.
        Model::unguarded(fn () => (new SystemSyncSeeder)->run());
    }

    /** What AdminMiddleware decides for this user and path. */
    private function middlewareAllows(User $user, string $path): bool
    {
        $method = new ReflectionMethod(AdminMiddleware::class, 'canUserAccessUrl');
        $method->setAccessible(true);

        return (bool) $method->invoke(new AdminMiddleware, Request::create($path, 'GET'), $user);
    }

    private function userHolding(array $slugs, string $roleSlug = 'zz-role'): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => 'ZZ', 'description' => 'test']);
        $ids = [];
        foreach ($slugs as $slug) {
            $ids[] = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
        }
        $role->permissions()->sync($ids);

        return User::forceCreate([
            'name' => 'ZZ', 'email' => $roleSlug.'@example.test',
            'password' => 'secret-password', 'role_id' => $role->id,
        ]);
    }

    private function registerNotices(array $extra = []): void
    {
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);
        $registry->addMenuPage($extra + [
            'slug' => 'kitchen-notices',
            'menu_title' => 'Notices',
            'route' => '/admin/notices',
            'capability' => 'access_kitchen_notices',
            'group' => 'Main',
        ]);
    }

    // ── a menu a plugin registered ───────────────────────────────────────────────

    public function test_a_registered_menu_opens_for_a_role_that_was_granted_it(): void
    {
        // The reported bug: the Notices menu was visible, its permission had been ticked in
        // Roles, and the page still answered 403 — because the middleware looked for the menu
        // that owns the path in the menus table alone, and a registered menu is not in it.
        $this->registerNotices();
        $user = $this->userHolding(['access_dashboard', 'access_kitchen_notices']);

        $this->assertTrue($this->middlewareAllows($user, '/admin/notices'));
    }

    public function test_a_registered_menu_is_still_refused_to_a_role_without_it(): void
    {
        $this->registerNotices();
        $user = $this->userHolding(['access_dashboard'], 'zz-bare');

        $this->assertFalse($this->middlewareAllows($user, '/admin/notices'));
    }

    public function test_a_public_registered_menu_opens_for_anyone_signed_in(): void
    {
        $this->registerNotices(['public' => true]);
        $user = $this->userHolding(['access_dashboard'], 'zz-public');

        $this->assertTrue($this->middlewareAllows($user, '/admin/notices'));
    }

    public function test_a_registered_submenu_is_matched_too(): void
    {
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);
        $registry->addMenuPage(['slug' => 'reports', 'menu_title' => 'Reports', 'route' => '#']);
        $registry->addSubmenuPage('reports', [
            'menu_title' => 'Monthly', 'route' => '/admin/reports/monthly', 'capability' => 'access_reports_monthly',
        ]);

        $granted = $this->userHolding(['access_dashboard', 'access_reports_monthly'], 'zz-rep');
        $without = $this->userHolding(['access_dashboard'], 'zz-norep');

        $this->assertTrue($this->middlewareAllows($granted, '/admin/reports/monthly'));
        $this->assertFalse($this->middlewareAllows($without, '/admin/reports/monthly'));
    }

    // ── the sweep ────────────────────────────────────────────────────────────────

    public function test_no_menu_is_ever_drawn_for_someone_the_page_then_refuses(): void
    {
        // For every menu in the sidebar: a user holding exactly that menu's permission must be
        // able to open it. Anything that fails here is a menu that appears and then says no.
        $this->registerNotices();
        $sidebar = new Sidebar;
        $failures = [];

        foreach ($this->everyLeafMenu() as $label => $menu) {
            $permission = $sidebar->getPermission($menu);
            $url = $sidebar->resolveRoute($menu);
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            if (!$path || trim($path, '/') === 'admin' || !str_starts_with(trim($path, '/'), 'admin')) {
                continue;
            }
            if (parse_url($url, PHP_URL_QUERY)) {
                $path .= '?'.parse_url($url, PHP_URL_QUERY);
            }

            $user = $this->userHolding(['access_dashboard', $permission], 'zz-'.md5($label));
            if (!$this->middlewareAllows($user, $path)) {
                $failures[] = "{$label} (needs {$permission}, path {$path})";
            }
        }

        $this->assertSame([], $failures,
            "these menus are drawn for a role the page then refuses:\n  ".implode("\n  ", $failures));
    }

    public function test_holding_only_the_dashboard_opens_only_the_dashboard(): void
    {
        // The other direction: a permission nobody granted must not let anything through.
        $this->registerNotices();
        $user = $this->userHolding(['access_dashboard'], 'zz-only-dash');

        $this->assertTrue($this->middlewareAllows($user, '/admin'));

        foreach (['/admin/settings', '/admin/users', '/admin/posts', '/admin/notices', '/admin/media'] as $path) {
            $this->assertFalse($this->middlewareAllows($user, $path), "{$path} opened without a permission for it");
        }
    }

    /**
     * Every menu that owns a page — database rows and registered ones, parents without children
     * included, children of parents that have them.
     *
     * @return array<string, mixed>
     */
    private function everyLeafMenu(): array
    {
        $out = [];

        foreach (Menu::with('children')->get() as $menu) {
            if ($menu->children->isNotEmpty()) {
                continue;
            }
            $label = $menu->parent_id
                ? (optional(Menu::find($menu->parent_id))->title.' → '.$menu->title)
                : $menu->title;
            $out[$label] = $menu;
        }

        foreach (app(AdminMenu::class)->grouped() as $items) {
            foreach ($items as $item) {
                if ($item->children->isEmpty()) {
                    $out['(registered) '.$item->title] = $item;
                }
                foreach ($item->children as $child) {
                    $out['(registered) '.$item->title.' → '.$child->title] = $child;
                }
            }
        }

        return $out;
    }
}
