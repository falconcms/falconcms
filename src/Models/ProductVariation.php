<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property array<string, mixed>|null $attributes_data
 * @property string|float|null $price
 * @property string|float|null $sale_price
 * @property string|null $sku
 * @property int|null $stock_quantity
 * @property string|null $stock_status
 * @property bool|int|null $manage_stock
 * @property string|null $image
 */
class ProductVariation extends Model
{
    protected $table = 'shop_product_variations';

    protected $fillable = [
        'product_id', 'attributes_data', 'price', 'sale_price', 'sku',
        'weight', 'length', 'width', 'height',
        'stock_quantity', 'stock_status', 'manage_stock', 'image',
    ];

    protected $casts = [
        'attributes_data' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(ProductData::class, 'product_id');
    }
}
