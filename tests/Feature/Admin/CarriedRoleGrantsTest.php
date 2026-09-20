<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Facades\DB;

/**
 * Carrying grants made under the old permission spelling across to the new one.
 *
 * Two rules used to derive a menu's slug: the seeder namespaced only a handful of child titles
 * by their parent and wrote `access_library`, while the sidebar namespaces every child and asks
 * for `access_library_media`. A role granted the first was checked against the second and came
 * up empty, so Media → Library never appeared for an editor and nothing explained why.
 *
 * The migration only ever adds. That is the property most worth holding: a site can come out of
 * it seeing more of what it was meant to see, and never less.
 */
class CarriedRoleGrantsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new MenuSeeder)->run();
    }

    private function migration()
    {
        return require __DIR__.'/../../../database/migrations/2026_09_20_000002_carry_role_grants_to_the_namespaced_permissions.php';
    }

    private function grant(Role $role, string $slug): Permission
    {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return $permission;
    }

    private function heldBy(Role $role): array
    {
        return $role->fresh()->permissions->pluck('slug')->sort()->values()->all();
    }

    private function role(string $slug = 'zz-editor'): Role
    {
        return Role::create(['name' => 'ZZ Editor', 'slug' => $slug, 'description' => 'test']);
    }

    public function test_a_grant_made_under_the_old_spelling_is_carried_to_the_new_one(): void
    {
        $role = $this->role();
        $this->grant($role, 'access_languages');   // what the old seeder wrote for Tools → Languages

        $this->migration()->up();

        // The sidebar's own answer for that menu, so the test cannot drift from the resolver.
        $languages = Menu::where('title', 'Languages')->whereNotNull('parent_id')->firstOrFail();
        $this->assertSame('access_languages_tools', (new Sidebar)->getPermission($languages));
        $this->assertContains('access_languages_tools', $this->heldBy($role));
    }

    public function test_the_old_grant_is_left_exactly_where_it_was(): void
    {
        // Anything still checking the old slug keeps finding it. Removing it would be the one
        // way this migration could take a working screen away from somebody.
        $role = $this->role();
        $this->grant($role, 'access_languages');

        $this->migration()->up();

        $this->assertContains('access_languages', $this->heldBy($role));
    }

    public function test_a_slug_two_menus_shared_is_never_carried(): void
    {
        // The old rule answered manage_settings for a child titled "Settings" as readily as for
        // the top-level one, so Shop → Settings and Settings were indistinguishable. Carrying it
        // would hand the Shop's settings to every role holding the site's — a grant nobody made.
        $role = $this->role();
        $this->grant($role, 'manage_settings');

        $this->migration()->up();

        $this->assertNotContains('access_settings_shop', $this->heldBy($role));
        $this->assertContains('manage_settings', $this->heldBy($role));
    }

    public function test_a_child_title_that_appears_under_two_parents_is_not_guessed_at(): void
    {
        // "Library" is both Media → Library and Falcon Builder → Library, and the old slug says
        // nothing about which was meant. Neither is granted; an administrator ticks the one they
        // want, which is the only way to be right about it.
        $role = $this->role();
        $this->grant($role, 'access_library');

        $this->migration()->up();

        $held = $this->heldBy($role);
        $this->assertNotContains('access_library_media', $held);
        $this->assertNotContains('access_library_falcon_builder', $held);
        $this->assertContains('access_library', $held);
    }

    public function test_a_role_that_held_nothing_old_is_untouched(): void
    {
        $role = $this->role();
        $this->grant($role, 'access_dashboard');
        $before = $this->heldBy($role);

        $this->migration()->up();

        $this->assertSame($before, $this->heldBy($role));
    }

    public function test_it_never_takes_a_permission_away_from_anybody(): void
    {
        // Swept across every seeded role at once, since "adds only" is the promise the whole
        // migration rests on.
        $roles = Role::all();
        $before = [];
        foreach ($roles as $role) {
            $this->grant($role, 'access_library');
            $this->grant($role, 'access_overview');
            $before[$role->id] = $this->heldBy($role);
        }

        $this->migration()->up();

        foreach ($roles as $role) {
            $after = $this->heldBy($role);
            $this->assertEmpty(
                array_diff($before[$role->id], $after),
                "role {$role->slug} lost a permission it held before the migration"
            );
        }
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $role = $this->role();
        $this->grant($role, 'access_library');

        $this->migration()->up();
        $once = $this->heldBy($role);
        $rows = DB::table('role_permission')->where('role_id', $role->id)->count();

        $this->migration()->up();

        $this->assertSame($once, $this->heldBy($role));
        $this->assertSame($rows, DB::table('role_permission')->where('role_id', $role->id)->count(),
            'the pivot gained a duplicate row on the second run');
    }
}
