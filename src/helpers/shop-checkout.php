<?php

/**
 * Shop: checkout fields, customer addresses and payment gateways.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\CustomerAddress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

if (!function_exists('falcon_customer_addresses')) {
    /**
     * The signed-in customer's saved addresses, newest default first.
     *
     * Returns an empty collection for guests and whenever the table is not there yet, so callers
     * never have to guard for either.
     */
    function falcon_customer_addresses()
    {
        if (!auth()->check() || !Schema::hasTable('shop_customer_addresses')) {
            return collect();
        }

        try {
            return CustomerAddress::where('user_id', auth()->id())
                ->orderByDesc('is_default_billing')
                ->orderByDesc('is_default_shipping')
                ->orderBy('id')
                ->get();
        } catch (Throwable $e) {
            Log::error('Customer address lookup failed: '.$e->getMessage());

            return collect();
        }
    }
}

if (!function_exists('falcon_default_customer_address')) {
    /**
     * The address to pre-fill a checkout section with: the one flagged default for that section,
     * otherwise the first saved one, otherwise null.
     */
    function falcon_default_customer_address(string $section = 'billing')
    {
        $addresses = falcon_customer_addresses();
        if ($addresses->isEmpty()) {
            return null;
        }

        $column = $section === 'shipping' ? 'is_default_shipping' : 'is_default_billing';

        return $addresses->firstWhere($column, true) ?: $addresses->first();
    }
}

if (!function_exists('falcon_get_checkout_fields')) {
    /**
     * Returns the sorted field array for 'billing' or 'shipping'.
     * Applies the falcon_billing_fields / falcon_shipping_fields filter so developers
     * can add, remove, or reorder fields from functions.php.
     *
     * Field keys:
     *   name        string   Input name attribute (required)
     *   type        string   text|email|tel|number|password|date|select|country|textarea|checkbox|hidden
     *   label       string|null  Label text; null = no label rendered
     *   required    bool     Adds required rule in placeOrder validation
     *   width       string   'half' = one column, 'full' = spans both columns (default full)
     *   priority    int      Sort order (lower = earlier, default 10)
     *   default     mixed    Default value if old() is empty
     *   placeholder string   Input placeholder
     *   options     array    key=>label pairs for select type
     *   rows        int      Rows for textarea type
     *   rules       string   Custom Laravel validation rule (overrides 'required' default)
     *   class       string   Extra CSS classes on the field wrapper div
     */
    function falcon_get_checkout_fields(string $section): array
    {
        static $defaults = null;

        if ($defaults === null) {
            $user = auth()->user();
            $defaults = [
                'billing' => [
                    ['name' => 'billing_first_name', 'type' => 'text',    'label' => 'First name',       'required' => true,  'width' => 'half', 'priority' => 10,  'default' => $user->first_name ?? ''],
                    ['name' => 'billing_last_name',  'type' => 'text',    'label' => 'Last name',         'required' => true,  'width' => 'half', 'priority' => 20,  'default' => $user->last_name ?? ''],
                    ['name' => 'billing_country',    'type' => 'country', 'label' => 'Country / Region',  'required' => true,  'width' => 'full', 'priority' => 30,  'default' => falcon_customer_shipping_country() ?? ''],
                    ['name' => 'billing_address_1',  'type' => 'text',    'label' => 'Street address',    'required' => true,  'width' => 'full', 'priority' => 40,  'placeholder' => 'House number and street name'],
                    ['name' => 'billing_address_2',  'type' => 'text',    'label' => null,                'required' => false, 'width' => 'full', 'priority' => 50,  'placeholder' => 'Apartment, suite, unit, etc. (optional)'],
                    ['name' => 'billing_city',       'type' => 'text',    'label' => 'Town / City',       'required' => true,  'width' => 'half', 'priority' => 60],
                    ['name' => 'billing_state',      'type' => 'text',    'label' => 'State / Province',  'required' => true,  'width' => 'half', 'priority' => 70],
                    ['name' => 'billing_postcode',   'type' => 'text',    'label' => 'ZIP Code',          'required' => true,  'width' => 'half', 'priority' => 80],
                    ['name' => 'billing_phone',      'type' => 'tel',     'label' => 'Phone',             'required' => true,  'width' => 'half', 'priority' => 90],
                    ['name' => 'billing_email',      'type' => 'email',   'label' => 'Email address',     'required' => true,  'width' => 'full', 'priority' => 100, 'default' => $user->email ?? ''],
                ],
                'shipping' => [
                    ['name' => 'shipping_first_name', 'type' => 'text',    'label' => 'First name',       'required' => true,  'width' => 'half', 'priority' => 10],
                    ['name' => 'shipping_last_name',  'type' => 'text',    'label' => 'Last name',         'required' => true,  'width' => 'half', 'priority' => 20],
                    ['name' => 'shipping_country',    'type' => 'country', 'label' => 'Country / Region',  'required' => true,  'width' => 'full', 'priority' => 30,  'default' => falcon_customer_shipping_country() ?? ''],
                    ['name' => 'shipping_address_1',  'type' => 'text',    'label' => 'Street address',    'required' => true,  'width' => 'full', 'priority' => 40,  'placeholder' => 'House number and street name'],
                    ['name' => 'shipping_address_2',  'type' => 'text',    'label' => null,                'required' => false, 'width' => 'full', 'priority' => 50,  'placeholder' => 'Apartment, suite, unit, etc. (optional)'],
                    ['name' => 'shipping_city',       'type' => 'text',    'label' => 'Town / City',       'required' => true,  'width' => 'half', 'priority' => 60],
                    ['name' => 'shipping_state',      'type' => 'text',    'label' => 'State / Province',  'required' => true,  'width' => 'half', 'priority' => 70],
                    ['name' => 'shipping_postcode',   'type' => 'text',    'label' => 'ZIP Code',          'required' => true,  'width' => 'half', 'priority' => 80],
                ],
            ];
        }

        $fields = $defaults[$section] ?? [];

        // Fill in from the customer's saved address before the theme filter runs, so a site that
        // adds its own fields still sees the values. Done here rather than in the template so it
        // works with JavaScript switched off, and so `old()` input still wins on a failed submit.
        $saved = falcon_default_customer_address($section);
        if ($saved) {
            foreach ($fields as $i => $field) {
                $key = $field['name'] ?? '';
                if (!str_starts_with($key, $section.'_')) {
                    continue;
                }
                $column = substr($key, strlen($section) + 1);
                $value = in_array($column, CustomerAddress::FIELDS, true)
                    ? trim((string) ($saved->{$column} ?? ''))
                    : '';

                if ($value !== '') {
                    $fields[$i]['default'] = $value;
                }
            }
        }

        $fields = apply_falcon_filters("falcon_{$section}_fields", $fields);
        usort($fields, fn ($a, $b) => ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10));

        return $fields;
    }
}
