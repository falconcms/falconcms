<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Closing two ways to become an administrator through the Roles screen:
 *
 *  1. The "admin" slug. isAdmin() treats super-admin, administrator AND admin as administrator
 *     slugs, but only the first two were protected: anyone allowed on the Roles screen could
 *     create a role called "admin", or re-slug their own role to it, and so become an
 *     administrator. All three are reserved now (Role::ADMIN_SLUGS).
 *  2. syncPermissions() created any slug it was sent (Permission::firstOrCreate) and a role
 *     manager could grant any permission, including ones they do not hold (the plugin
 *     installer). Only permissions the Roles screen offers are accepted, and a non-admin may
 *     only add permissions they hold themselves.
 */
class RoleEscalationHardeningTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // The menus decide both who reaches a screen (AdminMiddleware) and what the Roles
        // screen offers as permissions, so the real ones are seeded.
        (new MenuSeeder)->run();
    }

    private function roleId(string $slug): int
    {
        return (int) DB::table('roles')->where('slug', $slug)->value('id');
    }

    private function admin(): User
    {
        return $this->makeUser(['role_id' => $this->roleId('administrator')]);
    }

    private function permission(string $slug): int
    {
        return Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
    }

    /** A non-admin who may open the Roles screen (and optionally more). */
    private function roleManager(array $extra = []): User
    {
        $role = Role::create(['name' => 'Role Manager', 'slug' => 'role-manager-'.uniqid()]);
        foreach (array_merge(['manage_roles', 'access_roles_users', 'manage_users', 'access_all_users_users', 'access_users'], $extra) as $slug) {
            DB::table('role_permission')->insert(['role_id' => $role->id, 'permission_id' => $this->permission($slug)]);
        }

        return $this->makeUser(['role_id' => $role->id]);
    }

    private function form(array $extra = []): array
    {
        return array_merge(['name' => 'Test role', 'slug' => 'test-role', 'description' => '', 'permissions' => []], $extra);
    }

    public function test_the_role_manager_really_reaches_the_roles_screen(): void
    {
        $manager = $this->roleManager();
        $this->assertFalse($manager->isAdmin());
        $this->actingAs($manager)->get('/admin/roles')->assertOk();
    }

    public function test_no_one_can_create_a_role_with_an_administrator_slug(): void
    {
        foreach ([$this->admin(), $this->roleManager()] as $who) {
            foreach (['admin', 'Admin', ' ADMIN ', 'administrator', 'super-admin', 'Super Admin'] as $slug) {
                $before = Role::count();
                $this->actingAs($who)->post('/admin/roles', $this->form(['name' => 'X '.$slug, 'slug' => $slug]))
                    ->assertSessionHasErrors('slug');
                $this->assertSame($before, Role::count(), "role '{$slug}' was created");
            }
        }
        $this->assertSame(0, Role::where('slug', 'admin')->count());
    }

    public function test_no_one_can_rename_a_role_to_an_administrator_slug(): void
    {
        $editor = Role::where('slug', 'editor')->firstOrFail();
        foreach ([$this->admin(), $this->roleManager()] as $who) {
            foreach (['admin', 'ADMIN', 'administrator', 'super-admin'] as $slug) {
                $this->actingAs($who)->put("/admin/roles/{$editor->id}", $this->form(['name' => 'Editor', 'slug' => $slug]))
                    ->assertSessionHasErrors('slug');
                $this->assertSame('editor', $editor->fresh()->slug, "renamed to '{$slug}'");
            }
        }

        // The manager's own role, the shortest way to become an administrator.
        $manager = $this->roleManager();
        $own = Role::findOrFail($manager->getAttribute('role_id'));
        $this->actingAs($manager)->put("/admin/roles/{$own->id}", $this->form(['name' => 'Me', 'slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->assertFalse($manager->fresh()->isAdmin());
    }

    public function test_a_legacy_admin_role_keeps_working_and_keeps_its_slug(): void
    {
        // An installation that created an "admin" role by hand before this release.
        $legacy = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $member = $this->makeUser(['role_id' => $legacy->id]);
        $this->assertTrue($member->isAdmin(), 'existing admin-slug roles stay administrators');

        $this->actingAs($this->admin())->put("/admin/roles/{$legacy->id}", $this->form(['name' => 'Site admins', 'slug' => 'something-else']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Site admins', 'admin'], [$legacy->fresh()->name, $legacy->fresh()->slug], 'renamed, never re-slugged');
    }

    public function test_unknown_permission_identifiers_are_refused_and_never_created(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/roles', $this->form(['slug' => 'with-junk', 'permissions' => ['totally_made_up_permission']]))
            ->assertSessionHasErrors('permissions');
        $this->assertNull(Role::where('slug', 'with-junk')->first());
        $this->assertNull(Permission::where('slug', 'totally_made_up_permission')->first(), 'no permission row was created');

        $editor = Role::where('slug', 'editor')->firstOrFail();
        $before = $editor->permissions()->pluck('slug')->sort()->values()->all();
        $this->actingAs($admin)->put("/admin/roles/{$editor->id}", $this->form(['name' => 'Editor', 'slug' => 'editor', 'permissions' => array_merge($before, ['invented_super_power'])]))
            ->assertSessionHasErrors('permissions');
        $this->assertSame($before, $editor->fresh()->permissions()->pluck('slug')->sort()->values()->all(), 'nothing changed');
        $this->assertNull(Permission::where('slug', 'invented_super_power')->first());
    }

    public function test_a_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $manager = $this->roleManager();
        $own = Role::findOrFail($manager->getAttribute('role_id'));
        $held = $own->permissions()->pluck('slug')->all();

        foreach (['manage_plugins', 'manage_settings', 'access_themes'] as $power) {
            $this->assertFalse($manager->hasPermission($power));
            $this->actingAs($manager)->put("/admin/roles/{$own->id}", $this->form(['name' => 'Me', 'slug' => $own->slug, 'permissions' => array_merge($held, [$power])]))
                ->assertSessionHasErrors('permissions');
            $this->assertFalse($manager->fresh()->hasPermission($power), "{$power} was granted");
            // Nor to a brand-new role.
            $this->actingAs($manager)->post('/admin/roles', $this->form(['slug' => 'new-'.$power, 'permissions' => [$power]]))->assertSessionHasErrors('permissions');
            $this->assertNull(Role::where('slug', 'new-'.$power)->first());
        }

        // What they hold, they may pass on.
        $this->actingAs($manager)->post('/admin/roles', $this->form(['slug' => 'helpers', 'permissions' => ['access_roles_users']]))->assertSessionHasNoErrors();
        $this->assertSame(['access_roles_users'], Role::where('slug', 'helpers')->firstOrFail()->permissions()->pluck('slug')->all());
    }

    public function test_an_administrator_still_grants_every_registered_permission(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/roles', $this->form(['slug' => 'plugin-people', 'permissions' => ['manage_plugins', 'access_roles_users']]))
            ->assertSessionHasNoErrors();
        $role = Role::where('slug', 'plugin-people')->firstOrFail();
        $this->assertEqualsCanonicalizing(['manage_plugins', 'access_roles_users'], $role->permissions()->pluck('slug')->all());
    }

    public function test_registered_custom_permissions_from_code_menus_and_options_pages_are_accepted(): void
    {
        // A plugin's menu registered in code (as RegisteredMenuPermissionTest does it).
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);
        $registry->addMenuPage(['slug' => 'acme-reports', 'menu_title' => 'Acme Reports', 'capability' => 'acme_view_reports', 'group' => 'Extend']);
        config(['falcon-options.pages' => ['acme' => ['title' => 'Acme options']]]);

        $this->actingAs($this->admin())->post('/admin/roles', $this->form(['slug' => 'acme-staff', 'permissions' => ['acme_view_reports', 'manage_options_acme']]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['acme_view_reports', 'manage_options_acme'],
            Role::where('slug', 'acme-staff')->firstOrFail()->permissions()->pluck('slug')->all());
    }

    public function test_super_admin_and_administrator_protection_is_unchanged(): void
    {
        $super = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $this->actingAs($this->admin())->put("/admin/roles/{$super->id}", $this->form(['name' => 'Super Admin', 'slug' => 'super-admin']))->assertForbidden();

        $administrator = Role::where('slug', 'administrator')->firstOrFail();
        $this->actingAs($this->makeUser(['role_id' => $super->id]))->put("/admin/roles/{$administrator->id}", $this->form(['name' => 'Admins', 'slug' => 'renamed-admins']))
            ->assertSessionHasNoErrors();
        $this->assertSame('administrator', $administrator->fresh()->slug, 'system role keeps its slug');
    }

    public function test_only_an_administrator_can_create_an_administrator_account(): void
    {
        $manager = $this->roleManager(['access_add_new_users']);
        $admins = [$this->roleId('administrator')];
        $form = fn (string $u, array $roles) => ['username' => $u, 'name' => $u, 'email' => "{$u}@example.test", 'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1', 'roles' => $roles];

        // Primary role, and an extra role (role_user) — both are how FalconCMS decides isAdmin().
        foreach (['direct' => $admins, 'extra' => [$this->roleId('editor'), $this->roleId('administrator')]] as $name => $roles) {
            $this->actingAs($manager)->post('/admin/users', $form('mgr'.$name, $roles));
            $this->assertNull(User::where('email', "mgr{$name}@example.test")->first(), "manager created an administrator ({$name})");
        }

        $this->actingAs($this->admin())->post('/admin/users', $form('byadmin', [$this->roleId('editor'), $this->roleId('administrator')]));
        $created = User::where('email', 'byadmin@example.test')->firstOrFail();
        $this->assertTrue($created->isAdmin(), 'role_user administrator counts');

        // A manager can still create ordinary accounts.
        $this->actingAs($manager)->post('/admin/users', $form('plainuser', [$this->roleId('editor')]));
        $this->assertNotNull(User::where('email', 'plainuser@example.test')->first());
    }

    public function test_admin_by_role_id_and_by_role_user_both_count(): void
    {
        $viaPivot = $this->makeUser(['role_id' => $this->roleId('subscriber')]);
        DB::table('role_user')->insert(['user_id' => $viaPivot->id, 'role_id' => $this->roleId('administrator')]);
        $this->assertTrue($viaPivot->fresh()->isAdmin());
        $this->assertTrue($this->admin()->isAdmin());
        $this->assertFalse($this->makeUser(['role_id' => $this->roleId('editor')])->isAdmin());
    }
}
