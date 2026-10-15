<?php

/**
 * Shop: shop/cart/checkout URLs, price format, product schema, product type and the
 * `falcon_product_prices` filter every storefront price goes through.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\ProductData;
use FalconCms\Core\Models\ProductVariation;
use FalconCms\Core\Services\EcommerceData;
use Illuminate\Support\Str;

if (!function_exists('get_falcon_shop_url')) {
    function get_falcon_shop_url()
    {
        $pageId = get_shop_option('shop_shop_page_id');
        if ($pageId) {
            $page = Post::find($pageId);
            // Only a live page: a draft or deleted one would send the visitor to a 404.
            if ($page && $page->status === 'published') {
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
            // Only a live page: a draft or deleted one would send the visitor to a 404.
            if ($page && $page->status === 'published') {
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

        // A price the visitor may not see must not be published to search engines either.
        if (!falcon_can_see_prices($post)) {
            unset($schema['offers']);
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

if (!function_exists('falcon_filter_product_prices')) {
    /**
     * Run a catalogue price pair through the `falcon_product_prices` filter.
     *
     * The one door every price the shopper sees or pays goes through: product cards, the
     * single product page, variation prices, the cart and therefore the order. A wholesale or
     * role-pricing plugin hooks here once and the figure on the page and the figure charged
     * cannot disagree.
     *
     * Whatever a callback returns is normalised: a negative or non-numeric price becomes 0 and
     * a sale price of zero or less becomes null, because the cart treats a present sale price
     * as authoritative and a literal 0 would hand the item over for free.
     *
     * @param  string  $context  'display' (a page showing the price) or 'cart' (the cart line
     *                           being priced, which is what the order is written from)
     * @return array{price: float, sale_price: float|null}
     */
    function falcon_filter_product_prices($price, $salePrice, int $postId, ?int $variationId = null, int $quantity = 1, string $context = 'display'): array
    {
        $prices = [
            'price' => (float) $price,
            'sale_price' => ($salePrice !== null && $salePrice !== '' && (float) $salePrice > 0) ? (float) $salePrice : null,
        ];

        $filtered = apply_falcon_filters('falcon_product_prices', $prices, $postId, $variationId, max(1, $quantity), $context);
        if (!is_array($filtered)) {
            return $prices;
        }

        $outPrice = $filtered['price'] ?? $prices['price'];
        $outSale = array_key_exists('sale_price', $filtered) ? $filtered['sale_price'] : $prices['sale_price'];

        return [
            'price' => is_numeric($outPrice) ? max(0.0, round((float) $outPrice, 4)) : 0.0,
            'sale_price' => (is_numeric($outSale) && (float) $outSale > 0) ? round((float) $outSale, 4) : null,
        ];
    }
}

if (!function_exists('falcon_product_prices')) {
    /**
     * What the current shopper pays for a product (or one of its variations), after the
     * `falcon_product_prices` filter.
     *
     * A variation's sale ends with its parent's sale_ends_at, the same rule the cart applies.
     *
     * @param  mixed  $product  a Post/Product, its ProductData row, or a post id
     * @param  mixed  $variation  a ProductVariation, a variation id, or null
     * @return array{price: float, sale_price: float|null}
     */
    function falcon_product_prices($product, $variation = null, int $quantity = 1, string $context = 'display'): array
    {
        if ($product instanceof ProductData) {
            $shopData = $product;
        } elseif (is_object($product)) {
            $shopData = $product->shopData ?? null;
        } else {
            $shopData = ProductData::where('post_id', (int) $product)->first();
        }

        if (!$shopData) {
            return ['price' => 0.0, 'sale_price' => null];
        }

        if ($variation !== null && !is_object($variation)) {
            $variation = ProductVariation::find((int) $variation);
        }

        if ($variation) {
            $sale = $variation->sale_price;
            if (!empty($shopData->sale_ends_at) && !$shopData->sale_ends_at->isFuture()) {
                $sale = null;
            }

            return falcon_filter_product_prices($variation->price, $sale, (int) $shopData->post_id, (int) $variation->id, $quantity, $context);
        }

        return falcon_filter_product_prices($shopData->price, $shopData->active_sale_price, (int) $shopData->post_id, null, $quantity, $context);
    }
}

if (!function_exists('falcon_show_sale_badge')) {
    /**
     * Whether the "Sale!" badge goes on a product (or one of its variations).
     *
     * A struck-through price is not always a sale: a plugin's customer price (a wholesale
     * price) arrives as a sale price too, so the price reads "was / now" correctly. The
     * `falcon_product_sale_badge` filter lets that plugin say the badge does not belong.
     *
     * @param  bool  $hasSale  whether the price shown has a sale price
     */
    function falcon_show_sale_badge(bool $hasSale, int $postId, ?int $variationId = null): bool
    {
        return (bool) apply_falcon_filters('falcon_product_sale_badge', $hasSale, $postId, $variationId);
    }
}

if (!function_exists('falcon_can_see_prices')) {
    /**
     * Whether the current visitor may see this product's price and buy it.
     *
     * Always true in the core. A plugin hides prices (from guests, from retail customers, for a
     * B2B-only shop) through the `falcon_show_prices` filter. Templates then print
     * falcon_hidden_price_html() in place of the price and the buy button, adding to the cart
     * is refused, and the product's structured data leaves the price out.
     *
     * @param  mixed  $product  a Post/Product, or null for "prices in general"
     */
    function falcon_can_see_prices($product = null): bool
    {
        return (bool) apply_falcon_filters('falcon_show_prices', true, $product);
    }
}

if (!function_exists('falcon_hidden_price_html')) {
    /**
     * What stands in for a hidden price ($context 'price') or a hidden buy button ('cart'),
     * from the `falcon_hidden_price_html` filter. Empty in the core.
     */
    function falcon_hidden_price_html($product = null, string $context = 'price'): string
    {
        return (string) apply_falcon_filters('falcon_hidden_price_html', '', $product, $context);
    }
}
