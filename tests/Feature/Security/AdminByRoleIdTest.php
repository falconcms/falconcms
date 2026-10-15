<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Administrators are known by their role's slug, never by the role's id.
 *
 * The seeded administrator and super-admin are usually ids 1 and 6, and both isAdmin() and the
 * admin gate used to trust those numbers. Where those rows are missing (a trimmed install, a
 * restored backup), the next role created — by a plugin, for its customers — takes id 1 or 6,
 * and everyone given it became an administrator.
 */
class AdminByRoleIdTest extends TestCase
{
    private function roleWithId(int $id, string $slug): Role
    {
        // As if the seeded row had never been there: no role, no permissions left on its id.
        DB::table('role_permission')->where('role_id', $id)->delete();
        DB::table('roles')->where('id', $id)->delete();
        DB::table('roles')->insert(['id' => $id, 'name' => ucfirst($slug), 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);

        return Role::findOrFail($id);
    }

    public function test_a_plain_role_with_id_1_or_6_is_not_admin(): void
    {
        foreach ([1, 6] as $id) {
            $role = $this->roleWithId($id, 'trade-customer-'.$id);
            $user = User::forceCreate(['name' => "U{$id}", 'email' => "u{$id}@example.test", 'password' => 'x', 'role_id' => $role->id]);
            $this->assertFalse(User::findOrFail($user->id)->isAdmin(), "id {$id} as primary role");
            $this->assertFalse(User::findOrFail($user->id)->hasPermission('manage_users'));
            $this->actingAs($user)->get('/admin/users')->assertForbidden();

            // As an extra role beside a normal one.
            $other = User::forceCreate(['name' => "X{$id}", 'email' => "x{$id}@example.test", 'password' => 'x']);
            DB::table('role_user')->insert(['user_id' => $other->id, 'role_id' => $role->id]);
            $this->assertFalse(User::findOrFail($other->id)->isAdmin(), "id {$id} as an extra role");
        }
    }

    public function test_a_real_administrator_is_still_admin_whatever_its_id(): void
    {
        $role = Role::create(['name' => 'Administrator 2', 'slug' => 'administrator-legacy']);
        DB::table('roles')->where('slug', 'administrator')->delete();
        DB::table('roles')->insert(['id' => 4242, 'name' => 'Administrator', 'slug' => 'administrator', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::forceCreate(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x', 'role_id' => 4242]);

        $this->assertTrue(User::findOrFail($admin->id)->isAdmin());
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->assertNotNull($role);
    }
}
