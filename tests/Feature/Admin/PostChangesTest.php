<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\ApiToken;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Revision;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\PostChanges;
use FalconCms\Core\Support\PostLock;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Edits to live content, by someone who cannot publish, go live only after review. */
class PostChangesTest extends TestCase
{
    use MakesShopFixtures;

    private const WRITE = ['manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'manage_pages', 'access_all_pages_pages', 'access_add_new_pages'];

    private User $writer;

    private User $reviewer;

    private Post $live;

    protected function setUp(): void
    {
        parent::setUp();
        (new MenuSeeder)->run();
        $this->writer = $this->userWith(self::WRITE, 'zz-writer');
        $this->reviewer = $this->userWith(array_merge(self::WRITE, ['publish_content']), 'zz-reviewer');
        $this->live = $this->item();
    }

    private function userWith(array $slugs, string $roleSlug): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);
        $role->permissions()->sync(array_map(
            fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id,
            $slugs
        ));

        return $this->makeUser(['role_id' => $role->id]);
    }

    private function item(string $type = 'post', string $status = 'published'): Post
    {
        return Post::create([
            'title' => 'Live title', 'slug' => $type.'-'.uniqid(), 'type' => $type, 'status' => $status, 'lang_code' => 'en',
            'content' => '<p>Live body</p>', 'excerpt' => 'Live excerpt', 'editor_type' => 'rich', 'published_at' => now()->subDay(),
        ]);
    }

    private function form(array $extra = []): array
    {
        return array_merge([
            'type' => 'post', 'status' => 'pending', 'title' => 'Live title', 'content' => '<p>Live body</p>',
            'excerpt' => 'Live excerpt', 'editor_type' => 'rich',
        ], $extra);
    }

    private function propose(array $extra = [], ?Post $post = null)
    {
        $post ??= $this->live;

        return $this->actingAs($this->writer)->put("/admin/posts/{$post->id}", $this->form($extra));
    }

    public function test_readers_keep_the_live_version_until_it_is_approved(): void
    {
        $this->propose(['title' => 'New title', 'content' => '<p>New body</p>'])->assertRedirect();

        $fresh = $this->live->fresh();
        $this->assertSame('Live title', $fresh->title);
        $this->assertSame('<p>Live body</p>', $fresh->content);
        $this->assertSame('published', $fresh->status);
        $this->get(get_falcon_permalink($fresh))->assertOk()->assertSee('Live body')->assertDontSee('New body');

        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve'])->assertRedirect();

        $fresh = $this->live->fresh();
        $this->assertSame('New title', $fresh->title);
        $this->assertSame('<p>New body</p>', $fresh->content);
        $this->assertSame('published', $fresh->status);
        $this->assertNull(PostChanges::find($fresh->id));
        auth()->logout();
        clear_page_cache();
        $this->get(get_falcon_permalink($fresh))->assertOk()->assertSee('New body');
    }

    public function test_only_what_differs_is_kept(): void
    {
        $this->propose(['excerpt' => 'New excerpt']);

        $this->assertSame(['excerpt' => 'New excerpt'], PostChanges::find($this->live->id)->data);
    }

    public function test_saving_nothing_new_leaves_nothing_to_review(): void
    {
        $this->propose();
        $this->assertNull(PostChanges::find($this->live->id));

        $this->propose(['title' => 'New title']);
        $this->propose();
        $this->assertNull(PostChanges::find($this->live->id), 'putting it back as it was should clear the change');
    }

    public function test_the_writer_carries_on_from_their_own_changes(): void
    {
        $this->propose(['title' => 'First edit']);

        $this->actingAs($this->writer)->get("/admin/posts/{$this->live->id}/edit")
            ->assertOk()->assertSee('First edit')->assertSee('Submit Changes for Review');

        $this->actingAs($this->reviewer)->get("/admin/posts/{$this->live->id}/edit")
            ->assertOk()->assertDontSee('value="First edit"', false)->assertSee('Review Changes');
    }

    public function test_price_address_and_status_are_not_taken_from_the_form(): void
    {
        $slug = $this->live->slug;
        $this->propose(['title' => 'New title', 'slug' => 'hijacked', 'published_at' => '2000-01-01 00:00:00', 'price' => '1']);

        $change = PostChanges::find($this->live->id);
        $this->assertSame(['title'], array_keys($change->data));

        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve']);
        $this->assertSame($slug, $this->live->fresh()->slug);
    }

    public function test_categories_tags_seo_and_excerpt_wait_too_and_apply_on_approval(): void
    {
        $category = Category::create(['name' => 'News', 'slug' => 'news']);

        $this->propose([
            'categories' => [$category->id], 'tags' => 'alpha, beta', 'seo' => ['title' => 'SEO title'], 'excerpt' => 'New excerpt',
        ]);

        $this->assertCount(0, $this->live->fresh()->categories);
        $this->assertCount(0, $this->live->fresh()->tags);

        $this->actingAs($this->reviewer)->get("/admin/posts/{$this->live->id}/changes")
            ->assertOk()->assertSee('News')->assertSee('alpha, beta')->assertSee('SEO title')->assertSee('New excerpt');

        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve']);

        $fresh = $this->live->fresh();
        $this->assertSame([$category->id], $fresh->categories->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['alpha', 'beta'], $fresh->tags->pluck('name')->all());
        $this->assertSame('SEO title', $fresh->seo_meta['title']);
        $this->assertSame('New excerpt', $fresh->excerpt);
    }

    public function test_the_replaced_version_goes_into_revisions(): void
    {
        $this->propose(['content' => '<p>New body</p>']);
        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve']);

        $this->assertSame('<p>Live body</p>', Revision::where('revisionable_id', $this->live->id)->where('type', 'revision')->latest('id')->value('content'));
    }

    public function test_an_edit_made_in_the_meantime_survives_approval(): void
    {
        $this->propose(['excerpt' => 'Writer excerpt']);

        $this->actingAs($this->reviewer)->put("/admin/posts/{$this->live->id}", $this->form(['status' => 'published', 'title' => 'Reviewer title']));
        PostLock::release($this->live->id, $this->reviewer->id);
        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve']);

        $fresh = $this->live->fresh();
        $this->assertSame('Reviewer title', $fresh->title);
        $this->assertSame('Writer excerpt', $fresh->excerpt);
    }

    public function test_requesting_changes_returns_them_with_the_note_and_touches_nothing_live(): void
    {
        $this->propose(['title' => 'New title']);

        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'return', 'note' => 'Check the numbers']);

        $this->assertSame('Live title', $this->live->fresh()->title);
        $this->assertSame('draft', PostChanges::find($this->live->id)->status);
        $this->actingAs($this->writer)->get("/admin/posts/{$this->live->id}/edit")->assertSee('Check the numbers')->assertSee('New title');

        $this->propose(['title' => 'Reworked title']);
        $this->assertSame('pending', PostChanges::find($this->live->id)->status);
        $this->assertNull(PostChanges::find($this->live->id)->note);
    }

    public function test_saving_for_later_does_not_ask_for_review(): void
    {
        $this->propose(['title' => 'Not yet', 'status' => 'draft']);

        $this->assertSame('draft', PostChanges::find($this->live->id)->status);
        $this->assertSame(0, PostChanges::pendingCount());
        $this->assertDatabaseMissing('cms_post_reviews', ['post_id' => $this->live->id, 'action' => 'changes_submitted']);
    }

    public function test_only_a_reviewer_can_decide_but_the_writer_can_discard(): void
    {
        $this->propose(['title' => 'New title']);

        $this->actingAs($this->writer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->writer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'return'])->assertForbidden();
        $this->assertSame('Live title', $this->live->fresh()->title);

        $this->actingAs($this->writer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'discard'])->assertRedirect();
        $this->assertNull(PostChanges::find($this->live->id));
        $this->assertSame('Live title', $this->live->fresh()->title);
    }

    public function test_someone_who_cannot_write_it_sees_nothing(): void
    {
        $this->propose(['title' => 'New title']);
        $reader = $this->userWith(['manage_posts', 'access_all_posts_posts'], 'zz-reader');

        $this->actingAs($reader)->get("/admin/posts/{$this->live->id}/changes")->assertForbidden();
        $this->actingAs($reader)->get("/admin/posts/{$this->live->id}/changes/preview")->assertForbidden();
        $this->actingAs($reader)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'discard'])->assertForbidden();
    }

    public function test_the_preview_shows_the_change_without_saving_it(): void
    {
        $this->propose(['content' => '<p>Previewed body</p>']);

        $this->actingAs($this->reviewer)->get("/admin/posts/{$this->live->id}/changes/preview")->assertOk()->assertSee('Previewed body');
        $this->assertSame('<p>Live body</p>', $this->live->fresh()->content);
    }

    public function test_the_builder_saves_to_review_and_reopens_from_it(): void
    {
        $layout = [['id' => 'c1', 'type' => 'container', 'settings' => [], 'columns' => []]];

        $this->actingAs($this->writer)->postJson("/admin/falcon-builder/{$this->live->id}/save", ['layout' => $layout])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('<p>Live body</p>', $this->live->fresh()->content);
        $change = PostChanges::find($this->live->id);
        $this->assertSame('pending', $change->status);
        $this->assertSame($layout, json_decode($change->data['content'], true));
    }

    public function test_restoring_a_revision_goes_to_review(): void
    {
        $revision = Revision::create([
            'revisionable_type' => $this->live->getMorphClass(), 'revisionable_id' => $this->live->id,
            'type' => 'revision', 'title' => 'Old title', 'content' => '<p>Old body</p>',
        ]);

        $this->actingAs($this->writer)->post("/admin/posts/{$this->live->id}/revisions/{$revision->id}/restore")->assertRedirect();

        $this->assertSame('<p>Live body</p>', $this->live->fresh()->content);
        $this->assertSame('<p>Old body</p>', PostChanges::find($this->live->id)->data['content']);
    }

    public function test_deleting_revisions_stays_with_those_who_can_publish(): void
    {
        $this->actingAs($this->writer)->delete("/admin/posts/{$this->live->id}/revisions")->assertForbidden();
    }

    public function test_a_page_works_the_same(): void
    {
        $page = $this->item('page');

        $this->actingAs($this->writer)->get("/admin/pages/{$page->id}/edit")->assertOk()->assertSee('Submit Changes for Review');
        $this->actingAs($this->writer)->put("/admin/pages/{$page->id}", ['title' => 'New page title', 'status' => 'pending', 'content' => '<p>Live body</p>', 'excerpt' => 'Live excerpt', 'editor_type' => 'rich'])->assertRedirect();

        $this->assertSame('Live title', $page->fresh()->title);
        $this->actingAs($this->reviewer)->post("/admin/posts/{$page->id}/changes", ['decision' => 'approve']);
        $this->assertSame('New page title', $page->fresh()->title);
    }

    public function test_a_scheduled_post_stays_scheduled(): void
    {
        $post = $this->item('post', 'scheduled');
        $this->propose(['title' => 'New title'], $post);
        $this->assertSame('Live title', $post->fresh()->title);

        $this->actingAs($this->reviewer)->post("/admin/posts/{$post->id}/changes", ['decision' => 'approve']);

        $this->assertSame('New title', $post->fresh()->title);
        $this->assertSame('scheduled', $post->fresh()->status);
    }

    public function test_a_publisher_saves_straight_to_live(): void
    {
        $this->actingAs($this->reviewer)->put("/admin/posts/{$this->live->id}", $this->form(['status' => 'published', 'title' => 'Straight']));

        $this->assertSame('Straight', $this->live->fresh()->title);
        $this->assertNull(PostChanges::find($this->live->id));
    }

    public function test_the_queue_and_the_admin_bar_count_it(): void
    {
        $this->propose(['title' => 'New title']);

        $this->actingAs($this->reviewer)->get('/admin/posts/review-queue')
            ->assertOk()->assertSee('Changes to Live Content')->assertSee('Live title');
        $this->assertSame(1, PostChanges::pendingCount());

        $this->actingAs($this->reviewer)->get('/admin/posts?type=post')->assertOk()->assertSee('Changes waiting for review');
    }

    public function test_a_writer_left_on_the_editor_does_not_hold_up_the_reviewer(): void
    {
        $this->propose(['title' => 'New title']);
        PostLock::acquire($this->live->id, $this->writer->id);

        $this->actingAs($this->reviewer)->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve']);

        $this->assertSame('New title', $this->live->fresh()->title);
    }

    public function test_another_publisher_editing_does_hold_it_up(): void
    {
        $this->propose(['title' => 'New title']);
        PostLock::acquire($this->live->id, $this->reviewer->id);

        $this->actingAs($this->userWith(array_merge(self::WRITE, ['publish_content']), 'zz-reviewer-2'))
            ->from('/admin/posts/review-queue')->post("/admin/posts/{$this->live->id}/changes", ['decision' => 'approve'])
            ->assertSessionHasErrors('lock');

        $this->assertSame('Live title', $this->live->fresh()->title);
    }

    public function test_the_api_sends_an_edit_to_review_and_cannot_delete_live(): void
    {
        $plain = $this->writer->createApiToken('test');
        $headers = ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];

        $this->withHeaders($headers)->putJson("/api/v1/posts/{$this->live->id}", ['title' => 'From a client', 'status' => 'draft'])
            ->assertStatus(202)->assertJson(['pending_review' => true]);

        $fresh = $this->live->fresh();
        $this->assertSame('Live title', $fresh->title);
        $this->assertSame('published', $fresh->status);
        $this->assertSame('From a client', PostChanges::find($fresh->id)->data['title']);

        $this->withHeaders($headers)->deleteJson("/api/v1/posts/{$this->live->id}")->assertForbidden();
        $this->assertNull($this->live->fresh()->deleted_at);
        $this->assertTrue(class_exists(ApiToken::class));
    }

    public function test_deleting_an_item_for_good_removes_its_change(): void
    {
        $this->propose(['title' => 'New title']);

        $this->live->forceDelete();

        $this->assertSame(0, DB::table(PostChanges::TABLE)->count());
    }

    public function test_the_writer_is_told_and_the_reviewers_are_asked(): void
    {
        config(['mail.default' => 'array']);
        $this->propose(['title' => 'New title']);
        $this->app->terminate();

        $subjects = collect(Mail::mailer('array')->getSymfonyTransport()->messages())
            ->map(fn ($m) => [$m->getOriginalMessage()->getTo()[0]->getAddress(), $m->getOriginalMessage()->getSubject()]);

        $this->assertTrue($subjects->contains([$this->reviewer->email, 'Changes to review: Live title']));
        $this->assertFalse($subjects->contains(fn ($row) => $row[0] === $this->writer->email));
    }
}
