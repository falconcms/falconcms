<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;

/**
 * The address a builder post grid pages with.
 *
 * Every grid used ?cp_ + a hash of all its settings (falconcms.com/blog?cp_87d4825c=2): an ugly
 * address that changed whenever a colour or a spacing changed, so links to page 2 opened page 1,
 * and the canonical dropped it, so page 2 named page 1 as itself. Now the first grid of a page
 * uses ?page=2, a second grid ?cp_ + its element id's hash, and the canonical keeps both.
 */
class GridPaginationParamTest extends TestCase
{
    private function makePost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create(array_merge([
            'user_id' => 1, 'title' => "Grid post {$n}", 'slug' => "grid-post-{$n}", 'type' => 'post',
            'status' => 'published', 'lang_code' => 'en', 'content' => '<p>Text.</p>',
            'published_at' => now()->subDays($n),
        ], $attributes));
    }

    private function grid(string $id, array $settings = []): array
    {
        return ['id' => $id, 'type' => 'card', 'settings' => $settings + [
            'post_type' => 'post', 'posts_count' => 1, 'pagination_type' => 'numbered', 'order_by' => 'published_at',
        ]];
    }

    private function page(array $elements): Post
    {
        $layout = [['id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => $elements]]]];

        return $this->makePost(['title' => 'Blog', 'slug' => 'grid-blog', 'type' => 'page',
            'editor_type' => 'builder', 'content' => json_encode($layout), 'published_at' => now()->subYear()]);
    }

    public function test_a_page_with_one_grid_pages_with_plain_page(): void
    {
        $this->makePost(['title' => 'Newest story']);
        $this->makePost(['title' => 'Older story']);
        $this->page([$this->grid('grid1')]);

        $this->get('/grid-blog')->assertOk()->assertSee('Newest story')->assertSee('grid-blog?page=2', false)
            ->assertDontSee('cp_', false);

        $this->get('/grid-blog?page=2')->assertOk()->assertSee('Older story')->assertDontSee('Newest story')
            ->assertSee('<link rel="canonical" href="'.url('/grid-blog').'?page=2">', false);
    }

    public function test_a_second_grid_gets_its_own_name_from_its_id_not_its_settings(): void
    {
        $this->makePost();
        $this->makePost();
        $own = 'cp_'.substr(md5('grid2'), 0, 8);

        $page = $this->page([$this->grid('grid1'), $this->grid('grid2', ['cardBg' => '#fff'])]);
        $this->get('/grid-blog')->assertOk()->assertSee('grid-blog?page=2', false)->assertSee('grid-blog?'.$own.'=2', false);

        // Change the second grid's look: its address stays.
        $layout = json_decode($page->content, true);
        $layout[0]['columns'][0]['elements'][1] = $this->grid('grid2', ['cardBg' => '#000', 'colSpacing' => 40]);
        $page->update(['content' => json_encode($layout)]);
        $this->get('/grid-blog')->assertSee('grid-blog?'.$own.'=2', false);
    }

    public function test_an_old_settings_hash_link_redirects_to_its_new_address(): void
    {
        $this->makePost(['title' => 'Newest story']);
        $this->makePost(['title' => 'Older story']);
        $grid = $this->grid('grid1');
        $this->page([$grid]);
        $legacy = 'cp_'.substr(md5('card-'.substr(md5(json_encode($grid['settings'])), 0, 12)), 0, 8);

        // One address per page: the old one redirects for good, keeping any other parameter.
        $this->get('/grid-blog?'.$legacy.'=2&utm_source=news')->assertStatus(301)
            ->assertRedirect(url('/grid-blog').'?utm_source=news&page=2');
        $this->followingRedirects()->get('/grid-blog?'.$legacy.'=2')->assertOk()->assertSee('Older story')->assertDontSee('Newest story');
        // The grid's load-more fetch is not redirected.
        $this->get('/grid-blog?'.$legacy.'=2', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Older story');
    }

    public function test_the_canonical_keeps_only_the_list_page(): void
    {
        $this->assertSame('', falcon_canonical_page_query(['utm_source' => 'x', 'page' => '1']));
        $this->assertSame('?page=3', falcon_canonical_page_query(['page' => '3', 'orderby' => 'price']));
        $this->assertSame('?cp_0a1b2c3d=2', falcon_canonical_page_query(['cp_0a1b2c3d' => '2', 'cp_bad' => '4']));
        $this->assertSame('', falcon_canonical_page_query(['page' => ['x']]));
    }
}
