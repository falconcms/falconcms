<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Product Categories
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

        // 2. Product Tags
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
        // Optional: delete them on rollback
        // DB::table('custom_taxonomies')->whereIn('slug', ['product_cat', 'product_tag'])->delete();
    }
};
