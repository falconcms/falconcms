<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A section's list permission reads; its Add New permission writes.
 *
 * A role given All Posts and not Add New used to be able to edit and delete every post, since
 * the edit, update and delete addresses all sit under /admin/posts and were judged by the list's
 * permission. It now browses the list and changes nothing. Authors and contributors follow the
 * same rule — they used to see only their own rows, so one given All Posts met an empty page,
 * and could change only what they wrote even when granted Add New.
 *
 * Nothing names a section, so a post type, a role or a plugin menu added later is held to the
 * same rule.
 */
class ReadOnlyContentAccessTest extends TestCase
{
    use MakesShopFixtures;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        (new MenuSeeder)->run();

        $owner = $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);

        $this->post = Post::create([
            'user_id' => $owner->id,
            'title' => 'Someone elses post',
            'slug' => 'someone-elses-post',
            'type' => 'post',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => 'original content',
        ]);
    }

    /** A user whose only role holds exactly these permissions. */
    private function userWith(array $slugs, string $roleSlug = 'zz-staff'): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);
        $role->permissions()->sync(array_map(
            fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id,
            $slugs
        ));

        return $this->makeUser(['role_id' => $role->id]);
    }

    public function test_the_list_alone_reads_but_changes_nothing(): void
    {
        $user = $this->userWith(['manage_posts', 'access_all_posts_posts']);

        $this->actingAs($user)->get('/admin/posts')
            ->assertOk()
            ->assertSee('Someone elses post')
            ->assertDontSee(route('admin.posts.create', ['type' => 'post']), false);

        $this->actingAs($user)->get('/admin/posts/create')->assertForbidden();
        $this->actingAs($user)->get("/admin/posts/{$this->post->id}/edit")->assertForbidden();
        $this->actingAs($user)->delete("/admin/posts/{$this->post->id}")->assertForbidden();
        $this->actingAs($user)->post('/admin/posts/bulk', ['action' => 'trash', 'post_ids' => [$this->post->id]]);

        $this->assertNotNull(Post::find($this->post->id), 'a read-only role trashed a post');
    }

    public function test_add_new_opens_the_writes(): void
    {
        $user = $this->userWith(['manage_posts', 'access_all_posts_posts', 'access_add_new_posts']);

        $this->actingAs($user)->get('/admin/posts/create')->assertOk();
        $this->actingAs($user)->get("/admin/posts/{$this->post->id}/edit")->assertOk();
    }

    public function test_an_author_is_held_to_permissions_like_every_other_role(): void
    {
        // No rule by role name: an author with only All Posts reads everything and changes
        // nothing; given Add New, they change any post, as any other role would.
        $author = $this->userWith(['manage_posts', 'access_all_posts_posts'], 'author');

        $this->actingAs($author)->get('/admin/posts')->assertOk()->assertSee('Someone elses post');
        $this->actingAs($author)->get("/admin/posts/{$this->post->id}/edit")->assertForbidden();

        Role::where('slug', 'author')->firstOrFail()->permissions()->attach(
            Permission::firstOrCreate(['slug' => 'access_add_new_posts'], ['name' => 'access_add_new_posts'])->id
        );

        $this->actingAs($author->fresh())->get('/admin/posts')
            ->assertOk()
            ->assertSee(route('admin.posts.edit', $this->post), false);
        $this->actingAs($author->fresh())->get("/admin/posts/{$this->post->id}/edit")->assertOk();
    }

    public function test_pages_follow_the_same_rule(): void
    {
        $page = Post::create([
            'user_id' => $this->post->user_id, 'title' => 'About', 'slug' => 'about',
            'type' => 'page', 'status' => 'published', 'lang_code' => 'en', 'content' => 'x',
        ]);
        $reader = $this->userWith(['manage_pages', 'access_all_pages_pages']);

        $this->actingAs($reader)->get('/admin/pages')->assertOk()->assertSee('About');
        $this->actingAs($reader)->get("/admin/pages/{$page->id}/edit")->assertForbidden();
        $this->actingAs($reader)->delete("/admin/pages/{$page->id}")->assertForbidden();
        $this->assertNotNull(Post::find($page->id));

        $writer = $this->userWith(['manage_pages', 'access_all_pages_pages', 'access_add_new_pages'], 'zz-writer');
        $this->actingAs($writer)->get("/admin/pages/{$page->id}/edit")->assertOk();
    }

    /** @return array<string,array{string}> every seeded non-admin role, and one created later */
    public static function roleSlugs(): array
    {
        return [
            'editor' => ['editor'], 'author' => ['author'], 'contributor' => ['contributor'],
            'subscriber' => ['subscriber'], 'a role made later' => ['zz-made-later'],
        ];
    }

    /**
     * The same permissions mean the same access whatever the role is called. Nothing may single
     * a role out by name — the own-posts-only rule for authors and contributors did, and an
     * author granted Add New still could not edit a post they had not written.
     */
    #[DataProvider('roleSlugs')]
    public function test_every_role_is_held_to_the_same_rules(string $slug): void
    {
        $page = Post::create([
            'user_id' => $this->post->user_id, 'title' => 'About', 'slug' => 'about',
            'type' => 'page', 'status' => 'published', 'lang_code' => 'en', 'content' => 'x',
        ]);
        $read = ['manage_posts', 'access_all_posts_posts', 'manage_pages', 'access_all_pages_pages', 'manage_users', 'access_all_users_users'];
        $user = $this->userWith($read, $slug);

        $this->actingAs($user)->get('/admin/posts')->assertOk()->assertSee('Someone elses post');
        $this->actingAs($user)->get("/admin/posts/{$this->post->id}/edit")->assertForbidden();
        $this->actingAs($user)->get("/admin/pages/{$page->id}/edit")->assertForbidden();
        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();

        $this->userWith(array_merge($read, ['access_add_new_posts', 'access_add_new_pages', 'access_add_new_users']), $slug);

        $this->actingAs($user->fresh())->get("/admin/posts/{$this->post->id}/edit")->assertOk();
        $this->actingAs($user->fresh())->get("/admin/pages/{$page->id}/edit")->assertOk();
        $this->actingAs($user->fresh())->get('/admin/users/create')->assertOk();
    }

    public function test_every_list_with_an_add_new_hides_its_controls_without_it(): void
    {
        // Media, Users and Forms read through falcon_admin_can_write(), so any list whose menu
        // has an Add New beside it is read-only the same way Posts and Pages are. (Media is not
        // listed: its date filter is MySQL-only and the screen cannot render on SQLite.)
        $screens = [
            '/admin/users' => [['manage_users', 'access_all_users_users'], 'access_add_new_users', route('admin.users.create')],
            '/admin/forms' => [['access_forms', 'access_all_forms_forms'], 'access_add_new_forms', route('admin.forms.create')],
        ];

        foreach ($screens as $url => [$read, $addNew, $createUrl]) {
            $reader = $this->userWith($read, 'zz-read-'.md5($url));
            $this->actingAs($reader)->get($url)
                ->assertOk()
                ->assertDontSee('href="'.$createUrl.'"', false);

            $writer = $this->userWith(array_merge($read, [$addNew]), 'zz-write-'.md5($url));
            $this->actingAs($writer)->get($url)
                ->assertOk()
                ->assertSee('href="'.$createUrl.'"', false);
        }
    }

    public function test_submenu_items_sharing_one_capability_each_get_their_own(): void
    {
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);
        $registry->addMenuPage(['slug' => 'wholesale', 'menu_title' => 'Wholesale', 'capability' => 'manage_wholesale', 'group' => 'Extend']);
        $registry->addSubmenuPage('wholesale', ['menu_title' => 'Customers', 'capability' => 'manage_wholesale']);
        $registry->addSubmenuPage('wholesale', ['menu_title' => 'Settings', 'capability' => 'manage_wholesale']);

        $item = $registry->permissionTree()['Extend'][0];

        // One checkbox per sub-option, so ticking one no longer ticks them all.
        $this->assertSame('manage_wholesale', $item['slug']);
        $this->assertSame(['access_wholesale_customers', 'access_wholesale_settings'], array_column($item['children'], 'slug'));
    }

    public function test_a_role_saved_with_only_the_shared_capability_keeps_the_whole_section(): void
    {
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);
        $registry->addMenuPage(['slug' => 'wholesale', 'menu_title' => 'Wholesale', 'capability' => 'manage_wholesale', 'group' => 'Extend']);
        $registry->addSubmenuPage('wholesale', ['menu_title' => 'Customers', 'capability' => 'manage_wholesale']);
        $registry->addSubmenuPage('wholesale', ['menu_title' => 'Settings', 'capability' => 'manage_wholesale']);
        [$customers, $settings] = $registry->grouped()['Extend'][0]->children->all();
        $sidebar = new Sidebar;

        $legacy = $this->userWith(['manage_wholesale']);
        $this->assertTrue($sidebar->allows($legacy, $customers));
        $this->assertTrue($sidebar->allows($legacy, $settings));
        $this->assertSame(['access_wholesale_customers', 'access_wholesale_settings'], $registry->impliedPermissions(['manage_wholesale']));

        // Once any sub-option is saved, the sub-options decide.
        $narrow = $this->userWith(['manage_wholesale', 'access_wholesale_customers'], 'zz-narrow');
        $this->assertTrue($sidebar->allows($narrow, $customers));
        $this->assertFalse($sidebar->allows($narrow, $settings));
    }
}
