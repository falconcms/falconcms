<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shopMenu = DB::table('menus')->where('title', 'Shop')->first();
        if ($shopMenu) {
            // Delete any existing review related submenus for Shop
            DB::table('menus')
                ->where('parent_id', $shopMenu->id)
                ->where(function($q) {
                    $q->where('title', 'like', '%Review%')
                      ->orWhere('route', 'admin.shop.reviews.index');
                })
                ->delete();

            // Insert a clean "Product Reviews" submenu
            DB::table('menus')->insert([
                'parent_id' => $shopMenu->id,
                'title' => 'Product Reviews',
                'route' => 'admin.shop.reviews.index',
                'params' => null,
                'order' => 2,
                'created_at' => now(), 'updated_at' => now()
            ]);
            
            // Re-order other items for consistency
            DB::table('menus')->where('parent_id', $shopMenu->id)->where('title', 'Orders')->update(['order' => 1]);
            DB::table('menus')->where('parent_id', $shopMenu->id)->where('title', 'Customers')->update(['order' => 3]);
            DB::table('menus')->where('parent_id', $shopMenu->id)->where('title', 'Settings')->update(['order' => 4]);
        }
    }

    public function down(): void
    {
        // No revert needed
    }
};
