<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Tests\TestCase;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The analytics submenu migration gives the Analytics menu an Overview and a Visitor Log child.
 */
class AnalyticsMenuTest extends TestCase
{
    public function test_overview_is_active_only_on_its_own_page_not_the_log(): void
    {
        $active = fn (string $path) => Sidebar::isUrlActive(url($path));

        $this->get('/'); // bind a request so request()->getPathInfo() resolves

        $this->app->instance('request', Request::create('/admin/analytics/visitors', 'GET'));
        $this->assertFalse($active('admin/analytics'), 'Overview must not light up on the Visitor Log page');
        $this->assertTrue($active('admin/analytics/visitors'), 'Visitor Log is active on its own page');

        $this->app->instance('request', Request::create('/admin/analytics', 'GET'));
        $this->assertTrue($active('admin/analytics'), 'Overview is active on the analytics page');
        $this->assertFalse($active('admin/analytics/visitors'), 'Visitor Log is not active on the overview page');
    }

    /** A menu path is matched by whole segments: admin/settings/seo is not admin/settings/seo-audit. */
    public function test_a_sibling_sharing_a_prefix_is_not_lit(): void
    {
        $active = fn (string $path) => Sidebar::isUrlActive(url($path), true);
        $this->get('/');

        $this->app->instance('request', Request::create('/admin/settings/seo-audit', 'GET'));
        $this->assertTrue($active('admin/settings/seo-audit'));
        $this->assertFalse($active('admin/settings/seo'), 'SEO must not light up on SEO Audit');

        $this->app->instance('request', Request::create('/admin/settings/seo', 'GET'));
        $this->assertTrue($active('admin/settings/seo'));
        $this->assertFalse($active('admin/settings/seo-audit'));
    }

    public function test_the_migration_adds_analytics_children(): void
    {
        // Fresh schema has the Analytics parent from the seeder-equivalent? Build it, then migrate.
        $parentId = DB::table('menus')->insertGetId([
            'title' => 'Analytics', 'route' => 'admin.analytics', 'icon' => 'insights',
            'group' => 'System', 'order' => 87, 'created_at' => now(), 'updated_at' => now(),
        ]);

        require_once __DIR__.'/../../../database/migrations/2026_10_02_000001_add_analytics_submenus.php';
        (include __DIR__.'/../../../database/migrations/2026_10_02_000001_add_analytics_submenus.php')->up();

        $children = DB::table('menus')->where('parent_id', $parentId)->orderBy('order')->pluck('route')->all();
        $this->assertSame(['admin.analytics', 'admin.analytics.visitors'], $children);

        // Idempotent: running it again adds nothing.
        (include __DIR__.'/../../../database/migrations/2026_10_02_000001_add_analytics_submenus.php')->up();
        $this->assertCount(2, DB::table('menus')->where('parent_id', $parentId)->get());
    }
}
