<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;

/**
 * Google Discover shows a page with a large image only when the page allows it
 * (max-image-preview:large) and its image is at least 1200px wide.
 */
class DiscoverPreviewTest extends TestCase
{
    private function headFor(array $seo = []): string
    {
        $post = (object) ['type' => 'page', 'title' => 'T', 'excerpt' => null, 'content' => '', 'featured_image' => null, 'seo_meta' => $seo];

        return view('falcon-cms::components.frontend.seo-meta', ['post' => $post])->render();
    }

    public function test_indexable_pages_allow_large_image_previews_by_default(): void
    {
        $this->assertStringContainsString('<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">', $this->headFor());
        $this->assertStringContainsString('content="index,nofollow,max-image-preview:large', $this->headFor(['nofollow' => '1']));
    }

    public function test_noindex_pages_say_only_noindex(): void
    {
        $html = $this->headFor(['noindex' => '1']);

        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);
        $this->assertStringNotContainsString('max-image-preview', $html);
    }

    public function test_the_owner_can_turn_it_off(): void
    {
        $this->setCmsOptions(['seo_large_image_preview' => '0']);

        $this->assertStringNotContainsString('name="robots"', $this->headFor());
        $this->assertStringContainsString('<meta name="robots" content="index,nofollow">', $this->headFor(['nofollow' => '1']));
    }

    public function test_the_editor_warns_about_a_featured_image_under_1200px(): void
    {
        DB::table('media')->insert(['filename' => 's.jpg', 'path' => 'media/s.jpg', 'mime_type' => 'image/jpeg', 'original_size' => 1,
            'width' => 800, 'height' => 450, 'created_at' => now(), 'updated_at' => now()]);
        $post = new Post(['title' => 'T', 'slug' => 't', 'type' => 'post']);
        $post->featured_image = 'media/s.jpg';

        $html = view('falcon-cms::admin.posts.partials.seo', ['post' => $post, 'errors' => new ViewErrorBag])->render();

        $this->assertStringContainsString('The featured image is 800px wide', $html);
    }
}
