<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An automatic cart promotion ("buy 2 get 1 free", "buy a phone, the case is free").
 *
 * Rules are evaluated fresh on every cart read — nothing about a reward is ever stored in the
 * customer's session, so there is no client-side state to forge.
 */
class Promotion extends Model
{
    protected $table = 'shop_promotions';

    protected $fillable = [
        'name', 'cart_message', 'is_active', 'priority', 'starts_at', 'ends_at',
        'trigger_type', 'trigger_ids', 'trigger_qty',
        'reward_type', 'reward_scope', 'reward_ids', 'reward_qty', 'reward_value',
        'max_applications', 'usage_limit', 'usage_count',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'trigger_ids' => 'array',
        'reward_ids'  => 'array',
        'trigger_qty' => 'float',
        'reward_value'=> 'float',
    ];

    public const TRIGGER_TYPES = ['product', 'category', 'cart_total'];
    public const REWARD_TYPES  = ['free_item', 'percent_off', 'fixed_off'];
    public const REWARD_SCOPES = ['same', 'specific', 'category'];

    /** Active, inside its date window, and not past its global usage cap. */
    public function scopeUsable($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(fn ($q) => $q->whereNull('usage_limit')
                ->orWhereRaw('usage_count < usage_limit'))
            ->orderBy('priority')
            ->orderBy('id');
    }

    /**
     * Claim one use, refusing to go past the global cap.
     *
     * Conditional UPDATE rather than read-then-save, for the same reason coupons use one: two
     * checkouts completing in the same instant would otherwise both read the old count and both
     * write count+1, handing out one more redemption than the limit allows.
     */
    public function redeem(): bool
    {
        $query = static::whereKey($this->getKey());

        if ($this->usage_limit !== null && $this->usage_limit > 0) {
            $query->whereRaw('usage_count < usage_limit');
        }

        return $query->update([
            'usage_count' => DB::raw('usage_count + 1'),
            'updated_at'  => now(),
        ]) === 1;
    }

    /** Human-readable summary for the admin list, e.g. "Buy 2, get 1 free". */
    public function getSummaryAttribute(): string
    {
        $qty = (int) $this->trigger_qty;

        $trigger = match ($this->trigger_type) {
            'cart_total' => 'Spend ' . falcon_price_format($this->trigger_qty),
            'category'   => 'Buy ' . $qty . ' from selected categories',
            default      => 'Buy ' . $qty . ' of selected products',
        };

        $reward = match ($this->reward_type) {
            'percent_off' => rtrim(rtrim(number_format($this->reward_value, 2), '0'), '.') . '% off',
            'fixed_off'   => falcon_price_format($this->reward_value) . ' off',
            default       => 'free',
        };

        return $trigger . ', get ' . (int) $this->reward_qty . ' ' . $reward;
    }

    /**
     * What the shopper actually reads: the shop's own wording when it wrote some, otherwise the
     * generated summary. `{missing}` is substituted by the caller that knows the number.
     */
    public function getCustomerMessageAttribute(): string
    {
        $custom = trim((string) ($this->cart_message ?? ''));

        return $custom !== '' ? $custom : $this->summary;
    }
}
