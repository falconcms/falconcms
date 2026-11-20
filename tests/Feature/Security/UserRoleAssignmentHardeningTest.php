<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * User management must not be a way to a stronger account (Role::assignmentError).
 *
 * Before: a non-admin with manage_users + Add New could create an account with any
 * non-administrator role, including one holding permissions they lack (the plugin installer),
 * then sign in as it. And they could change the password or email of any account but a Super
 * Admin's, an administrator's included. Choosing the registration form's default role was
 * the same assignment, through Settings.
 */
class UserRoleAssignmentHardeningTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // The menus decide who reaches a screen (AdminMiddleware), so the real ones are seeded.
        (new MenuSeeder)->run();
    }

    private function roleId(string $slug): int
    {
        return (int) DB::table('roles')->where('slug', $slug)->value('id');
    }

    private function permission(string $slug): int
    {
        return Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
    }

    /** A role holding exactly $permissions. */
    private function role(string $slug, array $permissions): Role
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        foreach ($permissions as $p) {
            DB::table('role_permission')->insert(['role_id' => $role->id, 'permission_id' => $this->permission($p)]);
        }

        return $role;
    }

    private const MANAGER = ['manage_users', 'access_users', 'access_all_users_users', 'access_add_new_users', 'manage_posts'];

    /** A non-admin user manager: may list, create and edit users, and write posts. */
    private function manager(array $extra = []): User
    {
        $role = $this->role('user-manager-'.uniqid(), array_merge(self::MANAGER, $extra));

        return $this->makeUser(['role_id' => $role->id]);
    }

    private function admin(string $slug = 'administrator'): User
    {
        if ($slug === 'super-admin') {
            Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        }

        return $this->makeUser(['role_id' => $this->roleId($slug)]);
    }

    private function newUserForm(string $name, array $roleIds): array
    {
        return ['username' => $name, 'name' => $name, 'email' => "{$name}@example.test",
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1', 'roles' => $roleIds];
    }

    private function editForm(User $user, array $extra = []): array
    {
        return array_merge(['username' => $user->getAttribute('username') ?: 'u'.$user->getKey(), 'name' => (string) $user->getAttribute('name'),
            'email' => (string) $user->getAttribute('email')], $extra);
    }

    public function test_a_user_manager_cannot_create_an_account_with_a_role_holding_a_permission_they_lack(): void
    {
        $manager = $this->manager();
        $plugins = $this->role('plugin-people', ['manage_plugins']);

        $this->actingAs($manager)->post('/admin/users', $this->newUserForm('pluginguy', [$plugins->id]))
            ->assertSessionHas('error');
        $this->assertNull(User::where('email', 'pluginguy@example.test')->first());

        // A role that merely adds one power on top of the manager's own is refused as well.
        $almost = $this->role('almost-manager', array_merge(self::MANAGER, ['manage_settings']));
        $this->actingAs($manager)->post('/admin/users', $this->newUserForm('almost', [$almost->id]))->assertSessionHas('error');
        $this->assertNull(User::where('email', 'almost@example.test')->first());
    }

    public function test_an_extra_role_cannot_carry_what_the_primary_role_may_not(): void
    {
        $manager = $this->manager();
        $harmless = $this->role('writers', ['manage_posts']);
        $plugins = $this->role('plugin-people', ['manage_plugins']);

        // Either order: the first id becomes users.role_id, the rest go to role_user.
        foreach (['primary' => [$plugins->id, $harmless->id], 'extra' => [$harmless->id, $plugins->id]] as $name => $roles) {
            $this->actingAs($manager)->post('/admin/users', $this->newUserForm('mix'.$name, $roles))->assertSessionHas('error');
            $this->assertNull(User::where('email', "mix{$name}@example.test")->first(), "created via {$name}");
        }
        $this->assertSame(0, DB::table('role_user')->where('role_id', $plugins->id)->count());

        // Neither may two roles that are each harmless but together grant what the manager lacks.
        $a = $this->role('half-a', ['manage_posts', 'manage_settings']);
        $this->actingAs($manager)->post('/admin/users', $this->newUserForm('halves', [$harmless->id, $a->id]))->assertSessionHas('error');
        $this->assertNull(User::where('email', 'halves@example.test')->first());
    }

    public function test_a_user_manager_still_creates_accounts_within_their_own_permissions(): void
    {
        $manager = $this->manager();
        $writers = $this->role('writers', ['manage_posts']);
        $empty = $this->role('readers', []);
        $peers = $this->role('peer-managers', self::MANAGER);

        $this->actingAs($manager)->post('/admin/users', $this->newUserForm('writer', [$writers->id, $empty->id]))
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $writer = User::where('email', 'writer@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing([$writers->id, $empty->id], $writer->cmsRoleIds());

        $this->actingAs($manager)->post('/admin/users', $this->newUserForm('peer', [$peers->id]))->assertSessionMissing('error');
        $this->assertNotNull(User::where('email', 'peer@example.test')->first());
    }

    public function test_a_user_manager_cannot_edit_a_stronger_account(): void
    {
        $manager = $this->manager();
        $strong = $this->makeUser(['role_id' => $this->role('plugin-people', ['manage_plugins'])->id, 'username' => 'strong']);
        $admin = $this->makeUser(['role_id' => $this->roleId('administrator'), 'username' => 'theadmin']);
        // Administrator through role_user only, a subscriber by role_id.
        $pivotAdmin = $this->makeUser(['role_id' => $this->roleId('subscriber'), 'username' => 'pivotadmin']);
        DB::table('role_user')->insert(['user_id' => $pivotAdmin->id, 'role_id' => $this->roleId('administrator')]);

        foreach ([$strong, $admin, $pivotAdmin] as $target) {
            $hash = $target->fresh()->getAttribute('password');
            $this->actingAs($manager)->get("/admin/users/{$target->id}/edit")->assertRedirect(route('admin.users.index'))->assertSessionHas('error');
            $this->actingAs($manager)->put("/admin/users/{$target->id}", $this->editForm($target, [
                'email' => "taken-{$target->id}@example.test", 'password' => 'hijacked-pass-1', 'password_confirmation' => 'hijacked-pass-1',
            ]))->assertSessionHas('error');
            $fresh = $target->fresh();
            $this->assertSame($hash, $fresh->getAttribute('password'), "password of user {$target->id} changed");
            $this->assertNotSame("taken-{$target->id}@example.test", $fresh->getAttribute('email'));
        }
    }

    public function test_editing_cannot_give_a_role_the_editor_may_not_give(): void
    {
        $manager = $this->manager();
        $writers = $this->role('writers', ['manage_posts']);
        $plugins = $this->role('plugin-people', ['manage_plugins']);
        $target = $this->makeUser(['role_id' => $writers->id, 'username' => 'target']);
        DB::table('role_user')->insert(['user_id' => $target->id, 'role_id' => $writers->id]);

        $this->actingAs($manager)->put("/admin/users/{$target->id}", $this->editForm($target, ['name' => 'Renamed', 'roles' => [$writers->id, $plugins->id]]));
        $fresh = $target->fresh();
        $this->assertSame([$writers->id], $fresh->cmsRoleIds(), 'roles changed by a non-admin');
        $this->assertFalse($fresh->hasPermission('manage_plugins'));
        // The ordinary edit itself went through.
        $this->assertSame('Renamed', $fresh->getAttribute('name'));

        // Nor through their own profile.
        $this->actingAs($manager)->put("/admin/users/{$manager->id}", $this->editForm($manager, ['roles' => [$plugins->id]]));
        $this->assertFalse($manager->fresh()->hasPermission('manage_plugins'));
    }

    public function test_a_user_manager_still_edits_accounts_within_their_permissions_and_their_own(): void
    {
        $manager = $this->manager();
        $writer = $this->makeUser(['role_id' => $this->role('writers', ['manage_posts'])->id, 'username' => 'writer']);

        $this->actingAs($manager)->get("/admin/users/{$writer->id}/edit")->assertOk();
        $this->actingAs($manager)->put("/admin/users/{$writer->id}", $this->editForm($writer, [
            'name' => 'Writer Two', 'password' => 'new-password-12', 'password_confirmation' => 'new-password-12',
        ]))->assertSessionMissing('error')->assertRedirect(route('admin.users.edit', $writer));
        $this->assertSame('Writer Two', $writer->fresh()->getAttribute('name'));
        $this->assertTrue(Hash::check('new-password-12', (string) $writer->fresh()->getAttribute('password')));

        $this->actingAs($manager)->put("/admin/users/{$manager->id}", $this->editForm($manager, ['name' => 'Me Myself']))->assertSessionMissing('error');
        $this->assertSame('Me Myself', $manager->fresh()->getAttribute('name'));
    }

    public function test_administrators_and_super_admins_keep_managing_every_account(): void
    {
        $plugins = $this->role('plugin-people', ['manage_plugins']);
        $writers = $this->role('writers', ['manage_posts']);

        foreach (['administrator', 'super-admin'] as $slug) {
            $actor = $this->admin($slug);
            $this->actingAs($actor)->post('/admin/users', $this->newUserForm("by{$slug}", [$writers->id, $plugins->id, $this->roleId('administrator')]))
                ->assertSessionMissing('error');
            $created = User::where('email', "by{$slug}@example.test")->firstOrFail();
            $this->assertTrue($created->isAdmin());

            $this->actingAs($actor)->put("/admin/users/{$created->id}", $this->editForm($created, ['roles' => [$plugins->id]]))->assertSessionMissing('error');
            $this->assertSame([$plugins->id], $created->fresh()->cmsRoleIds());
            $this->assertTrue($created->fresh()->hasPermission('manage_plugins'));
        }

        // The Super Admin rules are unchanged.
        $super = $this->admin('super-admin');
        $this->actingAs($this->admin())->post('/admin/users', $this->newUserForm('newsuper', [$this->roleId('super-admin')]))->assertSessionHas('error');
        $this->assertNull(User::where('email', 'newsuper@example.test')->first());
        $this->actingAs($this->admin())->get("/admin/users/{$super->id}/edit")->assertRedirect(route('admin.users.index'));
    }

    public function test_reserved_roles_cannot_be_deleted(): void
    {
        $legacy = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = $this->admin();

        foreach (['administrator', 'super-admin', 'admin', 'subscriber'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHas('error');
            $this->assertNotNull(Role::find($role->id), "{$slug} was deleted");
        }
        $this->assertNotNull($legacy->fresh());

        $custom = $this->role('temporary-crew', ['manage_posts']);
        $this->actingAs($admin)->delete("/admin/roles/{$custom->id}")->assertSessionHas('success');
        $this->assertNull(Role::find($custom->id));
    }

    public function test_the_registration_default_role_follows_the_same_rule(): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => 'default_role'], ['value' => 'subscriber']);
        forget_cms_options_cache();
        $plugins = $this->role('plugin-people', ['manage_plugins']);
        $settingsManager = $this->manager(['manage_settings', 'access_general_settings']);

        DB::table('cms_settings')->updateOrInsert(['key' => 'site_title'], ['value' => 'Original']);
        forget_cms_options_cache();

        // The default role is an administrator's setting alone (approved 2026-11-19): a
        // non-admin settings manager cannot set it, not to a stronger role, not to an
        // administrator role, not even to a role within their own permissions, and not to the
        // value it already has. The request is refused whole: the site title rides along unsaved.
        $this->role('writers', ['manage_posts']);
        foreach (['plugin-people', 'administrator', 'writers', 'subscriber'] as $slug) {
            $this->actingAs($settingsManager)->post('/admin/settings', ['site_title' => 'Changed', 'default_role' => $slug])
                ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'Only an administrator'));
            forget_cms_options_cache();
            $this->assertSame('subscriber', get_cms_option('default_role'), "non-admin set default_role {$slug}");
            $this->assertSame('Original', get_cms_option('site_title'), 'a refused request saved part of itself');
        }

        // An administrator's choice stands, and does not block the manager's other settings.
        $this->actingAs($this->admin())->post('/admin/settings', ['site_title' => 'Site', 'default_role' => 'plugin-people'])->assertSessionMissing('error');
        forget_cms_options_cache();
        $this->assertSame('plugin-people', get_cms_option('default_role'));
        $this->actingAs($settingsManager)->post('/admin/settings', ['site_title' => 'Renamed site'])->assertSessionMissing('error');
        forget_cms_options_cache();
        $this->assertSame('Renamed site', get_cms_option('site_title'));
        $this->assertSame($plugins->slug, get_cms_option('default_role'));
    }
}
