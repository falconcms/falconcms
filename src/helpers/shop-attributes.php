<?php

/**
 * Shop: product attributes and the attribute index used by filters.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\ProductData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (!function_exists('falcon_attribute_slug')) {
    /**
     * URL-safe key for an attribute name or value.
     *
     * Str::slug() transliterates what it can (Bengali "নীল" becomes "neel") but returns an empty
     * string for scripts it has no map for — Chinese, emoji, punctuation-only values. Falling
     * back to the lower-cased original keeps those distinct; the slug only has to match itself.
     */
    function falcon_attribute_slug(string $text): string
    {
        $slug = Str::slug($text);

        return $slug !== '' ? $slug : mb_strtolower(trim($text));
    }
}

if (!function_exists('falcon_product_attribute_definitions')) {
    /**
     * The attributes a product declares, normalised out of shop_products.attributes_data.
     *
     * The stored shape is `[{name, values: "Red | Green | Blue", visible: "1", variation: "1"}]`,
     * written by the admin's Attributes tab.
     *
     * @return array<int, array{name: string, values: array<int, string>, visible: bool, variation: bool, filterable: bool}>
     */
    function falcon_product_attribute_definitions($shopData): array
    {
        $raw = $shopData->attributes_data ?? null;

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = is_scalar($entry['name'] ?? null) ? trim((string) $entry['name']) : '';
            if ($name === '') {
                continue;
            }

            $values = [];
            $rawValues = $entry['values'] ?? '';
            foreach (is_array($rawValues) ? $rawValues : explode('|', (string) $rawValues) as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = trim((string) $value);
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            $out[] = [
                'name' => $name,
                'values' => array_values(array_unique($values)),
                'visible' => (string) ($entry['visible'] ?? '') === '1',
                'variation' => (string) ($entry['variation'] ?? '') === '1',
                // Attributes saved before this option existed carry no key, and a shop owner who
                // adds an attribute expects to filter by it — so absent means yes.
                'filterable' => !array_key_exists('filterable', $entry) || (string) $entry['filterable'] === '1',
            ];
        }

        return $out;
    }
}

if (!function_exists('falcon_sync_product_attribute_index')) {
    /**
     * Rebuild one product's rows in the attribute index.
     *
     * Called on every product save. The index is derived data, so this replaces it wholesale
     * rather than patching it — a removed attribute or a renamed value cannot linger.
     */
    function falcon_sync_product_attribute_index($shopData): void
    {
        if (!$shopData || !Schema::hasTable('shop_product_attribute_values')) {
            return;
        }

        $postId = (int) ($shopData->post_id ?? 0);
        if ($postId <= 0) {
            return;
        }

        try {
            $rows = [];
            $seen = ['names' => [], 'values' => []];
            $isVariable = $shopData->isVariable();

            // Claims a slug, appending -2, -3 … when something already took it.
            $unique = static function (string $slug, array &$taken): string {
                if ($slug === '') {
                    return '';
                }
                $candidate = $slug;
                $n = 1;
                while (isset($taken[$candidate])) {
                    $candidate = $slug.'-'.(++$n);
                }
                $taken[$candidate] = true;

                return $candidate;
            };

            // What the variations actually offer, keyed by attribute name.
            $fromVariations = [];
            if ($isVariable) {
                foreach ($shopData->variations()->get(['attributes_data']) as $variation) {
                    $attrs = $variation->attributes_data;
                    if (is_string($attrs)) {
                        $attrs = json_decode($attrs, true);
                    }
                    if (!is_array($attrs)) {
                        continue;
                    }
                    foreach ($attrs as $name => $value) {
                        if (is_scalar($value) && trim((string) $value) !== '') {
                            $fromVariations[trim((string) $name)][] = trim((string) $value);
                        }
                    }
                }
            }

            foreach (falcon_product_attribute_definitions($shopData) as $attribute) {
                if (!$attribute['filterable']) {
                    continue;
                }

                // For a variable product the variations are the honest answer: the parent may
                // still list a colour nobody built a variation for, and filtering to it would
                // surface a product the shopper cannot actually buy. Fall back to the declared
                // list while no variations exist yet.
                $values = $attribute['values'];
                if ($isVariable && $attribute['variation'] && !empty($fromVariations[$attribute['name']])) {
                    $values = array_values(array_unique($fromVariations[$attribute['name']]));
                }
                if (empty($values)) {
                    continue;
                }

                $nameSlug = $unique(falcon_attribute_slug($attribute['name']), $seen['names']);
                if ($nameSlug === '') {
                    continue;
                }
                $seen['values'][$nameSlug] = $seen['values'][$nameSlug] ?? [];
                $sameValue = [];

                foreach ($values as $value) {
                    // "Blue", "blue" and " Blue " are one value — collapse them before slugging so
                    // they do not consume a disambiguation suffix.
                    $normalised = mb_strtolower(trim($value));
                    if ($normalised === '' || isset($sameValue[$normalised])) {
                        continue;
                    }
                    $sameValue[$normalised] = true;

                    // Genuinely different values can still slug the same ("XL" and "XL+" both
                    // reduce to "xl"). Suffixing keeps both filterable instead of silently
                    // dropping whichever came second.
                    $valueSlug = $unique(falcon_attribute_slug($value), $seen['values'][$nameSlug]);
                    if ($valueSlug === '') {
                        continue;
                    }

                    $rows[] = [
                        'post_id' => $postId,
                        'name' => mb_substr($attribute['name'], 0, 60),
                        'name_slug' => mb_substr($nameSlug, 0, 60),
                        'value' => mb_substr($value, 0, 120),
                        'value_slug' => mb_substr($valueSlug, 0, 120),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::transaction(function () use ($postId, $rows) {
                DB::table('shop_product_attribute_values')
                    ->where('post_id', $postId)->delete();

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('shop_product_attribute_values')->insert($chunk);
                }
            });
        } catch (Throwable $e) {
            // A broken index must never stop a shop owner from saving a product.
            Log::error('Attribute index sync failed for post '.$postId.': '.$e->getMessage());
        }
    }
}

if (!function_exists('falcon_reindex_all_product_attributes')) {
    /**
     * Rebuild the whole attribute index. Used by the install migration and by
     * `php artisan falcon:reindex-attributes`.
     *
     * @return int products processed
     */
    function falcon_reindex_all_product_attributes(): int
    {
        if (!Schema::hasTable('shop_product_attribute_values')
            || !Schema::hasTable('shop_products')) {
            return 0;
        }

        // Products whose post is gone (or whose foreign key was never created) leave orphans.
        DB::table('shop_product_attribute_values')
            ->whereNotIn('post_id', DB::table('posts')->select('id'))
            ->delete();

        $count = 0;
        ProductData::query()
            ->whereNotNull('attributes_data')
            ->chunkById(100, function ($chunk) use (&$count) {
                foreach ($chunk as $shopData) {
                    falcon_sync_product_attribute_index($shopData);
                    $count++;
                }
            });

        return $count;
    }
}
