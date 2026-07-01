<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantees `custom_taxonomies.deleted_at` exists.
 *
 * The column ships as part of the create migration's softDeletes(), but sites
 * whose table was created before softDeletes() was added would be missing it —
 * which makes "Move to Trash" / delete throw "Unknown column 'deleted_at'" so the
 * taxonomy never actually leaves the list (it appears to "come back"), while
 * Activate/Deactivate keep working. This backfills the column idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('custom_taxonomies')) return;

        if (!Schema::hasColumn('custom_taxonomies', 'deleted_at')) {
            Schema::table('custom_taxonomies', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op: dropping deleted_at would discard
        // soft-delete state and other migrations assume the column exists.
    }
};
