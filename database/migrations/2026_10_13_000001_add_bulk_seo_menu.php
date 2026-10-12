<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Put Bulk SEO in the Settings menu, next to SEO Audit. */
return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('menus')->whereNull('parent_id')
            ->where(fn ($q) => $q->where('route', 'admin.settings.index')->orWhere('title', 'Settings'))
            ->first();
        if (!$settings || DB::table('menus')->where('parent_id', $settings->id)->where('route', 'admin.settings.seo-bulk')->exists()) {
            return;
        }

        DB::table('menus')->insert([
            'title' => 'Bulk SEO', 'route' => 'admin.settings.seo-bulk', 'order' => 2, 'parent_id' => $settings->id,
            'icon' => null, 'group' => null, 'permission' => null, 'params' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'admin.settings.seo-bulk')->delete();
    }
};
