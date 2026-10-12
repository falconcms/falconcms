<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;

/**
 * A post lives at /post/slug. The catch-all used to answer at /slug as well, with a canonical
 * pointing at itself, so Google saw two copies of every post ("Crawled - currently not
 * indexed"). The bare address now redirects permanently; pages keep living at /slug.
 */
class BareSlugRedirectTest extends TestCase
{
    private function make(string $slug, string $type): Post
    {
        return Post::forceCreate(['title' => ucfirst($slug), 'slug' => $slug, 'type' => $type, 'status' => 'published',
            'content' => '<p>Body</p>', 'lang_code' => 'en']);
    }

    public function test_a_post_at_its_bare_slug_redirects_to_its_permalink(): void
    {
        $this->make('fasting', 'post');

        $this->get('/fasting?ref=x')->assertStatus(301)->assertRedirect(url('/post/fasting').'?ref=x');
        $this->get('/post/fasting')->assertOk();
    }

    public function test_a_page_stays_at_its_slug(): void
    {
        $this->make('about', 'page');

        $this->get('/about')->assertOk();
    }
}
