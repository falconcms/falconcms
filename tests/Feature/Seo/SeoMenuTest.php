<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/** SEO is a menu of its own, out of Settings, with tabs like Settings'. */
class SeoMenuTest extends TestCase
{
    use MakesShopFixtures;

    private function actingAsAdmin(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
    }

    public function test_seo_is_a_main_menu_with_its_screens_and_settings_has_none_of_them(): void
    {
        $seo = app(AdminMenu::class)->grouped()->flatten(1)->firstWhere('title', 'SEO');

        $this->assertNotNull($seo, 'SEO is in the sidebar');
        $this->assertSame('admin.settings.seo', $seo->route);
        $this->assertSame(['Settings', 'Site Analysis', 'Rank Tracker', 'SEO Audit', 'Bulk SEO', 'Redirects'], $seo->children->pluck('title')->all());

        (new MenuSeeder)->run();
        $settings = DB::table('menus')->whereNull('parent_id')->where('route', 'admin.settings.index')->first();
        $this->assertSame([], DB::table('menus')->where('parent_id', $settings->id)
            ->whereIn('route', ['admin.settings.seo', 'admin.settings.seo-audit', 'admin.settings.seo-bulk', 'admin.settings.site-analysis', 'admin.redirects.index'])
            ->pluck('title')->all(), 'a fresh install has no SEO entries under Settings');
    }

    public function test_every_seo_screen_has_the_seo_tabs_and_settings_tabs_do_not(): void
    {
        $this->actingAsAdmin();

        foreach (['/admin/seo/settings', '/admin/seo/site-analysis', '/admin/seo/audit', '/admin/seo/bulk', '/admin/seo/redirects'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach (['seo/settings', 'seo/site-analysis', 'seo/audit', 'seo/bulk', 'seo/redirects'] as $tab) {
                $this->assertStringContainsString('/admin/'.$tab.'"', $html, "{$url} has the {$tab} tab");
            }
        }

        // Settings' own tabs (the sidebar links to SEO, the tabs do not).
        $this->get('/admin/settings')->assertOk();
        $settingsTabs = view('falcon-cms::admin.settings.nav')->render();
        $this->assertStringContainsString('General Settings', $settingsTabs);
        $this->assertStringNotContainsString('/admin/seo/', $settingsTabs);
    }

    public function test_old_addresses_under_settings_redirect(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/settings/seo')->assertRedirect('/admin/seo/settings')->assertStatus(301);
        $this->get('/admin/settings/seo-audit')->assertRedirect('/admin/seo/audit');
        $this->get('/admin/settings/seo-bulk')->assertRedirect('/admin/seo/bulk');
        $this->get('/admin/settings/site-analysis')->assertRedirect('/admin/seo/site-analysis');
    }
}
