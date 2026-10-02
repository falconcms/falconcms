<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Settings → Site Health, last in the Settings submenu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('menus')->whereNull('parent_id')->where('route', 'admin.settings.index')->first()
            ?? DB::table('menus')->whereNull('parent_id')->where('title', 'Settings')->first();
        if (!$settings) {
            return;
        }
        if (DB::table('menus')->where('parent_id', $settings->id)->where('route', 'admin.settings.site-health')->exists()) {
            return;
        }

        DB::table('menus')->insert([
            'parent_id' => $settings->id,
            'title' => 'Site Health',
            'route' => 'admin.settings.site-health',
            'icon' => null,
            'group' => null,
            'order' => (int) DB::table('menus')->where('parent_id', $settings->id)->max('order') + 1,
            'permission' => null,
            'params' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'admin.settings.site-health')->delete();
    }
};
