<?php

/**
 * Shop: tax settings, rates and cart tax.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\ProductData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('falcon_tax_enabled')) {
    /** Shop → Tax → Enable Tax. Everything else in the tax engine is a no-op while this is off. */
    function falcon_tax_enabled(): bool
    {
        return get_shop_option('shop_calc_taxes') === '1';
    }
}

if (!function_exists('falcon_prices_include_tax')) {
    /** True when catalogue prices already contain tax, so tax is extracted rather than added. */
    function falcon_prices_include_tax(): bool
    {
        return get_shop_option('shop_tax_price_entry') === 'inclusive';
    }
}

if (!function_exists('falcon_display_prices_including_tax')) {
    /** Shop → Tax → Display prices in shop. Presentation only; never changes what is charged. */
    function falcon_display_prices_including_tax(): bool
    {
        return falcon_tax_enabled() && get_shop_option('shop_tax_display_shop', 'exclusive') === 'inclusive';
    }
}

if (!function_exists('falcon_tax_rate_for')) {
    /**
     * The tax rate that applies to a country, or null if none does.
     *
     * Matching runs most-specific first: an exact row ("Bangladesh - Dhaka"), then the country
     * without its region suffix, then the "*" catch-all. That way a store can set one national
     * rate and override single regions without listing every region.
     *
     * @return array{rate: float, name: string, shipping: bool}|null
     */
    function falcon_tax_rate_for(?string $country): ?array
    {
        if (!falcon_tax_enabled()) {
            return null;
        }

        $rates = get_shop_option('shop_tax_rates', []);
        if (!is_array($rates) || empty($rates)) {
            return null;
        }

        $normalise = static fn (string $v): string => strtolower(trim(str_replace(['—', '–'], '-', $v)));

        $country = $country !== null ? $normalise($country) : '';
        // "Bangladesh - Dhaka" → also try plain "Bangladesh".
        $countryOnly = $country !== '' ? trim(explode(' - ', $country)[0]) : '';

        $exact = $parent = $wildcard = null;

        foreach ($rates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowCountry = $normalise((string) ($row['country'] ?? ''));

            if ($rowCountry === '*') {
                $wildcard = $wildcard ?? $row;
            } elseif ($country !== '' && $rowCountry === $country) {
                $exact = $exact ?? $row;
            } elseif ($countryOnly !== '' && $rowCountry === $countryOnly) {
                $parent = $parent ?? $row;
            }
        }

        $match = $exact ?? $parent ?? $wildcard;
        if (!$match) {
            return null;
        }

        return [
            'rate' => (float) ($match['rate'] ?? 0),
            'name' => trim((string) ($match['name'] ?? '')) ?: 'Tax',
            'shipping' => (string) ($match['shipping'] ?? '0') === '1',
        ];
    }
}

if (!function_exists('falcon_product_tax_status')) {
    /**
     * A product's tax status ('taxable' | 'shipping' | 'none'), defaulting to taxable.
     * Results are memoised per request — the cart asks for the same handful of ids repeatedly.
     *
     * The memo lives on the container rather than in a `static`. A static outlives the
     * request in any long-running worker (Octane, a queue process), so it would keep
     * serving a tax status the shop owner has since changed, and would carry one test's
     * fixtures into the next. Bound to the application instance, it dies with it.
     */
    function falcon_product_tax_status($postId): string
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return 'taxable';
        }

        $cache = falcon_request_memo('product_tax_statuses');

        if ($cache->offsetExists($postId)) {
            return $cache[$postId];
        }

        $status = 'taxable';
        try {
            if (Schema::hasColumn('shop_products', 'tax_status')) {
                $found = DB::table('shop_products')
                    ->where('post_id', $postId)
                    ->value('tax_status');
                if (in_array($found, ProductData::TAX_STATUSES, true)) {
                    $status = $found;
                }
            }
        } catch (Throwable $e) {
            // Leave the default; a lookup failure must never block a checkout.
        }

        $cache[$postId] = $status;

        return $status;
    }
}
