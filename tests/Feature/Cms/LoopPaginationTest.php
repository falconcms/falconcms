<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The loop's paginator keeps the rest of the query string.
 *
 * A theme that paginates its own listing normally has something else in the address too — a
 * search box, a filter, a sort. get_falcon_posts() built its page links without any of it, so
 * page two quietly became the unfiltered list: no error, a reader who searched for something
 * and then turned the page just stopped seeing their results. The archive controller has
 * called withQueryString() since it was written; the loop had not.
 */
class LoopPaginationTest extends TestCase
{
    private function seedPosts(int $count): void
    {
        Post::where('type', 'post')->forceDelete();
        for ($i = 1; $i <= $count; $i++) {
            Post::forceCreate([
                'user_id' => 1,
                'title' => 'Paged post '.$i,
                'slug' => 'paged-post-'.$i,
                'content' => '<p>'.$i.'</p>',
                'type' => 'post',
                'status' => 'published',
                'lang_code' => 'en',
            ]);
        }
    }

    public function test_paginate_returns_a_paginator(): void
    {
        $this->seedPosts(9);

        $posts = get_falcon_posts(['post_type' => 'post', 'limit' => 4, 'paginate' => true]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertSame(9, $posts->total());
        $this->assertSame(3, $posts->lastPage());
        $this->assertCount(4, $posts->items());
    }

    public function test_the_page_links_keep_the_rest_of_the_query_string(): void
    {
        $this->seedPosts(9);

        $this->get('/?q=oats&sort=newest');   // put something else in the address
        request()->merge(['q' => 'oats', 'sort' => 'newest']);
        request()->server->set('QUERY_STRING', 'q=oats&sort=newest');

        $posts = get_falcon_posts(['post_type' => 'post', 'limit' => 4, 'paginate' => true]);
        $next = $posts->nextPageUrl();

        $this->assertStringContainsString('page=2', $next);
        $this->assertStringContainsString('q=oats', $next, 'the search term was dropped from page two');
        $this->assertStringContainsString('sort=newest', $next, 'the sort was dropped from page two');
    }

    public function test_a_plain_address_gains_nothing_but_the_page(): void
    {
        $this->seedPosts(9);

        $next = get_falcon_posts(['post_type' => 'post', 'limit' => 4, 'paginate' => true])
            ->nextPageUrl();

        $this->assertStringEndsWith('page=2', $next);
    }

    public function test_without_paginate_it_is_still_a_plain_collection(): void
    {
        $this->seedPosts(9);

        $posts = get_falcon_posts(['post_type' => 'post', 'limit' => 4]);

        $this->assertNotInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(4, $posts);
    }

    public function test_the_pagination_helper_ignores_anything_that_is_not_a_paginator(): void
    {
        // A theme that forgets 'paginate' => true gets an empty string, not a crash.
        $this->assertSame('', the_falcon_pagination(collect([1, 2, 3])));
        $this->assertSame('', the_falcon_pagination(null));
    }
}
