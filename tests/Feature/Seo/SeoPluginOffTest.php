<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The site with Falcon SEO switched off: its tools and output are gone, the core's basic SEO
 * (title, description, canonical, sitemap, robots.txt, redirects) carries on, and every
 * screen it used to add to still opens.
 *
 * Each test runs in its own process: the plugin's helpers and class aliases, once defined,
 * would outlive this class.
 */
#[RunTestsInSeparateProcesses]
class SeoPluginOffTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        putenv('FALCON_DISABLED_PLUGINS=falcon-seo');
        $_ENV['FALCON_DISABLED_PLUGINS'] = $_SERVER['FALCON_DISABLED_PLUGINS'] = 'falcon-seo';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('FALCON_DISABLED_PLUGINS');
        unset($_ENV['FALCON_DISABLED_PLUGINS'], $_SERVER['FALCON_DISABLED_PLUGINS']);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs($this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]));
    }

    public function test_the_plugin_and_its_routes_are_gone(): void
    {
        $this->assertFalse(falcon_plugin_active('falcon-seo'));
        foreach (['admin.settings.seo-audit', 'admin.settings.seo-bulk', 'admin.seo.page-analysis', 'admin.seo.keyword-demand',
            'admin.analytics.vitals', 'admin.media.avif-generate', 'frontend.web-vitals', 'frontend.indexnow-key'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} is still registered");
        }
        $this->assertFalse(class_exists('FalconCms\\Core\\Support\\SeoAudit'));
    }

    public function test_a_page_keeps_its_basic_seo_without_the_plugins_extras(): void
    {
        $this->setCmsOptions(['seo_hide_site' => '1', 'ai_block_training' => '1']);
        Post::forceCreate(['title' => 'Coffee guide', 'slug' => 'coffee-guide', 'type' => 'page', 'status' => 'published', 'content' => '<p>Brew it well.</p>', 'lang_code' => 'en']);

        $html = $this->get('/coffee-guide')->assertOk()->getContent();
        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringNotContainsString('application/ld+json', $html, 'structured data comes from the plugin');
        $this->assertStringNotContainsString('max-image-preview', $html);
        // The plugin's "hide this site" setting has no say once the plugin is off.
        $this->assertStringNotContainsString('noindex', $html);

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap:', $robots);
        $this->assertStringNotContainsString('GPTBot', $robots);
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_the_screens_it_added_to_still_open_without_it(): void
    {
        $this->actingAsAdmin();
        $post = Post::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'type' => 'post', 'status' => 'draft', 'content' => '<p>x</p>', 'lang_code' => 'en']);

        $seo = $this->get('/admin/settings/seo')->assertOk()->getContent();
        $this->assertStringNotContainsString('seo_hide_site', $seo);
        $this->assertStringNotContainsString('IndexNow', $seo);
        $this->assertStringNotContainsString('SEO Audit', $seo);

        $this->get('/admin/seo/redirects')->assertOk()->assertDontSee('404 errors');
        $this->get('/admin/settings/integrations')->assertOk()->assertDontSee('Cloudflare cache');
        $this->get('/admin/customizer')->assertOk()->assertDontSee('Serve AVIF Images');

        $editor = $this->get('/admin/posts/'.$post->id.'/edit')->assertOk()->getContent();
        $this->assertStringNotContainsString('seo-tab-btn-keywords', $editor);
        $this->assertStringNotContainsString('seo-tab-btn-analysis', $editor);
        $this->assertStringContainsString('seo-tab-btn-advanced', $editor);
    }

    public function test_saving_seo_settings_still_works(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/settings/seo', ['seo_separator' => '|', 'sitemap_include_categories' => '1'])->assertRedirect();
        $this->assertSame('|', DB::table('cms_settings')->where('key', 'seo_separator')->value('value'));
    }
}
