<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core Web Vitals measured in real visitors' browsers (Customizer → Performance → Measure Real
 * Visitor Speed), one row per page view, kept 90 days. Shown under Analytics → Web Vitals.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_web_vitals')) {
            Schema::create('cms_web_vitals', function (Blueprint $table) {
                $table->id();
                $table->date('date')->index();
                $table->char('path_hash', 40)->index();
                $table->string('path', 500);
                $table->char('device', 1); // m = mobile, d = desktop
                $table->unsignedInteger('lcp')->nullable(); // ms
                $table->unsignedInteger('inp')->nullable(); // ms
                $table->decimal('cls', 6, 4)->nullable();
                $table->unsignedInteger('ttfb')->nullable(); // ms
            });
        }

        $analytics = DB::table('menus')->whereNull('parent_id')
            ->where(fn ($q) => $q->where('route', 'admin.analytics')->orWhere('title', 'Analytics'))
            ->first();
        if ($analytics && !DB::table('menus')->where('parent_id', $analytics->id)->where('route', 'admin.analytics.vitals')->exists()) {
            DB::table('menus')->insert([
                'title' => 'Web Vitals', 'route' => 'admin.analytics.vitals', 'order' => 3, 'parent_id' => $analytics->id,
                'icon' => null, 'group' => null, 'permission' => null, 'params' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'admin.analytics.vitals')->delete();
        Schema::dropIfExists('cms_web_vitals');
    }
};
