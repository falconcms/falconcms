<?php

namespace Acme\CmsDashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'shop_orders';
    protected $guarded = [];

    protected $casts = [
        'paid_at'          => 'datetime',
        'refunded_amount'  => 'decimal:2',
        'refund_log'       => 'array',
        'meta'             => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        // Log "Order Placed" when first created
        static::created(function (Order $order) {
            $order->statusHistory()->create(['status' => $order->status ?? 'pending', 'note' => null]);
        });

        // Auto-log payment event when paid_at is first set
        static::updated(function (Order $order) {
            if ($order->wasChanged('paid_at') && $order->paid_at !== null) {
                $order->statusHistory()->create(['status' => 'payment', 'note' => 'Your order is paid.']);
            }
        });
    }

    // Call this manually after every status change to record it with an optional note.
    public function logStatus(string $status, ?string $note = null): void
    {
        $this->statusHistory()->create(['status' => $status, 'note' => $note ?: null]);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('created_at');
    }
}
