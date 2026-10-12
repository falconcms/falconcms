<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Support\KeywordDemand;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Search demand from free sources: Google Keyword Planner CSVs the owner imports, Bing
 * Webmaster Tools' keyword API with the site's own key, and (optionally) Google's search-box
 * suggestions. Nothing is asked of an outside service that is not set up.
 */
class KeywordDemandTest extends TestCase
{
    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'kw@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    /** What Keyword Planner downloads: UTF-16LE with a BOM, tab separated, two title lines, ranges. */
    private function plannerExport(): string
    {
        $tsv = "Keyword Stats 2026-10-13 at 10_00_00\n"
            ."All locations\n"
            ."Keyword\tCurrency\tAvg. monthly searches\tThree month change\tCompetition\tCompetition (indexed value)\n"
            ."laravel cms\tUSD\t1K – 10K\t0%\tLow\t12\n"
            ."best laravel cms\tUSD\t100 – 1K\t0%\tLow\t8\n"
            ."laravel cms open source\tUSD\t\"1,300\"\t0%\tMedium\t40\n"
            ."wordpress alternative\tUSD\t10K – 100K\t0%\tHigh\t80\n";

        return "\xFF\xFE".mb_convert_encoding($tsv, 'UTF-16LE', 'UTF-8');
    }

    public function test_a_keyword_planner_export_is_imported_with_its_ranges(): void
    {
        $this->assertSame(4, KeywordDemand::import($this->plannerExport()));

        $row = DB::table('cms_keyword_volumes')->where('keyword', 'laravel cms')->first();
        $this->assertSame([1000, 10000, 'Low'], [(int) $row->searches_low, (int) $row->searches_high, $row->competition]);
        $this->assertSame(1300, (int) DB::table('cms_keyword_volumes')->where('keyword', 'laravel cms open source')->value('searches_high'));

        // Importing again updates, it does not duplicate.
        KeywordDemand::import($this->plannerExport());
        $this->assertSame(4, DB::table('cms_keyword_volumes')->count());
    }

    public function test_any_keyword_tools_csv_with_a_keyword_and_a_volume_column_is_read(): void
    {
        $this->assertSame(2, KeywordDemand::import("\xEF\xBB\xBFKeyword,Search Volume\nCoffee Beans,\"2,400\"\nOrganic coffee,880\n"));
        $this->assertSame(2400, (int) DB::table('cms_keyword_volumes')->where('keyword', 'coffee beans')->value('searches_high'));
        $this->assertSame(0, KeywordDemand::import("Name,Price\nShirt,10\n"), 'no Keyword column, nothing read');
    }

    public function test_lookup_gives_the_volume_and_related_imported_keywords_most_searched_first(): void
    {
        KeywordDemand::import($this->plannerExport());

        $result = KeywordDemand::lookup('Laravel  CMS');

        $this->assertSame('laravel cms', $result['query']);
        $this->assertSame(['planner' => true, 'bing' => false, 'autocomplete' => false], $result['sources']);
        $this->assertSame(10000, $result['volume']['searches_high']);
        $this->assertSame(['laravel cms open source', 'best laravel cms'], array_column($result['related'], 'query'));
    }

    public function test_bing_is_asked_only_with_a_key_and_its_answer_is_cached(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'ssl.bing.com/webmaster/api.svc/json/GetKeyword?*' => Http::response(['d' => ['Query' => 'laravel cms', 'Impressions' => 540, 'BroadImpressions' => 900]]),
            'ssl.bing.com/webmaster/api.svc/json/GetRelatedKeywords?*' => Http::response(['d' => [
                ['Query' => 'laravel cms', 'Impressions' => 540], ['Query' => 'Laravel CMS free', 'Impressions' => 70],
            ]]),
        ]);

        $this->assertNull(KeywordDemand::lookup('laravel cms')['bing'], 'no key, no call');
        Http::assertNothingSent();

        $this->setCmsOptions([KeywordDemand::BING_KEY => Crypt::encryptString('secret-key')]);
        $result = KeywordDemand::lookup('laravel cms');

        $this->assertSame(540, $result['bing']['impressions']);
        $this->assertSame([['query' => 'laravel cms free', 'bing' => 70]], $result['related']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'apikey=secret-key') && str_contains($r->url(), 'country=us'));

        KeywordDemand::lookup('laravel cms');
        Http::assertSentCount(2);
    }

    public function test_a_rejected_bing_key_is_reported_not_thrown(): void
    {
        Http::swap(new Factory);
        Http::fake(['ssl.bing.com/*' => Http::response(['ErrorCode' => 3, 'Message' => 'ERROR!!! InvalidApiKey'], 400)]);
        $this->setCmsOptions([KeywordDemand::BING_KEY => Crypt::encryptString('wrong')]);

        $result = KeywordDemand::lookup('anything');

        $this->assertStringContainsString('API key was not accepted', $result['errors'][0]);
    }

    public function test_google_suggestions_only_when_turned_on(): void
    {
        Http::swap(new Factory);
        Http::fake(['suggestqueries.google.com/*' => Http::response(['laravel cms', ['laravel cms', 'laravel cms free', 'laravel cms github']])]);

        $this->assertSame([], KeywordDemand::lookup('laravel cms')['suggestions']);
        Http::assertNothingSent();

        $this->setCmsOptions([KeywordDemand::AUTOCOMPLETE => '1']);
        $this->assertSame(['laravel cms free', 'laravel cms github'], KeywordDemand::lookup('laravel cms')['suggestions']);
    }

    public function test_the_settings_screen_stores_the_bing_key_encrypted_and_keeps_it_when_left_empty(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.seo.update'), ['bing_webmaster_api_key' => 'abc123'])->assertRedirect();
        $stored = DB::table('cms_settings')->where('key', KeywordDemand::BING_KEY)->value('value');
        $this->assertNotSame('abc123', $stored);
        $this->assertSame('abc123', KeywordDemand::bingKey());

        $this->actingAs($admin)->post(route('admin.settings.seo.update'), ['bing_webmaster_api_key' => ''])->assertRedirect();
        $this->assertSame('abc123', KeywordDemand::bingKey(), 'an empty field keeps the saved key');

        $this->actingAs($admin)->post(route('admin.settings.seo.update'), ['bing_webmaster_api_key_remove' => '1'])->assertRedirect();
        $this->assertSame('', KeywordDemand::bingKey());
    }

    public function test_the_import_and_demand_endpoints(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->createWithContent('Keyword Stats.csv', $this->plannerExport());

        $this->actingAs($admin)->post(route('admin.settings.seo.keywords-import'), ['keywords_file' => $file])
            ->assertRedirect()->assertSessionHas('success', '4 keywords imported.');

        $this->actingAs($admin)->postJson(route('admin.seo.keyword-demand'), ['q' => 'wordpress alternative'])
            ->assertOk()->assertJsonPath('volume.searches_high', 100000);

        $this->actingAs($admin)->post(route('admin.settings.seo.keywords-clear'))->assertRedirect();
        $this->assertSame(0, DB::table('cms_keyword_volumes')->count());
    }
}
