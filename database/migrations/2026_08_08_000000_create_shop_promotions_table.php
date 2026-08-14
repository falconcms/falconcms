<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic cart promotions — "buy 2 get 1 free", "buy a phone, the case is free",
 * "spend 5000 and pick up a gift".
 *
 * Deliberately NOT a coupon and NOT a product type: a coupon needs a code the customer types
 * and discounts the cart as a whole, while a product type describes what something *is*. A
 * promotion is a rule that watches the cart and rewards specific units inside it, for a while.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_promotions')) {
            Schema::create('shop_promotions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                // Lower runs first, so an "everything" rule can be pushed behind a sharper one.
                $table->integer('priority')->default(10);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();

                // What has to be in the cart: 'product' | 'category' | 'cart_total'
                $table->string('trigger_type', 20)->default('product');
                $table->json('trigger_ids')->nullable();
                // Units for product/category triggers, money for cart_total.
                $table->decimal('trigger_qty', 15, 2)->default(1);

                // What the customer gets: 'free_item' | 'percent_off' | 'fixed_off'
                $table->string('reward_type', 20)->default('free_item');
                // Where the reward comes from: 'same' (the triggering items) | 'specific' | 'category'
                $table->string('reward_scope', 20)->default('same');
                $table->json('reward_ids')->nullable();
                $table->integer('reward_qty')->default(1);
                $table->decimal('reward_value', 15, 2)->default(0);

                // Ceilings — without these one cart could claim a rule an unlimited number of times.
                $table->integer('max_applications')->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_count')->default(0);

                $table->timestamps();

                $table->index(['is_active', 'priority']);
            });
        }

        $this->addAdminMenu();
    }

    /**
     * Slot "Promotions" into the Shop menu just after Coupons/Settings, mirroring how the
     * Reports entry was added.
     */
    protected function addAdminMenu(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $shopMenu = DB::table('menus')->whereNull('parent_id')->where('title', 'Shop')->first();
        if (!$shopMenu) {
            return;
        }

        $exists = DB::table('menus')
            ->where('parent_id', $shopMenu->id)
            ->where('route', 'admin.shop.promotions.index')
            ->exists();
        if ($exists) {
            return;
        }

        $order = (int) DB::table('menus')->where('parent_id', $shopMenu->id)->max('order');

        // Columns mirrored from the sibling Shop entries, which leave icon/permission blank.
        DB::table('menus')->insert([
            'title' => 'Promotions',
            'route' => 'admin.shop.promotions.index',
            'parent_id' => $shopMenu->id,
            'order' => $order + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('route', 'admin.shop.promotions.index')->delete();
        }
        Schema::dropIfExists('shop_promotions');
    }
};
