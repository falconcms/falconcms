<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upsells and cross-sells, the two links a shop owner picks by hand.
 *
 * Stored as JSON id lists on the product row rather than in a pivot table: the lists are short,
 * always read whole, and never queried from the other side ("which products upsell to this one?"
 * is not a question the storefront asks). Related products stay automatic — they are derived from
 * the category, so there is nothing to store.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_products')) {
            return;
        }

        Schema::table('shop_products', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_products', 'upsell_ids')) {
                $table->json('upsell_ids')->nullable()->after('attributes_data');
            }
            if (!Schema::hasColumn('shop_products', 'cross_sell_ids')) {
                $table->json('cross_sell_ids')->nullable()->after('upsell_ids');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shop_products')) {
            return;
        }

        Schema::table('shop_products', function (Blueprint $table) {
            foreach (['upsell_ids', 'cross_sell_ids'] as $column) {
                if (Schema::hasColumn('shop_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
