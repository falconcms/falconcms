<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * How someone becomes an administrator — and every way they must not.
 *
 * These are the highest-stakes rules in the CMS: everything else in the permission system
 * is only as good as the question of who gets to hold the permissions. The code already
 * defends them and this suite found no hole; it exists because "already correct" is a
 * property that has to be kept, and nothing was holding these in place.
 *
 * One of the rules leans on isAdmin(), which until this release memoised into a static
 * that outlived the request. A stale "yes" there would have unlocked the role editor for
 * whoever came next, so these tests sit downstream of that fix as well.
 */
class PrivilegeEscalationTest extends TestCase
{
    use MakesShopFixtures;

    private function roleId(string $slug): int
    {
        return (int) DB::table('roles')->where('slug', $slug)->value('id');
    }

    private function userWithRole(string $slug): User
    {
        return $this->makeUser(['role_id' => $this->roleId($slug)]);
    }

    private function superAdminRole(): Role
    {
        return Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    }

    /** @return array<string, mixed> the fields the edit form posts back */
    private function profileForm(User $user, array $overrides = []): array
    {
        return array_merge([
            'username' => $user->username ?: 'user'.$user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], $overrides);
    }

    // ---- editing your own profile ----------------------------------------------

    /**
     * The profile page is reachable without user-management, so the update behind it has
     * to ignore any role the form carries. Otherwise every account holds the keys.
     */
    public function test_a_subscriber_cannot_promote_themselves_through_their_profile(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $adminRoleId = $this->roleId('administrator');

        $this->actingAs($subscriber)->put("/admin/users/{$subscriber->id}", $this->profileForm($subscriber, [
            'roles' => [$adminRoleId],
        ]));

        $fresh = $subscriber->fresh();

        $this->assertFalse($fresh->isAdmin(), 'a subscriber made themselves an administrator');
        $this->assertSame($this->roleId('subscriber'), (int) $fresh->role_id);
        $this->assertNotContains($adminRoleId, $fresh->cmsRoleIds());
    }

    public function test_a_subscriber_cannot_promote_themselves_with_a_raw_role_id(): void
    {
        $subscriber = $this->userWithRole('subscriber');

        $this->actingAs($subscriber)->put("/admin/users/{$subscriber->id}", $this->profileForm($subscriber, [
            'role_id' => $this->roleId('administrator'),
        ]));

        $this->assertFalse($subscriber->fresh()->isAdmin(), 'role_id was mass-assigned');
    }

    public function test_a_subscriber_cannot_unblock_themselves(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $subscriber->forceFill(['is_blocked' => true])->save();

        $this->actingAs($subscriber)->put("/admin/users/{$subscriber->id}", $this->profileForm($subscriber, [
            'is_blocked' => 0,
        ]));

        $this->assertTrue((bool) $subscriber->fresh()->is_blocked, 'a blocked user cleared their own block');
    }

    /**
     * Changing your own name and password is the whole point of the page, so the refusals
     * above must not have come at the cost of it.
     *
     * Reaching the page at all needs the "Your Profile" permission — a role with nothing
     * granted cannot open it, which AdminAccessTest pins separately. This grants it, so
     * what is under test here is the update rather than the door.
     */
    public function test_a_user_can_still_edit_their_own_details(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $this->grantProfileAccess($subscriber);

        $this->actingAs($subscriber)->put("/admin/users/{$subscriber->id}", $this->profileForm($subscriber, [
            'name' => 'New Name',
        ]));

        $this->assertSame('New Name', $subscriber->fresh()->name, 'self-editing stopped working');
    }

    private function grantProfileAccess(User $user): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'access_your_profile_users'],
            ['name' => 'Your Profile']
        );

        Role::find($user->role_id)?->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function test_editing_your_own_profile_does_not_strip_the_roles_you_hold(): void
    {
        $editor = $this->userWithRole('editor');
        $this->grantProfileAccess($editor);

        $this->actingAs($editor)->put("/admin/users/{$editor->id}", $this->profileForm($editor, [
            'name' => 'Renamed',
        ]));

        $this->assertSame('Renamed', $editor->fresh()->name, 'the edit itself must have gone through');

        $this->assertSame($this->roleId('editor'), (int) $editor->fresh()->role_id,
            'a self-edit must keep the roles it was not allowed to change');
    }

    // ---- editing other people --------------------------------------------------

    public function test_a_subscriber_cannot_edit_another_account_at_all(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $victim = $this->userWithRole('editor');

        $this->actingAs($subscriber)
            ->put("/admin/users/{$victim->id}", $this->profileForm($victim, ['name' => 'Hijacked']))
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $victim->fresh()->name);
    }

    public function test_a_subscriber_cannot_delete_another_account(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $victim = $this->userWithRole('editor');

        $this->actingAs($subscriber)->delete("/admin/users/{$victim->id}")->assertForbidden();

        $this->assertNotNull(User::find($victim->id));
    }

    // ---- the super-admin fence -------------------------------------------------

    public function test_only_a_super_admin_can_hand_out_the_super_admin_role(): void
    {
        $superRole = $this->superAdminRole();
        $admin = $this->userWithRole('administrator');
        $target = $this->userWithRole('subscriber');

        $this->actingAs($admin)->put("/admin/users/{$target->id}", $this->profileForm($target, [
            'roles' => [$superRole->id],
        ]));

        $this->assertNotContains($superRole->id, $target->fresh()->cmsRoleIds(),
            'an ordinary administrator minted a super admin');
    }

    public function test_an_administrator_cannot_edit_a_super_admin(): void
    {
        $superRole = $this->superAdminRole();
        $admin = $this->userWithRole('administrator');
        $superAdmin = $this->makeUser(['role_id' => $superRole->id]);

        $this->actingAs($admin)->put("/admin/users/{$superAdmin->id}", $this->profileForm($superAdmin, [
            'name' => 'Demoted',
            'roles' => [$this->roleId('subscriber')],
        ]));

        $this->assertNotSame('Demoted', $superAdmin->fresh()->name);
    }

    public function test_an_administrator_cannot_delete_a_super_admin(): void
    {
        $superRole = $this->superAdminRole();
        $admin = $this->userWithRole('administrator');
        $superAdmin = $this->makeUser(['role_id' => $superRole->id]);

        $this->actingAs($admin)->delete("/admin/users/{$superAdmin->id}");

        $this->assertNotNull(User::find($superAdmin->id), 'a super admin was deleted by an administrator');
    }

    public function test_nobody_can_delete_their_own_account(): void
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $this->assertNotNull(User::find($admin->id), 'an administrator deleted themselves out of the site');
    }

    // ---- the other side: role management must still work ------------------------

    /**
     * Without these, "refuse every role change" would satisfy everything above and leave
     * the CMS with no way to appoint anyone.
     */
    public function test_an_administrator_can_change_someone_elses_role(): void
    {
        $admin = $this->userWithRole('administrator');
        $target = $this->userWithRole('subscriber');
        $editorRoleId = $this->roleId('editor');

        $this->actingAs($admin)->put("/admin/users/{$target->id}", $this->profileForm($target, [
            'roles' => [$editorRoleId],
        ]));

        $this->assertContains($editorRoleId, $target->fresh()->cmsRoleIds(), 'role management stopped working');
    }

    public function test_a_super_admin_can_hand_out_the_super_admin_role(): void
    {
        $superRole = $this->superAdminRole();
        $superAdmin = $this->makeUser(['role_id' => $superRole->id]);
        $target = $this->userWithRole('subscriber');

        $this->actingAs($superAdmin)->put("/admin/users/{$target->id}", $this->profileForm($target, [
            'roles' => [$superRole->id],
        ]));

        $this->assertContains($superRole->id, $target->fresh()->cmsRoleIds());
    }

    // ---- the role editor itself ------------------------------------------------

    /**
     * RoleController carries no checks of its own — it trusts AdminMiddleware entirely.
     * That is only safe while the middleware's default stays "deny", so it is pinned here
     * rather than left as an assumption.
     */
    public function test_the_role_editor_is_out_of_reach_without_the_permission(): void
    {
        $subscriber = $this->userWithRole('subscriber');

        $this->actingAs($subscriber)->get('/admin/roles')->assertForbidden();
        $this->actingAs($subscriber)->post('/admin/roles', [
            'name' => 'Sneaky', 'slug' => 'sneaky',
        ])->assertForbidden();

        $this->assertNull(Role::where('slug', 'sneaky')->first(), 'a subscriber created a role');
    }

    // ---- signing up ------------------------------------------------------------

    public function test_registration_uses_the_configured_role_and_ignores_the_form(): void
    {
        $this->setCmsOptions([
            'users_can_register' => '1',
            'default_role' => 'subscriber',
            'require_email_verification' => '0',
        ]);

        $this->post('/'.get_cms_option('register_url', 'falcon-registration'), [
            'name' => 'New Person',
            'email' => 'new@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            // Both spellings an attacker would try.
            'role_id' => $this->roleId('administrator'),
            'roles' => [$this->roleId('administrator')],
        ]);

        $user = User::where('email', 'new@example.test')->first();

        $this->assertNotNull($user, 'registration stopped working');
        $this->assertFalse($user->isAdmin(), 'a registrant chose their own role');
        $this->assertSame($this->roleId('subscriber'), (int) $user->role_id);
    }

    public function test_registration_stays_shut_when_the_site_has_it_disabled(): void
    {
        $this->setCmsOptions(['users_can_register' => '0']);

        $this->post('/'.get_cms_option('register_url', 'falcon-registration'), [
            'name' => 'Uninvited',
            'email' => 'uninvited@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertNull(User::where('email', 'uninvited@example.test')->first());
    }
}
