<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('currency')->nullable()->after('total');
            $table->string('currency_symbol')->nullable()->after('currency');
            $table->string('currency_position')->nullable()->after('currency_symbol');
            $table->string('thousand_separator')->nullable()->after('currency_position');
            $table->string('decimal_separator')->nullable()->after('thousand_separator');
            $table->integer('decimals')->default(2)->after('decimal_separator');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'currency_symbol',
                'currency_position',
                'thousand_separator',
                'decimal_separator',
                'decimals'
            ]);
        });
    }
};
