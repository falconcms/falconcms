<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $table    = 'shop_order_status_history';
    protected $fillable = ['order_id', 'status', 'note'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public static function defaultNote(string $status): string
    {
        return match ($status) {
            'pending'            => 'Your order has been received and is awaiting processing.',
            'processing'         => 'We have received your order and it is now being processed.',
            'confirmed'          => 'Your order has been confirmed.',
            'packing'            => 'We are currently packing your order.',
            'packed'             => 'Your order is packed and ready for shipment.',
            'delivering'         => 'Your order is on its way to you.',
            'completed'          => 'Your order has been completed.',
            'delivered'          => 'Your order has been delivered successfully.',
            'cancelled'          => 'Your order has been cancelled.',
            'refunded'           => 'Your order has been refunded.',
            'partially-refunded' => 'A partial refund has been issued for your order.',
            'on-hold'            => 'Your order is currently on hold.',
            'failed'             => 'Payment for this order has failed.',
            default              => ucfirst(str_replace('-', ' ', $status)) . '.',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'pending'            => 'Order Placed',
            'processing'         => 'Processing',
            'confirmed'          => 'Confirmed',
            'packing'            => 'Packing',
            'packed'             => 'Packed',
            'delivering'         => 'Delivering',
            'completed'          => 'Completed',
            'delivered'          => 'Delivered',
            'cancelled'          => 'Cancelled',
            'refunded'           => 'Refunded',
            'partially-refunded' => 'Partially Refunded',
            'on-hold'            => 'On Hold',
            'failed'             => 'Payment Failed',
            default              => ucwords(str_replace('-', ' ', $status)),
        };
    }
}
