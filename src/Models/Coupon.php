<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'shop_coupons';

    protected $fillable = [
        'code', 'type', 'amount', 'min_spend', 'max_spend',
        'products', 'categories',
        'usage_limit', 'total_usage_limit', 'usage_count',
        'expiry_date', 'is_active',
    ];

    protected $casts = [
        'amount'      => 'float',
        'min_spend'   => 'float',
        'max_spend'   => 'float',
        'products'    => 'array',
        'categories'  => 'array',
        'expiry_date' => 'datetime',
        'is_active'   => 'boolean',
    ];

    /**
     * The discount types the settings screen offers and the cart knows how to price.
     * Must stay in step with the Discount Type dropdown — a type missing from here is
     * silently rewritten to fixed_cart on save.
     */
    public const TYPES = ['percent', 'fixed_cart', 'fixed_product', 'free_shipping'];

    /** Types that discount money off the cart (free_shipping zeroes shipping instead). */
    public const AMOUNT_TYPES = ['percent', 'fixed_cart', 'fixed_product'];

    /**
     * Shape a coupon the way the cart, checkout and discount helpers already expect.
     *
     * The storefront was written against the old settings-JSON entries, so translating here
     * (rather than rewriting every consumer) keeps one definition of the coupon contract and
     * leaves the pricing code untouched.
     */
    public function toCartArray(): array
    {
        return [
            'code'              => $this->code,
            'type'              => $this->type,
            'amount'            => (float) $this->amount,
            'min_spend'         => $this->min_spend !== null ? (float) $this->min_spend : '',
            'max_spend'         => $this->max_spend !== null ? (float) $this->max_spend : '',
            'expiry'            => $this->expiry_date?->format('Y-m-d') ?? '',
            'usage_limit'       => $this->usage_limit ?? '',
            'total_usage_limit' => $this->total_usage_limit ?? '',
            'used_count'        => (int) $this->usage_count,
            'products'          => $this->products ?? [],
            'categories'        => $this->categories ?? [],
        ];
    }

    /**
     * Redeem one use, refusing to go past the global cap.
     *
     * A conditional UPDATE rather than read-then-save: with the old JSON counter, two checkouts
     * completing together both read the same count and wrote the same +1, so the last use of a
     * limited coupon could be handed out twice. Returns false when the cap is already reached.
     */
    public function redeem(): bool
    {
        $query = static::whereKey($this->getKey());

        if ($this->total_usage_limit !== null && $this->total_usage_limit > 0) {
            $query->whereRaw('usage_count < total_usage_limit');
        }

        return $query->update(['usage_count' => \Illuminate\Support\Facades\DB::raw('usage_count + 1'), 'updated_at' => now()]) === 1;
    }

    /** Case-insensitive code lookup, active coupons only. */
    public static function findByCode(?string $code): ?self
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        return static::whereRaw('UPPER(code) = ?', [strtoupper($code)])->where('is_active', true)->first();
    }
}
