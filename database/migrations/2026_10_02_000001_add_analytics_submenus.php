<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the Analytics menu two children — Overview and Visitor Log — so the log is reachable
 * from the sidebar, not only from a link on the analytics page.
 */
return new class extends Migration
{
    public function up(): void
    {
        $analytics = DB::table('menus')->whereNull('parent_id')
            ->where(fn ($q) => $q->where('route', 'admin.analytics')->orWhere('title', 'Analytics'))
            ->first();
        if (!$analytics) {
            return;
        }

        foreach ([
            ['title' => 'Overview',    'route' => 'admin.analytics',          'order' => 1],
            ['title' => 'Visitor Log', 'route' => 'admin.analytics.visitors', 'order' => 2],
        ] as $child) {
            $exists = DB::table('menus')->where('parent_id', $analytics->id)
                ->where('route', $child['route'])->exists();
            if (!$exists) {
                DB::table('menus')->insert($child + [
                    'parent_id' => $analytics->id,
                    'icon' => null, 'group' => null, 'permission' => null, 'params' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $analytics = DB::table('menus')->whereNull('parent_id')
            ->where('route', 'admin.analytics')->first();
        if ($analytics) {
            DB::table('menus')->where('parent_id', $analytics->id)
                ->whereIn('route', ['admin.analytics', 'admin.analytics.visitors'])->delete();
        }
    }
};
