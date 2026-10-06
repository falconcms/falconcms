<?php

/**
 * Shop: shop/cart/checkout URLs, price format, product schema and product type.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\EcommerceData;
use Illuminate\Support\Str;

if (!function_exists('get_falcon_shop_url')) {
    function get_falcon_shop_url()
    {
        $pageId = get_shop_option('shop_shop_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            if ($page) {
                return get_falcon_permalink($page);
            }
        }

        return url('/product');
    }
}

if (!function_exists('get_falcon_checkout_url')) {
    function get_falcon_checkout_url()
    {
        $pageId = get_shop_option('shop_checkout_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            if ($page) {
                return get_falcon_permalink($page);
            }
        }

        return route('shop.checkout');
    }
}

if (!function_exists('falcon_price_format')) {
    function falcon_price_format($price, $order = null)
    {
        if ($order && is_object($order) && isset($order->currency_symbol)) {
            $symbol = $order->currency_symbol;
            $position = $order->currency_position ?? 'left';
            $decimals = (int) ($order->decimals ?? 2);
            $thousandSep = $order->thousand_separator ?? ',';
            $decimalSep = $order->decimal_separator ?? '.';
        } else {
            $currencyCode = get_shop_option('shop_currency', 'USD');
            $symbol = EcommerceData::getCurrencySymbol($currencyCode);

            $position = get_shop_option('shop_currency_pos', 'left');
            $decimals = (int) get_shop_option('shop_num_decimals', 2);
            $thousandSep = get_shop_option('shop_thousand_sep', ',');
            $decimalSep = get_shop_option('shop_decimal_sep', '.');
        }

        $formatted = number_format((float) $price, $decimals, $decimalSep, $thousandSep);

        switch ($position) {
            case 'left':
                return $symbol.$formatted;
            case 'right':
                return $formatted.$symbol;
            case 'left_space':
                return $symbol.' '.$formatted;
            case 'right_space':
                return $formatted.' '.$symbol;
            default:
                return $symbol.$formatted;
        }
    }
}

if (!function_exists('falcon_product_schema')) {
    /**
     * schema.org Product markup for a single product page.
     *
     * This is what puts the price, the availability and the star rating into a Google result
     * instead of a bare blue link. Everything comes from the same helpers the page itself uses,
     * so the structured data cannot advertise a price the shop will not honour — Google treats
     * that as a violation, not a rounding error.
     *
     * @return array<string, mixed>|null null when the post is not a sellable product
     */
    function falcon_product_schema($post): ?array
    {
        $shopData = $post->shopData ?? null;
        if (!$shopData || ($post->type ?? null) !== 'product') {
            return null;
        }

        $currency = get_shop_option('shop_currency', 'USD');
        $url = get_falcon_permalink($post);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $post->title,
            'url' => $url,
        ];

        $description = trim(strip_tags((string) ($shopData->short_description ?: $post->excerpt ?: $post->content)));
        if ($description !== '') {
            $schema['description'] = Str::limit($description, 300, '');
        }

        if (!empty($post->featured_image)) {
            $schema['image'] = str_starts_with($post->featured_image, 'http')
                ? $post->featured_image
                : asset('storage/'.$post->featured_image);
        }

        if (!empty($shopData->sku)) {
            $schema['sku'] = (string) $shopData->sku;
        }

        // A "Brand" attribute is the only place a brand is recorded, so it is the only honest source.
        foreach (falcon_product_attribute_definitions($shopData) as $attribute) {
            if (strcasecmp($attribute['name'], 'brand') === 0 && !empty($attribute['values'])) {
                $schema['brand'] = ['@type' => 'Brand', 'name' => (string) $attribute['values'][0]];
                break;
            }
        }

        $availability = $post->is_in_stock
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        if ($shopData->isVariable()) {
            // One offer per price point would be noise; an aggregate is what Google expects for
            // a product sold in several variants.
            $prices = [];
            foreach ($shopData->variations as $variation) {
                $sale = $variation->sale_price !== null ? (float) $variation->sale_price : 0.0;
                $effective = $sale > 0 ? $sale : (float) $variation->price;
                if ($effective > 0) {
                    $prices[] = round((float) falcon_display_price($effective, $post->id), 2);
                }
            }

            if (!empty($prices)) {
                $schema['offers'] = [
                    '@type' => 'AggregateOffer',
                    'priceCurrency' => $currency,
                    'lowPrice' => number_format(min($prices), 2, '.', ''),
                    'highPrice' => number_format(max($prices), 2, '.', ''),
                    'offerCount' => count($prices),
                    'availability' => $availability,
                    'url' => $url,
                ];
            }
        } else {
            $sale = $shopData->active_sale_price;
            $price = round((float) falcon_display_price($sale !== null ? $sale : (float) $shopData->price, $post->id), 2);

            if ($price > 0) {
                $offer = [
                    '@type' => 'Offer',
                    'priceCurrency' => $currency,
                    'price' => number_format($price, 2, '.', ''),
                    'availability' => $availability,
                    'url' => $url,
                ];

                // Only meaningful while a sale is actually running.
                if ($sale !== null && !empty($shopData->sale_ends_at)) {
                    $offer['priceValidUntil'] = $shopData->sale_ends_at->format('Y-m-d');
                }

                $schema['offers'] = $offer;
            }
        }

        try {
            $reviewCount = $post->reviews()->count();
            if ($reviewCount > 0) {
                $average = (float) $post->reviews()->avg('rating');
                if ($average > 0) {
                    $schema['aggregateRating'] = [
                        '@type' => 'AggregateRating',
                        'ratingValue' => round($average, 1),
                        'reviewCount' => $reviewCount,
                    ];
                }
            }
        } catch (Throwable $e) {
            // Ratings are a bonus; never let them cost the page its markup.
        }

        return apply_falcon_filters('falcon_product_schema', $schema, $post);
    }
}

if (!function_exists('falcon_is_variable_product')) {
    /**
     * Whether a product is a variable product. ProductData::isVariable() holds the rule —
     * the table has two columns for this and only one of them is written by the admin.
     */
    function falcon_is_variable_product($product): bool
    {
        $sd = $product->shopData ?? null;

        return $sd ? $sd->isVariable() : false;
    }
}
