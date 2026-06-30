<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend shop_products with download metadata
        if (Schema::hasTable('shop_products')) Schema::table('shop_products', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_products', 'is_downloadable')) {
                $table->boolean('is_downloadable')->default(false)->after('sale_ends_at');
            }
            if (!Schema::hasColumn('shop_products', 'download_expiry_days')) {
                $table->unsignedSmallInteger('download_expiry_days')->nullable()->after('is_downloadable');
            }
        });

        // Per-product downloadable files
        if (!Schema::hasTable('shop_product_downloads')) Schema::create('shop_product_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id'); // FK → shop_products.id
            $table->string('name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('download_limit')->nullable(); // null = unlimited
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });

        // Per-order-item download tokens given to buyers
        if (!Schema::hasTable('shop_order_downloads')) Schema::create('shop_order_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('product_download_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('download_count')->default(0);
            $table->unsignedSmallInteger('download_limit')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'order_item_id']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_downloads');
        Schema::dropIfExists('shop_product_downloads');
        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropColumn(['is_downloadable', 'download_expiry_days']);
        });
    }
};
