<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\SystemSyncSeeder;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Facades\DB;

/**
 * What a brand-new account can do.
 *
 * Registering makes you a subscriber, and the subscriber role was seeded with `manage_users`.
 * That put a Users entry in the sidebar of every person who had just signed up — and every
 * page behind it answered 403, because user management has a guard of its own. The menu said
 * one thing and the site said another; the menu was wrong.
 *
 * A subscriber now holds the Dashboard and its Overview, which is what they can actually open.
 */
class SubscriberDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new SystemSyncSeeder)->run();
    }

    private function subscriber(): User
    {
        return User::forceCreate([
            'name' => 'New Signup', 'email' => 'signup@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);
    }

    public function test_a_subscriber_holds_the_dashboard_and_its_overview_and_nothing_else(): void
    {
        $held = Role::where('slug', 'subscriber')->firstOrFail()
            ->permissions->pluck('slug')->sort()->values()->all();

        $this->assertSame(['access_dashboard', 'access_overview_dashboard'], $held);
    }

    public function test_a_subscriber_is_no_longer_told_they_can_manage_users(): void
    {
        // The whole of the bug in one assertion: the permission that drew that menu is gone.
        $this->assertNotContains(
            'manage_users',
            Role::where('slug', 'subscriber')->firstOrFail()->permissions->pluck('slug')->all()
        );
    }

    public function test_the_sidebar_a_subscriber_gets_matches_the_pages_that_open(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user);

        $sidebar = new Sidebar;
        $dashboard = Menu::whereNull('parent_id')->where('title', 'Dashboard')->first();
        $users = Menu::whereNull('parent_id')->where('title', 'Users')->first();

        $this->assertNotNull($dashboard, 'the seeded Dashboard menu is missing');
        $this->assertTrue($sidebar->canSee($dashboard));

        if ($users) {
            $this->assertFalse($sidebar->canSee($users), 'Users is still drawn for a subscriber');
            foreach ($users->children as $child) {
                $this->assertFalse($sidebar->canSee($child), "Users → {$child->title} is still drawn");
            }
        }
    }

    public function test_the_dashboard_opens_and_user_management_does_not(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_a_subscriber_role_someone_has_edited_is_left_alone(): void
    {
        // The seeder only fills a role that holds nothing, so a site that has granted its
        // subscribers something extra keeps it.
        $role = Role::where('slug', 'subscriber')->firstOrFail();
        $extra = Permission::firstOrCreate(['slug' => 'access_comments'], ['name' => 'Access Comments']);
        $role->permissions()->syncWithoutDetaching([$extra->id]);

        (new SystemSyncSeeder)->run();

        $this->assertContains('access_comments', $role->fresh()->permissions->pluck('slug')->all());
    }
}
