<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The row-level post actions AdminMiddleware hands off.
 *
 * canUserAccessUrl() waves every /admin/posts/... and /admin/pages/... path straight
 * through, on the stated grounds that the required permission depends on the row's own
 * type and "PostController enforces those per-type". These tests check that the far side
 * of that handoff is actually there — for each action, not just for the CRUD methods.
 *
 * The actor throughout is a subscriber: signed in, holding no permission at all.
 */
class PostActionGuardTest extends TestCase
{
    use MakesShopFixtures;

    private User $subscriber;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriber = $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);

        $author = $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);

        $this->post = Post::create([
            'user_id' => $author->id,
            'title' => 'Someone elses post',
            'slug' => 'someone-elses-post',
            'type' => 'post',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => 'original content',
        ]);
    }

    private function revisionCount(): int
    {
        return DB::table('cms_revisions')
            ->where('revisionable_id', $this->post->id)
            ->where('revisionable_type', $this->post->getMorphClass())
            ->count();
    }

    public function test_a_subscriber_cannot_bulk_delete_posts(): void
    {
        $response = $this->actingAs($this->subscriber)->post('/admin/posts/bulk', [
            'action' => 'trash',
            'post_ids' => [$this->post->id],
        ]);

        $this->assertNotNull(Post::find($this->post->id),
            'the post was bulk-deleted (status '.$response->getStatusCode().')');
    }

    public function test_a_subscriber_cannot_clone_a_post(): void
    {
        $before = Post::count();

        $this->actingAs($this->subscriber)->post("/admin/posts/{$this->post->id}/clone");

        $this->assertSame($before, Post::count(), 'a copy was created');
    }

    public function test_a_subscriber_cannot_permanently_delete_a_post(): void
    {
        $this->post->delete();

        $this->actingAs($this->subscriber)->delete("/admin/posts/{$this->post->id}/force-delete");

        $this->assertNotNull(Post::withTrashed()->find($this->post->id),
            'the post was destroyed beyond recovery');
    }

    public function test_a_subscriber_cannot_restore_a_deleted_post(): void
    {
        $this->post->delete();

        $this->actingAs($this->subscriber)->post("/admin/posts/{$this->post->id}/restore");

        $this->assertNull(Post::find($this->post->id), 'the post was restored');
    }

    public function test_a_subscriber_cannot_write_into_a_posts_revision_history(): void
    {
        $this->actingAs($this->subscriber)->post("/admin/posts/{$this->post->id}/autosave", [
            'title' => 'Defaced',
            'content' => 'replaced content',
        ]);

        $this->assertSame(0, $this->revisionCount(), 'a stranger wrote into the revision history');
    }

    /**
     * The two unguarded halves compose into something worse than either: write your text
     * into the history through autosave, then promote it to the live post by restoring it.
     */
    public function test_a_subscriber_cannot_deface_a_post_by_chaining_autosave_and_restore(): void
    {
        $this->actingAs($this->subscriber)->post("/admin/posts/{$this->post->id}/autosave", [
            'title' => 'Defaced',
            'content' => 'replaced content',
        ]);

        $revisionId = DB::table('cms_revisions')
            ->where('revisionable_id', $this->post->id)
            ->where('revisionable_type', $this->post->getMorphClass())
            ->value('id');

        if ($revisionId) {
            $this->actingAs($this->subscriber)
                ->post("/admin/posts/{$this->post->id}/revisions/{$revisionId}/restore");
        }

        $this->assertSame('original content', $this->post->fresh()->content,
            'the post was defaced');
    }

    public function test_a_subscriber_cannot_clear_a_posts_revisions(): void
    {
        DB::table('cms_revisions')->insert([
            'revisionable_type' => $this->post->getMorphClass(),
            'revisionable_id' => $this->post->id,
            'user_id' => $this->post->user_id,
            'type' => 'revision',
            'title' => 'v1',
            'content' => 'v1 body',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->subscriber)->delete("/admin/posts/{$this->post->id}/revisions");

        $this->assertSame(1, $this->revisionCount(), 'the revision history was wiped');
    }

    // ---- the other side: the guards must not have simply shut the door ----------

    /**
     * A fix that makes every action 403 would pass every test above and break the CMS.
     * These are the same seven actions performed by someone who is allowed to.
     */
    public function test_an_administrator_can_still_bulk_trash(): void
    {
        $this->actingAs($this->admin())->post('/admin/posts/bulk', [
            'action' => 'trash',
            'post_ids' => [$this->post->id],
        ]);

        $this->assertNull(Post::find($this->post->id), 'bulk trash stopped working');
    }

    public function test_an_administrator_can_still_clone(): void
    {
        $before = Post::count();

        $this->actingAs($this->admin())->post("/admin/posts/{$this->post->id}/clone");

        $this->assertSame($before + 1, Post::count(), 'cloning stopped working');
    }

    public function test_an_administrator_can_still_restore_and_permanently_delete(): void
    {
        $this->post->delete();

        $this->actingAs($this->admin())->post("/admin/posts/{$this->post->id}/restore");
        $this->assertNotNull(Post::find($this->post->id), 'restore stopped working');

        $this->post->fresh()->delete();
        $this->actingAs($this->admin())->delete("/admin/posts/{$this->post->id}/force-delete");
        $this->assertNull(Post::withTrashed()->find($this->post->id), 'force delete stopped working');
    }

    public function test_an_administrator_can_still_autosave_and_restore_a_revision(): void
    {
        $this->actingAs($this->admin())->post("/admin/posts/{$this->post->id}/autosave", [
            'title' => 'Draft title',
            'content' => 'draft body',
        ]);

        $this->assertSame(1, $this->revisionCount(), 'autosave stopped working');

        $revisionId = DB::table('cms_revisions')
            ->where('revisionable_id', $this->post->id)
            ->where('revisionable_type', $this->post->getMorphClass())
            ->value('id');

        $this->actingAs($this->admin())
            ->post("/admin/posts/{$this->post->id}/revisions/{$revisionId}/restore");

        $this->assertSame('draft body', $this->post->fresh()->content, 'restoring a revision stopped working');
    }

    public function test_an_administrator_can_still_clear_revisions(): void
    {
        DB::table('cms_revisions')->insert([
            'revisionable_type' => $this->post->getMorphClass(),
            'revisionable_id' => $this->post->id,
            'user_id' => $this->post->user_id,
            'type' => 'revision',
            'title' => 'v1',
            'content' => 'v1 body',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())->delete("/admin/posts/{$this->post->id}/revisions");

        $this->assertSame(0, $this->revisionCount(), 'clearing revisions stopped working');
    }

    /** A bulk action over a mixed selection still does its job on the permitted rows. */
    public function test_a_bulk_action_over_a_mixed_selection_acts_on_what_it_may(): void
    {
        // The seeded author role holds no permissions of its own, so grant the one this
        // test is about: an author who may manage posts, but only their own.
        $authorRole = Role::where('slug', 'author')->firstOrFail();
        $permission = Permission::firstOrCreate(['slug' => 'manage_posts'], ['name' => 'Manage Posts']);
        $authorRole->permissions()->syncWithoutDetaching([$permission->id]);

        $author = $this->makeUser(['role_id' => $authorRole->id]);

        $theirs = Post::create([
            'user_id' => $author->id, 'title' => 'Mine', 'slug' => 'mine',
            'type' => 'post', 'status' => 'published', 'lang_code' => 'en', 'content' => 'x',
        ]);

        $this->actingAs($author)->post('/admin/posts/bulk', [
            'action' => 'trash',
            'post_ids' => [$this->post->id, $theirs->id],
        ]);

        $this->assertNull(Post::find($theirs->id), 'their own post should have been trashed');
        $this->assertNotNull(Post::find($this->post->id), "someone else's post was trashed anyway");
    }

    private function admin(): User
    {
        return $this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }
}
