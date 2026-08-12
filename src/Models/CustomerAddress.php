<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shipping/billing address a customer saved for reuse.
 *
 * Field names match the checkout form's `billing_*` / `shipping_*` inputs minus the prefix, so
 * filling the form from a saved address is a direct copy.
 */
class CustomerAddress extends Model
{
    protected $table = 'shop_customer_addresses';

    protected $fillable = [
        'user_id', 'label',
        'first_name', 'last_name', 'country', 'address_1', 'address_2',
        'city', 'state', 'postcode', 'phone', 'email',
        'is_default_billing', 'is_default_shipping',
    ];

    protected $casts = [
        'is_default_billing'  => 'boolean',
        'is_default_shipping' => 'boolean',
    ];

    /** The columns a checkout form can be filled from, without their billing_/shipping_ prefix. */
    public const FIELDS = [
        'first_name', 'last_name', 'country', 'address_1', 'address_2',
        'city', 'state', 'postcode', 'phone', 'email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'user_id');
    }

    /** One line, for pickers and summaries. */
    public function getSummaryAttribute(): string
    {
        $parts = array_filter([
            trim($this->first_name . ' ' . $this->last_name),
            $this->address_1,
            $this->address_2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        return implode(', ', $parts);
    }

    /**
     * The address as checkout form values, e.g. ['billing_city' => 'Dhaka', …].
     *
     * @return array<string, string>
     */
    public function toCheckoutFields(string $section = 'billing'): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            $out[$section . '_' . $field] = (string) ($this->{$field} ?? '');
        }

        // Shipping has no email field on the checkout form; sending one would be a dead key.
        if ($section === 'shipping') {
            unset($out['shipping_email'], $out['shipping_phone']);
        }

        return $out;
    }
}
