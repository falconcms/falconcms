<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/** The Roles screen shows Publish & review as it stands, and saving keeps what was ticked. */
class RolePublishPermissionTest extends TestCase
{
    use MakesShopFixtures;

    public function test_a_role_holding_it_shows_it_ticked_and_saving_keeps_it(): void
    {
        (new MenuSeeder)->run();
        $role = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
        $publish = Permission::firstOrCreate(['slug' => 'publish_content'], ['name' => 'Publish']);
        $role->permissions()->syncWithoutDetaching([$publish->id]);
        $admin = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $html = $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="publish_content"[^>]*\bchecked\b/s', $html);

        $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => $role->name, 'slug' => $role->slug, 'permissions' => ['publish_content', 'manage_posts'],
        ])->assertRedirect();

        $this->assertTrue($role->fresh()->permissions()->where('slug', 'publish_content')->exists());
    }
}
