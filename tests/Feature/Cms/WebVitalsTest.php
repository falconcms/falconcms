<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\WebVitals;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Real visitors' Core Web Vitals: a small script on every page (when turned on) sends LCP, INP,
 * CLS and TTFB with sendBeacon; Analytics → Web Vitals shows the 75th percentile, as Google does.
 */
class WebVitalsTest extends TestCase
{
    private function beacon(array $data, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0')
    {
        return $this->call('POST', '/falcon-vitals', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_USER_AGENT' => $agent], json_encode($data));
    }

    public function test_the_script_is_on_pages_only_when_turned_on(): void
    {
        Post::forceCreate(['title' => 'About', 'slug' => 'about', 'type' => 'page', 'status' => 'published', 'content' => '<p>x</p>', 'lang_code' => 'en']);

        $this->assertStringNotContainsString('falcon-vitals', $this->get('/about')->getContent());

        $this->setCmsOptions([WebVitals::OPTION => '1']);
        $html = $this->get('/about')->assertOk()->getContent();
        $this->assertStringContainsString('largest-contentful-paint', $html);
        $this->assertStringContainsString(json_encode(route('frontend.web-vitals'), JSON_UNESCAPED_SLASHES), $html);
        $this->assertLessThan(strripos($html, '</body>'), strpos($html, 'largest-contentful-paint'));
    }

    public function test_a_beacon_without_a_csrf_token_is_stored_and_nonsense_is_not(): void
    {
        $this->setCmsOptions([WebVitals::OPTION => '1']);

        $this->beacon(['p' => '/about', 'd' => 'm', 'lcp' => 1800.4, 'inp' => 120, 'cls' => 0.04, 'ttfb' => 300])->assertNoContent();
        $this->beacon(['p' => '/about', 'd' => 'd', 'lcp' => 999999])->assertNoContent();          // implausible LCP
        $this->beacon(['p' => '/about', 'lcp' => 1000], 'Googlebot/2.1')->assertNoContent();       // a bot
        $this->beacon(['p' => '/admin/posts', 'lcp' => 1000])->assertNoContent();                  // the back office
        $this->call('POST', '/falcon-vitals', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'not json')->assertNoContent();

        $rows = DB::table('cms_web_vitals')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(['/about', 'm', 1800, 120, 300], [$rows[0]->path, $rows[0]->device, (int) $rows[0]->lcp, (int) $rows[0]->inp, (int) $rows[0]->ttfb]);
        $this->assertEquals(0.04, (float) $rows[0]->cls);
    }

    public function test_nothing_is_stored_while_measuring_is_off(): void
    {
        $this->beacon(['p' => '/about', 'lcp' => 1000])->assertNoContent();

        $this->assertSame(0, DB::table('cms_web_vitals')->count());
    }

    public function test_the_report_uses_the_75th_percentile_and_googles_thresholds(): void
    {
        $rows = [];
        foreach ([1000, 1500, 2000, 4200, 2200, 5000, 900, 1100] as $i => $lcp) {
            $rows[] = ['date' => now()->toDateString(), 'path' => $i < 4 ? '/fast' : '/slow', 'path_hash' => sha1($i < 4 ? '/fast' : '/slow'),
                'device' => 'm', 'lcp' => $i < 4 ? [1000, 1200, 1300, 1400][$i] : [3000, 4500, 5000, 6000][$i - 4], 'inp' => 150, 'cls' => 0.05, 'ttfb' => 400];
        }
        DB::table('cms_web_vitals')->insert($rows);

        $report = WebVitals::report(28);

        $this->assertSame(8, $report['samples']);
        $this->assertSame(4500, $report['metrics']['lcp']['p75'], 'the 6th of 8 values, sorted');
        $this->assertSame('poor', $report['metrics']['lcp']['rating']);
        $this->assertSame([4, 1, 3], [$report['metrics']['lcp']['good'], $report['metrics']['lcp']['needs'], $report['metrics']['lcp']['poor']]);
        $this->assertSame('good', $report['metrics']['inp']['rating']);
        $this->assertSame(['/slow', '/fast'], array_column($report['pages'], 'path'), 'worst first');
        $this->assertSame('poor', $report['pages'][0]['lcp_rating']);
    }

    public function test_the_screen_renders(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'wv@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
        DB::table('cms_web_vitals')->insert(['date' => now()->toDateString(), 'path' => '/x', 'path_hash' => sha1('/x'), 'device' => 'd', 'lcp' => 2100, 'inp' => 90, 'cls' => 0.01, 'ttfb' => 200]);

        $this->actingAs($admin)->get(route('admin.analytics.vitals', ['range' => 7, 'device' => 'd']))->assertOk()
            ->assertSee('Web Vitals')->assertSee('2.1 s')->assertSee('Measuring is off');
    }
}
