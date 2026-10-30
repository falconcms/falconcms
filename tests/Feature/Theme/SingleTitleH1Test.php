<?php

namespace FalconCms\Core\Tests\Feature\Theme;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;

/**
 * A single post, custom post type entry or product has exactly one H1: the Page Title Bar's
 * when it shows the title, otherwise the template's own. With the bar switched off a builder
 * post had none, and a product with the bar on had two.
 */
class SingleTitleH1Test extends TestCase
{
    use MakesShopFixtures;

    public function test_a_classic_post_has_one_h1_with_the_bar_on_or_off(): void
    {
        $post = $this->entry('classic-post', '<p>Plain text.</p>', 'classic');

        $this->assertSame(1, $this->h1Count($post, true));
        $this->assertSame(1, $this->h1Count($post, false));
    }

    public function test_a_builder_post_has_one_h1_with_the_bar_on_or_off(): void
    {
        $layout = json_encode([[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [
                ['id' => 'e1', 'type' => 'text_block', 'settings' => ['content' => '<p>Built in the builder.</p>']],
            ]]],
        ]]);
        $post = $this->entry('builder-post', $layout, 'builder');

        $this->assertSame(1, $this->h1Count($post, true));
        $this->assertSame(1, $this->h1Count($post, false), 'with the bar off a builder post had no H1');
    }

    public function test_a_product_has_one_h1_with_the_bar_on_or_off(): void
    {
        $product = $this->makeProduct([], ['title' => 'Blue Mug']);

        $this->assertSame(1, $this->h1Count($product, true), 'with the bar on a product had two H1s');
        $this->assertSame(1, $this->h1Count($product, false));
    }

    public function test_a_hidden_bar_title_counts_as_off(): void
    {
        $post = $this->entry('hidden-title', $this->builderText(), 'builder');
        $this->setCmsOptions(['theme_title_bar_enabled' => '1', 'theme_title_bar_show_title' => '0']);
        app()->forgetInstance('falcon.memo.title_bar');

        $html = $this->get(get_falcon_permalink($post))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/i', $html));
    }

    private function h1Count(Post $post, bool $barOn): int
    {
        $this->setCmsOptions(['theme_title_bar_enabled' => $barOn ? '1' : '0', 'theme_title_bar_show_title' => '1']);
        app()->forgetInstance('falcon.memo.title_bar');

        $html = $this->get(get_falcon_permalink($post))->assertOk()->getContent();

        return preg_match_all('/<h1[\s>]/i', $html);
    }

    private function entry(string $slug, string $content, string $editor): Post
    {
        return Post::create([
            'title' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'type' => 'post',
            'status' => 'published', 'lang_code' => 'en', 'content' => $content, 'editor_type' => $editor,
        ]);
    }

    private function builderText(): string
    {
        return (string) json_encode([[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [
                ['id' => 'e1', 'type' => 'text_block', 'settings' => ['content' => '<p>Text.</p>']],
            ]]],
        ]]);
    }
}
