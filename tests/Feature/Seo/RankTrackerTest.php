<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\DataForSeo;
use FalconSeo\Support\Pro;
use FalconSeo\Support\RankTracker;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** SEO → Rank Tracker (Pro, with DataForSEO): tracked keywords in Google, and a competitor's gap. */
class RankTrackerTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://mysite.test']);
        Http::swap(new Factory);
    }

    private function admin()
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function setUpDataForSeo(): void
    {
        $this->withProLicensed();
        $this->setCmsOptions([Pro::DATAFORSEO_LOGIN => 'me@site.test', Pro::DATAFORSEO_PASSWORD => Crypt::encryptString('secret')]);
    }

    private static function ok(array $result): array
    {
        return ['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [$result]]]];
    }

    /** Google's results with the site at $position (null: not in them). */
    private function fakeGoogle(?int $position): void
    {
        $items = [
            ['type' => 'featured_snippet', 'rank_group' => 1, 'domain' => 'other.test', 'url' => 'https://other.test/x'],
            ['type' => 'people_also_ask', 'rank_group' => 1],
            ['type' => 'organic', 'rank_group' => 1, 'domain' => 'other.test', 'url' => 'https://other.test/x'],
        ];
        if ($position !== null) {
            $items[] = ['type' => 'organic', 'rank_group' => $position, 'domain' => 'www.mysite.test', 'url' => 'https://www.mysite.test/laravel-cms'];
            $items[] = ['type' => 'organic', 'rank_group' => $position + 3, 'domain' => 'mysite.test', 'url' => 'https://mysite.test/other'];
        }
        // A fresh fake each time: an earlier stub for the same address would answer first.
        Http::swap(new Factory);
        Http::fake([
            'api.dataforseo.com/v3/serp/*' => Http::response(self::ok(['items' => $items])),
            'api.dataforseo.com/v3/keywords_data/*' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['search_volume' => 1900]]]]]),
            'api.dataforseo.com/v3/dataforseo_labs/google/bulk_keyword_difficulty/*' => Http::response(self::ok(['items' => [['keyword_difficulty' => 42]]])),
        ]);
    }

    public function test_without_pro_and_dataforseo_the_screen_explains_what_it_needs(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/seo/rank-tracker')->assertOk()->assertSee('part of', false)->assertSee('FalconCMS Pro');
        $this->post('/admin/seo/rank-tracker', ['keywords' => 'laravel cms', 'country' => 'us'])->assertForbidden();

        $this->withProLicensed();
        $this->get('/admin/seo/rank-tracker')->assertOk()->assertSee('Add your DataForSEO login');
        Http::assertNothingSent();
    }

    public function test_keywords_are_added_once_checked_in_google_and_tracked_over_time(): void
    {
        $this->setUpDataForSeo();
        $this->actingAs($this->admin());
        $this->fakeGoogle(9);

        $this->post('/admin/seo/rank-tracker', ['keywords' => "Laravel  CMS\nlaravel cms\nfalcon builder", 'country' => 'bd', 'device' => 'mobile'])
            ->assertRedirect()->assertSessionHas('success', '2 keyword(s) added.');

        $row = DB::table(RankTracker::TABLE)->where('keyword', 'laravel cms')->first();
        $this->assertSame(['bd', 'mobile', 9, 1900, 42], [$row->country, $row->device, (int) $row->position, (int) $row->search_volume, (int) $row->difficulty]);
        $this->assertSame('https://www.mysite.test/laravel-cms', $row->url, 'the first of its results, www or not');
        $this->assertSame(['featured_snippet', 'people_also_ask'], json_decode($row->serp_features, true));
        $this->assertSame('other.test', $row->leader);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'serp/google/organic/live/advanced')
            && $r->data()[0] === ['keyword' => 'laravel cms', 'location_code' => 2050, 'language_code' => 'en', 'device' => 'mobile', 'depth' => 100]);

        // Not due again for a week; then it moves up.
        $this->assertSame([], RankTracker::due(10));
        $this->travel(8)->days();
        $this->fakeGoogle(4);
        $this->assertSame(2, RankTracker::runDue(10));

        $rows = collect(RankTracker::rows())->keyBy('keyword');
        $this->assertSame([4, 5, 4, [9, 4]], [$rows['laravel cms']['position'], $rows['laravel cms']['change'], $rows['laravel cms']['best'], $rows['laravel cms']['history']]);
        $this->assertSame(['total' => 2, 'top3' => 0, 'top10' => 2, 'top100' => 2, 'missing' => 0, 'up' => 2, 'down' => 0, 'snippets' => 0], RankTracker::summary(array_values($rows->all())));

        $this->get('/admin/seo/rank-tracker')->assertOk()->assertSee('laravel cms')->assertSee('Bangladesh')->assertSee('People also ask')->assertSee('▲ 5', false);
    }

    public function test_falling_out_of_the_top_100_and_a_daily_frequency(): void
    {
        $this->setUpDataForSeo();
        $this->setCmsOptions([RankTracker::FREQUENCY => 'daily']);
        $this->fakeGoogle(12);
        $id = RankTracker::add('laravel cms', 'us', 'desktop')['added'][0];
        RankTracker::check($id);

        $this->travel(1)->days();
        $this->assertSame([$id], RankTracker::due(10), 'daily: due the next day');
        $this->fakeGoogle(null);
        RankTracker::check($id);

        $row = RankTracker::rows()[0];
        $this->assertNull($row['position']);
        $this->assertTrue($row['lost']);
        $this->assertSame(12, $row['best']);
    }

    public function test_a_failed_check_is_kept_and_not_retried_every_hour(): void
    {
        $this->setUpDataForSeo();
        Http::fake(['api.dataforseo.com/*' => Http::response(['status_code' => 40200, 'status_message' => 'Payment Required.', 'tasks' => []])]);
        $id = RankTracker::add('laravel cms', 'us', 'desktop')['added'][0];

        RankTracker::check($id);

        $this->assertSame('DataForSEO: Payment Required.', DB::table(RankTracker::TABLE)->where('id', $id)->value('error'));
        $this->assertSame([], RankTracker::due(10));
    }

    public function test_the_competitor_gap_is_asked_once_and_its_keywords_can_be_tracked(): void
    {
        $this->setUpDataForSeo();
        $this->actingAs($this->admin());
        Http::fake([
            'api.dataforseo.com/v3/dataforseo_labs/google/domain_intersection/*' => Http::response(self::ok(['items' => [
                ['keyword_data' => ['keyword' => 'headless cms', 'keyword_info' => ['search_volume' => 2400], 'keyword_properties' => ['keyword_difficulty' => 35]],
                    'first_domain_serp_element' => ['rank_group' => 3, 'url' => 'https://rival.test/headless']],
            ]])),
        ]);

        $this->post('/admin/seo/rank-tracker/gap', ['competitor' => 'https://www.Rival.test/blog', 'country' => 'gb'])
            ->assertRedirect('/admin/seo/rank-tracker?tab=gap&competitor=rival.test&country=gb');
        Http::assertSent(fn ($r) => $r->data()[0]['target1'] === 'rival.test' && $r->data()[0]['target2'] === 'mysite.test'
            && $r->data()[0]['intersections'] === false && $r->data()[0]['location_code'] === 2826);

        $this->get('/admin/seo/rank-tracker?tab=gap&competitor=rival.test&country=gb')->assertOk()
            ->assertSee('headless cms')->assertSee('2,400')->assertSee('/headless');
        $this->post('/admin/seo/rank-tracker/gap', ['competitor' => 'rival.test', 'country' => 'gb']);
        Http::assertSentCount(1);

        $this->post('/admin/seo/rank-tracker/gap', ['competitor' => 'mysite.test', 'country' => 'gb'])->assertSessionHas('error');
    }

    public function test_keywords_can_be_removed_with_their_history(): void
    {
        $this->setUpDataForSeo();
        $this->actingAs($this->admin());
        $this->fakeGoogle(5);
        $id = RankTracker::add('laravel cms', 'us', 'desktop')['added'][0];
        RankTracker::check($id);

        $this->delete('/admin/seo/rank-tracker/'.$id)->assertRedirect();

        $this->assertSame(0, DB::table(RankTracker::TABLE)->count());
        $this->assertSame(0, DB::table(RankTracker::HISTORY)->count());
    }

    public function test_a_site_without_a_public_domain_is_told_why_nothing_can_match(): void
    {
        $this->setUpDataForSeo();
        config(['app.url' => 'http://localhost']);
        $this->actingAs($this->admin());
        Http::fake();

        $this->get('/admin/seo/rank-tracker')->assertOk()->assertSee('is not a public domain');
        $this->post('/admin/seo/rank-tracker/gap', ['competitor' => 'rival.test', 'country' => 'us'])->assertSessionHas('error', DataForSeo::NO_DOMAIN);
        Http::assertNothingSent();
    }

    public function test_domains_are_read_the_way_people_type_them(): void
    {
        $this->assertSame('example.com', DataForSeo::domain(' https://www.Example.com/blog?x=1 '));
        $this->assertSame('shop.example.co.uk', DataForSeo::domain('shop.example.co.uk'));
        $this->assertSame('', DataForSeo::domain('not a domain'));
        $this->assertSame('mysite.test', DataForSeo::ownDomain());
    }
}
