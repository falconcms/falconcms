<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Builder;

class Product extends Post
{
    protected $table = 'posts';

    protected static function booted()
    {
        static::addGlobalScope('product', function (Builder $builder) {
            $builder->where('type', 'product');
        });

        static::creating(function ($product) {
            $product->type = 'product';
        });
    }

    public function shopData()
    {
        return $this->hasOne(ProductData::class, 'post_id');
    }

    // Helpers for quick access
    public function getPriceAttribute()
    {
        return $this->shopData?->price ?? 0;
    }

    public function getSalePriceAttribute()
    {
        $shopData = $this->shopData;
        if (!$shopData || $shopData->sale_price === null) return null;
        if ($shopData->sale_ends_at && \Carbon\Carbon::parse($shopData->sale_ends_at)->isPast()) return null;
        return $shopData->sale_price;
    }

    public function getSkuAttribute()
    {
        return $this->shopData?->sku;
    }

    public function getStockStatusAttribute()
    {
        return $this->shopData?->stock_status ?? 'instock';
    }

    public function getIsInStockAttribute()
    {
        if (!$this->shopData) {
            return true;
        }
        if ($this->shopData->stock_status === 'outofstock') {
            return false;
        }
        if ($this->shopData->manage_stock && (int) $this->shopData->stock_quantity <= 0) {
            return false;
        }
        return true;
    }
}
