<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional shop-written wording for a promotion.
 *
 * The generated summary ("Buy 3 of selected products, get 1 free") is accurate but mechanical
 * and always English, which is no use to a shop selling in another language. Left empty, the
 * generated text is still used, so nothing changes for existing promotions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_promotions') && !Schema::hasColumn('shop_promotions', 'cart_message')) {
            Schema::table('shop_promotions', function (Blueprint $table) {
                $table->string('cart_message')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_promotions', 'cart_message')) {
            Schema::table('shop_promotions', function (Blueprint $table) {
                $table->dropColumn('cart_message');
            });
        }
    }
};
