<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDownload extends Model
{
    protected $table = 'shop_order_downloads';

    protected $fillable = [
        'order_id', 'order_item_id', 'product_download_id',
        'token', 'expires_at', 'download_count', 'download_limit',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productDownload()
    {
        return $this->belongsTo(ProductDownload::class, 'product_download_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->download_limit !== null && $this->download_count >= $this->download_limit;
    }

    public function isAccessible(): bool
    {
        return !$this->isExpired() && !$this->isExhausted();
    }
}
