<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $toolsMenu = DB::table('menus')->whereNull('parent_id')->where('title', 'Tools')->first();
        if (!$toolsMenu) return;

        $existing = DB::table('menus')
            ->where('parent_id', $toolsMenu->id)
            ->where('route', 'admin.export.index')
            ->exists();
        if ($existing) return;

        // Make room at order 2 (Backup stays at 1): bump WordPress Import & Languages down.
        DB::table('menus')
            ->where('parent_id', $toolsMenu->id)
            ->where('order', '>=', 2)
            ->increment('order');

        DB::table('menus')->insert([
            'parent_id'  => $toolsMenu->id,
            'title'      => 'Export',
            'route'      => 'admin.export.index',
            'icon'       => null,
            'group'      => null,
            'order'      => 2,
            'permission' => null,
            'params'     => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $toolsMenu = DB::table('menus')->whereNull('parent_id')->where('title', 'Tools')->first();
        if (!$toolsMenu) return;

        DB::table('menus')
            ->where('parent_id', $toolsMenu->id)
            ->where('route', 'admin.export.index')
            ->delete();

        DB::table('menus')
            ->where('parent_id', $toolsMenu->id)
            ->where('order', '>=', 3)
            ->decrement('order');
    }
};
