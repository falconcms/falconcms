<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\IndexNow;
use FalconSeo\Support\SeoAudit;
use Illuminate\Support\Facades\DB;

/**
 * Settings → SEO → "Hide this site from search engines": for a demo, a staging copy or a site
 * still being built. Every response says noindex, the sitemap goes, robots.txt still allows
 * crawling (so the noindex is seen), and the SEO Audit says so loudly.
 */
class SearchVisibilityTest extends TestCase
{
    private function page(): Post
    {
        return Post::forceCreate(['title' => 'About us', 'slug' => 'about-us', 'type' => 'page', 'status' => 'published',
            'content' => '<p>'.str_repeat('word ', 50).'</p>', 'lang_code' => 'en']);
    }

    public function test_a_site_is_visible_by_default(): void
    {
        $this->page();

        $response = $this->get('/about-us')->assertOk();

        $this->assertNull($response->headers->get('X-Robots-Tag'));
        $this->assertStringNotContainsString('noindex', $response->getContent());
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_hiding_the_site_noindexes_every_page_and_withdraws_the_sitemap(): void
    {
        $this->page();
        $this->setCmsOptions(['seo_hide_site' => '1']);

        $response = $this->get('/about-us')->assertOk();
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $response->getContent());
        $this->assertStringNotContainsString('max-image-preview', $response->getContent());

        // Not only pages: a 404 or an endpoint has no meta tag to carry it.
        $this->assertSame('noindex, nofollow', $this->get('/no-such-page')->assertNotFound()->headers->get('X-Robots-Tag'));

        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_robots_txt_keeps_allowing_crawling_so_the_noindex_is_seen(): void
    {
        $this->setCmsOptions(['seo_hide_site' => '1']);

        $response = $this->get('/robots.txt')->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString("Disallow: /\n", $body."\n", 'a blocked page never shows Google its noindex');
        $this->assertStringNotContainsString('Sitemap:', $body, 'the sitemap is withdrawn');
        $this->assertNull($response->headers->get('X-Robots-Tag'));
    }

    public function test_indexnow_stays_quiet_while_hidden(): void
    {
        config(['app.url' => 'https://example.com']);
        $this->setCmsOptions(['indexnow_enabled' => '1', 'seo_hide_site' => '1']);

        $this->assertFalse(IndexNow::enabled());
    }

    public function test_the_audit_flags_a_hidden_site_as_an_error(): void
    {
        $this->setCmsOptions(['seo_hide_site' => '1']);

        $check = collect(SeoAudit::run()['checks'])->firstWhere('key', 'site_hidden');

        $this->assertSame('error', $check['severity']);
    }

    public function test_the_setting_saves_from_the_seo_screen(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $this->actingAs($admin)->get(route('admin.settings.seo'))->assertOk()->assertSee('Hide this site from search engines');

        $this->actingAs($admin)->post(route('admin.settings.seo.update'), ['seo_hide_site' => '1'])->assertRedirect();
        $this->assertSame('1', DB::table('cms_settings')->where('key', 'seo_hide_site')->value('value'));

        $this->actingAs($admin)->post(route('admin.settings.seo.update'), [])->assertRedirect();
        $this->assertSame('0', DB::table('cms_settings')->where('key', 'seo_hide_site')->value('value'));
    }
}
