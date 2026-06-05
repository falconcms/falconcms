<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Register Product Post Type if not exists
        $productTypeExists = DB::table('post_types')->where('slug', 'product')->exists();
        if (!$productTypeExists) {
            DB::table('post_types')->insert([
                'name' => 'Products',
                'slug' => 'product',
                'singular_name' => 'Product',
                'is_builtin' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'supports' => json_encode(['title', 'editor', 'thumbnail', 'excerpt', 'comments'])
            ]);
        }

        // 2. Product Data Table (for fast filtering)
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('type')->default('simple');            // simple | variable
            $table->json('attributes_data')->nullable();          // selected attribute values
            $table->decimal('price', 15, 2)->default(0)->index();
            $table->decimal('sale_price', 15, 2)->nullable()->index();
            $table->timestamp('sale_ends_at')->nullable();
            $table->string('sku')->nullable()->index();
            $table->integer('stock_quantity')->default(0);
            $table->string('stock_status')->default('instock')->index(); // instock, outofstock, onbackorder
            $table->boolean('manage_stock')->default(false);
            $table->string('product_type')->default('simple'); // simple, variable, external, grouped
            $table->text('short_description')->nullable();
            $table->json('attributes')->nullable(); // weight, dimensions, etc.
            $table->timestamps();
        });

        // 3. Orders Table
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('pending')->index(); 
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->decimal('total', 15, 2)->index();
            
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postcode');
            $table->string('country');

            // Separate shipping address (optional — falls back to billing when empty)
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->text('shipping_address_line_1')->nullable();
            $table->text('shipping_address_line_2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postcode')->nullable();
            $table->string('shipping_country')->nullable();

            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('customer_note')->nullable();

            // Merged from former add-column migrations (currency, refunds, shipping, tracking, read-state)
            $table->string('currency')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('currency_position')->nullable();
            $table->string('thousand_separator')->nullable();
            $table->string('decimal_separator')->nullable();
            $table->integer('decimals')->default(2);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->json('refund_log')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_carrier')->nullable();
            $table->string('tracking_url')->nullable();
            $table->boolean('is_read')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Order Items Table
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->unsignedBigInteger('variation_id')->nullable(); // chosen variation for variable products
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // Variations for variable products (merged from former app migrations).
        Schema::create('shop_product_variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id'); // shop_products.id
            $table->json('attributes_data');           // e.g. {"Color":"Red","Size":"S"}
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('stock_status')->default('instock');
            $table->boolean('manage_stock')->default(false);
            $table->string('image')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('shop_products')->onDelete('cascade');
        });

        // 5. Register Product Taxonomies
        $catExists = DB::table('custom_taxonomies')->where('slug', 'product_cat')->exists();
        if (!$catExists) {
            DB::table('custom_taxonomies')->insert([
                'name' => 'Product Categories',
                'slug' => 'product_cat',
                'singular_name' => 'Product Category',
                'post_types' => json_encode(['product']),
                'hierarchical' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $tagExists = DB::table('custom_taxonomies')->where('slug', 'product_tag')->exists();
        if (!$tagExists) {
            DB::table('custom_taxonomies')->insert([
                'name' => 'Product Tags',
                'slug' => 'product_tag',
                'singular_name' => 'Product Tag',
                'post_types' => json_encode(['product']),
                'hierarchical' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_products');
        DB::table('post_types')->where('slug', 'product')->delete();
    }
};
