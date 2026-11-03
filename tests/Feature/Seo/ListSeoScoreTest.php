<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;

/**
 * The SEO percentage beside each row in the posts and pages lists (Post::getSeoScore()).
 *
 * On falconcms.com no page could pass 70%: the last 30 points came from the long-dead meta
 * keywords field and a hand-set social image, ignoring the Focus Keyword the editor asks for and
 * the featured or default image the page actually shares. Lengths were counted in bytes, so the
 * home page's 60-character title with one en dash scored as too long.
 */
class ListSeoScoreTest extends TestCase
{
    private function makePage(array $seo, array $attributes = []): Post
    {
        return new Post($attributes + ['title' => 'A page', 'type' => 'page', 'seo_meta' => $seo]);
    }

    public function test_a_complete_page_reaches_100(): void
    {
        update_cms_option('seo_default_image', null);
        $post = $this->makePage([
            'title' => str_repeat('a', 55),
            'description' => str_repeat('b', 155),
            'focus_keyword' => 'laravel cms',
        ], ['featured_image' => 'media/2026/11/cover.webp']);

        $this->assertSame(100, $post->getSeoScore());
    }

    public function test_lengths_are_counted_in_characters_not_bytes(): void
    {
        // 60 characters, 62 bytes: "–" is three bytes in UTF-8.
        $title = 'Laravel CMS – Open Source CMS for Modern Website | FalconCMS';
        $this->assertSame(60, mb_strlen($title));

        update_cms_option('seo_default_image', null);
        $post = $this->makePage(['title' => $title]);

        $this->assertSame(30, $post->getSeoScore());
    }

    public function test_the_focus_keyword_counts_and_old_meta_keywords_still_do(): void
    {
        update_cms_option('seo_default_image', null);

        $this->assertSame(10, $this->makePage(['focus_keyword' => 'laravel cms'], ['title' => ''])->getSeoScore());
        $this->assertSame(10, $this->makePage(['keywords' => 'laravel, cms'], ['title' => ''])->getSeoScore());
    }

    public function test_the_featured_or_site_default_image_counts_as_the_share_image(): void
    {
        update_cms_option('seo_default_image', null);
        $this->assertSame(20, $this->makePage([], ['title' => '', 'featured_image' => 'media/cover.webp'])->getSeoScore());
        $this->assertSame(0, $this->makePage([], ['title' => ''])->getSeoScore());

        update_cms_option('seo_default_image', 'media/share.png');
        $this->assertSame(20, $this->makePage([], ['title' => ''])->getSeoScore());
    }
}
