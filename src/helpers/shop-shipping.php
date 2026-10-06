<?php

/**
 * Shop: customer country, shipping methods, carriers and delivery.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Services\EcommerceData;

if (!function_exists('falcon_default_customer_country')) {
    /**
     * The country to assume for a customer who has not given an address yet —
     * Shop → General → Default customer location.
     *
     *  'none'       assume nothing (shipping falls back to the flat rate)
     *  'base'       the shop's own country
     *  'geolocate'  the visitor's country, from their IP
     *
     * Returns a value from the shop's own country list (so it lines up with the checkout
     * dropdowns and with shipping zones), or null when there is nothing sensible to assume.
     */
    function falcon_default_customer_country(): ?string
    {
        $mode = (string) get_shop_option('shop_default_customer_location', 'none');

        if ($mode === 'base') {
            $base = (string) get_shop_option('shop_country_state', '');

            return $base !== '' ? $base : null;
        }

        if ($mode !== 'geolocate' || !function_exists('falcon_geoip')) {
            return null;
        }

        $iso2 = falcon_geoip(request()->ip())['country_code'] ?? null;
        if (!$iso2) {
            return null;
        }

        // Map the ISO code onto the shop's *sellable* country list. Matching through
        // countryToIso2() rather than by string keeps suffixed names like
        // "United States (US)" working, and a country the shop does not sell to
        // simply finds no match — better than pre-filling a checkout that would be rejected.
        foreach (EcommerceData::getCountriesWithStates(true) as $value => $label) {
            $candidate = is_string($value) ? $value : $label;
            if (EcommerceData::countryToIso2($candidate) === strtoupper($iso2)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('falcon_customer_shipping_country')) {
    /**
     * The country totals should be calculated against right now: whatever the customer has
     * chosen, otherwise the store's default-location assumption.
     *
     * The resolved default is cached in the session because 'geolocate' costs an outbound
     * lookup; an explicit choice always overwrites the same session key, so a customer's own
     * selection can never be undone by this.
     */
    function falcon_customer_shipping_country(): ?string
    {
        $chosen = session()->get('falcon_shipping_country');
        if (is_string($chosen) && $chosen !== '') {
            return $chosen;
        }

        if (session()->has('falcon_default_country_resolved')) {
            return session()->get('falcon_default_country_resolved') ?: null;
        }

        $default = falcon_default_customer_country();
        session()->put('falcon_default_country_resolved', $default ?? '');

        return $default;
    }
}

if (!function_exists('falcon_shipping_carriers')) {
    /**
     * Shipping carriers for order tracking, grouped (Local / International).
     * Each value is a tracking-URL template with a {tracking} placeholder ('' = use universal fallback).
     */
    function falcon_shipping_carriers(): array
    {
        return [
            'Local (Bangladesh)' => [
                'Pathao' => 'https://merchant.pathao.com/tracking?consignment_id={tracking}',
                'Steadfast' => 'https://steadfast.com.bd/track/{tracking}',
                'RedX' => 'https://redx.com.bd/track-parcel/?trackingId={tracking}',
                'Paperfly' => '',
                'eCourier' => 'https://ecourier.com.bd/track?tracking_id={tracking}',
                'Sundarban Courier' => '',
                'SA Paribahan' => '',
                'Pickaboo' => '',
                'Delivery Tiger' => '',
            ],
            'International' => [
                'DHL' => 'https://www.dhl.com/track?tracking-id={tracking}',
                'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
                'UPS' => 'https://www.ups.com/track?tracknum={tracking}',
                'USPS' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking}',
                'Aramex' => 'https://www.aramex.com/track/results?ShipmentNumber={tracking}',
                'DPD' => 'https://www.dpd.com/tracking/{tracking}',
                'TNT' => 'https://www.tnt.com/express/en_us/site/tracking.html?searchType=con&cons={tracking}',
                'China Post' => 'https://www.17track.net/en/track?nums={tracking}',
                'India Post' => 'https://www.17track.net/en/track?nums={tracking}',
                'Other' => 'https://www.17track.net/en/track?nums={tracking}',
            ],
        ];
    }
}
