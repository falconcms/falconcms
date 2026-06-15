<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

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
