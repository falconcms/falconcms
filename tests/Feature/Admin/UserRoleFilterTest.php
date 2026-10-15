<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\SystemSyncSeeder;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Users → filter by role counts and finds a role held as an extra one (role_user), not only
 * as someone's primary role — a plugin's role ("Wholesale Customer") sits beside the user's own.
 */
class UserRoleFilterTest extends TestCase
{
    public function test_a_role_held_as_an_extra_one_is_counted_and_filtered(): void
    {
        (new SystemSyncSeeder)->run();
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
        $extra = Role::create(['name' => 'Trade Buyer', 'slug' => 'trade-buyer']);
        $customerRole = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);

        $buyer = User::forceCreate(['name' => 'Rahim Buyer', 'email' => 'rahim@example.test', 'password' => 'x', 'role_id' => $customerRole->id]);
        DB::table('role_user')->insert(['user_id' => $buyer->id, 'role_id' => $extra->id]);
        User::forceCreate(['name' => 'Plain Customer', 'email' => 'plain@example.test', 'password' => 'x', 'role_id' => $customerRole->id]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'trade-buyer']))->assertOk();
        $this->assertSame(['Rahim Buyer'], $response->viewData('users')->pluck('name')->all());
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/Trade Buyer\s*<span[^>]*>\s*\(1\)/', $html);
    }
}
