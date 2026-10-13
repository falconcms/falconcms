<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\SiteScan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Settings → Site Analysis: every page checked in the background, its score kept and shown. */
class SiteAnalysisTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Only the pages each test makes (the install seeds a few of its own).
        DB::table('posts')->delete();
    }

    private function admin()
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function page(string $slug, array $attrs = []): Post
    {
        return Post::forceCreate($attrs + ['title' => ucfirst($slug), 'slug' => $slug, 'type' => 'page', 'status' => 'published',
            'content' => '<p>Text</p>', 'lang_code' => 'en', 'user_id' => $this->admin()->id]);
    }

    /** Every page answers with the same small page: one H1, an image without alt, a broken link. */
    private function fakeSite(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'gone.test')) {
                return Http::response('', 404);
            }

            return Http::response('<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width"><title>A page about green tea and how to brew it</title></head>'
                .'<body><main><h1>Green tea</h1><p>'.str_repeat('Green tea is calming. ', 30).'</p><img src="/storage/a.jpg"><a href="https://gone.test/x">Old</a></main></body></html>',
                200, ['Content-Type' => 'text/html']);
        });
    }

    public function test_due_pages_are_checked_and_their_scores_kept(): void
    {
        $this->fakeSite();
        $pricing = $this->page('pricing');
        $this->page('about');
        $this->page('hidden', ['seo_meta' => ['noindex' => '1']]);
        Post::forceCreate(['title' => 'Header', 'slug' => 'header', 'type' => 'falcon_header', 'status' => 'published', 'content' => '', 'lang_code' => 'en', 'user_id' => $this->admin()->id]);

        $this->assertSame(2, SiteScan::run(10), 'layouts and noindex pages are not checked');

        $row = DB::table(SiteScan::TABLE)->where('post_id', $pricing->id)->first();
        $this->assertNotNull($row->analyzed_at);
        $this->assertSame('live', $row->mode);
        $this->assertGreaterThan(0, $row->score);
        $ids = array_column(json_decode($row->issues, true), 'id');
        $this->assertContains('broken_links', $ids);
        $this->assertContains('alt', $ids);

        $this->assertSame(0, SiteScan::run(10), 'nothing is due right after');

        // Changing a page makes it due again; its last score stays on show meanwhile.
        $pricing->update(['title' => 'Pricing plans']);
        $this->assertNull(DB::table(SiteScan::TABLE)->where('post_id', $pricing->id)->value('analyzed_at'));
        $this->assertSame(1, SiteScan::run(10));
    }

    public function test_the_summary_groups_each_problem_with_its_pages(): void
    {
        $this->fakeSite();
        $this->page('pricing');
        $this->page('about');
        SiteScan::run(10);

        $summary = SiteScan::summary();

        $this->assertSame(2, $summary['pages']);
        $this->assertSame(2, $summary['checked']);
        $this->assertSame(0, $summary['due']);
        $broken = collect($summary['problems'])->firstWhere('id', 'broken_links');
        $this->assertSame('Broken links', $broken['label']);
        $this->assertCount(2, $broken['pages']);
        $this->assertStringContainsString('https://gone.test/x', $broken['pages'][0]['items'][0]);
        $this->assertSame('error', $summary['problems'][0]['status'], 'errors first');
    }

    public function test_the_screen_scan_now_and_the_posts_list_badge(): void
    {
        $this->fakeSite();
        $pricing = $this->page('pricing');
        $this->actingAs($this->admin());

        $this->get('/admin/seo/site-analysis')->assertOk()->assertSee('Check 1 page(s) now')->assertSee('No page has been checked yet');

        $this->postJson('/admin/seo/site-analysis/scan', ['links' => 1])->assertOk()->assertJson(['done' => 1, 'due' => 0, 'checked' => 1]);

        $this->get('/admin/seo/site-analysis')->assertOk()->assertSee('Broken links')->assertSee('Pricing')->assertSee('Up to date');

        // "Check all again" clears every date first.
        $this->postJson('/admin/seo/site-analysis/scan', ['restart' => 1])->assertOk()->assertJson(['done' => 1]);

        $score = (int) DB::table(SiteScan::TABLE)->where('post_id', $pricing->id)->value('score');
        $this->get('/admin/pages')->assertOk()->assertSee('SEO score '.$score.'/100', false);
    }
}
