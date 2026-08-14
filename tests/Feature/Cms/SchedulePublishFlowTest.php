<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;

/**
 * DB-backed end-to-end checks for the scheduling lifecycle.
 * Runs against an isolated in-memory sqlite DB (phpunit.xml) — never touches real data.
 */
class SchedulePublishFlowTest extends TestCase
{
    private function makeUser(): User
    {
        return User::factory()->create(['is_blocked' => false]);
    }

    public function test_due_scheduled_post_is_auto_published_by_command(): void
    {
        $user = $this->makeUser();

        $post = Post::create([
            'title' => 'Due Post', 'slug' => 'due-post', 'type' => 'post',
            'status' => 'scheduled', 'published_at' => Carbon::now()->subMinute(),
            'user_id' => $user->id, 'lang_code' => 'en',
        ]);

        $this->artisan('falcon:publish-scheduled')->assertExitCode(0);

        $this->assertSame('published', $post->fresh()->status);
    }

    public function test_future_scheduled_post_stays_scheduled_and_is_hidden(): void
    {
        $user = $this->makeUser();

        $post = Post::create([
            'title' => 'Future Post', 'slug' => 'future-post', 'type' => 'post',
            'status' => 'scheduled', 'published_at' => Carbon::now()->addDay(),
            'user_id' => $user->id, 'lang_code' => 'en',
        ]);

        $this->artisan('falcon:publish-scheduled')->assertExitCode(0);

        // Still scheduled...
        $this->assertSame('scheduled', $post->fresh()->status);
        // ...and excluded from the published scope (so it won't show on the front-end).
        $this->assertFalse(Post::published()->whereKey($post->id)->exists());
    }

    public function test_published_post_is_visible_in_published_scope(): void
    {
        $user = $this->makeUser();

        $post = Post::create([
            'title' => 'Live Post', 'slug' => 'live-post', 'type' => 'post',
            'status' => 'published', 'published_at' => Carbon::now()->subDay(),
            'user_id' => $user->id, 'lang_code' => 'en',
        ]);

        $this->assertTrue(Post::published()->whereKey($post->id)->exists());
    }

    public function test_cms_timezone_reads_the_saved_setting(): void
    {
        update_cms_option('timezone', 'Asia/Dhaka');
        $this->assertSame('Asia/Dhaka', cms_timezone());

        update_cms_option('timezone', 'UTC');
        $this->assertSame('UTC', cms_timezone());
    }
}
