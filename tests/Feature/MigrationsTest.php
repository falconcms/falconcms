<?php

namespace FalconCms\Core\Tests\Feature;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

/**
 * The install path itself. Every other test depends on the migrations running, so if
 * this one is red nothing below it means anything.
 */
class MigrationsTest extends TestCase
{
    public function test_the_core_tables_exist_after_migrating(): void
    {
        foreach ([
            'users', 'posts', 'cms_settings', 'categories', 'tags', 'menus',
            'media', 'roles', 'permissions', 'cms_languages', 'cms_redirects',
            'post_types', 'custom_taxonomies', 'taxonomy_terms',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table: {$table}");
        }
    }

    public function test_the_shop_tables_exist_after_migrating(): void
    {
        foreach ([
            'shop_products', 'shop_product_variations', 'shop_product_attribute_values',
            'shop_customer_addresses', 'shop_orders', 'shop_order_items', 'shop_coupons',
            'shop_promotions', 'shop_reviews',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table: {$table}");
        }
    }

    public function test_the_columns_the_shop_logic_reads_are_present(): void
    {
        // Both spellings live on shop_products; the admin writes `type` while older code
        // read `product_type`, and reconciling them is what the 2026_08_12 migration does.
        $this->assertTrue(Schema::hasColumn('shop_products', 'type'));
        $this->assertTrue(Schema::hasColumn('shop_products', 'product_type'));

        $this->assertTrue(Schema::hasColumn('shop_products', 'sale_ends_at'));
        $this->assertTrue(Schema::hasColumn('shop_products', 'weight'));
        $this->assertTrue(Schema::hasColumn('shop_products', 'upsell_ids'));
        $this->assertTrue(Schema::hasColumn('shop_products', 'cross_sell_ids'));

        $this->assertTrue(Schema::hasColumn('users', 'is_blocked'));
    }
}
