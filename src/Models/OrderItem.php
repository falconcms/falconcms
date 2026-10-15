<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order. The columns are spelled out because static analysis cannot see through
 * Eloquent's `__get`; the list mirrors $fillable below.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variation_id
 * @property string $product_name
 * @property int $quantity
 * @property float|string $price
 * @property float|string $subtotal
 * @property array<string, mixed>|null $meta
 * @property-read Order|null $order
 * @property-read Product|null $product
 * @property-read ProductVariation|null $variation
 */
class OrderItem extends Model
{
    protected $table = 'shop_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'variation_id',
        'product_name', 'quantity', 'price', 'subtotal', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<ProductVariation, $this> */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }
}
