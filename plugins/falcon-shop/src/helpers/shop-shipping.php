<?php

/**
 * Shop plugin helpers (shop-shipping): the storefront-only part, loaded while the plugin is on.
 * The data-layer helpers the CMS itself needs stay in src/helpers/shop-shipping.php.
 */
if (!function_exists('falcon_shipping_destination')) {
    /**
     * Which address an order is fulfilled to — Shop → Shipping → Default Address Type.
     *
     *  'shipping'      the separate shipping address is the target; its fields are shown up front
     *  'billing'       billing is the target, with a separate shipping address as an opt-in
     *  'force_billing' orders always ship to billing; shipping fields are not offered at all
     *
     * Anything unrecognised (an option edited by hand, say) falls back to 'shipping', which is
     * the most permissive and therefore never blocks a checkout.
     */
    function falcon_shipping_destination(): string
    {
        $value = (string) get_shop_option('shop_shipping_destination', 'shipping');

        return in_array($value, ['shipping', 'billing', 'force_billing'], true) ? $value : 'shipping';
    }
}

if (!function_exists('falcon_allows_separate_shipping_address')) {
    /**
     * May this store accept a shipping address different from billing?
     *
     * Checked on the server for every order, not just used to render the form: under
     * 'force_billing' the goods must go to the address the payment was authorised against,
     * so a hand-crafted POST carrying shipping_* fields has to be ignored rather than trusted.
     */
    function falcon_allows_separate_shipping_address(): bool
    {
        return falcon_shipping_destination() !== 'force_billing';
    }
}

if (!function_exists('falcon_cart_has_free_shipping_coupon')) {
    /**
     * Is a "Free Shipping" coupon currently applied?
     *
     * The Discount Type dropdown has always offered this option, but nothing acted on it —
     * such a coupon took no money off and left shipping fully charged. Shipping costs are
     * resolved server-side, so this is checked where the cost is calculated, not in the view.
     */
    function falcon_cart_has_free_shipping_coupon(): bool
    {
        foreach (session()->get('falcon_coupons', []) as $coupon) {
            if (($coupon['type'] ?? '') === 'free_shipping') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('falcon_shipping_methods')) {
    /**
     * The shipping methods a customer can actually pick for this cart and country.
     *
     * Always keyed by method id, always at least 'delivery'. 'pickup' only appears when Local
     * Pickup is switched on in Shop → Shipping, which is what makes that setting mean something.
     *
     * @return array<string, array{id: string, label: string, cost: float}>
     */
    function falcon_shipping_methods($country = null): array
    {
        $country = $country ?? falcon_customer_shipping_country();
        $delivery = falcon_delivery_shipping_details($country);

        $methods = [
            'delivery' => [
                'id' => 'delivery',
                'label' => $delivery['label'],
                'cost' => (float) $delivery['cost'],
            ],
        ];

        if (get_shop_option('shop_local_pickup_enable') === '1') {
            $methods['pickup'] = [
                'id' => 'pickup',
                'label' => 'Local pickup',
                'cost' => 0.0,
            ];
        }

        // A Free Shipping coupon zeroes every method rather than discounting the cart, so the
        // saving lands on the shipping line where the customer expects to see it.
        if (falcon_cart_has_free_shipping_coupon()) {
            foreach ($methods as $id => $method) {
                $methods[$id]['cost'] = 0.0;
            }
        }

        // "Auto-hide paid shipping options when free delivery is applicable" — once something
        // is free, charging for the alternative is just a way to lose the sale. Only ever
        // removes paid options, so the list can never end up empty.
        if (get_shop_option('shop_calc_hide_paid_when_free') === '1') {
            $free = array_filter($methods, static fn (array $m): bool => $m['cost'] <= 0);
            if (!empty($free)) {
                $methods = $free;
            }
        }

        return $methods;
    }
}

if (!function_exists('falcon_selected_shipping_method')) {
    /**
     * Resolve the customer's chosen shipping method against what is genuinely on offer.
     *
     * The session only ever holds a method *id*; the cost is recalculated here on every call.
     * That is deliberate — a stored or posted price could be tampered with, a re-derived one
     * cannot. An id that is no longer available (pickup switched off mid-session, say) falls
     * back to the first method rather than erroring.
     *
     * @return array{id: string, label: string, cost: float}
     */
    function falcon_selected_shipping_method($country = null): array
    {
        $country = $country ?? falcon_customer_shipping_country();
        $methods = falcon_shipping_methods($country);
        $selected = session()->get('falcon_shipping_method');

        if (is_string($selected) && isset($methods[$selected])) {
            return $methods[$selected];
        }

        return reset($methods);
    }
}

if (!function_exists('get_falcon_cart_shipping_details')) {
    /**
     * Cost + label for the shipping the customer will actually be charged.
     * Delegates to the selected method, so Local Pickup zeroes shipping everywhere at once —
     * cart totals, checkout totals, and the order row written at checkout.
     */
    function get_falcon_cart_shipping_details($country = null)
    {
        $country = $country ?? falcon_customer_shipping_country();

        // Nothing in the basket, nothing to deliver. Without this the flat rate is quoted
        // against an empty cart, so a mini-cart or a "you are ৳X from free delivery" banner
        // reads the shipping charge as the whole total. Checkout itself is guarded separately,
        // so this was never chargeable — just wrong on screen.
        if (empty(session()->get('falcon_cart', []))) {
            return [
                'cost' => 0.0,
                'label' => 'Calculated at checkout',
                'method' => 'delivery',
                'pending' => true,
            ];
        }

        // "Only display shipping fees after a valid address is provided" — with no destination
        // yet there is nothing honest to quote, so nothing is charged either and the cart total
        // matches what the customer is shown. Checkout always has a country (billing_country is
        // required), so a real order is never priced from this branch.
        if (!falcon_shipping_is_calculable($country)) {
            return [
                'cost' => 0.0,
                'label' => 'Calculated at checkout',
                'method' => 'delivery',
                'pending' => true,
            ];
        }

        $method = falcon_selected_shipping_method($country);

        return ['cost' => $method['cost'], 'label' => $method['label'], 'method' => $method['id'], 'pending' => false];
    }
}

if (!function_exists('falcon_shipping_is_calculable')) {
    /** False only while "hide fees until an address is provided" is on and no country is known. */
    function falcon_shipping_is_calculable($country = null): bool
    {
        if (get_shop_option('shop_calc_hide_until_address') !== '1') {
            return true;
        }

        $country = $country ?? falcon_customer_shipping_country();

        return is_string($country) && $country !== '';
    }
}

if (!function_exists('falcon_delivery_shipping_details')) {
    /** Zone / flat-rate delivery cost — the calculation that existed before pickup was a choice. */
    function falcon_delivery_shipping_details($country = null)
    {
        $subtotal = get_falcon_cart_subtotal();
        $cart = session()->get('falcon_cart', []);
        $itemCount = 0;
        foreach ($cart as $item) {
            $itemCount += ($item['quantity'] ?? 0);
        }

        // 1. Check Global Free Shipping Threshold
        $globalFreeThreshold = (float) get_shop_option('shop_free_shipping_threshold', 0);
        if ($globalFreeThreshold > 0 && $subtotal >= $globalFreeThreshold) {
            return ['cost' => 0, 'label' => 'Free shipping'];
        }

        // 2. Advanced Shipping Zones
        $zones = get_shop_option('shop_shipping_zones', []);

        // Find matching zone if country is provided
        $matchedZone = null;
        if ($country) {
            $normalizedCountry = str_replace('—', '-', $country);
            foreach ($zones as $zone) {
                $zoneCountries = (array) ($zone['countries'] ?? []);
                $normalizedZoneCountries = array_map(fn ($c) => str_replace('—', '-', $c), $zoneCountries);

                if (in_array($normalizedCountry, $normalizedZoneCountries)) {
                    $matchedZone = $zone;
                    break;
                }

                if (strpos($normalizedCountry, ' - ') !== false) {
                    $parts = explode(' - ', $normalizedCountry);
                    $parentCountry = trim($parts[0]);
                    if (in_array($parentCountry, $normalizedZoneCountries)) {
                        $matchedZone = $zone;
                        break;
                    }
                }
            }
        }

        if ($matchedZone) {
            $zoneName = $matchedZone['name'] ?? 'Shipping';

            // Check zone-specific free shipping
            $zoneFreeThreshold = (float) ($matchedZone['free_threshold'] ?? 0);
            if ($zoneFreeThreshold > 0 && $subtotal >= $zoneFreeThreshold) {
                return ['cost' => 0, 'label' => 'Free shipping ('.$zoneName.')'];
            }

            $baseCost = (float) ($matchedZone['cost'] ?? 0);
            $type = $matchedZone['type'] ?? 'order';

            // Banded rates. 'item' bands on how many things are in the cart, 'weight' on how
            // heavy they are — the same rule rows, just measured differently.
            if (in_array($type, ['item', 'weight'], true) && !empty($matchedZone['rules'])) {
                $measure = $type === 'weight' ? falcon_cart_weight() : $itemCount;

                $ruleCost = 0;
                $matchedRule = false;
                foreach ($matchedZone['rules'] as $rule) {
                    // An incomplete or mistyped row must not become free shipping: skip it and
                    // let the zone's base cost apply. A deliberate 0 is numeric, so it survives.
                    if (!is_array($rule)
                        || !isset($rule['cost']) || !is_numeric($rule['cost'])
                        || (isset($rule['min']) && $rule['min'] !== '' && !is_numeric($rule['min']))
                        || (isset($rule['max']) && $rule['max'] !== '' && $rule['max'] !== null && !is_numeric($rule['max']))) {
                        continue;
                    }
                    // Weights are fractional (0.5 kg), item counts are not. Casting a weight
                    // band to int would quietly turn "up to 0.5" into "up to 0".
                    $min = (float) ($rule['min'] ?? 0);
                    $max = (($rule['max'] ?? '') === '' || ($rule['max'] ?? null) === null)
                        ? INF
                        : (float) $rule['max'];

                    if ($measure >= $min && $measure <= $max) {
                        $ruleCost = (float) ($rule['cost'] ?? 0);
                        $matchedRule = true;
                        break;
                    }
                }

                return [
                    'cost' => $matchedRule ? $ruleCost : $baseCost,
                    'label' => $zoneName,
                ];
            }

            return ['cost' => $baseCost, 'label' => $zoneName];
        }

        // 3. Fallback to Global Flat Rate
        return [
            'cost' => (float) get_shop_option('shop_flat_rate_cost', 0),
            'label' => 'Flat rate',
        ];
    }
}
