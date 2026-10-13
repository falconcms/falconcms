<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each page's latest SEO check-up (Falcon SEO → Site Analysis, the editor's Analysis tab and
 * the score in the posts list): its score and the problems found. One row per post.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_seo_page_scores')) {
            Schema::create('cms_seo_page_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('post_id')->unique();
                $table->string('url', 500)->nullable();
                $table->unsignedTinyInteger('score')->default(0);
                $table->string('grade', 8)->default('poor'); // good | ok | poor
                $table->unsignedSmallInteger('errors')->default(0);
                $table->unsignedSmallInteger('warnings')->default(0);
                $table->string('mode', 8)->default('live'); // live | editor
                $table->text('issues')->nullable(); // JSON: the checks that failed, with their items
                $table->timestamp('analyzed_at')->nullable()->index(); // null: changed since, due again
                $table->timestamps();
            });
        }

        $settings = DB::table('menus')->whereNull('parent_id')
            ->where(fn ($q) => $q->where('route', 'admin.settings.index')->orWhere('title', 'Settings'))
            ->first();
        if ($settings && !DB::table('menus')->where('parent_id', $settings->id)->where('route', 'admin.settings.site-analysis')->exists()) {
            DB::table('menus')->insert([
                'title' => 'Site Analysis', 'route' => 'admin.settings.site-analysis', 'order' => 2, 'parent_id' => $settings->id,
                'icon' => null, 'group' => null, 'permission' => null, 'params' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'admin.settings.site-analysis')->delete();
        Schema::dropIfExists('cms_seo_page_scores');
    }
};
