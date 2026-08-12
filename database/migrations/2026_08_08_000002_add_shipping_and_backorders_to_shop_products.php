<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two gaps in the simple-product record.
 *
 * Weight and dimensions only ever existed on variations, so a simple product had nowhere to
 * store them — leaving the Weight Unit / Dimension Unit settings with nothing to measure and
 * making weight-based shipping impossible.
 *
 * `backorders` gives the 'onbackorder' stock status something to act on: it was accepted by the
 * product form's validation but no code ever read it, so a shop could not sell ahead of stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_products')) {
            return;
        }

        Schema::table('shop_products', function (Blueprint $table) {
            foreach (['weight', 'length', 'width', 'height'] as $column) {
                if (!Schema::hasColumn('shop_products', $column)) {
                    $table->decimal($column, 12, 3)->nullable();
                }
            }

            // 'no' | 'notify' (allowed, but the shopper is told) | 'yes'
            if (!Schema::hasColumn('shop_products', 'backorders')) {
                $table->string('backorders', 10)->default('no')->after('stock_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_products', function (Blueprint $table) {
            foreach (['weight', 'length', 'width', 'height', 'backorders'] as $column) {
                if (Schema::hasColumn('shop_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
