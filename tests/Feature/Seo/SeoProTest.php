<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\Doubles\FakeClaudeTransport;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Mail\SeoReportMail;
use FalconSeo\Support\AiWriter;
use FalconSeo\Support\DataForSeo;
use FalconSeo\Support\Pro;
use FalconSeo\Support\SeoFixer;
use FalconSeo\Support\SeoReport;
use FalconSeo\Support\SiteScan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/** Falcon SEO's Pro tools: AI writing, Google keyword numbers, one-click fixes, the weekly email. */
class SeoProTest extends TestCase
{
    use MakesShopFixtures;

    private function admin()
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function claude(array $replies): FakeClaudeTransport
    {
        $fake = new FakeClaudeTransport($replies);
        $this->app->instance('falcon-seo.ai.transporter', $fake);
        $this->setCmsOptions([Pro::CLAUDE_KEY => Crypt::encryptString('sk-ant-test')]);

        return $fake;
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::forceCreate($attrs + ['title' => 'Green tea guide', 'slug' => 'green-tea-guide', 'type' => 'post', 'status' => 'published',
            'content' => '<p>Green tea is calming. It is brewed at 80 degrees.</p>', 'lang_code' => 'en', 'user_id' => $this->admin()->id]);
    }

    public function test_nothing_pro_answers_without_a_licence(): void
    {
        config(['falcon-options.freemium_grace_until' => '2000-01-01']);
        $this->actingAs($this->admin());

        $this->assertFalse(Pro::active());
        $this->postJson('/admin/seo/ai-suggest', ['field' => 'title'])->assertForbidden();
        $this->postJson('/admin/seo/site-analysis/fix', ['check' => 'description', 'post_ids' => [1]])->assertForbidden();
        $this->get('/admin/seo/settings')->assertOk()->assertSee('Upgrade to Pro');
    }

    public function test_claude_writes_title_options_with_the_page_and_keyword(): void
    {
        $this->withProLicensed();
        $fake = $this->claude([json_encode(['options' => [
            ['text' => 'Green Tea Guide: How to Brew It Right at Home', 'why' => 'Keyword first, clear benefit.'],
            ['text' => 'Brewing Green Tea: Temperatures and Times', 'why' => 'Specific.'],
        ]])]);
        $this->actingAs($this->admin());
        $post = $this->makePost();

        $res = $this->postJson('/admin/seo/ai-suggest', ['field' => 'title', 'post_id' => $post->id, 'keyword' => 'green tea'])->assertOk();

        $this->assertSame('Green Tea Guide: How to Brew It Right at Home', $res->json('options.0.text'));
        $body = $fake->body();
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertStringContainsString('Focus keyword: green tea', $body['messages'][0]['content']);
        $this->assertStringContainsString('brewed at 80 degrees', $body['messages'][0]['content']);
        $this->assertSame('sk-ant-test', $fake->requests[0]->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $fake->requests[0]->getHeaderLine('anthropic-beta'));
    }

    public function test_a_rejected_key_says_what_to_do(): void
    {
        $this->withProLicensed();
        $this->claude([401]);
        $this->actingAs($this->admin());

        $this->postJson('/admin/seo/ai-suggest', ['field' => 'description', 'title' => 'x'])
            ->assertStatus(422)->assertJson(['message' => 'Claude did not accept the API key. Check it in SEO → Settings → AI writing.']);
    }

    public function test_dataforseo_numbers_by_country(): void
    {
        $this->withProLicensed();
        $this->setCmsOptions([Pro::DATAFORSEO_LOGIN => 'me@site.test', Pro::DATAFORSEO_PASSWORD => Crypt::encryptString('secret')]);
        Http::fake([
            'api.dataforseo.com/v3/keywords_data/*' => function ($request) {
                $location = $request->data()[0]['location_code'];

                return Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [[
                    'keyword' => 'green tea', 'search_volume' => $location === 2840 ? 74000 : 1900, 'cpc' => 1.234, 'competition' => 'MEDIUM',
                    'monthly_searches' => [['year' => 2026, 'month' => 9, 'search_volume' => 80000], ['year' => 2026, 'month' => 8, 'search_volume' => 70000]],
                ]]]]]);
            },
            'api.dataforseo.com/v3/dataforseo_labs/*' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000,
                'result' => [['items' => [['keyword' => 'green tea', 'keyword_difficulty' => 47]]]]]]]),
        ]);

        $rows = DataForSeo::metrics('Green Tea', ['bd', 'us']);

        $this->assertSame(['us', 'bd'], array_column($rows, 'country'));
        $this->assertSame(74000, $rows[0]['searches']);
        $this->assertSame(1.23, $rows[0]['cpc']);
        $this->assertSame('medium', $rows[0]['competition']);
        $this->assertSame(47, $rows[0]['difficulty']);
        $this->assertSame([70000, 80000], $rows[0]['trend'], 'oldest month first');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'search_volume') && $r->data()[0]['location_code'] === 2050 && $r->hasHeader('Authorization'));

        DataForSeo::metrics('green tea', ['us']);
        Http::assertSentCount(4); // two countries x (volume + difficulty); the repeat came from the cache
    }

    public function test_one_click_fixes_change_only_what_the_problem_is_about(): void
    {
        $this->withProLicensed();
        $post = $this->makePost([
            'excerpt' => 'Everything about brewing green tea well, from water temperature to steeping time, in one short guide for beginners and tea lovers alike.',
            'content' => '<p>Intro</p><img src="/storage/media/green-tea_cup-2.jpg"><p>See <a href="https://gone.test/x">this old page</a> and <a href="/kept">this one</a>.</p>',
        ]);

        $result = SeoFixer::fix($post, 'description');
        $this->assertTrue($result['fixed']);
        $this->assertStringStartsWith('Everything about brewing green tea', $post->fresh()->seo_meta['description']);
        $this->assertFalse(SeoFixer::fix($post->fresh(), 'description')['fixed'], 'an existing description is left alone without AI');

        $this->assertTrue(SeoFixer::fix($post->fresh(), 'alt')['fixed']);
        $this->assertStringContainsString('<img alt="Green tea cup" src="/storage/media/green-tea_cup-2.jpg">', $post->fresh()->content);

        DB::table(SiteScan::TABLE)->insert(['post_id' => $post->id, 'score' => 50, 'grade' => 'ok', 'analyzed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'issues' => json_encode([['id' => 'broken_links', 'group' => 'links', 'status' => 'error', 'label' => '1 broken link(s)', 'items' => ['https://gone.test/x — 404']]])]);
        $this->assertTrue(SeoFixer::fix($post->fresh(), 'broken_links')['fixed']);
        $content = $post->fresh()->content;
        $this->assertStringContainsString('See this old page and <a href="/kept">this one</a>', $content);

        $this->assertGreaterThanOrEqual(2, DB::table('cms_revisions')->where('revisionable_id', $post->id)->count(), 'a revision before each content change');
    }

    public function test_ai_alt_text_for_builder_images_and_the_fix_endpoint(): void
    {
        $this->withProLicensed();
        $this->claude([json_encode(['alts' => [['number' => 1, 'alt' => 'A cup of green tea on a wooden table']]])]);
        $this->actingAs($this->admin());
        $post = $this->makePost(['content' => json_encode([['type' => 'section', 'children' => [['type' => 'image', 'settings' => ['url' => '/storage/media/IMG_2041.jpg', 'alt' => '']]]]])]);

        $res = $this->postJson('/admin/seo/site-analysis/fix', ['check' => 'alt', 'method' => 'ai', 'post_ids' => [$post->id]])->assertOk();

        $this->assertTrue($res->json('results.0.fixed'));
        $this->assertSame('A cup of green tea on a wooden table', json_decode($post->fresh()->content, true)[0]['children'][0]['settings']['alt']);
    }

    public function test_the_weekly_email_lists_drops_and_broken_links(): void
    {
        $this->withProLicensed();
        Mail::fake();
        $this->setCmsOptions([Pro::REPORT_ENABLED => '1', Pro::REPORT_TO => 'owner@site.test, not-an-email']);
        $post = $this->makePost();
        DB::table(SiteScan::TABLE)->insert(['post_id' => $post->id, 'score' => 52, 'previous_score' => 80, 'grade' => 'ok', 'analyzed_at' => now(),
            'created_at' => now(), 'updated_at' => now(), 'url' => 'https://site.test/post/green-tea-guide',
            'issues' => json_encode([['id' => 'broken_links', 'group' => 'links', 'status' => 'error', 'label' => '1 broken link(s)', 'items' => ['https://gone.test/x — 404']]])]);

        $this->artisan('falcon:seo-report')->assertSuccessful();

        Mail::assertSent(SeoReportMail::class, function (SeoReportMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('owner@site.test') && !$mail->hasTo('not-an-email')
                && str_contains($html, '80 → 52') && str_contains($html, 'https://gone.test/x')
                && str_contains($mail->envelope()->subject, '1 broken link(s)');
        });
        $this->assertSame(['owner@site.test'], SeoReport::recipients());
    }

    public function test_settings_keep_the_keys_encrypted(): void
    {
        $this->withProLicensed();
        $this->actingAs($this->admin());

        $this->post('/admin/seo/settings', [Pro::CLAUDE_KEY => 'sk-ant-secret', Pro::DATAFORSEO_LOGIN => ' me@site.test ', Pro::DATAFORSEO_PASSWORD => 'pw',
            Pro::CLAUDE_MODEL => 'claude-sonnet-5-5', Pro::REPORT_ENABLED => '1'])->assertRedirect();

        $stored = DB::table('cms_settings')->where('key', Pro::CLAUDE_KEY)->value('value');
        $this->assertNotSame('sk-ant-secret', $stored);
        forget_cms_options_cache();
        $this->assertSame('sk-ant-secret', Pro::claudeKey());
        $this->assertSame(['me@site.test', 'pw'], Pro::dataForSeo());
        $this->assertSame('claude-sonnet-5-5', Pro::claudeModel());

        // An empty field keeps the saved key; "Remove" clears it.
        $this->post('/admin/seo/settings', [Pro::CLAUDE_KEY => ''])->assertRedirect();
        forget_cms_options_cache();
        $this->assertSame('sk-ant-secret', Pro::claudeKey());
        $this->post('/admin/seo/settings', [Pro::CLAUDE_KEY.'_remove' => '1'])->assertRedirect();
        forget_cms_options_cache();
        $this->assertSame('', Pro::claudeKey());
        $this->assertFalse(AiWriter::available());
    }
}
