<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wishlist table the Wishlist model has always expected.
 *
 * It was never created by a migration. The development site had it — made by hand while
 * the feature was being built — so the wishlist worked there and nowhere else: every real
 * install got a 500 the moment anyone opened /wishlist or pressed the heart, because the
 * table the model names simply was not there.
 *
 * Guarded with hasTable() so the sites that do have it (built by hand, like the one this
 * was written on) migrate cleanly rather than failing on a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_wishlists')) {
            return;
        }

        Schema::create('shop_wishlists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            // One row per product per customer — pressing the heart twice toggles it off
            // rather than storing it again, and a double-submit cannot duplicate it.
            $table->unique(['user_id', 'product_id'], 'shop_wishlists_user_product_unique');

            // The list is always read for one customer at a time.
            $table->index('user_id', 'shop_wishlists_user_idx');
        });

        // Added separately and defensively: SQLite cannot attach a foreign key after the
        // fact, and some hosted MySQL setups refuse them outright. The wishlist reads
        // through published products anyway, so a stale row is invisible rather than
        // harmful — the constraint is a tidiness measure, not something to fail an
        // install over.
        try {
            Schema::table('shop_wishlists', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('posts')->cascadeOnDelete();
            });
        } catch (Throwable $e) {
            // Left without constraints; nothing downstream depends on them.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_wishlists');
    }
};
