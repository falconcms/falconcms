<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\Countries;
use FalconSeo\Support\KeywordDemand;
use FalconSeo\Support\PageAnalyzer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Editor → SEO → Analysis: what the check-up finds on a page, and its score. */
class PageAnalysisTest extends TestCase
{
    use MakesShopFixtures;

    private function check(array $result, string $id): ?array
    {
        foreach ($result['groups'] as $group) {
            foreach ($group['checks'] as $check) {
                if ($check['id'] === $id) {
                    return $check;
                }
            }
        }

        return null;
    }

    private function page(string $body, string $head = ''): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            .'<title>Best coffee beans for espresso at home guide</title>'
            .'<meta name="description" content="'.str_repeat('Coffee beans for espresso, roasted fresh. ', 3).'">'
            .'<link rel="canonical" href="https://site.test/coffee">'.$head.'</head><body><header><h1>Site name</h1></header><main>'.$body.'</main></body></html>';
    }

    public function test_it_finds_heading_image_and_link_problems(): void
    {
        $html = $this->page('<h1>Coffee beans</h1><h4>Skipped</h4><p>'.str_repeat('Coffee beans taste good. ', 20).'</p>'
            .'<img src="/storage/media/a.jpg"><img src="/storage/media/b.jpg" alt="Coffee beans" width="10" height="10">'
            .'<a href="/other">Other page</a><a href="https://elsewhere.test/x"></a>');

        $result = PageAnalyzer::analyze($html, ['url' => 'https://site.test/coffee', 'keyword' => 'coffee beans', 'slug' => 'coffee']);

        $this->assertSame('warning', $this->check($result, 'h1')['status'], 'two H1s: the theme\'s and the page\'s');
        $this->assertSame('warning', $this->check($result, 'heading_order')['status']);
        $this->assertSame(['a.jpg'], $this->check($result, 'alt')['items']);
        $this->assertSame('warning', $this->check($result, 'empty_links')['status']);
        $this->assertSame(1, $result['stats']['internal_links']);
        $this->assertSame(1, $result['stats']['external_links']);
        $this->assertSame('good', $this->check($result, 'kw_h1')['status']);
        $this->assertSame('warning', $this->check($result, 'kw_slug')['status']);
        $this->assertSame('warning', $this->check($result, 'og_image')['status']);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertLessThan(100, $result['score']);
    }

    public function test_a_clean_page_scores_high(): void
    {
        $text = str_repeat('Fresh coffee beans make better espresso at home. Grind them just before you brew. ', 30);
        $html = $this->page('<h1>Coffee beans for espresso</h1><p>'.$text.'</p><h2>Choosing coffee beans</h2><p>'.$text.'</p>'
            .'<img src="/storage/media/a.jpg" alt="Coffee beans in a grinder" width="10" height="10"><a href="/brewing">How to brew</a>',
            '<meta property="og:image" content="https://site.test/a.jpg"><script type="application/ld+json">{}</script>');

        $result = PageAnalyzer::analyze($html, ['url' => 'https://site.test/coffee', 'keyword' => 'coffee beans', 'slug' => 'coffee-beans']);

        $this->assertGreaterThanOrEqual(85, $result['score'], json_encode($result['groups']));
        $this->assertSame('good', $result['grade']);
    }

    public function test_broken_links_are_reported(): void
    {
        Http::fake([
            'https://ok.test/*' => Http::response('', 200),
            'https://gone.test/*' => Http::response('', 404),
        ]);

        $result = PageAnalyzer::brokenLinks(['https://ok.test/a', 'https://gone.test/b', 'mailto:x@y.z']);

        $this->assertSame(2, $result['checked']);
        $this->assertSame([['url' => 'https://gone.test/b', 'status' => '404']], $result['broken']);
    }

    public function test_the_editor_endpoint_analyses_an_unpublished_page_from_the_editor(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        $post = Post::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'type' => 'post', 'status' => 'draft', 'content' => '<p>Old</p>', 'lang_code' => 'en']);

        $res = $this->postJson('/admin/seo/page-analysis', [
            'post_id' => $post->id, 'mode' => 'live', 'keyword' => 'green tea', 'title' => 'Green tea',
            'content' => '<h2>Brewing</h2><p>Green tea is calming.</p>', 'check_links' => false,
        ])->assertOk();

        $this->assertSame('editor', $res->json('mode'));
        $this->assertNotEmpty($res->json('notice'));
        $this->assertSame(1, $res->json('stats.h1'), 'the title stands in as the H1');
        $this->assertSame(1, $res->json('stats.h2'));
        $this->assertSame('green tea', $res->json('keyword.keyword'));
    }

    public function test_a_keyword_is_compared_across_the_chosen_countries(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        $this->setCmsOptions([KeywordDemand::BING_KEY => Crypt::encryptString('secret-key')]);
        Http::fake(function ($request) {
            if (!str_contains($request->url(), 'GetKeyword?')) {
                return Http::response(['d' => []]);
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return Http::response(['d' => ['Query' => $q['q'], 'Impressions' => ['us' => 900, 'bd' => 300, 'gb' => 0][$q['country']] ?? 0]]);
        });
        $post = Post::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'type' => 'post', 'status' => 'draft', 'content' => '<p>Green tea.</p>', 'lang_code' => 'en']);

        $res = $this->postJson('/admin/seo/page-analysis', [
            'post_id' => $post->id, 'mode' => 'editor', 'keyword' => 'green tea', 'countries' => ['bd', 'us', 'gb', 'zz'], 'check_links' => false,
        ])->assertOk();

        $this->assertSame('bd', $res->json('keyword.country'), 'the first chosen is the main market');
        $this->assertSame(['bd', 'us', 'gb'], array_column($res->json('keyword.countries'), 'country'), 'an unknown code is dropped');

        // The editor then asks for the figures a batch at a time.
        $rows = $this->postJson('/admin/seo/keyword-countries', ['q' => 'green tea', 'countries' => ['bd', 'us', 'gb']])->assertOk()->json('rows');
        $this->assertSame([['us', 900], ['bd', 300], ['gb', 0]], array_map(fn ($r) => [$r['country'], $r['searches']], $rows));
        $this->assertSame('Bangladesh', $rows[1]['name']);
    }

    public function test_every_country_can_be_chosen_and_figures_come_in_batches(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        $post = Post::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'type' => 'post', 'status' => 'draft', 'content' => '<p>Tea.</p>', 'lang_code' => 'en']);
        $all = array_keys(Countries::ALL);

        $res = $this->postJson('/admin/seo/page-analysis', ['post_id' => $post->id, 'mode' => 'editor', 'keyword' => 'tea', 'countries' => $all, 'check_links' => false])->assertOk();
        $this->assertCount(count($all), $res->json('keyword.countries'));

        $this->postJson('/admin/seo/keyword-countries', ['q' => 'tea', 'countries' => array_slice($all, 0, KeywordDemand::COUNTRY_BATCH + 1)])
            ->assertStatus(422);
    }

    public function test_countries_are_compared_only_with_a_bing_key(): void
    {
        Http::fake();

        $this->assertSame([], KeywordDemand::byCountry('green tea', ['us', 'bd']));
        Http::assertNothingSent();
    }

    public function test_the_editor_shows_the_analysis_tab(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        $post = Post::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'type' => 'post', 'status' => 'draft', 'content' => '<p>x</p>', 'lang_code' => 'en']);

        $html = $this->get('/admin/posts/'.$post->id.'/edit')->assertOk()->getContent();
        foreach (['seo-tab-btn-analysis', 'seo-tab-analysis', 'seo-tab-btn-keywords', 'seo-tab-keywords', 'seo-tab-internal', 'falcon-seo-countries-list'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
        // Every country can be chosen, not a short list.
        $this->assertSame(count(Countries::ALL), substr_count($html, 'onchange="falconSeoCountriesPick(this)"'));
    }
}
