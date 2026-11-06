<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time on site: active seconds per address per day, and on each row of the visitor log. The daily
 * figures stand on their own, as the page cache and a CDN serve pages the visitor log never sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_page_time_days')) {
            Schema::create('cms_page_time_days', function (Blueprint $table) {
                $table->id();
                $table->date('day');
                $table->char('path_hash', 40);
                $table->string('path', 255);
                $table->unsignedBigInteger('post_id')->nullable();
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('visits')->default(0);
                $table->unsignedBigInteger('seconds')->default(0);

                $table->unique(['day', 'path_hash']);
                $table->index(['post_id', 'day']);
            });
        }

        if (Schema::hasTable('cms_analytics') && !Schema::hasColumn('cms_analytics', 'duration_seconds')) {
            Schema::table('cms_analytics', function (Blueprint $table) {
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->index(['ip_address', 'created_at'], 'cms_analytics_ip_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_time_days');

        if (Schema::hasTable('cms_analytics') && Schema::hasColumn('cms_analytics', 'duration_seconds')) {
            Schema::table('cms_analytics', function (Blueprint $table) {
                $table->dropIndex('cms_analytics_ip_created_index');
                $table->dropColumn('duration_seconds');
            });
        }
    }
};
