<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Saving SEO changes empties the page cache. Cached pages kept their old head: on
 * falconcms.com a new default share image stayed off every cached page until the cache
 * was cleared by hand.
 */
class SeoPageCacheTest extends TestCase
{
    use MakesShopFixtures;

    private const CACHED_PAGE = 'falcon-test:a-cached-page';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        Cache::put(self::CACHED_PAGE, '<html>old head</html>', 3600);
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::forceCreate($attrs + ['title' => 'Green tea guide', 'slug' => 'green-tea-guide', 'type' => 'page', 'status' => 'published',
            'content' => '<p>Green tea is calming. It is brewed at 80 degrees.</p>', 'lang_code' => 'en', 'user_id' => auth()->id()]);
    }

    public function test_saving_the_seo_settings_empties_the_page_cache(): void
    {
        $this->post('/admin/seo/settings', ['seo_default_image' => 'media/share.png'])->assertRedirect();

        $this->assertFalse(Cache::has(self::CACHED_PAGE));
    }

    public function test_bulk_seo_empties_the_page_cache_only_when_something_changed(): void
    {
        $post = $this->makePost();

        $this->post('/admin/seo/bulk', ['rows' => [$post->id => ['seo_title' => '']]])->assertRedirect();
        $this->assertTrue(Cache::has(self::CACHED_PAGE), 'nothing was saved, so nothing to clear');

        $this->post('/admin/seo/bulk', ['rows' => [$post->id => ['seo_title' => 'Brewing green tea']]])->assertRedirect();
        $this->assertFalse(Cache::has(self::CACHED_PAGE));
    }

    public function test_a_one_click_fix_empties_the_page_cache(): void
    {
        $this->withProLicensed();
        $post = $this->makePost(['excerpt' => 'Everything about brewing green tea well, from water temperature to steeping time.']);

        $this->postJson('/admin/seo/site-analysis/fix', ['check' => 'description', 'post_ids' => [$post->id]])
            ->assertOk()->assertJsonPath('results.0.fixed', true);

        $this->assertFalse(Cache::has(self::CACHED_PAGE));
    }
}
