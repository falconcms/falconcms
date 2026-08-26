<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Who can reach the dashboard, and what of it.
 *
 * There are no Policies in this package: authorisation for all 48 admin controllers is one
 * middleware, and the permission a page requires is derived from the menu item that owns
 * its path. That makes AdminMiddleware the single place where a mistake becomes "any
 * logged-in user can reach any screen", so it is worth far more coverage than any
 * individual controller.
 */
class AdminAccessTest extends TestCase
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

    // ---- getting through the door ----------------------------------------------

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_an_administrator_reaches_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('administrator'))
            ->get('/admin')
            ->assertOk();
    }

    public function test_a_blocked_administrator_is_logged_out(): void
    {
        $admin = $this->userWithRole('administrator');
        $admin->forceFill(['is_blocked' => true])->save();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.login'));

        $this->assertFalse(auth()->check(), 'the session must not survive the block');
    }

    public function test_a_temporarily_blocked_administrator_is_refused_until_it_lapses(): void
    {
        $admin = $this->userWithRole('administrator');

        $admin->forceFill(['blocked_until' => now()->addHour()])->save();
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.login'));

        $admin->forceFill(['blocked_until' => now()->subHour()])->save();
        $this->actingAs($admin->fresh())->get('/admin')->assertOk();
    }

    /** A shop customer is not a staff member; the dashboard is not theirs to see. */
    public function test_a_customer_is_sent_to_the_storefront(): void
    {
        Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);

        $response = $this->actingAs($this->userWithRole('customer'))->get('/admin');

        $response->assertRedirect();
        $this->assertStringNotContainsString('/admin', $response->headers->get('Location'));
    }

    // ---- what a low-privilege account can reach --------------------------------

    public function test_a_subscriber_reaches_the_dashboard_root(): void
    {
        $this->actingAs($this->userWithRole('subscriber'))->get('/admin')->assertOk();
    }

    /**
     * /admin/profile is a signpost, not a screen: it redirects to the signed-in user's own
     * account page. It has to stay reachable for everyone, because a user with no
     * permissions at all still needs somewhere to change their password.
     */
    public function test_the_profile_signpost_is_reachable_by_anyone_signed_in(): void
    {
        $subscriber = $this->userWithRole('subscriber');

        $this->actingAs($subscriber)->get('/admin/profile')
            ->assertRedirect("/admin/users/{$subscriber->id}/edit");
    }

    public function test_a_subscriber_is_refused_the_screens_they_have_no_permission_for(): void
    {
        $subscriber = $this->userWithRole('subscriber');

        foreach (['/admin/users', '/admin/roles', '/admin/settings', '/admin/media'] as $path) {
            $this->actingAs($subscriber)->get($path)->assertForbidden();
        }
    }

    /**
     * The default is deny. Holding one permission does not open a screen owned by a
     * different menu, or by no menu at all — the middleware never falls back to "allow".
     */
    public function test_holding_one_permission_does_not_open_the_rest(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $role = Role::find($subscriber->role_id);

        $permission = Permission::firstOrCreate(['slug' => 'manage_media'], ['name' => 'Manage Media']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        foreach (['/admin/users', '/admin/roles', '/admin/settings'] as $path) {
            $this->actingAs($subscriber)->get($path)->assertForbidden($path);
        }
    }

    /**
     * The permission a screen requires is derived from the menu item that owns its path,
     * so this is the mapping in one test: add the Users menu, grant the slug that menu
     * resolves to, and that screen — and only that screen — opens.
     *
     * The menus come from MenuSeeder, which runs at install rather than in a migration, so
     * a fresh database has almost none. The test supplies the one it is about.
     */
    public function test_a_granted_permission_opens_exactly_its_own_screen(): void
    {
        DB::table('menus')->insert([
            'title' => 'Users', 'route' => 'admin.users.index', 'parent_id' => null,
            'params' => null, 'order' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $subscriber = $this->userWithRole('subscriber');
        $role = Role::find($subscriber->role_id);

        $permission = Permission::firstOrCreate(['slug' => 'manage_users'], ['name' => 'Manage Users']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->actingAs($subscriber)->get('/admin/users')->assertOk();

        // and nothing beyond it
        $this->actingAs($subscriber)->get('/admin/settings')->assertForbidden();
    }

    // ---- the own-profile carve-out ---------------------------------------------

    /**
     * "Your Profile" links to /admin/users/{id}/edit, so that path has to open for the
     * signed-in user without granting user management. The whole point is that it opens
     * for their own id and no one else's.
     */
    public function test_a_user_can_open_their_own_account_page(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $role = Role::find($subscriber->role_id);

        $permission = Permission::firstOrCreate(
            ['slug' => 'access_your_profile_users'],
            ['name' => 'Your Profile']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->actingAs($subscriber)->get("/admin/users/{$subscriber->id}/edit")->assertOk();
    }

    /** The one that matters: the carve-out must not become "edit anybody". */
    public function test_a_user_cannot_open_someone_elses_account_page(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $role = Role::find($subscriber->role_id);

        $permission = Permission::firstOrCreate(
            ['slug' => 'access_your_profile_users'],
            ['name' => 'Your Profile']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $victim = $this->userWithRole('administrator');

        $this->actingAs($subscriber)
            ->get("/admin/users/{$victim->id}/edit")
            ->assertForbidden();
    }

    public function test_a_user_with_no_profile_permission_cannot_open_their_own_account_page(): void
    {
        $subscriber = $this->userWithRole('subscriber');

        $this->actingAs($subscriber)
            ->get("/admin/users/{$subscriber->id}/edit")
            ->assertForbidden();
    }

    // ---- the permission model itself -------------------------------------------

    public function test_an_administrator_has_every_permission_without_being_granted_any(): void
    {
        $admin = $this->userWithRole('administrator');

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasPermission('anything_at_all'));
    }

    public function test_a_subscriber_has_only_what_their_role_was_given(): void
    {
        $subscriber = $this->userWithRole('subscriber');
        $role = Role::find($subscriber->role_id);

        $permission = Permission::firstOrCreate(['slug' => 'manage_media'], ['name' => 'Manage Media']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->assertFalse($subscriber->isAdmin());
        $this->assertTrue($subscriber->hasPermission('manage_media'));
        $this->assertFalse($subscriber->hasPermission('manage_users'));
    }

    /** A second role through the pivot adds its permissions to the first. */
    public function test_permissions_are_the_union_of_every_role_the_user_holds(): void
    {
        $user = $this->userWithRole('subscriber');

        $editor = Role::where('slug', 'editor')->firstOrFail();
        $permission = Permission::firstOrCreate(['slug' => 'manage_content'], ['name' => 'Manage Content']);
        $editor->permissions()->syncWithoutDetaching([$permission->id]);

        $this->assertFalse($user->hasPermission('manage_content'));

        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $editor->id]);

        $this->assertTrue($user->fresh()->hasPermission('manage_content'));
    }

    /**
     * isAdmin() memoises its answer against the set of role ids the user holds. Role ids
     * are small integers that get reused — across an Octane request, a queue worker, or a
     * restored backup — so a memo that outlives the request can answer "yes, admin" for a
     * role id that now belongs to a subscriber. That is privilege escalation, not merely
     * stale data, which is why the memo is bound to the application instance and dies with
     * it rather than living in a static.
     *
     * Deliberately uses a role outside the hardcoded [1, 6] fast path, so what is being
     * exercised is the slug lookup and its memo rather than the seeded-id shortcut.
     */
    public function test_admin_status_is_memoised_per_request_and_no_longer(): void
    {
        // Two throwaway rows first, so the role under test lands clear of the hardcoded
        // [1, 6] fast path and the slug lookup is what actually decides.
        $roleId = 0;
        foreach (['spare_a', 'spare_b', 'admin'] as $slug) {
            $roleId = (int) DB::table('roles')->insertGetId([
                'name' => $slug, 'slug' => $slug, 'description' => 'test role',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->assertNotContains($roleId, [1, 6], 'this test needs a role outside the fast path');

        $admin = $this->makeUser(['role_id' => $roleId]);
        $this->assertTrue($admin->isAdmin());

        // The same role id, now pointing at something that is not an admin role.
        DB::table('roles')->where('id', $roleId)->update(['slug' => 'demoted']);

        // Still inside the same request: the memo is doing its job.
        $this->assertTrue(
            $this->makeUser(['role_id' => $roleId])->isAdmin(),
            'within one request the memoised answer is expected to stand'
        );

        // A fresh application instance — what the next request gets — sees the truth.
        falcon_request_memo('is_admin')->exchangeArray([]);

        $afterDemotion = $this->makeUser(['role_id' => $roleId]);
        $this->assertFalse($afterDemotion->isAdmin(),
            'a demoted role must not keep granting admin');
        $this->assertFalse($afterDemotion->hasPermission('manage_users'));
    }

    /**
     * Role ids 1 and 6 are treated as admin without consulting the roles table at all.
     * Pinned because it is load-bearing and surprising: it is what keeps the seeded
     * administrator working, and it means those two ids cannot safely be reused for
     * anything else on an install whose roles were created in a different order.
     */
    public function test_the_seeded_admin_role_ids_are_a_deliberate_shortcut(): void
    {
        DB::table('roles')->where('id', 1)->update(['slug' => 'not-an-admin-slug']);

        $this->assertTrue($this->makeUser(['role_id' => 1])->isAdmin(),
            'role id 1 is hardcoded as an admin role');
    }
}
