<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per "this product offers this attribute value".
 *
 * A derived index over shop_products.attributes_data, rebuilt on every product save by
 * falcon_sync_product_attribute_index(). Never edit it directly — run
 * `php artisan falcon:reindex-attributes` if it drifts.
 */
class ProductAttributeValue extends Model
{
    protected $table = 'shop_product_attribute_values';

    protected $fillable = ['post_id', 'name', 'name_slug', 'value', 'value_slug'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
