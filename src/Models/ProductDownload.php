<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDownload extends Model
{
    protected $table = 'shop_product_downloads';

    protected $fillable = [
        'product_id', 'name', 'file_path', 'file_size', 'download_limit', 'sort_order',
    ];

    public function productData()
    {
        return $this->belongsTo(ProductData::class, 'product_id');
    }

    public function orderDownloads()
    {
        return $this->hasMany(OrderDownload::class, 'product_download_id');
    }
}
