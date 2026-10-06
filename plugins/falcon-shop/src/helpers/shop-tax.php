<?php

/**
 * Shop plugin helpers (shop-tax): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-tax.php.
 */
if (!function_exists('falcon_tax_country')) {
    /**
     * The address tax is worked out against — Shop → Tax → Calculate Tax Based On.
     *
     * 'billing' falls back to the shipping country before checkout, because the billing address
     * simply isn't known while the customer is still on the cart page.
     */
    function falcon_tax_country(): ?string
    {
        switch ((string) get_shop_option('shop_tax_calculation_basis', 'shipping')) {
            case 'base':
                $base = (string) get_shop_option('shop_country_state', '');

                return $base !== '' ? $base : null;

            case 'billing':
                $billing = session()->get('falcon_billing_country');
                if (is_string($billing) && $billing !== '') {
                    return $billing;
                }
                // fall through
            default:
                return falcon_customer_shipping_country();
        }
    }
}

if (!function_exists('falcon_cart_taxable_subtotal')) {
    /**
     * The part of the cart subtotal that is actually subject to tax.
     * Only 'taxable' products count — 'shipping' items are taxed via the shipping line
     * (if the rate covers shipping) and 'none' items are exempt outright.
     */
    function falcon_cart_taxable_subtotal(): float
    {
        $total = 0.0;

        foreach (session()->get('falcon_cart', []) as $item) {
            if (falcon_product_tax_status($item['id'] ?? 0) !== 'taxable') {
                continue;
            }
            $price = $item['sale_price'] ?? $item['price'];
            $total += (float) $price * (int) ($item['quantity'] ?? 0);
        }

        return $total;
    }
}

if (!function_exists('get_falcon_cart_tax')) {
    /**
     * Tax due on the current cart.
     *
     * Exclusive pricing: tax sits on top of the taxable base.
     * Inclusive pricing: the base already contains it, so the tax is the portion extracted out
     * of that figure — adding it again would charge the customer twice.
     *
     * Coupons shrink the taxable base in proportion to how much of the cart is taxable, so a
     * discount never removes more (or less) tax than the goods it actually applies to.
     */
    function get_falcon_cart_tax()
    {
        if (!falcon_tax_enabled()) {
            return 0.0;
        }

        $rate = falcon_tax_rate_for(falcon_tax_country());
        if (!$rate || $rate['rate'] <= 0) {
            return 0.0;
        }

        $taxableBase = falcon_cart_taxable_subtotal();
        $subtotal = get_falcon_cart_subtotal();
        // Promotions reduce what is actually paid, so they reduce the taxable base alongside coupons.
        $discount = falcon_cart_discount_total() + falcon_cart_promotion_total();

        if ($subtotal > 0 && $discount > 0 && $taxableBase > 0) {
            $taxableBase = max(0.0, $taxableBase - ($discount * ($taxableBase / $subtotal)));
        }

        if ($rate['shipping']) {
            $taxableBase += (float) get_falcon_cart_shipping(falcon_customer_shipping_country());
        }

        if ($taxableBase <= 0) {
            return 0.0;
        }

        $fraction = $rate['rate'] / 100;

        return falcon_prices_include_tax()
            ? $taxableBase - ($taxableBase / (1 + $fraction))
            : $taxableBase * $fraction;
    }
}

if (!function_exists('falcon_cart_tax_label')) {
    /** The name to show next to the tax line ("VAT", "GST", …). */
    function falcon_cart_tax_label(): string
    {
        return falcon_tax_rate_for(falcon_tax_country())['name'] ?? 'Tax';
    }
}
