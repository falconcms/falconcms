<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring shop_products.product_type back in step with shop_products.type.
 *
 * The table has carried two columns for the same fact. The product editor only ever wrote `type`,
 * so `product_type` froze at whatever it held when the row was created — leaving products that
 * were variable on the storefront but simple in the admin (and the reverse for rows created by
 * import or by hand).
 *
 * `type` wins, because that is the column the editor has always written: it reflects what the
 * shop owner last chose. Rows where only `product_type` says variable are raised to variable
 * instead of being demoted, so a product that was working as variable keeps its variations.
 *
 * Saves write both columns from here on, so this only has to run once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_products')
            || !Schema::hasColumn('shop_products', 'type')
            || !Schema::hasColumn('shop_products', 'product_type')) {
            return;
        }

        // Either column claiming "variable" means the product has been treated as variable
        // somewhere, and demoting it would orphan its variations.
        DB::table('shop_products')
            ->where(fn ($q) => $q->where('type', 'variable')->orWhere('product_type', 'variable'))
            ->update(['type' => 'variable', 'product_type' => 'variable']);

        // Everything else is simple in both columns, including rows where one was left null.
        DB::table('shop_products')
            ->where(fn ($q) => $q->where('type', '!=', 'variable')->orWhereNull('type'))
            ->where(fn ($q) => $q->where('product_type', '!=', 'variable')->orWhereNull('product_type'))
            ->update(['type' => 'simple', 'product_type' => 'simple']);
    }

    public function down(): void
    {
        // Nothing to undo: this only makes two columns agree on what they already meant.
    }
};
