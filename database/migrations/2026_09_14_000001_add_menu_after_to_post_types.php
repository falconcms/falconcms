<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which sidebar menu a custom post type was placed after, so the choice survives an
 * edit: re-saving a post type re-applies its position instead of quietly appending it again.
 *
 * Plain nullable id, deliberately no foreign key — if the menu it points at is later deleted the
 * post type just falls back to the bottom of the sidebar rather than blocking the delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('post_types') || Schema::hasColumn('post_types', 'menu_after')) {
            return;
        }

        Schema::table('post_types', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_after')->nullable()->after('show_in_menu');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('post_types') && Schema::hasColumn('post_types', 'menu_after')) {
            Schema::table('post_types', function (Blueprint $table) {
                $table->dropColumn('menu_after');
            });
        }
    }
};
