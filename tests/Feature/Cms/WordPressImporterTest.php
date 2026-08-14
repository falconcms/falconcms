<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\WordPressImporter;
use FalconCms\Core\Tests\TestCase;

class WordPressImporterTest extends TestCase
{
    private function sampleWxr(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>Demo Site</title>
  <link>https://demo.example</link>
  <wp:base_site_url>https://demo.example</wp:base_site_url>
  <wp:author><wp:author_login>admin</wp:author_login><wp:author_email>a@a.com</wp:author_email><wp:author_display_name>Admin</wp:author_display_name></wp:author>
  <wp:category><wp:category_nicename>news</wp:category_nicename><wp:cat_name>News</wp:cat_name><wp:category_parent></wp:category_parent></wp:category>
  <wp:tag><wp:tag_slug>laravel</wp:tag_slug><wp:tag_name>Laravel</wp:tag_name></wp:tag>
  <item>
    <title>Hello WP</title>
    <dc:creator>admin</dc:creator>
    <content:encoded><![CDATA[<p>Hello <strong>world</strong></p>]]></content:encoded>
    <excerpt:encoded><![CDATA[Hi]]></excerpt:encoded>
    <wp:post_id>10</wp:post_id>
    <wp:post_date>2024-01-02 09:00:00</wp:post_date>
    <wp:post_date_gmt>2024-01-02 03:00:00</wp:post_date_gmt>
    <wp:post_name>hello-wp</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>0</wp:menu_order>
    <wp:post_type>post</wp:post_type>
    <category domain="category" nicename="news">News</category>
    <category domain="post_tag" nicename="laravel">Laravel</category>
    <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>11</wp:meta_value></wp:postmeta>
  </item>
  <item>
    <title>About</title>
    <content:encoded><![CDATA[<p>About page</p>]]></content:encoded>
    <wp:post_id>20</wp:post_id>
    <wp:post_name>about</wp:post_name>
    <wp:status>draft</wp:status>
    <wp:post_type>page</wp:post_type>
  </item>
  <item>
    <title>img</title>
    <wp:post_id>11</wp:post_id>
    <wp:post_type>attachment</wp:post_type>
    <wp:attachment_url>https://demo.example/img.jpg</wp:attachment_url>
  </item>
</channel>
</rss>
XML;
    }

    public function test_parser_extracts_posts_pages_and_metadata(): void
    {
        $p = WordPressImporter::parse($this->sampleWxr());

        // attachment is not a content item, but its URL is captured
        $this->assertCount(2, $p['items']);
        $this->assertSame('https://demo.example/img.jpg', $p['attachments'][11]);

        $post = $p['items'][0];
        $this->assertSame('post', $post['type']);
        $this->assertSame('publish', $post['status']);
        $this->assertSame('hello-wp', $post['slug']);
        $this->assertSame(11, $post['thumbnail_id']);
        $this->assertSame('news', $post['categories'][0]['slug']);
        $this->assertSame('laravel', $post['tags'][0]['slug']);

        $this->assertSame('admin', $p['authors'][0]['login']);
        $this->assertSame('news', $p['categories'][0]['slug']);
    }

    public function test_parser_returns_empty_for_non_wxr(): void
    {
        $this->assertSame([], WordPressImporter::parse('<html>not wxr</html>')['items']);
    }

    public function test_import_creates_posts_pages_categories_and_tags(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $summary = (new WordPressImporter)->importFromXml($this->sampleWxr(), ['user_id' => $user->id, 'lang' => 'en']);

        $this->assertSame(1, $summary['posts']);
        $this->assertSame(1, $summary['pages']);

        $post = Post::where('type', 'post')->where('slug', 'hello-wp')->first();
        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertStringContainsString('Hello', $post->content);
        $this->assertSame('https://demo.example/img.jpg', $post->featured_image); // featured via _thumbnail_id
        $this->assertSame('news', $post->categories()->first()->slug);
        $this->assertSame('laravel', $post->tags()->first()->slug);

        $page = Post::where('type', 'page')->where('slug', 'about')->first();
        $this->assertNotNull($page);
        $this->assertSame('draft', $page->status);
    }

    public function test_reimport_is_idempotent(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);
        $opts = ['user_id' => $user->id, 'lang' => 'en'];

        (new WordPressImporter)->importFromXml($this->sampleWxr(), $opts);
        $second = (new WordPressImporter)->importFromXml($this->sampleWxr(), $opts);

        // Second run creates nothing new — every item in the file already exists.
        $this->assertSame(0, $second['posts']);
        $this->assertSame(0, $second['pages']);
        $this->assertSame(0, $second['categories']);
        $this->assertSame(0, $second['tags']);

        // `skipped` counts every item that was already there — posts, pages, categories
        // and tags alike — so it is asserted as "everything", not as a fixed number that
        // has to be edited whenever the sample file gains an entry.
        $this->assertGreaterThanOrEqual(2, $second['skipped']);

        $this->assertSame(1, Post::where('slug', 'hello-wp')->count());
        $this->assertSame(1, Post::where('slug', 'about')->count());
    }
}
