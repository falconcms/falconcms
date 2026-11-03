<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post views, now in the core: a running total per post, and one row per post per day for "most
 * viewed today / this week / this month". Kept out of the posts table so counting a view never
 * touches the row editors are saving.
 *
 * A site that counted with Falcon News keeps its figures and its options: both are copied over
 * from the plugin's tables and settings the first time this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_post_views')) {
            Schema::create('cms_post_views', function (Blueprint $table) {
                $table->unsignedBigInteger('post_id')->primary();
                $table->unsignedBigInteger('views')->default(0)->index();
                $table->timestamp('last_viewed_at')->nullable();
            });

            if (Schema::hasTable('news_post_views')) {
                DB::table('news_post_views')->orderBy('post_id')->chunk(1000, function ($rows) {
                    DB::table('cms_post_views')->insert($rows->map(fn ($r) => (array) $r)->all());
                });
            }
        }

        if (!Schema::hasTable('cms_post_view_days')) {
            Schema::create('cms_post_view_days', function (Blueprint $table) {
                $table->unsignedBigInteger('post_id');
                $table->date('day');
                $table->unsignedInteger('views')->default(0);
                $table->primary(['post_id', 'day']);
                $table->index('day');
            });

            if (Schema::hasTable('news_post_view_days')) {
                DB::table('news_post_view_days')->orderBy('post_id')->orderBy('day')->chunk(1000, function ($rows) {
                    DB::table('cms_post_view_days')->insert($rows->map(fn ($r) => (array) $r)->all());
                });
            }
        }

        // The plugin's options become the core's, where the site set them.
        if (Schema::hasTable('cms_settings')) {
            foreach (['enabled', 'post_types', 'count_logged_in', 'window_minutes', 'keep_days'] as $name) {
                $old = DB::table('cms_settings')->where('key', 'news_views_'.$name)->value('value');
                if ($old !== null && !DB::table('cms_settings')->where('key', 'post_views_'.$name)->exists()) {
                    DB::table('cms_settings')->insert(['key' => 'post_views_'.$name, 'value' => $old, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_view_days');
        Schema::dropIfExists('cms_post_views');
    }
};
