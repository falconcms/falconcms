<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The theme header's own mega menu — a top-level item's sub-items laid out in columns.
 *
 * Deliberately separate from `mega_menu_id`, which points at a Layout-builder mega-menu design
 * and is rendered only by the builder's Menu element. That one keeps working exactly as it does;
 * these columns drive the theme header, and only when the Customizer's Mega Menu switch is on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('navigation_menu_items') || Schema::hasColumn('navigation_menu_items', 'mega_enabled')) {
            return;
        }

        Schema::table('navigation_menu_items', function (Blueprint $table) {
            $table->boolean('mega_enabled')->default(false)->after('mega_menu_id');
            // 1–6. Null means "whatever the theme defaults to", so a menu saved before the
            // column existed does not have to be re-saved to render.
            $table->unsignedTinyInteger('mega_columns')->nullable()->after('mega_enabled');
            // full | site | custom
            $table->string('mega_width', 20)->nullable()->after('mega_columns');
            $table->string('mega_custom_width', 20)->nullable()->after('mega_width');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('navigation_menu_items')) {
            return;
        }

        Schema::table('navigation_menu_items', function (Blueprint $table) {
            foreach (['mega_enabled', 'mega_columns', 'mega_width', 'mega_custom_width'] as $column) {
                if (Schema::hasColumn('navigation_menu_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
