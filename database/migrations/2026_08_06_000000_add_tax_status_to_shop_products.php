<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-product tax status. The product editor has always shown this select, but there was
     * nowhere to store it — the value was posted and dropped, so the control did nothing.
     * 'taxable' matches the previous effective behaviour (everything was treated as taxable
     * once tax is calculated), so existing rows need no data fix-up.
     */
    public function up(): void
    {
        if (Schema::hasTable('shop_products') && !Schema::hasColumn('shop_products', 'tax_status')) {
            Schema::table('shop_products', function (Blueprint $table) {
                $table->string('tax_status', 20)->default('taxable')->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_products', 'tax_status')) {
            Schema::table('shop_products', function (Blueprint $table) {
                $table->dropColumn('tax_status');
            });
        }
    }
};
