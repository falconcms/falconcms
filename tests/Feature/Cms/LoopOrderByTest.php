<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;

/**
 * get_falcon_posts() can be ordered by publish date.
 *
 * The orderby argument is checked against a whitelist before it reaches the query, and
 * published_at was missing from it. Anything outside the list falls back to created_at
 * silently, so a theme asking for the latest published posts got the latest *created* ones
 * instead — identical on a fresh site, wrong the moment a post is back-dated or scheduled,
 * and with nothing in the output to say the argument had been ignored.
 */
class LoopOrderByTest extends TestCase
{
    private function makePost(string $title, string $created, string $published): Post
    {
        return Post::forceCreate([
            'user_id' => 1,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'content' => '<p>'.$title.'</p>',
            'type' => 'post',
            'status' => 'published',
            'lang_code' => 'en',
            'created_at' => Carbon::parse($created),
            'updated_at' => Carbon::parse($created),
            'published_at' => Carbon::parse($published),
        ]);
    }

    /**
     * Created in one order, published in the reverse: the two columns disagree, which is the
     * only way to tell which one the loop actually used.
     */
    private function seedCrossedDates(): void
    {
        Post::where('type', 'post')->forceDelete();
        $this->makePost('Written first, published last', '2026-01-01 10:00', '2026-03-01 10:00');
        $this->makePost('Written last, published first', '2026-02-01 10:00', '2026-02-01 09:00');
    }

    public function test_posts_can_be_ordered_by_published_at(): void
    {
        $this->seedCrossedDates();

        $titles = get_falcon_posts([
            'post_type' => 'post',
            'orderby' => 'published_at',
            'order' => 'desc',
        ])->pluck('title')->all();

        $this->assertSame([
            'Written first, published last',
            'Written last, published first',
        ], $titles);
    }

    public function test_created_at_ordering_still_differs_from_published_at(): void
    {
        $this->seedCrossedDates();

        $titles = get_falcon_posts([
            'post_type' => 'post',
            'orderby' => 'created_at',
            'order' => 'desc',
        ])->pluck('title')->all();

        $this->assertSame([
            'Written last, published first',
            'Written first, published last',
        ], $titles);
    }

    public function test_an_unknown_column_still_falls_back_to_created_at(): void
    {
        $this->seedCrossedDates();

        $titles = get_falcon_posts([
            'post_type' => 'post',
            'orderby' => 'password',
            'order' => 'desc',
        ])->pluck('title')->all();

        $this->assertSame([
            'Written last, published first',
            'Written first, published last',
        ], $titles);
    }
}
