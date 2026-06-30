<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_orders') || Schema::hasColumn('shop_orders', 'meta')) {
            return;
        }

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->text('meta')->nullable()->after('customer_note');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
