<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_order_status_history')) {
            return;
        }

        Schema::create('shop_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_status_history');
    }
};
