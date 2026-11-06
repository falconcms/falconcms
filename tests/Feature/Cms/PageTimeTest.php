<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\PageTime;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/** How long visitors stay: what is recorded, what is refused, and the figures read back. */
class PageTimeTest extends TestCase
{
    use MakesShopFixtures;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0) Firefox/130.0';

    private function report(int $id, string $path, int $seconds, array $extra = [], string $ua = self::BROWSER, string $ip = '203.0.113.9')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $ua])
            ->post('/falcon/page-time', array_merge([
                'i' => $id, 'p' => $path, 's' => PageTime::sign($id, $path), 'd' => $seconds, 'f' => 1, 'n' => 1,
            ], $extra));
    }

    private function story(string $type = 'post'): Post
    {
        return Post::create([
            'title' => "A {$type}", 'slug' => $type.'-'.uniqid(), 'type' => $type, 'status' => 'published',
            'lang_code' => 'en', 'content' => 'Body', 'published_at' => now(),
        ]);
    }

    public function test_time_adds_up_and_a_later_report_is_not_a_new_view(): void
    {
        $post = $this->story();

        $this->report($post->id, '/post/x', 30)->assertNoContent();
        $this->report($post->id, '/post/x', 20, ['f' => 0, 'n' => 0])->assertNoContent();

        $row = DB::table(PageTime::TABLE)->first();
        $this->assertSame(50, (int) $row->seconds);
        $this->assertSame(1, (int) $row->views);
        $this->assertSame(1, (int) $row->visits);
        $this->assertSame($post->id, (int) $row->post_id);
    }

    public function test_a_forged_address_or_id_is_ignored(): void
    {
        $this->report(0, '/made-up', 30, ['s' => 'not-the-signature']);
        $this->report(5, '/x', 30, ['s' => PageTime::sign(6, '/x')]);

        $this->assertSame(0, DB::table(PageTime::TABLE)->count());
    }

    public function test_bots_and_nothing_spent_are_not_recorded(): void
    {
        $this->report(0, '/a', 30, [], 'Googlebot/2.1');
        $this->report(0, '/a', 0);
        $this->report(0, '/a', -5);

        $this->assertSame(0, DB::table(PageTime::TABLE)->count());
    }

    public function test_one_report_cannot_claim_more_than_the_limit(): void
    {
        $this->report(0, '/a', 999999);

        $this->assertSame(PageTime::MAX_SECONDS, (int) DB::table(PageTime::TABLE)->value('seconds'));
    }

    public function test_it_can_be_switched_off(): void
    {
        update_cms_option('page_time_enabled', '0');

        $this->report(0, '/a', 30);

        $this->assertSame(0, DB::table(PageTime::TABLE)->count());
    }

    public function test_the_visitor_log_row_gets_its_time(): void
    {
        $row = Analytics::create(['ip_address' => '203.0.113.9', 'url' => 'http://site.test/post/x?utm=1']);
        $other = Analytics::create(['ip_address' => '203.0.113.9', 'url' => 'http://site.test/other']);

        $this->report(0, '/post/x', 40);
        $this->report(0, '/post/x', 5, ['f' => 0, 'n' => 0]);

        $this->assertSame(45, (int) $row->fresh()->duration_seconds);
        $this->assertNull($other->fresh()->duration_seconds);
    }

    public function test_another_visitors_row_is_left_alone(): void
    {
        $row = Analytics::create(['ip_address' => '198.51.100.1', 'url' => 'http://site.test/post/x']);

        $this->report(0, '/post/x', 40);

        $this->assertNull($row->fresh()->duration_seconds);
    }

    public function test_the_figures_read_back_by_site_and_by_type(): void
    {
        $post = $this->story();
        $page = $this->story('page');
        $product = $this->story('product');

        $this->report($post->id, '/post/x', 100);
        $this->report($post->id, '/post/x', 200, ['n' => 0]);
        $this->report($page->id, '/about', 60);
        $this->report($product->id, '/product/y', 30, ['n' => 0]);
        $this->report(0, '/blog', 10, ['n' => 0]);

        $today = now()->timezone(cms_timezone());
        $summary = PageTime::summary($today, $today);

        $this->assertSame(400, $summary['seconds']);
        $this->assertSame(5, $summary['views']);
        $this->assertSame(2, $summary['visits']);
        $this->assertSame(200, $summary['avg_visit']);
        $this->assertSame(80, $summary['avg_page']);

        $top = PageTime::top($today, $today);
        $this->assertSame($post->title, $top[0]['label']);
        $this->assertSame(150, $top[0]['average']);

        $this->assertSame(['/product/y'], array_column(PageTime::top($today, $today, 'product'), 'url'));
        $this->assertSame(['/blog'], array_column(PageTime::top($today, $today, 'other'), 'url'));
        $this->assertEqualsCanonicalizing(['post', 'page', 'product', 'other'], PageTime::types($today, $today));
    }

    public function test_a_public_page_carries_the_script_and_a_missing_one_does_not(): void
    {
        $post = $this->story();

        $this->get(get_falcon_permalink($post))->assertOk()->assertSee('/falcon/page-time', false);
        $this->get('/nothing-here-at-all')->assertNotFound()->assertDontSee('page-time', false);
    }

    public function test_the_team_is_not_counted(): void
    {
        $post = $this->story();

        $this->actingAs($this->makeUser())->get(get_falcon_permalink($post))->assertDontSee('page-time', false);
    }

    public function test_durations_read_for_people(): void
    {
        $this->assertSame('45s', PageTime::format(45));
        $this->assertSame('2m 15s', PageTime::format(135));
        $this->assertSame('1h 5m', PageTime::format(3900));
    }

    public function test_old_figures_are_pruned(): void
    {
        update_cms_option('analytics_retention_days', '40');
        DB::table(PageTime::TABLE)->insert([
            ['day' => now()->subDays(100)->toDateString(), 'path_hash' => sha1('/a'), 'path' => '/a', 'views' => 1, 'visits' => 1, 'seconds' => 1],
            ['day' => now()->toDateString(), 'path_hash' => sha1('/b'), 'path' => '/b', 'views' => 1, 'visits' => 1, 'seconds' => 1],
        ]);

        $this->artisan('falcon:prune-post-views')->assertSuccessful();

        $this->assertSame(['/b'], DB::table(PageTime::TABLE)->pluck('path')->all());
    }
}
