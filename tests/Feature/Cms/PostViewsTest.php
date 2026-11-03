<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\PostViews;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Post views in the core (they began in Falcon News): what counts as a view, the figures per
 * period, ordering loops by them, the Post Views dynamic source in the builder's picker, and a
 * site that counted with the plugin keeping its figures.
 */
class PostViewsTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        falcon_request_memo('post_views')->exchangeArray([]);
    }

    private function story(string $title, array $extra = []): Post
    {
        return Post::create(array_merge([
            'title' => $title, 'slug' => Str::slug($title).'-'.uniqid(), 'type' => 'post',
            'status' => 'published', 'lang_code' => 'en', 'content' => '<p>Body</p>', 'published_at' => now(),
        ], $extra));
    }

    private function hit(int $id, string $ip = '203.0.113.9', string $ua = 'Mozilla/5.0 (Windows NT 10.0) Firefox/130.0')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $ua])
            ->post('/falcon/post-views', ['id' => $id]);
    }

    public function test_a_reader_is_counted_once_per_window(): void
    {
        $story = $this->story('Budget passed');

        $this->hit($story->id)->assertNoContent();
        $this->hit($story->id)->assertNoContent();
        $this->assertSame(1, PostViews::count($story->id));

        $this->hit($story->id, '198.51.100.7');
        falcon_request_memo('post_views')->exchangeArray([]);
        $this->assertSame(2, PostViews::count($story->id));
        $this->assertSame(2, PostViews::count($story->id, 'day'));
    }

    public function test_bots_drafts_other_types_and_a_switched_off_counter_are_not_counted(): void
    {
        $story = $this->story('Story');
        $draft = $this->story('Draft', ['status' => 'draft']);
        $page = $this->story('About us', ['type' => 'page']);

        $this->hit($story->id, '203.0.113.1', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->hit($story->id, '203.0.113.2', '');
        $this->hit($draft->id);
        $this->hit($page->id);
        $this->hit(999999);
        update_cms_option('post_views_enabled', '0');
        $this->hit($story->id, '203.0.113.3');

        $this->assertSame(0, DB::table(PostViews::TOTALS)->count());
    }

    public function test_the_beacon_endpoint_keeps_no_session_and_is_never_cached(): void
    {
        $response = $this->hit($this->story('Story')->id);

        $response->assertNoContent();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertEmpty($response->headers->getCookies(), 'no session or XSRF cookie is set');
    }

    public function test_figures_per_period_and_the_top_list(): void
    {
        $old = $this->story('Old favourite');
        $hot = $this->story('Breaking now');
        $week = $this->story('This week');

        PostViews::increment($old->id, 500, now()->subDays(40));
        PostViews::increment($week->id, 30, now()->subDays(3));
        PostViews::increment($hot->id, 12);
        PostViews::increment($old->id, 2);

        $this->assertSame(502, PostViews::count($old->id, 'all'));
        $this->assertSame(2, PostViews::count($old->id, 'day'));
        $this->assertSame(30, PostViews::count($week->id, 'week'));
        $this->assertSame([$week->id, $hot->id, $old->id], array_column(PostViews::top('week', 10), 'post_id'));
        $this->assertSame(544, PostViews::total('all'));
    }

    public function test_loops_can_be_ordered_by_views_on_any_site(): void
    {
        $this->story('Quiet', ['published_at' => now()->subHours(1)]);
        $popular = $this->story('Popular', ['published_at' => now()->subHours(5)]);
        $trending = $this->story('Trending', ['published_at' => now()->subHours(3)]);

        PostViews::increment($popular->id, 900, now()->subDays(20));
        PostViews::increment($trending->id, 50);

        $this->assertSame(['Popular', 'Trending', 'Quiet'], get_falcon_posts(['orderby' => 'views'])->pluck('title')->all());
        $this->assertSame(['Trending', 'Quiet', 'Popular'], get_falcon_posts(['orderby' => 'views_week'])->pluck('title')->all());
        $this->assertArrayHasKey('views_day', apply_falcon_filters('falcon_post_orderby_options', []));
    }

    public function test_counts_are_written_for_people_and_old_days_are_pruned(): void
    {
        $this->assertSame('12.3K views', PostViews::format(12345, 'short', 'views'));
        $this->assertSame('১২,৩৪৫ বার পড়া হয়েছে', PostViews::format(12345, 'full', '{count} বার পড়া হয়েছে', 'bengali'));

        $story = $this->story('Story');
        PostViews::increment($story->id, 5, now()->subDays(200));
        PostViews::increment($story->id, 1);
        $this->artisan('falcon:prune-post-views')->assertSuccessful();
        $this->assertSame(1, DB::table(PostViews::DAYS)->count());
        $this->assertSame(6, (int) DB::table(PostViews::TOTALS)->value('views'));
    }

    public function test_the_post_views_dynamic_source_needs_no_plugin(): void
    {
        $story = $this->story('Story');
        PostViews::increment($story->id, 1530);

        $this->assertContains('post_views', array_column(falcon_extra_dynamic_sources()['text'], 'key'));
        $value = fn (array $s) => falcon_resolve_dynamic_value('post_views', $story, falcon_dynamic_config($s));
        $this->assertSame('1,530 views', $value([]));
        $this->assertSame('১.৫K', $value(['dynamic_views_format' => 'short', 'dynamic_views_label' => '', 'dynamic_digits' => 'bengali']));
        $this->assertSame('New', $value(['dynamic_views_min' => '5000', 'dynamic_fallback' => 'New']));
        $this->assertSame('1,530 views', falcon_resolve_tokens('{lazy:post_views}', $story));
    }

    /**
     * The extra sources reached only the page builder, so Post Views was missing from the Post
     * Card builder — where a view count is most often wanted — and from the menu and off-canvas
     * builders.
     */
    public function test_every_builder_is_given_the_extra_dynamic_sources(): void
    {
        foreach (['index', 'post-card-builder', 'mega-menu-builder', 'off-canvas-builder'] as $view) {
            $source = (string) file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/'.$view.'.blade.php');
            $this->assertStringContainsString('window.falconExtraDynSources', $source, $view);
        }
    }

    public function test_a_post_page_carries_the_beacon(): void
    {
        $story = $this->story('Story');
        $html = $this->get(get_falcon_permalink($story))->assertOk()->getContent();

        $this->assertStringContainsString('/falcon/post-views', $html);
        $this->assertStringContainsString('var id='.$story->id, $html);
    }

    public function test_a_site_that_counted_with_falcon_news_keeps_its_figures(): void
    {
        Schema::dropIfExists(PostViews::DAYS);
        Schema::dropIfExists(PostViews::TOTALS);
        Schema::create('news_post_views', function ($t) {
            $t->unsignedBigInteger('post_id')->primary();
            $t->unsignedBigInteger('views');
            $t->timestamp('last_viewed_at')->nullable();
        });
        Schema::create('news_post_view_days', function ($t) {
            $t->unsignedBigInteger('post_id');
            $t->date('day');
            $t->unsignedInteger('views');
            $t->primary(['post_id', 'day']);
        });
        DB::table('news_post_views')->insert(['post_id' => 7, 'views' => 321, 'last_viewed_at' => now()]);
        DB::table('news_post_view_days')->insert(['post_id' => 7, 'day' => now()->toDateString(), 'views' => 4]);
        DB::table('cms_settings')->insert(['key' => 'news_views_window_minutes', 'value' => '15', 'created_at' => now(), 'updated_at' => now()]);

        (require __DIR__.'/../../../database/migrations/2026_11_03_000001_create_cms_post_views_tables.php')->up();

        $this->assertSame(321, (int) DB::table(PostViews::TOTALS)->where('post_id', 7)->value('views'));
        $this->assertSame(4, (int) DB::table(PostViews::DAYS)->where('post_id', 7)->value('views'));
        $this->assertSame('15', DB::table('cms_settings')->where('key', 'post_views_window_minutes')->value('value'));
    }
}
