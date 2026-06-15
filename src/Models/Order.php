<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'shop_orders';
    protected $fillable = [
        'user_id', 'order_number', 'status',
        'subtotal', 'shipping_total', 'tax_total', 'discount_total', 'coupon_code', 'total',
        'customer_email', 'customer_phone', 'first_name', 'last_name',
        'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country',
        'shipping_first_name', 'shipping_last_name',
        'shipping_address_line_1', 'shipping_address_line_2',
        'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country',
        'payment_method', 'transaction_id', 'paid_at', 'customer_note', 'meta',
        'currency', 'currency_symbol', 'currency_position',
        'thousand_separator', 'decimal_separator', 'decimals',
        'refunded_amount', 'refund_log',
        'shipping_method', 'tracking_number', 'tracking_carrier', 'tracking_url',
        'is_read',
    ];

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
