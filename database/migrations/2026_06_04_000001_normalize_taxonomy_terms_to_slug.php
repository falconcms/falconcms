<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalizes the `taxonomy_terms` table to the slug-based schema the application
 * actually uses (`taxonomy_slug` + `cpt_slug`). Older installs created the table
 * with a `taxonomy_id` FK (the original create migration), which made every
 * ACPT taxonomy screen throw "Unknown column 'taxonomy_terms.taxonomy_slug'".
 *
 * Idempotent: does nothing if the table is already on the slug schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taxonomy_terms')) {
            return;
        }
        if (Schema::hasColumn('taxonomy_terms', 'taxonomy_slug')) {
            return;
        } // already normalized

        // 1) Add the columns the application queries.
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            $table->string('taxonomy_slug')->nullable()->after('id');
            $table->string('cpt_slug')->nullable()->after('taxonomy_slug');
        });

        // 2) Backfill from custom_taxonomies via the legacy taxonomy_id.
        if (Schema::hasColumn('taxonomy_terms', 'taxonomy_id') && Schema::hasTable('custom_taxonomies')) {
            foreach (DB::table('custom_taxonomies')->get(['id', 'slug', 'post_types']) as $tax) {
                $cpt = null;
                $pt = json_decode($tax->post_types ?? '', true);
                if (is_array($pt) && !empty($pt)) {
                    $cpt = reset($pt);
                }
                DB::table('taxonomy_terms')
                    ->where('taxonomy_id', $tax->id)
                    ->update(['taxonomy_slug' => $tax->slug, 'cpt_slug' => $cpt]);
            }
        }

        // 3) Index the new lookup column.
        try {
            Schema::table('taxonomy_terms', function (Blueprint $table) {
                $table->index('taxonomy_slug', 'taxonomy_terms_taxonomy_slug_index');
            });
        } catch (Throwable $e) {
        }

        // 4) Retire the legacy taxonomy_id (drop unique index, FK + its index, then the
        //    column) so inserts that only set taxonomy_slug succeed and the schema matches.
        if (Schema::hasColumn('taxonomy_terms', 'taxonomy_id')) {
            Schema::table('taxonomy_terms', function (Blueprint $table) {
                try {
                    $table->dropUnique('terms_slug_tax_lang_unique');
                } catch (Throwable $e) {
                }
                try {
                    $table->dropForeign('taxonomy_terms_taxonomy_id_foreign');
                } catch (Throwable $e) {
                }
                try {
                    $table->dropIndex('taxonomy_terms_taxonomy_id_foreign');
                } catch (Throwable $e) {
                }
            });
            try {
                Schema::table('taxonomy_terms', function (Blueprint $table) {
                    $table->dropColumn('taxonomy_id');
                });
            } catch (Throwable $e) {
            }
        }

        // 5) Restore the uniqueness guarantee on the new column set.
        try {
            Schema::table('taxonomy_terms', function (Blueprint $table) {
                $table->unique(['slug', 'taxonomy_slug', 'lang_code'], 'terms_slug_taxslug_lang_unique');
            });
        } catch (Throwable $e) {
        }
    }

    public function down(): void
    {
        // One-way normalization; intentionally not reversible.
    }
};
