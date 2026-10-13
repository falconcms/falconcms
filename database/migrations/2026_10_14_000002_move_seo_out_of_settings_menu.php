<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEO is a menu of its own now (registered in code, with tabs like Settings), so its screens
 * leave the Settings menu: SEO, SEO Audit, Bulk SEO, Site Analysis and Redirects.
 */
return new class extends Migration
{
    private const ROUTES = ['admin.settings.seo', 'admin.settings.seo-audit', 'admin.settings.seo-bulk', 'admin.settings.site-analysis', 'admin.redirects.index'];

    public function up(): void
    {
        $settings = $this->settingsMenu();
        if ($settings) {
            DB::table('menus')->where('parent_id', $settings->id)->whereIn('route', self::ROUTES)->delete();
        }
    }

    public function down(): void
    {
        $settings = $this->settingsMenu();
        if (!$settings) {
            return;
        }
        foreach ([['SEO', 'admin.settings.seo', 2], ['Redirects', 'admin.redirects.index', 3]] as [$title, $route, $order]) {
            if (!DB::table('menus')->where('parent_id', $settings->id)->where('route', $route)->exists()) {
                DB::table('menus')->insert(['title' => $title, 'route' => $route, 'order' => $order, 'parent_id' => $settings->id,
                    'icon' => null, 'group' => null, 'permission' => null, 'params' => null, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function settingsMenu(): ?object
    {
        return DB::table('menus')->whereNull('parent_id')
            ->where(fn ($q) => $q->where('route', 'admin.settings.index')->orWhere('title', 'Settings'))
            ->first();
    }
};
