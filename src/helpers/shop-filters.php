<?php

/**
 * Shop: product filters, filter options and sorting on archive pages.
 *
 * Loaded by src/helpers.php.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

if (!function_exists('falcon_product_filters_active')) {
    /**
     * The archive filters currently in the URL, already validated.
     *
     * Everything is read through this one function so the query, the sidebar and the "active
     * filters" chips can never disagree about what is being filtered.
     *
     * @return array{search: string, min_price: ?float, max_price: ?float, categories: array<int, string>, attributes: array<string, array<int, string>>, in_stock: bool, on_sale: bool}
     */
    function falcon_product_filters_active(): array
    {
        $request = request();

        $price = static function ($value): ?float {
            // Arrays and objects arrive whenever someone hand-edits the query string.
            if (!is_scalar($value) || $value === '' || !is_numeric($value)) {
                return null;
            }
            $value = (float) $value;

            // INF/NAN (e.g. `1e400`) would poison every comparison downstream.
            return is_finite($value) ? max(0.0, $value) : null;
        };

        // Slugs only — they are matched against a column, never interpolated into SQL.
        // Non-scalars (`product_cat[][]=x`) are dropped rather than stringified, so a nested
        // array in the URL cannot reach the view and blow up htmlspecialchars().
        $categories = array_values(array_filter(array_map(
            static fn ($slug) => is_string($slug) ? trim($slug) : '',
            array_filter((array) $request->query('product_cat', []), 'is_scalar')
        )));

        // Attribute filters arrive as `?attr[color][]=blue&attr[size][]=xl`. Both halves are
        // slugs matched against indexed columns, never interpolated anywhere.
        $attributes = [];
        foreach ((array) $request->query('attr', []) as $name => $values) {
            // Bounded on purpose: a hand-written URL with thousands of keys would otherwise turn
            // into thousands of subqueries.
            if (count($attributes) >= 12) {
                break;
            }
            if (!is_string($name)) {
                continue;
            }
            $name = trim($name);
            if ($name === '' || mb_strlen($name) > 60) {
                continue;
            }

            $clean = [];
            foreach (array_filter((array) $values, 'is_scalar') as $value) {
                if (count($clean) >= 60) {
                    break;
                }
                $value = trim((string) $value);
                if ($value !== '' && mb_strlen($value) <= 120) {
                    $clean[] = $value;
                }
            }
            if ($clean) {
                $attributes[$name] = array_values(array_unique($clean));
            }
        }

        // Free-text search. Capped so a pathological term cannot drive an expensive LIKE.
        $search = $request->query('s');
        $search = is_scalar($search) ? trim((string) $search) : '';
        $search = $search !== '' ? mb_substr($search, 0, 120) : '';

        $min = $price($request->query('min_price'));
        $max = $price($request->query('max_price'));

        // A reversed range would silently match nothing; swapping is what the shopper meant.
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'search' => $search,
            'min_price' => $min,
            'max_price' => $max,
            'categories' => $categories,
            'attributes' => $attributes,
            'in_stock' => $request->query('in_stock') === '1',
            'on_sale' => $request->query('on_sale') === '1',
        ];
    }
}

if (!function_exists('falcon_apply_product_filters')) {
    /**
     * Narrow a product query by the archive filters.
     *
     * Deliberately uses whereHas subqueries rather than joins: the sorting code already joins
     * shop_products for price ordering, and a second join on the same table would break the
     * query. Subqueries compose with anything.
     */
    function falcon_apply_product_filters($query, ?array $filters = null)
    {
        $filters = $filters ?? falcon_product_filters_active();

        if (($filters['search'] ?? '') !== '') {
            // `%` and `_` are LIKE wildcards. Left unescaped, a search for "100%" would match
            // everything, and "_" would match any single character — neither is what was typed.
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';

            $query->where(function ($q) use ($term) {
                $q->where('posts.title', 'like', $term)
                    ->orWhere('posts.excerpt', 'like', $term)
                    // SKU matters more than prose in a shop: staff and repeat buyers search by it.
                    ->orWhereHas('shopData', fn ($sd) => $sd->where('sku', 'like', $term))
                    ->orWhereHas('shopData.variations', fn ($v) => $v->where('sku', 'like', $term));
            });
        }

        // Effective price = sale price when there is one, otherwise the regular price.
        //
        // Variable products keep their prices on the variations, not on the parent row — the
        // admin hides the price fields for them — so a parent-only check would drop every
        // variable product from any price filter. Both bounds are applied to the *same* row so
        // "1000-2000" means one variation actually costs that, not that the range merely
        // overlaps the product's spread.
        if ($filters['min_price'] !== null || $filters['max_price'] !== null) {
            $priceBounds = static function ($q) use ($filters) {
                $expr = 'COALESCE(NULLIF(sale_price, 0), price)';

                // The bound value is CAST because PDO sends floats as strings, and the left
                // side here is an expression, which carries no column affinity to coerce them
                // back. MySQL compares them as numbers anyway; SQLite sorts every integer
                // before every string, so `500 >= '200'` came out false and the price filter
                // returned an empty page on every SQLite site.
                $bound = 'CAST(? AS DECIMAL(10,2))';

                if ($filters['min_price'] !== null) {
                    $q->whereRaw($expr.' >= '.$bound, [$filters['min_price']]);
                }
                if ($filters['max_price'] !== null) {
                    $q->whereRaw($expr.' <= '.$bound, [$filters['max_price']]);
                }
            };

            $query->where(function ($outer) use ($priceBounds) {
                $outer->whereHas('shopData', function ($q) use ($priceBounds) {
                    // Excluded so a stale parent price left over from when the product was
                    // simple cannot match on a variable product's behalf.
                    $q->notVariable();
                    $priceBounds($q);
                })
                    ->orWhereHas('shopData', function ($q) use ($priceBounds) {
                        // Gated on the parent type: switching a product back to simple leaves its
                        // old variation rows in place, and those must not match any more.
                        $q->variable()->whereHas('variations', $priceBounds);
                    });
            });
        }

        if (!empty($filters['categories'])) {
            $query->whereHas('productCategories', fn ($q) => $q->whereIn('product_categories.slug', $filters['categories']));
        }

        // Values within one attribute are OR'd, separate attributes are AND'd — picking Red and
        // Blue widens the results, adding a size narrows them. That is what shoppers expect from
        // a layered filter, and each attribute needs its own subquery to express it.
        foreach ($filters['attributes'] ?? [] as $nameSlug => $valueSlugs) {
            $query->whereHas(
                'attributeValues',
                fn ($q) => $q->where('name_slug', $nameSlug)->whereIn('value_slug', $valueSlugs)
            );
        }

        if ($filters['on_sale']) {
            // A variable product is on sale when any one of its variations is.
            $onSale = static fn ($q) => $q->whereNotNull('sale_price')->where('sale_price', '>', 0);

            // Variations carry no end date, but the parent does — and an expired sale must drop
            // out of this filter the moment it ends, not whenever falcon:expire-sales next runs.
            $liveSale = static fn ($q) => $q->whereNotNull('sale_price')
                ->where('sale_price', '>', 0)
                ->where(fn ($w) => $w->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now()));

            $query->where(function ($outer) use ($onSale, $liveSale) {
                $outer->whereHas('shopData', function ($q) use ($liveSale) {
                    $q->notVariable();
                    $liveSale($q);
                })
                    ->orWhereHas('shopData', function ($q) use ($onSale) {
                        $q->variable()->whereHas('variations', $onSale);
                    });
            });
        }

        if ($filters['in_stock']) {
            $threshold = (int) get_shop_option('shop_out_of_stock_threshold', '0');
            $globalManage = get_shop_option('shop_manage_stock', '1') === '1';

            // Mirrors ProductData::isInStock() in SQL. A product with no shop row is treated as
            // available, exactly as the accessor does.

            // The parent's own shelf: what a simple product — and a variation that does not track
            // its own stock — is sold from.
            $parentShelf = static function ($q) use ($threshold, $globalManage) {
                if ($globalManage) {
                    $q->where(fn ($inner) => $inner
                        ->where('manage_stock', 0)
                        ->orWhere('stock_quantity', '>', $threshold)
                        ->orWhereIn('backorders', ['notify', 'yes']));
                }
            };

            $query->where(function ($outer) use ($threshold, $globalManage, $parentShelf) {
                $outer->whereDoesntHave('shopData')
                    ->orWhereHas('shopData', function ($q) use ($threshold, $globalManage, $parentShelf) {
                        $q->where('stock_status', '!=', 'outofstock')
                            ->where(function ($w) use ($threshold, $globalManage, $parentShelf) {
                                // Simple products, and variable ones with no variations built yet.
                                $w->where(function ($simple) use ($parentShelf) {
                                    $simple->where(fn ($t) => $t->notVariable()
                                        ->orWhereDoesntHave('variations'));
                                    $parentShelf($simple);
                                })
                                // A variable product is only as available as its variations.
                                    ->orWhere(function ($variable) use ($threshold, $globalManage, $parentShelf) {
                                        $variable->variable()
                                            ->where(function ($any) use ($threshold, $globalManage, $parentShelf) {
                                                // Backorders are set on the parent, so once they are on,
                                                // any variation still on the shelf can be sold.
                                                $any->where(function ($bo) {
                                                    $bo->whereIn('backorders', ['notify', 'yes'])
                                                        ->whereHas('variations', fn ($v) => $v->where('stock_status', '!=', 'outofstock'));
                                                })
                                                    // A variation holding its own stock.
                                                    ->orWhereHas('variations', function ($v) use ($threshold, $globalManage) {
                                                        $v->where('stock_status', '!=', 'outofstock');
                                                        if ($globalManage) {
                                                            $v->where('manage_stock', 1)->where('stock_quantity', '>', $threshold);
                                                        }
                                                    })
                                                    // A variation that inherits the parent's shelf.
                                                    ->orWhere(function ($inherit) use ($parentShelf) {
                                                        $inherit->whereHas('variations', fn ($v) => $v->where('stock_status', '!=', 'outofstock')
                                                            ->where('manage_stock', 0));
                                                        $parentShelf($inherit);
                                                    });
                                            });
                                    });
                            });
                    });
            });
        }

        return $query;
    }
}

if (!function_exists('falcon_apply_product_sorting')) {
    /**
     * Order a product query. Extracted so the shop page and the taxonomy archives cannot drift
     * apart — they each had their own copy of this switch.
     */
    function falcon_apply_product_sorting($query, ?string $orderby = null)
    {
        $orderby = $orderby ?? request('orderby', 'latest');

        switch ($orderby) {
            case 'price':
            case 'price-desc':
                $direction = $orderby === 'price' ? 'ASC' : 'DESC';

                // Sort a variable product by the cheapest variation when going low-to-high and
                // by the dearest when going high-to-low — matching the range a shopper sees.
                // Without the subquery every variable product sorts as NULL and clumps at one end.
                $agg = $orderby === 'price' ? 'MIN' : 'MAX';
                $expr = 'COALESCE('
                    .'(SELECT '.$agg.'(COALESCE(NULLIF(v.sale_price, 0), v.price))'
                    .' FROM shop_product_variations v WHERE v.product_id = shop_products.id'
                    ." AND (shop_products.type = 'variable' OR shop_products.product_type = 'variable')),"
                    .' COALESCE(NULLIF(shop_products.sale_price, 0), shop_products.price))';

                $query->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                    ->orderByRaw($expr.' '.$direction)
                    ->select('posts.*');
                break;

            case 'rating':
                $query->withCount(['reviews as average_rating' => fn ($q) => $q->select(DB::raw('avg(rating)'))])
                    ->orderBy('average_rating', 'desc');
                break;

            case 'popularity':
                $query->withCount('reviews')->orderBy('reviews_count', 'desc');
                break;

            case 'latest':
            default:
                $query->latest();
                break;
        }

        return $query;
    }
}

if (!function_exists('falcon_product_filter_options')) {
    /**
     * Data the filter sidebar needs: the categories on offer with their counts, and the price
     * range of the products being browsed.
     *
     * Counts come from the unfiltered set so a category never vanishes the moment it is
     * deselected — a filter panel that erases its own options is unusable.
     *
     * @param  callable  $baseQuery  returns a fresh, unfiltered query for this archive
     */
    function falcon_product_filter_options(callable $baseQuery): array
    {
        try {
            $ids = $baseQuery()->pluck('posts.id');

            $categories = DB::table('product_categories')
                ->join('product_category_post', 'product_category_post.product_category_id', '=', 'product_categories.id')
                ->whereIn('product_category_post.post_id', $ids)
                ->groupBy('product_categories.id', 'product_categories.name', 'product_categories.slug')
                ->orderBy('product_categories.name')
                ->get([
                    'product_categories.name',
                    'product_categories.slug',
                    DB::raw('COUNT(DISTINCT product_category_post.post_id) as total'),
                ]);

            // The band has to cover variation prices too, or the placeholders would understate
            // the real range on any shop that sells variable products.
            $bounds = DB::table('shop_products')
                ->leftJoin('shop_product_variations as v', function ($j) {
                    $j->on('v.product_id', '=', 'shop_products.id')
                        ->where(fn ($w) => $w->where('shop_products.type', '=', 'variable')
                            ->orWhere('shop_products.product_type', '=', 'variable'));
                })
                ->whereIn('shop_products.post_id', $ids)
                ->selectRaw(
                    'MIN(COALESCE(NULLIF(v.sale_price, 0), v.price, NULLIF(shop_products.sale_price, 0), shop_products.price)) as min_price,'
                    .' MAX(COALESCE(NULLIF(v.sale_price, 0), v.price, NULLIF(shop_products.sale_price, 0), shop_products.price)) as max_price'
                )
                ->first();

            // Whatever attributes this set of products happens to declare, grouped for the
            // sidebar. Nothing here is hard-coded, so a brand new attribute shows up by itself.
            $attributes = [];
            if (Schema::hasTable('shop_product_attribute_values')) {
                $rows = DB::table('shop_product_attribute_values')
                    ->whereIn('post_id', $ids)
                    ->groupBy('name', 'name_slug', 'value', 'value_slug')
                    ->orderBy('name')
                    ->orderBy('value')
                    ->get([
                        'name', 'name_slug', 'value', 'value_slug',
                        DB::raw('COUNT(DISTINCT post_id) as total'),
                    ]);

                foreach ($rows as $row) {
                    if (!isset($attributes[$row->name_slug])) {
                        $attributes[$row->name_slug] = [
                            'name' => $row->name,
                            'slug' => $row->name_slug,
                            'values' => [],
                        ];
                    }
                    $attributes[$row->name_slug]['values'][] = [
                        'label' => $row->value,
                        'slug' => $row->value_slug,
                        'total' => (int) $row->total,
                    ];
                }
            }

            return [
                'categories' => $categories,
                'attributes' => array_values($attributes),
                'min_price' => $bounds && $bounds->min_price !== null ? (float) $bounds->min_price : 0.0,
                'max_price' => $bounds && $bounds->max_price !== null ? (float) $bounds->max_price : 0.0,
            ];
        } catch (Throwable $e) {
            Log::error('Product filter options failed: '.$e->getMessage());

            return ['categories' => collect(), 'attributes' => [], 'min_price' => 0.0, 'max_price' => 0.0];
        }
    }
}
