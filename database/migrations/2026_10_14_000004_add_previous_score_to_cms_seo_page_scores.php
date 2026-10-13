<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each page's score before its latest check, for the weekly SEO email's "scores that dropped". */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_seo_page_scores') && !Schema::hasColumn('cms_seo_page_scores', 'previous_score')) {
            Schema::table('cms_seo_page_scores', function (Blueprint $table) {
                $table->unsignedTinyInteger('previous_score')->nullable()->after('score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cms_seo_page_scores', 'previous_score')) {
            Schema::table('cms_seo_page_scores', fn (Blueprint $table) => $table->dropColumn('previous_score'));
        }
    }
};
