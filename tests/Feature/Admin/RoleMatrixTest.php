<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\SystemSyncSeeder;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\PostChanges;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every role as a fresh install seeds it, against every step of writing and publishing. Each row
 * is what that role should be able to do; a change in what a role can do has to change it here.
 */
class RoleMatrixTest extends TestCase
{
    use MakesShopFixtures;

    /** role => [opens the posts list, can write, can publish, reaches the Roles screen] */
    public static function roles(): array
    {
        return [
            'administrator' => ['administrator', true, true, true, true],
            'super-admin' => ['super-admin', true, true, true, true],
            'editor' => ['editor', true, true, true, false],
            'author' => ['author', true, true, true, false],
            'contributor' => ['contributor', true, true, false, false],
            'user' => ['user', true, true, true, false],
            'subscriber' => ['subscriber', false, false, false, false],
        ];
    }

    private function member(string $slug): User
    {
        (new SystemSyncSeeder)->run();
        $role = Role::where('slug', $slug)->firstOrFail();

        return $this->makeUser(['role_id' => $role->id]);
    }

    private function live(): Post
    {
        return Post::create([
            'title' => 'Live title', 'slug' => 'live-'.uniqid(), 'type' => 'post', 'status' => 'published',
            'lang_code' => 'en', 'content' => '<p>Live</p>', 'editor_type' => 'rich', 'published_at' => now()->subDay(),
        ]);
    }

    private function form(array $extra = []): array
    {
        return array_merge(['type' => 'post', 'status' => 'published', 'title' => 'Edited', 'content' => '<p>Edited</p>', 'editor_type' => 'rich'], $extra);
    }

    #[DataProvider('roles')]
    public function test_the_role_can_do_exactly_what_it_should(string $slug, bool $lists, bool $writes, bool $publishes, bool $roles): void
    {
        $user = $this->member($slug);
        // Made before anybody signs in: the model holds a signed-in writer to the review rules.
        $live = $this->live();
        $target = $this->live();
        $waiting = $this->live();
        $waiting->update(['status' => 'pending']);
        $code = fn ($response) => $response->getStatusCode();

        // Reaching the screens.
        $this->assertSame($lists ? 200 : 403, $code($this->actingAs($user)->get('/admin/posts?type=post')), 'posts list');
        $this->assertSame($writes ? 200 : 403, $code($this->actingAs($user)->get('/admin/posts/create?type=post')), 'add new');
        $this->assertSame($writes ? 200 : 403, $code($this->actingAs($user)->get("/admin/posts/{$live->id}/edit")), 'edit live');
        $this->assertSame($publishes ? 200 : 403, $code($this->actingAs($user)->get('/admin/posts/review-queue')), 'review queue');
        $this->assertSame($roles ? 200 : 403, $code($this->actingAs($user)->get('/admin/roles')), 'roles screen');

        // A new post.
        $this->actingAs($user)->post('/admin/posts', $this->form(['title' => 'Brand new']));
        $new = Post::where('title', 'Brand new')->first();
        if ($writes) {
            $this->assertSame($publishes ? 'published' : 'pending', $new->status, 'new post');
        } else {
            $this->assertNull($new, 'new post');
        }

        // An edit to what is live.
        $this->actingAs($user)->put("/admin/posts/{$live->id}", $this->form());
        $this->assertSame($writes && $publishes ? 'Edited' : 'Live title', $live->fresh()->title, 'edit live saves');
        $this->assertSame($writes && !$publishes, (bool) PostChanges::find($live->id), 'edit live waits for review');

        // Taking it down.
        $this->actingAs($user)->delete("/admin/posts/{$target->id}");
        $this->assertSame($writes && $publishes, $target->fresh()->deleted_at !== null, 'trash live');

        // Deciding someone else's work.
        $this->actingAs($user)->post("/admin/posts/{$waiting->id}/review", ['decision' => 'approve']);
        $this->assertSame($writes && $publishes ? 'published' : 'pending', $waiting->fresh()->status, 'approve');
    }

    public function test_a_customer_is_sent_out_of_the_admin(): void
    {
        $user = $this->member('customer');
        $live = $this->live();

        foreach (['/admin', '/admin/posts', "/admin/posts/{$live->id}/edit", '/admin/posts/review-queue'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect();
        }
        $this->actingAs($user)->put("/admin/posts/{$live->id}", $this->form());

        $this->assertSame('Live title', $live->fresh()->title);
        $this->assertNull(PostChanges::find($live->id));
    }

    public function test_a_contributor_migrated_from_the_old_seed_can_write(): void
    {
        $role = Role::firstOrCreate(['slug' => 'contributor'], ['name' => 'Contributor']);
        $manage = DB::table('permissions')->where('slug', 'manage_posts')->value('id');
        if (!$manage) {
            $manage = DB::table('permissions')->insertGetId(['name' => 'Manage Posts', 'slug' => 'manage_posts']);
        }
        DB::table('role_permission')->where('role_id', $role->id)->delete();
        DB::table('role_permission')->insert(['role_id' => $role->id, 'permission_id' => $manage]);

        (require __DIR__.'/../../../database/migrations/2026_11_05_000003_create_cms_post_changes_table.php')->up();

        $slugs = $role->fresh()->permissions()->pluck('slug')->all();
        $this->assertContains('access_add_new_posts', $slugs);
        $this->assertNotContains('publish_content', $slugs);
    }
}
