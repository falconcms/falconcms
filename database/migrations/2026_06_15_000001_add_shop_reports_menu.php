<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shopMenu = DB::table('menus')->whereNull('parent_id')->where('title', 'Shop')->first();
        if (!$shopMenu) {
            return;
        }

        // Shift Orders (1→2) and Product Reviews (2→3) to make room.
        DB::table('menus')
            ->where('parent_id', $shopMenu->id)
            ->where('order', '>=', 1)
            ->where('order', '<=', 3)
            ->increment('order');

        // Insert Reports at order 1, right after Overview (order 0).
        $existing = DB::table('menus')
            ->where('parent_id', $shopMenu->id)
            ->where('route', 'admin.shop.reports.index')
            ->exists();

        if (!$existing) {
            DB::table('menus')->insert([
                'parent_id' => $shopMenu->id,
                'title' => 'Reports',
                'route' => 'admin.shop.reports.index',
                'icon' => null,
                'group' => null,
                'order' => 1,
                'permission' => null,
                'params' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $shopMenu = DB::table('menus')->whereNull('parent_id')->where('title', 'Shop')->first();
        if (!$shopMenu) {
            return;
        }

        DB::table('menus')
            ->where('parent_id', $shopMenu->id)
            ->where('route', 'admin.shop.reports.index')
            ->delete();

        // Shift Orders and Product Reviews back down.
        DB::table('menus')
            ->where('parent_id', $shopMenu->id)
            ->where('order', '>=', 2)
            ->where('order', '<=', 4)
            ->decrement('order');
    }
};
