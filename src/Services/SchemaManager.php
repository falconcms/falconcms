<?php

namespace FalconCms\Core\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SchemaManager
{
    /**
     * Ensure required columns exist in a table.
     *
     * @param string $table Table name
     * @param array $columns Definition: ['column_name' => ['type' => 'string', 'nullable' => true, 'default' => null, 'after' => 'other_col']]
     * @return void
     */
    public static function ensureColumns(string $table, array $columns)
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $columns) {
            foreach ($columns as $name => $definition) {
                if (!Schema::hasColumn($table, $name)) {
                    $type = $definition['type'] ?? 'string';
                    $column = $tableBlueprint->$type($name);

                    if (!empty($definition['nullable'])) {
                        $column->nullable();
                    }

                    if (array_key_exists('default', $definition)) {
                        $column->default($definition['default']);
                    }

                    if (!empty($definition['after'])) {
                        $column->after($definition['after']);
                    }
                    
                    if (!empty($definition['index'])) {
                        $column->index();
                    }
                }
            }
        });
    }

    /**
     * Get core schema definitions for different tables.
     */
    public static function getCoreDefinitions(): array
    {
        return [
            'posts' => [
                'gallery' => ['type' => 'json', 'nullable' => true, 'after' => 'featured_image'],
                'seo_meta' => ['type' => 'json', 'nullable' => true],
                'origin_id' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'index' => true],
                'lang_code' => ['type' => 'string', 'nullable' => true, 'default' => 'en', 'index' => true],
            ],
            'shop_products' => [
                'short_description' => ['type' => 'text', 'nullable' => true, 'after' => 'product_type'],
                'price' => ['type' => 'decimal', 'default' => 0, 'index' => true, 'params' => [15, 2]],
                'sale_price' => ['type' => 'decimal', 'nullable' => true, 'index' => true, 'params' => [15, 2]],
                'sku' => ['type' => 'string', 'nullable' => true, 'index' => true],
                'stock_quantity' => ['type' => 'integer', 'default' => 0],
                'stock_status' => ['type' => 'string', 'default' => 'instock', 'index' => true],
                'manage_stock' => ['type' => 'boolean', 'default' => false],
            ],
            'shop_orders' => [
                'discount_total' => ['type' => 'decimal', 'default' => 0, 'params' => [15, 2]],
                'coupon_code' => ['type' => 'string', 'nullable' => true],
            ],
            'shop_reviews' => [
                'parent_id' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'index' => true],
            ]
        ];
    }
}
