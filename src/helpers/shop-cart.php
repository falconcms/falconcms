<?php

/**
 * Shop: cart counts, totals, prices and weight.
 *
 * Loaded by src/helpers.php.
 */
if (!function_exists('get_falcon_cart_count')) {
    function get_falcon_cart_count()
    {
        $cart = session()->get('falcon_cart', []);
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['quantity'] ?? 0;
        }

        return $total;
    }
}

if (!function_exists('falcon_display_price')) {
    /**
     * Adjust a catalogue price for how the shop displays tax.
     *
     * Only ever converts between the two presentations of the same price; the amount actually
     * charged is settled by get_falcon_cart_tax() at checkout, never here.
     */
    function falcon_display_price($price, $postId = null): float
    {
        $price = (float) $price;

        if (!falcon_tax_enabled() || $price <= 0) {
            return $price;
        }

        if ($postId !== null && falcon_product_tax_status($postId) !== 'taxable') {
            return $price;
        }

        // Deliberately the shop's own country, not the visitor's.
        //
        // Catalogue pages are shared and cacheable — PageCacheMiddleware keys purely on the URL —
        // so a rate taken from one visitor's session would be baked into the page everyone else
        // is then served. A fixed base rate keeps every shopper looking at the same figure. What
        // is actually charged is still worked out from the customer's real address at checkout,
        // where the tax line spells the difference out.
        $baseCountry = (string) get_shop_option('shop_country_state', '');
        $rate = falcon_tax_rate_for($baseCountry !== '' ? $baseCountry : null);
        if (!$rate || $rate['rate'] <= 0) {
            return $price;
        }

        $fraction = $rate['rate'] / 100;
        $entryIncl = falcon_prices_include_tax();
        $showIncl = falcon_display_prices_including_tax();

        if ($entryIncl === $showIncl) {
            return $price; // Stored and displayed the same way — nothing to convert.
        }

        return $showIncl ? $price * (1 + $fraction) : $price / (1 + $fraction);
    }
}
