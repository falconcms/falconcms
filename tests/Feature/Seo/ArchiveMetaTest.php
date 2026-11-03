<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * What a category or tag archive tells search engines.
 *
 * Archives used to print the site's own description, which on falconcms.com was empty: nine
 * archives with no description at all, though each category had one written. A tag and a
 * category of the same name shared one title. And /index.php named itself as a second home.
 */
class ArchiveMetaTest extends TestCase
{
    private function archive(string $taxonomy, int $termId, string $title): string
    {
        update_cms_option('site_title', 'FalconCMS');
        update_cms_option('seo_separator', '|');
        falcon_layout_context(['kind' => 'archive', 'archive_type' => 'taxonomy', 'post_type' => 'post', 'taxonomy' => $taxonomy, 'term_id' => $termId]);

        return view('falcon-cms::components.frontend.seo-meta', ['title' => $title])->render();
    }

    public function test_a_category_archive_uses_the_categorys_description(): void
    {
        $category = Category::create(['name' => 'Guides', 'slug' => 'guides', 'lang_code' => 'en', 'description' => 'In-depth guides to building websites with Laravel.']);

        $html = $this->archive('category', $category->id, 'Guides');

        $this->assertStringContainsString('<title>Guides | FalconCMS</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="In-depth guides to building websites with Laravel.">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="In-depth guides to building websites with Laravel.">', $html);
    }

    public function test_a_tag_says_it_is_one_and_never_goes_without_a_description(): void
    {
        $tag = Tag::create(['name' => 'Laravel CMS', 'slug' => 'laravel-cms', 'lang_code' => 'en']);

        $html = $this->archive('post_tag', $tag->id, 'Laravel CMS');

        $this->assertStringContainsString('<title>Posts tagged Laravel CMS | FalconCMS</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Posts tagged Laravel CMS: articles and guides from FalconCMS.">', $html);
    }

    public function test_a_builder_page_without_a_description_is_described_in_its_own_words(): void
    {
        update_cms_option('site_title', 'FalconCMS');
        $post = (object) [
            'type' => 'page', 'title' => 'Home', 'excerpt' => '', 'featured_image' => null, 'seo_meta' => [],
            'content' => json_encode([['id' => 'c1', 'settings' => ['paddingTop' => 60], 'columns' => [['id' => 'k', 'settings' => [], 'elements' => [
                ['id' => 'e', 'type' => 'text_block', 'settings' => ['content' => '<p>Build Laravel websites visually.</p>']],
            ]]]]]),
        ];

        $html = view('falcon-cms::components.frontend.seo-meta', ['post' => $post])->render();

        $this->assertStringContainsString('<meta name="description" content="Build Laravel websites visually.">', $html);
        $this->assertStringNotContainsString('paddingTop', $html);
    }

    public function test_index_php_is_not_a_second_home_page(): void
    {
        $request = Request::create('https://example.test/index.php');
        $this->app->instance('request', $request);
        URL::setRequest($request);

        $html = view('falcon-cms::components.frontend.seo-meta', ['title' => 'Home'])->render();

        $this->assertStringContainsString('<link rel="canonical" href="https://example.test">', $html);
    }
}
