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

    // shopData() and the price / sale_price / sku / stock_status / is_in_stock accessors
    // are deliberately NOT redeclared here.
    //
    // They used to be, as copies of Post's — and the copies went stale. Post's
    // is_in_stock learned about variations, backorders, the shop-wide "manage stock"
    // switch and the out-of-stock threshold; this class's copy never did, so the same
    // product answered differently depending on which model happened to load it. The
    // sale_price copy likewise predates sale_ends_at being applied through
    // ProductData::active_sale_price.
    //
    // Product exists only to scope posts to type=product. Everything about what a
    // product costs and whether it can be bought belongs to ProductData, reached
    // through the accessors on Post.
}
