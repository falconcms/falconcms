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
            ->where('route', 'admin.import.index')
            ->exists();
        if ($existing) return;

        // Place Import right below Export. Export sits at order 2, so make room
        // at order 3 by bumping everything from 3 onwards down.
        DB::table('menus')
            ->where('parent_id', $toolsMenu->id)
            ->where('order', '>=', 3)
            ->increment('order');

        DB::table('menus')->insert([
            'parent_id'  => $toolsMenu->id,
            'title'      => 'Import',
            'route'      => 'admin.import.index',
            'icon'       => null,
            'group'      => null,
            'order'      => 3,
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
            ->where('route', 'admin.import.index')
            ->delete();

        DB::table('menus')
            ->where('parent_id', $toolsMenu->id)
            ->where('order', '>=', 4)
            ->decrement('order');
    }
};
