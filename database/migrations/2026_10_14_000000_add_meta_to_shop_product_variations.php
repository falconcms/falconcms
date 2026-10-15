<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-variation data a plugin keeps (a wholesale price, a supplier code).
 *
 * Variations are deleted and recreated on every product save, so a plugin table keyed by the
 * variation id would lose its rows each time. The data travels with the row instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_product_variations') || Schema::hasColumn('shop_product_variations', 'meta')) {
            return;
        }

        Schema::table('shop_product_variations', function (Blueprint $table) {
            $table->text('meta')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('shop_product_variations', 'meta')) {
            return;
        }

        Schema::table('shop_product_variations', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
