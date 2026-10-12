<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Controllers\SitemapController;
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

    /**
     * Each entry is the address the site links to and names as canonical (/post/slug), not a
     * bare /slug that answered too: Google was handed two addresses for every post.
     */
    public function test_entries_use_the_canonical_permalink(): void
    {
        $post = $this->publishedPost(['slug' => 'canonical-one', 'lang_code' => 'en']);
        $page = Post::forceCreate(['title' => 'About', 'slug' => 'about', 'type' => 'page', 'status' => 'published', 'content' => 'x', 'lang_code' => 'en']);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/post/canonical-one').'</loc>', $body);
        $this->assertStringNotContainsString('<loc>'.url('/canonical-one').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/about').'</loc>', $body);
    }

    public function test_noindexed_posts_and_the_home_page_are_not_listed_again(): void
    {
        $this->publishedPost(['slug' => 'kept-out', 'seo_meta' => ['noindex' => '1']]);
        $home = Post::forceCreate(['title' => 'Home', 'slug' => 'home', 'type' => 'page', 'status' => 'published', 'content' => 'x', 'lang_code' => 'en']);
        $this->setCmsOptions(['home_page_id' => (string) $home->id]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('kept-out', $body);
        $this->assertStringNotContainsString('<loc>'.url('/home').'</loc>', $body);
    }

    /** Google Images finds a page's pictures through the sitemap: featured image first, then the content's. */
    public function test_each_post_lists_its_images(): void
    {
        $json = json_encode([['type' => 'image', 'settings' => ['src' => '/storage/media/builder-photo.webp']]]);
        $this->publishedPost(['slug' => 'with-images', 'featured_image' => 'media/cover.jpg',
            'content' => '<p><img src="https://cdn.example.com/a.png" alt="a"><img src="data:image/gif;base64,AAA"></p>'.$json]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $body);
        $this->assertStringContainsString('<image:loc>'.asset('storage/media/cover.jpg').'</image:loc>', $body);
        $this->assertStringContainsString('<image:loc>https://cdn.example.com/a.png</image:loc>', $body);
        $this->assertStringContainsString('<image:loc>'.url('/storage/media/builder-photo.webp').'</image:loc>', $body);
        $this->assertStringNotContainsString('data:image', $body);
        $this->assertNotFalse(simplexml_load_string($body), 'the sitemap is well-formed XML');
    }

    /** Past PER_FILE addresses, sitemap.xml becomes an index of sitemap-1.xml, sitemap-2.xml… */
    public function test_a_large_site_gets_a_sitemap_index_of_numbered_files(): void
    {
        PostType::firstOrCreate(['slug' => 'post'], ['name' => 'Posts', 'singular_name' => 'Post', 'is_active' => true]);
        $rows = [];
        for ($i = 1; $i <= SitemapController::PER_FILE + 5; $i++) {
            $rows[] = ['title' => "Post $i", 'slug' => "post-$i", 'type' => 'post', 'status' => 'published', 'lang_code' => 'en',
                'content' => '', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('posts')->insert($chunk);
        }

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertStringContainsString('<loc>'.url('/sitemap-1.xml').'</loc>', $index);
        $this->assertStringContainsString('<loc>'.url('/sitemap-2.xml').'</loc>', $index);

        $first = $this->get('/sitemap-1.xml')->assertOk()->getContent();
        $second = $this->get('/sitemap-2.xml')->assertOk()->getContent();
        $this->assertSame(SitemapController::PER_FILE, substr_count($first, '<url>'));
        // Home + 1,005 posts: the first file holds home and 999 posts, the second the last six
        // (plus whatever pages the site has, such as the shop's).
        $this->assertSame(6, substr_count($second, '/post/post-'), 'home + 1005 posts = 1006 addresses');
        $this->get('/sitemap-3.xml')->assertNotFound();
    }

    public function test_a_draft_is_not_listed(): void
    {
        $this->publishedPost(['slug' => 'a-draft', 'status' => 'draft']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertDontSee('a-draft', false);
    }
}
