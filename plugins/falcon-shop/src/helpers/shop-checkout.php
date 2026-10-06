<?php

/**
 * Shop plugin helpers (shop-checkout): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-checkout.php.
 */

use FalconCms\Core\Services\EcommerceData;

// ── Checkout Field System ──────────────────────────────────────────────────────

if (!function_exists('falcon_standard_checkout_field_names')) {
    /**
     * Returns the field names that map to dedicated order columns.
     * Any field NOT in this list is treated as a custom field and saved to order meta.
     */
    function falcon_standard_checkout_field_names(): array
    {
        return [
            'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
            'billing_address_1',  'billing_address_2',  'billing_city', 'billing_state',
            'billing_postcode',   'billing_country',
            'shipping_first_name', 'shipping_last_name',
            'shipping_address_1',  'shipping_address_2',  'shipping_city', 'shipping_state',
            'shipping_postcode',   'shipping_country',
        ];
    }
}

if (!function_exists('falcon_render_checkout_field')) {
    /**
     * Renders a single checkout field inside the 2-column grid.
     * Full-width fields receive md:col-span-2; half-width get one column.
     */
    function falcon_render_checkout_field(array $field): void
    {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? null;
        $req = !empty($field['required']);
        $width = $field['width'] ?? 'full';
        $default = $field['default'] ?? '';
        $ph = $field['placeholder'] ?? '';
        $opts = $field['options'] ?? [];
        $class = $field['class'] ?? '';

        $value = old($name, $default);
        $span = $width === 'half' ? '' : 'md:col-span-2';
        $inp = 'w-full border border-[#ddd] rounded-sm px-3 py-2 text-[14px] focus:border-primary outline-none';

        if ($type === 'hidden') {
            echo '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';

            return;
        }

        echo '<div class="space-y-1.5 '.$span.($class ? ' '.e($class) : '').'">';

        if ($label !== null && $label !== '') {
            echo '<label class="text-[14px] font-bold text-heading">'
               .e($label)
               .($req ? ' <span class="text-red-600">*</span>' : '')
               .'</label>';
        }

        if ($type === 'country') {
            $countries = EcommerceData::getCountriesWithStates();
            echo '<select name="'.e($name).'" class="'.$inp.' bg-white cursor-pointer">';
            foreach ($countries as $code => $cname) {
                echo '<option value="'.e($code).'"'.($value == $code ? ' selected' : '').'>'.e($cname).'</option>';
            }
            echo '</select>';
        } elseif ($type === 'select') {
            echo '<select name="'.e($name).'" class="'.$inp.' bg-white">';
            if ($ph) {
                echo '<option value="">'.e($ph).'</option>';
            }
            foreach ($opts as $k => $v) {
                echo '<option value="'.e($k).'"'.($value == $k ? ' selected' : '').'>'.e($v).'</option>';
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            $rows = (int) ($field['rows'] ?? 3);
            echo '<textarea name="'.e($name).'" rows="'.$rows.'" placeholder="'.e($ph).'" class="'.$inp.' resize-none">'.e($value).'</textarea>';
        } elseif ($type === 'checkbox') {
            echo '<label class="flex items-center gap-2 cursor-pointer text-[14px] text-body">'
               .'<input type="checkbox" name="'.e($name).'" value="1" class="w-4 h-4 border-[#ddd] rounded text-primary focus:ring-0"'.($value ? ' checked' : '').'>'
               .($label !== null ? e($label) : '')
               .'</label>';
        } else {
            echo '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" placeholder="'.e($ph).'" class="'.$inp.'">';
        }

        echo '</div>';
    }
}

if (!function_exists('falcon_render_checkout_fields')) {
    /**
     * Renders all checkout fields inside a responsive 2-column grid.
     * Call with the output of falcon_get_checkout_fields().
     */
    function falcon_render_checkout_fields(array $fields): void
    {
        if (empty($fields)) {
            return;
        }
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
        foreach ($fields as $field) {
            falcon_render_checkout_field($field);
        }
        echo '</div>';
    }
}

if (!function_exists('falcon_enabled_payment_gateways')) {
    /**
     * Enabled storefront payment gateways (single source of truth for checkout + order processing).
     * Returns: [ id => ['id','title','desc','type' => offline|online] ]
     */
    function falcon_enabled_payment_gateways(): array
    {
        $gateways = [];

        if (get_shop_option('shop_payment_cod_enable') === '1') {
            $gateways['cod'] = [
                'id' => 'cod',
                'title' => get_shop_option('shop_payment_cod_title', 'Cash on Delivery'),
                'desc' => get_shop_option('shop_payment_cod_desc', 'Pay with cash upon delivery.'),
                'type' => 'offline',
            ];
        }
        if (get_shop_option('shop_payment_bank_enable') === '1') {
            $gateways['bank'] = [
                'id' => 'bank',
                'title' => get_shop_option('shop_payment_bank_title', 'Direct Bank Transfer'),
                'desc' => get_shop_option('shop_payment_bank_details', 'Make your payment directly into our bank account.'),
                'type' => 'offline',
            ];
        }
        if (get_shop_option('shop_payment_stripe_enable') === '1' && get_shop_option('shop_payment_stripe_secret')) {
            $gateways['stripe'] = [
                'id' => 'stripe',
                'title' => get_shop_option('shop_payment_stripe_title', 'Credit / Debit Card'),
                'desc' => get_shop_option('shop_payment_stripe_desc', 'Pay securely with your card via Stripe.'),
                'type' => 'online',
            ];
        }
        if (get_shop_option('shop_payment_paypal_enable') === '1' && get_shop_option('shop_payment_paypal_email')) {
            $gateways['paypal'] = [
                'id' => 'paypal',
                'title' => get_shop_option('shop_payment_paypal_title', 'PayPal'),
                'desc' => get_shop_option('shop_payment_paypal_desc', 'Pay via PayPal; you can pay with your card if you don’t have an account.'),
                'type' => 'online',
            ];
        }
        if (get_shop_option('shop_payment_sslcommerz_enable') === '1'
            && get_shop_option('shop_payment_sslcommerz_store_id')
            && get_shop_option('shop_payment_sslcommerz_store_pass')) {
            $gateways['sslcommerz'] = [
                'id' => 'sslcommerz',
                'title' => get_shop_option('shop_payment_sslcommerz_title', 'SSLCommerz'),
                'desc' => get_shop_option('shop_payment_sslcommerz_desc', 'Pay with cards, mobile banking (bKash, Nagad) and net banking via SSLCommerz.'),
                'type' => 'online',
            ];
        }

        return $gateways;
    }
}
