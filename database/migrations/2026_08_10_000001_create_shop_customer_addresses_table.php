<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses a customer has saved for reuse at checkout.
 *
 * Columns mirror falcon_get_checkout_fields() one for one, so filling the checkout form from a
 * saved address is a straight copy rather than a mapping that can drift.
 *
 * Orders keep their own copy of the address they were placed with — editing or deleting an
 * address here must never rewrite the history of an order already placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('label', 60)->nullable();      // "Home", "Office" — the customer's own name for it

            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('address_1', 191);
            $table->string('address_2', 191)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postcode', 30)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 191)->nullable();

            // Billing and shipping defaults are chosen separately: plenty of people are billed
            // at home and shipped to work.
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);

            $table->timestamps();

            $table->index('user_id', 'sca_user_idx');
        });

        if (Schema::hasTable('users')) {
            try {
                Schema::table('shop_customer_addresses', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            } catch (\Throwable $e) {
                // SQLite and some hosted MySQL setups refuse this; deletion is handled in code too.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_customer_addresses');
    }
};
