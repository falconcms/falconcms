<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The analytics submenu migration gives the Analytics menu an Overview and a Visitor Log child.
 */
class AnalyticsMenuTest extends TestCase
{
    public function test_overview_is_active_only_on_its_own_page_not_the_log(): void
    {
        $active = fn (string $path) => \FalconCms\Core\View\Components\Admin\Sidebar::isUrlActive(url($path));

        $this->get('/'); // bind a request so request()->getPathInfo() resolves

        $this->app->instance('request', \Illuminate\Http\Request::create('/admin/analytics/visitors', 'GET'));
        $this->assertFalse($active('admin/analytics'), 'Overview must not light up on the Visitor Log page');
        $this->assertTrue($active('admin/analytics/visitors'), 'Visitor Log is active on its own page');

        $this->app->instance('request', \Illuminate\Http\Request::create('/admin/analytics', 'GET'));
        $this->assertTrue($active('admin/analytics'), 'Overview is active on the analytics page');
        $this->assertFalse($active('admin/analytics/visitors'), 'Visitor Log is not active on the overview page');
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
