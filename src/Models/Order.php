<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A placed order. The columns are spelled out because static analysis cannot see through
 * Eloquent's `__get`; the list mirrors $fillable below.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $order_number
 * @property string $status
 * @property float $subtotal
 * @property float $shipping_total
 * @property float $tax_total
 * @property float $discount_total
 * @property float $total
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $payment_method
 * @property string|null $transaction_id
 * @property string|null $shipping_method
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $paid_at
 * @property bool $is_read
 * @property-read Collection<int, OrderItem> $items
 */
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
        'paid_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
        'refund_log' => 'array',
        'meta' => 'array',
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

            // On the model rather than in each controller, so every path that moves an order —
            // the admin screen, bulk actions, the Stripe webhook, a plugin's own code — fires it.
            // A query-builder update skips model events; markOrderPaid() fires it by hand.
            if ($order->wasChanged('status')) {
                static::fireStatusChanged($order, (string) $order->getOriginal('status'), (string) $order->status);
            }
        });
    }

    /**
     * Fire `falcon_order_status_changed` and the status-specific `falcon_order_status_{status}`.
     *
     * Public so a writer that bypasses Eloquent (a conditional UPDATE used as a lock) can still
     * announce the change once it has won.
     */
    public static function fireStatusChanged(Order $order, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus || !function_exists('do_falcon_action')) {
            return;
        }

        do_falcon_action('falcon_order_status_changed', $order, $oldStatus, $newStatus);
        do_falcon_action('falcon_order_status_'.$newStatus, $order, $oldStatus);
    }

    // Call this manually after every status change to record it with an optional note.
    public function logStatus(string $status, ?string $note = null): void
    {
        $this->statusHistory()->create(['status' => $status, 'note' => $note ?: null]);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('created_at');
    }
}
