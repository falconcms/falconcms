<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The sitemap has to survive whatever is in the posts table.
 *
 * It is fetched by search engines, not by people, so nobody sees it fail — the site
 * simply stops being crawled properly. It is also the one page whose content is entirely
 * out of the code's control: every published row goes into it, however that row was
 * written. Content imported from WordPress, or inserted by a seeder or a raw query, can
 * arrive with no timestamps and no slug, and the sitemap used to return a 500 for the
 * whole site when it met one — `->toAtomString()` on a null `updated_at`.
 *
 * So these tests feed it the rows that broke it rather than the tidy ones a factory makes.
 */
class SitemapTest extends TestCase
{
    private function publishedPost(array $overrides = []): Post
    {
        PostType::firstOrCreate(
            ['slug' => 'post'],
            ['name' => 'Posts', 'singular_name' => 'Post', 'is_active' => true]
        );

        return Post::forceCreate(array_merge([
            'title' => 'A published post',
            'slug' => 'a-published-post',
            'type' => 'post',
            'status' => 'published',
            'content' => 'Body',
        ], $overrides));
    }

    public function test_it_lists_a_published_post(): void
    {
        $this->publishedPost();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('a-published-post', false);
    }

    /**
     * The row that used to take the whole sitemap down. lastmod is optional in the
     * sitemap spec, so a post without timestamps is still a valid entry.
     */
    public function test_a_post_without_timestamps_does_not_break_it(): void
    {
        $post = $this->publishedPost(['slug' => 'no-timestamps']);
        DB::table('posts')->where('id', $post->id)->update([
            'updated_at' => null,
            'created_at' => null,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('no-timestamps', false);
    }

    /** created_at stands in when only updated_at is missing. */
    public function test_it_falls_back_to_created_at_for_lastmod(): void
    {
        $post = $this->publishedPost(['slug' => 'only-created']);
        DB::table('posts')->where('id', $post->id)->update([
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => null,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('2026-01-02', false);
    }

    /**
     * A row with no slug would otherwise emit the site root as its <loc>, listing the
     * home page again for every such row.
     */
    public function test_a_post_without_a_slug_is_left_out(): void
    {
        $keep = $this->publishedPost(['slug' => 'has-a-slug']);
        $drop = $this->publishedPost(['slug' => 'placeholder']);
        DB::table('posts')->where('id', $drop->id)->update(['slug' => '']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('has-a-slug', false);

        // Home appears exactly once: the entry the sitemap always opens with.
        $this->assertSame(
            1,
            substr_count($response->getContent(), '<loc>'.url('/').'</loc>'),
            'a slugless post added a second entry pointing at the home page'
        );
    }

    public function test_a_draft_is_not_listed(): void
    {
        $this->publishedPost(['slug' => 'a-draft', 'status' => 'draft']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertDontSee('a-draft', false);
    }
}
