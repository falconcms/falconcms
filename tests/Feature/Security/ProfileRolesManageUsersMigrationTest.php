<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\PublicRegistration;
use FalconCms\Core\Support\SiteHealth;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Subscriber and Customer lose `manage_users` only when it is the one thing out of place.
 *
 * Before: production installs kept the old seeded set (dashboard, profile, manage_users) on both
 * roles, because the 2026-09-20 narrowing did not recognise it. Public registration refuses a
 * role holding manage_users, so switching registration on registered nobody.
 */
class ProfileRolesManageUsersMigrationTest extends TestCase
{
    /** The set found on production for both roles. */
    private const PRODUCTION = ['access_dashboard', 'access_your_profile', 'access_your_profile_users', 'manage_users'];

    private function migration()
    {
        return require __DIR__.'/../../../database/migrations/2026_11_25_000001_remove_manage_users_from_the_profile_only_roles.php';
    }

    /** The role holding exactly $permissions, created if the test database has none. */
    private function roleWith(string $slug, array $permissions): Role
    {
        $role = Role::where('slug', $slug)->first() ?? Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        DB::table('role_permission')->where('role_id', $role->getKey())->delete();
        foreach ($permissions as $p) {
            DB::table('role_permission')->insert(['role_id' => $role->getKey(), 'permission_id' => Permission::firstOrCreate(['slug' => $p], ['name' => $p])->id]);
        }

        return $role;
    }

    private function heldBy(Role $role): array
    {
        return collect(Role::permissionSlugsFor([(int) $role->getKey()]))->sort()->values()->all();
    }

    private function sorted(array $slugs): array
    {
        sort($slugs);

        return $slugs;
    }

    public function test_the_production_set_loses_only_manage_users_on_both_roles(): void
    {
        $subscriber = $this->roleWith('subscriber', self::PRODUCTION);
        $customer = $this->roleWith('customer', self::PRODUCTION);

        $this->migration()->up();

        $kept = $this->sorted(['access_dashboard', 'access_your_profile', 'access_your_profile_users']);
        $this->assertSame($kept, $this->heldBy($subscriber));
        $this->assertSame($kept, $this->heldBy($customer));

        // Running it again changes nothing.
        $this->migration()->up();
        $this->assertSame($kept, $this->heldBy($subscriber));
        $this->assertSame($kept, $this->heldBy($customer));

        // And the registration form accepts the subscriber role again.
        $this->assertNull(PublicRegistration::roleError($subscriber->fresh()));
    }

    public function test_a_role_with_any_other_permission_is_left_exactly_as_it_is(): void
    {
        $subscriber = $this->roleWith('subscriber', [...self::PRODUCTION, 'access_overview_dashboard']);
        $customer = $this->roleWith('customer', [...self::PRODUCTION, 'manage_posts']);
        $before = [$this->heldBy($subscriber), $this->heldBy($customer)];

        $this->migration()->up();

        $this->assertSame($before, [$this->heldBy($subscriber), $this->heldBy($customer)]);
    }

    public function test_a_smaller_set_and_other_roles_are_not_touched(): void
    {
        // manage_users plus less than the production set is not the seeded set either.
        $subscriber = $this->roleWith('subscriber', ['access_dashboard', 'manage_users']);
        $editor = $this->roleWith('profile-editor', self::PRODUCTION);

        $this->migration()->up();

        $this->assertSame($this->sorted(['access_dashboard', 'manage_users']), $this->heldBy($subscriber));
        $this->assertSame($this->sorted(self::PRODUCTION), $this->heldBy($editor));
    }

    public function test_down_gives_nothing_back(): void
    {
        $subscriber = $this->roleWith('subscriber', self::PRODUCTION);
        $this->migration()->up();
        $this->migration()->down();

        $this->assertNotContains('manage_users', $this->heldBy($subscriber));
    }

    public function test_site_health_says_how_to_remove_manage_users_by_hand(): void
    {
        $this->setCmsOptions(['users_can_register' => '1', 'default_role' => 'subscriber']);
        $this->roleWith('subscriber', [...self::PRODUCTION, 'access_overview_dashboard']);   // customised: the migration leaves it
        $this->roleWith('customer', [...self::PRODUCTION, 'manage_posts']);

        $check = collect(SiteHealth::checks())->firstWhere('id', 'public_registration');

        $this->assertSame(SiteHealth::CRITICAL, $check['status']);
        $this->assertStringContainsString('Users → Roles → edit "Subscriber" → untick "Users"', $check['action']);
        $this->assertStringContainsString('edit "Customer"', $check['action']);

        // Once the subscriber no longer holds it, the check is good and the hint is gone.
        $this->roleWith('subscriber', ['access_dashboard', 'access_your_profile', 'access_your_profile_users']);
        $this->assertSame(SiteHealth::GOOD, collect(SiteHealth::checks())->firstWhere('id', 'public_registration')['status']);
    }
}
