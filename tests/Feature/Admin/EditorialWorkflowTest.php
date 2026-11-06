<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Page;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\PostLock;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Writing and publishing are separate, on every route that can publish, and one person edits an
 * item at a time.
 */
class EditorialWorkflowTest extends TestCase
{
    use MakesShopFixtures;

    private const WRITE = ['manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'manage_pages', 'access_all_pages_pages', 'access_add_new_pages'];

    protected function setUp(): void
    {
        parent::setUp();
        (new MenuSeeder)->run();
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

    private function writer(): User
    {
        return $this->userWith(self::WRITE, 'zz-writer');
    }

    private function reviewer(): User
    {
        return $this->userWith(array_merge(self::WRITE, ['publish_content']), 'zz-reviewer');
    }

    private function item(string $status, string $type = 'post', array $extra = []): Post
    {
        return Post::create(array_merge([
            'title' => "A {$type}", 'slug' => $type.'-'.uniqid(), 'type' => $type, 'status' => $status,
            'lang_code' => 'en', 'content' => 'original', 'published_at' => now()->subDay(),
        ], $extra));
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['title' => 'Edited', 'type' => 'post', 'content' => 'edited', 'status' => 'published'], $extra);
    }

    public function test_a_writer_cannot_publish_a_new_post_or_page(): void
    {
        $writer = $this->writer();

        $this->actingAs($writer)->post('/admin/posts', $this->payload(['title' => 'New post']));
        $this->assertSame('pending', Post::where('title', 'New post')->value('status'));

        $this->actingAs($writer)->post('/admin/pages', ['title' => 'New page', 'status' => 'published', 'content' => 'x']);
        $this->assertSame('pending', Page::where('title', 'New page')->value('status'));

        $this->actingAs($writer)->post('/admin/posts', $this->payload(['title' => 'Later', 'status' => 'scheduled', 'published_at' => now()->addWeek()->format('Y-m-d H:i:s')]));
        $this->assertSame('pending', Post::where('title', 'Later')->value('status'));
    }

    public function test_a_writer_cannot_publish_a_draft_of_their_own(): void
    {
        $writer = $this->writer();
        $draft = $this->item('draft', 'post', ['user_id' => $writer->id]);

        $this->actingAs($writer)->put("/admin/posts/{$draft->id}", $this->payload());

        $this->assertSame('pending', $draft->fresh()->status);
        $this->assertSame('edited', $draft->fresh()->content);
    }

    #[DataProvider('liveStatuses')]
    public function test_a_writer_cannot_change_what_is_live(string $status): void
    {
        $writer = $this->writer();
        $post = $this->item($status);
        $page = $this->item($status, 'page');

        // Editing is open, and what they save waits for review (PostChangesTest).
        $this->actingAs($writer)->get("/admin/posts/{$post->id}/edit")->assertOk();
        $this->actingAs($writer)->put("/admin/posts/{$post->id}", $this->payload(['status' => 'draft']))->assertRedirect();
        $this->actingAs($writer)->post("/admin/falcon-builder/{$post->id}/save", ['layout' => []])->assertOk();
        $this->actingAs($writer)->get("/admin/pages/{$page->id}/edit")->assertOk();
        // Taking it down is not an edit.
        $this->actingAs($writer)->delete("/admin/posts/{$post->id}")->assertForbidden();
        $this->actingAs($writer)->delete("/admin/pages/{$page->id}")->assertForbidden();
        $this->actingAs($writer)->post("/admin/posts/{$post->id}/variations/ajax", [])->assertForbidden();

        $this->assertSame($status, $post->fresh()->status);
        $this->assertSame('original', $post->fresh()->content);
        $this->assertNull($page->fresh()->deleted_at);
    }

    public static function liveStatuses(): array
    {
        return ['published' => ['published'], 'scheduled' => ['scheduled']];
    }

    public function test_bulk_actions_are_held_to_the_same_rules(): void
    {
        $writer = $this->writer();
        $draft = $this->item('draft');
        $live = $this->item('published');

        $this->actingAs($writer)->post('/admin/posts/bulk', ['post_ids' => [$draft->id], 'action' => 'published', 'action2' => '-1']);
        $this->assertSame('draft', $draft->fresh()->status);

        $this->actingAs($writer)->post('/admin/posts/bulk', ['post_ids' => [$draft->id, $live->id], 'action' => 'trash', 'action2' => '-1']);
        $this->assertNotNull($draft->fresh()->deleted_at);
        $this->assertNull($live->fresh()->deleted_at);
    }

    public function test_a_writer_cannot_restore_a_trashed_live_post_back_to_readers(): void
    {
        $live = $this->item('published');
        $live->delete();

        $this->actingAs($this->writer())->post("/admin/posts/{$live->id}/restore")->assertForbidden();
        $this->assertNotNull($live->fresh()->deleted_at);
    }

    public function test_the_api_cannot_publish_without_the_permission(): void
    {
        $writer = $this->writer();
        $this->actingAs($writer);
        $this->withServerVariables(['REQUEST_URI' => '/api/v1/posts']);

        $request = request()->create('/api/v1/posts', 'POST');
        $this->app->instance('request', $request);

        $post = Post::create(['title' => 'From a client', 'slug' => 'from-a-client', 'type' => 'post', 'status' => 'published', 'lang_code' => 'en']);

        $this->assertSame('pending', $post->fresh()->status);
    }

    public function test_someone_who_can_publish_publishes_as_before(): void
    {
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)->post('/admin/posts', $this->payload(['title' => 'Straight out']));

        $this->assertSame('published', Post::where('title', 'Straight out')->value('status'));
    }

    public function test_pending_work_is_not_public(): void
    {
        $this->item('pending', 'post', ['slug' => 'waiting-story', 'title' => 'Waiting story']);
        $this->item('pending', 'page', ['slug' => 'waiting-page']);

        $this->get('/post/waiting-story')->assertNotFound();
        $this->get('/waiting-page')->assertNotFound();
        $this->assertNotContains('Waiting story', get_falcon_posts(['post_type' => 'post'])->pluck('title')->all());
    }

    public function test_a_pending_date_does_not_schedule_it(): void
    {
        $writer = $this->writer();
        $draft = $this->item('draft');

        $this->actingAs($writer)->put("/admin/posts/{$draft->id}", $this->payload([
            'status' => 'pending', 'published_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]));

        $this->assertSame('pending', $draft->fresh()->status);
    }

    public function test_approving_publishes_it_now_and_records_who(): void
    {
        $reviewer = $this->reviewer();
        $item = $this->item('pending', 'post', ['user_id' => $this->writer()->id, 'published_at' => now()->subYear()]);

        $this->actingAs($reviewer)->post("/admin/posts/{$item->id}/review", ['decision' => 'approve'])->assertRedirect();

        $this->assertSame('published', $item->fresh()->status);
        $this->assertTrue($item->fresh()->published_at->isToday());
        $this->assertSame((int) $reviewer->id, (int) DB::table('cms_post_reviews')->where('post_id', $item->id)->where('action', 'approved')->value('user_id'));
    }

    public function test_approving_keeps_a_future_date_and_schedules_it(): void
    {
        $item = $this->item('pending', 'post', ['published_at' => now()->addWeek()]);

        $this->actingAs($this->reviewer())->post("/admin/posts/{$item->id}/review", ['decision' => 'approve']);

        $this->assertSame('scheduled', $item->fresh()->status);
    }

    public function test_returning_it_gives_the_writer_a_draft_and_the_note(): void
    {
        $writer = $this->writer();
        $this->actingAs($writer)->post('/admin/posts', $this->payload(['title' => 'Needs work']));
        $item = Post::where('title', 'Needs work')->firstOrFail();

        $this->actingAs($this->reviewer())->post("/admin/posts/{$item->id}/review", ['decision' => 'return', 'note' => 'Check the numbers']);

        $this->assertSame('draft', $item->fresh()->status);
        $this->actingAs($writer)->get("/admin/posts/{$item->id}/edit")->assertOk()->assertSee('Check the numbers')->assertSee('Submit for Review');
    }

    public function test_a_writer_cannot_review(): void
    {
        $item = $this->item('pending');

        $this->actingAs($this->writer())->post("/admin/posts/{$item->id}/review", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->writer())->get('/admin/posts/review-queue')->assertForbidden();

        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_the_queue_lists_every_type_a_reviewer_can_write(): void
    {
        $this->item('pending', 'post', ['title' => 'Queued post']);
        $this->item('pending', 'page', ['title' => 'Queued page']);
        $this->item('draft', 'post', ['title' => 'Only a draft']);

        $this->actingAs($this->reviewer())->get('/admin/posts/review-queue')
            ->assertOk()->assertSee('Queued post')->assertSee('Queued page')->assertDontSee('Only a draft');
    }

    public function test_reviewers_and_the_writer_are_told(): void
    {
        config(['mail.default' => 'array']);
        $sent = function (): array {
            $this->app->terminate();
            $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
            $out = [];
            foreach ($messages as $message) {
                $email = $message->getOriginalMessage();
                $out[] = [$email->getTo()[0]->getAddress(), $email->getSubject(), $email->getTextBody()];
            }
            $messages->splice(0);

            return $out;
        };

        $writer = $this->writer();
        $reviewer = $this->reviewer();

        $this->actingAs($writer)->post('/admin/posts', $this->payload(['title' => 'Tell them']));
        $item = Post::where('title', 'Tell them')->firstOrFail();

        $toReviewers = $sent();
        $this->assertContains([$reviewer->email, 'Review requested: Tell them'], array_map(fn ($m) => [$m[0], $m[1]], $toReviewers));
        $this->assertNotContains($writer->email, array_column($toReviewers, 0));

        $this->actingAs($reviewer)->post("/admin/posts/{$item->id}/review", ['decision' => 'return', 'note' => 'Check the numbers']);

        // terminate() here also reruns the earlier mail's callback, so pick out the new message.
        $toWriter = array_values(array_filter($sent(), fn ($m) => $m[1] === 'Changes requested: Tell them'));
        $this->assertSame([$writer->email], array_values(array_unique(array_column($toWriter, 0))));
        $this->assertStringContainsString('Check the numbers', $toWriter[0][2]);
    }

    public function test_the_roles_screen_offers_the_permission(): void
    {
        $role = Role::firstOrCreate(['slug' => 'zz-writer'], ['name' => 'zz-writer']);
        $admin = $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('publish_content');
    }

    public function test_the_migration_keeps_publishing_for_existing_roles_except_contributors(): void
    {
        // Every role already set up, as on a site being updated.
        $any = Permission::firstOrCreate(['slug' => 'manage_posts'], ['name' => 'Manage Posts'])->id;
        foreach (Role::all() as $role) {
            $role->permissions()->sync([$any]);
        }

        (require __DIR__.'/../../../database/migrations/2026_11_05_000001_create_editorial_workflow_tables.php')->up();

        $id = (int) DB::table('permissions')->where('slug', 'publish_content')->value('id');
        $holds = fn (string $slug) => DB::table('role_permission')->where('permission_id', $id)
            ->where('role_id', DB::table('roles')->where('slug', $slug)->value('id'))->exists();

        foreach (DB::table('roles')->pluck('slug') as $slug) {
            $this->assertSame(!in_array($slug, ['contributor', 'subscriber', 'customer'], true), $holds($slug), $slug);
        }
    }

    public function test_the_migration_leaves_a_fresh_install_to_the_seeder(): void
    {
        DB::table('role_permission')->delete();

        (require __DIR__.'/../../../database/migrations/2026_11_05_000001_create_editorial_workflow_tables.php')->up();

        $this->assertSame(0, DB::table('role_permission')->count());
    }

    public function test_layout_sections_stay_out_of_the_workflow(): void
    {
        $writer = $this->writer();
        $this->actingAs($writer);
        $this->app->instance('request', request()->create('/admin/posts', 'POST'));

        $section = Post::create(['title' => 'Header', 'slug' => 'header-x', 'type' => 'falcon_header', 'status' => 'published', 'lang_code' => 'en']);

        $this->assertSame('published', $section->fresh()->status);
    }

    // ── locks ───────────────────────────────────────────────────────────

    public function test_saving_is_refused_while_someone_else_edits(): void
    {
        $first = $this->reviewer();
        $second = $this->reviewer();
        $post = $this->item('draft');
        $page = $this->item('draft', 'page');

        PostLock::acquire($post->id, $first->id);
        PostLock::acquire($page->id, $first->id);

        $this->actingAs($second)->from("/admin/posts/{$post->id}/edit")
            ->put("/admin/posts/{$post->id}", $this->payload())
            ->assertRedirect()->assertSessionHasErrors('lock');
        $this->actingAs($second)->postJson("/admin/falcon-builder/{$post->id}/save", ['layout' => []])
            ->assertStatus(423)->assertJson(['locked' => true]);
        $this->actingAs($second)->postJson("/admin/falcon-builder/{$post->id}/autosave", ['layout' => []])->assertStatus(423);
        $this->actingAs($second)->from('/admin/posts')->delete("/admin/posts/{$post->id}")->assertSessionHasErrors('lock');
        $this->actingAs($second)->from("/admin/pages/{$page->id}/edit")
            ->put("/admin/pages/{$page->id}", ['title' => 'x', 'status' => 'draft'])->assertSessionHasErrors('lock');

        $this->assertSame('original', $post->fresh()->content);
        $this->assertNull($post->fresh()->deleted_at);

        $this->actingAs($first)->put("/admin/posts/{$post->id}", $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('edited', $post->fresh()->content);
    }

    public function test_taking_over_moves_the_lock_and_stops_the_first_editor(): void
    {
        $first = $this->reviewer();
        $second = $this->reviewer();
        $post = $this->item('draft');

        $this->actingAs($first)->postJson("/admin/posts/{$post->id}/lock")->assertJson(['locked' => false]);
        $this->actingAs($second)->postJson("/admin/posts/{$post->id}/lock")->assertJson(['locked' => true, 'holder' => ['id' => $first->id]]);
        $this->actingAs($second)->postJson("/admin/posts/{$post->id}/lock", ['force' => 1])->assertJson(['locked' => false]);

        $this->actingAs($first)->postJson("/admin/posts/{$post->id}/lock")->assertJson(['locked' => true, 'holder' => ['id' => $second->id]]);
        $this->actingAs($first)->postJson("/admin/falcon-builder/{$post->id}/save", ['layout' => []])->assertStatus(423);
    }

    public function test_a_lock_lapses_and_leaving_releases_it(): void
    {
        $first = $this->reviewer();
        $second = $this->reviewer();
        $post = $this->item('draft');

        PostLock::acquire($post->id, $first->id);
        DB::table(PostLock::TABLE)->where('post_id', $post->id)->update(['heartbeat_at' => now()->subSeconds(PostLock::TTL_SECONDS + 1)]);
        $this->assertNull(PostLock::acquire($post->id, $second->id));

        $this->actingAs($second)->post("/admin/posts/{$post->id}/unlock")->assertOk();
        $this->assertNull(PostLock::holder($post->id, $first->id));
    }

    public function test_only_the_holder_can_release_a_lock(): void
    {
        $first = $this->reviewer();
        $post = $this->item('draft');
        PostLock::acquire($post->id, $first->id);

        $this->actingAs($this->reviewer())->post("/admin/posts/{$post->id}/unlock");

        $this->assertSame((int) $first->id, PostLock::holder($post->id)['id']);
    }

    public function test_someone_who_cannot_write_cannot_take_a_lock(): void
    {
        $post = $this->item('draft');

        $this->actingAs($this->userWith(['manage_posts', 'access_all_posts_posts'], 'zz-reader'))
            ->postJson("/admin/posts/{$post->id}/lock")->assertForbidden();
    }

    public function test_bulk_actions_skip_what_someone_else_has_open(): void
    {
        $post = $this->item('draft');
        PostLock::acquire($post->id, $this->reviewer()->id);

        $this->actingAs($this->reviewer())->post('/admin/posts/bulk', ['post_ids' => [$post->id], 'action' => 'trash', 'action2' => '-1']);

        $this->assertNull($post->fresh()->deleted_at);
    }

    public function test_every_editor_carries_the_lock(): void
    {
        $user = $this->reviewer();
        foreach ([
            '/admin/posts/'.$this->item('draft')->id.'/edit',
            '/admin/pages/'.$this->item('draft', 'page')->id.'/edit',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('falcon-edit-lock', false);
        }
    }
}
