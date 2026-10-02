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
