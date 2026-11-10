<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cms_analytics.human — whether a real browser confirmed the visit.
 *
 * The visitor log used to count every successful page request whose user agent did not name a
 * bot. Most of what reached it did not: on falconcms.com, of 592 rows in four days only 51 came
 * from a browser that ran the page's scripts, the rest from data-centre addresses with a stock
 * Chrome user agent. A row the server writes is now 0 until the page's own script, running in a
 * visible tab with a person at it, confirms it (HumanVisits); the dashboard counts only 1s.
 *
 * Rows already in the log are kept. Those the page-time script reached are known to be people;
 * the rest cannot be told apart from the bots that make up most of them, so they are marked 0 —
 * hidden from the figures, not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_analytics') || Schema::hasColumn('cms_analytics', 'human')) {
            return;
        }

        Schema::table('cms_analytics', function (Blueprint $table) {
            // Defaults to 1 so that rows written by anything that does not know the column
            // (an import, an older plugin) keep being counted as they always were.
            $table->boolean('human')->default(true);
            $table->index(['human', 'created_at'], 'cms_analytics_human_created_index');
        });

        DB::table('cms_analytics')->whereNull('duration_seconds')->update(['human' => false]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_analytics') && Schema::hasColumn('cms_analytics', 'human')) {
            Schema::table('cms_analytics', function (Blueprint $table) {
                $table->dropIndex('cms_analytics_human_created_index');
                $table->dropColumn('human');
            });
        }
    }
};
